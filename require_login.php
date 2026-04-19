<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["role"])) {
    $_SESSION["role"] = "staff";
}

