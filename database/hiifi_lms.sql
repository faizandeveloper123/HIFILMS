-- ============================================================================
-- HIIFI / LAPS School & College — Complete Database (hiifi_lms)
-- MariaDB / MySQL 10.4+ (XAMPP)
--
-- This is the FULL, consolidated schema for the HIFILMS application.
-- It includes every table the PHP pages create at runtime (including
-- employee_removals and user_module_access) so the whole system works
-- out of the box with a SINGLE import. To import:
--
--   Option A (Command line):
--     mysql -u root -P 3306 < hiifi_lms.sql
--
--   Option B (phpMyAdmin):
--     1. Open http://localhost/phpmyadmin
--     2. Import tab -> choose this file -> Go
--
-- Default logins created after import:
--   kashif123@gmail.com / kash7395515   (admin)
--   laps@gmail.com      / Laps@2026     (admin)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `hiifi_lms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hiifi_lms`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Users / Login / Settings
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(191) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `full_name`  VARCHAR(191) NOT NULL DEFAULT '',
  `role`       ENUM('admin','staff','teacher','accounts') NOT NULL DEFAULT 'staff',
  `status`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(191) PRIMARY KEY,
  `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 2. Academic structure
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `class_heads` (
  `class_head_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `class_head_name` VARCHAR(191) NOT NULL,
  `status`          TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `classes` (
  `class_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `class_name`    VARCHAR(191) NOT NULL,
  `status`        TINYINT(1) NOT NULL DEFAULT 1,
  `monthly_fee`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `misc_fee`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at`    DATETIME DEFAULT NULL,
  `class_head_id` INT DEFAULT NULL,
  CONSTRAINT `fk_classes_head` FOREIGN KEY (`class_head_id`) REFERENCES `class_heads`(`class_head_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sections` (
  `section_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `class_id`     INT NOT NULL,
  `section_name` VARCHAR(50) NOT NULL,
  CONSTRAINT `fk_sections_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `subject_name` VARCHAR(191) NOT NULL,
  `subject_code` VARCHAR(50) DEFAULT NULL,
  `class_id`     INT DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_subjects_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `class_subjects` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `class_id`   INT NOT NULL,
  `section_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `session`    VARCHAR(50) NOT NULL DEFAULT '2026-2027',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cs` (`class_id`,`section_id`,`subject_id`),
  KEY `idx_cs_order` (`class_id`,`section_id`,`sort_order`),
  CONSTRAINT `fk_cs_class`  FOREIGN KEY (`class_id`)   REFERENCES `classes`(`class_id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_cs_section` FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cs_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `teacher_subjects` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT NOT NULL,
  `class_id`   INT NOT NULL,
  `section_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_ts` (`teacher_id`,`class_id`,`section_id`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `grade_scales` (
  `grade_id`        INT AUTO_INCREMENT PRIMARY KEY,
  `min_marks`       DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `max_marks`       DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `grade`           VARCHAR(10) NOT NULL,
  `remarks`         VARCHAR(191) NOT NULL,
  `teacher_remarks` VARCHAR(255) NOT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `report_templates` (
  `template_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(191) NOT NULL,
  `header_text` TEXT,
  `footer_text` TEXT,
  `options`     TEXT,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 3. Periods / Timetable
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `period_categories` (
  `id`   INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL,
  UNIQUE KEY `uq_period_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `periods` (
  `period_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `period_name` VARCHAR(191) NOT NULL,
  `start_time`  TIME DEFAULT NULL,
  `end_time`    TIME DEFAULT NULL,
  `class_id`    INT DEFAULT NULL,
  `category_id` INT DEFAULT NULL,
  CONSTRAINT `fk_periods_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_periods_cat`   FOREIGN KEY (`category_id`) REFERENCES `period_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `class_periods` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `class_id`      INT NOT NULL,
  `section_id`    INT DEFAULT NULL,
  `period_cat_id` INT DEFAULT NULL,
  UNIQUE KEY `uq_class_section` (`class_id`,`section_id`),
  CONSTRAINT `fk_cp_class`  FOREIGN KEY (`class_id`)   REFERENCES `classes`(`class_id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_cp_section` FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `timetable` (
  `timetable_id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id`     INT NOT NULL,
  `section_id`   INT DEFAULT NULL,
  `day`          VARCHAR(20) NOT NULL,
  `period_id`    INT DEFAULT NULL,
  `subject_id`   INT DEFAULT NULL,
  `teacher_id`   INT DEFAULT NULL,
  CONSTRAINT `fk_tt_class`   FOREIGN KEY (`class_id`)   REFERENCES `classes`(`class_id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_tt_section` FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tt_period`  FOREIGN KEY (`period_id`)  REFERENCES `periods`(`period_id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_tt_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Legacy table referenced by teacher_dashboard (only when it exists).
CREATE TABLE IF NOT EXISTS `class_period_selection` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `class_id`   INT DEFAULT NULL,
  `section_id` INT DEFAULT NULL,
  `subject_id` INT DEFAULT NULL,
  `period_id`  INT DEFAULT NULL,
  `teacher_id` INT DEFAULT NULL,
  `day`        VARCHAR(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 4. Students
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `student_id`             INT AUTO_INCREMENT PRIMARY KEY,
  `first_name`             VARCHAR(191) NOT NULL,
  `last_name`              VARCHAR(191) DEFAULT NULL,
  `father_name`            VARCHAR(191) DEFAULT NULL,
  `mother_name`            VARCHAR(191) DEFAULT NULL,
  `email`                  VARCHAR(191) DEFAULT NULL,
  `phone`                  VARCHAR(50) DEFAULT NULL,
  `dob`                    DATE DEFAULT NULL,
  `gender`                 ENUM('male','female','other') DEFAULT 'male',
  `religion`               VARCHAR(50) DEFAULT 'Islam',
  `session`                VARCHAR(50) DEFAULT NULL,
  `gr_no`                  VARCHAR(50) DEFAULT NULL,
  `board_council`          VARCHAR(100) DEFAULT NULL,
  `group_shift`            VARCHAR(100) DEFAULT NULL,
  `admission_source`       VARCHAR(100) DEFAULT NULL,
  `locality_id`            INT DEFAULT NULL,
  `route_id`               INT DEFAULT NULL,
  `father_cnic`            VARCHAR(50) DEFAULT NULL,
  `father_qualification`   VARCHAR(100) DEFAULT NULL,
  `father_business_address` TEXT,
  `father_income`          VARCHAR(50) DEFAULT NULL,
  `father_occupation`      VARCHAR(100) DEFAULT NULL,
  `father_cellno`          VARCHAR(50) DEFAULT NULL,
  `mother_cnic`            VARCHAR(50) DEFAULT NULL,
  `mother_qualification`   VARCHAR(100) DEFAULT NULL,
  `mother_activity`        VARCHAR(100) DEFAULT NULL,
  `mother_designation`     VARCHAR(100) DEFAULT NULL,
  `mother_cell`            VARCHAR(50) DEFAULT NULL,
  `form_b_no`              VARCHAR(50) DEFAULT NULL,
  `caste`                  VARCHAR(50) DEFAULT NULL,
  `guardian_name`          VARCHAR(191) DEFAULT NULL,
  `guardian_cnic`          VARCHAR(50) DEFAULT NULL,
  `guardian_cellno`        VARCHAR(50) DEFAULT NULL,
  `guardian_qualification` VARCHAR(100) DEFAULT NULL,
  `guardian_occupation`    VARCHAR(100) DEFAULT NULL,
  `guardian_income`        VARCHAR(50) DEFAULT NULL,
  `guardian_email`         VARCHAR(191) DEFAULT NULL,
  `guardian_address`       TEXT,
  `old_class`              VARCHAR(100) DEFAULT NULL,
  `old_school`             VARCHAR(191) DEFAULT NULL,
  `old_tmarks`             VARCHAR(50) DEFAULT NULL,
  `old_obtmarks`           VARCHAR(50) DEFAULT NULL,
  `admission_form_no`      VARCHAR(50) DEFAULT NULL,
  `school_leaving_reason`  VARCHAR(191) DEFAULT NULL,
  `whatsapp_number`        VARCHAR(50) DEFAULT NULL,
  `home_number`            VARCHAR(50) DEFAULT NULL,
  `place_of_birth`         VARCHAR(100) DEFAULT NULL,
  `state`                  VARCHAR(100) DEFAULT NULL,
  `city`                   VARCHAR(100) DEFAULT NULL,
  `address`                TEXT,
  `class_id`               INT DEFAULT NULL,
  `section_id`             INT DEFAULT NULL,
  `roll_no`                VARCHAR(50) DEFAULT NULL,
  `admission_date`         DATE DEFAULT NULL,
  `status`                 TINYINT(1) NOT NULL DEFAULT 1,
  `photo`                  VARCHAR(191) DEFAULT NULL,
  `family_code`            VARCHAR(50) DEFAULT NULL,
  `monthly_fee`            DECIMAL(12,2) NOT NULL DEFAULT 0,
  `old_balance`            DECIMAL(12,2) NOT NULL DEFAULT 0,
  `admission_no`           VARCHAR(50) DEFAULT NULL,
  `sibling_code`           VARCHAR(50) DEFAULT NULL,
  `course_package`         VARCHAR(191) DEFAULT NULL,
  `discount_package_id`    INT DEFAULT NULL,
  `course_package_id`      INT DEFAULT NULL,
  `transport_fee`          DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_reason`        VARCHAR(191) DEFAULT NULL,
  `miscellaneous_fee`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `payment_mode`           VARCHAR(30) DEFAULT 'monthly',
  `created_at`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_students_class`   FOREIGN KEY (`class_id`)   REFERENCES `classes`(`class_id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_students_section` FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_students_locality` FOREIGN KEY (`locality_id`) REFERENCES `localities`(`locality_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lookup tables for student admission
CREATE TABLE IF NOT EXISTS `boards` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(191) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `groups` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(191) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admission_sources` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(191) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `occupations` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(191) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `localities` (
  `locality_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `locality_name` VARCHAR(191) NOT NULL,
  `status`        TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `document_titles` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(191) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `student_documents` (
  `doc_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`  INT DEFAULT NULL,
  `doc_type`    VARCHAR(150) DEFAULT NULL,
  `doc_file`    VARCHAR(255) DEFAULT NULL,
  `file_path`   VARCHAR(255) DEFAULT NULL,
  `uploaded_by` INT DEFAULT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_studdoc_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `student_diaries` (
  `diary_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `class_id`   INT DEFAULT NULL,
  `section_id` INT DEFAULT NULL,
  `student_id` INT DEFAULT NULL,
  `subject`    VARCHAR(191) DEFAULT NULL,
  `diary_date` DATE DEFAULT NULL,
  `content`    LONGTEXT,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_diary_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `student_lectures` (
  `lecture_id`  INT AUTO_INCREMENT PRIMARY KEY,
  `class_id`    INT,
  `lecture_date` DATE,
  `subject`     VARCHAR(191),
  `content`     TEXT,
  `created_by`  INT,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `parent_access` (
  `access_id`  INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNIQUE,
  `username`   VARCHAR(191),
  `password`   VARCHAR(255),
  `status`     TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_parent_access_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 5. Student inquiries
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inquiries` (
  `inquiry_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(191) NOT NULL,
  `phone`      VARCHAR(50) DEFAULT NULL,
  `email`      VARCHAR(191) DEFAULT NULL,
  `class_id`   INT DEFAULT NULL,
  `message`    TEXT,
  `status`     ENUM('new','contacted','admitted','lost') DEFAULT 'new',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_inq_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `student_inquiries` (
  `inquiry_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `name`            VARCHAR(150) NOT NULL,
  `father_name`     VARCHAR(150) DEFAULT '',
  `phone`           VARCHAR(30) DEFAULT '',
  `father_cellno`   VARCHAR(30) DEFAULT '',
  `email`           VARCHAR(120) DEFAULT '',
  `class_id`        INT DEFAULT NULL,
  `section_id`      INT DEFAULT NULL,
  `session`         VARCHAR(20) DEFAULT '',
  `admission_source` VARCHAR(100) DEFAULT '',
  `locality`        VARCHAR(150) DEFAULT '',
  `address`         TEXT,
  `visit_date`      DATE DEFAULT NULL,
  `test_date`       DATE DEFAULT NULL,
  `test_time`       VARCHAR(20) DEFAULT '',
  `remarks`         TEXT,
  `status`          VARCHAR(30) DEFAULT 'New',
  `created_by`      INT NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_stinq_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inquiry_notes` (
  `note_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `inquiry_id` INT NOT NULL,
  `note`       TEXT NOT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_inot_inquiry` FOREIGN KEY (`inquiry_id`) REFERENCES `student_inquiries`(`inquiry_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inquiry_fee_vouchers` (
  `voucher_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `inquiry_id`   INT NOT NULL,
  `student_name` VARCHAR(191) DEFAULT '',
  `father_name`  VARCHAR(191) DEFAULT '',
  `class_id`     INT DEFAULT NULL,
  `amount`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_by`   INT DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 6. Attendance
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`    INT NOT NULL,
  `date`          DATE NOT NULL,
  `status`        ENUM('present','absent','late','leave','short_leave') NOT NULL DEFAULT 'present',
  `marked_by`     INT DEFAULT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_date` (`student_id`,`date`),
  CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qr_tokens` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT DEFAULT NULL,
  `user_type`  VARCHAR(20) NOT NULL DEFAULT 'student',
  `token`      VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uq_qr_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `qr_attendance` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `class_id`   INT DEFAULT NULL,
  `section_id` INT DEFAULT NULL,
  `scanned_at` DATETIME DEFAULT NULL,
  `method`     VARCHAR(20) DEFAULT 'qr',
  `device_ip`  VARCHAR(50) DEFAULT NULL,
  `status`     VARCHAR(20) DEFAULT 'present',
  CONSTRAINT `fk_qratt_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 7. Employees / HRM
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employees` (
  `emp_id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`                INT DEFAULT NULL,
  `first_name`             VARCHAR(191) NOT NULL,
  `last_name`              VARCHAR(191) DEFAULT NULL,
  `father_name`            VARCHAR(191) DEFAULT NULL,
  `email`                  VARCHAR(191) DEFAULT NULL,
  `phone`                  VARCHAR(50) DEFAULT NULL,
  `designation`            VARCHAR(191) DEFAULT NULL,
  `department`             VARCHAR(191) DEFAULT NULL,
  `dob`                    DATE DEFAULT NULL,
  `joining_date`           DATE DEFAULT NULL,
  `salary`                 DECIMAL(12,2) DEFAULT 0,
  `address`                TEXT,
  `status`                 TINYINT(1) NOT NULL DEFAULT 1,
  `photo`                  VARCHAR(191) DEFAULT NULL,
  `religion`               VARCHAR(50) DEFAULT NULL,
  `gender`                 VARCHAR(20) DEFAULT NULL,
  `blood_group`            VARCHAR(10) DEFAULT NULL,
  `cnic`                   VARCHAR(50) DEFAULT NULL,
  `marital_status`         VARCHAR(20) DEFAULT NULL,
  `qualification`          VARCHAR(191) DEFAULT NULL,
  `job_type`               VARCHAR(20) DEFAULT NULL,
  `contract_end`           DATE DEFAULT NULL,
  `class_head`             VARCHAR(191) DEFAULT NULL,
  `incharge_class`         INT DEFAULT NULL,
  `incharge_section`       INT DEFAULT NULL,
  `reg_no`                 VARCHAR(100) DEFAULT NULL,
  `bank_title`             VARCHAR(191) DEFAULT NULL,
  `bank_name`              VARCHAR(191) DEFAULT NULL,
  `bank_account`           VARCHAR(100) DEFAULT NULL,
  `home_phone`             VARCHAR(50) DEFAULT NULL,
  `postal_code`            VARCHAR(50) DEFAULT NULL,
  `allowance_traveling`    DECIMAL(12,2) DEFAULT 0,
  `allowance_reimbursement` DECIMAL(12,2) DEFAULT 0,
  `allowance_others`       DECIMAL(12,2) DEFAULT 0,
  `created_at`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_emp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_experience` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `designation` VARCHAR(191) DEFAULT NULL,
  `from_date`   DATE DEFAULT NULL,
  `to_date`     DATE DEFAULT NULL,
  `company`     VARCHAR(191) DEFAULT NULL,
  CONSTRAINT `fk_eexp_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`emp_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_security` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id`    INT NOT NULL,
  `month`          VARCHAR(7) NOT NULL,
  `security_amount` DECIMAL(10,2) DEFAULT 0,
  `paid`           DECIMAL(10,2) DEFAULT 0,
  `note`           VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY `uq_emp_month` (`employee_id`,`month`),
  CONSTRAINT `fk_esec_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`emp_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_attendance` (
  `attendance_id` INT AUTO_INCREMENT PRIMARY KEY,
  `emp_id`        INT NOT NULL,
  `date`          DATE NOT NULL,
  `status`        ENUM('present','absent','late','leave') NOT NULL DEFAULT 'present',
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_emp_date` (`emp_id`,`date`),
  CONSTRAINT `fk_eatt_emp` FOREIGN KEY (`emp_id`) REFERENCES `employees`(`emp_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `staff_attendance` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `att_date`    DATE NOT NULL,
  `status`      VARCHAR(10) NOT NULL DEFAULT 'present',
  `time_in`     TIME NULL,
  `time_out`    TIME NULL,
  UNIQUE KEY `uq_emp_attdate` (`employee_id`,`att_date`),
  CONSTRAINT `fk_satt_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`emp_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_removals` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `emp_id`       INT NOT NULL,
  `name`         VARCHAR(191) DEFAULT NULL,
  `phone`        VARCHAR(50) DEFAULT NULL,
  `reason`       VARCHAR(500) DEFAULT NULL,
  `removal_date` DATE DEFAULT NULL,
  `removed_by`   INT DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_er_emp` (`emp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_module_access` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `module`     VARCHAR(50) NOT NULL,
  `page`       VARCHAR(50) NOT NULL,
  `allowed`    TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_access` (`user_id`,`module`,`page`),
  CONSTRAINT `fk_uma_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 8. Payroll
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payroll` (
  `payroll_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `emp_id`       INT NOT NULL,
  `month`        VARCHAR(20) DEFAULT NULL,
  `year`         INT DEFAULT NULL,
  `basic_salary` DECIMAL(12,2) DEFAULT 0,
  `allowances`   DECIMAL(12,2) DEFAULT 0,
  `deductions`   DECIMAL(12,2) DEFAULT 0,
  `net_salary`   DECIMAL(12,2) DEFAULT 0,
  `status`       ENUM('pending','paid') DEFAULT 'pending',
  `paid_date`    DATE DEFAULT NULL,
  `p_bal`        DECIMAL(12,2) DEFAULT 0,
  `adv_amt`      DECIMAL(12,2) DEFAULT 0,
  `adv_dec`      DECIMAL(12,2) DEFAULT 0,
  `security`     DECIMAL(12,2) DEFAULT 0,
  `other_ded`    DECIMAL(12,2) DEFAULT 0,
  `absent`       DECIMAL(12,2) DEFAULT 0,
  `traveling`    DECIMAL(12,2) DEFAULT 0,
  `reimb`        DECIMAL(12,2) DEFAULT 0,
  `other_allow`  DECIMAL(12,2) DEFAULT 0,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payroll_emp` FOREIGN KEY (`emp_id`) REFERENCES `employees`(`emp_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 9. Fee / Finance
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_heads` (
  `head_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `head_name` VARCHAR(191) NOT NULL,
  `amount`    DECIMAL(12,2) NOT NULL DEFAULT 0,
  `class_id`  INT DEFAULT NULL,
  `status`    TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT `fk_feehead_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fee_challans` (
  `challan_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `challan_no`    VARCHAR(50) NOT NULL UNIQUE,
  `student_id`    INT NOT NULL,
  `class_id`      INT DEFAULT NULL,
  `month`         VARCHAR(20) DEFAULT NULL,
  `year`          INT DEFAULT NULL,
  `total_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0,
  `paid_amount`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_by`    INT DEFAULT NULL,
  `status`        ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `commitment`    TEXT,
  `commitment_date` DATE DEFAULT NULL,
  `fee_remarks`   VARCHAR(191) DEFAULT NULL,
  `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `reminder_sent_at` DATETIME DEFAULT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_challan_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fee_challan_items` (
  `item_id`     INT AUTO_INCREMENT PRIMARY KEY,
  `challan_id`  INT NOT NULL,
  `head_id`     INT DEFAULT NULL,
  `description` VARCHAR(191) DEFAULT NULL,
  `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount`    DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT `fk_ci_challan` FOREIGN KEY (`challan_id`) REFERENCES `fee_challans`(`challan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fee_payments` (
  `payment_id`     INT AUTO_INCREMENT PRIMARY KEY,
  `challan_id`     INT NOT NULL,
  `amount`         DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(50) DEFAULT 'cash',
  `received_by`    INT DEFAULT NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pay_challan` FOREIGN KEY (`challan_id`) REFERENCES `fee_challans`(`challan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `student_fee_plan` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `head_id`    INT DEFAULT NULL,
  `head_name`  VARCHAR(191) DEFAULT NULL,
  `amount`     DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_sfp_student` (`student_id`),
  CONSTRAINT `fk_sfp_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fee_discount_packages` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `name`             VARCHAR(191) NOT NULL,
  `discount_percent` DECIMAL(5,2) DEFAULT 0,
  `status`           TINYINT DEFAULT 1,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fee_course_packages` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(191) NOT NULL,
  `amount`     DECIMAL(12,2) DEFAULT 0,
  `status`     TINYINT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fee_discounts` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(191) NOT NULL,
  `type`       ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `value`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fee_commitments` (
  `commitment_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`       INT NOT NULL,
  `challan_id`       INT NOT NULL,
  `commitment_date`  DATE DEFAULT NULL,
  `commitment`       TEXT,
  `created_by`       INT DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_fc_student` (`student_id`),
  KEY `idx_fc_challan` (`challan_id`),
  CONSTRAINT `fk_fcommit_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fcommit_challan` FOREIGN KEY (`challan_id`) REFERENCES `fee_challans`(`challan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `revenue_heads` (
  `head_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `head_name` VARCHAR(191) NOT NULL,
  `status`    TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `revenue_heads` (`head_name`, `status`) VALUES
('Adm Fee', 1),
('Reg Fee', 1),
('Paper Fund', 1),
('Dummy Head 1', 1),
('Bus Head', 1),
('Donation from xyz', 1),
('Fee', 1);

CREATE TABLE IF NOT EXISTS `revenues` (
  `revenue_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`   INT DEFAULT NULL,
  `head_id`      INT DEFAULT NULL,
  `description`  VARCHAR(255) DEFAULT NULL,
  `amount`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `paid_by`      VARCHAR(50) DEFAULT 'Cash',
  `remarks`      VARCHAR(255) DEFAULT NULL,
  `paid_date`    DATE DEFAULT NULL,
  `revenue_date` DATE DEFAULT NULL,
  `created_by`   INT DEFAULT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_rev_head` FOREIGN KEY (`head_id`) REFERENCES `revenue_heads`(`head_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rev_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id`     INT AUTO_INCREMENT PRIMARY KEY,
  `name`   VARCHAR(191) NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `expense_subs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `expense_id` INT NULL,
  `name`       VARCHAR(191) NOT NULL,
  `status`     TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT `fk_esub_cat` FOREIGN KEY (`expense_id`) REFERENCES `expense_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `expenses` (
  `expense_id`     INT AUTO_INCREMENT PRIMARY KEY,
  `title`          VARCHAR(191) NOT NULL,
  `category`       VARCHAR(191) DEFAULT NULL,
  `category_id`    INT DEFAULT NULL,
  `sub_category_id` INT DEFAULT NULL,
  `amount`         DECIMAL(12,2) NOT NULL DEFAULT 0,
  `expense_date`   DATE DEFAULT NULL,
  `paid_by`        VARCHAR(50) DEFAULT 'Cash',
  `narration`      VARCHAR(255) DEFAULT NULL,
  `created_by`     INT DEFAULT NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_exp_cat` FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 10. Examinations
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exams` (
  `exam_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `exam_name`    VARCHAR(191) NOT NULL,
  `session`      VARCHAR(50) DEFAULT NULL,
  `exam_type`    VARCHAR(20) DEFAULT NULL,
  `display_mobile` VARCHAR(3) DEFAULT 'YES',
  `class_id`     INT DEFAULT NULL,
  `exam_date`    DATE DEFAULT NULL,
  `status`       TINYINT(1) DEFAULT 1,
  CONSTRAINT `fk_exam_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `marks` (
  `mark_id`         INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`      INT NOT NULL,
  `exam_id`         INT NOT NULL,
  `subject_id`      INT DEFAULT NULL,
  `obtained_marks`  DECIMAL(8,2) DEFAULT 0,
  `total_marks`     DECIMAL(8,2) DEFAULT 100,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_marks_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_marks_exam`    FOREIGN KEY (`exam_id`)    REFERENCES `exams`(`exam_id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `datesheet` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id`    INT NOT NULL,
  `class_id`   INT NOT NULL,
  `section_id` INT DEFAULT NULL,
  `subject_id` INT NOT NULL,
  `exam_date`  DATE DEFAULT NULL,
  `exam_time`  VARCHAR(80) DEFAULT NULL,
  UNIQUE KEY `uq_ds` (`exam_id`,`class_id`,`section_id`,`subject_id`),
  CONSTRAINT `fk_ds_exam`    FOREIGN KEY (`exam_id`)    REFERENCES `exams`(`exam_id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_ds_class`   FOREIGN KEY (`class_id`)   REFERENCES `classes`(`class_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ds_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `syllabus_entry` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `class_id`   INT NOT NULL,
  `section_id` INT NOT NULL DEFAULT 0,
  `subject_id` INT NOT NULL,
  `term_id`    INT NOT NULL,
  `syllabus`   TEXT,
  UNIQUE KEY `uq_syl` (`class_id`,`section_id`,`subject_id`,`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 11. Library
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `books` (
  `book_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `title`     VARCHAR(255) NOT NULL,
  `author`    VARCHAR(191) DEFAULT NULL,
  `category`  VARCHAR(191) DEFAULT NULL,
  `quantity`  INT DEFAULT 0,
  `available` INT DEFAULT 0,
  `status`    TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `book_issues` (
  `issue_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `book_id`    INT NOT NULL,
  `student_id` INT DEFAULT NULL,
  `issue_date` DATE DEFAULT NULL,
  `due_date`   DATE DEFAULT NULL,
  `return_date` DATE DEFAULT NULL,
  `status`     ENUM('issued','returned') DEFAULT 'issued',
  CONSTRAINT `fk_bi_book`    FOREIGN KEY (`book_id`)    REFERENCES `books`(`book_id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_bi_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 12. Transport
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicles` (
  `vehicle_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_no`   VARCHAR(50) NOT NULL,
  `vehicle_name` VARCHAR(191) DEFAULT NULL,
  `capacity`     INT DEFAULT 0,
  `driver_name`  VARCHAR(191) DEFAULT NULL,
  `status`       TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `routes` (
  `route_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `route_name` VARCHAR(191) NOT NULL,
  `fare`       DECIMAL(10,2) DEFAULT 0,
  `status`     TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vehicle_route` (
  `route_id`   INT NOT NULL PRIMARY KEY,
  `vehicle_id` INT NOT NULL,
  `assigned_on` DATE NULL,
  CONSTRAINT `fk_vr_route`   FOREIGN KEY (`route_id`)   REFERENCES `routes`(`route_id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_vr_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`vehicle_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 13. Point of Sale (Canteen)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_products` (
  `product_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `product_name` VARCHAR(120) NOT NULL,
  `price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock`        INT NOT NULL DEFAULT 0,
  `status`       TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pos_invoices` (
  `invoice_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no`    VARCHAR(40) NOT NULL,
  `customer_name` VARCHAR(120) DEFAULT NULL,
  `total_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(20) DEFAULT 'cash',
  `status`        VARCHAR(20) NOT NULL DEFAULT 'unpaid',
  `user_id`       INT DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pos_invoice_items` (
  `item_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id`   INT NOT NULL,
  `product_id`   INT DEFAULT NULL,
  `product_name` VARCHAR(120) NOT NULL,
  `qty`          INT NOT NULL DEFAULT 1,
  `price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  KEY `idx_posi_invoice` (`invoice_id`),
  CONSTRAINT `fk_posi_inv` FOREIGN KEY (`invoice_id`) REFERENCES `pos_invoices`(`invoice_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 14. Messages / SMS / WhatsApp
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `message_id`     INT AUTO_INCREMENT PRIMARY KEY,
  `title`          VARCHAR(191) DEFAULT NULL,
  `message`        TEXT,
  `recipient_type` VARCHAR(50) DEFAULT 'all',
  `channel`        VARCHAR(30) DEFAULT 'whatsapp',
  `recipient_list` TEXT,
  `status`         VARCHAR(20) DEFAULT 'pending',
  `message_type`   VARCHAR(20) DEFAULT 'english',
  `attachment`     VARCHAR(191) DEFAULT NULL,
  `template_title` VARCHAR(191) DEFAULT NULL,
  `created_by`     INT DEFAULT NULL,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `message_templates` (
  `template_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(191) NOT NULL,
  `body`        TEXT,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sms_templates` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(191) NOT NULL,
  `body`       TEXT,
  `channel`    VARCHAR(30) DEFAULT 'whatsapp',
  `status`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `whatsapp_logs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `phone`      VARCHAR(50) NOT NULL,
  `message`    TEXT NOT NULL,
  `type`       VARCHAR(30) DEFAULT 'general',
  `status`     VARCHAR(20) DEFAULT 'queued',
  `student_id` INT DEFAULT NULL,
  `parent_id`  INT DEFAULT NULL,
  `class_id`   INT DEFAULT NULL,
  `sent_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT DEFAULT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT,
  `type`       VARCHAR(30) DEFAULT 'general',
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `link`       VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 15. Tickets / Complaints / Visitors
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tickets` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_no`   VARCHAR(30) DEFAULT NULL,
  `subject`     VARCHAR(255) NOT NULL,
  `module`      VARCHAR(191) DEFAULT NULL,
  `priority`    VARCHAR(20) DEFAULT 'Medium',
  `description` TEXT,
  `status`      VARCHAR(20) DEFAULT 'open',
  `rating`      TINYINT(1) DEFAULT 0,
  `attachment`  VARCHAR(191) DEFAULT NULL,
  `created_by`  INT DEFAULT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `complaints` (
  `complaint_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_code`    VARCHAR(30) DEFAULT NULL,
  `subject`           VARCHAR(191) NOT NULL,
  `description`       TEXT,
  `complaint_type`    VARCHAR(50) DEFAULT 'general',
  `complainant_type`  VARCHAR(50) DEFAULT 'general',
  `complainant_name`  VARCHAR(191) DEFAULT NULL,
  `complainant_mobile` VARCHAR(50) DEFAULT NULL,
  `remarks`           TEXT,
  `status`            VARCHAR(30) NOT NULL DEFAULT 'new',
  `created_by`        INT DEFAULT NULL,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `visitors` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `visitor_name`   VARCHAR(255) NOT NULL,
  `cnic`           VARCHAR(30) DEFAULT NULL,
  `phone`          VARCHAR(30) DEFAULT NULL,
  `purpose`        VARCHAR(255) DEFAULT NULL,
  `person_to_meet` VARCHAR(255) DEFAULT NULL,
  `student_id`     INT DEFAULT NULL,
  `check_in`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  `check_out`      DATETIME DEFAULT NULL,
  `badge_number`   VARCHAR(30) DEFAULT NULL,
  `photo`          VARCHAR(255) DEFAULT NULL,
  `status`         VARCHAR(20) DEFAULT 'checked_in',
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 16. PTM / Lessons / AI / Audit
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ptm_meetings` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(255) NOT NULL,
  `meeting_date` DATE NOT NULL,
  `start_time`  TIME NOT NULL,
  `end_time`    TIME NOT NULL,
  `class_id`    INT DEFAULT NULL,
  `section_id`  INT DEFAULT NULL,
  `teacher_id`  INT DEFAULT NULL,
  `parent_id`   INT DEFAULT NULL,
  `notes`       TEXT DEFAULT NULL,
  `status`      VARCHAR(20) DEFAULT 'scheduled',
  `feedback`    TEXT DEFAULT NULL,
  `rating`      TINYINT DEFAULT NULL,
  `created_by`  INT DEFAULT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lesson_plans` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `teacher_id`  INT NOT NULL,
  `class_id`    INT NOT NULL,
  `section_id`  INT NOT NULL,
  `subject_id`  INT NOT NULL,
  `week_start`  DATE NOT NULL,
  `day`         VARCHAR(10) NOT NULL,
  `topic`       VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status`      ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `approved_by` INT DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_lp_week` (`week_start`),
  KEY `idx_lp_teacher` (`teacher_id`),
  KEY `idx_lp_class_section` (`class_id`,`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ai_chat_logs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT DEFAULT NULL,
  `user_role`  VARCHAR(30) DEFAULT NULL,
  `message`    TEXT NOT NULL,
  `response`   TEXT,
  `intent`     VARCHAR(50) DEFAULT NULL,
  `language`   VARCHAR(10) DEFAULT 'en',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_activity_log` (
  `log_id`     INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT DEFAULT NULL,
  `user_name`  VARCHAR(191) DEFAULT NULL,
  `action`     VARCHAR(191) DEFAULT NULL,
  `page`       VARCHAR(191) DEFAULT NULL,
  `details`    TEXT,
  `ip`         VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Initial Data
-- ============================================================================

-- Admin logins (password stored as SHA-256, matches index.php login logic)
INSERT INTO `users` (`email`, `password`, `full_name`, `role`, `status`) VALUES
('kashif123@gmail.com', SHA2('kash7395515', 256), 'System Administrator', 'admin', 1),
('laps@gmail.com', SHA2('Laps@2026', 256), 'LAPS Admin', 'admin', 1);

-- Default classes
INSERT INTO `classes` (`class_name`, `status`) VALUES
('Play Group', 1), ('KG-2', 1), ('1ST', 1), ('2ND', 1), ('3RD', 1), ('4TH', 1),
('5TH', 1), ('6TH', 1), ('7TH', 1), ('8TH', 1), ('9TH', 1), ('10TH', 1),
('BS Computer Science', 1), ('BSIT', 1), ('MIT', 1);

-- System settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('school_name', 'LAPS School & College'),
('school_tagline', 'Test Portal'),
('session_year', '2026-2027'),
('currency_symbol', 'Rs.');

-- Period categories
INSERT INTO `period_categories` (`name`) VALUES ('Primary'), ('Middle'), ('High'), ('BS');

-- Class heads / campuses
INSERT INTO `class_heads` (`class_head_name`, `status`) VALUES
('Hajvery Campus', 1), ('Main Campus', 1), ('Pharm-D', 1), ('Modern Edu', 1);

-- Discount packages (fee plan dropdown)
INSERT INTO `fee_discount_packages` (`name`, `discount_percent`, `status`) VALUES
('75', 75, 1), ('Sibling', 25, 1), ('Orphan', 50, 1);

-- Discount manager list
INSERT INTO `fee_discounts` (`name`, `type`, `value`, `status`) VALUES
('75', 'percentage', 75, 1), ('Subbling', 'percentage', 25, 1), ('Orphan', 'percentage', 50, 1);

-- Expense categories + sub categories
INSERT INTO `expense_categories` (`name`, `status`) VALUES ('Utilities', 1), ('Salaries', 1), ('Rent', 1),
('Transport', 1), ('Maintenance & Repair', 1), ('Stationery', 1), ('Advertisement / Marketing', 1),
('Events', 1), ('Banking & Finance', 1), ('Library', 1), ('Medical', 1), ('Security', 1),
('Software & IT', 1), ('Travel', 1), ('Other', 1);

INSERT INTO `expense_subs` (`expense_id`, `name`, `status`) VALUES
(1, 'Electricity', 1), (1, 'Gas', 1), (1, 'Water', 1), (1, 'Telephone', 1), (1, 'Internet', 1),
(2, 'Current Salary', 1), (2, 'Salary Advance', 1), (2, 'Out Source Wages', 1), (2, 'Visiting Faculty Payments', 1),
(3, 'Building Rent', 1), (3, 'Property Tax', 1),
(4, 'Fuel Expense', 1), (4, 'Vehicle Repair & Maintenance', 1), (4, 'Travelling & Transportation', 1),
(5, 'Repair & Maintenance', 1), (5, 'Building Maintenance', 1), (5, 'Maint. of AC', 1), (5, 'Maint. of Computer', 1), (5, 'Maint. of Printer', 1),
(6, 'Office Stationary', 1), (6, 'Paper', 1), (6, 'Print & Photocopy', 1), (6, 'Note Book', 1),
(7, 'Advertising', 1), (7, 'Printing of Prospectus', 1), (7, 'Social Media Marketing', 1), (7, 'Promotional Events', 1),
(8, 'Sports Gala', 1), (8, 'Fun Fair', 1), (8, 'Seerat Conference', 1), (8, 'Seminars & Workshops', 1),
(9, 'Bank Charges', 1), (9, 'Service Charges', 1), (9, 'Income Tax', 1), (9, 'Online Payment', 1),
(10, 'Book Exp', 1), (10, 'Library Resources', 1),
(11, 'Medical', 1), (11, 'Medicine Exp', 1),
(12, 'Security Services', 1), (12, 'Janitorial Staff', 1),
(13, 'Software Service Charges', 1), (13, 'Portal Monthly Charges', 1), (13, 'Server Hosting', 1), (13, 'Networking Accessories', 1),
(14, 'Travel Expense', 1), (14, 'Travelling / Bilty Charges', 1),
(15, 'Other''s', 1), (15, 'Misc. Expenses', 1);

-- Sample SMS / WhatsApp templates
INSERT INTO `sms_templates` (`title`, `body`, `channel`) VALUES
('Fee Reminder (Outstanding Balance)', 'Please be reminded of an outstanding fee balance of 5000. Kindly settle it at your earliest convenience.', 'whatsapp'),
('Fee Overdue', 'Your fee payment of 5500 is overdue. Kindly make the payment as soon as possible.', 'whatsapp'),
('Latecomer Notice', '(Latecomer Notice) Dear parents! Your child was late for school in the morning, please send your child according to school time. From: Principal', 'whatsapp'),
('Parent Teacher Meeting', 'Dear parents, a parent-teacher meeting is scheduled at school. Kindly ensure attendance. Regards, School Administration', 'whatsapp'),
('Independence Day', 'This message is for the Independence Day celebrations at school. Kindly join us. Regards, School Administration', 'whatsapp');

-- Default grade scale
INSERT INTO `grade_scales` (`min_marks`, `max_marks`, `grade`, `remarks`, `teacher_remarks`) VALUES
(80, 100, 'A+', 'Exceptional', 'Exceptional achievement! Continue to excel and inspire!'),
(70, 79.99, 'A', 'Excellent', 'Excellent performance! Keep up the outstanding work!'),
(60, 69.99, 'B', 'Good', 'Good work! Keep aiming higher to reach your full potential.'),
(50, 59.99, 'C', 'Fair', 'Satisfactory work, but there''s room for improvement. Keep going!'),
(33, 49.99, 'D', 'Pass', 'Shows potential but needs more consistent effort. Keep striving!'),
(0, 32.99, 'F', 'Failed', 'Needs improvement. Let''s work together to achieve success next term.');