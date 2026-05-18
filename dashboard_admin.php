<?php
include "db.php";

$request_id = $_GET['rid'];
$asset_id = $_GET['aid'];

// mark asset assigned
mysqli_query($conn,
"UPDATE assets SET status='Assigned' WHERE id=$asset_id");

// update request
mysqli_query($conn,
"UPDATE requests 
 SET status='Assigned', admin_message='Asset allocated'
 WHERE id=$request_id");
?>