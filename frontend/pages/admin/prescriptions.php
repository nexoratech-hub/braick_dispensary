<?php
// ================================================================
// FILE: frontend/pages/admin/prescriptions.php
// ADMIN - PRESCRIPTIONS GROUPED BY PATIENT + MEDICATION SUMMARY
// ✅ Medication Summary search (inashow jumla ya quantities)
// ✅ < > scroll buttons kwenye kila table
// ✅ Highlight inatoweka unapofuta search
// ✅ BLUE THEME
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

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// DELETE - SINGLE
if (isset($_GET['delete_prescription']) && is_numeric($_GET['delete_prescription'])) {
    $prescription_id = (int)$_GET['delete_prescription'];
    $branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?");
        $stmt->execute([$prescription_id]);
        $stmt = $db->prepare("DELETE FROM prescriptions WHERE id = ?");
        $stmt->execute([$prescription_id]);
        $stmt = $db->prepare("DELETE FROM bill_items WHERE reference_id = ? AND reference_type = 'prescription'");
        $stmt->execute([$prescription_id]);
        $db->commit();
        header('Location: prescriptions.php?branch=' . urlencode($branch_id) . '&deleted=1');
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        header('Location: prescriptions.php?branch=' . urlencode($branch_id) . '&error=delete_failed');
        exit;
    }
}

// DELETE ALL - PATIENT
if (isset($_GET['delete_patient']) && is_numeric($_GET['delete_patient'])) {
    $patient_id = (int)$_GET['delete_patient'];
    $branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT id FROM prescriptions WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $prescription_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($prescription_ids)) {
            $placeholders = implode(',', array_fill(0, count($prescription_ids), '?'));
            $stmt = $db->prepare("DELETE FROM prescription_items WHERE prescription_id IN ($placeholders)");
            $stmt->execute($prescription_ids);
            $stmt = $db->prepare("DELETE FROM bill_items WHERE reference_id IN ($placeholders) AND reference_type = 'prescription'");
            $stmt->execute($prescription_ids);
        }
        $stmt = $db->prepare("DELETE FROM prescriptions WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $db->commit();
        header('Location: prescriptions.php?branch=' . urlencode($branch_id) . '&deleted_all=1');
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        header('Location: prescriptions.php?branch=' . urlencode($branch_id) . '&error=delete_failed');
        exit;
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all') {
    foreach ($branches_list as $b) {
        if ($b['id'] == $selected_branch_id) { $selected_branch_name = $b['name']; break; }
    }
}

// WHERE CLAUSE
$where_clause = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (
        p.prescription_number LIKE ? 
        OR pat.full_name LIKE ? 
        OR pat.patient_id LIKE ? 
        OR p.diagnosis LIKE ?
        OR EXISTS (SELECT 1 FROM prescription_items pi WHERE pi.prescription_id = p.id AND pi.medication_name LIKE ?)
    )";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_clause .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($selected_branch_id !== 'all') {
    $where_clause .= " AND p.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// FETCH
$sql = "
    SELECT 
        p.id, p.prescription_number, p.patient_id, p.visit_id, p.doctor_id,
        p.diagnosis, p.instructions, p.status, p.branch_id, p.created_at, p.dispensed_at,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        doc.full_name as doctor_name,
        b.name as branch_name,
        (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as item_count,
        (SELECT COALESCE(SUM(total_price), 0) FROM prescription_items WHERE prescription_id = p.id) as total_amount
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users doc ON p.doctor_id = doc.id
    LEFT JOIN branches b ON p.branch_id = b.id
    $where_clause
    ORDER BY p.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_prescriptions as &$presc) {
    $stmt = $db->prepare("
        SELECT medication_name, dosage, frequency, quantity, unit_price, total_price, duration
        FROM prescription_items 
        WHERE prescription_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$presc['id']]);
    $presc['medications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($presc);

// GROUP BY PATIENT
$patients = [];
foreach ($all_prescriptions as $presc) {
    $patient_id = $presc['patient_id'] ?? 0;
    if (!isset($patients[$patient_id])) {
        $patients[$patient_id] = [
            'patient_id' => $patient_id,
            'patient_name' => $presc['patient_name'] ?? 'Unknown Patient',
            'patient_number' => $presc['patient_number'] ?? 'N/A',
            'patient_phone' => $presc['patient_phone'] ?? '',
            'patient_gender' => $presc['patient_gender'] ?? '',
            'date_of_birth' => $presc['date_of_birth'] ?? '',
            'prescriptions' => [],
            'total_amount' => 0,
            'latest_date' => null
        ];
    }
    $patients[$patient_id]['prescriptions'][] = $presc;
    $patients[$patient_id]['total_amount'] += $presc['total_amount'] ?? 0;
    if (!$patients[$patient_id]['latest_date'] || $presc['created_at'] > $patients[$patient_id]['latest_date']) {
        $patients[$patient_id]['latest_date'] = $presc['created_at'];
    }
}

$patients_array = array_values($patients);
foreach ($patients_array as &$p) {
    $p['prescription_count'] = count($p['prescriptions']);
}
unset($p);

// ================================================================
// MEDICATION SUMMARY (from PHP)
// ================================================================
$medication_summary = [];
try {
    $med_sql = "
        SELECT 
            pi.medication_name,
            p.status,
            COUNT(DISTINCT p.patient_id) as patient_count,
            SUM(pi.quantity) as total_quantity
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE 1=1
    ";
    $med_params = [];
    if ($selected_branch_id !== 'all') {
        $med_sql .= " AND p.branch_id = ?";
        $med_params[] = (int)$selected_branch_id;
    }
    $med_sql .= " GROUP BY pi.medication_name, p.status";
    
    $stmt = $db->prepare($med_sql);
    $stmt->execute($med_params);
    $med_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($med_rows as $row) {
        $name = $row['medication_name'] ?? 'Unknown';
        $nameLower = strtolower($name);
        
        if (!isset($medication_summary[$nameLower])) {
            $medication_summary[$nameLower] = [
                'name' => $name,
                'total' => 0,
                'patients' => 0,
                'byStatus' => [
                    'pending' => ['qty' => 0, 'patients' => 0],
                    'confirmed' => ['qty' => 0, 'patients' => 0],
                    'dispensed' => ['qty' => 0, 'patients' => 0],
                    'cancelled' => ['qty' => 0, 'patients' => 0],
                ]
            ];
        }
        
        $qty = (int)($row['total_quantity'] ?? 0);
        $patientsCount = (int)($row['patient_count'] ?? 0);
        $status = $row['status'] ?? 'pending';
        
        $medication_summary[$nameLower]['total'] += $qty;
        $medication_summary[$nameLower]['patients'] += $patientsCount;
        
        if (isset($medication_summary[$nameLower]['byStatus'][$status])) {
            $medication_summary[$nameLower]['byStatus'][$status]['qty'] += $qty;
            $medication_summary[$nameLower]['byStatus'][$status]['patients'] += $patientsCount;
        }
    }
} catch (Exception $e) {
    $medication_summary = [];
}

// STATS
$stats_where = " WHERE 1=1";
$stats_params = [];
if ($selected_branch_id !== 'all') {
    $stats_where .= " AND p.branch_id = ?";
    $stats_params[] = (int)$selected_branch_id;
}

$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions p $stats_where");
$stmt->execute($stats_params);
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions p $stats_where AND p.status = 'pending'");
$stmt->execute($stats_params);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions p $stats_where AND p.status = 'confirmed'");
$stmt->execute($stats_params);
$confirmed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions p $stats_where AND p.status = 'dispensed'");
$stmt->execute($stats_params);
$dispensed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM prescriptions p $stats_where AND p.status = 'cancelled'");
$stmt->execute($stats_params);
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(pi.total_price), 0) as total FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id $stats_where AND p.status = 'dispensed'");
$stmt->execute($stats_params);
$total_amount_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(pi.total_price), 0) as total FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id $stats_where AND p.status = 'pending'");
$stmt->execute($stats_params);
$pending_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(pi.total_price), 0) as total FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id $stats_where AND p.status = 'confirmed'");
$stmt->execute($stats_params);
$confirmed_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(pi.total_price), 0) as total FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id $stats_where AND p.status = 'cancelled'");
$stmt->execute($stats_params);
$cancelled_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_patients_count = count($patients_array);

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<style>
:root {
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-darker: #083C8A;
    --primary-light: #6EA8FE;
    --primary-bg: #E8F0FE;
    --primary-bg-dark: #1E3A5F;
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --purple-bg: #EDE9FE;
    --gray-50: #F8FAFC;
    --gray-100: #F1F5F9;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --radius: 10px;
    --radius-lg: 14px;
    --shadow: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
}

/* PAGE HEADER */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
    border-radius: 16px;
    padding: 20px 28px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
    position: relative;
    overflow: hidden;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.4rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
}

.page-header-custom .page-title i { font-size: 1.6rem; opacity: 0.9; }

.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.82rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 4px;
}

.page-header-custom .role-badge-display {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
}

.page-header-custom .branch-tag {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.page-header-custom .btn-outline-light {
    background: rgba(255,255,255,0.12);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.75rem;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    position: relative;
    z-index: 1;
}

.page-header-custom .btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

/* STATS - 5 CARDS */
.stats-grid-5 {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card-custom {
    border-radius: 12px;
    padding: 14px 16px;
    border: none;
    display: flex;
    flex-direction: column;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    color: white;
    position: relative;
    overflow: hidden;
    min-height: 95px;
    text-decoration: none;
}

.stat-card-custom::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 140px; height: 140px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    pointer-events: none;
}

.stat-card-custom:hover { transform: translateY(-4px) scale(1.01); box-shadow: 0 10px 32px rgba(0,0,0,0.2); }

.stat-card-custom .stat-icon {
    width: 38px; height: 38px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    background: rgba(255,255,255,0.18);
    color: white;
    border: 1px solid rgba(255,255,255,0.12);
    margin-bottom: 4px;
    position: relative;
    z-index: 1;
}

.stat-card-custom .stat-content { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; }
.stat-card-custom .stat-label { font-size: 0.55rem; color: rgba(255,255,255,0.85); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 1px 0; }
.stat-card-custom .stat-number { font-size: 1.7rem; font-weight: 800; color: white; margin: 0; line-height: 1.1; }
.stat-card-custom .stat-amount { font-size: 0.75rem; font-weight: 600; color: rgba(255,255,255,0.9); margin-top: 2px; }
.stat-card-custom .stat-arrow { position: absolute; right: 10px; bottom: 10px; color: rgba(255,255,255,0.12); font-size: 0.65rem; z-index: 1; }

.card-blue-1 { background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8); }
.card-blue-2 { background: linear-gradient(135deg, #0EA5E9, #0284C7, #075985); }
.card-blue-3 { background: linear-gradient(135deg, #0B5ED7, #083C8A, #062E6B); }
.card-blue-4 { background: linear-gradient(135deg, #4F46E5, #4338CA, #3730A3); }
.card-blue-5 { background: linear-gradient(135deg, #DC2626, #B91C1C, #991B1B); }

/* FILTER SECTION */
.filter-section {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 12px 16px;
    border: 1px solid var(--border-color);
    margin-bottom: 14px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}

.filter-section .filter-label {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-right: 4px;
}

.filter-btn {
    padding: 3px 12px;
    border-radius: 18px;
    font-size: 0.68rem;
    font-weight: 500;
    border: 2px solid var(--border-color);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.filter-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
.filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
.filter-btn i { margin-right: 4px; font-size: 0.6rem; }

/* COMPACT SEARCH PANEL */
.med-search-panel {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 14px;
    box-shadow: 0 3px 12px rgba(11, 94, 215, 0.2);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.med-search-panel .search-label {
    color: white;
    font-size: 0.72rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.med-search-panel .search-label i {
    background: rgba(255,255,255,0.2);
    width: 26px; height: 26px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
}

.med-search-panel .search-box {
    position: relative;
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.18);
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 8px;
    padding: 0 12px;
    transition: all 0.3s ease;
    height: 38px;
    flex: 1;
    min-width: 200px;
}

.med-search-panel .search-box:focus-within {
    background: rgba(255,255,255,0.28);
    border-color: rgba(255,255,255,0.6);
    box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
}

.med-search-panel .search-box .search-icon {
    color: rgba(255,255,255,0.9);
    font-size: 0.85rem;
    margin-right: 8px;
}

.med-search-panel .search-box input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: white;
    font-size: 0.82rem;
    padding: 0;
    font-weight: 500;
}

.med-search-panel .search-box input::placeholder {
    color: rgba(255,255,255,0.65);
    font-size: 0.78rem;
}

.med-search-panel .search-box .search-clear-med {
    background: rgba(255,255,255,0.25);
    border: none;
    color: white;
    width: 20px; height: 20px;
    border-radius: 50%;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    margin-left: 6px;
}

.med-search-panel .search-box .search-clear-med.visible { display: flex; }
.med-search-panel .search-box .search-clear-med:hover { background: rgba(255,255,255,0.4); }

.med-search-panel .search-count {
    color: rgba(255,255,255,0.9);
    font-size: 0.68rem;
    font-weight: 700;
    background: rgba(255,255,255,0.2);
    padding: 3px 10px;
    border-radius: 12px;
    white-space: nowrap;
    display: none;
}

.med-search-panel .search-count.show { display: inline-block; }

/* ✅ MEDICATION SUMMARY RESULT */
.med-summary-result {
    background: var(--bg-card);
    border: 2px solid var(--primary);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 14px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.15);
    animation: fadeInUp 0.4s ease forwards;
}

.med-summary-result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 12px;
    border-bottom: 2px dashed var(--border-color);
    margin-bottom: 14px;
    flex-wrap: wrap;
    gap: 10px;
}

.med-summary-result-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    color: var(--text-primary);
    flex-wrap: wrap;
}

.med-summary-result-title i {
    background: var(--primary);
    color: white;
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.med-summary-result-title strong {
    color: var(--primary);
    font-weight: 800;
    font-size: 1.05rem;
}

.med-summary-close {
    background: transparent;
    border: 1.5px solid var(--border-color);
    color: var(--text-secondary);
    width: 28px; height: 28px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    transition: all 0.2s;
}

.med-summary-close:hover {
    background: var(--danger);
    border-color: var(--danger);
    color: white;
}

.med-summary-result-body {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}

.med-summary-stat {
    background: var(--bg-body);
    border-radius: 10px;
    padding: 12px 10px;
    text-align: center;
    border: 2px solid transparent;
    transition: all 0.3s;
}

.med-summary-stat:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }

.med-summary-stat.total { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }
.med-summary-stat.dispensed { background: linear-gradient(135deg, #059669, #047857); color: white; }
.med-summary-stat.pending { background: linear-gradient(135deg, #D97706, #B45309); color: white; }
.med-summary-stat.confirmed { background: linear-gradient(135deg, #4F46E5, #4338CA); color: white; }

.med-summary-stat-label {
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.9;
    display: block;
    margin-bottom: 4px;
}

.med-summary-stat-value {
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1.1;
    display: block;
}

.med-summary-stat-sub {
    font-size: 0.6rem;
    opacity: 0.8;
    display: block;
    margin-top: 2px;
}

/* PATIENT CARD */
.patient-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
}

.patient-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-md);
}

.patient-card.filtered-out { display: none; }

.patient-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.patient-header:hover {
    background: linear-gradient(135deg, #0A4CA8, #083C8A);
}

.patient-header .patient-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 250px;
}

.patient-header .patient-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255,255,255,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.2rem;
    color: white;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.4);
}

.patient-header .patient-name {
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.patient-header .patient-name mark.highlight-name {
    background: #FEF08A;
    color: #854D0E;
    padding: 1px 4px;
    border-radius: 4px;
    font-weight: 800;
}

.patient-header .patient-meta {
    display: flex;
    gap: 12px;
    font-size: 0.72rem;
    opacity: 0.9;
    flex-wrap: wrap;
    margin-top: 2px;
}

.patient-header .patient-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.patient-header .patient-stats {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.patient-header .patient-stats .stat-pill {
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: 18px;
    font-size: 0.68rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.15);
}

.patient-header .chevron {
    font-size: 0.85rem;
    transition: transform 0.3s ease;
}

.patient-header .chevron.rotated { transform: rotate(180deg); }

.patient-body {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease, padding 0.3s ease;
    background: var(--bg-card);
}

.patient-body.open {
    max-height: 5000px;
    padding: 14px 20px 18px;
}

.patient-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 2px dashed var(--border-color);
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.patient-actions .patient-actions-info {
    font-size: 0.75rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

/* TABLE HEADER BAR */
.patient-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.patient-table-header .table-search-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
}

.patient-table-header .table-scroll-controls {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}

.table-scroll-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1.5px solid var(--border-color);
    background: var(--bg-card);
    color: var(--primary);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    transition: all 0.3s ease;
}

.table-scroll-btn:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(11, 94, 215, 0.3);
}

.table-scroll-btn:active { transform: scale(0.95); }

.table-scroll-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    background: var(--bg-body);
    color: var(--text-secondary);
}

.table-scroll-btn:disabled:hover {
    background: var(--bg-body);
    border-color: var(--border-color);
    color: var(--text-secondary);
    transform: none;
    box-shadow: none;
}

/* PER-PATIENT SEARCH BAR */
.patient-search-bar {
    background: var(--bg-body);
    border: 1.5px solid var(--border-color);
    border-radius: 7px;
    padding: 0 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    height: 32px;
    transition: all 0.2s;
    max-width: 420px;
    flex: 1;
}

.patient-search-bar:focus-within {
    border-color: var(--primary);
    background: var(--bg-card);
    box-shadow: 0 0 0 2px rgba(11, 94, 215, 0.1);
}

.patient-search-bar i {
    color: var(--primary);
    font-size: 0.7rem;
}

.patient-search-bar input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: var(--text-primary);
    font-size: 0.72rem;
    padding: 0;
}

.patient-search-bar input::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
    font-size: 0.7rem;
}

.patient-search-bar .per-patient-count {
    font-size: 0.6rem;
    color: var(--primary);
    font-weight: 700;
    background: var(--primary-bg);
    padding: 2px 8px;
    border-radius: 10px;
    white-space: nowrap;
}

.patient-search-bar .per-patient-clear {
    background: var(--primary-bg);
    border: none;
    color: var(--primary);
    width: 18px; height: 18px;
    border-radius: 50%;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 0.55rem;
    transition: all 0.2s;
}

.patient-search-bar .per-patient-clear.visible { display: flex; }
.patient-search-bar .per-patient-clear:hover { background: var(--primary); color: white; }

/* TABLE SCROLL */
.table-scroll {
    overflow-x: auto;
    overflow-y: visible;
    border-radius: 10px;
    scroll-behavior: smooth;
}

.table-scroll::-webkit-scrollbar { height: 8px; }
.table-scroll::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
.table-scroll::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
.table-scroll::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
    min-width: 1100px;
}

.data-table thead th {
    text-align: left;
    padding: 9px 12px;
    font-weight: 700;
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #ffffff;
    background: var(--primary);
    border-bottom: 3px solid var(--primary-dark);
    white-space: nowrap;
}

.data-table thead th i { margin-right: 4px; opacity: 0.7; }

.data-table tbody td {
    padding: 9px 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}

.data-table tbody tr:hover td { background: var(--primary-bg); }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:nth-child(even) td { background: var(--gray-50); }

[data-theme="dark"] .data-table tbody tr:nth-child(even) td { background: #1A1A2E; }
[data-theme="dark"] .data-table tbody tr:hover td { background: #1E3A5F; }

/* MEDICATIONS */
.medication-list {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.medication-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    background: var(--bg-body);
    padding: 4px 8px;
    border-radius: 5px;
    font-size: 0.7rem;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.medication-item:hover {
    border-color: var(--primary);
    background: var(--primary-bg);
}

.medication-item .med-name {
    font-weight: 500;
    flex: 1;
    color: var(--text-primary);
}

.medication-item .med-qty {
    background: var(--primary);
    color: white;
    padding: 1px 8px;
    border-radius: 9px;
    font-size: 0.62rem;
    font-weight: 700;
    white-space: nowrap;
}

.medication-item .med-dosage {
    font-size: 0.62rem;
    color: var(--text-secondary);
    margin-left: 4px;
}

.medication-item.highlighted-med {
    background: #FED7AA !important;
    border-color: #FDBA74 !important;
    box-shadow: 0 0 0 2px #FDBA74;
}

mark.highlight {
    background: #FEF08A;
    color: #854D0E;
    padding: 1px 3px;
    border-radius: 3px;
    font-weight: 700;
}

[data-theme="dark"] mark.highlight {
    background: #854D0E;
    color: #FEF08A;
}

/* BADGES */
.prescription-tag {
    display: inline-block;
    font-family: monospace;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 9px;
    border-radius: 5px;
    background: var(--primary-bg);
    color: var(--primary);
    white-space: nowrap;
}

[data-theme="dark"] .prescription-tag {
    background: #1E3A5F;
    color: #60A5FA;
}

.status-badge {
    padding: 3px 10px;
    border-radius: 18px;
    font-size: 0.62rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
}

.status-badge.warning { background: #FEF3C7; color: #D97706; }
.status-badge.success { background: #D1FAE5; color: #059669; }
.status-badge.danger { background: #FEE2E2; color: #EF4444; }
.status-badge.info { background: #E8F0FE; color: #0B5ED7; }

[data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
[data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
[data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }

.amount-cell {
    font-weight: 700;
    color: var(--primary);
    font-family: 'Courier New', monospace;
    font-size: 0.82rem;
}

.action-buttons-vertical {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: stretch;
    justify-content: center;
    min-width: 70px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.62rem;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
    white-space: nowrap;
    width: 100%;
    height: 26px;
}

.btn-action:hover { transform: translateY(-2px); }

.btn-action.btn-view-action {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    box-shadow: 0 2px 6px rgba(11, 94, 215, 0.3);
}

.btn-action.btn-delete-action {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
}

.btn-delete-all-patient {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 7px;
    font-weight: 700;
    font-size: 0.68rem;
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
}

.btn-delete-all-patient:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
}

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px dashed var(--border-color);
}

.empty-state i {
    font-size: 3.5rem;
    color: var(--primary);
    display: block;
    margin-bottom: 12px;
}

.empty-state p { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
.empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }

/* FOOTER */
.footer {
    padding: 14px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

/* TOAST */
.toast-custom {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 14px 20px;
    border-radius: 12px;
    z-index: 9999;
    max-width: 400px;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    box-shadow: var(--shadow-lg);
    font-size: 0.85rem;
}

.toast-custom.show { transform: translateY(0); opacity: 1; }
.toast-custom.success { background: linear-gradient(135deg, #059669, #047857); }
.toast-custom.error { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.toast-custom.info { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }

/* RESPONSIVE */
@media (max-width: 1200px) { .stats-grid-5 { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1024px) { .stats-grid-5 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
    .page-header-custom { padding: 14px 16px; }
    .page-header-custom .page-title { font-size: 1.15rem; }
    .stats-grid-5 { grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-card-custom { min-height: 80px; padding: 10px 12px; }
    .stat-card-custom .stat-number { font-size: 1.3rem; }
    .med-search-panel { flex-direction: column; align-items: stretch; }
    .med-search-panel .search-label { justify-content: center; }
    .med-summary-result-body { grid-template-columns: repeat(2, 1fr); }
    .patient-header { flex-direction: column; align-items: stretch; }
    .patient-actions { flex-direction: column; align-items: stretch; }
    .patient-table-header { flex-direction: column; align-items: stretch; }
    .patient-table-header .table-scroll-controls { align-self: flex-end; }
}
@media (max-width: 480px) {
    .stats-grid-5 { grid-template-columns: 1fr 1fr; gap: 6px; }
    .stat-card-custom { padding: 8px 10px; min-height: 70px; }
    .stat-card-custom .stat-number { font-size: 1rem; }
    .med-summary-result-body { grid-template-columns: 1fr; }
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
</style>

<main class="main-content">

    <div class="page-header-custom animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i>
                Prescriptions
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="branch-tag">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <span class="branch-tag">
                    <i class="fas fa-users"></i> <?= $total_patients_count ?> Patients
                </span>
                <span class="branch-tag">
                    <i class="fas fa-prescription"></i> <?= $total_all ?> Prescriptions
                </span>
                <span class="branch-tag" style="background:rgba(52,211,153,0.25);color:#A7F3D0;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_amount_all, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <div class="stats-grid-5 animate-fade-in-up">
        <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="stat-card-custom card-blue-1">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total</p>
                <p class="stat-number"><?= $total_all ?></p>
                <p class="stat-amount">TSh <?= number_format($total_amount_all, 0) ?></p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        <a href="prescriptions.php?branch=<?= $selected_branch_id ?>&status=pending" class="stat-card-custom card-blue-2">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= $pending_count ?></p>
                <p class="stat-amount">TSh <?= number_format($pending_amount, 0) ?></p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        <a href="prescriptions.php?branch=<?= $selected_branch_id ?>&status=confirmed" class="stat-card-custom card-blue-3">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Confirmed</p>
                <p class="stat-number"><?= $confirmed_count ?></p>
                <p class="stat-amount">TSh <?= number_format($confirmed_amount, 0) ?></p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        <a href="prescriptions.php?branch=<?= $selected_branch_id ?>&status=dispensed" class="stat-card-custom card-blue-4">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div class="stat-content">
                <p class="stat-label">Dispensed</p>
                <p class="stat-number"><?= $dispensed_count ?></p>
                <p class="stat-amount">TSh <?= number_format($total_amount_all, 0) ?></p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
        <a href="prescriptions.php?branch=<?= $selected_branch_id ?>&status=cancelled" class="stat-card-custom card-blue-5">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Cancelled</p>
                <p class="stat-number"><?= $cancelled_count ?></p>
                <p class="stat-amount">TSh <?= number_format($cancelled_amount, 0) ?></p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </a>
    </div>

    <div class="filter-section animate-fade-in-up">
        <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
        <a href="?branch=<?= $selected_branch_id ?>&status=" class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=pending" class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=confirmed" class="filter-btn <?= $status_filter === 'confirmed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Confirmed
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=dispensed" class="filter-btn <?= $status_filter === 'dispensed' ? 'active' : '' ?>">
            <i class="fas fa-pills"></i> Dispensed
        </a>
        <a href="?branch=<?= $selected_branch_id ?>&status=cancelled" class="filter-btn <?= $status_filter === 'cancelled' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Cancelled
        </a>
        <?php if (!empty($status_filter)): ?>
            <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="filter-btn" style="border-color: var(--danger); color: var(--danger);">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </div>

    <div class="med-search-panel animate-fade-in-up">
        <div class="search-label">
            <i class="fas fa-search"></i>
            <span>Search</span>
        </div>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" 
                   id="medSearchInput" 
                   placeholder="Search patient name, ID, prescription #, medicine..."
                   autocomplete="off">
            <button type="button" class="search-clear-med" id="medSearchClear" title="Clear">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <span class="search-count" id="medSearchCount"></span>
    </div>

    <!-- ✅ MEDICATION SUMMARY RESULT -->
    <div id="medSummaryResult" class="med-summary-result animate-fade-in-up" style="display:none;">
        <div class="med-summary-result-header">
            <div class="med-summary-result-title">
                <i class="fas fa-pills"></i>
                <span>Medication:</span>
                <strong id="medSummaryName">-</strong>
            </div>
            <button type="button" class="med-summary-close" onclick="closeMedSummary()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="med-summary-result-body">
            <div class="med-summary-stat total">
                <span class="med-summary-stat-label">Total Quantity</span>
                <span class="med-summary-stat-value" id="medSummaryTotal">0</span>
                <span class="med-summary-stat-sub"><span id="medSummaryPatients">0</span> patients</span>
            </div>
            <div class="med-summary-stat dispensed">
                <span class="med-summary-stat-label">Dispensed</span>
                <span class="med-summary-stat-value" id="medSummaryDispensed">0</span>
                <span class="med-summary-stat-sub"><span id="medSummaryDispensedPatients">0</span> patients</span>
            </div>
            <div class="med-summary-stat pending">
                <span class="med-summary-stat-label">Pending</span>
                <span class="med-summary-stat-value" id="medSummaryPending">0</span>
                <span class="med-summary-stat-sub"><span id="medSummaryPendingPatients">0</span> patients</span>
            </div>
            <div class="med-summary-stat confirmed">
                <span class="med-summary-stat-label">Confirmed</span>
                <span class="med-summary-stat-value" id="medSummaryConfirmed">0</span>
                <span class="med-summary-stat-sub"><span id="medSummaryConfirmedPatients">0</span> patients</span>
            </div>
        </div>
    </div>

    <div id="patientsContainer">
        <?php if (count($patients_array) > 0): ?>
            <?php foreach ($patients_array as $patient): 
                $patient_id = $patient['patient_id'];
                $presc_count = $patient['prescription_count'];
                $age = calculateAge($patient['date_of_birth']);
            ?>
                <div class="patient-card animate-fade-in-up" 
                     data-patient-id="<?= $patient_id ?>"
                     data-patient-search="<?= htmlspecialchars(strtolower($patient['patient_name'] . ' ' . $patient['patient_number'] . ' ' . $patient['patient_phone'])) ?>">
                    
                    <div class="patient-header" onclick="togglePatient(<?= $patient_id ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <span class="patient-name-text"><?= htmlspecialchars($patient['patient_name']) ?></span>
                                    <?php if ($presc_count > 1): ?>
                                        <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:0.58rem;font-weight:600;">
                                            <i class="fas fa-prescription"></i> <?= $presc_count ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_number']) ?></span>
                                    <?php if (!empty($patient['patient_phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['patient_gender'])): ?>
                                        <span><i class="fas fa-<?= $patient['patient_gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['patient_gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($patient['total_amount'] ?? 0, 0) ?>
                            </span>
                            <span class="stat-pill">
                                <i class="fas fa-clock"></i> <?= date('M d, Y', strtotime($patient['latest_date'] ?? 'now')) ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $patient_id ?>"></i>
                        </div>
                    </div>
                    
                    <div class="patient-body" id="body-<?= $patient_id ?>">
                        
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $presc_count ?></strong> prescription(s) •
                                Total: <strong>TSh <?= number_format($patient['total_amount'] ?? 0, 0) ?></strong>
                            </div>
                            <?php if ($presc_count > 1): ?>
                                <a href="?delete_patient=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
                                   class="btn-delete-all-patient"
                                   onclick="return confirm('⚠️ DELETE ALL PRESCRIPTIONS?\n\nPatient: <?= htmlspecialchars(addslashes($patient['patient_name'])) ?>\nPrescriptions: <?= $presc_count ?>\nTotal: TSh <?= number_format($patient['total_amount'] ?? 0, 0) ?>\n\nThis action CANNOT be undone!');">
                                    <i class="fas fa-trash-alt"></i>
                                    Delete All (<?= $presc_count ?>)
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="patient-table-header">
                            <div class="table-search-left">
                                <div class="patient-search-bar">
                                    <i class="fas fa-search"></i>
                                    <input type="text" 
                                           class="per-patient-search"
                                           data-patient-id="<?= $patient_id ?>"
                                           placeholder="Filter this patient's prescriptions..."
                                           autocomplete="off">
                                    <span class="per-patient-count">
                                        <?= $presc_count ?> total
                                    </span>
                                    <button type="button" 
                                            class="per-patient-clear" 
                                            data-patient-id="<?= $patient_id ?>"
                                            title="Clear">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="table-scroll-controls">
                                <button type="button" 
                                        class="table-scroll-btn" 
                                        id="scroll-left-<?= $patient_id ?>"
                                        onclick="scrollPatientTable(<?= $patient_id ?>, 'left')" 
                                        title="Scroll Left">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button type="button" 
                                        class="table-scroll-btn" 
                                        id="scroll-right-<?= $patient_id ?>"
                                        onclick="scrollPatientTable(<?= $patient_id ?>, 'right')" 
                                        title="Scroll Right">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-scroll" id="table-scroll-<?= $patient_id ?>">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width:45px;">#</th>
                                        <th><i class="fas fa-hashtag"></i> Prescription #</th>
                                        <th><i class="fas fa-pills"></i> Medications</th>
                                        <th><i class="fas fa-info-circle"></i> Status</th>
                                        <th><i class="fas fa-money-bill-wave"></i> Amount</th>
                                        <th><i class="fas fa-user-md"></i> Doctor</th>
                                        <th><i class="fas fa-calendar"></i> Date</th>
                                        <th style="text-align:center;width:110px;"><i class="fas fa-cog"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="patient-body-<?= $patient_id ?>">
                                    <?php $i = 1; foreach ($patient['prescriptions'] as $presc): 
                                        $status = $presc['status'] ?? 'pending';
                                        $status_class = 'warning';
                                        $status_icon = '⏳';
                                        if ($status === 'confirmed') { $status_class = 'info'; $status_icon = '✅'; }
                                        elseif ($status === 'dispensed') { $status_class = 'success'; $status_icon = '💊'; }
                                        elseif ($status === 'cancelled') { $status_class = 'danger'; $status_icon = '❌'; }
                                        
                                        $search_data = strtolower(
                                            ($presc['prescription_number'] ?? '') . ' ' . 
                                            ($presc['patient_name'] ?? '') . ' ' . 
                                            ($presc['patient_number'] ?? '') . ' ' . 
                                            ($presc['doctor_name'] ?? '')
                                        );
                                        foreach ($presc['medications'] as $med) {
                                            $search_data .= ' ' . ($med['medication_name'] ?? '');
                                        }
                                    ?>
                                        <tr class="test-row" data-search="<?= htmlspecialchars($search_data) ?>">
                                            <td class="row-number"><?= $i++ ?></td>
                                            <td>
                                                <span class="prescription-tag"><?= htmlspecialchars($presc['prescription_number'] ?? 'N/A') ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($presc['medications'])): ?>
                                                    <div class="medication-list">
                                                        <?php foreach ($presc['medications'] as $med): ?>
                                                            <div class="medication-item" data-med-name="<?= htmlspecialchars(strtolower($med['medication_name'] ?? '')) ?>">
                                                                <div class="med-name">
                                                                    <span class="med-name-text"><?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?></span>
                                                                    <?php if (!empty($med['dosage'])): ?>
                                                                        <span class="med-dosage"><?= htmlspecialchars($med['dosage']) ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <span class="med-qty">x<?= (int)($med['quantity'] ?? 0) ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted);font-size:0.72rem;">No medications</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= $status_class ?>">
                                                    <?= $status_icon ?> <?= ucfirst($status) ?>
                                                </span>
                                            </td>
                                            <td class="amount-cell">
                                                TSh <?= number_format($presc['total_amount'] ?? 0, 0) ?>
                                            </td>
                                            <td style="font-size:0.72rem;">
                                                Dr. <?= htmlspecialchars($presc['doctor_name'] ?? 'N/A') ?>
                                            </td>
                                            <td style="font-size:0.7rem;">
                                                <?= date('M d, Y', strtotime($presc['created_at'] ?? 'now')) ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons-vertical">
                                                    <a href="prescription_details.php?id=<?= $presc['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                                       class="btn-action btn-view-action" title="View">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="?delete_prescription=<?= $presc['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                                       class="btn-action btn-delete-action" 
                                                       title="Delete"
                                                       onclick="return confirm('⚠️ Delete this prescription?\n\nPrescription #: <?= htmlspecialchars(addslashes($presc['prescription_number'] ?? 'N/A')) ?>\nPatient: <?= htmlspecialchars(addslashes($presc['patient_name'] ?? 'N/A')) ?>\n\nThis action cannot be undone!');">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div id="noSearchResults" style="display:none;">
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>No results found</p>
                    <p class="sub">Try a different keyword</p>
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescriptions found</p>
                <p class="sub">
                    <?= !empty($search) || !empty($status_filter) ? 'Try adjusting your filters' : 'No prescriptions have been created yet' ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Prescriptions (Grouped by Patient)
            <span style="margin:0 8px;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span style="margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
// ================================================================
// MEDICATION SUMMARY DATA (from PHP)
// ================================================================
var MEDICATION_DATA = <?= json_encode($medication_summary) ?>;

// ================================================================
// TOGGLE PATIENT
// ================================================================
function togglePatient(patientId) {
    var body = document.getElementById('body-' + patientId);
    var chevron = document.getElementById('chevron-' + patientId);
    if (body) body.classList.toggle('open');
    if (chevron) chevron.classList.toggle('rotated');
    
    setTimeout(function() {
        updateScrollButtons(patientId);
    }, 350);
}

document.addEventListener('DOMContentLoaded', function() {
    var firstBody = document.querySelector('.patient-body');
    var firstChevron = document.querySelector('.chevron');
    if (firstBody) {
        setTimeout(function() {
            firstBody.classList.add('open');
            if (firstChevron) firstChevron.classList.add('rotated');
        }, 300);
    }
});

// ================================================================
// SCROLL TABLE FUNCTIONS
// ================================================================
function scrollPatientTable(patientId, direction) {
    var wrapper = document.getElementById('table-scroll-' + patientId);
    if (!wrapper) return;
    
    var scrollAmount = 400;
    if (direction === 'left') {
        wrapper.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        wrapper.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
}

function updateScrollButtons(patientId) {
    var wrapper = document.getElementById('table-scroll-' + patientId);
    var btnLeft = document.getElementById('scroll-left-' + patientId);
    var btnRight = document.getElementById('scroll-right-' + patientId);
    
    if (!wrapper || !btnLeft || !btnRight) return;
    
    var maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
    btnLeft.disabled = (wrapper.scrollLeft <= 5);
    btnRight.disabled = (wrapper.scrollLeft >= maxScroll - 5 || maxScroll <= 0);
}

document.addEventListener('DOMContentLoaded', function() {
    var wrappers = document.querySelectorAll('.table-scroll[id^="table-scroll-"]');
    wrappers.forEach(function(wrapper) {
        var patientId = wrapper.id.replace('table-scroll-', '');
        
        wrapper.addEventListener('scroll', function() {
            updateScrollButtons(patientId);
        });
        
        setTimeout(function() {
            updateScrollButtons(patientId);
        }, 200);
    });
    
    window.addEventListener('resize', function() {
        wrappers.forEach(function(wrapper) {
            var patientId = wrapper.id.replace('table-scroll-', '');
            setTimeout(function() {
                updateScrollButtons(patientId);
            }, 200);
        });
    });
});

// ================================================================
// ✅ MEDICATION SUMMARY SEARCH
// ================================================================
function searchMedicationSummary(query) {
    query = query.toLowerCase().trim();
    var resultCard = document.getElementById('medSummaryResult');
    
    if (!query) {
        resultCard.style.display = 'none';
        return;
    }
    
    // Find matching medication
    var matchedMed = null;
    
    // Exact match first
    if (MEDICATION_DATA[query]) {
        matchedMed = MEDICATION_DATA[query];
    } else {
        // Partial match
        for (var key in MEDICATION_DATA) {
            if (key.includes(query)) {
                matchedMed = MEDICATION_DATA[key];
                break;
            }
        }
    }
    
    if (!matchedMed) {
        resultCard.style.display = 'none';
        return;
    }
    
    // Show summary
    document.getElementById('medSummaryName').textContent = matchedMed.name;
    document.getElementById('medSummaryTotal').textContent = matchedMed.total;
    document.getElementById('medSummaryPatients').textContent = matchedMed.patients;
    
    document.getElementById('medSummaryDispensed').textContent = matchedMed.byStatus.dispensed.qty;
    document.getElementById('medSummaryDispensedPatients').textContent = matchedMed.byStatus.dispensed.patients;
    
    document.getElementById('medSummaryPending').textContent = matchedMed.byStatus.pending.qty;
    document.getElementById('medSummaryPendingPatients').textContent = matchedMed.byStatus.pending.patients;
    
    document.getElementById('medSummaryConfirmed').textContent = matchedMed.byStatus.confirmed.qty;
    document.getElementById('medSummaryConfirmedPatients').textContent = matchedMed.byStatus.confirmed.patients;
    
    resultCard.style.display = 'block';
}

function closeMedSummary() {
    document.getElementById('medSummaryResult').style.display = 'none';
}

// ================================================================
// MAIN SEARCH - HIGHLIGHT + FILTER + MEDICATION SUMMARY
// ================================================================
(function() {
    var medSearchInput = document.getElementById('medSearchInput');
    var medSearchClear = document.getElementById('medSearchClear');
    var medSearchCount = document.getElementById('medSearchCount');
    
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function highlightText(text, query) {
        if (!query || !text) return text;
        var escaped = escapeRegExp(query);
        var regex = new RegExp('(' + escaped + ')', 'gi');
        return text.replace(regex, '<mark class="highlight-name">$1</mark>');
    }
    
    function removeAllHighlights() {
        document.querySelectorAll('.patient-card').forEach(function(card) {
            var nameEl = card.querySelector('.patient-name-text');
            if (nameEl && nameEl.getAttribute('data-original')) {
                nameEl.innerHTML = nameEl.getAttribute('data-original');
            }
            card.querySelectorAll('.medication-item').forEach(function(item) {
                item.classList.remove('highlighted-med');
            });
        });
    }
    
    function applyPatientHighlight(card, query) {
        if (!query) return;
        var nameEl = card.querySelector('.patient-name-text');
        if (nameEl) {
            var orig = nameEl.getAttribute('data-original') || nameEl.textContent;
            nameEl.setAttribute('data-original', orig);
            nameEl.innerHTML = highlightText(orig, query);
        }
    }
    
    function filterAll(query) {
        var patientCards = document.querySelectorAll('.patient-card');
        var noSearchResults = document.getElementById('noSearchResults');
        var visiblePatients = 0;
        var queryLower = query.toLowerCase().trim();
        
        // ✅ Show medication summary
        searchMedicationSummary(queryLower);
        
        if (queryLower === '') {
            removeAllHighlights();
        }
        
        patientCards.forEach(function(card) {
            if (queryLower === '') {
                var nameEl = card.querySelector('.patient-name-text');
                if (nameEl && nameEl.getAttribute('data-original')) {
                    nameEl.innerHTML = nameEl.getAttribute('data-original');
                }
            }
            
            var patientSearchData = card.getAttribute('data-patient-search') || '';
            var testRows = card.querySelectorAll('.test-row');
            var hasMatch = false;
            
            if (queryLower === '') {
                hasMatch = true;
            } else {
                if (patientSearchData.includes(queryLower)) {
                    hasMatch = true;
                    applyPatientHighlight(card, query);
                }
            }
            
            testRows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                var medItems = row.querySelectorAll('.medication-item');
                var medMatch = false;
                
                medItems.forEach(function(item) {
                    var medName = item.getAttribute('data-med-name') || '';
                    if (queryLower !== '' && medName.includes(queryLower)) {
                        medMatch = true;
                        item.classList.add('highlighted-med');
                    } else {
                        item.classList.remove('highlighted-med');
                    }
                });
                
                if (queryLower === '' || searchData.includes(queryLower) || medMatch) {
                    hasMatch = true;
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (hasMatch) {
                card.classList.remove('filtered-out');
                visiblePatients++;
                
                if (queryLower !== '') {
                    var body = card.querySelector('.patient-body');
                    var chevron = card.querySelector('.chevron');
                    if (body && !body.classList.contains('open')) {
                        body.classList.add('open');
                        if (chevron) chevron.classList.add('rotated');
                    }
                }
            } else {
                card.classList.add('filtered-out');
            }
        });
        
        if (noSearchResults) {
            noSearchResults.style.display = (visiblePatients === 0 && queryLower !== '') ? '' : 'none';
        }
        
        if (queryLower !== '') {
            medSearchCount.textContent = visiblePatients + ' patient' + (visiblePatients !== 1 ? 's' : '');
            medSearchCount.classList.add('show');
            medSearchClear.classList.add('visible');
        } else {
            medSearchCount.classList.remove('show');
            medSearchClear.classList.remove('visible');
        }
        
        setTimeout(function() {
            patientCards.forEach(function(card) {
                var pid = card.getAttribute('data-patient-id');
                updateScrollButtons(pid);
            });
        }, 200);
    }
    
    if (medSearchInput) {
        medSearchInput.addEventListener('input', function() {
            filterAll(this.value);
        });
        medSearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                closeMedSummary();
                filterAll('');
                this.blur();
            }
        });
    }
    
    if (medSearchClear) {
        medSearchClear.addEventListener('click', function() {
            medSearchInput.value = '';
            closeMedSummary();
            filterAll('');
            medSearchInput.focus();
        });
    }
})();

// ================================================================
// PER-PATIENT SEARCH
// ================================================================
document.querySelectorAll('.per-patient-search').forEach(function(input) {
    input.addEventListener('input', function() {
        var patientId = this.getAttribute('data-patient-id');
        var query = this.value.toLowerCase().trim();
        var tbody = document.getElementById('patient-body-' + patientId);
        if (!tbody) return;
        
        var rows = tbody.querySelectorAll('.test-row');
        var visibleCount = 0;
        var searchBar = this.closest('.patient-search-bar');
        var clearBtn = searchBar ? searchBar.querySelector('.per-patient-clear') : null;
        
        if (clearBtn) {
            if (query !== '') {
                clearBtn.classList.add('visible');
            } else {
                clearBtn.classList.remove('visible');
            }
        }
        
        rows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            var medItems = row.querySelectorAll('.medication-item');
            var medMatch = false;
            
            medItems.forEach(function(item) {
                var medName = item.getAttribute('data-med-name') || '';
                if (query === '') {
                    item.classList.remove('highlighted-med');
                } else if (medName.includes(query)) {
                    medMatch = true;
                    item.classList.add('highlighted-med');
                } else {
                    item.classList.remove('highlighted-med');
                }
            });
            
            if (query === '' || searchData.includes(query) || medMatch) {
                row.style.display = '';
                visibleCount++;
                var rowNum = row.querySelector('.row-number');
                if (rowNum) rowNum.textContent = visibleCount;
            } else {
                row.style.display = 'none';
            }
        });
        
        var countBadge = searchBar ? searchBar.querySelector('.per-patient-count') : null;
        if (countBadge) {
            if (query === '') {
                countBadge.textContent = rows.length + ' total';
            } else {
                countBadge.textContent = visibleCount + ' of ' + rows.length;
            }
        }
        
        setTimeout(function() {
            updateScrollButtons(patientId);
        }, 100);
    });
});

document.querySelectorAll('.per-patient-clear').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var patientId = this.getAttribute('data-patient-id');
        var searchBar = this.closest('.patient-search-bar');
        var input = searchBar ? searchBar.querySelector('.per-patient-search') : null;
        
        if (input) {
            input.value = '';
            input.dispatchEvent(new Event('input'));
            input.focus();
        }
    });
});

// FOOTER TIME
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTimestamp');
    if (ftEl) ftEl.textContent = 'Last updated: ' + timeStr;
}, 1000);

// TOAST
function showToast(title, message, type) {
    var toast = document.getElementById('toast');
    var toastTitle = document.getElementById('toastTitle');
    var toastMessage = document.getElementById('toastMessage');
    if (!toast) return;
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
    document.addEventListener('DOMContentLoaded', function() {
        showToast('✅ Success', 'Prescription deleted successfully!', 'success');
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('deleted');
            window.history.replaceState({}, document.title, url.toString());
        }
    });
<?php endif; ?>

<?php if (isset($_GET['deleted_all']) && $_GET['deleted_all'] == 1): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('✅ Success', 'All prescriptions for this patient deleted!', 'success');
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('deleted_all');
            window.history.replaceState({}, document.title, url.toString());
        }
    });
<?php endif; ?>

console.log('%c💊 Braick - Prescriptions (Grouped by Patient)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Medication Summary - search dawa inaonyesha total quantities', 'font-size:12px;color:#34D399;');
console.log('%c✅ < > scroll buttons kwenye kila table', 'font-size:12px;color:#34D399;');
console.log('%c✅ Highlight inatoweka unapofuta search', 'font-size:12px;color:#34D399;');
console.log('%c✅ Per-patient search + clear button', 'font-size:12px;color:#34D399;');
console.log('%c📊 Total medications in system: <?= count($medication_summary) ?>', 'font-size:12px;color:#0B5ED7;');
</script>

</body>
</html>