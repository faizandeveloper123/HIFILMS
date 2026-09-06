<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hiifi_lms');

// Base URL (local web root)
define('BASE_URL', '/HIIFI LMS/');

function db_connect() {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            die('Database connection failed: ' . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function db_query($sql) {
    return db_connect()->query($sql);
}

function db_prepare($sql) {
    return db_connect()->prepare($sql);
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function get_setting($key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = @db_query("SELECT setting_key, setting_value FROM settings");
        if ($res) { while ($row = $res->fetch_assoc()) { $cache[$row['setting_key']] = $row['setting_value']; } }
    }
    return $cache[$key] ?? $default;
}

function role() {
    return $_SESSION['user_role'] ?? 'admin';
}

function require_role($roles) {
    if (!is_array($roles)) { $roles = [$roles]; }
    if (!in_array(role(), $roles)) {
        ?>
        <!DOCTYPE html>
        <html lang="en"><head>
            <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Denied | HIIFI LMS</title>
            <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/img/favicon.png">
            <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
            <link href="<?php echo BASE_URL; ?>assets/css/font-awesome.min.css" rel="stylesheet">
            <style>
                body{display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;font-family:'Segoe UI',sans-serif;background:#f8f9fa;}
                .denied-card{text-align:center;padding:40px;background:#fff;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,.08);max-width:400px;width:90%;}
                .denied-card h1{font-size:64px;color:#dc2626;margin:0;}
                .denied-card p{color:#6b7280;margin:12px 0 24px;font-size:15px;}
                .denied-card a{display:inline-block;padding:10px 24px;background:#ff8c00;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;}
                .denied-card a:hover{background:#e07c00;}
            </style>
        </head><body>
            <div class="denied-card">
                <h1><i class="fas fa-lock"></i></h1>
                <h3 style="color:#111827;">Access Denied</h3>
                <p>You do not have permission to access this page.</p>
                <a href="<?php echo BASE_URL; ?>dashboard.php"><i class="fas fa-home"></i> Go to Dashboard</a>
            </div>
        </body></html>
        <?php
        exit;
    }
}