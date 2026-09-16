<?php
// ================================================================
// FILE: frontend/pages/admin/patients.php
// SUPER ADMIN - MANAGE PATIENTS
// ✅ Inatumia SHARED HEADER & SIDEBAR pekee
// ✅ Imeondoa top-nav, search, dark mode, datetime, bell, avatar, branch selector
// ✅ Imeondoa DOCTYPE, html, head, body
// ✅ VIEW, EDIT, DELETE buttons
// ✅ BLUE THEME
// ✅ REGISTERED BY column
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$full_name = $_SESSION['full_name'] ?? 'Admin';
$role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// BRANCH FILTER
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

$branches_list = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches_list = [];
}

$filter_by_branch = ($selected_branch_id !== 'all' && is_numeric($selected_branch_id));
$filter_branch_id = $filter_by_branch ? (int)$selected_branch_id : 0;

$display_branch_name = 'All Branches';
if ($filter_by_branch) {
    foreach ($branches_list as $b) {
        if ($b['id'] == $filter_branch_id) {
            $display_branch_name = $b['name'];
            break;
        }
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$delete_id = isset($_GET['delete']) ? (int)$_GET['delete'] : 0;

$success = isset($_GET['success']) ? $_GET['success'] : '';

$message = '';
$message_type = '';

// DELETE PATIENT
if ($delete_id > 0) {
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("SELECT full_name, patient_id, branch_id FROM patients WHERE id = ?");
        $stmt->execute([$delete_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) throw new Exception("Patient not found");
        
        $patient_name = $patient['full_name'];
        $patient_number = $patient['patient_id'];
        $patient_branch = $patient['branch_id'];
        
        $revenue_to_subtract = 0;
        try {
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE patient_id = ?");
            $stmt->execute([$delete_id]);
            $revenue_to_subtract = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_paid'];
        } catch (Exception $e) {}
        
        $tables_to_delete = [
            'payments', 'bill_items', 'bills', 'prescription_items',
            'prescriptions', 'lab_tests', 'vital_signs', 'appointments',
            'referrals', 'visits', 'otc_sale_items', 'otc_sales',
            'patient_documents', 'stock_movements', 'receipts'
        ];
        
        $items_deleted = 0;
        foreach ($tables_to_delete as $table) {
            try {
                if ($table === 'bill_items') {
                    $stmt = $db->prepare("DELETE bi FROM bill_items bi INNER JOIN bills b ON bi.bill_id = b.id WHERE b.patient_id = ?");
                } elseif ($table === 'prescription_items') {
                    $stmt = $db->prepare("DELETE pi FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id WHERE p.patient_id = ?");
                } elseif ($table === 'otc_sale_items') {
                    $stmt = $db->prepare("DELETE osi FROM otc_sale_items osi INNER JOIN otc_sales os ON osi.sale_id = os.id WHERE os.patient_id = ?");
                } else {
                    $stmt = $db->prepare("DELETE FROM `$table` WHERE patient_id = ?");
                }
                $stmt->execute([$delete_id]);
                $items_deleted += $stmt->rowCount();
            } catch (Exception $e) {}
        }
        
        try {
            $stmt = $db->prepare("DELETE FROM notifications WHERE patient_id = ?");
            $stmt->execute([$delete_id]);
        } catch (Exception $e) {}
        
        $stmt = $db->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->execute([$delete_id]);
        
        try {
            $log_stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                VALUES (?, ?, 'patient_deleted', ?, NOW())
            ");
            $log_stmt->execute([
                $user_id, $patient_branch,
                "Patient deleted: $patient_name (ID: $patient_number) | Revenue removed: TSh " . number_format($revenue_to_subtract, 0) . " | By: " . $full_name
            ]);
        } catch (Exception $e) {}
        
        $db->commit();
        
        $_SESSION['flash_message'] = "✅ Patient deleted successfully!<br>" .
                                     "👤 <strong>$patient_name</strong> (ID: $patient_number)<br>" .
                                     "🗑️ <strong>" . number_format($items_deleted) . " records</strong> deleted<br>" .
                                     "💰 Revenue adjusted: <strong>- TSh " . number_format($revenue_to_subtract, 0) . "</strong>";
        $_SESSION['flash_type'] = 'success';
        
        header("Location: patients.php?branch=" . urlencode($selected_branch_id) . "&success=deleted");
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = "❌ Error deleting patient: " . $e->getMessage();
        $message_type = 'error';
    }
}

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// GET PATIENTS
try {
    $sql = "
        SELECT DISTINCT p.*, 
               u.full_name as assigned_doctor_name, 
               b.name as branch_name,
               COALESCE(
                   NULLIF(p.registered_by_name, ''),
                   reg_user.full_name,
                   creator.full_name,
                   'System'
               ) as registered_by_display,
               COALESCE(reg_user.role, creator.role, 'reception') as registered_by_role
        FROM patients p
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users reg_user ON p.registered_by = reg_user.id
        LEFT JOIN users creator ON p.created_by = creator.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($filter_by_branch) {
        $sql .= " AND p.branch_id = ?";
        $params[] = $filter_branch_id;
    }
    
    if (!empty($search)) {
        $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($filter === 'with_doctor') $sql .= " AND p.assigned_doctor_id IS NOT NULL";
    elseif ($filter === 'without_doctor') $sql .= " AND p.assigned_doctor_id IS NULL";
    elseif ($filter === 'new') $sql .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    elseif ($filter === 'no_visit') $sql .= " AND NOT EXISTS (SELECT 1 FROM visits WHERE patient_id = p.id)";
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $unique_patients = [];
    $seen_ids = [];
    foreach ($patients as $patient) {
        if (!in_array($patient['id'], $seen_ids)) {
            $seen_ids[] = $patient['id'];
            $unique_patients[] = $patient;
        }
    }
    $patients = $unique_patients;
    
    foreach ($patients as $key => $patient) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM visits WHERE patient_id = ?");
        $stmt->execute([$patient['id']]);
        $patients[$key]['total_visits'] = (int)$stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status IN ('scheduled', 'confirmed')");
        $stmt->execute([$patient['id']]);
        $patients[$key]['active_appointments'] = (int)$stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT DATEDIFF(NOW(), created_at) FROM patients WHERE id = ?");
        $stmt->execute([$patient['id']]);
        $patients[$key]['patient_days'] = (int)$stmt->fetchColumn();
        
        $patients[$key]['patient_status'] = ($patients[$key]['patient_days'] <= 7) ? 'new' : 'existing';
        
        $stmt = $db->prepare("SELECT id, visit_number, status, created_at FROM visits WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$patient['id']]);
        $patients[$key]['latest_visit'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $total_patients = count($patients);
    
    $stats_sql = "
        SELECT 
            COUNT(DISTINCT id) as total,
            SUM(CASE WHEN assigned_doctor_id IS NOT NULL THEN 1 ELSE 0 END) as with_doctor,
            SUM(CASE WHEN assigned_doctor_id IS NULL THEN 1 ELSE 0 END) as without_doctor,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_patients
        FROM patients 
        WHERE 1=1
    ";
    $stats_params = [];
    if ($filter_by_branch) {
        $stats_sql .= " AND branch_id = ?";
        $stats_params[] = $filter_branch_id;
    }
    $stmt = $db->prepare($stats_sql);
    $stmt->execute($stats_params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $patients = [];
    $stats = ['total' => 0, 'with_doctor' => 0, 'without_doctor' => 0, 'new_patients' => 0];
    $total_patients = 0;
}

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #EFF6FF;
    --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    --success: #059669;
    --success-dark: #047857;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-dark: #B91C1C;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --bg-card: #FFFFFF;
    --bg-body: #F0F4F8;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --text-muted: #94A3B8;
    --text-black: #0F172A;
    --border-color: #E2E8F0;
    --radius: 12px;
    --radius-lg: 18px;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --text-muted: #64748B;
    --text-black: #F1F5F9;
    --border-color: #334155;
    --primary-bg: #1E3A5F;
    --purple-bg: #2D1B5F;
}

/* PAGE HEADER */
.page-header-custom {
    background: var(--primary-gradient);
    border-radius: var(--radius-lg);
    padding: 24px 32px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 32px rgba(11, 94, 215, 0.25);
    position: relative;
    overflow: hidden;
    color: white;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -60%; right: -10%;
    width: 400px; height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.6rem;
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
    color: rgba(255,255,255,0.85);
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
}

.page-header-custom .header-badge {
    background: rgba(255,255,255,0.12);
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
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
    border-radius: var(--radius);
    font-weight: 500;
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
}

/* STATS CARDS */
.stats-grid-mini {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.stat-card-mini {
    background: var(--bg-card);
    border-radius: var(--radius);
    padding: 16px 20px;
    border: 2px solid var(--border-color);
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card-mini:hover {
    border-color: var(--primary);
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.stat-card-mini .stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary);
}

.stat-card-mini .stat-number.green { color: var(--success); }
.stat-card-mini .stat-number.orange { color: var(--warning); }
.stat-card-mini .stat-number.purple { color: var(--purple); }

.stat-card-mini .stat-label {
    font-size: 0.7rem;
    color: var(--text-secondary);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-top: 4px;
}

/* TABLE CARD */
.table-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    border: 2px solid var(--border-color);
    box-shadow: var(--shadow-md);
}

.table-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 14px;
    border-bottom: 2px solid var(--border-color);
}

.table-card .card-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-card .card-title i { color: var(--primary); }

/* FILTER BAR */
.filter-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    width: 100%;
    justify-content: space-between;
    padding: 10px 14px;
    background: var(--primary-gradient);
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(11, 94, 215, 0.2);
    margin-bottom: 16px;
}

.filter-bar-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1;
}

.filter-bar-right {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.filter-input {
    padding: 6px 12px;
    border: 1.5px solid rgba(255,255,255,0.3);
    border-radius: 8px;
    font-size: 0.75rem;
    background: rgba(255,255,255,0.95);
    color: #1E293B;
    outline: none;
    font-weight: 500;
    height: 36px;
}

.filter-input:focus {
    border-color: white;
    box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
}

select.filter-input {
    min-width: 140px;
    cursor: pointer;
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-input-wrapper i {
    position: absolute;
    left: 12px;
    color: var(--primary);
    font-size: 0.75rem;
    pointer-events: none;
}

.search-input-wrapper input {
    padding-left: 32px;
    min-width: 200px;
    width: 200px;
    font-size: 0.75rem;
}

.clear-filter-btn {
    display: none;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 600;
    background: rgba(255,255,255,0.95);
    color: var(--danger);
    border: 1.5px solid white;
    cursor: pointer;
    text-decoration: none;
    height: 36px;
}

.clear-filter-btn.show { display: inline-flex; }
.clear-filter-btn:hover { background: var(--danger); color: white; }

.filter-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.65rem;
    color: white;
    padding: 4px 12px;
    background: rgba(255,255,255,0.15);
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.2);
    height: 36px;
}

.auto-filter-loading {
    display: none;
    align-items: center;
    gap: 4px;
    font-size: 0.65rem;
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    background: rgba(255,255,255,0.2);
    height: 36px;
}

.auto-filter-loading.show { display: inline-flex; }

.auto-filter-loading .spinner-small {
    width: 10px;
    height: 10px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* SCROLL BUTTONS */
.scroll-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

.scroll-btn-header {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1.5px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.95);
    color: var(--primary);
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    transition: all 0.3s ease;
}

.scroll-btn-header:hover {
    background: white;
    transform: scale(1.08);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.scroll-btn-header:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    transform: none !important;
}

/* TABLE */
.table-scroll-wrapper {
    overflow-x: auto;
    overflow-y: auto;
    max-height: 600px;
    scroll-behavior: smooth;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}

.table-scroll-wrapper::-webkit-scrollbar { height: 8px; width: 8px; }
.table-scroll-wrapper::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
.table-scroll-wrapper::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

.patient-table {
    width: 100%;
    min-width: 1650px;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.patient-table thead {
    background: var(--primary-gradient);
    color: white;
    position: sticky;
    top: 0;
    z-index: 5;
}

.patient-table thead th {
    padding: 14px 16px;
    text-align: left;
    font-weight: 700;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: white;
    white-space: nowrap;
}

.patient-table .col-sno {
    width: 55px;
    text-align: center;
    font-weight: 700;
    color: var(--primary);
    font-size: 0.9rem;
}

.patient-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.85rem;
    color: var(--text-primary);
}

.patient-table tbody tr:nth-child(even) {
    background: var(--primary-bg);
}

.patient-table tbody tr:hover td {
    background: #D1FAE5;
}

[data-theme="dark"] .patient-table tbody tr:hover td {
    background: #1A3A2A;
}

/* PATIENT NAME - BLACK */
.patient-name-link {
    color: var(--text-black) !important;
    font-weight: 700 !important;
    font-size: 0.92rem !important;
    text-decoration: none !important;
    transition: all 0.2s ease;
}

.patient-name-link:hover {
    color: var(--primary) !important;
    text-decoration: underline !important;
}

.patient-id-text {
    color: var(--text-black) !important;
    font-family: 'Courier New', monospace !important;
    font-size: 0.85rem !important;
    font-weight: 700 !important;
}

.patient-contact-link {
    color: var(--text-black) !important;
    text-decoration: none !important;
    font-weight: 500 !important;
}

.patient-contact-link:hover {
    color: var(--primary) !important;
    text-decoration: underline !important;
}

/* BADGES */
.branch-badge-display {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
    background: var(--success-bg);
    color: var(--success);
}

.status-badge {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 12px;
}

.status-badge.new { background: var(--success-bg); color: var(--success); }
.status-badge.existing { background: var(--primary-bg); color: var(--primary); }
.status-badge.with_doctor { background: var(--success-bg); color: var(--success); }
.status-badge.without_doctor { background: var(--warning-bg); color: var(--warning); }

.days-badge-blue {
    display: inline-block;
    background: var(--primary) !important;
    color: #ffffff !important;
    padding: 4px 12px !important;
    border-radius: 12px !important;
    font-size: 0.68rem !important;
    font-weight: 600 !important;
}

.days-badge-blue.new {
    background: var(--success) !important;
}

.registered-by-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 12px;
    font-size: 0.72rem;
    font-weight: 600;
    background: var(--purple-bg);
    color: var(--purple);
    white-space: nowrap;
}

.registered-by-badge.role-admin {
    background: #FEF3C7;
    color: #B45309;
}

.registered-by-badge.role-reception {
    background: var(--purple-bg);
    color: var(--purple);
}

[data-theme="dark"] .registered-by-badge.role-admin {
    background: #3D2E0A;
    color: #FBBF24;
}

[data-theme="dark"] .registered-by-badge.role-reception {
    background: #2D1B5F;
    color: #C4B5FD;
}

/* BUTTONS */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 14px;
    border-radius: 7px;
    font-weight: 600;
    font-size: 0.75rem;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    text-decoration: none;
    white-space: nowrap;
}

.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }

.btn-success { background: var(--success); color: white; }
.btn-success:hover { background: var(--success-dark); transform: translateY(-1px); }

.btn-danger { background: var(--danger); color: white; }
.btn-danger:hover { background: var(--danger-dark); transform: translateY(-1px); }

.action-buttons-group {
    display: flex;
    gap: 5px;
    flex-wrap: nowrap;
}

/* MESSAGE */
.message-box {
    padding: 14px 20px;
    border-radius: var(--radius);
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-weight: 500;
}

.message-box.success { background: var(--success-bg); color: var(--success-dark); border-left: 5px solid var(--success); }
.message-box.error { background: var(--danger-bg); color: var(--danger-dark); border-left: 5px solid var(--danger); }
.message-box i { font-size: 1.2rem; margin-top: 2px; }

/* FOOTER */
.footer {
    padding: 14px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand {
    color: var(--primary);
    font-weight: 600;
}

/* TOAST */
.toast-custom {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 14px 20px;
    border-radius: var(--radius);
    z-index: 9999;
    max-width: 450px;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    box-shadow: var(--shadow-lg);
}

.toast-custom.show { transform: translateY(0); opacity: 1; }
.toast-custom.success { background: var(--success); }
.toast-custom.error { background: var(--danger); }
.toast-custom.info { background: var(--primary); }

/* DELETE MODAL */
.delete-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 10000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.delete-modal-overlay.show {
    display: flex;
}

.delete-modal-content {
    background: var(--bg-card);
    border-radius: 16px;
    max-width: 550px;
    width: 100%;
    padding: 24px;
    border: 2px solid var(--border-color);
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

/* ANIMATION */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in-up {
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .stats-grid-mini { grid-template-columns: repeat(2, 1fr); }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-bar-left, .filter-bar-right { width: 100%; justify-content: flex-start; }
    .search-input-wrapper input { width: 100%; min-width: unset; }
    .page-header-custom { padding: 16px 18px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
}

@media (max-width: 480px) {
    .stats-grid-mini { grid-template-columns: 1fr; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users"></i>
                Patients
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Manage patients in <strong><?= htmlspecialchars($display_branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-users"></i>
                    <?= $stats['total'] ?? 0 ?> Total
                </span>
                <span class="header-badge">
                    <i class="fas fa-user-md"></i>
                    <?= $stats['with_doctor'] ?? 0 ?> With Doctor
                </span>
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= $stats['without_doctor'] ?? 0 ?> No Doctor
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);">
                    <i class="fas fa-bolt"></i>
                    <?= $stats['new_patients'] ?? 0 ?> New (7 days)
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="add_patient.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-plus"></i> New Patient
            </a>
            <a href="dashboard.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid-mini animate-fade-in-up">
        <div class="stat-card-mini">
            <p class="stat-number"><?= $stats['total'] ?? 0 ?></p>
            <p class="stat-label">Total Patients</p>
        </div>
        <div class="stat-card-mini">
            <p class="stat-number green"><?= $stats['with_doctor'] ?? 0 ?></p>
            <p class="stat-label">With Doctor</p>
        </div>
        <div class="stat-card-mini">
            <p class="stat-number orange"><?= $stats['without_doctor'] ?? 0 ?></p>
            <p class="stat-label">No Doctor</p>
        </div>
        <div class="stat-card-mini">
            <p class="stat-number purple"><?= $stats['new_patients'] ?? 0 ?></p>
            <p class="stat-label">New (7 days)</p>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card animate-fade-in-up">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-list"></i> Patient List
                <span id="patientCountBadge" style="background:var(--primary-bg);color:var(--primary);padding:2px 12px;border-radius:20px;font-size:0.7rem;font-weight:500;">
                    <?= $total_patients ?> patients
                </span>
            </div>
        </div>
        
        <!-- FILTER BAR -->
        <div class="filter-bar">
            <div class="filter-bar-left">
                <div class="auto-filter-loading" id="filterLoading">
                    <div class="spinner-small"></div>
                    <span>Filtering...</span>
                </div>
                
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           id="searchFilter" 
                           class="filter-input" 
                           placeholder="Search patient name, ID, phone..." 
                           value="<?= htmlspecialchars($search) ?>"
                           oninput="autoFilter()">
                </div>
                
                <select id="filterSelect" class="filter-input" onchange="autoFilter()">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>👥 All</option>
                    <option value="new" <?= $filter === 'new' ? 'selected' : '' ?>>🆕 New (7d)</option>
                    <option value="with_doctor" <?= $filter === 'with_doctor' ? 'selected' : '' ?>>✅ With Doctor</option>
                    <option value="without_doctor" <?= $filter === 'without_doctor' ? 'selected' : '' ?>>⚠️ No Doctor</option>
                    <option value="no_visit" <?= $filter === 'no_visit' ? 'selected' : '' ?>>📋 No Visit</option>
                </select>
                
                <a href="patients.php?branch=<?= urlencode($selected_branch_id) ?>" class="clear-filter-btn <?= (!empty($search) || $filter !== 'all') ? 'show' : '' ?>" id="clearFilterBtn">
                    <i class="fas fa-times"></i> Clear
                </a>
                
                <span class="filter-status" id="filterStatus">
                    <i class="fas fa-bolt"></i>
                    <span id="filterStatusText">Auto-filter</span>
                </span>
            </div>
            
            <!-- SCROLL BUTTONS -->
            <div class="filter-bar-right">
                <div class="scroll-controls">
                    <button type="button" class="scroll-btn-header" id="scrollLeftBtn" onclick="scrollTable('left')" title="Scroll Left">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="scroll-btn-header" id="scrollRightBtn" onclick="scrollTable('right')" title="Scroll Right">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- PATIENT TABLE -->
        <div id="tableContainer">
            <?php if (!empty($patients) && count($patients) > 0): ?>
            <div class="table-scroll-wrapper" id="tableScrollWrapper">
                <table class="patient-table" id="patientsTable">
                    <thead>
                        <tr>
                            <th class="col-sno">#</th>
                            <th><i class="fas fa-user"></i> Patient</th>
                            <th><i class="fas fa-id-card"></i> Patient ID</th>
                            <th><i class="fas fa-phone"></i> Contact</th>
                            <th><i class="fas fa-store-alt"></i> Branch</th>
                            <th><i class="fas fa-calendar"></i> Days</th>
                            <th><i class="fas fa-user-md"></i> Doctor</th>
                            <th><i class="fas fa-notes-medical"></i> Visits</th>
                            <th><i class="fas fa-user-plus"></i> Registered By</th>
                            <th><i class="fas fa-circle"></i> Status</th>
                            <th style="min-width:230px;"><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="patientTableBody">
                        <?php $counter = 1; ?>
                        <?php foreach ($patients as $patient): 
                            $patient_days = isset($patient['patient_days']) ? (int)$patient['patient_days'] : 0;
                            $days_text = $patient_days > 0 ? '<span class="days-badge-blue">📅 ' . $patient_days . ' days</span>' : '<span class="days-badge-blue new">📅 New</span>';
                            
                            $status_class = $patient['patient_status'] ?? 'existing';
                            $status_text = $status_class === 'new' ? 'New' : 'Existing';
                            
                            if (!empty($patient['assigned_doctor_name'])) {
                                $doctor_status = '<span class="status-badge with_doctor">✅ Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) . '</span>';
                            } else {
                                $doctor_status = '<span class="status-badge without_doctor">⚠️ No Doctor</span>';
                            }
                            
                            $appointment_count = $patient['active_appointments'] ?? 0;
                            
                            $reg_by_name = $patient['registered_by_display'] ?? 'System';
                            $reg_by_role = $patient['registered_by_role'] ?? 'reception';
                            
                            $reg_icon = 'fa-user';
                            $reg_class = '';
                            if ($reg_by_role === 'admin') {
                                $reg_icon = 'fa-user-shield';
                                $reg_class = 'role-admin';
                            } elseif ($reg_by_role === 'reception') {
                                $reg_icon = 'fa-user-tie';
                                $reg_class = 'role-reception';
                            } elseif ($reg_by_role === 'doctor') {
                                $reg_icon = 'fa-user-md';
                            }
                        ?>
                            <tr class="patient-row" 
                                data-name="<?= strtolower(htmlspecialchars($patient['full_name'] ?? '')) ?>"
                                data-id="<?= strtolower(htmlspecialchars($patient['patient_id'] ?? '')) ?>"
                                data-phone="<?= strtolower(htmlspecialchars($patient['phone'] ?? '')) ?>"
                                data-days="<?= $patient_days ?>"
                                data-has-doctor="<?= !empty($patient['assigned_doctor_name']) ? '1' : '0' ?>"
                                data-visits="<?= $patient['total_visits'] ?? 0 ?>"
                                data-status="<?= $status_class ?>">
                                <td class="col-sno"><?= $counter++ ?></td>
                                <td>
                                    <a href="patient_details.php?id=<?= (int)$patient['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                       class="patient-name-link">
                                        <?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="patient-id-text"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($patient['phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($patient['phone']) ?>" class="patient-contact-link">
                                            <?= htmlspecialchars($patient['phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="branch-badge-display">
                                        🏥 <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= $days_text ?></td>
                                <td><?= $doctor_status ?></td>
                                <td>
                                    <span style="font-weight:700;color:var(--text-black);font-size:0.9rem;"><?= $patient['total_visits'] ?? 0 ?></span>
                                    <?php if (!empty($patient['latest_visit']['status'])): ?>
                                        <span style="font-size:0.7rem;color:var(--text-muted);display:block;"><?= ucfirst($patient['latest_visit']['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="registered-by-badge <?= $reg_class ?>">
                                        <i class="fas <?= $reg_icon ?>"></i>
                                        <?= htmlspecialchars($reg_by_name) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                                    <?php if ($appointment_count > 0): ?>
                                        <span style="font-size:0.7rem;color:var(--purple);display:block;font-weight:600;">📅 <?= $appointment_count ?> appt(s)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons-group">
                                        <a href="patient_details.php?id=<?= (int)$patient['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                           class="btn btn-primary" title="View Patient">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <a href="edit_patient.php?id=<?= (int)$patient['id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                           class="btn btn-success" title="Edit Patient">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <button type="button" 
                                                class="btn btn-danger" 
                                                onclick="confirmDelete(<?= (int)$patient['id'] ?>, '<?= htmlspecialchars(addslashes($patient['full_name'])) ?>', '<?= htmlspecialchars($patient['patient_id']) ?>')" 
                                                title="Delete Patient">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- NO RESULTS -->
            <div id="noResultsMessage" style="display:none;text-align:center;padding:40px 20px;">
                <i class="fas fa-search" style="font-size:2rem;color:var(--border-color);display:block;margin-bottom:12px;"></i>
                <p style="color:var(--text-secondary);font-size:0.9rem;">No patients match your search</p>
                <p style="color:var(--text-muted);font-size:0.75rem;">Try adjusting your filter criteria</p>
                <button onclick="clearFilter()" class="btn btn-primary" style="margin-top:12px;">
                    <i class="fas fa-times"></i> Clear Filter
                </button>
            </div>
            
            <?php else: ?>
            <div style="text-align:center;padding:40px 20px;">
                <i class="fas fa-users" style="font-size:2rem;color:var(--border-color);display:block;margin-bottom:8px;"></i>
                <p style="color:var(--text-secondary);">No patients found</p>
                <?php if (!empty($search)): ?>
                    <p style="color:var(--text-muted);font-size:0.85rem;">Try adjusting your search criteria</p>
                <?php endif; ?>
                <a href="patients.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary" style="margin-top:12px;">Clear Filters</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Patients Management (Admin)
            <span style="margin:0 8px;">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
            <span style="margin:0 8px;">|</span>
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

<!-- DELETE MODAL -->
<div id="deleteModal" class="delete-modal-overlay">
    <div class="delete-modal-content">
        <div style="text-align:center;margin-bottom:20px;">
            <div style="width:70px;height:70px;border-radius:50%;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:2rem;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 style="font-size:1.3rem;font-weight:700;color:var(--danger);margin-bottom:6px;">
                ⚠️ DELETE PATIENT?
            </h3>
            <p style="font-size:0.85rem;color:var(--text-secondary);">
                This action <strong>CANNOT</strong> be undone!
            </p>
        </div>
        
        <div style="background:var(--danger-bg);border:2px solid var(--danger);border-radius:12px;padding:14px 18px;margin-bottom:16px;">
            <p style="font-size:0.85rem;color:var(--danger-dark);margin:0 0 8px 0;font-weight:600;">
                Patient to delete:
            </p>
            <p style="font-size:1rem;color:var(--danger-dark);margin:0;font-weight:700;" id="deletePatientName">-</p>
            <p style="font-size:0.8rem;color:var(--danger-dark);margin:4px 0 0 0;font-family:monospace;" id="deletePatientId">-</p>
        </div>
        
        <div style="background:var(--warning-bg);border:2px solid var(--warning);border-radius:12px;padding:14px 18px;margin-bottom:16px;">
            <p style="font-size:0.8rem;color:var(--warning);margin:0 0 8px 0;font-weight:600;">
                🗑️ This will DELETE ALL related data:
            </p>
            <ul style="font-size:0.75rem;color:var(--warning);margin:0;padding-left:20px;line-height:1.8;">
                <li>💰 Bills, Payments & Receipts</li>
                <li>📋 Visits & Appointments</li>
                <li>💊 Prescriptions & Medications</li>
                <li>🔬 Lab Tests & Results</li>
                <li>📊 Vital Signs</li>
                <li>🔄 Referrals</li>
                <li>💳 OTC Sales</li>
                <li>📝 Patient Documents</li>
                <li>📦 Stock Movements</li>
                <li>📧 Notifications</li>
            </ul>
        </div>
        
        <div style="background:var(--primary-bg);border:2px solid var(--primary);border-radius:12px;padding:14px 18px;margin-bottom:20px;">
            <p style="font-size:0.8rem;color:var(--primary);margin:0;font-weight:600;">
                💡 <strong>Revenue Adjustment:</strong>
            </p>
            <p style="font-size:0.75rem;color:var(--primary);margin:4px 0 0 0;">
                All payments made by this patient will be <strong>subtracted</strong> from Total Revenue automatically.
            </p>
        </div>
        
        <div style="display:flex;gap:10px;">
            <button type="button" onclick="closeDeleteModal()" style="flex:1;padding:12px 20px;border-radius:10px;border:2px solid var(--border-color);background:transparent;color:var(--text-secondary);font-weight:600;font-size:0.85rem;cursor:pointer;">
                <i class="fas fa-times"></i> Cancel
            </button>
            <a id="confirmDeleteBtn" href="#" style="flex:1;padding:12px 20px;border-radius:10px;border:none;background:var(--danger);color:white;font-weight:700;font-size:0.85rem;cursor:pointer;text-align:center;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px;">
                <i class="fas fa-trash"></i> YES, DELETE
            </a>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE JAVASCRIPT - PEKEE -->
<!-- ================================================================ -->
<script>
var filterTimeout = null;

function autoFilter() {
    var searchValue = document.getElementById('searchFilter').value;
    var filterValue = document.getElementById('filterSelect').value;
    
    var loading = document.getElementById('filterLoading');
    if (loading) loading.classList.add('show');
    
    var clearBtn = document.getElementById('clearFilterBtn');
    if (clearBtn) {
        if (searchValue.trim() !== '' || filterValue !== 'all') {
            clearBtn.classList.add('show');
        } else {
            clearBtn.classList.remove('show');
        }
    }
    
    if (filterTimeout) clearTimeout(filterTimeout);
    
    filterTimeout = setTimeout(function() {
        filterTableRows(searchValue, filterValue);
        setTimeout(function() {
            if (loading) loading.classList.remove('show');
        }, 300);
    }, 300);
}

function filterTableRows(searchValue, filterValue) {
    var rows = document.querySelectorAll('.patient-row');
    var visibleCount = 0;
    var searchLower = searchValue.toLowerCase().trim();
    
    rows.forEach(function(row) {
        var name = row.getAttribute('data-name') || '';
        var id = row.getAttribute('data-id') || '';
        var phone = row.getAttribute('data-phone') || '';
        var days = parseInt(row.getAttribute('data-days')) || 0;
        var hasDoctor = row.getAttribute('data-has-doctor') === '1';
        var visits = parseInt(row.getAttribute('data-visits')) || 0;
        
        var show = true;
        
        if (searchLower !== '') {
            var matchesSearch = name.includes(searchLower) || id.includes(searchLower) || phone.includes(searchLower);
            if (!matchesSearch) show = false;
        }
        
        if (filterValue === 'new' && days > 7) show = false;
        if (filterValue === 'with_doctor' && !hasDoctor) show = false;
        if (filterValue === 'without_doctor' && hasDoctor) show = false;
        if (filterValue === 'no_visit' && visits > 0) show = false;
        
        if (show) { row.style.display = ''; visibleCount++; }
        else { row.style.display = 'none'; }
    });
    
    updateRowNumbers();
    
    var countBadge = document.getElementById('patientCountBadge');
    if (countBadge) countBadge.textContent = visibleCount + ' patient' + (visibleCount !== 1 ? 's' : '');
    
    var statusText = document.getElementById('filterStatusText');
    if (statusText) {
        if (searchLower !== '' || filterValue !== 'all') {
            statusText.textContent = 'Filtered: ' + visibleCount;
        } else {
            statusText.textContent = 'Auto-filter';
        }
    }
    
    var noResults = document.getElementById('noResultsMessage');
    var tableWrapper = document.getElementById('tableScrollWrapper');
    
    if (visibleCount === 0 && rows.length > 0) {
        if (noResults) noResults.style.display = 'block';
        if (tableWrapper) tableWrapper.style.display = 'none';
    } else {
        if (noResults) noResults.style.display = 'none';
        if (tableWrapper) tableWrapper.style.display = 'block';
    }
    
    setTimeout(updateScrollButtons, 150);
}

function updateRowNumbers() {
    var rows = document.querySelectorAll('.patient-row');
    var counter = 1;
    rows.forEach(function(row) {
        if (row.style.display !== 'none') {
            var snoCell = row.querySelector('.col-sno');
            if (snoCell) snoCell.textContent = counter;
            counter++;
        }
    });
}

function clearFilter() {
    document.getElementById('searchFilter').value = '';
    document.getElementById('filterSelect').value = 'all';
    autoFilter();
    
    var url = new URL(window.location.href);
    url.searchParams.delete('search');
    url.searchParams.delete('filter');
    window.history.replaceState({}, '', url.toString());
    
    showToast('🔄 Cleared', 'Filters have been cleared', 'info');
}

function scrollTable(direction) {
    var wrapper = document.getElementById('tableScrollWrapper');
    if (!wrapper) return;
    var scrollAmount = 400;
    
    if (direction === 'left') {
        wrapper.scrollLeft -= scrollAmount;
    } else {
        wrapper.scrollLeft += scrollAmount;
    }
    
    setTimeout(updateScrollButtons, 350);
}

function updateScrollButtons() {
    var wrapper = document.getElementById('tableScrollWrapper');
    var leftBtn = document.getElementById('scrollLeftBtn');
    var rightBtn = document.getElementById('scrollRightBtn');
    
    if (!wrapper || !leftBtn || !rightBtn) return;
    
    var maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
    
    leftBtn.disabled = wrapper.scrollLeft <= 10;
    rightBtn.disabled = wrapper.scrollLeft >= maxScroll - 10 || maxScroll <= 0;
}

document.addEventListener('DOMContentLoaded', function() {
    var wrapper = document.getElementById('tableScrollWrapper');
    if (wrapper) {
        wrapper.addEventListener('scroll', updateScrollButtons);
        setTimeout(updateScrollButtons, 300);
    }
    
    window.addEventListener('resize', function() {
        setTimeout(updateScrollButtons, 200);
    });
});

function confirmDelete(patientId, patientName, patientNumber) {
    document.getElementById('deletePatientName').textContent = patientName;
    document.getElementById('deletePatientId').textContent = 'ID: ' + patientNumber;
    
    var branchParam = '<?= urlencode($selected_branch_id) ?>';
    document.getElementById('confirmDeleteBtn').href = 'patients.php?branch=' + branchParam + '&delete=' + patientId;
    
    document.getElementById('deleteModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
    
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchFilter').focus();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    var searchValue = document.getElementById('searchFilter').value;
    var filterValue = document.getElementById('filterSelect').value;
    
    if (searchValue !== '' || filterValue !== 'all') {
        filterTableRows(searchValue, filterValue);
    }
    
    if (searchValue !== '') {
        document.getElementById('searchFilter').focus();
    }
});

// Footer time update only
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ft = document.getElementById('footerTimestamp');
    if (ft) ft.textContent = timeStr;
}, 1000);

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

<?php if ($success === 'deleted'): ?>
    showToast('🗑️ Deleted', 'Patient and all related data have been deleted', 'success');
<?php endif; ?>

console.log('%c👑 Braick - Admin Patients (Shared Header/Sidebar)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Inatumia SHARED HEADER & SIDEBAR pekee', 'font-size:12px;color:#34D399;');
console.log('%c✅ BLUE THEME applied', 'font-size:12px;color:#34D399;');
console.log('%c✅ REGISTERED BY column added', 'font-size:12px;color:#7C3AED;font-weight:bold;');
console.log('%c📊 Total Patients: <?= $stats['total'] ?? 0 ?>', 'font-size:12px;color:#059669;');
</script>

</body>
</html>