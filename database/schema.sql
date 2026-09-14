-- ============================================================
-- HIIFI LMS Database Schema (EduPortal clone) — rebranded for LAPS School & College
-- MariaDB / MySQL 10.4+  (XAMPP)
-- ============================================================
CREATE DATABASE IF NOT EXISTS `hiifi_lms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hiifi_lms`;

-- ------------------------------------------------------------
-- Users / Login
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(191) NOT NULL DEFAULT '',
  `role` ENUM('admin','staff','teacher','accounts') NOT NULL DEFAULT 'staff',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Classes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `classes` (
  `class_id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_name` VARCHAR(191) NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Sections
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sections` (
  `section_id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `section_name` VARCHAR(50) NOT NULL,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Subjects
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `subject_name` VARCHAR(191) NOT NULL,
  `subject_code` VARCHAR(50) DEFAULT NULL,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Students
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` INT AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(191) NOT NULL,
  `last_name` VARCHAR(191) DEFAULT NULL,
  `father_name` VARCHAR(191) DEFAULT NULL,
  `mother_name` VARCHAR(191) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `dob` DATE DEFAULT NULL,
  `gender` ENUM('male','female','other') DEFAULT 'male',
  `religion` VARCHAR(50) DEFAULT 'Islam',
  `session` VARCHAR(50) DEFAULT NULL,
  `gr_no` VARCHAR(50) DEFAULT NULL,
  `board_council` VARCHAR(100) DEFAULT NULL,
  `group_shift` VARCHAR(100) DEFAULT NULL,
  `admission_source` VARCHAR(100) DEFAULT NULL,
  `locality_id` INT DEFAULT NULL,
  `father_cnic` VARCHAR(50) DEFAULT NULL,
  `father_qualification` VARCHAR(100) DEFAULT NULL,
  `father_business_address` TEXT,
  `father_income` VARCHAR(50) DEFAULT NULL,
  `father_occupation` VARCHAR(100) DEFAULT NULL,
  `father_cellno` VARCHAR(50) DEFAULT NULL,
  `mother_cnic` VARCHAR(50) DEFAULT NULL,
  `mother_qualification` VARCHAR(100) DEFAULT NULL,
  `mother_activity` VARCHAR(100) DEFAULT NULL,
  `mother_designation` VARCHAR(100) DEFAULT NULL,
  `mother_cell` VARCHAR(50) DEFAULT NULL,
  `form_b_no` VARCHAR(50) DEFAULT NULL,
  `caste` VARCHAR(50) DEFAULT NULL,
  `guardian_name` VARCHAR(191) DEFAULT NULL,
  `guardian_cnic` VARCHAR(50) DEFAULT NULL,
  `guardian_cellno` VARCHAR(50) DEFAULT NULL,
  `guardian_qualification` VARCHAR(100) DEFAULT NULL,
  `guardian_occupation` VARCHAR(100) DEFAULT NULL,
  `guardian_income` VARCHAR(50) DEFAULT NULL,
  `guardian_email` VARCHAR(191) DEFAULT NULL,
  `guardian_address` TEXT,
  `old_class` VARCHAR(100) DEFAULT NULL,
  `old_school` VARCHAR(191) DEFAULT NULL,
  `old_tmarks` VARCHAR(50) DEFAULT NULL,
  `old_obtmarks` VARCHAR(50) DEFAULT NULL,
  `admission_form_no` VARCHAR(50) DEFAULT NULL,
  `school_leaving_reason` VARCHAR(191) DEFAULT NULL,
  `whatsapp_number` VARCHAR(50) DEFAULT NULL,
  `home_number` VARCHAR(50) DEFAULT NULL,
  `place_of_birth` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `address` TEXT,
  `class_id` INT DEFAULT NULL,
  `section_id` INT DEFAULT NULL,
  `roll_no` VARCHAR(50) DEFAULT NULL,
  `admission_date` DATE DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `photo` VARCHAR(191) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE SET NULL,
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`section_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Employees
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employees` (
  `emp_id` INT AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(191) NOT NULL,
  `last_name` VARCHAR(191) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `designation` VARCHAR(191) DEFAULT NULL,
  `department` VARCHAR(191) DEFAULT NULL,
  `dob` DATE DEFAULT NULL,
  `joining_date` DATE DEFAULT NULL,
  `salary` DECIMAL(12,2) DEFAULT 0,
  `address` TEXT,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `photo` VARCHAR(191) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Attendance (students)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `status` ENUM('present','absent','late','leave') NOT NULL DEFAULT 'present',
  `marked_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_date` (`student_id`,`date`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Employee Attendance
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_attendance` (
  `attendance_id` INT AUTO_INCREMENT PRIMARY KEY,
  `emp_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `status` ENUM('present','absent','late','leave') NOT NULL DEFAULT 'present',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_emp_date` (`emp_id`,`date`),
  FOREIGN KEY (`emp_id`) REFERENCES `employees`(`emp_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Messages / SMS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `message_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(191) DEFAULT NULL,
  `message` TEXT,
  `recipient_type` VARCHAR(50) DEFAULT 'all',
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `message_templates` (
  `template_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(191) NOT NULL,
  `body` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Fee / Challan
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_heads` (
  `head_id` INT AUTO_INCREMENT PRIMARY KEY,
  `head_name` VARCHAR(191) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `class_id` INT DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `fee_challans` (
  `challan_id` INT AUTO_INCREMENT PRIMARY KEY,
  `challan_no` VARCHAR(50) NOT NULL UNIQUE,
  `student_id` INT NOT NULL,
  `class_id` INT DEFAULT NULL,
  `month` VARCHAR(20) DEFAULT NULL,
  `year` INT DEFAULT NULL,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `status` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `fee_challan_items` (
  `item_id` INT AUTO_INCREMENT PRIMARY KEY,
  `challan_id` INT NOT NULL,
  `head_id` INT DEFAULT NULL,
  `description` VARCHAR(191) DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (`challan_id`) REFERENCES `fee_challans`(`challan_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `fee_payments` (
  `payment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `challan_id` INT NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `payment_method` VARCHAR(50) DEFAULT 'cash',
  `received_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`challan_id`) REFERENCES `fee_challans`(`challan_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Examinations / Marks
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exams` (
  `exam_id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_name` VARCHAR(191) NOT NULL,
  `class_id` INT DEFAULT NULL,
  `exam_date` DATE DEFAULT NULL,
  `status` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `marks` (
  `mark_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `exam_id` INT NOT NULL,
  `subject_id` INT DEFAULT NULL,
  `obtained_marks` DECIMAL(8,2) DEFAULT 0,
  `total_marks` DECIMAL(8,2) DEFAULT 100,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`exam_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Timetable / Periods
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `periods` (
  `period_id` INT AUTO_INCREMENT PRIMARY KEY,
  `period_name` VARCHAR(191) NOT NULL,
  `start_time` TIME DEFAULT NULL,
  `end_time` TIME DEFAULT NULL,
  `class_id` INT DEFAULT NULL,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `timetable` (
  `timetable_id` INT AUTO_INCREMENT PRIMARY KEY,
  `class_id` INT NOT NULL,
  `section_id` INT DEFAULT NULL,
  `day` VARCHAR(20) NOT NULL,
  `period_id` INT DEFAULT NULL,
  `subject_id` INT DEFAULT NULL,
  `teacher_id` INT DEFAULT NULL,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`class_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Transport
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicles` (
  `vehicle_id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_no` VARCHAR(50) NOT NULL,
  `vehicle_name` VARCHAR(191) DEFAULT NULL,
  `capacity` INT DEFAULT 0,
  `driver_name` VARCHAR(191) DEFAULT NULL,
  `status` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `routes` (
  `route_id` INT AUTO_INCREMENT PRIMARY KEY,
  `route_name` VARCHAR(191) NOT NULL,
  `fare` DECIMAL(10,2) DEFAULT 0,
  `status` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Library
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `books` (
  `book_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `author` VARCHAR(191) DEFAULT NULL,
  `category` VARCHAR(191) DEFAULT NULL,
  `quantity` INT DEFAULT 0,
  `available` INT DEFAULT 0,
  `status` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `book_issues` (
  `issue_id` INT AUTO_INCREMENT PRIMARY KEY,
  `book_id` INT NOT NULL,
  `student_id` INT DEFAULT NULL,
  `issue_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `return_date` DATE DEFAULT NULL,
  `status` ENUM('issued','returned') DEFAULT 'issued',
  FOREIGN KEY (`book_id`) REFERENCES `books`(`book_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Payroll
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payroll` (
  `payroll_id` INT AUTO_INCREMENT PRIMARY KEY,
  `emp_id` INT NOT NULL,
  `month` VARCHAR(20) DEFAULT NULL,
  `year` INT DEFAULT NULL,
  `basic_salary` DECIMAL(12,2) DEFAULT 0,
  `allowances` DECIMAL(12,2) DEFAULT 0,
  `deductions` DECIMAL(12,2) DEFAULT 0,
  `net_salary` DECIMAL(12,2) DEFAULT 0,
  `status` ENUM('pending','paid') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`emp_id`) REFERENCES `employees`(`emp_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Expenses / Revenue
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
  `expense_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(191) NOT NULL,
  `category` VARCHAR(191) DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `expense_date` DATE DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `revenue_heads` (
  `head_id` INT AUTO_INCREMENT PRIMARY KEY,
  `head_name` VARCHAR(191) NOT NULL,
  `status` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `revenues` (
  `revenue_id` INT AUTO_INCREMENT PRIMARY KEY,
  `head_id` INT DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `revenue_date` DATE DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`head_id`) REFERENCES `revenue_heads`(`head_id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Complaints / Inquiries
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inquiries` (
  `inquiry_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `class_id` INT DEFAULT NULL,
  `message` TEXT,
  `status` ENUM('new','contacted','admitted','lost') DEFAULT 'new',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `complaints` (
  `complaint_id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject` VARCHAR(191) NOT NULL,
  `description` TEXT,
  `complaint_type` VARCHAR(50) DEFAULT 'general',
  `status` ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Localities / System Settings
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `localities` (
  `locality_id` INT AUTO_INCREMENT PRIMARY KEY,
  `locality_name` VARCHAR(191) NOT NULL,
  `status` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(191) PRIMARY KEY,
  `setting_value` TEXT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Initial Data
-- ------------------------------------------------------------
INSERT INTO `users` (`email`, `password`, `full_name`, `role`) VALUES
('kashif123@gmail.com', SHA2('kash7395515', 256), 'System Administrator', 'admin'),
('laps@gmail.com', SHA2('Laps@2026', 256), 'LAPS Admin', 'admin');

INSERT INTO `classes` (`class_name`) VALUES
('Play Group'),('KG-2'),('1ST'),('2ND'),('3RD'),('4TH'),('5TH'),('6TH'),('7TH'),('8TH'),('9TH'),('10TH'),
('BS Computer Science'),('BSIT'),('MIT');

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('school_name', 'LAPS School & College'),
('school_tagline', 'Test Portal'),
('session_year', '2026-2027'),
('currency_symbol', 'Rs.');