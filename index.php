<?php
// index.php
require_once "includes/auth.php";

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    header("Location: $role/dashboard.php");
    exit;
} else {
    header("Location: login.php");
    exit;
}
?>
