<?php
include "db.php";

if(isset($_POST['add'])){

    $name = $_POST['name'];
    $category = $_POST['category'];
    $serial = $_POST['serial'];

    $query = "INSERT INTO assets(asset_name, category, serial_number, status)
              VALUES('$name', '$category', '$serial', 'Available')";

    mysqli_query($conn, $query);

    header("Location: assets.php");
}
?>

<link rel="stylesheet" href="style.css">

<div class="container">
    <h1>Add Asset</h1>

    <form method="POST">
        <input type="text" name="name" placeholder="Asset Name">
        <input type="text" name="category" placeholder="Category">
        <input type="text" name="serial" placeholder="Serial Number">
        <button name="add">Add</button>
    </form>
</div>