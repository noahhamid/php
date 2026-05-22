<?php
require_once 'db.php';
$hash = password_hash('password123', PASSWORD_DEFAULT);
$conn->query("UPDATE users SET password_hash='$hash' WHERE email IN ('admin@company.com','manager@company.com','staff@company.com')");
echo "Done! Hash: " . $hash;
?>