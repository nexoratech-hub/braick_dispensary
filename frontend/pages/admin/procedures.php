<?php
// ================================================================
// FILE: frontend/pages/admin/procedures.php
// ADMIN - VIEW ALL PROCEDURES AND BILL ITEMS
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ✅ Uses procedures_catalog, bills, bill_items, patients
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
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

// ================================================================
// GET ADMIN DATA
// ================================================================
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
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// FILTER PARAMETERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches = []; }

$branch_name = 'All Branches';
if ($selected_branch_id > 0) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) { $branch_name = $b['name']; break; }
    }
}

// ================================================================
// HELPERS
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}
function getStatusBadgeClass($status) {
    $status = strtolower($status);
    if (in_array($status, ['active', 'completed', 'paid'])) return 'success';
    if (in_array($status, ['inactive', 'cancelled'])) return 'danger';
    if (in_array($status, ['pending', 'in_progress'])) return 'warning';
    return 'info';
}
function getStatusLabel($status) {
    $status = strtolower($status);
    $labels = [
        'active' => '✅ Active', 'inactive' => '❌ Inactive',
        'pending' => '⏳ Pending', 'in_progress' => '🔄 In Progress',
        'completed' => '✅ Completed', 'cancelled' => '❌ Cancelled',
        'paid' => '✅ Paid', 'partial' => '⏳ Partial'
    ];
    return $labels[$status] ?? ucfirst($status);
}
function formatDateOnly($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
}

// ================================================================
// HANDLE ADD PROCEDURE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_procedure') {
    $procedure_name = trim($_POST['procedure_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = (float)str_replace(',', '', $_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $branch_id = (int)($_POST['branch_id'] ?? $selected_branch_id);
    
    $errors = [];
    if (empty($procedure_name)) $errors[] = 'Procedure name is required';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    if ($branch_id <= 0) $errors[] = 'Branch is required';
    
    if (empty($errors)) {
        try {
            $code = 'PROC-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("
                INSERT INTO procedures_catalog (procedure_name, procedure_code, category, branch_id, price, description, is_active, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$procedure_name, $code, $category, $branch_id, $price, $description, $is_active, $user_id]);
            header('Location: procedures.php?branch=' . $branch_id . '&msg=add_success');
            exit;
        } catch (Exception $e) {
            header('Location: procedures.php?branch=' . $branch_id . '&msg=add_error');
            exit;
        }
    } else {
        header('Location: procedures.php?branch=' . $branch_id . '&msg=add_validation_error');
        exit;
    }
}

// ================================================================
// HANDLE UPDATE PROCEDURE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_procedure') {
    $procedure_id = (int)($_POST['procedure_id'] ?? 0);
    $procedure_name = trim($_POST['procedure_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = (float)str_replace(',', '', $_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $branch_id = (int)($_POST['branch_id'] ?? $selected_branch_id);
    
    $errors = [];
    if (empty($procedure_name)) $errors[] = 'Procedure name is required';
    if ($price <= 0) $errors[] = 'Price must be greater than 0';
    
    if (empty($errors) && $procedure_id > 0) {
        try {
            $stmt = $db->prepare("
                UPDATE procedures_catalog 
                SET procedure_name = ?, category = ?, price = ?, description = ?, is_active = ?, updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([$procedure_name, $category, $price, $description, $is_active, $procedure_id, $branch_id]);
            header('Location: procedures.php?branch=' . $branch_id . '&msg=update_success');
            exit;
        } catch (Exception $e) {
            header('Location: procedures.php?branch=' . $branch_id . '&msg=update_error');
            exit;
        }
    } else {
        header('Location: procedures.php?branch=' . $branch_id . '&msg=update_validation_error');
        exit;
    }
}

// ================================================================
// HANDLE DELETE
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $procedure_id = (int)$_GET['delete'];
    $branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
    try {
        $stmt = $db->prepare("DELETE FROM procedures_catalog WHERE id = ?");
        $stmt->execute([$procedure_id]);
        header('Location: procedures.php?branch=' . $branch_id . ($stmt->rowCount() > 0 ? '&msg=delete_success' : '&msg=delete_error'));
        exit;
    } catch (Exception $e) {
        header('Location: procedures.php?branch=' . $branch_id . '&msg=delete_error');
        exit;
    }
}

// ================================================================
// HANDLE TOGGLE STATUS
// ================================================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $procedure_id = (int)$_GET['toggle'];
    $branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
    try {
        $stmt = $db->prepare("SELECT is_active FROM procedures_catalog WHERE id = ?");
        $stmt->execute([$procedure_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($current) {
            $new_status = $current['is_active'] == 1 ? 0 : 1;
            $stmt = $db->prepare("UPDATE procedures_catalog SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $procedure_id]);
            header('Location: procedures.php?branch=' . $branch_id . '&msg=toggle_success');
            exit;
        }
        header('Location: procedures.php?branch=' . $branch_id . '&msg=toggle_error');
        exit;
    } catch (Exception $e) {
        header('Location: procedures.php?branch=' . $branch_id . '&msg=toggle_error');
        exit;
    }
}

// ================================================================
// REDIRECT MESSAGES
// ================================================================
$redirect_msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$message = '';
$message_type = '';
$msg_map = [
    'add_success' => ['✅ Procedure added successfully!', 'success'],
    'add_error' => ['❌ Error adding procedure!', 'error'],
    'add_validation_error' => ['❌ Please fill in all required fields!', 'error'],
    'update_success' => ['✅ Procedure updated successfully!', 'success'],
    'update_error' => ['❌ Error updating procedure!', 'error'],
    'update_validation_error' => ['❌ Please fill in all required fields!', 'error'],
    'delete_success' => ['✅ Procedure deleted successfully!', 'success'],
    'delete_error' => ['❌ Error deleting procedure!', 'error'],
    'toggle_success' => ['✅ Procedure status updated!', 'success'],
    'toggle_error' => ['❌ Error updating status!', 'error']
];
if (isset($msg_map[$redirect_msg])) {
    list($message, $message_type) = $msg_map[$redirect_msg];
}

// ================================================================
// BUILD QUERY FOR PROCEDURES
// ================================================================
$conditions = ["1=1"];
$params = [];

if ($selected_branch_id > 0) { $conditions[] = "p.branch_id = ?"; $params[] = $selected_branch_id; }
if (!empty($filter_category)) { $conditions[] = "p.category = ?"; $params[] = $filter_category; }
if (!empty($search)) {
    $conditions[] = "(p.procedure_name LIKE ? OR p.category LIKE ? OR p.procedure_code LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$where_clause = implode(" AND ", $conditions);

$sql = "SELECT p.*, b.name as branch_name FROM procedures_catalog p LEFT JOIN branches b ON p.branch_id = b.id WHERE $where_clause ORDER BY p.procedure_name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILL ITEMS FOR PROCEDURES
// ================================================================
$bill_items = [];
$bill_conditions = ["bi.item_type = 'procedure'"];
$bill_params = [];
if ($selected_branch_id > 0) { $bill_conditions[] = "b.branch_id = ?"; $bill_params[] = $selected_branch_id; }
$bill_where = implode(" AND ", $bill_conditions);

$bill_sql = "
    SELECT bi.*, b.bill_number, b.status as bill_status, b.branch_id as bill_branch_id,
           p.full_name as patient_name, p.patient_id as patient_number, p.phone as patient_phone,
           b.total_amount as bill_total, b.paid_amount as bill_paid
    FROM bill_items bi
    LEFT JOIN bills b ON bi.bill_id = b.id
    LEFT JOIN patients p ON bi.patient_id = p.id
    WHERE $bill_where
    ORDER BY bi.created_at DESC
    LIMIT 50
";
$stmt = $db->prepare($bill_sql);
$stmt->execute($bill_params);
$bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_bill_items = count($bill_items);
$total_bill_amount = 0;
foreach ($bill_items as $item) $total_bill_amount += (float)$item['total_price'];

// ================================================================
// GET CATEGORIES FOR FILTER
// ================================================================
$categories = [];
try {
    $stmt = $db->prepare("SELECT DISTINCT category FROM procedures_catalog WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $categories = []; }

// ================================================================
// SUMMARY
// ================================================================
$total_procedures = count($procedures);
$total_active = 0;
$total_inactive = 0;
$total_amount = 0;
foreach ($procedures as $proc) {
    if ($proc['is_active'] == 1) $total_active++; else $total_inactive++;
    $total_amount += (float)$proc['price'];
}

// ================================================================
// GET PROCEDURE FOR EDIT
// ================================================================
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_procedure = null;
if ($edit_id > 0) {
    $stmt = $db->prepare("SELECT * FROM procedures_catalog WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_procedure = $stmt->fetch(PDO::FETCH_ASSOC);
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*)
     ================================================================ -->
<style>
    /* PAGE HEADER */
    .page-header-proc {
        background: linear-gradient(135deg, #059669, #047857);
        border-radius: 18px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 20px rgba(4, 120, 87, 0.25);
        position: relative;
        overflow: hidden;
    }

    .page-header-proc::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-proc .page-title-proc {
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

    .page-header-proc .page-subtitle-proc {
        color: rgba(255,255,255,0.85);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-proc .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.55rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .page-header-proc .header-badge-proc {
        background: rgba(255,255,255,0.12);
        color: white;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 500;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid rgba(255,255,255,0.08);
    }

    .page-header-proc .btn-outline-light-proc {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.12);
        padding: 8px 16px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
        cursor: pointer;
    }

    .page-header-proc .btn-outline-light-proc:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* STATS CARDS */
    .stats-row-proc {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .stat-card-proc {
        border-radius: 18px;
        padding: 14px 18px;
        border: none;
        transition: all 0.3s ease;
        color: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        text-decoration: none;
        display: block;
    }

    .stat-card-proc:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    }

    .stat-card-proc .stat-number-proc { font-size: 1.3rem; font-weight: 700; line-height: 1.2; }
    .stat-card-proc .stat-label-proc {
        font-size: 0.6rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        opacity: 0.85;
        margin-top: 2px;
    }
    .stat-card-proc .stat-icon-proc { font-size: 0.9rem; opacity: 0.8; }

    .stat-card-proc.total { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card-proc.active { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card-proc.inactive { background: linear-gradient(135deg, #DC2626, #991B1B); }
    .stat-card-proc.bills { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card-proc.amount { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }

    /* FILTER SECTION */
    .filter-section-proc {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 14px 18px;
        border: 1px solid var(--page-border, #E2E8F0);
        margin-bottom: 20px;
    }

    .filter-row-proc {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }

    .filter-input-proc {
        padding: 6px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        font-size: 0.78rem;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        outline: none;
        transition: all 0.3s;
        font-family: inherit;
    }

    .filter-input-proc:focus {
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    .btn-search-proc {
        padding: 6px 14px;
        background: #059669;
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.72rem;
        cursor: pointer;
        transition: all 0.3s;
        font-family: inherit;
    }

    .btn-search-proc:hover { background: #047857; transform: translateY(-1px); }

    .btn-add-proc {
        background: #059669;
        color: white;
        padding: 8px 18px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.78rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: inherit;
    }

    .btn-add-proc:hover {
        background: #047857;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .btn-reset-proc {
        padding: 5px 12px;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 600;
        border: 2px solid var(--page-border, #E2E8F0);
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-reset-proc:hover { border-color: #DC2626; color: #DC2626; }

    .filter-divider-proc {
        width: 1px;
        height: 28px;
        background: var(--page-border, #E2E8F0);
        margin: 0 4px;
    }

    /* TABLE */
    .table-container-proc {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        border: 1px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }

    .section-title-proc {
        padding: 10px 14px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
    }

    .section-title-proc i { color: #059669; }

    .table-scroll-proc { overflow-x: auto; }

    .data-table-proc {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }

    .data-table-proc thead th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #ffffff;
        background: #059669;
        border-bottom: 3px solid #047857;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .data-table-proc thead th i { margin-right: 4px; opacity: 0.7; }

    .data-table-proc tbody td {
        padding: 6px 12px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    .data-table-proc tbody tr:hover td { background: #ECFDF5; }
    [data-theme="dark"] .data-table-proc tbody tr:hover td { background: #1A3A2A; }

    .data-table-proc tbody tr:last-child td { border-bottom: none; }
    .data-table-proc tbody tr:nth-child(even) td { background: var(--page-hover, #F8FAFC); }
    [data-theme="dark"] .data-table-proc tbody tr:nth-child(even) td { background: #0F172A; }

    .total-row-proc {
        font-weight: 700;
        border-top: 3px solid #059669;
        background: #D1FAE5 !important;
    }
    [data-theme="dark"] .total-row-proc { background: #1A3A2A !important; }

    /* BADGES */
    .badge-status-proc {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 16px;
        font-size: 0.55rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .badge-success-proc { background: #D1FAE5; color: #059669; border: 1px solid #059669; }
    .badge-danger-proc { background: #FEE2E2; color: #DC2626; border: 1px solid #DC2626; }
    .badge-warning-proc { background: #FEF3C7; color: #D97706; border: 1px solid #D97706; }
    .badge-info-proc { background: #DBEAFE; color: #3B82F6; border: 1px solid #3B82F6; }

    [data-theme="dark"] .badge-success-proc { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .badge-danger-proc { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .badge-warning-proc { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .badge-info-proc { background: #1E3A5F; color: #6EA8FE; }

    /* ACTION BUTTONS */
    .action-buttons-proc {
        display: flex;
        flex-wrap: wrap;
        gap: 3px;
        align-items: center;
        justify-content: center;
    }

    .btn-edit-proc, .btn-delete-proc, .btn-toggle-proc {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 3px 10px;
        border-radius: 5px;
        font-weight: 600;
        font-size: 0.55rem;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }

    .btn-edit-proc { background: #3B82F6; color: white; }
    .btn-edit-proc:hover { background: #2563EB; transform: translateY(-1px); color: white; }

    .btn-delete-proc { background: #DC2626; color: white; }
    .btn-delete-proc:hover { background: #B91C1C; transform: translateY(-1px); color: white; }

    .btn-toggle-proc { background: #64748B; color: white; }
    .btn-toggle-proc:hover { background: #475569; transform: translateY(-1px); color: white; }
    .btn-toggle-proc.active { background: #059669; }
    .btn-toggle-proc.inactive { background: #DC2626; }

    /* TABLE FOOTER */
    .table-footer-proc {
        padding: 8px 14px;
        border-top: 1px solid var(--page-border, #E2E8F0);
        font-size: 0.65rem;
        color: var(--page-text-secondary, #64748B);
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
        background: var(--page-hover, #F8FAFC);
    }

    [data-theme="dark"] .table-footer-proc { background: #0F172A; }

    .count-badge-proc {
        background: #059669;
        color: white;
        padding: 1px 10px;
        border-radius: 16px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    /* EMPTY STATE */
    .empty-state-proc {
        text-align: center;
        padding: 40px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-proc i {
        font-size: 3rem;
        color: var(--page-text-muted, #94A3B8);
        display: block;
        margin-bottom: 12px;
    }

    /* MODAL */
    .modal-overlay-proc {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(4px);
    }

    .modal-overlay-proc.show { display: flex; }

    .modal-content-proc {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 24px 28px;
        max-width: 600px;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: slideUpProc 0.3s ease;
    }

    @keyframes slideUpProc {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header-proc {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 16px;
    }

    .modal-title-proc {
        font-size: 1rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-title-proc i { color: #059669; }

    .modal-close-proc {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--page-text-secondary, #64748B);
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .modal-close-proc:hover { color: #DC2626; transform: rotate(90deg); }

    .form-group-proc { margin-bottom: 14px; }

    .form-group-proc label {
        display: block;
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 4px;
    }

    .form-group-proc label .required { color: #DC2626; margin-left: 2px; }

    .form-control-proc {
        width: 100%;
        padding: 8px 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }

    .form-control-proc:focus {
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    textarea.form-control-proc { resize: vertical; min-height: 60px; }

    .form-actions-proc {
        display: flex;
        gap: 12px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    .btn-save-proc {
        background: #059669;
        color: white;
        padding: 8px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: inherit;
    }

    .btn-save-proc:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .btn-cancel-modal-proc {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: inherit;
    }

    .btn-cancel-modal-proc:hover { border-color: #DC2626; color: #DC2626; }

    .help-text-proc {
        font-size: 0.65rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 2px;
    }

    /* FOOTER */
    .footer-proc {
        padding: 10px 0;
        border-top: 1px solid var(--page-border, #E2E8F0);
        margin-top: 20px;
        text-align: center;
        font-size: 0.65rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-proc .footer-brand-proc {
        color: #059669;
        font-weight: 600;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .filter-divider-proc { display: none; }
        .filter-row-proc { flex-direction: column; align-items: stretch; }
        .filter-input-proc { width: 100%; }
        .stats-row-proc { grid-template-columns: 1fr 1fr; }
        .page-header-proc { padding: 16px 18px; }
        .page-header-proc .page-title-proc { font-size: 1.1rem; }
    }

    @media (max-width: 480px) {
        .stats-row-proc { grid-template-columns: 1fr; }
        .data-table-proc { font-size: 0.6rem; }
        .data-table-proc thead th, .data-table-proc td { padding: 4px 6px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-proc">
        <div>
            <h1 class="page-title-proc">
                <i class="fas fa-syringe"></i>
                Procedures
                <span class="role-badge-display">ADMIN</span>
                <?php if ($selected_branch_id > 0): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge-proc">
                    <i class="fas fa-list"></i> <?= $total_procedures ?> Procedures
                </span>
                <span class="header-badge-proc" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.2);color:#FBBF24;">
                    <i class="fas fa-file-invoice"></i> <?= $total_bill_items ?> Bills
                </span>
            </h1>
            <p class="page-subtitle-proc">
                <i class="fas fa-arrow-right"></i>
                Manage procedures catalog and view bill items
                <span class="header-badge-proc" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= formatMoney($total_bill_amount) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="openAddModalProc()" class="btn-outline-light-proc" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                <i class="fas fa-plus-circle"></i> Add Procedure
            </button>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-row-proc">
        <div class="stat-card-proc total">
            <div class="stat-icon-proc"><i class="fas fa-syringe"></i></div>
            <div class="stat-number-proc"><?= $total_procedures ?></div>
            <div class="stat-label-proc">Total Procedures</div>
        </div>
        <div class="stat-card-proc active">
            <div class="stat-icon-proc"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number-proc"><?= $total_active ?></div>
            <div class="stat-label-proc">✅ Active</div>
        </div>
        <div class="stat-card-proc inactive">
            <div class="stat-icon-proc"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number-proc"><?= $total_inactive ?></div>
            <div class="stat-label-proc">❌ Inactive</div>
        </div>
        <div class="stat-card-proc bills">
            <div class="stat-icon-proc"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-number-proc"><?= $total_bill_items ?></div>
            <div class="stat-label-proc">📄 Bill Items</div>
        </div>
        <div class="stat-card-proc amount">
            <div class="stat-icon-proc"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number-proc">TSh <?= formatMoney($total_bill_amount) ?></div>
            <div class="stat-label-proc">Total Bill Amount</div>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div style="padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:0.8rem;display:flex;align-items:center;gap:10px;animation:slideDownProc 0.4s ease;<?= $message_type === 'success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= $message ?></span>
        </div>
    <?php endif; ?>

    <!-- FILTERS -->
    <div class="filter-section-proc">
        <form method="GET" action="" id="filterFormProc">
            <div class="filter-row-proc">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                
                <?php if (!empty($categories)): ?>
                <select name="category" class="filter-input-proc" style="min-width:130px;" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $filter_category === $cat['category'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="filter-divider-proc"></span>
                <?php endif; ?>
                
                <input type="text" name="search" class="filter-input-proc" style="min-width:150px;flex:1;" placeholder="Search procedures..." value="<?= htmlspecialchars($search) ?>">
                
                <button type="submit" class="btn-search-proc">
                    <i class="fas fa-search"></i> Filter
                </button>
                
                <a href="procedures.php?branch=<?= $selected_branch_id ?>" class="btn-reset-proc">
                    <i class="fas fa-times"></i> Reset
                </a>
                
                <button type="button" onclick="openAddModalProc()" class="btn-add-proc">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
        </form>
    </div>

    <!-- PROCEDURES TABLE -->
    <div class="table-container-proc">
        <div class="section-title-proc">
            <i class="fas fa-syringe"></i> Procedures Catalog
            <span style="font-size:0.6rem;font-weight:400;color:var(--page-text-secondary);margin-left:8px;">
                <?= $total_procedures ?> procedures
            </span>
        </div>
        <div class="table-scroll-proc">
            <table class="data-table-proc">
                <thead>
                    <tr>
                        <th style="width:30px;"><i class="fas fa-hashtag"></i></th>
                        <th><i class="fas fa-code"></i> Code</th>
                        <th><i class="fas fa-syringe"></i> Procedure Name</th>
                        <th><i class="fas fa-tag"></i> Category</th>
                        <th><i class="fas fa-store-alt"></i> Branch</th>
                        <th style="text-align:center;"><i class="fas fa-money-bill"></i> Price</th>
                        <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                        <th style="text-align:center;"><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($procedures) > 0): ?>
                        <?php $i = 1; foreach ($procedures as $proc): ?>
                            <tr>
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span style="font-family:monospace;font-weight:600;color:#059669;font-size:0.65rem;">
                                        <?= htmlspecialchars($proc['procedure_code'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:600;font-size:0.75rem;">
                                        <?= htmlspecialchars($proc['procedure_name']) ?>
                                    </span>
                                    <?php if (!empty($proc['description'])): ?>
                                        <div style="font-size:0.6rem;color:var(--page-text-secondary);">
                                            <?= htmlspecialchars(substr($proc['description'], 0, 60)) ?>
                                            <?= strlen($proc['description']) > 60 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status-proc" style="background:#EDE9FE;color:#7C3AED;border-color:#7C3AED;">
                                        <?= htmlspecialchars($proc['category'] ?? 'Uncategorized') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.65rem;color:#0D9488;">
                                        <?= htmlspecialchars($proc['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;font-weight:700;color:#059669;">
                                    TSh <?= formatMoney($proc['price']) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status-proc badge-<?= getStatusBadgeClass($proc['is_active'] ? 'active' : 'inactive') ?>-proc">
                                        <?= getStatusLabel($proc['is_active'] ? 'active' : 'inactive') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="action-buttons-proc">
                                        <a href="procedures.php?edit=<?= $proc['id'] ?>&branch=<?= $selected_branch_id ?>&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>" class="btn-edit-proc" title="Edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="procedures.php?toggle=<?= $proc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-toggle-proc <?= $proc['is_active'] == 1 ? 'active' : 'inactive' ?>" title="Toggle Status">
                                            <i class="fas fa-<?= $proc['is_active'] == 1 ? 'pause' : 'play' ?>"></i>
                                            <?= $proc['is_active'] == 1 ? 'Deactivate' : 'Activate' ?>
                                        </a>
                                        <a href="procedures.php?delete=<?= $proc['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn-delete-proc" onclick="return confirm('Delete this procedure? This action cannot be undone!')" title="Delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state-proc">
                                    <i class="fas fa-syringe"></i>
                                    <p>No procedures found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer-proc">
            <span><i class="fas fa-list"></i> Showing <strong><?= count($procedures) ?></strong> procedures</span>
            <span><span class="count-badge-proc"><?= $total_procedures ?></span> Total</span>
        </div>
    </div>

    <!-- BILL ITEMS TABLE -->
    <div class="table-container-proc">
        <div class="section-title-proc">
            <i class="fas fa-file-invoice"></i> Procedure Bill Items
            <span style="font-size:0.6rem;font-weight:400;color:var(--page-text-secondary);margin-left:8px;">
                <?= $total_bill_items ?> items | Total: TSh <?= formatMoney($total_bill_amount) ?>
            </span>
        </div>
        <div class="table-scroll-proc">
            <table class="data-table-proc">
                <thead>
                    <tr>
                        <th style="width:30px;"><i class="fas fa-hashtag"></i></th>
                        <th><i class="fas fa-receipt"></i> Bill #</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-syringe"></i> Procedure</th>
                        <th style="text-align:center;"><i class="fas fa-money-bill"></i> Price</th>
                        <th style="text-align:center;"><i class="fas fa-calendar"></i> Date</th>
                        <th style="text-align:center;"><i class="fas fa-info-circle"></i> Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bill_items) > 0): ?>
                        <?php $i = 1; foreach ($bill_items as $item): ?>
                            <tr>
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span style="font-family:monospace;font-weight:600;color:#059669;font-size:0.65rem;">
                                        <?= htmlspecialchars($item['bill_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight:600;font-size:0.75rem;">
                                        <?= htmlspecialchars($item['patient_name'] ?? 'Unknown Patient') ?>
                                    </div>
                                    <div style="font-size:0.55rem;color:var(--page-text-secondary);">
                                        <?= htmlspecialchars($item['patient_number'] ?? 'ID: N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight:600;font-size:0.7rem;">
                                        <?= htmlspecialchars($item['item_name']) ?>
                                    </span>
                                    <div style="font-size:0.5rem;color:var(--page-text-secondary);">
                                        <?= ucfirst($item['item_type']) ?>
                                    </div>
                                </td>
                                <td style="text-align:center;font-weight:700;color:#059669;">
                                    TSh <?= formatMoney($item['total_price']) ?>
                                </td>
                                <td style="text-align:center;font-size:0.7rem;">
                                    <?= formatDateOnly($item['created_at']) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-status-proc badge-<?= getStatusBadgeClass($item['status'] ?? 'pending') ?>-proc">
                                        <?= getStatusLabel($item['status'] ?? 'pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row-proc">
                            <td colspan="4" style="text-align:right;font-size:0.75rem;">
                                <i class="fas fa-calculator" style="color:#059669;"></i> 
                                TOTAL PROCEDURE BILL AMOUNT
                            </td>
                            <td style="text-align:center;font-size:0.85rem;font-weight:700;color:#059669;">
                                TSh <?= formatMoney($total_bill_amount) ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state-proc">
                                    <i class="fas fa-file-invoice"></i>
                                    <p>No bill items found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer-proc">
            <span><i class="fas fa-list"></i> Showing <strong><?= $total_bill_items ?></strong> items</span>
            <span>
                <span class="count-badge-proc"><?= $total_bill_items ?></span>
                <span class="count-badge-proc">TSh <?= formatMoney($total_bill_amount) ?></span>
            </span>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer-proc">
        <p>
            <span class="footer-brand-proc">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Procedures
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- ADD MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay-proc" id="addModalProc">
    <div class="modal-content-proc">
        <div class="modal-header-proc">
            <div class="modal-title-proc"><i class="fas fa-plus-circle"></i> Add New Procedure</div>
            <button class="modal-close-proc" onclick="closeModalProc('addModalProc')">&times;</button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_procedure">
            <input type="hidden" name="branch_id" value="<?= $selected_branch_id ?>">
            
            <?php if ($selected_branch_id == 0): ?>
            <div class="form-group-proc">
                <label>Branch <span class="required">*</span></label>
                <select name="branch_id" class="form-control-proc" required>
                    <option value="">Select Branch</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="form-group-proc">
                <label>Procedure Name <span class="required">*</span></label>
                <input type="text" name="procedure_name" class="form-control-proc" placeholder="e.g. Wound Dressing" required>
            </div>
            
            <div class="form-group-proc">
                <label>Category <span class="required">*</span></label>
                <select name="category" class="form-control-proc" required>
                    <option value="">Select Category</option>
                    <option value="Wound Care">🩹 Wound Care</option>
                    <option value="Surgery">🔬 Surgery</option>
                    <option value="Orthopedics">🦴 Orthopedics</option>
                    <option value="Diagnostic">📊 Diagnostic</option>
                    <option value="Administration">💉 Administration</option>
                    <option value="Procedure">📋 Procedure</option>
                    <option value="Other">📌 Other</option>
                </select>
            </div>
            
            <div class="form-group-proc">
                <label>Price (TSh) <span class="required">*</span></label>
                <input type="text" name="price" id="priceInputProc" class="form-control-proc" placeholder="0" required oninput="formatAmountProc(this)" onfocus="this.select()">
                <div class="help-text-proc"><i class="fas fa-info-circle"></i> Type numbers - commas added automatically</div>
            </div>
            
            <div class="form-group-proc">
                <label>Description</label>
                <textarea name="description" class="form-control-proc" rows="2" placeholder="Procedure description..."></textarea>
            </div>
            
            <div class="form-group-proc" style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" name="is_active" id="isActiveProc" checked style="width:18px;height:18px;accent-color:#059669;">
                <label for="isActiveProc" style="margin:0;font-weight:500;font-size:0.8rem;">Active (available for use)</label>
            </div>
            
            <div class="form-actions-proc">
                <button type="submit" class="btn-save-proc"><i class="fas fa-save"></i> Save Procedure</button>
                <button type="button" class="btn-cancel-modal-proc" onclick="closeModalProc('addModalProc')"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- EDIT MODAL -->
<!-- ================================================================ -->
<?php if ($edit_procedure): ?>
<div class="modal-overlay-proc show" id="editModalProc">
    <div class="modal-content-proc">
        <div class="modal-header-proc">
            <div class="modal-title-proc">
                <i class="fas fa-edit"></i> Edit Procedure
                <span style="font-size:0.6rem;font-weight:400;color:var(--page-text-secondary);margin-left:8px;">
                    #<?= htmlspecialchars($edit_procedure['procedure_code'] ?? '') ?>
                </span>
            </div>
            <a href="procedures.php?branch=<?= $selected_branch_id ?>&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>" class="modal-close-proc">&times;</a>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_procedure">
            <input type="hidden" name="procedure_id" value="<?= $edit_procedure['id'] ?>">
            <input type="hidden" name="branch_id" value="<?= $edit_procedure['branch_id'] ?>">
            
            <div class="form-group-proc">
                <label>Procedure Code</label>
                <input type="text" class="form-control-proc" value="<?= htmlspecialchars($edit_procedure['procedure_code'] ?? 'N/A') ?>" disabled style="background:var(--page-hover);">
            </div>
            
            <div class="form-group-proc">
                <label>Procedure Name <span class="required">*</span></label>
                <input type="text" name="procedure_name" class="form-control-proc" value="<?= htmlspecialchars($edit_procedure['procedure_name']) ?>" required>
            </div>
            
            <div class="form-group-proc">
                <label>Category <span class="required">*</span></label>
                <select name="category" class="form-control-proc" required>
                    <option value="">Select Category</option>
                    <option value="Wound Care" <?= $edit_procedure['category'] == 'Wound Care' ? 'selected' : '' ?>>🩹 Wound Care</option>
                    <option value="Surgery" <?= $edit_procedure['category'] == 'Surgery' ? 'selected' : '' ?>>🔬 Surgery</option>
                    <option value="Orthopedics" <?= $edit_procedure['category'] == 'Orthopedics' ? 'selected' : '' ?>>🦴 Orthopedics</option>
                    <option value="Diagnostic" <?= $edit_procedure['category'] == 'Diagnostic' ? 'selected' : '' ?>>📊 Diagnostic</option>
                    <option value="Administration" <?= $edit_procedure['category'] == 'Administration' ? 'selected' : '' ?>>💉 Administration</option>
                    <option value="Procedure" <?= $edit_procedure['category'] == 'Procedure' ? 'selected' : '' ?>>📋 Procedure</option>
                    <option value="Other" <?= $edit_procedure['category'] == 'Other' ? 'selected' : '' ?>>📌 Other</option>
                </select>
            </div>
            
            <div class="form-group-proc">
                <label>Price (TSh) <span class="required">*</span></label>
                <input type="text" name="price" id="editPriceInputProc" class="form-control-proc" value="<?= formatMoney($edit_procedure['price']) ?>" required oninput="formatAmountProc(this)" onfocus="this.select()">
            </div>
            
            <div class="form-group-proc">
                <label>Description</label>
                <textarea name="description" class="form-control-proc" rows="2"><?= htmlspecialchars($edit_procedure['description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group-proc" style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" name="is_active" id="editIsActiveProc" <?= $edit_procedure['is_active'] == 1 ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#059669;">
                <label for="editIsActiveProc" style="margin:0;font-weight:500;font-size:0.8rem;">Active (available for use)</label>
            </div>
            
            <div class="form-actions-proc">
                <button type="submit" class="btn-save-proc"><i class="fas fa-save"></i> Update Procedure</button>
                <a href="procedures.php?branch=<?= $selected_branch_id ?>&category=<?= urlencode($filter_category) ?>&search=<?= urlencode($search) ?>" class="btn-cancel-modal-proc"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FOOTER TIME
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    // ================================================================
    // FORMAT AMOUNT
    // ================================================================
    function formatAmountProc(input) {
        var raw = input.value.replace(/[^0-9]/g, '');
        if (raw === '') { input.value = ''; return; }
        var num = parseInt(raw, 10);
        if (isNaN(num)) { input.value = ''; return; }
        input.value = num.toLocaleString('en-US');
    }

    // ================================================================
    // MODAL
    // ================================================================
    function openAddModalProc() {
        document.getElementById('addModalProc').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModalProc(id) {
        document.getElementById(id).classList.remove('show');
        document.body.style.overflow = 'auto';
    }
    
    document.querySelectorAll('.modal-overlay-proc').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay-proc.show').forEach(function(modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            });
        }
    });

    // ================================================================
    // TOAST (dynamic)
    // ================================================================
    function showToastProc(title, message, type) {
        var existing = document.getElementById('pageToastProc');
        if (existing) existing.remove();
        
        var toast = document.createElement('div');
        toast.id = 'pageToastProc';
        var bgColor = type === 'success' ? '#059669' : 
                      type === 'error' ? '#DC2626' : 
                      type === 'warning' ? '#D97706' : '#0B5ED7';
        var icon = type === 'success' ? 'fa-check-circle' : 
                   type === 'error' ? 'fa-exclamation-circle' : 
                   type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
        
        toast.style.cssText = `
            position: fixed; bottom: 24px; right: 24px; padding: 14px 20px;
            border-radius: 12px; z-index: 99999; max-width: 400px;
            display: flex; align-items: center; gap: 12px; color: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            animation: slideInProc 0.4s ease; font-size: 0.85rem;
            font-weight: 500; background: ${bgColor};
        `;
        toast.innerHTML = `
            <i class="fas ${icon}" style="font-size:1.1rem;"></i>
            <div>
                <p style="font-weight:600;font-size:0.85rem;margin:0;">${title}</p>
                <p style="font-size:0.75rem;opacity:0.9;margin:2px 0 0 0;">${message}</p>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(function() {
            toast.style.animation = 'slideOutProc 0.4s ease';
            setTimeout(function() { toast.remove(); }, 400);
        }, 3500);
    }

    if (!document.getElementById('toastAnimationsProc')) {
        var style = document.createElement('style');
        style.id = 'toastAnimationsProc';
        style.textContent = `
            @keyframes slideInProc { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            @keyframes slideOutProc { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
            @keyframes slideDownProc { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        `;
        document.head.appendChild(style);
    }

    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToastProc('<?= $message_type === 'success' ? '✅ Success' : '❌ Error' ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c💉 Braick - Procedures', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Total Procedures: <?= $total_procedures ?>', 'font-size:12px; color:#059669;');
    console.log('%c💰 Total Bill Amount: TSh <?= formatMoney($total_bill_amount) ?>', 'font-size:12px; color:#059669;');
</script>

</body>
</html>