<?php
/**
 * Dean Dashboard
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_DEAN);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$user = getLoggedInUser();
$dept = getUserDepartment($userId);
$selectedFacultyId = getSelectedFaculty();
$facultyId = $dept['faculty_id'] ?? 0;
if ($selectedFacultyId && $selectedFacultyId === $facultyId) {
    $facultyId = $selectedFacultyId;
}

// Get all submissions across departments in this faculty for the current session
$submissions = dbFetchAll(
    "SELECT s.*, u.full_name as lecturer_name, c.course_code, c.course_title, d.name as dept_name
     FROM submissions s
     JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
     JOIN courses c ON lc.course_id = c.id
     JOIN departments d ON c.department_id = d.id
     JOIN users u ON s.lecturer_id = u.id
     WHERE d.faculty_id = ? AND s.session_id = ?
     AND s.status IN ('" . STATUS_PENDING_DEAN . "', '" . STATUS_PENDING_DIRECTOR . "', '" . STATUS_APPROVED . "', '" . STATUS_REVERTED_HOD . "', '" . STATUS_REVERTED_DEAN . "')
     ORDER BY s.updated_at DESC",
    'ii',
    [$facultyId, $sessionId ?: 0]
);

$totalSubs = count($submissions);
$pendingDean = count(array_filter($submissions, fn($s) => in_array($s['status'], [STATUS_PENDING_DEAN, STATUS_REVERTED_DEAN])));
$forwarded = count(array_filter($submissions, fn($s) => in_array($s['status'], [STATUS_PENDING_DIRECTOR, STATUS_APPROVED])));

// Departments in this faculty
$departments = getDepartments($facultyId);
?>

<div class="page-content">
    <div class="page-header">
        <h2>Welcome,
            <?= htmlspecialchars($user['full_name']) ?>
        </h2>
        <p>Dean ·
            <?= htmlspecialchars($dept['faculty_name'] ?? '') ?>
        </p>
    </div>

    <?php if ($selectedFacultyId): ?>
        <div class="alert alert-info" style="margin-bottom:24px;">
            <strong>Selected Faculty:</strong>
            <?= htmlspecialchars($dept['faculty_name'] ?? 'Faculty') ?>
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
                    <?= $pendingDean ?>
                </h4>
                <p>Awaiting Your Review</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">✅</div>
            <div class="stat-info">
                <h4>
                    <?= $forwarded ?>
                </h4>
                <p>Forwarded to Director</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info">🏢</div>
            <div class="stat-info">
                <h4>
                    <?= count($departments) ?>
                </h4>
                <p>Departments</p>
            </div>
        </div>
    </div>

    <!-- Submissions by Department -->
    <div class="card">
        <div class="card-header">
            <h3>Recent Submissions</h3>
            <a href="<?= BASE_URL ?>dean/validate.php" class="btn btn-sm btn-primary">Review All</a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Lecturer</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding:32px;">No submissions found.</td>
                        </tr>
                    <?php else:
                        foreach (array_slice($submissions, 0, 10) as $s): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($s['dept_name']) ?>
                                </td>
                                <td><strong>
                                        <?= htmlspecialchars($s['lecturer_name']) ?>
                                    </strong></td>
                                <td>
                                    <?= htmlspecialchars($s['course_code']) ?>
                                </td>
                                <td>
                                    <?= statusLabel($s['status']) ?>
                                </td>
                                <td>
                                    <?= timeAgo($s['updated_at']) ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>dean/validate.php?id=<?= $s['id'] ?>"
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