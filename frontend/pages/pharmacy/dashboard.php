<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dashboard.php
// PHARMACY DASHBOARD - 8 CARDS WITH AUTO-UPDATE (AJAX)
// USING NEW DATABASE: dispensary_db
// QUICK ACTIONS: 3 BUTTONS (New Prescription, OTC Sale, Inventory)
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
// CHECK USER ACCESS (Pharmacy or Admin)
// ================================================================
$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

$branch_name = $user_branch_name;
$unread_notifications = 0;

try {
    $db = Database::getInstance()->getConnection();
    $today = date('Y-m-d');
    $thirty_days_later = date('Y-m-d', strtotime('+30 days'));
    
    // ================================================================
    // GET UNREAD NOTIFICATIONS
    // ================================================================
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
    
    // ================================================================
    // CARD 1: TOTAL STOCK ITEMS (Medicine + Equipment)
    // ================================================================
    
    // Medicines (active, not expired)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT medication_name) as count, SUM(quantity) as total_quantity
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $med_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_medicines = $med_data['count'] ?? 0;
    $total_med_quantity = $med_data['total_quantity'] ?? 0;
    
    // Equipment (active, not expired)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT equipment_name) as count, SUM(quantity) as total_quantity
        FROM medical_equipment 
        WHERE branch_id = ? 
        AND status = 'active'
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $equip_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_equipment = $equip_data['count'] ?? 0;
    $total_equip_quantity = $equip_data['total_quantity'] ?? 0;
    
    $total_stock_items = $total_medicines + $total_equipment;
    $total_stock_quantity = $total_med_quantity + $total_equip_quantity;
    
    // ================================================================
    // CARD 2: EXPIRED (Medicine + Equipment)
    // ================================================================
    
    // Expired Medicines (all - active + inactive)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, SUM(quantity) as total_quantity
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND expiry_date IS NOT NULL 
        AND expiry_date < CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $expired_med = $stmt->fetch(PDO::FETCH_ASSOC);
    $expired_med_count = $expired_med['count'] ?? 0;
    $expired_med_quantity = $expired_med['total_quantity'] ?? 0;
    
    // Expired Equipment (all - active + inactive)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, SUM(quantity) as total_quantity
        FROM medical_equipment 
        WHERE branch_id = ? 
        AND expiry_date IS NOT NULL 
        AND expiry_date < CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $expired_equip = $stmt->fetch(PDO::FETCH_ASSOC);
    $expired_equip_count = $expired_equip['count'] ?? 0;
    $expired_equip_quantity = $expired_equip['total_quantity'] ?? 0;
    
    $expired_count = $expired_med_count + $expired_equip_count;
    $expired_quantity = $expired_med_quantity + $expired_equip_quantity;
    
    // ================================================================
    // CARD 3: EXPIRE SOON (Medicine + Equipment)
    // ================================================================
    
    // Medicines expiring soon
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$user_branch_id]);
    $expire_soon_med = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Equipment expiring soon
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medical_equipment 
        WHERE branch_id = ? 
        AND status = 'active'
        AND expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$user_branch_id]);
    $expire_soon_equip = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $expire_soon_count = $expire_soon_med + $expire_soon_equip;
    
    // ================================================================
    // CARD 4: TOTAL PRESCRIPTIONS (Total + Pending + Completed)
    // ================================================================
    
    // Total prescriptions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Pending prescriptions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Completed/Dispensed prescriptions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'dispensed'");
    $stmt->execute([$user_branch_id]);
    $completed_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // CARD 5: OTC SALES (Total + Today)
    // ================================================================
    
    // Total OTC Sales
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $otc_sales_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Today's OTC Sales
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM otc_sales 
        WHERE branch_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$user_branch_id]);
    $otc_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ================================================================
    // CARD 6: DISPENSED MEDICINES (OTC + Prescription Dispensed)
    // ================================================================
    
    // Total OTC (paid)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ? AND payment_status = 'paid'");
    $stmt->execute([$user_branch_id]);
    $otc_dispensed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Total Prescription Dispensed
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ? AND status = 'dispensed'");
    $stmt->execute([$user_branch_id]);
    $prescription_dispensed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $total_dispensed = $otc_dispensed + $prescription_dispensed;
    
    // ================================================================
    // CARD 7: LOW STOCK (Medicine + Equipment)
    // ================================================================
    
    // Low Stock Medicines
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity > 0 
        AND quantity <= reorder_level
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_med = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Low Stock Equipment
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medical_equipment 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity > 0 
        AND quantity <= reorder_level
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_equip = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $low_stock_count = $low_stock_med + $low_stock_equip;
    
    // ================================================================
    // CARD 8: OUT OF STOCK (Medicine + Equipment)
    // ================================================================
    
    // Out of Stock Medicines
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity = 0
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $out_of_stock_med = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Out of Stock Equipment
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medical_equipment 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity = 0
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ");
    $stmt->execute([$user_branch_id]);
    $out_of_stock_equip = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $out_of_stock_count = $out_of_stock_med + $out_of_stock_equip;
    
    // ================================================================
    // LISTS FOR DISPLAY (Expired, Expire Soon, Low Stock, Out of Stock)
    // ================================================================
    
    // Expired Medicines List
    $stmt = $db->prepare("
        SELECT id, medication_name as name, quantity, expiry_date, batch_number, status, 'medicine' as type
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND expiry_date IS NOT NULL 
        AND expiry_date < CURDATE()
        ORDER BY expiry_date ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $expired_med_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Expired Equipment List
    $stmt = $db->prepare("
        SELECT id, equipment_name as name, quantity, expiry_date, batch_number, status, 'equipment' as type
        FROM medical_equipment 
        WHERE branch_id = ? 
        AND expiry_date IS NOT NULL 
        AND expiry_date < CURDATE()
        ORDER BY expiry_date ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $expired_equip_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge and sort expired
    $expired_all = array_merge($expired_med_list, $expired_equip_list);
    usort($expired_all, function($a, $b) {
        return strtotime($a['expiry_date']) - strtotime($b['expiry_date']);
    });
    $expired_all = array_slice($expired_all, 0, 10);
    
    // Expire Soon Medicines List
    $stmt = $db->prepare("
        SELECT id, medication_name as name, quantity, expiry_date, batch_number, 'medicine' as type
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY expiry_date ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $expire_soon_med_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Expire Soon Equipment List
    $stmt = $db->prepare("
        SELECT id, equipment_name as name, quantity, expiry_date, batch_number, 'equipment' as type
        FROM medical_equipment 
        WHERE branch_id = ? 
        AND status = 'active'
        AND expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY expiry_date ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $expire_soon_equip_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge and sort expire soon
    $expire_soon_all = array_merge($expire_soon_med_list, $expire_soon_equip_list);
    usort($expire_soon_all, function($a, $b) {
        return strtotime($a['expiry_date']) - strtotime($b['expiry_date']);
    });
    $expire_soon_all = array_slice($expire_soon_all, 0, 10);
    
    // Low Stock Medicines List
    $stmt = $db->prepare("
        SELECT id, medication_name as name, quantity, reorder_level, unit, 'medicine' as type
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity > 0 
        AND quantity <= reorder_level
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ORDER BY quantity ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_med_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Low Stock Equipment List
    $stmt = $db->prepare("
        SELECT id, equipment_name as name, quantity, reorder_level, unit, 'equipment' as type
        FROM medical_equipment 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity > 0 
        AND quantity <= reorder_level
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ORDER BY quantity ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_equip_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge and sort low stock
    $low_stock_all = array_merge($low_stock_med_list, $low_stock_equip_list);
    usort($low_stock_all, function($a, $b) {
        return $a['quantity'] - $b['quantity'];
    });
    $low_stock_all = array_slice($low_stock_all, 0, 10);
    
    // Out of Stock Medicines List
    $stmt = $db->prepare("
        SELECT id, medication_name as name, quantity, reorder_level, unit, 'medicine' as type
        FROM medications_inventory 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity = 0
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ORDER BY medication_name ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $out_of_stock_med_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Out of Stock Equipment List
    $stmt = $db->prepare("
        SELECT id, equipment_name as name, quantity, reorder_level, unit, 'equipment' as type
        FROM medical_equipment 
        WHERE branch_id = ? 
        AND status = 'active'
        AND quantity = 0
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ORDER BY equipment_name ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $out_of_stock_equip_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge out of stock
    $out_of_stock_all = array_merge($out_of_stock_med_list, $out_of_stock_equip_list);
    $out_of_stock_all = array_slice($out_of_stock_all, 0, 10);
    
    // Pending Prescriptions List
    $stmt = $db->prepare("
        SELECT p.*, pat.full_name as patient_name, pat.patient_id as patient_code
        FROM prescriptions p
        JOIN patients pat ON p.patient_id = pat.id
        WHERE p.branch_id = ? AND p.status = 'pending'
        ORDER BY p.created_at ASC
        LIMIT 10
    ");
    $stmt->execute([$user_branch_id]);
    $pending_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Set default values on error
    $total_stock_items = 0;
    $total_stock_quantity = 0;
    $total_medicines = 0;
    $total_equipment = 0;
    $expired_count = 0;
    $expired_quantity = 0;
    $expired_med_count = 0;
    $expired_equip_count = 0;
    $expire_soon_count = 0;
    $expire_soon_med = 0;
    $expire_soon_equip = 0;
    $total_prescriptions = 0;
    $pending_count = 0;
    $completed_prescriptions = 0;
    $otc_sales_count = 0;
    $otc_today_count = 0;
    $total_dispensed = 0;
    $otc_dispensed = 0;
    $prescription_dispensed = 0;
    $low_stock_count = 0;
    $low_stock_med = 0;
    $low_stock_equip = 0;
    $out_of_stock_count = 0;
    $out_of_stock_med = 0;
    $out_of_stock_equip = 0;
    $expired_all = [];
    $expire_soon_all = [];
    $low_stock_all = [];
    $out_of_stock_all = [];
    $pending_list = [];
}

// ================================================================
// GET INITIAL HASH FOR AUTO-UPDATE
// ================================================================
$initial_hash = '';
try {
    $api_url = '/dispensary_system/frontend/api/get_pharmacy_stats.php';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['hash'])) {
            $initial_hash = $data['hash'];
        }
    }
} catch (Exception $e) {
    // Silent fail
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
    <title>Pharmacy Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - PHARMACY THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
            --teal: #0D9488;
            --teal-bg: #CCFBF1;
            
            --pink: #DB2777;
            --pink-bg: #FCE7F3;
            
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
            
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
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
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            transition: all 0.3s ease;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
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
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        /* ================================================================
           STATS CARDS - 8 CARDS (4 TOP, 4 BOTTOM) - BIGGER FONTS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: 14px;
            padding: 20px 24px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-decoration: none;
            display: block;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card:active {
            transform: scale(0.97);
        }
        
        .stat-card .stat-icon {
            font-size: 1.5rem;
            opacity: 0.9;
            margin-bottom: 4px;
            display: block;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            margin-top: 4px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }
        
        .stat-card .stat-update {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.4);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .stat-card .stat-update .live-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1.5s infinite;
        }
        
        .stat-card .stat-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 6px;
        }
        
        .stat-card .stat-grid-2 .stat-number {
            font-size: 1.3rem;
        }
        
        .stat-card .stat-grid-2 .stat-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.55);
        }
        
        .stat-card .stat-sub-grid {
            display: flex;
            gap: 12px;
            margin-top: 4px;
            font-size: 0.65rem;
            color: rgba(255,255,255,0.65);
        }
        
        .stat-card .stat-sub-grid span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .stat-card .stat-arrow {
            position: absolute;
            bottom: 12px;
            right: 16px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.3);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-arrow {
            transform: translateX(4px);
            color: rgba(255,255,255,0.7);
        }
        
        /* Card Colors */
        .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
        .stat-card.pink { background: linear-gradient(135deg, #DB2777, #BE185D); }
        .stat-card.yellow { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.gray { background: linear-gradient(135deg, #6B7280, #4B5563); }
        
        /* ================================================================
           CARDS
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 18px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        .card-title .title-purple { color: var(--purple); }
        .card-title .title-orange { color: #D97706; }
        .card-title .title-red { color: var(--danger); }
        .card-title .title-teal { color: var(--teal); }
        
        /* ================================================================
           SCROLL CONTAINER
           ================================================================ */
        .scroll-container {
            max-height: 250px;
            overflow-y: auto;
        }
        
        .scroll-container::-webkit-scrollbar {
            width: 4px;
        }
        
        .scroll-container::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.55rem;
            font-weight: 600;
        }
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.dispensed { background: #D1FAE5; color: #059669; }
        .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        .status-badge.completed { background: #D1FAE5; color: #059669; }
        
        /* ================================================================
           LIST ITEMS
           ================================================================ */
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 8px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.75rem;
        }
        .list-item:last-child { border-bottom: none; }
        .list-item .name { font-weight: 500; }
        .list-item .qty { font-weight: 600; }
        .list-item .qty.low { color: #D97706; }
        .list-item .qty.out { color: #DC2626; }
        .list-item .expired-badge {
            background: #FEE2E2;
            color: #DC2626;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 0.5rem;
            font-weight: 600;
        }
        .list-item .expire-date.warning { color: #D97706; font-size: 0.65rem; }
        .list-item .expire-date.danger { color: #DC2626; font-size: 0.65rem; }
        .list-item .expire-date.expired { color: #DC2626; font-weight: 600; font-size: 0.65rem; }
        .list-item .batch { font-size: 0.55rem; color: var(--text-secondary); font-family: monospace; }
        
        .expired-row {
            background: var(--danger-bg) !important;
            border-radius: 4px;
            margin-bottom: 2px;
        }
        
        [data-theme="dark"] .expired-row {
            background: #3A1A1A !important;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
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
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { 
            color: var(--primary); 
            font-weight: 600; 
        }
        
        /* ================================================================
           QUICK ACTIONS - 3 BUTTONS
           ================================================================ */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .quick-action {
            padding: 18px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
        }
        
        .quick-action:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .quick-action .icon {
            font-size: 1.8rem;
            display: block;
            margin-bottom: 6px;
        }
        
        .quick-action .label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           GRID
           ================================================================ */
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        .grid-cols-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .gap-5 { gap: 20px; }
        .mt-5 { margin-top: 20px; }
        .mb-2 { margin-bottom: 8px; }
        
        .text-center { text-align: center; }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .text-gray-400 { color: var(--gray-400); }
        .text-primary { color: var(--primary); }
        .text-green-500 { color: var(--success); }
        .text-red-500 { color: var(--danger); }
        .text-orange-500 { color: var(--warning); }
        .block { display: block; }
        .inline-block { display: inline-block; }
        .py-4 { padding-top: 16px; padding-bottom: 16px; }
        .ml-1 { margin-left: 4px; }
        .ml-2 { margin-left: 8px; }
        .mr-1 { margin-right: 4px; }
        .mr-2 { margin-right: 8px; }
        
        .btn-sm {
            padding: 2px 8px;
            font-size: 0.6rem;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .grid-cols-4 { grid-template-columns: 1fr 1fr; }
            .quick-actions-grid { max-width: 100%; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stat-card .stat-number { font-size: 1.5rem; }
            .stat-card { padding: 14px 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .grid-cols-2 { grid-template-columns: 1fr; }
            .grid-cols-3 { grid-template-columns: 1fr 1fr; }
            .grid-cols-4 { grid-template-columns: 1fr 1fr; }
            .quick-actions-grid { grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
            .quick-action { padding: 12px; }
            .quick-action .icon { font-size: 1.4rem; }
            .quick-action .label { font-size: 0.7rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .stat-card { padding: 10px 12px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card .stat-grid-2 .stat-number { font-size: 1rem; }
            .quick-actions-grid { grid-template-columns: 1fr 1fr; }
            .quick-action { padding: 10px; }
            .quick-action .icon { font-size: 1.2rem; }
            .quick-action .label { font-size: 0.65rem; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.2s; }
        .animate-fade-in-up:nth-child(5) { animation-delay: 0.25s; }
        .animate-fade-in-up:nth-child(6) { animation-delay: 0.3s; }
        .animate-fade-in-up:nth-child(7) { animation-delay: 0.35s; }
        .animate-fade-in-up:nth-child(8) { animation-delay: 0.4s; }
        
        /* Update pulse */
        .update-pulse {
            animation: pulse-badge 2s infinite;
        }
        
        @keyframes pulse-badge {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
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
                Pharmacy Dashboard
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">PHARMACY</span>
                <span class="update-badge-light update-pulse" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Welcome back, <strong><?= htmlspecialchars($user_full_name) ?></strong>!
                
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?>
                </span>
                
                <span class="header-badge" id="liveTime">
                    <i class="fas fa-clock"></i> <?= date('H:i:s') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_prescriptions.php" class="btn-outline-light">
                <i class="fas fa-clock"></i> Pending (<?= $pending_count ?>)
            </a>
            <button onclick="manualUpdate()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS - 8 CARDS (4 TOP, 4 BOTTOM) - BIGGER FONTS -->
    <!-- ================================================================ -->
    
    <!-- ROW 1: Cards 1-4 -->
    <div class="stats-grid" id="statsGrid">
        
        <!-- CARD 1: Total Stock Items (Medicine + Equipment) -->
        <a href="inventory.php" class="stat-card blue animate-fade-in-up" id="card1">
            <span class="stat-icon">📦</span>
            <div class="stat-number" id="statTotalStock"><?= $total_stock_items ?></div>
            <div class="stat-label">Total Stock Items</div>
            <div class="stat-grid-2">
                <div>
                    <div class="stat-number" id="statMedicines">💊 <?= $total_medicines ?></div>
                    <div class="stat-sub">Medicine</div>
                </div>
                <div>
                    <div class="stat-number" id="statEquipment">🔧 <?= $total_equipment ?></div>
                    <div class="stat-sub">Equipment</div>
                </div>
            </div>
            <div class="stat-update"><span class="live-dot"></span> <span id="statTotalUnits"><?= $total_stock_quantity ?></span> units</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- CARD 2: Expired (Medicine + Equipment) -->
        <a href="expired.php" class="stat-card red animate-fade-in-up" id="card2">
            <span class="stat-icon">🚫</span>
            <div class="stat-number" id="statExpired"><?= $expired_count ?></div>
            <div class="stat-label">Expired</div>
            <div class="stat-grid-2">
                <div>
                    <div class="stat-number" id="statExpiredMed">💊 <?= $expired_med_count ?></div>
                    <div class="stat-sub">Medicine</div>
                </div>
                <div>
                    <div class="stat-number" id="statExpiredEquip">🔧 <?= $expired_equip_count ?></div>
                    <div class="stat-sub">Equipment</div>
                </div>
            </div>
            <div class="stat-update"><span class="live-dot"></span> <span id="statExpiredUnits"><?= $expired_quantity ?></span> units</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- CARD 3: Expire Soon (Medicine + Equipment) -->
        <a href="expiring_soon.php" class="stat-card orange animate-fade-in-up" id="card3">
            <span class="stat-icon">⏰</span>
            <div class="stat-number" id="statExpireSoon"><?= $expire_soon_count ?></div>
            <div class="stat-label">Expire Soon</div>
            <div class="stat-grid-2">
                <div>
                    <div class="stat-number" id="statExpireSoonMed">💊 <?= $expire_soon_med ?></div>
                    <div class="stat-sub">Medicine</div>
                </div>
                <div>
                    <div class="stat-number" id="statExpireSoonEquip">🔧 <?= $expire_soon_equip ?></div>
                    <div class="stat-sub">Equipment</div>
                </div>
            </div>
            <div class="stat-update"><span class="live-dot"></span> Within 30 days</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- CARD 4: Total Prescriptions (Total + Pending + Completed) -->
        <a href="prescription_history.php" class="stat-card purple animate-fade-in-up" id="card4">
            <span class="stat-icon">📋</span>
            <div class="stat-number" id="statPrescriptions"><?= $total_prescriptions ?></div>
            <div class="stat-label">Total Prescriptions</div>
            <div class="stat-sub-grid" id="statPrescriptionSub">
                <span>⏳ <?= $pending_count ?> Pending</span>
                <span>✅ <?= $completed_prescriptions ?> Completed</span>
            </div>
            <div class="stat-update"><span class="live-dot"></span> All time</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
    </div>
    
    <!-- ROW 2: Cards 5-8 -->
    <div class="stats-grid" id="statsGrid2">
        
        <!-- CARD 5: OTC Sales -->
        <a href="otc_history.php" class="stat-card teal animate-fade-in-up" id="card5">
            <span class="stat-icon">🛒</span>
            <div class="stat-number" id="statOTC"><?= $otc_sales_count ?></div>
            <div class="stat-label">OTC Sales</div>
            <div class="stat-sub" id="statOTCToday"><?= $otc_today_count ?> today</div>
            <div class="stat-update"><span class="live-dot"></span> Total sales</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- CARD 6: Dispensed Medicines (OTC + Prescription) -->
        <a href="dispensed.php" class="stat-card green animate-fade-in-up" id="card6">
            <span class="stat-icon">✅</span>
            <div class="stat-number" id="statDispensed"><?= $total_dispensed ?></div>
            <div class="stat-label">Dispensed Medicines</div>
            <div class="stat-grid-2">
                <div>
                    <div class="stat-number" id="statOTCDispensed">🛒 <?= $otc_dispensed ?></div>
                    <div class="stat-sub">OTC</div>
                </div>
                <div>
                    <div class="stat-number" id="statPrescriptionDispensed">📋 <?= $prescription_dispensed ?></div>
                    <div class="stat-sub">Prescription</div>
                </div>
            </div>
            <div class="stat-update"><span class="live-dot"></span> Total dispensed</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- CARD 7: Low Stock (Medicine + Equipment) -->
        <a href="low_stock.php" class="stat-card yellow animate-fade-in-up" id="card7">
            <span class="stat-icon">⚠️</span>
            <div class="stat-number" id="statLowStock"><?= $low_stock_count ?></div>
            <div class="stat-label">Low Stock</div>
            <div class="stat-grid-2">
                <div>
                    <div class="stat-number" id="statLowStockMed">💊 <?= $low_stock_med ?></div>
                    <div class="stat-sub">Medicine</div>
                </div>
                <div>
                    <div class="stat-number" id="statLowStockEquip">🔧 <?= $low_stock_equip ?></div>
                    <div class="stat-sub">Equipment</div>
                </div>
            </div>
            <div class="stat-update"><span class="live-dot"></span> Below reorder level</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- CARD 8: Out of Stock (Medicine + Equipment) -->
        <a href="out_of_stock.php" class="stat-card gray animate-fade-in-up" id="card8">
            <span class="stat-icon">🚫</span>
            <div class="stat-number" id="statOutOfStock"><?= $out_of_stock_count ?></div>
            <div class="stat-label">Out of Stock</div>
            <div class="stat-grid-2">
                <div>
                    <div class="stat-number" id="statOutOfStockMed">💊 <?= $out_of_stock_med ?></div>
                    <div class="stat-sub">Medicine</div>
                </div>
                <div>
                    <div class="stat-number" id="statOutOfStockEquip">🔧 <?= $out_of_stock_equip ?></div>
                    <div class="stat-sub">Equipment</div>
                </div>
            </div>
            <div class="stat-update"><span class="live-dot"></span> Quantity = 0</div>
            <span class="stat-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- LISTS: Expired, Expire Soon, Low Stock, Out of Stock, Pending -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" id="listsContainer">
        
        <!-- Expired List -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-skull title-red mr-2"></i> Expired
                    <span class="text-sm font-normal text-red-500">(<span id="expiredCount"><?= count($expired_all) ?></span> items)</span>
                </h3>
                <a href="expired.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            <div class="scroll-container" id="expiredList">
                <?php if (count($expired_all) > 0): ?>
                    <?php foreach ($expired_all as $item): ?>
                        <div class="list-item expired-row">
                            <span class="name">
                                <?= $item['type'] === 'medicine' ? '💊' : '🔧' ?>
                                <?= htmlspecialchars($item['name'] ?? 'Unknown') ?>
                                <span class="batch"><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></span>
                            </span>
                            <span class="qty"><?= $item['quantity'] ?> units</span>
                            <span class="expire-date expired">
                                <i class="fas fa-calendar-times mr-1"></i>
                                <?= date('d/m/Y', strtotime($item['expiry_date'])) ?>
                                <span class="expired-badge ml-1">EXPIRED</span>
                                <?php if (isset($item['status']) && $item['status'] === 'inactive'): ?>
                                    <span class="text-xs text-gray-400 ml-1">(inactive)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>
                        <p>No expired items</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Expire Soon List -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock title-orange mr-2"></i> Expire Soon
                    <span class="text-sm font-normal text-gray-400">(<span id="expireSoonCount"><?= count($expire_soon_all) ?></span> items)</span>
                </h3>
                <a href="expiring_soon.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            <div class="scroll-container" id="expireSoonList">
                <?php if (count($expire_soon_all) > 0): ?>
                    <?php foreach ($expire_soon_all as $item): ?>
                        <div class="list-item">
                            <span class="name">
                                <?= $item['type'] === 'medicine' ? '💊' : '🔧' ?>
                                <?= htmlspecialchars($item['name'] ?? 'Unknown') ?>
                                <span class="batch"><?= htmlspecialchars($item['batch_number'] ?? 'N/A') ?></span>
                            </span>
                            <span class="qty"><?= $item['quantity'] ?> units</span>
                            <span class="expire-date warning">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                <?= date('d/m/Y', strtotime($item['expiry_date'])) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>
                        <p>No items expiring soon</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Low Stock List -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exclamation-triangle title-orange mr-2"></i> Low Stock
                    <span class="text-sm font-normal text-gray-400">(<span id="lowStockCount"><?= count($low_stock_all) ?></span> items)</span>
                </h3>
                <a href="low_stock.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            <div class="scroll-container" id="lowStockList">
                <?php if (count($low_stock_all) > 0): ?>
                    <?php foreach ($low_stock_all as $item): ?>
                        <div class="list-item">
                            <span class="name">
                                <?= $item['type'] === 'medicine' ? '💊' : '🔧' ?>
                                <?= htmlspecialchars($item['name'] ?? 'Unknown') ?>
                                <span class="batch">(Reorder: <?= $item['reorder_level'] ?>)</span>
                            </span>
                            <span class="qty low"><?= $item['quantity'] ?> / <?= $item['reorder_level'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>
                        <p>No low stock items</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Out of Stock List -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-times-circle title-red mr-2"></i> Out of Stock
                    <span class="text-sm font-normal text-gray-400">(<span id="outOfStockCount"><?= count($out_of_stock_all) ?></span> items)</span>
                </h3>
                <a href="out_of_stock.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            <div class="scroll-container" id="outOfStockList">
                <?php if (count($out_of_stock_all) > 0): ?>
                    <?php foreach ($out_of_stock_all as $item): ?>
                        <div class="list-item">
                            <span class="name">
                                <?= $item['type'] === 'medicine' ? '💊' : '🔧' ?>
                                <?= htmlspecialchars($item['name'] ?? 'Unknown') ?>
                            </span>
                            <span class="qty out">0 units</span>
                            <a href="reorder.php?id=<?= $item['id'] ?>&type=<?= $item['type'] ?>" 
                               class="btn-sm" style="background:var(--primary);color:white;">
                                <i class="fas fa-shopping-cart"></i> Reorder
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>
                        <p>All items in stock</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pending Prescriptions List -->
        <div class="card animate-fade-in-up" style="grid-column: 1 / -1;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock title-red mr-2"></i> Pending Prescriptions
                    <span class="text-sm font-normal text-gray-400">(<span id="pendingCount"><?= count($pending_list) ?></span> items)</span>
                </h3>
                <a href="pending_prescriptions.php" class="text-primary text-sm hover:underline">View All →</a>
            </div>
            <div class="scroll-container" id="pendingList">
                <?php if (count($pending_list) > 0): ?>
                    <?php foreach ($pending_list as $pres): ?>
                        <div class="list-item">
                            <div>
                                <span class="name"><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></span>
                                <span class="batch block">#<?= htmlspecialchars($pres['prescription_number']) ?></span>
                            </div>
                            <span class="status-badge pending">Pending</span>
                            <a href="dispense.php?id=<?= $pres['id'] ?>" class="btn-sm" style="background:var(--success);color:white;">
                                <i class="fas fa-prescription"></i> Dispense
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-gray-400">
                        <i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>
                        <p>No pending prescriptions</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS - 3 BUTTONS (No Reports) -->
    <!-- ================================================================ -->
    <div class="quick-actions-grid mt-5">
        <a href="new_prescription.php" class="quick-action animate-fade-in-up">
            <span class="icon" style="color:#0B5ED7;">📝</span>
            <span class="label">New Prescription</span>
        </a>
        
        <a href="new_otc_sale.php" class="quick-action animate-fade-in-up">
            <span class="icon" style="color:#059669;">🛒</span>
            <span class="label">OTC Sale</span>
        </a>
        
        <a href="inventory.php" class="quick-action animate-fade-in-up">
            <span class="icon" style="color:#7C3AED;">📦</span>
            <span class="label">Inventory</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-400 mx-2">|</span>
            Pharmacy Dashboard
            <span class="text-gray-400 mx-2">|</span>
            <span id="footerTimestamp">● Live • <?= date('H:i:s') ?></span>
            <span class="text-gray-400 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - AUTO-UPDATE EVERY 3 SECONDS -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // INITIAL DATA HASH
    // ================================================================
    var currentHash = '<?= $initial_hash ?>';
    var updateInterval = 3000; // 3 seconds
    var isUpdating = false;
    var updateCounter = 0;
    
    // ================================================================
    // UPDATE FUNCTION
    // ================================================================
    function updateDashboard() {
        if (isUpdating) return;
        isUpdating = true;
        
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/dispensary_system/frontend/api/get_pharmacy_stats.php', true);
        xhr.timeout = 5000;
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                isUpdating = false;
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Check if data has changed
                            if (response.hash !== currentHash) {
                                currentHash = response.hash;
                                updateUI(response);
                                updateCounter = 0;
                                
                                // Show toast on update
                                var now = new Date();
                                var timeStr = now.toLocaleTimeString();
                                showToast('🔄 Updated', 'Dashboard auto-updated at ' + timeStr, 'info');
                            }
                            
                            // Update timestamp
                            var timestamp = document.getElementById('footerTimestamp');
                            if (timestamp) {
                                var now = new Date();
                                timestamp.textContent = '● Live • ' + now.toLocaleTimeString();
                            }
                            
                            var updateBadge = document.getElementById('updateBadge');
                            if (updateBadge) {
                                var now = new Date();
                                updateBadge.innerHTML = '<i class="fas fa-check-circle"></i> Live • ' + 
                                    now.toLocaleTimeString();
                            }
                            
                            var liveTime = document.getElementById('liveTime');
                            if (liveTime) {
                                var now = new Date();
                                liveTime.innerHTML = '<i class="fas fa-clock"></i> ' + now.toLocaleTimeString();
                            }
                            
                            updateCounter++;
                        }
                    } catch (e) {
                        console.log('Update error:', e);
                    }
                }
            }
        };
        
        xhr.onerror = function() {
            isUpdating = false;
        };
        
        xhr.ontimeout = function() {
            isUpdating = false;
        };
        
        xhr.send();
    }
    
    // ================================================================
    // MANUAL UPDATE
    // ================================================================
    function manualUpdate() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        btn.disabled = true;
        
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/dispensary_system/frontend/api/get_pharmacy_stats.php?manual=1', true);
        xhr.timeout = 5000;
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                btn.disabled = false;
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            currentHash = response.hash;
                            updateUI(response);
                            showToast('✅ Updated', 'Dashboard refreshed successfully', 'success');
                        }
                    } catch (e) {
                        showToast('❌ Error', 'Failed to update dashboard', 'error');
                    }
                }
            }
        };
        
        xhr.send();
    }
    
    // ================================================================
    // UPDATE UI WITH NEW DATA
    // ================================================================
    function updateUI(data) {
        var stats = data.stats;
        var lists = data.lists;
        
        // ================================================================
        // UPDATE STATS CARDS
        // ================================================================
        
        // CARD 1: Total Stock Items
        document.getElementById('statTotalStock').textContent = stats.total_stock_items;
        document.getElementById('statMedicines').textContent = '💊 ' + stats.total_medicines;
        document.getElementById('statEquipment').textContent = '🔧 ' + stats.total_equipment;
        document.getElementById('statTotalUnits').textContent = stats.total_stock_quantity;
        
        // CARD 2: Expired
        document.getElementById('statExpired').textContent = stats.expired_count;
        document.getElementById('statExpiredMed').textContent = '💊 ' + stats.expired_med_count;
        document.getElementById('statExpiredEquip').textContent = '🔧 ' + stats.expired_equip_count;
        document.getElementById('statExpiredUnits').textContent = stats.expired_quantity;
        
        // CARD 3: Expire Soon
        document.getElementById('statExpireSoon').textContent = stats.expire_soon_count;
        document.getElementById('statExpireSoonMed').textContent = '💊 ' + stats.expire_soon_med;
        document.getElementById('statExpireSoonEquip').textContent = '🔧 ' + stats.expire_soon_equip;
        
        // CARD 4: Prescriptions
        document.getElementById('statPrescriptions').textContent = stats.total_prescriptions;
        document.getElementById('statPrescriptionSub').innerHTML = 
            '<span>⏳ ' + stats.pending_count + ' Pending</span>' +
            '<span>✅ ' + stats.completed_prescriptions + ' Completed</span>';
        
        // CARD 5: OTC Sales
        document.getElementById('statOTC').textContent = stats.otc_sales_count;
        document.getElementById('statOTCToday').textContent = stats.otc_today_count + ' today';
        
        // CARD 6: Dispensed
        document.getElementById('statDispensed').textContent = stats.total_dispensed;
        document.getElementById('statOTCDispensed').textContent = '🛒 ' + stats.otc_dispensed;
        document.getElementById('statPrescriptionDispensed').textContent = '📋 ' + stats.prescription_dispensed;
        
        // CARD 7: Low Stock
        document.getElementById('statLowStock').textContent = stats.low_stock_count;
        document.getElementById('statLowStockMed').textContent = '💊 ' + stats.low_stock_med;
        document.getElementById('statLowStockEquip').textContent = '🔧 ' + stats.low_stock_equip;
        
        // CARD 8: Out of Stock
        document.getElementById('statOutOfStock').textContent = stats.out_of_stock_count;
        document.getElementById('statOutOfStockMed').textContent = '💊 ' + stats.out_of_stock_med;
        document.getElementById('statOutOfStockEquip').textContent = '🔧 ' + stats.out_of_stock_equip;
        
        // ================================================================
        // UPDATE LISTS
        // ================================================================
        
        // Expired List
        updateList('expiredList', 'expiredCount', lists.expired, function(item) {
            var type = item.type === 'medicine' ? '💊' : '🔧';
            var expiredText = '<span class="expired-badge ml-1">EXPIRED</span>';
            if (item.status === 'inactive') {
                expiredText += ' <span class="text-xs text-gray-400">(inactive)</span>';
            }
            return '<div class="list-item expired-row">' +
                '<span class="name">' + type + ' ' + escapeHtml(item.name) + 
                ' <span class="batch">' + escapeHtml(item.batch_number || 'N/A') + '</span></span>' +
                '<span class="qty">' + item.quantity + ' units</span>' +
                '<span class="expire-date expired">' +
                '<i class="fas fa-calendar-times mr-1"></i> ' + formatDate(item.expiry_date) + 
                ' ' + expiredText +
                '</span></div>';
        });
        
        // Expire Soon List
        updateList('expireSoonList', 'expireSoonCount', lists.expire_soon, function(item) {
            var type = item.type === 'medicine' ? '💊' : '🔧';
            return '<div class="list-item">' +
                '<span class="name">' + type + ' ' + escapeHtml(item.name) + 
                ' <span class="batch">' + escapeHtml(item.batch_number || 'N/A') + '</span></span>' +
                '<span class="qty">' + item.quantity + ' units</span>' +
                '<span class="expire-date warning">' +
                '<i class="fas fa-calendar-alt mr-1"></i> ' + formatDate(item.expiry_date) +
                '</span></div>';
        });
        
        // Low Stock List
        updateList('lowStockList', 'lowStockCount', lists.low_stock, function(item) {
            var type = item.type === 'medicine' ? '💊' : '🔧';
            return '<div class="list-item">' +
                '<span class="name">' + type + ' ' + escapeHtml(item.name) + 
                ' <span class="batch">(Reorder: ' + item.reorder_level + ')</span></span>' +
                '<span class="qty low">' + item.quantity + ' / ' + item.reorder_level + '</span></div>';
        });
        
        // Out of Stock List
        updateList('outOfStockList', 'outOfStockCount', lists.out_of_stock, function(item) {
            var type = item.type === 'medicine' ? '💊' : '🔧';
            var id = item.id;
            var itemType = item.type;
            return '<div class="list-item">' +
                '<span class="name">' + type + ' ' + escapeHtml(item.name) + '</span>' +
                '<span class="qty out">0 units</span>' +
                '<a href="reorder.php?id=' + id + '&type=' + itemType + '" class="btn-sm" style="background:var(--primary);color:white;">' +
                '<i class="fas fa-shopping-cart"></i> Reorder</a></div>';
        });
        
        // Pending Prescriptions List
        updateList('pendingList', 'pendingCount', lists.pending, function(item) {
            return '<div class="list-item">' +
                '<div><span class="name">' + escapeHtml(item.patient_name || 'Unknown') + '</span>' +
                '<span class="batch block">#' + escapeHtml(item.prescription_number) + '</span></div>' +
                '<span class="status-badge pending">Pending</span>' +
                '<a href="dispense.php?id=' + item.id + '" class="btn-sm" style="background:var(--success);color:white;">' +
                '<i class="fas fa-prescription"></i> Dispense</a></div>';
        });
    }
    
    // ================================================================
    // HELPER FUNCTIONS
    // ================================================================
    
    function updateList(elementId, countId, items, renderFn) {
        var container = document.getElementById(elementId);
        var countEl = document.getElementById(countId);
        
        if (!container) return;
        
        if (items && items.length > 0) {
            var html = '';
            for (var i = 0; i < items.length; i++) {
                html += renderFn(items[i]);
            }
            container.innerHTML = html;
            if (countEl) countEl.textContent = items.length;
        } else {
            container.innerHTML = '<div class="text-center py-4 text-gray-400">' +
                '<i class="fas fa-check-circle text-2xl block mb-2 text-green-500"></i>' +
                '<p>No items to display</p></div>';
            if (countEl) countEl.textContent = '0';
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        var d = new Date(dateStr);
        return ('0' + d.getDate()).slice(-2) + '/' + 
               ('0' + (d.getMonth() + 1)).slice(-2) + '/' + 
               d.getFullYear();
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
        }, 3000);
    }
    
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });
    
    // ================================================================
    // START AUTO-UPDATE
    // ================================================================
    // First update after 1 second
    setTimeout(function() {
        updateDashboard();
    }, 1000);
    
    // Then every 3 seconds
    setInterval(updateDashboard, updateInterval);
    
    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+R or F5 - Manual refresh
        if ((e.ctrlKey && e.key === 'r') || e.key === 'F5') {
            e.preventDefault();
            manualUpdate();
        }
    });
    
    console.log('%c💊 Braick - Pharmacy Dashboard (Auto-Update Every 3s)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔄 Auto-update interval: ' + updateInterval + 'ms', 'font-size:13px; color:#34D399;');
    console.log('%c📊 8 CARDS + 5 LISTS', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ QUICK ACTIONS: 3 buttons (No Reports)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Card fonts: BIGGER size', 'font-size:13px; color:#34D399;');
    console.log('%c📦 1. Total Stock: 💊<?= $total_medicines ?> + 🔧<?= $total_equipment ?> = <?= $total_stock_items ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c🚫 2. Expired: 💊<?= $expired_med_count ?> + 🔧<?= $expired_equip_count ?> = <?= $expired_count ?>', 'font-size:12px; color:#DC2626;');
    console.log('%c⏰ 3. Expire Soon: 💊<?= $expire_soon_med ?> + 🔧<?= $expire_soon_equip ?> = <?= $expire_soon_count ?>', 'font-size:12px; color:#D97706;');
    console.log('%c📋 4. Prescriptions: <?= $total_prescriptions ?> (⏳<?= $pending_count ?> Pending, ✅<?= $completed_prescriptions ?> Completed)', 'font-size:12px; color:#7C3AED;');
    console.log('%c🛒 5. OTC Sales: <?= $otc_sales_count ?> (<?= $otc_today_count ?> today)', 'font-size:12px; color:#0D9488;');
    console.log('%c✅ 6. Dispensed: 🛒<?= $otc_dispensed ?> + 📋<?= $prescription_dispensed ?> = <?= $total_dispensed ?>', 'font-size:12px; color:#059669;');
    console.log('%c⚠️ 7. Low Stock: 💊<?= $low_stock_med ?> + 🔧<?= $low_stock_equip ?> = <?= $low_stock_count ?>', 'font-size:12px; color:#D97706;');
    console.log('%c🚫 8. Out of Stock: 💊<?= $out_of_stock_med ?> + 🔧<?= $out_of_stock_equip ?> = <?= $out_of_stock_count ?>', 'font-size:12px; color:#6B7280;');
    console.log('%c✅ Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Press Ctrl+R or F5 for manual refresh', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>