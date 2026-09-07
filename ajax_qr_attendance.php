<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? $_POST['token'] ?? '');

    if ($token === '') {
        echo json_encode(['success' => false, 'message' => 'No token provided.']);
        exit;
    }

    $stmt = db_prepare("SELECT qt.id AS token_id, qt.user_id, qt.user_type, qt.expires_at, qt.is_active,
                               s.student_id, s.first_name, s.last_name, s.class_id, s.section_id, s.photo,
                               c.class_name, sec.section_name
                        FROM qr_tokens qt
                        JOIN students s ON qt.user_id = s.student_id AND qt.user_type = 'student'
                        LEFT JOIN classes c ON s.class_id = c.class_id
                        LEFT JOIN sections sec ON s.section_id = sec.section_id
                        WHERE qt.token = ? AND qt.is_active = 1 AND s.status = 1
                        LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Invalid or inactive QR token.']);
        exit;
    }

    if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'This QR token has expired.']);
        exit;
    }

    $student_id = (int) $row['student_id'];
    $class_id   = (int) $row['class_id'];
    $section_id = (int) $row['section_id'];
    $today      = date('Y-m-d');
    $now        = date('Y-m-d H:i:s');
    $device_ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $late_cutoff = '08:30:00';
    $status     = (date('H:i:s') > $late_cutoff) ? 'late' : 'present';

    $existing = db_prepare("SELECT id FROM qr_attendance WHERE student_id = ? AND DATE(scanned_at) = ?");
    $existing->bind_param('is', $student_id, $today);
    $existing->execute();
    $exist_row = $existing->get_result()->fetch_assoc();

    if ($exist_row) {
        echo json_encode([
            'success'      => false,
            'message'      => 'Attendance already marked for ' . e($row['first_name'] . ' ' . $row['last_name']) . ' today.',
            'student_name' => $row['first_name'] . ' ' . $row['last_name'],
            'class_name'   => $row['class_name'],
            'photo'        => $row['photo'] ? (BASE_URL . 'uploads/students/' . $row['photo']) : '',
            'status'       => 'already_marked',
        ]);
        exit;
    }

    $ins = db_prepare("INSERT INTO qr_attendance (student_id, class_id, section_id, scanned_at, method, device_ip, status) VALUES (?, ?, ?, ?, 'qr', ?, ?)");
    $ins->bind_param('iissss', $student_id, $class_id, $section_id, $now, $device_ip, $status);
    $ins->execute();

    $photo_url = $row['photo'] ? (BASE_URL . 'uploads/students/' . $row['photo']) : '';

    echo json_encode([
        'success'      => true,
        'message'      => 'Attendance marked successfully!',
        'student_name' => $row['first_name'] . ' ' . $row['last_name'],
        'class_name'   => $row['class_name'],
        'section_name' => $row['section_name'],
        'photo'        => $photo_url,
        'status'       => $status,
        'scanned_at'   => $now,
    ]);
    exit;
}

if ($method === 'GET') {
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $class_id = (int)($_GET['class_id'] ?? 0);

    $sql = "SELECT qa.id, qa.student_id, qa.scanned_at, qa.status, qa.method,
                   s.first_name, s.last_name, s.photo,
                   c.class_name, sec.section_name
            FROM qr_attendance qa
            JOIN students s ON qa.student_id = s.student_id
            LEFT JOIN classes c ON qa.class_id = c.class_id
            LEFT JOIN sections sec ON qa.section_id = sec.section_id
            WHERE DATE(qa.scanned_at) = CURDATE()";
    $params = []; $types = '';

    if ($class_id > 0) {
        $sql .= " AND qa.class_id = ?";
        $params[] = $class_id; $types .= 'i';
    }

    $sql .= " ORDER BY qa.scanned_at DESC LIMIT ?";
    $params[] = $limit; $types .= 'i';

    $stmt = db_prepare($sql);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    $log = [];
    while ($row = $res->fetch_assoc()) {
        $row['photo_url'] = $row['photo'] ? (BASE_URL . 'uploads/students/' . $row['photo']) : '';
        $log[] = $row;
    }

    echo json_encode(['success' => true, 'log' => $log]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
