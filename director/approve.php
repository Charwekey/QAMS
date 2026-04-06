<?php
/**
 * Director – Final Approval
 * Review Dean-approved submissions across the university, give final approval or revert.
 */
$pageTitle = 'Final Approval';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_DIRECTOR);

$userId    = getCurrentUserId();
$sessionId = getCurrentSession();

$error   = '';
$success = '';
$subId   = intval($_GET['id'] ?? 0);

// ── Handle approve / revert ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subId) {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $action  = $_POST['action'] ?? '';
        $comment = sanitize($_POST['comment'] ?? '');

        $check = dbFetchOne(
            "SELECT s.id, s.lecturer_id, s.lecturer_course_id, c.course_code, c.course_title, c.department_id, d.faculty_id
             FROM submissions s
             JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
             JOIN courses c ON lc.course_id = c.id
             JOIN departments d ON c.department_id = d.id
             WHERE s.id = ? AND s.status = ?",
            'is', [$subId, STATUS_PENDING_DIRECTOR]
        );

        if (!$check) {
            $error = 'Submission not found or not available for review.';
        } elseif ($action === 'approve') {
            dbExecute(
                "UPDATE submissions SET status = ?, director_comment = ?, director_reviewed_at = NOW() WHERE id = ?",
                'ssi', [STATUS_APPROVED, $comment, $subId]
            );
            createNotification($check['lecturer_id'], 'Submission Approved ✅',
                $check['course_code'] . ' – ' . $check['course_title'] . ' has been fully approved by the Director.',
                'lecturer/qams_form.php?lc_id=' . $check['lecturer_course_id']);
            $success = 'Submission approved!';
        } elseif ($action === 'revert') {
            if (empty($comment)) {
                $comment = 'Reverted by QA Director. Please review and update your submission.';
            }
            dbExecute(
                "UPDATE submissions SET status = ?, director_comment = ?, director_reviewed_at = NOW() WHERE id = ?",
                'ssi', [STATUS_REVERTED_DEAN, $comment, $subId]
            );
                // Notify Dean
                $dean = dbFetchOne(
                    "SELECT u.id FROM users u JOIN user_type_rel r ON u.id = r.user_id
                     JOIN departments d2 ON u.department_id = d2.id
                     WHERE r.user_type_id = ? AND d2.faculty_id = ?",
                    'ii', [ROLE_DEAN, $check['faculty_id']]
                );
                if ($dean) {
                    createNotification($dean['id'], 'Submission Reverted by Director',
                        $check['course_code'] . ' was reverted. Please review.',
                        'dean/validate.php?id=' . $subId);
                }
                $success = 'Submission reverted to Dean.';
        }
    }
}

// ── Single submission view ────────────────────────────────
if ($subId) {
    $submission = dbFetchOne(
        "SELECT s.*, u.full_name as lecturer_name, u.email as lecturer_email,
                c.course_code, c.course_title, c.credit_hours,
                lc.section, lc.total_students,
                d.name as dept_name, f.name as faculty_name
         FROM submissions s
         JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
         JOIN courses c ON lc.course_id = c.id
         JOIN departments d ON c.department_id = d.id
         JOIN faculties f ON d.faculty_id = f.id
         JOIN users u ON s.lecturer_id = u.id
         WHERE s.id = ?",
        'i', [$subId]
    );

    if (!$submission) {
        setFlash('error', 'Submission not found.');
        redirect('director/approve.php');
    }

    $files = dbFetchAll("SELECT * FROM submission_files WHERE submission_id = ?", 'i', [$subId]);
    $filesByType = [];
    foreach ($files as $f) $filesByType[$f['file_type']] = $f;

    $canReview = ($submission['status'] === STATUS_PENDING_DIRECTOR);
    ?>
    <div class="page-content">
        <div class="page-header">
            <div>
                <h2><?= htmlspecialchars($submission['course_code']) ?> – QAMS Approval</h2>
                <p><?= htmlspecialchars($submission['faculty_name']) ?>
                    · <?= htmlspecialchars($submission['dept_name']) ?>
                    · <?= htmlspecialchars($submission['course_title']) ?>
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

        <!-- Lecturer & Course Info -->
        <div class="card mb-24">
            <div class="card-header"><h3>Submission Overview</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div><strong>Lecturer:</strong> <?= htmlspecialchars($submission['lecturer_name']) ?></div>
                    <div><strong>Email:</strong> <?= htmlspecialchars($submission['lecturer_email']) ?></div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Faculty:</strong> <?= htmlspecialchars($submission['faculty_name']) ?></div>
                    <div><strong>Department:</strong> <?= htmlspecialchars($submission['dept_name']) ?></div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Section:</strong> <?= htmlspecialchars($submission['section']) ?> · <?= $submission['total_students'] ?> students</div>
                    <div><strong>Credits:</strong> <?= $submission['credit_hours'] ?></div>
                </div>
                <div class="form-row mt-8">
                    <div><strong>Submitted:</strong> <?= $submission['submitted_at'] ? timeAgo($submission['submitted_at']) : '—' ?></div>
                </div>
            </div>
        </div>

        <!-- Course Delivery -->
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
                    <div><strong>Midterm:</strong> <?= $submission['midterm_taken'] ? '✅ Yes' : '❌ No' ?></div>
                    <div><strong>Final:</strong> <?= $submission['final_taken'] ? '✅ Yes' : '❌ No' ?></div>
                </div>
            </div>
        </div>

        <!-- Review Chain -->
        <div class="card mb-24">
            <div class="card-header"><h3>Review Chain</h3></div>
            <div class="card-body">
                <div style="padding:8px 0; border-bottom:1px solid var(--border);">
                    <strong>HOD Review:</strong>
                    <?php if ($submission['hod_comment']): ?>
                        <p style="margin:4px 0;"><?= nl2br(htmlspecialchars($submission['hod_comment'])) ?></p>
                        <small class="text-muted">Reviewed: <?= $submission['hod_reviewed_at'] ? formatDateTime($submission['hod_reviewed_at']) : '—' ?></small>
                    <?php else: ?>
                        <span class="text-muted">No comment</span>
                    <?php endif; ?>
                </div>
                <div style="padding:8px 0;">
                    <strong>Dean Review:</strong>
                    <?php if ($submission['dean_comment']): ?>
                        <p style="margin:4px 0;"><?= nl2br(htmlspecialchars($submission['dean_comment'])) ?></p>
                        <small class="text-muted">Reviewed: <?= $submission['dean_reviewed_at'] ? formatDateTime($submission['dean_reviewed_at']) : '—' ?></small>
                    <?php else: ?>
                        <span class="text-muted">No comment</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Documents -->
        <div class="card mb-24">
            <div class="card-header"><h3>Supporting Documents</h3></div>
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
                    <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--border);">
                        <span style="font-size:1.2rem;"><?= $meta[0] ?></span>
                        <strong style="flex:1;"><?= $meta[1] ?></strong>
                        <?php if ($hasFile): ?>
                            <a href="<?= BASE_URL ?>uploads/<?= htmlspecialchars($filesByType[$key]['file_path']) ?>"
                                target="_blank" class="btn btn-sm btn-outline">📥 View</a>
                        <?php else: ?>
                            <span class="text-muted text-sm">Not uploaded</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Director Action -->
        <?php if ($canReview): ?>
            <div class="card">
                <div class="card-header"><h3>Verify QAMS Approval</h3></div>
                <div class="card-body">
                    <form method="POST" action="?id=<?= $subId ?>">
                        <?= csrfField() ?>
                        <div class="form-group">
                            <label>Comment</label>
                            <textarea name="comment" class="form-control" rows="3"
                                      placeholder="Final remarks (required for revert)..."></textarea>
                        </div>
                        <div style="display:flex; gap:12px; justify-content:flex-end; flex-wrap:wrap;">
                            <a href="<?= BASE_URL ?>director/approve.php" class="btn btn-secondary">← Back</a>
                            <button type="submit" name="action" value="revert" class="btn btn-danger"
                                    data-confirm="Revert back to the Dean?">🔄 Apply For Revert</button>
                            <button type="submit" name="action" value="approve" class="btn btn-primary"
                                    data-confirm="Give final approval?">✅ Approve</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <?php if ($submission['director_comment']): ?>
                <div class="card mb-24">
                    <div class="card-header"><h3>Your Decision</h3></div>
                    <div class="card-body">
                        <p><?= nl2br(htmlspecialchars($submission['director_comment'])) ?></p>
                        <small class="text-muted">Reviewed: <?= $submission['director_reviewed_at'] ? formatDateTime($submission['director_reviewed_at']) : '—' ?></small>
                    </div>
                </div>
            <?php endif; ?>
            <div style="text-align:right;">
                <a href="<?= BASE_URL ?>director/approve.php" class="btn btn-secondary">← Back</a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ── List view ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_scope'])) {
    if (!verifyCsrf()) {
        setFlash('error', 'Invalid security token.');
    } else {
        clearSelectedScope();
        setFlash('success', 'Review scope reset successfully.');
    }
    redirect('director/approve.php');
}

$filter    = sanitize($_GET['filter'] ?? 'pending');
$facFilter = isset($_GET['fac']) ? intval($_GET['fac']) : (getSelectedFaculty() ?: 0);
$deptFilter = isset($_GET['dept']) ? intval($_GET['dept']) : (getSelectedDepartment() ?: 0);
$selectedFaculty = $facFilter ? dbFetchOne("SELECT * FROM faculties WHERE id = ?", 'i', [$facFilter]) : null;
$selectedDepartment = $deptFilter ? dbFetchOne("SELECT * FROM departments WHERE id = ?", 'i', [$deptFilter]) : null;

$sql = "SELECT s.*, u.full_name as lecturer_name, c.course_code, c.course_title,
               d.name as dept_name, f.name as faculty_name
        FROM submissions s
        JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
        JOIN courses c ON lc.course_id = c.id
        JOIN departments d ON c.department_id = d.id
        JOIN faculties f ON d.faculty_id = f.id
        JOIN users u ON s.lecturer_id = u.id
        WHERE s.session_id = ?
        AND s.status IN ('" . STATUS_PENDING_DIRECTOR . "', '" . STATUS_APPROVED . "', '" . STATUS_REVERTED_DEAN . "')";
$types  = 'i';
$params = [$sessionId ?: 0];

if ($filter === 'pending') {
    $sql .= " AND s.status = ?"; $types .= 's'; $params[] = STATUS_PENDING_DIRECTOR;
} elseif ($filter === 'approved') {
    $sql .= " AND s.status = ?"; $types .= 's'; $params[] = STATUS_APPROVED;
} elseif ($filter === 'reverted') {
    $sql .= " AND s.status = ?"; $types .= 's'; $params[] = STATUS_REVERTED_DEAN;
}

if ($facFilter) {
    $sql .= " AND d.faculty_id = ?"; $types .= 'i'; $params[] = $facFilter;
}

if ($deptFilter) {
    $sql .= " AND c.department_id = ?"; $types .= 'i'; $params[] = $deptFilter;
}

$sql .= " ORDER BY FIELD(s.status, '" . STATUS_PENDING_DIRECTOR . "') DESC, s.updated_at DESC";
$submissions = dbFetchAll($sql, $types, $params);

$faculties = getFaculties();
$departments = getDepartments($facFilter ?: null);
?>

<div class="page-content">
    <div class="page-header">
        <h2>QAMS Approval</h2>
        <p>University-wide submission review</p>
    </div>

    <?php if ($selectedFaculty || $selectedDepartment): ?>
        <div class="alert alert-info" style="margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; gap:16px;">
            <div>
                <strong>Current scope:</strong>
                <?= $selectedFaculty ? htmlspecialchars($selectedFaculty['name']) : 'All Faculties' ?>
                <?= $selectedDepartment ? ' / ' . htmlspecialchars($selectedDepartment['name']) : '' ?>
            </div>
            <form method="POST" action="" style="margin:0;">
                <?= csrfField() ?>
                <input type="hidden" name="reset_scope" value="1">
                <button type="submit" class="btn btn-sm btn-secondary">Change scope</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-24">
        <div class="card-body" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <span style="font-weight:600; margin-right:8px;">Status:</span>
            <a href="<?= BASE_URL ?>director/approve.php<?= $filter || $facFilter || $deptFilter ? '?' . http_build_query(array_filter(['filter' => $filter, 'fac' => $facFilter, 'dept' => $deptFilter])) : '' ?>"
               class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <a href="<?= BASE_URL ?>director/approve.php?filter=pending<?= $facFilter ? '&fac='.$facFilter : '' ?><?= $deptFilter ? '&dept='.$deptFilter : '' ?>"
               class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">⏳ Pending</a>
            <a href="<?= BASE_URL ?>director/approve.php?filter=approved<?= $facFilter ? '&fac='.$facFilter : '' ?><?= $deptFilter ? '&dept='.$deptFilter : '' ?>"
               class="btn btn-sm <?= $filter === 'approved' ? 'btn-primary' : 'btn-secondary' ?>">✅ Approved</a>
            <a href="<?= BASE_URL ?>director/approve.php?filter=reverted<?= $facFilter ? '&fac='.$facFilter : '' ?><?= $deptFilter ? '&dept='.$deptFilter : '' ?>"
               class="btn btn-sm <?= $filter === 'reverted' ? 'btn-primary' : 'btn-secondary' ?>">🔄 Reverted</a>

            <span style="margin-left:16px; font-weight:600;">Faculty:</span>
            <a href="<?= BASE_URL ?>director/approve.php<?= $filter || $deptFilter ? '?' . http_build_query(array_filter(['filter' => $filter, 'dept' => $deptFilter])) : '' ?>"
               class="btn btn-sm <?= !$facFilter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <?php foreach ($faculties as $f): ?>
                <a href="<?= BASE_URL ?>director/approve.php?fac=<?= $f['id'] ?><?= $filter ? '&filter='.$filter : '' ?><?= $deptFilter ? '&dept='.$deptFilter : '' ?>"
                   class="btn btn-sm <?= $facFilter === $f['id'] ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= htmlspecialchars($f['code'] ?? $f['name']) ?>
                </a>
            <?php endforeach; ?>

            <span style="margin-left:16px; font-weight:600;">Department:</span>
            <a href="<?= BASE_URL ?>director/approve.php<?= $filter || $facFilter ? '?'. http_build_query(array_filter(['filter' => $filter, 'fac' => $facFilter])) : '' ?>"
               class="btn btn-sm <?= !$deptFilter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <?php foreach ($departments as $d): ?>
                <a href="<?= BASE_URL ?>director/approve.php?dept=<?= $d['id'] ?><?= $filter ? '&filter='.$filter : '' ?><?= $facFilter ? '&fac='.$facFilter : '' ?>"
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
                        <th>Faculty</th>
                        <th>Department</th>
                        <th>Lecturer</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No submissions found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['faculty_name']) ?></td>
                                <td><?= htmlspecialchars($s['dept_name']) ?></td>
                                <td><strong><?= htmlspecialchars($s['lecturer_name']) ?></strong></td>
                                <td><?= htmlspecialchars($s['course_code']) ?></td>
                                <td><?= statusLabel($s['status']) ?></td>
                                <td><?= timeAgo($s['submitted_at']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>director/approve.php?id=<?= $s['id'] ?>"
                                       class="btn btn-sm <?= $s['status'] === STATUS_PENDING_DIRECTOR ? 'btn-primary' : 'btn-outline' ?>">
                                        <?= $s['status'] === STATUS_PENDING_DIRECTOR ? 'Review' : 'View' ?>
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
