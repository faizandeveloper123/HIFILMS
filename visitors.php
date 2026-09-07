<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Visitor Management';

db_query("CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(255) NOT NULL,
    cnic VARCHAR(30) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    purpose VARCHAR(255) DEFAULT NULL,
    person_to_meet VARCHAR(255) DEFAULT NULL,
    student_id INT DEFAULT NULL,
    check_in DATETIME DEFAULT CURRENT_TIMESTAMP,
    check_out DATETIME DEFAULT NULL,
    badge_number VARCHAR(30) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'checked_in',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$today = date('Y-m-d');
$hist_from = $_GET['hist_from'] ?? '';
$hist_to = $_GET['hist_to'] ?? '';
$search_visitor = trim($_GET['search_visitor'] ?? '');

$todayVisitors = [];
$res = db_query("SELECT v.*, st.first_name AS student_first, st.last_name AS student_last,
        CONCAT(st.first_name, ' ', COALESCE(st.last_name, '')) AS student_name
        FROM visitors v
        LEFT JOIN students st ON v.student_id = st.student_id
        WHERE DATE(v.check_in) = '$today'
        ORDER BY v.check_in DESC");
while ($row = $res->fetch_assoc()) { $todayVisitors[] = $row; }

$histVisitors = [];
if ($hist_from !== '' || $hist_to !== '' || $search_visitor !== '') {
    $where = [];
    $params = [];
    $types = '';
    if ($hist_from !== '') { $where[] = "DATE(v.check_in) >= ?"; $params[] = $hist_from; $types .= 's'; }
    if ($hist_to !== '') { $where[] = "DATE(v.check_in) <= ?"; $params[] = $hist_to; $types .= 's'; }
    if ($search_visitor !== '') { $where[] = "(v.visitor_name LIKE ? OR v.cnic LIKE ? OR v.badge_number LIKE ?)"; $like = '%' . $search_visitor . '%'; $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss'; }
    $sql = "SELECT v.*, st.first_name AS student_first, st.last_name AS student_last,
            CONCAT(st.first_name, ' ', COALESCE(st.last_name, '')) AS student_name
            FROM visitors v
            LEFT JOIN students st ON v.student_id = st.student_id"
            . (count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY v.check_in DESC LIMIT 200";
    if (count($params) > 0) {
        $stmt = db_prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = db_query($sql);
    }
    while ($row = $res->fetch_assoc()) { $histVisitors[] = $row; }
}

$totalToday = count($todayVisitors);
$checkedIn = 0;
$checkedOut = 0;
foreach ($todayVisitors as $tv) {
    if ($tv['status'] === 'checked_in') $checkedIn++;
    else $checkedOut++;
}

$students_list = [];
$res = db_query("SELECT student_id, first_name, last_name, class_id, section_id, father_name, gr_no FROM students WHERE status=1 ORDER BY first_name");
while ($row = $res->fetch_assoc()) { $students_list[] = $row; }

$nextBadge = 'V-' . date('Ymd') . '-' . str_pad(1, 4, '0', STR_PAD_LEFT);
$resBadge = db_query("SELECT badge_number FROM visitors WHERE DATE(check_in) = '$today' ORDER BY id DESC LIMIT 1");
if ($resBadge && $resBadge->num_rows > 0) {
    $last = $resBadge->fetch_assoc();
    $parts = explode('-', $last['badge_number']);
    $lastNum = (int)end($parts);
    $nextBadge = 'V-' . date('Ymd') . '-' . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
}

include __DIR__ . '/includes/header.php';
?>

<style>
.vis-page { padding: 14px; }
.vis-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
.vis-header h3 { font-size:18px; font-weight:800; color:#111827; margin:0; }
.vis-header h3 i { color:#f97316; }
.btn-accent { background:#f97316; color:#fff; border:none; border-radius:10px; padding:9px 18px; font-weight:600; cursor:pointer; transition:all .2s; }
.btn-accent:hover { background:#ea6c0a; color:#fff; }
.btn-sm { padding:6px 14px; font-size:12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; color:#374151; cursor:pointer; transition:all .15s; }
.btn-sm:hover { background:#f97316; color:#fff; border-color:#f97316; }
.btn-sm.btn-checkin { background:#2563EB; color:#fff; border-color:#2563EB; }
.btn-sm.btn-checkin:hover { background:#1D4ED8; }
.btn-sm.btn-checkout { background:#F59E0B; color:#fff; border-color:#F59E0B; }
.btn-sm.btn-checkout:hover { background:#D97706; }
.btn-sm.btn-print { background:#8B5CF6; color:#fff; border-color:#8B5CF6; }
.btn-sm.btn-print:hover { background:#7C3AED; }
.kpi-row { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:16px; }
.kpi-card { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:18px; }
.kpi-top { display:flex; align-items:center; gap:11px; }
.kpi-icon { width:42px; height:42px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:17px; }
.kpi-label { font-size:12.5px; color:#6B7280; font-weight:600; }
.kpi-value { font-size:23px; font-weight:800; color:#111827; margin-top:14px; }
.section-card { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:16px; margin-bottom:16px; }
.section-title { font-size:15px; font-weight:700; margin:0 0 12px; color:#111827; }
.vis-table { width:100%; border-collapse:collapse; font-size:13px; }
.vis-table th { background:#f9fafb; padding:10px 8px; text-align:left; font-weight:600; color:#374151; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
.vis-table td { padding:9px 8px; border-bottom:1px solid #f3f4f6; color:#4B5563; vertical-align:middle; }
.vis-table tr:hover { background:#FFF7ED; }
.vis-badge { display:inline-block; padding:3px 10px; border-radius:6px; font-size:11px; font-weight:700; letter-spacing:.5px; }
.vis-badge.in { background:#D1FAE5; color:#065F46; }
.vis-badge.out { background:#E5E7EB; color:#374151; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
@media (max-width:768px) { .form-row { grid-template-columns:1fr; } .kpi-row { grid-template-columns:1fr; } }
.modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:1050; align-items:center; justify-content:center; }
.modal-backdrop.show { display:flex; }
.modal-box { background:#fff; border-radius:16px; padding:24px; width:95%; max-width:580px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.2); }
.modal-box h4 { font-size:17px; font-weight:700; margin:0 0 16px; color:#111827; }
.modal-box .form-group { margin-bottom:12px; }
.modal-box label { display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:4px; }
.modal-box input, .modal-box select, .modal-box textarea { width:100%; border:1px solid #e5e7eb; border-radius:10px; padding:9px 12px; font-size:13px; box-sizing:border-box; }
.modal-box .modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:16px; }
.modal-box .btn-cancel { background:#E5E7EB; color:#374151; border:none; border-radius:10px; padding:9px 18px; cursor:pointer; font-weight:600; }
.modal-box .btn-save { background:#f97316; color:#fff; border:none; border-radius:10px; padding:9px 18px; cursor:pointer; font-weight:600; }
.modal-box .btn-save:hover { background:#ea6c0a; }
.badge-preview { border:2px solid #f97316; border-radius:12px; padding:16px; text-align:center; width:280px; margin:0 auto; }
.badge-preview .bp-logo { font-size:10px; color:#6B7280; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; }
.badge-preview .bp-name { font-size:18px; font-weight:800; color:#111827; margin:8px 0 4px; }
.badge-preview .bp-badge { font-size:13px; font-weight:700; color:#f97316; background:#FFF7ED; display:inline-block; padding:4px 14px; border-radius:8px; margin:6px 0; }
.badge-preview .bp-purpose { font-size:12px; color:#6B7280; }
.badge-preview .bp-date { font-size:11px; color:#9CA3AF; margin-top:8px; }
.badge-preview .bp-person { font-size:12px; color:#4B5563; margin-top:4px; }
.badge-preview .bp-qr { margin-top:8px; font-size:11px; color:#9CA3AF; font-family:monospace; letter-spacing:2px; }
@media print { .no-print { display:none !important; } body * { visibility:hidden; } #printBadgeArea, #printBadgeArea * { visibility:visible; } #printBadgeArea { position:fixed; top:0; left:0; width:100%; z-index:9999; padding:20px; text-align:center; } }
.empty-state { text-align:center; padding:30px; color:#9CA3AF; }
.empty-state i { font-size:36px; margin-bottom:8px; color:#D1D5DB; }
.tab-bar { display:flex; gap:0; margin-bottom:16px; border-bottom:2px solid #e5e7eb; }
.tab-bar button { padding:10px 20px; border:none; background:none; font-size:14px; font-weight:600; color:#6B7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all .15s; }
.tab-bar button.active { color:#f97316; border-bottom-color:#f97316; }
.tab-content { display:none; }
.tab-content.active { display:block; }
</style>

<div class="vis-page">
    <div class="vis-header">
        <h3><i class="fa fa-id-badge"></i> Visitor Management</h3>
        <button class="btn-accent" onclick="openCheckin()"><i class="fa fa-user-plus"></i> Check-In Visitor</button>
    </div>

    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-top"><div class="kpi-icon" style="background:#FFF7ED; color:#f97316;"><i class="fa fa-users"></i></div><div class="kpi-label">Today's Visitors</div></div>
            <div class="kpi-value"><?php echo $totalToday; ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-top"><div class="kpi-icon" style="background:#D1FAE5; color:#059669;"><i class="fa fa-sign-in"></i></div><div class="kpi-label">Currently In</div></div>
            <div class="kpi-value"><?php echo $checkedIn; ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-top"><div class="kpi-icon" style="background:#E5E7EB; color:#6B7280;"><i class="fa fa-sign-out"></i></div><div class="kpi-label">Checked Out</div></div>
            <div class="kpi-value"><?php echo $checkedOut; ?></div>
        </div>
    </div>

    <div class="tab-bar">
        <button class="active" onclick="switchTab('today', this)"><i class="fa fa-calendar-day"></i> Today's Visitors</button>
        <button onclick="switchTab('history', this)"><i class="fa fa-history"></i> Visitor History</button>
    </div>

    <!-- Today Tab -->
    <div class="tab-content active" id="tab-today">
        <div class="section-card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h4 class="section-title"><i class="fa fa-list" style="color:#f97316;"></i> Today — <?php echo date('d M Y'); ?></h4>
                <button class="btn-sm no-print" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
            <?php if (count($todayVisitors) === 0): ?>
                <div class="empty-state"><i class="fa fa-user"></i><p>No visitors today yet.</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="vis-table">
                        <thead>
                            <tr>
                                <th>Badge #</th>
                                <th>Visitor</th>
                                <th>CNIC</th>
                                <th>Phone</th>
                                <th>Purpose</th>
                                <th>Person to Meet</th>
                                <th>Student</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayVisitors as $v): ?>
                                <tr>
                                    <td><strong style="color:#f97316;"><?php echo e($v['badge_number']); ?></strong></td>
                                    <td>
                                        <strong><?php echo e($v['visitor_name']); ?></strong>
                                        <?php if ($v['photo']): ?>
                                            <br><small style="color:#9CA3AF;"><i class="fa fa-camera"></i> Photo</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e($v['cnic'] ?: '-'); ?></td>
                                    <td><?php echo e($v['phone'] ?: '-'); ?></td>
                                    <td><?php echo e($v['purpose'] ?: '-'); ?></td>
                                    <td><?php echo e($v['person_to_meet'] ?: '-'); ?></td>
                                    <td><?php echo $v['student_name'] ? e($v['student_name']) : '<span style="color:#D1D5DB;">-</span>'; ?></td>
                                    <td><?php echo date('h:i A', strtotime($v['check_in'])); ?></td>
                                    <td><?php echo $v['check_out'] ? date('h:i A', strtotime($v['check_out'])) : '-'; ?></td>
                                    <td>
                                        <span class="vis-badge <?php echo $v['status']==='checked_in'?'in':'out'; ?>">
                                            <?php echo $v['status']==='checked_in'?'IN':'OUT'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($v['status'] === 'checked_in'): ?>
                                            <button class="btn-sm btn-checkout" onclick="checkOut(<?php echo $v['id']; ?>)"><i class="fa fa-sign-out"></i> Out</button>
                                        <?php endif; ?>
                                        <button class="btn-sm btn-print" onclick="printBadge(<?php echo $v['id']; ?>)"><i class="fa fa-id-card"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- History Tab -->
    <div class="tab-content" id="tab-history">
        <div class="section-card">
            <h4 class="section-title"><i class="fa fa-search" style="color:#f97316;"></i> Filter History</h4>
            <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                <div>
                    <label style="font-size:12px; font-weight:600; color:#374151;">From</label>
                    <input type="date" name="hist_from" value="<?php echo e($hist_from); ?>" style="border:1px solid #e5e7eb; border-radius:10px; padding:8px 12px; font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:600; color:#374151;">To</label>
                    <input type="date" name="hist_to" value="<?php echo e($hist_to); ?>" style="border:1px solid #e5e7eb; border-radius:10px; padding:8px 12px; font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:600; color:#374151;">Search</label>
                    <input type="text" name="search_visitor" value="<?php echo e($search_visitor); ?>" placeholder="Name, CNIC, Badge#" style="border:1px solid #e5e7eb; border-radius:10px; padding:8px 12px; font-size:13px; width:180px;">
                </div>
                <button type="submit" class="btn-accent" style="padding:8px 16px; font-size:13px;"><i class="fa fa-search"></i> Search</button>
                <a href="visitors.php" class="btn-sm" style="text-decoration:none;">Clear</a>
            </form>

            <?php if (count($histVisitors) > 0): ?>
                <div style="overflow-x:auto; margin-top:14px;">
                    <table class="vis-table">
                        <thead>
                            <tr>
                                <th>Badge #</th>
                                <th>Visitor</th>
                                <th>CNIC</th>
                                <th>Purpose</th>
                                <th>Person to Meet</th>
                                <th>Student</th>
                                <th>Date</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($histVisitors as $v): ?>
                                <tr>
                                    <td><strong style="color:#f97316;"><?php echo e($v['badge_number']); ?></strong></td>
                                    <td><strong><?php echo e($v['visitor_name']); ?></strong></td>
                                    <td><?php echo e($v['cnic'] ?: '-'); ?></td>
                                    <td><?php echo e($v['purpose'] ?: '-'); ?></td>
                                    <td><?php echo e($v['person_to_meet'] ?: '-'); ?></td>
                                    <td><?php echo $v['student_name'] ? e($v['student_name']) : '-'; ?></td>
                                    <td><?php echo date('d M Y', strtotime($v['check_in'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($v['check_in'])); ?></td>
                                    <td><?php echo $v['check_out'] ? date('h:i A', strtotime($v['check_out'])) : '-'; ?></td>
                                    <td><span class="vis-badge <?php echo $v['status']==='checked_in'?'in':'out'; ?>"><?php echo $v['status']==='checked_in'?'IN':'OUT'; ?></span></td>
                                    <td><button class="btn-sm btn-print" onclick="printBadge(<?php echo $v['id']; ?>)"><i class="fa fa-id-card"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($hist_from !== '' || $hist_to !== '' || $search_visitor !== ''): ?>
                <div class="empty-state"><i class="fa fa-search"></i><p>No visitors found for the selected filters.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Check-In Modal -->
<div class="modal-backdrop" id="checkinModal">
    <div class="modal-box">
        <h4><i class="fa fa-user-plus" style="color:#f97316;"></i> Check-In Visitor</h4>
        <form id="checkinForm" onsubmit="return submitCheckin(event)">
            <input type="hidden" id="cPhoto" value="">
            <div class="form-row">
                <div class="form-group">
                    <label>Visitor Name *</label>
                    <input type="text" id="cName" required placeholder="Full name">
                </div>
                <div class="form-group">
                    <label>CNIC</label>
                    <input type="text" id="cCnic" placeholder="XXXXX-XXXXXXX-X">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="cPhone" placeholder="03XXXXXXXXX">
                </div>
                <div class="form-group">
                    <label>Purpose *</label>
                    <input type="text" id="cPurpose" required placeholder="e.g. Fee inquiry, Meeting">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Person to Meet</label>
                    <input type="text" id="cPerson" placeholder="Teacher / Staff name">
                </div>
                <div class="form-group">
                    <label>Linked Student (Optional)</label>
                    <select id="cStudent">
                        <option value="">-- None --</option>
                        <?php foreach ($students_list as $s): ?>
                            <option value="<?php echo $s['student_id']; ?>"><?php echo e($s['first_name'] . ' ' . $s['last_name'] . ' (' . ($s['father_name'] ?: 'N/A') . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Photo (Optional)</label>
                <input type="file" id="cPhotoFile" accept="image/*" capture="environment" onchange="previewPhoto(this)" style="border:1px solid #e5e7eb; border-radius:10px; padding:8px;">
                <div id="photoPreview" style="margin-top:8px;"></div>
            </div>
            <div style="background:#FFF7ED; border:1px solid #fed7aa; border-radius:10px; padding:12px; margin-bottom:12px; text-align:center;">
                <div style="font-size:11px; color:#92400E; font-weight:600; text-transform:uppercase; letter-spacing:1px;">Auto-Assigned Badge</div>
                <div style="font-size:20px; font-weight:800; color:#f97316; font-family:monospace; letter-spacing:2px; margin-top:4px;"><?php echo $nextBadge; ?></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeCheckin()">Cancel</button>
                <button type="submit" class="btn-save"><i class="fa fa-sign-in"></i> Check In</button>
            </div>
        </form>
    </div>
</div>

<!-- Badge Print Area (hidden, shown only on print) -->
<div id="printBadgeArea" style="display:none;"></div>

<!-- All visitors data for JS -->
<script>
var allVisitors = <?php echo json_encode(array_merge($todayVisitors, $histVisitors)); ?>;

function switchTab(tab, btn) {
    document.querySelectorAll('.tab-content').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.tab-bar button').forEach(function(b){ b.classList.remove('active'); });
    document.getElementById('tab-' + tab).classList.add('active');
    btn.classList.add('active');
}

function openCheckin() { document.getElementById('checkinModal').classList.add('show'); }
function closeCheckin() { document.getElementById('checkinModal').classList.remove('show'); }

function previewPhoto(input) {
    var preview = document.getElementById('photoPreview');
    preview.innerHTML = '';
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('cPhoto').value = e.target.result;
            preview.innerHTML = '<img src="' + e.target.result + '" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid #f97316;">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function submitCheckin(e) {
    e.preventDefault();
    var data = new FormData();
    data.append('visitor_name', document.getElementById('cName').value.trim());
    data.append('cnic', document.getElementById('cCnic').value.trim());
    data.append('phone', document.getElementById('cPhone').value.trim());
    data.append('purpose', document.getElementById('cPurpose').value.trim());
    data.append('person_to_meet', document.getElementById('cPerson').value.trim());
    data.append('student_id', document.getElementById('cStudent').value);
    data.append('photo', document.getElementById('cPhoto').value);

    fetch('ajax_visitors.php', { method: 'POST', body: data })
    .then(function(r){ return r.json(); })
    .then(function(res){
        if (res.success) { window.location.reload(); }
        else { alert(res.message || 'Error'); }
    }).catch(function(){ alert('Network error'); });
    return false;
}

function checkOut(id) {
    if (!confirm('Confirm check-out for this visitor?')) return;
    var data = new FormData();
    data.append('action', 'checkout');
    data.append('id', id);
    fetch('ajax_visitors.php', { method: 'POST', body: data })
    .then(function(r){ return r.json(); })
    .then(function(res){
        if (res.success) { window.location.reload(); }
        else { alert(res.message || 'Error'); }
    }).catch(function(){ alert('Network error'); });
}

function printBadge(id) {
    var v = allVisitors.find(function(x){ return x.id == id; });
    if (!v) return;
    var d = new Date(v.check_in);
    var dateStr = d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
    var html = '<div class="badge-preview">';
    html += '<div class="bp-logo"><?php echo e(get_setting("school_name", "HIIFI LMS")); ?></div>';
    html += '<div style="font-size:8px; color:#9CA3AF; text-transform:uppercase; letter-spacing:2px;">VISITOR PASS</div>';
    html += '<div class="bp-badge"># ' + v.badge_number + '</div>';
    html += '<div class="bp-name">' + v.visitor_name + '</div>';
    if (v.purpose) html += '<div class="bp-purpose">Purpose: ' + v.purpose + '</div>';
    if (v.person_to_meet) html += '<div class="bp-person">Meeting: ' + v.person_to_meet + '</div>';
    if (v.student_name) html += '<div class="bp-person">Student: ' + v.student_name + '</div>';
    html += '<div class="bp-date">' + dateStr + ' ' + d.toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'}) + '</div>';
    html += '<div class="bp-qr">' + v.badge_number + '</div>';
    html += '</div>';
    document.getElementById('printBadgeArea').innerHTML = html;
    document.getElementById('printBadgeArea').style.display = 'block';
    window.print();
    setTimeout(function(){ document.getElementById('printBadgeArea').style.display = 'none'; }, 500);
}

document.querySelectorAll('.modal-backdrop').forEach(function(el){
    el.addEventListener('click', function(e){ if (e.target === el) el.classList.remove('show'); });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
