<?php
// ================================================================
// FILE: frontend/pages/admin/prescriptions.php
// PRESCRIPTIONS LIST - VIEW ALL PRESCRIPTIONS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Admin Only
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

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
$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// BUILD QUERY WITH FILTERS
// ================================================================
$where_clause = " WHERE 1=1";
$params = [];

// Search filter
if (!empty($search)) {
    $where_clause .= " AND (p.prescription_number LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter
if (!empty($status_filter)) {
    $where_clause .= " AND p.status = ?";
    $params[] = $status_filter;
}

// Branch filter
if ($selected_branch_id !== 'all') {
    $where_clause .= " AND p.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// GET PRESCRIPTIONS WITH PAGINATION
// ================================================================

// Get total count
$count_sql = "
    SELECT COUNT(*) as total 
    FROM prescriptions p
    INNER JOIN patients pat ON p.patient_id = pat.id
    $where_clause
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_prescriptions / $per_page);

// Get prescriptions for current page
$sql = "
    SELECT p.*, 
           pat.full_name as patient_name, pat.patient_id as patient_number,
           d.full_name as doctor_name,
           ph.full_name as pharmacy_name,
           b.name as branch_name,
           (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as total_items,
           (SELECT COALESCE(SUM(total_price), 0) FROM prescription_items WHERE prescription_id = p.id) as total_amount,
           CASE 
               WHEN p.status = 'pending' THEN 'warning'
               WHEN p.status = 'confirmed' THEN 'info'
               WHEN p.status = 'dispensed' THEN 'success'
               WHEN p.status = 'cancelled' THEN 'danger'
               ELSE 'secondary'
           END as status_color
    FROM prescriptions p
    INNER JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users d ON p.doctor_id = d.id
    LEFT JOIN users ph ON p.pharmacy_id = ph.id
    LEFT JOIN branches b ON p.branch_id = b.id
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
// GET STATISTICS
// ================================================================

// Total prescriptions
$stmt = $db->query("SELECT COUNT(*) as total FROM prescriptions");
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total amount of all prescriptions
$stmt = $db->query("
    SELECT COALESCE(SUM(pi.total_price), 0) as total_amount 
    FROM prescription_items pi
    INNER JOIN prescriptions p ON pi.prescription_id = p.id
    WHERE p.status != 'cancelled'
");
$total_amount_all = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Pending prescriptions
$stmt = $db->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'pending'");
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pending amount
$stmt = $db->query("
    SELECT COALESCE(SUM(pi.total_price), 0) as total_amount 
    FROM prescription_items pi
    INNER JOIN prescriptions p ON pi.prescription_id = p.id
    WHERE p.status = 'pending'
");
$pending_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Confirmed prescriptions
$stmt = $db->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'confirmed'");
$confirmed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Confirmed amount
$stmt = $db->query("
    SELECT COALESCE(SUM(pi.total_price), 0) as total_amount 
    FROM prescription_items pi
    INNER JOIN prescriptions p ON pi.prescription_id = p.id
    WHERE p.status = 'confirmed'
");
$confirmed_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Dispensed prescriptions
$stmt = $db->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'dispensed'");
$dispensed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Dispensed amount
$stmt = $db->query("
    SELECT COALESCE(SUM(pi.total_price), 0) as total_amount 
    FROM prescription_items pi
    INNER JOIN prescriptions p ON pi.prescription_id = p.id
    WHERE p.status = 'dispensed'
");
$dispensed_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Cancelled prescriptions
$stmt = $db->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'cancelled'");
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL STYLES
       ================================================================ */
    
    /* Stat Cards */
    .stat-card-mini {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .stat-card-mini:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #7B2FBE;
    }
    
    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #7B2FBE;
    }
    
    .stat-card-mini .stat-number.green { color: #059669; }
    .stat-card-mini .stat-number.orange { color: #F59E0B; }
    .stat-card-mini .stat-number.blue { color: #0B5ED7; }
    .stat-card-mini .stat-number.red { color: #EF4444; }
    .stat-card-mini .stat-number.purple { color: #7B2FBE; }
    
    .stat-card-mini .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .stat-card-mini .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }
    
    .stat-card-mini .stat-amount {
        font-size: 0.8rem;
        font-weight: 600;
        color: #7B2FBE;
        margin-top: 2px;
    }
    
    .stat-card-mini .stat-amount.green { color: #059669; }
    .stat-card-mini .stat-amount.orange { color: #F59E0B; }
    .stat-card-mini .stat-amount.blue { color: #0B5ED7; }
    .stat-card-mini .stat-amount.red { color: #EF4444; }
    
    [data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-card-mini:hover {
        border-color: #7B2FBE;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #A78BFA;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-amount {
        color: #A78BFA;
    }
    
    /* Table Container with Arrows */
    .table-container {
        position: relative;
        overflow: hidden;
        border-radius: 12px;
    }
    
    .table-scroll-wrapper {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
        position: relative;
    }
    
    .table-scroll-wrapper::-webkit-scrollbar {
        height: 6px;
    }
    
    .table-scroll-wrapper::-webkit-scrollbar-track {
        background: var(--bg-body);
        border-radius: 10px;
    }
    
    .table-scroll-wrapper::-webkit-scrollbar-thumb {
        background: #7B2FBE;
        border-radius: 10px;
    }
    
    /* Scroll Buttons (Arrows) */
    .scroll-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: #7B2FBE;
        color: white;
        font-size: 0.9rem;
        cursor: pointer;
        z-index: 10;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(123, 47, 190, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
    }
    
    .scroll-btn:hover {
        background: #6B21A8;
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 6px 20px rgba(123, 47, 190, 0.4);
    }
    
    .scroll-btn:active {
        transform: translateY(-50%) scale(0.95);
    }
    
    .scroll-btn.show {
        opacity: 1;
        visibility: visible;
    }
    
    .scroll-btn-left {
        left: 8px;
    }
    
    .scroll-btn-right {
        right: 8px;
    }
    
    [data-theme="dark"] .scroll-btn {
        background: #7B2FBE;
        box-shadow: 0 4px 12px rgba(123, 47, 190, 0.3);
    }
    
    [data-theme="dark"] .scroll-btn:hover {
        background: #6B21A8;
    }
    
    /* Table Header - Purple Theme */
    .table-purple thead th {
        background: linear-gradient(135deg, #7B2FBE, #6B21A8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.65rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 10px 14px !important;
        border-bottom: 3px solid #6B21A8 !important;
        white-space: nowrap !important;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    
    .table-purple thead th:first-child {
        border-radius: 8px 0 0 0 !important;
    }
    
    .table-purple thead th:last-child {
        border-radius: 0 8px 0 0 !important;
    }
    
    .table-purple tbody td {
        padding: 8px 14px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
        font-size: 0.82rem;
        white-space: nowrap;
    }
    
    .table-purple tbody tr:hover td {
        background: #F3E8FF !important;
    }
    
    [data-theme="dark"] .table-purple tbody td {
        color: #F1F5F9 !important;
        border-bottom-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-purple tbody tr:hover td {
        background: #2A1A3A !important;
    }
    
    /* Items Column - Black Color */
    .items-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
        color: #FFFFFF !important;
        background: #1A1A1A !important;
        border: 1px solid #333333;
    }
    
    [data-theme="dark"] .items-count {
        background: #000000 !important;
        border-color: #444444;
        color: #FFFFFF !important;
    }
    
    /* Table Header with Search and Scroll Controls */
    .table-header-wrapper {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .table-header-wrapper .search-box {
        position: relative;
        flex: 1;
        min-width: 200px;
        max-width: 350px;
    }
    
    .table-header-wrapper .search-box input {
        width: 100%;
        padding: 8px 16px 8px 38px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        background: #FFFFFF !important;
        color: #1E293B !important;
        outline: none;
    }
    
    .table-header-wrapper .search-box input:focus {
        border-color: #7B2FBE;
        box-shadow: 0 0 0 3px rgba(123, 47, 190, 0.15);
    }
    
    [data-theme="dark"] .table-header-wrapper .search-box input {
        background: #1E293B !important;
        color: #F1F5F9 !important;
        border-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-header-wrapper .search-box input:focus {
        border-color: #A78BFA;
        box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.15);
    }
    
    .table-header-wrapper .search-box .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .table-header-wrapper .search-info {
        font-size: 0.8rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    
    .table-header-wrapper .search-info strong {
        color: #7B2FBE;
    }
    
    [data-theme="dark"] .table-header-wrapper .search-info strong {
        color: #A78BFA;
    }
    
    /* Scroll Controls in Header */
    .scroll-controls {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-left: auto;
    }
    
    .scroll-btn-header {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: linear-gradient(135deg, #7B2FBE, #6B21A8);
        color: white;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(123, 47, 190, 0.25);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .scroll-btn-header:hover {
        background: linear-gradient(135deg, #6B21A8, #5B1A8A);
        transform: scale(1.05);
        box-shadow: 0 4px 14px rgba(123, 47, 190, 0.35);
    }
    
    .scroll-btn-header:active {
        transform: scale(0.95);
    }
    
    .scroll-btn-header:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none !important;
    }
    
    .scroll-btn-header:disabled:hover {
        box-shadow: 0 2px 8px rgba(123, 47, 190, 0.25);
        transform: none !important;
    }
    
    [data-theme="dark"] .scroll-btn-header {
        background: linear-gradient(135deg, #7B2FBE, #6B21A8);
        box-shadow: 0 2px 8px rgba(123, 47, 190, 0.3);
    }
    
    [data-theme="dark"] .scroll-btn-header:hover {
        background: linear-gradient(135deg, #6B21A8, #5B1A8A);
        box-shadow: 0 4px 14px rgba(123, 47, 190, 0.4);
    }
    
    .scroll-indicator {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
        padding: 0 4px;
    }
    
    /* Status Badges */
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
    
    /* Filter Buttons */
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
    
    .filter-btn:hover {
        border-color: #7B2FBE;
        color: #7B2FBE;
        background: #F3E8FF;
    }
    
    .filter-btn.active {
        background: #7B2FBE;
        color: white;
        border-color: #7B2FBE;
    }
    
    .filter-btn.active:hover {
        background: #6B21A8;
        border-color: #6B21A8;
    }
    
    [data-theme="dark"] .filter-btn:hover {
        background: #2A1A3A;
        border-color: #7B2FBE;
        color: #A78BFA;
    }
    
    .filter-btn i { margin-right: 4px; }
    
    /* Filter Section */
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
    
    .filter-section .filter-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-right: 4px;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.6rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    
    .btn-action i { font-size: 0.65rem; }
    
    .btn-view {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .btn-view:hover {
        background: #0B5ED7;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
    }
    
    .btn-edit {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .btn-edit:hover {
        background: #D97706;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
    }
    
    .btn-dispense {
        background: #D1FAE5;
        color: #059669;
    }
    
    .btn-dispense:hover {
        background: #059669;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
    }
    
    .btn-delete {
        background: #FEE2E2;
        color: #EF4444;
    }
    
    .btn-delete:hover {
        background: #EF4444;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    
    [data-theme="dark"] .btn-view {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    [data-theme="dark"] .btn-view:hover {
        background: #0B5ED7;
        color: white;
    }
    
    [data-theme="dark"] .btn-edit {
        background: #3A2A1A;
        color: #FBBF24;
    }
    [data-theme="dark"] .btn-edit:hover {
        background: #D97706;
        color: white;
    }
    
    [data-theme="dark"] .btn-dispense {
        background: #1A3A2A;
        color: #34D399;
    }
    [data-theme="dark"] .btn-dispense:hover {
        background: #059669;
        color: white;
    }
    
    [data-theme="dark"] .btn-delete {
        background: #3A1A1A;
        color: #F87171;
    }
    [data-theme="dark"] .btn-delete:hover {
        background: #EF4444;
        color: white;
    }
    
    /* Card */
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
    }
    
    .card:hover {
        border-color: #7B2FBE;
        box-shadow: 0 4px 12px rgba(123, 47, 190, 0.05);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .title-purple { color: #7B2FBE; }
    
    /* Pagination */
    .pagination {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
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
    
    .pagination .page-link:hover {
        background: #7B2FBE;
        color: white;
        border-color: #7B2FBE;
    }
    
    .pagination .page-link.active {
        background: #7B2FBE;
        color: white;
        border-color: #7B2FBE;
    }
    
    .pagination .page-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    [data-theme="dark"] .pagination .page-link {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .pagination .page-link:hover {
        background: #7B2FBE;
        border-color: #7B2FBE;
    }
    
    /* Badge for items */
    .badge {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .badge-info {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .badge-info {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .stat-card-mini .stat-number {
            font-size: 1.4rem;
        }
        .filter-section {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-section .filter-label {
            margin-bottom: 4px;
        }
        .table-purple tbody td {
            font-size: 0.7rem;
            padding: 6px 10px !important;
        }
        .btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .table-header-wrapper {
            flex-direction: column;
            align-items: stretch;
        }
        .table-header-wrapper .search-box {
            max-width: 100%;
        }
        .scroll-controls {
            margin-left: 0;
            justify-content: center;
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
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-prescription mr-2" style="color: #7B2FBE;"></i> Prescriptions
            </h1>
            <p class="page-subtitle">
                Manage all prescriptions in the system
                <span class="branch-tag ml-2" style="background: #7B2FBE;">
                    <i class="fas fa-prescription"></i> <?= $total_all ?> Total
                </span>
                <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                    <i class="fas fa-money-bill-wave mr-1"></i> TSh <?= number_format($total_amount_all) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">📋</div>
            <p class="stat-number"><?= $total_all ?></p>
            <p class="stat-label">Total</p>
            <p class="stat-amount">TSh <?= number_format($total_amount_all) ?></p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">⏳</div>
            <p class="stat-number orange"><?= $pending_count ?></p>
            <p class="stat-label">Pending</p>
            <p class="stat-amount orange">TSh <?= number_format($pending_amount) ?></p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">✅</div>
            <p class="stat-number blue"><?= $confirmed_count ?></p>
            <p class="stat-label">Confirmed</p>
            <p class="stat-amount blue">TSh <?= number_format($confirmed_amount) ?></p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">💊</div>
            <p class="stat-number green"><?= $dispensed_count ?></p>
            <p class="stat-label">Dispensed</p>
            <p class="stat-amount green">TSh <?= number_format($dispensed_amount) ?></p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">❌</div>
            <p class="stat-number red"><?= $cancelled_count ?></p>
            <p class="stat-label">Cancelled</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
        
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => '', 'page' => 1])) ?>" 
           class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'pending', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'confirmed', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'confirmed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Confirmed
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'dispensed', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'dispensed' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> Dispensed
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'cancelled', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'cancelled' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Cancelled
        </a>
        
        <?php if (!empty($search) || !empty($status_filter)): ?>
            <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="filter-btn" style="border-color: #EF4444; color: #EF4444;">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS LIST WITH SEARCH AND SCROLL CONTROLS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-purple mr-2"></i>
                Prescriptions List
                <span class="text-sm font-normal text-gray-400">(<?= $total_prescriptions ?> prescriptions)</span>
            </h3>
        </div>
        
        <!-- Table Header with Search and Scroll Controls -->
        <div class="table-header-wrapper">
            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="tableSearch" placeholder="Filter prescriptions in table..." onkeyup="filterTable()">
            </div>
            
            <!-- Search Info -->
            <div class="search-info">
                Showing <strong id="visibleCount"><?= count($prescriptions) ?></strong> of <strong id="totalCount"><?= $total_prescriptions ?></strong>
            </div>
            
            <!-- Scroll Controls (Arrows) -->
            <div class="scroll-controls">
                <button class="scroll-btn-header" id="scrollLeftBtn" onclick="scrollTable('left')" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="scroll-indicator">
                    <i class="fas fa-arrows-alt-h"></i>
                </span>
                <button class="scroll-btn-header" id="scrollRightBtn" onclick="scrollTable('right')" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <!-- Table Container with Scroll -->
        <div class="table-container">
            <div class="table-scroll-wrapper" id="tableScrollWrapper">
                <table class="data-table table-purple w-full" id="prescriptionsTable">
                    <thead>
                        <tr>
                            <th style="width: 50px; min-width: 50px;">#</th>
                            <th style="min-width: 140px;">Prescription #</th>
                            <th style="min-width: 140px;">Patient</th>
                            <th style="min-width: 120px;">Patient ID</th>
                            <th style="min-width: 120px;">Doctor</th>
                            <th style="min-width: 80px;">Items</th>
                            <th style="min-width: 100px;">Total</th>
                            <th style="min-width: 100px;">Status</th>
                            <th style="min-width: 100px;">Date</th>
                            <th style="min-width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if (count($prescriptions) > 0): ?>
                            <?php $i = $offset + 1; foreach ($prescriptions as $prescription): ?>
                                <tr>
                                    <td class="font-bold text-purple-600 dark:text-purple-400"><?= $i++ ?></td>
                                    <td class="font-mono text-xs font-bold"><?= htmlspecialchars($prescription['prescription_number']) ?></td>
                                    <td class="font-semibold"><?= htmlspecialchars($prescription['patient_name']) ?></td>
                                    <td class="font-mono text-xs"><?= htmlspecialchars($prescription['patient_number']) ?></td>
                                    <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="items-count"><?= $prescription['total_items'] ?? 0 ?></span>
                                    </td>
                                    <td class="font-bold">TSh <?= number_format($prescription['total_amount'] ?? 0) ?></td>
                                    <td>
                                        <span class="status-badge <?= $prescription['status_color'] ?? 'secondary' ?>">
                                            <?= ucfirst($prescription['status'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="text-xs"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- View -->
                                            <a href="prescription_details.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action btn-view" title="View Prescription">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <!-- Edit (only for pending) -->
                                            <?php if ($prescription['status'] === 'pending'): ?>
                                                <a href="edit_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                                   class="btn-action btn-edit" title="Edit Prescription">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Dispense (for pending or confirmed) -->
                                            <?php if ($prescription['status'] === 'pending' || $prescription['status'] === 'confirmed'): ?>
                                                <a href="dispense_prescription.php?id=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                                   class="btn-action btn-dispense" title="Dispense Prescription">
                                                    <i class="fas fa-check-circle"></i> Dispense
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Delete (only for pending) -->
                                            <?php if ($prescription['status'] === 'pending'): ?>
                                                <a href="?delete=<?= $prescription['id'] ?>&branch=<?= $selected_branch_id ?>&page=<?= $page ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                                                   class="btn-action btn-delete" 
                                                   onclick="return confirm('Are you sure you want to delete this prescription?')" 
                                                   title="Delete Prescription">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-8 text-gray-400">
                                    <i class="fas fa-prescription text-4xl block mb-3" style="color: #7B2FBE;"></i>
                                    <p class="text-lg font-medium" style="color: #1E293B; dark:text-white;">
                                        <?= !empty($search) || !empty($status_filter) ? 'No prescriptions found matching your filters' : 'No prescriptions found' ?>
                                    </p>
                                    <p class="text-sm">
                                        <?= !empty($search) || !empty($status_filter) ? 'Try changing your search or filter criteria' : 'No prescriptions have been created yet' ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- PAGINATION -->
        <!-- ================================================================ -->
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
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
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
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
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
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // TABLE SEARCH FILTER (Real-time)
    // ================================================================
    function filterTable() {
        var input = document.getElementById('tableSearch');
        var filter = input.value.toLowerCase();
        var table = document.getElementById('prescriptionsTable');
        var rows = table.getElementsByTagName('tr');
        var visibleCount = 0;
        
        for (var i = 1; i < rows.length; i++) {
            var row = rows[i];
            var text = row.textContent.toLowerCase();
            if (text.includes(filter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
        
        document.getElementById('visibleCount').textContent = visibleCount;
    }

    // ================================================================
    // TABLE SCROLL WITH ARROWS
    // ================================================================
    function scrollTable(direction) {
        var wrapper = document.getElementById('tableScrollWrapper');
        var scrollAmount = 350;
        
        if (direction === 'left') {
            wrapper.scrollLeft -= scrollAmount;
        } else {
            wrapper.scrollLeft += scrollAmount;
        }
        
        updateScrollButtons();
    }
    
    function updateScrollButtons() {
        var wrapper = document.getElementById('tableScrollWrapper');
        var leftBtn = document.getElementById('scrollLeftBtn');
        var rightBtn = document.getElementById('scrollRightBtn');
        
        if (wrapper.scrollLeft <= 10) {
            leftBtn.disabled = true;
        } else {
            leftBtn.disabled = false;
        }
        
        if (wrapper.scrollLeft >= wrapper.scrollWidth - wrapper.clientWidth - 10) {
            rightBtn.disabled = true;
        } else {
            rightBtn.disabled = false;
        }
    }
    
    // Show/hide scroll buttons based on scroll position
    document.getElementById('tableScrollWrapper')?.addEventListener('scroll', updateScrollButtons);
    
    // Initial check for scroll buttons
    window.addEventListener('load', function() {
        setTimeout(updateScrollButtons, 200);
    });
    
    // Update on resize
    window.addEventListener('resize', updateScrollButtons);

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('page');
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

    console.log('%c🏥 Braick Dispensary - Prescriptions Management', 'font-size:18px; font-weight:bold; color:#7B2FBE;');
    console.log('%c💊 Total Prescriptions: <?= $total_all ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Amount: TSh <?= number_format($total_amount_all) ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c🎨 Items column: Black color', 'font-size:13px; color:#1A1A1A;');
    console.log('%c⬅️➡️ Scroll arrows on header', 'font-size:13px; color:#7B2FBE;');
    console.log('%c🔍 Real-time table search filter enabled', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>