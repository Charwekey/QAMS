<?php
/**
 * Forgot Password
 * Simple reset via email lookup + new password set.
 */
require_once __DIR__ . '/../includes/functions.php';
startSession();

$step = $_GET['step'] ?? 'email';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['find_account'])) {
        $email = sanitize($_POST['email'] ?? '');
        $user = dbFetchOne("SELECT id, full_name, email FROM users WHERE email = ? AND is_active = 1", 's', [$email]);
        if ($user) {
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_user_name'] = $user['full_name'];
            $step = 'reset';
        } else {
            $error = 'No active account found with that email address.';
        }
    } elseif (isset($_POST['reset_password'])) {
        $newPwd = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';
        $userId = $_SESSION['reset_user_id'] ?? null;

        if (!$userId) {
            $error = 'Session expired. Please start over.';
            $step = 'email';
        } elseif ($newPwd !== $confirmPwd) {
            $error = 'Passwords do not match.';
            $step = 'reset';
        } elseif (strlen($newPwd) < 6) {
            $error = 'Password must be at least 6 characters.';
            $step = 'reset';
        } else {
            $hash = password_hash($newPwd, PASSWORD_DEFAULT);
            dbExecute("UPDATE users SET password = ? WHERE id = ?", 'si', [$hash, $userId]);
            unset($_SESSION['reset_user_id'], $_SESSION['reset_user_name']);
            setFlash('success', 'Password reset successfully! You can now log in.');
            redirect('auth/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – QAMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
</head>

<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <div class="logo-icon">Q</div>
                <h1>Reset Password</h1>
                <p>Enter your email to recover your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 'email'): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="your.email@university.edu"
                            required>
                    </div>
                    <button type="submit" name="find_account" class="btn btn-primary btn-block btn-lg">Find My
                        Account</button>
                </form>

            <?php elseif ($step === 'reset'): ?>
                <div class="alert alert-info" style="margin-bottom:20px;">
                    Account found: <strong>
                        <?= htmlspecialchars($_SESSION['reset_user_name'] ?? '') ?>
                    </strong>
                </div>
                <form method="POST" action="?step=reset">
                    <div class="form-group">
                        <label>New Password <span class="required">*</span></label>
                        <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters"
                            required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary btn-block btn-lg">Reset
                        Password</button>
                </form>
            <?php endif; ?>

            <div style="text-align:center; margin-top:20px;">
                <a href="<?= BASE_URL ?>auth/login.php" style="font-size:0.8125rem;">← Back to Login</a>
            </div>
        </div>
    </div>
</body>

</html>