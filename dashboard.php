<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: login.php");
}
?>

<link rel="stylesheet" href="style.css">

<div class="nav">
    <div>Asset System</div>
    <div>
        <a href="assets.php">Assets</a> |
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h1>Welcome <?php echo $_SESSION['user']; ?></h1>

    <?php
    if($_SESSION['role'] == 1){
        echo "<p>You are Admin</p>";
    } elseif($_SESSION['role'] == 2){
        echo "<p>You are Manager</p>";
    } else {
        echo "<p>You are Staff</p>";
    }
    ?>
</div>