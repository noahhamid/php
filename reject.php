<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 1 && $_SESSION['role'] != 2)) {
    die("Access Denied");
}

$id = intval($_GET['id']);

$stmt = mysqli_prepare($conn, "UPDATE requests SET status = 'Rejected' WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

header("Location: view_requests.php");
exit;
?>