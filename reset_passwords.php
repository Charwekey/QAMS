<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';

echo "<h1>Resetting Passwords...</h1>";

$newPassword = 'password123';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

// Update all users
$sql = "UPDATE users SET password = ?";
dbExecute($sql, 's', [$hash]);

echo "<p style='color:green;'>All user passwords have been reset to: <strong>$newPassword</strong></p>";

// List users for verification
$users = dbFetchAll("SELECT * FROM users");
echo "<h2>Updated Users</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role(s)</th></tr>";

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

    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) . "</td>";
    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
    echo "<td>" . implode(', ', $roleNames) . "</td>";
    echo "</tr>";
}
echo "</table>";
?>