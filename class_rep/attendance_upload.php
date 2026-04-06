<?php
/**
 * Class Rep – Upload Attendance
 * Upload signed attendance forms (PDF).
 */
$pageTitle = 'Upload Attendance';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_CLASS_REP);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$dept = getUserDepartment($userId);
$deptId = $dept['id'] ?? 0;

$error = '';
$success = '';

// Get department courses
$courses = dbFetchAll(
    "SELECT * FROM courses WHERE department_id = ? ORDER BY course_code",
    'i',
    [$deptId]
);

// ── Handle file upload ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $courseId = intval($_POST['course_id'] ?? 0) ?: null;
        $upload = uploadFile('attendance_file', 'attendance');

        if (!$upload['success']) {
            $error = $upload['error'];
        } else {
            dbInsert(
                "INSERT INTO attendance_uploads (user_id, session_id, course_id, original_name, stored_name, file_path)
                 VALUES (?, ?, ?, ?, ?, ?)",
                'iiisss',
                [$userId, $sessionId ?: 0, $courseId, $upload['original'], $upload['filename'], $upload['filepath']]
            );
            $success = 'Attendance sheet uploaded successfully!';
        }
    }
}

// ── Fetch upload history ──────────────────────────────────
$uploads = dbFetchAll(
    "SELECT a.*, c.course_code, c.course_title
     FROM attendance_uploads a
     LEFT JOIN courses c ON a.course_id = c.id
     WHERE a.user_id = ?
     ORDER BY a.uploaded_at DESC",
    'i',
    [$userId]
);
?>

<div class="page-content">
    <div class="page-header">
        <h2>Upload Attendance</h2>
        <p>Upload signed attendance forms for your courses</p>
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

    <!-- Upload Form -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>Upload New Attendance Sheet</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data" data-validate>
                <?= csrfField() ?>

                <div class="form-group">
                    <label>Course (optional)</label>
                    <select name="course_id" class="form-control">
                        <option value="">General / All Courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['course_code'] . ' – ' . $c['course_title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Attendance File (PDF) <span class="required">*</span></label>
                    <div class="file-upload">
                        <input type="file" name="attendance_file" accept=".pdf" required>
                        <p>Click to upload or drag & drop</p>
                        <small>PDF only, max 10MB</small>
                    </div>
                </div>

                <div style="text-align:right;">
                    <button type="submit" class="btn btn-primary">📄 Upload Attendance</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload History -->
    <div class="card">
        <div class="card-header">
            <h3>Upload History</h3>
            <span class="text-muted text-sm">
                <?= count($uploads) ?> file
                <?= count($uploads) !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Course</th>
                        <th>Uploaded</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($uploads)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted" style="padding:32px;">
                                No attendance sheets uploaded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($uploads as $u): ?>
                            <tr>
                                <td>📄
                                    <?= htmlspecialchars($u['original_name']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(($u['course_code'] ?? '') . ($u['course_title'] ? ' – ' . $u['course_title'] : '—')) ?>
                                </td>
                                <td>
                                    <?= timeAgo($u['uploaded_at']) ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>uploads/<?= htmlspecialchars($u['file_path']) ?>" target="_blank"
                                        class="btn btn-sm btn-outline">📥 View</a>
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