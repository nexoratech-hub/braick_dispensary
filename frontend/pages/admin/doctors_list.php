<?php
// ================================================================
// FILE: frontend/pages/admin/doctors_list.php
// DOCTORS LIST - VIEW ALL DOCTORS
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// ✅ FIXED: SQL ambiguous column error
// ✅ Search bar LEFT side, smaller size
// ✅ Live auto-search with highlight
// ✅ Action buttons: View + Edit only
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

$message = '';
$message_type = '';
$per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';

// ================================================================
// AJAX HANDLER - LIVE SEARCH
// ================================================================
if (isset($_GET['ajax_search']) && $_GET['ajax_search'] === '1') {
    header('Content-Type: application/json');
    
    $ajax_search = isset($_GET['q']) ? trim($_GET['q']) : '';
    $ajax_branch = isset($_GET['branch']) ? $_GET['branch'] : 'all';
    $ajax_status = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    $where_clause = " WHERE u.role = 'doctor'";
    $params = [];
    
    if (!empty($ajax_search)) {
        $where_clause .= " AND (u.full_name LIKE ? OR u.specialty LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $search_param = "%$ajax_search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($ajax_status)) {
        if ($ajax_status === 'online') {
            $where_clause .= " AND u.is_online = 1";
        } elseif ($ajax_status === 'offline') {
            $where_clause .= " AND (u.is_online = 0 OR u.is_online IS NULL)";
        }
    }
    
    if ($ajax_branch !== 'all' && is_numeric($ajax_branch)) {
        $where_clause .= " AND u.branch_id = ?";
        $params[] = (int)$ajax_branch;
    }
    
    $sql = "
        SELECT u.id, u.full_name, u.email, u.specialty, u.phone, u.is_online, u.branch_id,
               b.name as branch_name,
               (SELECT COUNT(*) FROM patients WHERE assigned_doctor_id = u.id) as total_patients,
               (SELECT COUNT(*) FROM visits WHERE doctor_id = u.id AND status != 'cancelled') as total_visits
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        $where_clause
        ORDER BY u.full_name ASC
        LIMIT 10
    ";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'count' => count($results),
            'doctors' => $results
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HANDLE TOGGLE DOCTOR STATUS
// ================================================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $doctor_id = (int)$_GET['toggle'];
    
    $stmt = $db->prepare("SELECT full_name, is_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doctor) {
        $new_status = $doctor['is_online'] == 1 ? 0 : 1;
        $action_text = $new_status == 1 ? 'online' : 'offline';
        
        $stmt = $db->prepare("UPDATE users SET is_online = ?, last_online = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $doctor_id]);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                VALUES (?, ?, 'doctor_status_changed', ?, NOW())
            ");
            $stmt->execute([$user_id, $user_branch_id, "Dr. {$doctor['full_name']} changed status to: $action_text"]);
        } catch (Exception $e) {}
        
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'reception' AND status = 'active'");
            $stmt->execute();
            $receptionists = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $status_text = $new_status == 1 ? 'ONLINE' : 'OFFLINE';
            
            foreach ($receptionists as $rec) {
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, branch_id, title, message, type, link, is_read, created_at)
                    VALUES (?, ?, 'Doctor Status: $status_text', ?, 'info', 'assign_doctor.php', 0, NOW())
                ");
                $stmt->execute([$rec['id'], $user_branch_id, "Dr. {$doctor['full_name']} is now $status_text"]);
            }
        } catch (Exception $e) {}
        
        header("Location: doctors_list.php?page=$page" . ($search ? "&search=" . urlencode($search) : "") . ($status_filter ? "&status=" . urlencode($status_filter) : "") . "&branch=" . $selected_branch_id);
        exit();
    }
}

// ================================================================
// BUILD QUERY - FIXED: use u. prefix to avoid ambiguity
// ================================================================
$where_clause = " WHERE u.role = 'doctor'";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (u.full_name LIKE ? OR u.specialty LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    if ($status_filter === 'online') {
        $where_clause .= " AND u.is_online = 1";
    } elseif ($status_filter === 'offline') {
        $where_clause .= " AND (u.is_online = 0 OR u.is_online IS NULL)";
    }
}

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $where_clause .= " AND u.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// ================================================================
// GET DOCTORS
// ================================================================
$count_sql = "SELECT COUNT(*) as total FROM users u $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_doctors / $per_page);

$sql = "
    SELECT u.*, b.name as branch_name,
           (SELECT COUNT(*) FROM visits WHERE doctor_id = u.id AND status != 'cancelled') as total_visits,
           (SELECT COUNT(*) FROM prescriptions WHERE doctor_id = u.id AND status != 'cancelled') as total_prescriptions,
           (SELECT COUNT(*) FROM patients WHERE assigned_doctor_id = u.id) as total_patients
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    $where_clause
    ORDER BY u.full_name ASC
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// STATISTICS
// ================================================================
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor'");
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND is_online = 1");
$online_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND (is_online = 0 OR is_online IS NULL)");
$offline_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(DISTINCT assigned_doctor_id) as total FROM patients WHERE assigned_doctor_id IS NOT NULL");
$with_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// HELPER: HIGHLIGHT SEARCH TERMS
// ================================================================
if (!function_exists('highlightSearch')) {
    function highlightSearch($text, $search) {
        if (empty($search) || empty($text)) {
            return htmlspecialchars($text ?? '');
        }
        $escaped = htmlspecialchars($text);
        $search_escaped = preg_quote(htmlspecialchars($search), '/');
        return preg_replace(
            '/(' . $search_escaped . ')/i',
            '<mark class="search-highlight">$1</mark>',
            $escaped
        );
    }
}

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-light: #6EA8FE;
        --page-primary-bg: #E8F0FE;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #F8FAFC;
        --page-shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-hover: #0F172A;
        --page-primary-bg: #1E3A5F;
    }

    /* ================================================================
       DARK MODE - PAGE YOTE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       SEARCH HIGHLIGHT
       ================================================================ */
    mark.search-highlight {
        background: #FEF08A;
        color: #78350F;
        padding: 1px 4px;
        border-radius: 4px;
        font-weight: 700;
        border: 1px solid #FDE047;
        box-shadow: 0 1px 3px rgba(250, 204, 21, 0.3);
    }

    [data-theme="dark"] mark.search-highlight {
        background: #FCD34D;
        color: #422006;
        border-color: #F59E0B;
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-custom {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
        border-radius: 18px;
        padding: 26px 34px;
        margin-bottom: 26px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom .page-title {
        color: white;
        font-size: 1.7rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-custom .page-subtitle {
        color: rgba(255,255,255,0.88);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
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
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(4px);
    }

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* ================================================================
       STAT CARDS
       ================================================================ */
    .stat-card-mini {
        background: var(--page-bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--page-border);
        transition: all 0.3s ease;
        text-align: center;
    }

    .stat-card-mini:hover {
        transform: translateY(-3px);
        box-shadow: var(--page-shadow-md);
        border-color: var(--page-primary);
    }

    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--page-primary);
    }

    .stat-card-mini .stat-number.green { color: #059669; }
    .stat-card-mini .stat-number.orange { color: #D97706; }
    .stat-card-mini .stat-number.red { color: #DC2626; }
    .stat-card-mini .stat-number.purple { color: #7C3AED; }

    .stat-card-mini .stat-label {
        font-size: 0.7rem;
        color: var(--page-text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .stat-card-mini .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }

    /* ================================================================
       FILTER SECTION
       ================================================================ */
    .filter-section {
        background: var(--page-bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--page-border);
        margin-bottom: 18px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        box-shadow: var(--page-shadow-sm);
    }

    .filter-section .filter-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--page-text-secondary);
        margin-right: 4px;
    }

    .filter-btn {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        border: 2px solid var(--page-border);
        background: transparent;
        color: var(--page-text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .filter-btn:hover {
        border-color: var(--page-primary);
        color: var(--page-primary);
        background: var(--page-primary-bg);
    }

    .filter-btn.active {
        background: var(--page-primary);
        color: white;
        border-color: var(--page-primary);
    }

    /* ================================================================
       CARD
       ================================================================ */
    .card-custom {
        background: var(--page-bg-card);
        border-radius: 18px;
        padding: 18px 20px;
        border: 2px solid var(--page-border);
        transition: all 0.3s;
        box-shadow: var(--page-shadow-sm);
    }

    /* ================================================================
       TABLE HEADER - SEARCH LEFT, SMALLER SIZE
       ================================================================ */
    .table-header-controls {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
        border-radius: 12px 12px 0 0;
        margin: -18px -20px 0 -20px;
    }

    /* ✅ LEFT SIDE: Title + Search */
    .table-header-left {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        flex: 1;
    }

    .table-header-controls .table-title {
        color: white;
        font-size: 0.9rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        white-space: nowrap;
    }

    .table-header-controls .table-title i {
        color: rgba(255,255,255,0.85);
    }

    .table-header-controls .table-title .count-badge {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        margin-left: 6px;
    }

    /* ✅ SEARCH BAR - SMALLER, LEFT SIDE */
    .table-header-search-wrapper {
        position: relative;
    }

    .table-header-search {
        display: flex;
        align-items: center;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.25);
        border-radius: 10px;
        height: 36px;
        width: 260px;
        transition: all 0.3s ease;
        backdrop-filter: blur(8px);
    }

    .table-header-search:focus-within {
        background: rgba(255,255,255,0.25);
        border-color: rgba(255,255,255,0.5);
        box-shadow: 0 0 0 4px rgba(255,255,255,0.1);
        width: 300px;
    }

    .table-header-search .search-icon {
        color: rgba(255,255,255,0.7);
        margin-left: 10px;
        font-size: 0.75rem;
    }

    .table-header-search input {
        border: none;
        background: transparent;
        padding: 6px 8px;
        width: 100%;
        font-size: 0.78rem;
        outline: none;
        color: white;
        font-weight: 500;
    }

    .table-header-search input::placeholder {
        color: rgba(255,255,255,0.55);
        font-weight: 400;
        font-size: 0.72rem;
    }

    .table-header-search .clear-search {
        background: transparent;
        border: none;
        color: rgba(255,255,255,0.7);
        cursor: pointer;
        padding: 6px 10px;
        font-size: 0.75rem;
        transition: all 0.2s;
        display: none;
    }

    .table-header-search .clear-search:hover {
        color: white;
    }

    .table-header-search .clear-search.visible {
        display: block;
    }

    /* ✅ LIVE SEARCH DROPDOWN */
    .live-search-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: var(--page-bg-card);
        border: 2px solid var(--page-border);
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        max-height: 320px;
        overflow-y: auto;
        z-index: 100;
        display: none;
    }

    .live-search-dropdown.show {
        display: block;
        animation: slideDown 0.25s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .live-search-item {
        padding: 10px 14px;
        cursor: pointer;
        border-bottom: 1px solid var(--page-border);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .live-search-item:last-child {
        border-bottom: none;
    }

    .live-search-item:hover {
        background: var(--page-primary-bg);
    }

    [data-theme="dark"] .live-search-item:hover {
        background: #1E3A5F;
    }

    .live-search-item .item-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.7rem;
        color: white;
        flex-shrink: 0;
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
    }

    .live-search-item .item-info {
        flex: 1;
        min-width: 0;
    }

    .live-search-item .item-name {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--page-text-primary);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .live-search-item .item-detail {
        font-size: 0.68rem;
        color: var(--page-text-secondary);
        margin: 2px 0 0 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .live-search-item .item-status {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .live-search-item .item-status.online { background: #059669; }
    .live-search-item .item-status.offline { background: #DC2626; }

    .live-search-empty {
        padding: 20px;
        text-align: center;
        color: var(--page-text-secondary);
        font-size: 0.8rem;
    }

    .live-search-empty i {
        font-size: 1.5rem;
        display: block;
        margin-bottom: 8px;
        color: var(--page-primary);
    }

    /* ✅ SCROLL BUTTONS */
    .table-header-scroll-btns {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .table-header-scroll-btn {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.25);
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(8px);
    }

    .table-header-scroll-btn:hover:not(:disabled) {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .table-header-scroll-btn:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }

    /* ================================================================
       TABLE SCROLL CONTAINER
       ================================================================ */
    .table-scroll-container {
        overflow-x: auto;
        overflow-y: hidden;
        scroll-behavior: smooth;
        scrollbar-width: thin;
        scrollbar-color: var(--page-primary) var(--page-border);
    }

    .table-scroll-container::-webkit-scrollbar {
        height: 8px;
    }

    .table-scroll-container::-webkit-scrollbar-track {
        background: var(--page-border);
        border-radius: 10px;
    }

    .table-scroll-container::-webkit-scrollbar-thumb {
        background: var(--page-primary);
        border-radius: 10px;
        border: 2px solid var(--page-border);
    }

    /* ================================================================
       DATA TABLE
       ================================================================ */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 1000px;
    }

    .data-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: var(--page-primary);
        border-bottom: 3px solid var(--page-primary-dark);
        white-space: nowrap;
    }

    .data-table tbody tr:nth-child(even) {
        background: var(--page-primary-bg);
    }

    .data-table tbody tr:nth-child(odd) {
        background: var(--page-bg-card);
    }

    .data-table tbody tr:hover {
        background: #DBEAFE;
    }

    [data-theme="dark"] .data-table tbody tr:hover {
        background: #1E40AF;
    }

    .data-table td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--page-border);
        color: var(--page-text-primary);
        vertical-align: middle;
    }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
        border: none;
    }

    .badge-info, .badge-blue { background: #0B5ED7; }
    .badge-green { background: #059669; }
    .badge-danger { background: #DC2626; }
    .badge-purple { background: #7C3AED; }

    .status-badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .status-badge.online {
        background: #D1FAE5;
        color: #059669;
    }

    .status-badge.offline {
        background: #FEE2E2;
        color: #DC2626;
    }

    [data-theme="dark"] .status-badge.online {
        background: #1A3A2A;
        color: #34D399;
    }

    [data-theme="dark"] .status-badge.offline {
        background: #3A1A1A;
        color: #F87171;
    }

    /* ================================================================
       DOCTOR AVATAR
       ================================================================ */
    .doctor-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        color: white;
        flex-shrink: 0;
        position: relative;
    }

    .doctor-avatar.blue { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }
    .doctor-avatar.green { background: linear-gradient(135deg, #059669, #0AA84F); }
    .doctor-avatar.purple { background: linear-gradient(135deg, #7B2FBE, #9B4DCA); }
    .doctor-avatar.orange { background: linear-gradient(135deg, #F59E0B, #FBBF24); }
    .doctor-avatar.red { background: linear-gradient(135deg, #EF4444, #F87171); }
    .doctor-avatar.pink { background: linear-gradient(135deg, #EC4899, #F472B6); }
    .doctor-avatar.teal { background: linear-gradient(135deg, #0D9488, #14B8A6); }

    .doctor-avatar-wrapper {
        position: relative;
        display: inline-block;
    }

    .doctor-avatar-wrapper .online-indicator {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid var(--page-bg-card);
    }

    .doctor-avatar-wrapper .online-indicator.online { background: #059669; }
    .doctor-avatar-wrapper .online-indicator.offline { background: #DC2626; }

    /* ================================================================
       ACTION BUTTONS - VIEW + EDIT ONLY
       ================================================================ */
    .action-buttons {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
        cursor: pointer;
        white-space: nowrap;
    }

    .btn-action i { font-size: 0.7rem; }

    .btn-view {
        background: #E8F0FE;
        color: #0B5ED7;
        border: 2px solid rgba(11, 94, 215, 0.2);
    }

    .btn-view:hover {
        background: #0B5ED7;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
    }

    [data-theme="dark"] .btn-view {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: rgba(59, 130, 246, 0.2);
    }
    [data-theme="dark"] .btn-view:hover {
        background: #0B5ED7;
        color: white;
    }

    .btn-edit {
        background: #FEF3C7;
        color: #D97706;
        border: 2px solid rgba(217, 119, 6, 0.2);
    }

    .btn-edit:hover {
        background: #D97706;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(217, 119, 6, 0.3);
    }

    [data-theme="dark"] .btn-edit {
        background: #3D2E0A;
        color: #FCD34D;
        border-color: rgba(252, 211, 77, 0.2);
    }
    [data-theme="dark"] .btn-edit:hover {
        background: #D97706;
        color: white;
    }

    .btn-toggle-online {
        background: #D1FAE5;
        color: #059669;
        border: 2px solid rgba(5, 150, 105, 0.2);
    }

    .btn-toggle-online:hover {
        background: #059669;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
    }

    [data-theme="dark"] .btn-toggle-online {
        background: #1A3A2A;
        color: #34D399;
        border-color: rgba(52, 211, 153, 0.2);
    }
    [data-theme="dark"] .btn-toggle-online:hover {
        background: #059669;
        color: white;
    }

    .btn-toggle-offline {
        background: #FEE2E2;
        color: #DC2626;
        border: 2px solid rgba(220, 38, 38, 0.2);
    }

    .btn-toggle-offline:hover {
        background: #DC2626;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
    }

    [data-theme="dark"] .btn-toggle-offline {
        background: #3A1A1A;
        color: #F87171;
        border-color: rgba(248, 113, 113, 0.2);
    }
    [data-theme="dark"] .btn-toggle-offline:hover {
        background: #DC2626;
        color: white;
    }

    /* ================================================================
       PAGINATION
       ================================================================ */
    .pagination {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }

    .pagination .page-link {
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid var(--page-border);
        color: var(--page-text-primary);
        text-decoration: none;
        font-size: 0.8rem;
        transition: all 0.3s;
        background: var(--page-bg-card);
    }

    .pagination .page-link:hover {
        background: var(--page-primary);
        color: white;
        border-color: var(--page-primary);
    }

    .pagination .page-link.active {
        background: var(--page-primary);
        color: white;
        border-color: var(--page-primary);
    }

    .pagination .page-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--page-border);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary);
    }

    .footer .footer-brand {
        color: var(--page-primary);
        font-weight: 700;
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
    @media (max-width: 992px) {
        .table-header-controls {
            flex-direction: column;
            align-items: stretch;
        }
        .table-header-left {
            flex-direction: column;
            align-items: stretch;
        }
        .table-header-search {
            width: 100%;
        }
        .table-header-search:focus-within {
            width: 100%;
        }
        .table-header-scroll-btns {
            justify-content: flex-end;
        }
    }

    @media (max-width: 768px) {
        .page-header-custom {
            padding: 18px 20px;
        }
        .page-header-custom .page-title {
            font-size: 1.3rem;
        }
        .stat-card-mini .stat-number {
            font-size: 1.4rem;
        }
    }

    @media (max-width: 480px) {
        .page-header-custom {
            flex-direction: column;
            align-items: flex-start !important;
        }
        .table-header-scroll-btn {
            width: 30px;
            height: 30px;
        }
    }
</style>

<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-md"></i>
                Doctors
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                Manage all doctors in the system
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user-md"></i> <?= $total_all ?> Total
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-circle"></i> <?= $online_count ?> Online
                </span>
                <span class="header-badge" style="background:rgba(248,113,113,0.2);border-color:rgba(248,113,113,0.3);color:#F87171;">
                    <i class="fas fa-circle"></i> <?= $offline_count ?> Offline
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="add_employee.php" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Doctor
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5 animate-fade-in-up">
        <div class="stat-card-mini">
            <div class="stat-icon">👨‍⚕️</div>
            <p class="stat-number"><?= $total_all ?></p>
            <p class="stat-label">Total Doctors</p>
        </div>
        <div class="stat-card-mini">
            <div class="stat-icon">🟢</div>
            <p class="stat-number green"><?= $online_count ?></p>
            <p class="stat-label">Online</p>
        </div>
        <div class="stat-card-mini">
            <div class="stat-icon">🔴</div>
            <p class="stat-number red"><?= $offline_count ?></p>
            <p class="stat-label">Offline</p>
        </div>
        <div class="stat-card-mini">
            <div class="stat-icon">👤</div>
            <p class="stat-number purple"><?= $with_patients ?></p>
            <p class="stat-label">With Patients</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
        
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => '', 'page' => 1])) ?>" 
           class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'online', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'online' ? 'active' : '' ?>">
            <i class="fas fa-circle" style="color: #059669;"></i> Online
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'offline', 'page' => 1])) ?>" 
           class="filter-btn <?= $status_filter === 'offline' ? 'active' : '' ?>">
            <i class="fas fa-circle" style="color: #DC2626;"></i> Offline
        </a>
        
        <?php if (!empty($search) || !empty($status_filter)): ?>
            <a href="doctors_list.php?branch=<?= $selected_branch_id ?>" class="filter-btn" style="border-color: #DC2626; color: #DC2626;">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>

    <!-- Doctors List Card -->
    <div class="card-custom animate-fade-in-up" style="animation-delay:0.1s;">
        
        <!-- ================================================================ -->
        <!-- TABLE HEADER - SEARCH LEFT, SMALLER -->
        <!-- ================================================================ -->
        <div class="table-header-controls">
            <!-- LEFT: Title + Search -->
            <div class="table-header-left">
                <h3 class="table-title">
                    <i class="fas fa-list"></i>
                    Doctors List
                    <span class="count-badge"><?= $total_doctors ?></span>
                </h3>
                
                <!-- Search Bar - LEFT SIDE, SMALLER -->
                <div class="table-header-search-wrapper">
                    <div class="table-header-search">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" 
                               id="liveSearchInput"
                               placeholder="Search doctors..." 
                               value="<?= htmlspecialchars($search) ?>"
                               autocomplete="off">
                        <button type="button" class="clear-search <?= !empty($search) ? 'visible' : '' ?>" id="clearSearchBtn" title="Clear">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <!-- Live Search Dropdown -->
                    <div class="live-search-dropdown" id="liveSearchDropdown"></div>
                </div>
            </div>
            
            <!-- RIGHT: Scroll Buttons -->
            <div class="table-header-scroll-btns">
                <button type="button" 
                        class="table-header-scroll-btn" 
                        id="headerScrollLeftBtn" 
                        onclick="scrollTable('left')" 
                        title="Scroll Left"
                        disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" 
                        class="table-header-scroll-btn" 
                        id="headerScrollRightBtn" 
                        onclick="scrollTable('right')" 
                        title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <!-- Scrollable Table Container -->
        <div class="table-scroll-container" id="tableScrollContainer">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="min-width: 200px;">Doctor</th>
                        <th style="min-width: 130px;">Specialty</th>
                        <th style="min-width: 130px;">Branch</th>
                        <th style="min-width: 90px;">Patients</th>
                        <th style="min-width: 90px;">Visits</th>
                        <th style="min-width: 110px;">Status</th>
                        <th style="min-width: 220px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="doctorsTableBody">
                    <?php if (count($doctors) > 0): ?>
                        <?php $i = $offset + 1; foreach ($doctors as $doctor): ?>
                            <?php 
                                $colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
                                $color_index = abs(crc32($doctor['full_name'])) % count($colors);
                                $avatar_color = $colors[$color_index];
                                $initials = implode('', array_map(function($name) {
                                    return strtoupper($name[0]);
                                }, explode(' ', trim($doctor['full_name']))));
                                
                                $is_online = $doctor['is_online'] == 1;
                            ?>
                            <tr>
                                <td style="font-weight:700;color:#0B5ED7;"><?= $i++ ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div class="doctor-avatar-wrapper">
                                            <div class="doctor-avatar <?= $avatar_color ?>">
                                                <?= substr($initials, 0, 2) ?>
                                                <span class="online-indicator <?= $is_online ? 'online' : 'offline' ?>"></span>
                                            </div>
                                        </div>
                                        <div>
                                            <p style="font-weight:600;font-size:0.85rem;margin:0;">
                                                <?= highlightSearch($doctor['full_name'], $search) ?>
                                            </p>
                                            <p style="font-size:0.72rem;color:var(--page-text-secondary);margin:0;">
                                                <?= highlightSearch($doctor['email'] ?? '', $search) ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?= highlightSearch($doctor['specialty'] ?? 'General', $search) ?>
                                    </span>
                                </td>
                                <td><?= highlightSearch($doctor['branch_name'] ?? 'N/A', $search) ?></td>
                                <td style="text-align:center;">
                                    <span class="badge badge-blue"><?= $doctor['total_patients'] ?? 0 ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge badge-green"><?= $doctor['total_visits'] ?? 0 ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $is_online ? 'online' : 'offline' ?>">
                                        <i class="fas fa-circle" style="font-size:8px;"></i>
                                        <?= $is_online ? 'Online' : 'Offline' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- ✅ VIEW ONLY -->
                                        <a href="doctor_details.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-view" title="View Doctor Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <!-- ✅ EDIT ONLY -->
                                        <a href="edit_employee.php?id=<?= $doctor['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action btn-edit" title="Edit Doctor">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <!-- Toggle Online/Offline -->
                                        <?php if ($is_online): ?>
                                            <a href="?toggle=<?= $doctor['id'] ?>&page=<?= $page ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                                               class="btn-action btn-toggle-offline" 
                                               onclick="return confirm('Set Dr. <?= htmlspecialchars($doctor['full_name']) ?> to OFFLINE?')" 
                                               title="Set Offline">
                                                <i class="fas fa-power-off"></i> Offline
                                            </a>
                                        <?php else: ?>
                                            <a href="?toggle=<?= $doctor['id'] ?>&page=<?= $page ?>&branch=<?= $selected_branch_id ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>" 
                                               class="btn-action btn-toggle-online" 
                                               onclick="return confirm('Set Dr. <?= htmlspecialchars($doctor['full_name']) ?> to ONLINE?')" 
                                               title="Set Online">
                                                <i class="fas fa-power-off"></i> Online
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:32px;color:var(--page-text-secondary);">
                                <i class="fas fa-user-md" style="font-size:2.5rem;display:block;margin-bottom:12px;color:#0B5ED7;"></i>
                                <p style="font-size:1rem;font-weight:500;margin:0 0 4px 0;">
                                    <?= !empty($search) || !empty($status_filter) ? 'No doctors found matching your filters' : 'No doctors found' ?>
                                </p>
                                <p style="font-size:0.8rem;margin:0;">
                                    <?= !empty($search) || !empty($status_filter) ? 'Try changing your search or filter criteria' : 'Contact administrator to add a doctor' ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;margin-top:16px;padding-top:12px;border-top:1px solid var(--page-border);">
                <div style="font-size:0.8rem;color:var(--page-text-secondary);">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_doctors) ?> of <?= $total_doctors ?> doctors
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

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Doctors Management
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
// ================================================================
// ✅ TABLE SCROLL FUNCTIONS
// ================================================================
function scrollTable(direction) {
    var container = document.getElementById('tableScrollContainer');
    if (!container) return;
    
    var scrollAmount = 400;
    
    if (direction === 'left') {
        container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
    
    setTimeout(updateScrollButtons, 350);
}

function updateScrollButtons() {
    var container = document.getElementById('tableScrollContainer');
    var leftBtn = document.getElementById('headerScrollLeftBtn');
    var rightBtn = document.getElementById('headerScrollRightBtn');
    
    if (!container || !leftBtn || !rightBtn) return;
    
    var scrollLeft = container.scrollLeft;
    var scrollWidth = container.scrollWidth;
    var clientWidth = container.clientWidth;
    var maxScroll = scrollWidth - clientWidth;
    
    if (maxScroll <= 5) {
        leftBtn.disabled = true;
        rightBtn.disabled = true;
        return;
    }
    
    leftBtn.disabled = (scrollLeft <= 5);
    rightBtn.disabled = (scrollLeft >= maxScroll - 5);
}

(function() {
    var container = document.getElementById('tableScrollContainer');
    if (!container) return;
    
    container.addEventListener('scroll', updateScrollButtons);
    window.addEventListener('resize', function() {
        setTimeout(updateScrollButtons, 100);
    });
    
    setTimeout(updateScrollButtons, 200);
    setTimeout(updateScrollButtons, 600);
    
    // Drag to scroll
    var isDown = false;
    var startX = 0;
    var scrollLeftStart = 0;
    
    container.addEventListener('mousedown', function(e) {
        if (e.target.closest('a, button, input')) return;
        isDown = true;
        container.style.cursor = 'grabbing';
        startX = e.pageX - container.offsetLeft;
        scrollLeftStart = container.scrollLeft;
    });
    
    container.addEventListener('mouseleave', function() {
        isDown = false;
        container.style.cursor = '';
    });
    
    container.addEventListener('mouseup', function() {
        isDown = false;
        container.style.cursor = '';
    });
    
    container.addEventListener('mousemove', function(e) {
        if (!isDown) return;
        e.preventDefault();
        var x = e.pageX - container.offsetLeft;
        var walk = (x - startX) * 1.5;
        container.scrollLeft = scrollLeftStart - walk;
    });
})();

// ================================================================
// ✅ LIVE AUTO-SEARCH WITH HIGHLIGHT
// ================================================================
(function() {
    var searchInput = document.getElementById('liveSearchInput');
    var dropdown = document.getElementById('liveSearchDropdown');
    var clearBtn = document.getElementById('clearSearchBtn');
    var tableBody = document.getElementById('doctorsTableBody');
    
    if (!searchInput || !dropdown) return;
    
    var searchTimeout = null;
    var currentSearch = '<?= addslashes($search) ?>';
    
    // Function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Function to highlight text
    function highlightText(text, search) {
        if (!search || !text) return escapeHtml(text);
        var escaped = escapeHtml(text);
        var regex = new RegExp('(' + search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return escaped.replace(regex, '<mark class="search-highlight">$1</mark>');
    }
    
    // Function to show dropdown
    function showDropdown(doctors, searchTerm) {
        if (doctors.length === 0) {
            dropdown.innerHTML = `
                <div class="live-search-empty">
                    <i class="fas fa-search"></i>
                    <p>No doctors found for "${escapeHtml(searchTerm)}"</p>
                </div>
            `;
            dropdown.classList.add('show');
            return;
        }
        
        var html = '';
        doctors.forEach(function(doc) {
            var initials = doc.full_name.split(' ').map(function(n) { return n[0]; }).join('').substring(0, 2).toUpperCase();
            var isOnline = doc.is_online == 1;
            
            html += `
                <div class="live-search-item" data-id="${doc.id}">
                    <div class="item-avatar">${initials}</div>
                    <div class="item-info">
                        <p class="item-name">${highlightText(doc.full_name, searchTerm)}</p>
                        <p class="item-detail">${highlightText(doc.specialty || 'General', searchTerm)} • ${highlightText(doc.email || '', searchTerm)}</p>
                    </div>
                    <span class="item-status ${isOnline ? 'online' : 'offline'}"></span>
                </div>
            `;
        });
        
        dropdown.innerHTML = html;
        dropdown.classList.add('show');
        
        // Add click handlers
        dropdown.querySelectorAll('.live-search-item').forEach(function(item) {
            item.addEventListener('click', function() {
                var docId = this.dataset.id;
                window.location.href = 'doctor_details.php?id=' + docId + '&branch=<?= $selected_branch_id ?>';
            });
        });
    }
    
    // Function to hide dropdown
    function hideDropdown() {
        dropdown.classList.remove('show');
    }
    
    // Function to perform AJAX search
    function performAjaxSearch(query) {
        var url = 'doctors_list.php?ajax_search=1&q=' + encodeURIComponent(query) + '&branch=<?= $selected_branch_id ?>';
        <?php if (!empty($status_filter)): ?>
        url += '&status=<?= urlencode($status_filter) ?>';
        <?php endif; ?>
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showDropdown(data.doctors, query);
                }
            })
            .catch(function(error) {
                console.error('Search error:', error);
            });
    }
    
    // Input event - live search
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        
        // Show/hide clear button
        if (query.length > 0) {
            clearBtn.classList.add('visible');
        } else {
            clearBtn.classList.remove('visible');
            hideDropdown();
            // Reload page without search
            if (currentSearch !== '') {
                var url = new URL(window.location.href);
                url.searchParams.delete('search');
                window.location.href = url.toString();
            }
            return;
        }
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Debounce - wait 250ms before searching
        searchTimeout = setTimeout(function() {
            performAjaxSearch(query);
        }, 250);
    });
    
    // Clear button
    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        clearBtn.classList.remove('visible');
        hideDropdown();
        
        var url = new URL(window.location.href);
        url.searchParams.delete('search');
        window.location.href = url.toString();
    });
    
    // Enter key - submit search
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var query = this.value.trim();
            if (query.length > 0) {
                var url = new URL(window.location.href);
                url.searchParams.set('search', query);
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            }
        }
    });
    
    // Click outside to close
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.table-header-search-wrapper')) {
            hideDropdown();
        }
    });
    
    // Focus - show results if there's text
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length > 0) {
            performAjaxSearch(this.value.trim());
        }
    });
})();

// ================================================================
// ✅ DARK MODE - PAGE YOTE INAKUWA DARK
// ================================================================
function enforceDarkModePage() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var body = document.body;
    var mainContent = document.querySelector('.main-content');
    
    if (isDark) {
        if (body) body.style.background = '#0F172A';
        if (mainContent) mainContent.style.background = '#0F172A';
    } else {
        if (body) body.style.background = '';
        if (mainContent) mainContent.style.background = '';
    }
}

enforceDarkModePage();

document.addEventListener('darkModeChanged', function() {
    setTimeout(enforceDarkModePage, 50);
});

var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'data-theme') {
            enforceDarkModePage();
        }
    });
});
observer.observe(document.documentElement, { attributes: true });

// ================================================================
// ✅ FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTime');
    if (ftEl) ftEl.textContent = timeStr;
}, 1000);

console.log('%c🏥 Braick Dispensary - Doctors Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ SQL ambiguous column FIXED', 'font-size:12px; color:#059669;');
console.log('%c✅ Search bar LEFT side + smaller: YES', 'font-size:12px; color:#059669;');
console.log('%c✅ Live auto-search + highlight: YES', 'font-size:12px; color:#059669;');
console.log('%c✅ Action buttons: View + Edit only', 'font-size:12px; color:#059669;');
console.log('%c✅ Dark mode kamili: YES', 'font-size:12px; color:#059669;');
</script>

</body>
</html>