<?php
// ================================================================
// FILE: frontend/pages/cashier/make_payment.php
// CASHIER - COMPLETE PARTIAL PAYMENTS
// SHOWS ONLY PARTIAL BILLS - NO DISCOUNT APPLIED
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

$is_admin = ($user_role === 'admin');
$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$selected_bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ================================================================
    // HANDLE AJAX REQUEST - COMPLETE PAYMENT
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'];
        $bill_ids = isset($_POST['bill_ids']) ? $_POST['bill_ids'] : [];
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
        
        if ($action === 'complete_payment') {
            if (empty($bill_ids) || !is_array($bill_ids)) {
                echo json_encode(['success' => false, 'message' => 'No bills selected for payment']);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                $success_count = 0;
                $failed_bills = [];
                $receipt_numbers = [];
                $total_amount_paid = 0;
                $total_remaining = 0;
                
                // Get total remaining balance
                $placeholders = implode(',', array_fill(0, count($bill_ids), '?'));
                $stmt = $db->prepare("
                    SELECT id, balance, patient_id, bill_number, total_amount, paid_amount, discount_amount, total_discount
                    FROM bills 
                    WHERE id IN ($placeholders) AND branch_id = ? AND status = 'partial'
                ");
                $params = array_merge($bill_ids, [$user_branch_id]);
                $stmt->execute($params);
                $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($bills as $bill) {
                    $remaining = (float)$bill['balance'];
                    if ($remaining <= 0) {
                        $failed_bills[] = $bill['bill_number'];
                        continue;
                    }
                    
                    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Update bill - complete payment
                    $stmt = $db->prepare("
                        UPDATE bills 
                        SET paid_amount = paid_amount + ?,
                            balance = 0,
                            status = 'paid',
                            updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt->execute([$remaining, $bill['id'], $user_branch_id]);
                    
                    // Update bill items to paid
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET status = 'paid',
                            updated_at = NOW()
                        WHERE bill_id = ? AND status != 'cancelled'
                    ");
                    $stmt->execute([$bill['id']]);
                    
                    // Insert payment
                    $stmt = $db->prepare("
                        INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW., ?)
                    ");
                    $stmt->execute([
                        $receipt_number,
                        $bill['id'],
                        $bill['patient_id'],
                        $remaining,
                        $payment_method,
                        $user_id,
                        $user_branch_id,
                        'Final payment - Balance cleared'
                    ]);
                    
                    $total_amount_paid += $remaining;
                    $total_remaining += $remaining;
                    $receipt_numbers[] = $receipt_number;
                    $success_count++;
                }
                
                $db->commit();
                
                $message = $success_count . " bill(s) fully paid!";
                $message .= " Total Paid: " . $currency . " " . number_format($total_amount_paid, 2);
                
                if (!empty($failed_bills)) {
                    $message .= " Failed: " . implode(', ', $failed_bills);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'receipt_numbers' => $receipt_numbers,
                    'total_paid' => $total_amount_paid,
                    'count' => $success_count
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    // ================================================================
    // GET PARTIAL BILLS ONLY
    // ================================================================
    $bills_query = "
        SELECT 
            b.*,
            b.discount_amount,
            b.total_discount,
            v.visit_number,
            v.visit_type,
            v.visit_date,
            u.full_name as doctor_name,
            p.full_name as patient_name,
            p.patient_id as patient_number,
            p.phone,
            p.gender,
            p.date_of_birth,
            p.address,
            p.blood_group,
            p.email,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled' AND status != 'paid') as pending_items,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status = 'paid') as paid_items
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE b.branch_id = ? AND b.status = 'partial'
    ";

    $params = [$user_branch_id];

    if ($selected_bill_id > 0) {
        $bills_query .= " AND b.id = ?";
        $params[] = $selected_bill_id;
    }

    if (!empty($search)) {
        $bills_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR b.bill_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $bills_query .= " ORDER BY b.created_at ASC";

    $stmt = $db->prepare($bills_query);
    $stmt->execute($params);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================================================
    // GET ITEMS FOR EACH BILL
    // ================================================================
    foreach ($bills as &$bill) {
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ? AND status != 'cancelled'
            ORDER BY status ASC, created_at ASC
        ");
        $stmt->execute([$bill['id']]);
        $bill['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================================================
    // GROUP BILLS BY PATIENT
    // ================================================================
    $patient_bills_data = [];
    $patient_map = [];

    foreach ($bills as $bill) {
        $patient_id = $bill['patient_id'];
        
        if (!isset($patient_map[$patient_id])) {
            $patient_map[$patient_id] = [
                'patient_id' => $patient_id,
                'full_name' => $bill['patient_name'],
                'patient_number' => $bill['patient_number'],
                'phone' => $bill['phone'],
                'gender' => $bill['gender'],
                'date_of_birth' => $bill['date_of_birth'],
                'address' => $bill['address'],
                'blood_group' => $bill['blood_group'],
                'email' => $bill['email'],
                'doctor_name' => $bill['doctor_name'],
                'bills' => []
            ];
        }
        
        $bill['items'] = $bill['items'] ?? [];
        $patient_map[$patient_id]['bills'][] = $bill;
    }

    $patient_bills_data = array_values($patient_map);

    // ================================================================
    // CALCULATE TOTALS
    // ================================================================
    $total_patients = count($patient_bills_data);
    $total_bills = count($bills);
    $total_remaining = 0;
    $total_amount = 0;
    $total_paid = 0;
    $total_discount = 0;

    foreach ($bills as $bill) {
        $total_remaining += (float)$bill['balance'];
        $total_amount += (float)$bill['total_amount'];
        $total_paid += (float)$bill['paid_amount'];
        $total_discount += (float)($bill['total_discount'] ?? 0);
    }

    $has_selected_bill = $selected_bill_id > 0 && !empty($bills);
    $selected_bill = null;
    if ($has_selected_bill) {
        foreach ($bills as $bill) {
            if ($bill['id'] == $selected_bill_id) {
                $selected_bill = $bill;
                break;
            }
        }
    }

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $bills = [];
    $patient_bills_data = [];
    $total_bills = 0;
    $total_patients = 0;
    $total_remaining = 0;
    $total_amount = 0;
    $total_paid = 0;
    $total_discount = 0;
    $has_selected_bill = false;
    $selected_bill = null;
    $currency = 'TSh';
    error_log("Make payment error: " . $e->getMessage());
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payments - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --primary-light: #34D399;
            --primary-bg: #D1FAE5;
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
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.1);
            --page-header-bg-from: #059669;
            --page-header-bg-to: #047857;
            --page-header-shadow: rgba(5, 150, 105, 0.25);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.4);
            --primary-bg: #1A3A2A;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --purple-bg: #2D1B5F;
            --page-header-bg-from: #047857;
            --page-header-bg-to: #065F46;
            --page-header-shadow: rgba(5, 150, 105, 0.15);
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
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--page-header-bg-from), var(--page-header-bg-to));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px var(--page-header-shadow);
            position: relative;
            overflow: hidden;
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
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-box:hover {
            border-color: var(--success);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .stat-box .number { font-size: 1.6rem; font-weight: 700; }
        .stat-box .number.green { color: var(--success); }
        .stat-box .number.orange { color: var(--warning); }
        .stat-box .number.red { color: var(--danger); }
        .stat-box .number.purple { color: var(--purple); }
        .stat-box .number.blue { color: #0B5ED7; }
        .stat-box .label { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; margin-top: 2px; }
        
        .patient-card {
            background: var(--bg-card);
            border-radius: 14px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .patient-card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        .patient-card .card-header {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            color: white;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .patient-card .card-header:hover { background: var(--success-dark); }
        .patient-card .card-header .patient-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .patient-card .card-header .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
        }
        .patient-card .card-header .patient-name { font-weight: 600; font-size: 1rem; }
        .patient-card .card-header .patient-id { font-size: 0.75rem; opacity: 0.8; font-family: monospace; }
        .patient-card .card-header .patient-details {
            display: flex;
            gap: 14px;
            font-size: 0.75rem;
            opacity: 0.85;
            flex-wrap: wrap;
        }
        .patient-card .card-header .patient-details span { display: flex; align-items: center; gap: 4px; }
        .patient-card .card-header .bill-summary {
            display: flex;
            gap: 16px;
            font-size: 0.75rem;
            background: rgba(255,255,255,0.1);
            padding: 4px 14px;
            border-radius: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        .patient-card .card-header .bill-summary .amount { font-weight: 700; font-size: 0.9rem; }
        .patient-card .card-body { padding: 0; }
        .patient-card .card-body.collapsed { display: none; }
        
        .bill-row {
            transition: all 0.3s ease;
        }
        .bill-row:hover td {
            background: var(--primary-bg);
        }
        
        .bill-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--success);
            border-radius: 4px;
        }
        .bill-checkbox:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .bill-status {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .bill-status.partial { background: #DBEAFE; color: #2563EB; }
        .bill-status.paid { background: #D1FAE5; color: #059669; }
        .bill-status.pending { background: #FEF3C7; color: #D97706; }
        
        .master-table-wrap {
            overflow-x: auto;
            padding: 0;
        }
        .master-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            min-width: 1000px;
        }
        .master-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 3px solid var(--border-color);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .master-table thead th:first-child { text-align: center; width: 40px; }
        .master-table tbody td { 
            padding: 8px 12px; 
            border-bottom: 1px solid var(--border-color); 
            color: var(--text-primary); 
            vertical-align: middle;
        }
        .master-table tbody tr:hover td { background: var(--table-hover); }
        .master-table tbody tr.selected td { background: var(--primary-bg); }
        
        .expand-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary);
            font-size: 0.7rem;
            padding: 4px 12px;
            border-radius: 6px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-body);
            border: 1px solid var(--border-color);
        }
        .expand-btn:hover { 
            background: var(--primary-bg);
            border-color: var(--success);
        }
        .expand-btn .badge-count {
            background: var(--success);
            color: white;
            border-radius: 50%;
            padding: 0 6px;
            font-size: 0.55rem;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
        }
        
        .items-container {
            display: none;
            padding: 8px 0 8px 30px;
        }
        .items-container.open { display: block; }
        
        .items-detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            background: var(--bg-body);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .items-detail-table thead th {
            text-align: left;
            padding: 4px 10px;
            font-weight: 600;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        .items-detail-table tbody td {
            padding: 4px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
            font-size: 0.75rem;
        }
        .items-detail-table tbody tr.paid-item td { opacity: 0.6; background: var(--success-bg); }
        .items-detail-table tbody tr.pending-item td { background: var(--warning-bg); }
        .items-detail-table .item-badge {
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.55rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .items-detail-table .item-badge.paid { background: var(--success-bg); color: var(--success); }
        .items-detail-table .item-badge.pending { background: var(--warning-bg); color: var(--warning); }
        .items-detail-table .items-total-row td {
            font-weight: 700;
            border-top: 2px solid var(--success);
            background: var(--primary-bg);
            padding: 6px 10px;
        }
        
        .payment-controls {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            position: sticky;
            bottom: 0;
            z-index: 20;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.05);
        }
        .payment-controls .control-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .payment-controls .control-group label { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); }
        .payment-controls select {
            padding: 6px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            width: 160px;
            font-family: monospace;
        }
        .payment-controls select:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
        }
        .payment-controls .selected-count {
            font-size: 0.8rem;
            color: var(--text-secondary);
            padding: 4px 14px;
            background: var(--bg-body);
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }
        .payment-controls .selected-count strong { color: var(--primary); }
        .payment-controls .divider { width: 1px; height: 30px; background: var(--border-color); }
        
        .total-display {
            display: flex;
            align-items: center;
            gap: 16px;
            background: var(--primary-bg);
            padding: 4px 16px;
            border-radius: 10px;
            border: 2px solid var(--success);
        }
        .total-display .total-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 4px 8px;
        }
        .total-display .total-item .label { font-size: 0.6rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; }
        .total-display .total-item .value { font-size: 1rem; font-weight: 700; color: var(--primary); font-family: monospace; }
        .total-display .total-item .value.grand { color: var(--danger); font-size: 1.2rem; }
        .total-display .total-item .value.green { color: var(--success); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: var(--success-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: var(--success); color: var(--success); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: var(--danger-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
        .btn-sm { padding: 4px 12px; font-size: 0.75rem; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }
        
        .toast-custom {
            position: fixed;
            bottom: 80px;
            right: 24px;
            padding: 12px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 420px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
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
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .master-table { font-size: 0.7rem; min-width: 700px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .payment-controls { flex-direction: column; align-items: stretch; }
            .payment-controls .control-group { justify-content: center; }
            .payment-controls .btn { width: 100%; justify-content: center; }
            .patient-card .card-header { flex-direction: column; align-items: stretch; }
            .patient-card .card-header .patient-details { font-size: 0.65rem; }
            .total-display { flex-wrap: wrap; justify-content: center; }
        }
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .page-header .btn-outline-light { padding: 4px 10px; font-size: 0.7rem; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-money-bill-wave"></i>
                Complete Payments
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_admin): ?>
                    <span class="header-badge" style="background:rgba(124,58,237,0.3);border-color:rgba(124,58,237,0.3);color:#C4B5FD;">
                        <i class="fas fa-user-shield"></i> ADMIN VIEW
                    </span>
                <?php endif; ?>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FCD34D;">
                        <i class="fas fa-eye"></i> RECEPTION
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                Complete partial payments - No additional discount
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_bills ?> partial bill(s)
                </span>
                
                <span class="header-badge" style="background:rgba(217,119,6,0.2);border-color:rgba(217,119,6,0.3);">
                    <i class="fas fa-money-bill"></i>
                    Remaining: <?= $currency ?> <?= number_format($total_remaining, 0) ?>
                </span>
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-check-double"></i>
                    Complete payment only
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($message) && $message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>" style="padding:12px 16px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;gap:10px;background:var(--success-bg);border:1px solid var(--success);color:var(--success);">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- STATISTICS -->
    <div class="stats-grid">
        <div class="stat-box">
            <p class="number purple"><?= $total_patients ?></p>
            <p class="label">👤 Patients</p>
        </div>
        <div class="stat-box">
            <p class="number orange"><?= $total_bills ?></p>
            <p class="label">📋 Partial Bills</p>
        </div>
        <div class="stat-box">
            <p class="number blue"><?= $currency ?> <?= number_format($total_amount, 0) ?></p>
            <p class="label">💰 Total Amount</p>
        </div>
        <div class="stat-box">
            <p class="number green"><?= $currency ?> <?= number_format($total_paid, 0) ?></p>
            <p class="label">✅ Already Paid</p>
        </div>
        <div class="stat-box">
            <p class="number orange"><?= $currency ?> <?= number_format($total_discount, 0) ?></p>
            <p class="label">🏷️ Total Discount</p>
        </div>
        <div class="stat-box">
            <p class="number red" id="totalRemaining"><?= $currency ?> <?= number_format($total_remaining, 0) ?></p>
            <p class="label">💰 Remaining Balance</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PARTIAL BILLS LIST -->
    <!-- ================================================================ -->
    <?php if (count($patient_bills_data) > 0): ?>
        <?php foreach ($patient_bills_data as $patient): 
            $patient_bills = isset($patient['bills']) && is_array($patient['bills']) ? $patient['bills'] : [];
            $patient_total_remaining = 0;
            $patient_total_amount = 0;
            $patient_total_paid = 0;
            $patient_total_discount = 0;
            foreach ($patient_bills as $bill) {
                $patient_total_remaining += (float)$bill['balance'];
                $patient_total_amount += (float)$bill['total_amount'];
                $patient_total_paid += (float)$bill['paid_amount'];
                $patient_total_discount += (float)($bill['total_discount'] ?? 0);
            }
            $doctor_name = $patient['doctor_name'] ?? 'Not Assigned';
            $is_selected_patient = $has_selected_bill && $selected_bill && $selected_bill['patient_id'] == $patient['patient_id'];
        ?>
        <div class="patient-card animate-fade-in-up" data-patient-id="<?= $patient['patient_id'] ?>" style="animation-delay:0.1s;">
            <div class="card-header" onclick="togglePatientCard(this)">
                <div class="patient-info">
                    <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
                        <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></div>
                        <div class="patient-id"><?= htmlspecialchars($patient['patient_number']) ?></div>
                        <div style="font-size:0.65rem; opacity:0.8;">
                            <i class="fas fa-user-md"></i> Doctor: <?= htmlspecialchars($doctor_name) ?>
                        </div>
                    </div>
                    <div class="patient-details">
                        <?php if (!empty($patient['phone'])): ?>
                            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($patient['gender'])): ?>
                            <span><i class="fas fa-<?= $patient['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['gender']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div class="bill-summary">
                        <span>Bills: <strong><?= count($patient_bills) ?></strong></span>
                        <span>|</span>
                        <span>Remaining: <strong class="amount" style="color: <?= $patient_total_remaining > 0 ? '#fcd34d' : '#34d399' ?>;">
                            <?= $currency ?> <?= number_format($patient_total_remaining, 0) ?>
                        </strong></span>
                        <?php if ($patient_total_discount > 0): ?>
                            <span>|</span>
                            <span style="color:var(--warning);">Disc: <?= $currency ?> <?= number_format($patient_total_discount, 0) ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="card-toggle" onclick="event.stopPropagation(); togglePatientCard(this.closest('.card-header'))">
                        <i class="fas fa-chevron-<?= $is_selected_patient ? 'up' : 'down' ?>"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body <?= $is_selected_patient ? '' : 'collapsed' ?>">
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <tr>
                                <th style="width:40px; text-align:center;">
                                    <input type="checkbox" class="bill-checkbox patient-select-all" 
                                           data-patient-id="<?= $patient['patient_id'] ?>" 
                                           onchange="selectPatientBills(this, <?= $patient['patient_id'] ?>)" 
                                           title="Select all bills for this patient">
                                </th>
                                <th style="min-width:120px;">Bill #</th>
                                <th style="min-width:100px;">Visit</th>
                                <th style="min-width:200px;">Items</th>
                                <th style="text-align:right; min-width:100px;">Total</th>
                                <th style="text-align:right; min-width:100px;">Paid</th>
                                <th style="text-align:right; min-width:100px;">Remaining</th>
                                <th style="text-align:center; min-width:80px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patient_bills as $bill): 
                                $remaining = (float)$bill['balance'];
                                $pending_items = (int)$bill['pending_items'];
                                $paid_items = (int)$bill['paid_items'];
                                $total_items = $pending_items + $paid_items;
                                $items = isset($bill['items']) && is_array($bill['items']) ? $bill['items'] : [];
                            ?>
                            <tr class="bill-row <?= ($has_selected_bill && $bill['id'] == $selected_bill_id) ? 'selected' : '' ?>" 
                                data-bill-id="<?= $bill['id'] ?>" 
                                data-remaining="<?= $remaining ?>" 
                                data-total="<?= $bill['total_amount'] ?>">
                                <td style="text-align:center;">
                                    <?php if ($remaining > 0): ?>
                                        <input type="checkbox" class="bill-checkbox bill-select" 
                                               data-id="<?= $bill['id'] ?>" 
                                               data-patient-id="<?= $patient['patient_id'] ?>"
                                               <?= ($has_selected_bill && $bill['id'] == $selected_bill_id) ? 'checked' : '' ?>
                                               onchange="updateSelectedTotal()">
                                    <?php else: ?>
                                        <span style="color:var(--success); font-size:0.8rem;" title="All paid">
                                            <i class="fas fa-check-circle"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-mono text-xs font-semibold" style="color:var(--success);">
                                        <?= htmlspecialchars($bill['bill_number']) ?>
                                    </span>
                                    <?php if (($bill['total_discount'] ?? 0) > 0): ?>
                                        <br><span style="font-size:0.5rem;color:var(--warning);">
                                            <i class="fas fa-tag"></i> Disc: <?= $currency ?> <?= number_format($bill['total_discount'] ?? 0, 0) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-xs">
                                        <?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?>
                                    </span>
                                    <span class="text-xs block" style="color:var(--text-secondary);">
                                        <?= date('d/m/Y', strtotime($bill['created_at'])) ?>
                                    </span>
                                    <?php if ($bill['doctor_name'] && $bill['doctor_name'] !== 'Not Assigned'): ?>
                                        <span class="text-xs block" style="color:var(--primary);">
                                            <i class="fas fa-user-md"></i> <?= htmlspecialchars($bill['doctor_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="bill-items">
                                        <button class="expand-btn" onclick="toggleItems(this)" 
                                                id="items-btn-<?= $bill['id'] ?>" 
                                                data-count="<?= $total_items ?>">
                                            <i class="fas fa-chevron-right" id="items-icon-<?= $bill['id'] ?>"></i>
                                            <span>Show Items</span>
                                            <span class="badge-count"><?= $total_items ?></span>
                                            <?php if ($paid_items > 0): ?>
                                                <span style="color:var(--success); font-size:0.6rem;">✅ <?= $paid_items ?> paid</span>
                                            <?php endif; ?>
                                            <?php if ($pending_items > 0): ?>
                                                <span style="color:var(--warning); font-size:0.6rem;">⏳ <?= $pending_items ?> pending</span>
                                            <?php endif; ?>
                                        </button>
                                        
                                        <!-- ITEMS DETAIL -->
                                        <div class="items-container" id="items-container-<?= $bill['id'] ?>" style="display:none;">
                                            <table class="items-detail-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:35%;">Item Name</th>
                                                        <th style="width:12%;">Type</th>
                                                        <th style="width:8%; text-align:center;">Qty</th>
                                                        <th style="width:15%; text-align:right;">Unit Price</th>
                                                        <th style="width:18%; text-align:right;">Total</th>
                                                        <th style="width:12%; text-align:center;">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    foreach ($items as $item): 
                                                        $is_paid = ($item['status'] === 'paid');
                                                        $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                                        $unit_price = (float)($item['unit_price'] ?? 0);
                                                        $qty = (int)($item['quantity'] ?? 1);
                                                    ?>
                                                        <tr class="<?= $is_paid ? 'paid-item' : 'pending-item' ?>">
                                                            <td>
                                                                <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                                                                <?php if (!empty($item['description'])): ?>
                                                                    <br><small style="color:var(--text-secondary);"><?= htmlspecialchars($item['description']) ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span style="font-size:0.55rem; background:var(--bg-body); padding:1px 8px; border-radius:4px; border:1px solid var(--border-color);">
                                                                    <?= ucfirst($item['item_type'] ?? 'item') ?>
                                                                </span>
                                                            </td>
                                                            <td style="text-align:center;"><?= $qty ?></td>
                                                            <td style="text-align:right; font-family:monospace;">
                                                                <?= $currency ?> <?= number_format($unit_price, 0) ?>
                                                            </td>
                                                            <td style="text-align:right; font-family:monospace; font-weight:600; <?= $is_paid ? 'color:var(--success);' : 'color:var(--danger);' ?>">
                                                                <?= $currency ?> <?= number_format($price, 0) ?>
                                                            </td>
                                                            <td style="text-align:center;">
                                                                <span class="item-badge <?= $is_paid ? 'paid' : 'pending' ?>">
                                                                    <?= $is_paid ? '✅ Paid' : '⏳ Pending' ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr class="items-total-row">
                                                        <td colspan="4" style="text-align:right; font-weight:700; color:var(--text-primary);">
                                                            <i class="fas fa-calculator"></i> Total:
                                                        </td>
                                                        <td style="text-align:right; font-weight:700; color:var(--success); font-family:monospace; font-size:0.9rem;">
                                                            <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                                                        </td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:right; font-weight:700; color:var(--success); font-family:monospace;">
                                    <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                                </td>
                                <td style="text-align:right; font-weight:600; color:var(--success); font-family:monospace;">
                                    <?= $currency ?> <?= number_format($bill['paid_amount'] ?? 0, 0) ?>
                                </td>
                                <td style="text-align:right; font-weight:700; color:<?= $remaining > 0 ? 'var(--danger)' : 'var(--success)' ?>; font-family:monospace;">
                                    <?= $currency ?> <?= number_format($remaining, 0) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="bill-status partial">🔄 Partial</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Patient Total Row -->
                            <tr style="background:var(--primary-bg);font-weight:700;border-top:3px solid var(--success);">
                                <td colspan="3" style="text-align:right; font-weight:700; font-size:0.9rem; color:var(--text-primary);">
                                    <i class="fas fa-user"></i> PATIENT TOTAL:
                                </td>
                                <td></td>
                                <td style="text-align:right; font-weight:700; color:var(--success); font-family:monospace;">
                                    <?= $currency ?> <?= number_format($patient_total_amount, 0) ?>
                                </td>
                                <td style="text-align:right; font-weight:700; color:var(--success); font-family:monospace;">
                                    <?= $currency ?> <?= number_format($patient_total_paid, 0) ?>
                                </td>
                                <td style="text-align:right; font-weight:700; color:<?= $patient_total_remaining > 0 ? 'var(--danger)' : 'var(--success)' ?>; font-family:monospace; font-size:1.1rem;">
                                    <?= $currency ?> <?= number_format($patient_total_remaining, 0) ?>
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center py-12" style="background:var(--bg-card);border-radius:16px;border:2px solid var(--border-color);padding:60px 20px;">
            <i class="fas fa-check-circle text-5xl" style="color:var(--success);display:block;margin-bottom:16px;"></i>
            <h3 class="text-xl font-semibold" style="color:var(--text-primary);">No Partial Bills</h3>
            <p style="color:var(--text-secondary);margin-top:8px;">All bills are either fully paid or pending. No partial payments to complete.</p>
            <a href="dashboard.php" class="btn btn-success mt-4">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PAYMENT CONTROLS - NO DISCOUNT -->
    <!-- ================================================================ -->
    <div class="payment-controls" id="paymentControls">
        <div class="control-group">
            <label><i class="fas fa-hand-holding-usd"></i> Method:</label>
            <select id="paymentMethod">
                <option value="cash">💰 Cash</option>
                <option value="m-pesa">📱 M-Pesa</option>
                <option value="airtel_money">📱 Airtel Money</option>
                <option value="tigo_pesa">📱 Tigo Pesa</option>
                <option value="halopesa">📱 HaloPesa</option>
                <option value="card">💳 Card</option>
                <option value="bank">🏦 Bank Transfer</option>
                <option value="insurance">🏥 Insurance</option>
                <option value="other">📦 Other</option>
            </select>
        </div>
        
        <div class="divider"></div>
        
        <div class="total-display" id="totalDisplay">
            <div class="total-item">
                <span class="label">Selected Bills</span>
                <span class="value" id="selectedCount">0</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item">
                <span class="label">Remaining Total</span>
                <span class="value" id="displayTotal"><?= $currency ?> 0</span>
            </div>
            <div style="color:var(--border-color);">|</div>
            <div class="total-item">
                <span class="label">Discount Already Applied</span>
                <span class="value" style="color:var(--warning);" id="displayDiscount"><?= $currency ?> 0</span>
            </div>
        </div>
        
        <div style="flex:1; display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;">
            <span class="selected-count">
                Selected: <strong id="selectedCountNum">0</strong> bills
            </span>
            <button onclick="selectAllBills()" class="btn btn-outline btn-sm">
                <i class="fas fa-check-double"></i> Select All
            </button>
            <button onclick="deselectAllBills()" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Deselect All
            </button>
            <button onclick="completePayment()" class="btn btn-success" id="completePayBtn">
                <i class="fas fa-check-circle"></i> COMPLETE PAYMENT
            </button>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Complete Payments
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
                <?php if ($is_reception): ?>
                    <span style="color:#FCD34D;font-weight:500;font-size:0.6rem;background:rgba(251,191,36,0.15);padding:2px 10px;border-radius:10px;margin-left:4px;">👀 Reception</span>
                <?php endif; ?>
            </span>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
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
    (function() {
        var htmlElement = document.documentElement;
        function syncDarkMode() {
            var isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        }
        syncDarkMode();
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') syncDarkMode();
        });
        document.addEventListener('darkModeChanged', function(e) {
            if (e.detail && e.detail.isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        });
    })();

    function togglePatientCard(header) {
        var card = header.closest('.patient-card');
        var body = card.querySelector('.card-body');
        var icon = header.querySelector('.card-toggle i');
        if (body.classList.contains('collapsed')) {
            body.classList.remove('collapsed');
            if (icon) icon.className = 'fas fa-chevron-up';
        } else {
            body.classList.add('collapsed');
            if (icon) icon.className = 'fas fa-chevron-down';
        }
    }

    function toggleItems(element) {
        var container = element.parentElement.querySelector('.items-container');
        var icon = element.querySelector('.fa-chevron-right');
        if (!icon) {
            icon = element.querySelector('.fa-chevron-down');
        }
        if (container) {
            if (container.style.display === 'none' || container.style.display === '') {
                container.style.display = 'block';
                container.classList.add('open');
                if (icon) {
                    icon.className = 'fas fa-chevron-down';
                }
                var text = element.querySelector('span:not(.badge-count)');
                if (text) text.textContent = ' Hide Items';
            } else {
                container.style.display = 'none';
                container.classList.remove('open');
                if (icon) {
                    icon.className = 'fas fa-chevron-right';
                }
                var text = element.querySelector('span:not(.badge-count)');
                if (text) text.textContent = ' Show Items';
            }
        }
    }

    function selectPatientBills(checkbox, patientId) {
        var checkboxes = document.querySelectorAll('.bill-select[data-patient-id="' + patientId + '"]');
        checkboxes.forEach(function(cb) {
            cb.checked = checkbox.checked;
        });
        updateSelectedTotal();
    }

    function selectAllBills() {
        var checkboxes = document.querySelectorAll('.bill-select:not(:disabled)');
        checkboxes.forEach(function(cb) {
            cb.checked = true;
        });
        document.querySelectorAll('.patient-select-all').forEach(function(cb) {
            var patientId = cb.dataset.patientId;
            var patientCheckboxes = document.querySelectorAll('.bill-select[data-patient-id="' + patientId + '"]');
            var allChecked = true;
            patientCheckboxes.forEach(function(pcb) {
                if (!pcb.checked) allChecked = false;
            });
            cb.checked = allChecked && patientCheckboxes.length > 0;
        });
        updateSelectedTotal();
    }

    function deselectAllBills() {
        document.querySelectorAll('.bill-select').forEach(function(cb) {
            cb.checked = false;
        });
        document.querySelectorAll('.patient-select-all').forEach(function(cb) {
            cb.checked = false;
        });
        updateSelectedTotal();
    }

    // ================================================================
    // UPDATE SELECTED TOTAL
    // ================================================================
    function updateSelectedTotal() {
        var checkboxes = document.querySelectorAll('.bill-select:checked');
        var count = checkboxes.length;
        var total_remaining = 0;
        var total_discount = 0;
        
        checkboxes.forEach(function(cb) {
            var row = cb.closest('.bill-row');
            if (row) {
                var remaining = parseFloat(row.dataset.remaining || 0);
                total_remaining += remaining;
                // Get discount from row
                var discText = row.querySelector('[style*="color:var(--warning)"]');
                if (discText) {
                    var match = discText.textContent.match(/[\d,]+/);
                    if (match) {
                        total_discount += parseFloat(match[0].replace(/,/g, ''));
                    }
                }
            }
        });
        
        var currency = '<?= $currency ?>';
        document.getElementById('selectedCountNum').textContent = count;
        document.getElementById('selectedCount').textContent = count;
        document.getElementById('displayTotal').textContent = currency + ' ' + total_remaining.toFixed(0);
        document.getElementById('displayDiscount').textContent = currency + ' ' + total_discount.toFixed(0);
        
        // Update patient select all checkboxes
        document.querySelectorAll('.patient-select-all').forEach(function(cb) {
            var patientId = cb.dataset.patientId;
            var patientCheckboxes = document.querySelectorAll('.bill-select[data-patient-id="' + patientId + '"]');
            var allChecked = true;
            patientCheckboxes.forEach(function(pcb) {
                if (!pcb.checked) allChecked = false;
            });
            cb.checked = allChecked && patientCheckboxes.length > 0;
        });
        
        // Enable/disable complete button
        var btn = document.getElementById('completePayBtn');
        if (count === 0) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Select Bills First';
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> COMPLETE PAYMENT (' + currency + ' ' + total_remaining.toFixed(0) + ')';
        }
    }

    // ================================================================
    // COMPLETE PAYMENT
    // ================================================================
    function completePayment() {
        var checkboxes = document.querySelectorAll('.bill-select:checked');
        var billIds = [];
        checkboxes.forEach(function(cb) {
            billIds.push(parseInt(cb.dataset.id));
        });
        
        if (billIds.length === 0) {
            showToast('⚠️ No Selection', 'Please select at least one bill', 'warning');
            return;
        }
        
        var paymentMethod = document.getElementById('paymentMethod').value;
        var totalRemaining = 0;
        checkboxes.forEach(function(cb) {
            var row = cb.closest('.bill-row');
            if (row) {
                totalRemaining += parseFloat(row.dataset.remaining || 0);
            }
        });
        
        var currency = '<?= $currency ?>';
        var confirmMsg = '💰 COMPLETE PAYMENT CONFIRMATION\n' +
                         '═══════════════════════════════\n' +
                         'Selected Bills: ' + billIds.length + '\n' +
                         'Total Remaining: ' + currency + ' ' + totalRemaining.toFixed(0) + '\n' +
                         'Payment Method: ' + paymentMethod.toUpperCase() + '\n\n' +
                         'Confirm complete payment for these bills?';
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        var btn = document.getElementById('completePayBtn');
        var originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> Processing...';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', 'complete_payment');
        formData.append('payment_method', paymentMethod);
        billIds.forEach(function(id) {
            formData.append('bill_ids[]', id);
        });
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Success', data.message, 'success');
                setTimeout(function() { window.location.reload(); }, 2500);
            } else {
                showToast('❌ Error', data.message, 'error');
            }
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            updateSelectedTotal();
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 4000);
    }

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
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
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($has_selected_bill && $selected_bill): ?>
            var billCheckbox = document.querySelector('.bill-select[data-id="<?= $selected_bill_id ?>"]');
            if (billCheckbox) {
                billCheckbox.checked = true;
            }
            var patientCard = document.querySelector('.patient-card[data-patient-id="<?= $selected_bill['patient_id'] ?>"]');
            if (patientCard) {
                var body = patientCard.querySelector('.card-body');
                if (body) {
                    body.classList.remove('collapsed');
                }
                var header = patientCard.querySelector('.card-header');
                if (header) {
                    var icon = header.querySelector('.card-toggle i');
                    if (icon) icon.className = 'fas fa-chevron-up';
                }
            }
        <?php endif; ?>
        updateSelectedTotal();
    });

    console.log('%c💰 Braick - Complete Payments (Partial Bills Only)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Shows only bills with status = partial', 'font-size:13px; color:#34D399;');
    console.log('%c✅ No discount field - discounts already applied', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Shows Total, Paid, Remaining, and Discount', 'font-size:13px; color:#34D399;');
    console.log('%c✅ One button: COMPLETE PAYMENT', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>