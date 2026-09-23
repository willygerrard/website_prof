<?php
include 'koneksi.php';
session_start();

if (!isset($_SESSION['is_login'])) {
    http_response_code(403);
    exit('Akses ditolak. Silakan login dulu.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID tidak valid.');
}

$stmt = $pdo->prepare("SELECT * FROM pengumpulan_tugas WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

// Hak akses: pemilik file sendiri, ATAU guru/admin (buat keperluan menilai)
$isOwner = ((int)$row['user_id'] === (int)($_SESSION['user_id'] ?? 0));
$isStaff = in_array($_SESSION['role'] ?? '', ['admin', 'guru'], true); // sesuaikan nama role kamu

if (!$isOwner && !$isStaff) {
    http_response_code(403);
    exit('Kamu tidak punya akses ke file ini.');
}

// $row['file_path'] disimpan relatif, contoh: "uploads/nama_folder/file.jpg"
$baseDir  = realpath('/var/www/html/uploads');
$fullPath = realpath('/var/www/html/' . $row['file_path']);

// Guard path traversal: pastikan hasil realpath masih di dalam folder uploads,
// bukan hasil manipulasi "../../" di file_path.
if ($baseDir === false || $fullPath === false || strpos($fullPath, $baseDir) !== 0) {
    http_response_code(400);
    exit('Path file tidak valid.');
}

if (!file_exists($fullPath)) {
    http_response_code(404);
    exit('File sudah tidak ada di server.');
}

$mime = mime_content_type($fullPath) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;