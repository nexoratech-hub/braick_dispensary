<?php
// ================================================================
// FILE: frontend/pages/admin/patient_details.php
// VIEW PATIENT - COMPLETE PATIENT DETAILS WITH PDF + EDIT/DELETE
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ✅ PDF Export → export_patient_pdf.php
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

$message = '';
$message_type = '';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php?error=invalid_patient');
    exit;
}

$patient = null;
$active_visit = null;
$visit_history = [];
$bills = [];
$bill_items = [];
$procedures = [];
$tools = [];
$latest_vitals = null;
$prescriptions = [];
$lab_tests = [];
$vital_signs = [];
$age = 'N/A';
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// HANDLE POST ACTIONS - DELETE
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // ============================================================
    // DELETE VISIT - INAFUTA KILA KITU CHA VISIT HIYO
    // ============================================================
    if ($action === 'delete_visit') {
        $visit_id = (int)($_POST['visit_id'] ?? 0);
        
        if ($visit_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT * FROM visits WHERE id = ? AND patient_id = ?");
                $stmt->execute([$visit_id, $patient_id]);
                $visit = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$visit) {
                    $db->rollBack();
                    $_SESSION['flash_message'] = "❌ Visit not found";
                    $_SESSION['flash_type'] = 'error';
                    header('Location: patient_details.php?id=' . $patient_id);
                    exit;
                }
                
                $deleted_summary = [];
                
                // RUDISHA STOCK KWA PRESCRIPTIONS
                $stmt = $db->prepare("
                    SELECT pi.*, mi.id as inv_id, mi.quantity as current_qty, mi.medication_name
                    FROM prescription_items pi
                    INNER JOIN prescriptions p ON pi.prescription_id = p.id
                    LEFT JOIN medications_inventory mi ON pi.medication_id = mi.id
                    WHERE p.visit_id = ? AND p.status = 'dispensed'
                ");
                $stmt->execute([$visit_id]);
                $dispensed_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $restored_count = 0;
                foreach ($dispensed_items as $item) {
                    if (!empty($item['inv_id']) && !empty($item['quantity'])) {
                        $stmt2 = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?");
                        $stmt2->execute([$item['quantity'], $item['inv_id']]);
                        $restored_count++;
                    }
                }
                if ($restored_count > 0) {
                    $deleted_summary[] = "$restored_count medication stock(s) restored";
                }
                
                // DELETE PRESCRIPTIONS
                $stmt = $db->prepare("DELETE pi FROM prescription_items pi INNER JOIN prescriptions p ON pi.prescription_id = p.id WHERE p.visit_id = ?");
                $stmt->execute([$visit_id]);
                
                $stmt = $db->prepare("DELETE FROM prescriptions WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
                if ($stmt->rowCount() > 0) {
                    $deleted_summary[] = $stmt->rowCount() . " prescription(s) deleted";
                }
                
                // DELETE LAB TESTS
                try {
                    $stmt = $db->prepare("DELETE FROM lab_tests WHERE visit_id = ?");
                    $stmt->execute([$visit_id]);
                    if ($stmt->rowCount() > 0) {
                        $deleted_summary[] = $stmt->rowCount() . " lab test(s) deleted";
                    }
                } catch (Exception $e) {}
                
                // DELETE BILLS
                $stmt = $db->prepare("SELECT id FROM bills WHERE visit_id = ?");
                $stmt->execute([$visit_id]);
                $visit_bills = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($visit_bills)) {
                    $placeholders = implode(',', array_fill(0, count($visit_bills), '?'));
                    
                    $stmt = $db->prepare("DELETE FROM payments WHERE bill_id IN ($placeholders)");
                    $stmt->execute($visit_bills);
                    $payments_deleted = $stmt->rowCount();
                    
                    $stmt = $db->prepare("DELETE FROM bill_items WHERE bill_id IN ($placeholders)");
                    $stmt->execute($visit_bills);
                    $items_deleted = $stmt->rowCount();
                    
                    $stmt = $db->prepare("DELETE FROM bills WHERE visit_id = ?");
                    $stmt->execute([$visit_id]);
                    $bills_deleted = $stmt->rowCount();
                    
                    if ($bills_deleted > 0) $deleted_summary[] = "$bills_deleted bill(s) deleted";
                    if ($items_deleted > 0) $deleted_summary[] = "$items_deleted bill item(s) deleted";
                    if ($payments_deleted > 0) $deleted_summary[] = "$payments_deleted payment(s) deleted";
                }
                
                // DELETE VITAL SIGNS
                try {
                    $stmt = $db->prepare("DELETE FROM vital_signs WHERE visit_id = ?");
                    $stmt->execute([$visit_id]);
                    if ($stmt->rowCount() > 0) {
                        $deleted_summary[] = $stmt->rowCount() . " vital sign(s) deleted";
                    }
                } catch (Exception $e) {}
                
                // DELETE VISIT
                $stmt = $db->prepare("DELETE FROM visits WHERE id = ? AND patient_id = ?");
                $stmt->execute([$visit_id, $patient_id]);
                
                // LOG ACTIVITY
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'visit_deleted', ?, NOW())");
                    $stmt->execute([$user_id, $branch_id, "Deleted visit ID: $visit_id for patient ID: $patient_id. " . implode(', ', $deleted_summary)]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $_SESSION['flash_message'] = "✅ <strong>Visit Deleted Successfully!</strong><br>" . 
                    (!empty($deleted_summary) ? implode('<br>', array_map(function($s) { return "• " . $s; }, $deleted_summary)) : "No additional records to delete.");
                $_SESSION['flash_type'] = 'success';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_message'] = "❌ Error deleting visit: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            }
        }
    }
    
    // DELETE PRESCRIPTION
    if ($action === 'delete_prescription') {
        $prescription_id = (int)($_POST['prescription_id'] ?? 0);
        
        if ($prescription_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("SELECT * FROM prescriptions WHERE id = ? AND patient_id = ?");
                $stmt->execute([$prescription_id, $patient_id]);
                $presc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($presc) {
                    if ($presc['status'] === 'dispensed') {
                        $stmt = $db->prepare("SELECT pi.*, mi.id as inv_id FROM prescription_items pi LEFT JOIN medications_inventory mi ON pi.medication_id = mi.id WHERE pi.prescription_id = ?");
                        $stmt->execute([$prescription_id]);
                        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($items as $item) {
                            if (!empty($item['inv_id']) && !empty($item['quantity'])) {
                                $stmt2 = $db->prepare("UPDATE medications_inventory SET quantity = quantity + ? WHERE id = ?");
                                $stmt2->execute([$item['quantity'], $item['inv_id']]);
                            }
                        }
                    }
                    
                    $stmt = $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?");
                    $stmt->execute([$prescription_id]);
                    
                    $stmt = $db->prepare("DELETE FROM prescriptions WHERE id = ?");
                    $stmt->execute([$prescription_id]);
                    
                    $db->commit();
                    $_SESSION['flash_message'] = "✅ Prescription deleted successfully!";
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $db->rollBack();
                    $_SESSION['flash_message'] = "❌ Prescription not found";
                    $_SESSION['flash_type'] = 'error';
                }
                
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            }
        }
    }
    
    // DELETE LAB TEST
    if ($action === 'delete_lab_test') {
        $test_id = (int)($_POST['test_id'] ?? 0);
        
        if ($test_id > 0) {
            try {
                $stmt = $db->prepare("DELETE FROM lab_tests WHERE id = ?");
                $stmt->execute([$test_id]);
                
                $_SESSION['flash_message'] = "✅ Lab test deleted successfully!";
                $_SESSION['flash_type'] = 'success';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            }
        }
    }
    
    // DELETE BILL
    if ($action === 'delete_bill') {
        $bill_id = (int)($_POST['bill_id'] ?? 0);
        
        if ($bill_id > 0) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("DELETE FROM payments WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                
                $stmt = $db->prepare("DELETE FROM bill_items WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                
                $stmt = $db->prepare("DELETE FROM bills WHERE id = ? AND patient_id = ?");
                $stmt->execute([$bill_id, $patient_id]);
                
                $db->commit();
                
                $_SESSION['flash_message'] = "✅ Bill deleted successfully!";
                $_SESSION['flash_type'] = 'success';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
                $_SESSION['flash_type'] = 'error';
                header('Location: patient_details.php?id=' . $patient_id);
                exit;
            }
        }
    }
}

// ================================================================
// CHECK FLASH MESSAGE
// ================================================================
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// ================================================================
// LOAD PATIENT DATA
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            u.full_name as created_by_name,
            b.name as branch_name,
            doc.full_name as assigned_doctor_name,
            doc.is_online as assigned_doctor_online
        FROM patients p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users doc ON p.assigned_doctor_id = doc.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: patients.php?error=patient_not_found');
        exit;
    }
    
    // Active visit
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as doctor_name, u.is_online as doctor_online
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ? AND v.status IN ('pending', 'assigned', 'with_doctor', 'lab_test')
        ORDER BY v.created_at DESC LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $active_visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Visit history
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as doctor_name, b.total_amount as bill_amount, b.status as bill_status, b.bill_number
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN bills b ON v.id = b.visit_id
        WHERE v.patient_id = ? ORDER BY v.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $visit_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Bills
    $stmt = $db->prepare("
        SELECT b.*, v.visit_number, u.full_name as created_by_name
        FROM bills b
        LEFT JOIN visits v ON b.visit_id = v.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.patient_id = ? ORDER BY b.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Bill items
    $bill_items = [];
    foreach ($bills as $bill) {
        if (!empty($bill['id'])) {
            $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY created_at DESC");
            $stmt->execute([$bill['id']]);
            $bill_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Procedures
    $stmt = $db->prepare("
        SELECT bi.*, b.patient_id, b.bill_number, b.created_at as bill_date
        FROM bill_items bi JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? AND bi.item_type = 'procedure'
        ORDER BY bi.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tools
    $stmt = $db->prepare("
        SELECT bi.*, b.patient_id, b.bill_number, b.created_at as bill_date
        FROM bill_items bi JOIN bills b ON bi.bill_id = b.id
        WHERE b.patient_id = ? AND bi.item_type = 'equipment'
        ORDER BY bi.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Latest vitals
    $stmt = $db->prepare("
        SELECT vs.*, u.full_name as recorded_by_name
        FROM vital_signs vs LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.patient_id = ? ORDER BY vs.recorded_at DESC LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $latest_vitals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Prescriptions
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as doctor_name
        FROM prescriptions p LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.patient_id = ? ORDER BY p.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lab tests
    $stmt = $db->prepare("
        SELECT lt.*, u.full_name as doctor_name
        FROM lab_tests lt LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE lt.visit_id IN (SELECT id FROM visits WHERE patient_id = ?)
        ORDER BY lt.created_at DESC LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Age
    if (!empty($patient['date_of_birth'])) {
        $birthDate = new DateTime($patient['date_of_birth']);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
    }
    
    $branch_name = $patient['branch_name'] ?? $branch_name;
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// Notifications
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - FULL DARK MODE -->
<!-- ================================================================ -->
<style>
    /* LIGHT MODE */
    :root {
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #F8FAFC;
    }

    /* DARK MODE */
    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-hover: #0F172A;
    }

    /* BODY & MAIN CONTENT */
    body { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* ================================================================
       BLUE PAGE HEADER
       ================================================================ */
    .page-header-custom {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
        border-radius: 18px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35), 0 4px 12px rgba(11, 94, 215, 0.2);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-custom .page-title {
        color: white;
        font-size: 1.6rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-custom .page-title i {
        width: 44px;
        height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .page-subtitle {
        color: rgba(255,255,255,0.9);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 8px;
    }

    .page-header-custom .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .btn-glass {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        background: rgba(255,255,255,0.18);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 10px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        white-space: nowrap;
        cursor: pointer;
    }

    .btn-glass:hover {
        background: rgba(255,255,255,0.3);
        transform: translateY(-2px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .btn-glass.pdf-btn {
        background: rgba(220,38,38,0.3);
        border-color: rgba(220,38,38,0.5);
    }

    .btn-glass.pdf-btn:hover {
        background: rgba(220,38,38,0.5);
    }

    /* ================================================================
       ALERT BOX
       ================================================================ */
    .alert-box {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-size: 0.9rem;
        font-weight: 500;
        border-left: 5px solid;
        animation: slideDown 0.4s ease;
    }

    .alert-box.success { background: #D1FAE5; color: #065F46; border-left-color: #059669; }
    .alert-box.error { background: #FEE2E2; color: #991B1B; border-left-color: #DC2626; }
    .alert-box.info { background: #E8F0FE; color: #0A4CA8; border-left-color: #0B5ED7; }

    html[data-theme="dark"] .alert-box.success { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .alert-box.error { background: #3A1A1A; color: #F87171; }
    html[data-theme="dark"] .alert-box.info { background: #1E3A5F; color: #6EA8FE; }

    .alert-box i { font-size: 1.3rem; flex-shrink: 0; margin-top: 2px; }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ================================================================
       PROFILE HEADER
       ================================================================ */
    .profile-header {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 18px;
        padding: 24px 28px;
        border: 2px solid #6EA8FE;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: center;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.15);
    }

    html[data-theme="dark"] .profile-header {
        background: #1E293B;
        border-color: #6EA8FE;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.2);
    }

    .profile-avatar {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.6rem;
        font-weight: 700;
        color: #ffffff;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .profile-avatar.avatar-male { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }
    .profile-avatar.avatar-female { background: linear-gradient(135deg, #DC2626, #EF4444); }
    .profile-avatar.avatar-other { background: linear-gradient(135deg, #7C3AED, #9B4DCA); }
    .profile-avatar.avatar-default { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }

    .profile-info { flex: 1; min-width: 200px; }
    
    .profile-info .patient-name {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .profile-info .patient-name { color: #F1F5F9; }

    .profile-info .patient-id {
        font-size: 0.8rem;
        font-family: monospace;
        color: var(--page-text-secondary, #64748B);
        background: var(--page-hover, #F8FAFC);
        padding: 3px 12px;
        border-radius: 12px;
        display: inline-block;
        margin-left: 8px;
        font-weight: 600;
    }

    html[data-theme="dark"] .profile-info .patient-id {
        background: #0F172A;
        color: #94A3B8;
    }

    .profile-info .patient-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 10px;
    }

    .profile-info .patient-meta .meta-item {
        font-size: 0.8rem;
        color: var(--page-text-secondary, #64748B);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .profile-info .patient-meta .meta-item i { color: #0B5ED7; width: 16px; }
    html[data-theme="dark"] .profile-info .patient-meta .meta-item i { color: #6EA8FE; }

    .profile-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.78rem;
        transition: all 0.25s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }
    .btn-primary-custom:hover {
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .btn-success-custom {
        background: #059669;
        color: white;
    }
    .btn-success-custom:hover {
        background: #047857;
        color: white;
        transform: translateY(-2px);
    }

    .btn-warning-custom {
        background: #F59E0B;
        color: white;
    }
    .btn-warning-custom:hover {
        background: #D97706;
        color: white;
        transform: translateY(-2px);
    }

    .btn-danger-custom {
        background: #DC2626;
        color: white;
    }
    .btn-danger-custom:hover {
        background: #B91C1C;
        color: white;
        transform: translateY(-2px);
    }

    .btn-outline-custom {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-custom:hover {
        background: var(--page-hover, #F1F5F9);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }

    .btn-xs-custom {
        padding: 5px 10px;
        font-size: 0.68rem;
        border-radius: 6px;
    }

    .action-buttons {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
    }

    /* ================================================================
       DETAIL CARDS
       ================================================================ */
    .detail-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 22px 26px;
        border: 2px solid var(--page-border, #E2E8F0);
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: all 0.3s ease;
    }

    html[data-theme="dark"] .detail-card {
        background: #1E293B;
        border-color: #334155;
    }

    .detail-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.08);
    }

    .detail-card .card-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        padding-bottom: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    html[data-theme="dark"] .detail-card .card-title {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .detail-card .card-title i { color: #0B5ED7; }
    html[data-theme="dark"] .detail-card .card-title i { color: #6EA8FE; }

    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px 24px;
    }

    .detail-grid .detail-item {
        display: flex;
        flex-direction: column;
        padding: 8px 0;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .detail-grid .detail-item {
        border-bottom-color: #334155;
    }

    .detail-grid .detail-item .detail-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .detail-grid .detail-item .detail-value {
        font-size: 0.88rem;
        font-weight: 500;
        color: var(--page-text-primary, #1E293B);
        margin-top: 2px;
    }

    html[data-theme="dark"] .detail-grid .detail-item .detail-value { color: #F1F5F9; }

    /* ================================================================
       VITALS
       ================================================================ */
    .vital-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }

    .vital-grid.second-row { margin-top: 12px; }

    .vital-item {
        background: #E8F0FE;
        border-radius: 10px;
        padding: 12px 14px;
        border-left: 4px solid #0B5ED7;
        text-align: center;
        transition: all 0.3s ease;
    }

    html[data-theme="dark"] .vital-item {
        background: #1E3A5F;
    }

    .vital-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(11, 94, 215, 0.15);
    }

    .vital-item .vital-label {
        font-size: 0.6rem;
        font-weight: 700;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: block;
    }

    .vital-item .vital-value {
        font-size: 1.1rem;
        font-weight: 800;
        color: #0A4CA8;
    }

    html[data-theme="dark"] .vital-item .vital-value { color: #6EA8FE; }

    .vital-item .vital-unit {
        font-size: 0.6rem;
        font-weight: 500;
        color: var(--page-text-secondary, #64748B);
    }

    .vital-item.green { border-left-color: #059669; }
    .vital-item.green .vital-value { color: #059669; }
    .vital-item.purple { border-left-color: #7C3AED; }
    .vital-item.purple .vital-value { color: #7C3AED; }
    .vital-item.orange { border-left-color: #F59E0B; }
    .vital-item.orange .vital-value { color: #F59E0B; }
    .vital-item.teal { border-left-color: #0D9488; }
    .vital-item.teal .vital-value { color: #0D9488; }
    .vital-item.red { border-left-color: #DC2626; }
    .vital-item.red .vital-value { color: #DC2626; }
    .vital-item.cyan { border-left-color: #0891B2; }
    .vital-item.cyan .vital-value { color: #0891B2; }

    /* ================================================================
       BADGES
       ================================================================ */
    .badge-custom {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.62rem;
        font-weight: 700;
        padding: 3px 12px;
        border-radius: 12px;
    }

    .badge-success { background: #D1FAE5; color: #059669; }
    .badge-danger { background: #FEE2E2; color: #DC2626; }
    .badge-warning { background: #FEF3C7; color: #D97706; }
    .badge-info { background: #E8F0FE; color: #0B5ED7; }
    .badge-purple { background: #EDE9FE; color: #7C3AED; }
    .badge-secondary { background: #E2E8F0; color: #64748B; }

    html[data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; }
    html[data-theme="dark"] .badge-warning { background: #3A2A1A; color: #FBBF24; }
    html[data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .badge-purple { background: #2D1B4E; color: #A78BFA; }
    html[data-theme="dark"] .badge-secondary { background: #334155; color: #94A3B8; }

    /* ================================================================
       TABLE
       ================================================================ */
    .table-wrapper {
        overflow-x: auto;
        border-radius: 10px;
    }

    .data-table-custom {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.78rem;
    }

    .data-table-custom thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        color: white;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        white-space: nowrap;
    }

    .data-table-custom thead th:first-child { border-radius: 10px 0 0 0; }
    .data-table-custom thead th:last-child { border-radius: 0 10px 0 0; }

    .data-table-custom tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    html[data-theme="dark"] .data-table-custom tbody td {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .data-table-custom tbody tr:nth-child(even) td {
        background: var(--page-hover, #F8FAFC);
    }

    html[data-theme="dark"] .data-table-custom tbody tr:nth-child(even) td {
        background: #1E3A5F;
    }

    .data-table-custom tbody tr:hover td {
        background: #E8F0FE;
    }

    html[data-theme="dark"] .data-table-custom tbody tr:hover td {
        background: #1E40AF;
    }

    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 30px;
        color: var(--page-text-muted, #94A3B8);
    }

    .empty-state i {
        font-size: 2.5rem;
        color: var(--page-text-muted, #94A3B8);
        display: block;
        margin-bottom: 8px;
        opacity: 0.5;
    }

    /* ================================================================
       MODALS
       ================================================================ */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.7);
        z-index: 9999;
        backdrop-filter: blur(4px);
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .modal-overlay.active { display: flex; }

    .modal-content {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        max-width: 550px;
        width: 100%;
        padding: 24px 28px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        animation: slideUp 0.3s ease;
    }

    html[data-theme="dark"] .modal-content {
        background: #1E293B;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }

    .modal-header .modal-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #DC2626;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--page-text-secondary, #64748B);
    }

    .modal-close:hover { color: #DC2626; }

    .warning-box {
        background: #FEE2E2;
        border: 2px solid #DC2626;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 16px;
        display: flex;
        gap: 12px;
    }

    html[data-theme="dark"] .warning-box {
        background: #3A1A1A;
        border-color: #7F1D1D;
    }

    .warning-box i {
        color: #DC2626;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .warning-box .warning-text {
        font-size: 0.82rem;
        color: #991B1B;
        line-height: 1.5;
    }

    html[data-theme="dark"] .warning-box .warning-text { color: #F87171; }

    .warning-box .warning-text strong {
        display: block;
        font-size: 0.95rem;
        margin-bottom: 4px;
    }

    .delete-list {
        background: var(--page-hover, #F8FAFC);
        border: 1px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 16px;
        font-size: 0.8rem;
    }

    html[data-theme="dark"] .delete-list { background: #0F172A; }

    .delete-list .list-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 0;
        color: var(--page-text-primary, #1E293B);
    }

    html[data-theme="dark"] .delete-list .list-item { color: #F1F5F9; }

    .delete-list .list-item i {
        color: #DC2626;
        font-size: 0.7rem;
        width: 16px;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding-top: 16px;
        border-top: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-confirm-delete {
        background: #DC2626;
        color: white;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
    }

    .btn-confirm-delete:hover {
        background: #B91C1C;
    }

    .btn-confirm-delete:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-cancel-modal {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
    }

    .btn-cancel-modal:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .vital-grid { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 768px) {
        .page-header-custom { padding: 18px; }
        .page-header-custom .page-title { font-size: 1.15rem; }
        .detail-grid { grid-template-columns: 1fr; }
        .profile-header { flex-direction: column; text-align: center; padding: 18px; }
        .profile-info .patient-meta { justify-content: center; }
        .profile-actions { justify-content: center; width: 100%; }
        .vital-grid { grid-template-columns: repeat(2, 1fr); }
        .action-buttons { flex-direction: column; }
        .action-buttons .btn-xs-custom { width: 100%; }
    }

    @media (max-width: 640px) {
        .detail-card { padding: 16px; }
        .vital-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media print {
        .btn-glass, .action-buttons, .profile-actions { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($patient): ?>
    
    <!-- FLASH MESSAGE -->
    <?php if ($message): ?>
        <div class="alert-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : ($message_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-circle')) ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>
    
    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i>
                Patient Details
                <span class="header-badge"><?= strtoupper($role) ?></span>
            </h1>
            <p class="page-subtitle">
                <span><i class="fas fa-id-card"></i> <strong><?= htmlspecialchars($patient['full_name']) ?></strong></span>
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($patient['patient_id']) ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="patients.php" class="btn-glass">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <!-- ✅ PDF EXPORT BUTTON - Inaelekeza kwenye export_patient_pdf.php -->
            <a href="export_patient_pdf.php?id=<?= $patient['id'] ?>&branch=<?= $branch_id ?>" 
               target="_blank" 
               class="btn-glass pdf-btn">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>
    </div>
    
    <!-- PROFILE HEADER -->
    <div class="profile-header">
        <?php
            $gender = $patient['gender'] ?? '';
            $avatar_class = 'avatar-default';
            if ($gender === 'Male') $avatar_class = 'avatar-male';
            elseif ($gender === 'Female') $avatar_class = 'avatar-female';
            elseif ($gender === 'Other') $avatar_class = 'avatar-other';
        ?>
        <div class="profile-avatar <?= $avatar_class ?>">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        
        <div class="profile-info">
            <div>
                <span class="patient-name"><?= htmlspecialchars($patient['full_name']) ?></span>
                <span class="patient-id"><?= htmlspecialchars($patient['patient_id']) ?></span>
                <?php if (!empty($patient['assigned_doctor_name'])): ?>
                    <span class="badge-custom badge-info" style="margin-left:8px;">
                        <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['assigned_doctor_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="patient-meta">
                <span class="meta-item"><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-calendar"></i> <?= !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
                <span class="meta-item"><i class="fas fa-clock"></i> <?= $age ?> years</span>
                <span class="meta-item"><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span class="meta-item"><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
        </div>
        
        <div class="profile-actions">
            <a href="assign_doctor.php?patient_id=<?= $patient['id'] ?>" class="btn-custom btn-primary-custom btn-xs-custom">
                <i class="fas fa-user-md"></i> Assign Doctor
            </a>
            <a href="edit_patient.php?id=<?= $patient['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom">
                <i class="fas fa-edit"></i> Edit Patient
            </a>
            <button onclick="window.print()" class="btn-custom btn-outline-custom btn-xs-custom">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <!-- 1. PERSONAL INFO -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-user"></i>
            Personal Information
            <a href="edit_patient.php?id=<?= $patient['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom" style="margin-left:auto;">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>
        
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Full Name</span>
                <span class="detail-value"><?= htmlspecialchars($patient['full_name']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Patient ID</span>
                <span class="detail-value"><?= htmlspecialchars($patient['patient_id']) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Gender</span>
                <span class="detail-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Date of Birth</span>
                <span class="detail-value"><?= !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?> (<?= $age ?> yrs)</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Blood Group</span>
                <span class="detail-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Marital Status</span>
                <span class="detail-value"><?= htmlspecialchars($patient['marital_status'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-item" style="grid-column:1/-1;">
                <span class="detail-label">Address</span>
                <span class="detail-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>
    
    <!-- 2. LATEST VITALS -->
    <?php if ($latest_vitals): 
        $spo2_value = !empty($latest_vitals['oxygen_saturation']) ? (int)$latest_vitals['oxygen_saturation'] : null;
    ?>
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-heartbeat" style="color:#DC2626;"></i>
            Latest Vital Signs
            <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);margin-left:auto;">
                <?= date('d M Y h:i A', strtotime($latest_vitals['recorded_at'])) ?>
            </span>
        </div>
        
        <div class="vital-grid">
            <div class="vital-item">
                <span class="vital-label">🌡️ Temp</span>
                <span class="vital-value"><?= $latest_vitals['temperature'] ?? 'N/A' ?> <span class="vital-unit">°C</span></span>
            </div>
            <div class="vital-item green">
                <span class="vital-label">❤️ Blood Pressure</span>
                <span class="vital-value">
                    <?php if (!empty($latest_vitals['blood_pressure_systolic']) && !empty($latest_vitals['blood_pressure_diastolic'])): ?>
                        <?= $latest_vitals['blood_pressure_systolic'] ?>/<?= $latest_vitals['blood_pressure_diastolic'] ?>
                        <span class="vital-unit">mmHg</span>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </span>
            </div>
            <div class="vital-item purple">
                <span class="vital-label">💓 Pulse</span>
                <span class="vital-value"><?= $latest_vitals['pulse_rate'] ?? 'N/A' ?> <span class="vital-unit">bpm</span></span>
            </div>
            <div class="vital-item orange">
                <span class="vital-label">⚖️ Weight</span>
                <span class="vital-value"><?= $latest_vitals['weight'] ?? 'N/A' ?> <span class="vital-unit">kg</span></span>
            </div>
        </div>
        
        <div class="vital-grid second-row">
            <div class="vital-item teal">
                <span class="vital-label">📏 Height</span>
                <span class="vital-value"><?= $latest_vitals['height'] ?? 'N/A' ?> <span class="vital-unit">cm</span></span>
            </div>
            <div class="vital-item red">
                <span class="vital-label">📊 BMI</span>
                <span class="vital-value"><?= $latest_vitals['bmi'] ?? 'N/A' ?> <span class="vital-unit">kg/m²</span></span>
            </div>
            <div class="vital-item cyan">
                <span class="vital-label">🫁 O₂ Saturation</span>
                <span class="vital-value"><?= $spo2_value !== null ? $spo2_value : 'N/A' ?> <span class="vital-unit">%</span></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 3. VISIT HISTORY -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-clock"></i>
            Visit History
            <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10 visits)</span>
            <a href="new_visit.php?patient_id=<?= $patient['id'] ?>" class="btn-custom btn-success-custom btn-xs-custom" style="margin-left:auto;">
                <i class="fas fa-plus"></i> New Visit
            </a>
        </div>
        
        <?php if (count($visit_history) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Visit #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Bill</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visit_history as $visit): ?>
                            <tr>
                                <td style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($visit['created_at'])) ?></td>
                                <td><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-custom badge-<?= $visit['status'] ?? 'secondary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($visit['bill_amount'])): ?>
                                        <strong>TSh <?= number_format($visit['bill_amount'], 0) ?></strong>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-muted);">No bill</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn-custom btn-primary-custom btn-xs-custom">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit_visit.php?id=<?= $visit['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" class="btn-custom btn-danger-custom btn-xs-custom" 
                                                onclick="confirmDeleteVisit(<?= $visit['id'] ?>, '<?= addslashes($visit['visit_number']) ?>', '<?= addslashes($visit['doctor_name'] ?? 'N/A') ?>', '<?= date('d M Y', strtotime($visit['created_at'])) ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clock"></i>
                <p>No visit history found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 4. BILLS -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-receipt"></i>
            Bills <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (count($bills) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Visit</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($bill['created_at'])) ?></td>
                                <td><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></td>
                                <td><strong>TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></strong></td>
                                <td style="color:#059669;">TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                                <td style="color:<?= ($bill['balance'] ?? 0) > 0 ? '#DC2626' : '#059669' ?>;">
                                    TSh <?= number_format($bill['balance'] ?? 0, 0) ?>
                                </td>
                                <td>
                                    <span class="badge-custom badge-<?= $bill['status'] ?? 'secondary' ?>">
                                        <?= ucfirst($bill['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn-custom btn-primary-custom btn-xs-custom">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_bill.php?id=<?= $bill['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-custom btn-danger-custom btn-xs-custom"
                                                onclick="confirmDeleteBill(<?= $bill['id'] ?>, '<?= addslashes($bill['bill_number']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No bills found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 5. LAB TESTS -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-flask"></i>
            Lab Tests <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Test Name</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Results</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                                <td><?= date('d M Y', strtotime($test['created_at'])) ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-custom badge-<?= $test['status'] ?? 'secondary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($test['results'])): ?>
                                        <span style="color:#059669;font-weight:700;">✅ Available</span>
                                    <?php else: ?>
                                        <span style="color:var(--page-text-secondary);">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="lab_test_details.php?id=<?= $test['id'] ?>&branch=<?= $branch_id ?>" class="btn-custom btn-primary-custom btn-xs-custom">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../laboratory/edit_test.php?id=<?= $test['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-custom btn-danger-custom btn-xs-custom"
                                                onclick="confirmDeleteLabTest(<?= $test['id'] ?>, '<?= addslashes($test['test_name']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <p>No lab tests found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 6. PRESCRIPTIONS -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-prescription"></i>
            Prescriptions <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (count($prescriptions) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Prescription #</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($prescription['created_at'])) ?></td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-custom badge-<?= $prescription['status'] ?? 'secondary' ?>">
                                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="../pharmacy/view_prescription.php?id=<?= $prescription['id'] ?>" class="btn-custom btn-primary-custom btn-xs-custom">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../pharmacy/edit_prescription.php?id=<?= $prescription['id'] ?>" class="btn-custom btn-warning-custom btn-xs-custom">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn-custom btn-danger-custom btn-xs-custom"
                                                onclick="confirmDeletePrescription(<?= $prescription['id'] ?>, '<?= addslashes($prescription['prescription_number']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescriptions found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 7. PROCEDURES -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-syringe" style="color:#7C3AED;"></i>
            Procedures <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (count($procedures) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Procedure</th>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Bill #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $procedure): ?>
                            <tr>
                                <td><?= htmlspecialchars($procedure['item_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($procedure['created_at'])) ?></td>
                                <td><?= $procedure['quantity'] ?? 1 ?></td>
                                <td><strong>TSh <?= number_format($procedure['total_price'] ?? 0, 0) ?></strong></td>
                                <td style="font-family:monospace;"><?= htmlspecialchars($procedure['bill_number'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-syringe"></i>
                <p>No procedures found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 8. TOOLS -->
    <div class="detail-card">
        <div class="card-title">
            <i class="fas fa-tools" style="color:#D97706;"></i>
            Tools / Equipment <span style="font-size:0.75rem;font-weight:400;color:var(--page-text-secondary);">(Last 10)</span>
        </div>
        
        <?php if (count($tools) > 0): ?>
            <div class="table-wrapper">
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>Tool Name</th>
                            <th>Date</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Bill #</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tools as $tool): ?>
                            <tr>
                                <td><?= htmlspecialchars($tool['item_name'] ?? 'N/A') ?></td>
                                <td><?= date('d M Y', strtotime($tool['created_at'])) ?></td>
                                <td><?= $tool['quantity'] ?? 1 ?></td>
                                <td><strong>TSh <?= number_format($tool['total_price'] ?? 0, 0) ?></strong></td>
                                <td style="font-family:monospace;"><?= htmlspecialchars($tool['bill_number'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-tools"></i>
                <p>No tools found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    
    <div class="detail-card">
        <div class="empty-state">
            <i class="fas fa-user-slash" style="font-size:3rem;"></i>
            <h3 style="font-size:1.2rem;margin:12px 0;">Patient Not Found</h3>
            <a href="patients.php" class="btn-custom btn-primary-custom" style="margin-top:12px;">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>
    
    <?php endif; ?>

</main>

<!-- ============================================================ -->
<!-- DELETE VISIT MODAL -->
<!-- ============================================================ -->
<div class="modal-overlay" id="deleteVisitModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-exclamation-triangle"></i>
                Delete Visit
            </div>
            <button class="modal-close" onclick="closeDeleteVisitModal()">&times;</button>
        </div>
        
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="warning-text">
                <strong>⚠️ ONYO: HATUA HII HAIWEZI KURUDISHWA!</strong>
                Ukifuta visit hii, KILA KITU kinachohusiana nayo kitafutwa kabisa.
            </div>
        </div>
        
        <div style="background:#E8F0FE;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
            <div style="font-size:0.75rem;font-weight:700;color:#0B5ED7;margin-bottom:6px;">
                <i class="fas fa-info-circle"></i> Visit Details:
            </div>
            <div style="font-size:0.85rem;">
                <div style="margin-bottom:4px;"><strong>Visit #:</strong> <span id="delVisitNumber" style="font-family:monospace;"></span></div>
                <div style="margin-bottom:4px;"><strong>Doctor:</strong> Dr. <span id="delVisitDoctor"></span></div>
                <div><strong>Date:</strong> <span id="delVisitDate"></span></div>
            </div>
        </div>
        
        <div class="delete-list">
            <div style="font-weight:700;font-size:0.85rem;color:#DC2626;margin-bottom:8px;">
                <i class="fas fa-trash"></i> KITAKACHOFUTWA:
            </div>
            <div class="list-item"><i class="fas fa-times-circle"></i> Visit record yenyewe</div>
            <div class="list-item"><i class="fas fa-times-circle"></i> Bills zote (+ items + payments)</div>
            <div class="list-item"><i class="fas fa-times-circle"></i> Prescriptions zote (+ items)</div>
            <div class="list-item"><i class="fas fa-times-circle"></i> Lab tests zote</div>
            <div class="list-item"><i class="fas fa-times-circle"></i> Vital signs</div>
            <div class="list-item" style="color:#059669;font-weight:700;">
                <i class="fas fa-check-circle"></i> <strong>Stock ya dawa ITARUDI</strong>
            </div>
        </div>
        
        <div style="background:#FEF3C7;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.78rem;color:#92400E;font-weight:600;">
            <i class="fas fa-keyboard"></i> Andika <strong>"DELETE"</strong> ili kuthibitisha:
        </div>
        
        <input type="text" id="deleteConfirmInput" placeholder="Andika DELETE hapa..." 
               style="width:100%;padding:12px 16px;border:2px solid var(--page-border);border-radius:8px;font-size:0.95rem;font-weight:700;text-transform:uppercase;font-family:monospace;background:var(--page-bg-card);color:var(--page-text-primary);"
               oninput="checkDeleteConfirm()">
        
        <form method="POST" id="deleteVisitForm">
            <input type="hidden" name="action" value="delete_visit">
            <input type="hidden" name="visit_id" id="deleteVisitId" value="">
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeDeleteVisitModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-confirm-delete" id="confirmDeleteBtn" disabled>
                    <i class="fas fa-trash"></i> YES, DELETE EVERYTHING
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- GENERIC DELETE MODAL -->
<!-- ============================================================ -->
<div class="modal-overlay" id="deleteGenericModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title" id="genericModalTitle">
                <i class="fas fa-trash"></i> Delete Record
            </div>
            <button class="modal-close" onclick="closeGenericModal()">&times;</button>
        </div>
        
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="warning-text">
                <strong>⚠️ Onyo!</strong>
                <span id="genericWarningText">Una uhakika unataka kufuta record hii?</span>
            </div>
        </div>
        
        <div style="background:#E8F0FE;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
            <div style="font-size:0.75rem;font-weight:700;color:#0B5ED7;margin-bottom:4px;">
                <i class="fas fa-info-circle"></i> Record Details:
            </div>
            <div style="font-size:0.9rem;font-weight:700;" id="genericRecordName"></div>
        </div>
        
        <form method="POST" id="genericDeleteForm">
            <input type="hidden" name="action" id="genericAction" value="">
            <input type="hidden" name="bill_id" id="genericBillId" value="">
            <input type="hidden" name="prescription_id" id="genericPrescriptionId" value="">
            <input type="hidden" name="test_id" id="genericTestId" value="">
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeGenericModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-confirm-delete">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ============================================================
    // DELETE VISIT MODAL
    // ============================================================
    function confirmDeleteVisit(visitId, visitNumber, doctorName, visitDate) {
        document.getElementById('deleteVisitId').value = visitId;
        document.getElementById('delVisitNumber').textContent = visitNumber;
        document.getElementById('delVisitDoctor').textContent = doctorName;
        document.getElementById('delVisitDate').textContent = visitDate;
        document.getElementById('deleteConfirmInput').value = '';
        document.getElementById('confirmDeleteBtn').disabled = true;
        document.getElementById('deleteVisitModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeDeleteVisitModal() {
        document.getElementById('deleteVisitModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    
    function checkDeleteConfirm() {
        var input = document.getElementById('deleteConfirmInput').value.trim().toUpperCase();
        var btn = document.getElementById('confirmDeleteBtn');
        btn.disabled = (input !== 'DELETE');
    }
    
    // ============================================================
    // GENERIC DELETE MODAL
    // ============================================================
    function confirmDeleteBill(billId, billNumber) {
        document.getElementById('genericModalTitle').innerHTML = '<i class="fas fa-trash"></i> Delete Bill';
        document.getElementById('genericAction').value = 'delete_bill';
        document.getElementById('genericBillId').value = billId;
        document.getElementById('genericPrescriptionId').value = '';
        document.getElementById('genericTestId').value = '';
        document.getElementById('genericRecordName').textContent = 'Bill #: ' + billNumber;
        document.getElementById('genericWarningText').textContent = 'Bill hii itafutwa pamoja na items na payments zake zote.';
        document.getElementById('deleteGenericModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function confirmDeletePrescription(prescriptionId, prescNumber) {
        document.getElementById('genericModalTitle').innerHTML = '<i class="fas fa-trash"></i> Delete Prescription';
        document.getElementById('genericAction').value = 'delete_prescription';
        document.getElementById('genericBillId').value = '';
        document.getElementById('genericPrescriptionId').value = prescriptionId;
        document.getElementById('genericTestId').value = '';
        document.getElementById('genericRecordName').textContent = 'Prescription #: ' + prescNumber;
        document.getElementById('genericWarningText').innerHTML = 'Prescription hii itafutwa. <strong style="color:#059669;">Stock ya dawa ITARUDI</strong>.';
        document.getElementById('deleteGenericModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function confirmDeleteLabTest(testId, testName) {
        document.getElementById('genericModalTitle').innerHTML = '<i class="fas fa-trash"></i> Delete Lab Test';
        document.getElementById('genericAction').value = 'delete_lab_test';
        document.getElementById('genericBillId').value = '';
        document.getElementById('genericPrescriptionId').value = '';
        document.getElementById('genericTestId').value = testId;
        document.getElementById('genericRecordName').textContent = 'Test: ' + testName;
        document.getElementById('genericWarningText').textContent = 'Lab test hii itafutwa pamoja na results zake zote.';
        document.getElementById('deleteGenericModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeGenericModal() {
        document.getElementById('deleteGenericModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // ============================================================
    // ESCAPE KEY
    // ============================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteVisitModal();
            closeGenericModal();
        }
    });
    
    // ============================================================
    // CLICK OUTSIDE MODAL
    // ============================================================
    document.getElementById('deleteVisitModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeDeleteVisitModal();
    });
    
    document.getElementById('deleteGenericModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeGenericModal();
    });

    // ============================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ============================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');
        
        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F1F5F9';
            if (mainContent) mainContent.style.background = '#F1F5F9';
        }
    }

    enforceDarkModeBackground();

    document.addEventListener('darkModeChanged', function(e) {
        setTimeout(enforceDarkModeBackground, 50);
    });

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'data-theme') {
                enforceDarkModeBackground();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });
    
    console.log('%c👤 Braick - Patient Details', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px;color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px;color:#7C3AED;');
    console.log('%c📄 PDF Export → export_patient_pdf.php', 'font-size:13px;color:#DC2626;');
    console.log('%c📋 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:13px;color:#059669;');
</script>

</body>
</html>