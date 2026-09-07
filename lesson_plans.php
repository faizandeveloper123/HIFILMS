<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Lesson Planner';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? 'teacher';

$isAdmin = in_array($user_role, ['admin', 'hod']);

db_query("CREATE TABLE IF NOT EXISTS lesson_plans (
    id INT(11) NOT NULL AUTO_INCREMENT,
    teacher_id INT(11) NOT NULL,
    class_id INT(11) NOT NULL,
    section_id INT(11) NOT NULL,
    subject_id INT(11) NOT NULL,
    week_start DATE NOT NULL,
    day VARCHAR(10) NOT NULL,
    topic VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
    approved_by INT(11) DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_week (week_start),
    KEY idx_teacher (teacher_id),
    KEY idx_class_section (class_id, section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$teacher_id_logged = 0;
try {
    $res = db_prepare("SELECT employee_id FROM employees WHERE user_id=? LIMIT 1");
    $res->bind_param('i', $user_id);
    $res->execute();
    $r = $res->get_result()->fetch_assoc();
    if ($r) $teacher_id_logged = (int)$r['employee_id'];
} catch (Throwable $e) {
    $teacher_id_logged = $user_id;
}

$classes = [];
try {
    $res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
    while ($row = $res->fetch_assoc()) { $classes[] = $row; }
} catch (Throwable $e) {}

$all_sections = [];
try {
    $res = db_query("SELECT s.section_id, s.class_id, s.section_name, c.class_name FROM sections s JOIN classes c ON c.class_id=s.class_id ORDER BY c.class_name, s.section_name");
    while ($row = $res->fetch_assoc()) { $all_sections[] = $row; }
} catch (Throwable $e) {}

$all_subjects = [];
try {
    $res = db_query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
    while ($row = $res->fetch_assoc()) { $all_subjects[] = $row; }
} catch (Throwable $e) {}

$teachers = [];
try {
    $res = db_query("SELECT e.user_id, CONCAT(e.first_name, ' ', e.last_name) AS tname FROM employees e WHERE e.user_id IS NOT NULL ORDER BY e.first_name");
    while ($row = $res->fetch_assoc()) { $teachers[(int)$row['user_id']] = $row['tname']; }
} catch (Throwable $e) {}
if (empty($teachers[$user_id])) {
    $teachers[$user_id] = $_SESSION['user_name'] ?? 'Teacher';
}

$today = date('Y-m-d');
$dayOfWeek = date('N', strtotime($today));
$monday = date('Y-m-d', strtotime($today . ' -' . ($dayOfWeek - 1) . ' days'));

include __DIR__ . '/includes/header.php';
?>

<style>
.lp-wrap { padding: 0; }
.lp-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.lp-card-header { padding: 14px 20px; background: linear-gradient(135deg, #ff9800, #f97316); color: #fff; font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px; }
.lp-filters { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; padding: 16px 20px; background: #FAFBFC; border-bottom: 1px solid #E5E7EB; }
.lp-filters label { font-weight: 600; font-size: 12px; color: #6B7280; margin-bottom: 4px; display: block; }
.lp-filters select, .lp-filters input[type="date"] { height: 38px; border-radius: 10px; border: 1px solid #D1D5DB; padding: 0 10px; font-size: 13px; background: #fff; }
.lp-filters .btn-search { height: 38px; padding: 0 20px; border-radius: 10px; background: #f97316; color: #fff; border: none; font-weight: 600; cursor: pointer; }
.lp-filters .btn-search:hover { background: #ea580c; }
.lp-week-nav { display: flex; align-items: center; gap: 10px; padding: 10px 20px; background: #fff; border-bottom: 1px solid #E5E7EB; }
.lp-week-nav button { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #D1D5DB; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.lp-week-nav button:hover { background: #F3F4F6; }
.lp-week-nav .week-label { font-weight: 700; font-size: 13px; color: #111827; }
.lp-grid-wrap { overflow-x: auto; padding: 0; }
.lp-grid { width: 100%; border-collapse: collapse; font-size: 13px; }
.lp-grid thead th { background: #1F2937; color: #fff; padding: 10px 8px; text-align: left; font-weight: 600; white-space: nowrap; position: sticky; top: 0; z-index: 2; }
.lp-grid thead th.lp-day-col { text-align: center; min-width: 180px; }
.lp-grid thead th.lp-class-col { min-width: 120px; }
.lp-grid tbody td { padding: 8px; border: 1px solid #E5E7EB; vertical-align: top; }
.lp-grid tbody tr:hover { background: #FFFBEB; }
.lp-cell { min-height: 42px; border-radius: 8px; padding: 6px 8px; cursor: pointer; transition: all 0.15s; }
.lp-cell:hover { background: #FFF7ED; box-shadow: 0 1px 4px rgba(249,115,22,0.15); }
.lp-cell.empty { border: 1px dashed #D1D5DB; background: #F9FAFB; text-align: center; color: #9CA3AF; font-style: italic; font-size: 12px; }
.lp-cell.empty:hover { border-color: #f97316; background: #FFF7ED; color: #f97316; }
.lp-plan-item { background: #fff; border: 1px solid #E5E7EB; border-radius: 8px; padding: 6px 8px; margin-bottom: 4px; position: relative; }
.lp-plan-item:last-child { margin-bottom: 0; }
.lp-plan-topic { font-weight: 600; color: #111827; font-size: 12.5px; line-height: 1.3; }
.lp-plan-subject { font-size: 11px; color: #6B7280; margin-top: 2px; }
.lp-plan-teacher { font-size: 10px; color: #9CA3AF; margin-top: 2px; }
.lp-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
.lp-badge-draft { background: #F3F4F6; color: #6B7280; }
.lp-badge-submitted { background: #DBEAFE; color: #1D4ED8; }
.lp-badge-approved { background: #D1FAE5; color: #059669; }
.lp-badge-rejected { background: #FEE2E2; color: #DC2626; }
.lp-actions { display: flex; gap: 4px; margin-top: 4px; }
.lp-actions button { padding: 2px 8px; border-radius: 6px; border: none; font-size: 10px; font-weight: 600; cursor: pointer; }
.lp-btn-approve { background: #D1FAE5; color: #059669; }
.lp-btn-approve:hover { background: #A7F3D0; }
.lp-btn-reject { background: #FEE2E2; color: #DC2626; }
.lp-btn-reject:hover { background: #FECACA; }
.lp-btn-edit { background: #DBEAFE; color: #1D4ED8; }
.lp-btn-edit:hover { background: #BFDBFE; }
.lp-btn-delete { background: #F3F4F6; color: #6B7280; }
.lp-btn-delete:hover { background: #E5E7EB; }
.lp-fab { position: fixed; bottom: 28px; right: 28px; width: 56px; height: 56px; border-radius: 50%; background: #f97316; color: #fff; border: none; font-size: 24px; cursor: pointer; box-shadow: 0 4px 16px rgba(249,115,22,0.4); display: flex; align-items: center; justify-content: center; z-index: 999; transition: transform 0.2s; }
.lp-fab:hover { transform: scale(1.1); }
.lp-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1050; align-items: center; justify-content: center; }
.lp-modal-overlay.active { display: flex; }
.lp-modal { background: #fff; border-radius: 16px; width: 95%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.lp-modal-header { padding: 16px 20px; background: linear-gradient(135deg, #ff9800, #f97316); color: #fff; border-radius: 16px 16px 0 0; display: flex; align-items: center; justify-content: space-between; }
.lp-modal-header h4 { margin: 0; font-size: 16px; font-weight: 700; }
.lp-modal-header button { background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; opacity: 0.8; }
.lp-modal-header button:hover { opacity: 1; }
.lp-modal-body { padding: 20px; }
.lp-modal-body .form-group { margin-bottom: 14px; }
.lp-modal-body label { font-weight: 600; font-size: 12px; color: #374151; display: block; margin-bottom: 4px; }
.lp-modal-body select, .lp-modal-body input[type="text"], .lp-modal-body textarea { width: 100%; padding: 8px 12px; border-radius: 10px; border: 1px solid #D1D5DB; font-size: 13px; }
.lp-modal-body textarea { min-height: 80px; resize: vertical; }
.lp-modal-footer { padding: 14px 20px; border-top: 1px solid #E5E7EB; display: flex; gap: 8px; justify-content: flex-end; }
.lp-modal-footer .btn { padding: 8px 20px; border-radius: 10px; font-weight: 600; font-size: 13px; border: none; cursor: pointer; }
.lp-btn-draft { background: #E5E7EB; color: #374151; }
.lp-btn-draft:hover { background: #D1D5DB; }
.lp-btn-submit { background: #f97316; color: #fff; }
.lp-btn-submit:hover { background: #ea580c; }
.lp-btn-cancel { background: #F3F4F6; color: #6B7280; }
.lp-btn-cancel:hover { background: #E5E7EB; }
.lp-stats { display: flex; gap: 12px; flex-wrap: wrap; padding: 12px 20px; background: #fff; border-bottom: 1px solid #E5E7EB; }
.lp-stat { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #6B7280; }
.lp-stat-dot { width: 10px; height: 10px; border-radius: 50%; }
.lp-print-btn { background: #fff; color: #f97316; border: 1px solid #f97316; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; }
.lp-print-btn:hover { background: #FFF7ED; }
@media print {
    .lp-filters, .lp-week-nav, .lp-fab, .lp-modal-overlay, .lp-actions, .lp-print-btn { display: none !important; }
    .lp-card { border: none; box-shadow: none; }
    .lp-grid thead th { background: #1F2937 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .lp-badge { border: 1px solid #ccc; }
}
</style>

<div class="main-content">
<div class="container-fluid">
    <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px 6px; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;"><i class="fa fa-calendar-check-o"></i> Lesson Planner</h3>
            <div style="font-size:12px; color:#6B7280; margin-top:4px;">
                <a href="<?php echo BASE_URL; ?>dashboard.php" style="color:#f97316; text-decoration:none;">Dashboard</a>
                &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; Lesson Planner
            </div>
        </div>
        <div style="display:flex; gap:8px;">
            <button class="lp-print-btn" onclick="window.print();"><i class="fa fa-print"></i> Print</button>
        </div>
    </div>

    <div class="lp-card">
        <div class="lp-card-header">
            <i class="fa fa-calendar"></i> Weekly Lesson Plans
        </div>

        <div class="lp-filters">
            <div style="flex:0 0 auto;">
                <label>Week Starting</label>
                <input type="date" id="lp_week_start" value="<?php echo e($monday); ?>">
            </div>
            <div style="flex:0 0 auto;">
                <label>Class</label>
                <select id="lp_class">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo (int)$c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:0 0 auto;">
                <label>Section</label>
                <select id="lp_section">
                    <option value="0">All Sections</option>
                </select>
            </div>
            <div style="flex:0 0 auto;">
                <button class="btn-search" onclick="loadLessonPlans()"><i class="fa fa-search"></i> Search</button>
            </div>
        </div>

        <div class="lp-week-nav">
            <button onclick="changeWeek(-7)" title="Previous Week"><i class="fa fa-chevron-left"></i></button>
            <span class="week-label" id="lp_week_label">-</span>
            <button onclick="changeWeek(7)" title="Next Week"><i class="fa fa-chevron-right"></i></button>
            <button onclick="goToday()" style="width:auto; padding:0 12px; font-size:11px; font-weight:600;">Today</button>
        </div>

        <div class="lp-stats" id="lp_stats"></div>

        <div class="lp-grid-wrap">
            <table class="lp-grid" id="lp_grid">
                <thead>
                    <tr>
                        <th class="lp-class-col">Class / Section</th>
                        <th class="lp-day-col">Monday</th>
                        <th class="lp-day-col">Tuesday</th>
                        <th class="lp-day-col">Wednesday</th>
                        <th class="lp-day-col">Thursday</th>
                        <th class="lp-day-col">Friday</th>
                        <th class="lp-day-col">Saturday</th>
                    </tr>
                </thead>
                <tbody id="lp_grid_body">
                    <tr><td colspan="7" style="text-align:center; padding:40px; color:#9CA3AF;">Select filters and click Search</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<button class="lp-fab" onclick="openModal()" title="Add Lesson Plan"><i class="fa fa-plus"></i></button>

<div class="lp-modal-overlay" id="lp_modal">
    <div class="lp-modal">
        <div class="lp-modal-header">
            <h4 id="lp_modal_title"><i class="fa fa-plus-circle"></i> New Lesson Plan</h4>
            <button onclick="closeModal()">&times;</button>
        </div>
        <div class="lp-modal-body">
            <input type="hidden" id="lp_edit_id" value="">
            <div class="form-group">
                <label>Class *</label>
                <select id="lp_form_class" onchange="loadFormSections()">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo (int)$c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Section *</label>
                <select id="lp_form_section">
                    <option value="">Select Class First</option>
                </select>
            </div>
            <div class="form-group">
                <label>Subject *</label>
                <select id="lp_form_subject">
                    <option value="">Select Class First</option>
                </select>
            </div>
            <div class="form-group">
                <label>Day *</label>
                <select id="lp_form_day">
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                </select>
            </div>
            <div class="form-group">
                <label>Topic *</label>
                <input type="text" id="lp_form_topic" placeholder="e.g. Chapter 5 - Photosynthesis">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="lp_form_desc" placeholder="Detailed description of the lesson..."></textarea>
            </div>
        </div>
        <div class="lp-modal-footer">
            <button class="btn lp-btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="btn lp-btn-draft" onclick="savePlan('draft')"><i class="fa fa-save"></i> Save Draft</button>
            <button class="btn lp-btn-submit" onclick="savePlan('submitted')"><i class="fa fa-paper-plane"></i> Submit</button>
        </div>
    </div>
</div>

<script>
var LP_AJAX = '<?php echo BASE_URL; ?>ajax_lesson_plans.php';
var LP_USER_ROLE = '<?php echo e($user_role); ?>';
var LP_USER_ID = <?php echo (int)$user_id; ?>;
var LP_PLAN_DATA = [];
var LP_CLASSES = <?php echo json_encode($classes); ?>;
var LP_SECTIONS = <?php echo json_encode($all_sections); ?>;
var LP_SUBJECTS = <?php echo json_encode($all_subjects); ?>;
var LP_TEACHERS = <?php echo json_encode($teachers); ?>;

function getWeekStart() {
    return document.getElementById('lp_week_start').value;
}

function setWeekStart(d) {
    document.getElementById('lp_week_start').value = d;
}

function changeWeek(offset) {
    var d = new Date(getWeekStart());
    d.setDate(d.getDate() + offset);
    var ds = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    setWeekStart(ds);
    loadLessonPlans();
}

function goToday() {
    var now = new Date();
    var dow = now.getDay();
    var diff = dow === 0 ? -6 : 1 - dow;
    var mon = new Date(now);
    mon.setDate(now.getDate() + diff);
    var ds = mon.getFullYear() + '-' + String(mon.getMonth()+1).padStart(2,'0') + '-' + String(mon.getDate()).padStart(2,'0');
    setWeekStart(ds);
    loadLessonPlans();
}

function updateWeekLabel() {
    var ws = new Date(getWeekStart());
    var we = new Date(ws);
    we.setDate(we.getDate() + 5);
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var label = ws.getDate() + ' ' + months[ws.getMonth()] + ' - ' + we.getDate() + ' ' + months[we.getMonth()] + ', ' + we.getFullYear();
    document.getElementById('lp_week_label').textContent = label;
}

document.getElementById('lp_class').addEventListener('change', function() {
    var cid = parseInt(this.value);
    var secSel = document.getElementById('lp_section');
    secSel.innerHTML = '<option value="0">All Sections</option>';
    LP_SECTIONS.forEach(function(s) {
        if (cid === 0 || parseInt(s.class_id) === cid) {
            secSel.innerHTML += '<option value="' + s.section_id + '">' + s.class_name + ' - ' + s.section_name + '</option>';
        }
    });
});

function loadFormSections() {
    var cid = parseInt(document.getElementById('lp_form_class').value) || 0;
    var secSel = document.getElementById('lp_form_section');
    var subSel = document.getElementById('lp_form_subject');
    secSel.innerHTML = '<option value="">Select Section</option>';
    subSel.innerHTML = '<option value="">Select Subject</option>';
    if (!cid) return;
    LP_SECTIONS.forEach(function(s) {
        if (parseInt(s.class_id) === cid) {
            secSel.innerHTML += '<option value="' + s.section_id + '">' + s.section_name + '</option>';
        }
    });
    LP_SUBJECTS.forEach(function(s) {
        subSel.innerHTML += '<option value="' + s.subject_id + '">' + s.subject_name + '</option>';
    });
}

function loadLessonPlans() {
    updateWeekLabel();
    var ws = getWeekStart();
    var cid = document.getElementById('lp_class').value;
    var sid = document.getElementById('lp_section').value;
    var url = LP_AJAX + '?week_start=' + ws;
    if (cid > 0) url += '&class_id=' + cid;
    if (sid > 0) url += '&section_id=' + sid;

    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                LP_PLAN_DATA = res.data;
                renderGrid();
            }
        })
        .catch(function(err) {
            console.error('Load failed:', err);
        });
}

function renderGrid() {
    var body = document.getElementById('lp_grid_body');
    var days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var rows = {};

    LP_PLAN_DATA.forEach(function(p) {
        var key = p.class_id + '_' + p.section_id;
        if (!rows[key]) {
            rows[key] = { class_name: p.class_name, section_name: p.section_name, plans: {} };
        }
        if (!rows[key].plans[p.day]) rows[key].plans[p.day] = [];
        rows[key].plans[p.day].push(p);
    });

    if (Object.keys(rows).length === 0) {
        body.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:#9CA3AF;"><i class="fa fa-calendar-o" style="font-size:28px; display:block; margin-bottom:8px;"></i>No lesson plans found for this week</td></tr>';
        updateStats([]);
        return;
    }

    var html = '';
    Object.keys(rows).sort().forEach(function(key) {
        var row = rows[key];
        html += '<tr>';
        html += '<td style="font-weight:600; font-size:13px; color:#111827; background:#F9FAFB;">' + escHtml(row.class_name) + '<br><span style="font-size:11px; color:#6B7280; font-weight:400;">' + escHtml(row.section_name) + '</span></td>';
        days.forEach(function(day) {
            var plans = row.plans[day] || [];
            if (plans.length === 0) {
                html += '<td><div class="lp-cell empty" onclick="openModalFor(\'' + key.split('_')[0] + '\',\'' + key.split('_')[1] + '\',\'' + day + '\')">+ Add</div></td>';
            } else {
                html += '<td>';
                plans.forEach(function(p) {
                    html += renderPlanItem(p);
                });
                html += '</td>';
            }
        });
        html += '</tr>';
    });

    body.innerHTML = html;
    updateStats(LP_PLAN_DATA);
}

function renderPlanItem(p) {
    var badgeClass = 'lp-badge-' + p.status;
    var actions = '';
    if (LP_USER_ROLE === 'admin' || LP_USER_ROLE === 'hod') {
        if (p.status === 'submitted') {
            actions += '<button class="lp-btn-approve" onclick="approvePlan(' + p.id + ')"><i class="fa fa-check"></i></button>';
            actions += '<button class="lp-btn-reject" onclick="rejectPlan(' + p.id + ')"><i class="fa fa-times"></i></button>';
        }
    }
    if (p.teacher_id === LP_USER_ID && (p.status === 'draft' || p.status === 'rejected')) {
        actions += '<button class="lp-btn-edit" onclick="editPlan(' + p.id + ')"><i class="fa fa-pencil"></i></button>';
        actions += '<button class="lp-btn-delete" onclick="deletePlan(' + p.id + ')"><i class="fa fa-trash"></i></button>';
    }

    return '<div class="lp-plan-item">' +
        '<div class="lp-plan-topic">' + escHtml(p.topic) + '</div>' +
        '<div class="lp-plan-subject"><i class="fa fa-book" style="margin-right:2px;"></i>' + escHtml(p.subject_name) + '</div>' +
        '<div class="lp-plan-teacher"><i class="fa fa-user" style="margin-right:2px;"></i>' + escHtml(p.teacher_name) + '</div>' +
        '<span class="lp-badge ' + badgeClass + '">' + p.status + '</span>' +
        (actions ? '<div class="lp-actions">' + actions + '</div>' : '') +
        '</div>';
}

function updateStats(data) {
    var stats = { draft: 0, submitted: 0, approved: 0, rejected: 0, total: data.length };
    data.forEach(function(p) { stats[p.status]++; });
    document.getElementById('lp_stats').innerHTML =
        '<div class="lp-stat"><span class="lp-stat-dot" style="background:#6B7280;"></span> Draft: ' + stats.draft + '</div>' +
        '<div class="lp-stat"><span class="lp-stat-dot" style="background:#1D4ED8;"></span> Submitted: ' + stats.submitted + '</div>' +
        '<div class="lp-stat"><span class="lp-stat-dot" style="background:#059669;"></span> Approved: ' + stats.approved + '</div>' +
        '<div class="lp-stat"><span class="lp-stat-dot" style="background:#DC2626;"></span> Rejected: ' + stats.rejected + '</div>' +
        '<div class="lp-stat" style="margin-left:auto; font-weight:700; color:#111827;">Total: ' + stats.total + '</div>';
}

function openModal() {
    document.getElementById('lp_edit_id').value = '';
    document.getElementById('lp_modal_title').innerHTML = '<i class="fa fa-plus-circle"></i> New Lesson Plan';
    document.getElementById('lp_form_class').value = '';
    document.getElementById('lp_form_section').innerHTML = '<option value="">Select Class First</option>';
    document.getElementById('lp_form_subject').innerHTML = '<option value="">Select Class First</option>';
    document.getElementById('lp_form_day').value = 'Monday';
    document.getElementById('lp_form_topic').value = '';
    document.getElementById('lp_form_desc').value = '';
    document.getElementById('lp_modal').classList.add('active');
}

function openModalFor(classId, sectionId, day) {
    openModal();
    document.getElementById('lp_form_class').value = classId;
    loadFormSections();
    setTimeout(function() {
        document.getElementById('lp_form_section').value = sectionId;
        document.getElementById('lp_form_day').value = day;
    }, 50);
}

function closeModal() {
    document.getElementById('lp_modal').classList.remove('active');
}

function editPlan(id) {
    var plan = null;
    for (var i = 0; i < LP_PLAN_DATA.length; i++) {
        if (LP_PLAN_DATA[i].id === id) { plan = LP_PLAN_DATA[i]; break; }
    }
    if (!plan) return;
    document.getElementById('lp_edit_id').value = plan.id;
    document.getElementById('lp_modal_title').innerHTML = '<i class="fa fa-edit"></i> Edit Lesson Plan';
    document.getElementById('lp_form_class').value = plan.class_id;
    loadFormSections();
    setTimeout(function() {
        document.getElementById('lp_form_section').value = plan.section_id;
        document.getElementById('lp_form_subject').value = plan.subject_id;
        document.getElementById('lp_form_day').value = plan.day;
    }, 50);
    document.getElementById('lp_form_topic').value = plan.topic;
    document.getElementById('lp_form_desc').value = plan.description;
    document.getElementById('lp_modal').classList.add('active');
}

function savePlan(status) {
    var id = document.getElementById('lp_edit_id').value;
    var payload = {
        action: 'save',
        id: id ? parseInt(id) : 0,
        class_id: parseInt(document.getElementById('lp_form_class').value) || 0,
        section_id: parseInt(document.getElementById('lp_form_section').value) || 0,
        subject_id: parseInt(document.getElementById('lp_form_subject').value) || 0,
        week_start: getWeekStart(),
        day: document.getElementById('lp_form_day').value,
        topic: document.getElementById('lp_form_topic').value.trim(),
        description: document.getElementById('lp_form_desc').value.trim(),
        status: status
    };

    if (!payload.class_id || !payload.section_id || !payload.subject_id || !payload.topic) {
        alert('Please fill all required fields (Class, Section, Subject, Topic)');
        return;
    }

    fetch(LP_AJAX, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            closeModal();
            loadLessonPlans();
        } else {
            alert(res.message || 'Save failed');
        }
    })
    .catch(function(err) {
        alert('Network error');
    });
}

function approvePlan(id) {
    if (!confirm('Approve this lesson plan?')) return;
    fetch(LP_AJAX, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'approve', id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) loadLessonPlans();
        else alert(res.message);
    });
}

function rejectPlan(id) {
    if (!confirm('Reject this lesson plan?')) return;
    fetch(LP_AJAX, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reject', id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) loadLessonPlans();
        else alert(res.message);
    });
}

function deletePlan(id) {
    if (!confirm('Delete this lesson plan?')) return;
    fetch(LP_AJAX, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) loadLessonPlans();
        else alert(res.message);
    });
}

function escHtml(s) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s || ''));
    return d.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    updateWeekLabel();
    loadLessonPlans();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
