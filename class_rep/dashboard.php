<?php
/**
 * Class Rep Dashboard
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_CLASS_REP);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$user = getLoggedInUser();
$dept = getUserDepartment($userId);

// Get upcoming QA schedules
$schedules = dbFetchAll(
    "SELECT * FROM qa_schedules WHERE session_id = ? AND schedule_date >= CURDATE() ORDER BY schedule_date ASC LIMIT 5",
    'i',
    [$sessionId ?: 0]
);

// Get recent feedback
$myFeedback = dbFetchAll(
    "SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    'i',
    [$userId]
);

$totalFeedback = count($myFeedback);
$upcomingSchedules = count($schedules);
?>

<div class="page-content">
    <div class="page-header">
        <h2>Welcome,
            <?= htmlspecialchars($user['full_name']) ?>
        </h2>
        <p>Class Representative ·
            <?= htmlspecialchars($dept['name'] ?? '') ?>
        </p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">💬</div>
            <div class="stat-info">
                <h4>
                    <?= $totalFeedback ?>
                </h4>
                <p>Feedback Submitted</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon accent">📅</div>
            <div class="stat-info">
                <h4>
                    <?= $upcomingSchedules ?>
                </h4>
                <p>Upcoming Schedules</p>
            </div>
        </div>
    </div>

    <div class="form-row" style="gap:20px;">
        <!-- Quick Actions -->
        <div class="card" style="flex:1;">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-body">
                <a href="<?= BASE_URL ?>class_rep/feedback.php" class="btn btn-primary btn-block mb-8">💬 Submit
                    Feedback</a>
                <a href="<?= BASE_URL ?>class_rep/attendance_upload.php" class="btn btn-success btn-block mb-8">📄
                    Upload Attendance</a>
                <a href="<?= BASE_URL ?>class_rep/schedules.php" class="btn btn-outline btn-block">📅 View Schedules</a>
            </div>
        </div>

        <!-- Upcoming Schedules -->
        <div class="card" style="flex:1;">
            <div class="card-header">
                <h3>Upcoming QA Schedules</h3>
            </div>
            <div class="card-body">
                <?php if (empty($schedules)): ?>
                    <div class="empty-state" style="padding:24px;">
                        <p class="text-muted">No upcoming schedules.</p>
                    </div>
                <?php else:
                    foreach ($schedules as $sch): ?>
                        <div style="padding:10px 0;border-bottom:1px solid var(--gray-100);">
                            <strong>
                                <?= htmlspecialchars($sch['title']) ?>
                            </strong>
                            <p class="text-sm text-muted">
                                <?= formatDate($sch['schedule_date']) ?>
                                <?= $sch['venue'] ? '· ' . htmlspecialchars($sch['venue']) : '' ?>
                            </p>
                        </div>
                    <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>