<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 1 && $_SESSION['role'] != 2)) {
    die("Access Denied");
}

// Handle Add Asset via form submission redirecting or processing contextually
if (isset($_POST['add'])) {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $serial = trim($_POST['serial']);

    $stmt = mysqli_prepare($conn, "INSERT INTO assets (asset_name, category, serial_number, status) VALUES (?, ?, ?, 'Available')");
    mysqli_stmt_bind_param($stmt, "sss", $name, $category, $serial);
    mysqli_stmt_execute($stmt);
    header("Location: assets.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assets Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Assets Registry</h1>

    <?php if($_SESSION['role'] == 1): ?>
    <form method="POST" action="assets.php">
        <h3>Add New Asset</h3>
        <input name="name" placeholder="Name" required>
        <input name="category" placeholder="Category" required>
        <input name="serial" placeholder="Serial" required>
        <button name="add">Add Asset</button>
    </form>
    <?php endif; ?>

    <h2>Current Inventory</h2>
    <?php
    $res = mysqli_query($conn, "SELECT * FROM assets");
    while($row = mysqli_fetch_assoc($res)){
        echo "<p><strong>" . htmlspecialchars($row['asset_name']) . "</strong> - Category: " . htmlspecialchars($row['category']) . " [" . htmlspecialchars($row['status']) . "]</p>";
    }
    ?>
    <br>
    <a href="logout.php">Logout</a>
</div>
</body>
</html>