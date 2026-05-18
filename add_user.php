<?php
session_start();
include "db.php";

if($_SESSION['role'] != 1){
    die("Access Denied");
}

if(isset($_POST['add'])){

    $username = $_POST['username'];
    $password = $_POST['password'];
    $role_id = $_POST['role_id'];

    $query = "INSERT INTO users(username, password, role_id)
              VALUES('$username', '$password', '$role_id')";

    mysqli_query($conn, $query);

    echo "User created successfully!";
}
?>

<link rel="stylesheet" href="style.css">

<div class="container">
    <h1>Create User</h1>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>

        <input type="password" name="password" placeholder="Password" required>

        <select name="role_id">
            <option value="1">Admin</option>
            <option value="2">Manager</option>
            <option value="3">Staff</option>
        </select>

        <button name="add">Create User</button>
    </form>
</div>