<?php
require_once 'session.php';      // session dulu
require_once 'csrf_helper.php';  // baru helper
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

csrf_require_valid_post();

$json = $_POST['update_json'] ?? '';
$data = json_decode($json, true);

if (!is_array($data) || empty($data)) {
    die("❌ Data JSON tidak valid.");
}

$success = 0;
$failed  = 0;
$errors  = [];

try {
    $pdo->beginTransaction();

    $stmt_pilgan = $pdo->prepare("
        UPDATE kuis_soal 
        SET pertanyaan = ?, pilihan_a = ?, pilihan_b = ?, pilihan_c = ?, pilihan_d = ?, jawaban = ?, materi = ?
        WHERE id = ? AND jenis_soal = 'pilgan'
    ");

    $stmt_isian_soal = $pdo->prepare("
        UPDATE kuis_soal 
        SET pertanyaan = ?, materi = ?,
            pilihan_a = NULL, pilihan_b = NULL, pilihan_c = NULL, pilihan_d = NULL, jawaban = NULL
        WHERE id = ? AND jenis_soal = 'isian'
    ");

    $stmt_del_alt = $pdo->prepare("DELETE FROM kuis_soal_alternatif_isian WHERE soal_id = ?");
    $stmt_ins_alt = $pdo->prepare("INSERT INTO kuis_soal_alternatif_isian (soal_id, jawaban_alternatif) VALUES (?, ?)");

    foreach ($data as $row) {
        $id = (int)($row['id'] ?? 0);
        if (!$id) { $failed++; $errors[] = "ID invalid"; continue; }

        if ($row['jenis'] === 'pilgan') {
            $ok = $stmt_pilgan->execute([
                $row['pertanyaan'], $row['pilihan_a'], $row['pilihan_b'],
                $row['pilihan_c'], $row['pilihan_d'], $row['jawaban'], $row['materi'] ?: null,
                $id
            ]);
            if ($ok) $success++; else { $failed++; $errors[] = "Gagal update pilgan id=$id"; }
        } elseif ($row['jenis'] === 'isian') {
            // 1. UPDATE soal (jangan DELETE — biar CASCADE tidak trigger)
            $ok = $stmt_isian_soal->execute([
                $row['pertanyaan'], $row['materi'] ?: null, $id
            ]);
            if (!$ok) { $failed++; $errors[] = "Gagal update isian id=$id"; continue; }

            // 2. Refresh alternatif (aman — tidak ada FK ke jawaban siswa)
            $stmt_del_alt->execute([$id]);
            foreach ($row['alternatif'] as $alt) {
                $alt = trim($alt);
                if ($alt === '') continue;
                $stmt_ins_alt->execute([$id, $alt]);
            }
            $success++;
        } else {
            $failed++; $errors[] = "Jenis tidak dikenal: " . ($row['jenis'] ?? '?');
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('DB Error [update_quiz]: ' . $e->getMessage());
    die("❌ Gagal: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hasil Update - SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 600px;">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-3">✅ Update Selesai</h4>
                <p class="fs-5"><strong><?= $success ?></strong> soal berhasil diupdate.</p>
                <?php if ($failed > 0): ?>
                    <div class="alert alert-warning text-start">
                        <strong><?= $failed ?> soal gagal:</strong>
                        <ul class="mb-0 small"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <a href="/pintu-rahasia-sija" class="btn btn-primary mt-2">Kembali ke Manage Kuis</a>
            </div>
        </div>
    </div>
</body>
</html>