<?php
/**
 * User Registration
 */
$pageTitle = 'Sign Up';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$success = '';

// Fetch Departments and User Types for dropdowns
$departments = dbFetchAll("SELECT * FROM departments ORDER BY name");
$userTypes = dbFetchAll("SELECT * FROM user_types WHERE slug != 'admin' ORDER BY type_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $idNumber = sanitize($_POST['id_number'] ?? ''); // Employee or Student ID
    $phone = sanitize($_POST['phone'] ?? '');
    $roleId = intval($_POST['role_id'] ?? 0);
    $deptId = intval($_POST['department_id'] ?? 0);

    // Basic Validation
    if (empty($fullName) || empty($email) || empty($password) || empty($roleId) || empty($deptId)) {
        $error = 'All fields marked with * are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email already exists
        $existing = dbFetchOne("SELECT id FROM users WHERE email = ?", 's', [$email]);
        if ($existing) {
            $error = 'Email is already registered. Please log in.';
        } else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Determine designation based on role (simplified)
            $roleName = '';
            foreach ($userTypes as $ut) {
                if ($ut['id'] == $roleId) {
                    $roleName = $ut['type_name'];
                    break;
                }
            }

            // Insert User
            $conn = getDbConnection();
            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare("INSERT INTO users (email, password, full_name, employee_id, designation, department_id, phone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param('sssssss', $email, $hashedPassword, $fullName, $idNumber, $roleName, $deptId, $phone);

                if ($stmt->execute()) {
                    $newUserId = $conn->insert_id;
                    $stmt->close();

                    // Assign Role
                    $stmtRole = $conn->prepare("INSERT INTO user_type_rel (user_id, user_type_id) VALUES (?, ?)");
                    $stmtRole->bind_param('ii', $newUserId, $roleId);
                    $stmtRole->execute();
                    $stmtRole->close();

                    $conn->commit();
                    $success = 'Account created successfully! You can now log in.';
                } else {
                    throw new Exception("Registration failed: " . $stmt->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up -
        <?= SITE_NAME ?>
    </title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: var(--bg-body);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .auth-card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 500px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-header h1 {
            color: var(--primary);
            margin: 0 0 8px 0;
            font-size: 24px;
        }

        .auth-header p {
            color: var(--text-secondary);
            margin: 0;
        }

        .logo {
            width: 64px;
            height: 64px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            margin: 0 auto 24px;
        }

        .form-row {
            display: flex;
            gap: 16px;
        }

        .form-row .form-group {
            flex: 1;
        }
    </style>
</head>

<body>

    <div class="auth-card">
        <div class="auth-header">
            <div class="logo">Q</div>
            <h1>Create Account</h1>
            <p>Join the Quality Assurance Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
                <div style="margin-top:10px;">
                    <a href="login.php" class="btn btn-primary btn-block">Go to Login</a>
                </div>
            </div>
        <?php else: ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" class="form-control" required
                        value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="e.g. John Doe">
                </div>

                <div class="form-group">
                    <label>University Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="e.g. user@university.edu">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-control" required placeholder="Min 6 characters">
                    </div>
                    <div class="form-group">
                        <label>ID Number</label>
                        <input type="text" name="id_number" class="form-control"
                            value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>" placeholder="Employee/Student ID">
                    </div>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" class="form-control"
                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="+233...">
                </div>

                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select name="role_id" class="form-control" required>
                        <option value="">Select Role...</option>
                        <?php foreach ($userTypes as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= (isset($_POST['role_id']) && $_POST['role_id'] == $role['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['type_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Department <span class="required">*</span></label>
                    <select name="department_id" class="form-control" required>
                        <option value="">Select Department...</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 24px;">Sign Up</button>
            </form>

            <div class="text-center mt-16">
                <p class="text-muted">Already have an account? <a href="login.php" class="text-primary font-medium">Log
                        In</a></p>
            </div>

        <?php endif; ?>
    </div>

</body>

</html>