<?php
/**
 * Application Constants
 */

// Base URL
define('BASE_URL', '/QAMS/');
define('SITE_NAME', 'QAMS');
define('SITE_FULL_NAME', 'Quality Assurance Management System');

// User type constants
define('ROLE_CLASS_REP', 1);
define('ROLE_LECTURER', 2);
define('ROLE_HOD', 3);
define('ROLE_DEAN', 4);
define('ROLE_DIRECTOR', 5);

// Role names map
define('ROLE_NAMES', [
    ROLE_CLASS_REP => 'Class Representative',
    ROLE_LECTURER => 'Lecturer',
    ROLE_HOD => 'Head of Department',
    ROLE_DEAN => 'Dean',
    ROLE_DIRECTOR => 'Director of Quality Assurance'
]);

// Role dashboard paths
define('ROLE_DASHBOARDS', [
    ROLE_CLASS_REP => 'class_rep/dashboard.php',
    ROLE_LECTURER => 'lecturer/dashboard.php',
    ROLE_HOD => 'hod/dashboard.php',
    ROLE_DEAN => 'dean/dashboard.php',
    ROLE_DIRECTOR => 'director/dashboard.php'
]);

// Submission status constants
define('STATUS_DRAFT', 'draft');
define('STATUS_PENDING_HOD', 'pending_hod');
define('STATUS_PENDING_DEAN', 'pending_dean');
define('STATUS_PENDING_DIRECTOR', 'pending_director');
define('STATUS_APPROVED', 'approved');
define('STATUS_REVERTED_LECTURER', 'reverted_to_lecturer');
define('STATUS_REVERTED_HOD', 'reverted_to_hod');
define('STATUS_REVERTED_DEAN', 'reverted_to_dean');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf']);

// Semesters
define('SEMESTERS', [
    1 => 'First Semester',
    2 => 'Second Semester',
    3 => 'Summer Semester'
]);
