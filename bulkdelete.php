<?php
include 'koneksi.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

// Hanya terima request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /pintu-rahasia-sija");
    exit();
}

// Ambil id-id yang dicentang dari checkbox
$ids = $_POST['ids'] ?? [];

if (!empty($ids)) {
    // Pastikan semua id berupa integer (mencegah SQL injection & data kotor)
    $ids = array_filter(array_map('intval', $ids));

    if (!empty($ids)) {
        // Buat placeholder ?,?,?,... sesuai jumlah id
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM kuis_soal WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    }
}

header("Location: /pintu-rahasia-sija");
exit();