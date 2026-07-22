<?php
// ================================================================
// FILE: frontend/pages/pharmacy/pending_prescriptions.php
// PHARMACY - PENDING PRESCRIPTIONS WITH DISPENSE BUTTON
// DISPENSE BUTTON → Redirects to dispense.php
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Pharmacy
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 9;
    $_SESSION['full_name'] = 'Pharmacy Dodoma';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy';
$user_id = $_SESSION['user_id'] ?? 9;

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    $db = getDB();
    
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
    // HANDLE ACTIONS
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $prescription_id = isset($_POST['prescription_id']) ? (int)$_POST['prescription_id'] : 0;
        
        // ================================================================
        // CONFIRM PRESCRIPTION - Send Bill to Cashier
        // ================================================================
        if ($action === 'confirm_prescription') {
            if ($prescription_id > 0) {
                try {
                    $db->beginTransaction();
                    
                    // Get prescription details
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
                        // Get medication price from inventory
                        $stmt = $db->prepare("
                            SELECT selling_price FROM medications_inventory 
                            WHERE medication_name = ? AND branch_id = ? AND status = 'active'
                            LIMIT 1
                        ");
                        $stmt->execute([$prescription['medication'], $user_branch_id]);
                        $price_result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $unit_price = $price_result['selling_price'] ?? 0;
                        
                        // Calculate total
                        $quantity = (int)$prescription['quantity'];
                        $total_amount = $unit_price * $quantity;
                        
                        // Generate bill number with unique check
                        $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 6, '0', STR_PAD_LEFT);
                        
                        // Check if bill number exists
                        $stmt = $db->prepare("SELECT id FROM patient_bills WHERE bill_number = ?");
                        $stmt->execute([$bill_number]);
                        if ($stmt->fetch()) {
                            $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 6, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                        }
                        
                        // Check if bill already exists for this visit
                        $stmt = $db->prepare("
                            SELECT id FROM patient_bills 
                            WHERE visit_id = ? AND branch_id = ?
                        ");
                        $stmt->execute([$prescription['visit_id'], $user_branch_id]);
                        $existing_bill = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$existing_bill) {
                            // Insert bill
                            $stmt = $db->prepare("
                                INSERT INTO patient_bills (
                                    bill_number, patient_id, visit_id, 
                                    total_amount, balance, status, 
                                    created_by, branch_id,
                                    created_at, updated_at
                                ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
                            ");
                            $stmt->execute([
                                $bill_number,
                                $prescription['patient_id'],
                                $prescription['visit_id'],
                                $total_amount,
                                $total_amount,
                                $user_id,
                                $user_branch_id
                            ]);
                            $bill_id = $db->lastInsertId();
                            
                            // Insert bill item
                            $stmt = $db->prepare("
                                INSERT INTO bill_items (
                                    bill_id, item_type, item_name, 
                                    quantity, unit_price, total_price,
                                    payment_status, is_paid, status, created_at
                                ) VALUES (?, 'medication', ?, ?, ?, ?, 'pending', 0, 'pending', NOW())
                            ");
                            $stmt->execute([
                                $bill_id,
                                $prescription['medication'],
                                $quantity,
                                $unit_price,
                                $total_amount
                            ]);
                            
                            // Update prescription status to 'confirmed'
                            $stmt = $db->prepare("
                                UPDATE prescriptions 
                                SET status = 'confirmed', 
                                    pharmacy_id = ?,
                                    updated_at = NOW()
                                WHERE id = ? AND branch_id = ?
                            ");
                            $stmt->execute([$user_id, $prescription_id, $user_branch_id]);
                            
                            // Log activity
                            $stmt = $db->prepare("
                                INSERT INTO activity_logs (user_id, action, details, created_at)
                                VALUES (?, 'prescription_confirmed', ?, NOW())
                            ");
                            $stmt->execute([
                                $user_id,
                                "Prescription #" . $prescription['prescription_number'] . " confirmed - Bill #" . $bill_number . " sent to Cashier"
                            ]);
                            
                            $db->commit();
                            $message = "✅ Prescription confirmed! Bill sent to Cashier.";
                            $message_type = 'success';
                        } else {
                            $db->rollBack();
                            $message = "⚠️ Bill already exists for this visit.";
                            $message_type = 'warning';
                        }
                    } else {
                        $db->rollBack();
                        $message = "❌ Prescription not found or already processed.";
                        $message_type = 'error';
                    }
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = "❌ Error: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
    
    // ================================================================
    // GET FILTER PARAMETERS
    // ================================================================
    $filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    
    // ================================================================
    // BUILD QUERY
    // ================================================================
    $conditions = ["p.branch_id = ?"];
    $params = [$user_branch_id];
    
    if ($filter_status !== 'all') {
        $conditions[] = "p.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($search)) {
        $conditions[] = "(pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.prescription_number LIKE ? OR p.medication LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($date_from)) {
        $conditions[] = "DATE(p.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $conditions[] = "DATE(p.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = implode(" AND ", $conditions);
    
    // ================================================================
    // GET PRESCRIPTIONS
    // ================================================================
    $sql = "
        SELECT 
            p.*,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone,
            u.full_name as doctor_name,
            u.specialty,
            v.visit_number,
            (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as item_count,
            (SELECT id FROM patient_bills WHERE visit_id = p.visit_id ORDER BY id DESC LIMIT 1) as bill_id,
            (SELECT status FROM patient_bills WHERE visit_id = p.visit_id ORDER BY id DESC LIMIT 1) as bill_status
        FROM prescriptions p
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        WHERE $where_clause
        ORDER BY 
            CASE 
                WHEN p.status = 'pending' THEN 0 
                WHEN p.status = 'confirmed' THEN 1 
                ELSE 2 
            END,
            p.created_at ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET STATUS COUNTS
    // ================================================================
    $statuses = ['pending', 'confirmed', 'dispensed', 'cancelled'];
    $status_counts = [];
    foreach ($statuses as $status) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM prescriptions 
            WHERE branch_id = ? AND status = ?
        ");
        $stmt->execute([$user_branch_id, $status]);
        $status_counts[$status] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }
    
    // Total
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE branch_id = ?");
    $stmt->execute([$user_branch_id]);
    $total_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $prescriptions = [];
    $total_count = 0;
    $status_counts = ['pending' => 0, 'confirmed' => 0, 'dispensed' => 0, 'cancelled' => 0];
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

function getBillStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending Payment',
        'partial' => '🔶 Partial',
        'paid' => '✅ Paid',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function getBillStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'partial' => 'badge-warning',
        'paid' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

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
    <title>Pending Prescriptions - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
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
            background: var(--primary);
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
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
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            border: none;
            transition: var(--transition);
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            text-decoration: none;
            display: block;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.65rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.85;
            margin-top: 2px;
        }
        
        .stat-card .stat-icon {
            font-size: 1rem;
            opacity: 0.8;
            margin-bottom: 2px;
        }
        
        .stat-card.pending { background: linear-gradient(135deg, #D97706, #B45309); }
        .stat-card.confirmed { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .stat-card.dispensed { background: linear-gradient(135deg, #059669, #047857); }
        .stat-card.total { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
        .stat-card.cancelled { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
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
        
        .filter-input {
            padding: 7px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .btn-search {
            padding: 7px 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ffffff;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i {
            margin-right: 5px;
            opacity: 0.7;
        }
        
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
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
        
        .badge-status {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
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
        
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.6rem;
            border-radius: 4px;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* DISPENSE BUTTON - Green and Prominent */
        .btn-dispense {
            background: var(--success);
            color: white;
            padding: 5px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-dispense:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-dispense:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-confirm {
            background: var(--primary);
            color: white;
            padding: 5px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-confirm:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .table-footer {
            padding: 10px 16px;
            border-top: 1px solid var(--border-color);
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .count-badge {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
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
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table tbody td { padding: 5px 8px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .stats-row { grid-template-columns: 1fr; }
            .page-title { font-size: 1.1rem; }
            .btn { padding: 3px 8px; font-size: 0.55rem; }
            .btn-dispense, .btn-confirm { padding: 3px 10px; font-size: 0.6rem; }
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
            <input type="text" id="searchInput" placeholder="Search prescriptions..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

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
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">PHARMACY</span>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-list"></i> <?= $total_count ?> Total
                </span>
                <?php if ($filter_status !== 'all'): ?>
                    <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                        <i class="fas fa-filter"></i> <?= ucfirst($filter_status) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-prescription"></i>
                Manage prescriptions in <strong><?= htmlspecialchars($branch_name) ?></strong>
                <span class="text-xs text-gray-400 ml-2">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Dispense</strong> = Go to dispense page
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-row">
        <a href="?status=all" class="stat-card total <?= $filter_status === 'all' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-number"><?= $total_count ?></div>
            <div class="stat-label">Total</div>
        </a>
        <a href="?status=pending" class="stat-card pending <?= $filter_status === 'pending' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $status_counts['pending'] ?? 0 ?></div>
            <div class="stat-label">⏳ Pending</div>
        </a>
        <a href="?status=confirmed" class="stat-card confirmed <?= $filter_status === 'confirmed' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $status_counts['confirmed'] ?? 0 ?></div>
            <div class="stat-label">✅ Confirmed</div>
        </a>
        <a href="?status=dispensed" class="stat-card dispensed <?= $filter_status === 'dispensed' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-prescription-bottle"></i></div>
            <div class="stat-number"><?= $status_counts['dispensed'] ?? 0 ?></div>
            <div class="stat-label">💊 Dispensed</div>
        </a>
        <a href="?status=cancelled" class="stat-card cancelled <?= $filter_status === 'cancelled' ? 'ring-2 ring-white ring-opacity-50' : '' ?>">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number"><?= $status_counts['cancelled'] ?? 0 ?></div>
            <div class="stat-label">❌ Cancelled</div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <div class="filter-row">
            <a href="?status=all" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?status=pending" class="filter-btn <?= $filter_status === 'pending' ? 'active' : '' ?>">⏳ Pending</a>
            <a href="?status=confirmed" class="filter-btn <?= $filter_status === 'confirmed' ? 'active' : '' ?>">✅ Confirmed</a>
            <a href="?status=dispensed" class="filter-btn <?= $filter_status === 'dispensed' ? 'active' : '' ?>">💊 Dispensed</a>
            <a href="?status=cancelled" class="filter-btn <?= $filter_status === 'cancelled' ? 'active' : '' ?>">❌ Cancelled</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:8px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <input type="text" name="search" class="filter-input" placeholder="Search patient, medication..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:150px;">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search) || $filter_status !== 'all'): ?>
                    <a href="pending_prescriptions.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE -->
    <!-- ================================================================ -->
    <div class="table-container">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-receipt"></i> Prescription #</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-pills"></i> Medication</th>
                        <th><i class="fas fa-cubes"></i> Qty</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-money-bill"></i> Bill</th>
                        <th><i class="fas fa-calendar"></i> Date</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($prescriptions) > 0): ?>
                        <?php $i = 1; foreach ($prescriptions as $pres): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono text-xs font-semibold" style="color:var(--primary);">
                                        <?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?>
                                    </span>
                                    <?php if (($pres['item_count'] ?? 0) > 0): ?>
                                        <span class="text-xs text-gray-400 block">(<?= $pres['item_count'] ?> items)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($pres['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($pres['patient_code'] ?? 'N/A') ?></div>
                                    <?php if (!empty($pres['phone'])): ?>
                                        <div class="text-xs text-gray-400">📱 <?= htmlspecialchars($pres['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-sm"><?= htmlspecialchars($pres['medication'] ?? 'N/A') ?></span>
                                    <?php if (!empty($pres['dosage'])): ?>
                                        <span class="text-xs text-gray-400 block"><?= htmlspecialchars($pres['dosage']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($pres['frequency'])): ?>
                                        <span class="text-xs text-gray-400 block"><?= htmlspecialchars($pres['frequency']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-sm font-semibold"><?= $pres['quantity'] ?? 0 ?></span>
                                    <?php if (!empty($pres['duration'])): ?>
                                        <span class="text-xs text-gray-400 block"><?= $pres['duration'] ?> days</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= getStatusBadgeClass($pres['status'] ?? 'pending') ?>">
                                        <?= getStatusLabel($pres['status'] ?? 'pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($pres['bill_id'])): ?>
                                        <span class="badge-status <?= getBillStatusBadgeClass($pres['bill_status'] ?? 'pending') ?>">
                                            <?= getBillStatusLabel($pres['bill_status'] ?? 'pending') ?>
                                        </span>
                                        <?php if ($pres['bill_status'] === 'paid'): ?>
                                            <span class="text-xs text-green-600 block">✅ Paid</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">No bill yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-xs"><?= formatDate($pres['created_at'] ?? '') ?></span>
                                    <?php if (!empty($pres['visit_number'])): ?>
                                        <span class="text-xs text-gray-400 block">Visit: <?= htmlspecialchars($pres['visit_number']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <!-- View Button -->
                                        <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn btn-primary btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- DISPENSE BUTTON - Goes to dispense.php -->
                                        <?php if (($pres['status'] ?? '') === 'pending' || ($pres['status'] ?? '') === 'confirmed'): ?>
                                            <a href="dispense.php?id=<?= $pres['id'] ?>" class="btn-dispense" title="Dispense Prescription">
                                                <i class="fas fa-prescription-bottle"></i> Dispense
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Confirm Button - Only for pending (if needed) -->
                                        <?php if (($pres['status'] ?? '') === 'pending' && empty($pres['bill_id'])): ?>
                                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Confirm this prescription?\n\n✅ Bill will be created and sent to Cashier.');">
                                                <input type="hidden" name="action" value="confirm_prescription">
                                                <input type="hidden" name="prescription_id" value="<?= $pres['id'] ?>">
                                                <button type="submit" class="btn-confirm" title="Confirm - Send Bill to Cashier">
                                                    <i class="fas fa-check-circle"></i> Confirm
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if (($pres['status'] ?? '') === 'dispensed'): ?>
                                            <span class="text-xs text-green-600">✅ Done</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="text-center py-8 text-gray-400">
                                    <i class="fas fa-prescription text-3xl block mb-2"></i>
                                    <p>No prescriptions found</p>
                                    <p class="text-sm mt-1">
                                        <?php if (!empty($search)): ?>
                                            No results for "<strong><?= htmlspecialchars($search) ?></strong>"
                                        <?php elseif ($filter_status !== 'all'): ?>
                                            No <?= ucfirst($filter_status) ?> prescriptions
                                        <?php else: ?>
                                            No prescriptions available in this branch
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Table Footer -->
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= count($prescriptions) ?></strong> prescriptions
            </span>
            <span>
                <span class="count-badge"><?= $total_count ?></span> Total prescriptions
                <?php if ($filter_status !== 'all'): ?>
                    <span class="text-xs text-gray-400 ml-2">(Filtered: <?= ucfirst($filter_status) ?>)</span>
                <?php endif; ?>
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

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var status = '<?= $filter_status ?>';
        if (query.length > 0) {
            window.location.href = 'pending_prescriptions.php?search=' + encodeURIComponent(query) + '&status=' + status;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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

    console.log('%c💊 Braick - Pending Prescriptions', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Total: <?= $total_count ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c⏳ Pending: <?= $status_counts['pending'] ?? 0 ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Confirmed: <?= $status_counts['confirmed'] ?? 0 ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💊 Dispensed: <?= $status_counts['dispensed'] ?? 0 ?>', 'font-size:13px; color:#059669;');
    console.log('%c❌ Cancelled: <?= $status_counts['cancelled'] ?? 0 ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c📊 Filter: <?= ucfirst($filter_status) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔄 DISPENSE button → Goes to dispense.php', 'font-size:13px; color:#059669;');
    console.log('%c✅ Confirm button → Creates bill and sends to Cashier', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>