<?php
/**
 * HOD – Action Plans
 * Submit and manage quality improvement proposals for the department.
 */
$pageTitle = 'Action Plans';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_HOD);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$dept = getUserDepartment($userId);
$deptId = $dept['id'] ?? 0;

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
            $targetDate = sanitize($_POST['target_date'] ?? '');

            if (empty($title) || empty($description)) {
                $error = 'Title and description are required.';
            } else {
                dbInsert(
                    "INSERT INTO action_plans (hod_id, department_id, session_id, title, description, target_date)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    'iiisss',
                    [$userId, $deptId, $sessionId ?: 0, $title, $description, $targetDate ?: null]
                );
                $success = 'Action plan created successfully!';
            }
        } elseif ($action === 'update_status') {
            $planId = intval($_POST['plan_id'] ?? 0);
            $newStatus = sanitize($_POST['status'] ?? '');
            $validStatuses = ['proposed', 'in_progress', 'completed'];

            if ($planId && in_array($newStatus, $validStatuses)) {
                dbExecute(
                    "UPDATE action_plans SET status = ? WHERE id = ? AND hod_id = ?",
                    'sii',
                    [$newStatus, $planId, $userId]
                );
                $success = 'Status updated.';
            }
        } elseif ($action === 'delete') {
            $planId = intval($_POST['plan_id'] ?? 0);
            if ($planId) {
                dbExecute(
                    "DELETE FROM action_plans WHERE id = ? AND hod_id = ?",
                    'ii',
                    [$planId, $userId]
                );
                $success = 'Action plan deleted.';
            }
        }
    }
}

// ── Fetch action plans ───────────────────────────────────
$plans = dbFetchAll(
    "SELECT * FROM action_plans WHERE hod_id = ? AND department_id = ? ORDER BY created_at DESC",
    'ii',
    [$userId, $deptId]
);

$statusLabelsMap = [
    'proposed' => ['Proposed', 'badge-pending'],
    'in_progress' => ['In Progress', 'badge-draft'],
    'completed' => ['Completed', 'badge-approved'],
];
?>

<div class="page-content">
    <div class="page-header">
        <h2>Action Plans</h2>
        <p>Quality improvement proposals for
            <?= htmlspecialchars($dept['name'] ?? '') ?>
        </p>
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

    <!-- New Action Plan Form -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>Create New Action Plan</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" data-validate>
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="create">

                <div class="form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" required
                        placeholder="e.g. Improve Lab Equipment Availability">
                </div>

                <div class="form-group">
                    <label>Description <span class="required">*</span></label>
                    <textarea name="description" class="form-control" rows="4" required
                        placeholder="Describe the quality improvement objective, steps, and expected outcomes..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Target Completion Date</label>
                        <input type="date" name="target_date" class="form-control">
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end;">
                        <button type="submit" class="btn btn-primary">📋 Create Action Plan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Plans -->
    <div class="card">
        <div class="card-header">
            <h3>My Action Plans</h3>
            <span class="text-muted text-sm">
                <?= count($plans) ?> plan
                <?= count($plans) !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Target Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:32px;">
                                No action plans yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($plans as $p):
                            $si = $statusLabelsMap[$p['status']] ?? ['Unknown', 'badge-draft'];
                            ?>
                            <tr>
                                <td><strong>
                                        <?= htmlspecialchars($p['title']) ?>
                                    </strong></td>
                                <td style="max-width:300px;">
                                    <?= htmlspecialchars(mb_strimwidth($p['description'], 0, 100, '...')) ?>
                                </td>
                                <td>
                                    <?= $p['target_date'] ? formatDate($p['target_date']) : '—' ?>
                                </td>
                                <td>
                                    <form method="POST" action="" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="form_action" value="update_status">
                                        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                        <select name="status" class="form-control"
                                            style="width:auto; padding:4px 8px; font-size:0.8rem;"
                                            onchange="this.form.submit()">
                                            <option value="proposed" <?= $p['status'] === 'proposed' ? 'selected' : '' ?>>Proposed
                                            </option>
                                            <option value="in_progress" <?= $p['status'] === 'in_progress' ? 'selected' : '' ?>>In
                                                Progress</option>
                                            <option value="completed" <?= $p['status'] === 'completed' ? 'selected' : '' ?>
                                                >Completed</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                            data-confirm="Delete this action plan?">🗑️</button>
                                    </form>
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