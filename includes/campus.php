<?php
if (!defined('HIIFI')) exit('Direct access not allowed.');

// The campus/school helper functions live in config.php for local development.
// config.php is intentionally excluded from production deploys, so a deployed
// server may still run an older config.php that does not define them. This
// compatibility shim defines the missing helpers (guarded) and ensures the
// campuses table exists, so the app keeps working regardless of config age.

db_query("CREATE TABLE IF NOT EXISTS campuses (
  campus_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL DEFAULT '',
  tagline VARCHAR(191) NOT NULL DEFAULT '',
  logo VARCHAR(255) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  phone VARCHAR(64) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!function_exists('save_setting')) {
    function save_setting($key, $value) {
        $GLOBALS['__settings_clear'] = true;
        try {
            $st = db_prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $st->bind_param('ss', $key, $value);
            return $st->execute();
        } catch (Exception $ex) {
            $st = db_prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
            $st->bind_param('ss', $value, $key);
            $st->execute();
            if ($st->affected_rows === 0) {
                $st2 = db_prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)');
                $st2->bind_param('ss', $key, $value);
                return $st2->execute();
            }
            return true;
        }
    }
}

if (!function_exists('get_campuses')) {
    function get_campuses() {
        $res = @db_query("SELECT * FROM campuses ORDER BY is_active DESC, name");
        $rows = [];
        if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
        return $rows;
    }
}

if (!function_exists('get_campus')) {
    function get_campus($id) {
        $id = (int) $id;
        if ($id <= 0) { return null; }
        $p = db_prepare("SELECT * FROM campuses WHERE campus_id = ? LIMIT 1");
        $p->bind_param('i', $id);
        $p->execute();
        $r = $p->get_result();
        return $r ? $r->fetch_assoc() : null;
    }
}

if (!function_exists('get_active_campus')) {
    function get_active_campus() {
        $res = @db_query("SELECT * FROM campuses WHERE is_active = 1 LIMIT 1");
        if ($res && ($row = $res->fetch_assoc())) { return $row; }
        $res = @db_query("SELECT * FROM campuses ORDER BY campus_id LIMIT 1");
        if ($res && ($row = $res->fetch_assoc())) { return $row; }
        return null;
    }
}

if (!function_exists('apply_campus_settings')) {
    function apply_campus_settings($c) {
        $c = (array) $c;
        save_setting('school_name', (string) ($c['name'] ?? ''));
        save_setting('school_tagline', (string) ($c['tagline'] ?? ''));
        save_setting('school_address', (string) ($c['address'] ?? ''));
        save_setting('school_phone', (string) ($c['phone'] ?? ''));
        save_setting('school_logo', (string) ($c['logo'] ?? ''));
    }
}

if (!function_exists('school_info')) {
    function school_info($campusId = 0) {
        $campus = ((int) $campusId > 0) ? get_campus((int) $campusId) : null;
        if (!$campus) { $campus = get_active_campus(); }
        if (!$campus) {
            return [
                'name'    => get_setting('school_name', 'LAPS School & College'),
                'tagline' => get_setting('school_tagline', ''),
                'addr'    => get_setting('school_address', ''),
                'phone'   => get_setting('school_phone', ''),
                'logo'    => get_setting('school_logo', ''),
            ];
        }
        return [
            'name'    => (string) ($campus['name'] ?? ''),
            'tagline' => (string) ($campus['tagline'] ?? ''),
            'addr'    => (string) ($campus['address'] ?? ''),
            'phone'   => (string) ($campus['phone'] ?? ''),
            'logo'    => (string) ($campus['logo'] ?? ''),
        ];
    }
}