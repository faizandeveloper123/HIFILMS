<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

header('Content-Type: application/json');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? 'teacher';

db_query("CREATE TABLE IF NOT EXISTS lesson_plans (
    id INT(11) NOT NULL AUTO_INCREMENT,
    teacher_id INT(11) NOT NULL,
    class_id INT(11) NOT NULL,
    section_id INT(11) NOT NULL,
    subject_id INT(11) NOT NULL,
    week_start DATE NOT NULL,
    day VARCHAR(10) NOT NULL,
    topic VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
    approved_by INT(11) DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_week (week_start),
    KEY idx_teacher (teacher_id),
    KEY idx_class_section (class_id, section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $week_start = trim($_GET['week_start'] ?? '');
    $class_id = (int)($_GET['class_id'] ?? 0);
    $section_id = (int)($_GET['section_id'] ?? 0);

    if ($week_start === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
        echo json_encode(['success' => false, 'message' => 'Invalid week_start']);
        exit;
    }

    $sql = "SELECT lp.*, c.class_name, sec.section_name, sub.subject_name,
                   CONCAT(e.first_name, ' ', e.last_name) AS teacher_name
            FROM lesson_plans lp
            JOIN classes c ON c.class_id = lp.class_id
            JOIN sections sec ON sec.section_id = lp.section_id
            JOIN subjects sub ON sub.subject_id = lp.subject_id
            JOIN employees e ON e.user_id = lp.teacher_id
            WHERE lp.week_start = ?";
    $types = 's';
    $params = [$week_start];

    if ($class_id > 0) {
        $sql .= " AND lp.class_id = ?";
        $types .= 'i';
        $params[] = $class_id;
    }
    if ($section_id > 0) {
        $sql .= " AND lp.section_id = ?";
        $types .= 'i';
        $params[] = $section_id;
    }

    $sql .= " ORDER BY FIELD(lp.day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), c.class_name, sec.section_name, sub.subject_name";

    $stmt = db_prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $plans = [];
    while ($row = $res->fetch_assoc()) {
        $plans[] = [
            'id' => (int)$row['id'],
            'teacher_id' => (int)$row['teacher_id'],
            'teacher_name' => $row['teacher_name'],
            'class_id' => (int)$row['class_id'],
            'class_name' => $row['class_name'],
            'section_id' => (int)$row['section_id'],
            'section_name' => $row['section_name'],
            'subject_id' => (int)$row['subject_id'],
            'subject_name' => $row['subject_name'],
            'week_start' => $row['week_start'],
            'day' => $row['day'],
            'topic' => $row['topic'],
            'description' => $row['description'] ?? '',
            'status' => $row['status'],
            'approved_by' => $row['approved_by'] ? (int)$row['approved_by'] : null,
            'approved_at' => $row['approved_at'],
            'created_at' => $row['created_at'],
        ];
    }

    echo json_encode(['success' => true, 'data' => $plans]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $action = trim($input['action'] ?? '');

    if ($action === 'save') {
        $id = (int)($input['id'] ?? 0);
        $teacher_id = (int)($input['teacher_id'] ?? $user_id);
        $class_id = (int)($input['class_id'] ?? 0);
        $section_id = (int)($input['section_id'] ?? 0);
        $subject_id = (int)($input['subject_id'] ?? 0);
        $week_start = trim($input['week_start'] ?? '');
        $day = trim($input['day'] ?? '');
        $topic = trim($input['topic'] ?? '');
        $description = trim($input['description'] ?? '');
        $status = trim($input['status'] ?? 'draft');

        $valid_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        $valid_statuses = ['draft','submitted'];

        if (!in_array($day, $valid_days)) {
            echo json_encode(['success' => false, 'message' => 'Invalid day']);
            exit;
        }
        if (!in_array($status, $valid_statuses)) {
            $status = 'draft';
        }
        if ($class_id <= 0 || $section_id <= 0 || $subject_id <= 0 || $week_start === '' || $topic === '') {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            echo json_encode(['success' => false, 'message' => 'Invalid week_start date']);
            exit;
        }

        if ($id > 0) {
            $stmt = db_prepare("UPDATE lesson_plans SET class_id=?, section_id=?, subject_id=?, day=?, topic=?, description=?, status=? WHERE id=? AND teacher_id=?");
            $stmt->bind_param('iiiisssii', $class_id, $section_id, $subject_id, $day, $topic, $description, $status, $id, $teacher_id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Lesson plan updated', 'id' => $id]);
        } else {
            $stmt = db_prepare("INSERT INTO lesson_plans (teacher_id, class_id, section_id, subject_id, week_start, day, topic, description, status) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('iiiisssss', $teacher_id, $class_id, $section_id, $subject_id, $week_start, $day, $topic, $description, $status);
            $stmt->execute();
            $new_id = $stmt->insert_id;
            echo json_encode(['success' => true, 'message' => 'Lesson plan created', 'id' => $new_id]);
        }
        exit;
    }

    if ($action === 'approve' || $action === 'reject') {
        if (!in_array($user_role, ['admin', 'hod'])) {
            echo json_encode(['success' => false, 'message' => 'Only admin/HOD can approve or reject']);
            exit;
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid lesson plan ID']);
            exit;
        }

        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        $now = date('Y-m-d H:i:s');

        $stmt = db_prepare("UPDATE lesson_plans SET status=?, approved_by=?, approved_at=? WHERE id=?");
        $stmt->bind_param('sisi', $new_status, $user_id, $now, $id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Lesson plan ' . $new_status]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $stmt = db_prepare("DELETE FROM lesson_plans WHERE id=? AND teacher_id=? AND status IN ('draft','rejected')");
        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Lesson plan deleted']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
