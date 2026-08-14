<?php
// ================================================================
// FILE: frontend/pages/cashier/payments.php
// NEW PAYMENT - FIXED FOR YOUR DATABASE
// ALLOWS RECEPTION, CASHIER AND ADMIN
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// ALLOWED ROLES: Cashier, Reception, Admin
// ================================================================
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

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// CHECK IF USER IS RECEPTION
// ================================================================
$is_reception = ($user_role === 'reception');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET PATIENT BY ID OR SEARCH
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$patient = null;
$bills = [];

if ($patient_id > 0) {
    // Get patient details
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ? AND branch_id = ?");
    $stmt->execute([$patient_id, $user_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient) {
        // Get patient bills
        $stmt = $db->prepare("
            SELECT pb.*, 
                   COUNT(bi.id) as item_count,
                   (SELECT COUNT(*) FROM payments WHERE bill_id = pb.id AND status = 'completed') as payment_count
            FROM patient_bills pb
            LEFT JOIN bill_items bi ON pb.id = bi.bill_id
            WHERE pb.patient_id = ? AND pb.branch_id = ?
            GROUP BY pb.id
            ORDER BY pb.created_at DESC
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
// GET SERVICES, MEDICATIONS, CONSULTATION FEES
// ================================================================

// Services
$services = [];
try {
    $stmt = $db->prepare("SELECT id, service_name, price FROM services WHERE is_active = 1 AND (branch_id = ? OR branch_id IS NULL) ORDER BY service_name");
    $stmt->execute([$user_branch_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

// Medications - Correct columns: id, name, strength, unit, category
$medications = [];
try {
    $stmt = $db->prepare("SELECT id, name, strength, unit, category FROM medications WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $medications[] = [
            'id' => $row['id'],
            'name' => $row['name'] . ($row['strength'] ? ' ' . $row['strength'] : ''),
            'price' => 0, // Price comes from medication_prices table or default
            'quantity' => 999 // Default quantity
        ];
    }
} catch (Exception $e) {
    $medications = [];
}

// Consultation fees
$consultation_fees = [
    ['id' => 1, 'visit_type' => 'new', 'fee' => 15000],
    ['id' => 2, 'visit_type' => 'follow-up', 'fee' => 10000],
    ['id' => 3, 'visit_type' => 'emergency', 'fee' => 25000]
];

// ================================================================
// GET MEDICATION PRICES (from medications_inventory table)
// ================================================================
$medication_prices = [];
try {
    $stmt = $db->prepare("SELECT id, medication_name, selling_price FROM medications_inventory WHERE branch_id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Match by name from medications table
        $medication_prices[$row['medication_name']] = $row['selling_price'];
    }
} catch (Exception $e) {
    // If no inventory table, use default prices
    $default_prices = [
        'Paracetamol' => 500,
        'Amoxicillin' => 1500,
        'Ciprofloxacin' => 2000,
        'Metformin' => 2500,
        'Lisinopril' => 1000,
        'Amlodipine' => 1200,
        'Omeprazole' => 800,
        'Pantoprazole' => 1000,
        'Atorvastatin' => 1500,
        'Rosuvastatin' => 1800,
        'Doxycycline' => 2000,
        'Glibenclamide' => 1200,
        'Enalapril' => 1000,
        'Artemether/Lumefantrine' => 500,
        'Quinine' => 600,
        'Ibuprofen' => 400,
        'Diclofenac' => 500,
        'Cetirizine' => 300
    ];
    $medication_prices = $default_prices;
}

// Add prices to medications
foreach ($medications as &$med) {
    // Try to find price by matching name
    $found = false;
    foreach ($medication_prices as $key => $price) {
        if (strpos($med['name'], $key) !== false) {
            $med['price'] = $price;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $med['price'] = 0;
    }
}

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
    // GENERATE BILL
    // ================================================================
    if ($action === 'generate_bill') {
        $patient_id = (int)$_POST['patient_id'];
        $items = json_decode($_POST['items_json'] ?? '[]', true);
        $consultation_type = $_POST['consultation_type'] ?? '';
        
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
                foreach ($consultation_fees as $cf) {
                    if ($cf['visit_type'] === $consultation_type) {
                        $fee = $cf['fee'];
                        break;
                    }
                }
                if ($fee > 0) {
                    $total += $fee;
                    $bill_items[] = [
                        'type' => 'consultation',
                        'name' => 'Consultation - ' . ucfirst($consultation_type),
                        'quantity' => 1,
                        'price' => $fee,
                        'total' => $fee
                    ];
                }
            }
            
            // Add selected items
            foreach ($items as $item) {
                $item_type = $item['type'] ?? '';
                $item_id = (int)$item['item_id'] ?? 0;
                $quantity = (int)$item['quantity'] ?? 1;
                
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
                    // Get medication from database
                    $stmt = $db->prepare("SELECT id, name, strength FROM medications WHERE id = ? AND status = 'active'");
                    $stmt->execute([$item_id]);
                    $med = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($med) {
                        $med_name = $med['name'] . ($med['strength'] ? ' ' . $med['strength'] : '');
                        $price = 0;
                        // Try to find price
                        foreach ($medication_prices as $key => $p) {
                            if (strpos($med_name, $key) !== false) {
                                $price = $p;
                                break;
                            }
                        }
                        $subtotal = $price * $quantity;
                        $total += $subtotal;
                        $bill_items[] = [
                            'type' => 'medication',
                            'name' => $med_name,
                            'quantity' => $quantity,
                            'price' => $price,
                            'total' => $subtotal
                        ];
                    }
                }
            }
            
            if ($total <= 0) {
                $message = "Total amount cannot be zero!";
                $message_type = 'error';
            } else {
                // Generate bill number
                $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Insert bill
                $stmt = $db->prepare("
                    INSERT INTO patient_bills (bill_number, patient_id, total_amount, paid_amount, balance, status, created_by, branch_id, created_at) 
                    VALUES (?, ?, ?, 0, ?, 'pending', ?, ?, NOW())
                ");
                $stmt->execute([$bill_number, $patient_id, $total, $total, $user_id, $user_branch_id]);
                $bill_id = $db->lastInsertId();
                
                // Insert bill items
                foreach ($bill_items as $item) {
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $bill_id,
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
                    SELECT pb.*, 
                           COUNT(bi.id) as item_count
                    FROM patient_bills pb
                    LEFT JOIN bill_items bi ON pb.id = bi.bill_id
                    WHERE pb.id = ?
                    GROUP BY pb.id
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
            // Get bill details
            $stmt = $db->prepare("SELECT * FROM patient_bills WHERE id = ? AND branch_id = ?");
            $stmt->execute([$bill_id, $user_branch_id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bill) {
                $message = "Bill not found!";
                $message_type = 'error';
            } else {
                $balance = $bill['balance'];
                
                if ($amount > $balance) {
                    $message = "Amount exceeds balance! Balance: TSh " . number_format($balance);
                    $message_type = 'error';
                } else {
                    // Generate receipt number
                    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Insert payment
                    $stmt = $db->prepare("
                        INSERT INTO payments (receipt_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, received_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$receipt_number, $bill_id, $bill['patient_id'], $amount, $payment_method, $user_id, $user_branch_id]);
                    $payment_id = $db->lastInsertId();
                    
                    // Update bill
                    $paid_amount = $bill['paid_amount'] + $amount;
                    $new_balance = $bill['total_amount'] - $paid_amount;
                    $status = ($new_balance <= 0) ? 'paid' : 'partial';
                    
                    $stmt = $db->prepare("
                        UPDATE patient_bills 
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
$current_bill = null;
$bill_items_details = [];

if ($bill_id > 0) {
    $stmt = $db->prepare("
        SELECT pb.*, p.full_name as patient_name, p.patient_id 
        FROM patient_bills pb
        JOIN patients p ON pb.patient_id = p.id
        WHERE pb.id = ? AND pb.branch_id = ?
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $current_bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_bill) {
        $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
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

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    .payment-item-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 12px 16px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 8px;
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
    }
    
    .bill-summary {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
    }
    
    .bill-summary .total {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .bill-summary .balance {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--danger);
    }
    
    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
    }
    
    .form-row {
        margin-bottom: 16px;
    }
    
    .btn-payment {
        background: var(--success);
        color: white;
        padding: 10px 24px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-payment:hover {
        background: var(--success-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .empty-state {
        text-align: center;
        padding: 30px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 10px;
    }
    
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 28px 32px;
        min-height: calc(100vh - 68px);
    }
    
    .page-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 16px;
        padding: 20px 24px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
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
    
    .card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
        box-shadow: var(--shadow-sm);
        max-width: 1400px;
        margin: 0 auto;
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
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
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
        color: #fff;
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
    
    .data-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    .badge {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
        border: none;
    }
    
    .badge-green { background: var(--success); }
    .badge-yellow { background: #D97706; }
    .badge-red { background: var(--danger); }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-blue {
        background: var(--primary);
        color: white;
    }
    .btn-blue:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-green {
        background: var(--success);
        color: white;
    }
    .btn-green:hover {
        background: var(--success-dark);
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
    
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .footer {
        padding: 14px 0;
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
        border-radius: 12px;
        z-index: 999;
        max-width: 360px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }
    
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    .toast-custom.success { background: var(--success); }
    .toast-custom.error { background: var(--danger); }
    .toast-custom.info { background: var(--primary); }
    
    @media (max-width: 1024px) {
        .main-content { margin-left: 0; padding: 16px; }
    }
    
    @media (max-width: 768px) {
        .page-header { padding: 16px 18px; }
        .page-header .page-title { font-size: 1.2rem; }
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
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;padding:4px 14px;border-radius:20px;font-size:0.65rem;font-weight:600;text-transform:uppercase;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;border-color:rgba(52,211,153,0.3);font-size:0.6rem;">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-money-bill-wave"></i> Process payments for patients
                <span class="header-badge" style="background:rgba(255,255,255,0.15);color:white;padding:4px 14px;border-radius:20px;font-size:0.7rem;border:1px solid rgba(255,255,255,0.1);backdrop-filter:blur(4px);">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);color:#34D399;border-color:rgba(52,211,153,0.2);">
                        <i class="fas fa-user-tag"></i> Reception Access
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);padding:8px 18px;border-radius:10px;font-weight:500;font-size:0.82rem;transition:all 0.3s;text-decoration:none;display:inline-flex;align-items:center;gap:8px;backdrop-filter:blur(4px);">
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
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-search title-blue mr-2" style="color:var(--primary);"></i> Find Patient
            </h3>
        </div>
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="text" name="search" class="form-control flex-1" 
                   placeholder="Search by name, patient ID, or phone..." 
                   value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit" class="btn btn-blue">Search</button>
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
    <div class="card mb-4" style="border-color: var(--success);">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-user text-green-600 mr-2"></i>
                    <?= htmlspecialchars($patient['full_name']) ?>
                </h3>
                <p class="text-sm text-gray-500">
                    Patient ID: <?= htmlspecialchars($patient['patient_id']) ?> | 
                    Phone: <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>
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
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-receipt title-blue mr-2" style="color:var(--primary);"></i> Patient Bills
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
                                <td class="font-semibold">TSh <?= number_format($bill['total_amount']) ?></td>
                                <td>TSh <?= number_format($bill['paid_amount']) ?></td>
                                <td class="font-semibold <?= $bill['balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    TSh <?= number_format($bill['balance']) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $bill['status'] === 'paid' ? 'badge-green' : ($bill['status'] === 'partial' ? 'badge-yellow' : 'badge-red') ?>">
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-sm"><?= date('d/m/Y H:i', strtotime($bill['created_at'])) ?></td>
                                <td>
                                    <?php if ($bill['balance'] > 0): ?>
                                        <a href="?bill_id=<?= $bill['id'] ?>&patient_id=<?= $patient_id ?>" class="btn btn-green btn-sm">
                                            <i class="fas fa-money-bill-wave"></i> Pay
                                        </a>
                                    <?php else: ?>
                                        <span class="text-green-600 text-sm">✓ Paid</span>
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
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-plus-circle title-green mr-2" style="color:var(--success);"></i> Generate New Bill
            </h3>
        </div>
        
        <form method="POST" id="billForm">
            <input type="hidden" name="action" value="generate_bill">
            <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>">
            <input type="hidden" name="items_json" id="itemsJson" value="[]">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Consultation Fee -->
                <div>
                    <label class="form-label">Consultation Type</label>
                    <select name="consultation_type" id="consultationType" class="form-control">
                        <option value="">No Consultation</option>
                        <?php foreach ($consultation_fees as $fee): ?>
                            <option value="<?= $fee['visit_type'] ?>">
                                <?= ucfirst($fee['visit_type']) ?> - TSh <?= number_format($fee['fee']) ?>
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
                                    <?= htmlspecialchars($service['service_name']) ?> - TSh <?= number_format($service['price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="addService()" class="btn btn-blue btn-sm">Add</button>
                    </div>
                </div>
                
                <!-- Medications -->
                <div class="md:col-span-2">
                    <label class="form-label">Add Medication</label>
                    <div class="flex gap-2">
                        <select id="medicationSelect" class="form-control flex-1">
                            <option value="">Select Medication</option>
                            <?php foreach ($medications as $med): ?>
                                <option value="<?= $med['id'] ?>" data-price="<?= $med['price'] ?>" data-qty="<?= $med['quantity'] ?>">
                                    <?= htmlspecialchars($med['name']) ?> - TSh <?= number_format($med['price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="medicationQty" class="form-control w-24" placeholder="Qty" value="1" min="1">
                        <button type="button" onclick="addMedication()" class="btn btn-green btn-sm">Add</button>
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
                        <span class="total" id="totalAmount">TSh 0</span>
                    </div>
                </div>
            </div>
            
            <!-- Submit -->
            <div class="mt-4">
                <button type="submit" class="btn btn-green w-full md:w-auto">
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
                <i class="fas fa-money-bill-wave title-green mr-2" style="color:var(--success);"></i> Process Payment
            </h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Bill Summary -->
            <div>
                <p><strong>Bill #:</strong> <?= htmlspecialchars($current_bill['bill_number']) ?></p>
                <p><strong>Patient:</strong> <?= htmlspecialchars($current_bill['patient_name']) ?></p>
                <p><strong>Patient ID:</strong> <?= htmlspecialchars($current_bill['patient_id']) ?></p>
                <div class="bill-summary mt-3">
                    <div class="flex justify-between">
                        <span>Total Amount:</span>
                        <span class="total">TSh <?= number_format($current_bill['total_amount']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Paid Amount:</span>
                        <span class="text-green-600 font-semibold">TSh <?= number_format($current_bill['paid_amount']) ?></span>
                    </div>
                    <div class="flex justify-between border-t pt-2 mt-2">
                        <span class="font-bold">Balance:</span>
                        <span class="balance">TSh <?= number_format($current_bill['balance']) ?></span>
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
                            </div>
                            <span class="item-price">TSh <?= number_format($item['total_price']) ?></span>
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
                        <p class="text-xs text-gray-400 mt-1">Max: TSh <?= number_format($current_bill['balance']) ?></p>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="m-pesa">M-Pesa</option>
                            <option value="insurance">Insurance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-payment w-full mt-2">
                        <i class="fas fa-check-circle mr-2"></i> Process Payment
                    </button>
                    
                    <a href="payments.php?patient_id=<?= $current_bill['patient_id'] ?>" class="btn btn-outline w-full mt-2 text-center">
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
        
        var name = option.text.split(' - ')[0];
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
        
        countEl.textContent = billItems.length + ' items';
        
        if (billItems.length === 0) {
            container.innerHTML = '<p class="text-gray-400 text-sm text-center py-4" id="emptyItemsMsg">No items added yet</p>';
            totalEl.textContent = 'TSh 0';
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
                        <span class="item-price">TSh ${item.total.toLocaleString()}</span>
                        <button type="button" onclick="removeItem(${item.id})" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        totalEl.textContent = 'TSh ' + total.toLocaleString();
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
        var query = searchInput.value.trim();
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

    console.log('%c💰 Braick - Payments (FIXED v2)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#64748B;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Reception access: <?= $is_reception ? 'YES' : 'NO' ?>', 'font-size:13px; color:#34D399;');
    console.log('%c💊 Using medications table: id, name, strength, unit, category', 'font-size:13px; color:#6EE7B7;');
    console.log('%c💳 Consultation fees: Hardcoded (new:15000, follow-up:10000, emergency:25000)', 'font-size:13px; color:#6EE7B7;');
</script>

</body>
</html>