<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    die("Access Denied: Admins Only");
}

if (isset($_POST['add'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role_id = intval($_POST['role_id']);

    $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssi", $username, $password, $role_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $success = "User created successfully!";
    } else {
        $error = "Error creating user.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create User</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Create User</h1>
    <?php 
    if (isset($success)) echo "<p style='color: #10b981;'>".htmlspecialchars($success)."</p>"; 
    if (isset($error)) echo "<p style='color: #ef4444;'>".htmlspecialchars($error)."</p>"; 
    ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="role_id">
            <option value="1">Admin</option>
            <option value="2">Manager</option>
            <option value="3">Staff</option>
        </select>
        <button name="add">Create User</button>
    </form>
    <a href="dashboard_admin.php">Back to Dashboard</a>
</div>
</body>
</html>