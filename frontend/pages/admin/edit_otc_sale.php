<?php
// ================================================================
// FILE: frontend/pages/admin/edit_otc_sale.php
// EDIT OTC SALE V2 - Admin Module
// ✅ Money format 1,000,000,000 (auto comma)
// ✅ Dosage, Frequency, Route, Instructions per item
// ✅ Edit customer info, payment, notes
// ✅ Edit items (qty, unit price) with auto-calculations
// ✅ Full dark mode support
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['admin', 'pharmacy', 'cashier'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET SALE ID
// ================================================================
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($sale_id <= 0) {
    header('Location: otc_sales.php?branch=' . urlencode($selected_branch_id));
    exit;
}

$message = '';
$message_type = '';

// ================================================================
// HANDLE UPDATE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_otc') {
    try {
        $db->beginTransaction();
        
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $payment_status = $_POST['payment_status'] ?? 'unpaid';
        $payment_method = $_POST['payment_method'] ?? 'cash';
        
        // ✅ Clean money values (remove commas)
        $discount_amount = (float)str_replace(',', '', $_POST['discount_amount'] ?? '0');
        $premium_amount = (float)str_replace(',', '', $_POST['premium_amount'] ?? '0');
        $notes = trim($_POST['notes'] ?? '');
        
        $items = $_POST['items'] ?? [];
        
        if (empty($items)) {
            throw new Exception("At least one item is required.");
        }
        
        // Calculate subtotal from items
        $subtotal = 0;
        $clean_items = [];
        
        foreach ($items as $idx => $item) {
            $item_name = trim($item['item_name'] ?? '');
            $quantity = (int)($item['quantity'] ?? 0);
            
            // ✅ Clean unit price (remove commas)
            $unit_price = (float)str_replace(',', '', $item['unit_price'] ?? '0');
            
            if (empty($item_name) || $quantity <= 0) continue;
            
            $total_price = $quantity * $unit_price;
            $subtotal += $total_price;
            
            // ✅ Get dosage, frequency, route, instructions
            $dosage = trim($item['dosage'] ?? '');
            $frequency = trim($item['frequency'] ?? '');
            $route = trim($item['route'] ?? '');
            $instructions = trim($item['instructions'] ?? '');
            
            $clean_items[] = [
                'id' => isset($item['id']) ? (int)$item['id'] : 0,
                'item_name' => $item_name,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'dosage' => $dosage,
                'frequency' => $frequency,
                'route' => $route,
                'instructions' => $instructions
            ];
        }
        
        if (empty($clean_items)) {
            throw new Exception("At least one valid item is required.");
        }
        
        // Final total = subtotal + premium - discount
        $total_amount = $subtotal + $premium_amount - $discount_amount;
        if ($total_amount < 0) $total_amount = 0;
        
        // Get current paid amount
        $stmt = $db->prepare("SELECT paid_amount FROM bills WHERE id = (SELECT bill_id FROM otc_sales WHERE id = ?)");
        $stmt->execute([$sale_id]);
        $paid_amount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['paid_amount'] ?? 0);
        
        // Determine actual status based on paid vs total
        if ($paid_amount >= $total_amount && $total_amount > 0) {
            $payment_status = 'paid';
        } elseif ($paid_amount > 0 && $paid_amount < $total_amount) {
            $payment_status = 'partial';
        }
        
        $balance = $total_amount - $paid_amount;
        if ($balance < 0) $balance = 0;
        
        // Update otc_sales
        $stmt = $db->prepare("
            UPDATE otc_sales 
            SET customer_name = ?,
                customer_phone = ?,
                subtotal = ?,
                discount_amount = ?,
                premium_amount = ?,
                total_amount = ?,
                payment_status = ?,
                payment_method = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $customer_name,
            $customer_phone,
            $subtotal,
            $discount_amount,
            $premium_amount,
            $total_amount,
            $payment_status,
            $payment_method,
            $notes,
            $sale_id
        ]);
        
        // Get bill_id if exists
        $stmt = $db->prepare("SELECT bill_id FROM otc_sales WHERE id = ?");
        $stmt->execute([$sale_id]);
        $bill_id = $stmt->fetch(PDO::FETCH_ASSOC)['bill_id'] ?? null;
        
        // Update bill if exists
        if ($bill_id) {
            $bill_status = 'pending';
            if ($paid_amount >= $total_amount && $total_amount > 0) {
                $bill_status = 'paid';
            } elseif ($paid_amount > 0) {
                $bill_status = 'partial';
            }
            
            $stmt = $db->prepare("
                UPDATE bills
                SET subtotal = ?,
                    discount_amount = ?,
                    total_amount = ?,
                    balance = ?,
                    status = ?,
                    payment_method = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $subtotal,
                $discount_amount,
                $total_amount,
                $balance,
                $bill_status,
                $payment_method,
                $bill_id
            ]);
            
            // Delete existing bill items and re-insert
            $db->prepare("DELETE FROM bill_items WHERE bill_id = ?")->execute([$bill_id]);
            
            // Get patient_id and branch_id from otc_sales
            $stmt = $db->prepare("SELECT patient_id, branch_id FROM otc_sales WHERE id = ?");
            $stmt->execute([$sale_id]);
            $sale_info = $stmt->fetch(PDO::FETCH_ASSOC);
            $patient_id = $sale_info['patient_id'] ?? null;
            $branch_id_for_item = $sale_info['branch_id'] ?? $user_branch_id;
            
            foreach ($clean_items as $item) {
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id, item_type, item_name,
                        quantity, unit_price, total_price, discount_amount,
                        tax_amount, final_price, status, created_at, updated_at
                    ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, 0, 0, ?, 'pending', NOW(), NOW())
                ");
                $stmt->execute([
                    $bill_id,
                    $patient_id,
                    $branch_id_for_item,
                    $item['item_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['total_price']
                ]);
            }
        }
        
        // Delete existing otc_sale_items and re-insert
        $db->prepare("DELETE FROM otc_sale_items WHERE sale_id = ?")->execute([$sale_id]);
        
        foreach ($clean_items as $item) {
            $stmt = $db->prepare("
                INSERT INTO otc_sale_items (
                    sale_id, item_name, quantity, dosage, frequency, route, instructions,
                    unit_price, total_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sale_id,
                $item['item_name'],
                $item['quantity'],
                $item['dosage'],
                $item['frequency'],
                $item['route'],
                $item['instructions'],
                $item['unit_price'],
                $item['total_price']
            ]);
        }
        
        // Log activity
        try {
            $stmt = $db->prepare("SELECT sale_number FROM otc_sales WHERE id = ?");
            $stmt->execute([$sale_id]);
            $sale_number = $stmt->fetch(PDO::FETCH_ASSOC)['sale_number'] ?? 'N/A';
            
            $db->prepare("INSERT INTO activity_logs (user_id, action, description, branch_id, ip_address, created_at) 
                          VALUES (?, 'UPDATE_OTC_SALE', ?, ?, ?, NOW())")
               ->execute([
                   $user_id,
                   "Updated OTC sale: " . $sale_number,
                   $user_branch_id,
                   $_SERVER['REMOTE_ADDR'] ?? 'unknown'
               ]);
        } catch (Exception $e) {}
        
        $db->commit();
        
        $_SESSION['otc_sale_message'] = "OTC Sale updated successfully!";
        $_SESSION['otc_sale_message_type'] = 'success';
        $_SESSION['otc_sale_message_time'] = time();
        
        header('Location: otc_sales.php?branch=' . urlencode($selected_branch_id));
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ================================================================
// GET SALE DATA
// ================================================================
$sale = null;
try {
    $stmt = $db->prepare("
        SELECT os.*,
               COALESCE(b.paid_amount, 0) as paid_amount,
               COALESCE(b.balance, os.total_amount) as bill_balance,
               COALESCE(b.status, 'pending') as bill_status,
               b.bill_number,
               br.name as branch_name,
               u.full_name as sold_by_name
        FROM otc_sales os
        LEFT JOIN bills b ON os.bill_id = b.id
        LEFT JOIN branches br ON os.branch_id = br.id
        LEFT JOIN users u ON os.sold_by = u.id
        WHERE os.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = "Error loading sale: " . $e->getMessage();
    $message_type = 'error';
}

if (!$sale) {
    header('Location: otc_sales.php?branch=' . urlencode($selected_branch_id));
    exit;
}

// ================================================================
// GET SALE ITEMS
// ================================================================
$sale_items = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM otc_sale_items
        WHERE sale_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// DOSAGE/FREQUENCY/ROUTE OPTIONS
// ================================================================
$dosage_options = ['1', '2', '3', '4', '5', '10', '15', '20', '25', '30', '50', '100', '150', '200', '250', '500', '1000'];
$frequency_options = ['Once Daily', 'Twice Daily', '3x Daily', '4x Daily', 'Every 4 Hours', 'Every 6 Hours', 'Every 8 Hours', 'Every 12 Hours', 'As Needed', 'Weekly', 'Monthly'];
$route_options = ['Oral', 'Injection', 'IV', 'IM', 'SC', 'Topical', 'Inhalation', 'Nasal', 'Ophthalmic', 'Otic', 'Rectal', 'Vaginal', 'Sublingual'];

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    i.fas, i.far, i.fab, i.fa, i[class*="fa-"] {
        font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands", "FontAwesome" !important;
        font-weight: 900 !important;
        font-style: normal !important;
        display: inline-block !important;
        line-height: 1;
    }

    :root {
        --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        --font-mono: 'JetBrains Mono', 'Courier New', monospace;
        
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #E8F0FE;
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-hover: #1E3A5F;
    }

    body, .main-content, .main-content * {
        font-family: var(--font-primary);
        -webkit-font-smoothing: antialiased;
    }

    body { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* PAGE HEADER */
    .page-header-custom {
        background: linear-gradient(135deg, #D97706 0%, #B45309 50%, #92400E 100%);
        border-radius: 18px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        box-shadow: 0 8px 32px rgba(217, 119, 6, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-custom .page-title {
        color: white;
        font-size: 1.6rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
        letter-spacing: -0.02em;
    }

    .page-header-custom .page-title i {
        width: 44px; height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .page-subtitle {
        color: rgba(255,255,255,0.9);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 8px;
    }

    .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .btn-outline-light {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1.5px solid rgba(255,255,255,0.3);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        position: relative;
        z-index: 1;
        cursor: pointer;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* ALERT */
    .alert-custom {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .alert-custom.success { background: #D1FAE5; color: #065F46; border-left: 4px solid #059669; }
    .alert-custom.error { background: #FEE2E2; color: #991B1B; border-left: 4px solid #DC2626; }

    /* CARD */
    .card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        margin-bottom: 20px;
    }

    html[data-theme="dark"] .card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .card-header-custom {
        padding: 14px 20px;
        background: var(--page-hover, #F8FAFC);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    html[data-theme="dark"] .card-header-custom {
        background: #0F172A;
        border-color: #334155;
    }

    .card-title-custom {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    html[data-theme="dark"] .card-title-custom { color: #F1F5F9; }
    .card-title-custom i { color: #0B5ED7; }
    html[data-theme="dark"] .card-title-custom i { color: #6EA8FE; }

    .card-body-custom { padding: 20px; }

    /* FORM */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
    }

    .form-group { display: flex; flex-direction: column; gap: 6px; }

    .form-group label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .form-group label i { color: #0B5ED7; font-size: 0.7rem; }

    .form-control {
        padding: 10px 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--page-bg-body, #F1F5F9);
        color: var(--page-text-primary, #1E293B);
        outline: none;
        transition: all 0.3s;
        font-family: var(--font-primary);
        width: 100%;
    }

    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }

    html[data-theme="dark"] .form-control {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    .form-control.mono {
        font-family: var(--font-mono);
        font-weight: 700;
        text-align: right;
    }

    /* ✅ MONEY INPUT - auto comma */
    .money-input {
        font-family: var(--font-mono) !important;
        font-weight: 700;
        text-align: right;
        letter-spacing: -0.02em;
    }

    /* ITEMS TABLE */
    .items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 1200px;
    }

    .items-table thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        font-weight: 700;
        padding: 10px 10px;
        font-size: 0.62rem;
        text-transform: uppercase;
        text-align: left;
        white-space: nowrap;
    }

    .items-table thead th:first-child { border-radius: 8px 0 0 0; }
    .items-table thead th:last-child { border-radius: 0 8px 0 0; }

    .items-table tbody td {
        padding: 8px 8px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    html[data-theme="dark"] .items-table tbody td {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .items-table input,
    .items-table select {
        width: 100%;
        padding: 7px 10px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        font-size: 0.78rem;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        outline: none;
        font-family: var(--font-primary);
        transition: all 0.3s;
    }

    .items-table input:focus,
    .items-table select:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    html[data-theme="dark"] .items-table input,
    html[data-theme="dark"] .items-table select {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    .items-table input.mono {
        font-family: var(--font-mono);
        font-weight: 700;
        text-align: right;
    }

    /* ✅ MODERN INPUT SIZE */
    .items-table .input-sm {
        padding: 6px 8px;
        font-size: 0.75rem;
    }

    /* INFO BOX */
    .info-box {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .info-item {
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    html[data-theme="dark"] .info-item {
        background: #1E293B;
        border-color: #334155;
    }

    .info-item .info-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .info-item .info-content { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .info-item .info-label { font-size: 0.6rem; color: var(--page-text-secondary, #64748B); font-weight: 700; text-transform: uppercase; }
    .info-item .info-value { font-size: 0.85rem; color: var(--page-text-primary, #1E293B); font-weight: 700; font-family: var(--font-mono); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    html[data-theme="dark"] .info-item .info-value { color: #F1F5F9; }

    /* TOTALS */
    .totals-box {
        background: var(--page-hover, #F8FAFC);
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        padding: 18px 22px;
        margin-top: 20px;
    }

    html[data-theme="dark"] .totals-box {
        background: #0F172A;
        border-color: #334155;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px dashed var(--page-border, #E2E8F0);
        font-size: 0.9rem;
        gap: 12px;
    }

    .total-row:last-child { border-bottom: none; }

    .total-row.grand-total {
        margin-top: 8px;
        padding-top: 14px;
        border-top: 3px solid #0B5ED7;
        border-bottom: none;
        font-size: 1.1rem;
        font-weight: 800;
    }

    .total-row .label {
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .total-row .value {
        font-family: var(--font-mono);
        font-weight: 800;
        color: var(--page-text-primary, #1E293B);
        font-size: 1rem;
    }

    html[data-theme="dark"] .total-row .value { color: #F1F5F9; }

    .total-row.grand-total .value {
        color: #0B5ED7;
        font-size: 1.35rem;
    }

    html[data-theme="dark"] .total-row.grand-total .value { color: #6EA8FE; }

    /* ✅ MONEY INPUT FIELD KATIKA TOTALS */
    .money-field-wrapper {
        display: flex;
        align-items: center;
        gap: 6px;
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        padding: 6px 12px;
        transition: all 0.3s;
        min-width: 180px;
    }

    .money-field-wrapper:focus-within {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }

    html[data-theme="dark"] .money-field-wrapper {
        background: #0F172A;
        border-color: #334155;
    }

    .money-field-wrapper .currency-prefix {
        font-family: var(--font-primary);
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--page-text-secondary, #64748B);
        flex-shrink: 0;
    }

    .money-field-wrapper input {
        border: none;
        background: transparent;
        outline: none;
        width: 100%;
        font-family: var(--font-mono);
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--page-text-primary, #1E293B);
        text-align: right;
        letter-spacing: -0.02em;
    }

    html[data-theme="dark"] .money-field-wrapper input { color: #F1F5F9; }

    /* ACTION BUTTONS */
    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 2px solid var(--page-border, #E2E8F0);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-primary {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
        color: white;
    }

    .btn-success {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        color: white;
    }

    .btn-secondary {
        background: var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
    }

    .btn-secondary:hover {
        background: #CBD5E1;
        transform: translateY(-2px);
    }

    .btn-add-item {
        background: #D1FAE5;
        color: #065F46;
        padding: 8px 16px;
        border-radius: 8px;
        border: 2px dashed #10B981;
        font-weight: 700;
        font-size: 0.78rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
    }

    .btn-add-item:hover {
        background: #10B981;
        color: white;
        border-color: #059669;
    }

    .btn-remove-item {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #FEE2E2;
        color: #DC2626;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }

    .btn-remove-item:hover {
        background: #DC2626;
        color: white;
        transform: scale(1.05);
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .page-header-custom { padding: 18px; }
        .page-header-custom .page-title { font-size: 1.15rem; }
        .card-body-custom { padding: 14px; }
        .action-buttons { flex-direction: column; }
        .action-buttons .btn { width: 100%; justify-content: center; }
    }
</style>

<main class="main-content">

    <div class="page-header-custom">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit OTC Sale
            </h1>
            <p class="page-subtitle">
                <span>Update sale information and items</span>
                <span class="header-badge"><i class="fas fa-receipt"></i> <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?></span>
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($sale['branch_name'] ?? $user_branch_name) ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="otc_sales.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="view_otc_sale.php?id=<?= $sale_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert-custom <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- INFO BOX -->
    <div class="info-box">
        <div class="info-item">
            <div class="info-icon"><i class="fas fa-receipt"></i></div>
            <div class="info-content">
                <div class="info-label">Sale Number</div>
                <div class="info-value"><?= htmlspecialchars($sale['sale_number']) ?></div>
            </div>
        </div>
        <div class="info-item">
            <div class="info-icon"><i class="fas fa-user"></i></div>
            <div class="info-content">
                <div class="info-label">Sold By</div>
                <div class="info-value"><?= htmlspecialchars($sale['sold_by_name'] ?? 'N/A') ?></div>
            </div>
        </div>
        <div class="info-item">
            <div class="info-icon"><i class="fas fa-calendar"></i></div>
            <div class="info-content">
                <div class="info-label">Date</div>
                <div class="info-value"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></div>
            </div>
        </div>
        <div class="info-item">
            <div class="info-icon" style="background:linear-gradient(135deg,#059669,#047857);">
                <i class="fas fa-money-bill"></i>
            </div>
            <div class="info-content">
                <div class="info-label">Paid</div>
                <div class="info-value">TSh <?= number_format($sale['paid_amount'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <form method="POST" id="editForm">
        <input type="hidden" name="action" value="update_otc">
        
        <!-- CUSTOMER INFO -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3 class="card-title-custom">
                    <i class="fas fa-user-circle"></i> Customer Information
                </h3>
            </div>
            <div class="card-body-custom">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Customer Name</label>
                        <input type="text" name="customer_name" class="form-control" 
                               value="<?= htmlspecialchars($sale['customer_name'] ?? '') ?>"
                               placeholder="Walk-in Customer">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="text" name="customer_phone" class="form-control mono" 
                               value="<?= htmlspecialchars($sale['customer_phone'] ?? '') ?>"
                               placeholder="07XXXXXXXX"
                               style="text-align:left;">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-credit-card"></i> Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="cash" <?= ($sale['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>💵 Cash</option>
                            <option value="mpesa" <?= ($sale['payment_method'] ?? '') === 'mpesa' ? 'selected' : '' ?>>📱 M-Pesa</option>
                            <option value="tigo_pesa" <?= ($sale['payment_method'] ?? '') === 'tigo_pesa' ? 'selected' : '' ?>>📱 Tigo Pesa</option>
                            <option value="airtel_money" <?= ($sale['payment_method'] ?? '') === 'airtel_money' ? 'selected' : '' ?>>📱 Airtel Money</option>
                            <option value="halopesa" <?= ($sale['payment_method'] ?? '') === 'halopesa' ? 'selected' : '' ?>>📱 Halopesa</option>
                            <option value="bank" <?= ($sale['payment_method'] ?? '') === 'bank' ? 'selected' : '' ?>>🏦 Bank</option>
                            <option value="card" <?= ($sale['payment_method'] ?? '') === 'card' ? 'selected' : '' ?>>💳 Card</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-info-circle"></i> Payment Status</label>
                        <select name="payment_status" class="form-control">
                            <option value="unpaid" <?= ($sale['payment_status'] ?? '') === 'unpaid' ? 'selected' : '' ?>>❌ Unpaid</option>
                            <option value="partial" <?= ($sale['payment_status'] ?? '') === 'partial' ? 'selected' : '' ?>>⏳ Partial</option>
                            <option value="paid" <?= ($sale['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>>✅ Paid</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ITEMS -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3 class="card-title-custom">
                    <i class="fas fa-pills"></i> Sale Items
                    <span style="font-size:0.7rem;font-weight:400;color:var(--page-text-secondary);">
                        (Scroll → for more fields)
                    </span>
                </h3>
                <button type="button" class="btn-add-item" onclick="addNewItem()">
                    <i class="fas fa-plus-circle"></i> Add Item
                </button>
            </div>
            <div class="card-body-custom">
                <div style="overflow-x:auto;">
                    <table class="items-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th style="min-width:180px;">Item Name</th>
                                <th style="width:80px;">Qty</th>
                                <th style="width:100px;">Dosage</th>
                                <th style="width:130px;">Frequency</th>
                                <th style="width:110px;">Route</th>
                                <th style="min-width:160px;">Instructions</th>
                                <th style="width:130px;">Unit Price</th>
                                <th style="width:130px;">Total</th>
                                <th style="width:50px;text-align:center;">×</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <?php if (count($sale_items) > 0): ?>
                                <?php foreach ($sale_items as $idx => $item): ?>
                                    <tr class="item-row">
                                        <td class="row-num"><?= $idx + 1 ?></td>
                                        <td>
                                            <input type="text" 
                                                   name="items[<?= $idx ?>][item_name]" 
                                                   class="item-name-input input-sm"
                                                   value="<?= htmlspecialchars($item['item_name']) ?>"
                                                   placeholder="Item name"
                                                   required>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="items[<?= $idx ?>][quantity]" 
                                                   class="item-qty-input mono input-sm"
                                                   value="<?= (int)$item['quantity'] ?>"
                                                   min="1"
                                                   oninput="recalculate()"
                                                   required>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="items[<?= $idx ?>][dosage]" 
                                                   class="input-sm"
                                                   value="<?= htmlspecialchars($item['dosage'] ?? '') ?>"
                                                   placeholder="e.g. 500mg"
                                                   list="dosage-list">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="items[<?= $idx ?>][frequency]" 
                                                   class="input-sm"
                                                   value="<?= htmlspecialchars($item['frequency'] ?? '') ?>"
                                                   placeholder="e.g. Twice Daily"
                                                   list="frequency-list">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="items[<?= $idx ?>][route]" 
                                                   class="input-sm"
                                                   value="<?= htmlspecialchars($item['route'] ?? '') ?>"
                                                   placeholder="e.g. Oral"
                                                   list="route-list">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="items[<?= $idx ?>][instructions]" 
                                                   class="input-sm"
                                                   value="<?= htmlspecialchars($item['instructions'] ?? '') ?>"
                                                   placeholder="e.g. Take with food">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="items[<?= $idx ?>][unit_price]" 
                                                   class="item-price-input mono input-sm money-input"
                                                   value="<?= number_format((float)$item['unit_price'], 0) ?>"
                                                   oninput="formatMoneyInput(this); recalculate();"
                                                   required>
                                        </td>
                                        <td style="font-family:var(--font-mono);font-weight:700;color:#0B5ED7;white-space:nowrap;font-size:0.78rem;" class="item-total">
                                            <?= number_format((float)$item['total_price']) ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <button type="button" class="btn-remove-item" onclick="removeItem(this)" style="width:28px;height:28px;">
                                                <i class="fas fa-trash" style="font-size:0.7rem;"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="item-row">
                                    <td class="row-num">1</td>
                                    <td><input type="text" name="items[0][item_name]" class="item-name-input input-sm" placeholder="Item name" required></td>
                                    <td><input type="number" name="items[0][quantity]" class="item-qty-input mono input-sm" value="1" min="1" oninput="recalculate()" required></td>
                                    <td><input type="text" name="items[0][dosage]" class="input-sm" placeholder="e.g. 500mg" list="dosage-list"></td>
                                    <td><input type="text" name="items[0][frequency]" class="input-sm" placeholder="e.g. Twice Daily" list="frequency-list"></td>
                                    <td><input type="text" name="items[0][route]" class="input-sm" placeholder="e.g. Oral" list="route-list"></td>
                                    <td><input type="text" name="items[0][instructions]" class="input-sm" placeholder="e.g. Take with food"></td>
                                    <td><input type="text" name="items[0][unit_price]" class="item-price-input mono input-sm money-input" value="0" oninput="formatMoneyInput(this); recalculate();" required></td>
                                    <td style="font-family:var(--font-mono);font-weight:700;color:#0B5ED7;white-space:nowrap;font-size:0.78rem;" class="item-total">0</td>
                                    <td style="text-align:center;">
                                        <button type="button" class="btn-remove-item" onclick="removeItem(this)" style="width:28px;height:28px;">
                                            <i class="fas fa-trash" style="font-size:0.7rem;"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- DATALISTS KWA AUTOCOMPLETE -->
                <datalist id="dosage-list">
                    <?php foreach ($dosage_options as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>">
                    <?php endforeach; ?>
                </datalist>
                <datalist id="frequency-list">
                    <?php foreach ($frequency_options as $f): ?>
                        <option value="<?= htmlspecialchars($f) ?>">
                    <?php endforeach; ?>
                </datalist>
                <datalist id="route-list">
                    <?php foreach ($route_options as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>">
                    <?php endforeach; ?>
                </datalist>
                
                <!-- TOTALS -->
                <div class="totals-box">
                    <div class="total-row">
                        <span class="label"><i class="fas fa-calculator"></i> Subtotal</span>
                        <span class="value" id="subtotalDisplay">TSh 0</span>
                    </div>
                    
                    <div class="total-row">
                        <span class="label"><i class="fas fa-tag"></i> Discount</span>
                        <div class="money-field-wrapper">
                            <span class="currency-prefix">TSh</span>
                            <input type="text" 
                                   name="discount_amount" 
                                   id="discountInput"
                                   class="money-input"
                                   value="<?= number_format((float)($sale['discount_amount'] ?? 0), 0) ?>"
                                   oninput="formatMoneyInput(this); recalculate();"
                                   autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="total-row">
                        <span class="label"><i class="fas fa-star"></i> Premium</span>
                        <div class="money-field-wrapper">
                            <span class="currency-prefix">TSh</span>
                            <input type="text" 
                                   name="premium_amount" 
                                   id="premiumInput"
                                   class="money-input"
                                   value="<?= number_format((float)($sale['premium_amount'] ?? 0), 0) ?>"
                                   oninput="formatMoneyInput(this); recalculate();"
                                   autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="total-row grand-total">
                        <span class="label"><i class="fas fa-check-circle"></i> Grand Total</span>
                        <span class="value" id="grandTotalDisplay">TSh 0</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- NOTES -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3 class="card-title-custom">
                    <i class="fas fa-sticky-note"></i> Notes
                </h3>
            </div>
            <div class="card-body-custom">
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Additional Notes</label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="Any additional notes..."><?= htmlspecialchars($sale['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="action-buttons">
            <a href="otc_sales.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </form>

</main>

<script>
    // ================================================================
    // ✅ FORMAT MONEY INPUT - 1,000,000,000
    // ================================================================
    function formatMoneyInput(input) {
        var cursorPos = input.selectionStart;
        var originalLength = input.value.length;
        
        // Remove all non-digit characters
        var value = input.value.replace(/[^0-9]/g, '');
        
        // Prevent empty
        if (value === '') {
            input.value = '';
            return;
        }
        
        // Format with commas
        var formatted = Number(value).toLocaleString('en-US');
        
        // Set value
        input.value = formatted;
        
        // Adjust cursor position
        var newLength = formatted.length;
        var newCursorPos = cursorPos + (newLength - originalLength);
        input.setSelectionRange(newCursorPos, newCursorPos);
    }
    
    // ================================================================
    // ✅ PARSE MONEY VALUE
    // ================================================================
    function parseMoney(value) {
        if (!value) return 0;
        return parseFloat(String(value).replace(/,/g, '')) || 0;
    }
    
    // ================================================================
    // ✅ FORMAT NUMBER WITH COMMAS
    // ================================================================
    function numberFormat(num) {
        return Number(num).toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }
    
    // ================================================================
    // RECALCULATE TOTALS
    // ================================================================
    function recalculate() {
        var rows = document.querySelectorAll('.item-row');
        var subtotal = 0;
        
        rows.forEach(function(row) {
            var qtyInput = row.querySelector('.item-qty-input');
            var priceInput = row.querySelector('.item-price-input');
            var totalCell = row.querySelector('.item-total');
            
            if (!qtyInput || !priceInput) return;
            
            var qty = parseFloat(qtyInput.value) || 0;
            var price = parseMoney(priceInput.value);
            var total = qty * price;
            
            subtotal += total;
            
            if (totalCell) {
                totalCell.textContent = numberFormat(total);
            }
        });
        
        var discountInput = document.getElementById('discountInput');
        var premiumInput = document.getElementById('premiumInput');
        
        var discount = parseMoney(discountInput.value);
        var premium = parseMoney(premiumInput.value);
        
        var grandTotal = subtotal + premium - discount;
        if (grandTotal < 0) grandTotal = 0;
        
        document.getElementById('subtotalDisplay').textContent = 'TSh ' + numberFormat(subtotal);
        document.getElementById('grandTotalDisplay').textContent = 'TSh ' + numberFormat(grandTotal);
    }
    
    // ================================================================
    // ADD NEW ITEM
    // ================================================================
    function addNewItem() {
        var tbody = document.getElementById('itemsBody');
        var rowCount = tbody.querySelectorAll('.item-row').length;
        
        var newRow = document.createElement('tr');
        newRow.className = 'item-row';
        newRow.innerHTML = `
            <td class="row-num">${rowCount + 1}</td>
            <td>
                <input type="text" 
                       name="items[${rowCount}][item_name]" 
                       class="item-name-input input-sm"
                       placeholder="Item name"
                       required>
            </td>
            <td>
                <input type="number" 
                       name="items[${rowCount}][quantity]" 
                       class="item-qty-input mono input-sm"
                       value="1"
                       min="1"
                       oninput="recalculate()"
                       required>
            </td>
            <td>
                <input type="text" 
                       name="items[${rowCount}][dosage]" 
                       class="input-sm"
                       placeholder="e.g. 500mg"
                       list="dosage-list">
            </td>
            <td>
                <input type="text" 
                       name="items[${rowCount}][frequency]" 
                       class="input-sm"
                       placeholder="e.g. Twice Daily"
                       list="frequency-list">
            </td>
            <td>
                <input type="text" 
                       name="items[${rowCount}][route]" 
                       class="input-sm"
                       placeholder="e.g. Oral"
                       list="route-list">
            </td>
            <td>
                <input type="text" 
                       name="items[${rowCount}][instructions]" 
                       class="input-sm"
                       placeholder="e.g. Take with food">
            </td>
            <td>
                <input type="text" 
                       name="items[${rowCount}][unit_price]" 
                       class="item-price-input mono input-sm money-input"
                       value="0"
                       oninput="formatMoneyInput(this); recalculate();"
                       required>
            </td>
            <td style="font-family:var(--font-mono);font-weight:700;color:#0B5ED7;white-space:nowrap;font-size:0.78rem;" class="item-total">0</td>
            <td style="text-align:center;">
                <button type="button" class="btn-remove-item" onclick="removeItem(this)" style="width:28px;height:28px;">
                    <i class="fas fa-trash" style="font-size:0.7rem;"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(newRow);
        renumberRows();
        recalculate();
    }
    
    // ================================================================
    // REMOVE ITEM
    // ================================================================
    function removeItem(btn) {
        var tbody = document.getElementById('itemsBody');
        var rowCount = tbody.querySelectorAll('.item-row').length;
        
        if (rowCount <= 1) {
            alert('At least one item is required!');
            return;
        }
        
        if (!confirm('Remove this item?')) return;
        
        var row = btn.closest('.item-row');
        row.remove();
        
        renumberRows();
        recalculate();
    }
    
    // ================================================================
    // RENUMBER ROWS
    // ================================================================
    function renumberRows() {
        var rows = document.querySelectorAll('.item-row');
        rows.forEach(function(row, idx) {
            row.querySelector('.row-num').textContent = idx + 1;
            
            var nameInput = row.querySelector('.item-name-input');
            var qtyInput = row.querySelector('.item-qty-input');
            var priceInput = row.querySelector('.item-price-input');
            var dosageInput = row.querySelector('input[name*="[dosage]"]');
            var frequencyInput = row.querySelector('input[name*="[frequency]"]');
            var routeInput = row.querySelector('input[name*="[route]"]');
            var instructionsInput = row.querySelector('input[name*="[instructions]"]');
            
            if (nameInput) nameInput.name = 'items[' + idx + '][item_name]';
            if (qtyInput) qtyInput.name = 'items[' + idx + '][quantity]';
            if (priceInput) priceInput.name = 'items[' + idx + '][unit_price]';
            if (dosageInput) dosageInput.name = 'items[' + idx + '][dosage]';
            if (frequencyInput) frequencyInput.name = 'items[' + idx + '][frequency]';
            if (routeInput) routeInput.name = 'items[' + idx + '][route]';
            if (instructionsInput) instructionsInput.name = 'items[' + idx + '][instructions]';
        });
    }
    
    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        recalculate();
    });
    
    // ================================================================
    // FORM SUBMIT VALIDATION
    // ================================================================
    document.getElementById('editForm').addEventListener('submit', function(e) {
        var rows = document.querySelectorAll('.item-row');
        var valid = false;
        
        rows.forEach(function(row) {
            var name = row.querySelector('.item-name-input').value.trim();
            var qty = parseFloat(row.querySelector('.item-qty-input').value) || 0;
            var price = parseMoney(row.querySelector('.item-price-input').value);
            
            if (name && qty > 0 && price >= 0) {
                valid = true;
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('At least one valid item is required!');
            return false;
        }
    });
    
    // ================================================================
    // DARK MODE
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');
        
        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F1F5F9';
            if (mainContent) mainContent.style.background = '#F1F5F9';
        }
    }
    
    enforceDarkModeBackground();
    document.addEventListener('darkModeChanged', function() {
        setTimeout(enforceDarkModeBackground, 50);
    });
    
    console.log('%c🏥 Braick - Edit OTC Sale V2', 'font-size:18px; font-weight:bold; color:#D97706;');
    console.log('%c✅ Money format: 1,000,000,000', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ Dosage, Frequency, Route, Instructions', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Sale ID: <?= $sale_id ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Sale #: <?= htmlspecialchars($sale['sale_number']) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>