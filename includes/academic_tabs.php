<?php
/* Shared tab bar for the Academic Setup section.
   Every page in this section includes this file, so the tabs can never drift
   apart again and the current page is always the highlighted one. */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}

/* Set this before including if the page needs a different container style. */
$academic_tabs_container_style = isset($academic_tabs_container_style) ? $academic_tabs_container_style : '';

/* The tab list is the single source of truth. */
$academic_tabs = array(
    'manage_exams.php'                => array('fa fa-plus', 'Manage Exams'),
    'subjects.php'                    => array('fa fa-book', 'Manage Subjects'),
    'class_subjects.php'              => array('fa fa-layer-group', 'Class Subjects'),
    'teacher_subjects_allocation.php' => array('fa fa-chalkboard-teacher', 'Teacher Subjects'),
    'create_awardList.php'            => array('fa fa-list', 'Award List'),
    'grades_marks.php'                => array('fa fa-star', 'Grade Settings'),
    'upload_signature.php'            => array('fa fa-signature', 'Academic Settings'),
    'manage_classes.php'              => array('fa fa-users', 'Class & Sections'),
);

/* Work out the active tab from the page being viewed. A page can force it by
   setting $academic_active before including this file. */
$academic_current = isset($academic_active) && $academic_active !== ''
    ? $academic_active
    : basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
if ($academic_current === '' || $academic_current === 'index.php') {
    $academic_current = basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
}
?>
<div class="nav-container"<?php echo $academic_tabs_container_style !== '' ? ' style="' . e($academic_tabs_container_style) . '"' : ''; ?>>
  <div class="nav-bar">
    <?php foreach ($academic_tabs as $academic_file => $academic_tab):
        $academic_is_active = ($academic_file === $academic_current); ?>
      <a href="<?php echo BASE_URL . $academic_file; ?>"
         class="nav-item<?php echo $academic_is_active ? ' active' : ''; ?>"
         <?php echo $academic_is_active ? 'aria-current="page"' : ''; ?>>
        <i class="<?php echo $academic_tab[0]; ?>" aria-hidden="true"></i><?php echo e($academic_tab[1]); ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
