<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Generate Student ID Cards';

// ---- Student filters ----
$selSession = trim((string)($_GET['session'] ?? ''));
$selClass   = (int) ($_GET['class_id'] ?? 0);
$selSection = (int) ($_GET['section_id'] ?? 0);

// ---- Data ----
$sessions = [];
$res = db_query("SELECT DISTINCT session FROM students WHERE session IS NOT NULL AND session <> '' ORDER BY session");
while ($row = $res->fetch_assoc()) { $sessions[] = $row['session']; }

$classes = [];
$res = db_query("SELECT class_id, class_name FROM classes WHERE status=1 ORDER BY class_name");
while ($row = $res->fetch_assoc()) { $classes[] = $row; }

$sections = [];
if ($selClass > 0) {
    $res = db_query("SELECT section_id, section_name FROM sections WHERE class_id=$selClass ORDER BY section_name");
    while ($row = $res->fetch_assoc()) { $sections[] = $row; }
}

$students = [];
$sql = "SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.gr_no,
               c.class_name, sec.section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        WHERE s.status=1";
if ($selClass > 0) { $sql .= " AND s.class_id=$selClass"; }
if ($selSection > 0) { $sql .= " AND s.section_id=$selSection"; }
if ($selSession !== '') { $sql .= " AND s.session='" . db_connect()->real_escape_string($selSession) . "'"; }
$sql .= " ORDER BY s.first_name";
$res = db_query($sql);
while ($row = $res->fetch_assoc()) { $students[] = $row; }

include __DIR__ . '/includes/header.php';
?>
<style>
      .scg-wrap { padding: 0 4px 40px; font-family: 'Inter', system-ui, sans-serif; color: #212529; }
      .scg-wrap * { box-sizing: border-box; }
      .scg-crumb { font-size: 12.5px; color: #8a99a8; padding: 2px 4px 8px; }
      .scg-crumb a { color: #3e7cb1; text-decoration: none; }
      .scg-crumb a:hover { text-decoration: underline; }

      .scg-title-row { display: flex; align-items: center; gap: 8px; padding: 0 4px 8px; font-size: 17px; font-weight: 800; color: #2a3f54; }
      .scg-title-row i { color: #3e7cb1; font-size: 16px; }

      /* Compact breadcrumb-style stepper (reference: numbered circle + label + connector line) */
      .scg-stepper { display: flex; align-items: center; padding: 2px 4px 16px; }
      .scg-step-pill { display: flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 700; color: #3e7cb1; white-space: nowrap; }
      .scg-step-circle { width: 22px; height: 22px; border-radius: 50%; background: #3e7cb1; color: #fff; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
      .scg-step-line { flex: 1 1 30px; max-width: 70px; height: 2px; background: #d6e2ec; margin: 0 10px; }
      @media (max-width: 650px) {
        .scg-step-pill span.lbl { display: none; }
        .scg-step-line { max-width: 26px; }
      }

      .scg-panel { background: #fff; border: 1px solid #e6e9ed; border-radius: 10px; margin-bottom: 12px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(42,63,84,.06); }

      /* Filters */
      .scg-filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
      .scg-filter-col { flex: 1 1 170px; min-width: 160px; }
      .scg-filter-col label { display: block; font-size: 11.5px; font-weight: 700; color: #495057; margin-bottom: 5px; }
      .scg-filter-col select, .scg-filter-col input { width: 100%; height: 38px; border: 1px solid #d6dee5; border-radius: 8px; padding: 0 12px; font-size: 13.5px; background: #fff; color: #2a3f54; }
      .scg-filter-col select:focus, .scg-filter-col input:focus { outline: none; border-color: #3e7cb1; box-shadow: 0 0 0 3px rgba(62,124,177,.14); }
      .scg-search-btn { height: 38px; padding: 0 22px; border: none; border-radius: 8px; background: #3e7cb1; color: #fff; font-weight: 700; font-size: 13.5px; cursor: pointer; flex-shrink: 0; }
      .scg-search-btn:hover { background: #336a99; }

      /* Format tiles — sticky, above the table, compact */
      .scg-tiles-panel { position: sticky; top: 0; z-index: 40; }
      .scg-tiles-hd { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; color: #2a3f54; margin-bottom: 9px; }
      .scg-tiles-hd .scg-tiles-hint { font-weight: 600; color: #6c757d; font-size: 11.5px; }
      .scg-tiles-hd .scg-tiles-hint b { color: #27ae60; }
      .scg-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px; }
      .scg-tile { display: flex; align-items: center; gap: 9px; border: 1.5px solid #e6e9ed; border-radius: 9px; padding: 7px 10px; cursor: pointer; background: #fff; text-align: left; transition: border-color .15s, box-shadow .15s; }
      .scg-tile:hover { border-color: #3e7cb1; box-shadow: 0 2px 10px rgba(62,124,177,.15); }
      .scg-tile-ic { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #fff; flex-shrink: 0; }
      .scg-tile h3 { margin: 0; font-size: 12px; font-weight: 700; color: #2a3f54; line-height: 1.2; }
      .scg-tile p { margin: 1px 0 0; font-size: 10px; color: #8a99a8; line-height: 1.25; }
      .scg-tile.portrait .scg-tile-ic { background: linear-gradient(135deg,#4facfe,#3e7cb1); }
      .scg-tile.landscape .scg-tile-ic { background: linear-gradient(135deg,#43cea2,#2c9a6f); }
      .scg-tile.family .scg-tile-ic { background: linear-gradient(135deg,#f857a6,#d63c8a); }
      .scg-tile.back .scg-tile-ic { background: linear-gradient(135deg,#a18cd1,#7b5cb5); }

      /* Student picker */
      .scg-pick-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 12px; }
      .scg-pick-count { font-size: 13px; color: #495057; }
      .scg-pick-count b { color: #2a3f54; font-size: 14px; }
      .scg-pick-count .scg-selected-n { color: #27ae60; font-weight: 800; }
      .scg-search-box { position: relative; margin-left: auto; flex: 0 1 260px; }
      .scg-search-box input { width: 100%; height: 36px; border: 1px solid #d6dee5; border-radius: 8px; padding: 0 14px 0 34px; font-size: 13px; }
      .scg-search-box i { position: absolute; left: 12px; top: 11px; color: #9aa7b4; font-size: 13px; }
      .scg-select-all-btn { height: 36px; padding: 0 16px; border: 1px solid #d6dee5; border-radius: 8px; background: #fff; color: #3e7cb1; font-weight: 600; font-size: 13px; cursor: pointer; }
      .scg-select-all-btn:hover { background: #f2f6f9; }

      table.scg-tbl thead th { background: #eef2f6; color: #33475b; font-size: 12px; text-transform: uppercase; letter-spacing: .3px; padding: 11px 10px; border-bottom: 2px solid #dde3e9; }
      table.scg-tbl tbody td { padding: 9px 10px; font-size: 13px; vertical-align: middle; }
      table.scg-tbl tbody tr:hover { background: #f5f9fc; }
      .scg-chk { width: 18px; height: 18px; cursor: pointer; }
      .scg-empty { text-align: center; padding: 40px 20px; color: #95a5a6; }
      .scg-empty i { font-size: 36px; color: #d5dbdb; display: block; margin-bottom: 10px; }

      @media (max-width: 700px) {
        .scg-search-box { margin-left: 0; flex-basis: 100%; }
      }

      /* Back Side Cards modal */
      .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20,29,38,.6); z-index: 1050; display: none; align-items: center; justify-content: center; }
      .modal-card { background: #fff; width: 92%; max-width: 440px; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.3); overflow: hidden; }
      .modal-header { padding: 16px 20px; border-bottom: 1px solid #eef1f4; font-weight: 700; font-size: 15px; color: #2a3f54; }
      .modal-body { padding: 18px 20px; }
      .modal-footer { padding: 14px 20px; border-top: 1px solid #eef1f4; text-align: right; display: flex; justify-content: flex-end; gap: 8px; }
      .modal-close { background: #f1f4f6; border: none; color: #495057; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }
      .modal-primary { background: #7b5cb5; border: none; color: #fff; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }
      .modal-body label.control-lbl { font-weight: 700; font-size: 12.5px; color: #495057; display: block; margin-bottom: 8px; }
      .radio-row { display: flex; gap: 18px; align-items: center; font-size: 13.5px; }
      .radio-row label { cursor: pointer; font-weight: 500; }
    </style>

<script>
// Load sections when a class is selected (local JSON endpoint)
function getsec(id){
  var sectionEl = document.getElementById("txt_section");
  if (!id || id === "" || id === "All") {
    if (sectionEl) { sectionEl.innerHTML = '<option value="All">All Sections</option>'; }
    return;
  }
  fetch('<?php echo BASE_URL; ?>ajax_get_sections.php?class_id=' + encodeURIComponent(id))
    .then(function(r){ return r.json(); })
    .then(function(data){
      var html = '<option value="All">All Sections</option>';
      if (data) {
        data.forEach(function(s){ html += '<option value="' + s.section_id + '">' + s.section_name + '</option>'; });
      }
      sectionEl.innerHTML = html;
    })
    .catch(function(){ sectionEl.innerHTML = '<option value="All">All Sections</option>'; });
}

// Collect ticked student ids. Returns null (with an alert) if none are selected.
function scgSelectedIds() {
  var chk_arr = document.getElementsByName("students[]");
  var ids = [];
  for (var k = 0; k < chk_arr.length; k++) {
    if (chk_arr[k].checked) {
      var vl = chk_arr[k].value.split(",");
      ids.push(vl[0]);
    }
  }
  if (ids.length === 0) {
    alert("Please select at least one student from the list below first.");
    return null;
  }
  return ids.join(",");
}

function scgClassSection() {
  return {
    class_id: document.getElementById("class_id").value,
    section: document.getElementById("txt_section").value
  };
}

// Professional Portrait ID Cards
function professionalPortraitCards() {
  var ids = scgSelectedIds();
  if (!ids) return;
  var cs = scgClassSection();
  var today = new Date().toISOString().slice(0, 10);
  window.location = "<?php echo BASE_URL; ?>print_students_cards.php?student_ids=" + ids
    + "&class_id=" + encodeURIComponent(cs.class_id) + "&section=" + encodeURIComponent(cs.section)
    + "&cell_no=YES&valid=" + today + "&DOB=YES";
}

// Classic Landscape ID Cards
function classicLandscapeCards() {
  var ids = scgSelectedIds();
  if (!ids) return;
  <?php if (file_exists(__DIR__ . '/print_landscape_students_cards.php')): ?>
  var cs = scgClassSection();
  window.location = "<?php echo BASE_URL; ?>print_landscape_students_cards.php?students=" + ids
    + "&class_id=" + encodeURIComponent(cs.class_id) + "&section=" + encodeURIComponent(cs.section);
  <?php else: ?>
  alert("Landscape cards are not installed on this server yet. Please use Portrait ID Card.");
  <?php endif; ?>
}

// Family Cards
function familyCards() {
  var ids = scgSelectedIds();
  if (!ids) return;
  <?php if (file_exists(__DIR__ . '/print_family_cards.php')): ?>
  var cs = scgClassSection();
  window.location = "<?php echo BASE_URL; ?>print_family_cards.php?students=" + ids
    + "&class_id=" + encodeURIComponent(cs.class_id) + "&section=" + encodeURIComponent(cs.section);
  <?php else: ?>
  alert("Family cards are not installed on this server yet. Please use Portrait ID Card.");
  <?php endif; ?>
}

// --- Search (plain JS filter, mirrors the DataTables search look) ---
function scgTableFilter() {
  var q = (document.getElementById('scgTableSearch').value || '').toLowerCase();
  var rows = document.querySelectorAll('#listofstudents tbody tr');
  rows.forEach(function (row) {
    row.style.display = (row.textContent || '').toLowerCase().indexOf(q) > -1 ? '' : 'none';
  });
}

// --- Selected counter sync ---
function updateSelectedCount() {
  var n = document.querySelectorAll('input[name="students[]"]:checked').length;
  var c1 = document.getElementById('scgSelectedCount');
  var c2 = document.getElementById('scgSelectedCount2');
  var pl = document.getElementById('scgSelectedCountPlural2');
  if (c1) c1.textContent = n;
  if (c2) c2.textContent = n;
  if (pl) pl.textContent = n === 1 ? '' : 's';
}

// --- Select All / Clear Selection & Check All ---
function toggleSelectAll() {
  var boxes = document.querySelectorAll('input[name="students[]"]');
  var allChecked = boxes.length > 0 && boxes.length === document.querySelectorAll('input[name="students[]"]:checked').length;
  var btn = document.getElementById('scgSelectAllBtn');
  var checkAll = document.getElementById('checkAll');
  boxes.forEach(function (cb) { cb.checked = !allChecked; });
  if (checkAll) checkAll.checked = !allChecked;
  if (btn) btn.innerHTML = !allChecked ? '<i class="fa fa-square-o"></i> Clear Selection' : '<i class="fa fa-check-square-o"></i> Select All';
  updateSelectedCount();
}
function toggleCheckAll(chk) {
  document.querySelectorAll('input[name="students[]"]').forEach(function (cb) { cb.checked = chk.checked; });
  updateSelectedCount();
}

// Back Side Cards modal controls
function openBacksideModal(){
  var modal = document.getElementById('backsideModal');
  if(modal){ modal.style.display = 'flex'; }
}
function closeBacksideModal(){
  var modal = document.getElementById('backsideModal');
  if(modal){ modal.style.display = 'none'; }
}
function handleBacksidePrint(){
  <?php if (!file_exists(__DIR__ . '/print_portrait_backside_stdcard.php') && !file_exists(__DIR__ . '/print_landscape_backside_stdcard.php')): ?>
  alert("Back side card printing is not installed on this server yet.");
  closeBacksideModal();
  return;
  <?php endif; ?>
  var colorEl = document.getElementById('backside_color');
  var validEl = document.getElementById('backside_valid');
  var selectedNumEl = document.querySelector('input[name="cards_per_page"]:checked');
  var selectedOrientationEl = document.querySelector('input[name="card_orientation"]:checked');
  var noteEl = document.getElementById('backside_note');

  var validDate = validEl ? validEl.value : '';
  var color = colorEl ? colorEl.value : '#9c27b0';
  var selectedNum = selectedNumEl ? selectedNumEl.value : '';
  var selectedOrientation = selectedOrientationEl ? selectedOrientationEl.value : 'landscape';
  var note = noteEl ? noteEl.value : 'In case of loss, kindly return this card to the school office.';

  if(!validDate){ alert('Please select a valid-until date.'); return; }
  if(!selectedNum){ alert('Please select number of cards (8 or 10).'); return; }

  var modifiedColor = color.substring(1);
  var printFile = (selectedOrientation === 'portrait')
      ? 'print_portrait_backside_stdcard.php'
      : 'print_landscape_backside_stdcard.php';

  var url = printFile + '?valid=' + encodeURIComponent(validDate)
      + '&color=' + encodeURIComponent(modifiedColor)
      + '&num=' + encodeURIComponent(selectedNum)
      + '&note=' + encodeURIComponent(note);

  window.location.href = url;
}
</script>

<div class="main-content">
    <div class="container-fluid">

        <div class="scg-wrap">

            <div class="scg-crumb">
                <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp;
                ID Cards Generator &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; Generate Student Cards
            </div>

            <div class="scg-title-row"><i class="fa fa-id-card"></i> Generate Student ID Cards</div>

            <!-- Compact breadcrumb-style step indicator -->
            <div class="scg-stepper">
                <div class="scg-step-pill"><span class="scg-step-circle">1</span><span class="lbl">Choose Class &amp; Section</span></div>
                <div class="scg-step-line"></div>
                <div class="scg-step-pill"><span class="scg-step-circle">2</span><span class="lbl">Select Students</span></div>
                <div class="scg-step-line"></div>
                <div class="scg-step-pill"><span class="scg-step-circle">3</span><span class="lbl">Print Cards</span></div>
            </div>

            <!-- ===== Filters ===== -->
            <form action="students_card.php" method="get" id="scgFilterForm">
                <div class="scg-panel">
                    <div class="scg-filter-row">
                        <div class="scg-filter-col">
                            <label>Session</label>
                            <select name="session" id="session">
                                    <option value="">All Sessions</option>
                                    <?php
                                    $currentSession = get_setting('session_year', '2026-2027');
                                    for ($sy = 2018; $sy <= 2030; $sy++) {
                                        $sLabel = $sy . '-' . ($sy + 1);
                                        $sSel = ($selSession !== '' && $selSession === $sLabel) || ($selSession === '' && $sLabel === $currentSession);
                                    ?>
                                        <option value="<?php echo e($sLabel); ?>" <?php echo $sSel ? 'selected' : ''; ?>><?php echo e($sLabel); ?></option>
                                    <?php } ?>
                                </select>
                        </div>

                        <div class="scg-filter-col">
                            <label>Class</label>
                            <select name="class_id" id="class_id" onChange="getsec(this.value)">
                                <option value="All">All Classes</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo (int)$c['class_id']; ?>" <?php echo $selClass === (int)$c['class_id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="scg-filter-col">
                            <label>Section</label>
                            <select name="section_id" id="txt_section">
                                <option value="All">All Sections</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo (int)$sec['section_id']; ?>" <?php echo $selSection === (int)$sec['section_id'] ? 'selected' : ''; ?>><?php echo e($sec['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <input type="hidden" name="addaccountAdmin" value="1">
                        <button type="submit" class="scg-search-btn"><i class="fa fa-search"></i> Search</button>
                    </div>
                </div>
            </form>

            <!-- ===== Card format — compact, sticky, sits ABOVE the student list so it never needs scrolling to reach ===== -->
            <div class="scg-panel scg-tiles-panel" id="scgTilesPanel">
                <div class="scg-tiles-hd">
                    <span>3&nbsp;&middot;&nbsp;Choose Card Format &amp; Print</span>
                    <span class="scg-tiles-hint"><b id="scgSelectedCount2">0</b> student<span id="scgSelectedCountPlural2">s</span> selected below</span>
                </div>
                <div class="scg-tiles">

                    <div class="scg-tile portrait" onclick="professionalPortraitCards();">
                        <div class="scg-tile-ic"><i class="fa fa-id-badge"></i></div>
                        <div><h3>Portrait ID Card</h3><p>Upright card, photo on top</p></div>
                    </div>

                    <div class="scg-tile landscape" onclick="classicLandscapeCards();">
                        <div class="scg-tile-ic"><i class="fa fa-id-card-o"></i></div>
                        <div><h3>Landscape ID Card</h3><p>Colour, DOB, valid-until options</p></div>
                    </div>

                    <div class="scg-tile family" onclick="familyCards();">
                        <div class="scg-tile-ic"><i class="fa fa-users"></i></div>
                        <div><h3>Family Card</h3><p>One card per family selected</p></div>
                    </div>

                    <div class="scg-tile back" onclick="openBacksideModal();">
                        <div class="scg-tile-ic"><i class="fa fa-clone"></i></div>
                        <div><h3>Card Back Side</h3><p>Plain back / return note</p></div>
                    </div>

                </div>
            </div>

            <!-- ===== Step 2: Pick students ===== -->
            <div class="scg-panel">

                <div class="scg-pick-bar">
                    <div class="scg-pick-count">2&nbsp;&middot;&nbsp;<b><?php echo count($students); ?></b> students found &middot; <span class="scg-selected-n" id="scgSelectedCount">0</span> selected</div>
                    <button type="button" class="scg-select-all-btn" id="scgSelectAllBtn" onclick="toggleSelectAll()"><i class="fa fa-check-square-o"></i> Select All</button>
                    <div class="scg-search-box"><i class="fa fa-search"></i><input type="text" id="scgTableSearch" placeholder="Search in this list&hellip;" oninput="scgTableFilter();"></div>
                </div>

                <div style="overflow-x:auto;">
                <table id="listofstudents" class="table table-striped table-bordered scg-tbl" style="width:100%;background-color:#FFFFFF;">
                    <thead>
                        <tr>
                            <th width="4%" style="text-align:center;"><input type="checkbox" id="checkAll" class="scg-chk" onchange="toggleCheckAll(this)"></th>
                            <th width="6%" style="text-align:center;"> S.No </th>
                            <th width="10%"> GR.NO </th>
                            <th width="24%"> Student Name </th>
                            <th width="22%"> Father Name </th>
                            <th width="24%"> Class/Section </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) === 0): ?>
                            <tr>
                                <td colspan="6" class="scg-empty"><i class="fa fa-user-circle-o"></i>No students found. Please choose a class &amp; section and hit Search.</td>
                            </tr>
                        <?php endif; ?>
                        <?php $si = 0; foreach ($students as $st): $si++;
                            $stName = trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? ''));
                            $grNow  = trim($st['gr_no'] ?? '');
                            $clsSec = trim((string)($st['class_name'] ?? '')) . (trim((string)($st['section_name'] ?? '')) !== '' ? ' - ' . e($st['section_name']) : '');
                        ?>
                            <tr>
                                <td style="text-align:center;">
                                    <input type="checkbox" class="scg-chk student-checkbox" name="students[]" value="<?php echo (int)$st['student_id']; ?>" onchange="updateSelectedCount()">
                                </td>
                                <td style="text-align:center;"> <?php echo $si; ?> </td>
                                <td><?php echo e($grNow); ?></td>
                                <td><?php echo e($stName); ?></td>
                                <td><?php echo e($st['father_name'] ?? ''); ?></td>
                                <td><?php echo $clsSec; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

            </div>

        </div>

    </div>
</div>

<!-- Back Side Cards Modal -->
<div id="backsideModal" class="modal-overlay" onclick="if(event.target===this) closeBacksideModal();">
  <div class="modal-card">
    <div class="modal-header"><i class="fa fa-clone"></i> Back Side Card Settings</div>
    <div class="modal-body">

      <div class="form-group" style="margin-bottom: 16px;">
        <label class="control-lbl">Card Orientation</label>
        <div class="radio-row">
          <label><input type="radio" name="card_orientation" value="landscape" checked> Landscape</label>
          <label><input type="radio" name="card_orientation" value="portrait"> Portrait</label>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 16px;">
        <label class="control-lbl">Cards Per Page</label>
        <div class="radio-row">
          <label><input type="radio" name="cards_per_page" value="8" checked> 8 Cards</label>
          <label><input type="radio" name="cards_per_page" value="10"> 10 Cards</label>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 16px;">
        <label class="control-lbl">Valid Up To</label>
        <input type="date" id="backside_valid" class="form-control" value="<?php echo date('Y-m-d'); ?>">
      </div>

      <div class="form-group" style="margin-bottom: 16px;">
        <label class="control-lbl">Card Colour</label>
        <input type="color" id="backside_color" class="form-control" value="#9c27b0" style="height:42px;padding:4px;">
      </div>

      <div class="form-group">
        <label class="control-lbl">Card Note</label>
        <input type="text" id="backside_note" class="form-control"
               value="In case of loss, kindly return this card to the school office.">
      </div>

    </div>
    <div class="modal-footer">
      <button type="button" class="modal-close" onclick="closeBacksideModal()">Cancel</button>
      <button type="button" class="modal-primary" onclick="handleBacksidePrint()"><i class="fa fa-print"></i> Print</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>