<?php
/**
 * Profile Page
 * View and update profile information for all roles.
 */
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$user = getLoggedInUser();
$dept = getUserDepartment($userId);

$error = '';
$success = '';

// ── Handle profile update ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } else {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $designation = sanitize($_POST['designation'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');

        if (empty($fullName)) {
            $error = 'Full name is required.';
        } else {
            dbExecute(
                "UPDATE users SET full_name = ?, designation = ?, phone = ? WHERE id = ?",
                'sssi',
                [$fullName, $designation, $phone, $userId]
            );
            // Refresh session
            $_SESSION['user_name'] = $fullName;
            $success = 'Profile updated successfully!';
            // Re-fetch user data
            $user = getLoggedInUser();
        }
    }
}

// ── Stats ────────────────────────────────────────────────
$stats = [];
if ($userRole == ROLE_LECTURER) {
    $stats['Submissions'] = dbFetchOne("SELECT COUNT(*) as cnt FROM submissions WHERE lecturer_id = ?", 'i', [$userId])['cnt'] ?? 0;
    $stats['Courses'] = dbFetchOne("SELECT COUNT(*) as cnt FROM lecturer_courses WHERE lecturer_id = ?", 'i', [$userId])['cnt'] ?? 0;
} elseif ($userRole == ROLE_HOD) {
    $deptId = $user['department_id'] ?? 0;
    $stats['Action Plans'] = dbFetchOne("SELECT COUNT(*) as cnt FROM action_plans WHERE hod_id = ?", 'i', [$userId])['cnt'] ?? 0;
    $stats['Dept Submissions'] = dbFetchOne(
        "SELECT COUNT(*) as cnt FROM submissions s
         JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
         JOIN courses c ON lc.course_id = c.id
         WHERE c.department_id = ?",
        'i',
        [$deptId]
    )['cnt'] ?? 0;
} elseif ($userRole == ROLE_CLASS_REP) {
    $stats['Feedback'] = dbFetchOne("SELECT COUNT(*) as cnt FROM feedback WHERE submitted_by = ?", 'i', [$userId])['cnt'] ?? 0;
    $stats['Uploads'] = dbFetchOne("SELECT COUNT(*) as cnt FROM attendance_uploads WHERE uploaded_by = ?", 'i', [$userId])['cnt'] ?? 0;
} elseif ($userRole == ROLE_DEAN) {
    $facultyId = $dept['faculty_id'] ?? 0;
    $stats['Faculty Submissions'] = dbFetchOne(
        "SELECT COUNT(*) as cnt FROM submissions s
         JOIN lecturer_courses lc ON s.lecturer_course_id = lc.id
         JOIN courses c ON lc.course_id = c.id
         JOIN departments d ON c.department_id = d.id
         WHERE d.faculty_id = ?",
        'i',
        [$facultyId]
    )['cnt'] ?? 0;
    $stats['Recommendations'] = dbFetchOne("SELECT COUNT(*) as cnt FROM recommendations WHERE dean_id = ?", 'i', [$userId])['cnt'] ?? 0;
} elseif ($userRole == ROLE_DIRECTOR) {
    $stats['Total Submissions'] = dbFetchOne("SELECT COUNT(*) as cnt FROM submissions", '', [])['cnt'] ?? 0;
    $stats['Templates'] = dbFetchOne("SELECT COUNT(*) as cnt FROM templates WHERE uploaded_by = ?", 'i', [$userId])['cnt'] ?? 0;
}

$notifCount = getUnreadNotificationCount($userId);

// Current session info
$currentSession = getCurrentAcademicSession();
$sessionLabel = $currentSession ? $currentSession['label'] : 'No active session';
?>

<div class="page-content">
    <div class="page-header">
        <h2>My Profile</h2>
        <p>
            <?= ROLE_NAMES[$userRole] ?? 'User' ?>
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

    <div class="form-row" style="gap:20px; align-items:flex-start;">
        <!-- Profile Card -->
        <div class="card" style="flex:0 0 300px;">
            <div class="card-body text-center" style="padding:32px;">
                <div class="user-avatar" style="width:80px; height:80px; font-size:28px; margin:0 auto 16px;">
                    <?php
                    $parts = explode(' ', $user['full_name'] ?? '');
                    echo strtoupper(($parts[0][0] ?? '') . ($parts[1][0] ?? ''));
                    ?>
                </div>
                <h3>
                    <?= htmlspecialchars($user['full_name'] ?? '') ?>
                </h3>
                <p class="text-muted">
                    <?= htmlspecialchars($user['email'] ?? '') ?>
                </p>
                <span class="badge badge-approved" style="margin-top:8px;">
                    <?= ROLE_NAMES[$userRole] ?? 'User' ?>
                </span>

                <div style="margin-top:24px; text-align:left;">
                    <div
                        style="padding:8px 0; border-bottom:1px solid var(--border); display:flex; justify-content:space-between;">
                        <span class="text-muted">Employee ID</span>
                        <strong>
                            <?= htmlspecialchars($user['employee_id'] ?? 'N/A') ?>
                        </strong>
                    </div>
                    <div
                        style="padding:8px 0; border-bottom:1px solid var(--border); display:flex; justify-content:space-between;">
                        <span class="text-muted">Department</span>
                        <strong>
                            <?= htmlspecialchars($dept['dept_name'] ?? 'N/A') ?>
                        </strong>
                    </div>
                    <div
                        style="padding:8px 0; border-bottom:1px solid var(--border); display:flex; justify-content:space-between;">
                        <span class="text-muted">Faculty</span>
                        <strong>
                            <?= htmlspecialchars($dept['faculty_name'] ?? 'N/A') ?>
                        </strong>
                    </div>
                    <div style="padding:8px 0; display:flex; justify-content:space-between;">
                        <span class="text-muted">Session</span>
                        <strong>
                            <?= htmlspecialchars($sessionLabel) ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form + Stats -->
        <div style="flex:1;">
            <!-- Activity Stats -->
            <?php if (!empty($stats)): ?>
                <div class="stats-grid mb-24" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
                    <?php foreach ($stats as $label => $value): ?>
                        <div class="stat-card">
                            <div class="stat-info">
                                <h4>
                                    <?= $value ?>
                                </h4>
                                <p>
                                    <?= $label ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>
                                <?= $notifCount ?>
                            </h4>
                            <p>Unread Notifications</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Edit Profile -->
            <div class="card">
                <div class="card-header">
                    <h3>Edit Profile</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" data-validate>
                        <?= csrfField() ?>

                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" class="form-control" required
                                value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control"
                                value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                            <small class="text-muted">Email cannot be changed.</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Designation</label>
                                <input type="text" name="designation" class="form-control"
                                    value="<?= htmlspecialchars($user['designation'] ?? '') ?>"
                                    placeholder="e.g. Senior Lecturer">
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" class="form-control"
                                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                    placeholder="e.g. +233 XX XXX XXXX">
                            </div>
                        </div>

                        <div style="display:flex; gap:12px; justify-content:flex-end;">
                            <a href="<?= BASE_URL ?>auth/change_password.php" class="btn btn-secondary">🔒 Change
                                Password</a>
                            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>