<?php
// ================================================================
// FILE: frontend/pages/admin/prescriptions.php
// PRESCRIPTIONS LIST - VIEW ALL PRESCRIPTIONS
// FIXED: Branch filter working correctly with statistics
// FIXED: Shows all prescriptions even without sales
// FIXED: Branch filter now properly filters by branch_id
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
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

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// HANDLE DELETE PRESCRIPTION
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("SELECT prescription_number, patient_id FROM prescriptions WHERE id = ?");
        $stmt->execute([$delete_id]);
        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($prescription) {
            $patient_id = $prescription['patient_id'];
            
            $stmt = $db->prepare("DELETE FROM prescription_sales WHERE prescription_id = ?");
            $stmt->execute([$delete_id]);
            
            $stmt = $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?");
            $stmt->execute([$delete_id]);
            
            $stmt = $db->prepare("DELETE FROM prescriptions WHERE id = ?");
            $stmt->execute([$delete_id]);
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            if ($count == 0) {
                $stmt = $db->prepare("DELETE FROM patient_bills WHERE patient_id = ?");
                $stmt->execute([$patient_id]);
                
                $stmt = $db->prepare("DELETE FROM bill_items WHERE patient_id = ?");
                $stmt->execute([$patient_id]);
                
                $stmt = $db->prepare("DELETE FROM patients WHERE id = ?");
                $stmt->execute([$patient_id]);
            }
        }
        
        $db->commit();
        header('Location: prescriptions.php?branch=' . urlencode($branch_id) . '&deleted=1');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Delete error: " . $e->getMessage());
        header('Location: prescriptions.php?branch=' . urlencode($branch_id) . '&error=delete_failed');
        exit;
    }
}

// ================================================================
// VARIABLES
// ================================================================
$message = '';
$message_type = '';
$per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get branch name for display
$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all') {
    foreach ($branches_list as $b) {
        if ($b['id'] == $selected_branch_id) {
            $selected_branch_name = $b['name'];
            break;
        }
    }
}

// ================================================================
// BUILD QUERY FROM prescriptions TABLE - FIXED
// ================================================================
$where_clause = " WHERE 1=1";
$params = [];

// Search filter
if (!empty($search)) {
    $where_clause .= " AND (p.prescription_number LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ? OR p.diagnosis LIKE ? OR p.medication LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter
if (!empty($status_filter)) {
    $where_clause .= " AND p.status = ?";
    $params[] = $status_filter;
}

// Branch filter - FIXED: Always apply branch filter when selected
if ($selected_branch_id !== 'all') {
    $where_clause .= " AND p.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// GET PRESCRIPTIONS FROM prescriptions TABLE - FIXED: LEFT JOIN correctly
// ================================================================

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    $where_clause
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_prescriptions / $per_page);

// Get prescriptions with doctor name and amount - FIXED: Use LEFT JOIN for prescription_sales
$sql = "
    SELECT 
        p.*,
        pat.full_name as patient_name, 
        pat.patient_id as patient_number,
        pat.id as patient_id,
        u.full_name as doctor_name,
        b.name as branch_name,
        COALESCE(ps.total_amount, 0) as prescription_amount,
        COALESCE(ps.sale_number, '') as sale_number,
        CASE 
            WHEN p.status = 'pending' THEN 'warning'
            WHEN p.status = 'confirmed' THEN 'info'
            WHEN p.status = 'dispensed' THEN 'success'
            WHEN p.status = 'cancelled' THEN 'danger'
            ELSE 'secondary'
        END as status_color
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN branches b ON p.branch_id = b.id
    LEFT JOIN prescription_sales ps ON p.id = ps.prescription_id
    $where_clause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS - FIXED: Properly filtered by branch
// ================================================================

// Build WHERE clause for statistics
$stats_where = " WHERE 1=1";
$stats_params = [];

if ($selected_branch_id !== 'all') {
    $stats_where .= " AND p.branch_id = ?";
    $stats_params[] = (int)$selected_branch_id;
}

// Total prescriptions (filtered by branch)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions p $stats_where");
$stmt->execute($stats_params);
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pending prescriptions (filtered by branch)
$pending_where = $stats_where . " AND p.status = 'pending'";
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions p $pending_where");
$stmt->execute($stats_params);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Confirmed prescriptions (filtered by branch)
$confirmed_where = $stats_where . " AND p.status = 'confirmed'";
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions p $confirmed_where");
$stmt->execute($stats_params);
$confirmed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Dispensed prescriptions (filtered by branch)
$dispensed_where = $stats_where . " AND p.status = 'dispensed'";
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions p $dispensed_where");
$stmt->execute($stats_params);
$dispensed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Cancelled prescriptions (filtered by branch)
$cancelled_where = $stats_where . " AND p.status = 'cancelled'";
$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions p $cancelled_where");
$stmt->execute($stats_params);
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// GET AMOUNT STATISTICS - FIXED: Filtered by branch
// ================================================================

// Build WHERE clause for prescription_sales with branch filter
$sales_where = " WHERE 1=1";
$sales_params = [];

if ($selected_branch_id !== 'all') {
    // Join with prescriptions to filter by branch
    $sales_where = " WHERE p.branch_id = ?";
    $sales_params[] = (int)$selected_branch_id;
}

// Total amount from prescription_sales (paid/dispensed)
$amount_sql = "
    SELECT COALESCE(SUM(ps.total_amount), 0) as total_amount 
    FROM prescription_sales ps
    LEFT JOIN prescriptions p ON ps.prescription_id = p.id
    $sales_where AND (ps.status = 'paid' OR ps.status = 'dispensed')
";
$stmt = $db->prepare($amount_sql);
$stmt->execute($sales_params);
$total_amount_all = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Pending amount
$pending_amount_sql = "
    SELECT COALESCE(SUM(ps.total_amount), 0) as total_amount 
    FROM prescription_sales ps
    LEFT JOIN prescriptions p ON ps.prescription_id = p.id
    $sales_where AND ps.status = 'pending'
";
$stmt = $db->prepare($pending_amount_sql);
$stmt->execute($sales_params);
$pending_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Dispensed amount
$dispensed_amount_sql = "
    SELECT COALESCE(SUM(ps.total_amount), 0) as total_amount 
    FROM prescription_sales ps
    LEFT JOIN prescriptions p ON ps.prescription_id = p.id
    $sales_where AND ps.status = 'dispensed'
";
$stmt = $db->prepare($dispensed_amount_sql);
$stmt->execute($sales_params);
$dispensed_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Confirmed amount
$confirmed_amount_sql = "
    SELECT COALESCE(SUM(ps.total_amount), 0) as total_amount 
    FROM prescription_sales ps
    LEFT JOIN prescriptions p ON ps.prescription_id = p.id
    $sales_where AND ps.status = 'confirmed'
";
$stmt = $db->prepare($confirmed_amount_sql);
$stmt->execute($sales_params);
$confirmed_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Cancelled amount
$cancelled_amount_sql = "
    SELECT COALESCE(SUM(ps.total_amount), 0) as total_amount 
    FROM prescription_sales ps
    LEFT JOIN prescriptions p ON ps.prescription_id = p.id
    $sales_where AND ps.status = 'cancelled'
";
$stmt = $db->prepare($cancelled_amount_sql);
$stmt->execute($sales_params);
$cancelled_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- CSS STYLES -->
<style>
    /* ================================================================
       5 STAT CARDS WITH BACKGROUND COLORS
       ================================================================ */
    .stats-grid-5 {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 14px;
        margin-bottom: 24px;
    }
    
    .stat-card-custom {
        border-radius: 14px;
        padding: 16px 18px;
        border: none;
        display: flex;
        flex-direction: column;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        color: white;
        position: relative;
        overflow: hidden;
        min-height: 110px;
        cursor: default;
    }
    
    .stat-card-custom::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 160px;
        height: 160px;
        background: rgba(255,255,255,0.06);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }
    .stat-card-custom::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -10%;
        width: 120px;
        height: 120px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.5s ease;
    }
    .stat-card-custom:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 10px 32px rgba(0,0,0,0.2);
    }
    .stat-card-custom:hover::before { transform: scale(1.3); right: -10%; }
    .stat-card-custom:hover::after { transform: scale(1.4); bottom: -30%; }
    
    .stat-card-custom .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.18);
        color: white;
        border: 1px solid rgba(255,255,255,0.12);
        backdrop-filter: blur(8px);
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
        margin-bottom: 4px;
    }
    .stat-card-custom:hover .stat-icon {
        transform: scale(1.05) rotate(-2deg);
        background: rgba(255,255,255,0.3);
    }
    .stat-card-custom .stat-content {
        position: relative;
        z-index: 1;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .stat-card-custom .stat-label {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.85);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0 0 1px 0;
    }
    .stat-card-custom .stat-number {
        font-size: 2.2rem;
        font-weight: 800;
        color: white;
        margin: 0;
        line-height: 1.1;
        letter-spacing: -0.02em;
    }
    .stat-card-custom .stat-amount {
        font-size: 0.9rem;
        font-weight: 600;
        color: rgba(255,255,255,0.9);
        margin-top: 2px;
    }
    .stat-card-custom .stat-sub {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.8);
        margin-top: 2px;
    }
    .stat-card-custom .stat-arrow {
        position: absolute;
        right: 12px;
        bottom: 12px;
        color: rgba(255,255,255,0.12);
        font-size: 0.7rem;
        transition: all 0.3s ease;
        z-index: 1;
    }
    .stat-card-custom:hover .stat-arrow {
        transform: translateX(6px);
        color: rgba(255,255,255,0.4);
    }
    
    .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .card-blue:hover { box-shadow: 0 10px 32px rgba(11, 94, 215, 0.4); }
    .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .card-orange:hover { box-shadow: 0 10px 32px rgba(217, 119, 6, 0.4); }
    .card-green { background: linear-gradient(135deg, #059669, #047857); }
    .card-green:hover { box-shadow: 0 10px 32px rgba(5, 150, 105, 0.4); }
    .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .card-red:hover { box-shadow: 0 10px 32px rgba(220, 38, 38, 0.4); }
    
    [data-theme="dark"] .card-blue { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
    [data-theme="dark"] .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
    [data-theme="dark"] .card-green { background: linear-gradient(135deg, #059669, #047857); }
    [data-theme="dark"] .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    
    /* Table */
    .table-container { position: relative; overflow: hidden; border-radius: 12px; }
    .table-scroll-wrapper { overflow-x: auto; overflow-y: visible; -webkit-overflow-scrolling: touch; scroll-behavior: smooth; position: relative; }
    .table-scroll-wrapper::-webkit-scrollbar { height: 6px; }
    .table-scroll-wrapper::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
    .table-scroll-wrapper::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 10px; }
    
    .table-blue thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.65rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 10px 14px !important;
        border-bottom: 3px solid #0A4CA8 !important;
        white-space: nowrap !important;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    .table-blue thead th:first-child { border-radius: 8px 0 0 0 !important; }
    .table-blue thead th:last-child { border-radius: 0 8px 0 0 !important; }
    .table-blue tbody td {
        padding: 8px 14px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
        white-space: nowrap;
    }
    .table-blue tbody tr:hover td { background: #E8F0FE !important; }
    [data-theme="dark"] .table-blue tbody td { color: #F1F5F9 !important; border-bottom-color: #334155 !important; }
    [data-theme="dark"] .table-blue tbody tr:hover td { background: #1A2A4A !important; }
    
    .amount-cell { font-weight: 700; color: #0B5ED7; font-family: 'Courier New', monospace; font-size: 0.85rem; }
    [data-theme="dark"] .amount-cell { color: #60A5FA; }
    
    /* ================================================================
       ONLY 3 ACTION BUTTONS: View, Edit, Delete
       ================================================================ */
    .action-buttons {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 14px;
        border-radius: 8px;
        font-size: 0.65rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    
    .btn-action i { font-size: 0.7rem; transition: transform 0.3s ease; }
    .btn-action:hover { transform: translateY(-2px) scale(1.03); }
    .btn-action:hover i { transform: translateX(2px); }
    .btn-action:active { transform: scale(0.95); }
    
    .btn-view {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
    }
    .btn-view:hover {
        box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
        background: linear-gradient(135deg, #0A4CA8, #083C8A);
    }
    
    .btn-edit {
        background: linear-gradient(135deg, #D97706, #B45309);
        color: white;
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25);
    }
    .btn-edit:hover {
        box-shadow: 0 6px 20px rgba(217, 119, 6, 0.4);
        background: linear-gradient(135deg, #B45309, #92400E);
    }
    
    .btn-delete {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        color: white;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
    }
    .btn-delete:hover {
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        background: linear-gradient(135deg, #B91C1C, #991B1B);
    }
    
    [data-theme="dark"] .btn-view { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
    [data-theme="dark"] .btn-view:hover { background: linear-gradient(135deg, #1D4ED8, #1A3E8C); }
    
    .status-badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .status-badge.warning { background: #FEF3C7; color: #D97706; }
    .status-badge.success { background: #D1FAE5; color: #059669; }
    .status-badge.danger { background: #FEE2E2; color: #EF4444; }
    .status-badge.info { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.secondary { background: #E2E8F0; color: #64748B; }
    [data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
    
    .filter-section {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        margin-bottom: 18px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    .filter-section .filter-label { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); margin-right: 4px; }
    .filter-btn {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    .filter-btn:hover { border-color: #0B5ED7; color: #0B5ED7; background: #E8F0FE; }
    .filter-btn.active { background: #0B5ED7; color: white; border-color: #0B5ED7; }
    .filter-btn.active:hover { background: #0A4CA8; border-color: #0A4CA8; }
    [data-theme="dark"] .filter-btn:hover { background: #1A2A4A; border-color: #3B82F6; color: #3B82F6; }
    .filter-btn i { margin-right: 4px; }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    .card:hover { border-color: #0B5ED7; box-shadow: 0 4px 12px rgba(11, 94, 215, 0.05); }
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    .card-title { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
    .title-blue { color: #0B5ED7; }
    
    .pagination { display: flex; gap: 4px; flex-wrap: wrap; }
    .pagination .page-link {
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.8rem;
        transition: all 0.3s;
        background: var(--bg-card);
    }
    .pagination .page-link:hover { background: #0B5ED7; color: white; border-color: #0B5ED7; }
    .pagination .page-link.active { background: #0B5ED7; color: white; border-color: #0B5ED7; }
    .pagination .page-link.disabled { opacity: 0.5; cursor: not-allowed; }
    [data-theme="dark"] .pagination .page-link { background: #1E293B; border-color: #334155; }
    [data-theme="dark"] .pagination .page-link:hover { background: #0B5ED7; border-color: #0B5ED7; }
    
    .page-header {
        background: #0B5ED7 !important;
        border-radius: 16px !important;
        padding: 20px 28px !important;
        margin-bottom: 20px !important;
        display: flex !important;
        flex-wrap: wrap !important;
        justify-content: space-between !important;
        align-items: center !important;
        gap: 12px !important;
        box-shadow: 0 4px 24px rgba(11, 94, 215, 0.25) !important;
    }
    .page-header .page-title { color: white !important; font-size: 1.4rem !important; font-weight: 700 !important; }
    .page-header .page-title i { color: white !important; }
    .page-header .page-subtitle { color: rgba(255,255,255,0.85) !important; font-size: 0.85rem !important; }
    .page-header .branch-tag { background: rgba(255,255,255,0.2) !important; color: white !important; padding: 3px 14px !important; border-radius: 20px !important; font-size: 0.7rem !important; font-weight: 600 !important; }
    
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
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: #0B5ED7; }
    
    @media (max-width: 1200px) { .stats-grid-5 { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) {
        .stats-grid-5 { grid-template-columns: 1fr 1fr; }
        .stat-card-custom .stat-number { font-size: 1.6rem; }
        .stat-card-custom { min-height: 90px; padding: 14px 16px; }
        .stat-card-custom .stat-icon { width: 38px; height: 38px; font-size: 0.9rem; }
        .filter-section { flex-direction: column; align-items: stretch; }
        .action-buttons { flex-wrap: wrap; justify-content: center; }
        .btn-action { font-size: 0.55rem; padding: 4px 10px; }
        .table-blue tbody td { font-size: 0.7rem; padding: 6px 10px !important; }
    }
    @media (max-width: 480px) {
        .stats-grid-5 { grid-template-columns: 1fr 1fr; }
        .stat-card-custom .stat-number { font-size: 1.4rem; }
        .stat-card-custom { min-height: 80px; padding: 12px 14px; }
        .stat-card-custom .stat-icon { width: 32px; height: 32px; font-size: 0.8rem; }
        .page-header { padding: 14px 16px !important; }
        .page-header .page-title { font-size: 1rem !important; }
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
</style>

<!-- TOP NAVIGATION -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <form method="GET" action="" class="flex-1 flex">
                <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search prescriptions..." 
                       class="flex-1 px-3 py-2 bg-transparent border-none outline-none text-sm" 
                       style="color: var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
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
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription mr-2"></i> Prescriptions
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="branch-tag ml-2" style="background:rgba(255,255,255,0.2);color:white;padding:3px 14px;border-radius:20px;font-size:0.7rem;font-weight:600;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Manage all prescriptions in the system
                <span class="branch-tag ml-2">
                    <i class="fas fa-prescription"></i> <?= $total_all ?> Total
                </span>
                <span class="ml-2 inline-flex bg-white/20 text-white px-3 py-1 rounded-full text-xs border border-white/10">
                    <i class="fas fa-money-bill-wave mr-1"></i> TSh <?= number_format($total_amount_all) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline-light" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.2);padding:6px 16px;border-radius:10px;font-size:0.8rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- 5 CARDS -->
    <div class="stats-grid-5 animate-fade-in-up">
        <div class="stat-card-custom card-blue">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total</p>
                <p class="stat-number"><?= $total_all ?></p>
                <p class="stat-amount">TSh <?= number_format($total_amount_all, 0) ?></p>
                <p class="stat-sub">All prescriptions</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <div class="stat-card-custom card-orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= $pending_count ?></p>
                <p class="stat-amount">TSh <?= number_format($pending_amount, 0) ?></p>
                <p class="stat-sub">Awaiting approval</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <div class="stat-card-custom card-blue">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Confirmed</p>
                <p class="stat-number"><?= $confirmed_count ?></p>
                <p class="stat-amount">TSh <?= number_format($confirmed_amount, 0) ?></p>
                <p class="stat-sub">Approved</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <div class="stat-card-custom card-green">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div class="stat-content">
                <p class="stat-label">Dispensed</p>
                <p class="stat-number"><?= $dispensed_count ?></p>
                <p class="stat-amount">TSh <?= number_format($dispensed_amount, 0) ?></p>
                <p class="stat-sub">Completed</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <div class="stat-card-custom card-red">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Cancelled</p>
                <p class="stat-number"><?= $cancelled_count ?></p>
                <p class="stat-amount">TSh <?= number_format($cancelled_amount, 0) ?></p>
                <p class="stat-sub">Voided</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
        
        <a href="?branch=<?= $selected_branch_id ?>&status=&page=1<?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=pending&page=1<?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=confirmed&page=1<?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="filter-btn <?= $status_filter === 'confirmed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Confirmed
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=dispensed&page=1<?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="filter-btn <?= $status_filter === 'dispensed' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> Dispensed
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=cancelled&page=1<?= $search ? '&search='.urlencode($search) : '' ?>" 
           class="filter-btn <?= $status_filter === 'cancelled' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Cancelled
        </a>
        
        <?php if (!empty($search) || !empty($status_filter)): ?>
            <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="filter-btn" style="border-color: #EF4444; color: #EF4444;">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>

    <!-- PRESCRIPTIONS LIST -->
    <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                Prescriptions List
                <span class="text-sm font-normal text-gray-400">(<?= $total_prescriptions ?> prescriptions)</span>
            </h3>
        </div>
        
        <div class="table-container">
            <div class="table-scroll-wrapper">
                <table class="data-table table-blue w-full">
                    <thead>
                        <tr>
                            <th style="width: 50px; min-width: 50px;">#</th>
                            <th style="min-width: 140px;">Prescription #</th>
                            <th style="min-width: 140px;">Patient</th>
                            <th style="min-width: 100px;">Patient ID</th>
                            <th style="min-width: 120px;">Doctor</th>
                            <th style="min-width: 150px;">Medication</th>
                            <th style="min-width: 120px;" class="text-right">Amount</th>
                            <th style="min-width: 100px;">Status</th>
                            <th style="min-width: 100px;">Date</th>
                            <th style="min-width: 200px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($prescriptions) > 0): ?>
                            <?php $i = $offset + 1; foreach ($prescriptions as $prescription): ?>
                                <tr>
                                    <td class="font-bold text-blue-600 dark:text-blue-400"><?= $i++ ?></td>
                                    <td class="font-mono text-xs font-bold"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></td>
                                    <td class="font-semibold"><?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?></td>
                                    <td class="font-mono text-xs"><?= htmlspecialchars($prescription['patient_number'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if (!empty($prescription['doctor_name'])): ?>
                                            <span class="badge badge-info" style="background:#E8F0FE;color:#0B5ED7;padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                                                <i class="fas fa-user-md"></i> <?= htmlspecialchars($prescription['doctor_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($prescription['medication'] ?? 'N/A') ?></td>
                                    <td class="text-right amount-cell">TSh <?= number_format($prescription['prescription_amount'] ?? 0, 0) ?></td>
                                    <td>
                                        <span class="status-badge <?= $prescription['status_color'] ?? 'secondary' ?>">
                                            <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="text-xs"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action btn-view" title="View Prescription">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <a href="edit_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action btn-edit" title="Edit Prescription">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <a href="?delete=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action btn-delete" title="Delete Prescription"
                                               onclick="return confirm('⚠️ Are you sure you want to delete this prescription?\n\nPrescription: <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>\nPatient: <?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?>\nAmount: TSh <?= number_format($prescription['prescription_amount'] ?? 0, 0) ?>\n\nThis action cannot be undone!')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-8 text-gray-400">
                                    <i class="fas fa-prescription text-4xl block mb-3" style="color: #0B5ED7;"></i>
                                    <p class="text-lg font-medium" style="color: var(--text-primary);">
                                        <?= !empty($search) || !empty($status_filter) ? 'No prescriptions found matching your filters' : 'No prescriptions found' ?>
                                    </p>
                                    <p class="text-sm" style="color: var(--text-secondary);">
                                        <?= !empty($search) || !empty($status_filter) ? 'Try changing your search or filter criteria' : 'No prescriptions have been created yet' ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="flex flex-wrap justify-between items-center gap-3 mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_prescriptions) ?> of <?= $total_prescriptions ?> prescriptions
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?page=<?= $p ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                           class="page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescriptions Management
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- JAVASCRIPT -->
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('page');
        url.searchParams.delete('deleted');
        url.searchParams.delete('error');
        window.location.href = url.toString();
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

    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
        showToast('✅ Success', 'Prescription deleted successfully!', 'success');
        if (window.history && window.history.replaceState) {
            var cleanUrl = window.location.href.split('?')[0];
            window.history.replaceState({}, document.title, cleanUrl);
        }
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] == 'delete_failed'): ?>
        showToast('⚠️ Error', 'Failed to delete prescription. Please try again.', 'error');
    <?php endif; ?>

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

    console.log('%c🏥 Braick Dispensary - Prescriptions Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($selected_branch_name) ?> (ID: <?= $selected_branch_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c💊 Total Prescriptions: <?= $total_all ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Statistics filtered by branch', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>