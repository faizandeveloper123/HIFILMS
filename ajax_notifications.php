<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ensure_schema.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Ensure tables exist
_hiifi_try_db("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type VARCHAR(30) DEFAULT 'general',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Auto-delete old notifications
_hiifi_try_db("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");

if ($method === 'GET') {
    // Return unread count + last 10 notifications
    $uc = db_prepare("SELECT COUNT(*) c FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
    $uc->bind_param('i', $user_id);
    $uc->execute();
    $unread = (int) $uc->get_result()->fetch_assoc()['c'];

    $nr = db_prepare("SELECT id, title, message, type, is_read, link, created_at FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY created_at DESC LIMIT 10");
    $nr->bind_param('i', $user_id);
    $nr->execute();
    $res = $nr->get_result();
    $notifs = [];
    while ($row = $res->fetch_assoc()) {
        $notifs[] = [
            'id'         => (int) $row['id'],
            'title'      => $row['title'],
            'message'    => $row['message'],
            'type'       => $row['type'],
            'is_read'    => (bool) $row['is_read'],
            'link'       => $row['link'],
            'created_at' => $row['created_at'],
        ];
    }

    echo json_encode(['unread' => $unread, 'notifications' => $notifs]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? $_POST['action'] ?? '';

    // Mark as read
    if ($action === 'mark_read') {
        $nid = (int) ($input['id'] ?? $_POST['id'] ?? 0);
        if ($nid > 0) {
            $st = db_prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
            $st->bind_param('ii', $nid, $user_id);
            $st->execute();
            echo json_encode(['success' => true, 'message' => 'Marked as read.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
        }
        exit;
    }

    // Create notification
    $target_user = $input['target_user'] ?? $_POST['target_user'] ?? '';
    $title       = $input['title'] ?? $_POST['title'] ?? '';
    $msg_body    = $input['message'] ?? $_POST['message'] ?? '';
    $type        = $input['type'] ?? $_POST['type'] ?? 'general';
    $link        = $input['link'] ?? $_POST['link'] ?? '';

    if ($title === '' || $msg_body === '') {
        echo json_encode(['success' => false, 'message' => 'Title and message are required.']);
        exit;
    }

    if ($target_user === '' || $target_user === 'all') {
        $ins = db_prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (NULL, ?, ?, ?, ?)");
        $ins->bind_param('ssss', $title, $msg_body, $type, $link);
    } else {
        $target_user = (int) $target_user;
        $ins = db_prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param('issss', $target_user, $title, $msg_body, $type, $link);
    }
    $ins->execute();
    echo json_encode(['success' => true, 'message' => 'Notification created.', 'id' => $ins->insert_id]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
