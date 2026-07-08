<?php
session_start();

function checkLogin() {
    if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
        header("Location: login.php");
        exit();
    }
}

function checkRole($allowed_roles = []) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        header("HTTP/1.1 404 Not Found");
        exit();
    }
    return $_SESSION['role'];

}
?>
