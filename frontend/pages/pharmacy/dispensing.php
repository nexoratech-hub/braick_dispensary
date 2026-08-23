<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dispensing.php
// PHARMACY - DISPENSING (Process Prescriptions)
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
// DATABASE CONNECTION - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET PRESCRIPTION ID
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ================================================================
// PROCESS DISPENSING - NEW DATABASE
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $prescription_id = (int)($_POST['prescription_id'] ?? 0);
    
    // ================================================================
    // DISPENSE PRESCRIPTION
    // ================================================================
    if ($action === 'dispense' && $prescription_id > 0) {
        try {
            $db->beginTransaction();
            
            // Get prescription details with items
            $stmt = $db->prepare("
                SELECT p.*, 
                       pat.full_name as patient_name,
                       pat.patient_id as patient_code,
                       u.full_name as doctor_name
                FROM prescriptions p
                JOIN patients pat ON p.patient_id = pat.id
                LEFT JOIN users u ON p.doctor_id = u.id
                WHERE p.id = ? AND p.branch_id = ? AND p.status = 'pending'
            ");
            $stmt->execute([$prescription_id, $user_branch_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$prescription) {
                throw new Exception("Prescription not found or already processed");
            }
            
            // Get prescription items
            $stmt = $db->prepare("
                SELECT * FROM prescription_items 
                WHERE prescription_id = ?
            ");
            $stmt->execute([$prescription_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($items)) {
                throw new Exception("No items found in this prescription");
            }
            
            // Check stock for each item - using NEW DB (medications_inventory)
            $stock_error = false;
            $out_of_stock_items = [];
            
            foreach ($items as $item) {
                // Get total available quantity across all batches
                $stmt = $db->prepare("
                    SELECT SUM(quantity) as total_quantity 
                    FROM medications_inventory 
                    WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                ");
                $stmt->execute([$item['medication_name'], $user_branch_id]);
                $stock = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $available = $stock['total_quantity'] ?? 0;
                
                if ($available < $item['quantity']) {
                    $stock_error = true;
                    $out_of_stock_items[] = [
                        'name' => $item['medication_name'],
                        'available' => $available,
                        'required' => $item['quantity']
                    ];
                }
            }
            
            if ($stock_error) {
                $error_msg = "Insufficient stock for the following items:<br>";
                foreach ($out_of_stock_items as $item) {
                    $error_msg .= "- {$item['name']}: Available {$item['available']}, Required {$item['required']}<br>";
                }
                $message = $error_msg;
                $message_type = 'error';
                $db->rollBack();
            } else {
                // Update stock for each item - FIFO (First Expiry First Out)
                foreach ($items as $item) {
                    $needed = $item['quantity'];
                    
                    // Get batches ordered by expiry date (earliest first)
                    $stmt = $db->prepare("
                        SELECT id, medication_name, quantity, batch_number, expiry_date
                        FROM medications_inventory 
                        WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                        ORDER BY expiry_date ASC
                    ");
                    $stmt->execute([$item['medication_name'], $user_branch_id]);
                    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($batches as $batch) {
                        if ($needed <= 0) break;
                        
                        $deduct = min($needed, $batch['quantity']);
                        $new_qty = $batch['quantity'] - $deduct;
                        
                        // Update inventory
                        $stmt_update = $db->prepare("
                            UPDATE medications_inventory 
                            SET quantity = ?,
                                updated_at = NOW()
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt_update->execute([$new_qty, $batch['id'], $user_branch_id]);
                        
                        // Log stock movement
                        $stmt_log = $db->prepare("
                            INSERT INTO stock_movements (
                                inventory_id,
                                patient_id,
                                movement_type,
                                quantity,
                                previous_stock,
                                new_stock,
                                reference_type,
                                reference_id,
                                performed_by,
                                branch_id,
                                notes,
                                created_at
                            ) VALUES (?, ?, 'out', ?, ?, ?, 'prescription', ?, ?, ?, ?, NOW())
                        ");
                        $stmt_log->execute([
                            $batch['id'],
                            $prescription['patient_id'],
                            $deduct,
                            $batch['quantity'],
                            $new_qty,
                            $prescription_id,
                            $user_id,
                            $user_branch_id,
                            "Dispensed {$deduct} units from batch {$batch['batch_number']}"
                        ]);
                        
                        $needed -= $deduct;
                    }
                }
                
                // Update prescription status to dispensed
                $stmt = $db->prepare("
                    UPDATE prescriptions 
                    SET status = 'dispensed',
                        dispensed_at = NOW(),
                        pharmacy_id = ?,
                        updated_at = NOW()
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$user_id, $prescription_id, $user_branch_id]);
                
                // Update bill status to paid if bill exists
                $stmt = $db->prepare("
                    SELECT id, status FROM bills 
                    WHERE visit_id = ? AND patient_id = ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$prescription['visit_id'], $prescription['patient_id']]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($bill && $bill['status'] !== 'paid') {
                    $stmt = $db->prepare("
                        UPDATE bills 
                        SET status = 'paid',
                            paid_amount = total_amount,
                            balance = 0,
                            updated_at = NOW()
                        WHERE id = ? AND visit_id = ?
                    ");
                    $stmt->execute([$bill['id'], $prescription['visit_id']]);
                    
                    // Update bill items
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET status = 'paid',
                            updated_at = NOW()
                        WHERE bill_id = ? AND reference_type = 'prescription' AND reference_id = ?
                    ");
                    $stmt->execute([$bill['id'], $prescription_id]);
                }
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                    VALUES (?, ?, 'prescription_dispensed', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $user_branch_id,
                    "Prescription #{$prescription['prescription_number']} dispensed by {$user_full_name}"
                ]);
                
                $db->commit();
                
                $message = "Prescription dispensed successfully!";
                $message_type = 'success';
                
                // Redirect to prescription history after 2 seconds
                echo '<script>setTimeout(function(){ window.location.href = "prescription_history.php?success=1"; }, 1500);</script>';
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // CANCEL PRESCRIPTION
    // ================================================================
    if ($action === 'cancel' && $prescription_id > 0) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                UPDATE prescriptions 
                SET status = 'cancelled',
                    updated_at = NOW()
                WHERE id = ? AND branch_id = ? AND status = 'pending'
            ");
            $stmt->execute([$prescription_id, $user_branch_id]);
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                VALUES (?, ?, 'prescription_cancelled', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Prescription # cancelled by {$user_full_name}"
            ]);
            
            $db->commit();
            
            $message = "Prescription cancelled successfully!";
            $message_type = 'success';
            
            header('Location: pending_prescriptions.php?cancelled=1');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// GET PRESCRIPTION DETAILS - NEW DATABASE
// ================================================================
$prescription = null;
$items = [];

if ($prescription_id > 0) {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            pat.id as patient_id,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone,
            pat.email,
            pat.gender,
            pat.date_of_birth,
            pat.address,
            u.full_name as doctor_name,
            u.specialty,
            v.visit_number,
            v.visit_type,
            v.diagnosis,
            v.symptoms,
            b.id as bill_id,
            b.bill_number,
            b.total_amount as bill_total,
            b.discount_amount as bill_discount,
            b.balance as bill_balance,
            b.status as bill_status
        FROM prescriptions p
        JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        LEFT JOIN bills b ON b.visit_id = p.visit_id AND b.patient_id = p.patient_id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$prescription_id, $user_branch_id]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($prescription) {
        // Get prescription items
        $stmt = $db->prepare("
            SELECT * FROM prescription_items 
            WHERE prescription_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$prescription_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check stock status for each item
        foreach ($items as &$item) {
            // Get total available quantity
            $stmt = $db->prepare("
                SELECT SUM(quantity) as total_quantity 
                FROM medications_inventory 
                WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
            ");
            $stmt->execute([$item['medication_name'], $user_branch_id]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $available = $stock['total_quantity'] ?? 0;
            $item['stock_available'] = $available;
            $item['stock_ok'] = ($available >= $item['quantity']);
        }
        unset($item);
    }
}

// ================================================================
// GET PENDING PRESCRIPTIONS LIST - NEW DATABASE
// ================================================================
$pending_list = [];
$stmt = $db->prepare("
    SELECT p.id, p.prescription_number, p.total_amount, p.created_at,
           pat.full_name as patient_name, pat.patient_id as patient_code
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    WHERE p.branch_id = ? AND p.status = 'pending'
    ORDER BY p.created_at DESC
");
$stmt->execute([$user_branch_id]);
$pending_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS FOR SIDEBAR - NEW DATABASE
// ================================================================
$pending_prescriptions = count($pending_list);

$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity > 0 AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-pending',
        'confirmed' => 'badge-info',
        'dispensed' => 'badge-dispensed',
        'cancelled' => 'badge-cancelled'
    ];
    return $map[$status] ?? 'badge-pending';
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

function formatMoney($amount) {
    if ($amount === null) return '0.00';
    return number_format((float)$amount, 2, '.', ',');
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispensing - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
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
            --primary-bg: #1E3A5F;
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
            transition: all 0.3s ease;
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
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
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
        
        .branch-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: var(--primary-gradient);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 25px rgba(11, 94, 215, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-outline {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(4px);
        }
        
        .btn-outline:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
        
        /* ================================================================
           PRESCRIPTION DETAIL CARD
           ================================================================ */
        .prescription-detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        
        .prescription-detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        [data-theme="dark"] .prescription-detail-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .prescription-detail-card .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .prescription-detail-card .detail-header .sale-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            font-family: monospace;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .badge-pending { background: var(--warning-bg); color: var(--warning); }
        .badge-dispensed { background: var(--success-bg); color: var(--success); }
        .badge-cancelled { background: var(--danger-bg); color: var(--danger); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        
        [data-theme="dark"] .badge-pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .badge-dispensed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .badge-cancelled { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; }
        
        .prescription-detail-card .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .prescription-detail-card .detail-grid .info-item .label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .prescription-detail-card .detail-grid .info-item .value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }
        
        .items-table th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.7rem;
            text-transform: uppercase;
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
        }
        
        .items-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .items-table tr:hover td {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .items-table tr:hover td {
            background: #1E3A5F;
        }
        
        .items-table .stock-ok {
            color: var(--success);
            font-weight: 600;
        }
        
        .items-table .stock-error {
            color: var(--danger);
            font-weight: 600;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .total-summary {
            background: var(--bg-body);
            border-radius: 10px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
        }
        
        [data-theme="dark"] .total-summary {
            background: var(--gray-700);
        }
        
        .total-summary .total-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .total-summary .total-amount {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* ================================================================
           PENDING LIST
           ================================================================ */
        .pending-list-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        
        [data-theme="dark"] .pending-list-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .pending-list-card .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .pending-list-card .list-item:hover {
            background: var(--primary-bg);
            border-radius: 8px;
        }
        
        .pending-list-card .list-item:last-child {
            border-bottom: none;
        }
        
        .pending-list-card .list-item .item-info .patient-name {
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .pending-list-card .list-item .item-info .sale-number {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        .pending-list-card .list-item .item-amount {
            font-weight: 600;
            color: var(--primary);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn-dispense-large {
            background: var(--success);
            color: white;
            padding: 10px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-dispense-large:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.4);
        }
        
        .btn-dispense-large:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-cancel-large {
            background: #EF4444;
            color: white;
            padding: 10px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .btn-cancel-large:hover {
            background: #DC2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.4);
        }
        
        .btn-outline-large {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 8px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-outline-large:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state .sub {
            font-size: 0.8rem;
            margin-top: 4px;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: #D97706; }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .prescription-detail-card .detail-grid {
                grid-template-columns: 1fr 1fr;
            }
            .prescription-detail-card {
                padding: 16px 18px;
            }
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .action-buttons .btn-dispense-large,
            .action-buttons .btn-cancel-large,
            .action-buttons .btn-outline-large {
                width: 100%;
                justify-content: center;
            }
            .total-summary {
                flex-direction: column;
                text-align: center;
            }
            .pending-list-card .list-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .main-content { padding: 10px; }
        }
        
        @media (max-width: 480px) {
            .prescription-detail-card .detail-grid {
                grid-template-columns: 1fr;
            }
            .items-table {
                font-size: 0.75rem;
            }
            .items-table th,
            .items-table td {
                padding: 4px 8px;
            }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search prescriptions...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='<?= $logo_path ?>'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription mr-2"></i> Dispensing
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <?php if ($pending_prescriptions > 0): ?>
                    <span class="branch-tag" style="background:rgba(239,68,68,0.3);color:#FCA5A5;border-color:rgba(239,68,68,0.2);">
                        <i class="fas fa-clock mr-1"></i> <?= $pending_prescriptions ?> pending
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Process and dispense prescription medicines
                <span class="text-xs text-white/50 ml-2">
                    <i class="fas fa-clock"></i> Updated: <?= date('H:i:s') ?>
                </span>
                <span class="text-xs text-white/60 ml-2">
                    <i class="fas fa-database"></i> New DB
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="pending_prescriptions.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Pending
            </a>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PENDING PRESCRIPTIONS LIST -->
    <!-- ================================================================ -->
    <?php if (count($pending_list) > 0 && !$prescription): ?>
    <div class="pending-list-card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                Pending Prescriptions
                <span class="text-sm font-normal text-gray-400">(<?= count($pending_list) ?> pending)</span>
            </h3>
        </div>
        
        <?php foreach ($pending_list as $pending): ?>
            <a href="dispensing.php?id=<?= $pending['id'] ?>" class="list-item">
                <div class="item-info">
                    <div class="patient-name"><?= htmlspecialchars($pending['patient_name']) ?></div>
                    <div class="sale-number"><?= htmlspecialchars($pending['prescription_number']) ?></div>
                </div>
                <div class="item-amount">
                    TSh <?= number_format($pending['total_amount'] ?? 0) ?>
                    <span class="text-xs text-gray-400 ml-2">
                        <?= date('M d, Y', strtotime($pending['created_at'])) ?>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION DETAILS -->
    <!-- ================================================================ -->
    <?php if ($prescription): ?>
        <div class="prescription-detail-card animate-fade-in-up">
            <div class="detail-header">
                <div class="sale-number">
                    <?= htmlspecialchars($prescription['prescription_number']) ?>
                    <span class="badge <?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>">
                        <?= getStatusLabel($prescription['status'] ?? 'pending') ?>
                    </span>
                </div>
                <div class="text-sm text-gray-400">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    <?= date('M d, Y h:i A', strtotime($prescription['created_at'])) ?>
                </div>
            </div>
            
            <div class="detail-grid">
                <div class="info-item">
                    <div class="label">Patient</div>
                    <div class="value"><?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?></div>
                    <div class="text-xs text-gray-400">ID: <?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Doctor</div>
                    <div class="value"><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Phone</div>
                    <div class="value"><?= htmlspecialchars($prescription['phone'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Total Amount</div>
                    <div class="value" style="color: #0D9488; font-weight:700;">
                        TSh <?= number_format($prescription['bill_total'] ?? $prescription['total_amount'] ?? 0) ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($prescription['diagnosis'])): ?>
                <div class="text-sm text-gray-600 mb-3">
                    <strong>Diagnosis:</strong> <?= htmlspecialchars($prescription['diagnosis']) ?>
                </div>
            <?php endif; ?>
            
            <!-- Items Table -->
            <h4 class="font-semibold text-gray-700 mb-2">Prescribed Medicines</h4>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:center;">Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                <?php if (!empty($item['dosage'])): ?>
                                    <br><span class="text-xs text-gray-400"><?= htmlspecialchars($item['dosage']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['frequency'])): ?>
                                    <span class="text-xs text-gray-400"> • <?= htmlspecialchars($item['frequency']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;"><?= $item['quantity'] ?></td>
                            <td style="text-align:right;font-family:monospace;">
                                TSh <?= number_format($item['unit_price'] ?? 0) ?>
                            </td>
                            <td style="text-align:right;font-family:monospace;font-weight:600;color:var(--primary);">
                                TSh <?= number_format($item['total_price'] ?? 0) ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($prescription['status'] === 'pending'): ?>
                                    <?php if ($item['stock_ok']): ?>
                                        <span class="stock-ok"><i class="fas fa-check-circle"></i> <?= $item['stock_available'] ?> avail</span>
                                    <?php else: ?>
                                        <span class="stock-error"><i class="fas fa-exclamation-triangle"></i> <?= $item['stock_available'] ?? 0 ?> avail</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Dispensed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Total Summary -->
            <div class="total-summary">
                <div>
                    <span class="total-label">Total Amount</span>
                    <span class="total-amount">TSh <?= number_format($prescription['bill_total'] ?? $prescription['total_amount'] ?? 0) ?></span>
                </div>
                <div>
                    <span class="total-label">Items: <?= count($items) ?></span>
                    <?php if ($prescription['bill_discount'] > 0): ?>
                        <span class="text-sm text-orange-500 ml-2">Discount: TSh <?= number_format($prescription['bill_discount'] ?? 0) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <?php if ($prescription['status'] === 'pending'): ?>
                <div class="action-buttons">
                    <form method="POST" action="" 
                          onsubmit="return confirm('Dispense this prescription? This will reduce medicine stock and cannot be undone.');">
                        <input type="hidden" name="action" value="dispense">
                        <input type="hidden" name="prescription_id" value="<?= $prescription['id'] ?>">
                        
                        <?php 
                            $all_in_stock = true;
                            foreach ($items as $item) {
                                if (!$item['stock_ok']) {
                                    $all_in_stock = false;
                                    break;
                                }
                            }
                        ?>
                        <button type="submit" class="btn-dispense-large" <?= !$all_in_stock ? 'disabled title="Some items are out of stock"' : '' ?>>
                            <i class="fas fa-prescription"></i> Dispense Prescription
                        </button>
                    </form>
                    
                    <form method="POST" action="pending_prescriptions.php" 
                          onsubmit="return confirm('Cancel this prescription?');">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="prescription_id" value="<?= $prescription['id'] ?>">
                        <button type="submit" class="btn-cancel-large">
                            <i class="fas fa-times"></i> Cancel Prescription
                        </button>
                    </form>
                    
                    <a href="pending_prescriptions.php" class="btn-outline-large">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            <?php elseif ($prescription['status'] === 'dispensed'): ?>
                <div class="action-buttons">
                    <a href="view_prescription.php?id=<?= $prescription['id'] ?>" class="btn btn-outline" style="background:var(--primary);color:white;border:none;padding:10px 24px;font-size:0.9rem;">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    <a href="prescription_history.php" class="btn-outline-large">
                        <i class="fas fa-arrow-left"></i> View History
                    </a>
                </div>
            <?php else: ?>
                <div class="action-buttons">
                    <a href="pending_prescriptions.php" class="btn-outline-large">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- NO PRESCRIPTION SELECTED -->
    <!-- ================================================================ -->
    <?php if (!$prescription && count($pending_list) == 0): ?>
        <div class="empty-state">
            <i class="fas fa-prescription"></i>
            <p>No pending prescriptions</p>
            <p class="sub">All prescriptions have been dispensed. Great job! 🎉</p>
        </div>
    <?php endif; ?>
    
    <?php if (!$prescription && count($pending_list) > 0): ?>
        <div class="empty-state">
            <i class="fas fa-hand-pointer"></i>
            <p>Select a prescription from the list above</p>
            <p class="sub">Click on any pending prescription to view details and dispense</p>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Dispensing
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:var(--primary);font-weight:600;font-size:0.65rem;">
                <i class="fas fa-database"></i> New DB
            </span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
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
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
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
        var footer = document.getElementById('footerTimestamp');
        if (footer) {
            footer.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
    });

    console.log('%c💊 Braick - Dispensing (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c📊 Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c🔐 Session-based login active', 'font-size:12px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Pending Prescriptions: <?= $pending_prescriptions ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Tables: prescriptions, prescription_items, medications_inventory, bills, stock_movements', 'font-size:13px; color:#34D399;');
    <?php if ($prescription): ?>
    console.log('%c📋 Processing: <?= htmlspecialchars($prescription['prescription_number']) ?> - <?= htmlspecialchars($prescription['patient_name']) ?>', 'font-size:13px; color:#0D9488;');
    <?php endif; ?>
</script>

</body>
</html>