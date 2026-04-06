<?php
/**
 * Class Rep – Submit Feedback
 * Submit feedback on course delivery, resource adequacy, infrastructure.
 */
$pageTitle = 'Submit Feedback';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_CLASS_REP);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$dept = getUserDepartment($userId);
$deptId = $dept['id'] ?? 0;

$error = '';
$success = '';

// Get department courses for the dropdown
$courses = dbFetchAll(
    "SELECT c.* FROM courses c WHERE c.department_id = ? ORDER BY c.course_code",
    'i',
    [$deptId]
);

// ── Handle form submission ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $category = sanitize($_POST['category'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $message = sanitize($_POST['message'] ?? '');
        $courseId = intval($_POST['course_id'] ?? 0) ?: null;

        $validCats = ['course_delivery', 'resource_adequacy', 'infrastructure', 'general'];

        if (empty($category) || !in_array($category, $validCats)) {
            $error = 'Please select a valid category.';
        } elseif (empty($message)) {
            $error = 'Please enter your feedback message.';
        } else {
            dbInsert(
                "INSERT INTO feedback (user_id, session_id, category, subject, message, department_id, course_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                'iisssi' . ($courseId ? 'i' : 's'),
                [$userId, $sessionId ?: 0, $category, $subject, $message, $deptId, $courseId]
            );
            $success = 'Feedback submitted successfully!';
        }
    }
}

// ── Fetch my feedback history ─────────────────────────────
$myFeedback = dbFetchAll(
    "SELECT f.*, c.course_code
     FROM feedback f
     LEFT JOIN courses c ON f.course_id = c.id
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC",
    'i',
    [$userId]
);

$categoryLabels = [
    'course_delivery' => ['📚', 'Course Delivery'],
    'resource_adequacy' => ['📦', 'Resource Adequacy'],
    'infrastructure' => ['🏗️', 'Infrastructure'],
    'general' => ['💬', 'General'],
];
?>

<div class="page-content">
    <div class="page-header">
        <h2>Submit Feedback</h2>
        <p>Share your feedback on courses, resources, and facilities</p>
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

    <!-- Feedback Form -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>New Feedback</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" data-validate>
                <?= csrfField() ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="">Select category</option>
                            <?php foreach ($categoryLabels as $val => $meta): ?>
                                <option value="<?= $val ?>">
                                    <?= $meta[0] ?>
                                    <?= $meta[1] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Related Course (optional)</label>
                        <select name="course_id" class="form-control">
                            <option value="">None / General</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['course_code'] . ' – ' . $c['course_title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" class="form-control" placeholder="Brief summary of your feedback">
                </div>

                <div class="form-group">
                    <label>Feedback Message <span class="required">*</span></label>
                    <textarea name="message" class="form-control" rows="5" required
                        placeholder="Describe your feedback in detail..."></textarea>
                </div>

                <div style="text-align:right;">
                    <button type="submit" class="btn btn-primary">💬 Submit Feedback</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Feedback History -->
    <div class="card">
        <div class="card-header">
            <h3>My Feedback History</h3>
            <span class="text-muted text-sm">
                <?= count($myFeedback) ?> submitted
            </span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Subject</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myFeedback)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:32px;">
                                No feedback submitted yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($myFeedback as $f):
                            $catInfo = $categoryLabels[$f['category']] ?? ['💬', 'General'];
                            $statusClass = $f['status'] === 'resolved' ? 'badge-approved' : ($f['status'] === 'in_progress' ? 'badge-pending' : 'badge-draft');
                            $statusText = ucfirst(str_replace('_', ' ', $f['status']));
                            ?>
                            <tr>
                                <td>
                                    <?= $catInfo[0] ?>
                                    <?= $catInfo[1] ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($f['subject'] ?: '—') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($f['course_code'] ?? '—') ?>
                                </td>
                                <td><span class="badge <?= $statusClass ?>">
                                        <?= $statusText ?>
                                    </span></td>
                                <td>
                                    <?= timeAgo($f['created_at']) ?>
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