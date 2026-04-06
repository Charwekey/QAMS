<?php
/**
 * QAMS – Index / Entry Point
 * Redirects to login or dashboard based on auth state.
 */

require_once __DIR__ . '/includes/functions.php';

startSession();

if (isLoggedIn()) {
    $role = getCurrentUserRole();
    redirect(ROLE_DASHBOARDS[$role] ?? 'auth/login.php');
} else {
    redirect('auth/login.php');
}
