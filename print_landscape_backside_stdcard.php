<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['valid'] ?? '') ? $_GET['valid'] : date('Y-m-d');
$color = preg_match('/^[0-9a-fA-F]{6}$/', $_GET['color'] ?? '') ? strtolower($_GET['color']) : '9c27b0';
$num = (int)($_GET['num'] ?? 8);
if (!in_array($num, [8, 10], true)) { $num = 8; }
$note = trim((string)($_GET['note'] ?? ''));
if ($note === '') { $note = 'In case of loss, kindly return this card to the school office.'; }

$schoolName  = get_setting('school_name', 'LAPS School & College');
$schoolAddr  = get_setting('school_address', '');
$schoolPhone = get_setting('school_phone', '');
$schoolLogo  = get_setting('school_logo', '');
$logoSrc     = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Back Side Cards</title>
    <style>
        :root {
            --card-primary: #<?php echo $color; ?>;
            --card-cut-w: 3.5in;
            --card-cut-h: 2.25in;
            --safe-w: 3.1in;
            --safe-h: 1.85in;
            --safe-inset-x: 0.2in;
            --safe-inset-y: 0.2in;
            --fixed-page-w: 8in;
            --fixed-page-h: 11.5in;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #0f172a;
            background: #fff;
        }
        page {
            background: #fff;
            display: flex;
            align-items: center;
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

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, var(--card-cut-w));
            justify-content: center;
            column-gap: 0;
            row-gap: 0;
            grid-auto-rows: var(--card-cut-h);
            align-content: center;
        }

        .box {
            width: var(--card-cut-w);
            height: var(--card-cut-h);
            border: 0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .back-card {
            width: var(--card-cut-w);
            height: var(--card-cut-h);
            background: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .safe-zone {
            position: absolute;
            top: var(--safe-inset-y);
            bottom: var(--safe-inset-y);
            left: var(--safe-inset-x);
            right: var(--safe-inset-x);
            pointer-events: none;
            z-index: 5;
        }

        .top-band,
        .bottom-band {
            height: 0.22in;
            background: var(--card-primary);
            flex-shrink: 0;
        }

        .back-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            text-align: center;
            padding: 6px var(--safe-inset-x) 6px;
            gap: 2px;
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 40%, #ffffff 100%);
        }

        .back-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            margin-bottom: 2px;
            flex-shrink: 0;
        }
        .back-school {
            font-size: 17px;
            font-weight: 900;
            letter-spacing: 0.2px;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .back-campus-row {
            display: flex;
            flex-direction: column;
            gap: 1px;
            margin-top: 3px;
            width: 100%;
        }
        .back-campus {
            font-size: 8px;
            font-weight: 700;
            color: #1f2937;
            line-height: 1.3;
        }
        .back-campus span {
            font-weight: 800;
            color: var(--card-primary);
        }
        .back-contact {
            font-size: 8px;
            font-weight: 700;
            color: #1f2937;
            line-height: 1.25;
        }
        .back-note {
            font-size: 7.5px;
            font-weight: 700;
            color: #111827;
            line-height: 1.28;
            max-width: 100%;
            margin-top: 3px;
            border-top: 1px solid #e5e7eb;
            padding-top: 3px;
            width: 100%;
        }
        .back-valid {
            font-size: 9px;
            font-weight: 800;
            color: #111827;
            margin-top: 1px;
        }

        @media print {
            @page { size: 8in 11.5in; margin: 0; }
            body, page {
                margin: 0;
                box-shadow: none;
            }
            page { border: 1px solid #d6dbe3; }
            #printToolbar { display: none !important; }
        }

        #printToolbar {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 9999;
        }
        #printToolbar button {
            background: var(--card-primary);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div id="printToolbar">
        <button onclick="window.print()">Print Back Cards</button>
    </div>
    <page size="A4">
        <div class="cards-grid">
            <?php for ($i = 0; $i < $num; $i++): ?>
            <div class="box">
                <div class="back-card">
                    <div class="safe-zone"></div>
                    <div class="top-band"></div>
                    <div class="back-body">
                        <img class="back-logo" src="<?php echo $logoSrc; ?>" alt="" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';">
                        <div class="back-school"><?php echo e($schoolName); ?></div>
                        <div class="back-campus-row">
                            <?php if (trim($schoolAddr) !== ''): ?>
                            <div class="back-campus"><span>Address:</span> <?php echo e($schoolAddr); ?></div>
                            <?php endif; ?>
                            <?php if (trim($schoolPhone) !== ''): ?>
                            <div class="back-campus"><span>Contact:</span> <?php echo e($schoolPhone); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="back-note"><?php echo e($note); ?></div>
                        <div class="back-valid">Valid Up To: <?php echo date('d-M-Y', strtotime($valid)); ?></div>
                    </div>
                    <div class="bottom-band"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </page>
</body>
</html>