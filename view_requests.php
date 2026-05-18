<?php
include "db.php";
?>

<link rel="stylesheet" href="style.css">

<div class="container">
<h1>Requests</h1>

<?php
$res = mysqli_query($conn, "SELECT * FROM requests");

while($row = mysqli_fetch_assoc($res)){
    echo "
    <div style='background:#1e293b;padding:10px;margin:10px'>
        <p>{$row['asset_name']}</p>
        <p>{$row['reason']}</p>
        <p>Status: {$row['status']}</p>

        <a href='approve.php?id={$row['id']}'>Approve</a> |
        <a href='reject.php?id={$row['id']}'>Reject</a>
    </div>
    ";
}
?>
</div>