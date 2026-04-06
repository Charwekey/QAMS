<?php
/**
 * Dean – Validate Submissions
 * Review HOD-approved submissions across the faculty, approve or revert.
 */
$pageTitle = 'Validate Submissions';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_DEAN);

$userId    = getCurrentUserId();
$sessionId = getCurrentSession();
$user      = getLoggedInUser();
$dept      = getUserDepartment($userId);
$selectedFacultyId = getSelectedFaculty();
$facultyId = $dept['faculty_id'] ?? 0;
if ($selectedFacultyId && $selectedFacultyId === $facultyId) {
    $facultyId = $selectedFacultyId;
}

$error   = '';
$success = '';
$subId   = intval($_GET['id'] ?? 0);

// ── Handle approve / revert actions ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subId) {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $action  = $_POST['action'] ?? '';
        $comment = sanitize($_POST['comment'] ?? '');

        $check = dbFetchOne(
            "SELECT s.id, s.lecturer_id, s.lecturer_course_id, c.course_code, c.course_title, c.department_id
             FROM submissions s
             JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
             JOIN courses c ON lc.course_id = c.id
             JOIN departments d ON c.department_id = d.id
             WHERE s.id = ? AND d.faculty_id = ? AND s.status = ?",
            'iis',
            [$subId, $facultyId, STATUS_PENDING_DEAN]
        );

        if (!$check) {
            $error = 'Submission not found or not available for review.';
        } elseif ($action === 'approve') {
            dbExecute(
                "UPDATE submissions SET status = ?, dean_comment = ?, dean_reviewed_at = NOW() WHERE id = ?",
                'ssi',
                [STATUS_PENDING_DIRECTOR, $comment, $subId]
            );
            // Notify lecturer
            createNotification($check['lecturer_id'], 'Submission Approved by Dean',
                $check['course_code'] . ' has been forwarded to the QA Director.',
                'lecturer/qams_form.php?lc_id=' . $check['lecturer_course_id']);
            // Notify HOD
            $hod = dbFetchOne(
                "SELECT u.id FROM users u JOIN user_type_rel r ON u.id = r.user_id
                 WHERE r.user_type_id = ? AND u.department_id = ?", 'ii',
                [ROLE_HOD, $check['department_id']]);
            if ($hod) {
                createNotification($hod['id'], 'Submission Forwarded by Dean',
                    $check['course_code'] . ' approved and forwarded to Director.',
                    'hod/validate.php');
            }
            // Notify Director
            $director = dbFetchOne(
                "SELECT u.id FROM users u JOIN user_type_rel r ON u.id = r.user_id
                 WHERE r.user_type_id = ?", 'i', [ROLE_DIRECTOR]);
            if ($director) {
                createNotification($director['id'], 'New Submission for Final Approval',
                    $check['course_code'] . ' approved by Dean, awaiting your review.',
                    'director/approve.php');
            }
            $success = 'Submission approved and forwarded to Director!';
        } elseif ($action === 'revert') {
            if (empty($comment)) {
                $error = 'Please provide a comment explaining the reason for reverting.';
            } else {
                dbExecute(
                    "UPDATE submissions SET status = ?, dean_comment = ?, dean_reviewed_at = NOW() WHERE id = ?",
                    'ssi',
                    [STATUS_REVERTED_HOD, $comment, $subId]
                );
                // Notify HOD
                $hod = dbFetchOne(
                    "SELECT u.id FROM users u JOIN user_type_rel r ON u.id = r.user_id
                     WHERE r.user_type_id = ? AND u.department_id = ?", 'ii',
                    [ROLE_HOD, $check['department_id']]);
                if ($hod) {
                    createNotification($hod['id'], 'Submission Reverted by Dean',
                        $check['course_code'] . ' was reverted. Please review.',
                        'hod/validate.php?id=' . $subId);
                }
                $success = 'Submission reverted to HOD.';
            }
        }
    }
}

// ── Single submission view ────────────────────────────────
if ($subId) {
    $submission = dbFetchOne(
        "SELECT s.*, u.full_name as lecturer_name, u.email as lecturer_email,
                u.employee_id, u.designation,
                c.course_code, c.course_title, c.credit_hours,
                lc.section, lc.total_students,
                d.name as dept_name
         FROM submissions s
         JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
         JOIN courses c ON lc.course_id = c.id
         JOIN departments d ON c.department_id = d.id
         JOIN users u ON s.lecturer_id = u.id
         WHERE s.id = ? AND d.faculty_id = ?",
        'ii',
        [$subId, $facultyId]
    );

    if (!$submission) {
        setFlash('error', 'Submission not found.');
        redirect('dean/validate.php');
    }

    $files = dbFetchAll("SELECT * FROM submission_files WHERE submission_id = ?", 'i', [$subId]);
    $filesByType = [];
    foreach ($files as $f) $filesByType[$f['file_type']] = $f;

    $canReview = ($submission['status'] === STATUS_PENDING_DEAN);
    ?>
    <div class="page-content">
        <div class="page-header">
            <div>
                <h2><?= htmlspecialchars($submission['course_code']) ?> – QAMS Validation Form</h2>
                <p><?= htmlspecialchars($submission['dept_name']) ?>
                    · <?= htmlspecialchars($submission['course_title']) ?>
                    · Section <?= htmlspecialchars($submission['section']) ?>
                </p>
            </div>
            <div><?= statusLabel($submission['status']) ?></div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Lecturer Info -->
        <div class="card mb-24">
            <div class="card-header"><h3>Lecturer Information</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div><strong>Name:</strong> <?= htmlspecialchars($submission['lecturer_name']) ?></div>
                    <div><strong>Employee ID:</strong> <?= htmlspecialchars($submission['employee_id'] ?? 'N/A') ?></div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Email:</strong> <?= htmlspecialchars($submission['lecturer_email']) ?></div>
                    <div><strong>Department:</strong> <?= htmlspecialchars($submission['dept_name']) ?></div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Submitted:</strong> <?= $submission['submitted_at'] ? timeAgo($submission['submitted_at']) : '—' ?></div>
                </div>
            </div>
        </div>

        <!-- Course Delivery Details -->
        <div class="card mb-24">
            <div class="card-header"><h3>Course Delivery Details</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div><strong>Classes Taken:</strong> <?= intval($submission['classes_taken']) ?></div>
                    <div><strong>Class Tests:</strong> <?= intval($submission['class_tests']) ?></div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Assignments:</strong> <?= intval($submission['assignments']) ?></div>
                    <div><strong>Presentations:</strong> <?= intval($submission['presentations']) ?></div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Midterm Exam:</strong> <?= $submission['midterm_taken'] ? '✅ Yes' : '❌ No' ?></div>
                    <div><strong>Final Exam:</strong> <?= $submission['final_taken'] ? '✅ Yes' : '❌ No' ?></div>
                </div>
            </div>
        </div>

        <!-- HOD Review -->
        <?php if ($submission['hod_comment']): ?>
            <div class="card mb-24">
                <div class="card-header"><h3>HOD Review</h3></div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($submission['hod_comment'])) ?></p>
                    <small class="text-muted">Reviewed: <?= $submission['hod_reviewed_at'] ? formatDateTime($submission['hod_reviewed_at']) : '—' ?></small>
                </div>
            </div>
        <?php endif; ?>

        <!-- Supporting Documents -->
        <div class="card mb-24">
            <div class="card-header"><h3>Supporting Documents</h3></div>
            <div class="card-body">
                <?php
                $fileLabels = [
                    'attendance'       => ['📋', 'Attendance Sheet'],
                    'midterm_question' => ['📝', 'Midterm Questions'],
                    'final_question'   => ['📝', 'Final Questions'],
                    'course_outline'   => ['📄', 'Course Outline'],
                ];
                foreach ($fileLabels as $key => $meta):
                    $hasFile = isset($filesByType[$key]);
                ?>
                    <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--border);">
                        <span style="font-size:1.2rem;"><?= $meta[0] ?></span>
                        <strong style="flex:1;"><?= $meta[1] ?></strong>
                        <?php if ($hasFile): ?>
                            <a href="<?= BASE_URL ?>uploads/<?= htmlspecialchars($filesByType[$key]['file_path']) ?>"
                                target="_blank" class="btn btn-sm btn-outline">📥 View / Download</a>
                        <?php else: ?>
                            <span class="text-muted text-sm">Not uploaded</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Dean Review Action -->
        <?php if ($canReview): ?>
            <div class="card">
                <div class="card-header"><h3>Verify QAMS Validation Form</h3></div>
                <div class="card-body">
                    <form method="POST" action="?id=<?= $subId ?>">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label>Comment / Feedback</label>
                            <textarea name="comment" class="form-control" rows="3"
                                      placeholder="Add your review comments (required for revert)..."></textarea>
                        </div>
                        <div style="display:flex; gap:12px; justify-content:flex-end; flex-wrap:wrap;">
                            <a href="<?= BASE_URL ?>dean/validate.php" class="btn btn-secondary">← Back</a>
                            <button type="submit" name="action" value="revert" class="btn btn-danger"
                                    data-confirm="Revert this submission back to the HOD?">
                                🔄 Apply For Revert
                            </button>
                            <button type="submit" name="action" value="approve" class="btn btn-primary"
                                    data-confirm="Approve and forward to the QA Director?">
                                ✅ Submit to QAMS Head
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <?php if ($submission['dean_comment']): ?>
                <div class="card mb-24">
                    <div class="card-header"><h3>Your Previous Review</h3></div>
                    <div class="card-body">
                        <p><?= nl2br(htmlspecialchars($submission['dean_comment'])) ?></p>
                        <small class="text-muted">Reviewed: <?= $submission['dean_reviewed_at'] ? formatDateTime($submission['dean_reviewed_at']) : '—' ?></small>
                    </div>
                </div>
            <?php endif; ?>
            <div style="text-align:right;">
                <a href="<?= BASE_URL ?>dean/validate.php" class="btn btn-secondary">← Back to All Submissions</a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ── List view ─────────────────────────────────────────────
$filter = sanitize($_GET['filter'] ?? 'pending');
$deptFilter = intval($_GET['dept'] ?? 0);

$sql = "SELECT s.*, u.full_name as lecturer_name, c.course_code, c.course_title, d.name as dept_name
        FROM submissions s
        JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
        JOIN courses c ON lc.course_id = c.id
        JOIN departments d ON c.department_id = d.id
        JOIN users u ON s.lecturer_id = u.id
        WHERE d.faculty_id = ? AND s.session_id = ?";
$types  = 'ii';
$params = [$facultyId, $sessionId ?: 0];

if ($filter === 'pending') {
    $sql .= " AND s.status = ?"; $types .= 's'; $params[] = STATUS_PENDING_DEAN;
} elseif ($filter === 'approved') {
    $sql .= " AND s.status IN ('" . STATUS_PENDING_DIRECTOR . "','" . STATUS_APPROVED . "')";
} elseif ($filter === 'reverted') {
    $sql .= " AND s.status = ?"; $types .= 's'; $params[] = STATUS_REVERTED_HOD;
}

if ($deptFilter) {
    $sql .= " AND c.department_id = ?"; $types .= 'i'; $params[] = $deptFilter;
}

$sql .= " ORDER BY FIELD(s.status, '" . STATUS_PENDING_DEAN . "') DESC, s.submitted_at DESC";
$submissions = dbFetchAll($sql, $types, $params);

$departments = getDepartments($facultyId);
?>

<div class="page-content">
    <div class="page-header">
        <h2>QAMS Validation Form</h2>
        <p><?= htmlspecialchars($dept['faculty_name'] ?? '') ?></p>
    </div>

    <!-- Filters -->
    <div class="card mb-24">
        <div class="card-body" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <span style="font-weight:600; margin-right:8px;">Status:</span>
            <a href="<?= BASE_URL ?>dean/validate.php<?= $deptFilter ? '?dept='.$deptFilter : '' ?>"
               class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <a href="<?= BASE_URL ?>dean/validate.php?filter=pending<?= $deptFilter ? '&dept='.$deptFilter : '' ?>"
               class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">⏳ Pending</a>
            <a href="<?= BASE_URL ?>dean/validate.php?filter=approved<?= $deptFilter ? '&dept='.$deptFilter : '' ?>"
               class="btn btn-sm <?= $filter === 'approved' ? 'btn-primary' : 'btn-secondary' ?>">✅ Approved</a>
            <a href="<?= BASE_URL ?>dean/validate.php?filter=reverted<?= $deptFilter ? '&dept='.$deptFilter : '' ?>"
               class="btn btn-sm <?= $filter === 'reverted' ? 'btn-primary' : 'btn-secondary' ?>">🔄 Reverted</a>

            <span style="margin-left:16px; font-weight:600;">Dept:</span>
            <a href="<?= BASE_URL ?>dean/validate.php<?= $filter ? '?filter='.$filter : '' ?>"
               class="btn btn-sm <?= !$deptFilter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <?php foreach ($departments as $d): ?>
                <a href="<?= BASE_URL ?>dean/validate.php?dept=<?= $d['id'] ?><?= $filter ? '&filter='.$filter : '' ?>"
                   class="btn btn-sm <?= $deptFilter === $d['id'] ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= htmlspecialchars($d['code'] ?? $d['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <h3>Submissions</h3>
            <span class="text-muted text-sm"><?= count($submissions) ?> total</span>
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
                        <tr><td colspan="6" class="text-center text-muted" style="padding:32px;">No submissions found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['dept_name']) ?></td>
                                <td><strong><?= htmlspecialchars($s['lecturer_name']) ?></strong></td>
                                <td><?= htmlspecialchars($s['course_code']) ?> – <?= htmlspecialchars($s['course_title']) ?></td>
                                <td><?= statusLabel($s['status']) ?></td>
                                <td><?= timeAgo($s['submitted_at']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>dean/validate.php?id=<?= $s['id'] ?>"
                                       class="btn btn-sm <?= $s['status'] === STATUS_PENDING_DEAN ? 'btn-primary' : 'btn-outline' ?>">
                                        <?= $s['status'] === STATUS_PENDING_DEAN ? 'Review' : 'View' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
