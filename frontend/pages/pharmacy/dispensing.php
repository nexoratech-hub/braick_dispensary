<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dispensing.php
// PHARMACY - DISPENSING (Process Prescriptions)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to pharm.peter
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Peter Ngalula';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.peter';
    $_SESSION['email'] = 'peter@braick.com';
    $_SESSION['phone'] = '+255 700 000 004';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$db = getDB();

// ================================================================
// GET PRESCRIPTION ID
// ================================================================
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ================================================================
// PROCESS DISPENSING
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    
    if ($action === 'dispense' && $sale_id > 0) {
        try {
            $db->beginTransaction();
            
            // Get sale details
            $stmt = $db->prepare("
                SELECT ps.*, psi.inventory_id, psi.quantity
                FROM prescription_sales ps
                JOIN prescription_sale_items psi ON ps.id = psi.sale_id
                WHERE ps.id = ? AND ps.branch_id = ? AND ps.status = 'pending'
            ");
            $stmt->execute([$sale_id, $user_branch_id]);
            $sale_items = $stmt->fetchAll();
            
            if (empty($sale_items)) {
                throw new Exception("Prescription not found or already processed");
            }
            
            // Check stock for each item
            $stock_error = false;
            $out_of_stock_items = [];
            
            foreach ($sale_items as $item) {
                $stmt = $db->prepare("
                    SELECT quantity, medication_name FROM medications_inventory 
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$item['inventory_id'], $user_branch_id]);
                $stock = $stmt->fetch();
                
                if (!$stock || $stock['quantity'] < $item['quantity']) {
                    $stock_error = true;
                    $out_of_stock_items[] = [
                        'name' => $item['medicine_name'],
                        'available' => $stock['quantity'] ?? 0,
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
                // Update stock for each item
                foreach ($sale_items as $item) {
                    $stmt = $db->prepare("
                        UPDATE medications_inventory 
                        SET quantity = quantity - ? 
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt->execute([$item['quantity'], $item['inventory_id'], $user_branch_id]);
                    
                    // Record stock movement
                    $stmt = $db->prepare("
                        INSERT INTO stock_movements 
                        (inventory_id, sale_type, sale_id, quantity, movement_type, performed_by, notes)
                        VALUES (?, 'prescription', ?, ?, 'out', ?, 'Dispensed prescription')
                    ");
                    $stmt->execute([$item['inventory_id'], $sale_id, $item['quantity'], $user_id]);
                }
                
                // Update sale status
                $stmt = $db->prepare("
                    UPDATE prescription_sales 
                    SET status = 'dispensed', dispensed_at = NOW() 
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$sale_id, $user_branch_id]);
                
                $db->commit();
                $message = "Prescription dispensed successfully!";
                $message_type = 'success';
                
                // Redirect to prescription history
                echo '<script>setTimeout(function(){ window.location.href = "prescription_history.php?success=1"; }, 1500);</script>';
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// GET PRESCRIPTION DETAILS
// ================================================================
$prescription = null;
$items = [];

if ($sale_id > 0) {
    $stmt = $db->prepare("
        SELECT ps.*, p.full_name as patient_name, p.patient_id, p.phone,
               u.full_name as doctor_name
        FROM prescription_sales ps
        LEFT JOIN patients p ON ps.patient_id = p.id
        LEFT JOIN users u ON ps.doctor_id = u.id
        WHERE ps.id = ? AND ps.branch_id = ?
    ");
    $stmt->execute([$sale_id, $user_branch_id]);
    $prescription = $stmt->fetch();
    
    if ($prescription) {
        // Get items
        $stmt = $db->prepare("
            SELECT psi.*, mi.quantity as stock_quantity
            FROM prescription_sale_items psi
            LEFT JOIN medications_inventory mi ON psi.inventory_id = mi.id
            WHERE psi.sale_id = ?
        ");
        $stmt->execute([$sale_id]);
        $items = $stmt->fetchAll();
        
        // Check stock status for each item
        foreach ($items as &$item) {
            $item['stock_ok'] = ($item['stock_quantity'] ?? 0) >= $item['quantity'];
        }
        unset($item);
    }
}

// ================================================================
// GET PENDING PRESCRIPTIONS LIST
// ================================================================
$pending_list = [];
$stmt = $db->prepare("
    SELECT ps.id, ps.sale_number, p.full_name as patient_name, p.patient_id,
           ps.total_amount, ps.created_at
    FROM prescription_sales ps
    JOIN patients p ON ps.patient_id = p.id
    WHERE ps.branch_id = ? AND ps.status = 'pending'
    ORDER BY ps.created_at DESC
");
$stmt->execute([$user_branch_id]);
$pending_list = $stmt->fetchAll();

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

// ================================================================
// UNREAD NOTIFICATIONS
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
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<style>
    /* ================================================================
       DISPENSING STYLES
       ================================================================ */
    
    .prescription-detail-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        margin-bottom: 20px;
    }
    
    .prescription-detail-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
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
        background: var(--table-hover);
    }
    
    .items-table .stock-ok {
        color: #059669;
        font-weight: 600;
    }
    
    .items-table .stock-error {
        color: #DC2626;
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
    
    .pending-list-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
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
    
    .btn-dispense-large {
        background: #059669;
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
    }
    
    .btn-dispense-large:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
    }
    
    .btn-cancel-large:hover {
        background: #DC2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
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
    
    .action-buttons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    @media (max-width: 768px) {
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
                <i class="fas fa-prescription mr-2" style="color: #059669;"></i> Dispensing
            </h1>
            <p class="page-subtitle">
                Process and dispense prescription medicines
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <?php if ($pending_prescriptions > 0): ?>
                    <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200">
                        <i class="fas fa-clock mr-1"></i> <?= $pending_prescriptions ?> pending
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
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
    <!-- PENDING PRESCRIPTIONS LIST (Sidebar) -->
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
                    <div class="sale-number"><?= htmlspecialchars($pending['sale_number']) ?></div>
                </div>
                <div class="item-amount">
                    TSh <?= number_format($pending['total_amount']) ?>
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
                    <?= htmlspecialchars($prescription['sale_number']) ?>
                    <span class="badge <?= 
                        $prescription['status'] === 'pending' ? 'badge-pending' :
                        ($prescription['status'] === 'dispensed' ? 'badge-dispensed' : 'badge-cancelled')
                    ?>">
                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
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
                    <div class="text-xs text-gray-400">ID: <?= htmlspecialchars($prescription['patient_id'] ?? 'N/A') ?></div>
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
                        TSh <?= number_format($prescription['total_amount'] ?? 0) ?>
                    </div>
                </div>
            </div>
            
            <!-- Items Table -->
            <h4 class="font-semibold text-gray-700 mb-2">Prescribed Medicines</h4>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th class="text-right">Total</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['medicine_name']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>TSh <?= number_format($item['unit_price'] ?? 0) ?></td>
                            <td class="text-right">TSh <?= number_format($item['total_price'] ?? 0) ?></td>
                            <td>
                                <?php if ($prescription['status'] === 'pending'): ?>
                                    <?php if ($item['stock_ok']): ?>
                                        <span class="stock-ok"><i class="fas fa-check-circle"></i> In Stock</span>
                                    <?php else: ?>
                                        <span class="stock-error"><i class="fas fa-exclamation-triangle"></i> Low Stock (<?= $item['stock_quantity'] ?? 0 ?> available)</span>
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
                    <span class="total-amount">TSh <?= number_format($prescription['total_amount'] ?? 0) ?></span>
                </div>
                <div>
                    <span class="total-label">Items: <?= count($items) ?></span>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <?php if ($prescription['status'] === 'pending'): ?>
                <div class="action-buttons">
                    <form method="POST" action="" 
                          onsubmit="return confirm('Dispense this prescription? This will reduce medicine stock and cannot be undone.');">
                        <input type="hidden" name="action" value="dispense">
                        <input type="hidden" name="sale_id" value="<?= $prescription['id'] ?>">
                        
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
                        <input type="hidden" name="sale_id" value="<?= $prescription['id'] ?>">
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
                    <a href="print_receipt.php?type=prescription&id=<?= $prescription['id'] ?>" class="btn-dispense-large" target="_blank">
                        <i class="fas fa-print"></i> Print Receipt
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
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Dispensing
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

    console.log('%c💊 Braick - Dispensing', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Pending Prescriptions: <?= $pending_prescriptions ?>', 'font-size:13px; color:#0B5ED7;');
    <?php if ($prescription): ?>
    console.log('%c📋 Processing: <?= htmlspecialchars($prescription['sale_number']) ?> - <?= htmlspecialchars($prescription['patient_name']) ?>', 'font-size:13px; color:#0D9488;');
    <?php endif; ?>
</script>

</body>
</html>