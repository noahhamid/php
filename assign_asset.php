<?php
include "db.php";

$request_id = $_GET['rid'];
$asset_id = $_GET['aid'];
$admin_id = $_GET['admin_id'];

// 1. update asset
mysqli_query($conn,
"UPDATE assets SET status='Assigned' WHERE id=$asset_id");

// 2. update request
mysqli_query($conn,
"UPDATE requests 
 SET status='Assigned', admin_message='Asset allocated'
 WHERE id=$request_id");

// 3. insert into assignments table (IMPORTANT)
mysqli_query($conn,
"INSERT INTO assignments(request_id, asset_id, assigned_by)
 VALUES('$request_id','$asset_id','$admin_id')");

echo "Asset assigned successfully!";
?>