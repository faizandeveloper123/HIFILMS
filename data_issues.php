<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Data Issues';

// --- Gather data issues --------------------------------------------------
$issues = [
    'required' => [],   // missing father_name / contact / address / DOB / section
    'gender'   => [],   // missing / invalid gender
    'mismatch' => [],   // class/section mismatch (invalid class id / no class / no section)
];

$res = db_query("SELECT s.student_id, s.gr_no, CONCAT(s.first_name, ' ', COALESCE(s.last_name,'')) AS name,
                        s.father_name, s.phone, s.address, s.dob, s.gender,
                        s.class_id, s.section_id,
                        COALESCE(c.class_name, '') AS class_name,
                        COALESCE(sec.section_name, '') AS section_name
                 FROM students s
                 LEFT JOIN classes c  ON s.class_id  = c.class_id
                 LEFT JOIN sections sec ON s.section_id = sec.section_id
                 WHERE s.status = 1
                 ORDER BY c.class_name, sec.section_name, s.first_name");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['student_id'];
        $miss = [];
        if (trim((string) $row['father_name']) === '') $miss[] = 'Father Name';
        if (trim((string) $row['phone']) === '')       $miss[] = 'Contact';
        if (trim((string) $row['address']) === '')     $miss[] = 'Address';
        if ($row['dob'] === null || $row['dob'] === '' || $row['dob'] === '0000-00-00') $miss[] = 'DOB';
        if ((int) $row['section_id'] <= 0)             $miss[] = 'Section';
        if ($miss) {
            $row['missing'] = implode(', ', $miss);
            $issues['required'][] = $row;
        }

        $g = strtolower(trim((string) $row['gender']));
        if ($g === '' || !in_array($g, ['male', 'female', 'other'], true)) {
            $issues['gender'][] = $row;
        }

        if ((int) $row['class_id'] <= 0 || $row['class_name'] === '') {
            $issues['mismatch'][] = $row;
        } elseif ((int) $row['section_id'] <= 0 || $row['section_name'] === '') {
            $issues['mismatch'][] = $row;
        }
    }
}

$totalIssues = count($issues['required']) + count($issues['gender']) + count($issues['mismatch']);

include __DIR__ . '/includes/header.php';
?>
<style type="text/css">
.issues-banner {
    background: #FEF2F2; border: 1px solid #FECACA; border-radius: 14px;
    padding: 14px 16px; display: flex; align-items: center; gap: 14px;
    margin-bottom: 16px; flex-wrap: wrap;
}
.issues-icon-badge {
    width: 44px; height: 44px; border-radius: 12px; background: #DC2626; color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 18px; flex: none;
}
.issues-banner-title { font-weight: 800; color: #B91C1C; font-size: 15px; }
.issues-banner-sub { font-size: 12px; color: #a15558; margin-top: 2px; }
.issues-detail-panel { background: #fff; border-radius: 14px; border: 1px solid #EEF1F4; padding: 16px 18px; margin-bottom: 16px; }
.issues-panel-head { display: flex; align-items: center; gap: 10px; margin: 0 0 10px 0; font-weight: 800; color: #111827; font-size: 13.5px; }
.issues-panel-head .ic { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex: none; }
.issues-panel p.issues-intro { font-size: 12px; color: #6B7280; margin: 0 0 12px 0; }
.tbl-wrap { overflow-x: auto; }
.tbl-wrap table { width: 100%; font-size: 12px; border-collapse: collapse; }
.tbl-wrap th { background: #F9FAFB; color: #374151; font-weight: 700; padding: 8px 10px; border: 1px solid #EEF1F4; text-align: left; white-space: nowrap; }
.tbl-wrap td { padding: 7px 10px; border: 1px solid #EEF1F4; color: #374151; }
.issue-tag { display: inline-block; background: #FEE2E2; color: #B91C1C; border-radius: 6px; padding: 2px 8px; font-weight: 700; font-size: 11px; }
.empty-row td { text-align: center; color: #9CA3AF; padding: 14px; }
.btn-fix { background: #FF7A1B; color: #fff; border-radius: 8px; padding: 5px 14px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-block; }
.btn-fix:hover { background: #E06508; color: #fff; }
</style>

<div class="main-content">
    <div class="container-fluid">
        <h2 style="margin: 8px 0 4px 0; font-size: 20px;"><i class="fa fa-exclamation-triangle" style="color:#DC2626;"></i> Data Issues</h2>
        <div style="margin-bottom:14px;">
            <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp;
            <a href="<?php echo BASE_URL; ?>students_analytics_dashboard.php">Student Analytics</a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp; Data Issues
        </div>

        <div class="issues-banner">
            <div class="issues-icon-badge"><i class="fa fa-exclamation-triangle"></i></div>
            <div>
                <div class="issues-banner-title">Data Issues Found (<?php echo $totalIssues; ?>)</div>
                <div class="issues-banner-sub">Some student records have incomplete or incorrect data. Review the details below and fix them directly.</div>
            </div>
        </div>

        <?php
        $sections = [
            'required' => ['title' => 'Missing Required Fields', 'icon' => 'fa fa-user', 'bg' => '#FEE2E2', 'fg' => '#B91C1C',
                           'empty' => 'Great! No students are missing required fields.'],
            'gender'   => ['title' => 'Missing/Invalid Gender', 'icon' => 'fa fa-venus-mars', 'bg' => '#EDE9FE', 'fg' => '#7C3AED',
                           'empty' => 'Great! All students have a valid gender.'],
            'mismatch' => ['title' => 'Class/Section Mismatch', 'icon' => 'fa fa-sitemap', 'bg' => '#FEF3C7', 'fg' => '#B45309',
                           'empty' => 'Great! All students have a valid class and section.'],
        ];
        foreach ($sections as $key => $info):
            $rows = $issues[$key];
        ?>
        <div class="issues-detail-panel">
            <h5 class="issues-panel-head">
                <span class="ic" style="background:<?php echo $info['bg']; ?>; color:<?php echo $info['fg']; ?>;"><i class="<?php echo $info['icon']; ?>"></i></span>
                <?php echo $info['title']; ?> (<?php echo count($rows); ?>)
            </h5>
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>GR No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Issue</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) === 0): ?>
                            <tr class="empty-row"><td colspan="7"><?php echo $info['empty']; ?></td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo (int) $r['student_id']; ?></td>
                                <td><?php echo e($r['gr_no']); ?></td>
                                <td><?php echo e($r['name']); ?></td>
                                <td><?php echo e($r['class_name'] !== '' ? $r['class_name'] : '-'); ?></td>
                                <td><?php echo e($r['section_name'] !== '' ? $r['section_name'] : '-'); ?></td>
                                <td><span class="issue-tag"><?php
                                        if ($key === 'required') echo e($r['missing']);
                                        elseif ($key === 'gender') echo e($r['gender'] === '' || $r['gender'] === null ? '(empty)' : $r['gender']);
                                        else echo e(($r['class_name'] === '' ? 'Invalid Class' : ($r['section_name'] === '' ? 'No Section Assigned' : 'Invalid Section')));
                                    ?></span></td>
                                <td><a class="btn-fix" href="<?php echo BASE_URL; ?>manage_students.php?edit_id=<?php echo (int) $r['student_id']; ?>"><i class="fa fa-pencil"></i> Fix</a></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>