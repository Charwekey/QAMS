<?php
/**
 * Director – Reports & Analytics
 * University-wide compliance reports and analytics.
 */
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_DIRECTOR);

$sessionId = getCurrentSession();

// ── University-wide stats ─────────────────────────────────
$totalSubs = dbFetchOne("SELECT COUNT(*) as cnt FROM submissions WHERE session_id = ?", 'i', [$sessionId ?: 0])['cnt'] ?? 0;
$approved = dbFetchOne("SELECT COUNT(*) as cnt FROM submissions WHERE session_id = ? AND status = ?", 'is', [$sessionId ?: 0, STATUS_APPROVED])['cnt'] ?? 0;
$pending = dbFetchOne("SELECT COUNT(*) as cnt FROM submissions WHERE session_id = ? AND status IN ('" . STATUS_PENDING_HOD . "','" . STATUS_PENDING_DEAN . "','" . STATUS_PENDING_DIRECTOR . "')", 'i', [$sessionId ?: 0])['cnt'] ?? 0;
$drafts = dbFetchOne("SELECT COUNT(*) as cnt FROM submissions WHERE session_id = ? AND status = ?", 'is', [$sessionId ?: 0, STATUS_DRAFT])['cnt'] ?? 0;
$reverted = dbFetchOne("SELECT COUNT(*) as cnt FROM submissions WHERE session_id = ? AND status IN ('" . STATUS_REVERTED_LECTURER . "','" . STATUS_REVERTED_HOD . "','" . STATUS_REVERTED_DEAN . "')", 'i', [$sessionId ?: 0])['cnt'] ?? 0;
$complianceRate = $totalSubs > 0 ? round(($approved / $totalSubs) * 100, 1) : 0;

// ── Per-faculty stats ─────────────────────────────────────
$facultyStats = dbFetchAll(
    "SELECT f.name as faculty_name, f.code,
            COUNT(s.id) as total,
            SUM(CASE WHEN s.status = ? THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN s.status IN (?,?,?) THEN 1 ELSE 0 END) as pending
     FROM faculties f
     LEFT JOIN departments d ON d.faculty_id = f.id
     LEFT JOIN courses c ON c.department_id = d.id
     LEFT JOIN lecturer_courses lc ON lc.course_id = c.id
     LEFT JOIN submissions s ON s.lecturer_course_id = lc.id AND s.session_id = ?
     GROUP BY f.id, f.name, f.code
     ORDER BY f.name",
    'ssssi',
    [STATUS_APPROVED, STATUS_PENDING_HOD, STATUS_PENDING_DEAN, STATUS_PENDING_DIRECTOR, $sessionId ?: 0]
);

// ── Per-department stats ──────────────────────────────────
$deptStats = dbFetchAll(
    "SELECT d.name as dept_name, f.name as faculty_name,
            COUNT(s.id) as total,
            SUM(CASE WHEN s.status = ? THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN s.status IN (?,?,?) THEN 1 ELSE 0 END) as pending
     FROM departments d
     JOIN faculties f ON f.id = d.faculty_id
     LEFT JOIN courses c ON c.department_id = d.id
     LEFT JOIN lecturer_courses lc ON lc.course_id = c.id
     LEFT JOIN submissions s ON s.lecturer_course_id = lc.id AND s.session_id = ?
     GROUP BY d.id, d.name, f.name
     ORDER BY f.name, d.name",
    'ssssi',
    [STATUS_APPROVED, STATUS_PENDING_HOD, STATUS_PENDING_DEAN, STATUS_PENDING_DIRECTOR, $sessionId ?: 0]
);

// ── Facility issues summary ──────────────────────────────
$openIssues = dbFetchOne("SELECT COUNT(*) as cnt FROM facility_issues WHERE status = 'open'", '', [])['cnt'] ?? 0;
$resolvedIssues = dbFetchOne("SELECT COUNT(*) as cnt FROM facility_issues WHERE status = 'resolved'", '', [])['cnt'] ?? 0;
?>

<div class="page-content">
    <div class="page-header">
        <h2>Reports & Analytics</h2>
        <p>University-wide quality assurance overview</p>
    </div>

    <!-- University Stats -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));">
        <div class="stat-card">
            <div class="stat-icon primary">📋</div>
            <div class="stat-info">
                <h4>
                    <?= $totalSubs ?>
                </h4>
                <p>Total Submissions</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">✅</div>
            <div class="stat-info">
                <h4>
                    <?= $approved ?>
                </h4>
                <p>Approved</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">⏳</div>
            <div class="stat-info">
                <h4>
                    <?= $pending ?>
                </h4>
                <p>In Review</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info">📝</div>
            <div class="stat-info">
                <h4>
                    <?= $drafts ?>
                </h4>
                <p>Drafts</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger">🔄</div>
            <div class="stat-info">
                <h4>
                    <?= $reverted ?>
                </h4>
                <p>Reverted</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon accent">📊</div>
            <div class="stat-info">
                <h4>
                    <?= $complianceRate ?>%
                </h4>
                <p>Compliance Rate</p>
            </div>
        </div>
    </div>

    <!-- Faculty Breakdown -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>Faculty Compliance</h3>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Faculty</th>
                        <th>Total</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Compliance</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($facultyStats as $fs):
                        $facTotal = intval($fs['total']);
                        $facApproved = intval($fs['approved']);
                        $facRate = $facTotal > 0 ? round(($facApproved / $facTotal) * 100) : 0;
                        ?>
                        <tr>
                            <td><strong>
                                    <?= htmlspecialchars($fs['faculty_name']) ?>
                                </strong>
                                <br><small class="text-muted">
                                    <?= htmlspecialchars($fs['code']) ?>
                                </small>
                            </td>
                            <td>
                                <?= $facTotal ?>
                            </td>
                            <td>
                                <?= $facApproved ?>
                            </td>
                            <td>
                                <?= intval($fs['pending']) ?>
                            </td>
                            <td><strong>
                                    <?= $facRate ?>%
                                </strong></td>
                            <td style="min-width:120px;">
                                <div style="background:var(--gray-100);border-radius:4px;height:8px;overflow:hidden;">
                                    <div
                                        style="background:var(--primary);height:100%;width:<?= $facRate ?>%;border-radius:4px;transition:width 0.3s;">
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Department Breakdown -->
    <div class="card mb-24">
        <div class="card-header">
            <h3>Department Details</h3>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Faculty</th>
                        <th>Total</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Compliance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deptStats as $ds):
                        $dTotal = intval($ds['total']);
                        $dApproved = intval($ds['approved']);
                        $dRate = $dTotal > 0 ? round(($dApproved / $dTotal) * 100) : 0;
                        ?>
                        <tr>
                            <td><strong>
                                    <?= htmlspecialchars($ds['dept_name']) ?>
                                </strong></td>
                            <td class="text-muted">
                                <?= htmlspecialchars($ds['faculty_name']) ?>
                            </td>
                            <td>
                                <?= $dTotal ?>
                            </td>
                            <td>
                                <?= $dApproved ?>
                            </td>
                            <td>
                                <?= intval($ds['pending']) ?>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div
                                        style="background:var(--gray-100);border-radius:4px;height:8px;width:60px;overflow:hidden;">
                                        <div
                                            style="background:var(--primary);height:100%;width:<?= $dRate ?>%;border-radius:4px;">
                                        </div>
                                    </div>
                                    <strong>
                                        <?= $dRate ?>%
                                    </strong>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Facility Issues Summary -->
    <div class="card">
        <div class="card-header">
            <h3>Facility Issues Overview</h3>
        </div>
        <div class="card-body">
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                <div class="stat-card">
                    <div class="stat-icon warning">🔧</div>
                    <div class="stat-info">
                        <h4>
                            <?= $openIssues ?>
                        </h4>
                        <p>Open Issues</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">✅</div>
                    <div class="stat-info">
                        <h4>
                            <?= $resolvedIssues ?>
                        </h4>
                        <p>Resolved</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>