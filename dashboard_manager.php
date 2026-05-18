<?php
session_start();
if($_SESSION['role'] != 2){
    die("Access Denied");
}
?>

<link rel="stylesheet" href="style.css">

<div class="nav">
    <div>Manager Panel</div>
    <a href="logout.php">Logout</a>
</div>

<div class="container">
<h1>Manager Dashboard</h1>

<a href="view_requests.php">View Requests</a>
</div>