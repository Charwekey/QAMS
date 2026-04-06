<?php
/**
 * Header Template
 * Include at the top of every dashboard page.
 * Expects: $pageTitle (string)
 */

require_once __DIR__ . '/auth.php';
requireLogin();

$currentUser = getLoggedInUser();
$userRole = getCurrentUserRole();
$notifCount = getUnreadNotificationCount(getCurrentUserId());
$initials = '';
$nameParts = explode(' ', $currentUser['full_name'] ?? 'U');
foreach ($nameParts as $p) {
    $initials .= strtoupper($p[0] ?? '');
}
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($pageTitle ?? 'QAMS') ?> – QAMS
    </title>
    <meta name="description" content="Quality Assurance Management System">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect fill='%234f46e5' width='32' height='32' rx='8'/><text x='16' y='22' text-anchor='middle' fill='white' font-size='16' font-weight='bold'>Q</text></svg>">
</head>

<body>
    <div class="app-layout">