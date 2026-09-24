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

$yn = function ($k, $def) {
    $v = $_GET[$k] ?? null;
    if ($v === null) { return $def; }
    return strtoupper((string)$v) === 'NO' ? 'NO' : 'YES';
};
$showDob     = $yn('DOB', 'YES');
$showCell    = $yn('cell_number', 'YES');
$showAddress = $yn('student_address', 'NO');
$showFamily  = $yn('family_code', 'NO');
$showSlogan  = $yn('slogan_display', 'YES');

$valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['valid'] ?? '') ? $_GET['valid'] : date('Y-m-d');
$color = preg_match('/^[0-9a-fA-F]{6}$/', $_GET['color'] ?? '') ? strtolower($_GET['color']) : '0067d7';
$titleFs = (int)($_GET['title_font_size'] ?? 15);
if ($titleFs < 10 || $titleFs > 25) { $titleFs = 15; }
$slogan = trim((string)($_GET['slogan'] ?? '')) !== '' ? trim((string)$_GET['slogan']) : get_setting('school_slogan', 'A Path to Excellence');

$per = (int)($_GET['cards_per_page'] ?? 8);
if (!in_array($per, [6, 8, 10], true)) { $per = 8; }

$num = function ($k, $def, $lo, $hi) {
    $v = (float)($_GET[$k] ?? $def);
    if ($v < $lo) { $v = $lo; }
    if ($v > $hi) { $v = $hi; }
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
};
$cardW   = $num('card_w', 3.5, 2, 5);
$cardH   = $num('card_h', 2.25, 1.5, 4);
$gapCol  = $num('gap_col', 0, 0, 0.75);
$gapRow  = $num('gap_row', 0, 0, 0.75);

$si = school_info((int)($_GET['campus_id'] ?? 0));
$schoolName  = $si['name'];
$schoolAddr  = $si['addr'];
$schoolPhone = $si['phone'];
$schoolLogo  = $si['logo'];
$logoSrc     = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';
$sigImg      = get_setting('signature_image', '');
$sigSrc      = $sigImg !== '' ? BASE_URL . 'assets/uploads/' . basename($sigImg) : '';

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

$pages = array_chunk($students, $per);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student ID Cards</title>
    <style>
        :root {
            --card-primary: #<?php echo $color; ?>;
            --card-primary-light: #599ce5;
            --card-text-on-primary: #ffffff;
            --card-body-bg: #ffffff;
            --card-text-body: #1e3a5f;
            --school-title-font-size: <?php echo $titleFs; ?>px;
            --card-photo-border: #d1d5db;
            /* Full cut size and inner safe zone (card_w/card_h from customization) */
            --sc-full-w: <?php echo $cardW; ?>in;
            --sc-full-h: <?php echo $cardH; ?>in;
            --sc-scale: 1;
            --card-gap-col: <?php echo $gapCol; ?>in;
            --card-gap-row: <?php echo $gapRow; ?>in;
            --sc-safe-w: 3.1in;
            --sc-safe-h: 1.85in;
            --sc-safe-inset-x: 0.2in;
            --sc-safe-inset-y: 0.2in;
            /* Extra blue above header row (content stays bottom-aligned to white body) */
            --sc-header-min-height: 0.36in;
            /* Fixed printable page box */
            --fixed-page-w: 8in;
            --fixed-page-h: 11.5in;
        }

        .section {
            width: 95%;
            height: auto;
            float: left;
            border-right: 2px dotted black;
            padding-right: 2.5%;
            margin-left: 2%;
        }

        .box {
            position: relative;
            width: var(--sc-full-w);
            height: var(--sc-full-h);
            box-sizing: border-box;
            padding: 0;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-weight: 600;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, var(--sc-full-w));
            justify-content: center;
            column-gap: var(--card-gap-col);
            row-gap: var(--card-gap-row);
            grid-auto-rows: var(--sc-full-h);
            /* Start at top when last page has fewer cards (avoid vertical centering gap) */
            align-content: start;
        }

        .student-card {
            width: 100%;
            height: 100%;
            position: relative;
            flex-shrink: 0;
            border-radius: 0;
            overflow: hidden;
            box-shadow: none;
            border: 0;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            background: var(--card-body-bg);
            margin: 0;
        }

        .student-card::after {
            content: "";
            position: absolute;
            top: var(--sc-safe-inset-y);
            bottom: var(--sc-safe-inset-y);
            left: var(--sc-safe-inset-x);
            right: var(--sc-safe-inset-x);
            pointer-events: none;
            z-index: 4;
        }

        .sc-header {
            background: var(--card-primary);
            color: var(--card-text-on-primary);
            box-sizing: border-box;
            min-height: calc(var(--sc-header-min-height, 0.36in) * var(--sc-scale));
            padding: calc(10px * var(--sc-scale)) 0.125in calc(5px * var(--sc-scale));
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
            gap: 2px;
            flex-shrink: 0;
        }

        .sc-header-logo {
            flex: 0 0 calc(45px * var(--sc-scale));
            width: calc(45px * var(--sc-scale));
            height: calc(45px * var(--sc-scale));
            border-radius: 6px;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .sc-header-logo img {
            max-width: calc(45px * var(--sc-scale));
            max-height: calc(45px * var(--sc-scale));
        }

        .sc-header-text {
            flex: 1;
            text-align: center;
            min-width: 0;
        }

        .sc-school-name {
            font-size: calc(var(--school-title-font-size, 15px) * var(--sc-scale));
            font-weight: 800;
            letter-spacing: 0.4px;
            line-height: 1.15;
            text-transform: uppercase;
            text-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
        }

        .sc-slogan {
            font-size: calc(9px * var(--sc-scale));
            font-weight: 500;
            font-style: italic;
            opacity: 0.92;
            margin-top: calc(2px * var(--sc-scale));
            letter-spacing: 0.2px;
            text-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
        }

        .sc-body {
            flex: 1;
            min-height: 0;
            overflow: hidden;
            position: relative;
            padding: calc(3px * var(--sc-scale)) 0.125in calc(2px * var(--sc-scale));
            background: linear-gradient(180deg, rgba(30, 58, 138, 0.06) 0%, #fff 18%, #fff 100%);
            background-color: var(--card-body-bg);
        }

        .sc-body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: calc(10px * var(--sc-scale));
            background: radial-gradient(ellipse 120% 100% at 50% 0%, var(--card-primary-light) 0%, transparent 72%);
            opacity: 0.35;
            pointer-events: none;
        }

        .sc-body-inner {
            display: flex;
            gap: calc(6px * var(--sc-scale));
            align-items: stretch;
            height: 100%;
            min-height: 0;
            overflow: hidden;
            position: relative;
            z-index: 1;
        }

        .sc-photo-wrap {
            flex: 0 0 calc(68px * var(--sc-scale));
            text-align: center;
        }

        .sc-photo {
            width: calc(68px * var(--sc-scale));
            height: calc(82px * var(--sc-scale));
            border-radius: 6px;
            border: 2px solid var(--card-photo-border);
            background: #f3f4f6;
            display: block;
            object-fit: cover;
            object-position: center;
        }

        .sc-photo-placeholder {
            width: calc(68px * var(--sc-scale));
            height: calc(82px * var(--sc-scale));
            border-radius: 6px;
            border: 2px solid var(--card-photo-border);
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: calc(5px * var(--sc-scale));
            font-weight: 700;
            color: #9ca3af;
            text-align: center;
            line-height: 1.2;
            padding: calc(2px * var(--sc-scale));
            box-sizing: border-box;
        }

        .sc-sig {
            font-size: calc(10px * var(--sc-scale));
            font-weight: 600;
            color: rgb(0, 0, 0);
            margin-top: 38%;
        }

        .sc-sig img {
            max-width: calc(42px * var(--sc-scale));
            max-height: calc(24px * var(--sc-scale));
            display: block;
            margin: 0 auto;
            margin-top: calc(-20px * var(--sc-scale));
        }

        .sc-fields {
            flex: 1;
            min-width: 0;
            min-height: 0;
            overflow: hidden;
            padding-right: calc(3px * var(--sc-scale));
            font-size: calc(7px * var(--sc-scale));
            color: var(--card-text-body);
        }

        .sc-row {
            border-bottom: 1px solid var(--card-primary);
            padding: calc(1px * var(--sc-scale)) 0 calc(0.5px * var(--sc-scale));
            margin-bottom: calc(1px * var(--sc-scale));
            line-height: 1.15;
        }

        .sc-row-inline {
            display: flex;
            gap: calc(6px * var(--sc-scale));
            align-items: flex-end;
            padding-bottom: calc(1px * var(--sc-scale));
        }

        .sc-inline-block {
            flex: 1;
            min-width: 0;
        }

        .sc-row:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
        }

        .sc-label {
            font-weight: 800;
            color: var(--card-primary);
            font-size: calc(7.5px * var(--sc-scale));
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .sc-value {
            font-weight: 600;
            font-size: calc(10px * var(--sc-scale));
            word-break: break-word;
        }

        /* Long names: keep within card width next to QR */
        .sc-value-name-sm {
            font-size: calc(9px * var(--sc-scale));
            line-height: 1.15;
        }

        .sc-value-name-xs {
            font-size: calc(8.5px * var(--sc-scale));
            line-height: 1.12;
        }

        .sc-value-compact {
            font-size: calc(8px * var(--sc-scale));
            line-height: 1.2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sc-qr {
            flex: 0 0 auto;
            align-self: flex-end;
            padding-bottom: calc(4px * var(--sc-scale));
            z-index: 2;
        }

        .sc-qr img {
            width: calc(75px * var(--sc-scale));
            height: calc(70px * var(--sc-scale));
            display: block;
            border-radius: calc(4px * var(--sc-scale));
            border: 1px solid #e5e7eb;
        }

        .sc-footer {
            background: var(--card-primary);
            color: var(--card-text-on-primary);
            padding: calc(1px * var(--sc-scale)) 0.125in calc(15px * var(--sc-scale));
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: calc(5px * var(--sc-scale));
            font-size: calc(9px * var(--sc-scale));
            flex-shrink: 0;
        }

        .sc-pill {
            background: rgba(255, 255, 255, 0.22);
            border-radius: 999px;
            padding: calc(2px * var(--sc-scale)) calc(8px * var(--sc-scale));
            font-weight: 700;
            white-space: nowrap;
        }

        .sc-footer-mid {
            text-align: center;
            flex: 1;
            font-weight: 700;
            line-height: 1.25;
        }

        .sc-footer-address {
            display: flex;
            align-items: center;
            gap: 3px;
            flex: 1;
            min-width: 0;
            font-weight: 700;
        }

        .sc-footer-address-text {
            flex: 1;
            min-width: 0;
            line-height: 1.2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sc-footer-phone {
            display: flex;
            align-items: center;
            gap: calc(3px * var(--sc-scale));
            font-weight: 700;
            white-space: nowrap;
            margin-right: calc(8px * var(--sc-scale));
        }

        /* Card customization popup (screen only) */
        .cc-ui {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 9999;
        }

        .cc-btn {
            background: var(--card-primary);
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(2, 6, 23, 0.18);
        }

        .cc-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            display: none;
            z-index: 10000;
        }

        .cc-modal {
            width: 95%;
            max-width: 980px;
            background: #fff;
            border-radius: 12px;
            margin: 5vh auto;
            overflow: hidden;
            box-shadow: 0 14px 40px rgba(2, 6, 23, 0.3);
        }

        .cc-header {
            background: var(--card-primary);
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

        .cc-body {
            padding: 14px 16px;
        }

        .cc-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px 16px;
            align-items: start;
        }

        .cc-field {
            min-width: 0;
        }

        .cc-field-span-full {
            grid-column: 1 / -1;
        }

        @media (max-width: 900px) {
            .cc-modal { max-width: 720px; }
            .cc-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }

        @media (max-width: 600px) {
            .cc-modal { max-width: 100%; }
            .cc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        .cc-label {
            display: block;
            font-weight: 900;
            color: var(--card-text-body);
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
            background: var(--card-primary);
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 1000;
        }

        tr th {
            background-color: gray;
            text-align: center;
            font-size: 14px;
            border: 1px solid gray;
        }

        tr td {
            border-bottom: 1px solid gray;
            border-left: 1px solid gray;
            padding: .7%;
        }

        .tdalign {
            text-align: center;
            border: 1px solid black;
        }

        body {
            font-weight: 600;
        }

        page {
            background: white;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            margin: 0 auto;
            margin-bottom: 0.5cm;
            width: var(--fixed-page-w);
            height: var(--fixed-page-h);
            padding: 0.12in;
            box-sizing: border-box;
            overflow: hidden;
            border: 1px solid #d6dbe3;
            page-break-after: always;
        }
        page:last-of-type { page-break-after: auto; }

        page[size="A4"] {
            width: var(--fixed-page-w);
            height: var(--fixed-page-h);
        }

        page[size="A4"][layout="landscape"] {
            width: var(--fixed-page-w);
            height: var(--fixed-page-h);
        }

        @media print {
            .cc-ui, #printToolbar, .cc-overlay { display: none !important; }
            body, page { margin: 0; box-shadow: none; }
            @page { size: 8in 11.5in; margin: 0; }
            .student-card { box-shadow: none; }
            .box { border: 1px dotted #b8b8b8; }
            page { border: 1px solid #d6dbe3; }
        }

        #printToolbar { position: fixed; top: 12px; left: 12px; z-index: 9999; }
        #printToolbar button { background:#ff7800; color:#fff; border:none; border-radius:10px; padding:10px 18px; font-size:14px; font-weight:800; cursor:pointer; }
    </style>
</head>

<body>
    <div id="printToolbar">
        <button onclick="window.print()">&#128424; Print Landscape Cards</button>
    </div>

    <div class="cc-ui">
        <button type="button" class="cc-btn" onclick="openCardCustomization()">Apply Customization</button>
        <button type="button" class="cc-btn" style="margin-left:8px;background:#374151;" onclick="openCardDimensions()">Card Dimensions</button>
    </div>

    <div id="ccOverlay" class="cc-overlay" onclick="if(event.target===this){closeCardCustomization();}">
        <div class="cc-modal">
            <div class="cc-header">
                <div class="cc-title">Card Customization</div>
                <button type="button" class="cc-close" onclick="closeCardCustomization()">&times;</button>
            </div>

            <form onsubmit="return applyCardCustomization(event);">
                <div class="cc-body">
                    <div class="cc-grid">
                        <div class="cc-field cc-field-span-full">
                            <div class="cc-label" style="margin-bottom:6px;font-weight:700;">Card size &amp; grid spacing (inches)</div>
                            <div style="font-size:12px;color:#6b7280;margin-bottom:0;">Set the cut size of each card, then spacing between columns and rows. Defaults match standard ID: 3.5 &times; 2.25 inches.</div>
                        </div>
                        <div class="cc-field">
                            <label class="cc-label">1. Card width (in)</label>
                            <input id="cc_card_w" class="cc-control" type="number" step="0.01" min="2" max="5" value="<?php echo e($cardW); ?>">
                        </div>
                        <div class="cc-field">
                            <label class="cc-label">2. Card height (in)</label>
                            <input id="cc_card_h" class="cc-control" type="number" step="0.01" min="1.5" max="4" value="<?php echo e($cardH); ?>">
                        </div>
                        <div class="cc-field">
                            <label class="cc-label">3. Gap between columns (in)</label>
                            <input id="cc_gap_col" class="cc-control" type="number" step="0.01" min="0" max="0.75" value="<?php echo e($gapCol); ?>">
                        </div>
                        <div class="cc-field">
                            <label class="cc-label">4. Gap between rows (in)</label>
                            <input id="cc_gap_row" class="cc-control" type="number" step="0.01" min="0" max="0.75" value="<?php echo e($gapRow); ?>">
                        </div>

                        <div class="cc-field">
                            <label class="cc-label">Display Date of Birth (DOB)</label>
                            <select id="cc_dob" class="cc-control">
                                <option value="YES" <?php echo $showDob === 'YES' ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo $showDob === 'NO' ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>

                        <div class="cc-field">
                            <label class="cc-label">Display Cell Number</label>
                            <select id="cc_cell" class="cc-control">
                                <option value="YES" <?php echo $showCell === 'YES' ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo $showCell === 'NO' ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>

                        <div class="cc-field">
                            <label class="cc-label">Display Student Address</label>
                            <select id="cc_student_address" class="cc-control">
                                <option value="YES" <?php echo $showAddress === 'YES' ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo $showAddress === 'NO' ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>

                        <div class="cc-field">
                            <label class="cc-label">Display Family Code</label>
                            <select id="cc_family_code" class="cc-control">
                                <option value="YES" <?php echo $showFamily === 'YES' ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo $showFamily === 'NO' ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>

                        <div class="cc-field">
                            <label class="cc-label">Valid Up To</label>
                            <input id="cc_valid" class="cc-control" type="date" value="<?php echo e($valid); ?>">
                        </div>

                        <div class="cc-field">
                            <label class="cc-label">Choose Color Scheme</label>
                            <input id="cc_color" class="cc-control" type="color" value="#<?php echo e($color); ?>">
                        </div>

                        <div class="cc-field">
                            <label class="cc-label">Display Slogan</label>
                            <select id="cc_slogan_display" class="cc-control">
                                <option value="YES" <?php echo $showSlogan === 'YES' ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo $showSlogan === 'NO' ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>

                        <div class="cc-field">
                            <label class="cc-label">School Title Font Size:</label>
                            <select id="cc_title_font_size" class="cc-control">
                                <?php for ($t = 10; $t <= 25; $t++): ?>
                                    <option value="<?php echo $t; ?>" <?php echo $t === $titleFs ? 'selected' : ''; ?>><?php echo $t; ?>px</option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="cc-field">
                            <label class="cc-label">Cards Per A4 Page</label>
                            <select id="cc_cards_per_page" class="cc-control">
                                <option value="8" <?php echo $per === 8 ? 'selected' : ''; ?>>8 Cards (2 x 4)</option>
                                <option value="10" <?php echo $per === 10 ? 'selected' : ''; ?>>10 Cards (2 x 5)</option>
                                <option value="6" <?php echo $per === 6 ? 'selected' : ''; ?>>6 Cards (2 x 3)</option>
                            </select>
                        </div>

                        <div class="cc-field cc-field-span-full">
                            <label class="cc-label">Slogan</label>
                            <input id="cc_slogan" class="cc-control" type="text" value="<?php echo e($slogan); ?>" placeholder="A Path to Excellence">
                        </div>
                    </div>
                </div>

                <div class="cc-footer">
                    <button type="button" class="cc-cancel" onclick="closeCardCustomization()">Cancel</button>
                    <button type="submit" class="cc-apply">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div id="dimOverlay" class="cc-overlay" onclick="if(event.target===this){closeCardDimensions();}">
        <div class="cc-modal" style="max-width:560px;">
            <div class="cc-header">
                <div class="cc-title">Card Dimensions Guide</div>
                <button type="button" class="cc-close" onclick="closeCardDimensions()">&times;</button>
            </div>
            <div class="cc-body" style="font-size:14px;line-height:1.6;">
                <p style="margin-bottom:10px;"><strong>Use these print-safe dimensions:</strong> You can change cut size, column gap, and row gap under <strong>Apply Customization</strong> (inches).</p>
                <ul style="padding-left:20px;margin-bottom:12px;">
                    <li><strong>Default full size (cutting size):</strong> 3.5" &times; 2.25"</li>
                    <li><strong>Safe Zone (Data Covered Size):</strong> 3.1" &times; 1.85"</li>
                    <li><strong>Recommended outer gap between cards:</strong> 0.06" (all sides)</li>
                    <li><strong>Recommended page margin:</strong> 0.12"</li>
                    <li><strong>Recommended A4 layouts:</strong> 10 cards (2&times;5), 8 cards (2&times;4), or 6 cards (2&times;3)</li>
                </ul>
                <div style="background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;padding:10px;color:#111827;">
                    <strong>Printing Tip:</strong> Keep important text and photo inside the safe zone to avoid cutting at edges.
                </div>
            </div>
            <div class="cc-footer">
                <button type="button" class="cc-cancel" onclick="closeCardDimensions()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function openCardCustomization() {
            var el = document.getElementById('ccOverlay');
            if (el) el.style.display = 'block';
        }

        function closeCardCustomization() {
            var el = document.getElementById('ccOverlay');
            if (el) el.style.display = 'none';
        }

        function openCardDimensions() {
            var el = document.getElementById('dimOverlay');
            if (el) el.style.display = 'block';
        }

        function closeCardDimensions() {
            var el = document.getElementById('dimOverlay');
            if (el) el.style.display = 'none';
        }

        function applyCardCustomization(e) {
            if (e && e.preventDefault) e.preventDefault();

            var overlay = document.getElementById('ccOverlay');
            if (overlay) overlay.style.display = 'none';

            var params = new URLSearchParams(window.location.search);
            params.set('DOB', document.getElementById('cc_dob').value);
            params.set('cell_number', document.getElementById('cc_cell').value);
            params.set('student_address', document.getElementById('cc_student_address').value);
            params.set('family_code', document.getElementById('cc_family_code').value);
            params.set('valid', document.getElementById('cc_valid').value);
            params.set('color', document.getElementById('cc_color').value.replace('#', ''));
            params.set('title_font_size', document.getElementById('cc_title_font_size').value);
            params.set('slogan_display', document.getElementById('cc_slogan_display').value);
            params.set('cards_per_page', document.getElementById('cc_cards_per_page').value);
            params.set('slogan', document.getElementById('cc_slogan').value || 'A Path to Excellence');
            params.set('card_w', document.getElementById('cc_card_w').value);
            params.set('card_h', document.getElementById('cc_card_h').value);
            params.set('gap_col', document.getElementById('cc_gap_col').value);
            params.set('gap_row', document.getElementById('cc_gap_row').value);

            window.location.search = params.toString();
            return false;
        }
    </script>

    <?php foreach ($pages as $pageStudents): ?>
    <page size="A4">
        <div class="cards-grid cards-grid-<?php echo $per; ?>">
            <?php foreach ($pageStudents as $st):
                $fullName = trim(($st['first_name'] ?? '') . ' / ' . ($st['last_name'] ?? ''));
                $fullName = trim($fullName, ' /');
                $nameLen = mb_strlen($fullName, 'UTF-8');
                $nameCls = '';
                if ($nameLen > 44) { $nameCls = ' sc-value-compact'; }
                elseif ($nameLen > 36) { $nameCls = ' sc-value-name-xs'; }
                elseif ($nameLen > 28) { $nameCls = ' sc-value-name-sm'; }

                $grNo = (string)($st['gr_no'] ?? '');
                $photo = '';
                if (!empty($st['photo']) && is_file(__DIR__ . '/uploads/students/' . $st['photo'])) {
                    $photo = BASE_URL . 'uploads/students/' . e($st['photo']);
                }
                $qrSrc = BASE_URL . 'qr_image.php?d=' . urlencode($st['qr_token']);
                $clsName = trim((string)($st['class_name'] ?? ''));
                $secName = trim((string)($st['section_name'] ?? ''));
                $classVal = ($clsName !== '' ? $clsName : 'Class not found') . ' - ' . $secName;
                $dobVal = !empty($st['dob']) ? date('d-M-Y', strtotime($st['dob'])) : '--';
                $phoneVal = trim((string)($st['phone'] ?? '')) !== '' ? $st['phone'] : '00';
                $addrVal = trim((string)($st['address'] ?? ''));
                $famVal = trim((string)($st['family_code'] ?? ''));
            ?>
            <div class="box">
                <div class="student-card">
                    <div class="sc-header">
                        <div class="sc-header-logo">
                            <img src="<?php echo $logoSrc; ?>" alt="" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                        </div>
                        <div class="sc-header-text">
                            <div class="sc-school-name"><?php echo e($schoolName); ?></div>
                            <?php if ($showSlogan === 'YES'): ?>
                                <div class="sc-slogan">&mdash; <?php echo e($slogan); ?> &mdash;</div>
                            <?php endif; ?>
                            <div class="sc-slogan" style="font-size: 12px;">Student ID Card</div>
                        </div>
                    </div>

                    <div class="sc-body">
                        <div class="sc-body-inner">
                            <div class="sc-photo-wrap">
                                <?php if ($photo !== ''): ?>
                                    <img class="sc-photo" src="<?php echo $photo; ?>" alt="">
                                <?php else: ?>
                                    <div class="sc-photo-placeholder">STUDENT<br>PHOTO</div>
                                <?php endif; ?>
                                <div class="sc-sig">
                                    <?php if ($sigSrc !== ''): ?>
                                        <img src="<?php echo $sigSrc; ?>" alt="" onerror="this.style.display='none';">
                                    <?php endif; ?>
                                    <span>Principal</span>
                                </div>
                            </div>

                            <div class="sc-fields">
                                <div class="sc-row sc-row-inline">
                                    <div class="sc-inline-block">
                                        <div class="sc-label">Student GR.No</div>
                                        <div class="sc-value"><?php echo e($grNo); ?></div>
                                    </div>
                                    <div class="sc-inline-block" style="text-align: right;">
                                        <div class="sc-label">Valid Up To</div>
                                        <div class="sc-value"><?php echo date('d-M-Y', strtotime($valid)); ?></div>
                                    </div>
                                </div>
                                <div class="sc-row">
                                    <div class="sc-label">Student Name</div>
                                    <div class="sc-value<?php echo $nameCls; ?>"><?php echo e($fullName); ?></div>
                                </div>
                                <div class="sc-row">
                                    <div class="sc-label">Class</div>
                                    <div class="sc-value"><?php echo e($classVal); ?></div>
                                </div>
                                <?php if ($showDob === 'YES'): ?>
                                <div class="sc-row">
                                    <div class="sc-label">Date of Birth </div>
                                    <div class="sc-value"><?php echo e($dobVal); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($showAddress === 'YES' && $addrVal !== ''): ?>
                                <div class="sc-row">
                                    <div class="sc-label">Student Address</div>
                                    <div class="sc-value sc-value-compact"><?php echo e($addrVal); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($showFamily === 'YES' && $famVal !== ''): ?>
                                <div class="sc-row">
                                    <div class="sc-label">Family Code</div>
                                    <div class="sc-value"><?php echo e($famVal); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($showCell === 'YES'): ?>
                                <div class="sc-row">
                                    <div class="sc-label">Contact</div>
                                    <div class="sc-value"><?php echo e($phoneVal); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="sc-qr">
                                <img src="<?php echo $qrSrc; ?>" alt="QR" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/qr-default.png';">
                            </div>
                        </div>
                    </div>

                    <div class="sc-footer">
                        <?php if (trim($schoolAddr) !== ''): ?>
                        <div class="sc-footer-address">
                            <span aria-hidden="true">&#128205;</span>
                            <span class="sc-footer-address-text"><?php echo e($schoolAddr); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (trim($schoolPhone) !== ''): ?>
                        <div class="sc-footer-phone">
                            <span aria-hidden="true">&#9742;</span>
                            <span><?php echo e($schoolPhone); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </page>
    <?php endforeach; ?>

</body>
</html>