<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    die("Access Denied");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="nav">
    <div>Manager Panel</div>
    <a href="logout.php">Logout</a>
</div>
<div class="container">
    <h1>Manager Dashboard</h1>
    <a href="view_requests.php">View Requests</a>
</div>
</body>
</html>