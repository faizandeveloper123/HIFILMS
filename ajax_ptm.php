<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

db_query("CREATE TABLE IF NOT EXISTS ptm_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    meeting_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    class_id INT DEFAULT NULL,
    section_id INT DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'scheduled',
    feedback TEXT DEFAULT NULL,
    rating TINYINT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$action = $_REQUEST['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'create' : 'list');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $date = $_GET['date'] ?? '';
    $class_id = (int) ($_GET['class_id'] ?? 0);
    $section_id = (int) ($_GET['section_id'] ?? 0);
    $status = trim($_GET['status'] ?? '');

    $where = [];
    $params = [];
    $types = '';

    if ($date !== '') {
        $where[] = "m.meeting_date = ?";
        $params[] = $date;
        $types .= 's';
    }
    if ($class_id > 0) {
        $where[] = "m.class_id = ?";
        $params[] = $class_id;
        $types .= 'i';
    }
    if ($section_id > 0) {
        $where[] = "m.section_id = ?";
        $params[] = $section_id;
        $types .= 'i';
    }
    if ($status !== '' && in_array($status, ['scheduled', 'completed', 'cancelled'])) {
        $where[] = "m.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    $sql = "SELECT m.*, c.class_name, sec.section_name,
                   CONCAT(te.first_name, ' ', COALESCE(te.last_name, '')) AS teacher_name,
                   CONCAT(st.first_name, ' ', COALESCE(st.last_name, '')) AS parent_name
            FROM ptm_meetings m
            LEFT JOIN classes c ON m.class_id = c.class_id
            LEFT JOIN sections sec ON m.section_id = sec.section_id
            LEFT JOIN employees te ON m.teacher_id = te.emp_id
            LEFT JOIN students st ON m.parent_id = st.student_id"
            . (count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY m.meeting_date DESC, m.start_time ASC";

    $meetings = [];
    if (count($params) > 0) {
        $stmt = db_prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = db_query($sql);
    }
    while ($row = $res->fetch_assoc()) { $meetings[] = $row; }

    echo json_encode(['success' => true, 'meetings' => $meetings]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $post_action = $post['action'] ?? $action;

    if ($post_action === 'complete') {
        $id = (int) ($post['id'] ?? 0);
        $feedback = trim($post['feedback'] ?? '');
        $rating = (int) ($post['rating'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid meeting ID']);
            exit;
        }
        if ($rating < 1 || $rating > 5) { $rating = null; }

        $stmt = db_prepare("UPDATE ptm_meetings SET status='completed', feedback=?, rating=? WHERE id=?");
        $stmt->bind_param('sii', $feedback, $rating, $id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Meeting marked as completed']);
        exit;
    }

    if ($post_action === 'update') {
        $id = (int) ($post['id'] ?? 0);
        $title = trim($post['title'] ?? '');
        $meeting_date = $post['meeting_date'] ?? '';
        $start_time = $post['start_time'] ?? '';
        $end_time = $post['end_time'] ?? '';
        $class_id = (int) ($post['class_id'] ?? 0);
        $section_id = (int) ($post['section_id'] ?? 0);
        $teacher_id = (int) ($post['teacher_id'] ?? 0);
        $parent_id = (int) ($post['parent_id'] ?? 0);
        $notes = trim($post['notes'] ?? '');
        $status = trim($post['status'] ?? 'scheduled');

        if ($id <= 0 || $title === '' || $meeting_date === '' || $start_time === '') {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        if (!in_array($status, ['scheduled', 'completed', 'cancelled'])) { $status = 'scheduled'; }

        $stmt = db_prepare("UPDATE ptm_meetings SET title=?, meeting_date=?, start_time=?, end_time=?,
            class_id=?, section_id=?, teacher_id=?, parent_id=?, notes=?, status=? WHERE id=?");
        $stmt->bind_param('ssssiissii', $title, $meeting_date, $start_time, $end_time,
            $class_id, $section_id, $teacher_id, $parent_id, $notes, $status, $id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Meeting updated', 'id' => $id]);
        exit;
    }

    // create
    $title = trim($post['title'] ?? '');
    $meeting_date = $post['meeting_date'] ?? '';
    $start_time = $post['start_time'] ?? '';
    $end_time = $post['end_time'] ?? '';
    $class_id = (int) ($post['class_id'] ?? 0);
    $section_id = (int) ($post['section_id'] ?? 0);
    $teacher_id = (int) ($post['teacher_id'] ?? 0);
    $parent_id = (int) ($post['parent_id'] ?? 0);
    $notes = trim($post['notes'] ?? '');

    if ($title === '' || $meeting_date === '' || $start_time === '' || $end_time === '') {
        echo json_encode(['success' => false, 'message' => 'Title, date, start and end time are required']);
        exit;
    }

    $created_by = (int) ($_SESSION['user_id'] ?? 0);

    $stmt = db_prepare("INSERT INTO ptm_meetings (title, meeting_date, start_time, end_time, class_id, section_id, teacher_id, parent_id, notes, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)");
    $class_id = $class_id ?: null;
    $section_id = $section_id ?: null;
    $teacher_id = $teacher_id ?: null;
    $parent_id = $parent_id ?: null;
    $stmt->bind_param('ssssiiissi', $title, $meeting_date, $start_time, $end_time, $class_id, $section_id, $teacher_id, $parent_id, $notes, $created_by);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Meeting created', 'id' => $stmt->insert_id]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
