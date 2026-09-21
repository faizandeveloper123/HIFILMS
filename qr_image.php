<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$data = (string) ($_GET['d'] ?? $_GET['data'] ?? '');
if ($data === '' || strlen($data) > 500) { http_response_code(400); exit; }

$size = max(2, min(12, (int) ($_GET['size'] ?? 6)));

require_once __DIR__ . '/lib/phpqrcode.php';

header('Content-Type: image/png');
QRcode::png($data, false, QR_ECLEVEL_L, $size, 2);