<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Academic Setup';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'AddClass') {
        $name = trim($_POST['class_name'] ?? '');
        if ($name === '') {
            $error = 'Course/Class Name is required.';
        } else {
            try {
                $st2 = db_prepare("INSERT INTO classes (class_name) VALUES (?)");
                $st2->bind_param('s', $name);
                $st2->execute();
                $message = "Class '$name' added!";
            } catch (Throwable $e) {
                $error = 'Add class failed: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'AddSection') {
        $class_id = (int) ($_POST['class_id'] ?? 0);
        $sec = trim($_POST['section_name'] ?? '');
        if ($class_id <= 0 || $sec === '') {
            $error = 'Class and section name are required.';
        } else {
            try {
                $st2 = db_prepare("INSERT INTO sections (class_id, section_name) VALUES (?, ?)");
                $st2->bind_param('is', $class_id, $sec);
                $st2->execute();
                $message = "Section '$sec' added!";
            } catch (Throwable $e) {
                $error = 'Add section failed: ' . $e->getMessage();
            }
        }
    }
}

$classes = [];
try {
    $res = db_query("SELECT * FROM classes ORDER BY class_id");
    while ($row = $res->fetch_assoc()) {
        $sections = [];
        $res2 = db_query("SELECT * FROM sections WHERE class_id={$row['class_id']} ORDER BY section_id");
        while ($s = $res2->fetch_assoc()) { $sections[] = $s; }
        $subjects_count = (int) (db_query("SELECT COUNT(*) c FROM subjects WHERE class_id={$row['class_id']}")->fetch_assoc()['c'] ?? 0);
        $students_count = (int) (db_query("SELECT COUNT(*) c FROM students WHERE class_id={$row['class_id']} AND status=1")->fetch_assoc()['c'] ?? 0);
        $row['sections'] = $sections;
        $row['subjects_count'] = $subjects_count;
        $row['students_count'] = $students_count;
        $classes[] = $row;
    }
} catch (Throwable $e) { $classes = []; }

$cards = [
    ['url' => 'manage_exams.php', 'icon' => 'fa fa-plus', 'title' => 'Manage Exams', 'desc' => 'Create exam terms and types for the current session.'],
    ['url' => 'subjects.php', 'icon' => 'fa fa-book', 'title' => 'Manage Subjects', 'desc' => 'Add and organize subjects offered by the school.'],
    ['url' => 'class_subjects.php', 'icon' => 'fa fa-layer-group', 'title' => 'Course/Class Subjects', 'desc' => 'Assign subjects to classes and sections.'],
    ['url' => 'teacher_subjects_allocation.php', 'icon' => 'fa fa-chalkboard-teacher', 'title' => 'Teacher Subjects', 'desc' => 'Allocate subjects to teachers.'],
    ['url' => 'create_awardList.php', 'icon' => 'fa fa-list', 'title' => 'Award List', 'desc' => 'Configure award lists and merit rules.'],
    ['url' => 'grades_marks.php', 'icon' => 'fa fa-star', 'title' => 'Grade Settings', 'desc' => 'Define grade scales and mark ranges.'],
    ['url' => 'upload_signature.php', 'icon' => 'fa fa-signature', 'title' => 'Academic Settings', 'desc' => 'Upload signatures and configure academic settings.'],
    ['url' => 'manage_classes.php', 'icon' => 'fa fa-users', 'title' => 'Class & Sections', 'desc' => 'Manage classes and sections for the branch.'],
];

include __DIR__ . '/includes/header.php';
?>
<style>
.page-title {
    background: #2b2b36;
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 16px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.page-title h3 {
    color: #fff;
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}
.page-subtitle {
    color: #fff;
    font-size: 12px;
    opacity: 0.9;
}
.setup-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
a.aqib-card {
    background: #fff;
    padding: 14px 16px;
    border-radius: 10px;
    border: 1px solid #E5E7EB;
    box-shadow: 0 2px 8px rgba(16,24,40,0.06);
    text-decoration: none;
    color: #111;
    display: block;
    transition: all 0.2s ease;
}
a.aqib-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(16,24,40,0.10);
    text-decoration: none;
}
.setup-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    color: #fff;
    background: #ff9800;
}
.setup-title {
    font-weight: 700;
    margin-bottom: 6px;
    font-size: 14px;
}
.setup-desc {
    font-size: 12px;
    color: #6b7280;
    line-height: 1.3;
}
.crumb { font-size:13px; color:#6B7280; margin:6px 4px 14px; }
.crumb a { color:#e67e22; text-decoration:none; }
.crumb a:hover { text-decoration:underline; }
.ac-card { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:14px; }
.ac-head { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="crumb"><a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; <a href="<?php echo BASE_URL; ?>academic_setup.php">Academics</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; Academic Setup</div>

        <div class="page-title">
            <h3><i class="fa fa-university"></i> Academic Setup</h3>
            <div class="page-subtitle">All academic configuration pages in one place for new &amp; existing schools.</div>
        </div>

        <?php $academic_tabs_container_style = 'margin:0 0 18px;'; include __DIR__ . '/includes/academic_tabs.php'; ?>

        <div class="setup-grid">
            <?php foreach ($cards as $card): ?>
                <a class="aqib-card" href="<?php echo BASE_URL . $card['url']; ?>">
                    <div class="setup-icon"><i class="<?php echo $card['icon']; ?>"></i></div>
                    <div class="setup-title"><?php echo e($card['title']); ?></div>
                    <div class="setup-desc"><?php echo e($card['desc']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <h4 style="font-size:16px; font-weight:800; color:#111827; margin:0 0 12px;"><i class="fa fa-users"></i> Course/Class &amp; Sections</h4>

        <div class="row" style="margin-bottom:16px;">
            <div class="col-md-5">
                <form method="post" action="academic_setup.php" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 12px;">Add Class</h4>
                    <input type="hidden" name="action" value="AddClass">
                    <div class="form-group"><input type="text" name="class_name" class="form-control" placeholder="e.g. MIT, BSCS, BBA" required></div>
                    <button class="btn btn-success" style="width:100%;"><i class="fa fa-plus"></i> Add Class</button>
                </form>
            </div>
            <div class="col-md-7">
                <form method="post" action="academic_setup.php" style="background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:16px;">
                    <h4 style="font-size:15px; font-weight:800; margin:0 0 12px;">Add Section</h4>
                    <input type="hidden" name="action" value="AddSection">
                    <div class="row">
                        <div class="form-group col-md-5">
                            <select name="class_id" class="form-control" required>
                                <option value="">Select Course/Class</option>
                                <?php foreach ($classes as $c): ?><option value="<?php echo $c['class_id']; ?>"><?php echo e($c['class_name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4"><input type="text" name="section_name" class="form-control" placeholder="e.g. A, B" required></div>
                        <div class="form-group col-md-3"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-plus"></i> Add</button></div>
                    </div>
                </form>
            </div>
        </div>

        <?php foreach ($classes as $c): ?>
            <div class="ac-card">
                <div class="ac-head">
                    <div>
                        <strong style="font-size:15px; color:#111827;"><?php echo e($c['class_name']); ?></strong>
                        <span class="status-badge status-present" style="margin-left:8px;"><?php echo $c['students_count']; ?> students</span>
                        <span class="status-badge" style="background:#E0E7FF; color:#4338CA; margin-left:6px;"><?php echo $c['subjects_count']; ?> subjects</span>
                    </div>
                    <div style="color:#6B7280; font-size:12.5px;">
                        Sections:
                        <?php if (count($c['sections']) === 0): ?>-<?php endif; ?>
                        <?php foreach ($c['sections'] as $s): ?><span class="status-badge" style="background:#F3F4F6; color:#374151;"><?php echo e($s['section_name']); ?></span> <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (count($classes) === 0): ?>
            <div style="text-align:center; color:#6B7280; padding:40px;">No classes yet. Use the form above to add one.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>