<?php
// ================================================================
// FILE: frontend/pages/admin/visits.php
// SUPER ADMIN - VISITS MANAGEMENT
// ✅ Delete button kwa KILA visit (hata Complete)
// ✅ Delete inafuta visit + data zote zinazohusiana
// ✅ Confirm modal ya Delete
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
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
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

// ================================================================
// BRANCH FILTER
// ================================================================
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
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$message = '';
$message_type = '';

// ================================================================
// AJAX HANDLERS (Reassign / Complete / Cancel / Delete)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $visit_id = (int)($_POST['visit_id'] ?? 0);
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        if ($visit_id <= 0) throw new Exception('Invalid visit ID');
        
        if ($action === 'reassign_doctor') {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT id, visit_number, doctor_id FROM visits WHERE id = ? LIMIT 1");
            $stmt->execute([$visit_id]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$visit) throw new Exception('Visit not found');
            
            $stmt = $db->prepare("UPDATE visits SET doctor_id = NULL, status = 'pending', assigned_at = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$visit_id]);
            
            if ($patient_id > 0) {
                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                $stmt->execute([$patient_id]);
            }
            
            try {
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'doctor_reassigned', ?, NOW())");
                $stmt->execute([$user_id, "Doctor removed from Visit #{$visit['visit_number']}"]);
            } catch (Exception $e) {}
            
            $db->commit();
            $response['success'] = true;
            $response['message'] = 'Doctor removed. Visit returned to Pending.';
            
        } elseif ($action === 'complete_visit') {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT id, visit_number FROM visits WHERE id = ? LIMIT 1");
            $stmt->execute([$visit_id]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$visit) throw new Exception('Visit not found');
            
            $stmt = $db->prepare("UPDATE visits SET status = 'completed', is_completed = 1, completed_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$visit_id]);
            
            try {
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'visit_completed', ?, NOW())");
                $stmt->execute([$user_id, "Visit #{$visit['visit_number']} completed"]);
            } catch (Exception $e) {}
            
            $db->commit();
            $response['success'] = true;
            $response['message'] = 'Visit marked as completed.';
            
        } elseif ($action === 'cancel_visit') {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT id, visit_number FROM visits WHERE id = ? LIMIT 1");
            $stmt->execute([$visit_id]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$visit) throw new Exception('Visit not found');
            
            $stmt = $db->prepare("UPDATE visits SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$visit_id]);
            
            if ($patient_id > 0) {
                $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                $stmt->execute([$patient_id]);
            }
            
            try {
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'visit_cancelled', ?, NOW())");
                $stmt->execute([$user_id, "Visit #{$visit['visit_number']} cancelled"]);
            } catch (Exception $e) {}
            
            $db->commit();
            $response['success'] = true;
            $response['message'] = 'Visit cancelled.';
            
        } elseif ($action === 'delete_visit') {
            // ================================================================
            // ✅ DELETE VISIT - Inafuta visit + data zote zinazohusiana
            // ================================================================
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT id, visit_number, patient_id FROM visits WHERE id = ? LIMIT 1");
            $stmt->execute([$visit_id]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$visit) throw new Exception('Visit not found');
            
            $visit_number = $visit['visit_number'];
            $patient_id_visit = (int)$visit['patient_id'];
            
            // ✅ Log activity KABLA ya kufuta
            try {
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'visit_deleted', ?, NOW())");
                $stmt->execute([$user_id, "Visit #{$visit_number} deleted PERMANENTLY (ID: {$visit_id})"]);
            } catch (Exception $e) {}
            
            // ✅ Futa payments za bills za visit hii
            try {
                $stmt = $db->prepare("
                    DELETE p FROM payments p
                    INNER JOIN bills b ON p.bill_id = b.id
                    WHERE b.visit_id = ?
                ");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
            
            // ✅ Futa bill_items za bills za visit hii
            try {
                $stmt = $db->prepare("
                    DELETE bi FROM bill_items bi
                    INNER JOIN bills b ON bi.bill_id = b.id
                    WHERE b.visit_id = ?
                ");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
            
            // ✅ Futa bills za visit hii
            try {
                $stmt = $db->prepare("DELETE FROM bills WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
            
            // ✅ Futa prescription_items za prescriptions za visit hii
            try {
                $stmt = $db->prepare("
                    DELETE pi FROM prescription_items pi
                    INNER JOIN prescriptions pr ON pi.prescription_id = pr.id
                    WHERE pr.visit_id = ?
                ");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
            
            // ✅ Futa prescriptions za visit hii
            try {
                $stmt = $db->prepare("DELETE FROM prescriptions WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
            
            // ✅ Futa lab_tests za visit hii
            try {
                $stmt = $db->prepare("DELETE FROM lab_tests WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
            
            // ✅ Futa vital_signs za visit hii
            try {
                $stmt = $db->prepare("DELETE FROM vital_signs WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
            
            // ✅ Futa procedures za visit hii
            try {
                $stmt = $db->prepare("DELETE FROM procedures WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
            
            // ✅ Futa medical_records za visit hii (kama zipo)
            try {
                $stmt = $db->prepare("DELETE FROM medical_records WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
            
            // ✅ Futa queue/notifications za visit (kama zipo)
            try {
                $stmt = $db->prepare("DELETE FROM notifications WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
            } catch (Exception $e) {}
            
            // ✅ Futa visit yenyewe
            $stmt = $db->prepare("DELETE FROM visits WHERE id = ?");
            $stmt->execute([$visit_id]);
            
            // ✅ Kama patient hana visits nyingine, ondoa assigned_doctor_id
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE patient_id = ?");
                $stmt->execute([$patient_id_visit]);
                $remaining = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
                
                if ($remaining === 0) {
                    $stmt = $db->prepare("UPDATE patients SET assigned_doctor_id = NULL WHERE id = ?");
                    $stmt->execute([$patient_id_visit]);
                }
            } catch (Exception $e) {}
            
            $db->commit();
            $response['success'] = true;
            $response['message'] = "Visit #{$visit_number} deleted permanently.";
            
        } else {
            throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// ================================================================
// GET VISITS
// ================================================================
try {
    $sql = "
        SELECT v.*, 
               p.full_name as patient_name, 
               p.patient_id as patient_number,
               p.phone as patient_phone,
               u.full_name as doctor_name,
               u.is_online as doctor_online,
               b.name as branch_name,
               s.service_name,
               (SELECT COUNT(*) FROM lab_tests lt 
                WHERE lt.visit_id = v.id 
                AND lt.status NOT IN ('completed', 'cancelled')) as pending_labs,
               (SELECT COUNT(*) FROM prescriptions pr 
                WHERE pr.visit_id = v.id 
                AND pr.status IN ('pending', 'prescribed')) as pending_prescriptions,
               (SELECT COUNT(*) FROM bills bl 
                WHERE bl.visit_id = v.id) as bill_count
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        LEFT JOIN services s ON v.service_id = s.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($filter_by_branch) {
        $sql .= " AND v.branch_id = ?";
        $params[] = $filter_branch_id;
    }
    
    if (!empty($search)) {
        $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR v.visit_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY v.created_at DESC LIMIT 1000";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $visits_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $visits = [];
    $status_counts = [
        'all' => 0, 'assigned' => 0, 'lab_test' => 0, 'prescribe' => 0,
        'waiting' => 0, 'complete' => 0, 'pending' => 0, 'cancelled' => 0
    ];
    
    foreach ($visits_raw as $v) {
        $status = $v['status'] ?? '';
        $category = 'pending';
        
        if ($status === 'completed') $category = 'complete';
        elseif ($status === 'cancelled') $category = 'cancelled';
        elseif ($status === 'lab_test' && (int)$v['pending_labs'] > 0) $category = 'lab_test';
        elseif ($status === 'prescribed') $category = 'prescribe';
        elseif ($status === 'waiting') $category = 'waiting';
        elseif (in_array($status, ['assigned', 'with_doctor'])) $category = 'assigned';
        elseif (in_array($status, ['new', 'pending'])) $category = 'pending';
        
        $v['category'] = $category;
        $visits[] = $v;
        
        $status_counts['all']++;
        if (isset($status_counts[$category])) {
            $status_counts[$category]++;
        }
    }
    
    $total_visits = count($visits);
    
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $month_start = date('Y-m-01');
    
    $stats_sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today,
            SUM(CASE WHEN DATE(created_at) >= ? THEN 1 ELSE 0 END) as this_week,
            SUM(CASE WHEN DATE(created_at) >= ? THEN 1 ELSE 0 END) as this_month
        FROM visits
        WHERE 1=1
    ";
    $stats_params = [$today, $week_start, $month_start];
    
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
    $visits = [];
    $stats = ['total' => 0, 'today' => 0, 'this_week' => 0, 'this_month' => 0];
    $total_visits = 0;
    $status_counts = [
        'all' => 0, 'assigned' => 0, 'lab_test' => 0,
        'prescribe' => 0, 'waiting' => 0, 'complete' => 0,
        'pending' => 0, 'cancelled' => 0
    ];
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

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

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
    --cyan: #0891B2;
    --cyan-bg: #E0F2FE;
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
    color: white;
}

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

.status-toggle-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    background: var(--bg-card);
    padding: 12px 16px;
    border-radius: var(--radius);
    border: 2px solid var(--border-color);
    margin-bottom: 16px;
    box-shadow: var(--shadow-sm);
}

.status-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 2px solid var(--border-color);
    background: var(--bg-body);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.status-toggle-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-1px);
}

.status-toggle-btn.active {
    background: var(--primary-gradient);
    color: white;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
}

.status-toggle-btn.active[data-status="lab_test"] {
    background: linear-gradient(135deg, #7C3AED, #5B21B6);
    border-color: #7C3AED;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}

.status-toggle-btn.active[data-status="prescribe"] {
    background: linear-gradient(135deg, #059669, #047857);
    border-color: #059669;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

.status-toggle-btn.active[data-status="waiting"] {
    background: linear-gradient(135deg, #D97706, #B45309);
    border-color: #D97706;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
}

.status-toggle-btn.active[data-status="complete"] {
    background: linear-gradient(135deg, #0891B2, #0E7490);
    border-color: #0891B2;
    box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3);
}

.status-toggle-btn.active[data-status="cancelled"] {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    border-color: #DC2626;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.toggle-count {
    background: rgba(255,255,255,0.25);
    padding: 1px 10px;
    border-radius: 10px;
    font-size: 0.65rem;
    font-weight: 700;
}

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
    min-width: 220px;
    width: 220px;
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

.visit-table {
    width: 100%;
    min-width: 2000px;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.visit-table thead {
    background: var(--primary-gradient);
    color: white;
    position: sticky;
    top: 0;
    z-index: 5;
}

.visit-table thead th {
    padding: 14px 16px;
    text-align: left;
    font-weight: 700;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: white;
    white-space: nowrap;
}

.visit-table .col-sno {
    width: 55px;
    text-align: center;
    font-weight: 700;
    color: var(--primary);
    font-size: 0.9rem;
}

.visit-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.85rem;
    color: var(--text-primary);
}

.visit-table tbody tr:nth-child(even) {
    background: var(--primary-bg);
}

.visit-table tbody tr:hover td {
    background: #D1FAE5;
}

[data-theme="dark"] .visit-table tbody tr:hover td {
    background: #1A3A2A;
}

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

.visit-number-text {
    color: var(--text-black) !important;
    font-family: 'Courier New', monospace !important;
    font-size: 0.82rem !important;
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

.status-badge.with_doctor { background: var(--success-bg); color: var(--success); }
.status-badge.without_doctor { background: var(--warning-bg); color: var(--warning); }

.category-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 5px 14px;
    border-radius: 20px;
    white-space: nowrap;
}

.category-badge.assigned { background: #D1FAE5; color: #059669; border: 1px solid #059669; }
.category-badge.lab_test { background: #EDE9FE; color: #7C3AED; border: 1px solid #7C3AED; }
.category-badge.prescribe { background: #D1FAE5; color: #047857; border: 1px solid #047857; }
.category-badge.waiting { background: #FEF3C7; color: #D97706; border: 1px solid #D97706; }
.category-badge.complete { background: #E0F2FE; color: #0891B2; border: 1px solid #0891B2; }
.category-badge.pending { background: #FEF3C7; color: #D97706; border: 1px solid #D97706; }
.category-badge.cancelled { background: #FEE2E2; color: #DC2626; border: 1px solid #DC2626; }

[data-theme="dark"] .category-badge.assigned { background: #064E3B; color: #6EE7B7; }
[data-theme="dark"] .category-badge.lab_test { background: #2D1B5F; color: #C4B5FD; }
[data-theme="dark"] .category-badge.prescribe { background: #064E3B; color: #6EE7B7; }
[data-theme="dark"] .category-badge.waiting { background: #3D2E0A; color: #FCD34D; }
[data-theme="dark"] .category-badge.complete { background: #083344; color: #67E8F9; }
[data-theme="dark"] .category-badge.cancelled { background: #450A0A; color: #FCA5A5; }

.days-badge-blue {
    display: inline-block;
    background: var(--primary) !important;
    color: #ffffff !important;
    padding: 4px 12px !important;
    border-radius: 12px !important;
    font-size: 0.68rem !important;
    font-weight: 600 !important;
}

.visit-type-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    background: var(--primary-bg);
    color: var(--primary);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 12px;
    border-radius: 7px;
    font-weight: 600;
    font-size: 0.72rem;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    text-decoration: none;
    white-space: nowrap;
}

.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); color: white; }

.btn-success { background: var(--success); color: white; }
.btn-success:hover { background: var(--success-dark); transform: translateY(-1px); color: white; }

.btn-danger { background: var(--danger); color: white; }
.btn-danger:hover { background: var(--danger-dark); transform: translateY(-1px); color: white; }

.btn-warning { background: var(--warning); color: white; }
.btn-warning:hover { background: #B45309; transform: translateY(-1px); color: white; }

.btn-purple { background: var(--purple); color: white; }
.btn-purple:hover { background: #5B21B6; transform: translateY(-1px); color: white; }

.btn-cyan { background: var(--cyan); color: white; }
.btn-cyan:hover { background: #0E7490; transform: translateY(-1px); color: white; }

/* ✅ DELETE BUTTON STYLE */
.btn-delete {
    background: linear-gradient(135deg, #991B1B, #7F1D1D);
    color: white;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(153, 27, 27, 0.3);
}
.btn-delete:hover {
    background: linear-gradient(135deg, #DC2626, #991B1B);
    transform: translateY(-1px);
    color: white;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.5);
}

.action-buttons-group {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    align-items: center;
}

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
.toast-custom.warning { background: var(--warning); }

.confirm-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 10000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.confirm-modal-overlay.show { display: flex; }

.confirm-modal-content {
    background: var(--bg-card);
    border-radius: 16px;
    max-width: 450px;
    width: 100%;
    padding: 24px;
    border: 2px solid var(--border-color);
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in-up {
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
}

@media (max-width: 768px) {
    .stats-grid-mini { grid-template-columns: repeat(2, 1fr); }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-bar-left, .filter-bar-right { width: 100%; justify-content: flex-start; }
    .search-input-wrapper input { width: 100%; min-width: unset; }
    .page-header-custom { padding: 16px 18px; }
    .page-header-custom .page-title { font-size: 1.2rem; }
    .status-toggle-btn { padding: 6px 12px; font-size: 0.7rem; }
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
                <i class="fas fa-notes-medical"></i>
                Visits Management
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-hospital"></i>
                Manage visits in <strong><?= htmlspecialchars($display_branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-list"></i>
                    <?= $stats['total'] ?? 0 ?> Total
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);">
                    <i class="fas fa-calendar-day"></i>
                    <?= $stats['today'] ?? 0 ?> Today
                </span>
                <span class="header-badge" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.3);">
                    <i class="fas fa-calendar-week"></i>
                    <?= $stats['this_week'] ?? 0 ?> This Week
                </span>
                <span class="header-badge" style="background:rgba(217,119,6,0.2);border-color:rgba(217,119,6,0.3);">
                    <i class="fas fa-calendar-alt"></i>
                    <?= $stats['this_month'] ?? 0 ?> This Month
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="assign_doctor.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-user-md"></i> Assign Doctor
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
            <p class="stat-label">Total Visits</p>
        </div>
        <div class="stat-card-mini">
            <p class="stat-number green"><?= $stats['today'] ?? 0 ?></p>
            <p class="stat-label">Today</p>
        </div>
        <div class="stat-card-mini">
            <p class="stat-number purple"><?= $stats['this_week'] ?? 0 ?></p>
            <p class="stat-label">This Week</p>
        </div>
        <div class="stat-card-mini">
            <p class="stat-number orange"><?= $stats['this_month'] ?? 0 ?></p>
            <p class="stat-label">This Month</p>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="table-card animate-fade-in-up">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-list"></i> Visit List
                <span id="visitCountBadge" style="background:var(--primary-bg);color:var(--primary);padding:2px 12px;border-radius:20px;font-size:0.7rem;font-weight:500;">
                    <?= $total_visits ?> visits
                </span>
            </div>
        </div>
        
        <!-- STATUS TOGGLE BUTTONS -->
        <div class="status-toggle-group" id="statusToggleGroup">
            <span style="font-size:0.75rem;font-weight:600;color:var(--text-secondary);margin-right:4px;">
                <i class="fas fa-filter"></i> Status:
            </span>
            
            <button type="button" class="status-toggle-btn active" data-status="all" onclick="filterByStatus('all')">
                <i class="fas fa-list"></i> All
                <span class="toggle-count" id="countAll"><?= $status_counts['all'] ?></span>
            </button>
            
            <button type="button" class="status-toggle-btn" data-status="assigned" onclick="filterByStatus('assigned')">
                <i class="fas fa-user-check"></i> Assigned
                <span class="toggle-count" id="countAssigned"><?= $status_counts['assigned'] ?></span>
            </button>
            
            <button type="button" class="status-toggle-btn" data-status="lab_test" onclick="filterByStatus('lab_test')">
                <i class="fas fa-flask"></i> Lab Test
                <span class="toggle-count" id="countLabTest"><?= $status_counts['lab_test'] ?></span>
            </button>
            
            <button type="button" class="status-toggle-btn" data-status="prescribe" onclick="filterByStatus('prescribe')">
                <i class="fas fa-prescription"></i> Prescribe
                <span class="toggle-count" id="countPrescribe"><?= $status_counts['prescribe'] ?></span>
            </button>
            
            <button type="button" class="status-toggle-btn" data-status="waiting" onclick="filterByStatus('waiting')">
                <i class="fas fa-clock"></i> Waiting
                <span class="toggle-count" id="countWaiting"><?= $status_counts['waiting'] ?></span>
            </button>
            
            <button type="button" class="status-toggle-btn" data-status="complete" onclick="filterByStatus('complete')">
                <i class="fas fa-check-circle"></i> Complete
                <span class="toggle-count" id="countComplete"><?= $status_counts['complete'] ?></span>
            </button>
            
            <button type="button" class="status-toggle-btn" data-status="pending" onclick="filterByStatus('pending')">
                <i class="fas fa-hourglass-half"></i> Pending
                <span class="toggle-count" id="countPending"><?= $status_counts['pending'] ?></span>
            </button>
            
            <button type="button" class="status-toggle-btn" data-status="cancelled" onclick="filterByStatus('cancelled')">
                <i class="fas fa-times-circle"></i> Cancelled
                <span class="toggle-count" id="countCancelled"><?= $status_counts['cancelled'] ?></span>
            </button>
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
                           placeholder="Search patient, visit #, ID..." 
                           value="<?= htmlspecialchars($search) ?>"
                           oninput="autoFilter()">
                </div>
                
                <a href="visits.php?branch=<?= urlencode($selected_branch_id) ?>" class="clear-filter-btn <?= !empty($search) ? 'show' : '' ?>" id="clearFilterBtn">
                    <i class="fas fa-times"></i> Clear
                </a>
                
                <span class="filter-status" id="filterStatus">
                    <i class="fas fa-bolt"></i>
                    <span id="filterStatusText">Auto-filter</span>
                </span>
            </div>
            
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

        <!-- VISITS TABLE -->
        <div id="tableContainer">
            <?php if (!empty($visits) && count($visits) > 0): ?>
            <div class="table-scroll-wrapper" id="tableScrollWrapper">
                <table class="visit-table" id="visitsTable">
                    <thead>
                        <tr>
                            <th class="col-sno">#</th>
                            <th><i class="fas fa-hashtag"></i> Visit #</th>
                            <th><i class="fas fa-user"></i> Patient</th>
                            <th><i class="fas fa-id-card"></i> Patient ID</th>
                            <th><i class="fas fa-phone"></i> Contact</th>
                            <th><i class="fas fa-user-md"></i> Doctor</th>
                            <th><i class="fas fa-tag"></i> Visit Type</th>
                            <th><i class="fas fa-store-alt"></i> Branch</th>
                            <th><i class="fas fa-calendar"></i> Date</th>
                            <th><i class="fas fa-info-circle"></i> Status</th>
                            <th style="min-width:420px;"><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="visitTableBody">
                        <?php $counter = 1; ?>
                        <?php foreach ($visits as $visit): 
                            $category = $visit['category'] ?? 'pending';
                            $visit_date = $visit['created_at'] ?? date('Y-m-d H:i:s');
                            $days_ago = (int)floor((time() - strtotime($visit_date)) / 86400);
                            $date_display = date('d M Y', strtotime($visit_date));
                            $time_display = date('H:i', strtotime($visit_date));
                            
                            $days_text = $days_ago > 0 
                                ? '<span class="days-badge-blue">📅 ' . $days_ago . 'd</span>' 
                                : '<span class="days-badge-blue">Today</span>';
                            
                            if (!empty($visit['doctor_name'])) {
                                $online_icon = ($visit['doctor_online'] ?? 0) == 1 ? '🟢' : '⚪';
                                $doctor_display = '<span class="status-badge with_doctor">' . $online_icon . ' Dr. ' . htmlspecialchars($visit['doctor_name']) . '</span>';
                            } else {
                                $doctor_display = '<span class="status-badge without_doctor">⚠️ No Doctor</span>';
                            }
                            
                            $category_label = '';
                            $category_icon = '';
                            switch ($category) {
                                case 'assigned': $category_label = 'Assigned'; $category_icon = 'fa-user-check'; break;
                                case 'lab_test': $category_label = 'Lab Test'; $category_icon = 'fa-flask'; break;
                                case 'prescribe': $category_label = 'Prescribed'; $category_icon = 'fa-prescription'; break;
                                case 'waiting': $category_label = 'Waiting'; $category_icon = 'fa-clock'; break;
                                case 'complete': $category_label = 'Complete'; $category_icon = 'fa-check-circle'; break;
                                case 'cancelled': $category_label = 'Cancelled'; $category_icon = 'fa-times-circle'; break;
                                default: $category_label = 'Pending'; $category_icon = 'fa-hourglass-half';
                            }
                            
                            $visit_id = (int)$visit['id'];
                            $patient_id = (int)$visit['patient_id'];
                            $visit_number_js = htmlspecialchars(addslashes($visit['visit_number']));
                            $patient_name_js = htmlspecialchars(addslashes($visit['patient_name'] ?? 'Unknown'));
                        ?>
                            <tr class="visit-row" 
                                data-visit="<?= strtolower(htmlspecialchars($visit['visit_number'] ?? '')) ?>"
                                data-patient="<?= strtolower(htmlspecialchars($visit['patient_name'] ?? '')) ?>"
                                data-patient-id="<?= strtolower(htmlspecialchars($visit['patient_number'] ?? '')) ?>"
                                data-category="<?= $category ?>">
                                <td class="col-sno"><?= $counter++ ?></td>
                                <td>
                                    <span class="visit-number-text"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <a href="patient_details.php?id=<?= $patient_id ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                       class="patient-name-link">
                                        <?= htmlspecialchars($visit['patient_name'] ?? 'Unknown') ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="visit-number-text"><?= htmlspecialchars($visit['patient_number'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($visit['patient_phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($visit['patient_phone']) ?>" class="patient-contact-link">
                                            <?= htmlspecialchars($visit['patient_phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $doctor_display ?></td>
                                <td>
                                    <span class="visit-type-badge">
                                        <?= htmlspecialchars($visit['visit_type'] ?? $visit['service_name'] ?? 'Consultation') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="branch-badge-display">
                                        🏥 <?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight:600;color:var(--text-black);font-size:0.8rem;"><?= $date_display ?></div>
                                    <div style="font-size:0.7rem;color:var(--text-muted);"><?= $time_display ?> • <?= $days_text ?></div>
                                </td>
                                <td>
                                    <span class="category-badge <?= $category ?>">
                                        <i class="fas <?= $category_icon ?>"></i>
                                        <?= $category_label ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons-group">
                                        <!-- VIEW - Always visible -->
                                        <a href="visit_details.php?id=<?= $visit_id ?>&branch=<?= urlencode($selected_branch_id) ?>" 
                                           class="btn btn-primary" title="View Visit">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if ($category === 'assigned'): ?>
                                            <button type="button" 
                                                    class="btn btn-danger" 
                                                    onclick="reassignDoctor(<?= $visit_id ?>, <?= $patient_id ?>, '<?= $visit_number_js ?>')" 
                                                    title="Reassign (Remove Doctor)">
                                                <i class="fas fa-user-minus"></i> Reassign
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-warning" 
                                                    onclick="changeDoctor(<?= $patient_id ?>)"
                                                    title="Change Doctor">
                                                <i class="fas fa-sync-alt"></i> Change
                                            </button>
                                            
                                        <?php elseif (in_array($category, ['lab_test', 'prescribe', 'waiting'])): ?>
                                            <button type="button" 
                                                    class="btn btn-success" 
                                                    onclick="completeVisit(<?= $visit_id ?>, '<?= $visit_number_js ?>')"
                                                    title="Complete Visit">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-danger" 
                                                    onclick="cancelVisit(<?= $visit_id ?>, <?= $patient_id ?>, '<?= $visit_number_js ?>')"
                                                    title="Cancel Visit">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                            
                                        <?php elseif ($category === 'complete'): ?>
                                            <span style="font-size:0.7rem;color:var(--success);font-weight:600;display:inline-flex;align-items:center;gap:3px;padding:7px 10px;background:var(--success-bg);border-radius:7px;">
                                                <i class="fas fa-check-circle"></i> Completed
                                            </span>
                                            
                                        <?php elseif ($category === 'cancelled'): ?>
                                            <span style="font-size:0.7rem;color:var(--danger);font-weight:600;display:inline-flex;align-items:center;gap:3px;padding:7px 10px;background:var(--danger-bg);border-radius:7px;">
                                                <i class="fas fa-times-circle"></i> Cancelled
                                            </span>
                                            
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-purple" 
                                                    onclick="changeDoctor(<?= $patient_id ?>)"
                                                    title="Assign Doctor">
                                                <i class="fas fa-user-md"></i> Assign
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- ✅ DELETE BUTTON - KILA VISIT -->
                                        <button type="button" 
                                                class="btn btn-delete" 
                                                onclick="deleteVisit(<?= $visit_id ?>, '<?= $visit_number_js ?>', '<?= $patient_name_js ?>')"
                                                title="Delete Visit PERMANENTLY">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="noResultsMessage" style="display:none;text-align:center;padding:40px 20px;">
                <i class="fas fa-search" style="font-size:2rem;color:var(--border-color);display:block;margin-bottom:12px;"></i>
                <p style="color:var(--text-secondary);font-size:0.9rem;">No visits match your filter</p>
                <p style="color:var(--text-muted);font-size:0.75rem;">Try adjusting your filter criteria</p>
                <button onclick="clearAllFilters()" class="btn btn-primary" style="margin-top:12px;">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
            </div>
            
            <?php else: ?>
            <div style="text-align:center;padding:40px 20px;">
                <i class="fas fa-notes-medical" style="font-size:2rem;color:var(--border-color);display:block;margin-bottom:8px;"></i>
                <p style="color:var(--text-secondary);">No visits found</p>
                <a href="visits.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-primary" style="margin-top:12px;">Clear Filters</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Visits Management (Admin)
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

<!-- CONFIRM MODAL -->
<div id="confirmModal" class="confirm-modal-overlay">
    <div class="confirm-modal-content">
        <div style="text-align:center;margin-bottom:20px;">
            <div id="confirmIcon" style="width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:2rem;background:var(--success-bg);color:var(--success);">
                <i class="fas fa-check"></i>
            </div>
            <h3 id="confirmTitle" style="font-size:1.2rem;font-weight:700;margin-bottom:6px;color:var(--text-primary);">
                Confirm Action
            </h3>
            <p style="font-size:0.85rem;color:var(--text-secondary);" id="confirmMessage">
                Are you sure?
            </p>
        </div>
        
        <div id="confirmWarning" style="display:none;background:var(--danger-bg);border:2px solid var(--danger);border-radius:12px;padding:12px 16px;margin-bottom:12px;">
            <p style="font-size:0.75rem;color:var(--danger);margin:0;font-weight:700;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-exclamation-triangle"></i>
                <span>This action CANNOT be undone!</span>
            </p>
        </div>
        
        <div style="background:var(--primary-bg);border:2px solid var(--primary);border-radius:12px;padding:12px 16px;margin-bottom:16px;">
            <p style="font-size:0.85rem;color:var(--primary);margin:0;font-weight:600;" id="confirmVisitInfo">-</p>
        </div>
        
        <div style="display:flex;gap:10px;">
            <button type="button" onclick="closeConfirmModal()" style="flex:1;padding:12px 20px;border-radius:10px;border:2px solid var(--border-color);background:transparent;color:var(--text-secondary);font-weight:600;font-size:0.85rem;cursor:pointer;">
                <i class="fas fa-times"></i> No
            </button>
            <button type="button" id="confirmActionBtn" style="flex:1;padding:12px 20px;border-radius:10px;border:none;background:var(--success);color:white;font-weight:700;font-size:0.85rem;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;">
                <i class="fas fa-check"></i> YES
            </button>
        </div>
    </div>
</div>

<script>
var filterTimeout = null;
var currentStatus = 'all';

// ============================================================
// STATUS TOGGLE FILTER
// ============================================================
function filterByStatus(status) {
    currentStatus = status;
    
    document.querySelectorAll('.status-toggle-btn').forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.dataset.status === status) {
            btn.classList.add('active');
        }
    });
    
    autoFilter();
}

// ============================================================
// AUTO FILTER
// ============================================================
function autoFilter() {
    var searchValue = document.getElementById('searchFilter').value;
    
    var loading = document.getElementById('filterLoading');
    if (loading) loading.classList.add('show');
    
    var clearBtn = document.getElementById('clearFilterBtn');
    if (clearBtn) {
        if (searchValue.trim() !== '' || currentStatus !== 'all') {
            clearBtn.classList.add('show');
        } else {
            clearBtn.classList.remove('show');
        }
    }
    
    if (filterTimeout) clearTimeout(filterTimeout);
    
    filterTimeout = setTimeout(function() {
        filterTableRows(searchValue, currentStatus);
        setTimeout(function() {
            if (loading) loading.classList.remove('show');
        }, 300);
    }, 200);
}

function filterTableRows(searchValue, statusFilter) {
    var rows = document.querySelectorAll('.visit-row');
    var visibleCount = 0;
    var searchLower = searchValue.toLowerCase().trim();
    
    rows.forEach(function(row) {
        var visitNum = row.getAttribute('data-visit') || '';
        var patient = row.getAttribute('data-patient') || '';
        var patientId = row.getAttribute('data-patient-id') || '';
        var category = row.getAttribute('data-category') || '';
        
        var show = true;
        
        if (searchLower !== '') {
            var matchesSearch = visitNum.includes(searchLower) 
                             || patient.includes(searchLower) 
                             || patientId.includes(searchLower);
            if (!matchesSearch) show = false;
        }
        
        if (statusFilter !== 'all') {
            if (category !== statusFilter) show = false;
        }
        
        if (show) { row.style.display = ''; visibleCount++; }
        else { row.style.display = 'none'; }
    });
    
    updateRowNumbers();
    
    var countBadge = document.getElementById('visitCountBadge');
    if (countBadge) countBadge.textContent = visibleCount + ' visit' + (visibleCount !== 1 ? 's' : '');
    
    var statusText = document.getElementById('filterStatusText');
    if (statusText) {
        if (searchLower !== '' || statusFilter !== 'all') {
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
    var rows = document.querySelectorAll('.visit-row');
    var counter = 1;
    rows.forEach(function(row) {
        if (row.style.display !== 'none') {
            var snoCell = row.querySelector('.col-sno');
            if (snoCell) snoCell.textContent = counter;
            counter++;
        }
    });
}

function clearAllFilters() {
    document.getElementById('searchFilter').value = '';
    currentStatus = 'all';
    document.querySelectorAll('.status-toggle-btn').forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.dataset.status === 'all') btn.classList.add('active');
    });
    autoFilter();
    showToast('🔄 Cleared', 'All filters cleared', 'info');
}

function scrollTable(direction) {
    var wrapper = document.getElementById('tableScrollWrapper');
    if (!wrapper) return;
    var scrollAmount = 400;
    
    if (direction === 'left') wrapper.scrollLeft -= scrollAmount;
    else wrapper.scrollLeft += scrollAmount;
    
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

// ============================================================
// REASSIGN DOCTOR
// ============================================================
function reassignDoctor(visitId, patientId, visitNumber) {
    if (!confirm('⚠️ Reassign Visit ' + visitNumber + '?\n\nThis will REMOVE the current doctor.\nVisit will return to Pending list.\n\nContinue?')) return;
    
    var formData = new FormData();
    formData.append('action', 'reassign_doctor');
    formData.append('visit_id', visitId);
    formData.append('patient_id', patientId);
    
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('✅ Reassigned', data.message, 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast('❌ Error', data.message || 'Failed', 'error');
            }
        })
        .catch(function(err) {
            showToast('❌ Error', 'Network: ' + err.message, 'error');
        });
}

// ============================================================
// CHANGE DOCTOR
// ============================================================
function changeDoctor(patientId) {
    var branchParam = '<?= urlencode($selected_branch_id) ?>';
    window.location.href = 'assign_doctor.php?patient_id=' + patientId + '&change=1&branch=' + branchParam;
}

// ============================================================
// COMPLETE VISIT
// ============================================================
function completeVisit(visitId, visitNumber) {
    document.getElementById('confirmIcon').innerHTML = '<i class="fas fa-check-circle"></i>';
    document.getElementById('confirmIcon').style.background = 'var(--success-bg)';
    document.getElementById('confirmIcon').style.color = 'var(--success)';
    document.getElementById('confirmTitle').textContent = '✅ Complete Visit?';
    document.getElementById('confirmMessage').textContent = 'This will mark the visit as COMPLETED.';
    document.getElementById('confirmVisitInfo').textContent = '📋 Visit #' + visitNumber;
    document.getElementById('confirmWarning').style.display = 'none';
    
    var btn = document.getElementById('confirmActionBtn');
    btn.style.background = 'var(--success)';
    btn.innerHTML = '<i class="fas fa-check"></i> YES, COMPLETE';
    
    btn.onclick = function() {
        closeConfirmModal();
        executeVisitAction('complete_visit', visitId, 0);
    };
    
    document.getElementById('confirmModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

// ============================================================
// CANCEL VISIT
// ============================================================
function cancelVisit(visitId, patientId, visitNumber) {
    document.getElementById('confirmIcon').innerHTML = '<i class="fas fa-times-circle"></i>';
    document.getElementById('confirmIcon').style.background = 'var(--danger-bg)';
    document.getElementById('confirmIcon').style.color = 'var(--danger)';
    document.getElementById('confirmTitle').textContent = '❌ Cancel Visit?';
    document.getElementById('confirmMessage').textContent = 'This will CANCEL the visit.';
    document.getElementById('confirmVisitInfo').textContent = '📋 Visit #' + visitNumber;
    document.getElementById('confirmWarning').style.display = 'none';
    
    var btn = document.getElementById('confirmActionBtn');
    btn.style.background = 'var(--danger)';
    btn.innerHTML = '<i class="fas fa-times"></i> YES, CANCEL';
    
    btn.onclick = function() {
        closeConfirmModal();
        executeVisitAction('cancel_visit', visitId, patientId);
    };
    
    document.getElementById('confirmModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

// ============================================================
// ✅ DELETE VISIT - CONFIRM MODAL
// ============================================================
function deleteVisit(visitId, visitNumber, patientName) {
    document.getElementById('confirmIcon').innerHTML = '<i class="fas fa-trash-alt"></i>';
    document.getElementById('confirmIcon').style.background = 'var(--danger-bg)';
    document.getElementById('confirmIcon').style.color = 'var(--danger)';
    document.getElementById('confirmTitle').textContent = '🗑️ Delete Visit?';
    document.getElementById('confirmMessage').innerHTML = 
        'This will <strong>PERMANENTLY DELETE</strong> the visit and all related data:<br>' +
        '<span style="font-size:0.75rem;color:var(--text-secondary);display:block;margin-top:6px;">' +
        '• Bills & Payments<br>• Lab Tests<br>• Prescriptions<br>• Vital Signs<br>• Procedures' +
        '</span>';
    document.getElementById('confirmVisitInfo').innerHTML = 
        '📋 Visit #' + visitNumber + '<br>' +
        '<span style="font-size:0.75rem;font-weight:500;">Patient: ' + patientName + '</span>';
    document.getElementById('confirmWarning').style.display = 'block';
    
    var btn = document.getElementById('confirmActionBtn');
    btn.style.background = 'var(--danger)';
    btn.innerHTML = '<i class="fas fa-trash-alt"></i> YES, DELETE';
    
    btn.onclick = function() {
        closeConfirmModal();
        executeVisitAction('delete_visit', visitId, 0);
    };
    
    document.getElementById('confirmModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function executeVisitAction(action, visitId, patientId) {
    var formData = new FormData();
    formData.append('action', action);
    formData.append('visit_id', visitId);
    formData.append('patient_id', patientId);
    
    // Show loading toast
    showToast('⏳ Processing...', 'Please wait', 'info');
    
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var msg = '';
                if (action === 'complete_visit') msg = 'Visit completed!';
                else if (action === 'cancel_visit') msg = 'Visit cancelled!';
                else if (action === 'delete_visit') msg = 'Visit deleted!';
                
                showToast('✅ Success', msg || data.message, 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast('❌ Error', data.message || 'Failed', 'error');
            }
        })
        .catch(function(err) {
            showToast('❌ Error', 'Network: ' + err.message, 'error');
        });
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('confirmModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeConfirmModal();
});

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var wrapper = document.getElementById('tableScrollWrapper');
    if (wrapper) {
        wrapper.addEventListener('scroll', updateScrollButtons);
        setTimeout(updateScrollButtons, 300);
    }
    
    window.addEventListener('resize', function() {
        setTimeout(updateScrollButtons, 200);
    });
    
    var searchValue = document.getElementById('searchFilter').value;
    if (searchValue !== '') {
        filterTableRows(searchValue, 'all');
        document.getElementById('searchFilter').focus();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConfirmModal();
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchFilter').focus();
    }
});

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

console.log('%c👑 Braick - Admin Visits + DELETE', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Delete button kwa KILA visit', 'font-size:12px;color:#DC2626;font-weight:bold;');
console.log('%c✅ Delete inafuta visit + data zote (bills, labs, prescriptions, vitals, procedures)', 'font-size:12px;color:#059669;');
console.log('%c✅ Confirm modal ya Delete na tahadhari', 'font-size:12px;color:#D97706;');
</script>

</body>
</html>