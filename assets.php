<?php
include "db.php";
?>

<link rel="stylesheet" href="style.css">

<div class="container">
<h1>Assets</h1>

<form method="POST" action="add_asset.php">
    <input name="name" placeholder="Name">
    <input name="category" placeholder="Category">
    <input name="serial" placeholder="Serial">
    <button>Add</button>
</form>

<?php
$res = mysqli_query($conn,"SELECT * FROM assets");

while($row = mysqli_fetch_assoc($res)){
    echo "<p>{$row['asset_name']} - {$row['status']}</p>";
}
?>
</div>