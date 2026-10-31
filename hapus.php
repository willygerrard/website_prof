<?php
// 1. Koneksi ke database MariaDB via PDO (Sesuaikan dengan file koneksi Bapak)
require_once 'session.php';      // session dulu
require_once 'csrf_helper.php';  // baru helper
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: gerbang-rahasia-sija");
    exit();
}

csrf_require_valid_post('csrf_token');

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($id === false) {
    header("Location: gerbang-rahasia-sija");
    exit();
}

try {
    $stmt_select = $pdo->prepare("SELECT image_path FROM modules WHERE id = :id");
    $stmt_select->execute([':id' => $id]);
    $modul = $stmt_select->fetch(PDO::FETCH_ASSOC);

    if (!$modul) {
        echo "<script>alert('Data modul tidak ditemukan, Pak!'); window.location.href = 'gerbang-rahasia-sija';</script>";
        exit();
    }

    $stmt_delete = $pdo->prepare("DELETE FROM modules WHERE id = :id");
    $stmt_delete->execute([':id' => $id]);

    if ($stmt_delete->rowCount() !== 1) {
        echo "<script>alert('Modul gagal dihapus, Pak!'); window.location.href = 'gerbang-rahasia-sija';</script>";
        exit();
    }

    $file_gambar = $modul['image_path'] ?? '';
    if (
        !empty($file_gambar)
        && strpos($file_gambar, 'default') === false
        && file_exists($file_gambar)
        && !unlink($file_gambar)
    ) {
        error_log('Gagal menghapus file gambar modul: ' . $file_gambar);
    }

    echo "<script>
            alert('Modul berhasil dihapus, Pak!');
            window.location.href = 'gerbang-rahasia-sija';
          </script>";
    exit();
} catch (PDOException $e) {
    db_error($e);
}
?>