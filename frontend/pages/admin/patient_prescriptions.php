<?php
// ================================================================
// FILE: frontend/pages/admin/patient_prescriptions.php
// ADMIN - PATIENT PRESCRIPTIONS (VIEW ALL + SMART DELETE)
// ✅ Group by Visit
// ✅ Pending → Edit + Cancel + Delete
// ✅ Confirmed/Dispensed → Delete only
// ✅ DELETE LOGIC:
//    - Pending/Confirmed/Cancelled → Return stock + Delete bill
//    - Dispensed → DO NOT return stock, DO NOT delete bill
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
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

// ================================================================
// GET PARAMETERS
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($patient_id <= 0) {
    header('Location: prescriptions.php');
    exit;
}

// ================================================================
// ✅ HANDLE DELETE PRESCRIPTION (SMART LOGIC)
// ================================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $prescription_id = (int)$_GET['delete'];
    
    try {
        $db->beginTransaction();
        
        // ============================================
        // 1. GET PRESCRIPTION INFO
        // ============================================
        $stmt = $db->prepare("
            SELECT p.id, p.prescription_number, p.status, p.patient_id, p.visit_id, p.branch_id
            FROM prescriptions p 
            WHERE p.id = ? AND p.patient_id = ?
        ");
        $stmt->execute([$prescription_id, $patient_id]);
        $presc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$presc) {
            $db->rollBack();
            header('Location: patient_prescriptions.php?patient_id=' . $patient_id . '&branch=' . urlencode($selected_branch_id) . '&error=not_found');
            exit;
        }
        
        $presc_status = $presc['status'];
        $presc_number = $presc['prescription_number'];
        $presc_branch = $presc['branch_id'];
        
        // ============================================
        // 2. GET PRESCRIPTION ITEMS (for stock return)
        // ============================================
        $stmt = $db->prepare("
            SELECT id, medication_name, quantity, dispensed_at
            FROM prescription_items 
            WHERE prescription_id = ?
        ");
        $stmt->execute([$prescription_id]);
        $presc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ============================================
        // 3. DETERMINE LOGIC BASED ON STATUS
        // ============================================
        // - pending / confirmed / cancelled → RETURN STOCK + DELETE BILL
        // - dispensed → NO STOCK RETURN + NO BILL DELETE
        // ============================================
        $should_return_stock = in_array($presc_status, ['pending', 'confirmed', 'cancelled']);
        $should_delete_bill = in_array($presc_status, ['pending', 'confirmed', 'cancelled']);
        
        $stock_returned_count = 0;
        $bills_deleted_count = 0;
        
        // ============================================
        // 4. RETURN STOCK (kwa pending/confirmed/cancelled pekee)
        // ============================================
        if ($should_return_stock && !empty($presc_items)) {
            foreach ($presc_items as $item) {
                $med_name = $item['medication_name'];
                $qty_to_return = (int)$item['quantity'];
                
                if ($qty_to_return <= 0 || empty($med_name)) continue;
                
                // Tafuta batches za dawa hii kwenye inventory ya branch hii
                $stmt_stock = $db->prepare("
                    SELECT id, quantity, batch_number
                    FROM medications_inventory 
                    WHERE medication_name = ? 
                      AND branch_id = ? 
                      AND status = 'active'
                    ORDER BY expiry_date ASC
                    LIMIT 1
                ");
                $stmt_stock->execute([$med_name, $presc_branch]);
                $stock_item = $stmt_stock->fetch(PDO::FETCH_ASSOC);
                
                if ($stock_item) {
                    $old_qty = (int)$stock_item['quantity'];
                    $new_qty = $old_qty + $qty_to_return;
                    
                    // Update stock
                    $db->prepare("
                        UPDATE medications_inventory 
                        SET quantity = ?, updated_at = NOW() 
                        WHERE id = ?
                    ")->execute([$new_qty, $stock_item['id']]);
                    
                    // Log stock movement
                    try {
                        $db->prepare("
                            INSERT INTO stock_movements (
                                inventory_id, patient_id, movement_type, quantity,
                                previous_stock, new_stock, reference_type, reference_id,
                                performed_by, branch_id, notes, created_at
                            ) VALUES (?, ?, 'in', ?, ?, ?, 'prescription_delete', ?, ?, ?, ?, NOW())
                        ")->execute([
                            $stock_item['id'],
                            $patient_id,
                            $qty_to_return,
                            $old_qty,
                            $new_qty,
                            $prescription_id,
                            $user_id,
                            $presc_branch,
                            "Stock returned - Deleted {$presc_status} prescription #{$presc_number}"
                        ]);
                    } catch (Exception $e) {
                        // Log table might not exist, continue
                    }
                    
                    $stock_returned_count++;
                }
            }
        }
        
        // ============================================
        // 5. DELETE BILL_ITEMS & BILL (kwa pending/confirmed/cancelled pekee)
        // ============================================
        if ($should_delete_bill) {
            // Tafuta bills zinazohusiana na prescription hii
            $stmt_bills = $db->prepare("
                SELECT DISTINCT b.id as bill_id, b.bill_number
                FROM bills b
                INNER JOIN bill_items bi ON b.id = bi.bill_id
                WHERE bi.reference_id = ? 
                  AND bi.reference_type = 'prescription'
            ");
            $stmt_bills->execute([$prescription_id]);
            $related_bills = $stmt_bills->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($related_bills as $bill) {
                $bill_id = $bill['bill_id'];
                
                // Futa bill_items za prescription hii pekee
                $db->prepare("
                    DELETE FROM bill_items 
                    WHERE bill_id = ? 
                      AND reference_id = ? 
                      AND reference_type = 'prescription'
                ")->execute([$bill_id, $prescription_id]);
                
                // Angalia kama bill haina bill_items zingine
                $stmt_check = $db->prepare("SELECT COUNT(*) as remaining FROM bill_items WHERE bill_id = ?");
                $stmt_check->execute([$bill_id]);
                $remaining = (int)($stmt_check->fetch(PDO::FETCH_ASSOC)['remaining'] ?? 0);
                
                // Kama bill haina items zingine, futa bill yote
                if ($remaining === 0) {
                    $db->prepare("DELETE FROM bills WHERE id = ?")->execute([$bill_id]);
                    $bills_deleted_count++;
                }
            }
        }
        
        // ============================================
        // 6. DELETE PRESCRIPTION_ITEMS
        // ============================================
        $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?")->execute([$prescription_id]);
        
        // ============================================
        // 7. DELETE PRESCRIPTION
        // ============================================
        $db->prepare("DELETE FROM prescriptions WHERE id = ? AND patient_id = ?")->execute([$prescription_id, $patient_id]);
        
        // ============================================
        // 8. LOG ACTIVITY
        // ============================================
        try {
            $log_desc = "Deleted prescription: {$presc_number} (Status: {$presc_status})";
            if ($should_return_stock && $stock_returned_count > 0) {
                $log_desc .= " - Stock returned for {$stock_returned_count} item(s)";
            }
            if ($should_delete_bill && $bills_deleted_count > 0) {
                $log_desc .= " - {$bills_deleted_count} bill(s) deleted";
            }
            if (!$should_return_stock) {
                $log_desc .= " - Stock NOT returned (dispensed)";
            }
            if (!$should_delete_bill) {
                $log_desc .= " - Bill NOT deleted (dispensed)";
            }
            
            $db->prepare("
                INSERT INTO activity_logs (user_id, action, description, branch_id, ip_address, created_at) 
                VALUES (?, 'DELETE_PRESCRIPTION', ?, ?, ?, NOW())
            ")->execute([
                $user_id,
                $log_desc,
                $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {}
        
        // ============================================
        // 9. COMMIT
        // ============================================
        $db->commit();
        
        // Redirect with success message
        $msg = 'deleted=1';
        if ($should_return_stock && $stock_returned_count > 0) {
            $msg .= '&stock_returned=' . $stock_returned_count;
        }
        if ($should_delete_bill && $bills_deleted_count > 0) {
            $msg .= '&bills_deleted=' . $bills_deleted_count;
        }
        if (!$should_return_stock) {
            $msg .= '&dispensed_kept=1';
        }
        
        header('Location: patient_prescriptions.php?patient_id=' . $patient_id . '&branch=' . urlencode($selected_branch_id) . '&' . $msg);
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("Delete prescription error: " . $e->getMessage());
        header('Location: patient_prescriptions.php?patient_id=' . $patient_id . '&branch=' . urlencode($selected_branch_id) . '&error=delete_failed');
        exit;
    }
}

// ================================================================
// HANDLE CANCEL PRESCRIPTION
// ================================================================
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $prescription_id = (int)$_GET['cancel'];
    try {
        $stmt = $db->prepare("SELECT prescription_number, status FROM prescriptions WHERE id = ? AND patient_id = ?");
        $stmt->execute([$prescription_id, $patient_id]);
        $presc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($presc && $presc['status'] === 'pending') {
            $db->prepare("UPDATE prescriptions SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND patient_id = ?")
               ->execute([$prescription_id, $patient_id]);
            
            try {
                $db->prepare("INSERT INTO activity_logs (user_id, action, description, branch_id, ip_address, created_at) 
                              VALUES (?, 'CANCEL_PRESCRIPTION', ?, ?, ?, NOW())")
                   ->execute([
                       $user_id,
                       "Cancelled prescription: " . ($presc['prescription_number'] ?? 'N/A'),
                       $selected_branch_id !== 'all' ? (int)$selected_branch_id : 1,
                       $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                   ]);
            } catch (Exception $e) {}
            
            header('Location: patient_prescriptions.php?patient_id=' . $patient_id . '&branch=' . urlencode($selected_branch_id) . '&cancelled=1');
            exit;
        } else {
            header('Location: patient_prescriptions.php?patient_id=' . $patient_id . '&branch=' . urlencode($selected_branch_id) . '&error=cancel_failed');
            exit;
        }
    } catch (Exception $e) {
        header('Location: patient_prescriptions.php?patient_id=' . $patient_id . '&branch=' . urlencode($selected_branch_id) . '&error=cancel_failed');
        exit;
    }
}

// ================================================================
// GET PATIENT
// ================================================================
$patient = null;
try {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name 
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$patient) {
    header('Location: prescriptions.php?error=patient_not_found');
    exit;
}

// ================================================================
// GET ALL PRESCRIPTIONS
// ================================================================
$all_prescriptions = [];
try {
    $sql = "
        SELECT 
            p.id, p.prescription_number, p.patient_id, p.visit_id, p.doctor_id,
            p.diagnosis, p.instructions, p.status, p.branch_id, p.created_at, p.dispensed_at,
            p.updated_at,
            doc.full_name as doctor_name,
            v.visit_number, v.visit_date, v.created_at as visit_created_at,
            b.name as branch_name,
            (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as item_count,
            (SELECT COALESCE(SUM(total_price), 0) FROM prescription_items WHERE prescription_id = p.id) as total_amount
        FROM prescriptions p
        LEFT JOIN users doc ON p.doctor_id = doc.id
        LEFT JOIN visits v ON p.visit_id = v.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.patient_id = ?
        ORDER BY p.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$patient_id]);
    $all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_prescriptions as &$presc) {
        $stmt = $db->prepare("
            SELECT id, medication_name, dosage, frequency, route, quantity, unit_price, total_price, duration, instructions
            FROM prescription_items 
            WHERE prescription_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$presc['id']]);
        $presc['medications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($presc);
} catch (Exception $e) {}

// ================================================================
// GROUP BY VISIT
// ================================================================
$visits_data = [];

foreach ($all_prescriptions as $presc) {
    $visit_id = $presc['visit_id'] ?? 0;
    
    if (!isset($visits_data[$visit_id])) {
        $visits_data[$visit_id] = [
            'visit_id' => $visit_id,
            'visit_number' => $presc['visit_number'] ?? 'N/A',
            'visit_date' => $presc['visit_date'] ?? $presc['visit_created_at'] ?? $presc['created_at'],
            'doctor_name' => $presc['doctor_name'] ?? 'N/A',
            'branch_name' => $presc['branch_name'] ?? 'N/A',
            'prescriptions' => [],
            'total_amount' => 0,
            'statuses' => []
        ];
    }
    
    $visits_data[$visit_id]['prescriptions'][] = $presc;
    $visits_data[$visit_id]['total_amount'] += $presc['total_amount'] ?? 0;
    $visits_data[$visit_id]['statuses'][] = $presc['status'];
}

foreach ($visits_data as &$visit) {
    $statuses = $visit['statuses'];
    if (in_array('pending', $statuses)) {
        $visit['overall_status'] = 'pending';
    } elseif (in_array('confirmed', $statuses)) {
        $visit['overall_status'] = 'confirmed';
    } elseif (in_array('dispensed', $statuses)) {
        $visit['overall_status'] = 'dispensed';
    } elseif (in_array('cancelled', $statuses)) {
        $visit['overall_status'] = 'cancelled';
    } else {
        $visit['overall_status'] = 'pending';
    }
}
unset($visit);

$visits_array = array_values($visits_data);
usort($visits_array, function($a, $b) {
    return strtotime($b['visit_date']) - strtotime($a['visit_date']);
});

// ================================================================
// STATS
// ================================================================
$total_prescriptions = count($all_prescriptions);
$pending_count = 0;
$confirmed_count = 0;
$dispensed_count = 0;
$cancelled_count = 0;
$total_amount = 0;

foreach ($all_prescriptions as $presc) {
    switch ($presc['status']) {
        case 'pending': $pending_count++; break;
        case 'confirmed': $confirmed_count++; break;
        case 'dispensed': $dispensed_count++; break;
        case 'cancelled': $cancelled_count++; break;
    }
    $total_amount += $presc['total_amount'] ?? 0;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
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
    --success: #059669;
    --success-bg: #D1FAE5;
    --danger: #DC2626;
    --danger-bg: #FEE2E2;
    --warning: #D97706;
    --warning-bg: #FEF3C7;
    --purple: #7C3AED;
    --gray-50: #F8FAFC;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --shadow: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
}

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

.patient-info-card {
    background: var(--bg-card);
    border-radius: 14px;
    padding: 18px 24px;
    border: 2px solid var(--border-color);
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 18px;
    box-shadow: var(--shadow);
}

.patient-info-card .p-avatar {
    width: 64px; height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: 800;
    color: white;
    font-family: 'Courier New', monospace;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.patient-info-card .p-details { flex: 1; min-width: 200px; }
.patient-info-card .p-name { font-size: 1.3rem; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; }
.patient-info-card .p-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.8rem;
    color: var(--text-secondary);
}
.patient-info-card .p-meta span { display: inline-flex; align-items: center; gap: 4px; }
.patient-info-card .p-meta i { color: var(--primary); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card {
    border-radius: 12px;
    padding: 14px 16px;
    color: white;
    position: relative;
    overflow: hidden;
    min-height: 85px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 120px; height: 120px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
}

.stat-card .stat-label {
    font-size: 0.55rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    opacity: 0.9;
    margin-bottom: 2px;
    position: relative;
    z-index: 1;
}

.stat-card .stat-number {
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1.1;
    position: relative;
    z-index: 1;
}

.stat-card .stat-icon {
    position: absolute;
    right: 12px;
    bottom: 12px;
    font-size: 1.5rem;
    opacity: 0.15;
}

.stat-card.blue { background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8); }
.stat-card.warning { background: linear-gradient(135deg, #F59E0B, #D97706, #B45309); }
.stat-card.success { background: linear-gradient(135deg, #10B981, #059669, #047857); }
.stat-card.info { background: linear-gradient(135deg, #4F46E5, #4338CA, #3730A3); }

.visit-section {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    margin-bottom: 18px;
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
}

.visit-section:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-md);
}

.visit-section-header {
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    border-bottom: 2px solid var(--primary-light);
}

[data-theme="dark"] .visit-section-header {
    background: linear-gradient(135deg, #1E3A5F, #16294A);
}

.visit-section-header .visit-info-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    flex: 1;
}

.visit-section-header .visit-icon-badge {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    box-shadow: 0 3px 10px rgba(245, 158, 11, 0.3);
}

.visit-section-header .visit-number {
    font-family: 'Courier New', monospace;
    font-weight: 800;
    font-size: 0.9rem;
    color: #78350F;
    background: rgba(255,255,255,0.6);
    padding: 4px 12px;
    border-radius: 8px;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

[data-theme="dark"] .visit-section-header .visit-number {
    background: rgba(255,255,255,0.1);
    color: #FCD34D;
}

.visit-section-header .visit-date {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
}

.visit-section-header .visit-doctor {
    font-size: 0.75rem;
    color: var(--primary);
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(255,255,255,0.6);
    padding: 4px 12px;
    border-radius: 20px;
    border: 1px solid var(--primary-light);
}

[data-theme="dark"] .visit-section-header .visit-doctor {
    background: rgba(255,255,255,0.1);
    color: #93C5FD;
}

.visit-section-header .visit-stats-right {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.visit-section-header .visit-mini-stat {
    background: rgba(255,255,255,0.7);
    padding: 4px 12px;
    border-radius: 18px;
    font-size: 0.68rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--text-primary);
}

[data-theme="dark"] .visit-section-header .visit-mini-stat {
    background: rgba(255,255,255,0.1);
}

.visit-section-header .visit-mini-stat .value {
    font-family: 'Courier New', monospace;
    color: var(--primary);
    font-weight: 800;
}

.visit-section-body {
    padding: 14px 18px 16px;
    background: var(--bg-card);
}

.prescription-card {
    background: var(--bg-body);
    border: 2px solid var(--border-color);
    border-radius: 10px;
    margin-bottom: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.prescription-card:last-child { margin-bottom: 0; }
.prescription-card:hover { border-color: var(--primary); box-shadow: 0 3px 12px rgba(11, 94, 215, 0.1); }

.prescription-header {
    background: linear-gradient(135deg, #FFFFFF, #F8FAFC);
    padding: 10px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    border-bottom: 1px solid var(--border-color);
}

[data-theme="dark"] .prescription-header {
    background: linear-gradient(135deg, #1E293B, #0F172A);
}

.prescription-header .presc-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    flex: 1;
}

.prescription-header .presc-number {
    font-family: 'Courier New', monospace;
    font-weight: 800;
    font-size: 0.75rem;
    color: var(--primary);
    background: var(--primary-bg);
    padding: 3px 10px;
    border-radius: 6px;
    border: 1px solid var(--primary-light);
}

.prescription-header .presc-date {
    font-size: 0.7rem;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.prescription-header .presc-amount {
    font-family: 'Courier New', monospace;
    font-weight: 800;
    font-size: 0.85rem;
    color: var(--success);
    background: var(--success-bg);
    padding: 3px 10px;
    border-radius: 6px;
}

.prescription-header .presc-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.btn-act {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.65rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.25s ease;
    white-space: nowrap;
}

.btn-act:hover { transform: translateY(-2px); }

.btn-act.edit {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
}
.btn-act.edit:hover { box-shadow: 0 4px 12px rgba(245, 158, 11, 0.5); }

.btn-act.cancel {
    background: linear-gradient(135deg, #6B7280, #4B5563);
    color: white;
    box-shadow: 0 2px 6px rgba(107, 114, 128, 0.3);
}
.btn-act.cancel:hover { box-shadow: 0 4px 12px rgba(107, 114, 128, 0.5); }

.btn-act.delete {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
    color: white;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
}
.btn-act.delete:hover { box-shadow: 0 4px 12px rgba(220, 38, 38, 0.5); }

.status-badge {
    padding: 4px 12px;
    border-radius: 18px;
    font-size: 0.62rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.status-badge.pending { background: #FEF3C7; color: #D97706; border: 1px solid #FCD34D; }
.status-badge.confirmed { background: #E8F0FE; color: #0B5ED7; border: 1px solid #93C5FD; }
.status-badge.dispensed { background: #D1FAE5; color: #059669; border: 1px solid #6EE7B7; }
.status-badge.cancelled { background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; }

[data-theme="dark"] .status-badge.pending { background: #3A2A1A; color: #FCD34D; border-color: #B45309; }
[data-theme="dark"] .status-badge.confirmed { background: #1E3A5F; color: #93C5FD; border-color: #3B82F6; }
[data-theme="dark"] .status-badge.dispensed { background: #1A3A2A; color: #34D399; border-color: #059669; }
[data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; border-color: #DC2626; }

.med-table-wrap {
    overflow-x: auto;
    background: var(--bg-card);
    border-radius: 8px;
}

.med-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.75rem;
    min-width: 700px;
}

.med-table thead th {
    text-align: left;
    padding: 8px 12px;
    font-weight: 700;
    font-size: 0.6rem;
    text-transform: uppercase;
    color: #ffffff;
    background: var(--primary);
    white-space: nowrap;
}

.med-table tbody td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    vertical-align: middle;
}

.med-table tbody tr:hover td { background: var(--primary-bg); }
[data-theme="dark"] .med-table tbody tr:hover td { background: #1E3A5F; }

.med-table .med-name {
    font-weight: 700;
    color: var(--primary);
}

.med-table .med-qty {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: var(--primary);
    color: white;
    padding: 2px 9px;
    border-radius: 10px;
    font-size: 0.68rem;
    font-weight: 800;
    font-family: 'Courier New', monospace;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px dashed var(--border-color);
}

.empty-state i { font-size: 3.5rem; color: var(--primary); display: block; margin-bottom: 12px; opacity: 0.5; }
.empty-state p { font-size: 1rem; font-weight: 600; color: var(--text-primary); }

.footer {
    padding: 14px 0;
    border-top: 1px solid var(--border-color);
    margin-top: 24px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
}

.footer .footer-brand { color: var(--primary); font-weight: 600; }

.toast-custom {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 14px 20px;
    border-radius: 12px;
    z-index: 9999;
    max-width: 420px;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.4s ease;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    color: white;
    font-size: 0.85rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.toast-custom.show { transform: translateY(0); opacity: 1; }
.toast-custom.success { background: linear-gradient(135deg, #059669, #047857); }
.toast-custom.error { background: linear-gradient(135deg, #DC2626, #B91C1C); }
.toast-custom.info { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
.toast-custom.warning { background: linear-gradient(135deg, #D97706, #B45309); }

@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header-custom { padding: 14px 16px; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .patient-info-card { padding: 14px 16px; }
    .prescription-header { flex-direction: column; align-items: stretch; }
}
</style>

<main class="main-content">

    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-medical-alt"></i>
                Patient Prescriptions
            </h1>
            <p class="page-subtitle">
                All prescriptions grouped by visit
                <span class="branch-tag">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($patient['full_name']) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="prescriptions.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="patient-info-card">
        <div class="p-avatar" style="background: <?= '#' . substr(md5($patient['full_name']), 0, 6) ?>;">
            <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        <div class="p-details">
            <div class="p-name"><?= htmlspecialchars($patient['full_name']) ?></div>
            <div class="p-meta">
                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                <?php if (!empty($patient['gender'])): ?>
                    <span><i class="fas fa-<?= $patient['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['gender']) ?></span>
                <?php endif; ?>
                <?php if (!empty($patient['date_of_birth'])): ?>
                    <span><i class="fas fa-calendar-alt"></i> <?= calculateAge($patient['date_of_birth']) ?> yrs</span>
                <?php endif; ?>
                <?php if (!empty($patient['phone'])): ?>
                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-label">Total Prescriptions</div>
            <div class="stat-number"><?= $total_prescriptions ?></div>
            <i class="fas fa-prescription stat-icon"></i>
        </div>
        <div class="stat-card warning">
            <div class="stat-label">Pending</div>
            <div class="stat-number"><?= $pending_count ?></div>
            <i class="fas fa-clock stat-icon"></i>
        </div>
        <div class="stat-card success">
            <div class="stat-label">Dispensed</div>
            <div class="stat-number"><?= $dispensed_count ?></div>
            <i class="fas fa-pills stat-icon"></i>
        </div>
        <div class="stat-card info">
            <div class="stat-label">Total Amount</div>
            <div class="stat-number" style="font-size:1.1rem;">TSh <?= number_format($total_amount, 0) ?></div>
            <i class="fas fa-money-bill-wave stat-icon"></i>
        </div>
    </div>

    <?php if (count($visits_array) > 0): ?>
        <?php foreach ($visits_array as $visit): 
            $visit_status_class = $visit['overall_status'];
            $visit_status_icon = '⏳';
            $visit_status_label = 'Pending';
            
            if ($visit_status_class === 'confirmed') { $visit_status_icon = '✅'; $visit_status_label = 'Confirmed'; }
            elseif ($visit_status_class === 'dispensed') { $visit_status_icon = '💊'; $visit_status_label = 'Dispensed'; }
            elseif ($visit_status_class === 'cancelled') { $visit_status_icon = '❌'; $visit_status_label = 'Cancelled'; }
            
            $visit_presc_count = count($visit['prescriptions']);
        ?>
            <div class="visit-section">
                <div class="visit-section-header">
                    <div class="visit-info-left">
                        <div class="visit-icon-badge">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <span class="visit-number"><?= htmlspecialchars($visit['visit_number']) ?></span>
                        <span class="visit-date">
                            <i class="fas fa-calendar-day"></i>
                            <?= !empty($visit['visit_date']) ? date('d M Y', strtotime($visit['visit_date'])) : 'N/A' ?>
                        </span>
                        <span class="visit-doctor">
                            <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                        </span>
                        <span class="status-badge <?= $visit_status_class ?>">
                            <?= $visit_status_icon ?> <?= $visit_status_label ?>
                        </span>
                    </div>
                    
                    <div class="visit-stats-right">
                        <span class="visit-mini-stat">
                            <i class="fas fa-prescription"></i>
                            Rx: <span class="value"><?= $visit_presc_count ?></span>
                        </span>
                        <span class="visit-mini-stat">
                            <i class="fas fa-money-bill-wave"></i>
                            <span class="value">TSh <?= number_format($visit['total_amount'], 0) ?></span>
                        </span>
                    </div>
                </div>
                
                <div class="visit-section-body">
                    <?php foreach ($visit['prescriptions'] as $presc): 
                        $status = $presc['status'] ?? 'pending';
                        
                        $status_icon = '⏳';
                        $status_label = 'Pending';
                        if ($status === 'confirmed') { $status_icon = '✅'; $status_label = 'Confirmed'; }
                        elseif ($status === 'dispensed') { $status_icon = '💊'; $status_label = 'Dispensed'; }
                        elseif ($status === 'cancelled') { $status_icon = '❌'; $status_label = 'Cancelled'; }
                        
                        $is_pending = ($status === 'pending');
                    ?>
                        <div class="prescription-card">
                            <div class="prescription-header">
                                <div class="presc-left">
                                    <span class="presc-number">
                                        <i class="fas fa-hashtag"></i>
                                        <?= htmlspecialchars($presc['prescription_number'] ?? 'N/A') ?>
                                    </span>
                                    <span class="presc-date">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d M Y, H:i', strtotime($presc['created_at'] ?? 'now')) ?>
                                    </span>
                                    <span class="status-badge <?= $status ?>">
                                        <?= $status_icon ?> <?= $status_label ?>
                                    </span>
                                    <span class="presc-amount">
                                        TSh <?= number_format($presc['total_amount'] ?? 0, 0) ?>
                                    </span>
                                </div>
                                
                                <div class="presc-actions">
                                    <?php if ($is_pending): ?>
                                        <a href="edit_prescription.php?id=<?= $presc['id'] ?>&patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-act edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>&cancel=<?= $presc['id'] ?>" 
                                           class="btn-act cancel" 
                                           onclick="return confirm('⚠️ Cancel this prescription?\n\nRx #: <?= htmlspecialchars(addslashes($presc['prescription_number'] ?? 'N/A')) ?>\n\nStatus → Cancelled');">
                                            <i class="fas fa-ban"></i> Cancel
                                        </a>
                                        <a href="?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>&delete=<?= $presc['id'] ?>" 
                                           class="btn-act delete" 
                                           onclick="return confirm('⚠️ DELETE this PENDING prescription?\n\nRx #: <?= htmlspecialchars(addslashes($presc['prescription_number'] ?? 'N/A')) ?>\n\n✅ Stock itarudi kwenye inventory\n✅ Bill itafutwa\n\nContinue?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php elseif ($status === 'confirmed'): ?>
                                        <a href="?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>&delete=<?= $presc['id'] ?>" 
                                           class="btn-act delete" 
                                           onclick="return confirm('⚠️ DELETE this CONFIRMED prescription?\n\nRx #: <?= htmlspecialchars(addslashes($presc['prescription_number'] ?? 'N/A')) ?>\n\n✅ Stock itarudi kwenye inventory\n✅ Bill itafutwa\n\nContinue?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php elseif ($status === 'dispensed'): ?>
                                        <a href="?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>&delete=<?= $presc['id'] ?>" 
                                           class="btn-act delete" 
                                           onclick="return confirm('⚠️ DELETE this DISPENSED prescription?\n\nRx #: <?= htmlspecialchars(addslashes($presc['prescription_number'] ?? 'N/A')) ?>\n\n⚠️ Dawa zimeshatolewa - STOCK HAITARUDI\n⚠️ BILL HAITAFUTWA\n\nKumbukumbu itabaki kwenye activity logs.\n\nContinue?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php else: ?>
                                        <a href="?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>&delete=<?= $presc['id'] ?>" 
                                           class="btn-act delete" 
                                           onclick="return confirm('⚠️ DELETE this CANCELLED prescription?\n\nRx #: <?= htmlspecialchars(addslashes($presc['prescription_number'] ?? 'N/A')) ?>\n\n✅ Stock itarudi kwenye inventory\n✅ Bill itafutwa\n\nContinue?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($presc['medications'])): ?>
                                <div class="med-table-wrap">
                                    <table class="med-table">
                                        <thead>
                                            <tr>
                                                <th style="width:40px;">#</th>
                                                <th><i class="fas fa-pills"></i> Medication</th>
                                                <th><i class="fas fa-prescription"></i> Dosage</th>
                                                <th><i class="fas fa-clock"></i> Frequency</th>
                                                <th><i class="fas fa-route"></i> Route</th>
                                                <th style="text-align:center;"><i class="fas fa-sort-numeric-up"></i> Qty</th>
                                                <th style="text-align:right;"><i class="fas fa-money-bill-wave"></i> Unit Price</th>
                                                <th style="text-align:right;"><i class="fas fa-coins"></i> Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $m = 1; foreach ($presc['medications'] as $med): ?>
                                                <tr>
                                                    <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-size:0.7rem;"><?= $m++ ?></td>
                                                    <td><span class="med-name"><?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?></span></td>
                                                    <td style="font-size:0.72rem;"><?= htmlspecialchars($med['dosage'] ?? '—') ?></td>
                                                    <td style="font-size:0.72rem;"><?= htmlspecialchars($med['frequency'] ?? '—') ?></td>
                                                    <td style="font-size:0.72rem;"><?= htmlspecialchars($med['route'] ?? '—') ?></td>
                                                    <td style="text-align:center;">
                                                        <span class="med-qty">x<?= (int)($med['quantity'] ?? 0) ?></span>
                                                    </td>
                                                    <td style="text-align:right;font-family:'Courier New',monospace;font-size:0.72rem;">
                                                        TSh <?= number_format($med['unit_price'] ?? 0, 0) ?>
                                                    </td>
                                                    <td style="text-align:right;font-family:'Courier New',monospace;font-weight:700;color:var(--success);font-size:0.75rem;">
                                                        TSh <?= number_format($med['total_price'] ?? 0, 0) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div style="padding:20px;text-align:center;color:var(--text-secondary);font-size:0.8rem;">
                                    <i class="fas fa-pills" style="opacity:0.3;font-size:1.5rem;display:block;margin-bottom:6px;"></i>
                                    No medications
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-prescription"></i>
            <p>No prescriptions found</p>
        </div>
    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Patient Prescriptions
            <span style="margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.2rem;flex-shrink:0;margin-top:2px;"></i>
    <div style="flex:1;">
        <p style="font-weight:700;font-size:0.85rem;margin:0 0 4px;" id="toastTitle">Notification</p>
        <p style="font-size:0.72rem;opacity:0.9;margin:0;line-height:1.5;" id="toastMessage"></p>
    </div>
</div>

<script>
function showToast(title, message, type, duration) {
    var toast = document.getElementById('toast');
    var toastTitle = document.getElementById('toastTitle');
    var toastMessage = document.getElementById('toastMessage');
    if (!toast) return;
    toast.className = 'toast-custom ' + type;
    toastTitle.textContent = title;
    toastMessage.innerHTML = message.replace(/\n/g, '<br>');
    toast.style.display = 'flex';
    toast.classList.add('show');
    clearTimeout(toast.timeout);
    toast.timeout = setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() { toast.style.display = 'none'; }, 400);
    }, duration || 5000);
}

// ================================================================
// ✅ SUCCESS MESSAGE - SMART (kwa kuzingatia status)
// ================================================================
<?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var msg = 'Prescription deleted successfully!';
        
        <?php if (isset($_GET['stock_returned']) && (int)$_GET['stock_returned'] > 0): ?>
            msg += '\n✅ Stock returned for <?= (int)$_GET['stock_returned'] ?> item(s)';
        <?php endif; ?>
        
        <?php if (isset($_GET['bills_deleted']) && (int)$_GET['bills_deleted'] > 0): ?>
            msg += '\n✅ <?= (int)$_GET['bills_deleted'] ?> bill(s) deleted';
        <?php endif; ?>
        
        <?php if (isset($_GET['dispensed_kept']) && $_GET['dispensed_kept'] == 1): ?>
            msg += '\n⚠️ Dispensed prescription - stock NOT returned';
            msg += '\n⚠️ Bill NOT deleted (already processed)';
            msg += '\n📝 Record kept in activity logs';
            showToast('💊 Dispensed - Deleted', msg, 'warning', 7000);
        <?php else: ?>
            showToast('✅ Deleted Successfully', msg, 'success', 5000);
        <?php endif; ?>
        
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('deleted');
            url.searchParams.delete('stock_returned');
            url.searchParams.delete('bills_deleted');
            url.searchParams.delete('dispensed_kept');
            window.history.replaceState({}, document.title, url.toString());
        }
    });
<?php endif; ?>

<?php if (isset($_GET['cancelled']) && $_GET['cancelled'] == 1): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('⚠️ Cancelled', 'Prescription status changed to Cancelled', 'info', 4000);
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('cancelled');
            window.history.replaceState({}, document.title, url.toString());
        }
    });
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var errMsg = 'An error occurred';
        <?php if ($_GET['error'] === 'delete_failed'): ?>
            errMsg = 'Failed to delete prescription';
        <?php elseif ($_GET['error'] === 'cancel_failed'): ?>
            errMsg = 'Failed to cancel prescription';
        <?php elseif ($_GET['error'] === 'not_found'): ?>
            errMsg = 'Prescription not found';
        <?php endif; ?>
        showToast('❌ Error', errMsg, 'error', 4000);
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('error');
            window.history.replaceState({}, document.title, url.toString());
        }
    });
<?php endif; ?>

console.log('%c💊 Braick - Patient Prescriptions (SMART DELETE)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Pending/Confirmed/Cancelled → Return stock + Delete bill', 'font-size:12px;color:#059669;font-weight:bold;');
console.log('%c💊 Dispensed → NO stock return + NO bill delete', 'font-size:12px;color:#D97706;font-weight:bold;');
</script>

</body>
</html>