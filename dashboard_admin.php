<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    die("Access Denied");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="nav">
    <div>Admin Panel (Welcome <?php echo htmlspecialchars($_SESSION['user']); ?>)</div>
    <a href="logout.php">Logout</a>
</div>
<div class="container">
    <h1>Admin Dashboard</h1>
    <ul>
        <li><a href="assets.php">Manage Assets</a></li>
        <li><a href="create_user.php">Create Users</a></li>
        <li><a href="view_requests.php">Review Requests</a></li>
    </ul>
</div>
</body>
</html>