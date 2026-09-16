<?php
// ================================================================
// FILE: frontend/pages/admin/edit_bill.php
// ADMIN - EDIT BILL
// BRAICK DISPENSARY - WITH PREMIUM
// ✅ Uses SHARED header & sidebar
// ✅ Page-specific CSS only
// ✅ Dark mode inatumia header toggle
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($bill_id <= 0) {
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH BILL
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            u.full_name as created_by_name,
            br.name as branch_name
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN branches br ON b.branch_id = br.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bill_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching bill: " . $e->getMessage());
    header('Location: bills.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// FETCH BILL ITEMS
// ================================================================
$bill_items = [];
try {
    $stmt = $db->prepare("
        SELECT 
            id,
            item_type,
            item_name,
            description,
            quantity,
            unit_price,
            total_price,
            status,
            created_at
        FROM bill_items
        WHERE bill_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bill_items = [];
}

// ================================================================
// ITEM TYPES
// ================================================================
$item_types = [
    'registration' => 'Registration Fee',
    'consultation' => 'Consultation Fee',
    'lab_test' => 'Lab Test',
    'medication' => 'Medication',
    'procedure' => 'Procedure',
    'equipment' => 'Equipment',
    'tool' => 'Tool/Supply',
    'other' => 'Other'
];

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// PROCESS FORM
// ================================================================
$message = '';
$message_type = '';
$update_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ============================================================
    // UPDATE BILL - WITH PREMIUM
    // ============================================================
    if ($action === 'update_bill') {
        $discount_amount = isset($_POST['discount_amount']) ? floatval(str_replace(',', '', $_POST['discount_amount'])) : 0;
        $premium_amount = isset($_POST['premium_amount']) ? floatval(str_replace(',', '', $_POST['premium_amount'])) : 0;
        $premium_note = trim($_POST['premium_note'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            $db->beginTransaction();
            
            $subtotal = 0;
            foreach ($bill_items as $item) {
                if ($item['status'] !== 'cancelled') {
                    $subtotal += (float)$item['total_price'];
                }
            }
            
            // ✅ FORMULA: Total = Subtotal - Discount + Premium
            $grand_total = $subtotal - $discount_amount + $premium_amount;
            if ($grand_total < 0) $grand_total = 0;
            
            $paid_amount = (float)$bill['paid_amount'];
            $balance = $grand_total - $paid_amount;
            if ($balance < 0) $balance = 0;
            
            $stmt = $db->prepare("
                UPDATE bills 
                SET 
                    discount_amount = ?,
                    discount_percent = ?,
                    premium_amount = ?,
                    premium_note = ?,
                    subtotal = ?,
                    total_amount = ?,
                    balance = ?,
                    status = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $discount_amount,
                0,
                $premium_amount,
                $premium_note,
                $subtotal,
                $grand_total,
                $balance,
                $status,
                $notes,
                $bill_id
            ]);
            
            $db->commit();
            
            $update_success = true;
            $message = "✅ Bill updated successfully!";
            $message_type = 'success';
            
            // Refresh bill
            $stmt = $db->prepare("
                SELECT b.*, p.full_name as patient_name, p.patient_id as patient_code,
                       p.phone as patient_phone, u.full_name as created_by_name,
                       br.name as branch_name
                FROM bills b
                LEFT JOIN patients p ON b.patient_id = p.id
                LEFT JOIN users u ON b.created_by = u.id
                LEFT JOIN branches br ON b.branch_id = br.id
                WHERE b.id = ?
            ");
            $stmt->execute([$bill_id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ============================================================
    // ADD ITEM
    // ============================================================
    if ($action === 'add_item') {
        $item_type = $_POST['item_type'] ?? 'other';
        $item_name = trim($_POST['item_name'] ?? '');
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        $unit_price = isset($_POST['unit_price']) ? floatval(str_replace(',', '', $_POST['unit_price'])) : 0;
        $description = trim($_POST['description'] ?? '');
        
        if (empty($item_name)) {
            $message = "❌ Item name is required";
            $message_type = 'error';
        } elseif ($quantity <= 0) {
            $message = "❌ Quantity must be greater than 0";
            $message_type = 'error';
        } elseif ($unit_price < 0) {
            $message = "❌ Unit price cannot be negative";
            $message_type = 'error';
        } else {
            try {
                $db->beginTransaction();
                
                $total_price = $quantity * $unit_price;
                $branch_id = $bill['branch_id'] ?? 1;
                
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id, item_type, item_name,
                        description, quantity, unit_price, total_price,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $bill_id, $bill['patient_id'], $branch_id,
                    $item_type, $item_name, $description,
                    $quantity, $unit_price, $total_price
                ]);
                
                // ✅ Recalculate with premium
                $stmt = $db->prepare("
                    SELECT COALESCE(SUM(total_price), 0) as subtotal 
                    FROM bill_items WHERE bill_id = ? AND status != 'cancelled'
                ");
                $stmt->execute([$bill_id]);
                $new_subtotal = (float)$stmt->fetch(PDO::FETCH_ASSOC)['subtotal'];
                
                $premium_amount = (float)($bill['premium_amount'] ?? 0);
                $discount_amount = (float)($bill['discount_amount'] ?? 0);
                $new_total = $new_subtotal - $discount_amount + $premium_amount;
                if ($new_total < 0) $new_total = 0;
                
                $paid_amount = (float)$bill['paid_amount'];
                $new_balance = $new_total - $paid_amount;
                if ($new_balance < 0) $new_balance = 0;
                
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET subtotal = ?, total_amount = ?, balance = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_subtotal, $new_total, $new_balance, $bill_id]);
                
                $db->commit();
                
                $message = "✅ Item added successfully!";
                $message_type = 'success';
                
                // Refresh
                $stmt = $db->prepare("
                    SELECT b.*, p.full_name as patient_name, p.patient_id as patient_code,
                           p.phone as patient_phone, u.full_name as created_by_name,
                           br.name as branch_name
                    FROM bills b
                    LEFT JOIN patients p ON b.patient_id = p.id
                    LEFT JOIN users u ON b.created_by = u.id
                    LEFT JOIN branches br ON b.branch_id = br.id
                    WHERE b.id = ?
                ");
                $stmt->execute([$bill_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT id, item_type, item_name, description, quantity, 
                           unit_price, total_price, status, created_at
                    FROM bill_items WHERE bill_id = ? ORDER BY created_at ASC
                ");
                $stmt->execute([$bill_id]);
                $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error adding item: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    
    // ============================================================
    // DELETE ITEM
    // ============================================================
    if ($action === 'delete_item') {
        $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        
        if ($item_id <= 0) {
            $message = "❌ Invalid item";
            $message_type = 'error';
        } else {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT total_price FROM bill_items WHERE id = ? AND bill_id = ?");
                $stmt->execute([$item_id, $bill_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($item) {
                    $stmt = $db->prepare("DELETE FROM bill_items WHERE id = ? AND bill_id = ?");
                    $stmt->execute([$item_id, $bill_id]);
                    
                    // ✅ Recalculate with premium
                    $stmt = $db->prepare("
                        SELECT COALESCE(SUM(total_price), 0) as subtotal 
                        FROM bill_items WHERE bill_id = ? AND status != 'cancelled'
                    ");
                    $stmt->execute([$bill_id]);
                    $new_subtotal = (float)$stmt->fetch(PDO::FETCH_ASSOC)['subtotal'];
                    
                    $premium_amount = (float)($bill['premium_amount'] ?? 0);
                    $discount_amount = (float)($bill['discount_amount'] ?? 0);
                    $new_total = $new_subtotal - $discount_amount + $premium_amount;
                    if ($new_total < 0) $new_total = 0;
                    
                    $paid_amount = (float)$bill['paid_amount'];
                    $new_balance = $new_total - $paid_amount;
                    if ($new_balance < 0) $new_balance = 0;
                    
                    $stmt = $db->prepare("
                        UPDATE bills 
                        SET subtotal = ?, total_amount = ?, balance = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_subtotal, $new_total, $new_balance, $bill_id]);
                }
                
                $db->commit();
                
                $message = "✅ Item deleted successfully!";
                $message_type = 'success';
                
                // Refresh
                $stmt = $db->prepare("
                    SELECT b.*, p.full_name as patient_name, p.patient_id as patient_code,
                           p.phone as patient_phone, u.full_name as created_by_name,
                           br.name as branch_name
                    FROM bills b
                    LEFT JOIN patients p ON b.patient_id = p.id
                    LEFT JOIN users u ON b.created_by = u.id
                    LEFT JOIN branches br ON b.branch_id = br.id
                    WHERE b.id = ?
                ");
                $stmt->execute([$bill_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT id, item_type, item_name, description, quantity, 
                           unit_price, total_price, status, created_at
                    FROM bill_items WHERE bill_id = ? ORDER BY created_at ASC
                ");
                $stmt->execute([$bill_id]);
                $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error deleting item: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// STATUS FUNCTIONS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'active' => 'success', 'inactive' => 'danger',
        'pending' => 'warning', 'paid' => 'success',
        'partial' => 'warning', 'cancelled' => 'danger',
        'completed' => 'success'
    ];
    return $classes[$status] ?? 'secondary';
}

function getItemTypeColor($type) {
    $colors = [
        'registration' => 'blue', 'consultation' => 'purple',
        'lab_test' => 'orange', 'medication' => 'green',
        'procedure' => 'red', 'equipment' => 'teal',
        'tool' => 'gray', 'other' => 'gray'
    ];
    return $colors[$type] ?? 'gray';
}

function getItemTypeLabel($type) {
    $labels = [
        'registration' => 'Registration', 'consultation' => 'Consultation',
        'lab_test' => 'Lab Test', 'medication' => 'Medication',
        'procedure' => 'Procedure', 'equipment' => 'Equipment',
        'tool' => 'Tool/Supply', 'other' => 'Other'
    ];
    return $labels[$type] ?? ucfirst($type);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - HAKUNA DUPLICATES ZA HEADER/SIDEBAR -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES - ONLY FOR THIS PAGE
       ================================================================ */
    :root {
        --bill-primary: #059669;
        --bill-primary-dark: #047857;
        --bill-primary-light: #34D399;
        --bill-primary-bg: #D1FAE5;
        --bill-primary-gradient: linear-gradient(135deg, #059669, #047857);
        --bill-primary-gradient-strong: linear-gradient(135deg, #047857, #065F46);
        --bill-success: #059669;
        --bill-success-bg: #D1FAE5;
        --bill-danger: #DC2626;
        --bill-danger-bg: #FEE2E2;
        --bill-warning: #D97706;
        --bill-warning-bg: #FEF3C7;
        --bill-purple: #7C3AED;
        --bill-purple-bg: #EDE9FE;
        --bill-teal: #0D9488;
        --bill-teal-bg: #ECFDF5;
        --bill-bg-body: #F0FDF4;
        --bill-bg-card: #FFFFFF;
        --bill-text-primary: #1E293B;
        --bill-text-secondary: #64748B;
        --bill-border: #D1FAE5;
        --bill-radius: 12px;
        --bill-radius-lg: 18px;
        --bill-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --bill-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --bill-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        --bill-shadow-xl: 0 20px 40px rgba(0,0,0,0.12);
        --bill-table-hover: #ECFDF5;
    }
    
    /* ================================================================
       DARK MODE - INAFANYA KAZI NA HEADER TOGGLE
       ================================================================ */
    [data-theme="dark"] {
        --bill-bg-body: #0F172A;
        --bill-bg-card: #1E293B;
        --bill-text-primary: #F1F5F9;
        --bill-text-secondary: #94A3B8;
        --bill-border: #334155;
        --bill-primary: #34D399;
        --bill-primary-bg: #1A3A2A;
        --bill-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --bill-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
        --bill-table-hover: #1A3A2A;
    }
    
    /* ================================================================
       PAGE HEADER - BLUE GREEN THEME
       ================================================================ */
    .page-header-custom {
        background: var(--bill-primary-gradient-strong);
        border-radius: var(--bill-radius-lg);
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(4, 120, 87, 0.35);
        position: relative;
        overflow: hidden;
    }
    
    .page-header-custom .page-title {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }
    
    .page-header-custom .page-title i { font-size: 2rem; opacity: 0.9; }
    
    .page-header-custom .page-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 8px;
    }
    
    .page-header-custom .role-badge-display {
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
    
    .page-header-custom .header-badge {
        background: rgba(255,255,255,0.12);
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
    
    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: var(--bill-radius);
        font-weight: 500;
        font-size: 0.82rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
        transition: all 0.3s ease;
    }
    
    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }
    
    /* ================================================================
       5 CARDS WITH BACKGROUND COLORS
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
        margin-bottom: 24px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .stat-card {
        border-radius: var(--bill-radius);
        padding: 18px 20px;
        border: 2px solid transparent;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
        box-shadow: var(--bill-shadow-md);
        text-decoration: none;
        color: inherit;
        position: relative;
        overflow: hidden;
        min-height: 100px;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: -30%;
        right: -20%;
        width: 120px;
        height: 120px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--bill-shadow-xl);
    }
    
    .stat-card:hover::before {
        transform: scale(1.3);
        right: -10%;
    }
    
    /* CARD 1: Total - BLUE */
    .stat-card.card-total {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        border-color: #0A4CA8;
    }
    .stat-card.card-total:hover { box-shadow: 0 12px 36px rgba(11, 94, 215, 0.4); }
    .stat-card.card-total .stat-icon {
        background: rgba(255,255,255,0.2);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    /* CARD 2: Discount - ORANGE */
    .stat-card.card-discount {
        background: linear-gradient(135deg, #D97706, #B45309);
        color: white;
        border-color: #B45309;
    }
    .stat-card.card-discount:hover { box-shadow: 0 12px 36px rgba(217, 119, 6, 0.4); }
    .stat-card.card-discount .stat-icon {
        background: rgba(255,255,255,0.2);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    /* CARD 3: Premium - GOLD */
    .stat-card.card-premium {
        background: linear-gradient(135deg, #F59E0B, #D97706);
        color: white;
        border-color: #D97706;
        animation: premiumPulse 2s ease-in-out infinite;
    }
    .stat-card.card-premium:hover { box-shadow: 0 12px 36px rgba(245, 158, 11, 0.5); }
    .stat-card.card-premium .stat-icon {
        background: rgba(255,255,255,0.25);
        color: white;
        border: 1px solid rgba(255,255,255,0.3);
    }
    
    @keyframes premiumPulse {
        0%, 100% { box-shadow: 0 4px 16px rgba(217, 119, 6, 0.3); }
        50% { box-shadow: 0 4px 24px rgba(217, 119, 6, 0.6); }
    }
    
    /* CARD 4: Paid - GREEN */
    .stat-card.card-paid {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        border-color: #047857;
    }
    .stat-card.card-paid:hover { box-shadow: 0 12px 36px rgba(5, 150, 105, 0.4); }
    .stat-card.card-paid .stat-icon {
        background: rgba(255,255,255,0.2);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    /* CARD 5: Balance - RED/GREEN */
    .stat-card.card-balance {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        border-color: #B91C1C;
    }
    .stat-card.card-balance.zero {
        background: linear-gradient(135deg, #059669, #047857);
        border-color: #047857;
    }
    .stat-card.card-balance:hover { box-shadow: 0 12px 36px rgba(220, 38, 38, 0.4); }
    .stat-card.card-balance.zero:hover { box-shadow: 0 12px 36px rgba(5, 150, 105, 0.4); }
    .stat-card.card-balance .stat-icon {
        background: rgba(255,255,255,0.2);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    .stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        transition: all 0.3s ease;
        backdrop-filter: blur(8px);
        position: relative;
        z-index: 1;
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(-3deg);
    }
    
    .stat-card .stat-content {
        flex: 1;
        position: relative;
        z-index: 1;
    }
    
    .stat-label {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0;
        display: block;
        color: rgba(255,255,255,0.9);
    }
    
    .stat-value {
        font-size: 1.15rem;
        font-weight: 800;
        margin: 3px 0 0 0;
        line-height: 1.2;
        display: block;
        color: white;
        letter-spacing: -0.02em;
    }
    
    .stat-sub {
        font-size: 0.55rem;
        margin-top: 3px;
        display: block;
        color: rgba(255,255,255,0.85);
    }
    
    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card {
        background: var(--bill-bg-card);
        border-radius: var(--bill-radius-lg);
        padding: 32px 36px;
        border: 2px solid var(--bill-border);
        transition: all 0.3s ease;
        max-width: 1200px;
        margin: 0 auto 24px;
        box-shadow: var(--bill-shadow-md);
    }
    
    .form-card:hover {
        border-color: var(--bill-primary);
        box-shadow: var(--bill-shadow-lg);
    }
    
    .form-card .form-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--bill-border);
    }
    
    .form-card .form-header .form-icon {
        width: 52px;
        height: 52px;
        background: var(--bill-primary-gradient);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.4rem;
        flex-shrink: 0;
        box-shadow: 0 4px 16px rgba(5, 150, 105, 0.25);
    }
    
    .form-card .form-header .form-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--bill-text-primary);
        margin: 0;
    }
    
    .form-card .form-header .form-subtitle {
        font-size: 0.8rem;
        color: var(--bill-text-secondary);
        margin-top: 2px;
    }
    
    .form-label {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--bill-text-primary);
        margin-bottom: 5px;
        display: block;
    }
    
    .form-label .required { color: var(--bill-danger); margin-left: 2px; }
    .form-label .label-icon { margin-right: 4px; color: var(--bill-primary); }
    .form-label .label-badge {
        font-weight: 400;
        font-size: 0.6rem;
        padding: 1px 10px;
        border-radius: 12px;
        background: #F1F5F9;
        color: var(--bill-text-secondary);
        margin-left: 6px;
    }
    
    [data-theme="dark"] .form-label .label-badge {
        background: #334155;
        color: #94A3B8;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--bill-border);
        border-radius: var(--bill-radius);
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bill-bg-card);
        color: var(--bill-text-primary);
        font-family: inherit;
    }
    
    .form-control:focus {
        border-color: var(--bill-primary);
        box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12);
    }
    
    .form-control::placeholder {
        color: var(--bill-text-secondary);
        opacity: 0.5;
    }
    
    .form-control:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    /* ✅ PREMIUM INPUT */
    .premium-input {
        border-color: #D97706 !important;
        background: #FEF3C7 !important;
        color: #D97706 !important;
        font-weight: 700 !important;
    }
    
    [data-theme="dark"] .premium-input {
        background: #3D2E0A !important;
        color: #FCD34D !important;
    }
    
    .premium-input:focus {
        border-color: #D97706 !important;
        box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.15) !important;
    }
    
    select.form-control { appearance: auto; cursor: pointer; }
    textarea.form-control { resize: vertical; min-height: 80px; }
    
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-row { margin-bottom: 20px; }
    .form-row:last-child { margin-bottom: 0; }
    
    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
    }
    
    .badge-success { background: #059669; }
    .badge-danger { background: #DC2626; }
    .badge-warning { background: #D97706; color: #1E293B; }
    .badge-info { background: #0B5ED7; }
    .badge-secondary { background: #64748B; }
    .badge-purple { background: #7C3AED; }
    
    [data-theme="dark"] .badge-warning { color: #1E293B; }
    
    .item-type-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.55rem;
        font-weight: 600;
    }
    
    .item-type-badge.blue { background: #EFF6FF; color: #0B5ED7; }
    .item-type-badge.purple { background: #F5F3FF; color: #7C3AED; }
    .item-type-badge.orange { background: #FFFBEB; color: #F59E0B; }
    .item-type-badge.green { background: #D1FAE5; color: #059669; }
    .item-type-badge.red { background: #FEE2E2; color: #DC2626; }
    .item-type-badge.teal { background: #ECFDF5; color: #0D9488; }
    .item-type-badge.gray { background: #F1F5F9; color: #64748B; }
    
    [data-theme="dark"] .item-type-badge.blue { background: #1E3A5F; color: #3B82F6; }
    [data-theme="dark"] .item-type-badge.purple { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .item-type-badge.orange { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .item-type-badge.green { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .item-type-badge.red { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .item-type-badge.teal { background: #0F3D3D; color: #5EEAD4; }
    [data-theme="dark"] .item-type-badge.gray { background: #334155; color: #94A3B8; }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: var(--bill-radius);
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn:hover { transform: translateY(-2px); }
    
    .btn-primary {
        background: var(--bill-primary-gradient);
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }
    .btn-primary:hover { box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35); }
    
    .btn-success {
        background: var(--bill-success);
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }
    .btn-success:hover { box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35); }
    
    .btn-outline {
        background: transparent;
        color: var(--bill-text-secondary);
        border: 2px solid var(--bill-border);
    }
    .btn-outline:hover { border-color: var(--bill-primary); color: var(--bill-primary); }
    
    .btn-danger {
        background: var(--bill-danger);
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
    }
    .btn-danger:hover { box-shadow: 0 6px 24px rgba(220, 38, 38, 0.35); }
    
    .btn-sm {
        padding: 5px 14px;
        font-size: 0.75rem;
        border-radius: 8px;
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--bill-border);
        flex-wrap: wrap;
    }
    
    /* ================================================================
       TABLE
       ================================================================ */
    .table-container {
        background: var(--bill-bg-card);
        border-radius: var(--bill-radius-lg);
        border: 2px solid var(--bill-border);
        overflow: hidden;
        box-shadow: var(--bill-shadow-sm);
        margin-bottom: 24px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .table-container .card-header {
        padding: 14px 20px;
        background: var(--bill-primary-gradient-strong);
        border-bottom: 2px solid var(--bill-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .table-container .card-header .card-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: white;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .table-container .card-header .card-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
    }
    
    .data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.78rem;
    }
    
    .data-table thead th {
        background: var(--bill-bg-body);
        color: var(--bill-text-secondary);
        font-weight: 700;
        padding: 10px 14px;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--bill-border);
        text-align: left;
    }
    
    .data-table td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--bill-border);
        color: var(--bill-text-primary);
        vertical-align: middle;
    }
    
    .data-table tbody tr:hover td { background: var(--bill-table-hover); }
    .data-table tbody tr:last-child td { border-bottom: none; }
    
    .data-table .total-label {
        text-align: right;
        font-weight: 700;
        font-size: 0.75rem;
        color: var(--bill-text-secondary);
        padding: 8px 14px;
    }
    
    .data-table .total-amount {
        text-align: right;
        font-family: monospace;
        font-weight: 700;
        padding: 8px 14px;
    }
    
    .data-table .total-amount.green { color: var(--bill-primary); }
    
    /* ================================================================
       ALERTS
       ================================================================ */
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 0.82rem;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        border: 2px solid transparent;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .alert-success {
        background: #D1FAE5;
        color: #065F46;
        border-color: #34D399;
    }
    .alert-danger {
        background: #FEE2E2;
        color: #991B1B;
        border-color: #F87171;
    }
    
    [data-theme="dark"] .alert-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #059669;
    }
    [data-theme="dark"] .alert-danger {
        background: #3A1A1A;
        color: #F87171;
        border-color: #DC2626;
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
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: var(--bill-success); }
    .toast-custom.error { background: var(--bill-danger); }
    .toast-custom.info { background: var(--bill-primary); }
    .toast-custom.warning { background: var(--bill-warning); }
    
    /* ================================================================
       TEXT UTILITIES
       ================================================================ */
    .text-xl { font-size: 1.25rem; }
    .font-bold { font-weight: 700; }
    .text-green-600 { color: var(--bill-success); }
    .text-blue-600 { color: #0B5ED7; }
    .text-purple-600 { color: var(--bill-purple); }
    .text-red-600 { color: var(--bill-danger); }
    .text-gray-400 { color: #94A3B8; }
    .text-xs { font-size: 0.7rem; }
    .text-center { text-align: center; }
    .py-6 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .block { display: block; }
    
    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(3, 1fr); }
    }
    
    @media (max-width: 1024px) {
        .grid-2 { grid-template-columns: 1fr; gap: 14px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    
    @media (max-width: 768px) {
        .page-header-custom { padding: 16px 18px; }
        .page-header-custom .page-title { font-size: 1.3rem; }
        .form-card { padding: 16px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .data-table { font-size: 0.65rem; }
        .data-table thead th, .data-table td { padding: 6px 8px; }
    }
    
    @media (max-width: 480px) {
        .page-header-custom { flex-direction: column; align-items: flex-start !important; }
        .stats-grid { grid-template-columns: 1fr; }
    }
    
    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .btn, .form-actions, .page-header-custom .btn-outline-light { display: none !important; }
        .form-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .table-container { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
        .page-header-custom {
            background: #059669 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-invoice"></i>
                Edit Bill
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                <strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= isset($bill['status']) && $bill['status'] === 'paid' ? 'check-circle' : 'clock' ?>"></i>
                    <?= ucfirst($bill['status'] ?? 'Pending') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i>
                    TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                </span>
                <?php if (($bill['premium_amount'] ?? 0) > 0): ?>
                    <span class="header-badge" style="background:rgba(245,158,11,0.3);border-color:rgba(245,158,11,0.4);color:#FCD34D;">
                        <i class="fas fa-crown"></i> Premium: TSh <?= number_format($bill['premium_amount'], 0) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="view_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 5 CARDS WITH BACKGROUND COLORS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        
        <!-- CARD 1: Total - BLUE -->
        <div class="stat-card card-total">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Bill</p>
                <p class="stat-value">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></p>
                <p class="stat-sub"><i class="fas fa-receipt"></i> Bill amount</p>
            </div>
        </div>
        
        <!-- CARD 2: Discount - ORANGE -->
        <div class="stat-card card-discount">
            <div class="stat-icon"><i class="fas fa-tags"></i></div>
            <div class="stat-content">
                <p class="stat-label">Discount</p>
                <p class="stat-value">TSh <?= number_format($bill['discount_amount'] ?? 0, 0) ?></p>
                <p class="stat-sub"><i class="fas fa-percent"></i> Total discount</p>
            </div>
        </div>
        
        <!-- CARD 3: Premium - GOLD -->
        <div class="stat-card card-premium">
            <div class="stat-icon"><i class="fas fa-crown"></i></div>
            <div class="stat-content">
                <p class="stat-label">Premium</p>
                <p class="stat-value">TSh <?= number_format($bill['premium_amount'] ?? 0, 0) ?></p>
                <?php if (!empty($bill['premium_note'])): ?>
                    <p class="stat-sub"><?= htmlspecialchars($bill['premium_note']) ?></p>
                <?php else: ?>
                    <p class="stat-sub">
                        <i class="fas fa-star"></i> <?= ($bill['premium_amount'] ?? 0) > 0 ? 'Premium charged' : 'No premium' ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- CARD 4: Paid - GREEN -->
        <div class="stat-card card-paid">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Paid Amount</p>
                <p class="stat-value">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></p>
                <p class="stat-sub">
                    <?php if (($bill['paid_amount'] ?? 0) > 0): ?>
                        <?php if (($bill['balance'] ?? 0) <= 0): ?>
                            <i class="fas fa-check-circle"></i> Fully paid
                        <?php else: ?>
                            <i class="fas fa-hourglass-half"></i> Partial paid
                        <?php endif; ?>
                    <?php else: ?>
                        <i class="fas fa-times-circle"></i> No payment
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- CARD 5: Balance - RED/GREEN -->
        <div class="stat-card card-balance <?= ($bill['balance'] ?? 0) <= 0 ? 'zero' : '' ?>">
            <div class="stat-icon"><i class="fas <?= ($bill['balance'] ?? 0) > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i></div>
            <div class="stat-content">
                <p class="stat-label">Balance</p>
                <p class="stat-value">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></p>
                <p class="stat-sub">
                    <?php if (($bill['balance'] ?? 0) > 0): ?>
                        <i class="fas fa-clock"></i> Pending payment
                    <?php else: ?>
                        <i class="fas fa-check-circle"></i> Fully paid!
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- BILL INFORMATION FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up">
        <div class="form-header">
            <div class="form-icon"><i class="fas fa-file-invoice"></i></div>
            <div>
                <h3 class="form-title">Bill Information</h3>
                <p class="form-subtitle">Update bill details, discount, and premium</p>
            </div>
        </div>

        <form method="POST" action="" id="billForm">
            <input type="hidden" name="action" value="update_bill">
            
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-file-invoice label-icon"></i> Bill Number
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>" disabled>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user label-icon"></i> Patient
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?> (<?= htmlspecialchars($bill['patient_code'] ?? 'N/A') ?>)" disabled>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-store label-icon"></i> Branch
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?>" disabled>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user-plus label-icon"></i> Created By
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?>" disabled>
                </div>
            </div>
            
            <!-- Discount & Premium -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-money-bill-wave label-icon"></i> Discount Amount
                        <span class="label-badge" style="background:#FEF3C7;color:#D97706;">TSh</span>
                    </label>
                    <input type="text" name="discount_amount" class="form-control" 
                           value="<?= number_format($bill['discount_amount'] ?? 0, 0) ?>" 
                           placeholder="0" oninput="formatAmount(this)">
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-crown label-icon" style="color:#D97706;"></i> Premium Amount
                        <span class="label-badge" style="background:#FEF3C7;color:#D97706;">TSh</span>
                    </label>
                    <input type="text" name="premium_amount" class="form-control premium-input" 
                           value="<?= number_format($bill['premium_amount'] ?? 0, 0) ?>" 
                           placeholder="0" oninput="formatAmount(this)">
                </div>
            </div>
            
            <!-- Premium Note -->
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-sticky-note label-icon" style="color:#D97706;"></i> Premium Note
                    <span class="label-badge">Optional</span>
                </label>
                <input type="text" name="premium_note" class="form-control" 
                       value="<?= htmlspecialchars($bill['premium_note'] ?? '') ?>" 
                       placeholder="e.g. Premium service charge">
            </div>
            
            <!-- Status -->
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-info-circle label-icon"></i> Status <span class="required">*</span>
                    </label>
                    <select name="status" class="form-control" required>
                        <option value="pending" <?= ($bill['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="partial" <?= ($bill['status'] ?? 'pending') === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="paid" <?= ($bill['status'] ?? 'pending') === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="cancelled" <?= ($bill['status'] ?? 'pending') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-sticky-note label-icon"></i> Notes
                    <span class="label-badge">Optional</span>
                </label>
                <textarea name="notes" class="form-control" rows="2" 
                          placeholder="Additional notes about this bill..."><?= htmlspecialchars($bill['notes'] ?? '') ?></textarea>
            </div>
            
            <!-- Bill Summary -->
            <div class="grid-2" style="margin-top:16px;padding-top:16px;border-top:2px solid var(--bill-border);">
                <div class="form-row">
                    <label class="form-label">Subtotal</label>
                    <div class="text-xl font-bold text-green-600">TSh <?= number_format($bill['subtotal'] ?? 0, 0) ?></div>
                </div>
                <div class="form-row">
                    <label class="form-label">Discount</label>
                    <div class="text-xl font-bold" style="color:var(--bill-warning);">- TSh <?= number_format($bill['discount_amount'] ?? 0, 0) ?></div>
                </div>
                <div class="form-row">
                    <label class="form-label">👑 Premium</label>
                    <div class="text-xl font-bold" style="color:#D97706;">+ TSh <?= number_format($bill['premium_amount'] ?? 0, 0) ?></div>
                </div>
                <div class="form-row">
                    <label class="form-label">Grand Total</label>
                    <div class="text-xl font-bold text-blue-600">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></div>
                </div>
                <div class="form-row">
                    <label class="form-label">Paid Amount</label>
                    <div class="text-xl font-bold text-purple-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></div>
                </div>
                <div class="form-row">
                    <label class="form-label">Balance</label>
                    <div class="text-xl font-bold <?= ($bill['balance'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' ?>">
                        TSh <?= number_format($bill['balance'] ?? 0, 0) ?>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Bill
                </button>
                <a href="view_bill.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- BILL ITEMS TABLE -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                Bill Items
                <span class="card-badge"><?= count($bill_items) ?></span>
            </h3>
        </div>
        <?php if (count($bill_items) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Unit Price</th>
                            <th style="text-align:right;">Total</th>
                            <th>Status</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($bill_items as $item): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                                    <?php if (!empty($item['description'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($item['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="item-type-badge <?= getItemTypeColor($item['item_type'] ?? 'other') ?>">
                                        <?= getItemTypeLabel($item['item_type'] ?? 'other') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;"><?= number_format($item['quantity'] ?? 0) ?></td>
                                <td style="text-align:right;font-family:monospace;">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td style="text-align:right;font-family:monospace;font-weight:700;color:var(--bill-primary);">
                                    TSh <?= number_format($item['total_price'] ?? 0, 0) ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= ($item['status'] ?? 'pending') === 'paid' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($item['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this item?')">
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding:3px 10px;font-size:0.6rem;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="total-label">Subtotal:</td>
                            <td class="total-amount green">TSh <?= number_format($bill['subtotal'] ?? 0, 0) ?></td>
                            <td colspan="2"></td>
                        </tr>
                        <?php if (($bill['discount_amount'] ?? 0) > 0): ?>
                        <tr style="background:var(--bill-warning-bg);">
                            <td colspan="5" class="total-label">Discount:</td>
                            <td class="total-amount" style="color:var(--bill-warning);">- TSh <?= number_format($bill['discount_amount'] ?? 0, 0) ?></td>
                            <td colspan="2"></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (($bill['premium_amount'] ?? 0) > 0): ?>
                        <tr style="background:#FEF3C7;">
                            <td colspan="5" class="total-label" style="color:#D97706;">👑 Premium:</td>
                            <td class="total-amount" style="color:#D97706;">+ TSh <?= number_format($bill['premium_amount'] ?? 0, 0) ?></td>
                            <td colspan="2"></td>
                        </tr>
                        <?php endif; ?>
                        <tr style="background:var(--bill-primary-bg);font-size:1rem;">
                            <td colspan="5" class="total-label" style="font-weight:700;">Grand Total:</td>
                            <td class="total-amount" style="color:var(--bill-primary);font-size:1.1rem;font-weight:700;">
                                TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-file-invoice text-2xl block mb-2"></i>
                <p>No items found for this bill</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- ADD ITEM FORM -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="form-header">
            <div class="form-icon"><i class="fas fa-plus-circle"></i></div>
            <div>
                <h3 class="form-title">Add Item to Bill</h3>
                <p class="form-subtitle">Add a new item to this bill</p>
            </div>
        </div>
        
        <form method="POST" action="" id="addItemForm">
            <input type="hidden" name="action" value="add_item">
            
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-tag label-icon"></i> Item Type <span class="required">*</span>
                    </label>
                    <select name="item_type" class="form-control" required>
                        <?php foreach ($item_types as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-cube label-icon"></i> Item Name <span class="required">*</span>
                    </label>
                    <input type="text" name="item_name" class="form-control" placeholder="e.g. Consultation Fee" required>
                </div>
            </div>
            
            <div class="grid-2">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-calculator label-icon"></i> Quantity <span class="required">*</span>
                    </label>
                    <input type="number" name="quantity" class="form-control" value="1" min="1" required>
                </div>
                
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-money-bill-wave label-icon"></i> Unit Price <span class="required">*</span>
                        <span class="label-badge">TSh</span>
                    </label>
                    <input type="text" name="unit_price" class="form-control" placeholder="0" value="0" oninput="formatAmount(this)" required>
                </div>
            </div>
            
            <div class="form-row">
                <label class="form-label">
                    <i class="fas fa-align-left label-icon"></i> Description
                    <span class="label-badge">Optional</span>
                </label>
                <textarea name="description" class="form-control" rows="2" placeholder="Item description..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Item
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

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
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FORMAT AMOUNT
    // ================================================================
    function formatAmount(input) {
        var val = input.value.replace(/[^0-9.]/g, '');
        var parts = val.split('.');
        var whole = parts[0];
        var decimal = parts.length > 1 ? '.' + parts[1].slice(0, 2) : '';
        if (whole.length > 0) {
            whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        input.value = whole + decimal;
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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    <?php if ($message_type === 'success'): ?>
        showToast('✅ Success', '<?= addslashes(strip_tags($message)) ?>', 'success');
    <?php elseif ($message_type === 'error'): ?>
        showToast('❌ Error', '<?= addslashes(strip_tags($message)) ?>', 'error');
    <?php endif; ?>

    console.log('%c📄 Braick Dispensary - Edit Bill (WITH PREMIUM)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Bill: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ 5 CARDS with BACKGROUND COLORS', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Premium Card + Input ADDED', 'font-size:13px; color:#FCD34D;');
    console.log('%c✅ Formula: Total = Subtotal - Discount + Premium', 'font-size:13px; color:#FBBF24;');
</script>

</body>
</html>