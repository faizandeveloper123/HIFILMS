<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['valid'] ?? '') ? $_GET['valid'] : date('Y-m-d');
$color = preg_match('/^[0-9a-fA-F]{6}$/', $_GET['color'] ?? '') ? strtolower($_GET['color']) : '9c27b0';
$num = (int)($_GET['num'] ?? 8);
if (!in_array($num, [8, 10], true)) { $num = 8; }
$note = trim((string)($_GET['note'] ?? ''));
if ($note === '') { $note = 'In the case of loss this card kindly return it to the school address.'; }

$si = school_info((int)($_GET['campus_id'] ?? 0));
$schoolName  = $si['name'];
$schoolAddr  = $si['addr'];
$schoolPhone = $si['phone'];
$schoolLogo  = $si['logo'];
$logoSrc     = $schoolLogo !== '' ? BASE_URL . $schoolLogo : BASE_URL . 'assets/img/logo.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Portrait Back Side Cards</title>
    <style>
        .section { width: 95%; height: auto; float: left; border-right: 2px solid black; border-right-style: dotted; padding-right: 2.5%; margin-left: 2%; }

        .box {
            position: relative;
            width: 2.2in;
            height: 3.6in;
            float: left;
            padding: 0.5%;
            margin-top: 13px;
            margin-left: 30px;
            border: 2px solid black;
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
        }
        #printToolbar button {
            background: #<?php echo $color; ?>;
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
        <?php for ($i = 0; $i < $num; $i++): ?>
        <div class="box">
            <div style="height: auto; margin: 0;">
                <div style="background: #<?php echo $color; ?>; width: 100%; height: 18px; border: 2px solid #<?php echo $color; ?>; border-radius: 5px; margin-bottom: 8px;"></div>

                <div style="width: 100%; text-align: center; margin-top: 10px;">
                    <img src="<?php echo $logoSrc; ?>" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/img/logo.jpg';" style="height: 70px; width: 75px;">
                </div>

                <?php if (trim($schoolAddr) !== ''): ?>
                <div style="width: 100%; font-size: 10px; padding: 3px; margin-top: 8px; text-align: center;">
                    <div><?php echo e($schoolAddr); ?></div>
                </div>
                <?php endif; ?>

                <?php if (trim($schoolPhone) !== ''): ?>
                <div style="font-size: 10px; margin-top: 5px; text-align: center;">
                    Contact No: <?php echo e($schoolPhone); ?>
                </div>
                <?php endif; ?>

                <div style="text-align: center; margin-top: 15px;">
                    <p style="text-align: center; color: black; font-size: 9px; margin-bottom: 5px; line-height: 1.3;">
                        <?php echo e($note); ?>
                    </p>
                    <p style="text-align: center; color: black; font-size: 10px; margin-top: 5px; font-weight: bold;">
                        Valid till: <?php echo date('d-M-Y', strtotime($valid)); ?>
                    </p>
                </div>
            </div>

            <div style="background: #<?php echo $color; ?>; width: 100%; height: 18px; border: 2px solid #<?php echo $color; ?>; border-radius: 5px; position: absolute; bottom: 5px; left: 0; right: 0; margin: 0 auto; width: 90%;"></div>
        </div>
        <?php endfor; ?>
    </page>
</body>
</html>