<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

db_query("CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(255) NOT NULL,
    cnic VARCHAR(30) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    purpose VARCHAR(255) DEFAULT NULL,
    person_to_meet VARCHAR(255) DEFAULT NULL,
    student_id INT DEFAULT NULL,
    check_in DATETIME DEFAULT CURRENT_TIMESTAMP,
    check_out DATETIME DEFAULT NULL,
    badge_number VARCHAR(30) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'checked_in',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$post = [];
if (isset($_POST['action'])) {
    $post = $_POST;
} else {
    $post = json_decode(file_get_contents('php://input'), true) ?: $_POST;
}

$action = $post['action'] ?? 'checkin';

if ($action === 'checkout') {
    $id = (int) ($post['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid visitor ID']);
        exit;
    }

    $stmt = db_prepare("UPDATE visitors SET check_out = NOW(), status = 'checked_out' WHERE id = ? AND status = 'checked_in'");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Visitor checked out']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Visitor not found or already checked out']);
    }
    exit;
}

// Check-in
$visitor_name = trim($post['visitor_name'] ?? '');
$cnic = trim($post['cnic'] ?? '');
$phone = trim($post['phone'] ?? '');
$purpose = trim($post['purpose'] ?? '');
$person_to_meet = trim($post['person_to_meet'] ?? '');
$student_id = (int) ($post['student_id'] ?? 0);
$photo = trim($post['photo'] ?? '');

if ($visitor_name === '') {
    echo json_encode(['success' => false, 'message' => 'Visitor name is required']);
    exit;
}

$today = date('Y-m-d');
$nextNum = 1;
$res = db_query("SELECT badge_number FROM visitors WHERE DATE(check_in) = '$today' ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows > 0) {
    $last = $res->fetch_assoc();
    $parts = explode('-', $last['badge_number']);
    $nextNum = (int)end($parts) + 1;
}
$badge_number = 'V-' . $today . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

$student_id_val = $student_id > 0 ? $student_id : null;

$stmt = db_prepare("INSERT INTO visitors (visitor_name, cnic, phone, purpose, person_to_meet, student_id, badge_number, photo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'checked_in')");
$stmt->bind_param('ssssssss', $visitor_name, $cnic, $phone, $purpose, $person_to_meet, $student_id_val, $badge_number, $photo);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Visitor checked in', 'badge_number' => $badge_number, 'id' => $stmt->insert_id]);
