<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Student Diaries';

db_query("CREATE TABLE IF NOT EXISTS student_diaries (
  diary_id INT(11) NOT NULL AUTO_INCREMENT,
  class_id INT(11) DEFAULT NULL,
  section_id INT(11) DEFAULT NULL,
  student_id INT(11) DEFAULT NULL,
  subject VARCHAR(191) DEFAULT NULL,
  diary_date DATE DEFAULT NULL,
  content LONGTEXT,
  created_by INT(11) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (diary_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (isset($_GET['getsecall'])) {
    $cid = (int) $_GET['getsecall'];
    $sections = db_query("SELECT section_id, section_name FROM sections WHERE class_id = $cid ORDER BY section_name ASC");
    echo '<option value="All">All</option>';
    while ($row = $sections->fetch_assoc()) {
        echo '<option value="' . (int) $row['section_id'] . '">' . htmlspecialchars($row['section_name'], ENT_QUOTES) . '</option>';
    }
    exit;
}

$msg = '';

if (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'DeleteDiary') {
    $diary_id = (int) $_GET['id'];
    $stmt = db_prepare("DELETE FROM student_diaries WHERE diary_id = ?");
    $stmt->bind_param('i', $diary_id);
    $stmt->execute();
    $msg = 'Diary deleted successfully.';
}

$sessions = array('3' => '2018-2019', '4' => '2019-2020', '5' => '2020-2021', '6' => '2021-2022', '7' => '2022-2023', '8' => '2023-2024', '9' => '2024-2025', '10' => '2025-2026', '11' => '2026-2027', '12' => '2027-2028', '13' => '2028-2029', '14' => '2029-2030', '15' => '2030-2031');
$cur_session = get_setting('session_year', '2026-2027');
$cur_key = array_search($cur_session, $sessions, true);
if ($cur_key === false) {
    $cur_key = '11';
}

$sel_session = isset($_GET['session']) && isset($sessions[$_GET['session']]) ? $_GET['session'] : $cur_key;
$sel_class = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 0;
$sel_section = isset($_GET['section']) && $_GET['section'] !== '' ? $_GET['section'] : 'All';

$classes = array();
$res = db_query("SELECT class_id, class_name FROM classes ORDER BY class_name ASC");
while ($row = $res->fetch_assoc()) {
    $classes[(int) $row['class_id']] = $row['class_name'];
}

$where = array();
$params = array();
$types = '';

if ($sel_class > 0) {
    $where[] = 'd.class_id = ?';
    $params[] = $sel_class;
    $types .= 'i';
}
if ($sel_section !== '' && $sel_section !== 'All') {
    $where[] = 'd.section_id = ?';
    $params[] = (int) $sel_section;
    $types .= 'i';
}

$sql = "SELECT d.diary_id, d.diary_date, d.section_id, d.class_id, c.class_name, s.section_name
        FROM student_diaries d
        LEFT JOIN sections s ON s.section_id = d.section_id
        LEFT JOIN classes c ON c.class_id = d.class_id";
if (count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY d.diary_date DESC, d.diary_id DESC';

$diaries = array();
if (count($params) > 0) {
    $stmt = db_prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = db_query($sql);
}
while ($row = $res->fetch_assoc()) {
    $diaries[] = $row;
}

include __DIR__ . '/includes/header.php';
?>
<style>
    .breadcrumb-custom {
        background: #fff;
        padding: 14px 18px;
        border-radius: 6px;
        margin-bottom: 18px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        font-size: 14px;
        color: #333;
        border-left: 4px solid #ff7800;
    }
    .breadcrumb-left a { color: #ff7800; text-decoration: none; }
    .records-count {
        display: inline-block;
        background: #ff7800;
        color: #fff;
        border-radius: 20px;
        padding: 2px 12px;
        font-weight: 600;
        font-size: 13px;
        margin-left: 8px;
    }
    .content-wrapper { }
    .table-header h3 { margin: 0 0 12px; font-weight: 600; }
    .table-container { background: #fff; border-radius: 6px; padding: 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
    .inputheight { height: 34px; }
</style>
<div class="right_col student-diaries-page" role="main" style="margin-top: -80px;">
  <div class="breadcrumb-custom">
    <div class="breadcrumb-left">
      <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> <i class="fa fa-angle-double-right"></i>
      Parents Portal <i class="fa fa-angle-double-right"></i>
      Student Diaries
      <span class="records-count"><?php echo count($diaries); ?></span>
    </div>
  </div>

<?php if ($msg !== ''): ?>
<div class="alert alert-success alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <?php echo e($msg); ?>
</div>
<?php endif; ?>

  <div class="content-wrapper">

    <section class="add_sub_agent" id="table_sub_agent">
      <div class="container">

        <div>
          <form action="<?php echo BASE_URL; ?>view_student_diaries.php" enctype="multipart/form-data" method="get">
            <div class="panel panel-default">
              <div class="panel-body">

                <div class="col-md-12">
                  <div class="col-md-2 col-xs-12">
                    <div class="form-group">
                      <label class="required">Session</label>
                      <select name="session" id="session" class="form-control" onChange="getterm(this.value)">
                        <?php foreach ($sessions as $sk => $sv): ?>
                        <option value="<?php echo (int) $sk; ?>" <?php echo $sk == $sel_session ? 'selected' : ''; ?>><?php echo e($sv); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="col-md-2 col-xs-6">
                    <div class="form-group">
                      <label class="required">Class</label>
                      <select name="class_id" class="form-control inputheight" onChange="getsecall(this.value)">
                        <option value="">All</option>
                        <?php foreach ($classes as $cid => $cname): ?>
                        <option value="<?php echo (int) $cid; ?>" <?php echo $sel_class === $cid ? 'selected' : ''; ?>><?php echo e($cname); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="col-md-2 col-xs-6">
                    <div class="form-group">
                      <label class="required">Section</label>
                      <select name="section" id="txt_section" class="form-control inputheight">
                        <option value="All">All</option>
                      </select>
                    </div>
                  </div>

                  <div class="col-md-3">
                    <div class="form-group">
                      <input type="hidden" name="addaccountAdmin" value="1">
                      <label style="opacity: 0; height: 22px; display: block;">Search</label>
                      <button type="submit" class="btn btn-primary">Search</button>
                      <a href="<?php echo BASE_URL; ?>add_student_diary.php" class="btn btn-primary btn_default">Add Diary</a>
                    </div>
                  </div>

                </div>
                <div class="clearfix"></div>
              </div>
            </div>
          </form>
          <div class="clearfix"></div>
        </div>

        <div class="col-md-12">

          <div class="table-header">
            <h3>List Of Diaries</h3>
          </div>

          <div class="table-container">
            <div style="overflow-x:auto;">
              <table id="listofstudents1" data-page-length='100' class="table table-striped table-bordered" style="width:100%">

                <thead>
                  <tr>
                    <th width="5%">S.No</th>
                    <th width="20%">Class/Section</th>
                    <th width="20%">Diary Details</th>
                    <th width="15%">Date</th>
                    <th width="10%">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1; foreach ($diaries as $d): ?>
                  <tr>
                    <td style="text-align:center;"> <?php echo (int) $i; ?> </td>
                    <td style="text-align:center;"> <?php echo e($d['class_name'] . ' - ' . $d['section_name']); ?> </td>
                    <td>
                      <a class="btn btn-success" style="padding: 0px 5px; font-size:14px;color:white;" href="<?php echo BASE_URL; ?>diary_details.php?section=<?php echo (int) $d['section_id']; ?>&class_id=<?php echo (int) $d['class_id']; ?>&diary_id=<?php echo (int) $d['diary_id']; ?>"> View Diary </a>
                    </td>
                    <td style=""> <?php echo $d['diary_date'] ? date('d-M-Y', strtotime($d['diary_date'])) : 'N/A'; ?> </td>
                    <td>
                      <a style="padding: 0px 5px; font-size:14px;"
                         href="<?php echo BASE_URL; ?>add_student_diary.php?diaryup=<?php echo (int) $d['diary_id']; ?>&session=&class_id=<?php echo (int) $d['class_id']; ?>&section=<?php echo (int) $d['section_id']; ?>&diaryDate=<?php echo $d['diary_date'] ? date('Y-m-d', strtotime($d['diary_date'])) : ''; ?>&subject="
                         style="cursor:pointer;" class="btn btn-success"> <i class="fa fa-pencil" aria-hidden="true"></i></a>
                      <a href="<?php echo BASE_URL; ?>view_student_diaries.php?id=<?php echo (int) $d['diary_id']; ?>&action=DeleteDiary" style="padding: 0px 5px; font-size:14px;"
                         onClick="return confirm('Are you sure you want to delete this record');" class="btn btn-danger"><i class="fa fa-remove"></i> </a>
                    </td>
                  </tr>
                  <?php $i++; endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>

      </div>
    </section>

  </div>
</div>

<script type="text/javascript">
  function getsecall(id){
    var xmlhttp;
    if (window.XMLHttpRequest) {
      xmlhttp = new XMLHttpRequest();
    } else {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function() {
      if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
        document.getElementById("txt_section").innerHTML = xmlhttp.responseText;
      }
    }
    xmlhttp.open("GET", "<?php echo BASE_URL; ?>view_student_diaries.php?getsecall=" + id, true);
    xmlhttp.send();
  }

  function getterm(id) {
    return true;
  }

  <?php if ($sel_class > 0): ?>
  window.addEventListener('DOMContentLoaded', function() {
    getsecall('<?php echo (int) $sel_class; ?>');
  });
  <?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>