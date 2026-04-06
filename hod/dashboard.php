<?php
/**
 * HOD Dashboard
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_HOD);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$user = getLoggedInUser();
$dept = getUserDepartment($userId);
$selectedDeptId = getSelectedDepartment();
$deptId = $dept['id'] ?? 0;
if ($selectedDeptId && $selectedDeptId === $deptId) {
    $deptId = $selectedDeptId;
}

// Get submissions from lecturers in this department
$submissions = dbFetchAll(
    "SELECT s.*, u.full_name as lecturer_name, c.course_code, c.course_title
     FROM submissions s
     JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
     JOIN courses c ON lc.course_id = c.id
     JOIN users u ON s.lecturer_id = u.id
     WHERE c.department_id = ? AND s.session_id = ?
     ORDER BY s.submitted_at DESC",
    'ii',
    [$deptId, $sessionId ?: 0]
);

$totalSubs = count($submissions);
$pendingHod = count(array_filter($submissions, fn($s) => in_array($s['status'], [STATUS_PENDING_HOD, STATUS_REVERTED_HOD])));
$approved = count(array_filter($submissions, fn($s) => in_array($s['status'], [STATUS_PENDING_DEAN, STATUS_PENDING_DIRECTOR, STATUS_APPROVED])));
$reverted = count(array_filter($submissions, fn($s) => $s['status'] === STATUS_REVERTED_LECTURER));
?>

<div class="page-content">
    <div class="page-header">
        <h2>Welcome,
            <?= htmlspecialchars($user['full_name']) ?>
        </h2>
        <p>Head of Department ·
            <?= htmlspecialchars($dept['name'] ?? '') ?>,
            <?= htmlspecialchars($dept['faculty_name'] ?? '') ?>
        </p>
    </div>

    <?php if ($selectedDeptId): ?>
        <div class="alert alert-info" style="margin-bottom:24px;">
            <strong>Selected Department:</strong>
            <?= htmlspecialchars($dept['name'] ?? 'Department') ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">📋</div>
            <div class="stat-info">
                <h4>
                    <?= $totalSubs ?>
                </h4>
                <p>Total Submissions</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">⏳</div>
            <div class="stat-info">
                <h4>
                    <?= $pendingHod ?>
                </h4>
                <p>Awaiting Your Review</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">✅</div>
            <div class="stat-info">
                <h4>
                    <?= $approved ?>
                </h4>
                <p>Forwarded to Dean</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger">🔄</div>
            <div class="stat-info">
                <h4>
                    <?= $reverted ?>
                </h4>
                <p>Reverted</p>
            </div>
        </div>
    </div>

    <!-- Recent Submissions -->
    <div class="card">
        <div class="card-header">
            <h3>Lecturer Submissions</h3>
            <a href="<?= BASE_URL ?>hod/validate.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Lecturer</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:32px;">No submissions yet.</td>
                        </tr>
                    <?php else:
                        foreach (array_slice($submissions, 0, 10) as $s): ?>
                            <tr>
                                <td><strong>
                                        <?= htmlspecialchars($s['lecturer_name']) ?>
                                    </strong></td>
                                <td>
                                    <?= htmlspecialchars($s['course_code']) ?> –
                                    <?= htmlspecialchars($s['course_title']) ?>
                                </td>
                                <td>
                                    <?= statusLabel($s['status']) ?>
                                </td>
                                <td>
                                    <?= $s['submitted_at'] ? timeAgo($s['submitted_at']) : '—' ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>hod/validate.php?id=<?= $s['id'] ?>"
                                        class="btn btn-sm btn-outline">Review</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>