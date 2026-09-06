<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'Parents Login Statistics';

db_query("CREATE TABLE IF NOT EXISTS parent_access (
    access_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    username VARCHAR(191),
    password VARCHAR(255),
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$totalStudents = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status = 1")->fetch_assoc()['c'] ?? 0);
$withPhone = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status = 1 AND (father_cellno IS NOT NULL AND father_cellno <> '' OR phone IS NOT NULL AND phone <> '')")->fetch_assoc()['c'] ?? 0);
$withWhatsapp = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status = 1 AND whatsapp_number IS NOT NULL AND whatsapp_number <> ''")->fetch_assoc()['c'] ?? 0);
$withNeither = (int) (db_query("SELECT COUNT(*) c FROM students WHERE status = 1 AND (father_cellno IS NULL OR father_cellno = '') AND (phone IS NULL OR phone = '') AND (whatsapp_number IS NULL OR whatsapp_number = '')")->fetch_assoc()['c'] ?? 0);
$registeredLogins = (int) (db_query("SELECT COUNT(DISTINCT student_id) c FROM parent_access")->fetch_assoc()['c'] ?? 0);
$activeLogins = (int) (db_query("SELECT COUNT(DISTINCT student_id) c FROM parent_access WHERE status = 1")->fetch_assoc()['c'] ?? 0);

$active7 = (int) (db_query("SELECT COUNT(DISTINCT student_id) c FROM parent_access WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetch_assoc()['c'] ?? 0);
$activeMonth = (int) (db_query("SELECT COUNT(DISTINCT student_id) c FROM parent_access WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetch_assoc()['c'] ?? 0);
$activeYear = (int) (db_query("SELECT COUNT(DISTINCT student_id) c FROM parent_access WHERE YEAR(created_at) = YEAR(NOW())")->fetch_assoc()['c'] ?? 0);

$classLabels = [];
$classData = [];
$res = db_query("SELECT c.class_name, COUNT(s.student_id) total
                 FROM classes c
                 LEFT JOIN students s ON s.class_id = c.class_id AND s.status = 1
                 WHERE c.status = 1
                 GROUP BY c.class_id, c.class_name
                 HAVING total > 0
                 ORDER BY total DESC");
while ($row = $res->fetch_assoc()) {
    $classLabels[] = $row['class_name'];
    $classData[] = (int) $row['total'];
}

include __DIR__ . '/includes/header.php';
?>
<style>
.ps-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 16px; }
.ps-stat { background: #fff; border: 1px solid #edf0f5; border-radius: 12px; box-shadow: 0 1px 2px rgba(16,24,40,0.04); padding: 14px 16px; display: flex; align-items: center; gap: 12px; }
.ps-stat-icon { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
.ps-stat.total .ps-stat-icon { background: #eef2ff; color: #6366f1; }
.ps-stat.phone .ps-stat-icon { background: #ecfdf5; color: #10b981; }
.ps-stat.whatsapp .ps-stat-icon { background: #d1fae5; color: #059669; }
.ps-stat.reg .ps-stat-icon { background: #fff1f2; color: #f43f5e; }
.ps-stat.act .ps-stat-icon { background: #fdf2f8; color: #ec4899; }
.ps-stat-value { font-size: 20px; font-weight: 800; color: #1f2937; line-height: 1.2; }
.ps-stat-label { font-size: 11px; color: #8a94a6; font-weight: 600; }
.chart-box { background: #fff; border: 1px solid #edf0f5; border-radius: 12px; box-shadow: 0 1px 2px rgba(16,24,40,0.04); padding: 16px; }
@media (max-width: 1100px) { .ps-stats { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 576px) { .ps-stats { grid-template-columns: 1fr; } }
</style>

<div class="main-content">
    <div class="container-fluid">

        <a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a> &nbsp;
        <i class="fa fa-angle-double-right"></i> &nbsp;
        Parents Portal &nbsp;
        <i class="fa fa-angle-double-right"></i> &nbsp;
        Login Statistics

        <a href="<?php echo BASE_URL; ?>parents_id.php" class="btn btn-round btn-info quiklink pull-right">
            View Parents Login IDs
        </a>
        <br><br>

        <div class="row">
            <div style="margin-top:10px;">
                <h3 style="float: left;">Parents Login Statistics</h3>
                <div class="clearfix"></div>
            </div>
        </div>
        <div class="clearfix"></div>

        <div class="ps-stats">
            <div class="ps-stat total">
                <div class="ps-stat-icon"><i class="fa fa-users"></i></div>
                <div>
                    <div class="ps-stat-value"><?php echo $totalStudents; ?></div>
                    <div class="ps-stat-label">Total Students</div>
                </div>
            </div>
            <div class="ps-stat phone">
                <div class="ps-stat-icon"><i class="fa fa-mobile"></i></div>
                <div>
                    <div class="ps-stat-value"><?php echo $withPhone; ?></div>
                    <div class="ps-stat-label">Parents with Phone</div>
                </div>
            </div>
            <div class="ps-stat whatsapp">
                <div class="ps-stat-icon"><i class="fab fa-whatsapp"></i></div>
                <div>
                    <div class="ps-stat-value"><?php echo $withWhatsapp; ?></div>
                    <div class="ps-stat-label">Parents with WhatsApp</div>
                </div>
            </div>
            <div class="ps-stat reg">
                <div class="ps-stat-icon"><i class="fa fa-key"></i></div>
                <div>
                    <div class="ps-stat-value"><?php echo $registeredLogins; ?></div>
                    <div class="ps-stat-label">Registered Logins</div>
                </div>
            </div>
            <div class="ps-stat act">
                <div class="ps-stat-icon"><i class="fa fa-check-circle"></i></div>
                <div>
                    <div class="ps-stat-value"><?php echo $activeLogins; ?></div>
                    <div class="ps-stat-label">Active Logins</div>
                </div>
            </div>
        </div>

        <div class="row" style="background-color: white;">

            <section class="add_sub_agent" id="table_sub_agent">
                <div class="container">

                    <div style="margin-top:10px;">
                        <h3 style="float: left;">Login Activity</h3>
                        <div class="clearfix"></div>
                    </div>

                    <div class="clearfix"></div>
                    <br>

                    <div style="overflow-x:auto;">
                        <table id="listofstudents" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr style="font-size: 18px;">
                                    <th width="5%">S.No</th>
                                    <th width="40%">Details</th>
                                    <th width="10%" class="text-center">Statistics</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center">1</td>
                                    <td>Active Parents in Last 7 Days</td>
                                    <td class="text-center">
                                        <a href="<?php echo BASE_URL; ?>parents_statistics.php?filter=7days" class="btn btn-success" style="padding: 0px 20px;">
                                            <?php echo $active7; ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-center">2</td>
                                    <td>Active Parents in Current Month (<?php echo date('F'); ?>)</td>
                                    <td class="text-center">
                                        <a href="<?php echo BASE_URL; ?>parents_statistics.php?filter=month" class="btn btn-success" style="padding: 0px 20px;">
                                            <?php echo $activeMonth; ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-center">3</td>
                                    <td>Active Parents in Current Year (<?php echo date('Y'); ?>)</td>
                                    <td class="text-center">
                                        <a href="<?php echo BASE_URL; ?>parents_statistics.php?filter=year" class="btn btn-success" style="padding: 0px 20px;">
                                            <?php echo $activeYear; ?>
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <br><br>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-box">
                                <h4 style="margin:0 0 12px 0; font-size:15px; font-weight:700; color:#1f2937;"><i class="fa fa-bar-chart"></i> Students per Class</h4>
                                <canvas id="classChart" height="150"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-box">
                                <h4 style="margin:0 0 12px 0; font-size:15px; font-weight:700; color:#1f2937;"><i class="fa fa-pie-chart"></i> Contact Information Coverage</h4>
                                <canvas id="contactChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="clearfix"></div>
            </section>

        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
var classLabels = <?php echo json_encode($classLabels); ?>;
var classData = <?php echo json_encode($classData); ?>;
var totalCount = <?php echo $totalStudents; ?>;
var phoneCount = <?php echo $withPhone; ?>;
var waCount = <?php echo $withWhatsapp; ?>;
var neitherCount = <?php echo $withNeither; ?>;
window.addEventListener('load', function() {
    new Chart(document.getElementById('classChart'), {
        type: 'bar',
        data: {
            labels: classLabels,
            datasets: [{
                label: 'Students',
                data: classData,
                backgroundColor: '#6366f1',
                borderColor: '#4f46e5',
                borderWidth: 1,
                borderRadius: 6,
                barPercentage: 0.65
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
    new Chart(document.getElementById('contactChart'), {
        type: 'doughnut',
        data: {
            labels: ['With Phone', 'With WhatsApp', 'No Contact'],
            datasets: [{
                data: [phoneCount, waCount, neitherCount],
                backgroundColor: ['#10b981', '#06b6d4', '#f43f5e']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>