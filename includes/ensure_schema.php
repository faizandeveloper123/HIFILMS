<?php
if (!defined('HIIFI')) exit('Direct access not allowed.');

require_once __DIR__ . '/campus.php';

// Idempotent runtime schema upgrades for the Messages / Complaint Hub / Support Tickets parity work.
// Cannot modify database/schema.sql, so these run on page load via CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS.

function _hiifi_try_db($sql) {
    try {
        db_query($sql);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// --- messages: parity columns -------------------------------------------------
_hiifi_try_db("ALTER TABLE messages ADD COLUMN IF NOT EXISTS channel VARCHAR(30) DEFAULT 'whatsapp'");
_hiifi_try_db("ALTER TABLE messages ADD COLUMN IF NOT EXISTS recipient_list TEXT");
_hiifi_try_db("ALTER TABLE messages ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending'");
_hiifi_try_db("ALTER TABLE messages ADD COLUMN IF NOT EXISTS message_type VARCHAR(20) DEFAULT 'english'");
_hiifi_try_db("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment VARCHAR(191) DEFAULT NULL");
_hiifi_try_db("ALTER TABLE messages ADD COLUMN IF NOT EXISTS template_title VARCHAR(191) DEFAULT NULL");

// --- sms_templates ------------------------------------------------------------
_hiifi_try_db("CREATE TABLE IF NOT EXISTS sms_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(191) NOT NULL,
    body TEXT,
    channel VARCHAR(30) DEFAULT 'whatsapp',
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// --- tickets ------------------------------------------------------------------
_hiifi_try_db("CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(30) DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    module VARCHAR(191) DEFAULT NULL,
    priority VARCHAR(20) DEFAULT 'Medium',
    description TEXT,
    status VARCHAR(20) DEFAULT 'open',
    rating TINYINT(1) DEFAULT 0,
    attachment VARCHAR(191) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// --- complaints: parity columns ----------------------------------------------
// complaint_id already exists as the INT auto-increment PK, so the IF NOT EXISTS below is a skip.
_hiifi_try_db("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS complaint_id VARCHAR(30) DEFAULT NULL");
_hiifi_try_db("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS complaint_code VARCHAR(30) DEFAULT NULL");
_hiifi_try_db("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS complainant_type VARCHAR(50) DEFAULT 'general'");
_hiifi_try_db("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS complainant_name VARCHAR(191) DEFAULT NULL");
_hiifi_try_db("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS complainant_mobile VARCHAR(50) DEFAULT NULL");
_hiifi_try_db("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS remarks TEXT");

// Widen status to a plain string, then map the earlier legacy values
// open -> new, in_progress -> in-process, closed -> resolved, resolved -> resolved
_hiifi_try_db("ALTER TABLE complaints MODIFY status VARCHAR(30) NOT NULL DEFAULT 'new'");
_hiifi_try_db("UPDATE complaints SET status='new' WHERE status IN ('open')");
_hiifi_try_db("UPDATE complaints SET status='in-process' WHERE status IN ('in_progress')");
_hiifi_try_db("UPDATE complaints SET status='resolved' WHERE status IN ('closed', 'resolved')");

// Back-fill display codes + complainant data for rows created before parity.
_hiifi_try_db("UPDATE complaints SET complaint_code = CONCAT('CMP-', DATE_FORMAT(created_at, '%Y'), '-', LPAD(complaint_id, 4, '0'))
               WHERE complaint_code IS NULL OR complaint_code = ''");
_hiifi_try_db("UPDATE complaints SET complainant_type = NULLIF(complaint_type, '') WHERE (complainant_type IS NULL OR complainant_type = '') AND complaint_type IS NOT NULL");
_hiifi_try_db("UPDATE complaints SET complainant_name = CONCAT('Complainant #', complaint_id) WHERE complainant_name IS NULL OR complainant_name = ''");

// --- LAPS rebrand: switch legacy default school name once -------------------
$lapsCheck = @db_query("SELECT setting_value FROM settings WHERE setting_key = 'school_name'");
if ($lapsCheck && ($lapsRow = $lapsCheck->fetch_assoc()) && stripos((string)($lapsRow['setting_value'] ?? ''), 'HIIFI') !== false) {
    _hiifi_try_db("UPDATE settings SET setting_value = 'LAPS School & College' WHERE setting_key = 'school_name'");
}

// --- LAPS admin credentials: rebrand the primary admin login once -------------
$_lapsAdminId = 0;
$_lapsRes = @db_query("SELECT user_id FROM users WHERE email IN ('admin@hiifi.pk','admin@laps.pk') ORDER BY user_id LIMIT 1");
if ($_lapsRes && ($_lapsRow = $_lapsRes->fetch_assoc())) { $_lapsAdminId = (int)($_lapsRow['user_id'] ?? 0); }
if ($_lapsAdminId <= 0) {
    $_lapsRes = @db_query("SELECT user_id FROM users WHERE email = 'kashif123@gmail.com' ORDER BY user_id LIMIT 1");
    if ($_lapsRes && ($_lapsRow = $_lapsRes->fetch_assoc())) { $_lapsAdminId = (int)($_lapsRow['user_id'] ?? 0); }
}
if ($_lapsAdminId > 0) {
    _hiifi_try_db("UPDATE users SET email = 'laps@gmail.com', password = SHA2('Laps@2026', 256), full_name = 'LAPS Admin' WHERE user_id = " . $_lapsAdminId . " AND email <> 'laps@gmail.com'");
}
unset($_lapsAdminId, $_lapsRes, $_lapsRow);

// --- sample sms templates (only if table is empty) ---------------------------
$seedCheck = @db_query("SELECT COUNT(*) c FROM sms_templates");
if ($seedCheck && ((int) $seedCheck->fetch_assoc()['c']) === 0) {
    $seedTemplates = [
        ['Fee Reminder (Outstanding Balance)',  'Please be reminded of an outstanding fee balance of 5000. Kindly settle it at your earliest convenience.', 'whatsapp'],
        ['Fee Overdue',                          'Your fee payment of 5500 is overdue. Kindly make the payment as soon as possible.', 'whatsapp'],
        ['Latecomer Notice',                     '(Latecomer Notice) Dear parents! Your child was late for school in the morning, please send your child according to school time. From: Principal', 'whatsapp'],
        ['Parent Teacher Meeting',               'Dear parents, a parent-teacher meeting is scheduled at school. Kindly ensure attendance. Regards, School Administration', 'whatsapp'],
        ['Independence Day',                     'This message is for the Independence Day celebrations at school. Kindly join us. Regards, School Administration', 'whatsapp'],
    ];
    $seedStmt = db_prepare("INSERT INTO sms_templates (title, body, channel) VALUES (?, ?, ?)");
    if ($seedStmt) {
        foreach ($seedTemplates as $seed) {
            $seedStmt->bind_param('sss', $seed[0], $seed[1], $seed[2]);
            $seedStmt->execute();
        }
    }
}