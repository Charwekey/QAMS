<?php
/**
 * Lecturer – QAMS Submission Form
 * Fill / edit / view submission for a specific lecturer-course assignment.
 */
$pageTitle = 'QAMS Submission Form';
require_once __DIR__ . '/../includes/auth.php';
requireRole(ROLE_LECTURER);

$userId      = getCurrentUserId();
$currentUser = getLoggedInUser();
$sessionId   = getCurrentSession();
$lcId        = intval($_GET['lc_id'] ?? 0);
$dept        = getUserDepartment($userId);
$deptId      = $dept['id'] ?? 0;

// ── Course creation for lecturers who input their own courses ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$lcId && ($_POST['action'] ?? '') === 'create_course') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $course_code    = strtoupper(sanitize($_POST['course_code'] ?? ''));
        $course_title   = sanitize($_POST['course_title'] ?? '');
        $credit_hours   = max(1, intval($_POST['credit_hours'] ?? 0));
        $section        = sanitize($_POST['section'] ?? 'A');
        $total_students = max(0, intval($_POST['total_students'] ?? 0));

        if (!$course_code || !$course_title) {
            $error = 'Please provide both the course code and course title.';
        } elseif (!$deptId) {
            $error = 'Unable to determine your department. Please contact the administrator.';
        } else {
            $existingCourse = dbFetchOne(
                "SELECT id FROM courses WHERE course_code = ? AND department_id = ?",
                'si',
                [$course_code, $deptId]
            );

            if ($existingCourse) {
                $courseId = $existingCourse['id'];
            } else {
                $courseId = dbInsert(
                    "INSERT INTO courses (department_id, course_code, course_title, credit_hours)
                     VALUES (?, ?, ?, ?)",
                    'issi',
                    [$deptId, $course_code, $course_title, $credit_hours]
                );
            }

            if ($courseId) {
                $existingAssignment = dbFetchOne(
                    "SELECT id FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ? AND session_id = ? AND section = ?",
                    'iiis',
                    [$userId, $courseId, $sessionId ?: 0, $section]
                );
                if ($existingAssignment) {
                    $lcId = $existingAssignment['id'];
                } else {
                    $lcId = dbInsert(
                        "INSERT INTO lecturer_courses (lecturer_id, course_id, session_id, section, total_students)
                         VALUES (?, ?, ?, ?, ?)",
                        'iiisi',
                        [$userId, $courseId, $sessionId ?: 0, $section, $total_students]
                    );
                }
            }

            if ($lcId) {
                redirect('lecturer/qams_form.php?lc_id=' . $lcId);
            }
        }
    }
}

// ── Validate lecturer-course ownership ────────────────────
$lecturerCourse = null;
if ($lcId) {
    $lecturerCourse = dbFetchOne(
        "SELECT lc.*, c.course_code, c.course_title, c.credit_hours, d.name as dept_name, d.id as department_id, f.name as faculty_name
         FROM lecturer_courses lc
         JOIN courses c ON lc.course_id = c.id
         JOIN departments d ON c.department_id = d.id
         JOIN faculties f ON d.faculty_id = f.id
         WHERE lc.id = ? AND lc.lecturer_id = ?",
        'ii',
        [$lcId, $userId]
    );
}

// If no valid lc_id, show course picker
if (!$lecturerCourse) {
    require_once __DIR__ . '/../includes/header.php';
    require_once __DIR__ . '/../includes/sidebar.php';

    $courses = dbFetchAll(
        "SELECT lc.id, c.course_code, c.course_title, lc.section, c.credit_hours
         FROM lecturer_courses lc
         JOIN courses c ON lc.course_id = c.id
         WHERE lc.lecturer_id = ? AND lc.session_id = ?
         ORDER BY c.course_code",
        'ii',
        [$userId, $sessionId ?: 0]
    );
    ?>
    <div class="page-content">
        <div class="page-header">
            <h2>Select a Course</h2>
            <p>Choose a course to fill or view its QAMS form</p>
        </div>
        <div class="card">
            <div class="card-body">
                <?php if (empty($courses)): ?>
                    <p class="text-center text-muted" style="padding:32px;">No courses assigned for this session.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Title</th>
                                    <th>Section</th>
                                    <th>Credits</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($c['course_title']) ?></td>
                                        <td><?= htmlspecialchars($c['section']) ?></td>
                                        <td><?= $c['credit_hours'] ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>lecturer/qams_form.php?lc_id=<?= $c['id'] ?>"
                                               class="btn btn-sm btn-primary">Open Form</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-24">
            <div class="card-header"><h3>Add Your Course</h3></div>
            <div class="card-body">
                <p class="text-muted">If your course is not assigned, enter the course details below and the system will create it for you.</p>
                <?php if (!empty($error) && isset($_POST['action']) && $_POST['action'] === 'create_course'): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="" class="course-create-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create_course">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Course Code <span class="required">*</span></label>
                            <input type="text" name="course_code" class="form-control" placeholder="e.g. CSC301" required>
                        </div>
                        <div class="form-group">
                            <label>Course Title <span class="required">*</span></label>
                            <input type="text" name="course_title" class="form-control" placeholder="e.g. Database Systems" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Credit Hours <span class="required">*</span></label>
                            <input type="number" name="credit_hours" class="form-control" min="1" value="3" required>
                        </div>
                        <div class="form-group">
                            <label>Section / Group <span class="required">*</span></label>
                            <input type="text" name="section" class="form-control" value="A" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Total Students</label>
                            <input type="number" name="total_students" class="form-control" min="0" value="0">
                        </div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:12px;">
                        <button type="submit" class="btn btn-primary">Add Course & Fill Form</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ── Load existing submission (if any) ─────────────────────
$submission = dbFetchOne(
    "SELECT * FROM submissions WHERE lecturer_course_id = ? AND session_id = ?",
    'ii',
    [$lcId, $sessionId ?: 0]
);

$existingFiles = [];
if ($submission) {
    $fileRows = dbFetchAll(
        "SELECT * FROM submission_files WHERE submission_id = ?",
        'i',
        [$submission['id']]
    );
    foreach ($fileRows as $f) {
        $existingFiles[$f['file_type']] = $f;
    }
}

// Is this read-only? (already submitted, not reverted)
$readOnly = $submission &&
    !in_array($submission['status'], [STATUS_DRAFT, STATUS_REVERTED_LECTURER]);

$error   = '';
$success = '';

// ── Handle form submission ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'save';

        // Initialize submissionId
        $submissionId = $submission['id'] ?? null;

        if ($action === 'request_revert') {
            if ($submissionId) {
                dbExecute(
                    "UPDATE submissions SET revert_requested = 1 WHERE id = ?",
                    'i',
                    [$submissionId]
                );
                
                // Notify HOD
                $hod = dbFetchOne(
                    "SELECT u.id FROM users u
                     JOIN user_type_rel r ON u.id = r.user_id
                     WHERE r.user_type_id = ? AND u.department_id = ?",
                    'ii',
                    [ROLE_HOD, $lecturerCourse['department_id'] ?? 0]
                );
                if ($hod) {
                    createNotification(
                        $hod['id'],
                        'Revert Requested',
                        htmlspecialchars($lecturerCourse['course_code']) . ': Revert requested by ' . htmlspecialchars($currentUser['full_name']),
                        'hod/validate.php'
                    );
                }
                $success = 'Revert request sent to HOD.';
            }
        } elseif (!$readOnly) {
            // Normal Save/Submit Logic
            $data = [
                'learning_feedback_link' => sanitize($_POST['learning_feedback_link'] ?? ''),
                'classes_taken'          => intval($_POST['classes_taken'] ?? 0),
                'class_tests'            => intval($_POST['class_tests'] ?? 0),
                'midterm_taken'          => isset($_POST['midterm_taken']) ? 1 : 0,
                'final_taken'            => isset($_POST['final_taken']) ? 1 : 0,
                'assignments'            => intval($_POST['assignments'] ?? 0),
                'presentations'          => intval($_POST['presentations'] ?? 0),
                'course_outline_covered' => sanitize($_POST['course_outline_covered'] ?? ''),
            ];

            if ($action === 'submit') {
                $requiredFiles = [
                    'attendance' => 'Attendance Sheet',
                    'midterm_question' => 'Midterm Exam Questions',
                    'final_question' => 'Final Exam Questions',
                    'course_outline' => 'Course Outline',
                ];

                foreach ($requiredFiles as $fileKey => $label) {
                    $inputName = 'file_' . $fileKey;
                    $fileUploaded = isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] !== UPLOAD_ERR_NO_FILE;
                    if (!$fileUploaded && !isset($existingFiles[$fileKey])) {
                        $error = "Please upload the required PDF: {$label}.";
                        break;
                    }
                }
            }

            if (empty($error)) {
                $status = ($action === 'submit') ? STATUS_PENDING_HOD : STATUS_DRAFT;

                if ($submission) {
                    // Update existing
                    dbExecute(
                        "UPDATE submissions SET
                            learning_feedback_link = ?, classes_taken = ?, class_tests = ?,
                            midterm_taken = ?, final_taken = ?, assignments = ?, presentations = ?,
                            course_outline_covered = ?, status = ?,
                            submitted_at = " . ($action === 'submit' ? 'NOW()' : 'submitted_at') . ",
                            revert_requested = 0
                         WHERE id = ?",
                        'siiiiiissi',
                        [
                            $data['learning_feedback_link'], $data['classes_taken'], $data['class_tests'],
                            $data['midterm_taken'], $data['final_taken'], $data['assignments'], $data['presentations'],
                            $data['course_outline_covered'], $status, $submission['id']
                        ]
                    );
                    $submissionId = $submission['id'];
                } else {
                    // Insert new
                    $submissionId = dbInsert(
                        "INSERT INTO submissions
                            (lecturer_course_id, session_id, lecturer_id,
                             learning_feedback_link, classes_taken, class_tests,
                             midterm_taken, final_taken, assignments, presentations,
                             course_outline_covered, status, submitted_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, " . ($action === 'submit' ? 'NOW()' : 'NULL') . ")",
                        'iiisiiiiisss',
                        [
                            $lcId, $sessionId ?: 0, $userId,
                            $data['learning_feedback_link'], $data['classes_taken'], $data['class_tests'],
                            $data['midterm_taken'], $data['final_taken'], $data['assignments'], $data['presentations'],
                            $data['course_outline_covered'], $status
                        ]
                    );
                }

                // Handle file uploads
                // Updated file types array to match diagram/migration
                $fileTypes = [
                    'attendance',
                    'midterm_question',
                    'final_question',
                    'course_outline',
                    'assignment',
                    'presentation',
                    'course_coverage'
                ];

                foreach ($fileTypes as $ft) {
                    $inputName = 'file_' . $ft;
                    if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
                        $upload = uploadFile($inputName, 'submissions');
                        if ($upload['success']) {
                            if (isset($existingFiles[$ft])) {
                                dbExecute("DELETE FROM submission_files WHERE id = ?", 'i', [$existingFiles[$ft]['id']]);
                            }
                            dbInsert(
                                "INSERT INTO submission_files (submission_id, file_type, original_name, stored_name, file_path)
                                 VALUES (?, ?, ?, ?, ?)",
                                'issss',
                                [$submissionId, $ft, $upload['original'], $upload['filename'], $upload['filepath']]
                            );
                        } else {
                            $error = $upload['error'];
                            break;
                        }
                    }
                }

                if (empty($error)) {
                    // Notify HOD if submitting
                    if ($action === 'submit') {
                        $hod = dbFetchOne(
                            "SELECT u.id FROM users u
                             JOIN user_type_rel r ON u.id = r.user_id
                             WHERE r.user_type_id = ? AND u.department_id = ?",
                            'ii',
                            [ROLE_HOD, $lecturerCourse['department_id'] ?? 0]
                        );
                        if ($hod) {
                            createNotification(
                                $hod['id'],
                                'New QAMS Submission',
                                htmlspecialchars($lecturerCourse['course_code'] . ' - ' . $lecturerCourse['course_title']) . ' submitted by ' . htmlspecialchars($currentUser['full_name']),
                                'hod/validate.php'
                            );
                        }
                        $success = 'Form submitted to HOD for review!';
                    } else {
                        $success = 'Draft saved successfully.';
                    }
                }
            }
        }
    }

// Re-load submission data
        $submission = dbFetchOne(
            "SELECT * FROM submissions WHERE id = ?", 'i', [$submissionId]
        );
        $fileRows = dbFetchAll(
            "SELECT * FROM submission_files WHERE submission_id = ?", 'i', [$submissionId]
        );
        $existingFiles = [];
        foreach ($fileRows as $f) {
            $existingFiles[$f['file_type']] = $f;
        }
        $readOnly = $submission && !in_array($submission['status'], [STATUS_DRAFT, STATUS_REVERTED_LECTURER]);
}

// Shorthand for field values
$val = function ($field, $default = '') use ($submission) {
    return $submission[$field] ?? $default;
};

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h2><?= htmlspecialchars($lecturerCourse['course_code']) ?> – QAMS Form</h2>
            <p><?= htmlspecialchars($lecturerCourse['course_title']) ?>
                · Section <?= htmlspecialchars($lecturerCourse['section']) ?>
                · <?= $lecturerCourse['credit_hours'] ?> Credit Hours
                · <?= htmlspecialchars($lecturerCourse['dept_name']) ?>
                · <?= htmlspecialchars($lecturerCourse['faculty_name']) ?>
            </p>
        </div>
        <?php if ($submission): ?>
            <div>
                <?= statusLabel($submission['status']) ?>
                <?php if ($submission['revert_requested']): ?>
                    <span class="badge badge-reverted">Revert Requested</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card mb-24">
        <div class="card-header"><h3>Course & Lecturer Details</h3></div>
        <div class="card-body">
            <div class="form-row">
                <div><strong>Lecturer:</strong> <?= htmlspecialchars($currentUser['full_name']) ?></div>
                <div><strong>Employment:</strong> <?= htmlspecialchars($currentUser['employment_type'] === 'part_time' ? 'Part-Time' : 'Full-Time') ?></div>
            </div>
            <div class="form-row mt-8">
                <div><strong>Department:</strong> <?= htmlspecialchars($lecturerCourse['dept_name']) ?></div>
                <div><strong>Faculty:</strong> <?= htmlspecialchars($lecturerCourse['faculty_name']) ?></div>
            </div>
            <div class="form-row mt-8">
                <div><strong>Course Code:</strong> <?= htmlspecialchars($lecturerCourse['course_code']) ?></div>
                <div><strong>Credit Hours:</strong> <?= htmlspecialchars($lecturerCourse['credit_hours']) ?></div>
            </div>
            <div class="form-row mt-8">
                <div><strong>Section / Group:</strong> <?= htmlspecialchars($lecturerCourse['section']) ?></div>
                <div><strong>Total Students:</strong> <?= htmlspecialchars($lecturerCourse['total_students']) ?></div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($readOnly): ?>
        <!-- Reviewer Comments -->
        <?php if ($submission['hod_comment'] || $submission['dean_comment'] || $submission['director_comment']): ?>
            <div class="card mb-24">
                <div class="card-header"><h3>Review Comments</h3></div>
                <div class="card-body">
                    <?php if ($submission['hod_comment']): ?>
                        <div class="mb-16">
                            <strong>HOD Comment:</strong>
                            <p><?= htmlspecialchars($submission['hod_comment']) ?></p>
                            <small class="text-muted"><?= $submission['hod_reviewed_at'] ? formatDateTime($submission['hod_reviewed_at']) : '' ?></small>
                        </div>
                    <?php endif; ?>
                    <?php if ($submission['dean_comment']): ?>
                        <div class="mb-16">
                            <strong>Dean Comment:</strong>
                            <p><?= htmlspecialchars($submission['dean_comment']) ?></p>
                            <small class="text-muted"><?= $submission['dean_reviewed_at'] ? formatDateTime($submission['dean_reviewed_at']) : '' ?></small>
                        </div>
                    <?php endif; ?>
                    <?php if ($submission['director_comment']): ?>
                        <div class="mb-16">
                            <strong>Director Comment:</strong>
                            <p><?= htmlspecialchars($submission['director_comment']) ?></p>
                            <small class="text-muted"><?= $submission['director_reviewed_at'] ? formatDateTime($submission['director_reviewed_at']) : '' ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Revert notice -->
    <?php if ($submission && $submission['status'] === STATUS_REVERTED_LECTURER): ?>
        <div class="alert alert-warning" style="margin-bottom:16px;">
            <strong>This submission has been reverted by the HOD.</strong>
            <?php if ($submission['hod_comment']): ?>
                <p style="margin-top:8px;">"<?= htmlspecialchars($submission['hod_comment']) ?>"</p>
            <?php endif; ?>
            <p style="margin-top:4px;">Please make the required corrections and re-submit.</p>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" data-validate>
        <?= csrfField() ?>

        <!-- ── Course Delivery Details ──────────────── -->
        <div class="card mb-24">
            <div class="card-header"><h3>Course Delivery Details</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Learning Feedback Link</label>
                        <input type="url" name="learning_feedback_link" class="form-control"
                               placeholder="https://forms.google.com/..."
                               value="<?= htmlspecialchars($val('learning_feedback_link')) ?>"
                               <?= $readOnly ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Number of Classes Taken <span class="required">*</span></label>
                        <input type="number" name="classes_taken" class="form-control" min="0"
                               value="<?= intval($val('classes_taken', 0)) ?>"
                               <?= $readOnly ? 'disabled' : 'required' ?>>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Class Tests Given</label>
                        <input type="number" name="class_tests" class="form-control" min="0"
                               value="<?= intval($val('class_tests', 0)) ?>"
                               <?= $readOnly ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Assignments Given</label>
                        <input type="number" name="assignments" class="form-control" min="0"
                               value="<?= intval($val('assignments', 0)) ?>"
                               <?= $readOnly ? 'disabled' : '' ?>>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Presentations Given</label>
                        <input type="number" name="presentations" class="form-control" min="0"
                               value="<?= intval($val('presentations', 0)) ?>"
                               <?= $readOnly ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group" style="display:flex; gap:24px; align-items:center; padding-top:24px;">
                        <label class="checkbox-label">
                            <input type="checkbox" name="midterm_taken" value="1"
                                   <?= $val('midterm_taken') ? 'checked' : '' ?>
                                   <?= $readOnly ? 'disabled' : '' ?>>
                            Midterm Exam Taken
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="final_taken" value="1"
                                   <?= $val('final_taken') ? 'checked' : '' ?>
                                   <?= $readOnly ? 'disabled' : '' ?>>
                            Final Exam Taken
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Course Outline Coverage / Remarks</label>
                    <textarea name="course_outline_covered" class="form-control" rows="4"
                              placeholder="Describe the extent of course outline coverage and any additional remarks..."
                              <?= $readOnly ? 'disabled' : '' ?>><?= htmlspecialchars($val('course_outline_covered')) ?></textarea>
                </div>
            </div>
        </div>

        <!-- ── File Uploads ─────────────────────────── -->
        <div class="card mb-24">
            <div class="card-header"><h3>Supporting Documents (PDF)</h3></div>
            <div class="card-body">
                <p class="text-muted" style="margin-bottom:16px;">Upload PDF copies of the course outline, attendance sheet, and exam questions for HOD review.</p>
                <?php
                $fileFields = [
                    'course_outline'   => ['label' => 'Course Outline',         'icon' => '📄'],
                    'attendance'       => ['label' => 'Attendance Sheet',       'icon' => '📋'],
                    'assignment'       => ['label' => 'Assignment (Sample)',    'icon' => '📝'],
                    'presentation'     => ['label' => 'Presentation (Sample)',  'icon' => '📊'],
                    'midterm_question' => ['label' => 'Midterm Exam Questions', 'icon' => '📝'],
                    'final_question'   => ['label' => 'Final Exam Questions',   'icon' => '📝'],
                    'course_coverage'  => ['label' => 'Course Coverage Evidence','icon' => '📁'],
                ];
                foreach ($fileFields as $key => $meta):
                    $hasFile = isset($existingFiles[$key]);
                ?>
                    <div class="form-group">
                        <label><?= $meta['icon'] ?> <?= $meta['label'] ?></label>
                        <?php if ($hasFile): ?>
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                                <span class="badge badge-approved" style="font-size:0.75rem;">Uploaded</span>
                                <a href="<?= BASE_URL ?>uploads/<?= htmlspecialchars($existingFiles[$key]['file_path']) ?>"
                                   target="_blank" style="font-size:0.8125rem;">
                                    <?= htmlspecialchars($existingFiles[$key]['original_name']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if (!$readOnly): ?>
                            <div class="file-upload">
                                <input type="file" name="file_<?= $key ?>" accept=".pdf">
                                <p><?= $hasFile ? 'Upload new file to replace' : 'Click to upload or drag & drop' ?></p>
                                <small>PDF only, max 10MB</small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Actions ──────────────────────────────── -->
        <div class="card">
            <div class="card-body" style="display:flex; gap:12px; justify-content:flex-end; flex-wrap:wrap;">
                
                <?php if ($readOnly): ?>
                    <!-- Revert Request Button (if submitted but not yet reverted) -->
                    <?php if (!$submission['revert_requested']): ?>
                        <button type="submit" name="action" value="request_revert" class="btn btn-warning"
                                data-confirm="Request the HOD to revert this submission so you can edit it?">
                            ↩ Request Revert
                        </button>
                    <?php else: ?>
                        <span class="btn btn-secondary disabled">Revert Requested</span>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>lecturer/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                
                <?php else: ?>
                    <!-- Editable actions -->
                    <a href="<?= BASE_URL ?>lecturer/dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="action" value="save" class="btn btn-secondary">
                        💾 Save as Draft
                    </button>
                    <button type="submit" name="action" value="submit" class="btn btn-primary"
                            data-confirm="Are you sure you want to submit this form to the HOD? You will not be able to edit it unless it is reverted.">
                        📤 Submit to HOD
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
