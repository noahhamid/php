<?php
include "db.php";

$id = $_GET['id'];
$msg = $_GET['msg']; // simple version
$status = $_GET['status'];

mysqli_query($conn,
"UPDATE requests 
 SET status='$status',
 manager_message='$msg'
 WHERE id=$id");

header("Location: view_requests.php");
?>