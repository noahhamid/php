<?php
session_start();
include "db.php";

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 1 && $_SESSION['role'] != 2)) {
    die("Access Denied");
}

$id = intval($_GET['id']);
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : 'Approved';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'Approved';

$stmt = mysqli_prepare($conn, "UPDATE requests SET status = ?, manager_message = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "ssi", $status, $msg, $id);
mysqli_stmt_execute($stmt);

header("Location: view_requests.php");
exit;
?>