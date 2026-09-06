<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Video Lectures';

db_query("CREATE TABLE IF NOT EXISTS student_lectures (
    lecture_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT,
    lecture_date DATE,
    subject VARCHAR(191),
    content TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$message = '';
$error = '';

$sel_session = $_GET['session'] ?? get_setting('session_year', '2026-2027');
$sel_class = (int) ($_GET['class_id'] ?? 0);
$sel_section = $_GET['section'] ?? 'All';
$sel_date = trim($_GET['lecture_date'] ?? '');

$edit_id = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_lecture') {
        $lid = (int) ($_POST['lecture_id'] ?? 0);
        $cid = (int) ($_POST['class_id'] ?? 0);
        $ldate = trim($_POST['lecture_date'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($cid <= 0 || $ldate === '' || $subject === '') {
            $error = 'Please provide class, date and subject.';
        } else {
            if ($lid > 0) {
                $st = db_prepare('UPDATE student_lectures SET class_id=?, lecture_date=?, subject=?, content=? WHERE lecture_id=?');
                $st->bind_param('isssi', $cid, $ldate, $subject, $content, $lid);
                $st->execute();
                $message = 'Lecture updated successfully.';
            } else {
                $st = db_prepare('INSERT INTO student_lectures (class_id, lecture_date, subject, content, created_by) VALUES (?, ?, ?, ?, ?)');
                $st->bind_param('isssi', $cid, $ldate, $subject, $content, $uid);
                $st->execute();
                $message = 'Lecture added successfully.';
            }
        }
    }

    if ($action === 'delete_lecture') {
        $lid = (int) ($_POST['lecture_id'] ?? 0);
        if ($lid > 0) {
            $st = db_prepare('DELETE FROM student_lectures WHERE lecture_id=?');
            $st->bind_param('i', $lid);
            $st->execute();
            $message = 'Lecture deleted successfully.';
        }
    }
}

$sessions = [];
for ($y = 2018; $y <= 2030; $y++) { $sessions[] = $y . '-' . substr($y + 1, -2); }
if (!in_array($sel_session, $sessions, true)) { $sel_session = get_setting('session_year', '2026-2027'); }

$classes = [];
$res = db_query('SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name');
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sections = [];
$ssq = $sel_class > 0 ? 'SELECT section_id, class_id, section_name FROM sections WHERE class_id=? ORDER BY section_name' : 'SELECT section_id, class_id, section_name FROM sections ORDER BY section_name';
$sst = db_prepare($ssq);
if ($sel_class > 0) { $sst->bind_param('i', $sel_class); }
$sst->execute();
$ssr = $sst->get_result();
while ($row = $ssr->fetch_assoc()) { $sections[] = $row; }

$sections_by_class = [];
foreach ($sections as $sec) { $sections_by_class[(int) $sec['class_id']][] = $sec; }

$where = [];
$params = [];
$types = '';
if (preg_match('/^(\d{4})-(\d{2})$/', $sel_session, $sm)) {
    $y1 = (int) $sm[1];
    $where[] = 'l.lecture_date BETWEEN ? AND ?';
    $params[] = $y1 . '-07-01';
    $params[] = ($y1 + 1) . '-06-30';
    $types .= 'ss';
}
if ($sel_class > 0) {
    $where[] = 'l.class_id = ?';
    $params[] = $sel_class;
    $types .= 'i';
}
if ($sel_date !== '') {
    $where[] = 'l.lecture_date = ?';
    $params[] = $sel_date;
    $types .= 's';
}

$lectures = [];
$sql = 'SELECT l.*, c.class_name FROM student_lectures l LEFT JOIN classes c ON l.class_id = c.class_id';
if (count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY l.lecture_date DESC, l.lecture_id DESC';
$st = db_prepare($sql);
if (count($params) > 0) { $st->bind_param($types, ...$params); }
$st->execute();
$row = $st->get_result();
while ($r = $row->fetch_assoc()) { $lectures[] = $r; }

$edit_lecture = null;
if ($edit_id > 0) {
    $st = db_prepare('SELECT * FROM student_lectures WHERE lecture_id=? LIMIT 1');
    $st->bind_param('i', $edit_id);
    $st->execute();
    $er = $st->get_result();
    if ($r = $er->fetch_assoc()) { $edit_lecture = $r; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
      .vl-wrap{ padding: 4px 0 16px; }
      .vl-crumb{ font-size:13px; color:#7b8794; margin-bottom:14px; }
      .vl-crumb a{ color:#5e6ee8; text-decoration:none; font-weight:500; }
      .vl-crumb i{ font-size:10px; margin:0 6px; vertical-align:middle; }

      .vl-filter-card{
        background:linear-gradient(135deg,#6a5cf0 0%,#5e6ee8 55%,#4f8ef7 100%);
        border-radius:14px;
        padding:16px 18px;
        box-shadow:0 8px 24px rgba(94,110,232,0.25);
        margin-bottom:18px;
      }
      .vl-filter-card form{ margin:0; }
      .vl-filter-row{
        display:flex;
        flex-wrap:wrap;
        align-items:flex-end;
        gap:10px;
      }
      .vl-field{ display:flex; flex-direction:column; min-width:150px; flex:1 1 150px; }
      .vl-field label{
        color:#eef0ff; font-size:11px; font-weight:600; letter-spacing:.3px;
        text-transform:uppercase; margin-bottom:5px;
      }
      .vl-field select, .vl-field input{
        border:none; border-radius:8px; height:38px; padding:0 10px;
        font-size:13px; box-shadow:0 2px 6px rgba(0,0,0,0.12);
      }
      .vl-actions{ display:flex; gap:8px; flex:0 0 auto; }
      .vl-btn{
        height:38px; border:none; border-radius:8px; padding:0 18px;
        font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap;
        display:inline-flex; align-items:center; gap:6px; text-decoration:none;
      }
      .vl-btn-search{ background:#ffffff; color:#5e6ee8; }
      .vl-btn-search:hover{ background:#eef0ff; color:#4f4fd6; }
      .vl-btn-add{ background:#22c55e; color:#fff; }
      .vl-btn-add:hover{ background:#16a34a; color:#fff; }

      .vl-table-card{
        background:#fff; border-radius:14px; overflow:hidden;
        box-shadow:0 4px 16px rgba(30,41,59,0.08);
      }
      .vl-table-head{
        display:flex; align-items:center; justify-content:space-between;
        padding:14px 18px; border-bottom:1px solid #eef0f4;
      }
      .vl-table-head h3{ margin:0; font-size:16px; font-weight:700; color:#2d3348; }
      .vl-count-badge{
        background:#eef0ff; color:#5e6ee8; font-size:12px; font-weight:700;
        padding:4px 12px; border-radius:20px;
      }
      table.vl-table{ width:100%; border-collapse:collapse; }
      table.vl-table thead th{
        background:#f7f8fc; color:#5b6472; font-size:11px; font-weight:700;
        text-transform:uppercase; letter-spacing:.4px; padding:10px 12px;
        border-bottom:2px solid #eef0f4; text-align:left;
      }
      table.vl-table tbody td{
        padding:9px 12px; font-size:13px; color:#333c4d;
        border-bottom:1px solid #f1f2f7; vertical-align:middle;
      }
      table.vl-table tbody tr:hover{ background:#f9faff; }
      .vl-badge-subject{
        background:#eef2ff; color:#4f5fd6; padding:3px 10px;
        border-radius:6px; font-size:12px; font-weight:600;
      }
      .vl-badge-class{
        background:#eafbf0; color:#1f9d55; padding:3px 10px;
        border-radius:6px; font-size:12px; font-weight:600;
      }
      .vl-icon-btn{
        display:inline-flex; align-items:center; justify-content:center;
        width:28px; height:28px; border-radius:7px; margin-right:4px;
        color:#fff !important; text-decoration:none; font-size:12px;
        border:none; cursor:pointer;
      }
      .vl-icon-btn.play{ background:#3b82f6; }
      .vl-icon-btn.play:hover{ background:#2563eb; }
      .vl-icon-btn.edit{ background:#22c55e; }
      .vl-icon-btn.edit:hover{ background:#16a34a; }
      .vl-icon-btn.del{ background:#ef4444; }
      .vl-icon-btn.del:hover{ background:#dc2626; }
      .vl-add-form{ padding:18px 20px; }
      .vl-add-form label{ font-size:12px; font-weight:600; color:#374151; }
      .vl-add-form .form-control{ border-radius:8px; }
      .vl-lecture-text{ font-size:14px; color:#374151; line-height:1.6; white-space:pre-wrap; }
</style>

<div class="vl-wrap">
  <div class="vl-crumb">
    <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a>
    <i class="fa fa-angle-double-right"></i>
    Parents Portal
    <i class="fa fa-angle-double-right"></i>
    Video Lectures
  </div>

  <?php if ($message !== ''): ?>
    <div class="alert alert-success alert-dismissible fade in">
        <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
        <?php echo e($message); ?>
    </div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade in">
        <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
        <?php echo e($error); ?>
    </div>
  <?php endif; ?>

<section class="add_sub_agent" id="table_sub_agent">

    <div class="vl-filter-card">
      <form action="<?php echo BASE_URL; ?>view_student_lecture.php" enctype="multipart/form-data" method="get">
        <div class="vl-filter-row">
          <div class="vl-field">
            <label>Session</label>
            <select name="session" id="session">
              <?php foreach ($sessions as $sv): ?>
                <option value="<?php echo e($sv); ?>" <?php echo $sel_session === $sv ? 'selected' : ''; ?>><?php echo e($sv); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="vl-field">
            <label>Class</label>
            <select name="class_id" class="inputheight">
              <option value="">All</option>
              <?php foreach ($classes as $cl): ?>
                <option value="<?php echo (int) $cl['class_id']; ?>" <?php echo $sel_class === (int) $cl['class_id'] ? 'selected' : ''; ?>><?php echo e($cl['class_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="vl-field">
            <label>Section</label>
            <select name="section" id="txt_section" class="inputheight">
                <option value="All">All</option>
                <?php foreach ($sections as $sec): ?>
                    <option value="<?php echo (int) $sec['section_id']; ?>" <?php echo $sel_section == $sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                <?php endforeach; ?>
            </select>
          </div>

          <div class="vl-field">
            <label>Date</label>
            <input type="date" name="lecture_date" value="<?php echo e($sel_date); ?>">
          </div>

          <div class="vl-actions">
            <button type="submit" class="vl-btn vl-btn-search"><i class="fa fa-search"></i> Search</button>
            <a href="#addLecture" class="vl-btn vl-btn-add"><i class="fa fa-plus"></i> Add Video Lecture</a>
          </div>
        </div>
      </form>
    </div>

    <div class="vl-table-card">
      <div class="vl-table-head">
        <h3>List Of Lectures</h3>
        <span class="vl-count-badge"><?php echo count($lectures); ?> Records</span>
      </div>
      <div style="overflow-x:auto;">

      <table id="listofstudents1" data-page-length='100' class="vl-table" style="width:100%">

          <thead>
            <tr>

                <th width="5%">S.No</th>
                <th width="20%">Subject</th>
                <th width="20%">Class/Section</th>
                <th width="10%">Date</th>
                <th width="15%">Action</th>

            </tr>
        </thead>
        <tbody>
            <?php if (count($lectures) === 0): ?>
                <tr><td colspan="5" style="text-align:center; color:#9CA3AF; padding:30px;">No lectures found for the selected filters.</td></tr>
            <?php endif; ?>
            <?php $i = 1; foreach ($lectures as $lec):
                $secName = '';
                if (isset($sections_by_class[(int) $lec['class_id']])) {
                    $secName = $sections_by_class[(int) $lec['class_id']][0]['section_name'];
                }
                $clsTxt = trim(e($lec['class_name'] ?? ''));
                $secTxt = trim(e($secName));
                $badgeTxt = ($clsTxt !== '' ? $clsTxt : '') . ($secTxt !== '' ? ' - ' . $secTxt : '');
            ?>
            <tr>
                <td style="text-align:center;"><?php echo $i; ?></td>
                <td><span class="vl-badge-subject"><?php echo e($lec['subject']); ?></span></td>
                <td style="text-align:center;"><span class="vl-badge-class"><?php echo $badgeTxt !== '' ? $badgeTxt : '-'; ?></span></td>
                <td><?php echo date('d-M-Y', strtotime($lec['lecture_date'])); ?></td>
                <td>
                    <a href="#" data-toggle="modal" data-target="#videoModal" data-video="<?php echo e($lec['content']); ?>" onclick="loadVideoModal(this)" class="vl-icon-btn play" title="Watch Video"> <i class="fa fa-play" aria-hidden="true"></i></a>
                    <a href="<?php echo BASE_URL; ?>view_student_lecture.php?edit=<?php echo (int) $lec['lecture_id']; ?>" class="vl-icon-btn edit" title="Edit"> <i class="fa fa-pencil" aria-hidden="true"></i></a>
                    <form method="post" action="<?php echo BASE_URL; ?>view_student_lecture.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this record');">
                        <input type="hidden" name="action" value="delete_lecture">
                        <input type="hidden" name="lecture_id" value="<?php echo (int) $lec['lecture_id']; ?>">
                        <button type="submit" class="vl-icon-btn del" title="Delete"><i class="fa fa-remove"></i></button>
                    </form>
                </td>
            </tr>
            <?php $i++; endforeach; ?>

        </tbody>

    </table>

  </div>
  </div>

  <div class="vl-table-card" id="addLecture" style="margin-top:18px;">
    <div class="vl-table-head">
      <h3><?php echo $edit_lecture ? 'Edit Lecture' : 'Add Video Lecture'; ?></h3>
    </div>
    <form class="vl-add-form" action="<?php echo BASE_URL; ?>view_student_lecture.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_lecture">
      <?php if ($edit_lecture): ?>
        <input type="hidden" name="lecture_id" value="<?php echo (int) $edit_lecture['lecture_id']; ?>">
      <?php endif; ?>
      <div class="row">
        <div class="col-md-4 col-xs-12">
          <div class="form-group">
            <label>Class</label>
            <select name="class_id" class="form-control" required>
              <option value="">Select Class</option>
              <?php foreach ($classes as $cl): ?>
                <option value="<?php echo (int) $cl['class_id']; ?>" <?php echo $edit_lecture && (int) $edit_lecture['class_id'] === (int) $cl['class_id'] ? 'selected' : ''; ?>><?php echo e($cl['class_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-4 col-xs-12">
          <div class="form-group">
            <label>Date</label>
            <input type="date" name="lecture_date" class="form-control" value="<?php echo $edit_lecture ? e($edit_lecture['lecture_date']) : e($sel_date); ?>" required>
          </div>
        </div>
        <div class="col-md-4 col-xs-12">
          <div class="form-group">
            <label>Subject</label>
            <input type="text" name="subject" class="form-control" value="<?php echo $edit_lecture ? e($edit_lecture['subject']) : ''; ?>" placeholder="Enter Subject" required>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label>Content / Lecture Details</label>
        <textarea name="content" class="form-control" rows="4" placeholder="Enter lecture notes, homework or video link"><?php echo $edit_lecture ? e($edit_lecture['content']) : ''; ?></textarea>
      </div>
      <button type="submit" class="vl-btn vl-btn-add"><i class="fa fa-save"></i> Save Lecture</button>
    </form>
  </div>

</section>

</div>

<div id="videoModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title">Watch Video</h4>
        </div>
        <div class="modal-body" style="padding:0;">
          <div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; display:none;" id="videoModalFrameWrap">
            <iframe id="videoModalFrame" src="" style="position:absolute; top:0; left:0; width:100%; height:100%;" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
          </div>
          <div id="videoModalText" class="vl-lecture-text" style="padding:20px; display:none;"></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function toYouTubeEmbed(url) {
      var m = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
      return m ? 'https://www.youtube.com/embed/' + m[1] + '?autoplay=1' : null;
    }
    function loadVideoModal(el) {
      var link = el.getAttribute('data-video');
      var embed = toYouTubeEmbed(link);
      var frameWrap = document.getElementById('videoModalFrameWrap');
      var textEl = document.getElementById('videoModalText');
      document.getElementById('videoModalFrame').src = '';
      if (embed) {
        frameWrap.style.display = 'block';
        textEl.style.display = 'none';
        document.getElementById('videoModalFrame').src = embed;
      } else {
        frameWrap.style.display = 'none';
        textEl.style.display = 'block';
        textEl.textContent = link || 'No content available.';
      }
    }
    $('#videoModal').on('hidden.bs.modal', function () {
      document.getElementById('videoModalFrame').src = '';
    });
  </script>

<?php include __DIR__ . '/includes/footer.php'; ?>