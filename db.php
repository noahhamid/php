<?php

$conn = mysqli_connect(
    "localhost",
    "root",
    "1234",
    "asset_system"
);

if(!$conn){
    die("Connection Failed");
}

?>