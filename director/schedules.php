<?php
/**
 * Director – Manage QA Schedules
 * Create and manage QA survey schedules visible to class reps.
 */
$pageTitle = 'QA Schedules';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_DIRECTOR);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();

$error = '';
$success = '';

// ── Handle actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'create') {
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $schedDate = sanitize($_POST['schedule_date'] ?? '');
            $schedTime = sanitize($_POST['schedule_time'] ?? '');
            $venue = sanitize($_POST['venue'] ?? '');

            if (empty($title) || empty($schedDate)) {
                $error = 'Title and date are required.';
            } else {
                dbInsert(
                    "INSERT INTO qa_schedules (session_id, title, description, schedule_date, schedule_time, venue, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    'isssssi',
                    [$sessionId ?: 0, $title, $description, $schedDate, $schedTime ?: null, $venue, $userId]
                );
                $success = 'Schedule created!';
            }
        } elseif ($action === 'delete') {
            $schedId = intval($_POST['schedule_id'] ?? 0);
            if ($schedId) {
                dbExecute("DELETE FROM qa_schedules WHERE id = ?", 'i', [$schedId]);
                $success = 'Schedule deleted.';
            }
        }
    }
}

// ── Fetch schedules ──────────────────────────────────────
$schedules = dbFetchAll(
    "SELECT qs.*, u.full_name as created_by_name FROM qa_schedules qs
     JOIN users u ON qs.created_by = u.id
     WHERE qs.session_id = ?
     ORDER BY qs.schedule_date ASC",
    'i',
    [$sessionId ?: 0]
);
?>

<div class="page-content">
    <div class="page-header">
        <h2>QA Schedules</h2>
        <p>Manage quality assurance survey schedules and feedback sessions</p>
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

    <!-- Create Form -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>Create New Schedule</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" data-validate>
                <?= csrfField() ?>
                <input type="hidden" name="form_action" value="create">

                <div class="form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" required
                        placeholder="e.g. Mid-Semester QA Survey">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date <span class="required">*</span></label>
                        <input type="date" name="schedule_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" name="schedule_time" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Venue</label>
                        <input type="text" name="venue" class="form-control" placeholder="e.g. Main Auditorium">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"
                        placeholder="Details about the schedule..."></textarea>
                </div>

                <div style="text-align:right;">
                    <button type="submit" class="btn btn-primary">📅 Create Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Schedules -->
    <div class="card">
        <div class="card-header">
            <h3>All Schedules</h3>
            <span class="text-muted text-sm">
                <?= count($schedules) ?> total
            </span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Title</th>
                        <th>Venue</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schedules)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding:32px;">No schedules created yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($schedules as $s):
                            $isUpcoming = strtotime($s['schedule_date']) >= strtotime('today');
                            $isToday = date('Y-m-d', strtotime($s['schedule_date'])) === date('Y-m-d');
                            ?>
                            <tr style="<?= $isToday ? 'background:rgba(79,70,229,0.05);' : '' ?>">
                                <td>
                                    <strong>
                                        <?= formatDate($s['schedule_date']) ?>
                                    </strong>
                                    <?php if ($isToday): ?>
                                        <span class="badge badge-approved" style="font-size:0.65rem;">TODAY</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $s['schedule_time'] ? date('h:i A', strtotime($s['schedule_time'])) : '—' ?>
                                </td>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($s['title']) ?>
                                    </strong>
                                    <?php if ($s['description']): ?>
                                        <br><small class="text-muted">
                                            <?= htmlspecialchars(mb_strimwidth($s['description'], 0, 60, '...')) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($s['venue'] ?: '—') ?>
                                </td>
                                <td>
                                    <span class="badge <?= $isUpcoming ? 'badge-approved' : 'badge-draft' ?>">
                                        <?= $isUpcoming ? 'Upcoming' : 'Past' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="form_action" value="delete">
                                        <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                            data-confirm="Delete this schedule?">🗑️</button>
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