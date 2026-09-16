<?php
// ================================================================
// FILE: frontend/pages/admin/lab_tests.php
// ADMIN - VIEW ALL LAB TESTS
// ✅ Inatumia SHARED HEADER & SIDEBAR pekee
// ✅ Imeondoa top-nav, search, dark mode, branch selector, datetime
// ✅ Imeondoa DOCTYPE, html, head, body
// ✅ CSS za page pekee
// WITH FULL DELETE FUNCTIONALITY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
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
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET FILTERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$patient_search = isset($_GET['patient']) ? trim($_GET['patient']) : '';

// ================================================================
// HANDLE DELETE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lab_test']) && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    $branch_param = isset($_POST['branch']) ? $_POST['branch'] : 'all';
    
    if ($delete_id > 0) {
        try {
            $stmt = $db->prepare("SELECT * FROM lab_tests WHERE id = ?");
            $stmt->execute([$delete_id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$test) {
                header('Location: lab_tests.php?branch=' . urlencode($branch_param) . '&error=notfound');
                exit;
            }
            
            $stmt = $db->prepare("
                SELECT b.id, b.status 
                FROM bill_items bi
                LEFT JOIN bills b ON bi.bill_id = b.id
                WHERE bi.reference_id = ? AND bi.reference_type = 'lab_test' AND b.status = 'paid'
                LIMIT 1
            ");
            $stmt->execute([$delete_id]);
            if ($stmt->fetch()) {
                header('Location: lab_tests.php?branch=' . urlencode($branch_param) . '&error=cannot_delete_paid');
                exit;
            }
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("DELETE FROM bill_items WHERE reference_id = ? AND reference_type = 'lab_test'");
            $stmt->execute([$delete_id]);
            
            $stmt = $db->prepare("DELETE FROM lab_test_equipment WHERE lab_test_id = ?");
            $stmt->execute([$delete_id]);
            
            $stmt = $db->prepare("DELETE FROM lab_tests WHERE id = ?");
            $stmt->execute([$delete_id]);
            
            $visit_id = $test['visit_id'] ?? null;
            if ($visit_id) {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
                $remaining = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                if ($remaining == 0) {
                    $stmt = $db->prepare("UPDATE visits SET status = 'assigned', updated_at = NOW() WHERE id = ? AND status = 'lab_test'");
                    $stmt->execute([$visit_id]);
                }
            }
            
            if ($visit_id) {
                $stmt = $db->prepare("SELECT id FROM bills WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
                $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($bill) {
                    $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
                    $stmt->execute([$bill['id']]);
                    $subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
                    
                    $stmt = $db->prepare("SELECT total_discount FROM bills WHERE id = ?");
                    $stmt->execute([$bill['id']]);
                    $discount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_discount'] ?? 0);
                    
                    $total_amount = max(0, $subtotal - $discount);
                    
                    $stmt = $db->prepare("SELECT SUM(amount) as payment_total FROM payments WHERE bill_id = ?");
                    $stmt->execute([$bill['id']]);
                    $paid_amount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
                    
                    $balance = $total_amount - $paid_amount;
                    
                    if ($total_amount == 0) {
                        $bill_status = 'pending';
                    } elseif ($balance <= 0 && $total_amount > 0) {
                        $bill_status = 'paid';
                    } elseif ($paid_amount > 0 && $balance > 0) {
                        $bill_status = 'partial';
                    } else {
                        $bill_status = 'pending';
                    }
                    
                    $stmt = $db->prepare("UPDATE bills SET subtotal = ?, total_amount = ?, paid_amount = ?, balance = ?, status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$subtotal, $total_amount, $paid_amount, $balance, $bill_status, $bill['id']]);
                }
            }
            
            $db->commit();
            header('Location: lab_tests.php?branch=' . urlencode($branch_param) . '&error=delete_success');
            exit;
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Delete lab test error: " . $e->getMessage());
            header('Location: lab_tests.php?branch=' . urlencode($branch_param) . '&error=database_error');
            exit;
        }
    } else {
        header('Location: lab_tests.php?branch=' . urlencode($branch_param) . '&error=invalid_id');
        exit;
    }
}

// ================================================================
// ERROR MESSAGES
// ================================================================
$error = $_GET['error'] ?? '';
$error_message = '';
$show_error = false;
$error_message_type = 'error';

if ($error === 'invalid_id') {
    $error_message = '⚠️ Invalid test ID provided.';
    $show_error = true;
} elseif ($error === 'notfound') {
    $error_message = '⚠️ Test not found.';
    $show_error = true;
} elseif ($error === 'database_error') {
    $error_message = '⚠️ Database error occurred. Please try again.';
    $show_error = true;
} elseif ($error === 'cannot_delete_paid') {
    $error_message = '❌ Cannot delete: This lab test is already paid.';
    $show_error = true;
} elseif ($error === 'delete_success') {
    $error_message = '✅ Lab test deleted successfully!';
    $show_error = true;
    $error_message_type = 'success';
}

// ================================================================
// BUILD QUERY
// ================================================================
$sql = "
    SELECT 
        lt.*,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        u.full_name as doctor_name,
        u2.full_name as technician_name,
        v.visit_number,
        v.visit_type,
        v.id as visit_id,
        b.name as branch_name
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON lt.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users u2 ON lt.lab_technician_id = u2.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    WHERE 1=1
";
$params = [];

if ($selected_branch_id !== 'all') {
    $sql .= " AND lt.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}
if ($status_filter !== 'all') {
    $sql .= " AND lt.status = ?";
    $params[] = $status_filter;
}
if (!empty($search)) {
    $sql .= " AND (lt.test_name LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}
if (!empty($patient_search)) {
    $sql .= " AND p.full_name LIKE ?";
    $params[] = "%$patient_search%";
}
if (!empty($date_from)) {
    $sql .= " AND DATE(lt.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $sql .= " AND DATE(lt.created_at) <= ?";
    $params[] = $date_to;
}
$sql .= " ORDER BY lt.created_at DESC";

$lab_tests = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests = [];
}

$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// TOTALS
// ================================================================
$total_tests = count($lab_tests);
$pending_tests = 0;
$in_progress_tests = 0;
$completed_tests = 0;
$cancelled_tests = 0;
$total_revenue = 0;

foreach ($lab_tests as $test) {
    $status = $test['status'] ?? 'pending';
    if ($status === 'pending') $pending_tests++;
    elseif ($status === 'in_progress') $in_progress_tests++;
    elseif ($status === 'completed') $completed_tests++;
    elseif ($status === 'cancelled') $cancelled_tests++;
    $total_revenue += floatval($test['test_price'] ?? 0);
}

function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'in_progress' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'pending' => 'fa-clock',
        'in_progress' => 'fa-spinner fa-spin',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - PEKEE -->
<!-- ================================================================ -->
<style>
/* ================================================================
   BLUE THEME
   ================================================================ */
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #EFF6FF;
    --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
}

[data-theme="dark"] {
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3D2E0A;
}

/* ================================================================
   PAGE HEADER - BLUE
   ================================================================ */
.page-header-custom {
    background: var(--primary-gradient);
    border-radius: 18px;
    padding: 24px 32px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
    color: white;
    position: relative;
    overflow: hidden;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 350px; height: 350px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0;
    position: relative;
    z-index: 1;
}

.page-header-custom .page-title i {
    color: rgba(255,255,255,0.9);
}

.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 6px;
    position: relative;
    z-index: 1;
}

.page-header-custom .badge-role {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.page-header-custom .header-badge {
    background: rgba(255,255,255,0.12);
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.68rem;
    font-weight: 500;
    backdrop-filter: blur(4px);
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.1);
}

.page-header-custom .btn-outline-light {
    background: rgba(255,255,255,0.12);
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
    gap: 6px;
    backdrop-filter: blur(4px);
    position: relative;
    z-index: 1;
}

.page-header-custom .btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

/* ================================================================
   ALERTS
   ================================================================ */
.alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: fadeInUp 0.5s ease forwards;
}

.alert-danger {
    background: var(--danger-bg);
    border: 2px solid var(--danger);
    color: var(--danger);
}

.alert-success {
    background: var(--success-bg);
    border: 2px solid var(--success);
    color: var(--success);
}

.alert i { font-size: 1.2rem; }

.alert .alert-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: inherit;
    opacity: 0.6;
    transition: opacity 0.3s;
    padding: 0 4px;
}

.alert .alert-close:hover { opacity: 1; }

/* ================================================================
   STATS CARDS
   ================================================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--primary-gradient);
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(11, 94, 215, 0.25);
    text-decoration: none;
    color: white;
    position: relative;
    overflow: hidden;
    border: none;
}

.stat-card:hover {
    transform: translateY(-4px) scale(1.01);
    box-shadow: 0 8px 32px rgba(11, 94, 215, 0.4);
}

.stat-card .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.1);
}

.stat-card .stat-label {
    font-size: 0.62rem;
    color: rgba(255,255,255,0.85);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0;
}

.stat-card .stat-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: white;
    margin: 0;
    line-height: 1.2;
}

[data-theme="dark"] .stat-card {
    background: linear-gradient(135deg, #1D4ED8, #1E40AF);
}

/* ================================================================
   FILTER BAR
   ================================================================ */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
    align-items: center;
    background: var(--bg-card);
    padding: 16px 20px;
    border-radius: 12px;
    border: 2px solid var(--border-color);
}

.filter-bar select, 
.filter-bar input {
    background: var(--bg-card);
    border: 2px solid var(--border-color);
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 0.8rem;
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s;
    min-width: 150px;
}

.filter-bar select:focus, 
.filter-bar input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
}

/* ================================================================
   BUTTONS
   ================================================================ */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
}

.btn-primary {
    background: var(--primary-gradient);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.btn-outline {
    background: transparent;
    color: var(--text-secondary);
    border: 2px solid var(--border-color);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.btn-danger {
    background: var(--danger);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 24px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-danger:hover {
    background: #B91C1C;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
}

/* ================================================================
   CARD
   ================================================================ */
.card {
    background: var(--bg-card);
    border-radius: 16px;
    border: 2px solid var(--border-color);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    margin-bottom: 24px;
}

.card-header {
    padding: 16px 24px;
    background: var(--bg-card);
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.card-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-title i { color: var(--primary); }

/* ================================================================
   DATA TABLE
   ================================================================ */
.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8rem;
}

.data-table thead th {
    background: var(--primary-gradient);
    color: white;
    font-weight: 600;
    padding: 12px 14px;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: none;
    white-space: nowrap;
    text-align: left;
}

.data-table thead th:first-child { border-radius: 0; }
.data-table thead th:last-child { border-radius: 0; }

.data-table td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}

.data-table tbody tr:hover td {
    background: var(--primary-bg);
}

/* ================================================================
   BADGES
   ================================================================ */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    color: white;
}

.badge-success { background: var(--success); }
.badge-danger { background: var(--danger); }
.badge-warning { background: var(--warning); color: #1E293B; }
.badge-info { background: var(--primary); }
.badge-secondary { background: #64748B; }

/* ================================================================
   ACTION BUTTONS - 3 ROWS
   ================================================================ */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 3px;
    align-items: center;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.62rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    cursor: pointer;
    min-width: 55px;
    width: 100%;
    min-height: 26px;
    white-space: nowrap;
}

.btn-action i { font-size: 0.65rem; }
.btn-action .btn-label { font-size: 0.58rem; }
.btn-action:hover { transform: translateY(-1px) scale(1.02); }
.btn-action:active { transform: scale(0.95); }

/* View - Blue */
.btn-view {
    background: var(--primary-bg);
    color: var(--primary);
    border-color: var(--primary);
}

.btn-view:hover {
    background: var(--primary);
    color: white;
}

/* Edit - Green */
.btn-edit {
    background: var(--success-bg);
    color: var(--success);
    border-color: var(--success);
}

.btn-edit:hover {
    background: var(--success);
    color: white;
}

.btn-edit-disabled {
    background: #F1F5F9;
    color: #94A3B8;
    border-color: #CBD5E1;
    cursor: not-allowed;
    opacity: 0.6;
}

/* Delete - Red */
.btn-delete {
    background: var(--danger-bg);
    color: var(--danger);
    border-color: var(--danger);
}

.btn-delete:hover {
    background: var(--danger);
    color: white;
}

/* ================================================================
   EMPTY STATE
   ================================================================ */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    color: var(--border-color);
    margin-bottom: 16px;
    display: block;
}

.empty-state h3 {
    font-size: 1.1rem;
    color: var(--text-primary);
    margin-bottom: 8px;
}

/* ================================================================
   MODAL
   ================================================================ */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
}

.modal-overlay.show { display: flex; }

.modal-content {
    background: var(--bg-card);
    border-radius: 16px;
    padding: 32px;
    max-width: 450px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    border: 2px solid var(--border-color);
    animation: modalSlideUp 0.3s ease;
}

@keyframes modalSlideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--danger-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 2rem;
    color: var(--danger);
}

.modal-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    text-align: center;
    margin-bottom: 8px;
}

.modal-message {
    font-size: 0.85rem;
    color: var(--text-secondary);
    text-align: center;
    margin-bottom: 4px;
}

.modal-test-name {
    font-size: 0.75rem;
    color: var(--danger);
    text-align: center;
    font-weight: 600;
    margin-top: 8px;
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 20px;
}

/* ================================================================
   TOAST
   ================================================================ */
.toast-custom {
    position: fixed;
    bottom: 24px; right: 24px;
    padding: 14px 20px;
    border-radius: 12px;
    z-index: 99999;
    max-width: 420px;
    transform: translateY(120px);
    opacity: 0;
    transition: all 0.4s ease;
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.toast-custom.show { transform: translateY(0); opacity: 1; }
.toast-custom.success { background: var(--success); }
.toast-custom.error { background: var(--danger); }
.toast-custom.warning { background: var(--warning); }
.toast-custom.info { background: var(--primary); }

.toast-custom .toast-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.6);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0 4px;
}

.toast-custom .toast-close:hover {
    color: white;
}

/* ================================================================
   FOOTER
   ================================================================ */
.footer {
    padding: 14px 0;
    border-top: 2px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand {
    color: var(--primary);
    font-weight: 600;
}

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
@media (max-width: 768px) {
    .page-header-custom { padding: 16px 18px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
    .data-table { font-size: 0.7rem; }
    .data-table th, .data-table td { padding: 6px 8px; }
    .btn-action { padding: 3px 6px; min-height: 24px; min-width: 40px; }
    .btn-action .btn-label { display: none; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .action-buttons { gap: 2px; }
}
</style>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Lab Tests
                <span class="badge-role">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-vial"></i>
                <strong><?= $total_tests ?></strong> total tests
                <span class="header-badge" style="background:rgba(52,211,153,0.2);">
                    <i class="fas fa-check-circle"></i> <?= $completed_tests ?> Completed
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);">
                    <i class="fas fa-spinner fa-spin"></i> <?= $in_progress_tests ?> In Progress
                </span>
                <span class="header-badge" style="background:rgba(220,38,38,0.2);">
                    <i class="fas fa-clock"></i> <?= $pending_tests ?> Pending
                </span>
                <span class="header-badge">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_revenue, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="add_lab_test.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Test
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($show_error && !empty($error_message)): ?>
        <div class="alert <?= $error_message_type === 'success' ? 'alert-success' : 'alert-danger' ?> animate-fade-in-up">
            <i class="fas <?= $error_message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $error_message ?>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid animate-fade-in-up">
        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="stat-card">
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
            <div>
                <p class="stat-label">Total Tests</p>
                <p class="stat-value"><?= number_format($total_tests) ?></p>
            </div>
        </a>
        
        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>&status=completed" class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <p class="stat-label">Completed</p>
                <p class="stat-value"><?= number_format($completed_tests) ?></p>
            </div>
        </a>
        
        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>&status=in_progress" class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <div>
                <p class="stat-label">In Progress</p>
                <p class="stat-value"><?= number_format($in_progress_tests) ?></p>
            </div>
        </a>
        
        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>&status=pending" class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-value"><?= number_format($pending_tests) ?></p>
            </div>
        </a>
        
        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>&status=cancelled" class="stat-card">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div>
                <p class="stat-label">Cancelled</p>
                <p class="stat-value"><?= number_format($cancelled_tests) ?></p>
            </div>
        </a>
        
        <a href="reports.php?branch=<?= urlencode($selected_branch_id) ?>&type=lab" class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-label">Total Revenue</p>
                <p class="stat-value">TSh <?= number_format($total_revenue, 0) ?></p>
            </div>
        </a>
    </div>

    <!-- FILTERS -->
    <div class="filter-bar animate-fade-in-up">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;width:100%;">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            
            <select name="status" onchange="this.form.submit()">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            
            <input type="text" name="search" placeholder="Search by test or patient..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>
        </form>
    </div>

    <!-- TABLE -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                Lab Tests List
                <span style="font-size:0.75rem;color:var(--text-secondary);font-weight:400;">(<?= $total_tests ?> records)</span>
            </h3>
            <a href="add_lab_test.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Test
            </a>
        </div>
        
        <div style="overflow-x:auto;">
            <?php if (count($lab_tests) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Technician</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Created</th>
                            <th style="text-align:center; min-width:80px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): 
                            $is_completed = ($test['status'] ?? '') === 'completed';
                        ?>
                            <tr>
                                <td style="font-weight:500;"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($test['patient_id']) && !empty($test['patient_name'])): ?>
                                        <a href="view_patient.php?id=<?= $test['patient_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" style="color:var(--primary);text-decoration:none;font-weight:500;">
                                            <?= htmlspecialchars($test['patient_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($test['doctor_name'])): ?>
                                        Dr. <?= htmlspecialchars($test['doctor_name']) ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.7rem;">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($test['technician_name'])): ?>
                                        <?= htmlspecialchars($test['technician_name']) ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.7rem;">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getStatusBadge($test['status'] ?? 'pending') ?>">
                                        <i class="fas <?= getStatusIcon($test['status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td style="font-weight:600;">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></td>
                                <td style="font-size:0.72rem;color:var(--text-secondary);">
                                    <?= date('M d, Y', strtotime($test['created_at'] ?? 'now')) ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View -->
                                        <a href="view_lab_result.php?id=<?= $test['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                           class="btn-action btn-view" title="View Test">
                                            <i class="fas fa-eye"></i>
                                            <span class="btn-label">View</span>
                                        </a>
                                        
                                        <!-- Edit -->
                                        <?php if ($is_completed): ?>
                                            <a href="edit_lab_test.php?id=<?= $test['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                               class="btn-action btn-edit" title="Edit Test">
                                                <i class="fas fa-edit"></i>
                                                <span class="btn-label">Edit</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="btn-action btn-edit-disabled" title="Edit only for completed tests">
                                                <i class="fas fa-edit"></i>
                                                <span class="btn-label">Edit</span>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- Delete -->
                                        <button type="button" 
                                                class="btn-action btn-delete" 
                                                title="Delete Test" 
                                                onclick="deleteLabTest(<?= $test['id'] ?>, '<?= htmlspecialchars($test['test_name'] ?? 'Unknown') ?>', '<?= urlencode($selected_branch_id) ?>')">
                                            <i class="fas fa-trash"></i>
                                            <span class="btn-label">Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-flask"></i>
                    <h3>No Lab Tests Found</h3>
                    <p><?= !empty($search) ? 'No results match your search criteria.' : 'No lab tests have been created yet.' ?></p>
                    <?php if (!empty($search) || $status_filter !== 'all'): ?>
                        <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary" style="margin-top:16px;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php else: ?>
                        <a href="add_lab_test.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary" style="margin-top:16px;">
                            <i class="fas fa-plus"></i> Add Lab Test
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            Lab Tests - <?= $total_tests ?> tests
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:var(--border-color);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- DELETE MODAL -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-icon">
            <i class="fas fa-trash"></i>
        </div>
        <h3 class="modal-title">⚠️ Confirm Delete</h3>
        <p class="modal-message" id="deleteModalMessage">Are you sure you want to delete this lab test?</p>
        <p class="modal-test-name" id="deleteModalTestName">Test: Unknown</p>
        
        <form id="deleteForm" method="POST" action="lab_tests.php">
            <input type="hidden" name="delete_id" id="deleteId" value="">
            <input type="hidden" name="branch" id="deleteBranch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()" style="padding:8px 24px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="delete_lab_test" class="btn-danger">
                    <i class="fas fa-trash"></i> Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle toast-icon"></i>
    <div class="toast-content">
        <p class="toast-title" id="toastTitle">Notification</p>
        <p class="toast-message" id="toastMessage"></p>
    </div>
    <button class="toast-close" onclick="closeToast()">&times;</button>
</div>

<!-- ================================================================ -->
<!-- PAGE JAVASCRIPT - PEKEE -->
<!-- ================================================================ -->
<script>
// ================================================================
// DATE/TIME - Footer only (header ina update yake)
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

// ================================================================
// DELETE MODAL
// ================================================================
function openDeleteModal(testId, testName, branch) {
    document.getElementById('deleteId').value = testId;
    document.getElementById('deleteModalTestName').textContent = 'Test: ' + testName;
    document.getElementById('deleteModalMessage').textContent = 'Are you sure you want to delete this lab test?\n\nThis will also remove bill items and equipment linked to this test.';
    document.getElementById('deleteBranch').value = branch || 'all';
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

function deleteLabTest(testId, testName, branch) {
    openDeleteModal(testId, testName, branch);
}

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});

// ================================================================
// TOAST
// ================================================================
function showToast(title, message, type) {
    var toast = document.getElementById('toast');
    var toastTitle = document.getElementById('toastTitle');
    var toastMessage = document.getElementById('toastMessage');
    var toastIcon = toast.querySelector('.toast-icon');
    
    toast.className = 'toast-custom ' + type;
    toastIcon.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') + ' toast-icon';
    toastTitle.textContent = title;
    toastMessage.textContent = message;
    toast.style.display = 'flex';
    
    setTimeout(function() { toast.classList.add('show'); }, 50);
    
    clearTimeout(toast.timeout);
    toast.timeout = setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() { toast.style.display = 'none'; }, 400);
    }, 4000);
}

function closeToast() {
    var toast = document.getElementById('toast');
    toast.classList.remove('show');
    setTimeout(function() { toast.style.display = 'none'; }, 400);
}

// ================================================================
// AUTO-SHOW MESSAGE
// ================================================================
<?php if ($show_error && !empty($error_message)): ?>
setTimeout(function() {
    showToast(
        '<?= $error_message_type === 'success' ? '✅ Success' : '❌ Error' ?>',
        '<?= addslashes($error_message) ?>',
        '<?= $error_message_type === 'success' ? 'success' : 'error' ?>'
    );
}, 300);
<?php endif; ?>

console.log('%c🧪 Braick - Lab Tests (Shared Header/Sidebar)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Inatumia SHARED HEADER & SIDEBAR', 'font-size:12px;color:#34D399;');
console.log('%c✅ Blue theme applied', 'font-size:12px;color:#34D399;');
console.log('%c📊 Total: <?= $total_tests ?> | Completed: <?= $completed_tests ?> | Pending: <?= $pending_tests ?>', 'font-size:12px;color:#64748B;');
</script>

</body>
</html>