<?php
/**
 * csrf_helper.php
 * Helper CSRF token sederhana berbasis session.
 * WAJIB include file ini SETELAH session_start().
 */

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify($token) {
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Panggil di awal script yang menerima POST, sebelum proses data apapun.
 * Otomatis 403 + exit kalau token tidak valid.
 */
function csrf_require_valid_post() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!csrf_verify($token)) {
            header("HTTP/1.1 403 Forbidden");
            echo "Invalid CSRF token. Silakan kembali dan coba lagi.";
            exit();
        }
    }
}