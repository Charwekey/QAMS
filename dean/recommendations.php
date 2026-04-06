<?php
/**
 * Dean – Recommendations
 * Submit and view recommendations to the QA Director.
 */
$pageTitle = 'Recommendations';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_DEAN);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$dept = getUserDepartment($userId);
$facultyId = $dept['faculty_id'] ?? 0;

$error = '';
$success = '';

// ── Handle form submission ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'create') {
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $priority = sanitize($_POST['priority'] ?? 'medium');

            $validPriorities = ['low', 'medium', 'high', 'critical'];
            if (empty($title) || empty($description)) {
                $error = 'Title and description are required.';
            } elseif (!in_array($priority, $validPriorities)) {
                $error = 'Invalid priority level.';
            } else {
                dbInsert(
                    "INSERT INTO recommendations (dean_id, faculty_id, session_id, title, description, priority)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    'iiisss',
                    [$userId, $facultyId, $sessionId ?: 0, $title, $description, $priority]
                );
                // Notify Director
                $director = dbFetchOne(
                    "SELECT u.id FROM users u JOIN user_type_rel r ON u.id = r.user_id WHERE r.user_type_id = ?",
                    'i',
                    [ROLE_DIRECTOR]
                );
                if ($director) {
                    createNotification(
                        $director['id'],
                        'New Dean Recommendation',
                        $title,
                        'director/recommendations.php'
                    );
                }
                $success = 'Recommendation submitted to the QA Director!';
            }
        }
    }
}

// ── Fetch recommendations ────────────────────────────────
$recs = dbFetchAll(
    "SELECT * FROM recommendations WHERE dean_id = ? ORDER BY created_at DESC",
    'i',
    [$userId]
);

$priorityStyles = [
    'low' => ['🟢', 'badge-approved'],
    'medium' => ['🟡', 'badge-pending'],
    'high' => ['🟠', 'badge-draft'],
    'critical' => ['🔴', 'badge-rejected'],
];
?>

<div class="page-content">
    <div class="page-header">
        <h2>Recommendations</h2>
        <p>Submit quality improvement recommendations to the QA Director</p>
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

    <!-- New Recommendation -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>New Recommendation</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" data-validate>
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="create">

                <div class="form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" required
                        placeholder="e.g. Faculty-wide Teaching Assessment Framework">
                </div>

                <div class="form-group">
                    <label>Description <span class="required">*</span></label>
                    <textarea name="description" class="form-control" rows="5" required
                        placeholder="Describe the recommendation, rationale, and expected impact..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low">🟢 Low</option>
                            <option value="medium" selected>🟡 Medium</option>
                            <option value="high">🟠 High</option>
                            <option value="critical">🔴 Critical</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end;">
                        <button type="submit" class="btn btn-primary">📨 Submit Recommendation</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- My Recommendations -->
    <div class="card">
        <div class="card-header">
            <h3>My Recommendations</h3>
            <span class="text-muted text-sm">
                <?= count($recs) ?> submitted
            </span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Director Response</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recs)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:32px;">No recommendations yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recs as $r):
                            $ps = $priorityStyles[$r['priority']] ?? ['⚪', 'badge-draft'];
                            $statusClass = $r['status'] === 'reviewed' ? 'badge-approved' : 'badge-pending';
                            ?>
                            <tr>
                                <td><strong>
                                        <?= htmlspecialchars($r['title']) ?>
                                    </strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars(mb_strimwidth($r['description'], 0, 80, '...')) ?>
                                    </small>
                                </td>
                                <td><span class="badge <?= $ps[1] ?>">
                                        <?= $ps[0] ?>
                                        <?= ucfirst($r['priority']) ?>
                                    </span></td>
                                <td><span class="badge <?= $statusClass ?>">
                                        <?= ucfirst($r['status']) ?>
                                    </span></td>
                                <td>
                                    <?= $r['director_response'] ? htmlspecialchars(mb_strimwidth($r['director_response'], 0, 80, '...')) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td>
                                    <?= timeAgo($r['created_at']) ?>
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