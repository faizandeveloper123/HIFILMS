<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'QR Attendance Scan Station';

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status = 1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
.qr-scan-wrapper { max-width: 900px; margin: 0 auto; }
.qr-scanner-card {
    background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.06);
    padding: 24px; margin-bottom: 20px; position: relative; overflow: hidden;
}
.qr-scanner-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #f97316, #fb923c);
}
#qr-reader { border-radius: 12px; overflow: hidden; min-height: 320px; }
#qr-reader video { border-radius: 12px; }
.scan-result-card {
    display: none; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.06);
    padding: 24px; margin-bottom: 20px; text-align: center;
    animation: slideUp .4s ease;
}
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.scan-result-card.show { display: block; }
.student-photo {
    width: 100px; height: 100px; border-radius: 50%; object-fit: cover;
    border: 4px solid #f97316; box-shadow: 0 4px 16px rgba(249,115,22,.3);
    margin: 0 auto 14px;
}
.student-photo-placeholder {
    width: 100px; height: 100px; border-radius: 50%; background: #fff7ed;
    border: 4px solid #f97316; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 40px; color: #f97316;
}
.status-badge {
    display: inline-block; padding: 6px 18px; border-radius: 20px;
    font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: .5px;
}
.status-present { background: #d1fae5; color: #065f46; }
.status-late { background: #fef3c7; color: #92400e; }
.status-already_marked { background: #dbeafe; color: #1e40af; }
.filter-bar {
    display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
    background: #fff; border-radius: 14px; padding: 14px 18px;
    box-shadow: 0 2px 12px rgba(0,0,0,.04); margin-bottom: 20px;
}
.filter-bar select {
    height: 40px; border-radius: 10px; border: 1px solid #e5e7eb;
    padding: 0 12px; font-size: 13px; min-width: 180px;
}
.attendance-log-card {
    background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.06);
    padding: 20px; margin-bottom: 20px;
}
.attendance-log-card h4 { font-size: 15px; font-weight: 700; color: #111827; margin: 0 0 14px; }
.log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.log-table th {
    background: #f9fafb; padding: 10px 12px; text-align: left;
    font-weight: 600; color: #6b7280; border-bottom: 2px solid #e5e7eb;
}
.log-table td {
    padding: 10px 12px; border-bottom: 1px solid #f3f4f6;
    color: #374151; vertical-align: middle;
}
.log-table tr:hover td { background: #fffbeb; }
.log-photo {
    width: 32px; height: 32px; border-radius: 50%; object-fit: cover;
    border: 2px solid #e5e7eb;
}
.no-logs { text-align: center; padding: 40px; color: #9ca3af; font-size: 14px; }
#scanner-status {
    text-align: center; padding: 12px; font-size: 13px; color: #6b7280;
    background: #f9fafb; border-radius: 10px; margin-top: 10px;
}
#scanner-status.active { color: #065f46; background: #ecfdf5; }
#scanner-status.error { color: #991b1b; background: #fef2f2; }
.start-scan-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 28px; background: linear-gradient(135deg, #f97316, #fb923c);
    color: #fff; border: none; border-radius: 12px; font-size: 15px;
    font-weight: 700; cursor: pointer; box-shadow: 0 4px 16px rgba(249,115,22,.3);
    transition: all .2s;
}
.start-scan-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(249,115,22,.4); }
.stop-scan-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 28px; background: #dc2626; color: #fff; border: none;
    border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer;
}
.time-display { font-size: 28px; font-weight: 800; color: #111827; font-family: 'Courier New', monospace; }
.late-warning { color: #dc2626; font-weight: 700; font-size: 13px; margin-top: 6px; }
.scan-count-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: #fff7ed; color: #f97316; padding: 6px 14px;
    border-radius: 20px; font-size: 13px; font-weight: 700;
}
</style>

<div class="main-content">
<div class="container-fluid">
<div class="qr-scan-wrapper">

    <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 4px; flex-wrap:wrap; gap:10px;">
        <h3 style="font-size:18px; font-weight:800; color:#111827; margin:0;">
            <i class="fa fa-qrcode" style="color:#f97316;"></i> QR Attendance Scan Station
        </h3>
        <div style="display:flex; align-items:center; gap:14px;">
            <div class="time-display" id="liveClock"></div>
            <div class="scan-count-badge"><i class="fa fa-check-circle"></i> Scanned today: <span id="scanCount">0</span></div>
        </div>
    </div>

    <div class="filter-bar">
        <div>
            <label style="font-size:12px; font-weight:600; color:#6b7280; display:block; margin-bottom:4px;">Filter by Class</label>
            <select id="classFilter" onchange="loadAttendanceLog()">
                <option value="0">All Classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo (int)$c['class_id']; ?>"><?php echo e($c['class_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:600; color:#6b7280; display:block; margin-bottom:4px;">Late After</label>
            <input type="text" value="08:30 AM" readonly style="height:40px; border-radius:10px; border:1px solid #e5e7eb; padding:0 12px; font-size:13px; width:120px; background:#f9fafb;">
        </div>
    </div>

    <div class="qr-scanner-card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
            <h4 style="font-size:15px; font-weight:700; color:#111827; margin:0;">
                <i class="fa fa-camera"></i> Camera Scanner
            </h4>
            <div>
                <button class="start-scan-btn" id="startScanBtn" onclick="startScanner()">
                    <i class="fa fa-camera"></i> Start Scanner
                </button>
                <button class="stop-scan-btn" id="stopScanBtn" onclick="stopScanner()" style="display:none;">
                    <i class="fa fa-stop"></i> Stop Scanner
                </button>
            </div>
        </div>
        <div id="qr-reader"></div>
        <div id="scanner-status"><i class="fa fa-info-circle"></i> Click "Start Scanner" to begin scanning QR codes</div>

        <div style="margin-top:14px;">
            <label style="font-size:12px; font-weight:600; color:#6b7280; display:block; margin-bottom:6px;">Or Enter Token Manually</label>
            <div style="display:flex; gap:8px;">
                <input type="text" id="manualToken" placeholder="Enter QR token..."
                    style="flex:1; height:42px; border-radius:10px; border:1px solid #e5e7eb; padding:0 14px; font-size:14px;">
                <button onclick="submitManualToken()" style="padding:0 20px; height:42px; border-radius:10px; border:none; background:#f97316; color:#fff; font-weight:700; cursor:pointer;">
                    <i class="fa fa-paper-plane"></i> Submit
                </button>
            </div>
        </div>
    </div>

    <div class="scan-result-card" id="scanResult">
        <div id="resultPhoto"></div>
        <h3 id="resultName" style="font-size:20px; font-weight:800; color:#111827; margin:0 0 6px;"></h3>
        <p id="resultClass" style="font-size:14px; color:#6b7280; margin:0 0 10px;"></p>
        <div id="resultStatus"></div>
        <p id="resultTime" style="font-size:12px; color:#9ca3af; margin:10px 0 0;"></p>
    </div>

    <div class="attendance-log-card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <h4 style="font-size:15px; font-weight:700; color:#111827; margin:0;">
                <i class="fa fa-list"></i> Today's Attendance Log
            </h4>
            <button onclick="loadAttendanceLog()" style="padding:6px 14px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; font-size:12px; cursor:pointer; font-weight:600;">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </div>
        <div id="logContainer">
            <div class="no-logs"><i class="fa fa-hourglass-half"></i> No scans recorded yet today.</div>
        </div>
    </div>

</div>
</div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
var html5QrCode = null;
var scanning = false;

function startScanner() {
    document.getElementById('startScanBtn').style.display = 'none';
    document.getElementById('stopScanBtn').style.display = 'inline-flex';
    document.getElementById('scanner-status').className = 'active';
    document.getElementById('scanner-status').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Initializing camera...';

    html5QrCode = new Html5Qrcode("qr-reader");
    html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        function onScanSuccess(decodedText) {
            processToken(decodedText);
        },
        function onScanFailure(error) {}
    ).then(function() {
        scanning = true;
        document.getElementById('scanner-status').innerHTML = '<i class="fa fa-video-camera"></i> Scanner active — point camera at QR code';
    }).catch(function(err) {
        document.getElementById('scanner-status').className = 'error';
        document.getElementById('scanner-status').innerHTML = '<i class="fa fa-exclamation-triangle"></i> Camera error: ' + err + '. Check permissions.';
        document.getElementById('startScanBtn').style.display = 'inline-flex';
        document.getElementById('stopScanBtn').style.display = 'none';
    });
}

function stopScanner() {
    if (html5QrCode && scanning) {
        html5QrCode.stop().then(function() {
            scanning = false;
            document.getElementById('startScanBtn').style.display = 'inline-flex';
            document.getElementById('stopScanBtn').style.display = 'none';
            document.getElementById('scanner-status').className = '';
            document.getElementById('scanner-status').innerHTML = '<i class="fa fa-pause-circle"></i> Scanner stopped';
        }).catch(function(e) {});
    }
}

var lastScanTime = 0;
var lastScanToken = '';
function processToken(token) {
    var now = Date.now();
    if (token === lastScanToken && (now - lastScanTime) < 5000) return;
    lastScanTime = now;
    lastScanToken = token;

    playBeep();

    fetch('<?php echo BASE_URL; ?>ajax_qr_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var card = document.getElementById('scanResult');
        card.classList.add('show');

        if (data.photo) {
            document.getElementById('resultPhoto').innerHTML = '<img src="' + data.photo + '" class="student-photo" onerror="this.outerHTML=\'<div class=student-photo-placeholder><i class=fa fa-user></i></div>\'">';
        } else {
            document.getElementById('resultPhoto').innerHTML = '<div class="student-photo-placeholder"><i class="fa fa-user"></i></div>';
        }

        document.getElementById('resultName').textContent = data.student_name || 'Unknown';
        document.getElementById('resultClass').textContent = (data.class_name || '') + (data.section_name ? ' — ' + data.section_name : '');

        var statusClass = 'status-' + (data.status || 'error');
        var statusText = data.status === 'present' ? 'Present' :
                         data.status === 'late' ? 'Late Arrival' :
                         data.status === 'already_marked' ? 'Already Marked' : 'Error';
        document.getElementById('resultStatus').innerHTML = '<span class="status-badge ' + statusClass + '">' + statusText + '</span>';
        document.getElementById('resultTime').textContent = data.scanned_at || '';

        if (data.status === 'late') {
            document.getElementById('resultTime').innerHTML += ' <span class="late-warning">⚠ After 8:30 AM</span>';
        }

        setTimeout(function() { card.classList.remove('show'); }, 5000);
        loadAttendanceLog();
    })
    .catch(function(err) {
        var card = document.getElementById('scanResult');
        card.classList.add('show');
        document.getElementById('resultPhoto').innerHTML = '<div class="student-photo-placeholder" style="border-color:#dc2626;"><i class="fa fa-exclamation" style="color:#dc2626;"></i></div>';
        document.getElementById('resultName').textContent = 'Scan Error';
        document.getElementById('resultClass').textContent = 'Could not process QR code';
        document.getElementById('resultStatus').innerHTML = '<span class="status-badge" style="background:#fef2f2;color:#991b1b;">Error</span>';
    });
}

function submitManualToken() {
    var token = document.getElementById('manualToken').value.trim();
    if (!token) return;
    processToken(token);
    document.getElementById('manualToken').value = '';
}

document.getElementById('manualToken').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { submitManualToken(); }
});

function loadAttendanceLog() {
    var classId = document.getElementById('classFilter').value;
    fetch('<?php echo BASE_URL; ?>ajax_qr_attendance.php?limit=20&class_id=' + classId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var container = document.getElementById('logContainer');
        if (!data.success || !data.log || data.log.length === 0) {
            container.innerHTML = '<div class="no-logs"><i class="fa fa-hourglass-half"></i> No scans recorded yet today.</div>';
            document.getElementById('scanCount').textContent = '0';
            return;
        }

        var html = '<table class="log-table"><thead><tr>';
        html += '<th>#</th><th>Photo</th><th>Student</th><th>Class</th><th>Time</th><th>Status</th>';
        html += '</tr></thead><tbody>';

        data.log.forEach(function(row, i) {
            var photoHtml = row.photo_url
                ? '<img src="' + row.photo_url + '" class="log-photo" onerror="this.style.display=\'none\';">'
                : '<div style="width:32px;height:32px;border-radius:50%;background:#fff7ed;display:flex;align-items:center;justify-content:center;color:#f97316;font-weight:700;font-size:12px;">' + (row.first_name || '?')[0] + '</div>';

            var statusBadge = row.status === 'present'
                ? '<span class="status-badge status-present">Present</span>'
                : '<span class="status-badge status-late">Late</span>';

            var time = row.scanned_at ? row.scanned_at.split(' ')[1] || row.scanned_at : '-';

            html += '<tr>';
            html += '<td>' + (i + 1) + '</td>';
            html += '<td>' + photoHtml + '</td>';
            html += '<td style="font-weight:600;">' + (row.first_name + ' ' + row.last_name) + '</td>';
            html += '<td>' + (row.class_name || '-') + ' ' + (row.section_name || '') + '</td>';
            html += '<td><i class="fa fa-clock-o" style="color:#9ca3af;"></i> ' + time + '</td>';
            html += '<td>' + statusBadge + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        container.innerHTML = html;
        document.getElementById('scanCount').textContent = data.log.length;
    });
}

function playBeep() {
    try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 1200;
        osc.type = 'sine';
        gain.gain.value = 0.3;
        osc.start();
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
        osc.stop(ctx.currentTime + 0.3);
    } catch(e) {}
}

function updateClock() {
    var now = new Date();
    var h = String(now.getHours()).padStart(2, '0');
    var m = String(now.getMinutes()).padStart(2, '0');
    var s = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('liveClock').textContent = h + ':' + m + ':' + s;
}
setInterval(updateClock, 1000);
updateClock();

loadAttendanceLog();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
