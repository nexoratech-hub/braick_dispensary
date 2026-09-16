<?php
// ================================================================
// FILE: frontend/pages/admin/cancel_bill.php
// ADMIN - CANCEL BILL
// ✅ Uses SHARED header & sidebar
// ✅ Beautiful danger page header card
// ✅ Full dark mode support
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
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
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

// ================================================================
// PARAMETERS
// ================================================================
$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($bill_id <= 0) {
    header('Location: bills.php?branch=' . $selected_branch_id);
    exit;
}

$message = '';
$message_type = '';
$cancellation_reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
$confirm_cancel = isset($_POST['confirm_cancel']) ? (bool)$_POST['confirm_cancel'] : false;

// ================================================================
// FETCH BILL DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        b.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        p.phone as patient_phone,
        v.visit_number,
        v.visit_type,
        v.status as visit_status,
        u.full_name as created_by_name,
        br.name as branch_name
    FROM bills b
    LEFT JOIN patients p ON b.patient_id = p.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN branches br ON b.branch_id = br.id
    WHERE b.id = ?
");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bill) {
    header('Location: bills.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// FETCH BILL ITEMS
// ================================================================
$stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? AND status != 'cancelled' ORDER BY created_at DESC");
$stmt->execute([$bill_id]);
$bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// FETCH PAYMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, u.full_name as received_by_name
    FROM payments p
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.bill_id = ?
    ORDER BY p.received_at DESC
");
$stmt->execute([$bill_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_paid = 0;
foreach ($payments as $payment) {
    $total_paid += $payment['amount'];
}

// ================================================================
// FETCH PRESCRIPTIONS
// ================================================================
$prescriptions = [];
if (!empty($bill['visit_id'])) {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name as doctor_name
        FROM prescriptions pr
        LEFT JOIN users u ON pr.doctor_id = u.id
        WHERE pr.visit_id = ? AND pr.status != 'cancelled'
    ");
    $stmt->execute([$bill['visit_id']]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// HANDLE CANCELLATION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirm_cancel) {
    try {
        $db->beginTransaction();

        if ($bill['status'] === 'cancelled') {
            throw new Exception('Bill is already cancelled.');
        }

        $branch_id = $bill['branch_id'] ?? $user_branch_id;
        $cancellation_note = "Cancelled by {$user_full_name}. Reason: {$cancellation_reason}";

        // 1. Log payment reversals
        if (!empty($payments)) {
            foreach ($payments as $payment) {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at)
                    VALUES (?, ?, ?, 'payment_reversed', ?, NOW())
                ");
                $stmt->execute([
                    $user_id, $branch_id, $bill['patient_id'],
                    "Payment #{$payment['receipt_number']} of TSh " . number_format($payment['amount'], 0) . " reversed due to bill cancellation. Reason: {$cancellation_reason}"
                ]);
            }
        }

        // 2. Reverse stock movements
        $stmt = $db->prepare("
            SELECT sm.*, mi.id as inventory_id
            FROM stock_movements sm
            LEFT JOIN medications_inventory mi ON sm.inventory_id = mi.id
            WHERE sm.reference_id = ? AND sm.reference_type = 'prescription' AND sm.movement_type = 'out'
        ");
        $stmt->execute([$bill_id]);
        $stock_movements_to_reverse = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stock_movements_to_reverse as $movement) {
            if ($movement['inventory_id']) {
                $stmt = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ?");
                $stmt->execute([$movement['inventory_id']]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                $current_qty = $current['quantity'] ?? 0;

                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$movement['quantity'], $movement['inventory_id']]);

                $stmt = $db->prepare("
                    INSERT INTO stock_movements (
                        inventory_id, patient_id, movement_type, quantity, 
                        previous_stock, new_stock, reference_type, reference_id,
                        performed_by, branch_id, notes, created_at
                    ) VALUES (?, ?, 'in', ?, ?, ?, 'adjustment', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $movement['inventory_id'], $bill['patient_id'], $movement['quantity'],
                    $current_qty, $current_qty + $movement['quantity'],
                    $bill_id, $user_id, $branch_id,
                    "Stock reversal due to bill cancellation"
                ]);
            }
        }

        // 3. Cancel prescriptions
        if (!empty($bill['visit_id'])) {
            $stmt = $db->prepare("
                UPDATE prescriptions 
                SET status = 'cancelled',
                    notes = CONCAT(IFNULL(notes, ''), '\n', ?)
                WHERE visit_id = ? AND status != 'cancelled'
            ");
            $stmt->execute([$cancellation_note, $bill['visit_id']]);
        }

        // 4. Cancel bill items
        $stmt = $db->prepare("UPDATE bill_items SET status = 'cancelled' WHERE bill_id = ? AND status != 'cancelled'");
        $stmt->execute([$bill_id]);

        // 5. Update bill
        $stmt = $db->prepare("
            UPDATE bills 
            SET status = 'cancelled',
                notes = CONCAT(IFNULL(notes, ''), '\n', ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$cancellation_note, $bill_id]);

        // 6. Update visit
        if ($bill['visit_id']) {
            $stmt = $db->prepare("
                UPDATE visits 
                SET payment_status = 'cancelled',
                    notes = CONCAT(IFNULL(notes, ''), '\n', ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$cancellation_note, $bill['visit_id']]);
        }

        // 7. Log activity
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, branch_id, patient_id, action, details, created_at)
            VALUES (?, ?, ?, 'bill_cancelled', ?, NOW())
        ");
        $stmt->execute([
            $user_id, $branch_id, $bill['patient_id'],
            "Bill #{$bill['bill_number']} (TSh " . number_format($bill['total_amount'], 0) . ") cancelled. Reason: {$cancellation_reason}"
        ]);

        $db->commit();

        $message = "✅ Bill #{$bill['bill_number']} has been cancelled successfully!";
        $message .= "<br>📋 Reason: " . htmlspecialchars($cancellation_reason);
        if (!empty($payments)) {
            $message .= "<br>💰 Payments reversed: TSh " . number_format($total_paid, 0);
        }
        $message_type = 'success';

        echo '<script>
            setTimeout(function(){ 
                window.location.href = "bills.php?branch=' . $selected_branch_id . '&success=1"; 
            }, 3000);
        </script>';

    } catch (Exception $e) {
        $db->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) { $unread_notifications = 0; }

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS
     ================================================================ -->
<style>
    :root {
        --page-primary: #2563EB;
        --page-primary-bg: #EFF6FF;
        --page-primary-light: #60A5FA;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-purple: #7C3AED;
        --page-purple-bg: #EDE9FE;
        --page-bg-body: #F0F4F8;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-primary-bg: #1E3A5F;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
        --page-purple-bg: #2D1B5F;
        --page-primary: #3B82F6;
    }

    body { background: var(--page-bg-body); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* ================================================================
       DANGER PAGE HEADER CARD (RED/AMBER)
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #DC2626 0%, #B91C1C 50%, #7F1D1D 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(220, 38, 38, 0.3), 0 4px 12px rgba(220, 38, 38, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .page-header-card.already-cancelled {
        background: linear-gradient(135deg, #059669 0%, #047857 50%, #065F46 100%);
        box-shadow: 0 8px 32px rgba(5, 150, 105, 0.3), 0 4px 12px rgba(5, 150, 105, 0.2);
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        color: white;
        position: relative;
        z-index: 2;
    }

    .page-header-title i {
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

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.95);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .page-header-subtitle strong { color: white; font-weight: 700; }

    .role-badge-display {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .page-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .btn-outline-light {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       SUMMARY CARDS
       ================================================================ */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .summary-card {
        background: var(--page-bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1.5px solid var(--page-border);
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        position: relative;
        overflow: hidden;
    }

    .summary-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
    }

    .summary-card.blue::before { background: linear-gradient(90deg, #2563EB, #60A5FA); }
    .summary-card.green::before { background: linear-gradient(90deg, #059669, #34D399); }
    .summary-card.red::before { background: linear-gradient(90deg, #DC2626, #F87171); }
    .summary-card.warning::before { background: linear-gradient(90deg, #D97706, #FBBF24); }
    .summary-card.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }

    .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--page-shadow-md);
        border-color: var(--page-primary);
    }

    .summary-label {
        font-size: 0.65rem;
        color: var(--page-text-secondary);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0 0 4px 0;
    }

    .summary-value {
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--page-text-primary);
        margin: 0;
        line-height: 1.2;
    }

    .summary-value.blue { color: #2563EB; }
    .summary-value.green { color: #059669; }
    .summary-value.red { color: #DC2626; }
    .summary-value.warning { color: #D97706; }
    .summary-value.purple { color: #7C3AED; }

    .summary-sub {
        font-size: 0.7rem;
        color: var(--page-text-secondary);
        margin: 4px 0 0 0;
    }

    /* ================================================================
       CARD
       ================================================================ */
    .card-modern {
        background: var(--page-bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 1px solid var(--page-border);
        box-shadow: var(--page-shadow-md);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }

    .card-modern.danger { border: 2px solid var(--page-danger); }
    .card-modern.success { border: 2px solid var(--page-success); background: var(--page-success-bg); }

    .card-modern-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 14px;
        border-bottom: 2px solid var(--page-border);
    }

    .card-modern-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--page-text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .card-modern-title i { color: var(--page-primary); }

    .card-modern-badge {
        background: var(--page-primary-bg);
        color: var(--page-primary);
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    /* ================================================================
       DETAIL GRID
       ================================================================ */
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .detail-label {
        font-size: 0.65rem;
        color: var(--page-text-secondary);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .detail-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--page-text-primary);
    }

    /* ================================================================
       TABLES
       ================================================================ */
    .table-modern {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }

    .table-modern thead th {
        padding: 12px 14px;
        text-align: left;
        font-weight: 700;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #ffffff;
        background: linear-gradient(135deg, #2563EB, #1D4ED8);
        white-space: nowrap;
    }

    .table-modern tbody td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--page-border);
        color: var(--page-text-primary);
        vertical-align: middle;
    }

    .table-modern tbody tr:hover { background: var(--page-primary-bg); }
    .table-modern tbody tr:last-child td { border-bottom: none; }

    .table-modern tfoot td {
        padding: 12px 14px;
        border-top: 2px solid var(--page-border);
        font-weight: 700;
    }

    /* ================================================================
       STATUS BADGES
       ================================================================ */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.paid { background: #D1FAE5; color: #059669; }
    .status-badge.partial { background: #FEF3C7; color: #D97706; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }

    html[data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    html[data-theme="dark"] .status-badge.paid { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }

    /* ================================================================
       FORM ELEMENTS
       ================================================================ */
    .form-label {
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--page-text-primary);
        margin-bottom: 6px;
        display: block;
    }

    .form-label .required { color: var(--page-danger); margin-left: 2px; }
    .form-label .label-icon { margin-right: 4px; color: var(--page-primary); }

    .form-control-modern {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--page-border);
        border-radius: 12px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-bg-card);
        color: var(--page-text-primary);
        font-family: inherit;
    }

    html[data-theme="dark"] .form-control-modern {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    html[data-theme="dark"] .form-control-modern::placeholder { color: #64748B; }

    .form-control-modern:focus {
        border-color: var(--page-primary);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    textarea.form-control-modern { resize: vertical; min-height: 90px; }

    /* ================================================================
       ALERT
       ================================================================ */
    .alert-modern {
        padding: 16px 20px;
        border-radius: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-modern-success { background: #D1FAE5; color: #047857; border: 2px solid #059669; }
    .alert-modern-error { background: #FEE2E2; color: #B91C1C; border: 2px solid #DC2626; }
    .alert-modern-warning { background: #FEF3C7; color: #92400E; border: 2px solid #D97706; }

    html[data-theme="dark"] .alert-modern-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    html[data-theme="dark"] .alert-modern-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
    html[data-theme="dark"] .alert-modern-warning { background: #3D2E0A; color: #FBBF24; border-color: #D97706; }

    .alert-modern i { font-size: 1.2rem; margin-top: 2px; flex-shrink: 0; }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-modern {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 28px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        font-family: inherit;
        min-height: 46px;
    }

    .btn-modern-primary {
        background: linear-gradient(135deg, #2563EB, #1D4ED8);
        color: white;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
    }

    .btn-modern-primary:hover {
        background: linear-gradient(135deg, #1D4ED8, #1E40AF);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
    }

    .btn-modern-danger {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3);
    }

    .btn-modern-danger:hover {
        background: linear-gradient(135deg, #B91C1C, #991B1B);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
    }

    .btn-modern-danger:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-modern-outline {
        background: transparent;
        color: var(--page-text-secondary);
        border: 2px solid var(--page-border);
    }

    html[data-theme="dark"] .btn-modern-outline {
        color: #94A3B8;
        border-color: #334155;
    }

    .btn-modern-outline:hover {
        background: var(--page-bg-body);
        border-color: var(--page-primary);
        color: var(--page-primary);
        transform: translateY(-2px);
    }

    /* ================================================================
       WARNING BOX
       ================================================================ */
    .warning-box {
        background: var(--page-warning-bg);
        border: 2px solid var(--page-warning);
        border-radius: 14px;
        padding: 16px 20px;
        margin: 16px 0;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .warning-box i {
        color: var(--page-warning);
        font-size: 1.3rem;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .warning-box strong { color: #92400E; }

    html[data-theme="dark"] .warning-box strong { color: #FBBF24; }

    .warning-box ul {
        margin: 8px 0 0 0;
        padding-left: 20px;
        list-style-type: disc;
        color: #92400E;
        font-size: 0.85rem;
    }

    html[data-theme="dark"] .warning-box ul { color: #FCD34D; }

    .warning-box li { margin-bottom: 4px; }

    /* ================================================================
       SPINNER
       ================================================================ */
    .spinner {
        display: inline-block;
        width: 16px; height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.4s ease forwards;
        opacity: 0;
    }

    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

    /* ================================================================
       TOAST
       ================================================================ */
    .toast-modern {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 16px 22px;
        border-radius: 14px;
        z-index: 9999;
        max-width: 420px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: flex-start;
        gap: 12px;
        color: white;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }

    .toast-modern.show { transform: translateY(0); opacity: 1; }
    .toast-modern.success { background: linear-gradient(135deg, #059669, #047857); }
    .toast-modern.error { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .toast-modern.info { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
    .toast-modern.warning { background: linear-gradient(135deg, #D97706, #B45309); }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-card { padding: 18px 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .summary-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .card-modern { padding: 18px 16px; }
        .grid-2 { grid-template-columns: 1fr; }
        .table-modern { font-size: 0.7rem; }
        .table-modern thead th, .table-modern tbody td { padding: 8px 10px; }
    }

    @media (max-width: 480px) {
        .summary-grid { grid-template-columns: 1fr; }
        .table-modern { font-size: 0.65rem; }
        .table-modern thead th, .table-modern tbody td { padding: 6px 8px; }
    }

    /* Print */
    @media print {
        .page-header-actions, .btn-modern, .btn-outline-light, .toast-modern { display: none !important; }
        .page-header-card { background: #DC2626 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .card-modern { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Danger Page Header Card -->
    <div class="page-header-card <?= $bill['status'] === 'cancelled' ? 'already-cancelled' : '' ?> animate-fade-in-up">
        <div>
            <h1 class="page-header-title">
                <i class="fas <?= $bill['status'] === 'cancelled' ? 'fa-check-circle' : 'fa-ban' ?>"></i>
                <?= $bill['status'] === 'cancelled' ? 'Bill Cancelled' : 'Cancel Bill' ?>
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-file-invoice"></i>
                Bill #<strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                <span class="page-header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="page-header-badge">
                    <i class="fas fa-tag"></i>
                    <span class="status-badge <?= $bill['status'] ?? 'pending' ?>">
                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                    </span>
                </span>
                <span class="page-header-badge">
                    <i class="fas fa-money-bill-wave"></i>
                    TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                </span>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="bill_details.php?id=<?= $bill_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View Bill
            </a>
            <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-modern alert-modern-<?= $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'error') ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <?php if ($bill['status'] !== 'cancelled'): ?>
    <!-- Warning Box -->
    <div class="warning-box animate-fade-in-up" style="animation-delay:0.05s;">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>⚠️ Warning: Cancelling this bill will:</strong>
            <ul>
                <li>Reverse all payments made against this bill</li>
                <li>Restore medication stock quantities</li>
                <li>Cancel associated prescriptions</li>
                <li>Mark all bill items as cancelled</li>
                <li>Update the visit payment status</li>
            </ul>
            <?php if (!empty($payments)): ?>
                <div style="margin-top:10px;padding:10px 14px;background:var(--page-danger-bg);border-radius:10px;color:var(--page-danger);font-weight:700;">
                    <i class="fas fa-coins"></i>
                    <?= count($payments) ?> payment(s) totaling TSh <?= number_format($total_paid, 0) ?> will be reversed.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================
         SUMMARY CARDS
         ================================================================ -->
    <div class="summary-grid animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="summary-card blue">
            <p class="summary-label">Total Bill Amount</p>
            <p class="summary-value blue">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></p>
        </div>
        <div class="summary-card green">
            <p class="summary-label">Paid Amount</p>
            <p class="summary-value green">TSh <?= number_format($total_paid, 0) ?></p>
            <p class="summary-sub"><?= count($payments) ?> payment(s)</p>
        </div>
        <div class="summary-card red">
            <p class="summary-label">Balance</p>
            <p class="summary-value red">TSh <?= number_format(($bill['total_amount'] ?? 0) - $total_paid, 0) ?></p>
        </div>
        <div class="summary-card warning">
            <p class="summary-label">Bill Items</p>
            <p class="summary-value warning"><?= count($bill_items) ?></p>
            <p class="summary-sub">item(s) in this bill</p>
        </div>
        <?php if (!empty($prescriptions)): ?>
        <div class="summary-card purple">
            <p class="summary-label">Prescriptions</p>
            <p class="summary-value purple"><?= count($prescriptions) ?></p>
            <p class="summary-sub">will be cancelled</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================
         BILL DETAILS
         ================================================================ -->
    <div class="card-modern animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="card-modern-header">
            <h3 class="card-modern-title">
                <i class="fas fa-file-invoice"></i> Bill Information
            </h3>
        </div>

        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Bill Number</span>
                <span class="detail-value" style="font-family:'Courier New',monospace;color:var(--page-primary);">
                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Patient</span>
                <span class="detail-value"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Patient ID</span>
                <span class="detail-value"><?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Branch</span>
                <span class="detail-value"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Created By</span>
                <span class="detail-value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Created At</span>
                <span class="detail-value"><?= date('F d, Y h:i A', strtotime($bill['created_at'] ?? 'now')) ?></span>
            </div>
            <?php if (!empty($bill['visit_number'])): ?>
            <div class="detail-item">
                <span class="detail-label">Visit Number</span>
                <span class="detail-value"><?= htmlspecialchars($bill['visit_number']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================
         BILL ITEMS
         ================================================================ -->
    <div class="card-modern animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-modern-header">
            <h3 class="card-modern-title">
                <i class="fas fa-list"></i> Bill Items
                <span class="card-modern-badge"><?= count($bill_items) ?></span>
            </h3>
        </div>

        <?php if (count($bill_items) > 0): ?>
            <div style="overflow-x:auto;">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Item Type</th>
                            <th>Item Name</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Unit Price</th>
                            <th style="text-align:right;">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bill_items as $item): ?>
                            <tr>
                                <td>
                                    <span class="status-badge" style="background:var(--page-primary-bg);color:var(--page-primary);">
                                        <?= ucfirst($item['item_type'] ?? 'Other') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                <td style="text-align:center;"><?= $item['quantity'] ?? 1 ?></td>
                                <td style="text-align:right;">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td style="text-align:right;"><strong>TSh <?= number_format($item['total_price'] ?? 0, 0) ?></strong></td>
                                <td>
                                    <span class="status-badge <?= ($item['status'] ?? 'pending') === 'cancelled' ? 'cancelled' : 'pending' ?>">
                                        <?= ucfirst($item['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align:right;padding:12px;">Total:</td>
                            <td colspan="2" style="padding:12px;color:var(--page-primary);font-size:1.05rem;">
                                TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:40px 20px;color:var(--page-text-secondary);">
                <i class="fas fa-box-open" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.5;"></i>
                <p style="font-size:0.9rem;">No items found for this bill</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================
         PAYMENTS
         ================================================================ -->
    <?php if (count($payments) > 0): ?>
    <div class="card-modern animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-modern-header">
            <h3 class="card-modern-title">
                <i class="fas fa-coins"></i> Payments
                <span class="card-modern-badge"><?= count($payments) ?></span>
                <span class="card-modern-badge" style="background:var(--page-success-bg);color:var(--page-success);">
                    Total: TSh <?= number_format($total_paid, 0) ?>
                </span>
            </h3>
        </div>

        <div style="overflow-x:auto;">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Method</th>
                        <th>Received By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td style="font-family:'Courier New',monospace;"><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></td>
                            <td style="text-align:right;"><strong style="color:var(--page-success);">TSh <?= number_format($payment['amount'] ?? 0, 0) ?></strong></td>
                            <td><?= ucfirst($payment['payment_method'] ?? 'Cash') ?></td>
                            <td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td>
                            <td style="font-size:0.75rem;"><?= date('d/m/Y H:i', strtotime($payment['received_at'] ?? 'now')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================
         CANCELLATION FORM
         ================================================================ -->
    <?php if ($bill['status'] !== 'cancelled'): ?>
    <div class="card-modern danger animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-modern-header" style="border-bottom-color:var(--page-danger);">
            <h3 class="card-modern-title" style="color:var(--page-danger);">
                <i class="fas fa-ban" style="color:var(--page-danger);"></i>
                Cancel This Bill
            </h3>
        </div>

        <form method="POST" action="" id="cancelForm">
            <input type="hidden" name="confirm_cancel" value="1">

            <div style="margin-bottom:20px;">
                <label class="form-label">
                    <i class="fas fa-comment label-icon"></i> Cancellation Reason <span class="required">*</span>
                </label>
                <textarea name="reason" class="form-control-modern" required placeholder="Please provide a reason for cancelling this bill (e.g., duplicate entry, patient request, payment error...)" rows="4"><?= htmlspecialchars($cancellation_reason) ?></textarea>
            </div>

            <div class="alert-modern alert-modern-warning" style="margin:16px 0;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Please confirm:</strong> This action cannot be undone. All payments will be reversed and stock will be restored.
                </div>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap;padding-top:16px;border-top:2px solid var(--page-border);">
                <button type="button" class="btn-modern btn-modern-danger" id="cancelBtn" onclick="confirmCancellation()">
                    <i class="fas fa-ban"></i> Cancel Bill
                </button>
                <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-modern btn-modern-outline">
                    <i class="fas fa-times"></i> Go Back
                </a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <!-- Already Cancelled -->
    <div class="card-modern success animate-fade-in-up" style="animation-delay:0.3s;">
        <div style="text-align:center;padding:30px 20px;">
            <i class="fas fa-check-circle" style="font-size:3.5rem;color:var(--page-success);margin-bottom:16px;display:block;"></i>
            <h3 style="font-size:1.3rem;font-weight:700;color:var(--page-success);margin:0 0 8px 0;">
                Bill Already Cancelled
            </h3>
            <p style="color:var(--page-text-secondary);margin:0 0 4px 0;">
                This bill was cancelled on <?= date('F d, Y h:i A', strtotime($bill['updated_at'] ?? 'now')) ?>
            </p>
            <?php if (!empty($bill['notes']) && strpos($bill['notes'], 'Cancelled by') !== false): ?>
                <p style="color:var(--page-text-primary);margin:12px 0;padding:12px;background:var(--page-bg-card);border-radius:10px;font-size:0.85rem;">
                    <?= nl2br(htmlspecialchars($bill['notes'])) ?>
                </p>
            <?php endif; ?>
            <div style="margin-top:20px;">
                <a href="bills.php?branch=<?= $selected_branch_id ?>" class="btn-modern btn-modern-primary">
                    <i class="fas fa-arrow-left"></i> Back to Bills
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-modern" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
    <div>
        <p style="font-weight:700;font-size:0.85rem;margin:0 0 2px 0;" id="toastTitle">Notification</p>
        <p style="font-size:0.78rem;opacity:0.95;margin:0;line-height:1.4;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');

        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F0F4F8';
            if (mainContent) mainContent.style.background = '#F0F4F8';
        }
    }

    enforceDarkModeBackground();
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-modern ' + type;
        toastTitle.textContent = title;
        toastMessage.innerHTML = message;
        toast.style.display = 'flex';
        void toast.offsetWidth;
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 4000);
    }

    // ================================================================
    // CONFIRM CANCELLATION
    // ================================================================
    function confirmCancellation() {
        var reason = document.querySelector('textarea[name="reason"]');
        if (!reason.value.trim()) {
            showToast('⚠️ Warning', 'Please provide a cancellation reason', 'warning');
            reason.focus();
            return;
        }

        var totalAmount = <?= json_encode($bill['total_amount'] ?? 0) ?>;
        var totalPaid = <?= json_encode($total_paid) ?>;

        var message = '⚠️ Are you sure you want to cancel this bill?\n\n';
        message += 'Bill #: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>\n';
        message += 'Total Amount: TSh ' + totalAmount.toLocaleString() + '\n';
        if (totalPaid > 0) {
            message += 'Paid Amount: TSh ' + totalPaid.toLocaleString() + ' (will be reversed)\n';
        }
        message += '\nThis action CANNOT be undone!';

        if (confirm(message)) {
            var btn = document.getElementById('cancelBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Processing...';

            document.getElementById('cancelForm').submit();
        }
    }

    console.log('%c🚫 Braick - Cancel Bill', 'font-size:18px; font-weight:bold; color:#DC2626;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🎨 Danger page header + summary cards', 'font-size:13px; color:#DC2626;');
    console.log('%c🌙 FULL DARK MODE works', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Bill: <?= htmlspecialchars($bill["bill_number"] ?? "N/A") ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>