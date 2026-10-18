<?php
require_once 'session.php';
require_once 'csrf_helper.php';
require 'koneksi.php';
include __DIR__ . '/includes/soal_writer.php';

checkLogin();
checkRole(['admin']);
csrf_require_valid_post();

$json = $_POST['quiz_json'] ?? file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data || !isset($data['questions'])) {
    die(json_encode(["success" => false, "error" => "JSON tidak valid."]));
}

$kategori    = $_POST['kategori'] ?? 'Network';
$level       = $_POST['level'] ?? 'pemula';
$materi      = trim($_POST['materi'] ?? '') !== '' ? trim($_POST['materi']) : null;
$jenis_soal  = $_POST['jenis_soal'] ?? 'pilgan'; // Tangkap jenis soal dari form

$total = 0;

try {
    $pdo->beginTransaction();

    foreach ($data['questions'] as $q) {

        // ==========================================
        // LOGIKA SIMPAN SOAL ISIAN
        // ==========================================
        if ($jenis_soal === 'isian') {
            if (empty($q['question_text']) || empty($q['correct_answer'])) {
                continue;
            }

            // Gabungkan jawaban utama dan alternatif
            $alternatif = [$q['correct_answer']];
            if (!empty($q['alternatif']) && is_array($q['alternatif'])) {
                $alternatif = array_merge($alternatif, $q['alternatif']);
            }
            $alternatif = array_values(array_unique(array_filter(array_map('trim', $alternatif))));

            // Panggil fungsi simpan isian (pastikan fungsi ini ada di soal_writer.php)
            simpanSoalIsian($pdo, [
                'kategori'   => $kategori,
                'level'      => $level,
                'materi'     => $materi,
                'pertanyaan' => trim($q['question_text']),
                'alternatif' => $alternatif,
            ]);

            $total++;
            continue; // Lanjut ke soal berikutnya
        }

        // ==========================================
        // LOGIKA SIMPAN SOAL PILIHAN GANDA
        // ==========================================
        if (
            empty($q['question_text']) ||
            empty($q['options']) ||
            count($q['options']) < 4
        ) {
            continue;
        }

        $a = trim($q['options'][0]);
        $b = trim($q['options'][1]);
        $c = trim($q['options'][2]);
        $d = trim($q['options'][3]);

        $correct = trim($q['correct_answer']);

        if ($correct == $a) $jawaban = "a";
        elseif ($correct == $b) $jawaban = "b";
        elseif ($correct == $c) $jawaban = "c";
        elseif ($correct == $d) $jawaban = "d";
        else continue;

        simpanSoalPilgan($pdo, [
            'kategori'   => $kategori,
            'level'      => $level,
            'materi'     => $materi,
            'pertanyaan' => trim($q['question_text']),
            'pilihan_a'  => $a,
            'pilihan_b'  => $b,
            'pilihan_c'  => $c,
            'pilihan_d'  => $d,
            'jawaban'    => $jawaban,
        ]);

        $total++;
    }

    $pdo->commit();
    header("Location: pintu-rahasia-sija?pesan=sukses");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage());
}