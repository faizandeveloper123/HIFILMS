<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Parent-Teacher Meeting';

db_query("CREATE TABLE IF NOT EXISTS ptm_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    meeting_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    class_id INT DEFAULT NULL,
    section_id INT DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'scheduled',
    feedback TEXT DEFAULT NULL,
    rating TINYINT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$employees = [];
$res = db_query("SELECT emp_id, first_name, last_name FROM employees WHERE status=1 ORDER BY first_name");
while ($row = $res->fetch_assoc()) { $employees[] = $row; }

$parents_list = [];
$res = db_query("SELECT student_id, first_name, last_name, father_name, class_id, section_id FROM students WHERE status=1 ORDER BY first_name");
while ($row = $res->fetch_assoc()) { $parents_list[] = $row; }

$sel_status = trim($_GET['status'] ?? '');
$sel_class = (int) ($_GET['class_id'] ?? 0);
$sel_date = $_GET['filter_date'] ?? '';

$where = [];
$params = [];
$types = '';
if ($sel_status !== '' && in_array($sel_status, ['scheduled', 'completed', 'cancelled'])) {
    $where[] = "m.status = ?";
    $params[] = $sel_status;
    $types .= 's';
}
if ($sel_class > 0) {
    $where[] = "m.class_id = ?";
    $params[] = $sel_class;
    $types .= 'i';
}
if ($sel_date !== '') {
    $where[] = "m.meeting_date = ?";
    $params[] = $sel_date;
    $types .= 's';
}

$sql = "SELECT m.*, c.class_name, sec.section_name,
               CONCAT(te.first_name, ' ', COALESCE(te.last_name, '')) AS teacher_name,
               CONCAT(st.first_name, ' ', COALESCE(st.last_name, '')) AS parent_name
        FROM ptm_meetings m
        LEFT JOIN classes c ON m.class_id = c.class_id
        LEFT JOIN sections sec ON m.section_id = sec.section_id
        LEFT JOIN employees te ON m.teacher_id = te.emp_id
        LEFT JOIN students st ON m.parent_id = st.student_id"
        . (count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '')
        . " ORDER BY m.meeting_date DESC, m.start_time ASC";

$meetings = [];
if (count($params) > 0) {
    $stmt = db_prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = db_query($sql);
}
while ($row = $res->fetch_assoc()) { $meetings[] = $row; }

include __DIR__ . '/includes/header.php';
?>

<style>
.ptm-page { padding: 14px; }
.ptm-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.ptm-header h3 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.ptm-header h3 i { color:#f97316; }
.btn-accent { background:#f97316; color:#fff; border:none; border-radius:10px; padding:9px 18px; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-accent:hover { background:#ea6c0a; color:#fff; }
.filter-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:16px; }
.filter-bar select, .filter-bar input { border:1px solid #e5e7eb; border-radius:10px; padding:8px 12px; font-size:13px; background:#fff; }
.filter-bar .btn-accent { padding:8px 16px; font-size:13px; }
.meetings-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:14px; }
.meeting-card { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:16px; box-shadow:0 2px 12px rgba(0,0,0,0.04); transition:transform .15s; }
.meeting-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.08); }
.meeting-card .mc-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px; }
.meeting-card .mc-title { font-size:15px; font-weight:700; color:#111827; }
.meeting-card .mc-status { padding:4px 10px; border-radius:8px; font-size:11px; font-weight:600; text-transform:uppercase; }
.mc-status.scheduled { background:#FEF3C7; color:#D97706; }
.mc-status.completed { background:#D1FAE5; color:#059669; }
.mc-status.cancelled { background:#FEE2E2; color:#DC2626; }
.meeting-card .mc-detail { display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:13px; color:#4B5563; }
.meeting-card .mc-detail i { width:18px; text-align:center; color:#f97316; font-size:13px; }
.meeting-card .mc-actions { display:flex; gap:6px; margin-top:10px; flex-wrap:wrap; }
.meeting-card .mc-actions button, .meeting-card .mc-actions a { font-size:12px; padding:5px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; color:#374151; cursor:pointer; text-decoration:none; transition:all .15s; }
.meeting-card .mc-actions button:hover, .meeting-card .mc-actions a:hover { background:#f97316; color:#fff; border-color:#f97316; }
.meeting-card .mc-actions .btn-complete { background:#059669; color:#fff; border-color:#059669; }
.meeting-card .mc-actions .btn-complete:hover { background:#047857; }
.rating-stars { display:flex; gap:2px; }
.rating-stars i { cursor:pointer; font-size:18px; color:#D1D5DB; transition:color .15s; }
.rating-stars i.active, .rating-stars i:hover { color:#F59E0B; }
.star-row { display:flex; gap:3px; }
.star-row i { font-size:14px; color:#D1D5DB; }
.star-row i.filled { color:#F59E0B; }
.empty-state { text-align:center; padding:50px 20px; color:#9CA3AF; }
.empty-state i { font-size:48px; margin-bottom:12px; color:#D1D5DB; }
.modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:1050; align-items:center; justify-content:center; }
.modal-backdrop.show { display:flex; }
.modal-box { background:#fff; border-radius:16px; padding:24px; width:95%; max-width:600px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.2); }
.modal-box h4 { font-size:17px; font-weight:700; margin:0 0 16px; color:#111827; }
.modal-box .form-group { margin-bottom:12px; }
.modal-box label { display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:4px; }
.modal-box input, .modal-box select, .modal-box textarea { width:100%; border:1px solid #e5e7eb; border-radius:10px; padding:9px 12px; font-size:13px; box-sizing:border-box; }
.modal-box textarea { resize:vertical; min-height:70px; }
.modal-box .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.modal-box .btn-close-modal { background:#E5E7EB; color:#374151; border:none; border-radius:10px; padding:9px 18px; cursor:pointer; font-weight:600; }
.modal-box .btn-save { background:#f97316; color:#fff; border:none; border-radius:10px; padding:9px 18px; cursor:pointer; font-weight:600; }
.modal-box .btn-save:hover { background:#ea6c0a; }
.modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:16px; }
.calendar-grid { display:grid; grid-template-columns:repeat(7, 1fr); gap:2px; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; }
.cal-head { background:#f97316; color:#fff; padding:8px 4px; text-align:center; font-size:12px; font-weight:600; }
.cal-day { min-height:80px; padding:4px; border:1px solid #f3f4f6; background:#fff; position:relative; }
.cal-day.empty { background:#f9fafb; }
.cal-day.today { background:#FFF7ED; }
.cal-day .day-num { font-size:12px; font-weight:700; color:#374151; padding:2px 4px; }
.cal-day.today .day-num { color:#f97316; }
.cal-event { font-size:10px; padding:2px 4px; border-radius:4px; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.cal-event.scheduled { background:#FEF3C7; color:#92400E; }
.cal-event.completed { background:#D1FAE5; color:#065F46; }
.cal-event.cancelled { background:#FEE2E2; color:#991B1B; }
.cal-nav { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
.cal-nav button { background:none; border:1px solid #e5e7eb; border-radius:8px; padding:6px 12px; cursor:pointer; font-size:13px; }
.cal-nav button:hover { background:#f97316; color:#fff; border-color:#f97316; }
.cal-nav span { font-weight:700; font-size:15px; color:#111827; }
.print-badge { display:none; }
@media print { .no-print { display:none !important; } .print-badge { display:block !important; position:fixed; top:0; left:0; width:100%; } }
</style>

<div class="ptm-page">
    <div class="ptm-header">
        <h3><i class="fa fa-comments"></i> Parent-Teacher Meetings</h3>
        <div style="display:flex; gap:8px;">
            <button class="btn-accent" onclick="toggleCalendar()"><i class="fa fa-calendar"></i> Calendar</button>
            <button class="btn-accent" onclick="printSchedule()"><i class="fa fa-print"></i> Print</button>
            <button class="btn-accent" onclick="openModal()"><i class="fa fa-plus"></i> New Meeting</button>
        </div>
    </div>

    <div class="filter-bar no-print">
        <select id="filterStatus" onchange="applyFilters()">
            <option value="">All Status</option>
            <option value="scheduled" <?php echo $sel_status==='scheduled'?'selected':''; ?>>Scheduled</option>
            <option value="completed" <?php echo $sel_status==='completed'?'selected':''; ?>>Completed</option>
            <option value="cancelled" <?php echo $sel_status==='cancelled'?'selected':''; ?>>Cancelled</option>
        </select>
        <select id="filterClass" onchange="applyFilters()">
            <option value="">All Classes</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?php echo $c['class_id']; ?>" <?php echo $sel_class==(int)$c['class_id']?'selected':''; ?>><?php echo e($c['class_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" id="filterDate" value="<?php echo e($sel_date); ?>" onchange="applyFilters()">
    </div>

    <div id="calendarView" style="display:none; margin-bottom:20px;">
        <div class="cal-nav">
            <button onclick="calNav(-1)"><i class="fa fa-chevron-left"></i></button>
            <span id="calTitle"></span>
            <button onclick="calNav(1)"><i class="fa fa-chevron-right"></i></button>
        </div>
        <div class="calendar-grid" id="calGrid"></div>
    </div>

    <div id="listView">
        <?php if (count($meetings) === 0): ?>
            <div class="empty-state">
                <i class="fa fa-comments"></i>
                <h4 style="color:#6B7280;">No meetings found</h4>
                <p>Click "New Meeting" to schedule a parent-teacher meeting.</p>
            </div>
        <?php else: ?>
            <div class="meetings-grid" id="meetingsGrid">
                <?php foreach ($meetings as $m): ?>
                    <div class="meeting-card" data-id="<?php echo $m['id']; ?>">
                        <div class="mc-head">
                            <div class="mc-title"><?php echo e($m['title']); ?></div>
                            <span class="mc-status <?php echo e($m['status']); ?>"><?php echo e(ucfirst($m['status'])); ?></span>
                        </div>
                        <div class="mc-detail"><i class="fa fa-calendar"></i> <?php echo date('d M Y', strtotime($m['meeting_date'])); ?></div>
                        <div class="mc-detail"><i class="fa fa-clock-o"></i> <?php echo date('h:i A', strtotime($m['start_time'])); ?> - <?php echo date('h:i A', strtotime($m['end_time'])); ?></div>
                        <div class="mc-detail"><i class="fa fa-user"></i> Teacher: <?php echo e($m['teacher_name'] ?: 'Not assigned'); ?></div>
                        <div class="mc-detail"><i class="fa fa-users"></i> Parent: <?php echo e($m['parent_name'] ?: 'Not assigned'); ?></div>
                        <div class="mc-detail"><i class="fa fa-graduation-cap"></i> <?php echo e($m['class_name'] ?: '-'); ?><?php echo $m['section_name'] ? ' / ' . e($m['section_name']) : ''; ?></div>
                        <?php if ($m['notes']): ?>
                            <div class="mc-detail"><i class="fa fa-sticky-note-o"></i> <?php echo e(mb_substr($m['notes'], 0, 60)); ?><?php echo mb_strlen($m['notes']) > 60 ? '...' : ''; ?></div>
                        <?php endif; ?>
                        <?php if ($m['status'] === 'completed' && $m['rating']): ?>
                            <div style="margin-top:6px;">
                                <span style="font-size:12px; color:#6B7280;">Rating:</span>
                                <span class="star-row">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa fa-star <?php echo $i <= $m['rating'] ? 'filled' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="mc-actions">
                            <?php if ($m['status'] === 'scheduled'): ?>
                                <button onclick="openComplete(<?php echo $m['id']; ?>)"><i class="fa fa-check"></i> Complete</button>
                                <button onclick="openEdit(<?php echo $m['id']; ?>)"><i class="fa fa-edit"></i> Edit</button>
                            <?php endif; ?>
                            <button onclick="openDetail(<?php echo $m['id']; ?>)"><i class="fa fa-eye"></i> View</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal-backdrop" id="meetingModal">
    <div class="modal-box">
        <h4 id="modalTitle"><i class="fa fa-plus" style="color:#f97316;"></i> New Meeting</h4>
        <input type="hidden" id="editId" value="">
        <div class="form-group">
            <label>Title *</label>
            <input type="text" id="fTitle" placeholder="e.g. Academic Review - Semester 2">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Date *</label>
                <input type="date" id="fDate" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="fStatus">
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Start Time *</label>
                <input type="time" id="fStartTime" value="09:00">
            </div>
            <div class="form-group">
                <label>End Time *</label>
                <input type="time" id="fEndTime" value="10:00">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Class</label>
                <select id="fClass" onchange="loadSections('fSection', this.value)">
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Section</label>
                <select id="fSection">
                    <option value="">-- Select Section --</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Teacher</label>
            <select id="fTeacher">
                <option value="">-- Select Teacher --</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp['emp_id']; ?>"><?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Parent (Student)</label>
            <select id="fParent">
                <option value="">-- Select Parent --</option>
                <?php foreach ($parents_list as $p): ?>
                    <option value="<?php echo $p['student_id']; ?>"><?php echo e($p['first_name'] . ' ' . $p['last_name'] . ' (Father: ' . ($p['father_name'] ?: 'N/A') . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea id="fNotes" rows="3" placeholder="Additional notes..."></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn-close-modal" onclick="closeModal()">Cancel</button>
            <button class="btn-save" onclick="saveMeeting()"><i class="fa fa-save"></i> Save Meeting</button>
        </div>
    </div>
</div>

<!-- Complete Modal -->
<div class="modal-backdrop" id="completeModal">
    <div class="modal-box" style="max-width:440px;">
        <h4><i class="fa fa-check-circle" style="color:#059669;"></i> Complete Meeting</h4>
        <input type="hidden" id="cId" value="">
        <div class="form-group">
            <label>Feedback</label>
            <textarea id="cFeedback" rows="4" placeholder="Write meeting feedback..."></textarea>
        </div>
        <div class="form-group">
            <label>Rating</label>
            <div class="rating-stars" id="cRatingStars" style="font-size:28px; display:flex; gap:4px;">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fa fa-star" data-val="<?php echo $i; ?>" style="cursor:pointer; color:#D1D5DB;" onclick="setRating(<?php echo $i; ?>)" onmouseover="hoverRating(<?php echo $i; ?>)" onmouseout="resetRating()"></i>
                <?php endfor; ?>
            </div>
            <input type="hidden" id="cRatingVal" value="0">
        </div>
        <div class="modal-actions">
            <button class="btn-close-modal" onclick="closeComplete()">Cancel</button>
            <button class="btn-save" style="background:#059669;" onclick="submitComplete()"><i class="fa fa-check"></i> Mark Complete</button>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal-backdrop" id="detailModal">
    <div class="modal-box" style="max-width:480px;">
        <h4><i class="fa fa-info-circle" style="color:#3B82F6;"></i> Meeting Details</h4>
        <div id="detailContent"></div>
        <div class="modal-actions">
            <button class="btn-close-modal" onclick="closeDetail()">Close</button>
        </div>
    </div>
</div>

<script>
var allMeetings = <?php echo json_encode($meetings); ?>;
var calDate = new Date();
var calMode = false;

function applyFilters() {
    var s = document.getElementById('filterStatus').value;
    var c = document.getElementById('filterClass').value;
    var d = document.getElementById('filterDate').value;
    var url = 'ptm_meetings.php?';
    if (s) url += 'status=' + s + '&';
    if (c) url += 'class_id=' + c + '&';
    if (d) url += 'filter_date=' + d + '&';
    window.location.href = url.replace(/[&?]$/, '');
}

function toggleCalendar() {
    calMode = !calMode;
    document.getElementById('calendarView').style.display = calMode ? 'block' : 'none';
    document.getElementById('listView').style.display = calMode ? 'none' : 'block';
    if (calMode) renderCalendar();
}

function calNav(dir) {
    calDate.setMonth(calDate.getMonth() + dir);
    renderCalendar();
}

function renderCalendar() {
    var y = calDate.getFullYear(), m = calDate.getMonth();
    document.getElementById('calTitle').textContent = calDate.toLocaleString('default', {month:'long', year:'numeric'});
    var grid = document.getElementById('calGrid');
    grid.innerHTML = '';
    var days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    days.forEach(function(d) { grid.innerHTML += '<div class="cal-head">' + d + '</div>'; });
    var first = new Date(y, m, 1).getDay();
    var daysInMonth = new Date(y, m+1, 0).getDate();
    var today = new Date();
    for (var i = 0; i < first; i++) { grid.innerHTML += '<div class="cal-day empty"></div>'; }
    for (var d = 1; d <= daysInMonth; d++) {
        var dateStr = y + '-' + String(m+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        var isToday = (today.getFullYear()===y && today.getMonth()===m && today.getDate()===d);
        var cls = 'cal-day' + (isToday ? ' today' : '');
        var evHtml = '';
        allMeetings.forEach(function(mt) {
            if (mt.meeting_date === dateStr) {
                var label = mt.title.length > 12 ? mt.title.substring(0,12)+'...' : mt.title;
                evHtml += '<div class="cal-event ' + mt.status + '">' + label + '</div>';
            }
        });
        grid.innerHTML += '<div class="' + cls + '"><div class="day-num">' + d + '</div>' + evHtml + '</div>';
    }
}

function openModal() {
    document.getElementById('editId').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="fa fa-plus" style="color:#f97316;"></i> New Meeting';
    document.getElementById('fTitle').value = '';
    document.getElementById('fDate').value = '<?php echo date("Y-m-d"); ?>';
    document.getElementById('fStartTime').value = '09:00';
    document.getElementById('fEndTime').value = '10:00';
    document.getElementById('fClass').value = '';
    document.getElementById('fSection').innerHTML = '<option value="">-- Select Section --</option>';
    document.getElementById('fTeacher').value = '';
    document.getElementById('fParent').value = '';
    document.getElementById('fNotes').value = '';
    document.getElementById('fStatus').value = 'scheduled';
    document.getElementById('fStatus').closest('.form-group').style.display = 'none';
    document.getElementById('meetingModal').classList.add('show');
}

function closeModal() { document.getElementById('meetingModal').classList.remove('show'); }

function openEdit(id) {
    var m = allMeetings.find(function(x){ return x.id == id; });
    if (!m) return;
    document.getElementById('editId').value = m.id;
    document.getElementById('modalTitle').innerHTML = '<i class="fa fa-edit" style="color:#f97316;"></i> Edit Meeting';
    document.getElementById('fTitle').value = m.title;
    document.getElementById('fDate').value = m.meeting_date;
    document.getElementById('fStartTime').value = m.start_time.substring(0,5);
    document.getElementById('fEndTime').value = m.end_time.substring(0,5);
    document.getElementById('fClass').value = m.class_id || '';
    document.getElementById('fTeacher').value = m.teacher_id || '';
    document.getElementById('fParent').value = m.parent_id || '';
    document.getElementById('fNotes').value = m.notes || '';
    document.getElementById('fStatus').value = m.status;
    document.getElementById('fStatus').closest('.form-group').style.display = 'block';
    if (m.class_id) {
        loadSections('fSection', m.class_id, m.section_id);
    } else {
        document.getElementById('fSection').innerHTML = '<option value="">-- Select Section --</option>';
    }
    document.getElementById('meetingModal').classList.add('show');
}

function saveMeeting() {
    var data = {
        title: document.getElementById('fTitle').value.trim(),
        meeting_date: document.getElementById('fDate').value,
        start_time: document.getElementById('fStartTime').value,
        end_time: document.getElementById('fEndTime').value,
        class_id: document.getElementById('fClass').value || null,
        section_id: document.getElementById('fSection').value || null,
        teacher_id: document.getElementById('fTeacher').value || null,
        parent_id: document.getElementById('fParent').value || null,
        notes: document.getElementById('fNotes').value.trim(),
        status: document.getElementById('fStatus').value
    };
    if (!data.title || !data.meeting_date || !data.start_time || !data.end_time) {
        alert('Title, date, start and end time are required');
        return;
    }
    var editId = document.getElementById('editId').value;
    if (editId) {
        data.id = editId;
        data.action = 'update';
    }
    fetch('ajax_ptm.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(function(r){ return r.json(); }).then(function(res){
        if (res.success) { window.location.reload(); }
        else { alert(res.message || 'Error saving meeting'); }
    }).catch(function(){ alert('Network error'); });
}

function openComplete(id) {
    document.getElementById('cId').value = id;
    document.getElementById('cFeedback').value = '';
    document.getElementById('cRatingVal').value = '0';
    resetRating();
    document.getElementById('completeModal').classList.add('show');
}

function closeComplete() { document.getElementById('completeModal').classList.remove('show'); }

function setRating(v) {
    document.getElementById('cRatingVal').value = v;
    var stars = document.querySelectorAll('#cRatingStars i');
    stars.forEach(function(s, i) { s.style.color = (i < v) ? '#F59E0B' : '#D1D5DB'; });
}

function hoverRating(v) {
    var stars = document.querySelectorAll('#cRatingStars i');
    stars.forEach(function(s, i) { if (i < v) s.style.color = '#FCD34D'; });
}

function resetRating() {
    var v = parseInt(document.getElementById('cRatingVal').value) || 0;
    var stars = document.querySelectorAll('#cRatingStars i');
    stars.forEach(function(s, i) { s.style.color = (i < v) ? '#F59E0B' : '#D1D5DB'; });
}

function submitComplete() {
    var data = {
        action: 'complete',
        id: document.getElementById('cId').value,
        feedback: document.getElementById('cFeedback').value.trim(),
        rating: document.getElementById('cRatingVal').value
    };
    fetch('ajax_ptm.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(function(r){ return r.json(); }).then(function(res){
        if (res.success) { window.location.reload(); }
        else { alert(res.message || 'Error'); }
    }).catch(function(){ alert('Network error'); });
}

function openDetail(id) {
    var m = allMeetings.find(function(x){ return x.id == id; });
    if (!m) return;
    var ratingHtml = '';
    if (m.rating) {
        for (var i=1; i<=5; i++) { ratingHtml += '<i class="fa fa-star" style="color:' + (i<=m.rating?'#F59E0B':'#D1D5DB') + ';"></i> '; }
    }
    var html = '<table style="width:100%; font-size:13px; border-collapse:collapse;">';
    html += '<tr><td style="padding:6px 0; color:#6B7280; width:120px;">Title</td><td style="font-weight:600;">' + m.title + '</td></tr>';
    html += '<tr><td style="padding:6px 0; color:#6B7280;">Date</td><td>' + m.meeting_date + '</td></tr>';
    html += '<tr><td style="padding:6px 0; color:#6B7280;">Time</td><td>' + m.start_time + ' - ' + m.end_time + '</td></tr>';
    html += '<tr><td style="padding:6px 0; color:#6B7280;">Class</td><td>' + (m.class_name||'-') + (m.section_name ? ' / '+m.section_name : '') + '</td></tr>';
    html += '<tr><td style="padding:6px 0; color:#6B7280;">Teacher</td><td>' + (m.teacher_name||'Not assigned') + '</td></tr>';
    html += '<tr><td style="padding:6px 0; color:#6B7280;">Parent</td><td>' + (m.parent_name||'Not assigned') + '</td></tr>';
    html += '<tr><td style="padding:6px 0; color:#6B7280;">Status</td><td><span class="mc-status ' + m.status + '">' + m.status.toUpperCase() + '</span></td></tr>';
    if (m.notes) html += '<tr><td style="padding:6px 0; color:#6B7280;">Notes</td><td>' + m.notes + '</td></tr>';
    if (m.feedback) html += '<tr><td style="padding:6px 0; color:#6B7280;">Feedback</td><td>' + m.feedback + '</td></tr>';
    if (m.rating) html += '<tr><td style="padding:6px 0; color:#6B7280;">Rating</td><td>' + ratingHtml + '</td></tr>';
    html += '</table>';
    document.getElementById('detailContent').innerHTML = html;
    document.getElementById('detailModal').classList.add('show');
}

function closeDetail() { document.getElementById('detailModal').classList.remove('show'); }

function loadSections(selectId, classId, preselect) {
    var sel = document.getElementById(selectId);
    sel.innerHTML = '<option value="">Loading...</option>';
    if (!classId) { sel.innerHTML = '<option value="">-- Select Section --</option>'; return; }
    fetch('get_sections.php?class_id=' + classId).then(function(r){ return r.json(); }).then(function(data){
        sel.innerHTML = '<option value="">-- Select Section --</option>';
        data.forEach(function(s){
            var opt = document.createElement('option');
            opt.value = s.section_id; opt.textContent = s.section_name;
            if (preselect && s.section_id == preselect) opt.selected = true;
            sel.appendChild(opt);
        });
    });
}

function printSchedule() { window.print(); }

document.querySelectorAll('.modal-backdrop').forEach(function(el){
    el.addEventListener('click', function(e){ if (e.target === el) el.classList.remove('show'); });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
