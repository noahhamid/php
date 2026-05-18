<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 1 && $_SESSION['role'] != 2)) {
    die("Access Denied");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Requests</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Incoming System Requests</h1>
    <?php
    $res = mysqli_query($conn, "SELECT * FROM requests");
    while ($row = mysqli_fetch_assoc($res)) {
        echo "
        <div style='background:#1e293b; padding:15px; margin:10px 0; border-radius:5px;'>
            <p><strong>Item Requested:</strong> " . htmlspecialchars($row['asset_type']) . "</p>
            <p><strong>Reason:</strong> " . htmlspecialchars($row['reason']) . "</p>
            <p><strong>Status:</strong> " . htmlspecialchars($row['status']) . "</p>";
            
            if ($row['status'] == 'Pending') {
                echo "
                <a href='approve.php?id=" . intval($row['id']) . "&status=Approved&msg=Approved+by+Manager'>Approve</a> | 
                <a href='reject.php?id=" . intval($row['id']) . "'>Reject</a>";
            }
        echo "</div>";
    }
    ?>
    <br>
    <a href="logout.php">Logout</a>
</div>
</body>
</html>