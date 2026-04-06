<?php
/**
 * HOD – Validate Submissions
 * View, approve, or revert lecturer QAMS submissions.
 */
$pageTitle = 'Validate Submissions';
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

$error = '';
$success = '';
$subId = intval($_GET['id'] ?? 0);

// ── Handle approve / revert actions ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subId) {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        $comment = sanitize($_POST['comment'] ?? '');

        // Verify submission belongs to this department
        $check = dbFetchOne(
            "SELECT s.id, s.lecturer_id, s.lecturer_course_id, c.course_code, c.course_title
             FROM submissions s
             JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
             JOIN courses c ON lc.course_id = c.id
             WHERE s.id = ? AND c.department_id = ? AND s.status IN (?, ?)",
            'iiss',
            [$subId, $deptId, STATUS_PENDING_HOD, STATUS_REVERTED_HOD]
        );

        if (!$check) {
            $error = 'Submission not found or not available for review.';
        } elseif ($action === 'approve') {
            dbExecute(
                "UPDATE submissions SET status = ?, hod_comment = ?, hod_reviewed_at = NOW(), revert_requested = 0 WHERE id = ?",
                'ssi',
                [STATUS_PENDING_DEAN, $comment, $subId]
            );
            // Notify lecturer
            createNotification(
                $check['lecturer_id'],
                'Submission Approved by HOD',
                $check['course_code'] . ' – ' . $check['course_title'] . ' has been forwarded to the Dean.',
                'lecturer/qams_form.php?lc_id=' . $check['lecturer_course_id']
            );
            // Notify Dean
            $dean = dbFetchOne(
                "SELECT u.id FROM users u
                 JOIN user_type_rel r ON u.id = r.user_id
                 JOIN departments d ON u.department_id = d.id
                 WHERE r.user_type_id = ? AND d.faculty_id = ?",
                'ii',
                [ROLE_DEAN, $dept['faculty_id'] ?? 0]
            );
            if ($dean) {
                createNotification(
                    $dean['id'],
                    'New Submission for Dean Review',
                    $check['course_code'] . ' – ' . $check['course_title'] . ' approved by HOD.',
                    'dean/validate.php'
                );
            }
            $success = 'Submission approved and forwarded to Dean!';
        } elseif ($action === 'revert') {
            if (empty($comment)) {
                $comment = 'Reverted by HOD. Please review and update your submission.';
            }
            dbExecute(
                "UPDATE submissions SET status = ?, hod_comment = ?, hod_reviewed_at = NOW(), revert_requested = 0 WHERE id = ?",
                'ssi',
                [STATUS_REVERTED_LECTURER, $comment, $subId]
            );
            createNotification(
                $check['lecturer_id'],
                'Submission Reverted by HOD',
                $check['course_code'] . ' – ' . $check['course_title'] . ' was reverted. Please review and re-submit.',
                'lecturer/qams_form.php?lc_id=' . $check['lecturer_course_id']
            );
            $success = 'Submission reverted back to lecturer.';
        }
    }
}

// ── Single submission view ────────────────────────────────
if ($subId) {
    $submission = dbFetchOne(
        "SELECT s.*, u.full_name as lecturer_name, u.email as lecturer_email,
                u.employee_id, u.designation,
                c.course_code, c.course_title, c.credit_hours,
                lc.section, lc.total_students
         FROM submissions s
         JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
         JOIN courses c ON lc.course_id = c.id
         JOIN users u ON s.lecturer_id = u.id
         WHERE s.id = ? AND c.department_id = ?",
        'ii',
        [$subId, $deptId]
    );

    if (!$submission) {
        setFlash('error', 'Submission not found.');
        redirect('hod/validate.php');
    }

    $files = dbFetchAll(
        "SELECT * FROM submission_files WHERE submission_id = ?",
        'i',
        [$subId]
    );
    $filesByType = [];
    foreach ($files as $f) {
        $filesByType[$f['file_type']] = $f;
    }

    $canReview = in_array($submission['status'], [STATUS_PENDING_HOD, STATUS_REVERTED_HOD]);
    ?>
    <div class="page-content">
        <div class="page-header">
            <div>
                <h2>
                    <?= htmlspecialchars($submission['course_code']) ?> – Submission Review
                </h2>
                <p>
                    <?= htmlspecialchars($submission['course_title']) ?>
                    · Section
                    <?= htmlspecialchars($submission['section']) ?>
                    ·
                    <?= $submission['credit_hours'] ?> Credits
                </p>
            </div>
            <div>
                <?= statusLabel($submission['status']) ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($submission['revert_requested']): ?>
            <div class="alert alert-warning" style="margin-bottom:24px; border-left:4px solid #f59e0b;">
                <h4 style="margin:0 0 8px 0;">↩ Revert Requested</h4>
                <p style="margin:0;">The lecturer has requested that this submission be reverted for changes.</p>
            </div>
        <?php endif; ?>

        <!-- Lecturer Info -->
        <div class="card mb-24">
            <div class="card-header">
                <h3>Lecturer Information</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div><strong>Name:</strong>
                        <?= htmlspecialchars($submission['lecturer_name']) ?>
                    </div>
                    <div><strong>Employee ID:</strong>
                        <?= htmlspecialchars($submission['employee_id'] ?? 'N/A') ?>
                    </div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Email:</strong>
                        <?= htmlspecialchars($submission['lecturer_email']) ?>
                    </div>
                    <div><strong>Designation:</strong>
                        <?= htmlspecialchars($submission['designation'] ?? 'N/A') ?>
                    </div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Students:</strong>
                        <?= $submission['total_students'] ?>
                    </div>
                    <div><strong>Submitted:</strong>
                        <?= $submission['submitted_at'] ? timeAgo($submission['submitted_at']) : '—' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Delivery Details -->
        <div class="card mb-24">
            <div class="card-header">
                <h3>Course Delivery Details</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div><strong>Classes Taken:</strong>
                        <?= intval($submission['classes_taken']) ?>
                    </div>
                    <div><strong>Class Tests:</strong>
                        <?= intval($submission['class_tests']) ?>
                    </div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Assignments:</strong>
                        <?= intval($submission['assignments']) ?>
                    </div>
                    <div><strong>Presentations:</strong>
                        <?= intval($submission['presentations']) ?>
                    </div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Midterm Exam:</strong>
                        <?= $submission['midterm_taken'] ? '✅ Yes' : '❌ No' ?>
                    </div>
                    <div><strong>Final Exam:</strong>
                        <?= $submission['final_taken'] ? '✅ Yes' : '❌ No' ?>
                    </div>
                </div>
                <?php if ($submission['learning_feedback_link']): ?>
                    <div class="mt-8">
                        <strong>Feedback Link:</strong>
                        <a href="<?= htmlspecialchars($submission['learning_feedback_link']) ?>" target="_blank"
                            style="word-break:break-all;">
                            <?= htmlspecialchars($submission['learning_feedback_link']) ?>
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($submission['course_outline_covered']): ?>
                    <div class="mt-8">
                        <strong>Course Outline Coverage:</strong>
                        <p style="margin-top:4px;">
                            <?= nl2br(htmlspecialchars($submission['course_outline_covered'])) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Uploaded Files -->
        <div class="card mb-24">
            <div class="card-header">
                <h3>Supporting Documents</h3>
            </div>
            <div class="card-body">
                <?php
                $fileLabels = [
                    'course_outline' => ['📄', 'Course Outline'],
                    'attendance' => ['📋', 'Attendance Sheet'],
                    'assignment' => ['📝', 'Assignment (Sample)'],
                    'presentation' => ['📊', 'Presentation (Sample)'],
                    'midterm_question' => ['📝', 'Midterm Exam Questions'],
                    'final_question' => ['📝', 'Final Exam Questions'],
                    'course_coverage' => ['📁', 'Course Coverage Evidence'],
                ];
                foreach ($fileLabels as $key => $meta):
                    $hasFile = isset($filesByType[$key]);
                    ?>
                    <div
                        style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--border);">
                        <span style="font-size:1.2rem;">
                            <?= $meta[0] ?>
                        </span>
                        <strong style="flex:1;">
                            <?= $meta[1] ?>
                        </strong>
                        <?php if ($hasFile): ?>
                            <a href="<?= BASE_URL ?>uploads/<?= htmlspecialchars($filesByType[$key]['file_path']) ?>"
                                target="_blank" class="btn btn-sm btn-outline">
                                📥 View / Download
                            </a>
                        <?php else: ?>
                            <span class="text-muted text-sm">Not uploaded</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Review Action -->
        <?php if ($canReview): ?>
            <div class="card">
                <div class="card-header">
                    <h3>Verify QAMS Validation Form</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="?id=<?= $subId ?>">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label>Comment / Feedback</label>
                            <textarea name="comment" class="form-control" rows="3"
                                placeholder="<?= $submission['revert_requested'] ? 'Lecturer requested revert. Please add a comment acknowledging it.' : 'Add your review comments (required for revert)...' ?>"><?= $submission['revert_requested'] ? 'Revert request details: ' : '' ?></textarea>
                        </div>
                        <div style="display:flex; gap:12px; justify-content:flex-end; flex-wrap:wrap;">
                            <a href="<?= BASE_URL ?>hod/validate.php" class="btn btn-secondary">← Back</a>
                            <button type="submit" name="action" value="revert" class="btn btn-danger"
                                data-confirm="Are you sure you want to revert this submission back to the lecturer?">
                                🔄 Apply For Revert
                            </button>
                            <button type="submit" name="action" value="approve" class="btn btn-primary"
                                data-confirm="Are you sure you want to approve and forward this to the Dean?">
                                ✅ Submit to Dean
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Already reviewed -->
            <?php if ($submission['hod_comment']): ?>
                <div class="card mb-24">
                    <div class="card-header">
                        <h3>Your Previous Review</h3>
                    </div>
                    <div class="card-body">
                        <p>
                            <?= nl2br(htmlspecialchars($submission['hod_comment'])) ?>
                        </p>
                        <small class="text-muted">Reviewed:
                            <?= $submission['hod_reviewed_at'] ? formatDateTime($submission['hod_reviewed_at']) : '—' ?>
                        </small>
                    </div>
                </div>
            <?php endif; ?>
            <div style="text-align:right;">
                <a href="<?= BASE_URL ?>hod/validate.php" class="btn btn-secondary">← Back to All Submissions</a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ── List all submissions ──────────────────────────────────
$filter = sanitize($_GET['filter'] ?? 'pending');
$sql = "SELECT s.*, u.full_name as lecturer_name, c.course_code, c.course_title
        FROM submissions s
        JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
        JOIN courses c ON lc.course_id = c.id
        JOIN users u ON s.lecturer_id = u.id
        WHERE c.department_id = ? AND s.session_id = ?";
$types = 'ii';
$params = [$deptId, $sessionId ?: 0];

if ($filter === 'pending') {
    $sql .= " AND s.status IN (?, ?)";
    $types .= 'ss';
    $params[] = STATUS_PENDING_HOD;
    $params[] = STATUS_REVERTED_HOD;
} elseif ($filter === 'approved') {
    $sql .= " AND s.status IN ('" . STATUS_PENDING_DEAN . "','" . STATUS_PENDING_DIRECTOR . "','" . STATUS_APPROVED . "')";
} elseif ($filter === 'reverted') {
    $sql .= " AND s.status = ?";
    $types .= 's';
    $params[] = STATUS_REVERTED_LECTURER;
}

$sql .= " ORDER BY s.revert_requested DESC, FIELD(s.status, '" . STATUS_PENDING_HOD . "') DESC, s.submitted_at DESC";
$submissions = dbFetchAll($sql, $types, $params);
?>

<div class="page-content">
    <div class="page-header">
        <h2>QAMS Validation Form</h2>
        <p>
            <?= htmlspecialchars($dept['name'] ?? '') ?> Department
        </p>
    </div>

    <!-- Filters -->
    <div class="card mb-24">
        <div class="card-body" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <span style="font-weight:600; margin-right:8px;">Filter:</span>
            <a href="<?= BASE_URL ?>hod/validate.php"
                class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <a href="<?= BASE_URL ?>hod/validate.php?filter=pending"
                class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">⏳ Pending</a>
            <a href="<?= BASE_URL ?>hod/validate.php?filter=approved"
                class="btn btn-sm <?= $filter === 'approved' ? 'btn-primary' : 'btn-secondary' ?>">✅ Approved</a>
            <a href="<?= BASE_URL ?>hod/validate.php?filter=reverted"
                class="btn btn-sm <?= $filter === 'reverted' ? 'btn-primary' : 'btn-secondary' ?>">🔄 Reverted</a>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <h3>Submissions</h3>
            <span class="text-muted text-sm">
                <?= count($submissions) ?> total
            </span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Lecturer</th>
                        <th>Course</th>
                        <th>Classes</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding:32px;">
                                No submissions found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($s['lecturer_name']) ?></strong>
                                    <?php if ($s['revert_requested']): ?>
                                        <div style="margin-top:4px;">
                                            <span class="badge badge-reverted" style="font-size:0.75rem;">↩ Revert Requested</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($s['course_code']) ?> –
                                    <?= htmlspecialchars($s['course_title']) ?>
                                </td>
                                <td>
                                    <?= intval($s['classes_taken']) ?>
                                </td>
                                <td>
                                    <?= statusLabel($s['status']) ?>
                                </td>
                                <td>
                                    <?= $s['submitted_at'] ? timeAgo($s['submitted_at']) : '—' ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>hod/validate.php?id=<?= $s['id'] ?>"
                                        class="btn btn-sm <?= $s['status'] === STATUS_PENDING_HOD ? 'btn-primary' : 'btn-outline' ?>">
                                        <?= $s['status'] === STATUS_PENDING_HOD ? 'Review' : 'View' ?>
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