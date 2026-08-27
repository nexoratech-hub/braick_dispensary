<?php
// ================================================================
// FILE: frontend/pages/pharmacy/pending_prescriptions.php
// PHARMACY - PRESCRIPTIONS (Pending, Confirmed, Dispensed)
// SIMPLIFIED VIEW - ONE ROW PER PATIENT
// TOTAL QUANTITY = SUM OF ALL ITEMS
// TOTAL BILL = FROM BILLS TABLE
// DARK MODE SUPPORT - Via Header Button
// USING NEW DATABASE: dispensary_db
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT PHARMACY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$user_username = $_SESSION['username'] ?? 'pharmacy';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
    
    // ================================================================
    // ✅ AUTO-DISPENSE
    // ================================================================
    $auto_dispensed_count = 0;
    try {
        $stmt = $db->prepare("
            SELECT 
                p.id as prescription_id,
                p.patient_id,
                p.visit_id,
                p.prescription_number,
                b.id as bill_id,
                b.bill_number
            FROM prescriptions p
            JOIN bills b ON b.visit_id = p.visit_id AND b.patient_id = p.patient_id
            WHERE p.branch_id = ? 
            AND p.status = 'confirmed'
            AND b.status = 'paid'
            AND p.id NOT IN (
                SELECT pi.prescription_id 
                FROM prescription_items pi 
                WHERE pi.prescription_id = p.id AND pi.dispensed_at IS NOT NULL
            )
            GROUP BY p.id
        ");
        $stmt->execute([$user_branch_id]);
        $to_dispense = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($to_dispense as $item) {
            try {
                $db->beginTransaction();
                
                $stmt_items = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
                $stmt_items->execute([$item['prescription_id']]);
                $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($items)) {
                    $db->rollBack();
                    continue;
                }
                
                // Check stock
                $stock_errors = [];
                foreach ($items as $pres_item) {
                    $stmt_stock = $db->prepare("
                        SELECT SUM(quantity) as total_available 
                        FROM medications_inventory 
                        WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                    ");
                    $stmt_stock->execute([$pres_item['medication_name'], $user_branch_id]);
                    $stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
                    $available = $stock['total_available'] ?? 0;
                    
                    if ($available < $pres_item['quantity']) {
                        $stock_errors[] = "{$pres_item['medication_name']} - Required: {$pres_item['quantity']}, Available: {$available}";
                    }
                }
                
                if (!empty($stock_errors)) {
                    $db->rollBack();
                    error_log("Auto-dispense stock error: " . implode(', ', $stock_errors));
                    continue;
                }
                
                // Update prescription status to dispensed
                $stmt = $db->prepare("
                    UPDATE prescriptions 
                    SET status = 'dispensed', 
                        dispensed_at = NOW(), 
                        updated_at = NOW(),
                        pharmacy_id = ?
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$user_id, $item['prescription_id'], $user_branch_id]);
                
                // Update prescription items
                $stmt = $db->prepare("
                    UPDATE prescription_items 
                    SET dispensed_at = NOW(),
                        dispensed_by = ?
                    WHERE prescription_id = ?
                ");
                $stmt->execute([$user_id, $item['prescription_id']]);
                
                // Update inventory
                foreach ($items as $pres_item) {
                    $needed = $pres_item['quantity'];
                    
                    $stmt_batches = $db->prepare("
                        SELECT id, medication_name, quantity, batch_number, expiry_date
                        FROM medications_inventory 
                        WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                        ORDER BY expiry_date ASC
                    ");
                    $stmt_batches->execute([$pres_item['medication_name'], $user_branch_id]);
                    $batches = $stmt_batches->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($batches as $batch) {
                        if ($needed <= 0) break;
                        
                        $deduct = min($needed, $batch['quantity']);
                        $new_qty = $batch['quantity'] - $deduct;
                        
                        $stmt_update = $db->prepare("
                            UPDATE medications_inventory 
                            SET quantity = ?,
                                updated_at = NOW()
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt_update->execute([$new_qty, $batch['id'], $user_branch_id]);
                        
                        $stmt_log = $db->prepare("
                            INSERT INTO stock_movements (
                                inventory_id, patient_id, movement_type, quantity,
                                previous_stock, new_stock, reference_type, reference_id,
                                performed_by, branch_id, notes, created_at
                            ) VALUES (?, ?, 'out', ?, ?, ?, 'prescription', ?, ?, ?, ?, NOW())
                        ");
                        $stmt_log->execute([
                            $batch['id'], $item['patient_id'], $deduct,
                            $batch['quantity'], $new_qty,
                            $item['prescription_id'], $user_id, $user_branch_id,
                            "Auto-dispensed from batch {$batch['batch_number']} - Prescription #{$item['prescription_number']}"
                        ]);
                        
                        $needed -= $deduct;
                    }
                }
                
                // Update bill
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET status = 'paid',
                        paid_amount = total_amount,
                        balance = 0,
                        updated_at = NOW()
                    WHERE id = ? AND visit_id = ?
                ");
                $stmt->execute([$item['bill_id'], $item['visit_id']]);
                
                $db->commit();
                $auto_dispensed_count++;
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Auto-dispense error: " . $e->getMessage());
            }
        }
        
        if ($auto_dispensed_count > 0) {
            $message = "✅ " . $auto_dispensed_count . " prescription(s) auto-dispensed!";
            $message_type = 'success';
        }
        
    } catch (Exception $e) {
        error_log("Auto-dispense check error: " . $e->getMessage());
    }
    
    // ================================================================
    // ✅ HANDLE CONFIRM ACTION
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_prescription') {
        $prescription_id = isset($_POST['prescription_id']) ? (int)$_POST['prescription_id'] : 0;
        
        if ($prescription_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("
                    SELECT p.*, pat.id as patient_id, pat.full_name as patient_name, 
                           pat.patient_id as patient_code, v.id as visit_id
                    FROM prescriptions p
                    JOIN patients pat ON p.patient_id = pat.id
                    LEFT JOIN visits v ON p.visit_id = v.id
                    WHERE p.id = ? AND p.branch_id = ? AND p.status = 'pending'
                ");
                $stmt->execute([$prescription_id, $user_branch_id]);
                $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($prescription) {
                    $stmt_items = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
                    $stmt_items->execute([$prescription_id]);
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($items)) {
                        throw new Exception("No items found in this prescription");
                    }
                    
                    $total_amount = 0;
                    foreach ($items as $item) {
                        $stmt_price = $db->prepare("
                            SELECT selling_price FROM medications_inventory 
                            WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                            ORDER BY created_at DESC LIMIT 1
                        ");
                        $stmt_price->execute([$item['medication_name'], $user_branch_id]);
                        $price_result = $stmt_price->fetch(PDO::FETCH_ASSOC);
                        $unit_price = $price_result['selling_price'] ?? 0;
                        
                        $item_total = $unit_price * $item['quantity'];
                        $total_amount += $item_total;
                        
                        $stmt_update = $db->prepare("
                            UPDATE prescription_items 
                            SET unit_price = ?, total_price = ?
                            WHERE id = ? AND prescription_id = ?
                        ");
                        $stmt_update->execute([$unit_price, $item_total, $item['id'], $prescription_id]);
                    }
                    
                    // Create bill
                    $bill_number = 'BILL-PRES-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                    
                    $stmt = $db->prepare("
                        INSERT INTO bills (
                            bill_number, patient_id, visit_id, branch_id, created_by,
                            subtotal, discount_amount, discount_percent, total_amount,
                            paid_amount, balance, status, payment_method, notes,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'cash', ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $bill_number, $prescription['patient_id'], $prescription['visit_id'],
                        $user_branch_id, $user_id, $total_amount, 0, 0, $total_amount,
                        0, $total_amount, "Prescription #{$prescription['prescription_number']} - Confirmed"
                    ]);
                    $bill_id = $db->lastInsertId();
                    
                    foreach ($items as $item) {
                        $item_total = $item['unit_price'] * $item['quantity'];
                        $stmt = $db->prepare("
                            INSERT INTO bill_items (
                                bill_id, patient_id, branch_id, item_type, item_name,
                                quantity, unit_price, total_price, discount_amount,
                                tax_amount, final_price, reference_id, reference_type,
                                status, created_at, updated_at
                            ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, ?, ?, ?, ?, 'prescription', 'pending', NOW(), NOW())
                        ");
                        $stmt->execute([
                            $bill_id, $prescription['patient_id'], $user_branch_id,
                            $item['medication_name'], $item['quantity'],
                            $item['unit_price'], $item_total, 0, 0, $item_total,
                            $prescription_id
                        ]);
                    }
                    
                    // Update prescription status
                    $stmt = $db->prepare("
                        UPDATE prescriptions 
                        SET status = 'confirmed', pharmacy_id = ?, updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt->execute([$user_id, $prescription_id, $user_branch_id]);
                    
                    $db->commit();
                    
                    $_SESSION['flash_message'] = "✅ Prescription confirmed! Bill sent to Cashier. Total: " . $currency . " " . number_format($total_amount, 2);
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $db->rollBack();
                    $_SESSION['flash_message'] = "❌ Prescription not found or already processed.";
                    $_SESSION['flash_type'] = 'error';
                }
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
            }
        }
        
        $redirect_url = 'pending_prescriptions.php';
        if (!empty($_GET)) {
            $params = [];
            if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
            if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
            if (!empty($params)) {
                $redirect_url .= '?' . http_build_query($params);
            }
        }
        header('Location: ' . $redirect_url);
        exit;
    }
    
    // ================================================================
    // GET FLASH MESSAGES
    // ================================================================
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $message_type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
    
    // ================================================================
    // GET FILTER PARAMETERS
    // ================================================================
    $filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // ================================================================
    // BUILD QUERY - GROUP BY patient_id (ONE ROW PER PATIENT)
    // ================================================================
    $conditions = ["p.branch_id = ?"];
    $params = [$user_branch_id];
    
    if ($filter_status === 'all') {
        $conditions[] = "p.status IN ('pending', 'confirmed', 'dispensed')";
    } elseif ($filter_status === 'dispensed') {
        $conditions[] = "p.status = 'dispensed'";
    } else {
        $conditions[] = "p.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($search)) {
        $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $conditions);
    
    // Build params for the query
    $query_params = [$user_branch_id];
    $query_params = array_merge($query_params, $params);
    
    $sql = "
        SELECT 
            pat.id as patient_id,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone,
            pat.gender,
            pat.date_of_birth,
            p.branch_id,
            p.status,
            p.created_at,
            p.dispensed_at,
            COUNT(DISTINCT p.id) as prescription_count,
            COUNT(DISTINCT pi.id) as item_count,
            COALESCE(SUM(pi.quantity), 0) as total_quantity,
            COALESCE(SUM(pi.unit_price * pi.quantity), 0) as total_amount,
            b.id as bill_id,
            b.bill_number,
            b.total_amount as bill_total,
            b.balance as bill_balance,
            b.status as bill_status,
            GROUP_CONCAT(DISTINCT p.prescription_number SEPARATOR ', ') as prescription_numbers,
            GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') as doctor_names,
            GROUP_CONCAT(DISTINCT v.visit_number SEPARATOR ', ') as visit_numbers
        FROM patients pat
        LEFT JOIN prescriptions p ON pat.id = p.patient_id AND p.branch_id = ?
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
        LEFT JOIN bills b ON b.visit_id = p.visit_id AND b.patient_id = pat.id
        WHERE $where_clause
        GROUP BY pat.id
        ORDER BY 
            CASE 
                WHEN MAX(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) = 1 THEN 0
                WHEN MAX(CASE WHEN p.status = 'confirmed' THEN 1 ELSE 0 END) = 1 THEN 1
                WHEN MAX(CASE WHEN p.status = 'dispensed' THEN 1 ELSE 0 END) = 1 THEN 2
                ELSE 3
            END,
            MAX(p.created_at) DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($query_params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each patient, get their prescriptions and items
    foreach ($patients as &$patient) {
        $stmt_pres = $db->prepare("
            SELECT p.*, u.full_name as doctor_name, v.visit_number
            FROM prescriptions p
            LEFT JOIN users u ON p.doctor_id = u.id
            LEFT JOIN visits v ON p.visit_id = v.id
            WHERE p.patient_id = ? AND p.branch_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt_pres->execute([$patient['patient_id'], $user_branch_id]);
        $patient['prescriptions'] = $stmt_pres->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_items = $db->prepare("
            SELECT 
                pi.*,
                p.prescription_number,
                (pi.unit_price * pi.quantity) as item_total
            FROM prescription_items pi
            JOIN prescriptions p ON pi.prescription_id = p.id
            WHERE pi.patient_id = ? AND p.branch_id = ?
            ORDER BY pi.created_at DESC
        ");
        $stmt_items->execute([$patient['patient_id'], $user_branch_id]);
        $patient['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // GET STATUS COUNTS - Grouped by patient
    // ================================================================
    $status_counts = ['pending' => 0, 'confirmed' => 0, 'dispensed' => 0];
    foreach ($patients as $patient) {
        $status = $patient['status'] ?? 'pending';
        if ($status === 'pending') {
            $status_counts['pending']++;
        } elseif ($status === 'confirmed') {
            $status_counts['confirmed']++;
        } elseif ($status === 'dispensed') {
            $status_counts['dispensed']++;
        }
    }
    $total_count = $status_counts['pending'] + $status_counts['confirmed'] + $status_counts['dispensed'];
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $patients = [];
    $total_count = 0;
    $status_counts = ['pending' => 0, 'confirmed' => 0, 'dispensed' => 0];
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
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

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE PHARMACY HEADER & SIDEBAR
// ================================================================
include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - DARK MODE SUPPORT
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
            --radius: 8px;
            --radius-lg: 12px;
            --shadow: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --transition: all 0.3s ease;
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
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
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
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        [data-theme="dark"] .page-header {
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 1.4rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 5px 12px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.7rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(52, 211, 153, 0.15);
            color: #34D399;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 0.55rem;
            font-weight: 500;
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
        
        .new-db-tag {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.7);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.08);
            letter-spacing: 0.03em;
        }
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }
        
        /* ================================================================
           STATS ROW
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            border-radius: var(--radius-lg);
            padding: 12px 14px;
            border: none;
            transition: var(--transition);
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            text-decoration: none;
            display: block;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card .stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.85;
            margin-top: 1px;
        }
        
        .stat-card .stat-icon {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .stat-card.total { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.pending { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.confirmed { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.dispensed { background: linear-gradient(135deg, #059669, #047857); }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-btn.dispensed.active {
            background: var(--success);
            border-color: var(--success);
        }
        
        .filter-input {
            padding: 5px 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            flex: 1;
            min-width: 120px;
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .btn-search {
            padding: 5px 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        /* ================================================================
           TABLE - PATIENT ROW (ONE ROW PER PATIENT)
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i {
            margin-right: 6px;
            opacity: 0.8;
        }
        
        .data-table thead th:first-child { text-align: left; }
        .data-table thead th:nth-child(3) { text-align: center; }
        .data-table thead th:nth-child(4) { text-align: center; }
        .data-table thead th:nth-child(5) { text-align: center; }
        .data-table thead th:last-child { text-align: center; }
        
        .data-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
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
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.65rem;
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
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(11, 94, 215, 0.25);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-confirm {
            background: var(--primary);
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-confirm:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(11, 94, 215, 0.25);
        }
        
        .btn-view-items {
            background: var(--success);
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-view-items:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.25);
        }
        
        .btn-view-items i {
            font-size: 0.65rem;
        }
        
        .btn-dispensed {
            background: var(--success);
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.6rem;
            border: none;
            cursor: default;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-auto-dispensed {
            background: #8B5CF6;
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.6rem;
            border: none;
            cursor: default;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .action-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        
        /* ================================================================
           TABLE FOOTER
           ================================================================ */
        .table-footer {
            padding: 8px 14px;
            border-top: 1px solid var(--border-color);
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            background: var(--gray-50);
            transition: background 0.3s ease, border-color 0.3s ease;
        }
        
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .count-badge {
            background: var(--primary);
            color: white;
            padding: 1px 10px;
            border-radius: 16px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .count-badge.dispensed {
            background: var(--success);
        }
        
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
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
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
            transition: border-color 0.3s ease;
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        .footer .new-db-footer {
            color: var(--success);
            font-weight: 600;
            font-size: 0.6rem;
        }
        
        .font-mono { font-family: 'Courier New', monospace; }
        .text-center { text-align: center; }
        .vertical-middle { vertical-align: middle; }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 14px 16px; }
            .page-header .page-title { font-size: 1.1rem; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.65rem; }
            .data-table thead th, .data-table tbody td { padding: 6px 8px; }
            .btn-view-items { padding: 2px 8px; font-size: 0.55rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .stats-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Prescriptions
                <span class="role-badge-display">PHARMACY</span>
                <span class="header-badge">
                    <i class="fas fa-list"></i> <span id="totalCount"><?= $total_count ?></span> Total
                </span>
                <span class="new-db-tag">
                    <i class="fas fa-database"></i> New DB
                </span>
                <span class="live-badge">
                    <span class="live-update-indicator"></span>
                    Auto-Update <span id="liveTime" style="font-weight:400;font-size:0.5rem;"><?= date('H:i:s') ?></span>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                <span class="header-badge" style="background:rgba(251,191,36,0.15);border-color:rgba(251,191,36,0.1);font-size:0.5rem;">⏳ Pending</span>
                <span class="header-badge" style="background:rgba(96,165,250,0.15);border-color:rgba(96,165,250,0.1);font-size:0.5rem;">✅ Confirmed</span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);border-color:rgba(52,211,153,0.1);font-size:0.5rem;">💊 Dispensed</span>
                <span class="header-badge" style="background:rgba(251,191,36,0.15);border-color:rgba(251,191,36,0.1);font-size:0.5rem;">
                    <i class="fas fa-user"></i> One row per patient
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="prescription_history.php" class="btn-outline-light">
                <i class="fas fa-history"></i> History
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
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

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-row animate-fade-in-up">
        <a href="?status=all" class="stat-card total <?= $filter_status === 'all' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-number" id="statTotal"><?= $total_count ?></div>
            <div class="stat-label">📋 All</div>
        </a>
        <a href="?status=pending" class="stat-card pending <?= $filter_status === 'pending' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number" id="statPending"><?= $status_counts['pending'] ?? 0 ?></div>
            <div class="stat-label">⏳ Pending</div>
        </a>
        <a href="?status=confirmed" class="stat-card confirmed <?= $filter_status === 'confirmed' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number" id="statConfirmed"><?= $status_counts['confirmed'] ?? 0 ?></div>
            <div class="stat-label">✅ Confirmed</div>
        </a>
        <a href="?status=dispensed" class="stat-card dispensed <?= $filter_status === 'dispensed' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-prescription-bottle"></i></div>
            <div class="stat-number" id="statDispensed"><?= $status_counts['dispensed'] ?? 0 ?></div>
            <div class="stat-label">💊 Dispensed</div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up">
        <div class="filter-row">
            <a href="?status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=confirmed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn <?= $filter_status === 'confirmed' ? 'active' : '' ?>">✅ Confirmed</a>
            <a href="?status=dispensed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="filter-btn dispensed <?= $filter_status === 'dispensed' ? 'active' : '' ?>">💊 Dispensed</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:6px;" id="filterForm">
                <input type="hidden" name="status" id="filterStatus" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" id="searchInput" placeholder="Search patient..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search) || $filter_status !== 'all'): ?>
                    <a href="pending_prescriptions.php" class="btn btn-outline" style="padding:5px 10px;font-size:0.6rem;">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE - ONE ROW PER PATIENT -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" id="prescriptionsContainer">
        <div class="table-scroll">
            <table class="data-table" id="prescriptionsTable">
                <thead>
                    <tr>
                        <th style="min-width:180px;"><i class="fas fa-receipt"></i> Prescriptions</th>
                        <th style="min-width:200px;"><i class="fas fa-user"></i> Patient</th>
                        <th style="text-align:center;min-width:80px;"><i class="fas fa-cubes"></i> Total Qty</th>
                        <th style="text-align:center;min-width:120px;"><i class="fas fa-info-circle"></i> Status</th>
                        <th style="text-align:center;min-width:120px;"><i class="fas fa-money-bill"></i> Bill</th>
                        <th style="text-align:center;min-width:100px;"><i class="fas fa-cog"></i> Action</th>
                    </tr>
                </thead>
                <tbody id="prescriptionsTableBody">
                    <?php if (count($patients) > 0): ?>
                        <?php foreach ($patients as $patient): 
                            $age = calculateAge($patient['date_of_birth'] ?? '');
                            $is_paid = ($patient['bill_status'] ?? '') === 'paid';
                            $bill_exists = !empty($patient['bill_id']);
                            $status = $patient['status'] ?? 'pending';
                            $total_qty = $patient['total_quantity'] ?? 0;
                            $item_count = $patient['item_count'] ?? 0;
                            $total_amount = $patient['total_amount'] ?? 0;
                            $bill_total = $patient['bill_total'] ?? 0;
                            $prescription_numbers = $patient['prescription_numbers'] ?? '';
                            $doctor_names = $patient['doctor_names'] ?? '';
                            $visit_numbers = $patient['visit_numbers'] ?? '';
                            
                            $has_pending = false;
                            $has_confirmed = false;
                            $has_dispensed = false;
                            foreach ($patient['prescriptions'] as $pres) {
                                if ($pres['status'] === 'pending') $has_pending = true;
                                if ($pres['status'] === 'confirmed') $has_confirmed = true;
                                if ($pres['status'] === 'dispensed') $has_dispensed = true;
                            }
                            
                            if ($has_pending) {
                                $status = 'pending';
                                $status_label = '⏳ Pending';
                                $status_class = 'badge-warning';
                            } elseif ($has_confirmed) {
                                $status = 'confirmed';
                                $status_label = '✅ Confirmed';
                                $status_class = 'badge-info';
                            } elseif ($has_dispensed) {
                                $status = 'dispensed';
                                $status_label = '💊 Dispensed';
                                $status_class = 'badge-success';
                            } else {
                                $status = 'pending';
                                $status_label = '⏳ Pending';
                                $status_class = 'badge-warning';
                            }
                            
                            $all_dispensed = true;
                            foreach ($patient['prescriptions'] as $pres) {
                                if ($pres['status'] !== 'dispensed') {
                                    $all_dispensed = false;
                                    break;
                                }
                            }
                        ?>
                            <tr data-patient-id="<?= $patient['patient_id'] ?>" data-status="<?= $status ?>">
                                <td>
                                    <?php if (!empty($prescription_numbers)): ?>
                                        <div class="font-mono font-semibold" style="color:var(--primary);font-size:0.7rem;">
                                            <?= htmlspecialchars($prescription_numbers) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">No prescriptions</span>
                                    <?php endif; ?>
                                    <?php if (!empty($visit_numbers)): ?>
                                        <div class="text-xs text-gray-400">Visits: <?= htmlspecialchars($visit_numbers) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($doctor_names)): ?>
                                        <div class="text-xs text-gray-400">Dr. <?= htmlspecialchars($doctor_names) ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-400"><?= count($patient['prescriptions']) ?> prescription(s)</div>
                                </td>
                                <td>
                                    <div class="font-medium" style="font-size:0.85rem;"><?= htmlspecialchars($patient['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs" style="color:var(--text-secondary);">ID: <?= htmlspecialchars($patient['patient_code'] ?? 'N/A') ?></div>
                                    <div class="text-xs" style="color:var(--text-secondary);">
                                        <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?> • <?= $age ?> yrs
                                    </div>
                                    <?php if (!empty($patient['phone'])): ?>
                                        <div class="text-xs" style="color:var(--text-secondary);">📱 <?= htmlspecialchars($patient['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="font-bold" style="font-size:1.2rem;color:var(--primary);"><?= $total_qty ?></span>
                                    <div class="text-xs text-gray-400"><?= $item_count ?> items</div>
                                    <?php if ($total_amount > 0): ?>
                                        <div class="text-xs" style="color:var(--success);"><?= $currency ?> <?= number_format($total_amount, 0) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status <?= $status_class ?>">
                                        <?= $status_label ?>
                                    </span>
                                    <?php if ($status === 'confirmed' && !$is_paid): ?>
                                        <div class="text-xs" style="color:var(--warning);">⏳ Waiting for payment</div>
                                    <?php endif; ?>
                                    <?php if ($status === 'confirmed' && $is_paid): ?>
                                        <div class="text-xs" style="color:var(--success);">✅ Payment confirmed</div>
                                    <?php endif; ?>
                                    <?php if ($all_dispensed): ?>
                                        <div class="text-xs" style="color:var(--success);">✅ All dispensed</div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($bill_exists): ?>
                                        <?php if ($is_paid): ?>
                                            <span class="badge-status badge-success">✅ Paid</span>
                                            <div class="text-xs" style="color:var(--success);"><?= $currency ?> <?= number_format($bill_total, 0) ?></div>
                                        <?php else: ?>
                                            <span class="badge-status badge-warning">⏳ Pending</span>
                                            <div class="text-xs" style="color:var(--warning);"><?= $currency ?> <?= number_format($bill_total, 0) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs" style="color:var(--text-secondary);">No bill</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <div class="action-cell">
                                        <a href="view_patient_prescriptions.php?patient_id=<?= $patient['patient_id'] ?>" class="btn-view-items">
                                            <i class="fas fa-eye"></i> View Items
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="text-center py-6" style="color:var(--text-secondary);">
                                    <i class="fas fa-prescription text-2xl block mb-2" style="color:var(--border-color);"></i>
                                    <p style="font-size:0.85rem;">No patients with prescriptions found</p>
                                    <p class="text-xs mt-1" style="color:var(--text-secondary);">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif ($filter_status !== 'all'): ?>
                                            No <?= ucfirst($filter_status) ?> prescriptions
                                        <?php else: ?>
                                            No patients with prescriptions found
                                        <?php endif; ?>
                                    </p>
                                    <a href="prescription_history.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-history"></i> View History
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong id="rowCount"><?= count($patients) ?></strong> patients
                <span class="text-xs" style="color:var(--text-secondary);">
                    <?= $filter_status === 'all' ? '(All)' : '(' . ucfirst($filter_status) . ')' ?>
                </span>
            </span>
            <span>
                <span class="count-badge <?= $filter_status === 'dispensed' ? 'dispensed' : '' ?>" id="totalCountBadge"><?= $total_count ?></span> Total
                <span class="text-xs" style="color:var(--text-secondary);" id="updateTimeDisplay">Last update: <?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescriptions
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            <span class="new-db-footer"><i class="fas fa-database"></i> New DB</span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
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
    // DARK MODE - Sync with header button
    // ================================================================
    var htmlElement = document.documentElement;
    
    // Load saved dark mode preference
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
    
    // Listen for dark mode changes from header
    window.addEventListener('storage', function(e) {
        if (e.key === 'darkMode') {
            if (e.newValue === 'true') {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        }
    });
    
    // Also listen for DOM changes (header button)
    var observer = new MutationObserver(function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
    });
    observer.observe(htmlElement, { attributes: true, attributeFilter: ['data-theme'] });

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
        var liveTime = document.getElementById('liveTime');
        if (liveTime) {
            liveTime.textContent = timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var status = '<?= $filter_status ?>';
        if (query.length > 0) {
            window.location.href = 'pending_prescriptions.php?search=' + encodeURIComponent(query) + '&status=' + status;
        } else {
            window.location.href = 'pending_prescriptions.php?status=' + status;
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // AUTO-UPDATE EVERY 3 SECONDS
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var currentStatusFilter = '<?= $filter_status ?>';
    var currentSearch = '<?= addslashes($search) ?>';

    function fetchPrescriptionsStatus() {
        if (isUpdating) return;
        isUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_prescriptions_status');
        formData.append('branch_id', '<?= $user_branch_id ?>');
        formData.append('filter_status', currentStatusFilter);
        formData.append('search', currentSearch);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                updateUI(data);
            }
            isUpdating = false;
        })
        .catch(function(error) {
            console.error('Update error:', error);
            isUpdating = false;
        });
    }

    function updateUI(data) {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        
        var tbody = document.getElementById('prescriptionsTableBody');
        if (tbody && data.rows_html) {
            tbody.innerHTML = data.rows_html;
        }
        
        var statTotal = document.getElementById('statTotal');
        var statPending = document.getElementById('statPending');
        var statConfirmed = document.getElementById('statConfirmed');
        var statDispensed = document.getElementById('statDispensed');
        var totalCount = document.getElementById('totalCount');
        var totalCountBadge = document.getElementById('totalCountBadge');
        var rowCount = document.getElementById('rowCount');
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        
        if (statTotal) statTotal.textContent = data.total_count;
        if (statPending) statPending.textContent = data.pending_count;
        if (statConfirmed) statConfirmed.textContent = data.confirmed_count;
        if (statDispensed) statDispensed.textContent = data.dispensed_count;
        if (totalCount) totalCount.textContent = data.total_count;
        if (totalCountBadge) totalCountBadge.textContent = data.total_count;
        if (rowCount) {
            var rows = tbody ? tbody.querySelectorAll('tr').length : 0;
            rowCount.textContent = rows;
        }
        if (updateTimeDisplay) updateTimeDisplay.textContent = 'Last update: ' + timeStr;
        
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = timeStr;
    }

    function startAutoUpdate() {
        if (updateInterval) clearInterval(updateInterval);
        setTimeout(function() {
            fetchPrescriptionsStatus();
        }, 1000);
        updateInterval = setInterval(fetchPrescriptionsStatus, 3000);
        console.log('%c🔄 Prescription auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Prescription auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        if (e.key === 'F5') {
            e.preventDefault();
            window.location.reload();
        }
    });

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Warning' : '❌ Error') ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c💊 Braick - Prescriptions (One Row Per Patient - FIXED)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Quantity = SUM of ALL items for each patient', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c📋 Total Patients: <?= count($patients) ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c⏳ Pending: <?= $status_counts['pending'] ?? 0 ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Confirmed: <?= $status_counts['confirmed'] ?? 0 ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c💊 Dispensed: <?= $status_counts['dispensed'] ?? 0 ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔧 FIXED: SQLSTATE[HY093] Invalid parameter number', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark Mode: Sync with header button', 'font-size:13px; color:#6EA8FE;');
</script>

</body>
</html>