<?php
/**
 * Sidebar Template
 * Role-based navigation menus.
 */

$roleDashboard = ROLE_DASHBOARDS[$userRole] ?? 'auth/login.php';
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$currentDir = basename(dirname($_SERVER['SCRIPT_NAME']));

function isActive($page, $dir = '')
{
    global $currentPage, $currentDir;
    if ($dir && $currentDir !== $dir)
        return '';
    return ($currentPage === $page) ? 'active' : '';
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-icon">Q</div>
        <div>
            <h2>QAMS</h2>
            <p>Quality Assurance</p>
        </div>
    </div>

    <nav class="sidebar-nav">

        <?php if ($userRole == ROLE_LECTURER): ?>
            <!-- ── Lecturer Nav ────────────────────────────── -->
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <a href="<?= BASE_URL ?>lecturer/dashboard.php"
                    class="nav-link <?= isActive('dashboard.php', 'lecturer') ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <a href="<?= BASE_URL ?>lecturer/qams_form.php"
                    class="nav-link <?= isActive('qams_form.php', 'lecturer') ?>">
                    <span class="nav-icon">📝</span> QAMS Form
                </a>
                <a href="<?= BASE_URL ?>lecturer/facility_report.php"
                    class="nav-link <?= isActive('facility_report.php', 'lecturer') ?>">
                    <span class="nav-icon">🔧</span> Facility Issues
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Resources</div>
                <a href="<?= BASE_URL ?>lecturer/templates.php"
                    class="nav-link <?= isActive('templates.php', 'lecturer') ?>">
                    <span class="nav-icon">📁</span> Templates & Guidelines
                </a>
            </div>

        <?php elseif ($userRole == ROLE_HOD): ?>
            <!-- ── HOD Nav ─────────────────────────────────── -->
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <a href="<?= BASE_URL ?>hod/dashboard.php" class="nav-link <?= isActive('dashboard.php', 'hod') ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <a href="<?= BASE_URL ?>hod/validate.php" class="nav-link <?= isActive('validate.php', 'hod') ?>">
                    <span class="nav-icon">✅</span> Validate Submissions
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Data Input</div>
                <a href="<?= BASE_URL ?>hod/action_plans.php" class="nav-link <?= isActive('action_plans.php', 'hod') ?>">
                    <span class="nav-icon">📋</span> Action Plans
                </a>
                <a href="<?= BASE_URL ?>hod/graduate_output.php"
                    class="nav-link <?= isActive('graduate_output.php', 'hod') ?>">
                    <span class="nav-icon">🎓</span> Graduate Output
                </a>
            </div>

        <?php elseif ($userRole == ROLE_CLASS_REP): ?>
            <!-- ── Class Rep Nav ───────────────────────────── -->
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <a href="<?= BASE_URL ?>class_rep/dashboard.php"
                    class="nav-link <?= isActive('dashboard.php', 'class_rep') ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <a href="<?= BASE_URL ?>class_rep/feedback.php"
                    class="nav-link <?= isActive('feedback.php', 'class_rep') ?>">
                    <span class="nav-icon">💬</span> Submit Feedback
                </a>
                <a href="<?= BASE_URL ?>class_rep/attendance_upload.php"
                    class="nav-link <?= isActive('attendance_upload.php', 'class_rep') ?>">
                    <span class="nav-icon">📄</span> Upload Attendance
                </a>
                <a href="<?= BASE_URL ?>class_rep/schedules.php"
                    class="nav-link <?= isActive('schedules.php', 'class_rep') ?>">
                    <span class="nav-icon">📅</span> QA Schedules
                </a>
            </div>

        <?php elseif ($userRole == ROLE_DEAN): ?>
            <!-- ── Dean Nav ────────────────────────────────── -->
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <a href="<?= BASE_URL ?>dean/dashboard.php" class="nav-link <?= isActive('dashboard.php', 'dean') ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <a href="<?= BASE_URL ?>dean/validate.php" class="nav-link <?= isActive('validate.php', 'dean') ?>">
                    <span class="nav-icon">✅</span> Validate Submissions
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Input</div>
                <a href="<?= BASE_URL ?>dean/recommendations.php"
                    class="nav-link <?= isActive('recommendations.php', 'dean') ?>">
                    <span class="nav-icon">💡</span> Recommendations
                </a>
            </div>

        <?php elseif ($userRole == ROLE_DIRECTOR): ?>
            <!-- ── Director (QAMS Head) Nav ────────────────── -->
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <a href="<?= BASE_URL ?>director/dashboard.php"
                    class="nav-link <?= isActive('dashboard.php', 'director') ?>">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                <a href="<?= BASE_URL ?>director/approve.php" class="nav-link <?= isActive('approve.php', 'director') ?>">
                    <span class="nav-icon">✅</span> Approve Submissions
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <a href="<?= BASE_URL ?>director/templates.php"
                    class="nav-link <?= isActive('templates.php', 'director') ?>">
                    <span class="nav-icon">📁</span> Templates & Guidelines
                </a>
                <a href="<?= BASE_URL ?>director/reports.php" class="nav-link <?= isActive('reports.php', 'director') ?>">
                    <span class="nav-icon">📈</span> Reports
                </a>
                <a href="<?= BASE_URL ?>director/recommendations.php"
                    class="nav-link <?= isActive('recommendations.php', 'director') ?>">
                    <span class="nav-icon">💡</span> Recommendations
                </a>
                <a href="<?= BASE_URL ?>director/schedules.php"
                    class="nav-link <?= isActive('schedules.php', 'director') ?>">
                    <span class="nav-icon">📅</span> QA Schedules
                </a>
            </div>
        <?php endif; ?>

        <!-- ── Common Links ────────────────────────────── -->
        <div class="nav-section">
            <div class="nav-section-title">Account</div>
            <a href="<?= BASE_URL ?>api/profile.php" class="nav-link <?= isActive('profile.php', 'api') ?>">
                <span class="nav-icon">👤</span> My Profile
            </a>
            <a href="<?= BASE_URL ?>auth/change_password.php"
                class="nav-link <?= isActive('change_password.php', 'auth') ?>">
                <span class="nav-icon">🔒</span> Change Password
            </a>
            <a href="<?= BASE_URL ?>auth/logout.php" class="nav-link">
                <span class="nav-icon">🚪</span> Sign Out
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">
                <?= $initials ?>
            </div>
            <div class="user-info">
                <div class="name">
                    <?= htmlspecialchars($currentUser['full_name'] ?? 'User') ?>
                </div>
                <div class="role">
                    <?= ROLE_NAMES[$userRole] ?? 'Unknown' ?>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- ── Main Content Wrapper ──────────────────────────────── -->
<main class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
            <h1>
                <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
            </h1>
        </div>
        <div class="topbar-right">
            <button class="notification-bell" onclick="window.location.href='<?= BASE_URL ?>api/notifications.php'"
                title="Notifications">
                🔔
                <?php if ($notifCount > 0): ?>
                    <span class="badge-count">
                        <?= $notifCount > 9 ? '9+' : $notifCount ?>
                    </span>
                <?php endif; ?>
            </button>
            <div class="user-avatar" style="width:32px;height:32px;font-size:12px;">
                <?= $initials ?>
            </div>
        </div>
    </div>

    <!-- Flash message -->
    <?php $flash = getFlash();
    if ($flash): ?>
        <div class="page-content" style="padding-bottom:0;">
            <div class="alert alert-<?= $flash['type'] ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        </div>
    <?php endif; ?>