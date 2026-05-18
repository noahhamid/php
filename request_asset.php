<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) {
    die("Access Denied");
}

if (isset($_POST['send'])) {
    $uid = $_SESSION['user_id'];
    $type = trim($_POST['asset_type']);
    $reason = trim($_POST['reason']);

    $stmt = mysqli_prepare($conn, "INSERT INTO requests (user_id, asset_type, reason, status) VALUES (?, ?, ?, 'Pending')");
    mysqli_stmt_bind_param($stmt, "iss", $uid, $type, $reason);
    mysqli_stmt_execute($stmt);
    $msg = "Request sent successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Asset</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Request Asset</h1>
    <?php if (isset($msg)) echo "<p style='color:#10b981;'>".htmlspecialchars($msg)."</p>"; ?>
    <form method="POST">
        <input name="asset_type" placeholder="What do you need? (Laptop, Mouse...)" required>
        <input name="reason" placeholder="Reason" required>
        <button name="send">Send Request</button>
    </form>
    <a href="dashboard_staff.php">Back</a>
</div>
</body>
</html>