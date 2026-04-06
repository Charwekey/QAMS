<?php
/**
 * Lecturer Dashboard
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_LECTURER);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$user = getLoggedInUser();
$dept = getUserDepartment($userId);

// Get lecturer courses for current session
$courses = dbFetchAll(
    "SELECT lc.*, c.course_code, c.course_title, c.credit_hours
     FROM lecturer_courses lc
     JOIN courses c ON lc.course_id = c.id
     WHERE lc.lecturer_id = ? AND lc.session_id = ?
     ORDER BY c.course_code",
    'ii',
    [$userId, $sessionId ?: 0]
);

// Get submission stats
$totalCourses = count($courses);
$submitted = 0;
$reverted = 0;
foreach ($courses as &$course) {
    $sub = dbFetchOne(
        "SELECT status FROM submissions WHERE lecturer_course_id = ? AND session_id = ?",
        'ii',
        [$course['id'], $sessionId ?: 0]
    );
    $course['submission_status'] = $sub['status'] ?? null;
    if ($sub) {
        if ($sub['status'] === STATUS_REVERTED_LECTURER)
            $reverted++;
        elseif ($sub['status'] !== STATUS_DRAFT)
            $submitted++;
    }
}
unset($course);
$pending = $totalCourses - $submitted - $reverted;

// Total credit hours
$totalCredits = array_sum(array_column($courses, 'credit_hours'));
?>

<div class="page-content">
    <div class="page-header">
        <h2>Welcome,
            <?= htmlspecialchars($user['full_name']) ?>
        </h2>
        <p>
            <?= htmlspecialchars($user['designation'] ?? 'Lecturer') ?> ·
            <?= htmlspecialchars($dept['name'] ?? '') ?>,
            <?= htmlspecialchars($dept['faculty_name'] ?? '') ?>
        </p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">📚</div>
            <div class="stat-info">
                <h4>
                    <?= $totalCourses ?>
                </h4>
                <p>Courses Assigned</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">✅</div>
            <div class="stat-info">
                <h4>
                    <?= $submitted ?>
                </h4>
                <p>Forms Submitted</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">⏳</div>
            <div class="stat-info">
                <h4>
                    <?= $pending ?>
                </h4>
                <p>Pending Submission</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info">📊</div>
            <div class="stat-info">
                <h4>
                    <?= $totalCredits ?>
                </h4>
                <p>Total Credit Hours</p>
            </div>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>Profile Information</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div><strong>Name:</strong>
                    <?= htmlspecialchars($user['full_name']) ?>
                </div>
                <div><strong>Employee ID:</strong>
                    <?= htmlspecialchars($user['employee_id'] ?? 'N/A') ?>
                </div>
            </div>
            <div class="form-row mt-8">
                <div><strong>Department:</strong>
                    <?= htmlspecialchars($dept['name'] ?? 'N/A') ?>
                </div>
                <div><strong>Faculty:</strong>
                    <?= htmlspecialchars($dept['faculty_name'] ?? 'N/A') ?>
                </div>
            </div>
            <div class="form-row mt-8">
                <div><strong>Designation:</strong>
                    <?= htmlspecialchars($user['designation'] ?? 'N/A') ?>
                </div>
                <div><strong>Type:</strong>
                    <?= $user['employment_type'] === 'full_time' ? 'Full-Time' : 'Part-Time' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Courses Table -->
    <div class="card">
        <div class="card-header">
            <h3>My Courses</h3>
            <span class="text-muted text-sm">
                <?= $totalCourses ?> courses ·
                <?= $totalCredits ?> credit hours
            </span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Title</th>
                        <th>Section</th>
                        <th>Credits</th>
                        <th>Students</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding:32px;">No courses assigned for
                                this session.</td>
                        </tr>
                    <?php else:
                        foreach ($courses as $c): ?>
                            <tr>
                                <td><strong>
                                        <?= htmlspecialchars($c['course_code']) ?>
                                    </strong></td>
                                <td>
                                    <?= htmlspecialchars($c['course_title']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($c['section']) ?>
                                </td>
                                <td>
                                    <?= $c['credit_hours'] ?>
                                </td>
                                <td>
                                    <?= $c['total_students'] ?>
                                </td>
                                <td>
                                    <?= $c['submission_status'] ? statusLabel($c['submission_status']) : '<span class="badge badge-draft">Not Started</span>' ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>lecturer/qams_form.php?lc_id=<?= $c['id'] ?>"
                                        class="btn btn-sm btn-primary">
                                        <?= $c['submission_status'] === STATUS_REVERTED_LECTURER ? 'Resubmit' : ($c['submission_status'] && $c['submission_status'] !== STATUS_DRAFT ? 'View' : 'Fill Form') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>