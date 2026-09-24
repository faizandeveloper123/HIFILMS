<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$ids = trim((string)($_POST['student_ids'] ?? ($_GET['student_ids'] ?? ($_GET['students'] ?? ''))));
$idList = [];
if ($ids !== '') {
    foreach (explode(',', $ids) as $i) {
        $i = (int)trim($i);
        if ($i > 0) { $idList[$i] = true; }
    }
}

$valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['valid'] ?? '') ? $_GET['valid'] : date('Y-m-d');

$color = preg_match('/^[0-9a-fA-F]{6}$/', $_GET['color'] ?? '') ? strtolower($_GET['color']) : '1a3c8f';
$titleFs = (int)($_GET['title_font_size'] ?? 15);
if ($titleFs < 10 || $titleFs > 32) { $titleFs = 15; }
$slogan = trim((string)($_GET['slogan'] ?? '')) !== '' ? trim((string)$_GET['slogan']) : get_setting('school_slogan', 'A Path to Excellence');

require_once __DIR__ . '/includes/card_design.php';
$design = card_design('student');
$si = school_info((int)($_GET['campus_id'] ?? 0));
$schoolName = $si['name'] !== '' ? $si['name'] : $design['school_name'];
$schoolAddr = $si['addr'] !== '' ? $si['addr'] : $design['school_addr'];
$schoolPhone = $si['phone'];
$schoolLogo = $si['logo'] !== '' ? $si['logo'] : $design['logo'];
$logoSrc = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';

$students = [];
if (count($idList) > 0) {
    $ph = implode(',', array_fill(0, count($idList), '?'));
    $st2 = db_prepare("SELECT s.student_id, s.first_name, s.last_name, s.father_name, s.mother_name,
                              s.gr_no, s.dob, s.phone, s.family_code, s.sibling_code, s.photo, s.roll_no,
                              c.class_name, sec.section_name
                       FROM students s
                       LEFT JOIN classes c ON s.class_id=c.class_id
                       LEFT JOIN sections sec ON s.section_id=sec.section_id
                       WHERE s.status=1 AND s.student_id IN ($ph)
                       ORDER BY s.family_code, s.father_name, s.first_name");
    $st2->bind_param(str_repeat('i', count($idList)), ...array_keys($idList));
    $st2->execute();
    $res = $st2->get_result();
    while ($row = $res->fetch_assoc()) { $students[] = $row; }
}

if (count($students) === 0) { die('No students selected.'); }

// Group students into families.
$families = [];
foreach ($students as $st) {
    $famCode = trim((string)($st['family_code'] ?? ''));
    if ($famCode === '') { $famCode = trim((string)($st['sibling_code'] ?? '')); }
    if ($famCode === '') { $famCode = 'FAM-' . $st['student_id']; }
    $families[$famCode][] = $st;
}

// Order families by their head (father) name for stable output.
$familyData = [];
foreach ($families as $code => $members) {
    $head = $members[0];
    $familyData[] = [
        'code' => $code,
        'head_name' => trim((string)($head['father_name'] ?? '')) !== '' ? trim((string)$head['father_name']) : 'Family',
        'members' => $members,
    ];
}
usort($familyData, function ($a, $b) {
    return strcmp(mb_strtolower($a['head_name'], 'UTF-8'), mb_strtolower($b['head_name'], 'UTF-8'));
});

// ~8 family cards per A4 page, 2 columns.
$perPage = 8;
$pages = array_chunk($familyData, $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Family ID Cards</title>
    <style>
        :root {
            --cp: #<?php echo $color; ?>;
            --cl: #6a80b6;
            --cw: #ffffff;
            --ct: #1e3a5f;
            --title-fs: <?php echo $titleFs; ?>px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-weight: 600;
            background: #e8eaf0;
        }

        page {
            background: white;
            display: block;
            margin: 0 auto 0.5cm;
            width: 21cm;
            min-height: 29.7cm;
            padding: 0.5cm;
        }

        .cards-row {
            display: flex;
            flex-wrap: wrap;
            row-gap: 28px;
            column-gap: 12px;
        }

        /* Each family card takes half the page width */
        .fc-box {
            width: calc(50% - 6px);
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .fc-card {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.15);
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: #fff;
        }

        /* ── Header ── */
        .fc-header {
            background: var(--cp);
            color: var(--cw);
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .fc-logo {
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .fc-logo img { max-width: 38px; max-height: 38px; }

        .fc-header-text { flex: 1; text-align: center; }

        .fc-school-name {
            font-size: var(--title-fs);
            font-weight: 800;
            letter-spacing: 0.4px;
            line-height: 1.15;
            text-transform: uppercase;
            text-shadow: 0 1px 0 rgba(0,0,0,0.12);
        }

        .fc-slogan {
            font-size: 6.5px;
            font-style: italic;
            opacity: 0.9;
            margin-top: 2px;
            letter-spacing: 0.2px;
        }

        .fc-card-type {
            font-size: 9px;
            font-weight: 700;
            margin-top: 3px;
            letter-spacing: 1.2px;
            opacity: 0.88;
            text-transform: uppercase;
        }

        /* ── Family Info Band ── */
        .fc-family-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            background: linear-gradient(135deg, rgba(26,60,143,0.08) 0%, #ffffff 70%);
            border-bottom: 2px solid var(--cp);
        }

        .fc-family-img {
            flex: 0 0 48px;
            width: 48px;
            height: 54px;
            border-radius: 8px;
            border: 2px solid var(--cp);
            background: var(--cp);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .fc-family-img svg { width: 34px; height: 36px; }

        .fc-family-img-label {
            font-size: 5.5px;
            color: rgba(255,255,255,0.92);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-top: 1px;
        }

        .fc-family-details { flex: 1; min-width: 0; }

        .fc-family-name {
            font-size: 11px;
            font-weight: 900;
            color: var(--ct);
            text-transform: uppercase;
            line-height: 1.15;
            letter-spacing: 0.3px;
        }

        .fc-family-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 2px 10px;
            margin-top: 4px;
        }

        .fc-meta-item { line-height: 1.3; }
        .fc-meta-label {
            font-size: 6px;
            font-weight: 800;
            color: var(--cp);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .fc-meta-val {
            font-size: 8px;
            font-weight: 700;
            color: var(--ct);
        }

        /* ── Members Table ── */
        .fc-table-wrap { overflow: hidden; }

        .fc-table {
            width: 100%;
            border-collapse: collapse;
        }

        .fc-table thead tr {
            background: var(--cp);
            color: var(--cw);
        }

        .fc-table thead th {
            padding: 5px 8px;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            text-align: left;
            border-right: 1px solid rgba(255,255,255,0.18);
        }

        .fc-table thead th:first-child {
            text-align: center;
            width: 26px;
        }

        .fc-table thead th:last-child { border-right: none; }

        .fc-table tbody tr { border-bottom: 1px solid #eaecf4; }
        .fc-table tbody tr:last-child { border-bottom: none; }
        .fc-table tbody tr:nth-child(even) { background: #f5f6ff; }

        .fc-table td {
            padding: 5px 8px;
            font-size: 8px;
            font-weight: 600;
            color: var(--ct);
            vertical-align: middle;
            border-right: 1px solid #eaecf4;
        }

        .fc-table td:last-child { border-right: none; }
        .fc-table td:first-child { text-align: center; color: #9ca3af; font-size: 7px; }
        .fc-table td.td-name { font-weight: 800; }

        /* ── Footer ── */
        .fc-footer {
            background: var(--cp);
            color: var(--cw);
            padding: 5px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            font-size: 7.5px;
            font-weight: 700;
        }

        .fc-footer-addr {
            display: flex;
            align-items: center;
            gap: 3px;
            flex: 1;
            min-width: 0;
        }

        .fc-footer-addr-text {
            flex: 1;
            line-height: 1.2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .fc-footer-phone {
            display: flex;
            align-items: center;
            gap: 3px;
            white-space: nowrap;
        }

        /* ── Customization UI ── */
        .cc-ui { position: fixed; top: 12px; right: 12px; z-index: 9999; }
        .cc-btn {
            background: var(--cp);
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .cc-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            display: none;
            z-index: 10000;
        }
        .cc-modal {
            width: 92%;
            max-width: 480px;
            background: #fff;
            border-radius: 12px;
            margin: 7vh auto;
            overflow: hidden;
            box-shadow: 0 14px 40px rgba(2,6,23,0.3);
        }
        .cc-header {
            background: var(--cp);
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .cc-title { font-weight: 900; font-size: 14px; }
        .cc-close {
            background: rgba(255,255,255,0.18);
            color: #fff;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            line-height: 32px;
        }
        .cc-body { padding: 14px 16px; }
        .cc-grid { display: flex; flex-wrap: wrap; gap: 12px; }
        .cc-field { flex: 1; min-width: 150px; }
        .cc-label { display: block; font-weight: 800; font-size: 12px; margin-bottom: 5px; }
        .cc-control {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 13px;
            box-sizing: border-box;
        }
        .cc-footer-bar {
            padding: 12px 16px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .cc-cancel {
            background: #e5e7eb;
            border: none;
            padding: 9px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 800;
        }
        .cc-apply {
            background: var(--cp);
            color: #fff;
            border: none;
            padding: 9px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 900;
        }

        #printToolbar { position: fixed; top: 12px; left: 12px; z-index: 9999; }
        #printToolbar button { background:#ff7800; color:#fff; border:none; border-radius:10px; padding:10px 18px; font-size:14px; font-weight:800; cursor:pointer; }

        @media print {
            body { background: white; }
            .cc-ui, #printToolbar { display: none !important; }
            page { margin: 0; box-shadow: none; padding: 0.3cm; }
        }
    </style>
</head>
<body>

<!-- Print Toolbar -->
<div id="printToolbar">
    <button onclick="window.print()"><i class="fa fa-print"></i> Print Family Cards</button>
</div>

<!-- Customization Button -->
<div class="cc-ui">
    <button type="button" class="cc-btn" onclick="document.getElementById('ccOverlay').style.display='block'">
        &#9881; Customize
    </button>
</div>

<!-- Customization Modal -->
<div id="ccOverlay" class="cc-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="cc-modal">
        <div class="cc-header">
            <div class="cc-title">Card Customization</div>
            <button type="button" class="cc-close" onclick="document.getElementById('ccOverlay').style.display='none'">&times;</button>
        </div>
        <form onsubmit="return applyCardCustomization(event);">
            <div class="cc-body">
                <div class="cc-grid">
                    <div class="cc-field">
                        <label class="cc-label">Valid Up To</label>
                        <input id="cc_valid" class="cc-control" type="date" value="<?php echo e($valid); ?>">
                    </div>
                    <div class="cc-field">
                        <label class="cc-label">Color Scheme</label>
                        <input id="cc_color" class="cc-control" type="color" value="#<?php echo e($color); ?>">
                    </div>
                    <div class="cc-field">
                        <label class="cc-label">Title Font Size</label>
                        <select id="cc_title_font_size" class="cc-control">
                            <?php for ($t = 10; $t <= 25; $t++): ?>
                                <option value="<?php echo $t; ?>" <?php echo $t === $titleFs ? 'selected' : ''; ?>><?php echo $t; ?>px</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="cc-field" style="min-width:100%">
                        <label class="cc-label">Slogan</label>
                        <input id="cc_slogan" class="cc-control" type="text" value="<?php echo e($slogan); ?>" placeholder="A Path to Excellence">
                    </div>
                </div>
            </div>
            <div class="cc-footer-bar">
                <button type="button" class="cc-cancel" onclick="document.getElementById('ccOverlay').style.display='none'">Cancel</button>
                <button type="submit" class="cc-apply">Apply</button>
            </div>
        </form>
    </div>
</div>

<script>
function applyCardCustomization(e) {
    if (e && e.preventDefault) e.preventDefault();
    document.getElementById('ccOverlay').style.display = 'none';
    var params = new URLSearchParams(window.location.search);
    params.set('valid',           document.getElementById('cc_valid').value);
    params.set('color',           document.getElementById('cc_color').value.replace('#', ''));
    params.set('title_font_size', document.getElementById('cc_title_font_size').value);
    params.set('slogan',          document.getElementById('cc_slogan').value || 'A Path to Excellence');
    window.location.search = params.toString();
    return false;
}
</script>

<?php foreach ($pages as $pageList): ?>
<page size="A4">
    <div class="cards-row">
        <?php foreach ($pageList as $fam):
            $members = $fam['members'];
            $head = $members[0];
            $famName = mb_strtoupper($fam['head_name'], 'UTF-8') . ' Family';
        ?>
        <div class="fc-box">
            <div class="fc-card">

                <!-- ── Card Header ── -->
                <div class="fc-header">
                    <div class="fc-logo">
                        <img src="<?php echo $logoSrc; ?>" alt="" onerror="this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                    </div>
                    <div class="fc-header-text">
                        <div class="fc-school-name"><?php echo e($schoolName); ?></div>
                        <div class="fc-slogan">&mdash; <?php echo e($slogan); ?> &mdash;</div>
                        <div class="fc-card-type">&#128106; Family ID Card</div>
                    </div>
                    <div class="fc-logo" style="opacity:0.85;">
                        <span style="font-size:11px;color:#fbbf24;line-height:1;">&#9733;&#9733;&#9733;</span>
                    </div>
                </div>

                <!-- ── Family Info ── -->
                <div class="fc-family-info">

                    <!-- Family Image (SVG silhouette) -->
                    <div class="fc-family-img">
                        <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="20" cy="16" r="9" fill="rgba(255,255,255,0.75)"></circle>
                            <path d="M7 44 Q7 29 20 29 Q33 29 33 44 L33 52 L7 52Z" fill="rgba(255,255,255,0.65)"></path>
                            <circle cx="60" cy="16" r="9" fill="rgba(255,255,255,0.55)"></circle>
                            <path d="M47 44 Q47 29 60 29 Q73 29 73 44 L73 52 L47 52Z" fill="rgba(255,255,255,0.55)"></path>
                            <circle cx="32" cy="62" r="7" fill="rgba(255,255,255,0.85)"></circle>
                            <path d="M22 78 Q22 68 32 68 Q42 68 42 78 L42 80 L22 80Z" fill="rgba(255,255,255,0.85)"></path>
                            <circle cx="52" cy="62" r="7" fill="rgba(255,255,255,0.70)"></circle>
                            <path d="M42 78 Q42 68 52 68 Q62 68 62 78 L62 80 L42 80Z" fill="rgba(255,255,255,0.70)"></path>
                        </svg>
                        <div class="fc-family-img-label">Family</div>
                    </div>

                    <!-- Family Name & Meta -->
                    <div class="fc-family-details">
                        <div class="fc-family-name"><?php echo e($famName); ?></div>
                        <div class="fc-family-meta">
                            <div class="fc-meta-item">
                                <div class="fc-meta-label">Family Code</div>
                                <div class="fc-meta-val"><?php echo e($fam['code']); ?></div>
                            </div>
                            <div class="fc-meta-item">
                                <div class="fc-meta-label">Members</div>
                                <div class="fc-meta-val"><?php echo count($members); ?></div>
                            </div>
                            <div class="fc-meta-item">
                                <div class="fc-meta-label">Valid Up To</div>
                                <div class="fc-meta-val"><?php echo date('d-M-Y', strtotime($valid)); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Family Members Table ── -->
                <div class="fc-table-wrap">
                    <table class="fc-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>GR No</th>
                                <th>Student Name</th>
                                <th>Class / Section</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $mi = 0; foreach ($members as $m): $mi++;
                                $mName = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
                                $mClass = trim((string)($m['class_name'] ?? '')) !== '' ? trim((string)$m['class_name']) : '&mdash;';
                                $mSection = trim((string)($m['section_name'] ?? '')) !== '' ? trim((string)$m['section_name']) : '&mdash;';
                            ?>
                            <tr>
                                <td><?php echo $mi; ?></td>
                                <td><?php echo trim((string)($m['gr_no'] ?? '')) !== '' ? e($m['gr_no']) : '&mdash;'; ?></td>
                                <td class="td-name"><?php echo e($mName); ?></td>
                                <td><?php echo $mClass; ?> / <?php echo $mSection; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ── Card Footer ── -->
                <div class="fc-footer">
                    <div class="fc-footer-addr">
                        <span>&#128205;</span>
                        <span class="fc-footer-addr-text"><?php echo e($schoolAddr); ?></span>
                    </div>
                    <?php if (trim($schoolPhone) !== ''): ?>
                    <div class="fc-footer-phone">
                        <span>&#9742;</span>
                        <span><?php echo e($schoolPhone); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

            </div><!-- /fc-card -->
        </div><!-- /fc-box -->
        <?php endforeach; ?>
    </div><!-- /cards-row -->
</page>
<?php endforeach; ?>

</body>
</html>