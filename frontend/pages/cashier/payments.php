<?php
// ================================================================
// FILE: frontend/pages/cashier/payments.php
// NEW PAYMENT - FIXED FOR YOUR DATABASE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to reception.rose
// ================================================================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Rose Mwangi';

// ================================================================
// DATABASE CONNECTION
// ================================================================
$db = getDB();

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
        $bills = $stmt->fetchAll();
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
    $search_results = $stmt->fetchAll();
}

// ================================================================
// GET SERVICES, MEDICATIONS, CONSULTATION FEES
// ================================================================

// Services
$services = [];
try {
    $stmt = $db->query("SELECT id, service_name, price FROM services WHERE status = 'active' ORDER BY service_name");
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    $services = [];
}

// FIXED: Medications table - correct columns: id, name, strength, unit, category
$medications = [];
$stmt = $db->query("SELECT id, name, strength, unit, category FROM medications WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $medications[] = [
        'id' => $row['id'],
        'name' => $row['name'] . ($row['strength'] ? ' ' . $row['strength'] : ''),
        'price' => 0, // Price comes from medication_prices table or default
        'quantity' => 999 // Default quantity
    ];
}

// Consultation fees - hardcoded or from settings
$consultation_fees = [
    ['id' => 1, 'visit_type' => 'new', 'fee' => 15000],
    ['id' => 2, 'visit_type' => 'follow-up', 'fee' => 10000],
    ['id' => 3, 'visit_type' => 'emergency', 'fee' => 25000]
];

// ================================================================
// GET MEDICATION PRICES (if you have a prices table)
// ================================================================
$medication_prices = [];
try {
    $stmt = $db->query("SELECT medication_id, selling_price FROM medication_prices WHERE branch_id = ? AND is_active = 1");
    $stmt->execute([$user_branch_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $medication_prices[$row['medication_id']] = $row['selling_price'];
    }
} catch (Exception $e) {
    // If no prices table, use default prices
    $default_prices = [
        1 => 500,   // Paracetamol
        2 => 1500,  // Amoxicillin 250mg
        3 => 2500,  // Amoxicillin 500mg
        4 => 2000,  // Ciprofloxacin
        5 => 2500,  // Metformin 850mg
        6 => 1500,  // Metformin 500mg
        7 => 1000,  // Lisinopril
        8 => 1200,  // Amlodipine
        9 => 800,   // Omeprazole
        10 => 1000, // Pantoprazole
        11 => 1500, // Atorvastatin
        12 => 1800, // Rosuvastatin
        13 => 2000, // Doxycycline
        14 => 1200, // Glibenclamide
        15 => 1000  // Enalapril
    ];
    $medication_prices = $default_prices;
}

// Add prices to medications
foreach ($medications as &$med) {
    $med['price'] = $medication_prices[$med['id']] ?? 0;
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
                        $stmt = $db->prepare("SELECT service_name, price FROM services WHERE id = ? AND status = 'active'");
                        $stmt->execute([$item_id]);
                        $service = $stmt->fetch();
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
                    $med = $stmt->fetch();
                    if ($med) {
                        $med_name = $med['name'] . ($med['strength'] ? ' ' . $med['strength'] : '');
                        $price = $medication_prices[$item_id] ?? 0;
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
                    INSERT INTO patient_bills (bill_number, patient_id, total_amount, paid_amount, balance, status, created_by, branch_id) 
                    VALUES (?, ?, ?, 0, ?, 'pending', ?, ?)
                ");
                $stmt->execute([$bill_number, $patient_id, $total, $total, $user_id, $user_branch_id]);
                $bill_id = $db->lastInsertId();
                
                // Insert bill items
                foreach ($bill_items as $item) {
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price) 
                        VALUES (?, ?, ?, ?, ?, ?)
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
                $bills = [$stmt->fetch()];
                
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
            $bill = $stmt->fetch();
            
            if (!$bill) {
                $message = "Bill not found!";
                $message_type = 'error';
            } else {
                $balance = $bill['balance'];
                
                if ($amount > $balance) {
                    $message = "Amount exceeds balance! Balance: TSh " . number_format($balance);
                    $message_type = 'error';
                } else {
                    // Generate payment number
                    $payment_number = 'PAY-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Insert payment
                    $stmt = $db->prepare("
                        INSERT INTO payments (payment_number, bill_id, patient_id, amount, payment_method, received_by, branch_id, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
                    ");
                    $stmt->execute([$payment_number, $bill_id, $bill['patient_id'], $amount, $payment_method, $user_id, $user_branch_id]);
                    $payment_id = $db->lastInsertId();
                    
                    // Update bill
                    $paid_amount = $bill['paid_amount'] + $amount;
                    $new_balance = $bill['total_amount'] - $paid_amount;
                    $status = ($new_balance <= 0) ? 'paid' : 'partial';
                    
                    $stmt = $db->prepare("
                        UPDATE patient_bills 
                        SET paid_amount = ?, balance = ?, status = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$paid_amount, $new_balance, $status, $bill_id]);
                    
                    $message = "Payment processed successfully! Payment #: $payment_number";
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
    $current_bill = $stmt->fetch();
    
    if ($current_bill) {
        $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $bill_items_details = $stmt->fetchAll();
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
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
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
</style>

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
            <input type="text" id="searchInput" placeholder="Search patient by name, ID, or phone...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select class="branch-selector" disabled style="opacity:0.7;cursor:not-allowed;">
            <option value="<?= $user_branch_id ?>">
                🏥 <?= htmlspecialchars($user_branch_name) ?>
            </option>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" id="notifBtn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="../cashier/profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle mr-2" style="color: var(--primary);"></i> New Payment
            </h1>
            <p class="page-subtitle">
                Process payments for patients
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
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
    <!-- SEARCH PATIENT -->
    <!-- ================================================================ -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-search title-blue mr-2"></i> Find Patient
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
                <i class="fas fa-receipt title-blue mr-2"></i> Patient Bills
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
                <i class="fas fa-plus-circle title-green mr-2"></i> Generate New Bill
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
                <i class="fas fa-money-bill-wave title-green mr-2"></i> Process Payment
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
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Payments
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
    console.log('%c🏥 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#34D399;');
    console.log('%c💊 Using medications table: id, name, strength, unit, category', 'font-size:13px; color:#6EE7B7;');
    console.log('%c💳 Consultation fees: Hardcoded (new:15000, follow-up:10000, emergency:25000)', 'font-size:13px; color:#6EE7B7;');
</script>

</body>
</html>