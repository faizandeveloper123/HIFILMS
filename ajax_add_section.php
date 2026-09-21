<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json');

$class_id    = (int) ($_POST['class_id'] ?? 0);
$section_name = trim($_POST['section_name'] ?? '');

if ($class_id <= 0 || $section_name === '') {
    echo json_encode(['success' => false, 'message' => 'Class and section name are required.']);
    exit;
}

$chk = db_prepare("SELECT section_id, section_name FROM sections WHERE class_id = ? AND LOWER(section_name) = LOWER(?) LIMIT 1");
$chk->bind_param('is', $class_id, $section_name);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();

if ($existing) {
    echo json_encode(['success' => true, 'section_id' => (int) $existing['section_id'], 'section_name' => $existing['section_name'], 'created' => false]);
    exit;
}

$ins = db_prepare("INSERT INTO sections (class_id, section_name) VALUES (?, ?)");
$ins->bind_param('is', $class_id, $section_name);
if (!$ins->execute()) {
    echo json_encode(['success' => false, 'message' => 'Could not add section: ' . $ins->error]);
    exit;
}

echo json_encode(['success' => true, 'section_id' => (int) $ins->insert_id, 'section_name' => $section_name, 'created' => true]);