<?php
session_start();
function check_role($allowed_roles) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }

    $role = $_SESSION['user_role'];

    if (is_array($allowed_roles)) {
        if (!in_array($role, $allowed_roles)) {
            die("<p style='font-family:sans-serif;padding:2rem;color:#b91c1c'>
                 Access denied. You do not have permission to view this page.
                 <a href='../login.php'>Go back</a></p>");
        }
    } else {
        if ($role !== $allowed_roles) {
            die("<p style='font-family:sans-serif;padding:2rem;color:#b91c1c'>
                 Access denied. You do not have permission to view this page.
                 <a href='../login.php'>Go back</a></p>");
        }
    }
}