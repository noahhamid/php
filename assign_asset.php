<?php
session_start();
include "db.php";

// Only Admins should execute hardware allocation paths
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    die("Access Denied");
}

$request_id = intval($_GET['rid']);
$asset_id = intval($_GET['aid']);
$admin_id = intval($_SESSION['user_id']); 

// 1. Update Asset Status
$stmt1 = mysqli_prepare($conn, "UPDATE assets SET status = 'Assigned' WHERE id = ?");
mysqli_stmt_bind_param($stmt1, "i", $asset_id);
mysqli_stmt_execute($stmt1);

// 2. Update Request Context
$stmt2 = mysqli_prepare($conn, "UPDATE requests SET status = 'Assigned', admin_message = 'Asset allocated' WHERE id = ?");
mysqli_stmt_bind_param($stmt2, "i", $request_id);
mysqli_stmt_execute($stmt2);

// 3. Log into Assignments Table
$stmt3 = mysqli_prepare($conn, "INSERT INTO assignments (request_id, asset_id, assigned_by) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt3, "iii", $request_id, $asset_id, $admin_id);
mysqli_stmt_execute($stmt3);

echo "Asset assigned successfully! <a href='dashboard_admin.php'>Return</a>";
?>