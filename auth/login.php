<?php
/**
 * Login Page
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    redirect(ROLE_DASHBOARDS[$role] ?? 'auth/login.php');
}

$error = '';

$selectedFacultyId = 0;
$selectedDepartmentId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId = intval($_POST['user_type'] ?? 0);
    $sessionId = intval($_POST['session_id'] ?? 0);
    $selectedFacultyId = intval($_POST['faculty_id'] ?? 0);
    $selectedDepartmentId = intval($_POST['department_id'] ?? 0);

    if (empty($email) || empty($password) || $roleId === 0) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check user credentials
        $user = dbFetchOne("SELECT * FROM users WHERE email = ? AND is_active = 1", 's', [$email]);

        if ($user && password_verify($password, $user['password'])) {
            // Verify user has the selected role
            $hasRole = dbFetchOne(
                "SELECT * FROM user_type_rel WHERE user_id = ? AND user_type_id = ?",
                'ii',
                [$user['id'], $roleId]
            );

            if ($hasRole) {
                // Validate role-specific selections
                if ($roleId === ROLE_HOD && $selectedDepartmentId === 0) {
                    $error = 'Please select your department.';
                } elseif (($roleId === ROLE_DEAN || $roleId === ROLE_DIRECTOR) && $selectedFacultyId === 0) {
                    $error = 'Please select your faculty.';
                } else {
                    // Optionally verify selected department/faculty against user profile
                    $userDept = getUserDepartment($user['id']);
                    if ($roleId === ROLE_HOD && $userDept && $userDept['id'] != $selectedDepartmentId) {
                        $error = 'Selected department does not match your profile.';
                    } elseif ($roleId === ROLE_DEAN && $userDept && $userDept['faculty_id'] != $selectedFacultyId) {
                        $error = 'Selected faculty does not match your profile.';
                    } else {
                        // Set session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_role'] = $roleId;
                        $_SESSION['session_id'] = $sessionId ?: null;
                        $_SESSION['login_time'] = time();
                        $_SESSION['selected_faculty_id'] = $selectedFacultyId ?: null;
                        $_SESSION['selected_department_id'] = $selectedDepartmentId ?: null;

                        // Update last login
                        dbExecute("UPDATE users SET last_login = NOW() WHERE id = ?", 'i', [$user['id']]);

                        // Redirect to role dashboard
                        redirect(ROLE_DASHBOARDS[$roleId]);
                    }
                }
            } else {
                $error = 'You do not have the selected role.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

// Fetch data for dropdowns
$userTypes = dbFetchAll("SELECT * FROM user_types ORDER BY id");
$sessions = getSessions();
$faculties = getFaculties();
$departments = getDepartments();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – QAMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect fill='%234f46e5' width='32' height='32' rx='8'/><text x='16' y='22' text-anchor='middle' fill='white' font-size='16' font-weight='bold'>Q</text></svg>">
</head>

<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <div class="logo-icon">Q</div>
                <h1>QAMS</h1>
                <p>Quality Assurance Management System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php $fl = getFlash();
            if ($fl): ?>
                <div class="alert alert-<?= $fl['type'] ?>">
                    <?= htmlspecialchars($fl['message']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" data-validate>
                <?= csrfField() ?>

                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="your.email@university.edu"
                        value="<?= htmlspecialchars($email ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password"
                        required>
                </div>

                <div class="form-group">
                    <label>Login As <span class="required">*</span></label>
                    <select name="user_type" id="user_type" class="form-control" required>
                        <option value="">Select your role</option>
                        <?php foreach ($userTypes as $ut): ?>
                            <option value="<?= $ut['id'] ?>" <?= ($roleId ?? 0) == $ut['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ut['type_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row" id="login-session-row">
                    <div class="form-group">
                        <label>Academic Session</label>
                        <select name="session_id" class="form-control">
                            <option value="">Select session</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($sessionId ?? 0) == $s['id'] ? 'selected' : ($s['is_current'] ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($s['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row" id="faculty-row" style="display:none;">
                    <div class="form-group">
                        <label>Faculty</label>
                        <select name="faculty_id" class="form-control">
                            <option value="">Select faculty</option>
                            <?php foreach ($faculties as $faculty): ?>
                                <option value="<?= $faculty['id'] ?>" <?= ($selectedFacultyId ?? 0) == $faculty['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($faculty['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row" id="department-row" style="display:none;">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" class="form-control">
                            <option value="">Select department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= ($selectedDepartmentId ?? 0) == $dept['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
                    Sign In
                </button>
            </form>

            <div style="text-align:center; margin-top:20px; display:flex; flex-direction:column; gap:8px;">
                <a href="<?= BASE_URL ?>auth/register.php" style="font-weight:600;">Don't have an account? Sign Up</a>
                <a href="<?= BASE_URL ?>auth/forgot_password.php"
                    style="font-size:0.8125rem; color:var(--text-secondary);">Forgot Password?</a>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
    <script>
        function toggleLoginFields() {
            const role = document.getElementById('user_type').value;
            const facultyRow = document.getElementById('faculty-row');
            const departmentRow = document.getElementById('department-row');

            facultyRow.style.display = 'none';
            departmentRow.style.display = 'none';

            if (role === '4' || role === '5') {
                facultyRow.style.display = 'block';
            }
            if (role === '3' || role === '5') {
                departmentRow.style.display = 'block';
            }
        }

        document.getElementById('user_type').addEventListener('change', toggleLoginFields);
        window.addEventListener('DOMContentLoaded', toggleLoginFields);
    </script>
</body>

</html>