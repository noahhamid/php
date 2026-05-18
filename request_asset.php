<?php
session_start();
include "db.php";
?>

<div class="container">
<h1>Request Asset</h1>

<form method="POST">
    <input name="asset_type" placeholder="What do you need? (Laptop, Mouse...)">
    <input name="reason" placeholder="Reason">
    <button name="send">Send Request</button>
</form>
</div>

<?php
if(isset($_POST['send'])){

    $uid = $_SESSION['user_id'];
    $type = $_POST['asset_type'];
    $reason = $_POST['reason'];

    mysqli_query($conn,
    "INSERT INTO requests(user_id, asset_type, reason)
     VALUES('$uid','$type','$reason')");
}
?>