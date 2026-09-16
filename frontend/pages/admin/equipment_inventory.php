<?php
// ================================================================
// FILE: frontend/pages/admin/equipment_inventory.php
// ADMIN - EQUIPMENT INVENTORY MANAGEMENT
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ✅ Clickable filter cards + table scroll arrows
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

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA
// ================================================================
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
// GET FILTER PARAMETERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$branch_id_for_query = null;
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id_for_query = (int)$selected_branch_id;
}

// ================================================================
// GET ALL EQUIPMENT
// ================================================================
$equipment_items = [];
$total_items = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;
$expire_soon_count = 0;
$expired_count = 0;
$linked_count = 0;

try {
    $query = "
        SELECT 
            e.id,
            e.equipment_name,
            e.category,
            e.unit,
            e.quantity,
            e.reorder_level,
            e.selling_price,
            e.supplier,
            e.expiry_date,
            e.batch_number,
            e.branch_id,
            e.status,
            e.created_at,
            e.updated_at,
            b.name as branch_name,
            (SELECT COUNT(*) FROM lab_test_equipment WHERE equipment_id = e.id) as linked_count
        FROM medical_equipment e
        LEFT JOIN branches b ON e.branch_id = b.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($branch_id_for_query !== null && is_numeric($branch_id_for_query)) {
        $query .= " AND (e.branch_id = ? OR e.branch_id IS NULL)";
        $params[] = $branch_id_for_query;
    }
    
    if ($status_filter !== 'all' && in_array($status_filter, ['active', 'inactive'])) {
        $query .= " AND e.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (e.equipment_name LIKE ? OR e.category LIKE ? OR e.batch_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY e.equipment_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $equipment_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $today = date('Y-m-d');
    $thirty_days = date('Y-m-d', strtotime('+30 days'));
    
    foreach ($equipment_items as $item) {
        $total_items++;
        
        if ($item['quantity'] <= 0) {
            $out_of_stock_count++;
        } elseif ($item['quantity'] <= $item['reorder_level']) {
            $low_stock_count++;
        }
        
        if (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00') {
            if ($item['expiry_date'] < $today) {
                $expired_count++;
            } elseif ($item['expiry_date'] <= $thirty_days) {
                $expire_soon_count++;
            }
        }
        
        if ($item['linked_count'] > 0) {
            $linked_count++;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching equipment: " . $e->getMessage());
    $equipment_items = [];
}

// ================================================================
// APPLY FILTER
// ================================================================
$filtered_items = [];
$today = date('Y-m-d');
$thirty_days = date('Y-m-d', strtotime('+30 days'));

foreach ($equipment_items as $item) {
    $include = false;
    
    switch ($filter) {
        case 'all': $include = true; break;
        case 'low_stock': $include = ($item['quantity'] > 0 && $item['quantity'] <= $item['reorder_level']); break;
        case 'out_of_stock': $include = ($item['quantity'] <= 0); break;
        case 'expire_soon':
            $include = (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00' && 
                       $item['expiry_date'] >= $today && $item['expiry_date'] <= $thirty_days);
            break;
        case 'expired':
            $include = (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00' && 
                       $item['expiry_date'] < $today);
            break;
        case 'linked': $include = ($item['linked_count'] > 0); break;
        default: $include = true;
    }
    
    if ($include) $filtered_items[] = $item;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*)
     ================================================================ -->
<style>
    /* ================================================================
       PAGE HEADER - BLUE BACKGROUND
       ================================================================ */
    .page-header-eq {
        background: linear-gradient(135deg, #0A4CA8, #073B8A);
        border-radius: 18px;
        padding: 28px 36px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-eq::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-eq .page-title-eq {
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

    .page-header-eq .page-title-eq i { font-size: 2rem; opacity: 0.9; }

    .page-header-eq .page-subtitle-eq {
        color: rgba(255,255,255,0.85);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-eq .role-badge-display {
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

    .page-header-eq .header-badge-eq {
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

    .page-header-eq .btn-outline-light-eq {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
    }

    .page-header-eq .btn-outline-light-eq:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        color: white;
    }

    /* ================================================================
       FILTER CARDS - CLICKABLE
       ================================================================ */
    .filter-cards-eq {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }

    .filter-card-eq {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px;
        padding: 14px 16px;
        border: 3px solid var(--page-border, #E2E8F0);
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        position: relative;
        overflow: hidden;
        text-align: center;
    }

    .filter-card-eq::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        transition: height 0.3s ease;
    }

    .filter-card-eq:hover {
        transform: translateY(-4px);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .filter-card-eq.active {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.2);
        transform: translateY(-4px);
    }

    .filter-card-eq.active::before { height: 5px; }

    .filter-card-eq .card-icon-eq {
        font-size: 1.3rem;
        margin-bottom: 2px;
        display: block;
    }

    .filter-card-eq .card-count-eq {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--page-text-primary, #1E293B);
        line-height: 1.2;
    }

    .filter-card-eq .card-label-eq {
        font-size: 0.6rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .filter-card-eq.all { border-color: var(--page-primary, #0B5ED7); }
    .filter-card-eq.all::before { background: linear-gradient(135deg, #0A4CA8, #073B8A); }
    .filter-card-eq.all .card-icon-eq { color: var(--page-primary, #0B5ED7); }

    .filter-card-eq.low_stock { border-color: #D97706; }
    .filter-card-eq.low_stock::before { background: #D97706; }
    .filter-card-eq.low_stock .card-icon-eq { color: #D97706; }

    .filter-card-eq.out_of_stock { border-color: #DC2626; }
    .filter-card-eq.out_of_stock::before { background: #DC2626; }
    .filter-card-eq.out_of_stock .card-icon-eq { color: #DC2626; }

    .filter-card-eq.expire_soon { border-color: #D97706; }
    .filter-card-eq.expire_soon::before { background: #D97706; }
    .filter-card-eq.expire_soon .card-icon-eq { color: #D97706; }

    .filter-card-eq.expired { border-color: #DC2626; }
    .filter-card-eq.expired::before { background: #DC2626; }
    .filter-card-eq.expired .card-icon-eq { color: #DC2626; }

    .filter-card-eq.linked { border-color: #7C3AED; }
    .filter-card-eq.linked::before { background: #7C3AED; }
    .filter-card-eq.linked .card-icon-eq { color: #7C3AED; }

    /* ================================================================
       FILTER BAR
       ================================================================ */
    .filter-bar-eq {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 24px;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        position: relative;
        overflow: hidden;
    }

    .filter-bar-eq::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, #0A4CA8, #073B8A);
    }

    .filter-bar-eq .filter-label-eq {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--page-primary, #0B5ED7);
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .filter-bar-eq select,
    .filter-bar-eq input[type="text"] {
        background: var(--page-hover, #F8FAFC);
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 0.78rem;
        color: var(--page-text-primary, #1E293B);
        outline: none;
        transition: all 0.3s;
        font-family: inherit;
    }

    [data-theme="dark"] .filter-bar-eq select,
    [data-theme="dark"] .filter-bar-eq input[type="text"] {
        background: #0F172A;
    }

    .filter-bar-eq select:focus,
    .filter-bar-eq input:focus {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.15);
    }

    .filter-bar-eq .btn-filter-eq {
        background: linear-gradient(135deg, #0A4CA8, #073B8A);
        color: white;
        border: none;
        padding: 7px 18px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.72rem;
        cursor: pointer;
        transition: all 0.3s;
        font-family: inherit;
    }

    .filter-bar-eq .btn-filter-eq:hover {
        transform: scale(1.03);
        box-shadow: 0 4px 16px rgba(10, 76, 168, 0.35);
    }

    .filter-bar-eq .btn-reset-eq {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.72rem;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
    }

    .filter-bar-eq .btn-reset-eq:hover {
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    /* ================================================================
       CARD
       ================================================================ */
    .card-eq {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 20px 24px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        margin-bottom: 24px;
    }

    .card-eq:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: var(--page-shadow-md, 0 4px 12px rgba(0,0,0,0.08));
    }

    .card-header-eq {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .card-title-eq {
        font-size: 1rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .card-title-eq i { color: var(--page-primary, #0B5ED7); }

    /* Scroll Arrow Buttons */
    .scroll-arrow-btn-eq {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: 2px solid var(--page-border, #E2E8F0);
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-secondary, #64748B);
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
    }

    .scroll-arrow-btn-eq:hover {
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
        background: #E8F0FE;
        transform: scale(1.05);
    }

    [data-theme="dark"] .scroll-arrow-btn-eq {
        background: #1E293B;
    }

    [data-theme="dark"] .scroll-arrow-btn-eq:hover {
        background: #1E3A5F;
    }

    /* ================================================================
       TABLE SCROLL CONTAINER
       ================================================================ */
    .table-scroll-eq {
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid var(--page-border, #E2E8F0);
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    .table-scroll-eq::-webkit-scrollbar { height: 6px; }
    .table-scroll-eq::-webkit-scrollbar-track { background: var(--page-hover); border-radius: 10px; }
    .table-scroll-eq::-webkit-scrollbar-thumb { background: var(--page-primary); border-radius: 10px; }

    .table-scroll-eq table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 1100px;
        white-space: nowrap;
    }

    .table-scroll-eq thead {
        background: linear-gradient(135deg, #0A4CA8, #073B8A);
        color: #ffffff;
    }

    .table-scroll-eq thead th {
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }

    .table-scroll-eq thead th i { margin-right: 6px; opacity: 0.8; }

    .table-scroll-eq tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }

    .table-scroll-eq tbody tr:last-child { border-bottom: none; }
    .table-scroll-eq tbody tr:hover { background: #E8F0FE; }
    [data-theme="dark"] .table-scroll-eq tbody tr:hover { background: #1E3A5F; }

    .table-scroll-eq tbody td {
        padding: 10px 16px;
        vertical-align: middle;
        color: var(--page-text-primary, #1E293B);
        white-space: nowrap;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .status-badge-eq {
        display: inline-block;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }

    .status-badge-eq.active { background: #D1FAE5; color: #059669; }
    .status-badge-eq.inactive { background: #FEE2E2; color: #DC2626; }

    [data-theme="dark"] .status-badge-eq.active { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-eq.inactive { background: #3A1A1A; color: #F87171; }

    .stock-badge-eq {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .stock-badge-eq.ok { background: #D1FAE5; color: #059669; }
    .stock-badge-eq.low { background: #FEF3C7; color: #D97706; }
    .stock-badge-eq.out { background: #FEE2E2; color: #DC2626; }

    [data-theme="dark"] .stock-badge-eq.ok { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .stock-badge-eq.low { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .stock-badge-eq.out { background: #3A1A1A; color: #F87171; }

    .expiry-badge-eq {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .expiry-badge-eq.valid { background: #D1FAE5; color: #059669; }
    .expiry-badge-eq.expiring { background: #FEF3C7; color: #D97706; }
    .expiry-badge-eq.expired { background: #FEE2E2; color: #DC2626; }
    .expiry-badge-eq.no-expiry { background: var(--page-hover); color: var(--page-text-secondary); }

    [data-theme="dark"] .expiry-badge-eq.valid { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .expiry-badge-eq.expiring { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .expiry-badge-eq.expired { background: #3A1A1A; color: #F87171; }

    .branch-tag-eq {
        display: inline-block;
        background: #E8F0FE;
        color: #0B5ED7;
        padding: 1px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
    }

    [data-theme="dark"] .branch-tag-eq { background: #1E3A5F; color: #6EA8FE; }

    .branch-tag-eq.all-branches { background: #FEF3C7; color: #D97706; }
    [data-theme="dark"] .branch-tag-eq.all-branches { background: #3D2E0A; color: #FBBF24; }

    .batch-number-eq {
        font-family: monospace;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 1px 8px;
        border-radius: 4px;
        background: #E8F0FE;
        color: #0B5ED7;
    }

    [data-theme="dark"] .batch-number-eq { background: #1E3A5F; color: #6EA8FE; }

    .price-display-eq {
        font-weight: 600;
        color: var(--page-primary, #0B5ED7);
    }

    [data-theme="dark"] .price-display-eq { color: #6EA8FE; }

    .linked-badge-eq {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #EDE9FE;
        color: #7C3AED;
    }

    [data-theme="dark"] .linked-badge-eq { background: #2D1B4E; color: #A78BFA; }

    /* Action Buttons */
    .action-btns-eq {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .btn-icon-eq {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        text-decoration: none;
    }

    .btn-icon-eq:hover { transform: scale(1.1); }

    .btn-icon-eq.view { background: #E8F0FE; color: #0B5ED7; }
    .btn-icon-eq.view:hover { background: #0B5ED7; color: white; }
    .btn-icon-eq.edit { background: #FEF3C7; color: #D97706; }
    .btn-icon-eq.edit:hover { background: #D97706; color: white; }
    .btn-icon-eq.delete { background: #FEE2E2; color: #DC2626; }
    .btn-icon-eq.delete:hover { background: #DC2626; color: white; }

    /* Empty State */
    .empty-state-eq {
        text-align: center;
        padding: 40px 20px;
        color: var(--page-text-secondary, #64748B);
    }

    .empty-state-eq i {
        font-size: 3rem;
        color: var(--page-text-muted, #94A3B8);
        display: block;
        margin-bottom: 12px;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-eq {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-eq .footer-brand-eq {
        color: var(--page-primary, #0B5ED7);
        font-weight: 700;
    }

    /* ================================================================
       MODAL
       ================================================================ */
    .modal-overlay-eq {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        backdrop-filter: blur(4px);
        align-items: center;
        justify-content: center;
    }

    .modal-overlay-eq.show { display: flex; }

    .modal-content-eq {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        max-width: 450px;
        width: 95%;
        padding: 30px 35px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: modalSlideInEq 0.3s ease;
    }

    @keyframes modalSlideInEq {
        from { transform: translateY(-30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header-eq {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        padding-bottom: 16px;
        margin-bottom: 20px;
    }

    .modal-header-eq h2 {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0;
    }

    .modal-header-eq h2 i { color: #DC2626; }

    .modal-close-eq {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: #FEE2E2;
        color: #DC2626;
        cursor: pointer;
        font-size: 1rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-close-eq:hover {
        background: #DC2626;
        color: white;
        transform: rotate(90deg);
    }

    .modal-actions-eq {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        justify-content: flex-end;
        border-top: 2px solid var(--page-border, #E2E8F0);
        padding-top: 16px;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-eq { padding: 16px 18px; }
        .page-header-eq .page-title-eq { font-size: 1.3rem; }
        .filter-cards-eq { grid-template-columns: repeat(2, 1fr); }
        .filter-bar-eq { flex-direction: column; align-items: stretch; }
        .card-header-eq { flex-direction: column; align-items: flex-start !important; }
        .table-scroll-eq table { min-width: 900px; font-size: 0.75rem; }
        .table-scroll-eq thead th,
        .table-scroll-eq tbody td { padding: 8px 10px; }
    }

    @media (max-width: 480px) {
        .filter-cards-eq { grid-template-columns: 1fr; }
        .page-header-eq { flex-direction: column; align-items: flex-start !important; }
        .table-scroll-eq table { min-width: 750px; font-size: 0.7rem; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-eq">
        <div>
            <h1 class="page-title-eq">
                <i class="fas fa-tools"></i>
                Equipment Inventory                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle-eq">
                <i class="fas fa-list"></i>
                <strong><?= count($filtered_items) ?></strong> equipment items found
                <span class="header-badge-eq">
                    <i class="fas fa-boxes"></i> <?= number_format($total_items) ?> Total
                </span>
                <?php if ($filter !== 'all'): ?>
                    <span class="header-badge-eq" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                        <i class="fas fa-filter"></i> <?= ucfirst(str_replace('_', ' ', $filter)) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="add_equipment.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light-eq">
                <i class="fas fa-plus"></i> Add Equipment
            </a>
            <a href="services.php?branch=<?= urlencode($selected_branch_id) ?>&tab=equipment" class="btn-outline-light-eq">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER CARDS -->
    <!-- ================================================================ -->
    <div class="filter-cards-eq">
        <div class="filter-card-eq all <?= $filter === 'all' ? 'active' : '' ?>" onclick="applyFilter('all')">
            <span class="card-icon-eq"><i class="fas fa-boxes"></i></span>
            <div class="card-count-eq"><?= $total_items ?></div>
            <div class="card-label-eq">All Equipment</div>
        </div>
        
        <div class="filter-card-eq low_stock <?= $filter === 'low_stock' ? 'active' : '' ?>" onclick="applyFilter('low_stock')">
            <span class="card-icon-eq"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="card-count-eq"><?= $low_stock_count ?></div>
            <div class="card-label-eq">Low Stock</div>
        </div>
        
        <div class="filter-card-eq out_of_stock <?= $filter === 'out_of_stock' ? 'active' : '' ?>" onclick="applyFilter('out_of_stock')">
            <span class="card-icon-eq"><i class="fas fa-times-circle"></i></span>
            <div class="card-count-eq"><?= $out_of_stock_count ?></div>
            <div class="card-label-eq">Out of Stock</div>
        </div>
        
        <div class="filter-card-eq expire_soon <?= $filter === 'expire_soon' ? 'active' : '' ?>" onclick="applyFilter('expire_soon')">
            <span class="card-icon-eq"><i class="fas fa-clock"></i></span>
            <div class="card-count-eq"><?= $expire_soon_count ?></div>
            <div class="card-label-eq">Expire Soon</div>
        </div>
        
        <div class="filter-card-eq expired <?= $filter === 'expired' ? 'active' : '' ?>" onclick="applyFilter('expired')">
            <span class="card-icon-eq"><i class="fas fa-skull-crossbones"></i></span>
            <div class="card-count-eq"><?= $expired_count ?></div>
            <div class="card-label-eq">Expired</div>
        </div>
        
        <div class="filter-card-eq linked <?= $filter === 'linked' ? 'active' : '' ?>" onclick="applyFilter('linked')">
            <span class="card-icon-eq"><i class="fas fa-link"></i></span>
            <div class="card-count-eq"><?= $linked_count ?></div>
            <div class="card-label-eq">Linked to Lab Tests</div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER BAR -->
    <!-- ================================================================ -->
    <div class="filter-bar-eq">
        <span class="filter-label-eq"><i class="fas fa-filter"></i> Filter</span>
        
        <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;width:100%;">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            
            <select name="status" style="flex:1;min-width:120px;">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            
            <input type="text" name="search" placeholder="Search equipment..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:180px;">
            
            <button type="submit" class="btn-filter-eq">
                <i class="fas fa-search"></i> Apply
            </button>
            
            <a href="equipment_inventory.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-reset-eq">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- EQUIPMENT TABLE -->
    <!-- ================================================================ -->
    <div class="card-eq">
        <div class="card-header-eq">
            <div style="display:flex;flex-direction:column;gap:2px;">
                <h3 class="card-title-eq">
                    <i class="fas fa-list"></i> 
                    Equipment List
                    <span style="font-size:0.7rem;font-weight:400;color:var(--page-text-secondary);">
                        (<?= count($filtered_items) ?> items)
                    </span>
                </h3>
                <div style="font-size:0.7rem;color:var(--page-text-secondary);">
                    <i class="fas fa-info-circle"></i> Click cards above to filter 
                    <span style="margin:0 6px;">|</span> 
                    <i class="fas fa-arrows-alt-h"></i> Use arrows to scroll
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <button onclick="scrollTableEq('left')" class="scroll-arrow-btn-eq" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button onclick="scrollTableEq('right')" class="scroll-arrow-btn-eq" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll-eq" id="equipmentTableScroll">
            <?php if (count($filtered_items) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><i class="fas fa-tools"></i> Equipment</th>
                            <th><i class="fas fa-tag"></i> Category</th>
                            <th><i class="fas fa-cube"></i> Unit</th>
                            <th><i class="fas fa-store-alt"></i> Branch</th>
                            <th><i class="fas fa-boxes"></i> Qty</th>
                            <th><i class="fas fa-chart-line"></i> Stock</th>
                            <th><i class="fas fa-tag"></i> Price</th>
                            <th><i class="fas fa-truck"></i> Supplier</th>
                            <th><i class="fas fa-calendar"></i> Expiry</th>
                            <th><i class="fas fa-link"></i> Linked</th>
                            <th><i class="fas fa-circle"></i> Status</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($filtered_items as $item): 
                            $stock_status = 'ok';
                            if ($item['quantity'] <= 0) {
                                $stock_status = 'out';
                            } elseif ($item['quantity'] <= $item['reorder_level']) {
                                $stock_status = 'low';
                            }
                            
                            $expiry_status = 'no-expiry';
                            $expiry_label = 'No Expiry';
                            if (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00') {
                                $days_left = floor((strtotime($item['expiry_date']) - time()) / 86400);
                                if ($days_left < 0) {
                                    $expiry_status = 'expired';
                                    $expiry_label = 'Expired';
                                } elseif ($days_left <= 30) {
                                    $expiry_status = 'expiring';
                                    $expiry_label = 'Expiring Soon';
                                } else {
                                    $expiry_status = 'valid';
                                    $expiry_label = 'Valid';
                                }
                            }
                        ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['equipment_name']) ?></strong>
                                    <div style="font-size:0.6rem;color:var(--page-text-secondary);">
                                        Batch: <span class="batch-number-eq"><?= htmlspecialchars($item['batch_number']) ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></td>
                                <td>
                                    <?php if ($item['branch_id'] === null): ?>
                                        <span class="branch-tag-eq all-branches">🌐 All Branches</span>
                                    <?php else: ?>
                                        <span class="branch-tag-eq"><?= htmlspecialchars($item['branch_name'] ?? 'N/A') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;font-weight:700;">
                                    <?= number_format($item['quantity']) ?>
                                </td>
                                <td>
                                    <span class="stock-badge-eq <?= $stock_status ?>">
                                        <i class="fas <?= $stock_status === 'ok' ? 'fa-check-circle' : ($stock_status === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                        <?= ucfirst(str_replace('_', ' ', $stock_status)) ?>
                                    </span>
                                </td>
                                <td class="price-display-eq">
                                    <?= ($item['selling_price'] ?? 0) > 0 ? 'TSh ' . number_format($item['selling_price'], 0) : 'FREE' ?>
                                </td>
                                <td><?= htmlspecialchars($item['supplier'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="expiry-badge-eq <?= $expiry_status ?>">
                                        <i class="fas <?= $expiry_status === 'valid' ? 'fa-check' : ($expiry_status === 'expiring' ? 'fa-clock' : ($expiry_status === 'expired' ? 'fa-skull' : 'fa-infinity')) ?>"></i>
                                        <?= $expiry_label ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['linked_count'] > 0): ?>
                                        <span class="linked-badge-eq">
                                            <i class="fas fa-link"></i> <?= $item['linked_count'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:0.6rem;color:var(--page-text-secondary);">Not linked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge-eq <?= $item['status'] === 'active' ? 'active' : 'inactive' ?>">
                                        <?= ucfirst($item['status'] ?? 'active') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns-eq">
                                        <a href="view_equipment.php?id=<?= $item['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-icon-eq view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_equipment.php?id=<?= $item['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-icon-eq edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn-icon-eq delete" onclick="deleteEquipmentEq(<?= $item['id'] ?>, '<?= addslashes($item['equipment_name']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-eq">
                    <i class="fas fa-tools"></i>
                    <p>No equipment items found matching your filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-eq">
        <p>
            <span class="footer-brand-eq">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Equipment Inventory
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- DELETE CONFIRM MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay-eq" id="deleteModalEq">
    <div class="modal-content-eq">
        <div class="modal-header-eq">
            <h2>
                <i class="fas fa-trash"></i> Confirm Delete
            </h2>
            <button class="modal-close-eq" onclick="closeDeleteModalEq()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="delete_equipment.php">
            <input type="hidden" name="delete_id" id="deleteIdEq" value="">
            <p id="deleteMessageEq" style="margin-bottom:20px;font-size:1rem;">Are you sure you want to delete this equipment?</p>
            <div class="modal-actions-eq">
                <button type="button" class="btn-icon-eq" style="width:auto;padding:8px 20px;background:var(--page-hover);color:var(--page-text-secondary);border:2px solid var(--page-border);border-radius:10px;font-weight:600;" onclick="closeDeleteModalEq()">Cancel</button>
                <button type="submit" style="padding:8px 20px;background:#DC2626;color:white;border:none;border-radius:10px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT (NO dark mode, NO sidebar, NO date-time) -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // ✅ FOOTER TIME ONLY (header ina date/time yake)
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
    // ✅ APPLY FILTER
    // ================================================================
    function applyFilter(filter) {
        var branch = '<?= $selected_branch_id ?>';
        var status = '<?= $status_filter ?>';
        var search = '<?= addslashes($search) ?>';
        var url = 'equipment_inventory.php?branch=' + encodeURIComponent(branch) + '&filter=' + encodeURIComponent(filter) + '&status=' + encodeURIComponent(status);
        if (search.length > 0) {
            url += '&search=' + encodeURIComponent(search);
        }
        window.location.href = url;
    }

    // ================================================================
    // ✅ TABLE SCROLL
    // ================================================================
    function scrollTableEq(direction) {
        var container = document.getElementById('equipmentTableScroll');
        if (!container) return;
        
        var scrollAmount = container.clientWidth * 0.7;
        if (direction === 'left') {
            container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
    }

    // Keyboard shortcuts kwa table scroll
    document.addEventListener('keydown', function(e) {
        var container = document.getElementById('equipmentTableScroll');
        if (!container) return;
        
        var isFocused = container.matches(':hover') || container.matches(':focus-within');
        if (!isFocused) return;
        
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            scrollTableEq('left');
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            scrollTableEq('right');
        }
    });

    // ================================================================
    // ✅ DELETE EQUIPMENT
    // ================================================================
    function deleteEquipmentEq(id, name) {
        document.getElementById('deleteIdEq').value = id;
        document.getElementById('deleteMessageEq').textContent = 'Are you sure you want to delete "' + name + '"? This action cannot be undone.';
        document.getElementById('deleteModalEq').classList.add('show');
    }
    
    function closeDeleteModalEq() {
        document.getElementById('deleteModalEq').classList.remove('show');
    }
    
    document.getElementById('deleteModalEq')?.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });

    console.log('%c🔧 Braick - Equipment Inventory', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c📦 Total Equipment: <?= $total_items ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Cards are clickable filters', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Scroll arrows work with keyboard arrows too', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>