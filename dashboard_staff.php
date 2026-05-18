<?php
session_start();
if($_SESSION['role'] != 3){
    die("Access Denied");
}
?>

<link rel="stylesheet" href="style.css">

<div class="nav">
    <div>Staff Panel</div>
    <a href="logout.php">Logout</a>
</div>

<div class="container">
<h1>Staff Dashboard</h1>

<a href="request_asset.php">Request Asset</a>
</div>