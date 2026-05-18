<?php
include "db.php";

echo "<h2>Database Raw Values Verification</h2>";

// Let's read exactly what is in your table right now
$query = "SELECT id, username, password, role_id FROM users";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

echo "<table border='1' cellpadding='10' style='border-collapse:collapse; background:#1e293b; color:white;'>";
echo "<tr><th>ID</th><th>Username</th><th>Role ID</th><th>Password Length</th><th>Raw Password Value in DB</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    $length = strlen($row['password']);
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
    echo "<td>{$row['role_id']}</td>";
    echo "<td>{$length} chars</td>";
    echo "<td><code>" . htmlspecialchars($row['password']) . "</code></td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Bcrypt Hash Reference Check</h3>";
$expected_hash = '$2y$10$8C9g4MvD7hKzYVfE4v9KJu1.k7Z0gWvS0eR1A4bV3cDxEfGhIjKlMn';
echo "Expected full hash length: **" . strlen($expected_hash) . " chars**<br>";
?>