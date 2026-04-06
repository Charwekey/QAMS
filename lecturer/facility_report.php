<?php
/**
 * Lecturer – Facility Issues Report
 * Report infrastructure problems (markers, boards, projectors, etc.)
 */
$pageTitle = 'Facility Issues';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_LECTURER);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$dept = getUserDepartment($userId);

$error = '';
$success = '';

// ── Handle form submission ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $issueType = sanitize($_POST['issue_type'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $roomNumber = sanitize($_POST['room_number'] ?? '');

        $validTypes = ['markers', 'boards', 'projectors', 'fans', 'sockets', 'switches', 'fan_regulators', 'seats', 'room_suitability', 'other'];

        if (empty($issueType) || !in_array($issueType, $validTypes)) {
            $error = 'Please select a valid issue type.';
        } elseif (empty($description)) {
            $error = 'Please describe the issue.';
        } else {
            dbInsert(
                "INSERT INTO facility_issues (reporter_id, department_id, session_id, issue_type, description, room_number)
                 VALUES (?, ?, ?, ?, ?, ?)",
                'iiisss',
                [$userId, $dept['id'] ?? null, $sessionId ?: 0, $issueType, $description, $roomNumber]
            );
            $success = 'Facility issue reported successfully!';
        }
    }
}

// ── Fetch existing reports by this lecturer ───────────────
$myReports = dbFetchAll(
    "SELECT * FROM facility_issues WHERE reporter_id = ? ORDER BY created_at DESC",
    'i',
    [$userId]
);

// Issue type display mapping
$issueTypeLabels = [
    'markers' => ['🖊️', 'Markers'],
    'boards' => ['📋', 'Boards'],
    'projectors' => ['📽️', 'Projectors'],
    'fans' => ['🌀', 'Fans'],
    'sockets' => ['🔌', 'Sockets'],
    'switches' => ['💡', 'Switches'],
    'fan_regulators' => ['🎛️', 'Fan Regulators'],
    'seats' => ['💺', 'Seats'],
    'room_suitability' => ['🏛️', 'Room Suitability'],
    'other' => ['❓', 'Other'],
];
?>

<div class="page-content">
    <div class="page-header">
        <h2>Report Facility Issues</h2>
        <p>Report infrastructure or equipment problems in lecture rooms</p>
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

    <!-- ── Report Form ──────────────────────────────── -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>New Issue Report</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" data-validate>
                <?= csrfField() ?>

                <div class="form-row">
                    <div class="form-group">
                        <label>Issue Type <span class="required">*</span></label>
                        <select name="issue_type" class="form-control" required>
                            <option value="">Select issue type</option>
                            <?php foreach ($issueTypeLabels as $val => $meta): ?>
                                <option value="<?= $val ?>">
                                    <?= $meta[0] ?>
                                    <?= $meta[1] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room / Location</label>
                        <input type="text" name="room_number" class="form-control" placeholder="e.g. Room 201, Block A">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description <span class="required">*</span></label>
                    <textarea name="description" class="form-control" rows="4" required
                        placeholder="Describe the issue in detail..."></textarea>
                </div>

                <div style="display:flex; gap:12px; justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">🔧 Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── My Reports History ───────────────────────── -->
    <div class="card">
        <div class="card-header">
            <h3>My Reports</h3>
            <span class="text-muted text-sm">
                <?= count($myReports) ?> report
                <?= count($myReports) !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Room</th>
                        <th>Status</th>
                        <th>Reported</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myReports)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:32px;">
                                No facility reports yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($myReports as $r):
                            $typeInfo = $issueTypeLabels[$r['issue_type']] ?? ['❓', 'Unknown'];
                            $statusClass = $r['status'] === 'resolved' ? 'badge-approved' : 'badge-pending';
                            $statusText = $r['status'] === 'resolved' ? 'Resolved' : 'Open';
                            ?>
                            <tr>
                                <td>
                                    <span title="<?= $typeInfo[1] ?>">
                                        <?= $typeInfo[0] ?>
                                    </span>
                                    <?= $typeInfo[1] ?>
                                </td>
                                <td style="max-width:300px;">
                                    <?= htmlspecialchars(mb_strimwidth($r['description'], 0, 100, '...')) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($r['room_number'] ?: '—') ?>
                                </td>
                                <td><span class="badge <?= $statusClass ?>">
                                        <?= $statusText ?>
                                    </span></td>
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