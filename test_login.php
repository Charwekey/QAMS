<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';

echo "<h1>QAMS Diagnostic - Phase 3 (Post-Reset)</h1>";

// Verify Hash
$password = 'password123';
echo "<h2>Password Verification</h2>";
echo "<p>Testing new password '$password' against all users:</p>";

$users = dbFetchAll("SELECT * FROM users");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Email</th><th>Role</th><th>Login Check</th></tr>";

foreach ($users as $user) {
    // Get roles
    $roles = dbFetchAll(
        "SELECT t.type_name FROM user_types t 
         JOIN user_type_rel r ON t.id = r.user_type_id 
         WHERE r.user_id = ?",
        'i',
        [$user['id']]
    );
    $roleNames = array_column($roles, 'type_name');
    $roleStr = implode(', ', $roleNames);

    $status = password_verify($password, $user['password'])
        ? "<span style='color:green; font-weight:bold;'>SUCCESS</span>"
        : "<span style='color:red; font-weight:bold;'>FAIL</span>";

    echo "<tr>";
    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
    echo "<td>" . $roleStr . "</td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
}
echo "</table>";
?>