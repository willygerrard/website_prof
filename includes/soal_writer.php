<?php
/**
 * soal_writer.php
 * Satu tempat untuk logic insert/update soal ke database, dipakai bareng
 * oleh tambah_soal.php, save_quiz.php, update_quiz.php, dan edit_soal.php.
 *
 * Kenapa disatukan: sebelum ini logic insert/update tersebar di beberapa
 * file secara terpisah-pisah, dan gampang salah satu ketinggalan pas ada
 * perubahan skema (kejadian nyata: bug kolom materi, lalu bug kolom
 * jenis_soal yang belum ke-handle di beberapa titik). Sekarang cukup ubah
 * di sini, otomatis kepakai di semua titik yang manggil.
 *
 * WAJIB include koneksi.php SEBELUM file ini (butuh $pdo sudah aktif),
 * meski $pdo di sini selalu dioper eksplisit sebagai parameter (bukan
 * global), supaya function ini jelas dependensinya dan gampang ditest.
 *
 * Fungsi *Isian() sengaja cek $pdo->inTransaction() dulu sebelum buka
 * transaction baru sendiri -- supaya AMAN dipanggil dari dalam batch
 * transaction yang lebih besar (misal nanti AI Import isian jadi banyak
 * soal sekaligus), tanpa error "transaction already active".
 */

function simpanSoalPilgan(PDO $pdo, array $data): int {
    $stmt = $pdo->prepare(
        "INSERT INTO kuis_soal (kategori, level, jenis_soal, materi, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban)
         VALUES (?, ?, 'pilgan', ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $data['kategori'],
        $data['level'],
        $data['materi'] ?? null,
        $data['pertanyaan'],
        $data['pilihan_a'],
        $data['pilihan_b'],
        $data['pilihan_c'],
        $data['pilihan_d'],
        $data['jawaban'],
    ]);
    return (int) $pdo->lastInsertId();
}

function simpanSoalIsian(PDO $pdo, array $data): int {
    // $data['alternatif'] = array of string, minimal 1 elemen non-kosong
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO kuis_soal (kategori, level, jenis_soal, materi, pertanyaan) VALUES (?, ?, 'isian', ?, ?)"
        );
        $stmt->execute([$data['kategori'], $data['level'], $data['materi'] ?? null, $data['pertanyaan']]);
        $soal_id = (int) $pdo->lastInsertId();

        $stmtAlt = $pdo->prepare("INSERT INTO kuis_soal_alternatif_isian (soal_id, jawaban_alternatif) VALUES (?, ?)");
        foreach ($data['alternatif'] as $alt) {
            $alt = trim($alt);
            if ($alt === '') continue;
            $stmtAlt->execute([$soal_id, $alt]);
        }

        if ($ownTransaction) $pdo->commit();
        return $soal_id;
    } catch (Exception $e) {
        if ($ownTransaction) $pdo->rollBack();
        throw $e;
    }
}

function updateSoalPilgan(PDO $pdo, int $id, array $data): void {
    $stmt = $pdo->prepare(
        "UPDATE kuis_soal
         SET kategori = ?, level = ?, jenis_soal = 'pilgan', materi = ?, pertanyaan = ?,
             pilihan_a = ?, pilihan_b = ?, pilihan_c = ?, pilihan_d = ?, jawaban = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $data['kategori'],
        $data['level'],
        $data['materi'] ?? null,
        $data['pertanyaan'],
        $data['pilihan_a'],
        $data['pilihan_b'],
        $data['pilihan_c'],
        $data['pilihan_d'],
        $data['jawaban'],
        $id,
    ]);
}

function updateSoalIsian(PDO $pdo, int $id, array $data): void {
    // $data['alternatif'] = array of string. Alternatif LAMA dihapus semua,
    // diganti total dengan yang baru (bukan merge) -- lebih predictable.
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "UPDATE kuis_soal
             SET kategori = ?, level = ?, jenis_soal = 'isian', materi = ?, pertanyaan = ?,
                 pilihan_a = NULL, pilihan_b = NULL, pilihan_c = NULL, pilihan_d = NULL, jawaban = NULL
             WHERE id = ?"
        );
        $stmt->execute([$data['kategori'], $data['level'], $data['materi'] ?? null, $data['pertanyaan'], $id]);

        $pdo->prepare("DELETE FROM kuis_soal_alternatif_isian WHERE soal_id = ?")->execute([$id]);

        $stmtAlt = $pdo->prepare("INSERT INTO kuis_soal_alternatif_isian (soal_id, jawaban_alternatif) VALUES (?, ?)");
        foreach ($data['alternatif'] as $alt) {
            $alt = trim($alt);
            if ($alt === '') continue;
            $stmtAlt->execute([$id, $alt]);
        }

        if ($ownTransaction) $pdo->commit();
    } catch (Exception $e) {
        if ($ownTransaction) $pdo->rollBack();
        throw $e;
    }
}