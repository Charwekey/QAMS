<?php
/**
 * Change Password
 */
$pageTitle = 'Change Password';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';
    $userId = getCurrentUserId();

    if (empty($currentPwd) || empty($newPwd) || empty($confirmPwd)) {
        setFlash('error', 'All fields are required.');
        redirect('auth/change_password.php');
    } elseif ($newPwd !== $confirmPwd) {
        setFlash('error', 'New passwords do not match.');
        redirect('auth/change_password.php');
    } elseif (strlen($newPwd) < 6) {
        setFlash('error', 'Password must be at least 6 characters.');
        redirect('auth/change_password.php');
    } else {
        $user = dbFetchOne("SELECT password FROM users WHERE id = ?", 'i', [$userId]);
        if ($user && password_verify($currentPwd, $user['password'])) {
            $hash = password_hash($newPwd, PASSWORD_DEFAULT);
            dbExecute("UPDATE users SET password = ? WHERE id = ?", 'si', [$hash, $userId]);
            setFlash('success', 'Password changed successfully!');
            redirect('auth/change_password.php');
        } else {
            setFlash('error', 'Current password is incorrect.');
            redirect('auth/change_password.php');
        }
    }
}
?>

<div class="page-content">
    <div class="page-header">
        <h2>Change Password</h2>
        <p>Update your account password</p>
    </div>

    <div class="card" style="max-width:500px;">
        <div class="card-body">
            <form method="POST" action="" data-validate>
                <?= csrfField() ?>

                <div class="form-group">
                    <label>Current Password <span class="required">*</span></label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>New Password <span class="required">*</span></label>
                    <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters"
                        required>
                </div>

                <div class="form-group">
                    <label>Confirm New Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>