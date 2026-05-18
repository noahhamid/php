<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) {
    die("Access Denied");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="nav">
    <div>Staff Panel</div>
    <a href="logout.php">Logout</a>
</div>
<div class="container">
    <h1>Staff Dashboard</h1>
    <a href="request_asset.php">Request Asset</a>
</div>
</body>
</html>