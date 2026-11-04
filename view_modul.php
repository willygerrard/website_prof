<?php
/**
 * view_modul.php — Tampilkan modul dengan theme wrapper (untuk siswa).
 *
 * Halaman ini dibuka di tab baru dari buka_modul.php.
 * Menggunakan theme wrapper yang sama dengan preview admin.
 */

require_once 'session.php';
require_once 'ai_helper.php';
require 'koneksi.php';
if (!defined('AIMM_PUBLIC_DIR')) define('AIMM_PUBLIC_DIR', __DIR__ . '/modul');

checkLogin();

$modul_id = (int)($_GET['id'] ?? 0);
$user_id  = $_SESSION['user_id'] ?? null;
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

if (!$user_id || !$modul_id) {
    header("Location: index.php");
    exit();
}

// Ambil data modul
if ($is_admin) {
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
} else {
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ? AND status = 'approved'");
}
$stmt->execute([$modul_id]);
$modul = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$modul || empty($modul['file_path'])) {
    header("Location: index.php");
    exit();
}

// Baca konten file modul (HTML mentah AI yang sudah disanitasi)
$filePath = $modul['file_path'];
$modulContent = '';
if (file_exists($filePath) && is_readable($filePath)) {
    $modulContent = file_get_contents($filePath);
} elseif (file_exists(AIMM_PUBLIC_DIR . '/' . basename($filePath)) && is_readable(AIMM_PUBLIC_DIR . '/' . basename($filePath))) {
    $modulContent = file_get_contents(AIMM_PUBLIC_DIR . '/' . basename($filePath));
}

// Bungkus dengan theme wrapper
$wrappedContent = aimmWrapWithTheme($modulContent, $modul['title'] ?? 'Modul Pembelajaran');

// Output langsung HTML yang sudah dibungkus theme
echo $wrappedContent;