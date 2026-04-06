<?php
/**
 * Director – View & Respond to Recommendations
 * View recommendations from Deans and respond.
 */
$pageTitle = 'Recommendations';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_DIRECTOR);

$userId = getCurrentUserId();
$error = '';
$success = '';

// ── Handle response ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $recId = intval($_POST['rec_id'] ?? 0);
        $response = sanitize($_POST['director_response'] ?? '');

        if ($recId && !empty($response)) {
            dbExecute(
                "UPDATE recommendations SET director_response = ?, status = 'reviewed', reviewed_at = NOW() WHERE id = ?",
                'si',
                [$response, $recId]
            );
            // Notify Dean
            $rec = dbFetchOne("SELECT dean_id, title FROM recommendations WHERE id = ?", 'i', [$recId]);
            if ($rec) {
                createNotification(
                    $rec['dean_id'],
                    'Director Responded to Recommendation',
                    'Your recommendation "' . $rec['title'] . '" has been reviewed.',
                    'dean/recommendations.php'
                );
            }
            $success = 'Response submitted.';
        } else {
            $error = 'Please provide a response.';
        }
    }
}

// ── Fetch recommendations ────────────────────────────────
$filter = sanitize($_GET['filter'] ?? '');
$sql = "SELECT r.*, u.full_name as dean_name, f.name as faculty_name
        FROM recommendations r
        JOIN users u ON r.dean_id = u.id
        LEFT JOIN faculties f ON r.faculty_id = f.id
        WHERE 1=1";
$types = '';
$params = [];

if ($filter === 'pending') {
    $sql .= " AND r.status = 'pending'";
} elseif ($filter === 'reviewed') {
    $sql .= " AND r.status = 'reviewed'";
}

$sql .= " ORDER BY FIELD(r.status, 'pending') DESC, r.created_at DESC";
$recs = dbFetchAll($sql, $types, $params);

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
        <p>View and respond to quality improvement recommendations from Deans</p>
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

    <!-- Filters -->
    <div class="card mb-24">
        <div class="card-body" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <span style="font-weight:600; margin-right:8px;">Filter:</span>
            <a href="<?= BASE_URL ?>director/recommendations.php"
                class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-secondary' ?>">All</a>
            <a href="<?= BASE_URL ?>director/recommendations.php?filter=pending"
                class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">⏳ Pending</a>
            <a href="<?= BASE_URL ?>director/recommendations.php?filter=reviewed"
                class="btn btn-sm <?= $filter === 'reviewed' ? 'btn-primary' : 'btn-secondary' ?>">✅ Reviewed</a>
        </div>
    </div>

    <?php if (empty($recs)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding:48px;">
                <div style="font-size:3rem; margin-bottom:12px;">📨</div>
                <h3 style="margin-bottom:8px;">No Recommendations</h3>
                <p class="text-muted">No recommendations have been submitted yet.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($recs as $r):
            $ps = $priorityStyles[$r['priority']] ?? ['⚪', 'badge-draft'];
            $isPending = ($r['status'] === 'pending');
            ?>
            <div class="card mb-24">
                <div class="card-header">
                    <div>
                        <h3>
                            <?= htmlspecialchars($r['title']) ?>
                        </h3>
                        <small class="text-muted">
                            By
                            <?= htmlspecialchars($r['dean_name']) ?>
                            ·
                            <?= htmlspecialchars($r['faculty_name'] ?? '') ?>
                            ·
                            <?= timeAgo($r['created_at']) ?>
                        </small>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <span class="badge <?= $ps[1] ?>">
                            <?= $ps[0] ?>
                            <?= ucfirst($r['priority']) ?>
                        </span>
                        <span class="badge <?= $isPending ? 'badge-pending' : 'badge-approved' ?>">
                            <?= $isPending ? 'Pending' : 'Reviewed' ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <p>
                        <?= nl2br(htmlspecialchars($r['description'])) ?>
                    </p>

                    <?php if ($r['director_response']): ?>
                        <div
                            style="margin-top:12px; padding:12px; background:var(--gray-50); border-radius:8px; border-left:3px solid var(--primary);">
                            <strong>Your Response:</strong>
                            <p style="margin-top:4px;">
                                <?= nl2br(htmlspecialchars($r['director_response'])) ?>
                            </p>
                            <small class="text-muted">Responded:
                                <?= $r['reviewed_at'] ? formatDateTime($r['reviewed_at']) : '—' ?>
                            </small>
                        </div>
                    <?php endif; ?>

                    <?php if ($isPending): ?>
                        <form method="POST" action="" style="margin-top:12px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="rec_id" value="<?= $r['id'] ?>">
                            <div class="form-group">
                                <label>Your Response</label>
                                <textarea name="director_response" class="form-control" rows="3" required
                                    placeholder="Respond to this recommendation..."></textarea>
                            </div>
                            <div style="text-align:right;">
                                <button type="submit" class="btn btn-primary">📩 Submit Response</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>