<?php
/**
 * session.php
 * Bootstrap session + helper auth.
 * WAJIB di-include PALING ATAS, sebelum file lain.
 */

// Set lifetime konsisten dengan signup.php / session_bootstrap.php
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 7200;

    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.cookie_lifetime', $lifetime);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function checkLogin() {
    if (empty($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
        header("Location: login.php");
        exit();
    }
}

function checkRole($allowed_roles = []) {
    if (empty($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles, true)) {
        http_response_code(403);
        echo "403 Forbidden";
        exit();
    }
    return $_SESSION['role'];
}