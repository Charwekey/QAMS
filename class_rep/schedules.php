<?php
/**
 * Class Rep – QA Schedules
 * View upcoming QA survey schedules and feedback sessions.
 */
$pageTitle = 'QA Schedules';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_CLASS_REP);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();

// ── Fetch schedules ───────────────────────────────────────
$filter = sanitize($_GET['filter'] ?? '');

$sql = "SELECT qs.*, u.full_name as created_by_name FROM qa_schedules qs JOIN users u ON qs.created_by = u.id WHERE qs.session_id = ?";
$types = 'i';
$params = [$sessionId ?: 0];

if ($filter === 'upcoming') {
    $sql .= " AND qs.schedule_date >= CURDATE()";
} elseif ($filter === 'past') {
    $sql .= " AND qs.schedule_date < CURDATE()";
}

$sql .= " ORDER BY qs.schedule_date ASC";
$schedules = dbFetchAll($sql, $types, $params);

$upcomingCount = 0;
$pastCount = 0;
foreach ($schedules as $s) {
    if (strtotime($s['schedule_date']) >= strtotime('today')) {
        $upcomingCount++;
    } else {
        $pastCount++;
    }
}
?>

<div class="page-content">
    <div class="page-header">
        <h2>QA Schedules</h2>
        <p>Quality assurance survey schedules and feedback sessions</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid mb-24" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
        <div class="stat-card">
            <div class="stat-icon primary">📅</div>
            <div class="stat-info">
                <h4>
                    <?= count($schedules) ?>
                </h4>
                <p>Total Schedules</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">🟢</div>
            <div class="stat-info">
                <h4>
                    <?= $upcomingCount ?>
                </h4>
                <p>Upcoming</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info">⏰</div>
            <div class="stat-info">
                <h4>
                    <?= $pastCount ?>
                </h4>
                <p>Past</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-24">
        <div class="card-body" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <span style="font-weight:600; margin-right:8px;">Filter:</span>
            <a href="<?= BASE_URL ?>class_rep/schedules.php"
                class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <a href="<?= BASE_URL ?>class_rep/schedules.php?filter=upcoming"
                class="btn btn-sm <?= $filter === 'upcoming' ? 'btn-primary' : 'btn-secondary' ?>">🟢 Upcoming</a>
            <a href="<?= BASE_URL ?>class_rep/schedules.php?filter=past"
                class="btn btn-sm <?= $filter === 'past' ? 'btn-primary' : 'btn-secondary' ?>">⏰ Past</a>
        </div>
    </div>

    <!-- Schedules List -->
    <?php if (empty($schedules)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding:48px;">
                <div style="font-size:3rem; margin-bottom:12px;">📅</div>
                <h3 style="margin-bottom:8px;">No Schedules Found</h3>
                <p class="text-muted">
                    <?= $filter ? 'No ' . $filter . ' schedules.' : 'No QA schedules have been created for this session yet.' ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Title</th>
                            <th>Venue</th>
                            <th>Created By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
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
                                        <span class="badge badge-approved" style="font-size:0.65rem; margin-left:4px;">TODAY</span>
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
                                        <p class="text-muted text-sm" style="margin-top:2px;">
                                            <?= htmlspecialchars(mb_strimwidth($s['description'], 0, 80, '...')) ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($s['venue'] ?: '—') ?>
                                </td>
                                <td class="text-muted">
                                    <?= htmlspecialchars($s['created_by_name']) ?>
                                </td>
                                <td>
                                    <?php if ($isUpcoming): ?>
                                        <span class="badge badge-approved">Upcoming</span>
                                    <?php else: ?>
                                        <span class="badge badge-draft">Past</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>