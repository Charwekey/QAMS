-- ============================================================
-- QAMS Database Schema
-- Quality Assurance Management System
-- ============================================================

CREATE DATABASE IF NOT EXISTS `qams_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `qams_db`;

-- ── User Types ───────────────────────────────────────────────
CREATE TABLE `user_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type_name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(30) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Faculties ────────────────────────────────────────────────
CREATE TABLE `faculties` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Departments ──────────────────────────────────────────────
CREATE TABLE `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `faculties`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Programmes ───────────────────────────────────────────────
CREATE TABLE `programmes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `type` ENUM('diploma','bachelor','postgraduate') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `employee_id` VARCHAR(50) DEFAULT NULL,
    `designation` VARCHAR(100) DEFAULT NULL,
    `department_id` INT DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `employment_type` ENUM('full_time','part_time') DEFAULT 'full_time',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── User Type Relationship ───────────────────────────────────
CREATE TABLE `user_type_rel` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `user_type_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_type` (`user_id`, `user_type_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_type_id`) REFERENCES `user_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Academic Sessions ────────────────────────────────────────
CREATE TABLE `sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `year` INT NOT NULL,
    `semester` TINYINT NOT NULL,
    `label` VARCHAR(100) DEFAULT NULL,
    `is_current` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_session` (`year`, `semester`)
) ENGINE=InnoDB;

-- ── Courses ──────────────────────────────────────────────────
CREATE TABLE `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT NOT NULL,
    `course_code` VARCHAR(20) NOT NULL,
    `course_title` VARCHAR(200) NOT NULL,
    `credit_hours` INT NOT NULL DEFAULT 3,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Lecturer-Course Assignments ──────────────────────────────
CREATE TABLE `lecturer_courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lecturer_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `session_id` INT NOT NULL,
    `section` VARCHAR(10) DEFAULT 'A',
    `total_students` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_assignment` (`lecturer_id`, `course_id`, `session_id`, `section`),
    FOREIGN KEY (`lecturer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Submissions (QAMS Form) ─────────────────────────────────
CREATE TABLE `submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lecturer_course_id` INT NOT NULL,
    `session_id` INT NOT NULL,
    `lecturer_id` INT NOT NULL,
    `learning_feedback_link` VARCHAR(500) DEFAULT NULL,
    `classes_taken` INT DEFAULT 0,
    `class_tests` INT DEFAULT 0,
    `midterm_taken` TINYINT(1) DEFAULT 0,
    `final_taken` TINYINT(1) DEFAULT 0,
    `assignments` INT DEFAULT 0,
    `presentations` INT DEFAULT 0,
    `course_outline_covered` TEXT DEFAULT NULL,
    `status` VARCHAR(30) DEFAULT 'draft',
    `hod_comment` TEXT DEFAULT NULL,
    `dean_comment` TEXT DEFAULT NULL,
    `director_comment` TEXT DEFAULT NULL,
    `submitted_at` DATETIME DEFAULT NULL,
    `hod_reviewed_at` DATETIME DEFAULT NULL,
    `dean_reviewed_at` DATETIME DEFAULT NULL,
    `director_reviewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`lecturer_course_id`) REFERENCES `lecturer_courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`lecturer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Submission Files ─────────────────────────────────────────
CREATE TABLE `submission_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT NOT NULL,
    `file_type` ENUM('attendance','midterm_question','final_question','course_outline') NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Feedback (Class Reps & Others) ───────────────────────────
CREATE TABLE `feedback` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `session_id` INT NOT NULL,
    `category` ENUM('course_delivery','resource_adequacy','infrastructure','general') DEFAULT 'general',
    `subject` VARCHAR(200) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `department_id` INT DEFAULT NULL,
    `course_id` INT DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Attendance Uploads (Class Rep) ───────────────────────────
CREATE TABLE `attendance_uploads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `session_id` INT NOT NULL,
    `course_id` INT DEFAULT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Facility Issues (Lecturer-reported) ──────────────────────
CREATE TABLE `facility_issues` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reporter_id` INT NOT NULL,
    `department_id` INT DEFAULT NULL,
    `session_id` INT NOT NULL,
    `issue_type` ENUM('markers','boards','projectors','fans','sockets','switches','fan_regulators','seats','room_suitability','other') NOT NULL,
    `description` TEXT NOT NULL,
    `room_number` VARCHAR(50) DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'open',
    `resolved_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`reporter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Graduate Output Data (HOD) ───────────────────────────────
CREATE TABLE `graduate_output` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hod_id` INT NOT NULL,
    `programme_id` INT NOT NULL,
    `session_id` INT NOT NULL,
    `graduation_year` INT NOT NULL,
    `programme_type` ENUM('diploma','bachelor','postgraduate') NOT NULL,
    -- Diploma: Distinction, Pass
    `distinction_male` INT DEFAULT 0,
    `distinction_female` INT DEFAULT 0,
    `pass_male` INT DEFAULT 0,
    `pass_female` INT DEFAULT 0,
    -- Bachelor: First Class, 2nd Upper, 2nd Lower, Third Class, Pass
    `first_class_male` INT DEFAULT 0,
    `first_class_female` INT DEFAULT 0,
    `second_upper_male` INT DEFAULT 0,
    `second_upper_female` INT DEFAULT 0,
    `second_lower_male` INT DEFAULT 0,
    `second_lower_female` INT DEFAULT 0,
    `third_class_male` INT DEFAULT 0,
    `third_class_female` INT DEFAULT 0,
    -- Postgraduate: Pass only (reusing pass_male / pass_female)
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`hod_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`programme_id`) REFERENCES `programmes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Action Plans (HOD) ───────────────────────────────────────
CREATE TABLE `action_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hod_id` INT NOT NULL,
    `department_id` INT NOT NULL,
    `session_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `target_date` DATE DEFAULT NULL,
    `status` ENUM('proposed','in_progress','completed') DEFAULT 'proposed',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`hod_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Dean Recommendations ─────────────────────────────────────
CREATE TABLE `recommendations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `dean_id` INT NOT NULL,
    `faculty_id` INT NOT NULL,
    `session_id` INT NOT NULL,
    `category` ENUM('framework','facilities','other') DEFAULT 'framework',
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `status` VARCHAR(20) DEFAULT 'submitted',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`dean_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`faculty_id`) REFERENCES `faculties`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Templates & Guidelines (Director) ────────────────────────
CREATE TABLE `templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `uploaded_by` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category` ENUM('course_delivery','assessment','reporting','policy','other') DEFAULT 'other',
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Notifications ────────────────────────────────────────────
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(500) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── QA Schedules ─────────────────────────────────────────────
CREATE TABLE `qa_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `schedule_date` DATE NOT NULL,
    `schedule_time` TIME DEFAULT NULL,
    `venue` VARCHAR(200) DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- SEED DATA
-- ============================================================

-- User Types
INSERT INTO `user_types` (`id`, `type_name`, `slug`, `description`) VALUES
(1, 'Class Representative', 'class_rep', 'Student class representative'),
(2, 'Lecturer', 'lecturer', 'Teaching staff'),
(3, 'Head of Department', 'hod', 'Department head'),
(4, 'Dean', 'dean', 'Faculty dean'),
(5, 'Director of Quality Assurance', 'director', 'QAMS Head / Director');

-- Faculties
INSERT INTO `faculties` (`id`, `name`, `code`) VALUES
(1, 'Faculty of Computing & Information Systems', 'FCIS'),
(2, 'Faculty of Business & Economics', 'FBE'),
(3, 'Faculty of Engineering', 'FEN'),
(4, 'Faculty of Arts & Social Sciences', 'FASS');

-- Departments
INSERT INTO `departments` (`id`, `faculty_id`, `name`, `code`) VALUES
(1, 1, 'Computer Science', 'CS'),
(2, 1, 'Information Technology', 'IT'),
(3, 2, 'Accounting & Finance', 'AF'),
(4, 2, 'Business Administration', 'BA'),
(5, 3, 'Electrical Engineering', 'EE'),
(6, 3, 'Mechanical Engineering', 'ME'),
(7, 4, 'English', 'ENG'),
(8, 4, 'Social Work', 'SW');

-- Programmes
INSERT INTO `programmes` (`department_id`, `name`, `type`) VALUES
(1, 'Diploma in Computer Science', 'diploma'),
(1, 'BSc Computer Science', 'bachelor'),
(1, 'MSc Computer Science', 'postgraduate'),
(2, 'Diploma in Information Technology', 'diploma'),
(2, 'BSc Information Technology', 'bachelor'),
(3, 'Diploma in Accounting', 'diploma'),
(3, 'BSc Accounting & Finance', 'bachelor'),
(4, 'BBA Business Administration', 'bachelor'),
(4, 'MBA Business Administration', 'postgraduate'),
(5, 'BSc Electrical Engineering', 'bachelor'),
(6, 'BSc Mechanical Engineering', 'bachelor');

-- Sessions
INSERT INTO `sessions` (`year`, `semester`, `label`, `is_current`) VALUES
(2025, 1, '2024/2025 First Semester', 0),
(2025, 2, '2024/2025 Second Semester', 0),
(2026, 1, '2025/2026 First Semester', 1),
(2026, 2, '2025/2026 Second Semester', 0);

-- Courses
INSERT INTO `courses` (`department_id`, `course_code`, `course_title`, `credit_hours`) VALUES
(1, 'CS101', 'Introduction to Computer Science', 3),
(1, 'CS201', 'Data Structures & Algorithms', 3),
(1, 'CS301', 'Database Management Systems', 3),
(1, 'CS401', 'Software Engineering', 3),
(2, 'IT101', 'Fundamentals of IT', 3),
(2, 'IT201', 'Web Development', 3),
(3, 'AF101', 'Principles of Accounting', 3),
(3, 'AF201', 'Financial Management', 3),
(4, 'BA101', 'Principles of Management', 3),
(5, 'EE101', 'Circuit Analysis', 4),
(6, 'ME101', 'Engineering Mechanics', 4);

-- ── Demo Users (password: "password123" for all) ─────────────
-- Password hash for "password123"
SET @pwd = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- Director (QAMS Head)
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `employee_id`, `designation`, `department_id`, `employment_type`)
VALUES (1, 'director@university.edu', @pwd, 'Dr. Amina Mensah', 'EMP001', 'Director of Quality Assurance', 1, 'full_time');
INSERT INTO `user_type_rel` (`user_id`, `user_type_id`) VALUES (1, 5);

-- Dean (Faculty of Computing)
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `employee_id`, `designation`, `department_id`, `employment_type`)
VALUES (2, 'dean.computing@university.edu', @pwd, 'Prof. Kwame Asante', 'EMP002', 'Dean', 1, 'full_time');
INSERT INTO `user_type_rel` (`user_id`, `user_type_id`) VALUES (2, 4);

-- HOD (Computer Science)
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `employee_id`, `designation`, `department_id`, `employment_type`)
VALUES (3, 'hod.cs@university.edu', @pwd, 'Dr. Fatima Ibrahim', 'EMP003', 'Head of Department', 1, 'full_time');
INSERT INTO `user_type_rel` (`user_id`, `user_type_id`) VALUES (3, 3);

-- Lecturers
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `employee_id`, `designation`, `department_id`, `employment_type`)
VALUES (4, 'lecturer1@university.edu', @pwd, 'Mr. John Doe', 'EMP004', 'Senior Lecturer', 1, 'full_time');
INSERT INTO `user_type_rel` (`user_id`, `user_type_id`) VALUES (4, 2);

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `employee_id`, `designation`, `department_id`, `employment_type`)
VALUES (5, 'lecturer2@university.edu', @pwd, 'Ms. Sarah Owusu', 'EMP005', 'Lecturer', 1, 'part_time');
INSERT INTO `user_type_rel` (`user_id`, `user_type_id`) VALUES (5, 2);

-- Class Rep
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `employee_id`, `designation`, `department_id`, `employment_type`)
VALUES (6, 'classrep@university.edu', @pwd, 'Abdul Rahman', 'STU001', 'Class Representative', 1, 'full_time');
INSERT INTO `user_type_rel` (`user_id`, `user_type_id`) VALUES (6, 1);

-- Dean (Faculty of Business)
INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `employee_id`, `designation`, `department_id`, `employment_type`)
VALUES (7, 'dean.business@university.edu', @pwd, 'Prof. Grace Osei', 'EMP006', 'Dean', 3, 'full_time');
INSERT INTO `user_type_rel` (`user_id`, `user_type_id`) VALUES (7, 4);

-- Lecturer-Course Assignments
INSERT INTO `lecturer_courses` (`lecturer_id`, `course_id`, `session_id`, `section`, `total_students`) VALUES
(4, 1, 3, 'A', 45),
(4, 2, 3, 'A', 38),
(5, 3, 3, 'A', 50),
(5, 4, 3, 'B', 42);
