<?php
session_start();

$valid_username = "admin";
$valid_password = "bdms25";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION["user"] = $username;
        $_SESSION["role"] = "administrator";
        header("Location: dashboard.php");
        exit();
    } else {
        $_SESSION["error"] = "Invalid username or password!";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
