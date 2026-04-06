<?php
/**
 * Director (QAMS Head) Dashboard
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
requireRole(ROLE_DIRECTOR);

$userId = getCurrentUserId();
$sessionId = getCurrentSession();
$user = getLoggedInUser();
$selectedFacultyId = getSelectedFaculty();
$selectedDepartmentId = getSelectedDepartment();
$selectedFaculty = $selectedFacultyId ? dbFetchOne("SELECT * FROM faculties WHERE id = ?", 'i', [$selectedFacultyId]) : null;
$selectedDepartment = $selectedDepartmentId ? dbFetchOne("SELECT * FROM departments WHERE id = ?", 'i', [$selectedDepartmentId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_scope'])) {
    if (!verifyCsrf()) {
        setFlash('error', 'Invalid security token.');
    } else {
        clearSelectedScope();
        setFlash('success', 'Review scope reset successfully.');
    }
    redirect('director/dashboard.php');
}

$filterClause = "WHERE s.session_id = ?";
$filterTypes = 'i';
$filterParams = [$sessionId ?: 0];

$issueFilterClause = "WHERE session_id = ?";
$issueFilterTypes = 'i';
$issueFilterParams = [$sessionId ?: 0];

if ($selectedFacultyId) {
    $filterClause .= " AND d.faculty_id = ?";
    $filterTypes .= 'i';
    $filterParams[] = $selectedFacultyId;
    $issueFilterClause .= " AND department_id IN (SELECT id FROM departments WHERE faculty_id = ?)";
    $issueFilterTypes .= 'i';
    $issueFilterParams[] = $selectedFacultyId;
}

if ($selectedDepartmentId) {
    $filterClause .= " AND d.id = ?";
    $filterTypes .= 'i';
    $filterParams[] = $selectedDepartmentId;
    $issueFilterClause .= " AND department_id = ?";
    $issueFilterTypes .= 'i';
    $issueFilterParams[] = $selectedDepartmentId;
}

// Filtered stats by selected scope
$totalSubmissions = dbFetchOne(
    "SELECT COUNT(*) as cnt FROM submissions s
     JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
     JOIN courses c ON lc.course_id = c.id
     JOIN departments d ON c.department_id = d.id
     $filterClause",
    $filterTypes,
    $filterParams
)['cnt'] ?? 0;

$pendingDirector = dbFetchOne(
    "SELECT COUNT(*) as cnt FROM submissions s
     JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
     JOIN courses c ON lc.course_id = c.id
     JOIN departments d ON c.department_id = d.id
     $filterClause AND s.status = ?",
    $filterTypes . 's',
    array_merge($filterParams, [STATUS_PENDING_DIRECTOR])
)['cnt'] ?? 0;

$approvedCount = dbFetchOne(
    "SELECT COUNT(*) as cnt FROM submissions s
     JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
     JOIN courses c ON lc.course_id = c.id
     JOIN departments d ON c.department_id = d.id
     $filterClause AND s.status = ?",
    $filterTypes . 's',
    array_merge($filterParams, [STATUS_APPROVED])
)['cnt'] ?? 0;

$openIssues = dbFetchOne(
    "SELECT COUNT(*) as cnt FROM facility_issues $issueFilterClause AND status = 'open'",
    $issueFilterTypes,
    $issueFilterParams
)['cnt'] ?? 0;

$faculties = getFaculties();

// Recent submissions awaiting director review
$recentSubs = dbFetchAll(
    "SELECT s.*, u.full_name as lecturer_name, c.course_code, d.name as dept_name, f.name as faculty_name
     FROM submissions s
     JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
     JOIN courses c ON lc.course_id = c.id
     JOIN departments d ON c.department_id = d.id
     JOIN faculties f ON d.faculty_id = f.id
     JOIN users u ON s.lecturer_id = u.id
     $filterClause
     ORDER BY s.updated_at DESC
     LIMIT 10",
    $filterTypes,
    $filterParams
);

$complianceRate = $totalSubmissions > 0 ? round(($approvedCount / $totalSubmissions) * 100) : 0;
?>

<div class="page-content">
    <div class="page-header">
        <h2>Welcome,
            <?= htmlspecialchars($user['full_name']) ?>
        </h2>
        <p>Director of Quality Assurance · University Overview</p>
    </div>

    <?php if ($selectedFaculty || $selectedDepartment): ?>
        <div class="alert alert-info" style="margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; gap:16px;">
            <div>
                <strong>Current scope:</strong>
                <?= $selectedFaculty ? htmlspecialchars($selectedFaculty['name']) : 'All Faculties' ?>
                <?= $selectedDepartment ? ' / ' . htmlspecialchars($selectedDepartment['name']) : '' ?>
            </div>
            <form method="POST" action="" style="margin:0;">
                <?= csrfField() ?>
                <input type="hidden" name="reset_scope" value="1">
                <button type="submit" class="btn btn-sm btn-secondary">Change scope</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">📋</div>
            <div class="stat-info">
                <h4>
                    <?= $totalSubmissions ?>
                </h4>
                <p>Total Submissions</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">⏳</div>
            <div class="stat-info">
                <h4>
                    <?= $pendingDirector ?>
                </h4>
                <p>Awaiting Final Approval</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">✅</div>
            <div class="stat-info">
                <h4>
                    <?= $approvedCount ?>
                </h4>
                <p>Approved & Archived</p>
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

    <div class="form-row" style="gap:20px;">
        <!-- Faculty Overview -->
        <div class="card" style="flex:1;">
            <div class="card-header">
                <h3>Faculties</h3>
            </div>
            <div class="card-body">
                <?php foreach ($faculties as $f): ?>
                    <?php
                    $fSubs = dbFetchOne(
                        "SELECT COUNT(*) as cnt FROM submissions s
                         JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
                         JOIN courses c ON lc.course_id = c.id
                         JOIN departments d ON c.department_id = d.id
                         WHERE d.faculty_id = ? AND s.session_id = ?",
                        'ii',
                        [$f['id'], $sessionId ?: 0]
                    )['cnt'] ?? 0;
                    ?>
                    <div
                        style="padding:12px 0;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <strong>
                                <?= htmlspecialchars($f['name']) ?>
                            </strong>
                            <p class="text-xs text-muted">
                                <?= htmlspecialchars($f['code']) ?>
                            </p>
                        </div>
                        <span class="badge badge-info">
                            <?= $fSubs ?> submissions
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="flex:0 0 280px;">
            <div class="card-header">
                <h3>Quick Actions</h3>
            </div>
            <div class="card-body">
                <a href="<?= BASE_URL ?>director/approve.php" class="btn btn-primary btn-block mb-8">✅ Review
                    Submissions</a>
                <a href="<?= BASE_URL ?>director/templates.php" class="btn btn-success btn-block mb-8">📁 Manage
                    Templates</a>
                <a href="<?= BASE_URL ?>director/reports.php" class="btn btn-outline btn-block">📈 Generate Reports</a>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card mt-24">
        <div class="card-header">
            <h3>Recent Submissions</h3>
            <a href="<?= BASE_URL ?>director/approve.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Faculty</th>
                        <th>Department</th>
                        <th>Lecturer</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentSubs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding:32px;">No submissions yet.</td>
                        </tr>
                    <?php else:
                        foreach ($recentSubs as $s): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($s['faculty_name']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($s['dept_name']) ?>
                                </td>
                                <td><strong>
                                        <?= htmlspecialchars($s['lecturer_name']) ?>
                                    </strong></td>
                                <td>
                                    <?= htmlspecialchars($s['course_code']) ?>
                                </td>
                                <td>
                                    <?= statusLabel($s['status']) ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>director/approve.php?id=<?= $s['id'] ?>"
                                        class="btn btn-sm btn-outline">Review</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>