<?php
include 'koneksi.php';
include 'csrf_helper.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 404 Not Found");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['update_json'])) {
    header("Location: /pintu-rahasia-sija");
    exit();
}

csrf_require_valid_post();

$kategori = trim($_POST['kategori'] ?? '');
$level    = trim($_POST['level'] ?? '');
$allowed_level = ['pemula', 'menengah', 'mahir'];

$data = json_decode($_POST['update_json'], true);

if (!is_array($data) || empty($data) || !$kategori || !in_array($level, $allowed_level, true)) {
    header("Location: /pintu-rahasia-sija?msg=bulk_edit_invalid");
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE kuis_soal
         SET kategori = ?, level = ?, materi = ?, pertanyaan = ?, pilihan_a = ?, pilihan_b = ?, pilihan_c = ?, pilihan_d = ?, jawaban = ?
         WHERE id = ?"
    );

    foreach ($data as $row) {
        $id         = (int)($row['id'] ?? 0);
        $pertanyaan = trim($row['pertanyaan'] ?? '');
        $pilihan_a  = trim($row['pilihan_a'] ?? '');
        $pilihan_b  = trim($row['pilihan_b'] ?? '');
        $pilihan_c  = trim($row['pilihan_c'] ?? '');
        $pilihan_d  = trim($row['pilihan_d'] ?? '');
        $jawaban    = strtolower(trim($row['jawaban'] ?? ''));
        $materi     = trim($row['materi'] ?? '') !== '' ? trim($row['materi']) : null;

        if (!$id || !$pertanyaan || !$pilihan_a || !$pilihan_b || !$pilihan_c || !$pilihan_d
            || !in_array($jawaban, ['a', 'b', 'c', 'd'], true)) {
            throw new Exception("Data soal id={$id} tidak lengkap atau tidak valid.");
        }

        $stmt->execute([$kategori, $level, $materi, $pertanyaan, $pilihan_a, $pilihan_b, $pilihan_c, $pilihan_d, $jawaban, $id]);
    }

    $pdo->commit();
    header("Location: /pintu-rahasia-sija?msg=bulk_edit_success");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: /pintu-rahasia-sija?msg=bulk_edit_failed");
    exit();
}
