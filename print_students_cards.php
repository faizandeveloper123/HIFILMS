<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$idParam = trim((string)($_POST['students'] ?? ($_GET['students'] ?? ($_POST['student_ids'] ?? ($_GET['student_ids'] ?? '')))));
$idList = [];
if ($idParam !== '') {
    foreach (explode(',', $idParam) as $i) {
        $i = (int)trim($i);
        if ($i > 0) { $idList[$i] = true; }
    }
}

$valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['valid'] ?? '') ? $_GET['valid'] : date('Y-m-d');
$showDob  = strtoupper((string)($_GET['DOB'] ?? 'NO')) === 'YES';
$showCell = strtoupper((string)($_GET['cell_no'] ?? 'NO')) === 'YES';

$si = school_info((int)($_GET['campus_id'] ?? ($_POST['campus_id'] ?? 0)));
$schoolName = $si['name'];
$schoolLogo = $si['logo'];
$logoSrc = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';
$sigImg = get_setting('signature_image', '');
$sigSrc = $sigImg !== '' ? BASE_URL . 'assets/uploads/' . basename($sigImg) : '';

$students = [];
if (count($idList) > 0) {
    $ph = implode(',', array_fill(0, count($idList), '?'));
    $st2 = db_prepare("SELECT s.*, c.class_name, sec.section_name, qt.token AS qr_token
                       FROM students s
                       LEFT JOIN classes c ON s.class_id=c.class_id
                       LEFT JOIN sections sec ON s.section_id=sec.section_id
                       LEFT JOIN qr_tokens qt ON s.student_id = qt.user_id AND qt.user_type='student' AND qt.is_active = 1
                       WHERE s.status=1 AND s.student_id IN ($ph) ORDER BY s.first_name");
    $st2->bind_param(str_repeat('i', count($idList)), ...array_keys($idList));
    $st2->execute();
    $res = $st2->get_result();
    while ($row = $res->fetch_assoc()) { $students[] = $row; }
}

if (count($students) === 0) { die('No students selected.'); }

$insToken = db_prepare("INSERT INTO qr_tokens (user_id, user_type, token, created_at, expires_at, is_active) VALUES (?, 'student', ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 YEAR), 1)");
foreach ($students as $i => $st) {
    if (empty($st['qr_token'])) {
        $token = 'QR-' . strtoupper(bin2hex(random_bytes(8)));
        $sid = (int)$st['student_id'];
        $insToken->bind_param('is', $sid, $token);
        $insToken->execute();
        $students[$i]['qr_token'] = $token;
    }
}

$photoDir = BASE_URL . 'uploads/students/';
$blankCircle = BASE_URL . 'assets/img/logo.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Students Cards</title>
    <style>
        :root {
            --pc-text: #000;
            --pc-font: 8px;
            --pc-photo-bg: #d70000;
            --pc-primary: #d70000;
            --pc-text-body: #374151;
        }
        .section { width: 95%; height: auto; float: left; padding-right: 2.5%; margin-left: 2%; }

        .box1 {
            width: 2.2in;
            float: left;
            height: 3.6in;
            padding: 0.5%;
            margin-top: 13px;
            margin-left: 30px;
            background: #fff;
            background-size: cover;
            border: 2px solid black;
            position: relative;
        }
        .box1::after {
            content: "";
            position: absolute;
            inset: 5px;
            border: 1px solid rgba(0, 0, 0, 0.35);
            pointer-events: none;
        }
        .box1::before {
            content: "";
            position: absolute;
            inset: 2px;
            border: 1px dotted rgba(0, 0, 0, 0.18);
            pointer-events: none;
        }

        .circular-image1 {
            width: 105px;
            height: 103px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
        }
        .pc-photo-disc {
            width: 116px;
            height: 116px;
            border-radius: 50%;
            background: var(--pc-photo-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
            margin-left: 45px;
        }

        tr th { background-color: gray; text-align: center; font-size: 14px; border: 1px solid gray; }
        tr td { border-bottom: 1px solid gray; border-left: 1px solid gray; padding: .7%; }
        .tdalign { text-align: center; border: 1px solid black; }

        body { font-weight: bold; }

        page {
            background: white;
            display: block;
            margin: 0 auto;
            margin-bottom: 0.5cm;
            border: lightgray;
            height: 21cm;
        }
        page[size="A4"] { width: 21cm; height: 29.7cm; }
        page[size="A4"][layout="landscape"] { width: 29.7cm; height: 21cm; }
        page[size="A3"] { width: 29.7cm; height: 42cm; }
        page[size="A3"][layout="landscape"] { width: 42cm; height: 29.7cm; }
        page[size="A5"] { width: 14.8cm; height: 21cm; }
        page[size="A5"][layout="landscape"] { width: 21cm; height: 14.8cm; }

        @media print {
            body, page { margin: 0; box-shadow: none; border: none; }
            #printToolbar { display: none !important; }
        }

        #printToolbar {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 9999;
            display: flex;
            gap: 8px;
        }
        #printToolbar button {
            background: #ff7800;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }
        #btnCustomize { background: var(--pc-primary); }

        .cc-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            display: none;
            z-index: 10000;
        }
        .cc-modal {
            width: 95%;
            max-width: 720px;
            background: #fff;
            border-radius: 12px;
            margin: 6vh auto;
            overflow: hidden;
            box-shadow: 0 14px 40px rgba(2, 6, 23, 0.3);
        }
        .cc-header {
            background: var(--pc-primary);
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .cc-title {
            font-weight: 1000;
            font-size: 14px;
            letter-spacing: 0.2px;
        }
        .cc-close {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 18px;
            line-height: 34px;
        }
        .cc-body { padding: 14px 16px; }
        .cc-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px 16px;
            align-items: start;
        }
        .cc-field { min-width: 0; }
        .cc-field-span-full { grid-column: 1 / -1; }
        .cc-label {
            display: block;
            font-weight: 900;
            color: var(--pc-text-body);
            font-size: 12px;
            margin-bottom: 6px;
        }
        .cc-control {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 13px;
            box-sizing: border-box;
        }
        .cc-control[type="color"] { height: 42px; padding: 4px; cursor: pointer; }
        .cc-footer {
            padding: 12px 16px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .cc-cancel {
            background: #e5e7eb;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 800;
        }
        .cc-apply {
            background: var(--pc-primary);
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 1000;
        }
        .pc-photo-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }
        .pc-photo-preview img {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #d1d5db;
        }
        .pc-photo-preview button {
            background: #e5e7eb;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 800;
        }
        @media (max-width: 600px) {
            .cc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body style="font-family: 'Arial', sans-serif; text-transform: uppercase;">
    <div id="printToolbar">
        <button id="btnCustomize" onclick="openCustomize()">Customize Card</button>
        <button onclick="window.print()">Print Cards</button>
    </div>

    <div class="cc-overlay" id="ccOverlay" onclick="if(event.target===this) closeCustomize()">
        <div class="cc-modal">
            <div class="cc-header">
                <div class="cc-title">Customize ID Card</div>
                <button class="cc-close" onclick="closeCustomize()">&times;</button>
            </div>
            <div class="cc-body">
                <div class="cc-grid">
                    <div class="cc-field">
                        <label class="cc-label" for="optTextColor">Text Color</label>
                        <input class="cc-control" type="color" id="optTextColor" value="#000000">
                    </div>
                    <div class="cc-field">
                        <label class="cc-label" for="optFontSize">Font Size (px)</label>
                        <input class="cc-control" type="number" id="optFontSize" min="6" max="16" step="1" value="8">
                    </div>
                    <div class="cc-field">
                        <label class="cc-label" for="optPhotoBg">Photo Background</label>
                        <input class="cc-control" type="color" id="optPhotoBg" value="#d70000">
                    </div>
                    <div class="cc-field cc-field-span-full">
                        <label class="cc-label" for="optPhoto">Upload Photo (applies to all cards on this page)</label>
                        <input class="cc-control" type="file" id="optPhoto" accept="image/*">
                        <div class="pc-photo-preview" id="photoPreview" style="display:none;">
                            <img id="photoPreviewImg" src="" alt="">
                            <button type="button" onclick="resetPhoto()">Remove Photo</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cc-footer">
                <button class="cc-cancel" onclick="closeCustomize()">Cancel</button>
                <button class="cc-apply" onclick="applyCustomize()">Apply</button>
            </div>
        </div>
    </div>
    <page size="A4">
        <?php foreach ($students as $st):
            $fullName = trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? ''));
            $father = trim($st['father_name'] ?? '');
            $classVal = !empty($st['class_name']) ? $st['class_name'] . ( !empty($st['section_name']) ? ' / ' . $st['section_name'] : '' ) : '-- / --';
            $grNo = trim($st['gr_no'] ?? '');
            $dob = (!empty($st['dob']) && $st['dob'] !== '0000-00-00') ? date('d-M-Y', strtotime($st['dob'])) : '--';
            $cell = trim($st['phone'] ?? '') !== '' ? trim($st['phone']) : '00';
            $photo = '';
            if (!empty($st['photo']) && is_file(__DIR__ . '/uploads/students/' . $st['photo'])) {
                $photo = $photoDir . e($st['photo']);
            }
            $qrSrc = BASE_URL . 'qr_image.php?d=' . urlencode($st['qr_token']);
        ?>
        <div class="box1">
            <div style="padding: 2%; height: 3.6in; margin-left: 2%; padding-left: 0%;">
                <div class="pc-photo-disc">
                    <?php if ($photo !== ''): ?>
                        <img src="<?php echo $photo; ?>" class="circular-image1" onerror="this.onerror=null; this.src='<?php echo $blankCircle; ?>';">
                    <?php else: ?>
                        <img src="<?php echo $blankCircle; ?>" class="circular-image1" onerror="this.onerror=null; this.style.opacity=0.15;">
                    <?php endif; ?>
                </div>

                <div style="display: flex; align-items: flex-start; justify-content: center; margin-top: 16px; ">
                    <div style="display: flex; align-items: center; margin-right: 5px;">
                        <img src="<?php echo $logoSrc; ?>" style="height: 25px; width: 25px; margin-top: -2px; object-fit: contain;" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                    </div>
                    <div style="font-size: calc(var(--pc-font) + 3px); font-weight: bold; word-wrap: break-word; color: var(--pc-text);">
                        <?php echo e($schoolName); ?>
                    </div>
                </div>

                <div style="width: 100%; margin-top: 18px; margin-left: 3px;">
                    <div style="display: flex; width: 100%; line-height: 1.5;">
                        <div style="width: 45%; font-size: var(--pc-font); white-space: nowrap; color: var(--pc-text);">STUDENT NAME:</div>
                        <div style="width: 55%; font-size: var(--pc-font); word-wrap: break-word; overflow-wrap: break-word; color: var(--pc-text);"><?php echo e($fullName); ?></div>
                    </div>

                    <div style="display: flex; width: 100%; line-height: 1.5;">
                        <div style="width: 45%; font-size: var(--pc-font); white-space: nowrap; color: var(--pc-text);">FATHER'S NAME:</div>
                        <div style="width: 55%; font-size: var(--pc-font); word-wrap: break-word; overflow-wrap: break-word; color: var(--pc-text);"><?php echo e($father); ?></div>
                    </div>

                    <?php if ($showDob): ?>
                    <div style="display: flex; width: 100%; line-height: 1.5;">
                        <div style="width: 45%; font-size: var(--pc-font); white-space: nowrap; color: var(--pc-text);">DATE OF BIRTH (DOB):</div>
                        <div style="width: 55%; font-size: var(--pc-font); color: var(--pc-text);"><?php echo e($dob); ?></div>
                    </div>
                    <?php endif; ?>

                    <div style="display: flex; width: 100%; line-height: 1.5;">
                        <div style="width: 45%; font-size: var(--pc-font); white-space: nowrap; color: var(--pc-text);">CLASS:</div>
                        <div style="width: 55%; font-size: var(--pc-font); color: var(--pc-text);"><?php echo e($classVal); ?></div>
                    </div>

                    <div style="display: flex; width: 100%; line-height: 1.5;">
                        <div style="width: 45%; font-size: var(--pc-font); white-space: nowrap; color: var(--pc-text);">GR NO:</div>
                        <div style="width: 55%; font-size: var(--pc-font); color: var(--pc-text);"><?php echo e($grNo); ?></div>
                    </div>

                    <?php if ($showCell): ?>
                    <div style="display: flex; width: 100%; line-height: 1.5;">
                        <div style="width: 45%; font-size: var(--pc-font); white-space: nowrap; color: var(--pc-text);">CONTACT NUMBER</div>
                        <div style="width: 55%; font-size: var(--pc-font); color: var(--pc-text);"><?php echo e($cell); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <div style="text-align: center; width: 30%; float: left; font-size: var(--pc-font); margin-top: 35px; margin-bottom: 10px; color: var(--pc-text);">
                        <span style="margin-bottom: 5px;"><?php echo date('d-M-Y', strtotime($valid)); ?></span><br><br>
                        Validity
                    </div>

                    <div style="width: 40%; float: left;">
                        <img src="<?php echo $qrSrc; ?>" style="width: 62px; height: 62px; margin-top: 7px; margin-left: 20px;" alt="QR Code" onerror="this.onerror=null; this.style.display='none';">
                    </div>

                    <div style="width: 30%; float: left;">
                        <?php if ($sigSrc !== ''): ?>
                        <img src="<?php echo $sigSrc; ?>" style="width: 45px; height: 20px; margin-right: 0px; object-fit: contain;" onerror="this.onerror=null; this.style.display='none';">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </page>

    <script>
        var ccPhotoData = null;
        function setVar(name, v) {
            document.documentElement.style.setProperty(name, v);
        }
        function openCustomize() {
            document.getElementById('ccOverlay').style.display = 'block';
        }
        function closeCustomize() {
            document.getElementById('ccOverlay').style.display = 'none';
        }
        function applyCustomize() {
            var tc = document.getElementById('optTextColor').value;
            var fs = parseInt(document.getElementById('optFontSize').value, 10);
            if (isNaN(fs)) { fs = 8; }
            if (fs < 6) { fs = 6; }
            if (fs > 16) { fs = 16; }
            var lb = document.getElementById('optPhotoBg').value;
            setVar('--pc-text', tc);
            setVar('--pc-font', fs + 'px');
            setVar('--pc-photo-bg', lb);
            var pb = document.getElementById('photoPreview');
            pb.style.display = ccPhotoData ? 'flex' : 'none';
            closeCustomize();
        }
        document.addEventListener('input', function (e) {
            if (e.target && e.target.id === 'optTextColor') { setVar('--pc-text', e.target.value); }
            if (e.target && e.target.id === 'optPhotoBg') { setVar('--pc-photo-bg', e.target.value); }
            if (e.target && e.target.id === 'optFontSize') {
                var v = parseInt(e.target.value, 10);
                if (!isNaN(v) && v >= 6 && v <= 16) { setVar('--pc-font', v + 'px'); }
            }
        });
        document.getElementById('optPhoto').addEventListener('change', function () {
            var f = this.files && this.files[0];
            if (!f) { return; }
            var rd = new FileReader();
            rd.onload = function () {
                ccPhotoData = rd.result;
                var imgs = document.querySelectorAll('.circular-image1');
                for (var i = 0; i < imgs.length; i++) { imgs[i].src = ccPhotoData; imgs[i].style.opacity = 1; }
                var pb = document.getElementById('photoPreview');
                document.getElementById('photoPreviewImg').src = ccPhotoData;
                pb.style.display = 'flex';
            };
            rd.readAsDataURL(f);
        });
        function resetPhoto() {
            ccPhotoData = null;
            var ph = document.getElementById('optPhoto');
            ph.value = '';
            document.getElementById('photoPreview').style.display = 'none';
            var imgs = document.querySelectorAll('.circular-image1');
            for (var i = 0; i < imgs.length; i++) {
                var o = imgs[i].getAttribute('data-orig');
                if (o) { imgs[i].src = o; imgs[i].style.opacity = imgs[i].getAttribute('data-opacity') || 1; }
            }
        }
        (function () {
            var imgs = document.querySelectorAll('.circular-image1');
            for (var i = 0; i < imgs.length; i++) {
                imgs[i].setAttribute('data-opacity', imgs[i].style.opacity || '1');
                imgs[i].setAttribute('data-orig', imgs[i].src);
            }
        })();
    </script>
</body>
</html>