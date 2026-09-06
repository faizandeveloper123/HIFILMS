<?php
define('HIIFI', true);
require_once __DIR__ . '/config.php';
require_login();
$page_title = 'Graph Analytics';

$attRows = [];
$res = db_query("SELECT DATE_FORMAT(date, '%c') mm, status, COUNT(*) c FROM attendance GROUP BY DATE_FORMAT(date, '%c'), status");
if ($res) { while ($row = $res->fetch_assoc()) { $attRows[] = $row; } }

$staffAttRows = [];
$res = db_query("SELECT DATE_FORMAT(date, '%c') mm, status, COUNT(*) c FROM employee_attendance GROUP BY DATE_FORMAT(date, '%c'), status");
if ($res) { while ($row = $res->fetch_assoc()) { $staffAttRows[] = $row; } }

$monthLabels = ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'];
$monthIndex = function ($mm) { return ((int) $mm - 10 + 12) % 12; };
$studentAttPct = array_fill(0, 11, 0);
$staffAttPct = array_fill(0, 11, 0);
foreach ($attRows as $r) {
    $idx = $monthIndex($r['mm']);
    $tot = 0; $pres = 0;
    foreach ($attRows as $rr) { if ($monthIndex($rr['mm']) === $idx) { $tot += (int) $rr['c']; if ($rr['status'] === 'present') { $pres += (int) $rr['c']; } } }
    $studentAttPct[$idx] = $tot > 0 ? round($pres / $tot * 100, 2) : 0;
}
foreach ($staffAttRows as $r) {
    $idx = $monthIndex($r['mm']);
    $tot = 0; $pres = 0;
    foreach ($staffAttRows as $rr) { if ($monthIndex($rr['mm']) === $idx) { $tot += (int) $rr['c']; if ($rr['status'] === 'present') { $pres += (int) $rr['c']; } } }
    $staffAttPct[$idx] = $tot > 0 ? round($pres / $tot * 100, 2) : 0;
}

$enrollYears = range(2021, (int) date('Y'));
$enrollData = []; $dropoutData = [];
foreach ($enrollYears as $y) {
    $tot = (int) (db_query("SELECT COUNT(*) c FROM students WHERE YEAR(admission_date) = $y")->fetch_assoc()['c'] ?? 0);
    $drop = (int) (db_query("SELECT COUNT(*) c FROM students WHERE YEAR(admission_date) = $y AND status != 1")->fetch_assoc()['c'] ?? 0);
    $enrollData[] = $tot;
    $dropoutData[] = $drop;
}

$fullMonthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
$feeLabels = ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'];
$paidStudents = array_fill(0, 12, 0);
$unpaidStudents = array_fill(0, 12, 0);
$feeRows = [];
$res = db_query("SELECT month, status, COUNT(*) c FROM fee_challans GROUP BY month, status");
if ($res) { while ($row = $res->fetch_assoc()) { $feeRows[] = $row; } }
foreach ($feeRows as $r) {
    $mn = (int) $r['month'];
    if ($mn === 0) { foreach ($fullMonthNames as $num => $name) { if (stripos($r['month'], $name) !== false) { $mn = $num; break; } } }
    if ($mn < 1 || $mn > 12) { continue; }
    $idx = ($mn - 10 + 12) % 12;
    if ($r['status'] === 'paid') { $paidStudents[$idx] += (int) $r['c']; }
    else { $unpaidStudents[$idx] += (int) $r['c']; }
}

$maleStudents = (int) (db_query("SELECT COUNT(*) c FROM students WHERE gender='male'")->fetch_assoc()['c'] ?? 0);
$femaleStudents = (int) (db_query("SELECT COUNT(*) c FROM students WHERE gender='female'")->fetch_assoc()['c'] ?? 0);

$examLabels = []; $passData = []; $failData = [];
$res = db_query("SELECT sub.subject_name, m.obtained_marks, m.total_marks FROM marks m JOIN subjects sub ON sub.subject_id=m.subject_id ORDER BY sub.subject_name");
if ($res) {
    $subjectMarks = [];
    while ($row = $res->fetch_assoc()) {
        $subjectMarks[$row['subject_name']][] = $row;
    }
    foreach ($subjectMarks as $subjectName => $rows) {
        $examLabels[] = $subjectName;
        $pass = 0; $fail = 0;
        foreach ($rows as $m) {
            if ($m['total_marks'] > 0 && $m['obtained_marks'] >= $m['total_marks'] * 0.4) { $pass++; }
            else { $fail++; }
        }
        $passData[] = $pass;
        $failData[] = $fail;
    }
}

$localityLabels = []; $localityCounts = [];
$res = db_query("SELECT COALESCE(l.locality_name, s.city, 'Not Specified') as loc_name, COUNT(*) c FROM students s LEFT JOIN localities l ON l.locality_id = s.locality_id GROUP BY loc_name ORDER BY c DESC");
if ($res) { while ($row = $res->fetch_assoc()) { if ($row['c'] > 0) { $localityLabels[] = $row['loc_name']; $localityCounts[] = (int) $row['c']; } } }
if (count($localityLabels) === 0) { $localityLabels[] = 'No Locality'; $localityCounts[] = 0; }

include __DIR__ . '/includes/header.php';
?>
<style>
.graph-container {
background: white;
padding: 15px;
border-radius: 10px;
box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
margin-bottom: 20px;
}
</style>

<div class="main-content">
<div class="container-fluid">
<a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard </a> &nbsp; <i style="" class="fa fa-angle-double-right"></i>  &nbsp;
Analytics Dashboard
<br><br>
<div class="" style="background-color: white;">

<section class="add_sub_agent" id="table_sub_agent">

    <h2 style="font-size: 26px; color: #333; font-weight: 600; margin-bottom: 20px; text-align: center;">
        📊 School Insights & Analytics Dashboard
    </h2>
<div class="container">
  <div class="col-md-7 col-sm-6 col-xs-12" style="margin-top: 15px;">
        <div class="graph-container" style="max-width: 600px; height: 360px; margin: auto; padding: 15px; background: #fff; border-radius: 10px; box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);">
            <h2 class="text-center">Monthly Attendance Trends (Students vs Staff)</h2>
            <canvas id="attendanceChart"></canvas>
        </div>
 </div>

 <div class="col-md-5 col-sm-6 col-xs-12" style="margin-top: 15px;">
        <div class="graph-container" style="max-width: 450px; height: 360px; margin: auto; padding: 15px; background: #fff; border-radius: 10px; box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);">
            <h2 class="text-center">Student Enrollment & Dropout Rate</h2>
            <canvas id="enrollmentChart"></canvas>
        </div>
 </div>

 <div class="col-md-7 col-sm-6 col-xs-12" style="margin-top: 15px;">
        <div class="graph-container" style="max-width: 600px; height: 360px; margin: auto; padding: 15px; background: #fff; border-radius: 10px; box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);">
            <h2 class="text-center">Monthly Fee Collection: Paid vs. Unpaid Students</h2>
            <canvas id="feeCollectionChart"></canvas>
        </div>
 </div>

<div class="col-md-5 col-sm-6 col-xs-12" style="margin-top: 15px;">
    <div class="graph-container" style="max-width: 450px; height: 360px; margin: auto; padding: 15px; background: #fff; border-radius: 10px; box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);">
        <h2 class="text-center" style="font-size: 16px; margin-bottom: 10px;">Male vs. Female Students</h2>
        <canvas id="genderChart" style="max-width: 100% !important; max-height: 250px !important;"></canvas>
    </div>
</div>

 <div class="col-md-7 col-sm-6 col-xs-12" style="margin-top: 15px;">
        <div class="graph-container" style="max-width: 600px; height: 360px; margin: auto; padding: 15px; background: #fff; border-radius: 10px; box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);">
            <h2 class="text-center">Pass vs. fail ratio comparison</h2>
            <canvas id="examPerformanceChart"></canvas>
        </div>
 </div>

    <div class="col-md-5 col-sm-6 col-xs-12" style="margin-top: 15px;">
        <div style="background: white; padding: 15px; border-radius: 10px; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2); max-width: 450px; height: 360px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <h2 style="text-align: center; font-size: 18px; margin-bottom: 10px;">Student Distribution by Locality</h2>
            <canvas id="localityChart" style="width: 100% !important; height: 100% !important;"></canvas>
        </div>
    </div>

</div>

</section>
</div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
    var ctx = document.getElementById('attendanceChart').getContext('2d');

    var gradientGreen = ctx.createLinearGradient(0, 0, 0, 400);
    gradientGreen.addColorStop(0, "rgba(0, 200, 83, 1)");
    gradientGreen.addColorStop(1, "rgba(0, 200, 83, 0.3)");

    var gradientOrange = ctx.createLinearGradient(0, 0, 0, 400);
    gradientOrange.addColorStop(0, "rgba(255, 124, 27, 1)");
    gradientOrange.addColorStop(1, "rgba(255, 124, 27, 0.3)");

    var months = <?php echo json_encode($monthLabels); ?>;
    var feeCollectionChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: "Students Attendance",
                    backgroundColor: gradientGreen,
                    borderColor: "rgba(0, 200, 83, 1)",
                    borderWidth: 2,
                    borderRadius: 10,
                    data: <?php echo json_encode($studentAttPct); ?>
                },
                {
                    label: "Staff Attendance",
                    backgroundColor: gradientOrange,
                    borderColor: "rgba(255, 124, 27, 1)",
                    borderWidth: 2,
                    borderRadius: 10,
                    data: <?php echo json_encode($staffAttPct); ?>
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: "#333",
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (tooltipItem) {
                            return tooltipItem.dataset.label + ": " + tooltipItem.raw + "%";
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: "Attendance Percentage",
                        color: "#555",
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: "rgba(200, 200, 200, 0.2)"
                    },
                    ticks: {
                        color: "#222",
                        font: {
                            size: 12
                        },
                        callback: function (value) {
                            return value + "%";
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: "Months",
                        color: "#555",
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    },
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: "#222",
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
</script>

<script>
    var ctx = document.getElementById('enrollmentChart').getContext('2d');

    var enrollmentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($enrollYears); ?>,
            datasets: [
                {
                    label: "Enrollments",
                    backgroundColor: "rgba(0, 102, 255, 0.8)",
                    borderColor: "rgba(0, 102, 255, 1)",
                    borderWidth: 1,
                    data: <?php echo json_encode($enrollData); ?>
                },
                {
                    label: "Dropouts",
                    backgroundColor: "rgba(255, 69, 0, 0.8)",
                    borderColor: "rgba(255, 69, 0, 1)",
                    borderWidth: 1,
                    data: <?php echo json_encode($dropoutData); ?>
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: "Number of Students"
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: "Year"
                    }
                }
            }
        }
    });
</script>

<script>
    var ctx = document.getElementById('feeCollectionChart').getContext('2d');

    var feeCollectionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($feeLabels); ?>,
            datasets: [
                {
                    label: "Paid Students",
                    backgroundColor: "rgba(76, 175, 80, 0.2)",
                    borderColor: "#4CAF50",
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointBackgroundColor: "#4CAF50",
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.4,
                    data: <?php echo json_encode($paidStudents); ?>
                },
                {
                    label: "Unpaid Students",
                    backgroundColor: "rgba(244, 67, 54, 0.2)",
                    borderColor: "#F44336",
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointBackgroundColor: "#F44336",
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.4,
                    data: <?php echo json_encode($unpaidStudents); ?>
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            size: 14,
                            weight: 'bold'
                        },
                        color: "#333"
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: "rgba(0, 0, 0, 0.1)",
                        borderDash: [5, 5]
                    },
                    title: {
                        display: true,
                        text: "Number of Students",
                        font: {
                            size: 14,
                            weight: "bold"
                        },
                        color: "#333"
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: "Months",
                        font: {
                            size: 14,
                            weight: "bold"
                        },
                        color: "#333"
                    }
                }
            }
        }
    });
</script>

<script>
    var ctx = document.getElementById("genderChart").getContext("2d");

    var genderChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ["Male Students", "Female Students"],
            datasets: [{
                data: [<?php echo (int) $maleStudents; ?>, <?php echo (int) $femaleStudents; ?>],
                backgroundColor: ["#007BFF", "#FF4081"],
                borderColor: "#fff",
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            size: 14
                        }
                    }
                }
            }
        }
    });
</script>

<script>
    var ctx = document.getElementById("examPerformanceChart").getContext("2d");

    var examChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($examLabels); ?>,
            datasets: [
                {
                    label: "Passed",
                    data: <?php echo json_encode($passData); ?>,
                    backgroundColor: "rgba(52, 152, 219, 0.8)",
                    borderColor: "rgba(41, 128, 185, 1)",
                    borderWidth: 1
                },
                {
                    label: "Failed",
                    data: <?php echo json_encode($failData); ?>,
                    backgroundColor: "rgba(231, 76, 60, 0.8)",
                    borderColor: "rgba(192, 57, 43, 1)",
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: "Number of Students",
                        font: {
                            size: 14
                        }
                    },
                    grid: {
                        color: "rgba(0,0,0,0.1)",
                        borderDash: [5, 5]
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: "Subjects",
                        font: {
                            size: 14
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    }
                }
            }
        }
    });
</script>

<script>
        var localities = <?php echo json_encode($localityLabels); ?>;
        var studentCounts = <?php echo json_encode($localityCounts); ?>;
        var backgroundColors = ["#007BFF", "#28A745", "#DC3545", "#FFC107", "#17A2B8", "#6F42C1", "#20C997", "#E83E8C", "#6610F2", "#FD7E14"];

        var ctx = document.getElementById("localityChart").getContext("2d");

        var localityChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: localities,
                datasets: [{
                    label: "Number of Students",
                    data: studentCounts,
                    backgroundColor: backgroundColors.slice(0, localities.length),
                    borderColor: "#fff",
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { size: 14 },
                            color: "#333"
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                return `${tooltipItem.label}: ${tooltipItem.raw} students`;
                            }
                        }
                    }
                }
            }
        });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>