<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Student Profile';

$student_id = (int) ($_GET['student'] ?? $_GET['student_id'] ?? 0);

$stu = null;
if ($student_id > 0) {
    $st1 = db_prepare("SELECT s.*, c.class_name, sec.section_name, l.locality_name
                       FROM students s
                       LEFT JOIN classes c ON c.class_id = s.class_id
                       LEFT JOIN sections sec ON sec.section_id = s.section_id
                       LEFT JOIN localities l ON l.locality_id = s.locality_id
                       WHERE s.student_id = ?");
    $st1->bind_param('i', $student_id);
    $st1->execute();
    $stu = $st1->get_result()->fetch_assoc();
}

if (!$stu) {
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="container mt-4 page-card" style="width:100%; text-align:center; padding:60px 20px;">
                <i class="fa fa-user-times" style="font-size:52px; color:#D5DBDB;"></i>
                <h3 style="font-size:18px; color:#111827; margin:14px 0 6px;">Student not found</h3>
                <p style="color:#8A99A8; font-size:13.5px;">The student you are looking for does not exist or may have been removed.</p>
                <a href="<?php echo BASE_URL; ?>manage_students.php" class="btn btn-default" style="margin-top:14px;"><i class="fa fa-arrow-left"></i> Back to Students</a>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

function hifi_challan_month($ch) {
    if (!empty($ch['month_year'])) {
        return date('M Y', strtotime((string) $ch['month_year']));
    }
    $m = (int) ($ch['month'] ?? 0);
    $y = (int) ($ch['year'] ?? 0);
    if ($m >= 1 && $m <= 12 && $y > 0) {
        return date('M Y', mktime(0, 0, 0, $m, 1, $y));
    }
    return '—';
}

function hifi_letter_grade($obtained, $total) {
    $total = (float) ($total > 0 ? $total : 0);
    $pct = $total > 0 ? ((float) $obtained / $total) * 100 : 0;
    if ($pct >= 90) return 'A+';
    if ($pct >= 80) return 'A';
    if ($pct >= 70) return 'B';
    if ($pct >= 60) return 'C';
    if ($pct >= 50) return 'D';
    if ($pct >= 40) return 'E';
    return 'F';
}

// Fee ledger
require_once __DIR__ . '/includes/ensure_schema.php';
$feeChallans = [];
$fc = db_prepare("SELECT fc.*, (SELECT COALESCE(SUM(amount),0) FROM fee_payments fp WHERE fp.challan_id = fc.challan_id) AS paid_amount
                  FROM fee_challans fc WHERE fc.student_id = ? ORDER BY fc.year DESC, fc.month DESC");
$fc->bind_param('i', $student_id);
$fc->execute();
$fr = $fc->get_result();
$feeTotal = 0.0;
if ($fr) {
    while ($row = $fr->fetch_assoc()) {
        $feeChallans[] = $row;
        $feeTotal += (float)$row['total_amount'] - (float)$row['paid_amount'];
    }
}

// Fee plan
$feePlan = [];
$fd = db_prepare('SELECT head_name, amount, discount FROM student_fee_plan WHERE student_id = ? ORDER BY id');
$fd->bind_param('i', $student_id);
$fd->execute();
$r2 = $fd->get_result();
if ($r2) { while ($row = $r2->fetch_assoc()) { $feePlan[] = $row; } }

// Global active fee heads
$globalHeads = [];
$gh = db_query('SELECT head_name, amount FROM fee_heads WHERE status = 1 ORDER BY head_id');
if ($gh) { while ($row = $gh->fetch_assoc()) { $globalHeads[] = $row; } }

// Documents
$docs = [];
try {
    $dd = db_prepare('SELECT doc_id, doc_type, file_path, uploaded_at FROM student_documents WHERE student_id = ? ORDER BY doc_id');
    $dd->bind_param('i', $student_id);
    $dd->execute();
    $r = $dd->get_result();
    if ($r) { while ($row = $r->fetch_assoc()) { $docs[] = $row; } }
} catch (Exception $e) {
    try {
        $rowCountKey = 'id';
        $dd = db_prepare('SELECT id AS doc_id, doc_type, file_path, created_at AS uploaded_at FROM student_documents WHERE student_id = ? ORDER BY id');
        $dd->bind_param('i', $student_id);
        $dd->execute();
        $r = $dd->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) { $docs[] = $row; } }
    } catch (Exception $e2) {}
}

// Student records (marks)
$examResults = [];
if (isset($stu['class_id'])) {
    try {
        $ex = db_prepare("SELECT ex.exam_name, sub.subject_name AS subject, m.obtained_marks, m.total_marks
                          FROM marks m
                          LEFT JOIN exams ex ON ex.exam_id = m.exam_id
                          LEFT JOIN subjects sub ON sub.subject_id = m.subject_id
                          WHERE m.student_id = ? ORDER BY m.mark_id DESC LIMIT 30");
        $ex->bind_param('i', $student_id);
        $ex->execute();
        $exr = $ex->get_result();
        if ($exr) { while ($row = $exr->fetch_assoc()) { $examResults[] = $row; } }
    } catch (Exception $e) {
        try {
            $ex = db_prepare("SELECT ex.exam_name, m.obtained_marks, m.total_marks
                              FROM marks m
                              LEFT JOIN exams ex ON ex.exam_id = m.exam_id
                              WHERE m.student_id = ? ORDER BY m.mark_id DESC LIMIT 30");
            $ex->bind_param('i', $student_id);
            $ex->execute();
            $exr = $ex->get_result();
            if ($exr) { while ($row = $exr->fetch_assoc()) { $examResults[] = $row; } }
        } catch (Exception $e2) {}
    }
}

// Attendance summary
$attSummary = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0, 'total' => 0];
$att = db_prepare("SELECT status, COUNT(*) c FROM attendance WHERE student_id = ? GROUP BY status");
$att->bind_param('i', $student_id);
$att->execute();
$attRes = $att->get_result();
if ($attRes) {
    while ($row = $attRes->fetch_assoc()) {
        $st = strtolower($row['status']);
        if (in_array($st, ['present', 'absent', 'late', 'leave'])) { $attSummary[$st] = (int)$row['c']; }
        $attSummary['total'] += (int)$row['c'];
    }
}

// Siblings (same family code)
$siblings = [];
if (!empty($stu['family_code'])) {
    $sib = db_prepare("SELECT student_id, first_name, last_name, gr_no, status FROM students WHERE family_code = ? AND student_id != ? AND status = 1");
    $fam = $stu['family_code'];
    $sib->bind_param('si', $fam, $student_id);
    $sib->execute();
    $sibRes = $sib->get_result();
    if ($sibRes) { while ($row = $sibRes->fetch_assoc()) { $siblings[] = $row; } }
}

$photoUrl = (!empty($stu['photo'])) ? BASE_URL . 'uploads/students/' . $stu['photo'] : null;
$docDirUrl = BASE_URL . 'uploads/students/documents/';
$dob = $stu['dob'] ? date('d M Y', strtotime($stu['dob'])) : '—';
$age = ($stu['dob'] && $stu['dob'] !== '0000-00-00') ? floor((time() - strtotime($stu['dob'])) / 31536000) : '—';
$genderLabel = ($stu['gender'] ?? '') === 'male' ? '<i class="fa fa-mars"></i> Male' : (($stu['gender'] ?? '') === 'female' ? '<i class="fa fa-venus"></i> Female' : '—');
$admittedDate = $stu['admission_date'] ? date('d M Y', strtotime($stu['admission_date'])) : '—';
$isActive = (int)$stu['status'] === 1;

include __DIR__ . '/includes/header.php';
?>
<style>
/* ================================================================
   STUDENT PROFILE - NEW DESIGN (cloned from EduPortal)
   ================================================================ */
@media (min-width: 768px) {
  .right_col { margin-left: 0 !important; width: 100% !important; }
}

#hideMe { animation: hideAnimation 0s ease-in 18s; animation-fill-mode: forwards; }
@keyframes hideAnimation { to { visibility: hidden; width: 0; height: 0; } }

@keyframes blinkEffect { 0% { color: #ff5733; } 50% { color: #007bff; } 100% { color: #ff5733; } }
.blinking-tab { font-weight: bold; transition: all 0.3s ease; animation: blinkEffect 1.5s infinite; }
.blinking-tab:hover { color: #0056b3 !important; }

@keyframes spin2 { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.custom-loader-wrapper { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; text-align: center; animation: fadeIn 0.4s ease-in; }
.custom-loader { width: 60px; height: 60px; border-radius: 50%; border: 6px solid #f3f3f3; border-top: 6px solid #FF7C1B; animation: spin2 1s linear infinite; margin-bottom: 15px; }
.custom-loader-text { color: #666; font-size: 16px; font-weight: 500; font-family: 'Segoe UI', sans-serif; }

.sp-banner { display: flex; align-items: center; gap: 20px; background: linear-gradient(135deg, #f0671e 0%, #e84393 60%, #9b59b6 100%); border-radius: 12px; padding: 24px 28px; margin-bottom: 20px; position: relative; color: #fff; box-shadow: 0 4px 18px rgba(240,103,30,0.25); flex-wrap: wrap; }
.sp-banner-photo { flex-shrink: 0; }
.sp-avatar { width: 100px; height: 100px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.85); object-fit: cover; background: rgba(255,255,255,0.2); display: block; }
.sp-banner-info { flex: 1; min-width: 0; }
.sp-name { margin: 0 0 4px; font-size: 26px; font-weight: 700; color: #fff; letter-spacing: 0.5px; line-height: 1.2; text-transform: uppercase; }
.sp-subtitle { margin: 0 0 12px; font-size: 13px; color: rgba(255,255,255,0.88); line-height: 1.5; }
.sp-dot { opacity: 0.7; margin: 0 2px; }
.sp-badges { display: flex; flex-wrap: wrap; gap: 7px; }
.sp-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 11px; border-radius: 20px; font-size: 12px; font-weight: 600; background: rgba(255,255,255,0.22); color: #fff; border: 1px solid rgba(255,255,255,0.35); white-space: nowrap; }
.sp-badge-active { background: rgba(39,174,96,0.85); border-color: rgba(39,174,96,0.5); }
.sp-badge-struckoff { background: rgba(231,76,60,0.85); border-color: rgba(231,76,60,0.5); }
.sp-banner-actions { flex-shrink: 0; display: flex; flex-direction: row; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; }
.sp-action-btn { font-size: 13px !important; padding: 6px 12px !important; white-space: nowrap; border-radius: 5px !important; }

.sp-card { border-radius: 10px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 20px; background: #fff; border: 1px solid #eee; }
.sp-card-header { padding: 12px 18px; font-size: 14px; font-weight: 700; color: #fff; letter-spacing: 0.3px; display: flex; align-items: center; gap: 8px; }
.sp-header-orange { background: linear-gradient(90deg, #f0671e, #ff8c42); }
.sp-header-teal   { background: linear-gradient(90deg, #16a085, #1abc9c); }
.sp-header-purple { background: linear-gradient(90deg, #8e44ad, #9b59b6); }
.sp-card-body { padding: 8px 0 4px; }
.sp-row { display: flex; align-items: flex-start; padding: 7px 18px; border-bottom: 1px solid #f4f4f4; font-size: 13px; transition: background 0.15s; }
.sp-row:last-child { border-bottom: none; }
.sp-row:hover { background: #fafafa; }
.sp-label { flex: 0 0 42%; color: #777; font-weight: 500; line-height: 1.4; padding-right: 8px; }
.sp-value { flex: 1; color: #2c3e50; font-weight: 400; line-height: 1.4; word-break: break-word; }

.sp-info-alert { margin: 10px 14px 6px; padding: 8px 12px; border-radius: 6px; background: #e8f7f3; border-left: 3px solid #16a085; font-size: 12px; color: #117a65; display: flex; align-items: flex-start; gap: 6px; line-height: 1.4; }
.sp-info-alert-purple { background: #f4ecfc; border-left-color: #8e44ad; color: #6c3483; }
.sp-send-link-row { padding: 12px 18px 8px; }
.sp-send-link-btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 18px; background: linear-gradient(90deg, #f0671e, #ff8c42); color: #fff !important; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: opacity 0.2s; box-shadow: 0 2px 8px rgba(240,103,30,0.3); }
.sp-send-link-btn:hover { opacity: 0.88; color: #fff !important; text-decoration: none; }

.att-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.att-toolbar-title { font-size: 15px; font-weight: 700; color: #fff; }
.att-history-btn { background: rgba(255,255,255,0.18); color: #fff !important; border: 1px solid rgba(255,255,255,0.4); border-radius: 6px; padding: 6px 14px; font-size: 12px; font-weight: 600; text-decoration: none !important; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s; }
.att-history-btn:hover { background: rgba(255,255,255,0.32); color: #fff !important; }
.att-year-select { height: 32px !important; width: auto; padding: 2px 10px; font-size: 12px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.4); background: rgba(255,255,255,0.18); color: #fff; }

.att-stats-row { display: flex; flex-wrap: wrap; gap: 12px; padding: 16px 18px 6px; }
.att-stat { flex: 1; min-width: 110px; background: #fafbfc; border: 1px solid #eee; border-radius: 8px; padding: 12px 10px; text-align: center; }
.att-stat-value { font-size: 22px; font-weight: 700; line-height: 1.2; }
.att-stat-label { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.4px; color: #888; margin-top: 4px; font-weight: 600; }
.att-stat-present .att-stat-value { color: #16a085; }
.att-stat-absent  .att-stat-value { color: #e74c3c; }
.att-stat-leave   .att-stat-value { color: #2980b9; }
.att-stat-la      .att-stat-value { color: #8e44ad; }

.sp-table-wrap { padding: 10px 18px 18px; overflow-x: auto; }
.sp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.sp-table th { background: #f4f6f8; color: #566573; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; padding: 10px 8px; text-align: center; border-bottom: 2px solid #e5e8ec; white-space: nowrap; }
.sp-table td { padding: 9px 8px; vertical-align: middle; border-bottom: 1px solid #f0f1f3; }
.sp-table tbody tr:nth-child(even) { background: #fbfbfc; }
.sp-table tbody tr:hover { background: #f5f8fc; }
.sp-table-empty { text-align: center; padding: 24px; color: #888; }

.sp-cards-row { margin-top: 0; }
@media (max-width: 991px) { .sp-banner { flex-wrap: wrap; } .sp-banner-actions { flex-direction: row; flex-wrap: wrap; justify-content: flex-end; } .sp-name { font-size: 20px; } }
@media (max-width: 767px) {
  .sp-banner { flex-direction: column; align-items: center; text-align: center; padding: 16px 16px; gap: 14px; }
  .sp-banner-photo { flex-shrink: 0; } .sp-banner-info { width: 100%; text-align: center; }
  .sp-badges { justify-content: center; }
  .sp-banner-actions { width: 100%; flex-direction: column; align-items: stretch; justify-content: flex-start; }
  .sp-action-btn { display: block; width: 100%; box-sizing: border-box; text-align: center; }
  .sp-avatar { width: 76px; height: 76px; } .sp-name { font-size: 18px; } .sp-label { flex: 0 0 45%; }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="container mt-4" style="width:100%;">

            <!-- Breadcrumb -->
            <div style="padding:6px 4px 12px; font-size:12.5px; color:#6B7280;">
                <a href="dashboard.php" style="color:#377dff;">Dashboard</a> <i class="fa fa-angle-double-right"></i>
                <a href="manage_students.php" style="color:#377dff;">Students</a> <i class="fa fa-angle-double-right"></i>
                Student Profile
            </div>

            <!-- ============ PROFILE BANNER ============ -->
            <div class="sp-banner">
                <div class="sp-banner-photo">
                    <?php if ($photoUrl): ?>
                        <img src="<?php echo e($photoUrl); ?>" class="sp-avatar" alt="Student Photo">
                    <?php else: ?>
                        <img src="<?php echo BASE_URL; ?>assets/img/upload.png" class="sp-avatar" alt="No Photo" onerror="this.onerror=null;this.src='<?php echo BASE_URL; ?>assets/img/loginbanner.png';">
                    <?php endif; ?>
                </div>
                <div class="sp-banner-info">
                    <h2 class="sp-name"><?php echo e(trim($stu['first_name'] . ' ' . $stu['last_name'])); ?></h2>
                    <p class="sp-subtitle">
                        <span><?php echo e($stu['father_name'] ?? '—'); ?></span>
                        <span class="sp-dot">&bull;</span>
                        <span><?php echo e(ucfirst($stu['gender'] ?? '—')); ?></span>
                        <span class="sp-dot">&bull;</span>
                        <span>GR# <?php echo e($stu['gr_no'] ?? '—'); ?></span>
                        <span class="sp-dot">&bull;</span>
                        <span><?php echo e($stu['class_name'] ?? '—'); ?> <?php echo e($stu['section_name'] ?? ''); ?></span>
                        <span class="sp-dot">&bull;</span>
                        <span>Session: <?php echo e($stu['session'] ?? '—'); ?></span>
                    </p>
                    <div class="sp-badges">
                        <span class="sp-badge"><i class="fa fa-birthday-cake"></i> Age: <?php echo e($age); ?> Yrs</span>
                        <span class="sp-badge"><i class="fa fa-venus-mars"></i> <?php echo e(ucfirst($stu['gender'] ?? '—')); ?></span>
                        <span class="sp-badge"><i class="fa fa-calendar"></i> Admitted: <?php echo e($admittedDate); ?></span>
                        <span class="sp-badge <?php echo $isActive ? 'sp-badge-active' : 'sp-badge-struckoff'; ?>"><i class="fa fa-circle"></i> <?php echo $isActive ? 'Active' : 'Struck Off'; ?></span>
                    </div>
                </div>
                <div class="sp-banner-actions">
                    <a href="<?php echo BASE_URL; ?>add_student.php?student=<?php echo (int)$stu['student_id']; ?>#sec" class="btn btn-success sp-action-btn"><i class="fa fa-edit"></i> Edit Profile</a>
                    <a href="<?php echo BASE_URL; ?>fee_plan.php?student_id=<?php echo (int)$stu['student_id']; ?>" class="btn btn-warning sp-action-btn"><i class="fa fa-money"></i> Fee Plan</a>
                    <a href="<?php echo BASE_URL; ?>student_fee_payments_view.php?student_id=<?php echo (int)$stu['student_id']; ?>" class="btn btn-info sp-action-btn"><i class="fa fa-credit-card"></i> Payments</a>
                    <a href="<?php echo BASE_URL; ?>manage_students.php" class="btn btn-default sp-action-btn"><i class="fa fa-arrow-left"></i> Back</a>
                </div>
            </div>

            <!-- ============ TABS ============ -->
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active">
                    <a href="#tab_content4" role="tab" id="profile-tab4" data-toggle="tab" aria-expanded="false"><i class="fa fa-user"></i> Student Profile</a>
                </li>
                <li role="presentation"><a href="#feeLedger" role="tab" data-toggle="tab"><i class="fa fa-money"></i> Fee History</a></li>
                <li role="presentation"><a href="#studentResults" role="tab" data-toggle="tab"><i class="fa fa-trophy"></i> Results</a></li>
                <li role="presentation"><a href="#attendanceHistory" role="tab" data-toggle="tab"><i class="fa fa-calendar-check-o"></i> Attendance</a></li>
                <li role="presentation"><a href="#SiblingsTab" role="tab" data-toggle="tab"><i class="fa fa-users"></i> Siblings</a></li>
                <li role="presentation"><a href="#studentDocuments" role="tab" data-toggle="tab"><i class="fa fa-file-text-o"></i> Documents</a></li>
            </ul>

            <div id="myTabContent" class="tab-content" style="padding:1%;">

                <!-- ============ STUDENT PROFILE TAB ============ -->
                <div role="tabpanel" class="tab-pane active" id="tab_content4">
                    <div class="row sp-cards-row">

                        <!-- Column 1: Student Information -->
                        <div class="col-md-4 col-sm-12">
                            <div class="sp-card">
                                <div class="sp-card-header sp-header-orange"><i class="fa fa-user"></i> Student Information</div>
                                <div class="sp-card-body">
                                    <div class="sp-row"><span class="sp-label">Father Name</span><span class="sp-value"><?php echo e($stu['father_name'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Father Occupation</span><span class="sp-value"><?php echo e($stu['father_occupation'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Mother Name</span><span class="sp-value"><?php echo e($stu['mother_name'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Family Code</span><span class="sp-value"><?php echo e($stu['family_code'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Religion</span><span class="sp-value"><?php echo e($stu['religion'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">CNIC</span><span class="sp-value"><?php echo e($stu['father_cnic'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">B-Form</span><span class="sp-value"><?php echo e($stu['form_b_no'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">GR No</span><span class="sp-value"><?php echo e($stu['gr_no'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Roll No</span><span class="sp-value"><?php echo e($stu['roll_no'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Guardian</span><span class="sp-value"><?php echo e($stu['guardian_name'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Date of Birth</span><span class="sp-value"><?php echo e($dob); ?></span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Column 2: Contact & Address Details -->
                        <div class="col-md-4 col-sm-12">
                            <div class="sp-card">
                                <div class="sp-card-header sp-header-teal"><i class="fa fa-address-book"></i> Contact &amp; Address Details</div>
                                <div class="sp-card-body">
                                    <div class="sp-info-alert"><i class="fa fa-info-circle"></i> Contact information for student and family members.</div>
                                    <div class="sp-row"><span class="sp-label">Cell Number</span><span class="sp-value"><?php echo e($stu['phone'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">WhatsApp Number</span><span class="sp-value"><?php echo e($stu['whatsapp_number'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Father Contact</span><span class="sp-value"><?php echo e($stu['father_cellno'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Mother Contact</span><span class="sp-value"><?php echo e($stu['mother_cell'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Home Contact</span><span class="sp-value"><?php echo e($stu['home_number'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Email</span><span class="sp-value"><?php echo e($stu['email'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Address</span><span class="sp-value"><?php echo e($stu['address'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Locality</span><span class="sp-value"><?php echo e($stu['locality_name'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">City</span><span class="sp-value"><?php echo e($stu['city'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">State / Province</span><span class="sp-value"><?php echo e($stu['state'] ?? '—'); ?></span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Column 3: Academic / Fee Details -->
                        <div class="col-md-4 col-sm-12">
                            <div class="sp-card">
                                <div class="sp-card-header sp-header-purple"><i class="fa fa-graduation-cap"></i> Academic &amp; Admission</div>
                                <div class="sp-card-body">
                                    <div class="sp-row"><span class="sp-label">Admission Date</span><span class="sp-value"><?php echo e($admittedDate); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Board / Council</span><span class="sp-value"><?php echo e($stu['board_council'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Group / Shift</span><span class="sp-value"><?php echo e($stu['group_shift'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Admission Source</span><span class="sp-value"><?php echo e($stu['admission_source'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Caste</span><span class="sp-value"><?php echo e($stu['caste'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Place of Birth</span><span class="sp-value"><?php echo e($stu['place_of_birth'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Previous Class</span><span class="sp-value"><?php echo e($stu['old_class'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Previous School</span><span class="sp-value"><?php echo e($stu['old_school'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Admission Form No</span><span class="sp-value"><?php echo e($stu['admission_form_no'] ?? '—'); ?></span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Plan Card -->
                        <div class="col-md-4 col-sm-12">
                            <div class="sp-card">
                                <div class="sp-card-header sp-header-orange"><i class="fa fa-money"></i> Fee Plan</div>
                                <div class="sp-card-body">
                                    <?php if (count($feePlan) > 0): ?>
                                        <div class="sp-table-wrap" style="padding:8px 18px 14px;">
                                            <table class="sp-table">
                                                <tbody>
                                                    <?php $t = 0.0; foreach ($feePlan as $f): $t += (float)$f['amount'] - (float)$f['discount']; ?>
                                                        <tr><td style="text-align:left;"><?php echo e($f['head_name']); ?></td><td style="text-align:right; font-weight:700;">Rs. <?php echo number_format((float)$f['amount'] - (float)$f['discount'], 2); ?></td></tr>
                                                    <?php endforeach; ?>
                                                    <tr class="sp-table-total"><td style="text-align:left;">Total</td><td style="text-align:right; color:#f0671e;">Rs. <?php echo number_format($t, 2); ?></td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="font-size:13px; color:#8A99A8; margin:10px 18px;">No per-student fee plan set yet.</p>
                                        <?php if (count($globalHeads) > 0): ?>
                                            <div class="sp-table-wrap" style="padding:4px 18px 10px;">
                                                <table class="sp-table">
                                                    <tbody>
                                                        <?php foreach ($globalHeads as $g): ?>
                                                            <tr><td style="text-align:left;"><?php echo e($g['head_name']); ?></td><td style="text-align:right;">Rs. <?php echo number_format((float)$g['amount'], 2); ?></td></tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                        <div style="padding:0 18px 12px;">
                                            <a href="<?php echo BASE_URL; ?>fee_plan.php?student_id=<?php echo (int)$stu['student_id']; ?>" class="sp-send-link-btn" style="width:100%; justify-content:center;"><i class="fa fa-pencil"></i> Set Fee Plan</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile App Login Details -->
                        <div class="col-md-4 col-sm-12">
                            <div class="sp-card">
                                <div class="sp-card-header sp-header-purple"><i class="fa fa-mobile"></i> Mobile App Login Details</div>
                                <div class="sp-card-body">
                                    <div class="sp-info-alert sp-info-alert-purple"><i class="fa fa-info-circle"></i> Parent app login credentials for this student's account.</div>
                                    <div class="sp-row"><span class="sp-label">Username</span><span class="sp-value"><?php echo e($stu['gr_no'] ?? '—'); ?></span></div>
                                    <div class="sp-row"><span class="sp-label">Password</span><span class="sp-value">••••••••</span></div>
                                    <div class="sp-send-link-row">
                                        <a href="<?php echo BASE_URL; ?>sms_settings_user.php" class="sp-send-link-btn"><i class="fa fa-paper-plane"></i> Send App Link</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ============ FEE HISTORY TAB ============ -->
                <div role="tabpanel" class="tab-pane" id="feeLedger">
                    <div class="sp-card" style="margin-bottom:0;">
                        <div class="sp-card-header sp-header-orange att-toolbar">
                            <span class="att-toolbar-title"><i class="fa fa-money"></i> Fee Ledger</span>
                            <a class="att-history-btn" id="feePrintReportBtn" href="<?php echo BASE_URL; ?>print_unpaid_fee_new.php?grNo=<?php echo urlencode($stu['gr_no'] ?? ''); ?>&monthly=1&reportType=both" target="_blank"><i class="fa fa-print"></i> Print Report</a>
                        </div>
                        <div class="sp-table-wrap">
                            <table class="sp-table">
                                <thead>
                                    <tr><th>Sr.</th><th>Challan No</th><th>Month</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (count($feeChallans) > 0): $s_no = 1; ?>
                                        <?php foreach ($feeChallans as $ch): $bal = (float)$ch['total_amount'] - (float)$ch['paid_amount']; ?>
                                            <tr>
                                                <td><?php echo $s_no++; ?></td>
                                                <td><?php echo e($ch['challan_no'] ?? $ch['challan_id'] ?? '—'); ?></td>
                                                <td><?php echo e(hifi_challan_month($ch)); ?></td>
                                                <td>Rs. <?php echo number_format((float)$ch['total_amount'], 2); ?></td>
                                                <td style="color:#16a085;">Rs. <?php echo number_format((float)$ch['paid_amount'], 2); ?></td>
                                                <td style="color:#e74c3c; font-weight:700;">Rs. <?php echo number_format($bal, 2); ?></td>
                                                <td><span class="sp-badge <?php echo $bal <= 0 ? 'sp-badge-active' : ''; ?>"><?php echo $bal <= 0 ? 'Paid' : (($ch['payment_status'] ?? 'unpaid') == 'partial' ? 'Partial' : 'Unpaid'); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="sp-table-empty">No fee challans found for this student.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============ RESULTS TAB ============ -->
                <div role="tabpanel" class="tab-pane" id="studentResults">
                    <div class="sp-card" style="margin-bottom:0;">
                        <div class="sp-card-header sp-header-orange att-toolbar">
                            <span class="att-toolbar-title"><i class="fa fa-trophy"></i> Exam Results</span>
                            <a class="att-history-btn" href="<?php echo BASE_URL; ?>reportcards.php?student_id=<?php echo (int)$student_id; ?>" target="_blank"><i class="fa fa-print"></i> Report Card</a>
                        </div>
                        <div class="sp-table-wrap">
                            <table class="sp-table">
                                <thead><tr><th>Sr.</th><th>Exam</th><th>Subject</th><th>Marks</th><th>Obt.</th><th>Grade</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php if (count($examResults) > 0): $s_no = 1; ?>
                                        <?php foreach ($examResults as $mr): ?>
                                            <tr>
                                                <td><?php echo $s_no++; ?></td>
                                                <td><?php echo e($mr['exam_name'] ?? '—'); ?></td>
                                                <td><?php echo e($mr['subject'] ?? '—'); ?></td>
                                                <td><?php echo e($mr['total_marks'] ?? '—'); ?></td>
                                                <td><?php echo e($mr['obtained_marks'] ?? '—'); ?></td>
                                                <td><?php echo e($mr['grade'] ?? hifi_letter_grade($mr['obtained_marks'] ?? 0, $mr['total_marks'] ?? 100)); ?></td>
                                                <td><span class="sp-badge <?php echo (float)$mr['obtained_marks'] >= (float)$mr['total_marks'] * 0.4 ? 'sp-badge-active' : 'sp-badge-struckoff'; ?>"><?php echo e(ucfirst($mr['status'] ?? (((float)$mr['obtained_marks'] >= (float)$mr['total_marks'] * 0.4) ? 'Pass' : 'Fail'))); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="sp-table-empty">No exam results found for this student.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============ ATTENDANCE TAB ============ -->
                <div role="tabpanel" class="tab-pane" id="attendanceHistory">
                    <div class="sp-card" style="margin-bottom:0;">
                        <div class="sp-card-header sp-header-orange att-toolbar">
                            <span class="att-toolbar-title"><i class="fa fa-calendar-check-o"></i> Attendance Summary</span>
                            <a class="att-history-btn" href="<?php echo BASE_URL; ?>mark_attendanceReport_list.php?student_id=<?php echo (int)$student_id; ?>" target="_blank"><i class="fa fa-print"></i> Full Attendance History</a>
                        </div>
                        <div class="att-stats-row">
                            <div class="att-stat att-stat-present"><div class="att-stat-value"><?php echo $attSummary['present']; ?></div><div class="att-stat-label">Present</div></div>
                            <div class="att-stat att-stat-absent"><div class="att-stat-value"><?php echo $attSummary['absent']; ?></div><div class="att-stat-label">Absent</div></div>
                            <div class="att-stat att-stat-la"><div class="att-stat-value"><?php echo $attSummary['late']; ?></div><div class="att-stat-label">Late</div></div>
                            <div class="att-stat att-stat-leave"><div class="att-stat-value"><?php echo $attSummary['leave']; ?></div><div class="att-stat-label">Leave</div></div>
                            <div class="att-stat"><div class="att-stat-value"><?php echo $attSummary['total']; ?></div><div class="att-stat-label">Total Records</div></div>
                        </div>
                        <div class="sp-table-wrap">
                            <table class="sp-table">
                                <thead><tr><th>Sr.</th><th>Present</th><th>Absent</th><th>Late</th><th>Leave</th><th>Attendance %</th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td>1</td>
                                        <td><?php echo $attSummary['present']; ?></td>
                                        <td><?php echo $attSummary['absent']; ?></td>
                                        <td><?php echo $attSummary['late']; ?></td>
                                        <td><?php echo $attSummary['leave']; ?></td>
                                        <td><?php echo $attSummary['total'] > 0 ? number_format($attSummary['present'] / $attSummary['total'] * 100, 1) : '0'; ?>%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============ SIBLINGS TAB ============ -->
                <div role="tabpanel" class="tab-pane" id="SiblingsTab">
                    <div class="sp-card" style="margin-bottom:0;">
                        <div class="sp-card-header sp-header-orange att-toolbar">
                            <span class="att-toolbar-title"><i class="fa fa-users"></i> Siblings</span>
                        </div>
                        <div class="sp-table-wrap">
                            <table class="sp-table">
                                <thead><tr><th>Sr.</th><th>Name</th><th>GR No</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php if (count($siblings) > 0): $s_no = 1; ?>
                                        <?php foreach ($siblings as $sib): ?>
                                            <tr>
                                                <td><?php echo $s_no++; ?></td>
                                                <td><a href="<?php echo BASE_URL; ?>student.php?student=<?php echo (int)$sib['student_id']; ?>"><?php echo e($sib['first_name'] . ' ' . $sib['last_name']); ?></a></td>
                                                <td><?php echo e($sib['gr_no'] ?? '—'); ?></td>
                                                <td><span class="sp-badge sp-badge-active">Active</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="sp-table-empty">No siblings found (no family code assigned).</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============ DOCUMENTS TAB ============ -->
                <div role="tabpanel" class="tab-pane" id="studentDocuments">
                    <div class="sp-card" style="margin-bottom:0;">
                        <div class="sp-card-header sp-header-orange att-toolbar">
                            <span class="att-toolbar-title"><i class="fa fa-file-text-o"></i> Student Documents</span>
                            <a class="att-history-btn" href="<?php echo BASE_URL; ?>add_student.php?student=<?php echo (int)$stu['student_id']; ?>#sec"><i class="fa fa-upload"></i> Upload Documents</a>
                        </div>
                        <div class="row" style="padding:16px 18px;">
                            <?php if (count($docs) > 0): ?>
                                <?php foreach ($docs as $d): $isPdf = $d['file_path'] && strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION)) === 'pdf'; ?>
                                    <div class="col-xs-6 col-sm-4 col-md-3" style="padding:6px;">
                                        <div class="sp-card" style="margin:0;">
                                            <div style="height:100px; background:#f4f6f8; display:flex; align-items:center; justify-content:center; overflow:hidden; border-radius:8px 8px 0 0;">
                                                <?php if ($isPdf): ?>
                                                    <i class="fa fa-file-pdf-o" style="font-size:34px; color:#e74c3c;"></i>
                                                <?php elseif ($d['file_path']): ?>
                                                    <img src="<?php echo e($docDirUrl . $d['file_path']); ?>" style="width:100%; height:100%; object-fit:cover;" alt="" onerror="this.style.display='none';">
                                                <?php else: ?>
                                                    <i class="fa fa-file-alt" style="font-size:34px; color:#9AA7B4;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div style="padding:8px 10px; font-size:12.5px; font-weight:600; color:#2A3F54; display:flex; justify-content:space-between; align-items:center;">
                                                <?php echo e($d['doc_type'] ?? 'Document'); ?>
                                                <?php if ($d['file_path']): ?><a href="<?php echo e($docDirUrl . $d['file_path']); ?>" target="_blank" style="color:#f0671e;"><i class="fa fa-external-link"></i></a><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align:center; padding:40px 20px; color:#888; font-size:13px;">No documents uploaded for this student.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
