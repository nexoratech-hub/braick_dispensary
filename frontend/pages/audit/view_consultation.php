<?php
// ================================================================
// FILE: frontend/pages/audit/view_consultation.php
// AUDIT - VIEW CONSULTATION DETAILS
// ✅ View consultation details
// ✅ Delete button - inafuta consultation + bill_item + update bill
// ✅ BLUE THEME (Audit)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ✅ AUDIT ROLE ONLY
if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($consultation_id <= 0) {
    header('Location: other_services.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// FETCH CONSULTATION DETAILS
// ================================================================
$sql = "
    SELECT 
        c.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        pat.address as patient_address,
        doc.full_name as doctor_name,
        doc.role as doctor_role,
        b.name as branch_name,
        v.visit_number,
        v.visit_date,
        v.created_at as visit_created_at,
        v.status as visit_status,
        v.id as visit_id
    FROM consultations c
    LEFT JOIN patients pat ON c.patient_id = pat.id
    LEFT JOIN users doc ON c.doctor_id = doc.id
    LEFT JOIN branches b ON c.branch_id = b.id
    LEFT JOIN visits v ON c.visit_id = v.id
    WHERE c.id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([$consultation_id]);
$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {
    header('Location: other_services.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// FIND RELATED BILL & BILL ITEM
// ================================================================
$bill_item = null;
$bill = null;
$is_paid = false;

$bill_sql = "
    SELECT 
        bi.*,
        b.id as bill_id,
        b.bill_number,
        b.total_amount,
        b.paid_amount,
        b.balance,
        b.payment_status,
        b.status as bill_status,
        b.created_at as bill_created_at,
        b.updated_at as bill_updated_at,
        b.visit_id as bill_visit_id
    FROM bill_items bi
    INNER JOIN bills b ON bi.bill_id = b.id
    WHERE bi.item_type = 'consultation'
    AND (
        bi.reference_id = ?
        OR bi.reference_type = 'consultation'
    )
    AND bi.bill_id IN (
        SELECT id FROM bills WHERE visit_id = ? OR patient_id = ?
    )
    ORDER BY bi.id DESC
    LIMIT 1
";

$stmt = $db->prepare($bill_sql);
$stmt->execute([
    $consultation_id,
    $consultation['visit_id'] ?? 0,
    $consultation['patient_id'] ?? 0
]);
$bill_item = $stmt->fetch(PDO::FETCH_ASSOC);

if ($bill_item) {
    $bill = [
        'id' => $bill_item['bill_id'],
        'bill_number' => $bill_item['bill_number'],
        'total_amount' => $bill_item['total_amount'],
        'paid_amount' => $bill_item['paid_amount'],
        'balance' => $bill_item['balance'],
        'payment_status' => $bill_item['payment_status'],
        'status' => $bill_item['bill_status'],
        'created_at' => $bill_item['bill_created_at'],
        'updated_at' => $bill_item['bill_updated_at']
    ];
    
    if ($bill_item['total_price'] > 0 && ($bill['paid_amount'] > 0 || $bill['payment_status'] === 'paid' || $bill['payment_status'] === 'partial')) {
        $is_paid = true;
    }
}

// ================================================================
// HANDLE DELETE
// ================================================================
$delete_message = '';
$delete_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $db->beginTransaction();
        
        $deleted_info = [];
        
        // ✅ Futa bill_item na update bill kama ipo
        if ($bill_item) {
            // 1. Futa bill_item
            $stmt = $db->prepare("DELETE FROM bill_items WHERE id = ?");
            $stmt->execute([$bill_item['id']]);
            $deleted_info[] = "Bill item deleted";
            
            // 2. Recalculate bill totals
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(total_price), 0) as new_total
                FROM bill_items 
                WHERE bill_id = ?
            ");
            $stmt->execute([$bill['id']]);
            $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['new_total'];
            
            // 3. Update bill
            $new_balance = $new_total - $bill['paid_amount'];
            $new_status = 'pending';
            if ($bill['paid_amount'] >= $new_total && $new_total > 0) {
                $new_status = 'paid';
            } elseif ($bill['paid_amount'] > 0) {
                $new_status = 'partial';
            }
            
            // Kama bill haina items tena, futa bill
            if ($new_total <= 0) {
                $stmt = $db->prepare("DELETE FROM bills WHERE id = ?");
                $stmt->execute([$bill['id']]);
                $deleted_info[] = "Empty bill deleted";
            } else {
                $stmt = $db->prepare("
                    UPDATE bills 
                    SET total_amount = ?, 
                        balance = ?, 
                        payment_status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_total, $new_balance, $new_status, $bill['id']]);
                $deleted_info[] = "Bill updated: Total=" . number_format($new_total, 0) . ", Balance=" . number_format($new_balance, 0);
            }
        }
        
        // ✅ Futa consultation
        $stmt = $db->prepare("DELETE FROM consultations WHERE id = ?");
        $stmt->execute([$consultation_id]);
        $deleted_info[] = "Consultation deleted";
        
        // ✅ Log activity
        try {
            $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, ip_address, created_at) 
                          VALUES (?, ?, 'delete_consultation', ?, ?, NOW())")
               ->execute([
                   $user_id, 
                   $selected_branch_id !== 'all' ? (int)$selected_branch_id : $user_branch_id,
                   "Deleted consultation #{$consultation_id} - Patient: " . ($consultation['patient_name'] ?? 'N/A') . " | " . implode(" | ", $deleted_info),
                   $_SERVER['REMOTE_ADDR'] ?? 'unknown'
               ]);
        } catch (Exception $e) {}
        
        $db->commit();
        
        $delete_success = true;
        $delete_message = "✅ Consultation deleted successfully! " . implode(" | ", $deleted_info);
        
        header("refresh:2;url=other_services.php?branch=" . $selected_branch_id);
        
    } catch (Exception $e) {
        $db->rollBack();
        $delete_message = "❌ Error deleting consultation: " . $e->getMessage();
    }
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

function getStatusBadge($status) {
    $map = [
        'pending' => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Pending'],
        'in_progress' => ['class' => 'info', 'icon' => '🔄', 'label' => 'In Progress'],
        'completed' => ['class' => 'success', 'icon' => '✅', 'label' => 'Completed'],
        'cancelled' => ['class' => 'danger', 'icon' => '❌', 'label' => 'Cancelled'],
        'paid' => ['class' => 'success', 'icon' => '✅', 'label' => 'Paid'],
        'partial' => ['class' => 'info', 'icon' => '🔄', 'label' => 'Partial'],
        'active' => ['class' => 'success', 'icon' => '✅', 'label' => 'Active']
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status)];
}

$status_info = getStatusBadge($consultation['status'] ?? 'completed');
$age = calculateAge($consultation['date_of_birth']);

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Details - Audit</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-darker: #083C8A;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --cyan: #0891B2;
    --cyan-bg: #CFFAFE;
    --gray-50: #F8FAFC;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 40px rgba(0,0,0,0.12);
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --gray-50: #1A1A2E;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}

* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; box-sizing: border-box; }
body { background: var(--bg-body); color: var(--text-primary); margin: 0; padding: 0; }

.main-content { margin-left: 270px; margin-top: 68px; padding: 24px 28px; min-height: calc(100vh - 68px); }

@media (max-width: 1024px) {
    .main-content { margin-left: 0; padding: 16px; }
}

/* PAGE HEADER */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
    border-radius: 16px;
    padding: 20px 28px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
    position: relative;
    overflow: hidden;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.4rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
}

.page-header-custom .page-title i { font-size: 1.6rem; opacity: 0.9; }
.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.82rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 4px;
}

.btn-header {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 14px;
    border-radius: 9px;
    font-weight: 600;
    font-size: 0.75rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    position: relative;
    z-index: 1;
}

.btn-header:hover {
    background: rgba(255,255,255,0.28);
    transform: translateY(-2px);
    color: white;
}

/* ALERTS */
.alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.4s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success { background: var(--success-bg); color: var(--success); border: 2px solid var(--success); }
.alert-danger { background: var(--danger-bg); color: var(--danger); border: 2px solid var(--danger); }

/* DELETE WARNING */
.delete-warning {
    background: var(--danger-bg);
    border: 2px solid var(--danger);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 20px;
    color: var(--danger);
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.delete-warning i {
    font-size: 1.4rem;
    margin-top: 2px;
}

.delete-warning ul {
    margin: 8px 0 0;
    padding-left: 20px;
    font-weight: 500;
}

/* DETAIL CARD */
.detail-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.detail-card .card-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    padding: 14px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.detail-card .card-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-card .card-body { padding: 20px; }

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-item .info-label {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.info-item .info-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    font-family: var(--font-mono);
}

.info-item .info-value.highlight {
    color: var(--primary);
    font-size: 1rem;
    font-weight: 800;
}

/* STATUS BADGE */
.status-badge {
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.status-badge.warning { background: #FEF3C7; color: #D97706; }
.status-badge.success { background: #D1FAE5; color: #059669; }
.status-badge.danger { background: #FEE2E2; color: #EF4444; }
.status-badge.info { background: #E8F0FE; color: #0B5ED7; }
.status-badge.purple { background: #EDE9FE; color: #7C3AED; }

/* BILL INFO BOX */
.bill-info {
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    border: 2px solid var(--primary-light);
    border-radius: 12px;
    padding: 16px;
    margin-top: 16px;
}

[data-theme="dark"] .bill-info {
    background: linear-gradient(135deg, #1E3A5F, #16294A);
    border-color: #334155;
}

.bill-info .bill-title {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.bill-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}

.bill-grid .bill-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.bill-grid .bill-label {
    font-size: 0.6rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
}

.bill-grid .bill-value {
    font-size: 0.85rem;
    font-weight: 700;
    font-family: var(--font-mono);
    color: var(--text-primary);
}

.bill-grid .bill-value.amount { color: var(--primary); }
.bill-grid .bill-value.paid { color: var(--success); }
.bill-grid .bill-value.balance { color: var(--danger); }

/* RESULT BOX */
.result-box {
    background: var(--gray-50);
    border: 2px dashed var(--border-color);
    border-radius: 10px;
    padding: 16px;
    margin-top: 10px;
    font-family: var(--font-mono);
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.6;
}

[data-theme="dark"] .result-box {
    background: #1A1A2E;
}

/* ACTION BUTTONS */
.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px dashed var(--border-color);
}

.btn {
    padding: 10px 22px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    border: none;
    transition: all 0.3s ease;
}

.btn-secondary {
    background: var(--bg-body);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-bg);
    transform: translateY(-2px);
}

.btn-delete {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    box-shadow: 0 3px 10px rgba(220, 38, 38, 0.3);
}

.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
}

/* MODAL */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(4px);
}

.modal-overlay.show { display: flex; }

.modal-box {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 28px;
    max-width: 480px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalIn 0.3s ease;
    text-align: center;
}

@keyframes modalIn {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.modal-box .modal-icon {
    width: 68px; height: 68px;
    border-radius: 50%;
    background: var(--danger-bg);
    color: var(--danger);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 16px;
}

.modal-box h3 {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 8px;
}

.modal-box p {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0 0 20px;
    line-height: 1.5;
}

.modal-box .modal-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.modal-box .modal-actions button {
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.modal-box .btn-cancel {
    background: var(--bg-body);
    color: var(--text-primary);
    border: 2px solid var(--border-color);
}

.modal-box .btn-confirm-delete {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
}

@media (max-width: 768px) {
    .page-header-custom { padding: 16px 20px; }
    .page-header-custom .page-title { font-size: 1.15rem; }
    .info-grid { grid-template-columns: 1fr; }
}
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-stethoscope"></i>
                Consultation Details
                <span style="background:rgba(255,255,255,0.2);padding:3px 12px;border-radius:20px;font-size:0.6rem;font-weight:600;text-transform:uppercase;">AUDIT VIEW</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hashtag"></i> ID: <?= $consultation['id'] ?> • 
                <i class="fas fa-calendar"></i> <?= date('d M Y, H:i', strtotime($consultation['created_at'] ?? $consultation['visit_date'])) ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="other_services.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- DELETE MESSAGE -->
    <?php if (!empty($delete_message)): ?>
        <div class="alert <?= $delete_success ? 'alert-success' : 'alert-danger' ?>">
            <i class="fas <?= $delete_success ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($delete_message) ?>
        </div>
    <?php endif; ?>

    <!-- WARNING KAMA INA BILL -->
    <?php if ($bill_item && !$delete_success): ?>
        <div class="delete-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>⚠️ This consultation is linked to a Bill!</strong><br>
                Deleting this consultation will also:
                <ul>
                    <li>Delete the related bill item (<?= htmlspecialchars($bill_item['item_name'] ?? 'Consultation') ?>)</li>
                    <li>Recalculate Bill #<?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?> totals</li>
                    <li>Update bill balance & payment status</li>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- CONSULTATION INFO -->
    <div class="detail-card">
        <div class="card-header">
            <h3><i class="fas fa-stethoscope"></i> Consultation Information</h3>
            <span class="status-badge <?= $status_info['class'] ?>" style="background:rgba(255,255,255,0.2);color:white;">
                <?= $status_info['icon'] ?> <?= $status_info['label'] ?>
            </span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-stethoscope"></i> Consultation Type</span>
                    <span class="info-value highlight"><?= htmlspecialchars($consultation['consultation_type'] ?? 'General') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-money-bill-wave"></i> Fee</span>
                    <span class="info-value highlight">TSh <?= number_format($consultation['consultation_fee'] ?? 0, 0) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-calendar"></i> Consultation Date</span>
                    <span class="info-value">
                        <?= !empty($consultation['consultation_date']) ? date('d M Y', strtotime($consultation['consultation_date'])) : 'N/A' ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-clock"></i> Time</span>
                    <span class="info-value">
                        <?= !empty($consultation['consultation_time']) ? date('H:i', strtotime($consultation['consultation_time'])) : 'N/A' ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-store-alt"></i> Branch</span>
                    <span class="info-value"><?= htmlspecialchars($consultation['branch_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-user-md"></i> Doctor</span>
                    <span class="info-value">Dr. <?= htmlspecialchars($consultation['doctor_name'] ?? 'N/A') ?></span>
                </div>
            </div>

            <!-- CHIEF COMPLAINT -->
            <?php if (!empty($consultation['chief_complaint'])): ?>
            <div style="margin-top:20px;">
                <span class="info-label" style="font-size:0.65rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;">
                    <i class="fas fa-comment-medical"></i> Chief Complaint
                </span>
                <div class="result-box">
                    <?= nl2br(htmlspecialchars($consultation['chief_complaint'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- DIAGNOSIS -->
            <?php if (!empty($consultation['diagnosis'])): ?>
            <div style="margin-top:16px;">
                <span class="info-label" style="font-size:0.65rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;">
                    <i class="fas fa-notes-medical"></i> Diagnosis
                </span>
                <div class="result-box" style="border-color:var(--success);">
                    <?= nl2br(htmlspecialchars($consultation['diagnosis'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- TREATMENT -->
            <?php if (!empty($consultation['treatment'])): ?>
            <div style="margin-top:16px;">
                <span class="info-label" style="font-size:0.65rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;">
                    <i class="fas fa-prescription-bottle-medical"></i> Treatment
                </span>
                <div class="result-box" style="border-color:var(--primary);">
                    <?= nl2br(htmlspecialchars($consultation['treatment'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- NOTES -->
            <?php if (!empty($consultation['notes'])): ?>
            <div style="margin-top:16px;">
                <span class="info-label" style="font-size:0.65rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;">
                    <i class="fas fa-sticky-note"></i> Notes
                </span>
                <div class="result-box">
                    <?= nl2br(htmlspecialchars($consultation['notes'])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PATIENT & VISIT INFO -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        
        <!-- PATIENT INFO -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Patient Information</h3>
            </div>
            <div class="card-body">
                <div class="info-grid" style="grid-template-columns:1fr;">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value highlight"><?= htmlspecialchars($consultation['patient_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Patient ID</span>
                        <span class="info-value"><?= htmlspecialchars($consultation['patient_number'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?= htmlspecialchars($consultation['patient_phone'] ?? '—') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Gender / Age</span>
                        <span class="info-value">
                            <?= htmlspecialchars($consultation['patient_gender'] ?? '—') ?> • <?= $age ?> yrs
                        </span>
                    </div>
                    <?php if (!empty($consultation['patient_address'])): ?>
                    <div class="info-item">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?= htmlspecialchars($consultation['patient_address']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- VISIT INFO -->
        <div class="detail-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-check"></i> Visit Information</h3>
            </div>
            <div class="card-body">
                <div class="info-grid" style="grid-template-columns:1fr;">
                    <div class="info-item">
                        <span class="info-label">Visit Number</span>
                        <span class="info-value highlight"><?= htmlspecialchars($consultation['visit_number'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Visit Date</span>
                        <span class="info-value">
                            <?= !empty($consultation['visit_date']) ? date('d M Y', strtotime($consultation['visit_date'])) : 'N/A' ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Doctor</span>
                        <span class="info-value">Dr. <?= htmlspecialchars($consultation['doctor_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Visit Status</span>
                        <span class="info-value"><?= htmlspecialchars(ucfirst($consultation['visit_status'] ?? 'N/A')) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BILL INFO -->
    <?php if ($bill_item): ?>
    <div class="detail-card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice"></i> Related Bill Information</h3>
            <span class="status-badge <?= $is_paid ? 'success' : 'warning' ?>" style="background:rgba(255,255,255,0.2);color:white;">
                <?= $is_paid ? '✅ PAID' : '⏳ ' . strtoupper($bill['payment_status'] ?? 'PENDING') ?>
            </span>
        </div>
        <div class="card-body">
            <div class="bill-info">
                <div class="bill-title">
                    <i class="fas fa-receipt"></i>
                    Bill #<?= htmlspecialchars($bill['bill_number']) ?>
                </div>
                <div class="bill-grid">
                    <div class="bill-item">
                        <span class="bill-label">Bill Amount</span>
                        <span class="bill-value amount">TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></span>
                    </div>
                    <div class="bill-item">
                        <span class="bill-label">Paid Amount</span>
                        <span class="bill-value paid">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></span>
                    </div>
                    <div class="bill-item">
                        <span class="bill-label">Balance</span>
                        <span class="bill-value balance">TSh <?= number_format($bill['balance'] ?? 0, 0) ?></span>
                    </div>
                    <div class="bill-item">
                        <span class="bill-label">Item Name</span>
                        <span class="bill-value" style="font-size:0.75rem;"><?= htmlspecialchars($bill_item['item_name'] ?? 'Consultation') ?></span>
                    </div>
                    <div class="bill-item">
                        <span class="bill-label">Item Price</span>
                        <span class="bill-value amount">TSh <?= number_format($bill_item['total_price'] ?? 0, 0) ?></span>
                    </div>
                    <div class="bill-item">
                        <span class="bill-label">Bill Date</span>
                        <span class="bill-value" style="font-size:0.75rem;"><?= date('d M Y H:i', strtotime($bill['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACTIONS -->
    <?php if (!$delete_success): ?>
    <div class="action-buttons">
        <a href="other_services.php?branch=<?= $selected_branch_id ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Services
        </a>
        <button type="button" class="btn btn-delete" onclick="openDeleteModal()">
            <i class="fas fa-trash"></i> Delete Consultation
        </button>
    </div>
    <?php endif; ?>

</main>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Delete Consultation?</h3>
        <p>
            Are you sure you want to delete consultation for<br>
            <strong style="color:var(--primary);font-family:var(--font-mono);">
                <?= htmlspecialchars($consultation['patient_name'] ?? 'N/A') ?>
            </strong><br>
            <?php if ($bill_item): ?>
                <span style="color:var(--danger);font-weight:700;font-size:0.8rem;display:block;margin-top:8px;">
                    <i class="fas fa-exclamation-circle"></i> This will also delete the bill item and update Bill #<?= htmlspecialchars($bill['bill_number']) ?>
                </span>
            <?php endif; ?>
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-confirm-delete">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteModal() {
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});

console.log('%c🔍 Audit - View Consultation', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Consultation ID: <?= $consultation_id ?>', 'font-size:12px;color:#10B981;');
console.log('%c✅ Patient: <?= htmlspecialchars($consultation['patient_name'] ?? 'N/A') ?>', 'font-size:12px;color:#3B82F6;');
<?php if ($bill_item): ?>
console.log('%c✅ Linked to Bill: <?= htmlspecialchars($bill['bill_number']) ?>', 'font-size:12px;color:#F59E0B;');
<?php endif; ?>
</script>

</body>
</html>