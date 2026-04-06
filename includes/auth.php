<?php
/**
 * Auth Middleware
 * Include at top of protected pages.
 */

require_once __DIR__ . '/functions.php';

startSession();

/**
 * Require the user to be logged in.
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to access this page.');
        redirect('auth/login.php');
    }
}

/**
 * Require a specific role. Accepts a single role ID or array.
 */
function requireRole($roles)
{
    requireLogin();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!in_array(getCurrentUserRole(), $roles)) {
        setFlash('error', 'You do not have permission to access this page.');
        redirect('auth/login.php');
    }
}

/**
 * Get full user data for the logged-in user.
 */
function getLoggedInUser()
{
    $userId = getCurrentUserId();
    if (!$userId)
        return null;
    return dbFetchOne(
        "SELECT u.*, ut.type_name as role_name
         FROM users u
         JOIN user_type_rel utr ON u.id = utr.user_id
         JOIN user_types ut ON utr.user_type_id = ut.id
         WHERE u.id = ?",
        'i',
        [$userId]
    );
}

/**
 * Get user department info
 */
function getUserDepartment($userId)
{
    return dbFetchOne(
        "SELECT d.*, f.name as faculty_name, f.id as faculty_id
         FROM users u
         JOIN departments d ON u.department_id = d.id
         JOIN faculties f ON d.faculty_id = f.id
         WHERE u.id = ?",
        'i',
        [$userId]
    );
}

/**
 * Get user faculty info
 */
function getUserFaculty($userId)
{
    return dbFetchOne(
        "SELECT f.*
         FROM users u
         JOIN departments d ON u.department_id = d.id
         JOIN faculties f ON d.faculty_id = f.id
         WHERE u.id = ?",
        'i',
        [$userId]
    );
}
