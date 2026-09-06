<?php if (!defined('HIIFI')) exit('Direct access not allowed.'); ?>
<!DOCTYPE html><html lang="en"><head>
    <title><?php echo isset($page_title) ? e($page_title) . ' | HIIFI LMS' : 'HIIFI LMS'; ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/img/favicon.png">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/customizedStyling.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/custom.min1.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/style11.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/font-awesome-5.min.css" rel="stylesheet">
    <style>
    .col-md-3.left_col { display: contents !important; width: auto !important; padding: 0 !important; margin: 0 !important; float: none !important; }
    </style>
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
</style></head>
<body class="sidebar-expanded">
    <style>
  :root {
    --sb-collapsed: 90px;
    --sb-expanded: 244px;
    --sb-bg: #2A3F54;
    --sb-fly: #1f3548;
    --text: #ECEFF1;
  }
  .left_col {
    position: fixed !important;
    left: 0;
    top: 0;
    height: 100%;
    background: var(--sb-bg);
    z-index: 1000;
    transition: width .25s ease;
    border-right: 1px solid rgba(255, 255, 255, 0.05);
    overflow-y: auto;
  }
  body.sidebar-collapsed .left_col { width: var(--sb-collapsed); }
  body.sidebar-expanded .left_col { width: var(--sb-expanded); }
  body.sidebar-collapsed .right_col { margin-left: var(--sb-collapsed) !important; transition: margin-left .25s ease; width: calc(100% - var(--sb-collapsed)); }
  body.sidebar-expanded .right_col { margin-left: var(--sb-expanded) !important; transition: margin-left .25s ease; width: calc(100% - var(--sb-expanded)); }
  .right_col { position: relative; min-height: 100vh; box-sizing: border-box; }
  .main_content, .content-wrapper, .dashboard-content { position: relative; z-index: 1; }
  .ds-brand { display: flex; align-items: center; justify-content: center; padding: 10px 12px; height: 60px; box-sizing: border-box; }
  .ds-brand .ds-toggle { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; color: #fff; }
  .ds-brand .ds-toggle:hover { background: rgba(255, 255, 255, 0.08); }
  .ds-branch { color: #fff; text-align: center; padding: 6px 8px; font-size: 12px; opacity: .9; }
  body.sidebar-collapsed .ds-branch { display: none; }
  #sidebar-menu { padding: 6px 0 16px; }
  #sidebar-menu .side-menu { list-style: none; margin: 0; padding: 0; }
  #sidebar-menu .side-menu>li { position: relative; }
  #sidebar-menu .side-menu>li>a {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: var(--text);
    text-decoration: none; border-radius: 10px; margin: 4px 8px; white-space: nowrap;
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif;
    font-weight: 500; font-size: 14px; letter-spacing: 0.2px; transition: all 0.2s ease;
  }
  #sidebar-menu .side-menu>li>a:hover { background: rgba(255, 255, 255, 0.08); transform: translateX(2px); }
  #sidebar-menu .side-menu>li>a i { min-width: 20px; text-align: center; font-size: 18px; opacity: 0.9; }
  #sidebar-menu .side-menu>li>a .chev { margin-left: auto; opacity: .6; transition: transform 0.2s ease; }
  body.sidebar-collapsed #sidebar-menu .side-menu>li>a { justify-content: center; font-size: 0; padding: 8px 6px; flex-direction: column; align-items: center; gap: 0; }
  body.sidebar-collapsed #sidebar-menu .side-menu>li>a i { font-size: 18px; line-height: 1; }
  body.sidebar-collapsed #sidebar-menu .side-menu>li>a .label { display: block; font-size: 10px; margin-top: 2px; line-height: 1.05; max-width: 64px; text-align: center; white-space: normal; }
  body.sidebar-collapsed #sidebar-menu .side-menu>li>a .chev { display: none; }
  .child_menu { display: none; list-style: none; margin: 0; padding: 6px 0; background: var(--sb-fly); box-shadow: 0 8px 24px rgba(0, 0, 0, .25); border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.06); }
  .child_menu li a { display: block; padding: 10px 16px; font-size: 13px; color: #fff; text-decoration: none; font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif; font-weight: 400; letter-spacing: 0.1px; transition: all 0.2s ease; border-radius: 6px; margin: 2px 6px; }
  .child_menu li a:hover { background: rgba(255, 255, 255, 0.08); transform: translateX(3px); color: #fff; }
  body.sidebar-expanded #sidebar-menu .side-menu>li.active>.child_menu { display: block; position: static; margin: 2px 8px 8px 44px; border-radius: 8px; animation: slideIn 0.2s ease; }
  body.sidebar-collapsed #sidebar-menu .side-menu>li>.child_menu { position: fixed; left: var(--sb-collapsed); min-width: 220px; max-height: 80vh; overflow: auto; display: none; z-index: 2001; border: 1px solid rgba(255, 255, 255, 0.05); }
  @media (max-width: 768px) {
    .left_col { width: var(--sb-collapsed) !important; }
    body.sidebar-collapsed .right_col { margin-left: var(--sb-collapsed) !important; width: calc(100% - var(--sb-collapsed)) !important; }
    #sidebar-menu .side-menu>li>a { min-height: 44px; padding: 12px 8px; }
    body.sidebar-collapsed #sidebar-menu .side-menu>li>.child_menu { left: var(--sb-collapsed); min-width: 200px; max-width: calc(100vw - var(--sb-collapsed) - 20px); }
    .child_menu { font-size: 14px; }
    .child_menu li a { padding: 12px 16px; min-height: 44px; display: flex; align-items: center; }
  }
  body.sidebar-expanded #sidebar-menu .side-menu>li>a .label { font-weight: 500; font-size: 14px; color: #fff; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1); }
  body.sidebar-expanded #sidebar-menu .side-menu>li>a .chev { font-size: 12px; transition: transform 0.2s ease; }
  body.sidebar-expanded #sidebar-menu .side-menu>li:hover>a .chev { transform: rotate(90deg); }
  .ds-branch { color: #fff; text-align: center; padding: 8px 12px; font-size: 11px; font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif; font-weight: 500; opacity: .9; letter-spacing: 0.3px; text-transform: uppercase; border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 8px; }
  @keyframes slideIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
  .ds-logout { margin: 12px; }
  .ds-logout a { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 14px; text-decoration: none; color: #fff; background: transparent; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 10px; transition: all .2s ease; }
  .ds-logout a:hover { background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.3); }
  .left_col::-webkit-scrollbar { width: 2px; }
  .left_col::-webkit-scrollbar-track { background: transparent; }
  .left_col::-webkit-scrollbar-thumb { background: linear-gradient(180deg, rgb(67, 67, 68), rgb(241, 243, 245)); border-radius: 10px; }
  .left_col::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, rgb(86, 85, 87), rgb(237, 232, 247)); }
  .left_col { scrollbar-width: thin; scrollbar-color: rgb(100, 98, 101) transparent; border-top-right-radius: 1%; border-bottom-right-radius: 1%; }
  .sidebar-logo { text-align: center; padding: 10px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
  .sidebar-logo img { height: 54px; width: 54px; border-radius: 50%; object-fit: cover; border: 2px solid #ff8c00; box-shadow: 0 4px 14px rgba(255, 140, 0, 0.35); background: #fff; display: block; margin: 0 auto; }
  #ios_toggle_btn { display: none; position: fixed; top: 12px; left: 12px; z-index: 2004; width: 42px; height: 42px; border-radius: 10px; border: none; background: var(--sb-bg); color: #fff; font-size: 18px; cursor: pointer; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(0, 0, 0, .3); }
  #menu-ios { display: none; padding: 8px 10px 16px; }
  .ios-nav .nav-item { display: flex; align-items: center; gap: 0; text-decoration: none; color: #fff; padding: 12px 16px; border-radius: 8px; margin: 2px 6px; background: transparent; }
  .ios-nav .nav-item:active { background: rgba(255, 255, 255, 0.08); }
  .ios-nav .nav-item .nav-icon { display: none; }
  .ios-nav .nav-item .nav-text { font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif; font-weight: 600; font-size: 15px; letter-spacing: .2px; }
  .ios-nav .submenu { display: none; background: var(--sb-fly); border: 1px solid rgba(255, 255, 255, .05); border-radius: 10px; margin: 4px 8px 8px 12px; padding: 6px 0; box-shadow: 0 8px 24px rgba(0, 0, 0, .25); }
  .ios-nav .submenu-item { display: block; padding: 12px 16px; font-size: 14px; color: #fff; text-decoration: none; border-radius: 6px; margin: 2px 6px; }
  .ios-nav .submenu-item:active { background: rgba(255, 255, 255, 0.08); }
  #ios_sidebar_backdrop { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, .45); z-index: 2002; }
  body.ios-device .left_col { transform: translateX(-100%); transition: transform .25s ease; width: 260px !important; z-index: 2003; }
  body.ios-device.ios-sidebar-open .left_col { transform: translateX(0); }
  body.ios-device .right_col { margin-left: 0 !important; width: 100% !important; }
  body.ios-device #ios_toggle_btn { display: inline-flex; }
  body.ios-device.ios-sidebar-open #ios_sidebar_backdrop { display: block; }
  body.ios-device #menu-web { display: none; }
  body.ios-device #menu-ios { display: block; }
</style>

<button id="ios_toggle_btn" aria-label="Toggle sidebar"><i class="fa fa-bars"></i></button>
<div class="left_col scroll-view" id="dsLeftCol">
  <div class="sidebar-logo">
    <img src="<?php echo BASE_URL; ?>assets/img/logo.jpg" alt="Logo">
  </div>
  <div class="ds-branch">
    <div style="font-weight:700; font-size:12px;"><?php echo e(get_setting('school_name', 'HIIFI LMS')); ?></div>
    <div style="font-size:12px;">(<?php echo e(get_setting('session_year', '2026-2027')); ?>)</div>
  </div>
  <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
    <ul class="side-menu" id="menu-web">
      <li><a href="<?php echo BASE_URL; ?>software_demo_videos.php"><i class="fa fa-play"></i><span class="label" style="font-weight: normal !important;">Guidline Videos</span></a></li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-phone"></i><span class="label" style="font-weight: normal !important;">Front Office</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>front_desk_analytics.php">Front Desk Overview</a></li>
          <li><a href="<?php echo BASE_URL; ?>student_inquiry.php">Admission Inquiries</a></li>
          <li><a href="<?php echo BASE_URL; ?>manage_complaint.php">Complaint Hub</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-tachometer-alt"></i><span class="label" style="font-weight: normal !important;">Dashboard</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>dashboard.php">Executive Dashboard</a></li>
          <li><a href="<?php echo BASE_URL; ?>basic_dashboard.php">Staff Dashboard</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-users"></i><span class="label" style="font-weight: normal !important;">Students</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>add_student.php">Add New Student</a></li>
          <li><a href="<?php echo BASE_URL; ?>students_analytics_dashboard.php">Student Analytics</a></li>
          <li><a href="<?php echo BASE_URL; ?>class_promotion.php">Class Promotion</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-microphone"></i><span class="label" style="font-weight: normal !important;">Attendance</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>mark_attend.php">Mark Attendance</a></li>
          <li><a href="<?php echo BASE_URL; ?>mark_attendanceReport_list.php">Attendance Analytics</a></li>
          <li><a href="<?php echo BASE_URL; ?>send_msgs.php?attendance=A">Send SMS Report</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-envelope"></i><span class="label" style="font-weight: normal !important;">Messages</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>new_message.php">New Message</a></li>
          <li><a href="<?php echo BASE_URL; ?>messages_history.php">View Messages</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_templates.php">View Templates</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-money"></i><span class="label" style="font-weight: normal !important;">Fee Collection</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>monthly_challan.php">Create Challan</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_challan_details.php">View Challan</a></li>
          <li><a href="<?php echo BASE_URL; ?>multi_fee_reports.php">Fee Reporting</a></li>
          <li><a href="<?php echo BASE_URL; ?>update_fee_settings.php">Fee Settings</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-graduation-cap"></i><span class="label" style="font-weight: normal !important;">Examination</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>view_marksheet.php">Add Marks Sheet</a></li>
          <li><a href="<?php echo BASE_URL; ?>reportcards.php">View Marks Sheet</a></li>
          <li><a href="<?php echo BASE_URL; ?>manage_exams.php">Academic Setting</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-clock-o"></i><span class="label" style="font-weight: normal !important;">Timetable</span><span class="fa fa-chevron-right chev"></span></a>
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
        <a href="javascript:void(0)"><i class="fa fa-user"></i><span class="label" style="font-weight: normal !important;">Employees/HRM</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>add_emp.php">Add Employee</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_emp.php">View Employees</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_emp_attendance.php">Staff Attendance</a></li>
          <li><a href="<?php echo BASE_URL; ?>monthly_attendance.php">Attendance Report</a></li>
          <li><a href="<?php echo BASE_URL; ?>old_employee.php">Old Employees</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-clock-o"></i><span class="label" style="font-weight: normal !important;">Datesheet</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>create_datesheet.php">Create Datesheet</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_datesheet.php">View Datesheet</a></li>
          <li><a href="<?php echo BASE_URL; ?>generate_rollnoSlips.php">Generate Roll No Slips</a></li>
          <li><a href="<?php echo BASE_URL; ?>syllabus_management.php">Syllabus Management</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-truck"></i><span class="label" style="font-weight: normal !important;">Transport</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>vehicles.php">Vehicles</a></li>
          <li><a href="<?php echo BASE_URL; ?>route.php">Routes</a></li>
          <li><a href="<?php echo BASE_URL; ?>vehicle_route.php">Assign Vehicles</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-book"></i><span class="label" style="font-weight: normal !important;">Library</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>list_books.php">Book List</a></li>
          <li><a href="<?php echo BASE_URL; ?>issue_return.php">Issue Return</a></li>
          <li><a href="<?php echo BASE_URL; ?>issue_return_employee.php">Employee Issue&amp;Return</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fab fa-paypal"></i><span class="label" style="font-weight: normal !important;">PayRoll</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>creat_payroll.php">Create PayRoll</a></li>
          <li><a href="<?php echo BASE_URL; ?>view_payroll.php">View PayRoll</a></li>
          <li><a href="<?php echo BASE_URL; ?>staff_security.php">Staff Security Fee</a></li>
          <li><a href="<?php echo BASE_URL; ?>payroll_setting.php">PayRoll Setting</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-home"></i><span class="label" style="font-weight: normal !important;">Parents Portal</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>parents_portal_dashboard.php">Parents Overview</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-money"></i><span class="label" style="font-weight: normal !important;">Expenses</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>manage_expenses.php">Add/View Expenses</a></li>
          <li><a href="<?php echo BASE_URL; ?>monthly_expenses_report.php">Expenses Report</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-file"></i><span class="label" style="font-weight: normal !important;">Cards Generator</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>cards.php">Staff Cards</a></li>
          <li><a href="<?php echo BASE_URL; ?>students_card.php">Students Cards</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-search"></i><span class="label" style="font-weight: normal !important;">Point of Sale</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>canteen_dashboard.php">POS Dashboard</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-dollar"></i><span class="label" style="font-weight: normal !important;">Academic Setup</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>academic_setup.php">Manage Academics</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-gear"></i><span class="label" style="font-weight: normal !important;">System Settings</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>settings.php">Update Settings</a></li>
          <li><a href="<?php echo BASE_URL; ?>manage_localities.php">Manage Localities</a></li>
        </ul>
      </li>
      <li class="has-children">
        <a href="javascript:void(0)"><i class="fa fa-calculator"></i><span class="label" style="font-weight: normal !important;">Accounts</span><span class="fa fa-chevron-right chev"></span></a>
        <ul class="child_menu" style="display: none;">
          <li><a href="<?php echo BASE_URL; ?>add_revenue.php">Add Revenue</a></li>
          <li><a href="<?php echo BASE_URL; ?>revenue_list.php">List of Revenues</a></li>
          <li><a href="<?php echo BASE_URL; ?>revenue_heads.php">Revenue Heads</a></li>
        </ul>
      </li>
      <li class="ds-logout">
        <a href="#" onclick="localStorage.clear(); window.location.href='<?php echo BASE_URL; ?>logout.php'; return false;">
          <span>LOGOUT</span> <i class="fa fa-sign-out" aria-hidden="true"></i>
        </a>
      </li>
    </ul>

    <!-- iOS simple menu with requested structure -->
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
        <div class="nav-icon"><i class="fa fa-dashboard"></i></div>
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
$topSchoolName = get_setting('school_name', 'HIIFI LMS');
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
<style>
    .top_nav { width: 100%; background: #fff; border-bottom: 1px solid #eaecef; box-shadow: 0 2px 6px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 900; }
    .header { display:flex; align-items:center; justify-content: space-between; gap: 0; padding: 0 24px; height: 80px; background: #fff; }
    .header > * { display: flex; }
    .brand-info{ display:flex; flex-direction:column; justify-content:center; min-width:260px; padding:6px 10px; }
    .brand-title{ font-weight:700; font-size:16px; color:#212B36; }
    .brand-subtitle{ font-style:italic; color:#919EAB; font-size:13px; margin-top:2px; }
    .searchbar-container{ width: 20%; display:flex; align-items:center; justify-content:flex-start; margin-left:0; }
    .searchbar-container input{ width:100%; height:44px; padding:0 14px; border-radius:28px; background:#fff; border:1px solid rgba(145,158,171,0.20); box-shadow:0 6px 16px rgba(145,158,171,0.15); }
    #livesearch { position:absolute; top:100%; left:0; width:100%; background-color:white; z-index:902; padding:10px; border:1px solid rgba(145,158,171,0.20); box-shadow:0 6px 16px rgba(145,158,171,0.15); display:none; }
    .search-item { display:block; padding:8px 10px; border-bottom:1px solid #f0f0f0; color:#111; text-decoration:none; font-size:13px; }
    .search-item:last-child { border-bottom:none; }
    .search-item:hover,.search-item.active { background:#f4f6f8; }
    .search-item .student-name { font-weight:600; }
    .right-nav a{ text-decoration:none; display:flex; align-items:center; }
    .quick-link-btn{ position:relative; margin:0; flex-shrink:0; }
    .quick-link-btn .btn{ display:flex; align-items:center; height:36px; padding:6px 12px; border-radius:18px; }
    .message{ position:relative; display:flex; align-items:center; height:36px; flex-shrink:0; }
    .chip{ display:flex; align-items:center; justify-content:center; height:36px; border-radius:18px; padding:0 10px; cursor:pointer; }
    .sms-chip{ width:36px; padding:0; }
    .user-chip{ gap:8px; }
    .avtar{ width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#3949ab; color:#fff; font-weight:600; }
    .chip.user-chip{ position: relative; }
    #logout_btn_mobile, #logout_btn_desktop{ position: absolute; right: 0; top: 100%; z-index: 99999; width: 230px !important; box-sizing: border-box; border-radius: 14px; }
    @media (min-width: 769px){
      #logout_btn_desktop{ position: fixed !important; left: auto !important; right: 20px !important; top: 84px !important; width: 230px !important; }
    }
    #logout_btn_mobile .dropdown-header, #logout_btn_desktop .dropdown-header{ padding:9px 12px; font-size:12.5px; gap:6px; }
    #logout_btn_mobile .dropdown-header i, #logout_btn_desktop .dropdown-header i{ font-size:13px; color:#3949ab; }
    #logout_btn_mobile .dropdown-section-title, #logout_btn_desktop .dropdown-section-title{ padding:5px 12px 2px; font-size:9.5px; font-weight:700; }
    #logout_btn_mobile .dropdown-item, #logout_btn_desktop .dropdown-item{ padding:6px 12px; font-size:12.5px; gap:8px; transition:background .15s ease,padding-left .15s ease; }
    #logout_btn_mobile .dropdown-item:hover, #logout_btn_desktop .dropdown-item:hover{ background:#f4f6ff; padding-left:15px; }
    #logout_btn_mobile .dropdown-item i, #logout_btn_desktop .dropdown-item i{ width:20px; height:20px; min-width:20px; display:flex; align-items:center; justify-content:center; border-radius:6px; background:#f1f3f9; color:#475569; font-size:10.5px; }
    #logout_btn_mobile .dropdown-item:hover i, #logout_btn_desktop .dropdown-item:hover i{ background:#3949ab; color:#fff; }
    #logout_btn_mobile a[href*="logout"], #logout_btn_desktop a[href*="logout"]{ color:#dc2626; }
    #logout_btn_mobile a[href*="logout"] i, #logout_btn_desktop a[href*="logout"] i{ color:#dc2626; background:#fef2f2; }
    #logout_btn_mobile a[href*="logout"]:hover i, #logout_btn_desktop a[href*="logout"]:hover i{ background:#dc2626; color:#fff; }
    .user-info{ display:flex; flex-direction:column; line-height:1; }
    .user-name{ font-size:13px; font-weight:600; }
    .user-designation{ font-size:11px; opacity:.7; }
    @media (max-width: 1200px){ .user-info{ display:none; } }
    .mobile-profile { display: none !important; }
    .desktop-profile { display: flex !important; }
    .user-dropdown { background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 12px 32px rgba(15,23,42,0.12); overflow:hidden; }
    .dropdown-card { min-width:280px; }
    .dropdown-header { display:flex; align-items:center; gap:8px; padding:12px 14px; font-weight:600; color:#111827; border-bottom:1px solid #eef2f7; background:#f9fafb; }
    .dropdown-section-title { padding:8px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; background:#fafafa; border-top:1px solid #f1f5f9; }
    .dropdown-item { display:flex; align-items:center; gap:10px; padding:10px 14px; text-decoration:none; color:#111827; }
    .dropdown-item i { width:18px; text-align:center; color:#64748b; }
    .dropdown-item:hover { background:#f3f4f6; }
    .dropdown-list { margin:0; padding:0; list-style:none; }
    .usage-card { padding:12px 14px; border-bottom:1px solid #f1f5f9; }
    .usage-title { font-weight:600; font-size:12px; display:flex; align-items:center; gap:6px; }
    .usage-metrics { display:flex; justify-content:space-between; margin-top:6px; font-size:11px; color:#6b7280; }
    .usage-bar { width:100%; height:4px; background:#e5e7eb; border-radius:2px; margin-top:6px; }
    .usage-bar-fill { height:100%; border-radius:2px; }
    .dropdown-content { z-index:99999; position:absolute; top:100%; left:0; background:white; border:1px solid #ddd; border-radius:4px; box-shadow:0 4px 12px rgba(0,0,0,0.15); min-width:200px; display:none; }
    .dropdown-content a { display:block; padding:8px 12px; text-decoration:none; color:#333; font-size:13px; }
    .dropdown-content a:hover { background:#f0f0f0; }
    @media (max-width: 768px){
      .header{ flex-wrap:wrap; height:auto; padding:8px 12px; gap:8px; }
      .brand-info{ display:block !important; width:100% !important; min-width:auto !important; flex:1 1 100% !important; padding:0; }
      .brand-title{ font-size:18px !important; }
      .brand-subtitle{ font-size:12px !important; }
      .searchbar-container{ width:100% !important; flex:1 1 100% !important; }
      .quick-link-btn{ width:100% !important; }
      .quick-link-btn .btn{ width:100% !important; justify-content:space-between; }
      .message{ margin-left:0 !important; width:100% !important; display:flex !important; flex-direction:row !important; gap:8px !important; align-items:center !important; justify-content:center !important; flex-wrap:nowrap !important; }
      .chip.user-chip{ width:auto !important; flex-shrink:0 !important; }
      .mobile-profile { display: flex !important; }
      .desktop-profile { display: none !important; }
    }
</style>

<div class="top_nav">
<div class="nav_menu">
<nav>
<div class="flex header" style="padding:0px;">

<div class="brand-info" style="width:260px;min-width:180px;max-width:400px;flex:1 1 260px;margin-right:0;">
    <div class="brand-title" style="font-size:15px;font-weight:700;color:#212B36;line-height:1.2;"><?php echo e($topSchoolName); ?></div>
    <div class="brand-subtitle" style="font-size:14px;color:#637381;font-style:normal;margin-top:4px;"><?php echo e($topSession); ?></div>
</div>

<div class="searchbar-container" style="width:260px;min-width:180px;max-width:400px;flex:1 1 260px;margin:0 0px;padding-bottom:11px;margin-left:25px;position:relative;">
    <input type="text" id="filter" style="width:100%;height:44px;padding:0 14px;border-radius:28px;background:#fff;border:1px solid rgba(145,158,171,0.20);box-shadow:0 6px 16px rgba(145,158,171,0.15);" placeholder="Search Student with | Name | GR No | Family Code | Cell No" onkeyup="showResult(this.value)">
    <div id="livesearch" style="background-color:white;z-index:902;padding:10px;display:none;"></div>
</div>

<div class="quick-link-btn" style="margin-left:auto;margin-right:10px;">
    <button id="quickLinkBtn" class="btn" style="width:214px;height:44px;border:1px solid #e0e0e0;color:#637381;background:white;display:flex;align-items:center;justify-content:space-between;padding:0 20px;border-radius:22px;font-size:14px;font-weight:500;box-shadow:0 2px 4px rgba(0,0,0,0.05);" onmouseover="this.style.background='#FFA500';this.style.color='white';this.style.borderColor='#FFA500';this.style.boxShadow='0 4px 12px rgba(255,165,0,0.3)';" onmouseout="this.style.background='white';this.style.color='#637381';this.style.borderColor='#e0e0e0';this.style.boxShadow='0 2px 4px rgba(0,0,0,0.05)';">
        <span>Quick Links</span>
        <i class="fa fa-chevron-down" style="font-size:12px;margin-left:8px;"></i>
    </button>
    <div id="dropdownContent" class="dropdown-content">
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

<div class="message" style="margin-right:8px;">
    <div id="div1" style="position:absolute;top:0;left:0;z-index:2;" onclick="sms_show();"></div>
    <div id="csr_whatsapp_wrap" style="position:relative;height:auto;z-index:9999;margin-right:6px;">
        <a href="https://wa.me/923000228123" target="_blank" rel="noopener" class="chip sms-chip" style="width:35px;border:none;" title="Chat with your Support Representative (Mubeen Arshad) on WhatsApp">
            <i class="fab fa-whatsapp" style="font-size:19px;color:#25D366;"></i>
        </a>
    </div>
    <div id="msg_wrap" style="position:relative;height:auto;z-index:9999;margin-right:6px;">
        <div class="chip sms-chip" onclick="msg_show();" style="width:35px;border:none;">
            <i class="fa fa-envelope" style="font-size:18px;color:#808080;"></i>
        </div>
        <div class="user-dropdown dropdown-card" id="msg_show_hide" style="display:none;z-index:99999;width:24em;position:absolute;right:0;top:100%;">
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
    <div id="div2" style="position:relative;height:auto;z-index:9999;">
        <div class="chip sms-chip" onclick="sms_show();" style="width:35px;border:none;">
            <i class="fa fa-bell" style="font-size:18px;color:#808080;"></i>
        </div>
        <div class="user-dropdown dropdown-card" id="sms_show_hide" style="display:none;z-index:99999;width:26em;position:absolute;left:-20em;top:100%;">
            <div class="dropdown-header"><i class="fa fa-bell"></i> Notifications</div>
            <div class="dropdown-list"></div>
        </div>
    </div>
    <div class="chip user-chip mobile-profile" style="background:white;margin-left:8px;" onclick="show_hide();">
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
            <a href="<?php echo BASE_URL; ?>logout.php" class="dropdown-item"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </div>
        <div class="avtar"><?php echo strtoupper(substr($topUserName, 0, 1)); ?></div>
        <div class="user-info">
            <span class="user-name" style="color:black;"><?php echo e($topUserName); ?></span>
            <span style="display:inline-block;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;margin-top:2px;<?php echo $topRoleStyle; ?>"><?php echo $topRoleLabel; ?></span>
        </div>
    </div>
</div>

<div class="chip user-chip desktop-profile" style="background:white;" onclick="show_hide();">
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
        <a href="<?php echo BASE_URL; ?>logout.php" class="dropdown-item"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="avtar"><?php echo strtoupper(substr($topUserName, 0, 1)); ?></div>
    <div class="user-info">
        <span class="user-name" style="color:black;"><?php echo e($topUserName); ?></span>
        <span style="display:inline-block;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;margin-top:2px;<?php echo $topRoleStyle; ?>"><?php echo $topRoleLabel; ?></span>
    </div>
</div>

</div>
</nav>
</div>
</div>
<!-- /top navigation -->

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
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
        if (dd) dd.style.display = dd.style.display === "block" ? "none" : "block";
    });
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.quick-link-btn')) {
            var dd = document.getElementById("dropdownContent");
            if (dd) dd.style.display = "none";
        }
    });
}
</script>