<?php
// ================================================================
// FILE: frontend/pages/admin/branches.php
// SUPER ADMIN - BRANCHES MANAGEMENT
// ✅ Uses SHARED header & sidebar
// ✅ Beautiful blue cards & stat cards
// ✅ Full dark mode support
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit();
}

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$selected_branch_id = $_GET['branch'] ?? 'all';
$search_term = $_GET['search'] ?? '';
$message = '';
$message_type = '';

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if ($id > 0 && in_array($status, ['active', 'inactive'])) {
            try {
                $stmt = $db->prepare("UPDATE branches SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $id]);
                $message = "✅ Branch status updated to <strong>" . ucfirst($status) . "</strong>";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches_for_filter = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches_for_filter = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $branches_for_filter = []; }

// ================================================================
// FETCH BRANCHES
// ================================================================
$query = "SELECT b.* FROM branches b WHERE 1=1";
$params = [];

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $query .= " AND b.id = :branch_id";
    $params[':branch_id'] = (int)$selected_branch_id;
}

if (!empty($search_term)) {
    $query .= " AND (b.name LIKE :search OR b.location LIKE :search OR b.phone LIKE :search OR b.email LIKE :search)";
    $params[':search'] = '%' . $search_term . '%';
}

$query .= " ORDER BY b.name ASC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$branches_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// ENRICH BRANCH DATA
// ================================================================
$branches = [];
$total_staff = 0;
$total_patients = 0;
$active_branches = 0;

foreach ($branches_raw as $branch) {
    $staff_stmt = $db->prepare("SELECT role, COUNT(*) as count FROM users WHERE branch_id = ? AND status = 'active' GROUP BY role");
    $staff_stmt->execute([$branch['id']]);
    $staff_counts = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

    $admin_count = 0; $doctor_count = 0; $reception_count = 0;
    $pharmacy_count = 0; $cashier_count = 0; $lab_count = 0;

    foreach ($staff_counts as $sc) {
        switch ($sc['role']) {
            case 'admin': $admin_count = $sc['count']; break;
            case 'doctor': $doctor_count = $sc['count']; break;
            case 'reception': $reception_count = $sc['count']; break;
            case 'pharmacy': $pharmacy_count = $sc['count']; break;
            case 'cashier': $cashier_count = $sc['count']; break;
            case 'laboratory': $lab_count = $sc['count']; break;
        }
    }

    $branch_total_staff = $admin_count + $doctor_count + $reception_count + $pharmacy_count + $cashier_count + $lab_count;
    $total_staff += $branch_total_staff;

    $patient_stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
    $patient_stmt->execute([$branch['id']]);
    $patient_count = $patient_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $total_patients += $patient_count;

    $visits_stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status IN ('pending', 'assigned', 'with_doctor') THEN 1 END) as active_visits,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_visits
        FROM visits WHERE branch_id = ?
    ");
    $visits_stmt->execute([$branch['id']]);
    $visits_data = $visits_stmt->fetch(PDO::FETCH_ASSOC);

    if ($branch['status'] === 'active') $active_branches++;

    $branch['admin_count'] = $admin_count;
    $branch['doctor_count'] = $doctor_count;
    $branch['reception_count'] = $reception_count;
    $branch['pharmacy_count'] = $pharmacy_count;
    $branch['cashier_count'] = $cashier_count;
    $branch['lab_count'] = $lab_count;
    $branch['patient_count'] = $patient_count;
    $branch['active_visits'] = $visits_data['active_visits'] ?? 0;
    $branch['completed_visits'] = $visits_data['completed_visits'] ?? 0;
    $branch['total_staff'] = $branch_total_staff;

    $branches[] = $branch;
}

$total_branches = count($branches);

$user_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS
     ================================================================ -->
<style>
    :root {
        --page-primary: #1A56DB;
        --page-primary-dark: #1A3E8C;
        --page-primary-bg: #E8EFF9;
        --page-primary-light: #3B82F6;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-purple: #7C3AED;
        --page-purple-bg: #EDE9FE;
        --page-bg-body: #F0F4F8;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-primary-bg: #1E3A5F;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
        --page-purple-bg: #2D1B5F;
        --page-primary: #3B82F6;
        --page-shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
        --page-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
    }

    /* BODY & MAIN CONTENT - DARK MODE */
    body { background: var(--page-bg-body); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* ================================================================
       BLUE PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #1A56DB 0%, #1A3E8C 50%, #163278 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(26, 86, 219, 0.3), 0 4px 12px rgba(26, 86, 219, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        color: white;
        position: relative;
        z-index: 2;
    }

    .page-header-title i {
        width: 44px; height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .role-badge-display {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .page-header-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .btn-outline-light {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       STAT CARDS - BLUE
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: linear-gradient(135deg, #1A56DB 0%, #1A3E8C 100%);
        border-radius: 16px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.3s ease;
        box-shadow: 0 6px 20px rgba(26, 86, 219, 0.25);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -40%; right: -20%;
        width: 180px; height: 180px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(26, 86, 219, 0.4);
    }

    .stat-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        background: rgba(255,255,255,0.2);
        color: white;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.15);
        position: relative;
        z-index: 1;
    }

    .stat-label {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.85);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .stat-value {
        font-size: 1.6rem;
        font-weight: 800;
        color: white;
        margin: 4px 0 0 0;
        line-height: 1.1;
        position: relative;
        z-index: 1;
    }

    /* ================================================================
       BRANCH CARDS
       ================================================================ */
    .branches-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 20px;
    }

    .branch-card {
        background: var(--page-bg-card);
        border-radius: 18px;
        border: 1px solid var(--page-border);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
    }

    .branch-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--page-shadow-lg);
        border-color: var(--page-primary);
    }

    .branch-card.active { border-left: 4px solid var(--page-primary); }
    .branch-card.inactive { border-left: 4px solid var(--page-danger); opacity: 0.9; }
    .branch-card.inactive:hover { opacity: 1; }

    .branch-card-header {
        padding: 16px 20px;
        background: linear-gradient(135deg, #1A56DB 0%, #1A3E8C 100%);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        position: relative;
        overflow: hidden;
    }

    .branch-card-header::before {
        content: '';
        position: absolute;
        top: -50%; right: -10%;
        width: 200px; height: 200px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .branch-info {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        position: relative;
        z-index: 1;
    }

    .branch-icon {
        width: 42px; height: 42px;
        border-radius: 10px;
        background: rgba(255,255,255,0.2);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.15);
    }

    .branch-name {
        font-size: 0.95rem;
        font-weight: 700;
        color: white;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .branch-code {
        font-size: 0.62rem;
        color: rgba(255,255,255,0.75);
        display: block;
        font-family: 'Courier New', monospace;
        font-weight: 600;
    }

    .branch-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.62rem;
        font-weight: 700;
        color: white;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.15);
        position: relative;
        z-index: 1;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .branch-status-badge.active {
        background: rgba(52, 211, 153, 0.3);
        border-color: rgba(52, 211, 153, 0.5);
        color: #D1FAE5;
    }

    .branch-status-badge.inactive {
        background: rgba(248, 113, 113, 0.3);
        border-color: rgba(248, 113, 113, 0.5);
        color: #FEE2E2;
    }

    .branch-card-body { padding: 16px 20px; }

    .branch-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 16px;
        margin-bottom: 14px;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.82rem;
        color: var(--page-text-primary);
        padding: 6px 0;
        border-bottom: 1px solid var(--page-border);
    }

    .detail-item:last-child { border-bottom: none; }

    .detail-item i {
        color: var(--page-primary);
        font-size: 0.85rem;
        width: 18px;
        text-align: center;
        flex-shrink: 0;
    }

    .detail-item .detail-label {
        color: var(--page-text-secondary);
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        min-width: 60px;
    }

    .detail-item .detail-value {
        color: var(--page-text-primary);
        font-weight: 600;
        word-break: break-all;
    }

    .branch-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        padding: 12px 0;
        border-top: 1px solid var(--page-border);
        border-bottom: 1px solid var(--page-border);
    }

    .stat-item { text-align: center; }

    .stat-item .stat-number {
        display: block;
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--page-text-primary);
    }

    .stat-item .stat-label {
        font-size: 0.58rem;
        color: var(--page-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
        font-weight: 700;
        margin-top: 2px;
        display: block;
    }

    .branch-card-footer {
        padding: 14px 20px;
        background: var(--page-bg-body);
        border-top: 1px solid var(--page-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    html[data-theme="dark"] .branch-card-footer { background: #0F172A; }

    .staff-breakdown {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }

    .staff-tag {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 0.6rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 10px;
        background: var(--page-bg-card);
        color: var(--page-text-secondary);
        border: 1px solid var(--page-border);
        white-space: nowrap;
    }

    html[data-theme="dark"] .staff-tag { background: #1E293B; }

    .staff-tag i { font-size: 0.55rem; }

    .branch-actions {
        display: flex;
        gap: 4px;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        background: var(--page-bg-card);
        color: var(--page-text-primary);
        border: 1.5px solid var(--page-border);
    }

    .btn:hover { transform: translateY(-2px); box-shadow: var(--page-shadow-md); }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.7rem;
        border-radius: 8px;
        min-width: 36px;
        justify-content: center;
    }

    .btn-primary {
        background: linear-gradient(135deg, #1A56DB, #1A3E8C);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(26, 86, 219, 0.25);
    }

    .btn-primary:hover {
        color: white;
        box-shadow: 0 6px 20px rgba(26, 86, 219, 0.4);
    }

    .btn-outline-primary {
        background: var(--page-primary-bg);
        color: var(--page-primary);
        border: 1.5px solid var(--page-primary);
    }

    .btn-outline-primary:hover {
        background: var(--page-primary);
        color: white;
        box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
    }

    .btn-outline-success {
        background: #D1FAE5;
        color: #065F46;
        border-color: #059669;
    }

    .btn-outline-success:hover {
        background: #059669;
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }

    .btn-outline-danger {
        background: #FEE2E2;
        color: #991B1B;
        border-color: #DC2626;
    }

    .btn-outline-danger:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }

    html[data-theme="dark"] .btn-outline-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }

    html[data-theme="dark"] .btn-outline-danger {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    /* ================================================================
       ALERT
       ================================================================ */
    .alert-modern {
        padding: 16px 20px;
        border-radius: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-modern-success { background: #D1FAE5; color: #047857; border: 2px solid #059669; }
    .alert-modern-error { background: #FEE2E2; color: #B91C1C; border: 2px solid #DC2626; }

    html[data-theme="dark"] .alert-modern-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    html[data-theme="dark"] .alert-modern-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }

    .alert-modern i { font-size: 1.2rem; margin-top: 2px; flex-shrink: 0; }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--page-bg-card);
        border-radius: 18px;
        border: 1px solid var(--page-border);
    }

    .empty-state i {
        font-size: 4rem;
        color: var(--page-text-secondary);
        opacity: 0.3;
        margin-bottom: 16px;
        display: block;
    }

    .empty-state h3 {
        font-size: 1.2rem;
        color: var(--page-text-primary);
        margin: 0 0 8px 0;
    }

    .empty-state p {
        color: var(--page-text-secondary);
        margin: 0 0 20px 0;
        font-size: 0.9rem;
    }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.4s ease forwards;
        opacity: 0;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .branches-grid { grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
    }

    @media (max-width: 768px) {
        .page-header-card { padding: 18px 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card { padding: 14px 16px; gap: 10px; }
        .stat-icon { width: 42px; height: 42px; font-size: 1rem; }
        .stat-value { font-size: 1.3rem; }
        .branches-grid { grid-template-columns: 1fr; }
        .branch-details { grid-template-columns: 1fr; }
        .branch-stats { grid-template-columns: repeat(2, 1fr); }
        .branch-card-footer { flex-direction: column; align-items: stretch; }
        .staff-breakdown { justify-content: center; }
        .branch-actions { justify-content: center; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .branch-card-header { flex-direction: column; align-items: stretch; text-align: center; }
        .branch-info { flex-direction: column; text-align: center; }
    }

    /* Print */
    @media print {
        .page-header-actions, .btn, .btn-outline-light, .branch-actions { display: none !important; }
        .page-header-card { background: #1A56DB !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .branch-card-header { background: #1A56DB !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .stat-card, .branch-status-badge, .staff-tag { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Blue Page Header Card -->
    <div class="page-header-card animate-fade-in-up">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-store-alt"></i>
                Branches Management
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-building"></i>
                Manage all dispensary branches
                <span class="page-header-badge">
                    <i class="fas fa-store"></i> <?= $total_branches ?> Total
                </span>
                <span class="page-header-badge" style="background:rgba(52,211,153,0.25);border-color:rgba(52,211,153,0.4);">
                    <i class="fas fa-check-circle" style="color:#34D399;"></i>
                    <?= $active_branches ?> Active
                </span>
                <span class="page-header-badge" style="background:rgba(248,113,113,0.25);border-color:rgba(248,113,113,0.4);">
                    <i class="fas fa-times-circle" style="color:#F87171;"></i>
                    <?= $total_branches - $active_branches ?> Inactive
                </span>
                <span class="page-header-badge" style="background:rgba(167,139,250,0.25);border-color:rgba(167,139,250,0.4);">
                    <i class="fas fa-users" style="color:#A78BFA;"></i>
                    <?= number_format($total_staff) ?> Staff
                </span>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="add_branch.php" class="btn-outline-light">
                <i class="fas fa-plus-circle"></i> Add Branch
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert-modern alert-modern-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="stats-grid animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <div>
                <p class="stat-label">Total Branches</p>
                <p class="stat-value"><?= $total_branches ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <p class="stat-label">Active Branches</p>
                <p class="stat-value"><?= $active_branches ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div>
                <p class="stat-label">Total Staff</p>
                <p class="stat-value"><?= number_format($total_staff) ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
            <div>
                <p class="stat-label">Total Patients</p>
                <p class="stat-value"><?= number_format($total_patients) ?></p>
            </div>
        </div>
    </div>

    <!-- Branches Grid -->
    <?php if (count($branches) > 0): ?>
        <div class="branches-grid animate-fade-in-up" style="animation-delay:0.1s;">
            <?php foreach ($branches as $branch): 
                $branch_id = $branch['id'] ?? 0;
                $branch_name = htmlspecialchars($branch['name'] ?? 'Unknown');
                $branch_status = $branch['status'] ?? 'active';
                $branch_location = htmlspecialchars($branch['location'] ?? 'Not set');
                $branch_phone = htmlspecialchars($branch['phone'] ?? 'Not set');
                $branch_email = htmlspecialchars($branch['email'] ?? 'Not set');

                $admin_count = $branch['admin_count'] ?? 0;
                $doctor_count = $branch['doctor_count'] ?? 0;
                $reception_count = $branch['reception_count'] ?? 0;
                $pharmacy_count = $branch['pharmacy_count'] ?? 0;
                $cashier_count = $branch['cashier_count'] ?? 0;
                $lab_count = $branch['lab_count'] ?? 0;
                $total_staff_branch = $branch['total_staff'] ?? 0;

                $patient_count = $branch['patient_count'] ?? 0;
                $active_visits = $branch['active_visits'] ?? 0;
                $completed_visits = $branch['completed_visits'] ?? 0;
            ?>
                <div class="branch-card <?= $branch_status === 'active' ? 'active' : 'inactive' ?>">
                    <div class="branch-card-header">
                        <div class="branch-info">
                            <div class="branch-icon">
                                <i class="fas fa-hospital"></i>
                            </div>
                            <div>
                                <h3 class="branch-name"><?= $branch_name ?></h3>
                                <span class="branch-code">ID: #<?= $branch_id ?></span>
                            </div>
                        </div>
                        <div class="branch-status">
                            <span class="branch-status-badge <?= $branch_status === 'active' ? 'active' : 'inactive' ?>">
                                <i class="fas fa-<?= $branch_status === 'active' ? 'circle' : 'times-circle' ?>"></i>
                                <?= ucfirst($branch_status) ?>
                            </span>
                        </div>
                    </div>

                    <div class="branch-card-body">
                        <div class="branch-details">
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?= $branch_location ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-phone"></i>
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><?= $branch_phone ?></span>
                            </div>
                            <div class="detail-item" style="grid-column: span 2;">
                                <i class="fas fa-envelope"></i>
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?= $branch_email ?></span>
                            </div>
                        </div>

                        <div class="branch-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?= $total_staff_branch ?></span>
                                <span class="stat-label">Staff</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= $patient_count ?></span>
                                <span class="stat-label">Patients</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= $active_visits ?></span>
                                <span class="stat-label">Active Visits</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= $completed_visits ?></span>
                                <span class="stat-label">Completed</span>
                            </div>
                        </div>
                    </div>

                    <div class="branch-card-footer">
                        <div class="staff-breakdown">
                            <?php if ($admin_count > 0): ?>
                                <span class="staff-tag"><i class="fas fa-user-tie"></i> <?= $admin_count ?> Admins</span>
                            <?php endif; ?>
                            <?php if ($doctor_count > 0): ?>
                                <span class="staff-tag"><i class="fas fa-user-md"></i> <?= $doctor_count ?> Doctors</span>
                            <?php endif; ?>
                            <?php if ($reception_count > 0): ?>
                                <span class="staff-tag"><i class="fas fa-user-friends"></i> <?= $reception_count ?> Reception</span>
                            <?php endif; ?>
                            <?php if ($pharmacy_count > 0): ?>
                                <span class="staff-tag"><i class="fas fa-prescription-bottle"></i> <?= $pharmacy_count ?> Pharmacy</span>
                            <?php endif; ?>
                            <?php if ($lab_count > 0): ?>
                                <span class="staff-tag"><i class="fas fa-flask"></i> <?= $lab_count ?> Lab</span>
                            <?php endif; ?>
                            <?php if ($total_staff_branch == 0): ?>
                                <span class="staff-tag" style="color:var(--page-text-secondary);">
                                    <i class="fas fa-user-slash"></i> No staff assigned
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="branch-actions">
                            <a href="view_branch.php?id=<?= $branch_id ?>&branch=<?= $branch_id ?>" 
                               class="btn btn-sm btn-outline-primary" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit_branch.php?id=<?= $branch_id ?>&branch=<?= $branch_id ?>" 
                               class="btn btn-sm btn-outline-primary" title="Edit Branch">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="branch_staff.php?id=<?= $branch_id ?>&branch=<?= $branch_id ?>" 
                               class="btn btn-sm btn-outline-primary" title="Manage Staff">
                                <i class="fas fa-users-cog"></i>
                            </a>
                            <?php if ($branch_status === 'active'): ?>
                                <button onclick="toggleBranch(<?= $branch_id ?>, 'inactive')" 
                                        class="btn btn-sm btn-outline-danger" title="Deactivate">
                                    <i class="fas fa-pause"></i>
                                </button>
                            <?php else: ?>
                                <button onclick="toggleBranch(<?= $branch_id ?>, 'active')" 
                                        class="btn btn-sm btn-outline-success" title="Activate">
                                    <i class="fas fa-play"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state animate-fade-in-up">
            <i class="fas fa-store-alt-slash"></i>
            <h3>No Branches Found</h3>
            <p>No branches match your search criteria. Try adjusting your search or add a new branch.</p>
            <a href="add_branch.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Branch
            </a>
        </div>
    <?php endif; ?>

</main>

<!-- Hidden toggle form -->
<form id="toggleStatusForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="id" id="toggleStatusId" value="0">
    <input type="hidden" name="status" id="toggleStatusValue" value="">
</form>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');

        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F0F4F8';
            if (mainContent) mainContent.style.background = '#F0F4F8';
        }
    }

    enforceDarkModeBackground();
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });

    // ================================================================
    // TOGGLE BRANCH STATUS
    // ================================================================
    function toggleBranch(id, status) {
        var action = status === 'active' ? 'activate' : 'deactivate';
        if (confirm('Are you sure you want to ' + action + ' this branch?')) {
            var form = document.getElementById('toggleStatusForm');
            var currentBranch = '<?= htmlspecialchars($selected_branch_id) ?>';
            form.action = 'branches.php?branch=' + encodeURIComponent(currentBranch);
            document.getElementById('toggleStatusId').value = id;
            document.getElementById('toggleStatusValue').value = status;
            form.submit();
        }
    }

    console.log('%c🏢 Braick - Branches Management', 'font-size:18px; font-weight:bold; color:#1A56DB;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🎨 Blue page header + blue stat cards + blue branch cards', 'font-size:13px; color:#1A56DB;');
    console.log('%c🌙 FULL DARK MODE works', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>