<?php if (!defined('HIIFI')) exit('Direct access not allowed.');
require_once __DIR__ . '/ensure_schema.php';
require_once __DIR__ . '/campus.php';
$sbUserName = e($_SESSION['user_name'] ?? 'Admin');
$sbUserRole = e($_SESSION['user_role'] ?? 'admin');
?>
<!DOCTYPE html><html lang="en"><head>
    <title><?php echo isset($page_title) ? e($page_title) . ' | LAPS School & College' : 'LAPS School & College'; ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/img/favicon.png">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/customizedStyling.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/style11.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/font-awesome-5.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/laps-design.css" rel="stylesheet">
<style>
.nav-container {
  width: 100%;
  background: #fff;
  border-bottom: 1px solid #eaecef;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
  position: sticky;
  top: 0;
  z-index: 0;
  margin-top:15px;
}
.nav-bar {
  display: flex;
  gap: 1px;
  flex-wrap: nowrap;
  overflow-x: auto;
  justify-content: center;
}
.nav-item {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px 15px;
  text-decoration: none;
  color: #2c3e50;
  font-weight: 500;
  font-size: 14px;
  white-space: nowrap;
  transition: all 0.3s ease;
  background: #f8f9fa;
  border: 1px solid #eaecef;
}
.nav-item:hover { background: #fff4e6; border-color: #ffd8b3; color: #e67e22; }
.nav-item.active { background: #ff7800; color: white; border-color: #ff7800; box-shadow: 0 3px 8px rgba(230, 126, 34, 0.3); }
.nav-item.active i { color: white; }
.nav-item i { font-size: 14px; margin-right: 6px; color: #2c3e50; transition: all 0.3s ease; }
.nav-item:hover i { color: #e67e22; }
.nav-bar::-webkit-scrollbar { display: none; }
.nav-bar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
</head>
<body class="sidebar-expanded">

<button id="ios_toggle_btn" aria-label="Toggle sidebar"><i class="fa fa-bars"></i></button>
<div class="left_col scroll-view" id="dsLeftCol">
  <div class="sidebar-logo">
    <img src="<?php echo BASE_URL; ?>assets/img/favicon.png" alt="LAPS Logo">
    <button class="ds-sidebar-toggle" id="dsToggleIn" type="button" title="Collapse Sidebar" aria-label="Collapse Sidebar"><i class="fa fa-bars"></i></button>
  </div>
  <div class="ds-branch">
    <div class="sb-name"><?php echo e(get_setting('school_name', 'LAPS School & College')); ?></div>
    <div class="sb-session"><span class="sb-dot">&#9679;</span>&nbsp; Session <?php echo e(get_setting('session_year', '2026-2027')); ?></div>
  </div>
  <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
    <ul class="side-menu" id="menu-web">

      <li class="ds-nav-label"><span>Resources</span></li>
      <li><a href="<?php echo BASE_URL; ?>software_demo_videos.php" title="Guidline Videos"><i class="fa fa-play"></i><span class="label">Guidline Videos</span></a></li>

      <li class="ds-nav-label"><span>Main</span></li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Dashboard"><i class="fas fa-tachometer-alt"></i><span class="label">Dashboard</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>dashboard.php">Executive Dashboard</a></li>
          <li><a href="<?php echo BASE_URL; ?>basic_dashboard.php">Staff Dashboard</a></li>
        </ul>
      </li>

      <li class="ds-nav-label"><span>Academics</span></li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Students"><i class="fa fa-users"></i><span class="label">Students</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>add_student.php">Add New Student</a></li>
          <li><a href="<?php echo BASE_URL; ?>students_analytics_dashboard.php">Student Analytics</a></li>
          <li><a href="<?php echo BASE_URL; ?>data_issues.php">Data Issues</a></li>
          <li><a href="<?php echo BASE_URL; ?>class_promotion.php">Class Promotion</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Attendance"><i class="fa fa-microphone"></i><span class="label">Attendance</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>mark_attend.php">Mark Attendance</a></li>
          <li><a href="<?php echo BASE_URL; ?>mark_attendanceReport_list.php">Attendance Analytics</a></li>
          <li><a href="<?php echo BASE_URL; ?>send_msgs.php?attendance=A">Send SMS Report</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Examination"><i class="fa fa-graduation-cap"></i><span class="label">Examination</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>view_marksheet.php">Add Marks Sheet</a></li>
          <li><a href="<?php echo BASE_URL; ?>reportcards.php">View Marks Sheet</a></li>
          <li><a href="<?php echo BASE_URL; ?>manage_exams.php">Academic Setting</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Timetable"><i class="fa fa-clock-o"></i><span class="label">Timetable</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>period_categories.php">Periods Category</a></li>
          <li><a href="<?php echo BASE_URL; ?>create_period_details.php">Create/Manage Periods</a></li>
          <li><a href="<?php echo BASE_URL; ?>class_period.php">Assign Periods to Classes</a></li>
          <li><a href="<?php echo BASE_URL; ?>class_period_selection.php">Create Timetable</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_class_period_selection.php">View Timetable</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_teachers_timetable.php">Teachers Timetable</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Datesheet"><i class="fa fa-clock-o"></i><span class="label">Datesheet</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>create_datesheet.php">Create Datesheet</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_datesheet.php">View Datesheet</a></li>
          <li><a href="<?php echo BASE_URL; ?>generate_rollnoSlips.php">Generate Roll No Slips</a></li>
          <li><a href="<?php echo BASE_URL; ?>syllabus_management.php">Syllabus Management</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Academic Setup"><i class="fa fa-dollar"></i><span class="label">Academic Setup</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>academic_setup.php">Manage Academics</a></li>
        </ul>
      </li>

      <li class="ds-nav-label"><span>Communication</span></li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Messages"><i class="fa fa-envelope"></i><span class="label">Messages</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>new_message.php">New Message</a></li>
          <li><a href="<?php echo BASE_URL; ?>messages_history.php">View Messages</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_templates.php">View Templates</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Parents Portal"><i class="fa fa-home"></i><span class="label">Parents Portal</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>parents_portal_dashboard.php">Parents Overview</a></li>
        </ul>
      </li>
      <li>
        <a href="<?php echo BASE_URL; ?>student_portal.php" title="Student Portal"><i class="fa fa-graduation-cap"></i><span class="label">Student Portal</span></a>
      </li>

      <li class="ds-nav-label"><span>Finance</span></li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Fee Collection"><i class="fa fa-money"></i><span class="label">Fee Collection</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>monthly_challan.php">Create Challan</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_challan_details.php">View Challan</a></li>
          <li><a href="<?php echo BASE_URL; ?>multi_fee_reports.php">Fee Reporting</a></li>
          <li><a href="<?php echo BASE_URL; ?>update_fee_settings.php">Fee Settings</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Expenses"><i class="fa fa-money"></i><span class="label">Expenses</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>manage_expenses.php">Add/View Expenses</a></li>
          <li><a href="<?php echo BASE_URL; ?>monthly_expenses_report.php">Expenses Report</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="PayRoll"><i class="fab fa-paypal"></i><span class="label">PayRoll</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>creat_payroll.php">Create PayRoll</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_payroll.php">View PayRoll</a></li>
          <li><a href="<?php echo BASE_URL; ?>staff_security.php">Staff Security Fee</a></li>
          <li><a href="<?php echo BASE_URL; ?>payroll_setting.php">PayRoll Setting</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Accounts"><i class="fa fa-calculator"></i><span class="label">Accounts</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>add_revenue.php">Add Revenue</a></li>
          <li><a href="<?php echo BASE_URL; ?>revenue_list.php">List of Revenues</a></li>
          <li><a href="<?php echo BASE_URL; ?>revenue_heads.php">Revenue Heads</a></li>
        </ul>
      </li>

      <li class="ds-nav-label"><span>Human Resources</span></li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Employees/HRM"><i class="fa fa-user"></i><span class="label">Employees/HRM</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>add_emp.php">Add Employee</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_emp.php">View Employees</a></li>
          <li><a href="<?php echo BASE_URL; ?>staff_directory.php">Staff Directory</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_emp_attendance.php">Staff Attendance</a></li>
          <li><a href="<?php echo BASE_URL; ?>monthly_attendance.php">Attendance Report</a></li>
          <li><a href="<?php echo BASE_URL; ?>old_employee.php">Old Employees</a></li>
        </ul>
      </li>

      <li class="ds-nav-label"><span>Administration</span></li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Front Office"><i class="fa fa-phone"></i><span class="label">Front Office</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>front_desk_analytics.php">Front Desk Overview</a></li>
          <li><a href="<?php echo BASE_URL; ?>student_inquiry.php">Admission Inquiries</a></li>
          <li><a href="<?php echo BASE_URL; ?>manage_complaint.php">Complaint Hub</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Transport"><i class="fa fa-truck"></i><span class="label">Transport</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>vehicles.php">Vehicles</a></li>
          <li><a href="<?php echo BASE_URL; ?>route.php">Routes</a></li>
          <li><a href="<?php echo BASE_URL; ?>vehicle_route.php">Assign Vehicles</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Library"><i class="fa fa-book"></i><span class="label">Library</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>list_books.php">Book List</a></li>
          <li><a href="<?php echo BASE_URL; ?>issue_return.php">Issue Return</a></li>
          <li><a href="<?php echo BASE_URL; ?>issue_return_employee.php">Employee Issue&amp;Return</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Cards Generator"><i class="fa fa-file"></i><span class="label">Cards Generator</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>cards.php">Staff Cards</a></li>
          <li><a href="<?php echo BASE_URL; ?>students_card.php">Students Cards</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)" title="Point of Sale"><i class="fa fa-search"></i><span class="label">Point of Sale</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>canteen_dashboard.php">POS Dashboard</a></li>
        </ul>
      </li>

      <li class="ds-nav-label"><span>System</span></li>
      <li class="has-children">
        <a href="javascript:void(0)" title="System Settings"><i class="fa fa-gear"></i><span class="label">System Settings</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>settings.php">Update Settings</a></li>
          <li><a href="<?php echo BASE_URL; ?>manage_schools.php">Manage Schools (Campuses)</a></li>
          <li><a href="<?php echo BASE_URL; ?>manage_localities.php">Manage Localities</a></li>
        </ul>
      </li>

    </ul>

    <div class="ds-sidebar-user">
      <div class="u-row">
        <div class="avatar"><?php echo strtoupper(substr($sbUserName, 0, 1)); ?></div>
        <div class="u-meta">
          <div class="u-name"><?php echo $sbUserName; ?></div>
          <div class="u-role"><?php echo ucfirst($sbUserRole); ?></div>
        </div>
      </div>
      <div class="u-actions">
        <a href="<?php echo BASE_URL; ?>update_profile.php" title="Profile"><i class="fa fa-user"></i> Profile</a>
        <a href="<?php echo BASE_URL; ?>settings.php" title="Settings"><i class="fa fa-gear"></i> Settings</a>
        <a href="#" onclick="localStorage.clear(); window.location.href='<?php echo BASE_URL; ?>logout.php'; return false;" title="Logout"><i class="fa fa-sign-out"></i> Exit</a>
      </div>
    </div>

    <div id="menu-ios" class="ios-nav">
      <a href="<?php echo BASE_URL; ?>software_demo_videos.php" class="nav-item">
        <div class="nav-icon"><i class="fa fa-play"></i></div>
        <div class="nav-text">Guidline Videos</div>
      </a>
      <a href="<?php echo BASE_URL; ?>front_desk_analytics.php" class="nav-item">
        <div class="nav-icon"><i class="fa fa-line-chart"></i></div>
        <div class="nav-text">Front Desk Analytics</div>
      </a>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-phone"></i></div>
        <div class="nav-text">Front Office</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>front_desk_analytics.php" class="submenu-item">Front Desk Overview</a>
        <a href="<?php echo BASE_URL; ?>student_inquiry.php" class="submenu-item">Admission Inquiries</a>
        <a href="<?php echo BASE_URL; ?>manage_complaint.php" class="submenu-item">Complaint Hub</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fas fa-tachometer-alt"></i></div>
        <div class="nav-text">Dashboard</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>dashboard.php" class="submenu-item">Executive Dashboard</a>
        <a href="<?php echo BASE_URL; ?>basic_dashboard.php" class="submenu-item">Staff Dashboard</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-users"></i></div>
        <div class="nav-text">Students</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>add_student.php" class="submenu-item">Add New Student</a>
        <a href="<?php echo BASE_URL; ?>students_analytics_dashboard.php" class="submenu-item">Student Analytics</a>
        <a href="<?php echo BASE_URL; ?>data_issues.php" class="submenu-item">Data Issues</a>
        <a href="<?php echo BASE_URL; ?>class_promotion.php" class="submenu-item">Class Promotion</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-microphone"></i></div>
        <div class="nav-text">Attendance</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>mark_attend.php" class="submenu-item">Mark Attendance</a>
        <a href="<?php echo BASE_URL; ?>mark_attendanceReport_list.php" class="submenu-item">Attendance Analytics</a>
        <a href="<?php echo BASE_URL; ?>send_msgs.php?attendance=A" class="submenu-item">Send SMS Report</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-envelope"></i></div>
        <div class="nav-text">Messages</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>new_message.php" class="submenu-item">New Message</a>
        <a href="<?php echo BASE_URL; ?>messages_history.php" class="submenu-item">View Messages</a>
        <a href="<?php echo BASE_URL; ?>view_templates.php" class="submenu-item">View Templates</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-money"></i></div>
        <div class="nav-text">Fee Collection</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>monthly_challan.php" class="submenu-item">Create Challan</a>
        <a href="<?php echo BASE_URL; ?>view_challan_details.php" class="submenu-item">View Challan</a>
        <a href="<?php echo BASE_URL; ?>multi_fee_reports.php" class="submenu-item">Fee Reporting</a>
        <a href="<?php echo BASE_URL; ?>update_fee_settings.php" class="submenu-item">Fee Settings</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-graduation-cap"></i></div>
        <div class="nav-text">Examination</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>view_marksheet.php" class="submenu-item">Add Marks Sheet</a>
        <a href="<?php echo BASE_URL; ?>reportcards.php" class="submenu-item">View Marks Sheet</a>
        <a href="<?php echo BASE_URL; ?>manage_exams.php" class="submenu-item">Academic Setting</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-clock-o"></i></div>
        <div class="nav-text">Timetable</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>period_categories.php" class="submenu-item">Periods Category</a>
        <a href="<?php echo BASE_URL; ?>create_period_details.php" class="submenu-item">Create/Manage Periods</a>
        <a href="<?php echo BASE_URL; ?>class_period.php" class="submenu-item">Assign Periods to Classes</a>
        <a href="<?php echo BASE_URL; ?>class_period_selection.php" class="submenu-item">Create Timetable</a>
        <a href="<?php echo BASE_URL; ?>view_class_period_selection.php" class="submenu-item">View Timetable</a>
        <a href="<?php echo BASE_URL; ?>view_teachers_timetable.php" class="submenu-item">Teachers Timetable</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-user"></i></div>
        <div class="nav-text">Employees/HRM</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>add_emp.php" class="submenu-item">Add Employee</a>
        <a href="<?php echo BASE_URL; ?>view_emp.php" class="submenu-item">View Employees</a>
        <a href="<?php echo BASE_URL; ?>staff_directory.php" class="submenu-item">Staff Directory</a>
        <a href="<?php echo BASE_URL; ?>view_emp_attendance.php" class="submenu-item">Staff Attendance</a>
        <a href="<?php echo BASE_URL; ?>monthly_attendance.php" class="submenu-item">Attendance Report</a>
        <a href="<?php echo BASE_URL; ?>old_employee.php" class="submenu-item">Old Employees</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-clock-o"></i></div>
        <div class="nav-text">Datesheet</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>create_datesheet.php" class="submenu-item">Create Datesheet</a>
        <a href="<?php echo BASE_URL; ?>view_datesheet.php" class="submenu-item">View Datesheet</a>
        <a href="<?php echo BASE_URL; ?>generate_rollnoSlips.php" class="submenu-item">Generate Roll No Slips</a>
        <a href="<?php echo BASE_URL; ?>syllabus_management.php" class="submenu-item">Syllabus Management</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-truck"></i></div>
        <div class="nav-text">Transport</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>vehicles.php" class="submenu-item">Vehicles</a>
        <a href="<?php echo BASE_URL; ?>route.php" class="submenu-item">Routes</a>
        <a href="<?php echo BASE_URL; ?>vehicle_route.php" class="submenu-item">Assign Vehicles</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-book"></i></div>
        <div class="nav-text">Library</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>list_books.php" class="submenu-item">Book List</a>
        <a href="<?php echo BASE_URL; ?>issue_return.php" class="submenu-item">Issue Return</a>
        <a href="<?php echo BASE_URL; ?>issue_return_employee.php" class="submenu-item">Employee Issue&amp;Return</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fab fa-paypal"></i></div>
        <div class="nav-text">PayRoll</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>creat_payroll.php" class="submenu-item">Create PayRoll</a>
        <a href="<?php echo BASE_URL; ?>view_payroll.php" class="submenu-item">View PayRoll</a>
        <a href="<?php echo BASE_URL; ?>staff_security.php" class="submenu-item">Staff Security Fee</a>
        <a href="<?php echo BASE_URL; ?>payroll_setting.php" class="submenu-item">PayRoll Setting</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-home"></i></div>
        <div class="nav-text">Parents Portal</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>parents_portal_dashboard.php" class="submenu-item">Parents Overview</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-money"></i></div>
        <div class="nav-text">Expenses</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>manage_expenses.php" class="submenu-item">Add/View Expenses</a>
        <a href="<?php echo BASE_URL; ?>monthly_expenses_report.php" class="submenu-item">Expenses Report</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-file"></i></div>
        <div class="nav-text">Cards Generator</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>cards.php" class="submenu-item">Staff Cards</a>
        <a href="<?php echo BASE_URL; ?>students_card.php" class="submenu-item">Students Cards</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-search"></i></div>
        <div class="nav-text">Point of Sale</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>canteen_dashboard.php" class="submenu-item">POS Dashboard</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-dollar"></i></div>
        <div class="nav-text">Academic Setup</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>academic_setup.php" class="submenu-item">Manage Academics</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-gear"></i></div>
        <div class="nav-text">System Settings</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>settings.php" class="submenu-item">Update Settings</a>
        <a href="<?php echo BASE_URL; ?>manage_schools.php" class="submenu-item">Manage Schools (Campuses)</a>
        <a href="<?php echo BASE_URL; ?>manage_localities.php" class="submenu-item">Manage Localities</a>
      </div>
      <a href="#" class="nav-item has-submenu">
        <div class="nav-icon"><i class="fa fa-calculator"></i></div>
        <div class="nav-text">Accounts</div>
      </a>
      <div class="submenu">
        <a href="<?php echo BASE_URL; ?>add_revenue.php" class="submenu-item">Add Revenue</a>
        <a href="<?php echo BASE_URL; ?>revenue_list.php" class="submenu-item">List of Revenues</a>
        <a href="<?php echo BASE_URL; ?>revenue_heads.php" class="submenu-item">Revenue Heads</a>
      </div>
      <a href="#" class="nav-item" onclick="localStorage.clear(); window.location.href='<?php echo BASE_URL; ?>logout.php'; return false;">
        <div class="nav-icon"><i class="fa fa-sign-out"></i></div>
        <div class="nav-text">Logout</div>
      </a>
    </div>
  </div>
</div>

<!-- iOS backdrop -->
<div id="ios_sidebar_backdrop"></div>

<script>
  (function() {
    var ua = navigator.userAgent || navigator.vendor || window.opera;
    var isiOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (!isiOS) return;
    document.body.classList.add('ios-device');
    var btn = document.getElementById('ios_toggle_btn');
    var backdrop = document.getElementById('ios_sidebar_backdrop');
    if (btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        document.body.classList.toggle('ios-sidebar-open');
      }, { passive: false });
    }
    if (backdrop) {
      backdrop.addEventListener('click', function() {
        document.body.classList.remove('ios-sidebar-open');
      });
    }
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        document.body.classList.remove('ios-sidebar-open');
      }
    });
    document.querySelectorAll('#menu-ios .has-submenu').forEach(function(item) {
      item.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        document.body.classList.add('ios-sidebar-open');
        var submenu = this.nextElementSibling;
        document.querySelectorAll('#menu-ios .submenu').forEach(function(sm) {
          if (sm !== submenu) sm.style.display = 'none';
        });
        if (submenu) submenu.style.display = (submenu.style.display === 'block') ? 'none' : 'block';
      }, { passive: false });
    });
  })();
</script>

        <div class="right_col" role="main" style="min-height: 733px;">

<link href="<?php echo BASE_URL; ?>assets/plugins/select2/select2.min.css" rel="stylesheet">
<script src="<?php echo BASE_URL; ?>assets/js/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/plugins/select2/select2.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/app_shared.js"></script>
<?php
$topSchoolName = get_setting('school_name', 'LAPS School & College');
$topSession    = get_setting('session_year', '2026-2027');
$topSmsUsed    = (int) get_setting('whatsapp_sms_used', 0);
$topSmsLimit   = (int) get_setting('whatsapp_sms_limit', 10000);
$topSmsPct     = $topSmsLimit > 0 ? min(100, round($topSmsUsed / $topSmsLimit * 100)) : 0;
$topSimUsed    = (int) get_setting('sim_sms_used', 0);
$topSimLimit   = (int) get_setting('sim_sms_limit', 0);
$topSimPct     = $topSimLimit > 0 ? min(100, round($topSimUsed / $topSimLimit * 100)) : 0;
$topNewComp    = (int) (db_query("SELECT COUNT(*) c FROM complaints WHERE status IN ('new','open')")->fetch_assoc()['c'] ?? 0);
$topUserName   = e($_SESSION['user_name'] ?? 'Admin');
$topUserRole   = e($_SESSION['user_role'] ?? 'admin');
$roleBadgeColors = [
    'admin'    => 'background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;',
    'staff'    => 'background:#dbeafe;color:#2563eb;border:1px solid #93c5fd;',
    'teacher'  => 'background:#d1fae5;color:#059669;border:1px solid #6ee7b7;',
    'accounts' => 'background:#fef3c7;color:#d97706;border:1px solid #fcd34d;',
];
$topRoleStyle = $roleBadgeColors[$topUserRole] ?? $roleBadgeColors['admin'];
$topRoleLabel = ucfirst($topUserRole);
?>
<div class="top_nav">
<div class="nav_menu">
<nav>
<div class="flex header">

<button type="button" id="dsSidebarToggleOut" title="Show Sidebar" aria-label="Show Sidebar"><i class="fa fa-bars"></i></button>

<div class="brand-info">
    <div class="brand-title"><?php echo e($topSchoolName); ?><span class="dot">.</span></div>
    <div class="brand-subtitle">Session <?php echo e($topSession); ?></div>
</div>

<div class="searchbar-container" style="position:relative;">
    <input type="text" id="filter" placeholder="Search Student | Name | GR No | Cell No" aria-label="Search Student" onkeyup="showResult(this.value)">
    <div id="livesearch" style="display:none;"></div>
</div>

<div class="quick-link-btn">
    <button id="quickLinkBtn" type="button" aria-haspopup="true" aria-expanded="false">
        <span><i class="fa fa-bolt ql-bolt" style="font-size:12px;margin-right:8px;color:inherit;"></i>Quick Links</span>
        <i class="fa fa-chevron-down ql-caret"></i>
    </button>
    <div id="dropdownContent" class="dropdown-content">
        <a href="<?php echo BASE_URL; ?>manage_schools.php"><i class="fa fa-university"></i> Edit School Info</a>
        <a href="<?php echo BASE_URL; ?>graph_analytics.php">Analytics Dashboard</a>
        <a href="<?php echo BASE_URL; ?>data_health_checker.php">Data Health Checker</a>
        <a href="<?php echo BASE_URL; ?>students_reports.php">Students Reports</a>
        <a href="<?php echo BASE_URL; ?>customer_tickets.php">Support Tickets</a>
        <a href="<?php echo BASE_URL; ?>print_daily_income_exp_report.php">Daily Closing</a>
        <a href="<?php echo BASE_URL; ?>print_profitloss_report.php">Monthly Closing</a>
        <a href="<?php echo BASE_URL; ?>mark_attend.php">Mark Attendance</a>
        <a href="<?php echo BASE_URL; ?>view_challan.php">View Challan</a>
        <a href="<?php echo BASE_URL; ?>messages_history.php">View Messages</a>
        <a href="<?php echo BASE_URL; ?>manage_expenses.php">Expenses</a>
        <a href="<?php echo BASE_URL; ?>user_audit_report.php">Users Activities</a>
        <a href="<?php echo BASE_URL; ?>parents_id.php">View Parents IDs</a>
        <a href="<?php echo BASE_URL; ?>manage_complaint.php?from_date=<?php echo date('Y-m-01'); ?>&to_date=<?php echo date('Y-m-d'); ?>&status=All&type=All&addaccountAdmin=1">Complaint</a>
    </div>
</div>

<div class="message">
    <div id="div1" onclick="sms_show();"></div>
    <div id="csr_whatsapp_wrap" style="position:relative;height:auto;z-index:4;">
        <a href="https://wa.me/923000228123" target="_blank" rel="noopener" class="chip sms-chip" title="Chat with your Support Representative (Mubeen Arshad) on WhatsApp">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>
    <div id="msg_wrap" style="position:relative;height:auto;z-index:5;">
        <div class="chip sms-chip" onclick="msg_show();" title="Message / SMS Usage">
            <i class="fa fa-envelope"></i>
        </div>
        <div class="user-dropdown dropdown-card" id="msg_show_hide" style="display:none;z-index:2080;width:24em;position:absolute;right:0;top:calc(100% + 8px);">
            <div class="dropdown-header"><i class="fa fa-signal"></i> SMS Usage</div>
            <div class="usage-card">
                <div class="usage-title" style="color:#25D366;"><i class="fab fa-whatsapp"></i> WhatsApp SMS</div>
                <div class="usage-metrics"><span>Used: <?php echo $topSmsUsed; ?></span><span>Limit: <?php echo $topSmsLimit; ?></span></div>
                <div class="usage-bar"><div class="usage-bar-fill" style="width:<?php echo $topSmsPct; ?>%;background:#25D366;"></div></div>
            </div>
            <div class="usage-card">
                <div class="usage-title" style="color:#007bff;"><i class="fa fa-mobile"></i> SIM SMS</div>
                <div class="usage-metrics"><span>Used: <?php echo $topSimUsed; ?></span><span>Limit: <?php echo number_format($topSimLimit); ?></span></div>
                <div class="usage-bar"><div class="usage-bar-fill" style="width:<?php echo $topSimPct; ?>%;background:#007bff;"></div></div>
            </div>
            <div class="usage-card">
                <div class="usage-title"><i class="fa fa-bar-chart"></i> Total SMS Usage (This Month)</div>
                <div class="usage-metrics"><span>WhatsApp: <?php echo $topSmsUsed; ?></span><span>SIM: <?php echo $topSimUsed; ?></span></div>
                <div class="usage-metrics" style="margin-top:4px;"><span>Total: <?php echo $topSmsUsed + $topSimUsed; ?></span><span>Remaining: <?php echo number_format(($topSmsLimit - $topSmsUsed) + ($topSimLimit - $topSimUsed)); ?></span></div>
            </div>
            <a class="dropdown-item" href="<?php echo BASE_URL; ?>messages_history.php"><i class="fa fa-history"></i> View Messages History</a>
        </div>
    </div>
    <div id="div2" style="position:relative;height:auto;z-index:6;">
        <div class="chip sms-chip" onclick="sms_show();" title="Notifications">
            <i class="fa fa-bell"></i>
            <?php if ($topNewComp > 0): ?><span class="icon-button__badge ds-badge"><?php echo min($topNewComp, 9); ?></span><?php endif; ?>
        </div>
        <div class="user-dropdown dropdown-card" id="sms_show_hide" style="display:none;z-index:2080;width:26em;position:absolute;right:-10em;top:calc(100% + 8px);">
            <div class="dropdown-header"><i class="fa fa-bell"></i> Notifications</div>
            <div class="dropdown-list"></div>
        </div>
    </div>
    <div class="chip user-chip mobile-profile" onclick="show_hide();">
        <div class="user-dropdown dropdown-card" id="logout_btn_mobile" style="display:none;">
            <div class="dropdown-header"><i class="fa fa-user-circle"></i> Account</div>
            <div class="dropdown-section-title">Profile</div>
            <a href="<?php echo BASE_URL; ?>update_profile.php" class="dropdown-item"><i class="fa fa-user"></i> Profile</a>
            <a href="<?php echo BASE_URL; ?>customer_tickets.php" class="dropdown-item"><i class="fa fa-ticket-alt"></i> Support Tickets</a>
            <div class="dropdown-section-title">Invoices</div>
            <a href="<?php echo BASE_URL; ?>monthly_invoices.php" class="dropdown-item"><i class="fa fa-file-alt"></i> Monthly Invoices</a>
            <div class="dropdown-section-title">Security</div>
            <a href="<?php echo BASE_URL; ?>update_pswd.php" class="dropdown-item"><i class="fa fa-key"></i> Change Password</a>
            <div class="dropdown-section-title">Session</div>
            <a href="<?php echo BASE_URL; ?>logout.php" class="dropdown-item logout"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
        <div class="avtar"><?php echo strtoupper(substr($topUserName, 0, 1)); ?></div>
        <div class="user-info">
            <span class="user-name"><?php echo e($topUserName); ?></span>
            <span class="user-designation" style="<?php echo $topRoleStyle; ?> padding:1px 7px !important; border-radius:999px;"><?php echo $topRoleLabel; ?></span>
        </div>
    </div>
</div>

<div class="chip user-chip desktop-profile" onclick="show_hide();">
    <div class="user-dropdown dropdown-card" id="logout_btn_desktop" style="display:none;">
        <div class="dropdown-header"><i class="fa fa-user-circle"></i> Account</div>
        <div class="dropdown-section-title">Profile</div>
        <a href="<?php echo BASE_URL; ?>update_profile.php" class="dropdown-item"><i class="fa fa-user"></i> Profile</a>
        <a href="<?php echo BASE_URL; ?>customer_tickets.php" class="dropdown-item"><i class="fa fa-ticket-alt"></i> Support Tickets</a>
        <div class="dropdown-section-title">Invoices</div>
        <a href="<?php echo BASE_URL; ?>monthly_invoices.php" class="dropdown-item"><i class="fa fa-file-alt"></i> Monthly Invoices</a>
        <div class="dropdown-section-title">Security</div>
        <a href="<?php echo BASE_URL; ?>update_pswd.php" class="dropdown-item"><i class="fa fa-key"></i> Change Password</a>
        <div class="dropdown-section-title">Session</div>
        <a href="<?php echo BASE_URL; ?>logout.php" class="dropdown-item logout"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="avtar"><?php echo strtoupper(substr($topUserName, 0, 1)); ?></div>
    <div class="user-info">
        <span class="user-name"><?php echo e($topUserName); ?></span>
        <span class="user-designation" style="<?php echo $topRoleStyle; ?> padding:1px 7px !important; border-radius:999px;"><?php echo $topRoleLabel; ?></span>
    </div>
</div>

</div>
</nav>
</div>
</div>
<!-- /top navigation -->

<script>
var a,b;
function showResult(str) {
    if (!str || str.length == 0) { document.getElementById("livesearch").innerHTML=""; document.getElementById("livesearch").style.display="none"; return; }
    if (str.length > 100) str = str.substring(0, 100);
    str = str.replace(/[<>\"'&]/g, function(m) {
        switch(m) { case '<': return '&lt;'; case '>': return '&gt;'; case '"': return '&quot;'; case "'": return '&#x27;'; case '&': return '&amp;'; default: return m; }
    });
    var xmlhttp = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById("livesearch").innerHTML = this.responseText;
            document.getElementById("livesearch").style.display = "block";
        }
    };
    xmlhttp.open("GET", "<?php echo BASE_URL; ?>livesearch.php?q=" + encodeURIComponent(str), true);
    xmlhttp.send();
}
function show_hide(){
    if(a==1) {
        var m = document.getElementById("logout_btn_mobile"); if(m) m.style.display = "inline";
        var d = document.getElementById("logout_btn_desktop"); if(d) d.style.display = "inline";
        return a=0;
    } else {
        var m = document.getElementById("logout_btn_mobile"); if(m) m.style.display = "none";
        var d = document.getElementById("logout_btn_desktop"); if(d) d.style.display = "none";
        return a=1;
    }
}
function sms_show(){
    if(a==1) {
        var msg = document.getElementById("msg_show_hide"); if(msg) msg.style.display = "none";
        document.getElementById("sms_show_hide").style.display="inline";
        return a=0;
    } else {
        document.getElementById("sms_show_hide").style.display="none";
        return a=1;
    }
}
function msg_show(){
    if(b==1) {
        var n = document.getElementById("sms_show_hide"); if(n) n.style.display = "none";
        document.getElementById("msg_show_hide").style.display="inline";
        return b=0;
    } else {
        document.getElementById("msg_show_hide").style.display="none";
        return b=1;
    }
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.chip')) {
        var m = document.getElementById("logout_btn_mobile"); if(m) m.style.display = "none";
        var d = document.getElementById("logout_btn_desktop"); if(d) d.style.display = "none";
        var msg = document.getElementById("msg_show_hide"); if(msg) msg.style.display = "none";
        var sms = document.getElementById("sms_show_hide"); if(sms) sms.style.display = "none";
    }
});
var quickLinkBtn = document.getElementById("quickLinkBtn");
if (quickLinkBtn) {
    quickLinkBtn.addEventListener("click", function() {
        var dd = document.getElementById("dropdownContent");
        var open = dd.style.display === "block";
        dd.style.display = open ? "none" : "block";
        quickLinkBtn.setAttribute("aria-expanded", open ? "false" : "true");
    });
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.quick-link-btn')) {
            var dd = document.getElementById("dropdownContent");
            if (dd) dd.style.display = "none";
        }
    });
}
</script>