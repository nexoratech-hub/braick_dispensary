<?php
// ================================================================
// FILE: frontend/pages/cashier/payments.php
// NEW PAYMENT - USING YOUR DATABASE STRUCTURE
// ALLOWS RECEPTION, CASHIER AND ADMIN
// BRAICK DISPENSARY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET SYSTEM SETTINGS
// ================================================================
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'currency') {
            $currency = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    $currency = 'TSh';
}

// ================================================================
// GET PATIENT BY ID OR SEARCH
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$patient = null;
$bills = [];
$bill_items_details = [];
$current_bill = null;

if ($patient_id > 0) {
    // Get patient details
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ? AND branch_id = ?");
    $stmt->execute([$patient_id, $user_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient) {
        // Get patient bills - using bills table
        $stmt = $db->prepare("
            SELECT 
                b.*,
                (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count
            FROM bills b
            WHERE b.patient_id = ? AND b.branch_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$patient_id, $user_branch_id]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================================
// SEARCH PATIENTS
// ================================================================
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_results = [];

if (!empty($search_query)) {
    $stmt = $db->prepare("
        SELECT id, full_name, patient_id, phone 
        FROM patients 
        WHERE branch_id = ? 
        AND (full_name LIKE ? OR patient_id LIKE ? OR phone LIKE ?)
        LIMIT 10
    ");
    $search_term = "%$search_query%";
    $stmt->execute([$user_branch_id, $search_term, $search_term, $search_term]);
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// GET SERVICES AND MEDICATIONS
// ================================================================

// Services from services table
$services = [];
try {
    $stmt = $db->prepare("
        SELECT id, service_name, price 
        FROM services 
        WHERE is_active = 1 AND (branch_id = ? OR branch_id IS NULL) 
        ORDER BY service_name
    ");
    $stmt->execute([$user_branch_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

// Medications from medications_inventory
$medications = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication_name, selling_price, quantity, unit 
        FROM medications_inventory 
        WHERE branch_id = ? AND status = 'active' AND quantity > 0
        ORDER BY medication_name
    ");
    $stmt->execute([$user_branch_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $medications[] = [
            'id' => $row['id'],
            'name' => $row['medication_name'],
            'price' => $row['selling_price'] ?? 0,
            'quantity' => $row['quantity'] ?? 0,
            'unit' => $row['unit'] ?? 'pcs'
        ];
    }
} catch (Exception $e) {
    $medications = [];
}

// Consultation types
$consultation_types = [
    ['id' => 'new_patient', 'name' => 'New Patient', 'fee' => 10000],
    ['id' => 'general', 'name' => 'General Consultation', 'fee' => 15000],
    ['id' => 'follow_up', 'name' => 'Follow-up', 'fee' => 10000],
    ['id' => 'specialist', 'name' => 'Specialist Consultation', 'fee' => 30000],
    ['id' => 'emergency', 'name' => 'Emergency', 'fee' => 25000]
];

// ================================================================
// PROCESS PAYMENT FORM
// ================================================================
$message = '';
$message_type = '';
$bill_items = [];
$total_amount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // GENERATE BILL - Using bills table
    // ================================================================
    if ($action === 'generate_bill') {
        $patient_id = (int)$_POST['patient_id'];
        $items = json_decode($_POST['items_json'] ?? '[]', true);
        $consultation_type = $_POST['consultation_type'] ?? '';
        $visit_id = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : null;
        
        if ($patient_id <= 0) {
            $message = "Please select a patient!";
            $message_type = 'error';
        } elseif (empty($items) && empty($consultation_type)) {
            $message = "Please add at least one item!";
            $message_type = 'error';
        } else {
            // Calculate total
            $total = 0;
            $bill_items = [];
            
            // Add consultation fee
            if (!empty($consultation_type)) {
                $fee = 0;
                foreach ($consultation_types as $ct) {
                    if ($ct['id'] === $consultation_type) {
                        $fee = $ct['fee'];
                        break;
                    }
                }
                if ($fee > 0) {
                    $total += $fee;
                    $bill_items[] = [
                        'type' => 'consultation',
                        'name' => 'Consultation - ' . ucfirst(str_replace('_', ' ', $consultation_type)),
                        'quantity' => 1,
                        'price' => $fee,
                        'total' => $fee
                    ];
                }
            }
            
            // Add selected items
            foreach ($items as $item) {
                $item_type = $item['type'] ?? '';
                $item_id = (int)($item['item_id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 1);
                
                if ($item_type === 'service') {
                    try {
                        $stmt = $db->prepare("SELECT service_name, price FROM services WHERE id = ? AND is_active = 1");
                        $stmt->execute([$item_id]);
                        $service = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($service) {
                            $price = $service['price'];
                            $subtotal = $price * $quantity;
                            $total += $subtotal;
                            $bill_items[] = [
                                'type' => 'service',
                                'name' => $service['service_name'],
                                'quantity' => $quantity,
                                'price' => $price,
                                'total' => $subtotal
                            ];
                        }
                    } catch (Exception $e) {}
                } elseif ($item_type === 'medication') {
                    try {
                        $stmt = $db->prepare("SELECT medication_name, selling_price FROM medications_inventory WHERE id = ? AND branch_id = ?");
                        $stmt->execute([$item_id, $user_branch_id]);
                        $med = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($med) {
                            $price = $med['selling_price'] ?? 0;
                            $subtotal = $price * $quantity;
                            $total += $subtotal;
                            $bill_items[] = [
                                'type' => 'medication',
                                'name' => $med['medication_name'],
                                'quantity' => $quantity,
                                'price' => $price,
                                'total' => $subtotal
                            ];
                        }
                    } catch (Exception $e) {}
                }
            }
            
            if ($total <= 0) {
                $message = "Total amount cannot be zero!";
                $message_type = 'error';
            } else {
                // Generate bill number
                $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Insert bill - using bills table
                $stmt = $db->prepare("
                    INSERT INTO bills (
                        bill_number, patient_id, visit_id, branch_id, created_by,
                        subtotal, total_amount, paid_amount, balance, status, 
                        payment_method, notes, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 'pending', 'cash', ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $bill_number,
                    $patient_id,
                    $visit_id,
                    $user_branch_id,
                    $user_id,
                    $total,
                    $total,
                    $total,
                    "Bill generated by " . $user_full_name
                ]);
                $bill_id = $db->lastInsertId();
                
                // Insert bill items
                foreach ($bill_items as $item) {
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (
                            bill_id, patient_id, branch_id, item_type, item_name,
                            quantity, unit_price, total_price, status, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
                    ");
                    $stmt->execute([
                        $bill_id,
                        $patient_id,
                        $user_branch_id,
                        $item['type'],
                        $item['name'],
                        $item['quantity'],
                        $item['price'],
                        $item['total']
                    ]);
                }
                
                $message = "Bill generated successfully! Bill #: $bill_number";
                $message_type = 'success';
                
                // Refresh bill data
                $stmt = $db->prepare("
                    SELECT b.*, 
                           (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count
                    FROM bills b
                    WHERE b.id = ?
                ");
                $stmt->execute([$bill_id]);
                $bills = [$stmt->fetch(PDO::FETCH_ASSOC)];
                
                // Redirect to payment
                echo '<script>setTimeout(function(){ window.location.href = "payments.php?bill_id=' . $bill_id . '&patient_id=' . $patient_id . '"; }, 1500);</script>';
            }
        }
    }
    
    // ================================================================
    // PROCESS PAYMENT
    // ================================================================
    if ($action === 'process_payment') {
        $bill_id = (int)$_POST['bill_id'];
        $amount = (float)$_POST['amount'];
        $payment_method = $_POST['payment_method'] ?? 'cash';
        
        if ($bill_id <= 0) {
            $message = "Invalid bill!";
            $message_type = 'error';
        } elseif ($amount <= 0) {
            $message = "Amount must be greater than zero!";
            $message_type = 'error';
        } else {
            // Get bill details - using bills table
            $stmt = $db->prepare("SELECT * FROM bills WHERE id = ? AND branch_id = ?");
            $stmt->execute([$bill_id, $user_branch_id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bill) {
                $message = "Bill not found!";
                $message_type = 'error';
            } else {
                $balance = $bill['balance'];
                
                if ($amount > $balance) {
                    $message = "Amount exceeds balance! Balance: " . $currency . " " . number_format($balance);
                    $message_type = 'error';
                } else {
                    // Generate receipt number
                    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Insert payment
                    $stmt = $db->prepare("
                        INSERT INTO payments (
                            receipt_number, bill_id, patient_id, amount, 
                            payment_method, received_by, branch_id, received_at, notes
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ");
                    $stmt->execute([
                        $receipt_number,
                        $bill_id,
                        $bill['patient_id'],
                        $amount,
                        $payment_method,
                        $user_id,
                        $user_branch_id,
                        'Payment processed by ' . $user_full_name
                    ]);
                    $payment_id = $db->lastInsertId();
                    
                    // Update bill
                    $paid_amount = $bill['paid_amount'] + $amount;
                    $new_balance = $bill['total_amount'] - $paid_amount;
                    $status = ($new_balance <= 0) ? 'paid' : 'partial';
                    
                    $stmt = $db->prepare("
                        UPDATE bills 
                        SET paid_amount = ?, balance = ?, status = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$paid_amount, $new_balance, $status, $bill_id]);
                    
                    $message = "Payment processed successfully! Receipt #: $receipt_number";
                    $message_type = 'success';
                    
                    // Redirect to receipt
                    echo '<script>setTimeout(function(){ window.location.href = "receipt.php?payment_id=' . $payment_id . '"; }, 1500);</script>';
                }
            }
        }
    }
}

// ================================================================
// GET BILL FOR PAYMENT
// ================================================================
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

if ($bill_id > 0) {
    $stmt = $db->prepare("
        SELECT b.*, p.full_name as patient_name, p.patient_id as patient_code
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        WHERE b.id = ? AND b.branch_id = ?
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $current_bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_bill) {
        $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
        $stmt->execute([$bill_id]);
        $bill_items_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>

<style>
    :root {
        --primary: #059669;
        --primary-dark: #047857;
        --primary-light: #34D399;
        --primary-bg: #D1FAE5;
        --success: #059669;
        --success-dark: #047857;
        --danger: #DC2626;
        --warning: #D97706;
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
        --radius: 10px;
        --radius-lg: 14px;
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
        padding: 28px 32px;
        min-height: calc(100vh - 68px);
        transition: background 0.3s ease;
    }
    
    .page-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: var(--radius-lg);
        padding: 20px 28px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
        position: relative;
        overflow: hidden;
    }
    
    .page-header .page-title {
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }
    
    .page-header .page-title i { font-size: 1.6rem; opacity: 0.9; }
    .page-header .page-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
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
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.12);
        padding: 8px 18px;
        border-radius: var(--radius);
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
        background: rgba(255,255,255,0.2);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    .card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 18px 22px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        box-shadow: var(--shadow);
        margin-bottom: 20px;
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
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
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .card-title i { color: var(--primary); font-size: 1.1rem; }
    
    .form-control {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        font-size: 0.85rem;
        transition: all 0.3s;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }
    
    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 4px;
        display: block;
    }
    
    .form-row { margin-bottom: 14px; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: var(--radius);
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s;
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
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
    
    .btn-success {
        background: var(--success);
        color: white;
    }
    .btn-success:hover {
        background: var(--success-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-sm { padding: 4px 12px; font-size: 0.75rem; border-radius: 6px; }
    .btn-block { width: 100%; justify-content: center; }
    
    .payment-item-card {
        background: var(--bg-card);
        border-radius: var(--radius);
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .payment-item-card:hover {
        border-color: var(--primary);
    }
    
    .item-price {
        font-weight: 600;
        color: var(--success);
        font-family: monospace;
    }
    
    .badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
    }
    .badge-paid { background: var(--success); }
    .badge-partial { background: var(--warning); }
    .badge-pending { background: #D97706; }
    .badge-cancelled { background: var(--danger); }
    
    .bill-summary {
        background: var(--bg-body);
        border-radius: var(--radius);
        padding: 14px 18px;
        border: 2px solid var(--border-color);
    }
    
    .bill-summary .total { font-size: 1.3rem; font-weight: 700; color: var(--primary); }
    .bill-summary .balance { font-size: 1.2rem; font-weight: 700; color: var(--danger); }
    .bill-summary .paid { font-size: 1.2rem; font-weight: 700; color: var(--success); }
    
    .empty-state {
        text-align: center;
        padding: 30px 20px;
        color: var(--text-secondary);
    }
    .empty-state i { font-size: 2.5rem; color: var(--border-color); display: block; margin-bottom: 10px; }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
    }
    
    .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .data-table tbody tr:hover td { background: var(--primary-bg); }
    
    .footer {
        padding: 12px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: var(--radius);
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
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: var(--success); }
    .toast-custom.error { background: var(--danger); }
    .toast-custom.info { background: var(--primary); }
    
    @media (max-width: 1024px) {
        .main-content { margin-left: 0; padding: 16px; }
    }
    @media (max-width: 768px) {
        .page-header { padding: 16px 18px; }
        .page-header .page-title { font-size: 1.2rem; }
        .card { padding: 14px 16px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle"></i> New Payment
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.3);color:#34D399;border-color:rgba(52,211,153,0.3);">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-money-bill-wave"></i> Process payments for patients
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);color:#34D399;">
                        <i class="fas fa-user-tag"></i> Reception Access
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>" style="max-width:1400px;margin:0 auto;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- SEARCH PATIENT -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-search"></i> Find Patient
            </h3>
        </div>
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="text" name="search" class="form-control flex-1" 
                   placeholder="Search by name, patient ID, or phone..." 
                   value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
        
        <?php if (!empty($search_query) && count($search_results) > 0): ?>
            <div class="mt-3">
                <p class="text-sm text-gray-500 mb-2">Found <?= count($search_results) ?> patient(s)</p>
                <?php foreach ($search_results as $result): ?>
                    <a href="?patient_id=<?= $result['id'] ?>" class="payment-item-card">
                        <div>
                            <strong><?= htmlspecialchars($result['full_name']) ?></strong>
                            <span class="text-sm text-gray-500 ml-2">ID: <?= htmlspecialchars($result['patient_id']) ?></span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($result['phone'] ?? 'N/A') ?>
                            <i class="fas fa-chevron-right ml-3"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php elseif (!empty($search_query)): ?>
            <p class="text-gray-400 text-center py-3">No patients found</p>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SELECTED PATIENT -->
    <!-- ================================================================ -->
    <?php if ($patient): ?>
    <div class="card" style="border-color: var(--success);">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-user text-green-600 mr-2"></i>
                    <?= htmlspecialchars($patient['full_name']) ?>
                </h3>
                <p class="text-sm text-gray-500">
                    Patient ID: <?= htmlspecialchars($patient['patient_id']) ?> | 
                    Phone: <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?> |
                    Gender: <?= ucfirst($patient['gender'] ?? 'N/A') ?>
                </p>
            </div>
            <a href="payments.php" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT BILLS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-receipt"></i> Patient Bills
                <span class="text-sm font-normal text-gray-400">(<?= count($bills) ?> bills)</span>
            </h3>
        </div>
        
        <?php if (count($bills) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td class="font-mono text-sm"><?= htmlspecialchars($bill['bill_number']) ?></td>
                                <td class="font-semibold"><?= $currency ?> <?= number_format($bill['total_amount']) ?></td>
                                <td><?= $currency ?> <?= number_format($bill['paid_amount']) ?></td>
                                <td class="font-semibold <?= $bill['balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    <?= $currency ?> <?= number_format($bill['balance']) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $bill['status'] === 'paid' ? 'badge-paid' : ($bill['status'] === 'partial' ? 'badge-partial' : 'badge-pending') ?>">
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-sm"><?= date('d/m/Y H:i', strtotime($bill['created_at'])) ?></td>
                                <td>
                                    <?php if ($bill['balance'] > 0 && $bill['status'] !== 'cancelled'): ?>
                                        <a href="?bill_id=<?= $bill['id'] ?>&patient_id=<?= $patient_id ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-money-bill-wave"></i> Pay
                                        </a>
                                    <?php elseif ($bill['status'] === 'paid'): ?>
                                        <span class="text-green-600 text-sm">✓ Paid</span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-sm">Cancelled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No bills found for this patient</p>
                <p class="text-xs text-gray-400 mt-1">Generate a new bill below</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- GENERATE NEW BILL -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-plus-circle" style="color:var(--success);"></i> Generate New Bill
            </h3>
        </div>
        
        <form method="POST" id="billForm">
            <input type="hidden" name="action" value="generate_bill">
            <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>">
            <input type="hidden" name="items_json" id="itemsJson" value="[]">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Consultation Type -->
                <div>
                    <label class="form-label">Consultation Type</label>
                    <select name="consultation_type" id="consultationType" class="form-control">
                        <option value="">No Consultation</option>
                        <?php foreach ($consultation_types as $ct): ?>
                            <option value="<?= $ct['id'] ?>">
                                <?= $ct['name'] ?> - <?= $currency ?> <?= number_format($ct['fee']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Services -->
                <div>
                    <label class="form-label">Add Service</label>
                    <div class="flex gap-2">
                        <select id="serviceSelect" class="form-control flex-1">
                            <option value="">Select Service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service['id'] ?>" data-price="<?= $service['price'] ?>">
                                    <?= htmlspecialchars($service['service_name']) ?> - <?= $currency ?> <?= number_format($service['price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="addService()" class="btn btn-primary btn-sm">Add</button>
                    </div>
                </div>
                
                <!-- Medications -->
                <div class="md:col-span-2">
                    <label class="form-label">Add Medication (from inventory)</label>
                    <div class="flex gap-2">
                        <select id="medicationSelect" class="form-control flex-1">
                            <option value="">Select Medication</option>
                            <?php foreach ($medications as $med): ?>
                                <option value="<?= $med['id'] ?>" data-price="<?= $med['price'] ?>" data-qty="<?= $med['quantity'] ?>">
                                    <?= htmlspecialchars($med['name']) ?> (<?= $med['unit'] ?? 'pcs' ?>) - <?= $currency ?> <?= number_format($med['price']) ?> | Stock: <?= $med['quantity'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="medicationQty" class="form-control w-24" placeholder="Qty" value="1" min="1">
                        <button type="button" onclick="addMedication()" class="btn btn-success btn-sm">Add</button>
                    </div>
                </div>
            </div>
            
            <!-- Bill Items List -->
            <div class="mt-4" id="billItemsContainer">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-semibold text-gray-700">Bill Items</h4>
                    <span class="text-sm text-gray-500" id="itemCount">0 items</span>
                </div>
                <div id="billItemsList" class="space-y-2">
                    <p class="text-gray-400 text-sm text-center py-4" id="emptyItemsMsg">No items added yet</p>
                </div>
                <div class="bill-summary mt-3">
                    <div class="flex justify-between">
                        <span>Total Amount:</span>
                        <span class="total" id="totalAmount"><?= $currency ?> 0</span>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-success btn-block">
                    <i class="fas fa-file-invoice"></i> Generate Bill
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PROCESS PAYMENT -->
    <!-- ================================================================ -->
    <?php elseif ($current_bill): ?>
    <div class="card" style="border-color: var(--success);">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-money-bill-wave" style="color:var(--success);"></i> Process Payment
            </h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Bill Summary -->
            <div>
                <p><strong>Bill #:</strong> <?= htmlspecialchars($current_bill['bill_number']) ?></p>
                <p><strong>Patient:</strong> <?= htmlspecialchars($current_bill['patient_name']) ?></p>
                <p><strong>Patient ID:</strong> <?= htmlspecialchars($current_bill['patient_code']) ?></p>
                <div class="bill-summary mt-3">
                    <div class="flex justify-between">
                        <span>Total Amount:</span>
                        <span class="total"><?= $currency ?> <?= number_format($current_bill['total_amount']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Paid Amount:</span>
                        <span class="paid"><?= $currency ?> <?= number_format($current_bill['paid_amount']) ?></span>
                    </div>
                    <div class="flex justify-between border-t pt-2 mt-2">
                        <span class="font-bold">Balance:</span>
                        <span class="balance"><?= $currency ?> <?= number_format($current_bill['balance']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Status:</span>
                        <span class="badge <?= $current_bill['status'] === 'paid' ? 'badge-paid' : ($current_bill['status'] === 'partial' ? 'badge-partial' : 'badge-pending') ?>">
                            <?= ucfirst($current_bill['status'] ?? 'Pending') ?>
                        </span>
                    </div>
                </div>
                
                <!-- Bill Items -->
                <div class="mt-3">
                    <h4 class="font-semibold text-gray-700 mb-2">Items</h4>
                    <?php foreach ($bill_items_details as $item): ?>
                        <div class="payment-item-card">
                            <div>
                                <span class="font-medium"><?= htmlspecialchars($item['item_name']) ?></span>
                                <span class="text-sm text-gray-500">x<?= $item['quantity'] ?></span>
                                <span class="text-xs text-gray-400 ml-2"><?= ucfirst($item['item_type'] ?? 'item') ?></span>
                            </div>
                            <span class="item-price"><?= $currency ?> <?= number_format($item['total_price']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Payment Form -->
            <div>
                <form method="POST">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="bill_id" value="<?= $current_bill['id'] ?>">
                    
                    <div class="form-row">
                        <label class="form-label">Amount to Pay</label>
                        <input type="number" name="amount" class="form-control" 
                               placeholder="Enter amount" 
                               max="<?= $current_bill['balance'] ?>" 
                               value="<?= $current_bill['balance'] ?>" required>
                        <p class="text-xs text-gray-400 mt-1">Max: <?= $currency ?> <?= number_format($current_bill['balance']) ?></p>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="m-pesa">M-Pesa</option>
                            <option value="airtel_money">Airtel Money</option>
                            <option value="tigo_pesa">Tigo Pesa</option>
                            <option value="halopesa">HaloPesa</option>
                            <option value="card">Card</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="insurance">Insurance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-block mt-2">
                        <i class="fas fa-check-circle mr-2"></i> Process Payment
                    </button>
                    
                    <a href="payments.php?patient_id=<?= $current_bill['patient_id'] ?>" class="btn btn-outline btn-block mt-2 text-center">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Payments
            <span class="text-gray-300 mx-2">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <?php if ($is_reception): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#34D399;">👀 Reception Access</span>
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
    var htmlElement = document.documentElement;
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
    }

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

    // ================================================================
    // ADD ITEMS TO BILL
    // ================================================================
    var billItems = [];
    var itemId = 0;

    function addService() {
        var select = document.getElementById('serviceSelect');
        var option = select.options[select.selectedIndex];
        if (!option.value) {
            showToast('Error', 'Please select a service', 'error');
            return;
        }
        
        var name = option.text.split(' - ')[0];
        var price = parseFloat(option.dataset.price);
        
        billItems.push({
            id: ++itemId,
            type: 'service',
            item_id: parseInt(option.value),
            name: name,
            price: price,
            quantity: 1,
            total: price
        });
        
        select.value = '';
        renderBillItems();
    }

    function addMedication() {
        var select = document.getElementById('medicationSelect');
        var qtyInput = document.getElementById('medicationQty');
        var option = select.options[select.selectedIndex];
        if (!option.value) {
            showToast('Error', 'Please select a medication', 'error');
            return;
        }
        
        var qty = parseInt(qtyInput.value) || 1;
        var maxQty = parseInt(option.dataset.qty) || 999;
        if (qty > maxQty) {
            showToast('Error', 'Not enough stock! Available: ' + maxQty, 'error');
            return;
        }
        
        var name = option.text.split(' (')[0];
        var price = parseFloat(option.dataset.price);
        
        billItems.push({
            id: ++itemId,
            type: 'medication',
            item_id: parseInt(option.value),
            name: name,
            price: price,
            quantity: qty,
            total: price * qty
        });
        
        select.value = '';
        qtyInput.value = 1;
        renderBillItems();
    }

    function removeItem(id) {
        billItems = billItems.filter(function(item) { return item.id !== id; });
        renderBillItems();
    }

    function renderBillItems() {
        var container = document.getElementById('billItemsList');
        var countEl = document.getElementById('itemCount');
        var totalEl = document.getElementById('totalAmount');
        var currency = '<?= $currency ?>';
        
        countEl.textContent = billItems.length + ' items';
        
        if (billItems.length === 0) {
            container.innerHTML = '<p class="text-gray-400 text-sm text-center py-4">No items added yet</p>';
            totalEl.textContent = currency + ' 0';
            document.getElementById('itemsJson').value = '[]';
            return;
        }
        
        var html = '';
        var total = 0;
        billItems.forEach(function(item) {
            total += item.total;
            html += `
                <div class="payment-item-card">
                    <div>
                        <span class="font-medium">${item.name}</span>
                        <span class="text-sm text-gray-500">x${item.quantity}</span>
                        <span class="text-xs text-gray-400 ml-2">${item.type}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="item-price">${currency} ${item.total.toLocaleString()}</span>
                        <button type="button" onclick="removeItem(${item.id})" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        totalEl.textContent = currency + ' ' + total.toLocaleString();
        document.getElementById('itemsJson').value = JSON.stringify(billItems);
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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput?.value?.trim() || '';
        if (query.length > 0) {
            window.location.href = 'payments.php?search=' + encodeURIComponent(query);
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
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('footerTimestamp');
        if (el) {
            el.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c💰 Braick - Payments (Using Your Database)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Uses bills table (not patient_bills)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Uses bill_items table', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Medications from medications_inventory', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Services from services table', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>