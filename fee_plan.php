<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

// Ensure the per-student fee-plan table exists
try { db_query("CREATE TABLE IF NOT EXISTS student_fee_plan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    head_id INT DEFAULT NULL,
    head_name VARCHAR(191) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (student_id)
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

// Additional tables for fee structure
try { db_query("CREATE TABLE IF NOT EXISTS fee_discount_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

try { db_query("CREATE TABLE IF NOT EXISTS fee_course_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    amount DECIMAL(12,2) DEFAULT 0,
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB"); } catch (Throwable $ex) {}

// Ensure the per-student fee-plan columns exist on the students table
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS discount_package_id INT DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS course_package_id INT DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS old_balance DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS transport_fee DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS discount_reason VARCHAR(191) DEFAULT NULL"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS miscellaneous_fee DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Throwable $ex) {}
try { db_query("ALTER TABLE students ADD COLUMN IF NOT EXISTS payment_mode VARCHAR(30) DEFAULT 'monthly'"); } catch (Throwable $ex) {}

$studentId = (int) ($_GET['student_id'] ?? $_GET['student'] ?? 0);
$saved = false;
$savedMessage = '';

// --- POST: save the fee plan ---
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['save_fee_plan'] ?? '') === '1') {
    $sid = (int) ($_POST['student_id'] ?? 0);
    if ($sid > 0) {
        // Delete existing
        $st = db_prepare('DELETE FROM student_fee_plan WHERE student_id = ?');
        $st->bind_param('i', $sid);
        $st->execute();
        $st->close();
        
        $heads = isset($_POST['heads']) ? $_POST['heads'] : [];
        $done = 0;
        $ins = db_prepare('INSERT INTO student_fee_plan (student_id, head_id, head_name, amount, discount) VALUES (?, ?, ?, ?, ?)');
        foreach ((array) $heads as $head_id => $row) {
            if (empty($row)) continue;
            $hname = trim($_POST['head_names'][$head_id] ?? '');
            $amt = (float) ($_POST['amounts'][$head_id] ?? 0);
            $disc = (float) ($_POST['discounts'][$head_id] ?? 0);
            $ins->bind_param('iisdd', $sid, (int) $head_id, $hname, $amt, $disc);
            $ins->execute();
            $done++;
        }
        $ins->close();
        
        // Save additional fields
        $discount_package = (int) ($_POST['discount_package'] ?? 0);
        $course_package = (int) ($_POST['course_package'] ?? 0);
        $old_balance = (float) ($_POST['old_balance'] ?? 0);
        $misc_fee = (float) ($_POST['miscellaneous_fee'] ?? 0);
        $transport = (float) ($_POST['transport'] ?? 0);
        $discount_reason = trim($_POST['discount_reason'] ?? '');
        $payment_mode = trim($_POST['payment_mode'] ?? 'monthly');
        
        // Save to student_fee_plan as meta or update student table
        $upd = db_prepare("UPDATE students SET 
            discount_package_id = ?, 
            course_package_id = ?, 
            old_balance = ?, 
            miscellaneous_fee = ?,
            transport_fee = ?,
            discount_reason = ?,
            payment_mode = ?
            WHERE student_id = ?");
        $upd->bind_param('iidddssi', $discount_package, $course_package, $old_balance, $misc_fee, $transport, $discount_reason, $payment_mode, $sid);
        $upd->execute();
        $upd->close();
        
        $message = 'Fee plan saved with ' . $done . ' fee head(s).';
        $saved = true;
        $savedMessage = $message;
    } else {
        $error = 'Please select a valid student before saving the fee plan.';
    }
}

// --- Load the student ---
$student = null;
if ($studentId > 0) {
    $sq = db_prepare('SELECT student_id, first_name, last_name, gr_no, class_id, section_id, admission_date, 
        discount_package_id, course_package_id, old_balance, miscellaneous_fee, transport_fee, discount_reason, payment_mode 
        FROM students WHERE student_id = ?');
    $sq->bind_param('i', $studentId);
    $sq->execute();
    $student = $sq->get_result()->fetch_assoc();
    $sq->close();
}

// Existing fee plan for pre-fill
$savedPlan = [];
if ($studentId > 0) {
    $fp = db_prepare('SELECT head_id, head_name, amount, discount FROM student_fee_plan WHERE student_id = ? ORDER BY id');
    $fp->bind_param('i', $studentId);
    $fp->execute();
    $res = $fp->get_result();
    while ($r = $res->fetch_assoc()) { $savedPlan[(int) $r['head_id']] = $r; }
    $fp->close();
}

// Active fee heads
$feeHeads = [];
$fr = db_query("SELECT head_id, head_name, amount FROM fee_heads WHERE status=1 ORDER BY head_id");
if ($fr) { while ($row = $fr->fetch_assoc()) { $feeHeads[] = $row; } }

// Discount packages
$discountPackages = [];
$dp = db_query("SELECT id, name, discount_percent FROM fee_discount_packages WHERE status=1 ORDER BY name");
if ($dp) { while ($row = $dp->fetch_assoc()) { $discountPackages[] = $row; } }

// Course packages
$coursePackages = [];
$cp = db_query("SELECT id, name, amount FROM fee_course_packages WHERE status=1 ORDER BY name");
if ($cp) { while ($row = $cp->fetch_assoc()) { $coursePackages[] = $row; } }

// Class / section labels
$classLabel = '';
if ($student && !empty($student['class_id'])) {
    $cr = db_prepare('Select class_name FROM classes WHERE class_id = ?');
    $cr->bind_param('i', $student['class_id']);
    $cr->execute();
    $classLabel = $cr->get_result()->fetch_row()[0] ?? '';
    $cr->close();
}
$sectionLabel = '';
if ($student && !empty($student['section_id'])) {
    $sr = db_prepare('SELECT section_name FROM sections WHERE section_id = ?');
    $sr->bind_param('i', $student['section_id']);
    $sr->execute();
    $sectionLabel = $sr->get_result()->fetch_row()[0] ?? '';
    $sr->close();
}

include __DIR__ . '/includes/header.php';
?>

<style>
    :root {
        --primary: #FF6B2C;
        --primary-light: #FFF3EC;
        --primary-hover: #E55A1E;
        --gray-50: #F8FAFC;
        --gray-100: #F1F5F9;
        --gray-200: #E2E8F0;
        --gray-300: #CBD5E1;
        --gray-400: #94A3B8;
        --gray-500: #64748B;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1E293B;
        --gray-900: #0F172A;
        --border-radius: 12px;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--gray-50); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }

    .page-wrapper { max-width: 1200px; margin: 0 auto; padding: 16px 20px 40px; width: 100%; }

    /* Top Bar */
    .top-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--gray-200); }
    .top-bar-left { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
    .top-bar-left .brand { font-size: clamp(16px, 2.2vw, 22px); font-weight: 700; color: var(--gray-900); letter-spacing: -0.3px; }
    .top-bar-left .brand span { color: var(--primary); }
    .top-bar-left .session-badge { font-size: clamp(10px, 1.2vw, 13px); font-weight: 600; color: var(--gray-500); background: var(--gray-100); padding: 3px 12px; border-radius: 20px; border: 1px solid var(--gray-200); white-space: nowrap; }
    .top-bar-right { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
    .top-bar-right .search-box { display: flex; align-items: center; gap: 6px; background: white; border: 1px solid var(--gray-200); border-radius: 8px; padding: 5px 12px; font-size: clamp(11px, 1.1vw, 13px); color: var(--gray-500); min-width: 140px; flex: 1; max-width: 320px; }
    .top-bar-right .search-box i { color: var(--gray-400); font-size: clamp(12px, 1vw, 14px); }
    .top-bar-right .search-box input { border: none; outline: none; background: transparent; font-size: clamp(11px, 1.1vw, 13px); color: var(--gray-700); width: 100%; min-width: 80px; }
    .top-bar-right .search-box input::placeholder { color: var(--gray-400); font-size: clamp(10px, 1vw, 12px); }
    .top-bar-right .user-badge { display: flex; align-items: center; gap: 6px; font-size: clamp(11px, 1.1vw, 13px); font-weight: 500; color: var(--gray-700); white-space: nowrap; }
    .top-bar-right .user-badge .avatar { width: clamp(28px, 3vw, 36px); height: clamp(28px, 3vw, 36px); border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: clamp(12px, 1.2vw, 15px); font-weight: 600; flex-shrink: 0; }

    /* Quick Links */
    .quick-links { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-bottom: 14px; font-size: clamp(11px, 1.1vw, 13px); color: var(--gray-500); }
    .quick-links a { color: var(--gray-600); text-decoration: none; padding: 3px 8px; border-radius: 6px; transition: all 0.2s; font-size: clamp(11px, 1.1vw, 13px); white-space: nowrap; }
    .quick-links a:hover { background: var(--gray-100); color: var(--gray-800); }
    .quick-links .separator { color: var(--gray-300); font-size: clamp(9px, 0.8vw, 11px); }

    /* Card */
    .fee-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--border-radius); overflow: hidden; margin-bottom: clamp(14px, 1.8vw, 24px); }
    .fee-card .card-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: clamp(10px, 1.2vw, 16px) clamp(14px, 1.8vw, 24px); border-bottom: 1px solid var(--gray-100); background: var(--gray-50); }
    .fee-card .card-header h3 { font-size: clamp(13px, 1.2vw, 16px); font-weight: 600; color: var(--gray-700); display: flex; align-items: center; gap: 8px; }
    .fee-card .card-header h3 i { color: var(--primary); font-size: clamp(14px, 1.2vw, 17px); }
    .fee-card .card-body { padding: clamp(14px, 1.8vw, 24px); }

    /* Wizard Steps */
    .wizard-steps { display: flex; align-items: center; gap: clamp(10px, 1.5vw, 20px); background: white; border: 1px solid var(--gray-200); border-radius: var(--border-radius); padding: clamp(10px, 1.2vw, 16px) clamp(14px, 1.8vw, 24px); margin-bottom: clamp(16px, 2vw, 24px); flex-wrap: wrap; }
    .wizard-steps .step { display: flex; align-items: center; gap: clamp(8px, 1vw, 14px); }
    .wizard-steps .step .num { width: clamp(28px, 2.8vw, 36px); height: clamp(28px, 2.8vw, 36px); border-radius: 50%; background: var(--gray-200); color: var(--gray-500); display: flex; align-items: center; justify-content: center; font-size: clamp(12px, 1.2vw, 15px); font-weight: 700; flex-shrink: 0; }
    .wizard-steps .step .num.active { background: var(--primary); color: white; }
    .wizard-steps .step .num.completed { background: #22C55E; color: white; }
    .wizard-steps .step .info { flex: 1; min-width: 0; }
    .wizard-steps .step .info .title { font-size: clamp(12px, 1.1vw, 14px); font-weight: 600; color: var(--gray-700); }
    .wizard-steps .step .info .sub { font-size: clamp(10px, 0.9vw, 12px); color: var(--gray-400); }
    .wizard-steps .divider { flex: 1; min-width: 20px; max-width: 100px; border-top: 2px dashed var(--gray-200); }

    /* Student Info Box */
    .student-info-box { display: flex; align-items: center; gap: 14px; padding: 12px 16px; background: var(--gray-50); border-radius: 10px; border: 1px solid var(--gray-200); margin-bottom: 16px; flex-wrap: wrap; }
    .student-info-box .avatar-placeholder { width: 48px; height: 48px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; flex-shrink: 0; }
    .student-info-box .details { flex: 1; min-width: 0; }
    .student-info-box .details .name { font-size: 15px; font-weight: 600; color: var(--gray-800); }
    .student-info-box .details .class { font-size: 13px; color: var(--gray-500); }
    .student-info-box .details .class i { margin-right: 4px; }

    /* Fee Grid - Main Layout */
    .fee-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .fee-grid .field-group { display: flex; flex-direction: column; gap: 6px; }
    .fee-grid .field-group label { font-size: 12px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.3px; }
    .fee-grid .field-group .field-value { font-size: 15px; font-weight: 500; color: var(--gray-800); padding: 8px 12px; background: var(--gray-50); border-radius: 6px; border: 1px solid var(--gray-200); min-height: 40px; display: flex; align-items: center; }
    .fee-grid .field-group select, .fee-grid .field-group input { width: 100%; padding: 8px 12px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px; color: var(--gray-800); background: white; outline: none; transition: border-color 0.2s; min-height: 40px; }
    .fee-grid .field-group select:focus, .fee-grid .field-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(255,107,44,0.08); }
    .fee-grid .field-group .field-value .amount { font-weight: 700; color: var(--primary); }

    /* Fee Table */
    .fee-table { width: 100%; border-collapse: collapse; font-size: clamp(13px, 1.1vw, 15px); margin-top: 16px; }
    .fee-table th { padding: clamp(8px, 0.9vw, 12px) clamp(10px, 1vw, 16px); text-align: left; font-size: clamp(11px, 0.9vw, 13px); text-transform: uppercase; color: var(--gray-500); font-weight: 600; background: var(--gray-50); border-bottom: 2px solid var(--gray-200); }
    .fee-table td { padding: clamp(8px, 0.9vw, 12px) clamp(10px, 1vw, 16px); border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .fee-table .fh-amount, .fee-table .fh-discount { width: 140px; }
    .fee-table .fh-amount input, .fee-table .fh-discount input { width: 100%; padding: 6px 10px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: clamp(12px, 1.1vw, 14px); outline: none; transition: border-color 0.2s; }
    .fee-table .fh-amount input:focus, .fee-table .fh-discount input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(255,107,44,0.08); }
    .fee-table .total-row td { font-weight: 700; border-top: 2px solid var(--gray-200); padding-top: 12px; }
    .fee-table .total-row .total-label { text-align: right; color: var(--gray-600); }

    /* Buttons */
    .btn-primary { display: inline-flex; align-items: center; gap: clamp(6px, 0.6vw, 10px); padding: clamp(8px, 0.9vw, 12px) clamp(16px, 1.8vw, 28px); border-radius: 8px; background: var(--primary); color: white; font-size: clamp(12px, 1.1vw, 15px); font-weight: 600; border: none; cursor: pointer; transition: background 0.2s; text-decoration: none; white-space: nowrap; }
    .btn-primary:hover { background: var(--primary-hover); color: white; }
    .btn-secondary { display: inline-flex; align-items: center; gap: clamp(6px, 0.6vw, 10px); padding: clamp(8px, 0.9vw, 12px) clamp(14px, 1.5vw, 22px); border-radius: 8px; background: var(--gray-100); color: var(--gray-600); font-size: clamp(12px, 1.1vw, 15px); font-weight: 500; border: none; cursor: pointer; transition: background 0.2s; text-decoration: none; white-space: nowrap; }
    .btn-secondary:hover { background: var(--gray-200); color: var(--gray-700); }

    /* Form Actions */
    .form-actions { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: clamp(10px, 1.2vw, 16px); padding-top: clamp(16px, 2vw, 24px); border-top: 1px solid var(--gray-200); margin-top: 4px; }
    .form-actions .note { font-size: clamp(11px, 1vw, 13px); color: var(--gray-400); }

    /* Success Box */
    .success-box { text-align: center; padding: 30px 20px; }
    .success-box .icon { font-size: 60px; color: #22C55E; margin-bottom: 16px; }
    .success-box h2 { font-size: 22px; color: var(--gray-800); margin-bottom: 8px; }
    .success-box p { font-size: 14px; color: var(--gray-500); }

    /* Toast */
    #toast-container { position: fixed; top: 20px; right: 20px; z-index: 1000; display: flex; flex-direction: column; gap: 8px; max-width: min(360px, 90vw); width: 100%; pointer-events: none; }
    .toast-item { padding: clamp(12px, 1.2vw, 16px) clamp(14px, 1.5vw, 20px); border-radius: 10px; color: white; font-size: clamp(12px, 1.1vw, 14px); font-weight: 500; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-lg); animation: slideIn 0.3s ease; pointer-events: auto; width: 100%; }
    .toast-item i { font-size: clamp(14px, 1.2vw, 18px); flex-shrink: 0; }
    .toast-item.success { background: #22C55E; }
    .toast-item.error { background: #EF4444; }
    .toast-item.warning { background: #F59E0B; }
    .toast-item.info { background: #3B82F6; }
    @keyframes slideIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: none; } }

    /* Responsive */
    @media (max-width: 991px) {
        .fee-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 767px) {
        .page-wrapper { padding: 12px 16px 28px; }
        .top-bar { flex-direction: column; align-items: stretch; gap: 8px; }
        .top-bar-right { flex-wrap: wrap; }
        .top-bar-right .search-box { max-width: 100%; }
        .wizard-steps .divider { display: none; }
        .wizard-steps .step .info .sub { display: none; }
        .fee-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-actions { flex-direction: row; flex-wrap: wrap; align-items: center; }
        .form-actions .note { margin-left: 0; }
        .btn-primary, .btn-secondary { justify-content: center; }
        .fee-table { min-width: 500px; }
        .fee-table-wrapper { overflow-x: auto; }
        .student-info-box { flex-direction: row; flex-wrap: wrap; justify-content: center; }
    }
    @media (max-width: 479px) {
        .page-wrapper { padding: 10px 12px 24px; }
        .top-bar-left .brand { font-size: 16px; }
        .wizard-steps { flex-direction: row; align-items: center; gap: 8px; }
        .wizard-steps .divider { display: none; }
        .wizard-steps .step .info .sub { display: none; }
        .fee-table { min-width: 400px; }
    }
    @media (max-height: 500px) and (orientation: landscape) {
        .page-wrapper { padding: 8px 16px 16px; }
        .top-bar { margin-bottom: 8px; padding-bottom: 8px; }
        .top-bar-left .brand { font-size: 16px; }
        .wizard-steps { padding: 6px 12px; margin-bottom: 10px; gap: 6px; }
        .wizard-steps .step .num { width: 22px; height: 22px; font-size: 10px; }
        .wizard-steps .step .info .title { font-size: 10px; }
        .wizard-steps .step .info .sub { display: none; }
        .wizard-steps .divider { display: none; }
        .fee-card .card-header { padding: 6px 12px; }
        .fee-card .card-header h3 { font-size: 11px; }
        .fee-card .card-body { padding: 8px 12px; }
        .fee-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .fee-grid .field-group label { font-size: 9px; }
        .fee-grid .field-group .field-value, .fee-grid .field-group select, .fee-grid .field-group input { font-size: 11px; padding: 4px 8px; min-height: 28px; }
        .fee-table th { padding: 4px 8px; font-size: 9px; }
        .fee-table td { padding: 4px 8px; }
        .btn-primary, .btn-secondary { font-size: 10px; padding: 4px 12px; }
        .form-actions { padding-top: 10px; gap: 6px; }
        .student-info-box { padding: 6px 10px; }
        .student-info-box .avatar-placeholder { width: 28px; height: 28px; font-size: 12px; }
        .student-info-box .details .name { font-size: 11px; }
        .student-info-box .details .class { font-size: 9px; }
        .success-box { padding: 16px 12px; }
        .success-box .icon { font-size: 32px; }
        .success-box h2 { font-size: 14px; }
    }
</style>

<div class="page-wrapper">

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-left">
            <div class="brand">Test <span>Portal</span></div>
            <div class="session-badge">2026-2027</div>
        </div>
        <div class="top-bar-right">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search Student with | Name | GR No | Family Code">
            </div>
            <div class="user-badge">
                <span>Super Admin</span>
                <div class="avatar">SA</div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="quick-links">
        <a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <span class="separator">/</span>
        <a href="<?php echo BASE_URL; ?>manage_students.php">Students</a>
        <span class="separator">/</span>
        <span style="color: var(--gray-700); font-weight: 500;">Create Fee Plan</span>
    </div>

    <?php if ($error): ?>
        <div style="display: flex; align-items: center; gap: 10px; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 8px; padding: clamp(10px, 1.2vw, 14px) clamp(14px, 1.5vw, 20px); margin-bottom: clamp(14px, 1.8vw, 20px); color: #DC2626; font-size: clamp(12px, 1.1vw, 14px);">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo e($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$student): ?>
        <div class="fee-card">
            <div class="card-body" style="text-align:center; padding: 40px 20px;">
                <i class="fas fa-user-slash" style="font-size: 48px; color: var(--gray-300); margin-bottom: 12px;"></i>
                <h3 style="font-size:18px; color: var(--gray-600);">No Student Selected</h3>
                <p style="color: var(--gray-400); margin: 8px 0 16px;">Please select a student to create a fee plan.</p>
                <a href="<?php echo BASE_URL; ?>manage_students.php" class="btn-primary"><i class="fas fa-arrow-left"></i> Back to Students</a>
            </div>
        </div>
    <?php else: ?>

    <?php
    $fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
    $classDisplay = $classLabel . ($sectionLabel ? ' - ' . $sectionLabel : '');
    $discountPackageId = $student['discount_package_id'] ?? 0;
    $coursePackageId = $student['course_package_id'] ?? 0;
    $oldBalance = (float) ($student['old_balance'] ?? 0);
    $miscFee = (float) ($student['miscellaneous_fee'] ?? 0);
    $transportFee = (float) ($student['transport_fee'] ?? 0);
    $discountReason = $student['discount_reason'] ?? '';
    $paymentMode = $student['payment_mode'] ?? 'monthly';
    ?>

    <!-- Wizard Steps -->
    <div class="wizard-steps">
        <div class="step">
            <div class="num completed"><i class="fas fa-check" style="font-size:14px;"></i></div>
            <div class="info">
                <div class="title">Student Information</div>
                <div class="sub">Name, class &amp; contact details</div>
            </div>
        </div>
        <div class="divider"></div>
        <div class="step">
            <div class="num active">2</div>
            <div class="info">
                <div class="title">Save Fee Plan</div>
                <div class="sub">Configure monthly fee parameters</div>
            </div>
        </div>
        <div class="divider"></div>
        <div class="step">
            <div class="num <?php echo $saved ? 'completed' : ''; ?>"><?php echo $saved ? '<i class="fas fa-check" style="font-size:14px;"></i>' : '3'; ?></div>
            <div class="info">
                <div class="title">Finish</div>
                <div class="sub">Complete admission process</div>
            </div>
        </div>
    </div>

    <!-- Student Info -->
    <div class="student-info-box">
        <div class="avatar-placeholder"><?php echo strtoupper(substr($fullName, 0, 2) ?: 'S'); ?></div>
        <div class="details">
            <div class="name"><?php echo e($fullName); ?> <span style="font-weight:400; color:var(--gray-400); font-size:13px;">(GR No: <?php echo e($student['gr_no']); ?>)</span></div>
            <div class="class"><i class="far fa-calendar"></i> <?php echo e($classDisplay ?: 'No class assigned'); ?></div>
        </div>
        <?php if ($student['admission_date']): ?>
        <div style="font-size:13px; color:var(--gray-400); white-space:nowrap;">
            <i class="far fa-calendar-check"></i> <?php echo date('d M Y', strtotime($student['admission_date'])); ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($saved): ?>
        <!-- SUCCESS VIEW -->
        <div class="fee-card">
            <div class="card-body success-box">
                <div class="icon"><i class="far fa-check-circle"></i></div>
                <h2>Fee Plan Saved Successfully</h2>
                <p style="margin-bottom: 20px;"><?php echo e($savedMessage); ?></p>
                <div style="display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>add_student.php" class="btn-primary"><i class="fas fa-plus"></i> Add New Student</a>
                    <a href="<?php echo BASE_URL; ?>student.php?student=<?php echo (int) $studentId; ?>" class="btn-secondary"><i class="fas fa-user"></i> View Profile</a>
                    <a href="<?php echo BASE_URL; ?>adm_form.php?student_id=<?php echo (int) $studentId; ?>" class="btn-secondary"><i class="fas fa-file-alt"></i> Admission Form</a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- FEE PLAN FORM -->
        <form id="feePlanForm" action="fee_plan.php?student_id=<?php echo (int) $studentId; ?>" method="post">
            <input type="hidden" name="save_fee_plan" value="1">
            <input type="hidden" name="student_id" value="<?php echo (int) $studentId; ?>">

            <div class="fee-card">
                <div class="card-header">
                    <h3><i class="far fa-file-invoice"></i> Manage Student Fee (Monthly Fee Parameters)</h3>
                </div>
                <div class="card-body">

                    <!-- Fee Grid - Top Row -->
                    <div class="fee-grid">
                        <!-- Payment Mode -->
                        <div class="field-group">
                            <label>Payment Mode</label>
                            <div class="field-value" style="background:white; display:flex; gap:20px; flex-wrap:wrap;">
                                <label style="display:flex; align-items:center; gap:6px; font-weight:400; cursor:pointer;">
                                    <input type="radio" name="payment_mode" value="monthly" <?php echo $paymentMode === 'monthly' ? 'checked' : ''; ?> style="accent-color:var(--primary); width:16px; height:16px;"> Monthly
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; font-weight:400; cursor:pointer;">
                                    <input type="radio" name="payment_mode" value="quarterly" <?php echo $paymentMode === 'quarterly' ? 'checked' : ''; ?> style="accent-color:var(--primary); width:16px; height:16px;"> Quarterly
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; font-weight:400; cursor:pointer;">
                                    <input type="radio" name="payment_mode" value="half_yearly" <?php echo $paymentMode === 'half_yearly' ? 'checked' : ''; ?> style="accent-color:var(--primary); width:16px; height:16px;"> Half Yearly
                                </label>
                                <label style="display:flex; align-items:center; gap:6px; font-weight:400; cursor:pointer;">
                                    <input type="radio" name="payment_mode" value="yearly" <?php echo $paymentMode === 'yearly' ? 'checked' : ''; ?> style="accent-color:var(--primary); width:16px; height:16px;"> Yearly
                                </label>
                            </div>
                        </div>

                        <!-- Discount Package -->
                        <div class="field-group">
                            <label>Discount Package</label>
                            <select name="discount_package">
                                <option value="">Select Discount</option>
                                <?php foreach ($discountPackages as $dp): ?>
                                <option value="<?php echo $dp['id']; ?>" <?php echo ($discountPackageId == $dp['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($dp['name']); ?> (<?php echo $dp['discount_percent']; ?>%)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Monthly Fee -->
                        <div class="field-group">
                            <label>Monthly Fee</label>
                            <div class="field-value"><span class="amount" id="monthlyFeeDisplay">0.00</span></div>
                        </div>

                        <!-- Course Package -->
                        <div class="field-group">
                            <label>Course Package</label>
                            <select name="course_package">
                                <option value="">Select Course</option>
                                <?php foreach ($coursePackages as $cp): ?>
                                <option value="<?php echo $cp['id']; ?>" data-amount="<?php echo (float) $cp['amount']; ?>" <?php echo ($coursePackageId == $cp['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($cp['name']); ?> (<?php echo number_format($cp['amount'], 2); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Miscellaneous Fee -->
                        <div class="field-group">
                            <label>Miscellaneous Fee</label>
                            <input type="number" step="0.01" min="0" name="miscellaneous_fee" placeholder="0.00" value="<?php echo $miscFee > 0 ? $miscFee : 0; ?>">
                        </div>

                        <!-- Old Balance -->
                        <div class="field-group">
                            <label>Old Balance</label>
                            <input type="number" step="0.01" min="0" name="old_balance" placeholder="0.00" value="<?php echo $oldBalance; ?>">
                        </div>

                        <!-- Discount Reason -->
                        <div class="field-group">
                            <label>Discount Reason</label>
                            <input type="text" name="discount_reason" placeholder="e.g. Sibling discount, Early bird" value="<?php echo e($discountReason); ?>">
                        </div>

                        <!-- Transport -->
                        <div class="field-group">
                            <label>Transport</label>
                            <input type="number" step="0.01" min="0" name="transport" placeholder="0.00" value="<?php echo $transportFee; ?>">
                        </div>
                    </div>

                    <!-- Fee Heads Table -->
                    <?php if ($feeHeads): ?>
                    <div style="margin-top: 20px;">
                        <h4 style="font-size:13px; font-weight:600; color:var(--gray-600); margin-bottom:10px;">Fee Heads Breakdown</h4>
                        <div class="fee-table-wrapper">
                            <table class="fee-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">#</th>
                                        <th>Fee Head</th>
                                        <th style="width:160px;">Amount (PKR)</th>
                                        <th style="width:160px;">Discount (PKR)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalAmount = 0;
                                    $totalDiscount = 0;
                                    foreach ($feeHeads as $i => $fh):
                                        $hid = (int) $fh['head_id'];
                                        $existing = $savedPlan[$hid] ?? null;
                                        $checked = $existing ? 'checked' : '';
                                        $amt = $existing ? (float) $existing['amount'] : (float) $fh['amount'];
                                        $disc = $existing ? (float) $existing['discount'] : 0;
                                        if ($checked) {
                                            $totalAmount += $amt;
                                            $totalDiscount += $disc;
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td>
                                            <label style="display:flex; align-items:center; gap:8px; font-weight:500; cursor:pointer;">
                                                <input type="hidden" name="heads[<?php echo $hid; ?>]" value="0">
                                                <input type="checkbox" name="heads[<?php echo $hid; ?>]" value="1" <?php echo $checked; ?> class="fh-check" style="width:16px; height:16px; accent-color: var(--primary);">
                                                <span><?php echo e($fh['head_name']); ?></span>
                                                <input type="hidden" name="head_names[<?php echo $hid; ?>]" value="<?php echo e($fh['head_name']); ?>">
                                            </label>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" class="fh-amount-input" name="amounts[<?php echo $hid; ?>]" value="<?php echo $amt; ?>" style="width:100%; padding:6px 10px; border:1px solid var(--gray-200); border-radius:6px; font-size:clamp(12px,1.1vw,14px); outline:none; transition:border-color 0.2s;">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" class="fh-discount-input" name="discounts[<?php echo $hid; ?>]" value="<?php echo $disc; ?>" style="width:100%; padding:6px 10px; border:1px solid var(--gray-200); border-radius:6px; font-size:clamp(12px,1.1vw,14px); outline:none; transition:border-color 0.2s;">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="2" class="total-label">Total:</td>
                                        <td><strong id="feeTotal"><?php echo number_format($totalAmount - $totalDiscount, 2); ?></strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <p style="font-size: 12px; color: var(--gray-400); margin-top: 16px;">
                        <i class="fas fa-info-circle"></i> Amounts are auto-filled from class fee settings — adjust if required.
                    </p>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <span class="note"><i class="far fa-clock"></i> All amounts are in PKR</span>
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <a href="<?php echo BASE_URL; ?>add_student.php" class="btn-secondary"><i class="fas fa-plus"></i> Add New Student</a>
                            <button type="submit" class="btn-primary" id="btnSaveFeePlan"><i class="fas fa-save"></i> Save Fee Plan</button>
                            <a href="<?php echo BASE_URL; ?>student.php?student=<?php echo (int) $studentId; ?>" class="btn-secondary"><i class="fas fa-user"></i> View Student</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<script>
(function () {
    var form = document.getElementById('feePlanForm');
    if (!form) return;
    
    // Calculate total
    function calcTotal() {
        var total = 0;
        document.querySelectorAll('.fh-check').forEach(function (cb) {
            if (cb.checked) {
                var tr = cb.closest('tr');
                var amt = parseFloat((tr.querySelector('.fh-amount-input') || {}).value || 0) || 0;
                var disc = parseFloat((tr.querySelector('.fh-discount-input') || {}).value || 0) || 0;
                total += amt - disc;
            }
        });
        var t = document.getElementById('feeTotal');
        if (t) t.textContent = total.toFixed(2);
        var display = document.getElementById('monthlyFeeDisplay');
        if (display) display.textContent = total.toFixed(2);
    }
    
    form.addEventListener('change', calcTotal);
    form.addEventListener('input', calcTotal);
    calcTotal();

    // Auto-check checkbox when amount is entered
    document.querySelectorAll('.fh-amount-input').forEach(function(input) {
        input.addEventListener('input', function() {
            var tr = this.closest('tr');
            var cb = tr.querySelector('.fh-check');
            if (parseFloat(this.value) > 0) {
                cb.checked = true;
                calcTotal();
            }
        });
    });

    // Update monthly fee display when course package changes
    var courseSelect = form.querySelector('select[name="course_package"]');
    if (courseSelect) {
        courseSelect.addEventListener('change', function() {
            var selected = this.options[this.selectedIndex];
            var amount = parseFloat(selected.getAttribute('data-amount') || selected.textContent.match(/\(([\d.]+)\)/)?.[1] || 0);
            var display = document.getElementById('monthlyFeeDisplay');
            if (display && amount > 0) {
                display.textContent = amount.toFixed(2);
            }
        });
    }
})();

function showToast(msg, type) {
    var types = { success: 'success', warning: 'warning', info: 'info', error: 'error' };
    var icons = { success: 'fa-check-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle', error: 'fa-exclamation-circle' };
    var el = document.createElement('div');
    el.className = 'toast-item ' + (types[type] || types.info);
    el.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + msg + '</span>';
    document.getElementById('toast-container').appendChild(el);
    setTimeout(function () { el.style.opacity = '0'; el.style.transform = 'translateX(30px)'; setTimeout(function () { el.remove(); }, 300); }, 3200);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>