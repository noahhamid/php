<?php
include "db.php";

$id = $_GET['id'];

mysqli_query($conn,
"UPDATE requests SET status='Rejected' WHERE id=$id");

header("Location: view_requests.php");
?>