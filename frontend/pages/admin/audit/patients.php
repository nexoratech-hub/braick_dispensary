<?php
// ================================================================
// FILE: frontend/pages/admin/audit/patients.php
// ADMIN AUDIT - PATIENTS REPORT (V3 - IMPROVED BUTTONS)
// ✅ Action buttons zinaonekana vizuri (View, Edit, Delete)
// ✅ Table layout imeboreshwa
// ✅ Row height imeongezwa
// ✅ Hover effects nzuri
// ✅ Search + Scroll buttons
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$selected_branch_id = $_GET['branch'] ?? 'all';

require_once __DIR__ . '/../../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// HANDLE DELETE
// ================================================================
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    if ($patient_id > 0) {
        try {
            $stmt = $db->prepare("SELECT full_name, patient_id FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($p) {
                try { $db->prepare("DELETE FROM vital_signs WHERE patient_id = ?")->execute([$patient_id]); } catch (Exception $e) {}
                try { $db->prepare("DELETE FROM appointments WHERE patient_id = ?")->execute([$patient_id]); } catch (Exception $e) {}
                try { $db->prepare("DELETE FROM referrals WHERE patient_id = ?")->execute([$patient_id]); } catch (Exception $e) {}
                
                $db->prepare("DELETE FROM patients WHERE id = ?")->execute([$patient_id]);
                
                try {
                    $db->prepare("INSERT INTO activity_logs (user_id, action, description, branch_id, ip_address, created_at) 
                                  VALUES (?, 'DELETE_PATIENT', ?, ?, ?, NOW())")
                       ->execute([
                           $user_id,
                           "Deleted patient: " . ($p['full_name'] ?? 'N/A') . " (" . ($p['patient_id'] ?? 'N/A') . ")",
                           $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                           $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                       ]);
                } catch (Exception $e) {}
                
                $alert_message = "Patient " . ($p['full_name'] ?? 'N/A') . " deleted successfully!";
                $alert_type = 'success';
            }
        } catch (Exception $e) {
            $alert_message = "Error: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
}

// FILTERS
$search = $_GET['search'] ?? '';
$gender_filter = $_GET['gender'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$branch_cond = "";
$branch_params = [];
if ($selected_branch_id !== 'all') {
    $branch_cond = " AND p.branch_id = ?";
    $branch_params[] = (int)$selected_branch_id;
}

$search_cond = "";
$search_params = [];
if (!empty($search)) {
    $search_cond = " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
    $search_params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$gender_cond = "";
$gender_params = [];
if ($gender_filter !== 'all') {
    $gender_cond = " AND p.gender = ?";
    $gender_params[] = $gender_filter;
}

$date_params = [$date_from, $date_to];

// STATS
$stats = ['total' => 0, 'today' => 0, 'period' => 0, 'with_visits' => 0];
try {
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM patients p WHERE 1=1 $branch_cond");
    $stmt->execute($branch_params);
    $stats['total'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM patients p WHERE DATE(p.created_at) = CURDATE() $branch_cond");
    $stmt->execute($branch_params);
    $stats['today'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM patients p WHERE DATE(p.created_at) BETWEEN ? AND ? $branch_cond");
    $stmt->execute(array_merge($date_params, $branch_params));
    $stats['period'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
} catch (Exception $e) {}

// GENDER STATS
$male_count = 0;
$female_count = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM patients p WHERE p.gender = 'Male' $branch_cond");
    $stmt->execute($branch_params);
    $male_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM patients p WHERE p.gender = 'Female' $branch_cond");
    $stmt->execute($branch_params);
    $female_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
} catch (Exception $e) {}

// PATIENTS
$patients = [];
try {
    $sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.id) as total_visits,
        (SELECT COALESCE(SUM(b.total_amount), 0) FROM bills b WHERE b.patient_id = p.id AND b.status = 'paid') as total_spent,
        (SELECT MAX(v2.visit_date) FROM visits v2 WHERE v2.patient_id = p.id) as last_visit
        FROM patients p
        WHERE 1=1 $branch_cond $search_cond $gender_cond
        ORDER BY p.created_at DESC LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params, $search_params, $gender_params));
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $currency = $row['setting_value'];
} catch (Exception $e) {}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../../components/admin_audit_header.php';
include_once __DIR__ . '/../../../components/admin_audit_sidebar.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ================================================================
   COMPACT FONTS + THEME
   ================================================================ */
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --pink: #DB2777;
    --pink-bg: #FCE7F3;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 2px 8px rgba(0,0,0,0.06);
    --shadow-lg: 0 4px 16px rgba(0,0,0,0.1);
}

[data-theme="dark"] {
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-bg: #1E3A5F;
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --pink-bg: #4A1E36;
}

* {
    font-family: var(--font-primary);
    -webkit-font-smoothing: antialiased;
}

html, body {
    font-family: var(--font-primary);
    background: var(--bg-body);
    font-size: 14px;
}

.mono, .money-cell, .money-number, .stat-value,
.patient-id-badge, .avatar-circle, .phone-number {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    letter-spacing: -0.02em;
}

/* ================================================================
   PAGE HEADER - COMPACT
   ================================================================ */
.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 14px;
    padding: 16px 22px;
    margin-bottom: 16px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 16px rgba(11, 94, 215, 0.2);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(110, 168, 254, 0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.page-header .page-title {
    color: white;
    font-size: 1.15rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    letter-spacing: -0.02em;
}

.page-header .page-title i { 
    font-size: 1.3rem; 
    color: #BFDBFE;
}

.page-header .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.72rem;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 3px;
}

.branch-tag {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 2px 10px;
    border-radius: 14px;
    font-size: 0.62rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.15);
}

.btn-outline-light {
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 7px 14px;
    border-radius: 9px;
    font-weight: 600;
    font-size: 0.72rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    position: relative;
    z-index: 1;
    cursor: pointer;
}

.btn-outline-light:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-1px);
}

/* ================================================================
   ALERT
   ================================================================ */
.alert {
    padding: 10px 16px;
    border-radius: 10px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 0.78rem;
    animation: slideDown 0.4s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert.success { background: var(--success-bg); color: var(--success); border-left: 3px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 3px solid var(--danger); }
.alert i { font-size: 1rem; }

/* ================================================================
   FILTER CARD - COMPACT
   ================================================================ */
.filter-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 14px 18px;
    border: 1.5px solid var(--border-color);
    margin-bottom: 16px;
    box-shadow: var(--shadow-sm);
}

.filter-form {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
    gap: 10px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filter-group label {
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-secondary);
    letter-spacing: 0.04em;
}

.filter-group input,
.filter-group select {
    padding: 7px 11px;
    border-radius: 8px;
    border: 1.5px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.76rem;
    font-weight: 600;
    outline: none;
    transition: all 0.3s ease;
    height: 34px;
}

.filter-group input:focus,
.filter-group select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
}

.filter-btn-primary {
    padding: 7px 14px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
    font-weight: 700;
    font-size: 0.72rem;
    cursor: pointer;
    height: 34px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.filter-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.filter-btn-secondary {
    padding: 7px 14px;
    border-radius: 8px;
    background: transparent;
    color: var(--text-secondary);
    border: 1.5px solid var(--border-color);
    font-weight: 700;
    font-size: 0.72rem;
    height: 34px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.filter-btn-secondary:hover {
    border-color: var(--danger);
    color: var(--danger);
}

/* ================================================================
   STATS GRID - COMPACT
   ================================================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}

.stat-card {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 14px 16px;
    border: 1.5px solid var(--border-color);
    transition: all 0.3s ease;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
    min-height: 115px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.stat-card.total::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.stat-card.today::before { background: linear-gradient(90deg, #059669, #34D399); }
.stat-card.male::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }
.stat-card.female::before { background: linear-gradient(90deg, #DB2777, #F472B6); }

.stat-card .stat-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    color: white;
    margin-bottom: 8px;
    transition: transform 0.3s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.08) rotate(-5deg);
}

.stat-card.total .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.today .stat-icon { background: linear-gradient(135deg, #059669, #34D399); }
.stat-card.male .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.female .stat-icon { background: linear-gradient(135deg, #DB2777, #F472B6); }

.stat-card .stat-label {
    font-size: 0.6rem;
    color: var(--text-secondary);
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 3px;
    letter-spacing: 0.05em;
}

.stat-card .stat-value {
    font-size: 1.25rem;
    font-weight: 800;
    font-family: var(--font-mono);
    color: var(--text-primary);
    letter-spacing: -0.03em;
    line-height: 1.1;
}

.stat-card.total .stat-value { color: var(--primary); }
[data-theme="dark"] .stat-card.total .stat-value { color: #60A5FA; }
.stat-card.today .stat-value { color: var(--success); }
.stat-card.male .stat-value { color: var(--primary); }
[data-theme="dark"] .stat-card.male .stat-value { color: #60A5FA; }
.stat-card.female .stat-value { color: var(--pink); }
[data-theme="dark"] .stat-card.female .stat-value { color: #F472B6; }

.stat-card .stat-sub {
    font-size: 0.6rem;
    color: var(--text-secondary);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 6px;
}

.stat-card .stat-sub i {
    font-size: 0.55rem;
    opacity: 0.7;
}

/* ================================================================
   SECTION CARD + TABLE HEADER - COMPACT
   ================================================================ */
.section-card {
    background: var(--bg-card);
    border-radius: 12px;
    border: 1.5px solid var(--border-color);
    margin-bottom: 16px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.table-header-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 11px 16px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    flex-wrap: wrap;
}

.table-header-title {
    color: white;
    font-size: 0.82rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
}

.table-header-title i { 
    color: #BFDBFE; 
    font-size: 0.9rem;
}

.table-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 180px;
    max-width: 420px;
}

.search-box {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
}

.search-box .search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,0.7);
    font-size: 0.72rem;
    pointer-events: none;
}

.search-box input {
    width: 100%;
    padding: 7px 30px 7px 30px;
    border-radius: 8px;
    border: 1.5px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.15);
    color: white;
    font-size: 0.72rem;
    font-weight: 500;
    outline: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(4px);
    height: 32px;
}

.search-box input::placeholder {
    color: rgba(255,255,255,0.6);
}

.search-box input:focus {
    background: rgba(255,255,255,0.25);
    border-color: rgba(255,255,255,0.5);
    box-shadow: 0 0 0 3px rgba(255,255,255,0.15);
}

.search-box .search-clear {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.15);
    border: none;
    color: white;
    cursor: pointer;
    font-size: 0.65rem;
    width: 20px;
    height: 20px;
    border-radius: 5px;
    display: none;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.search-box .search-clear:hover {
    background: rgba(255,255,255,0.3);
}

.search-box.has-value .search-clear {
    display: flex;
}

.search-result-count {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 8px;
    font-size: 0.62rem;
    font-weight: 700;
    background: rgba(255,255,255,0.2);
    color: white;
    white-space: nowrap;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.15);
    height: 26px;
    transition: all 0.3s ease;
}

.search-result-count.has-results { 
    background: rgba(16, 185, 129, 0.3); 
    border-color: rgba(16, 185, 129, 0.5);
}

.search-result-count.no-results { 
    background: rgba(220, 38, 38, 0.3); 
    border-color: rgba(220, 38, 38, 0.5);
}

.table-header-right {
    display: flex;
    align-items: center;
    gap: 6px;
}

.scroll-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1.5px solid rgba(255,255,255,0.2);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 800;
    transition: all 0.25s ease;
    flex-shrink: 0;
    backdrop-filter: blur(4px);
}

.scroll-btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-1px);
}

.scroll-btn:active {
    transform: translateY(0) scale(0.95);
}

.table-wrapper {
    overflow-x: auto;
    scroll-behavior: smooth;
    position: relative;
}

.table-wrapper::-webkit-scrollbar { height: 6px; }
.table-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.table-wrapper::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

/* ================================================================
   DATA TABLE - IMPROVED
   ================================================================ */
.data-table { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 0.74rem;
    min-width: 1100px;
}

.data-table thead th {
    text-align: left;
    padding: 11px 14px;
    font-weight: 800;
    font-size: 0.58rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-primary);
    background: var(--primary-bg);
    white-space: nowrap;
    border-bottom: 1.5px solid var(--border-color);
    position: sticky;
    top: 0;
    z-index: 2;
}

[data-theme="dark"] .data-table thead th {
    background: #1E3A5F;
    color: #93C5FD;
}

.data-table tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
    font-weight: 500;
}

.data-table tbody tr { 
    transition: all 0.2s ease;
}

.data-table tbody tr:hover td { 
    background: var(--primary-bg); 
}

[data-theme="dark"] .data-table tbody tr:hover td { 
    background: #1E3A5F; 
}

.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr.hidden-row { display: none !important; }

/* ================================================================
   AVATAR - COMPACT
   ================================================================ */
.avatar-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.82rem;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    box-shadow: 0 2px 6px rgba(11, 94, 215, 0.25);
    flex-shrink: 0;
    text-transform: uppercase;
}

/* ================================================================
   BADGES - COMPACT
   ================================================================ */
.badge-gender {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 10px;
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
}

.badge-gender.male { 
    background: linear-gradient(135deg, #DBEAFE, #BFDBFE); 
    color: #1E40AF; 
    border: 1px solid #93C5FD;
}

.badge-gender.female { 
    background: linear-gradient(135deg, #FCE7F3, #FBCFE8); 
    color: #9D174D; 
    border: 1px solid #F9A8D4;
}

.badge-gender.other { 
    background: var(--border-color); 
    color: var(--text-secondary); 
}

[data-theme="dark"] .badge-gender.male { background: #1E3A8A; color: #93C5FD; border-color: #3B82F6; }
[data-theme="dark"] .badge-gender.female { background: #4A1E36; color: #F9A8D4; border-color: #DB2777; }

.patient-id-badge {
    font-family: var(--font-mono);
    font-size: 0.65rem;
    color: var(--primary);
    font-weight: 800;
    background: var(--primary-bg);
    padding: 4px 10px;
    border-radius: 6px;
    letter-spacing: -0.02em;
    display: inline-block;
    border: 1px solid rgba(11, 94, 215, 0.2);
}

[data-theme="dark"] .patient-id-badge {
    background: #1E3A5F;
    color: #93C5FD;
    border-color: rgba(96, 165, 250, 0.3);
}

/* ================================================================
   MONEY CELL - COMPACT
   ================================================================ */
.money-cell {
    font-family: var(--font-mono);
    font-weight: 800;
    font-size: 0.78rem;
    color: var(--success);
    text-align: right;
    letter-spacing: -0.02em;
    white-space: nowrap;
}

.money-cell .currency-prefix {
    font-size: 0.62rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-right: 2px;
}

/* ================================================================
   ✅ V3: ACTION BUTTONS - IMPROVED (With Labels!)
   ================================================================ */
.action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
    align-items: center;
    flex-wrap: nowrap;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 0.68rem;
    font-weight: 800;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    white-space: nowrap;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    font-family: var(--font-primary);
    position: relative;
    overflow: hidden;
    min-width: 68px;
}

.btn-action::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s ease;
}

.btn-action:hover::before {
    left: 100%;
}

.btn-action i {
    font-size: 0.72rem;
}

.btn-action:hover {
    transform: translateY(-2px);
}

.btn-action:active {
    transform: translateY(0) scale(0.97);
}

/* VIEW Button - Blue */
.btn-action.view {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    box-shadow: 0 2px 6px rgba(11, 94, 215, 0.3);
}

.btn-action.view:hover {
    background: linear-gradient(135deg, #0A4CA8, #083C8A);
    box-shadow: 0 6px 16px rgba(11, 94, 215, 0.5);
    color: white;
}

/* EDIT Button - Amber */
.btn-action.edit {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
}

.btn-action.edit:hover {
    background: linear-gradient(135deg, #D97706, #B45309);
    box-shadow: 0 6px 16px rgba(245, 158, 11, 0.5);
    color: white;
}

/* DELETE Button - Red */
.btn-action.delete {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
}

.btn-action.delete:hover {
    background: linear-gradient(135deg, #B91C1C, #991B1B);
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.5);
    color: white;
}

/* Dark mode adjustments */
[data-theme="dark"] .btn-action.view {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
}

[data-theme="dark"] .btn-action.edit {
    background: linear-gradient(135deg, #FBBF24, #F59E0B);
}

[data-theme="dark"] .btn-action.delete {
    background: linear-gradient(135deg, #EF4444, #DC2626);
}

mark.search-highlight {
    background: #FEF08A;
    color: #713F12;
    padding: 1px 2px;
    border-radius: 3px;
    font-weight: 800;
}

[data-theme="dark"] mark.search-highlight {
    background: #78350F;
    color: #FEF3C7;
}

/* ================================================================
   EMPTY STATE - COMPACT
   ================================================================ */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 2rem;
    opacity: 0.3;
    margin-bottom: 10px;
    display: block;
    color: var(--primary);
}

.empty-state p {
    font-weight: 600;
    font-size: 0.78rem;
}

/* ================================================================
   DELETE MODAL - COMPACT
   ================================================================ */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(6px);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.active { display: flex; }

.modal-box {
    background: var(--bg-card);
    border-radius: 16px;
    max-width: 400px;
    width: 100%;
    padding: 26px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    text-align: center;
}

@keyframes modalPop {
    0% { opacity: 0; transform: scale(0.8) translateY(20px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-icon {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: var(--danger-bg);
    color: var(--danger);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin: 0 auto 14px;
    animation: iconPulse 1.5s infinite;
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
}

.modal-title {
    font-size: 1.05rem;
    font-weight: 800;
    margin-bottom: 8px;
    color: var(--text-primary);
}

.modal-text {
    font-size: 0.78rem;
    color: var(--text-secondary);
    margin-bottom: 20px;
    line-height: 1.6;
}

.modal-text strong {
    color: var(--primary);
    background: var(--primary-bg);
    padding: 2px 8px;
    border-radius: 5px;
    font-family: var(--font-mono);
    font-weight: 700;
    font-size: 0.72rem;
}

.modal-warning {
    color: var(--danger);
    font-weight: 700;
    display: block;
    margin-top: 5px;
    font-size: 0.72rem;
}

.modal-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.modal-btn {
    padding: 9px 20px;
    border-radius: 9px;
    font-weight: 700;
    font-size: 0.76rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.modal-btn.cancel { 
    background: var(--border-color); 
    color: var(--text-primary); 
}

.modal-btn.cancel:hover { 
    background: #CBD5E1; 
    transform: translateY(-1px); 
}

.modal-btn.danger { 
    background: linear-gradient(135deg, #DC2626, #B91C1C); 
    color: white; 
}

.modal-btn.danger:hover { 
    transform: translateY(-1px); 
    box-shadow: 0 4px 16px rgba(220, 38, 38, 0.4); 
}

/* ================================================================
   RESPONSIVE
   ================================================================ */
@media (max-width: 1024px) {
    .filter-form { grid-template-columns: 1fr 1fr; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .page-header { padding: 14px 16px; }
    .page-header .page-title { font-size: 1rem; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 12px; min-height: 100px; }
    .filter-form { grid-template-columns: 1fr; }
    
    .table-header-custom {
        flex-direction: column;
        align-items: stretch;
    }
    
    .table-header-left,
    .table-header-right {
        width: 100%;
        max-width: 100%;
    }
    
    .table-header-right {
        justify-content: flex-end;
    }
    
    .data-table { font-size: 0.68rem; min-width: 1000px; }
    .data-table thead th,
    .data-table tbody td { padding: 9px 10px; }
    
    /* Make buttons smaller but keep labels */
    .btn-action {
        padding: 6px 9px;
        font-size: 0.6rem;
        min-width: 58px;
    }
    
    .btn-action i { font-size: 0.65rem; }
    .btn-action span { display: inline; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .search-result-count { display: none; }
    
    /* Hide labels on very small screens, show icons only */
    .btn-action span { display: none; }
    .btn-action { min-width: 36px; padding: 7px 9px; }
    .btn-action i { font-size: 0.78rem; }
}
</style>

<main class="main-content">

    <!-- ALERT -->
    <?php if (!empty($alert_message)): ?>
        <div class="alert <?= $alert_type ?>" id="alertBox">
            <i class="fas fa-<?= $alert_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <span><?= htmlspecialchars($alert_message) ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) { 
                    el.style.transition = 'all 0.5s ease'; 
                    el.style.opacity = '0'; 
                    setTimeout(function() { el.remove(); }, 500); 
                }
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-injured"></i>
                Patients Report
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-users"></i>
                Patient Demographics & Activity
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> 
                    <?= $selected_branch_id === 'all' ? 'All Branches' : htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar"></i> <?= date('d M Y') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="/dispensary_system/frontend/pages/admin/audit/dashboard.php?branch=<?= $selected_branch_id ?>" 
               class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-card">
        <form method="GET" class="filter-form">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Search Patient</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name, ID, phone...">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-venus-mars"></i> Gender</label>
                <select name="gender">
                    <option value="all">All</option>
                    <option value="Male" <?= $gender_filter === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $gender_filter === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= $gender_filter === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            
            <button type="submit" class="filter-btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
            
            <a href="?branch=<?= $selected_branch_id ?>" class="filter-btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </form>
    </div>

    <!-- STATS - GENDER CARDS -->
    <div class="stats-grid">
        
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
            <div class="stat-sub">
                <i class="fas fa-database"></i> All registered
            </div>
        </div>
        
        <div class="stat-card today">
            <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
            <div class="stat-label">Today</div>
            <div class="stat-value"><?= number_format($stats['today']) ?></div>
            <div class="stat-sub">
                <i class="fas fa-clock"></i> <?= date('d M Y') ?>
            </div>
        </div>
        
        <div class="stat-card male">
            <div class="stat-icon"><i class="fas fa-mars"></i></div>
            <div class="stat-label">Male Patients</div>
            <div class="stat-value"><?= number_format($male_count) ?></div>
            <div class="stat-sub">
                <i class="fas fa-percentage"></i> 
                <?php 
                $male_pct = $stats['total'] > 0 ? round(($male_count / $stats['total']) * 100, 1) : 0;
                echo $male_pct;
                ?>% of total
            </div>
        </div>
        
        <div class="stat-card female">
            <div class="stat-icon"><i class="fas fa-venus"></i></div>
            <div class="stat-label">Female Patients</div>
            <div class="stat-value"><?= number_format($female_count) ?></div>
            <div class="stat-sub">
                <i class="fas fa-percentage"></i> 
                <?php 
                $female_pct = $stats['total'] > 0 ? round(($female_count / $stats['total']) * 100, 1) : 0;
                echo $female_pct;
                ?>% of total
            </div>
        </div>
        
    </div>

    <!-- PATIENTS TABLE -->
    <div class="section-card">
        
        <div class="table-header-custom">
            
            <div class="table-header-left">
                <div class="table-header-title">
                    <i class="fas fa-list"></i>
                    Patient Records
                </div>
                
                <div class="search-box" id="tableSearchBox">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           id="tableSearch" 
                           placeholder="Search name, ID, phone..."
                           oninput="filterTable(this.value)"
                           autocomplete="off">
                    <button type="button" 
                            class="search-clear" 
                            onclick="clearTableSearch()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <span class="search-result-count" id="searchCount">
                    <i class="fas fa-users"></i>
                    <span id="countText"><?= count($patients) ?></span>
                </span>
            </div>
            
            <div class="table-header-right">
                <button type="button" 
                        class="scroll-btn" 
                        onclick="scrollTable('tableWrapper', -1)"
                        title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" 
                        class="scroll-btn" 
                        onclick="scrollTable('tableWrapper', 1)"
                        title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-wrapper" id="tableWrapper">
            <table class="data-table" id="patientsTable">
                <thead>
                    <tr>
                        <th style="width:45px;text-align:center;">#</th>
                        <th style="min-width:200px;">Patient</th>
                        <th style="min-width:130px;">Patient ID</th>
                        <th style="min-width:100px;">Gender</th>
                        <th style="min-width:130px;">Phone</th>
                        <th style="width:80px;text-align:center;">Visits</th>
                        <th style="width:130px;text-align:right;">Total Spent</th>
                        <th style="min-width:110px;">Last Visit</th>
                        <th style="width:240px;text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($patients) > 0): ?>
                        <?php $row_num = 1; foreach ($patients as $p): 
                            $gender = strtolower($p['gender'] ?? 'male');
                            $gender_class = $gender === 'female' ? 'female' : ($gender === 'other' ? 'other' : 'male');
                            $gender_icon = $gender === 'female' ? 'venus' : ($gender === 'other' ? 'genderless' : 'mars');
                            $initial = strtoupper(substr($p['full_name'] ?? 'P', 0, 1));
                        ?>
                            <tr class="patient-row">
                                <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-family:var(--font-mono);font-size:0.72rem;">
                                    <?= $row_num++ ?>
                                </td>
                                <td class="searchable-cell">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="avatar-circle"><?= $initial ?></div>
                                        <div>
                                            <div style="font-weight:700;font-size:0.78rem;color:var(--text-primary);"><?= htmlspecialchars($p['full_name'] ?? 'N/A') ?></div>
                                            <?php if (!empty($p['email'])): ?>
                                                <div style="font-size:0.62rem;color:var(--text-secondary);margin-top:1px;">
                                                    <i class="fas fa-envelope" style="font-size:0.55rem;"></i>
                                                    <?= htmlspecialchars($p['email']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="searchable-cell">
                                    <span class="patient-id-badge">
                                        <?= htmlspecialchars($p['patient_id'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="searchable-cell">
                                    <span class="badge-gender <?= $gender_class ?>">
                                        <i class="fas fa-<?= $gender_icon ?>"></i>
                                        <?= htmlspecialchars(ucfirst($p['gender'] ?? 'N/A')) ?>
                                    </span>
                                </td>
                                <td class="searchable-cell" style="font-size:0.72rem;font-family:var(--font-mono);font-weight:600;">
                                    <?php if (!empty($p['phone'])): ?>
                                        <i class="fas fa-phone" style="font-size:0.58rem;color:var(--text-secondary);"></i>
                                        <?= htmlspecialchars($p['phone']) ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.68rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-weight:800;color:var(--primary);font-family:var(--font-mono);font-size:0.85rem;background:var(--primary-bg);padding:3px 10px;border-radius:6px;display:inline-block;">
                                        <?= number_format($p['total_visits'] ?? 0) ?>
                                    </span>
                                </td>
                                <td class="money-cell">
                                    <span class="currency-prefix"><?= $currency ?></span><?= number_format($p['total_spent'] ?? 0, 0) ?>
                                </td>
                                <td style="font-size:0.68rem;color:var(--text-secondary);font-family:var(--font-mono);">
                                    <?php if ($p['last_visit']): ?>
                                        <i class="fas fa-calendar-check" style="font-size:0.58rem;color:var(--success);"></i>
                                        <?= date('d M Y', strtotime($p['last_visit'])) ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-style:italic;">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="patient_details.php?id=<?= $p['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action view" 
                                           title="View Patient Details"
                                           target="_blank">
                                            <i class="fas fa-eye"></i>
                                            <span>View</span>
                                        </a>
                                        
                                        <a href="patient_edit.php?id=<?= $p['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action edit" 
                                           title="Edit Patient">
                                            <i class="fas fa-edit"></i>
                                            <span>Edit</span>
                                        </a>
                                        
                                        <button type="button" 
                                                class="btn-action delete" 
                                                title="Delete Patient"
                                                onclick="confirmDeletePatient(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['full_name'] ?? 'N/A')) ?>', '<?= htmlspecialchars(addslashes($p['patient_id'] ?? 'N/A')) ?>')">
                                            <i class="fas fa-trash"></i>
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr id="noResultsRow" style="display:none;">
                            <td colspan="9" class="empty-state">
                                <i class="fas fa-search"></i>
                                <p>No matching patients found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <i class="fas fa-user-injured"></i>
                                <p>No patients found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title">Delete Patient?</h3>
        <p class="modal-text">
            Are you sure you want to delete<br>
            <strong id="deletePatientName">-</strong><br>
            <span style="font-size:0.7rem;">ID: <span id="deletePatientId">-</span></span>
            <span class="modal-warning">
                <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
            </span>
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="patient_id" id="deletePatientIdInput" value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function filterTable(searchTerm) {
    var term = (searchTerm || '').trim().toLowerCase();
    var rows = document.querySelectorAll('#patientsTable tbody tr.patient-row');
    var visibleCount = 0;
    var searchBox = document.getElementById('tableSearchBox');
    var countEl = document.getElementById('searchCount');
    var countText = document.getElementById('countText');
    var noResultsRow = document.getElementById('noResultsRow');
    
    if (searchBox) searchBox.classList.toggle('has-value', term.length > 0);
    
    rows.forEach(function(row) {
        resetHighlights(row);
        
        var rowText = '';
        row.querySelectorAll('.searchable-cell').forEach(function(cell) {
            rowText += ' ' + cell.textContent;
        });
        rowText = rowText.toLowerCase();
        
        if (term === '' || rowText.indexOf(term) !== -1) {
            row.classList.remove('hidden-row');
            row.style.display = '';
            visibleCount++;
            if (term !== '') highlightMatches(row, term);
        } else {
            row.classList.add('hidden-row');
            row.style.display = 'none';
        }
    });
    
    if (noResultsRow) {
        noResultsRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
    }
    
    if (countEl && countText) {
        countEl.classList.remove('has-results', 'no-results');
        
        if (term === '') {
            countText.textContent = visibleCount;
        } else if (visibleCount > 0) {
            countEl.classList.add('has-results');
            countText.textContent = visibleCount + ' found';
        } else {
            countEl.classList.add('no-results');
            countText.textContent = 'No results';
        }
    }
}

function highlightMatches(row, term) {
    var cells = row.querySelectorAll('.searchable-cell');
    cells.forEach(function(cell) {
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = cell.innerHTML;
        
        var walker = document.createTreeWalker(tempDiv, NodeFilter.SHOW_TEXT, null, false);
        var textNodes = [];
        var node;
        while (node = walker.nextNode()) textNodes.push(node);
        
        textNodes.forEach(function(textNode) {
            var text = textNode.nodeValue;
            var lowerText = text.toLowerCase();
            var lowerTerm = term.toLowerCase();
            
            if (lowerText.indexOf(lowerTerm) === -1) return;
            
            var fragments = [];
            var lastIndex = 0;
            var index;
            
            while ((index = lowerText.indexOf(lowerTerm, lastIndex)) !== -1) {
                if (index > lastIndex) fragments.push(document.createTextNode(text.substring(lastIndex, index)));
                
                var mark = document.createElement('mark');
                mark.className = 'search-highlight';
                mark.textContent = text.substring(index, index + term.length);
                fragments.push(mark);
                
                lastIndex = index + term.length;
            }
            
            if (lastIndex < text.length) fragments.push(document.createTextNode(text.substring(lastIndex)));
            
            if (fragments.length > 0) {
                var parent = textNode.parentNode;
                fragments.forEach(function(frag) { parent.insertBefore(frag, textNode); });
                parent.removeChild(textNode);
            }
        });
        
        cell.innerHTML = tempDiv.innerHTML;
    });
}

function resetHighlights(row) {
    var marks = row.querySelectorAll('mark.search-highlight');
    marks.forEach(function(mark) {
        var parent = mark.parentNode;
        parent.replaceChild(document.createTextNode(mark.textContent), mark);
        parent.normalize();
    });
}

function clearTableSearch() {
    var input = document.getElementById('tableSearch');
    if (input) {
        input.value = '';
        filterTable('');
        input.focus();
    }
}

function scrollTable(wrapperId, direction) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    wrapper.scrollTo({
        left: wrapper.scrollLeft + (direction * 300),
        behavior: 'smooth'
    });
}

function confirmDeletePatient(patientId, patientName, patientCode) {
    document.getElementById('deletePatientIdInput').value = patientId;
    document.getElementById('deletePatientName').textContent = patientName;
    document.getElementById('deletePatientId').textContent = patientCode;
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
        var activeInput = document.activeElement;
        if (activeInput && activeInput.id === 'tableSearch') {
            clearTableSearch();
        }
    }
});

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        var searchInput = document.getElementById('tableSearch');
        if (searchInput) searchInput.focus();
    }
});

console.log('%c📋 Patients Report V3 - IMPROVED BUTTONS', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Action buttons: View (blue), Edit (amber), Delete (red)', 'font-size:12px; color:#34D399;');
console.log('%c✅ Buttons zina labels + icons', 'font-size:12px; color:#34D399;');
console.log('%c♂ Male: <?= $male_count ?> | ♀ Female: <?= $female_count ?>', 'font-size:12px; color:#3B82F6;');
</script>

</body>
</html>