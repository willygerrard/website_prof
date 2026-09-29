<?php
/**
 * csrf_helper.php
 * Helper CSRF token sederhana berbasis session.
 * WAJIB di-include SETELAH session aktif (session_start() sudah dipanggil).
 */

function csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Session belum aktif — jangan start di sini (rawan headers already sent).
        // Ini kesalahan programmer, jadi kita log + kembalikan string kosong.
        error_log('csrf_token(): session belum aktif. Pastikan session_start() dipanggil dulu.');
        return '';
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = csrf_token();
    if ($token === '') {
        return '<!-- CSRF token tidak tersedia: session belum aktif -->';
    }
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify($token) {
    if (session_status() !== PHP_SESSION_ACTIVE) return false;
    if (empty($_SESSION['csrf_token'])) return false;
    if (!is_string($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_require_valid_post($param_name = 'csrf_token') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST[$param_name] ?? '';
        if (!csrf_verify($token)) {
            http_response_code(403);
            echo "❌ 403 Forbidden: Invalid CSRF token. Silakan kembali dan coba lagi.";
            exit();
        }
    }
}

function csrf_require_valid_get($param_name = 'token') {
    $token = $_GET[$param_name] ?? '';
    if (!csrf_verify($token)) {
        http_response_code(403);
        echo "❌ 403 Forbidden: Invalid CSRF token untuk aksi ini. Permintaan diblokir.";
        exit();
    }
}