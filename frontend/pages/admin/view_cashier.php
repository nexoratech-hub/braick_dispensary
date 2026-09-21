<?php
// ================================================================
// FILE: frontend/pages/admin/view_cashier.php
// ADMIN - VIEW CASHIER BRANCH DETAILS (V14 - PRESCRIPTION GROSS)
// ✅ V14: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium)
// ✅ V14: Prescription BILA round off (exact value, 2 decimal places)
// ✅ V14: Formula breakdown card imeondolewa
// ✅ SAWA KWA 100% NA AUDIT V13, ADMIN V20, CASHIERS V13.1
// ✅ FIXED: Font Awesome loaded kwa kila page
// ✅ FIXED: JetBrains Mono font
// ✅ PAYMENTS-BASED: Patient Bills = payments.amount
// ✅ 6 CARDS with icons
// ✅ Recent Bills with View, Edit, Cancel buttons
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

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// ✅ V14: ROUND TO NEAREST 50 FUNCTION (kwa cards zingine)
// ================================================================
function round_to_50($value) {
    return round($value / 50) * 50;
}

// ================================================================
// GET BRANCH ID
// ================================================================
$cashier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($cashier_id <= 0) {
    header('Location: cashiers.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// HANDLE CANCEL BILL ACTION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_bill') {
    $bill_id = (int)($_POST['bill_id'] ?? 0);
    $cancel_reason = trim($_POST['cancel_reason'] ?? 'Cancelled by Admin');
    
    if ($bill_id > 0) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT id, bill_number, status, paid_amount FROM bills WHERE id = ?");
            $stmt->execute([$bill_id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bill) throw new Exception('Bill not found');
            if ($bill['status'] === 'cancelled') throw new Exception('Bill is already cancelled');
            if ($bill['paid_amount'] > 0) throw new Exception('Cannot cancel a paid or partially paid bill. Please refund first.');
            
            $stmt = $db->prepare("
                UPDATE bills 
                SET status = 'cancelled', 
                    notes = CONCAT(COALESCE(notes, ''), '\n[CANCELLED by Admin: ', ?, ' at ', NOW(), ']'),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$cancel_reason, $bill_id]);
            
            $stmt = $db->prepare("UPDATE bill_items SET status = 'cancelled', updated_at = NOW() WHERE bill_id = ?");
            $stmt->execute([$bill_id]);
            
            try {
                $log_stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'bill_cancelled', ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id, 
                    $user_branch_id,
                    "Bill cancelled: {$bill['bill_number']} - Reason: $cancel_reason"
                ]);
            } catch (Exception $e) {}
            
            $db->commit();
            
            $_SESSION['flash_message'] = "✅ Bill <strong>{$bill['bill_number']}</strong> cancelled successfully!";
            $_SESSION['flash_type'] = 'success';
            header('Location: view_cashier.php?id=' . $cashier_id . '&branch=' . urlencode($selected_branch_id));
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_message'] = "❌ Error cancelling bill: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            header('Location: view_cashier.php?id=' . $cashier_id . '&branch=' . urlencode($selected_branch_id));
            exit;
        }
    }
}

// ================================================================
// FETCH CASHIER BRANCH DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            b.*,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier' AND status = 'active') as active_cashiers,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier') as total_cashiers,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception' AND status = 'active') as active_receptions,
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'reception') as total_receptions,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'pending' AND patient_id IS NOT NULL AND visit_id IS NOT NULL AND bill_number NOT LIKE 'BILL-OTC-%') as pending_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'partial' AND patient_id IS NOT NULL AND visit_id IS NOT NULL AND bill_number NOT LIKE 'BILL-OTC-%') as partial_bills,
            (SELECT COUNT(DISTINCT p.bill_id) FROM payments p INNER JOIN bills pb ON p.bill_id = pb.id WHERE pb.branch_id = b.id AND pb.patient_id IS NOT NULL AND pb.visit_id IS NOT NULL AND pb.bill_number NOT LIKE 'BILL-OTC-%') as paid_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND status = 'cancelled' AND patient_id IS NOT NULL AND visit_id IS NOT NULL AND bill_number NOT LIKE 'BILL-OTC-%') as cancelled_bills,
            (SELECT COUNT(*) FROM bills WHERE branch_id = b.id AND patient_id IS NOT NULL AND visit_id IS NOT NULL AND bill_number NOT LIKE 'BILL-OTC-%') as total_bills,
            (SELECT COUNT(*) FROM payments WHERE branch_id = b.id) as total_payments,
            (SELECT COUNT(*) FROM payments WHERE branch_id = b.id AND DATE(received_at) = CURDATE()) as today_payments
        FROM branches b
        WHERE b.id = ?
    ");
    $stmt->execute([$cashier_id]);
    $cashier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cashier) {
        header('Location: cashiers.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching cashier: " . $e->getMessage());
    header('Location: cashiers.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// REVENUE QUERIES - PAYMENTS-BASED
// ================================================================
$patient_bills_revenue = 0;
$payments_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as bills_revenue,
               COUNT(DISTINCT p.id) as payments_count,
               COUNT(DISTINCT p.bill_id) as bills_count
        FROM payments p
        INNER JOIN bills b ON p.bill_id = b.id
        WHERE p.branch_id = ? 
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
    ");
    $stmt->execute([$cashier_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_bills_revenue = $row['bills_revenue'] ?? 0;
    $payments_count = $row['payments_count'] ?? 0;
} catch (Exception $e) {
    $patient_bills_revenue = 0;
    $payments_count = 0;
}

$otc_revenue = 0;
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as otc_revenue
        FROM otc_sales 
        WHERE branch_id = ? 
        AND payment_status = 'paid'
    ");
    $stmt->execute([$cashier_id]);
    $otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['otc_revenue'] ?? 0;
} catch (Exception $e) {
    $otc_revenue = 0;
}

// ✅ V14: Medication RAW (GROSS - bila discount)
$medication_raw = 0;
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(bi.total_price), 0) as medication_raw
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE bi.branch_id = ?
        AND bi.item_type = 'medication'
        AND bi.status != 'cancelled'
        AND b.status IN ('paid', 'partial')
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
    ");
    $stmt->execute([$cashier_id]);
    $medication_raw = $stmt->fetch(PDO::FETCH_ASSOC)['medication_raw'] ?? 0;
} catch (Exception $e) {
    $medication_raw = 0;
}

// Pharmacy Discount (info only)
$pharmacy_discount = 0;
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(b.pharmacy_discount), 0) as pharmacy_discount
        FROM bills b
        WHERE b.branch_id = ?
        AND b.status IN ('paid', 'partial')
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        AND b.pharmacy_discount > 0
    ");
    $stmt->execute([$cashier_id]);
    $pharmacy_discount = $stmt->fetch(PDO::FETCH_ASSOC)['pharmacy_discount'] ?? 0;
} catch (Exception $e) {
    $pharmacy_discount = 0;
}

// ✅ V14: PRESCRIPTION REVENUE = Medication_RAW (GROSS - bila discount, bila premium, bila round)
$prescription_revenue = (float)$medication_raw;

// Round cards zingine (bila Prescription)
$patient_bills_revenue = round_to_50($patient_bills_revenue);
$otc_revenue = round_to_50($otc_revenue);
$medication_raw = round_to_50($medication_raw);
$pharmacy_discount = round_to_50($pharmacy_discount);

$total_revenue = $patient_bills_revenue + $otc_revenue;

$total_expenses = 0;
try {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_expenses
        FROM expenses 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$cashier_id]);
    $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;
} catch (Exception $e) {
    $total_expenses = 0;
}
$total_expenses = round_to_50($total_expenses);

$net_profit = $total_revenue - $total_expenses;

// ================================================================
// GET STAFF FOR THIS BRANCH
// ================================================================
$staff_list = [];
try {
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, role, status, created_at 
        FROM users 
        WHERE branch_id = ? 
        AND role IN ('cashier', 'reception')
        ORDER BY role, full_name
    ");
    $stmt->execute([$cashier_id]);
    $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $staff_list = [];
}

$cashier_count = 0;
$reception_count = 0;
foreach ($staff_list as $staff) {
    if ($staff['role'] === 'cashier') $cashier_count++;
    if ($staff['role'] === 'reception') $reception_count++;
}

// ================================================================
// GET RECENT PAYMENTS
// ================================================================
$recent_payments = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.receipt_number,
            p.amount,
            p.payment_method,
            p.received_at,
            b.bill_number,
            pat.full_name as patient_name,
            u.full_name as received_by_name
        FROM payments p
        LEFT JOIN bills b ON p.bill_id = b.id
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.branch_id = ?
        ORDER BY p.received_at DESC
        LIMIT 10
    ");
    $stmt->execute([$cashier_id]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_payments = [];
}

// ================================================================
// GET RECENT BILLS
// ================================================================
$recent_bills = [];
try {
    $stmt = $db->prepare("
        SELECT 
            b.id,
            b.bill_number,
            b.patient_id,
            b.visit_id,
            b.subtotal,
            b.discount_amount,
            b.total_discount,
            b.total_amount,
            b.paid_amount,
            b.balance,
            b.status,
            b.payment_method,
            b.notes,
            b.created_at,
            b.updated_at,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            v.visit_number,
            u.full_name as created_by_name
        FROM bills b
        LEFT JOIN patients pat ON b.patient_id = pat.id
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.branch_id = ?
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        ORDER BY b.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$cashier_id]);
    $recent_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_bills = [];
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// HELPERS
// ================================================================
function getRoleBadge($role) {
    $badges = [
        'cashier' => '<span class="badge-custom badge-blue"><i class="fas fa-cash-register"></i> Cashier</span>',
        'reception' => '<span class="badge-custom badge-teal"><i class="fas fa-headset"></i> Reception</span>'
    ];
    return $badges[$role] ?? '<span class="badge-custom badge-secondary">' . ucfirst($role) . '</span>';
}

function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message']);
unset($_SESSION['flash_type']);

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================
     ✅ FONT AWESOME 6 - LOADED DIRECTLY
     ================================================================ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- ✅ JETBRAINS MONO -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --font-mono: 'JetBrains Mono', 'Courier New', monospace;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #F1F5F9;
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

    /* ══════════════════════════════════════════════════════════════
       ✅ FORCE FONT AWESOME ICONS
       ══════════════════════════════════════════════════════════════ */
    i.fas, i.far, i.fab, i.fa,
    .fas, .far, .fab, .fa,
    i[class*="fa-"],
    span[class*="fa-"] {
        font-family: 'Font Awesome 6 Free', 'Font Awesome 6 Brands', 'FontAwesome' !important;
        font-weight: 900 !important;
        font-style: normal !important;
        font-variant: normal !important;
        text-rendering: auto !important;
        -webkit-font-smoothing: antialiased !important;
        display: inline-block !important;
        line-height: 1 !important;
    }
    
    i.far, .far { font-family: 'Font Awesome 6 Free' !important; font-weight: 400 !important; }
    i.fab, .fab { font-family: 'Font Awesome 6 Brands' !important; font-weight: 400 !important; }

    /* ✅ JetBrains Mono kwa namba */
    .detail-value-custom,
    .detail-label-custom,
    .card-amount-custom,
    .card-label-custom,
    .card-sub-custom,
    .stat-number-mini,
    .stat-label-mini,
    .data-table-custom,
    .badge-custom,
    .status-badge-pending, .status-badge-paid, .status-badge-partial, .status-badge-cancelled,
    .header-badge,
    .page-subtitle,
    .flash-message-custom,
    .bill-action-btn {
        font-family: var(--font-mono) !important;
        font-variant-numeric: tabular-nums;
    }

    body { background: var(--page-bg-body); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* PAGE HEADER */
    .page-header-custom {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083C8A 100%);
        border-radius: 18px;
        padding: 28px 36px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-custom .page-title {
        color: white;
        font-size: 1.8rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-custom .page-title i { 
        font-size: 2rem; 
        opacity: 0.9;
        font-family: 'Font Awesome 6 Free' !important;
        font-weight: 900 !important;
    }

    .page-header-custom .page-subtitle {
        color: rgba(255,255,255,0.9);
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
        background: linear-gradient(135deg, #FCD34D, #F59E0B);
        color: #78350F;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .v14-badge {
        background: rgba(252,211,77,0.25);
        color: #FCD34D;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 800;
        border: 1px solid rgba(252,211,77,0.4);
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .pulse-dot {
        display: inline-block;
        width: 6px; height: 6px;
        border-radius: 50%;
        background: currentColor;
        margin-right: 4px;
        animation: pulseDot 1.5s infinite;
    }

    @keyframes pulseDot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }

    .page-header-custom .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .header-badge i { 
        font-size: 0.75rem;
        font-family: 'Font Awesome 6 Free' !important;
        font-weight: 900 !important;
    }

    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1.5px solid rgba(255,255,255,0.3);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
    }

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* CASHIER INFO CARD */
    .detail-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        padding: 20px 24px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    html[data-theme="dark"] .detail-card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .detail-label-custom {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 4px;
    }

    .detail-value-custom {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .detail-value-custom { color: #F1F5F9; }

    /* REVENUE CARDS */
    .revenue-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    .revenue-card-custom {
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid rgba(255,255,255,0.1);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
        text-decoration: none;
        color: white;
        display: block;
        min-height: 140px;
    }

    .revenue-card-custom::before {
        content: '';
        position: absolute;
        top: -50%; right: -20%;
        width: 200px; height: 200px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .revenue-card-custom:hover {
        transform: translateY(-6px) scale(1.02);
        box-shadow: 0 12px 36px rgba(0,0,0,0.2);
        color: white;
    }

    .revenue-card-custom:hover::before {
        transform: scale(1.3);
        right: -10%;
    }

    .revenue-card-custom .card-icon-custom {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-bottom: 8px;
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.3s ease;
    }

    .revenue-card-custom:hover .card-icon-custom {
        transform: scale(1.1) rotate(-3deg);
        background: rgba(255,255,255,0.3);
    }

    .revenue-card-custom .card-icon-custom i {
        font-size: 1.2rem;
        color: white;
        font-family: 'Font Awesome 6 Free' !important;
        font-weight: 900 !important;
    }

    .revenue-card-custom .card-amount-custom {
        font-size: 1.4rem;
        font-weight: 800;
        color: white;
        line-height: 1.2;
        font-family: var(--font-mono);
        letter-spacing: -0.02em;
    }

    .revenue-card-custom .card-label-custom {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.7);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin: 4px 0 2px 0;
        font-family: var(--font-mono);
    }

    .revenue-card-custom .card-sub-custom {
        font-size: 0.65rem;
        color: rgba(255,255,255,0.6);
        margin: 0;
        opacity: 0.8;
        font-family: var(--font-mono);
    }

    .revenue-card-custom .card-nav-arrow {
        position: absolute;
        bottom: 10px; right: 14px;
        font-size: 0.7rem;
        color: rgba(255,255,255,0.4);
        transition: all 0.3s ease;
    }

    .revenue-card-custom:hover .card-nav-arrow {
        opacity: 1;
        transform: translateX(4px);
        color: rgba(255,255,255,0.9);
    }

    /* Card Colors */
    .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .card-blue:hover { box-shadow: 0 12px 36px rgba(11, 94, 215, 0.4); }
    .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .card-red:hover { box-shadow: 0 12px 36px rgba(220, 38, 38, 0.4); }
    .card-green { background: linear-gradient(135deg, #059669, #047857); }
    .card-green:hover { box-shadow: 0 12px 36px rgba(5, 150, 105, 0.4); }
    .card-teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
    .card-teal:hover { box-shadow: 0 12px 36px rgba(13, 148, 136, 0.4); }
    .card-purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .card-purple:hover { box-shadow: 0 12px 36px rgba(124, 58, 237, 0.4); }
    .card-cyan { background: linear-gradient(135deg, #0891B2, #0E7490); }
    .card-cyan:hover { box-shadow: 0 12px 36px rgba(8, 145, 178, 0.4); }

    /* TABLE CONTAINER */
    .table-container-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }

    .table-container-custom:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }

    html[data-theme="dark"] .table-container-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .table-container-custom .card-header-custom {
        padding: 14px 20px;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        position: relative;
        overflow: hidden;
    }

    .table-container-custom .card-header-custom::before {
        content: '';
        position: absolute;
        top: -50%; right: -10%;
        width: 300px; height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .table-container-custom .card-header-custom .card-title-custom {
        font-size: 0.85rem;
        font-weight: 700;
        color: white;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
        font-family: var(--font-mono);
    }

    .table-container-custom .card-header-custom .card-action-custom {
        color: rgba(255,255,255,0.85);
        font-size: 0.7rem;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 600;
        position: relative;
        z-index: 1;
        font-family: var(--font-mono);
    }

    .table-container-custom .card-header-custom .card-action-custom:hover {
        color: white;
        transform: translateX(2px);
    }

    /* DATA TABLE */
    .data-table-custom {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.78rem;
    }

    .data-table-custom thead th {
        background: var(--page-hover, #F1F5F9);
        color: var(--page-text-secondary, #64748B);
        font-weight: 700;
        padding: 10px 14px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        text-align: left;
        font-family: var(--font-mono);
    }

    html[data-theme="dark"] .data-table-custom thead th {
        background: #0F172A;
        border-bottom-color: #334155;
    }

    .data-table-custom td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
        font-family: var(--font-mono);
    }

    html[data-theme="dark"] .data-table-custom td {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .data-table-custom tbody tr:hover td {
        background: var(--page-hover, #F1F5F9);
    }

    html[data-theme="dark"] .data-table-custom tbody tr:hover td {
        background: #1E3A5F;
    }

    .data-table-custom tbody tr:last-child td {
        border-bottom: none;
    }

    /* STATUS BADGES */
    .status-badge-pending {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
        letter-spacing: 0.03em;
        text-transform: uppercase;
        font-family: var(--font-mono);
    }

    .status-badge-paid {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        letter-spacing: 0.03em;
        text-transform: uppercase;
        font-family: var(--font-mono);
    }

    .status-badge-partial {
        background: linear-gradient(135deg, #D97706, #B45309);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
        letter-spacing: 0.03em;
        text-transform: uppercase;
        font-family: var(--font-mono);
    }

    .status-badge-cancelled {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
        letter-spacing: 0.03em;
        text-transform: uppercase;
        font-family: var(--font-mono);
    }

    /* BILL ACTIONS */
    .bill-actions {
        display: flex;
        gap: 5px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .bill-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 700;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        white-space: nowrap;
        font-family: inherit;
    }

    .bill-btn-view {
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid rgba(11, 94, 215, 0.2);
    }

    .bill-btn-view:hover {
        background: #0B5ED7;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .bill-btn-edit {
        background: #FEF3C7;
        color: #D97706;
        border: 1px solid rgba(217, 119, 6, 0.2);
    }

    .bill-btn-edit:hover {
        background: #D97706;
        color: white;
        transform: translateY(-2px);
    }

    .bill-btn-cancel {
        background: #FEE2E2;
        color: #DC2626;
        border: 1px solid rgba(220, 38, 38, 0.2);
    }

    .bill-btn-cancel:hover {
        background: #DC2626;
        color: white;
        transform: translateY(-2px);
    }

    .bill-btn-disabled {
        background: #F1F5F9;
        color: #94A3B8;
        border: 1px solid #E2E8F0;
        cursor: not-allowed;
        opacity: 0.5;
    }

    [data-theme="dark"] .bill-btn-view { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .bill-btn-edit { background: #3A2E1A; color: #FBBF24; }
    [data-theme="dark"] .bill-btn-cancel { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .bill-btn-disabled { background: #1E293B; color: #64748B; }

    /* STAT MINI */
    .stat-mini-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        text-decoration: none;
        color: var(--page-text-primary, #1E293B);
        display: block;
    }

    html[data-theme="dark"] .stat-mini-custom {
        background: #1E293B;
        border-color: #334155;
        color: #F1F5F9;
    }

    .stat-mini-custom:hover {
        border-color: #0B5ED7;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.15);
    }

    .stat-mini-custom .stat-label-mini {
        font-size: 0.65rem;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .stat-mini-custom .stat-number-mini {
        font-size: 1.5rem;
        font-weight: 800;
        font-family: var(--font-mono);
    }

    .stat-mini-custom .text-green-600 { color: #059669; }
    .stat-mini-custom .text-yellow-600 { color: #D97706; }
    .stat-mini-custom .text-purple-600 { color: #7C3AED; }
    .stat-mini-custom .text-teal-600 { color: #0D9488; }
    .stat-mini-custom .text-red-600 { color: #DC2626; }
    .stat-mini-custom .text-blue-600 { color: #0B5ED7; }

    /* BADGES */
    .badge-custom {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 700;
        font-family: var(--font-mono);
    }

    .badge-blue { background: #E8F0FE; color: #0B5ED7; }
    .badge-teal { background: #ECFDF5; color: #0D9488; }
    .badge-secondary { background: #E2E8F0; color: #64748B; }

    html[data-theme="dark"] .badge-blue { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .badge-teal { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .badge-secondary { background: #334155; color: #94A3B8; }

    /* FLASH MESSAGE */
    .flash-message-custom {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        animation: slideDown 0.5s ease;
        font-family: var(--font-mono);
    }

    .flash-message-custom.success {
        background: #D1FAE5;
        color: #065F46;
        border-left: 5px solid #059669;
    }

    .flash-message-custom.error {
        background: #FEE2E2;
        color: #991B1B;
        border-left: 5px solid #DC2626;
    }

    html[data-theme="dark"] .flash-message-custom.success {
        background: #1A3A2A;
        color: #34D399;
    }

    html[data-theme="dark"] .flash-message-custom.error {
        background: #3A1A1A;
        color: #F87171;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* MODAL */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .modal-overlay.show { display: flex; }

    .modal-content-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        max-width: 500px;
        width: 100%;
        padding: 24px 28px;
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: 0 20px 30px rgba(0,0,0,0.2);
    }

    html[data-theme="dark"] .modal-content-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .modal-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 16px;
    }

    .modal-title-custom {
        font-size: 1.1rem;
        font-weight: 700;
        color: #DC2626;
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: var(--font-mono);
    }

    .modal-close-custom {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--page-text-secondary, #64748B);
    }

    .cancel-reason-textarea {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        font-size: 0.9rem;
        resize: vertical;
        min-height: 80px;
        background: var(--page-bg-body, #F1F5F9);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }

    .cancel-reason-textarea:focus {
        border-color: #DC2626;
        outline: none;
    }

    /* GRID UTILITIES */
    .grid-5-cols { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }

    /* ANIMATIONS */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    @media (max-width: 1024px) {
        .revenue-grid { grid-template-columns: repeat(2, 1fr); }
        .grid-5-cols { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-custom { padding: 18px; }
        .page-header-custom .page-title { font-size: 1.15rem; }
        .revenue-grid { grid-template-columns: 1fr; }
        .grid-5-cols { grid-template-columns: 1fr 1fr; }
        .bill-actions { flex-direction: column; }
        .bill-action-btn { width: 100%; justify-content: center; }
    }

    @media print {
        .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .bill-actions { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- FLASH MESSAGE -->
    <?php if ($flash_message) { ?>
        <div class="flash-message-custom <?= $flash_type ?>">
            <i class="fas <?= $flash_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= $flash_message ?></span>
        </div>
    <?php } ?>

    <!-- PAGE HEADER -->
    <div class="page-header-custom animate-fade-in-up">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title">
                <i class="fas fa-cash-register"></i>
                Cashier Details
                <span class="role-badge-display"><i class="fas fa-user-shield"></i> ADMIN</span>
                <span class="v14-badge">
                    <span class="pulse-dot"></span> V14 GROSS
                </span>
            </h1>
            <p class="page-subtitle">
                <span><i class="fas fa-store-alt"></i> <strong><?= htmlspecialchars($cashier['name'] ?? 'N/A') ?></strong></span>
                <span class="header-badge">
                    <i class="fas fa-<?= ($cashier['status'] ?? 'active') === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($cashier['status'] ?? 'Active') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.25);border-color:rgba(52,211,153,0.4);color:#A7F3D0;">
                    <i class="fas fa-money-bill-wave"></i> <?= formatCurrency($total_revenue) ?> Revenue
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.25);border-color:rgba(251,191,36,0.4);color:#FDE68A;">
                    <i class="fas fa-file-invoice"></i> <?= $cashier['total_bills'] ?? 0 ?> Bills
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.25);border-color:rgba(52,211,153,0.4);color:#A7F3D0;">
                    <i class="fas fa-chart-line"></i> Profit: <?= formatCurrency($net_profit) ?>
                </span>
            </p>
        </div>
        <div style="position:relative;z-index:1;">
            <a href="cashiers.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- CASHIER INFO -->
    <div class="detail-card-custom animate-fade-in-up" style="animation-delay:0.05s;">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
            <div>
                <p class="detail-label-custom"><i class="fas fa-map-marker-alt"></i> Location</p>
                <p class="detail-value-custom"><?= htmlspecialchars($cashier['location'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-custom"><i class="fas fa-phone"></i> Phone</p>
                <p class="detail-value-custom"><?= htmlspecialchars($cashier['phone'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-custom"><i class="fas fa-envelope"></i> Email</p>
                <p class="detail-value-custom"><?= htmlspecialchars($cashier['email'] ?? 'N/A') ?></p>
            </div>
            <div>
                <p class="detail-label-custom"><i class="fas fa-user-tie"></i> Staff</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <span class="badge-custom badge-blue">
                        <i class="fas fa-cash-register"></i> <?= $cashier['active_cashiers'] ?? 0 ?> Cashiers
                    </span>
                    <span class="badge-custom badge-teal">
                        <i class="fas fa-headset"></i> <?= $cashier['active_receptions'] ?? 0 ?> Reception
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 6 REVENUE CARDS -->
    <div class="revenue-grid animate-fade-in-up" style="animation-delay:0.1s;">
        <a href="revenue.php?branch=<?= $cashier_id ?>" class="revenue-card-custom card-blue">
            <div class="card-icon-custom"><i class="fas fa-money-bill-wave"></i></div>
            <p class="card-amount-custom"><?= formatCurrency($total_revenue) ?></p>
            <p class="card-label-custom">Total Revenue</p>
            <p class="card-sub-custom">Payments + OTC Sales</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <a href="expenses.php?branch=<?= $cashier_id ?>" class="revenue-card-custom card-red">
            <div class="card-icon-custom"><i class="fas fa-arrow-up"></i></div>
            <p class="card-amount-custom"><?= formatCurrency($total_expenses) ?></p>
            <p class="card-label-custom">Total Expenses</p>
            <p class="card-sub-custom">From expenses (paid)</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <a href="profit.php?branch=<?= $cashier_id ?>" class="revenue-card-custom card-green">
            <div class="card-icon-custom"><i class="fas fa-chart-line"></i></div>
            <p class="card-amount-custom"><?= formatCurrency($net_profit) ?></p>
            <p class="card-label-custom">Net Profit</p>
            <p class="card-sub-custom">Revenue - Expenses</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <a href="bills.php?branch=<?= $cashier_id ?>&status=paid" class="revenue-card-custom card-green">
            <div class="card-icon-custom"><i class="fas fa-hand-holding-usd"></i></div>
            <p class="card-amount-custom"><?= formatCurrency($patient_bills_revenue) ?></p>
            <p class="card-label-custom">Patient Payments</p>
            <p class="card-sub-custom"><?= number_format($payments_count) ?> payments</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <a href="otc_sales.php?branch=<?= $cashier_id ?>" class="revenue-card-custom card-teal">
            <div class="card-icon-custom"><i class="fas fa-cash-register"></i></div>
            <p class="card-amount-custom"><?= formatCurrency($otc_revenue) ?></p>
            <p class="card-label-custom">OTC Sales</p>
            <p class="card-sub-custom">Over-the-counter sales</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
        
        <!-- ✅ V14: PRESCRIPTION = GROSS (Bila round off) -->
        <a href="prescriptions.php?branch=<?= $cashier_id ?>" class="revenue-card-custom card-purple">
            <div class="card-icon-custom"><i class="fas fa-prescription"></i></div>
            <p class="card-amount-custom" style="font-size:1.25rem;">
                TSh <?= number_format($prescription_revenue, 2, '.', ',') ?>
            </p>
            <p class="card-label-custom">Prescriptions (Gross)</p>
            <p class="card-sub-custom">GROSS = Medication RAW</p>
            <span class="card-nav-arrow"><i class="fas fa-arrow-right"></i></span>
        </a>
    </div>

    <!-- BILLS SUMMARY CARDS -->
    <div class="grid-5-cols animate-fade-in-up" style="animation-delay:0.15s;">
        <a href="bills.php?branch=<?= $cashier_id ?>" class="stat-mini-custom">
            <p class="stat-label-mini"><i class="fas fa-file-invoice"></i> Total Bills</p>
            <p class="stat-number-mini text-blue-600"><?= number_format($cashier['total_bills'] ?? 0) ?></p>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>&status=pending" class="stat-mini-custom">
            <p class="stat-label-mini"><i class="fas fa-clock"></i> Pending</p>
            <p class="stat-number-mini text-yellow-600"><?= number_format($cashier['pending_bills'] ?? 0) ?></p>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>&status=partial" class="stat-mini-custom">
            <p class="stat-label-mini"><i class="fas fa-hourglass-half"></i> Partial</p>
            <p class="stat-number-mini text-purple-600"><?= number_format($cashier['partial_bills'] ?? 0) ?></p>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>&status=paid" class="stat-mini-custom">
            <p class="stat-label-mini"><i class="fas fa-check-circle"></i> Paid</p>
            <p class="stat-number-mini text-green-600"><?= number_format($cashier['paid_bills'] ?? 0) ?></p>
        </a>
        <a href="bills.php?branch=<?= $cashier_id ?>&status=cancelled" class="stat-mini-custom">
            <p class="stat-label-mini"><i class="fas fa-times-circle"></i> Cancelled</p>
            <p class="stat-number-mini text-red-600"><?= number_format($cashier['cancelled_bills'] ?? 0) ?></p>
        </a>
    </div>

    <!-- RECENT BILLS -->
    <div class="table-container-custom animate-fade-in-up" style="animation-delay:0.2s;">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-file-invoice"></i>
                Recent Bills (<?= count($recent_bills) ?>)
            </h3>
            <a href="bills.php?branch=<?= $cashier_id ?>" class="card-action-custom">View All →</a>
        </div>
        <?php if (count($recent_bills) > 0) { ?>
            <div style="overflow-x:auto;">
                <table class="data-table-custom" id="billsTable">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="text-align:center;min-width:220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bills as $bill) { 
                            $balance = (float)$bill['balance'];
                            $bill_status = strtolower($bill['status'] ?? 'pending');
                            
                            $status_class = '';
                            $status_icon = '';
                            $status_text = '';
                            
                            switch ($bill_status) {
                                case 'paid':
                                    $status_class = 'status-badge-paid';
                                    $status_icon = 'fa-check-circle';
                                    $status_text = 'PAID';
                                    break;
                                case 'partial':
                                    $status_class = 'status-badge-partial';
                                    $status_icon = 'fa-hourglass-half';
                                    $status_text = 'PARTIAL';
                                    break;
                                case 'pending':
                                    $status_class = 'status-badge-pending';
                                    $status_icon = 'fa-clock';
                                    $status_text = 'PENDING';
                                    break;
                                case 'cancelled':
                                    $status_class = 'status-badge-cancelled';
                                    $status_icon = 'fa-times-circle';
                                    $status_text = 'CANCELLED';
                                    break;
                                default:
                                    $status_class = 'status-badge-pending';
                                    $status_icon = 'fa-circle';
                                    $status_text = strtoupper($bill_status);
                            }
                        ?>
                            <tr>
                                <td style="font-size:0.7rem;font-weight:700;color:#0B5ED7;">
                                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></strong>
                                    <?php if (!empty($bill['patient_code'])) { ?>
                                        <div style="font-size:0.6rem;color:var(--page-text-secondary);">
                                            <?= htmlspecialchars($bill['patient_code']) ?>
                                        </div>
                                    <?php } ?>
                                </td>
                                <td style="font-weight:700;"><?= formatCurrency($bill['total_amount'] ?? 0) ?></td>
                                <td style="color:#059669;font-weight:700;"><?= formatCurrency($bill['paid_amount'] ?? 0) ?></td>
                                <td>
                                    <?php if ($balance > 0) { ?>
                                        <span style="color:#DC2626;font-weight:700;"><?= formatCurrency($balance) ?></span>
                                    <?php } else { ?>
                                        <span style="color:#059669;font-weight:700;"><?= formatCurrency(0) ?></span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="<?= $status_class ?>">
                                        <i class="fas <?= $status_icon ?>"></i>
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td style="font-size:0.7rem;"><?= date('M d, Y', strtotime($bill['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <div class="bill-actions">
                                        <a href="view_bill.php?id=<?= $bill['id'] ?>&branch=<?= $cashier_id ?>" 
                                           class="bill-action-btn bill-btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if ($bill_status !== 'cancelled') { ?>
                                            <a href="edit_bill.php?id=<?= $bill['id'] ?>&branch=<?= $cashier_id ?>" 
                                               class="bill-action-btn bill-btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        <?php } else { ?>
                                            <button class="bill-action-btn bill-btn-disabled" disabled>
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        <?php } ?>
                                        
                                        <?php if ($bill_status !== 'cancelled' && $bill_status !== 'paid' && $balance == $bill['total_amount']) { ?>
                                            <button type="button" 
                                                    class="bill-action-btn bill-btn-cancel" 
                                                    onclick="openCancelModal(<?= $bill['id'] ?>, '<?= addslashes($bill['bill_number'] ?? 'N/A') ?>', '<?= addslashes($bill['patient_name'] ?? 'N/A') ?>')">
                                                <i class="fas fa-ban"></i> Cancel
                                            </button>
                                        <?php } else { ?>
                                            <button class="bill-action-btn bill-btn-disabled" disabled>
                                                <i class="fas fa-ban"></i> Cancel
                                            </button>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <div style="text-align:center;padding:30px;color:var(--page-text-muted);">
                <i class="fas fa-file-invoice" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                <p>No bills found</p>
            </div>
        <?php } ?>
    </div>

    <!-- RECENT PAYMENTS -->
    <div class="table-container-custom animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-credit-card"></i>
                Recent Payments (<?= count($recent_payments) ?>)
            </h3>
            <a href="payments.php?branch=<?= $cashier_id ?>" class="card-action-custom">View All →</a>
        </div>
        <?php if (count($recent_payments) > 0) { ?>
            <div style="overflow-x:auto;">
                <table class="data-table-custom" id="paymentsTable">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Received By</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $payment) { ?>
                            <tr>
                                <td style="font-size:0.7rem;"><?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></td>
                                <td style="font-size:0.7rem;"><?= htmlspecialchars($payment['bill_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($payment['patient_name'] ?? 'N/A') ?></td>
                                <td style="font-weight:700;color:#059669;"><?= formatCurrency($payment['amount'] ?? 0) ?></td>
                                <td>
                                    <span class="badge-custom badge-blue">
                                        <?= ucfirst($payment['payment_method'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td>
                                <td style="font-size:0.7rem;"><?= date('M d, Y h:i A', strtotime($payment['received_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="view_payment.php?id=<?= $payment['id'] ?>&branch=<?= $cashier_id ?>" 
                                       class="bill-action-btn bill-btn-view" style="font-size:0.65rem;padding:4px 10px;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <div style="text-align:center;padding:30px;color:var(--page-text-muted);">
                <i class="fas fa-credit-card" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                <p>No payments found</p>
            </div>
        <?php } ?>
    </div>

    <!-- STAFF LIST -->
    <div class="table-container-custom animate-fade-in-up" style="animation-delay:0.3s;">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-users"></i>
                Staff (<?= count($staff_list) ?>)
            </h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <span class="badge-custom" style="background:rgba(255,255,255,0.2);color:white;">
                    <i class="fas fa-cash-register"></i> Cashiers: <?= $cashier_count ?>
                </span>
                <span class="badge-custom" style="background:rgba(255,255,255,0.2);color:white;">
                    <i class="fas fa-headset"></i> Reception: <?= $reception_count ?>
                </span>
                <a href="add_employee.php?branch=<?= $cashier_id ?>" class="card-action-custom" style="background:rgba(255,255,255,0.15);padding:3px 12px;border-radius:12px;">
                    <i class="fas fa-plus"></i> Add Staff
                </a>
            </div>
        </div>
        <?php if (count($staff_list) > 0) { ?>
            <div style="overflow-x:auto;">
                <table class="data-table-custom" id="staffTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_list as $staff) { ?>
                            <tr>
                                <td style="font-weight:600;"><?= htmlspecialchars($staff['full_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($staff['email'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($staff['phone'] ?? 'N/A') ?></td>
                                <td><?= getRoleBadge($staff['role'] ?? 'other') ?></td>
                                <td>
                                    <span class="badge-custom" style="background:<?= $staff['status'] === 'active' ? '#059669' : '#DC2626' ?>;color:white;">
                                        <?= ucfirst($staff['status'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_employee.php?id=<?= $staff['id'] ?>&branch=<?= $cashier_id ?>" 
                                       class="bill-action-btn bill-btn-view" style="font-size:0.65rem;padding:4px 10px;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <div style="text-align:center;padding:30px;color:var(--page-text-muted);">
                <i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                <p>No staff assigned to this branch</p>
            </div>
        <?php } ?>
    </div>

</main>

<!-- ================================================================ -->
<!-- CANCEL BILL MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="cancelBillModal">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <div class="modal-title-custom">
                <i class="fas fa-exclamation-triangle"></i> Cancel Bill
            </div>
            <button class="modal-close-custom" onclick="closeCancelModal()">&times;</button>
        </div>
        
        <div style="text-align:center;font-size:3rem;color:#DC2626;margin-bottom:10px;">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        
        <p style="text-align:center;color:var(--page-text-primary);font-weight:600;margin-bottom:8px;">
            Are you sure you want to cancel this bill?
        </p>
        <p style="text-align:center;color:var(--page-text-secondary);font-size:0.85rem;margin-bottom:16px;">
            Bill: <strong id="cancelBillNumber"></strong><br>
            Patient: <strong id="cancelPatientName"></strong>
        </p>
        
        <div style="background:#FEE2E2;border:2px solid #DC2626;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:0.8rem;color:#991B1B;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>WARNING:</strong> This will cancel the entire bill and all its items. This action cannot be undone.
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="cancel_bill">
            <input type="hidden" name="bill_id" id="cancelBillId" value="">
            
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:0.8rem;font-weight:700;margin-bottom:6px;">
                    Reason for Cancellation <span style="color:#DC2626;">*</span>
                </label>
                <textarea name="cancel_reason" id="cancelReason" class="cancel-reason-textarea" 
                          placeholder="Enter reason for cancelling this bill..." required></textarea>
            </div>
            
            <div style="display:flex;gap:10px;padding-top:14px;border-top:2px solid var(--page-border);">
                <button type="submit" style="flex:1;background:#DC2626;color:white;border:none;padding:10px 24px;border-radius:8px;font-weight:700;font-size:0.9rem;cursor:pointer;font-family:var(--font-mono);">
                    <i class="fas fa-ban"></i> Yes, Cancel Bill
                </button>
                <button type="button" onclick="closeCancelModal()" style="background:transparent;color:var(--page-text-secondary);border:2px solid var(--page-border);padding:10px 24px;border-radius:8px;font-weight:700;font-size:0.9rem;cursor:pointer;font-family:var(--font-mono);">
                    <i class="fas fa-times"></i> No, Go Back
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ✅ VERIFY FONT AWESOME LOADED
    (function() {
        var testIcon = document.createElement('i');
        testIcon.className = 'fas fa-check';
        testIcon.style.position = 'absolute';
        testIcon.style.left = '-9999px';
        testIcon.style.fontSize = '24px';
        document.body.appendChild(testIcon);
        
        setTimeout(function() {
            var computed = window.getComputedStyle(testIcon, ':before');
            var content = computed.getPropertyValue('content');
            
            if (content === 'none' || content === '""' || content === 'normal') {
                console.warn('%c⚠️ Font Awesome HAIPO!', 'color:#F59E0B;font-weight:bold;font-size:14px;');
            } else {
                console.log('%c✅ Font Awesome imepakiwa vizuri', 'color:#10B981;font-weight:bold;');
            }
            document.body.removeChild(testIcon);
        }, 1000);
    })();

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

    document.addEventListener('darkModeChanged', function(e) {
        setTimeout(enforceDarkModeBackground, 50);
    });

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'data-theme') {
                enforceDarkModeBackground();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    function openCancelModal(billId, billNumber, patientName) {
        var modal = document.getElementById('cancelBillModal');
        if (!modal) return;
        
        document.getElementById('cancelBillId').value = billId;
        document.getElementById('cancelBillNumber').textContent = billNumber;
        document.getElementById('cancelPatientName').textContent = patientName;
        document.getElementById('cancelReason').value = '';
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        setTimeout(function() {
            document.getElementById('cancelReason').focus();
        }, 300);
    }
    
    function closeCancelModal() {
        var modal = document.getElementById('cancelBillModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    
    document.getElementById('cancelBillModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeCancelModal();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeCancelModal();
    });

    console.log('%c💰 Braick - View Cashier V14 GROSS', 'font-family: monospace; font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ V14: PRESCRIPTION = Medication_RAW (GROSS - bila discount, bila premium)', 'font-family: monospace; font-size:13px; color:#10B981; font-weight:bold;');
    console.log('%c✅ V14: Prescription BILA round off (exact value, 2 decimal places)', 'font-family: monospace; font-size:13px; color:#FCD34D; font-weight:bold;');
    console.log('%c✅ V14: Formula breakdown card imeondolewa', 'font-family: monospace; font-size:13px; color:#FCD34D;');
    console.log('%c✅ SAWA KWA 100% NA AUDIT V13, ADMIN V20, CASHIERS V13.1', 'font-family: monospace; font-size:13px; color:#FCD34D; font-weight:bold;');
    console.log('%c✅ FIXED: Font Awesome forced', 'font-family: monospace; font-size:13px; color:#10B981;');
    console.log('%c✅ FONT: JetBrains Mono', 'font-family: monospace; font-size:13px; color:#34D399;');
    console.log('%c💰 Prescription (GROSS): TSh <?= number_format($prescription_revenue, 2, '.', ',') ?>', 'font-family: monospace; font-size:12px; color:#7C3AED; font-weight:bold;');
</script>

</body>
</html>