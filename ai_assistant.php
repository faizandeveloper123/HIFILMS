<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ensure_schema.php';
require_login();

$page_title = 'AI Assistant';

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'admin';

// Ensure ai_chat_logs table exists
_hiifi_try_db("CREATE TABLE IF NOT EXISTS ai_chat_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    user_role VARCHAR(30) DEFAULT NULL,
    message TEXT NOT NULL,
    response TEXT,
    intent VARCHAR(50) DEFAULT NULL,
    language VARCHAR(10) DEFAULT 'en',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Handle AJAX chat requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $user_msg = trim($input['message'] ?? $_POST['message'] ?? '');

    if ($user_msg === '') {
        echo json_encode(['success' => false, 'response' => 'Please type a question.']);
        exit;
    }

    // Detect language (Urdu characters)
    $is_urdu = preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $user_msg);
    $lang = $is_urdu ? 'ur' : 'en';
    $lower = mb_strtolower($user_msg, 'UTF-8');

    // Intent detection
    $intent = 'unknown';
    $response = '';

    // Student count by class
    if (preg_match('/how many students?\s*(in|are in|are there in)?\s*(class\s*)?(\w+)/i', $user_msg, $m) ||
        preg_match('/(\w+)\s*(class|grade)\s* students?/i', $user_msg, $m) ||
        preg_match('/students? count.*?(\w+)/i', $user_msg, $m)) {
        $intent = 'student_count';
        $class_name = trim($m[count($m)-1]);
        $st = db_prepare("SELECT COUNT(*) c FROM students s JOIN classes c ON c.class_id = s.class_id WHERE c.class_name = ? AND s.status = 1");
        $st->bind_param('s', $class_name);
        $st->execute();
        $count = (int) $st->get_result()->fetch_assoc()['c'];
        $response = $lang === 'ur'
            ? "Class {$class_name} mein {$count} students hain."
            : "There are {$count} students in class {$class_name}.";
    }
    // Total students
    elseif (preg_match('/total students|students total|kitne students|total kitne/i', $lower)) {
        $intent = 'total_students';
        $r = db_query("SELECT COUNT(*) c FROM students WHERE status=1");
        $count = (int) $r->fetch_assoc()['c'];
        $response = $lang === 'ur'
            ? "School mein total {$count} students hain."
            : "Total students in school: {$count}.";
    }
    // Fee collection this month
    elseif (preg_match('/fee collection|fee collected|fee this month|is month.*?fee|fee.*?kitna/i', $lower)) {
        $intent = 'fee_collection';
        $month = date('Y-m');
        $r = db_query("SELECT COALESCE(SUM(fp.amount),0) total FROM fee_payments fp WHERE DATE_FORMAT(fp.created_at, '%Y-%m') = '$month'");
        $total = (float) $r->fetch_assoc()['total'];
        $response = $lang === 'ur'
            ? "Is mahiney ki fee collection: Rs. " . number_format($total, 0) . "."
            : "Fee collection this month: Rs. " . number_format($total, 0) . ".";
    }
    // Total fee collected
    elseif (preg_match('/total fee collected|total fee|total.*?fee/i', $lower)) {
        $intent = 'total_fee';
        $r = db_query("SELECT COALESCE(SUM(amount),0) total FROM fee_payments");
        $total = (float) $r->fetch_assoc()['total'];
        $response = $lang === 'ur'
            ? "Total fee collected: Rs. " . number_format($total, 0) . "."
            : "Total fee collected: Rs. " . number_format($total, 0) . ".";
    }
    // Attendance today
    elseif (preg_match('/attendance.*?today|aaj.*?attendance|today.*?attendance|attendance summary/i', $lower)) {
        $intent = 'attendance_today';
        $today = date('Y-m-d');
        $r = db_query("SELECT status, COUNT(*) c FROM attendance WHERE date = '$today' GROUP BY status");
        $data = [];
        while ($row = $r->fetch_assoc()) { $data[$row['status']] = (int) $row['c']; }
        if (empty($data)) {
            $response = $lang === 'ur' ? "Aaj koi attendance record nahi hai." : "No attendance recorded for today.";
        } else {
            $parts = [];
            foreach ($data as $s => $c) { $parts[] = ucfirst($s) . ": $c"; }
            $response = $lang === 'ur'
                ? "Aaj ki attendance: " . implode(', ', $parts) . "."
                : "Today's attendance: " . implode(', ', $parts) . ".";
        }
    }
    // Attendance of specific class
    elseif (preg_match('/attendance.*?class\s*(\w+)|class\s*(\w+).*?attendance/i', $user_msg, $m)) {
        $intent = 'attendance_class';
        $class_name = trim($m[1] ?? $m[2] ?? '');
        $today = date('Y-m-d');
        $st = db_prepare("SELECT a.status, COUNT(*) c FROM attendance a
                           JOIN students s ON s.student_id = a.student_id
                           JOIN classes c ON c.class_id = s.class_id
                           WHERE c.class_name = ? AND a.date = ? GROUP BY a.status");
        $st->bind_param('ss', $class_name, $today);
        $st->execute();
        $r = $st->get_result();
        $data = [];
        while ($row = $r->fetch_assoc()) { $data[$row['status']] = (int) $row['c']; }
        if (empty($data)) {
            $response = $lang === 'ur' ? "Class {$class_name} ki aaj koi attendance nahi hai." : "No attendance found for class {$class_name} today.";
        } else {
            $parts = [];
            foreach ($data as $s => $c) { $parts[] = ucfirst($s) . ": $c"; }
            $response = $lang === 'ur'
                ? "Class {$class_name} ki attendance: " . implode(', ', $parts) . "."
                : "Class {$class_name} attendance: " . implode(', ', $parts) . ".";
        }
    }
    // Defaulters
    elseif (preg_match('/defaulter|pending fee|unpaid|baki|dues/i', $lower)) {
        $intent = 'fee_defaulters';
        $r = db_query("SELECT s.first_name, s.father_name, fc.total_amount - fc.paid_amount AS due, c.class_name
                        FROM fee_challans fc
                        JOIN students s ON s.student_id = fc.student_id
                        JOIN classes c ON c.class_id = fc.class_id
                        WHERE fc.status IN ('unpaid','partial') AND s.status = 1
                        ORDER BY due DESC LIMIT 10");
        $rows = [];
        while ($row = $r->fetch_assoc()) { $rows[] = $row; }
        if (empty($rows)) {
            $response = $lang === 'ur' ? "Koi fee defaulter nahi hai. Sab ka fee paid hai." : "No fee defaulters found. Everyone has paid.";
        } else {
            $lines = [];
            foreach ($rows as $row) {
                $lines[] = "- {$row['first_name']} ({$row['father_name']}) - Class {$row['class_name']} - Rs. " . number_format($row['due'], 0);
            }
            $header = $lang === 'ur' ? "Top fee defaulters:" : "Top fee defaulters:";
            $response = $header . "\n" . implode("\n", $lines);
        }
    }
    // Total teachers/employees
    elseif (preg_match('/how many teachers|teachers? count|total teachers|kitne teacher|employees? total|total employees/i', $lower)) {
        $intent = 'employee_count';
        $r = db_query("SELECT COUNT(*) c FROM employees WHERE status=1");
        $count = (int) $r->fetch_assoc()['c'];
        $response = $lang === 'ur'
            ? "School mein {$count} employees hain."
            : "Total employees in school: {$count}.";
    }
    // Class list
    elseif (preg_match('/list.*?class|all classes|class.*?list|kitni classes/i', $lower)) {
        $intent = 'class_list';
        $r = db_query("SELECT class_name FROM classes WHERE status=1 ORDER BY class_id");
        $names = [];
        while ($row = $r->fetch_assoc()) { $names[] = $row['class_name']; }
        $response = $lang === 'ur'
            ? "Classes: " . implode(', ', $names) . "."
            : "Classes: " . implode(', ', $names) . ".";
    }
    // Employees by designation
    elseif (preg_match('/employees?.*?(\w+)|(\w+).*?employees?|teachers? of|staff/i', $lower)) {
        $intent = 'employee_list';
        $r = db_query("SELECT first_name, last_name, designation FROM employees WHERE status=1 ORDER BY first_name LIMIT 15");
        $rows = [];
        while ($row = $r->fetch_assoc()) { $rows[] = $row; }
        if (empty($rows)) {
            $response = $lang === 'ur' ? "Koi employees nahi milay." : "No employees found.";
        } else {
            $lines = [];
            foreach ($rows as $row) {
                $lines[] = "- {$row['first_name']} {$row['last_name']} (" . ($row['designation'] ?? 'N/A') . ")";
            }
            $response = ($lang === 'ur' ? "Employees list:" : "Employees list:") . "\n" . implode("\n", $lines);
        }
    }
    // Monthly expenses
    elseif (preg_match('/expense|expenses.*?month|kharcha|month.*?expense/i', $lower)) {
        $intent = 'monthly_expenses';
        $month = date('Y-m');
        $r = db_query("SELECT COALESCE(SUM(amount),0) total FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = '$month'");
        $total = (float) $r->fetch_assoc()['total'];
        $response = $lang === 'ur'
            ? "Is mahiney ka total kharcha: Rs. " . number_format($total, 0) . "."
            : "Total expenses this month: Rs. " . number_format($total, 0) . ".";
    }
    // Today date
    elseif (preg_match('/today|date|aaj|din|what day/i', $lower)) {
        $intent = 'today_date';
        $response = $lang === 'ur'
            ? "Aaj " . date('l, d M Y') . " hai."
            : "Today is " . date('l, d M Y') . ".";
    }
    // Help / unknown
    else {
        $intent = 'help';
        $response = $lang === 'ur'
            ? "Mein aapki madad kar sakta hoon. Ye puch sakte hain:\n- Kitne students hain class X mein?\n- Fee collection kitni hai is month?\n- Aaj ki attendance dikhao\n- Fee defaulters kaun hain?\n- Kitne teachers hain?\n- Total students kitne hain?"
            : "I can help you with school data. You can ask:\n- How many students are in class X?\n- What is the fee collection this month?\n- Show me attendance for today\n- Who are the defaulters?\n- How many teachers are there?\n- List all classes?";
    }

    // Store in chat log
    $ins = db_prepare("INSERT INTO ai_chat_logs (user_id, user_role, message, response, intent, language) VALUES (?, ?, ?, ?, ?, ?)");
    $ins->bind_param('isssss', $user_id, $user_role, $user_msg, $response, $intent, $lang);
    $ins->execute();

    echo json_encode(['success' => true, 'response' => $response, 'intent' => $intent, 'language' => $lang]);
    exit;
}

// Load recent chat history
$chat_history = [];
$hr = db_prepare("SELECT message, response, intent, language, created_at FROM ai_chat_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
$hr->bind_param('i', $user_id);
$hr->execute();
$hr_res = $hr->get_result();
while ($row = $hr_res->fetch_assoc()) {
    $chat_history[] = $row;
}
$chat_history = array_reverse($chat_history);

include __DIR__ . '/includes/header.php';
?>
<style>
.ai-chat-wrap{display:flex;flex-direction:column;height:calc(100vh - 140px);max-height:calc(100vh - 140px);background:#fff;border:1px solid #E5E7EB;border-radius:16px;overflow:hidden;}
.ai-chat-header{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;padding:16px 20px;display:flex;align-items:center;gap:12px;flex-shrink:0;}
.ai-chat-header .ai-avatar{width:42px;height:42px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:20px;}
.ai-chat-header .ai-info{flex:1;}
.ai-chat-header .ai-info h4{margin:0;font-size:16px;font-weight:700;}
.ai-chat-header .ai-info p{margin:2px 0 0;font-size:12px;opacity:0.85;}
.ai-quick-chips{padding:10px 16px;display:flex;gap:6px;flex-wrap:wrap;background:#F9FAFB;border-bottom:1px solid #E5E7EB;flex-shrink:0;}
.ai-chip{padding:6px 14px;border-radius:20px;border:1px solid #E5E7EB;background:#fff;color:#374151;font-size:12px;cursor:pointer;transition:all 0.2s;white-space:nowrap;}
.ai-chip:hover{background:#f97316;color:#fff;border-color:#f97316;}
.ai-chat-body{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;}
.ai-msg{max-width:75%;padding:12px 16px;border-radius:16px;font-size:13.5px;line-height:1.5;white-space:pre-wrap;animation:msgFade 0.3s ease;}
.ai-msg.user{align-self:flex-end;background:#f97316;color:#fff;border-bottom-right-radius:4px;}
.ai-msg.bot{align-self:flex-start;background:#F3F4F6;color:#111827;border-bottom-left-radius:4px;}
.ai-msg .msg-time{display:block;font-size:10px;margin-top:4px;opacity:0.6;}
.ai-typing{display:flex;gap:4px;padding:12px 18px;align-self:flex-start;background:#F3F4F6;border-radius:16px;border-bottom-left-radius:4px;}
.ai-typing span{width:8px;height:8px;border-radius:50%;background:#9CA3AF;animation:bounce 1.4s infinite ease-in-out;}
.ai-typing span:nth-child(1){animation-delay:0s;}
.ai-typing span:nth-child(2){animation-delay:0.2s;}
.ai-typing span:nth-child(3){animation-delay:0.4s;}
@keyframes bounce{0%,80%,100%{transform:scale(0.6);opacity:0.4;}40%{transform:scale(1);opacity:1;}}
@keyframes msgFade{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
.ai-chat-input{padding:12px 16px;border-top:1px solid #E5E7EB;display:flex;gap:8px;align-items:center;background:#fff;flex-shrink:0;}
.ai-chat-input input{flex:1;padding:12px 16px;border:1px solid #E5E7EB;border-radius:24px;font-size:14px;outline:none;transition:border-color 0.2s;}
.ai-chat-input input:focus{border-color:#f97316;}
.ai-chat-input button{width:44px;height:44px;border-radius:50%;background:#f97316;border:none;color:#fff;font-size:18px;cursor:pointer;transition:all 0.2s;flex-shrink:0;}
.ai-chat-input button:hover{background:#ea580c;}
.ai-chat-input button:disabled{background:#D1D5DB;cursor:not-allowed;}
</style>
<div class="main-content">
    <div class="container-fluid">
        <div class="ai-chat-wrap">
            <div class="ai-chat-header">
                <div class="ai-avatar"><i class="fa fa-robot"></i></div>
                <div class="ai-info">
                    <h4>HIIFI AI Assistant</h4>
                    <p>School Data Assistant - Ask me anything about your school</p>
                </div>
            </div>

            <div class="ai-quick-chips">
                <div class="ai-chip" onclick="sendQuick(this)">📊 Total students</div>
                <div class="ai-chip" onclick="sendQuick(this)">💰 Fee collection this month</div>
                <div class="ai-chip" onclick="sendQuick(this)">📋 Attendance today</div>
                <div class="ai-chip" onclick="sendQuick(this)">⚠️ Fee defaulters</div>
                <div class="ai-chip" onclick="sendQuick(this)">👨‍🏫 How many teachers</div>
                <div class="ai-chip" onclick="sendQuick(this)">📚 List all classes</div>
                <div class="ai-chip" onclick="sendQuick(this)">📅 Today's date</div>
                <div class="ai-chip" onclick="sendQuick(this)">💸 Monthly expenses</div>
            </div>

            <div class="ai-chat-body" id="chatBody">
                <div class="ai-msg bot">
                    Assalam-o-Alaikum! Mein HIIFI AI Assistant hoon. Aap mujh se school data ke baray mein kuch bhi pooch sakte hain. English ya Roman Urdu mein poochain.
                    <span class="msg-time"><?php echo date('h:i A'); ?></span>
                </div>
                <?php foreach ($chat_history as $ch): ?>
                    <div class="ai-msg user"><?php echo e($ch['message']); ?><span class="msg-time"><?php echo date('h:i A', strtotime($ch['created_at'])); ?></span></div>
                    <div class="ai-msg bot"><?php echo e($ch['response']); ?><span class="msg-time"><?php echo date('h:i A', strtotime($ch['created_at'])); ?></span></div>
                <?php endforeach; ?>
            </div>

            <div class="ai-chat-input">
                <input type="text" id="aiInput" placeholder="Type your question... (English / Roman Urdu)" onkeydown="if(event.key==='Enter'){sendAI();}" autocomplete="off">
                <button id="aiSendBtn" onclick="sendAI()"><i class="fa fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
var chatBody = document.getElementById('chatBody');
var aiInput = document.getElementById('aiInput');
var sendBtn = document.getElementById('aiSendBtn');

function scrollToBottom() {
    chatBody.scrollTop = chatBody.scrollHeight;
}
scrollToBottom();

function sendQuick(el) {
    var text = el.textContent.replace(/^[^\s]+\s/, '').trim();
    aiInput.value = text;
    sendAI();
}

function sendAI() {
    var msg = aiInput.value.trim();
    if (!msg) return;

    // User message
    var userDiv = document.createElement('div');
    userDiv.className = 'ai-msg user';
    userDiv.innerHTML = escapeHtml(msg) + '<span class="msg-time">' + new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) + '</span>';
    chatBody.appendChild(userDiv);
    aiInput.value = '';
    scrollToBottom();

    // Typing indicator
    var typingDiv = document.createElement('div');
    typingDiv.className = 'ai-typing';
    typingDiv.id = 'aiTyping';
    typingDiv.innerHTML = '<span></span><span></span><span></span>';
    chatBody.appendChild(typingDiv);
    scrollToBottom();

    sendBtn.disabled = true;

    fetch('<?php echo BASE_URL; ?>ai_assistant.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({message: msg})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var typing = document.getElementById('aiTyping');
        if (typing) typing.remove();

        var botDiv = document.createElement('div');
        botDiv.className = 'ai-msg bot';
        var respText = data.response || 'Sorry, I could not understand that.';
        botDiv.innerHTML = escapeHtml(respText) + '<span class="msg-time">' + new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) + '</span>';
        chatBody.appendChild(botDiv);
        scrollToBottom();
        sendBtn.disabled = false;
        aiInput.focus();
    })
    .catch(function() {
        var typing = document.getElementById('aiTyping');
        if (typing) typing.remove();

        var botDiv = document.createElement('div');
        botDiv.className = 'ai-msg bot';
        botDiv.innerHTML = 'Sorry, something went wrong. Please try again.<span class="msg-time">' + new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) + '</span>';
        chatBody.appendChild(botDiv);
        scrollToBottom();
        sendBtn.disabled = false;
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
