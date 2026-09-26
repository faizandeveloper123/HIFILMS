<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/includes/period_schema.php';
require_once __DIR__ . '/includes/campus.php';

$page_title = 'Create Weekly Timetable';
$message = '';
$error = '';
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$campuses = get_campuses();
$campusMap = [];
foreach ($campuses as $campus) {
    $campusMap[(int) ($campus['campus_id'] ?? 0)] = $campus;
}

$classes = [];
$classMap = [];
$classLoadError = '';
try {
    $res = db_query("SELECT class_id, class_name FROM classes WHERE status = 1 ORDER BY class_name");
    while ($row = $res->fetch_assoc()) {
        $classes[] = $row;
        $classMap[(int) $row['class_id']] = $row;
    }
} catch (Throwable $e) {
    $classLoadError = 'Active classes could not be loaded.';
}

$periods = [];
$periodLoadError = '';
try {
    $res = db_query("SELECT p.*, pc.name AS category_name
                     FROM periods p
                     LEFT JOIN period_categories pc ON pc.id = p.category_id
                     ORDER BY p.start_time, p.period_id");
    while ($row = $res->fetch_assoc()) {
        $periods[] = $row;
    }
} catch (Throwable $e) {
    $periodLoadError = 'Periods could not be loaded.';
}

$previewPeriods = [
    ['period_id' => 0, 'period_name' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '08:40:00', 'category_name' => '', 'is_break' => false],
    ['period_id' => 0, 'period_name' => 'Period 2', 'start_time' => '08:40:00', 'end_time' => '09:20:00', 'category_name' => '', 'is_break' => false],
    ['period_id' => 0, 'period_name' => 'Period 3', 'start_time' => '09:20:00', 'end_time' => '10:00:00', 'category_name' => '', 'is_break' => false],
    ['period_id' => 0, 'period_name' => 'Period 4', 'start_time' => '10:00:00', 'end_time' => '10:40:00', 'category_name' => '', 'is_break' => false],
    ['period_id' => 0, 'period_name' => 'Break', 'start_time' => '10:40:00', 'end_time' => '11:00:00', 'category_name' => '', 'is_break' => true],
    ['period_id' => 0, 'period_name' => 'Period 5', 'start_time' => '11:00:00', 'end_time' => '11:40:00', 'category_name' => '', 'is_break' => false],
    ['period_id' => 0, 'period_name' => 'Period 6', 'start_time' => '11:40:00', 'end_time' => '12:20:00', 'category_name' => '', 'is_break' => false],
    ['period_id' => 0, 'period_name' => 'Period 7', 'start_time' => '12:20:00', 'end_time' => '13:00:00', 'category_name' => '', 'is_break' => false],
];
$displayPeriods = $periods;
if (count($periods) < count($previewPeriods)) {
    $displayPeriods = $previewPeriods;
    $teachingSlots = [];
    $breakSlot = null;
    foreach ($previewPeriods as $previewIndex => $previewPeriod) {
        if (!empty($previewPeriod['is_break'])) {
            $breakSlot = $previewIndex;
        } else {
            $teachingSlots[] = $previewIndex;
        }
    }
    $realTeachingIndex = 0;
    foreach ($periods as $period) {
        $periodName = strtolower((string) ($period['period_name'] ?? ''));
        $categoryName = strtolower((string) ($period['category_name'] ?? ''));
        $isRealBreak = false;
        foreach (['break', 'lunch', 'recess', 'tea'] as $breakHint) {
            if (strpos($periodName, $breakHint) !== false || strpos($categoryName, $breakHint) !== false) {
                $isRealBreak = true;
                break;
            }
        }
        if ($isRealBreak && $breakSlot !== null) {
            $displayPeriods[$breakSlot] = array_merge($period, ['is_break' => true]);
        } elseif (!$isRealBreak && isset($teachingSlots[$realTeachingIndex])) {
            $displayPeriods[$teachingSlots[$realTeachingIndex]] = array_merge($period, ['is_break' => false]);
            $realTeachingIndex++;
        }
    }
}

$employees = [];
$employeeMap = [];
$employeeLoadError = '';
try {
    $res = db_query("SELECT emp_id, first_name, last_name, designation
                     FROM employees
                     WHERE status = 1
                     ORDER BY first_name, last_name, emp_id");
    while ($row = $res->fetch_assoc()) {
        $employeeId = (int) ($row['emp_id'] ?? 0);
        $employeeName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        if ($employeeName === '') {
            $employeeName = 'Employee #' . $employeeId;
        }
        $employeeLabel = $employeeName;
        if (trim((string) ($row['designation'] ?? '')) !== '') {
            $employeeLabel .= ' (' . trim((string) $row['designation']) . ')';
        }
        $employees[] = ['emp_id' => $employeeId, 'label' => $employeeLabel];
        if ($employeeId > 0) {
            $employeeMap[$employeeId] = $employeeLabel;
        }
    }
} catch (Throwable $e) {
    $employeeLoadError = 'Active employees could not be loaded.';
}

try {
    $teacherColumn = db_query("SHOW COLUMNS FROM timetable LIKE 'teacher_id'");
    if ($teacherColumn && !$teacherColumn->fetch_assoc()) {
        db_query("ALTER TABLE timetable ADD COLUMN teacher_id INT DEFAULT NULL");
    }
} catch (Throwable $e) {
}

$formatTime = static function ($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('g:i', $timestamp);
};

$periodMap = [];
$breakPeriodMap = [];
foreach ($periods as $period) {
    $periodId = (int) ($period['period_id'] ?? 0);
    if ($periodId <= 0) {
        continue;
    }
    $periodMap[$periodId] = $period;
    $periodName = trim((string) ($period['period_name'] ?? ''));
    $categoryName = trim((string) ($period['category_name'] ?? ''));
    foreach (['break', 'lunch', 'recess', 'tea'] as $breakHint) {
        if (stripos($periodName, $breakHint) !== false || stripos($categoryName, $breakHint) !== false) {
            $breakPeriodMap[$periodId] = true;
            break;
        }
    }
}

$isPost = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST';
$actionRaw = $_POST['action'] ?? '';
$isSaveRequest = $isPost && is_scalar($actionRaw) && (string) $actionRaw === 'SaveClassTimetable';
$getCampusRaw = $_GET['campus_id'] ?? 0;
$getClassRaw = $_GET['class_id'] ?? 0;
$getSectionRaw = $_GET['section_id'] ?? 0;
$requestCampusId = $isSaveRequest && is_scalar($_POST['campus_id'] ?? 0) ? (int) $_POST['campus_id'] : (is_scalar($getCampusRaw) ? (int) $getCampusRaw : 0);
$requestClassId = $isSaveRequest && is_scalar($_POST['class_id'] ?? 0) ? (int) $_POST['class_id'] : (is_scalar($getClassRaw) ? (int) $getClassRaw : 0);
$requestSectionId = $isSaveRequest && is_scalar($_POST['section_id'] ?? 0) ? (int) $_POST['section_id'] : (is_scalar($getSectionRaw) ? (int) $getSectionRaw : 0);

$selectedCampusId = isset($campusMap[$requestCampusId]) ? $requestCampusId : 0;
if ($selectedCampusId <= 0) {
    $activeCampus = get_active_campus();
    $activeCampusId = (int) ($activeCampus['campus_id'] ?? 0);
    if (isset($campusMap[$activeCampusId])) {
        $selectedCampusId = $activeCampusId;
    }
}
if ($selectedCampusId <= 0 && count($campuses) > 0) {
    $selectedCampusId = (int) $campuses[0]['campus_id'];
}

$selectedClassId = isset($classMap[$requestClassId]) ? $requestClassId : 0;
if ($selectedClassId <= 0 && count($classes) > 0) {
    $selectedClassId = (int) $classes[0]['class_id'];
}

$sections = [];
$sectionMap = [];
if ($selectedClassId > 0) {
    try {
        $sectionStmt = db_prepare("SELECT section_id, section_name FROM sections WHERE class_id = ? ORDER BY section_name");
        $sectionStmt->bind_param('i', $selectedClassId);
        $sectionStmt->execute();
        $sectionResult = $sectionStmt->get_result();
        if ($sectionResult) {
            while ($row = $sectionResult->fetch_assoc()) {
                $sections[] = $row;
                $sectionMap[(int) $row['section_id']] = $row;
            }
        }
    } catch (Throwable $e) {
    }
}

$selectedSectionId = isset($sectionMap[$requestSectionId]) ? $requestSectionId : 0;
if ($selectedSectionId <= 0 && count($sections) > 0) {
    $selectedSectionId = (int) $sections[0]['section_id'];
}
$selectedClassName = (string) ($classMap[$selectedClassId]['class_name'] ?? '');
$selectedSectionName = (string) ($sectionMap[$selectedSectionId]['section_name'] ?? '');

$subjects = [];
$subjectLoadError = '';
if ($selectedClassId > 0 && $selectedSectionId > 0) {
    try {
        $subjectStmt = db_prepare("SELECT DISTINCT s.subject_id, s.subject_name, cs.sort_order
                                   FROM subjects s
                                   INNER JOIN class_subjects cs ON cs.subject_id = s.subject_id
                                   WHERE cs.class_id = ? AND cs.section_id = ?
                                   ORDER BY cs.sort_order ASC, s.subject_name ASC, s.subject_id ASC");
        $subjectStmt->bind_param('ii', $selectedClassId, $selectedSectionId);
        $subjectStmt->execute();
        $subjectResult = $subjectStmt->get_result();
        if ($subjectResult) {
            while ($row = $subjectResult->fetch_assoc()) {
                $subjects[] = $row;
            }
        }
    } catch (Throwable $e) {
    }
    if (count($subjects) === 0) {
        try {
            $subjectStmt = db_prepare("SELECT s.subject_id, s.subject_name FROM subjects s WHERE s.class_id = ? ORDER BY s.subject_name, s.subject_id");
            $subjectStmt->bind_param('i', $selectedClassId);
            $subjectStmt->execute();
            $subjectResult = $subjectStmt->get_result();
            if ($subjectResult) {
                while ($row = $subjectResult->fetch_assoc()) {
                    $subjects[] = $row;
                }
            }
        } catch (Throwable $e) {
            $subjectLoadError = 'Subjects could not be loaded.';
        }
    }
}

$allowedSubjectIds = [];
foreach ($subjects as $subject) {
    $subjectId = (int) ($subject['subject_id'] ?? 0);
    if ($subjectId > 0) {
        $allowedSubjectIds[$subjectId] = true;
    }
}

$schedule = [];
$scheduleLoadError = '';
$loadSchedule = static function () use (&$schedule, &$scheduleLoadError, $selectedClassId, $selectedSectionId, $days, $periodMap) {
    if ($selectedClassId <= 0 || $selectedSectionId <= 0) {
        return;
    }
    try {
        $scheduleStmt = db_prepare("SELECT timetable_id, day, period_id, subject_id, teacher_id FROM timetable WHERE class_id = ? AND section_id = ? ORDER BY timetable_id");
        $scheduleStmt->bind_param('ii', $selectedClassId, $selectedSectionId);
        $scheduleStmt->execute();
        $scheduleResult = $scheduleStmt->get_result();
        if ($scheduleResult) {
            while ($row = $scheduleResult->fetch_assoc()) {
                $day = (string) ($row['day'] ?? '');
                $periodId = (int) ($row['period_id'] ?? 0);
                if (in_array($day, $days, true) && isset($periodMap[$periodId])) {
                    $schedule[$day][$periodId] = [
                        'subject_id' => (int) ($row['subject_id'] ?? 0),
                        'teacher_id' => (int) ($row['teacher_id'] ?? 0),
                    ];
                }
            }
        }
        $scheduleLoadError = '';
    } catch (Throwable $e) {
        $scheduleLoadError = 'Existing timetable entries could not be loaded.';
    }
};
$loadSchedule();

if (empty($_SESSION['class_period_selection_csrf_token']) || !is_string($_SESSION['class_period_selection_csrf_token'])) {
    $_SESSION['class_period_selection_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['class_period_selection_csrf_token'];

if ($isSaveRequest) {
    $validationErrors = [];
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $validationErrors[] = 'Your session token expired. Please try again.';
    }

    $postedCampusRaw = $_POST['campus_id'] ?? 0;
    $postedClassRaw = $_POST['class_id'] ?? 0;
    $postedSectionRaw = $_POST['section_id'] ?? 0;
    $postedCampusId = is_scalar($postedCampusRaw) ? (int) $postedCampusRaw : 0;
    $postedClassId = is_scalar($postedClassRaw) ? (int) $postedClassRaw : 0;
    $postedSectionId = is_scalar($postedSectionRaw) ? (int) $postedSectionRaw : 0;
    if (!isset($campusMap[$postedCampusId])) {
        $validationErrors[] = 'Please select a valid campus.';
    }
    if ($postedClassId <= 0) {
        $validationErrors[] = 'Please select a valid class.';
    } else {
        try {
            $classCheck = db_prepare("SELECT class_id FROM classes WHERE class_id = ? AND status = 1");
            $classCheck->bind_param('i', $postedClassId);
            $classCheck->execute();
            $classCheckResult = $classCheck->get_result();
            if (!$classCheckResult || !$classCheckResult->fetch_assoc()) {
                $validationErrors[] = 'Please select a valid active class.';
            }
        } catch (Throwable $e) {
            $validationErrors[] = 'The selected class could not be verified.';
        }
    }
    if ($postedSectionId <= 0 || $postedClassId <= 0) {
        $validationErrors[] = 'Please select a valid section.';
    } else {
        try {
            $sectionCheck = db_prepare("SELECT section_id FROM sections WHERE section_id = ? AND class_id = ?");
            $sectionCheck->bind_param('ii', $postedSectionId, $postedClassId);
            $sectionCheck->execute();
            $sectionCheckResult = $sectionCheck->get_result();
            if (!$sectionCheckResult || !$sectionCheckResult->fetch_assoc()) {
                $validationErrors[] = 'The selected section does not belong to the selected class.';
            }
        } catch (Throwable $e) {
            $validationErrors[] = 'The selected section could not be verified.';
        }
    }
    if (count($periods) === 0) {
        $validationErrors[] = 'Configure periods before saving a timetable.';
    }
    if (count($subjects) === 0) {
        $validationErrors[] = 'Assign subjects to this class and section before saving.';
    }

    $entries = [];
    if (count($validationErrors) === 0) {
        $postedTimetable = $_POST['timetable'] ?? [];
        if (!is_array($postedTimetable)) {
            $validationErrors[] = 'The timetable data is invalid.';
        } else {
            foreach ($postedTimetable as $postedDay => $postedPeriods) {
                $day = (string) $postedDay;
                if (!in_array($day, $days, true)) {
                    $validationErrors[] = 'The timetable contains an invalid day.';
                    continue;
                }
                if (!is_array($postedPeriods)) {
                    $validationErrors[] = 'The timetable contains an invalid period group.';
                    continue;
                }
                foreach ($postedPeriods as $postedPeriodId => $postedCell) {
                    $periodId = (int) $postedPeriodId;
                    if ($periodId <= 0 || !isset($periodMap[$periodId])) {
                        $validationErrors[] = 'The timetable contains an unconfigured period.';
                        continue;
                    }
                    if (!is_array($postedCell)) {
                        $validationErrors[] = 'The timetable contains an invalid cell.';
                        continue;
                    }
                    $subjectRaw = $postedCell['subject_id'] ?? '';
                    $teacherRaw = $postedCell['teacher_id'] ?? '';
                    if (!is_scalar($subjectRaw) || !is_scalar($teacherRaw)) {
                        $validationErrors[] = 'The timetable contains an invalid selection.';
                        continue;
                    }
                    $subjectRaw = trim((string) $subjectRaw);
                    $teacherRaw = trim((string) $teacherRaw);
                    $hasSubject = $subjectRaw !== '' && $subjectRaw !== '0';
                    $hasTeacher = $teacherRaw !== '' && $teacherRaw !== '0';
                    if ($hasTeacher && !$hasSubject) {
                        $validationErrors[] = 'Choose a subject before assigning a teacher.';
                        continue;
                    }
                    $subjectId = $hasSubject ? (int) $subjectRaw : 0;
                    $teacherId = $hasTeacher ? (int) $teacherRaw : 0;
                    if ($hasSubject && $subjectId <= 0) {
                        $validationErrors[] = 'The timetable contains an invalid subject.';
                        continue;
                    }
                    if ($subjectId > 0 && !isset($allowedSubjectIds[$subjectId])) {
                        $validationErrors[] = 'The timetable contains a subject not assigned to this class and section.';
                        continue;
                    }
                    if ($hasTeacher && $teacherId <= 0) {
                        $validationErrors[] = 'The timetable contains an invalid teacher.';
                        continue;
                    }
                    if ($teacherId > 0 && !isset($employeeMap[$teacherId])) {
                        $validationErrors[] = 'The timetable contains an inactive or unknown teacher.';
                        continue;
                    }
                    if (isset($breakPeriodMap[$periodId]) && ($hasSubject || $hasTeacher)) {
                        $validationErrors[] = 'Break periods cannot contain subject or teacher assignments.';
                        continue;
                    }
                    if ($subjectId > 0) {
                        $entries[] = [
                            'day' => $day,
                            'period_id' => $periodId,
                            'subject_id' => $subjectId,
                            'teacher_id' => $teacherId,
                        ];
                    }
                }
            }
        }
    }

    if (count($validationErrors) > 0) {
        $error = implode(' ', array_values(array_unique($validationErrors)));
    } else {
        $transactionStarted = false;
        $conn = db_connect();
        try {
            $conn->begin_transaction();
            $transactionStarted = true;
            $deleteStmt = $conn->prepare("DELETE FROM timetable WHERE class_id = ? AND section_id = ?");
            if (!$deleteStmt) {
                throw new RuntimeException('Unable to prepare timetable deletion.');
            }
            $deleteStmt->bind_param('ii', $postedClassId, $postedSectionId);
            $deleteStmt->execute();
            $inserted = 0;
            if (count($entries) > 0) {
                $insertStmt = $conn->prepare("INSERT INTO timetable (class_id, section_id, day, period_id, subject_id, teacher_id) VALUES (?, ?, ?, ?, ?, NULLIF(?, ''))");
                if (!$insertStmt) {
                    throw new RuntimeException('Unable to prepare timetable insertion.');
                }
                foreach ($entries as $entry) {
                    $teacherValue = $entry['teacher_id'] > 0 ? (string) $entry['teacher_id'] : '';
                    $insertStmt->bind_param('iisiis', $postedClassId, $postedSectionId, $entry['day'], $entry['period_id'], $entry['subject_id'], $teacherValue);
                    $insertStmt->execute();
                    $inserted++;
                }
            }
            $conn->commit();
            $transactionStarted = false;
            $message = $inserted > 0 ? 'Timetable saved successfully with ' . $inserted . ' scheduled ' . ($inserted === 1 ? 'entry' : 'entries') . '.' : 'Timetable saved successfully. The schedule is now empty.';
            $schedule = [];
            $scheduleLoadError = '';
            $loadSchedule();
        } catch (Throwable $e) {
            if ($transactionStarted) {
                try {
                    if ($conn->in_transaction()) {
                        $conn->rollback();
                    }
                } catch (Throwable $ignored) {
                }
            }
            $error = 'The timetable could not be saved. No changes were applied.';
        }
    }
}

if ($isSaveRequest && $error !== '' && isset($_POST['timetable']) && is_array($_POST['timetable'])) {
    foreach ($days as $day) {
        $postedDay = $_POST['timetable'][$day] ?? null;
        if (!is_array($postedDay)) {
            continue;
        }
        foreach ($periodMap as $periodId => $period) {
            $postedCell = $postedDay[(string) $periodId] ?? null;
            if (!is_array($postedCell)) {
                continue;
            }
            $postedSubject = $postedCell['subject_id'] ?? '';
            $postedTeacher = $postedCell['teacher_id'] ?? '';
            $schedule[$day][$periodId] = [
                'subject_id' => is_scalar($postedSubject) ? (int) $postedSubject : 0,
                'teacher_id' => is_scalar($postedTeacher) ? (int) $postedTeacher : 0,
            ];
        }
    }
}

if ($error === '' && $message === '') {
    $loadErrors = array_values(array_filter([$classLoadError, $periodLoadError, $subjectLoadError, $employeeLoadError, $scheduleLoadError]));
    if (count($loadErrors) > 0) {
        $error = implode(' ', array_unique($loadErrors));
    }
}

$canSave = $selectedCampusId > 0 && $selectedClassId > 0 && $selectedSectionId > 0 && count($periods) > 0 && count($subjects) > 0;
$statusText = $message !== '' ? $message : $error;
$statusType = $message !== '' ? 'success' : 'error';
$routeUrl = BASE_URL . 'class_period_selection.php';
$regularPeriodCount = count($periodMap) - count($breakPeriodMap);

include __DIR__ . '/includes/header.php';
?>
<style>
.tt-page{background:#faf9f6;color:#1e293b;font-family:'Inter','Segoe UI',sans-serif;min-height:100%;padding:16px}
.tt-page,.tt-page *{box-sizing:border-box}
.tt-shell{max-width:1600px;margin:0 auto}
.tt-header{align-items:center;border-bottom:1px solid #e5e7eb;display:flex;gap:16px;justify-content:space-between;margin-bottom:24px;padding:0 0 16px}
.tt-title{color:#0f172a;font-family:Georgia,'Times New Roman',serif;font-size:30px;font-weight:700;letter-spacing:-.025em;line-height:1.2;margin:0}
.tt-subtitle{color:#6b7280;font-size:13px;margin:2px 0 0}
.tt-header-right{align-items:center;display:flex;flex-wrap:wrap;gap:12px;justify-content:flex-end}
.tt-actions,.tt-top-filters{align-items:center;display:flex;flex-wrap:wrap;gap:8px}
.tt-top-filters{margin-left:8px}
.tt-btn{align-items:center;background:#fff;border:1px solid #d1d5db;border-radius:6px;color:#374151;cursor:pointer;display:inline-flex;font-size:12px;font-weight:500;gap:6px;justify-content:center;min-height:32px;padding:6px 12px;transition:background-color .15s ease,border-color .15s ease,color .15s ease}
.tt-btn:hover{background:#f9fafb;border-color:#9ca3af;color:#111827}
.tt-btn:focus{box-shadow:0 0 0 3px rgba(30,41,59,.12);outline:none}
.tt-btn-primary{background:#1e293b;border-color:#1e293b;box-shadow:0 1px 2px rgba(15,23,42,.12);color:#fff}
.tt-btn-primary:hover{background:#0f172a;border-color:#0f172a;color:#fff}
.tt-btn-reset{color:#dc2626}
.tt-btn-reset:hover{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
.tt-btn:disabled{cursor:not-allowed;opacity:.5}
.tt-branch{max-width:170px}
.tt-select,.tt-cell-select,.tt-branch{appearance:none;background-color:#fff;background-image:url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23334155' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");background-position:right .6rem center;background-repeat:no-repeat;background-size:.9em;border:1px solid #d1d5db;border-radius:6px;color:#374151;cursor:pointer;font-size:13px;min-height:38px;outline:none;padding:8px 30px 8px 12px;width:100%}
.tt-select:focus,.tt-cell-select:focus,.tt-branch:focus{border-color:#64748b;box-shadow:0 0 0 1px #64748b;outline:none}
.tt-select:disabled,.tt-cell-select:disabled,.tt-branch:disabled{background-color:#f9fafb;color:#9ca3af;cursor:not-allowed}
.tt-date{color:#6b7280;font-size:12px;font-weight:500;white-space:nowrap}
.tt-card{background:#fff;border:1px solid rgba(229,231,235,.9);border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,.06);padding:24px}
.tt-card-title{color:#0f172a;font-family:Georgia,'Times New Roman',serif;font-size:18px;font-weight:700;margin:0 0 20px}
.tt-filter-row{display:grid;gap:16px;grid-template-columns:repeat(2,minmax(0,1fr));margin-bottom:16px}
.tt-field label{color:#4b5563;display:block;font-size:12px;font-weight:500;margin:0 0 6px}
.tt-required{color:#ef4444}
.tt-status{border:1px solid transparent;border-radius:6px;font-size:12px;margin:0 0 16px;padding:10px 12px}
.tt-status.success{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
.tt-status.error{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
.tt-table-shell{border-top:1px solid #e5e7eb;overflow-x:auto;padding-top:16px;-webkit-overflow-scrolling:touch}
.tt-table{border-collapse:collapse;min-width:1100px;table-layout:fixed;width:100%}
.tt-table thead tr{border-bottom:1px solid #e5e7eb}
.tt-table th,.tt-table td{padding:12px 8px;text-align:left;vertical-align:top}
.tt-table thead th{color:#6b7280;font-size:11px;font-weight:600;letter-spacing:.06em;padding:12px 8px;text-transform:uppercase}
.tt-table thead th:first-child{width:124px}
.tt-page .tt-table thead th,.tt-page .tt-table tbody th.tt-period{background-color:#fff!important}
.tt-page .tt-table tbody th.tt-period{color:#1f2937}
.tt-table tbody tr{border-bottom:1px solid #f1f5f9}
.tt-table tbody tr:not(.tt-break):hover{background:#f8fafc}
.tt-period{white-space:nowrap;width:124px}
.tt-period-name{color:#1f2937;display:block;font-size:12px;font-weight:600}
.tt-period-time{color:#9ca3af;display:block;font-size:11px;font-variant-numeric:tabular-nums;font-weight:400;letter-spacing:-.01em;margin-top:3px;white-space:nowrap}
.tt-cell{padding:10px 8px!important}
.tt-cell-select{font-size:12px;margin-bottom:6px;min-height:32px;padding:6px 28px 6px 10px}
.tt-cell-select:last-child{margin-bottom:0}
.tt-break{background:#fafafa}
.tt-break td{color:#64748b;font-size:12px;font-weight:500;letter-spacing:.04em;padding:16px 8px;text-align:center}
.tt-break .tt-period{background:transparent}
.tt-setup-notice{align-items:center;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;color:#92400e;display:flex;font-size:12px;gap:12px;justify-content:space-between;margin:16px 0 0;padding:10px 12px}
.tt-setup-notice a{color:#92400e;font-weight:600;text-decoration:none;white-space:nowrap}
.tt-setup-notice a:hover{text-decoration:underline}
.tt-mobile-note{color:#9ca3af;display:none;font-size:11px;margin-top:8px;text-align:right}
.tt-toast{align-items:center;background:#0f172a;border-radius:8px;bottom:20px;box-shadow:0 10px 25px rgba(15,23,42,.2);color:#fff;display:flex;font-size:12px;gap:8px;opacity:0;padding:12px 16px;pointer-events:none;position:fixed;right:20px;transform:translateY(80px);transition:opacity .3s ease,transform .3s ease;z-index:9999}
.tt-toast.visible{opacity:1;transform:translateY(0)}
.tt-toast i{color:#4ade80}
.tt-page{overflow-x:hidden}
.tt-table-shell{max-width:100%}
.tt-table-shell::-webkit-scrollbar{height:6px;width:6px}
.tt-table-shell::-webkit-scrollbar-track{background:#f1f5f9}
.tt-table-shell::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
.tt-table-shell::-webkit-scrollbar-thumb:hover{background:#94a3b8}
@media(min-width:768px) and (max-width:1199px){.tt-header{align-items:flex-start;flex-direction:column;gap:12px}.tt-header-right{justify-content:space-between;width:100%}.tt-table{min-width:1000px}}
@media(max-width:767px){.tt-page{padding:12px 8px 24px}.tt-header{align-items:flex-start;flex-direction:column;gap:14px;margin-bottom:16px;padding-bottom:14px}.tt-header-right{align-items:stretch;gap:10px;width:100%}.tt-actions,.tt-top-filters{width:100%}.tt-actions{justify-content:flex-start}.tt-actions .tt-btn{flex:1 1 0;justify-content:center;min-width:0;padding-left:8px;padding-right:8px}.tt-top-filters{justify-content:space-between;margin-left:0}.tt-branch{flex:1 1 150px;max-width:none}.tt-date{font-size:11px;text-align:right;white-space:normal}.tt-title{font-size:26px}.tt-card{padding:16px 12px}.tt-card-title{font-size:17px;margin-bottom:16px}.tt-filter-row{gap:12px;grid-template-columns:1fr;margin-bottom:12px}.tt-select,.tt-branch{font-size:16px;min-height:42px}.tt-table-shell{border-radius:0;margin:0 -4px;padding:12px 4px 8px;width:calc(100% + 8px)}.tt-table{border-collapse:separate;border-spacing:0;font-size:12px;min-width:900px}.tt-table th,.tt-table td{padding:10px 6px}.tt-table thead th{font-size:10px;letter-spacing:.04em;padding:10px 6px}.tt-table thead th:first-child{position:sticky;left:0;width:118px;z-index:4;background:#f8fafc;box-shadow:2px 0 4px rgba(15,23,42,.06)}.tt-period{position:sticky;left:0;width:118px;z-index:2;background:#fff;box-shadow:2px 0 4px rgba(15,23,42,.05)}.tt-break .tt-period{background:#fafafa}.tt-cell{padding:8px 6px!important}.tt-cell-select{font-size:16px;min-height:38px;padding:7px 26px 7px 9px}.tt-mobile-note{display:block;margin-top:8px}.tt-setup-notice{align-items:flex-start;flex-direction:column;gap:6px}.tt-toast{bottom:12px;left:12px;right:12px;justify-content:center}}
@media(max-width:480px){.tt-page{padding:10px 6px 20px}.tt-actions{gap:6px}.tt-actions .tt-btn{font-size:11px;min-height:34px;padding-left:6px;padding-right:6px}.tt-top-filters{align-items:stretch;flex-direction:column;gap:8px}.tt-branch{max-width:none}.tt-date{text-align:left}.tt-card{padding:14px 8px}.tt-table-shell{margin:0 -2px;width:calc(100% + 4px)}.tt-table{min-width:840px}.tt-cell-select{font-size:15px}}
@media print{body *{visibility:hidden!important}.tt-page,.tt-page *{visibility:visible!important}.tt-page{left:0;overflow:visible;padding:0;position:absolute;top:0;width:100%}.tt-header{border:0;margin-bottom:12px;padding:0}.tt-actions,.tt-top-filters,.tt-filter-row,.tt-status,.tt-setup-notice,.tt-mobile-note,.tt-toast{display:none!important}.tt-card{border:0;box-shadow:none;padding:0}.tt-table-shell{overflow:visible;padding-top:0}.tt-table{font-size:9px;min-width:0}.tt-table th,.tt-table td{padding:4px}.tt-cell-select{appearance:none;background-image:none;border:0!important;min-height:0;padding:1px 0}}
</style>
<div class="tt-page">
    <div class="tt-shell">
    <form method="post" action="<?php echo e($routeUrl); ?>" id="timetableForm">
        <input type="hidden" name="action" value="SaveClassTimetable">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <header class="tt-header">
            <div>
                <h1 class="tt-title">Timetable</h1>
                <p class="tt-subtitle">Weekly class schedules</p>
            </div>
            <div class="tt-header-right">
                <div class="tt-actions no-print">
                    <button class="tt-btn tt-btn-primary" type="submit"<?php echo $canSave ? '' : ' disabled'; ?>><i class="fa fa-floppy-disk" aria-hidden="true"></i> Save</button>
                    <button class="tt-btn" type="button" id="ttPrint"><i class="fa fa-print" aria-hidden="true"></i> Print</button>
                    <button class="tt-btn tt-btn-reset" type="button" id="ttReset"><i class="fa fa-rotate-left" aria-hidden="true"></i> Reset</button>
                </div>
                <div class="tt-top-filters no-print">
                    <select class="tt-branch" id="branchSelect" aria-label="Branch">
                        <option value="">All Branches</option>
                        <?php foreach ($campuses as $campus) {
                            $campusId = (int) ($campus['campus_id'] ?? 0);
                            $campusLabel = trim((string) ($campus['name'] ?? '')) ?: 'Campus #' . $campusId;
                        ?>
                            <option value="<?php echo e((string) $campusId); ?>"><?php echo e($campusLabel); ?></option>
                        <?php } ?>
                    </select>
                    <div class="tt-date"><?php echo e(date('l, F j, Y')); ?></div>
                </div>
            </div>
        </header>
        <section class="tt-card print-card" aria-labelledby="weeklyScheduleTitle">
            <h2 class="tt-card-title" id="weeklyScheduleTitle">Weekly Schedule</h2>

            <div class="tt-filter-row">
                <div class="tt-field">
                    <label for="campusSelect">Campus <span class="tt-required">*</span></label>
                    <?php if (count($campuses) > 0) { ?>
                        <select class="tt-select" id="campusSelect" name="campus_id">
                            <?php foreach ($campuses as $campus) {
                                $campusId = (int) ($campus['campus_id'] ?? 0);
                                $campusLabel = trim((string) ($campus['name'] ?? '')) ?: 'Campus #' . $campusId;
                            ?>
                                <option value="<?php echo e((string) $campusId); ?>"<?php echo $selectedCampusId === $campusId ? ' selected' : ''; ?>><?php echo e($campusLabel); ?></option>
                            <?php } ?>
                        </select>
                    <?php } else { ?>
                        <select class="tt-select" id="campusSelect" name="campus_id" disabled><option value="">No campuses available</option></select>
                    <?php } ?>
                </div>
                <div class="tt-field">
                    <label for="classSelect">Class <span class="tt-required">*</span></label>
                    <?php if (count($classes) > 0) { ?>
                        <select class="tt-select" id="classSelect" name="class_id">
                            <?php foreach ($classes as $class) {
                                $classId = (int) ($class['class_id'] ?? 0);
                                $classLabel = trim((string) ($class['class_name'] ?? '')) ?: 'Class #' . $classId;
                            ?>
                                <option value="<?php echo e((string) $classId); ?>"<?php echo $selectedClassId === $classId ? ' selected' : ''; ?>><?php echo e($classLabel); ?></option>
                            <?php } ?>
                        </select>
                    <?php } else { ?>
                        <select class="tt-select" id="classSelect" name="class_id" disabled><option value="">No active classes available</option></select>
                    <?php } ?>
                </div>
            </div>

            <div class="tt-filter-row">
                <div class="tt-field">
                    <label for="sectionSelect">Section</label>
                    <select class="tt-select" id="sectionSelect" name="section_id"<?php echo count($sections) > 0 ? '' : ' disabled'; ?>>
                        <?php if (count($sections) > 0) { ?>
                            <?php foreach ($sections as $section) {
                                $sectionId = (int) ($section['section_id'] ?? 0);
                                $sectionLabel = trim((string) ($section['section_name'] ?? '')) ?: 'Section #' . $sectionId;
                            ?>
                                <option value="<?php echo e((string) $sectionId); ?>"<?php echo $selectedSectionId === $sectionId ? ' selected' : ''; ?>><?php echo e($sectionLabel); ?></option>
                            <?php } ?>
                        <?php } else { ?>
                            <option value="">No sections available</option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <?php if ($statusText !== '') { ?>
                <div class="tt-status <?php echo e($statusType); ?>" role="status"><?php echo e($statusText); ?></div>
            <?php } ?>

            <div class="tt-table-shell">
                <table class="tt-table" aria-label="Weekly class timetable">
                    <thead>
                        <tr>
                            <th scope="col">PERIOD</th>
                            <?php foreach ($days as $day) { ?>
                                <th scope="col"><?php echo e(strtoupper($day)); ?></th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayPeriods as $periodIndex => $period) {
                            $periodId = (int) ($period['period_id'] ?? 0);
                            $periodKey = $periodId > 0 ? (string) $periodId : 'preview-' . (int) $periodIndex;
                            $isPreview = $periodId <= 0;
                            $isBreak = $isPreview ? !empty($period['is_break']) : isset($breakPeriodMap[$periodId]);
                            $periodName = trim((string) ($period['period_name'] ?? '')) ?: 'Period #' . $periodId;
                            $startTime = $formatTime($period['start_time'] ?? '');
                            $endTime = $formatTime($period['end_time'] ?? '');
                            $periodTime = $startTime !== '' ? $startTime . ($endTime !== '' ? ' - ' . $endTime : '') : 'Time not set';
                            $cellEnabled = $canSave && !$isBreak && $periodId > 0;
                        ?>
                            <?php if ($isBreak) { ?>
                                <tr class="tt-break">
                                    <td class="tt-period">
                                        <span class="tt-period-name"><?php echo e($periodName); ?></span>
                                        <span class="tt-period-time"><?php echo e($periodTime); ?></span>
                                    </td>
                                    <td colspan="<?php echo count($days); ?>">Break</td>
                                </tr>
                            <?php } else { ?>
                                <tr>
                                    <th class="tt-period" scope="row">
                                        <span class="tt-period-name"><?php echo e($periodName); ?></span>
                                        <span class="tt-period-time"><?php echo e($periodTime); ?></span>
                                    </th>
                                    <?php foreach ($days as $day) {
                                        $initial = $schedule[$day][$periodId] ?? ['subject_id' => 0, 'teacher_id' => 0];
                                        $initialSubjectId = (int) ($initial['subject_id'] ?? 0);
                                        $initialTeacherId = (int) ($initial['teacher_id'] ?? 0);
                                    ?>
                                        <td class="tt-cell">
                                            <select class="tt-cell-select" id="subject-<?php echo e($periodKey . '-' . strtolower($day)); ?>" name="timetable[<?php echo e($day); ?>][<?php echo e((string) $periodId); ?>][subject_id]" data-initial-value="<?php echo e((string) $initialSubjectId); ?>" aria-label="<?php echo e($day . ', ' . $periodName . ', subject'); ?>"<?php echo $cellEnabled ? '' : ' disabled'; ?>>
                                                <option value="">— Subject —</option>
                                                <?php foreach ($subjects as $subject) {
                                                    $subjectId = (int) ($subject['subject_id'] ?? 0);
                                                    if ($subjectId <= 0) {
                                                        continue;
                                                    }
                                                    $subjectName = trim((string) ($subject['subject_name'] ?? '')) ?: 'Subject #' . $subjectId;
                                                ?>
                                                    <option value="<?php echo e((string) $subjectId); ?>"<?php echo $initialSubjectId === $subjectId ? ' selected' : ''; ?>><?php echo e($subjectName); ?></option>
                                                <?php } ?>
                                            </select>
                                            <select class="tt-cell-select" id="teacher-<?php echo e($periodKey . '-' . strtolower($day)); ?>" name="timetable[<?php echo e($day); ?>][<?php echo e((string) $periodId); ?>][teacher_id]" data-initial-value="<?php echo e((string) $initialTeacherId); ?>" aria-label="<?php echo e($day . ', ' . $periodName . ', teacher'); ?>"<?php echo $cellEnabled ? '' : ' disabled'; ?>>
                                                <option value="">— Teacher —</option>
                                                <?php foreach ($employees as $employee) {
                                                    $employeeId = (int) ($employee['emp_id'] ?? 0);
                                                    if ($employeeId <= 0) {
                                                        continue;
                                                    }
                                                ?>
                                                    <option value="<?php echo e((string) $employeeId); ?>"<?php echo $initialTeacherId === $employeeId ? ' selected' : ''; ?>><?php echo e($employee['label']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$canSave) { ?>
                <div class="tt-setup-notice">
                    <span>
                        <?php if (count($periods) === 0) { ?>
                            Configure teaching and break periods to enable editing.
                        <?php } elseif (count($subjects) === 0) { ?>
                            Assign subjects to this class and section to enable editing.
                        <?php } else { ?>
                            Choose a campus, class, and section to begin.
                        <?php } ?>
                    </span>
                    <?php if (count($periods) === 0) { ?>
                        <a href="<?php echo e(BASE_URL . 'create_period_details.php'); ?>">Configure periods</a>
                    <?php } elseif (count($subjects) === 0) { ?>
                        <a href="<?php echo e(BASE_URL . 'class_subjects.php'); ?>">Assign subjects</a>
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="tt-mobile-note"><i class="fa fa-arrows-h" aria-hidden="true"></i> Swipe horizontally to view all days.</div>
        </section>
    </form>
    </div>
    <div class="tt-toast" id="ttToast" role="status" aria-live="polite"><i class="fa fa-circle-check" aria-hidden="true"></i><span id="ttToastMessage">Changes saved successfully!</span></div>
</div>
<script>
(function () {
    var routeUrl = <?php echo json_encode($routeUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var sectionsUrl = <?php echo json_encode(BASE_URL . 'get_sections.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var form = document.getElementById('timetableForm');
    var campus = document.getElementById('campusSelect');
    var branch = document.getElementById('branchSelect');
    var classSelect = document.getElementById('classSelect');
    var section = document.getElementById('sectionSelect');
    var toast = document.getElementById('ttToast');
    var toastMessage = document.getElementById('ttToastMessage');
    var toastTimer;
    var save = form ? form.querySelector('button[type="submit"]') : null;

    function showToast(message) {
        if (!toast || !toastMessage) return;
        toastMessage.textContent = message;
        toast.classList.add('visible');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () {
            toast.classList.remove('visible');
        }, 3000);
    }

    function go(campusId, classId, sectionId) {
        var query = [];
        if (campusId) query.push('campus_id=' + encodeURIComponent(campusId));
        if (classId) query.push('class_id=' + encodeURIComponent(classId));
        if (sectionId) query.push('section_id=' + encodeURIComponent(sectionId));
        window.location.assign(routeUrl + (query.length ? '?' + query.join('&') : ''));
    }

    function setSections(items) {
        section.innerHTML = '';
        if (!items.length) {
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'No sections available';
            section.appendChild(empty);
            section.disabled = true;
            return;
        }
        items.forEach(function (item) {
            var option = document.createElement('option');
            option.value = String(item.section_id || '');
            option.textContent = String(item.section_name || ('Section #' + (item.section_id || '')));
            section.appendChild(option);
        });
        section.disabled = false;
        section.value = section.options[0].value;
    }

    function loadSections(classId) {
        if (!classId) {
            setSections([]);
            go(campus ? campus.value : '', '', '');
            return;
        }
        section.disabled = true;
        var loading = document.createElement('option');
        loading.value = '';
        loading.textContent = 'Loading sections…';
        section.innerHTML = '';
        section.appendChild(loading);
        var request = new XMLHttpRequest();
        request.open('GET', sectionsUrl + '?class_id=' + encodeURIComponent(classId), true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.onload = function () {
            if (request.status < 200 || request.status >= 300) {
                setSections([]);
                go(campus ? campus.value : '', classId, '');
                return;
            }
            var items;
            try {
                items = JSON.parse(request.responseText || '[]');
            } catch (error) {
                setSections([]);
                go(campus ? campus.value : '', classId, '');
                return;
            }
            if (!Array.isArray(items)) items = [];
            setSections(items);
            go(campus ? campus.value : '', classId, items.length ? section.value : '');
        };
        request.onerror = function () {
            setSections([]);
            go(campus ? campus.value : '', classId, '');
        };
        request.send();
    }

    if (classSelect) {
        classSelect.addEventListener('change', function () {
            loadSections(classSelect.value);
        });
    }
    if (section) {
        section.addEventListener('change', function () {
            if (section.value) go(campus ? campus.value : '', classSelect ? classSelect.value : '', section.value);
        });
    }
    if (campus) {
        campus.addEventListener('change', function () {
            if (branch && campus.value) branch.value = campus.value;
            go(campus.value, classSelect ? classSelect.value : '', section ? section.value : '');
        });
    }
    if (branch) {
        branch.addEventListener('change', function () {
            if (!branch.value || !campus) return;
            campus.value = branch.value;
            campus.dispatchEvent(new Event('change'));
        });
    }

    var reset = document.getElementById('ttReset');
    if (reset) {
        reset.addEventListener('click', function () {
            var selects = document.querySelectorAll('[data-initial-value]');
            for (var i = 0; i < selects.length; i++) {
                var select = selects[i];
                var initial = select.getAttribute('data-initial-value') || '';
                var found = Array.prototype.some.call(select.options, function (option) {
                    return option.value === initial;
                });
                select.value = found ? initial : '';
            }
            showToast('Timetable reset to original state.');
        });
    }

    var print = document.getElementById('ttPrint');
    if (print) {
        print.addEventListener('click', function () {
            window.print();
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            if (save) {
                save.disabled = true;
                save.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Saving…';
            }
        });
    }

    var successStatus = document.querySelector('.tt-status.success');
    if (successStatus) showToast(successStatus.textContent.trim());
}());
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
