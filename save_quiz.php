<?php

// 2. KONEKSI KE MARIADB (Sesuaikan konfigurasi database Anda)
include 'koneksi.php'; 
include 'csrf_helper.php';
session_start();

// =========================
// AMBIL JSON
// =========================
$json = $_POST['quiz_json'] ?? file_get_contents("php://input");

$data = json_decode($json, true);

if (!$data || !isset($data['questions'])) {
    die(json_encode([
        "success" => false,
        "error" => "JSON tidak valid."
    ]));
}
// kategori & level
$kategori  = $_POST['kategori'] ?? 'Network'; // Default fallback
$level     = $_POST['level'] ?? 'pemula';    // Default fallback


// =========================
// PREPARE INSERT
// =========================

$sql = "INSERT INTO kuis_soal
(
kategori,
pertanyaan,
pilihan_a,
pilihan_b,
pilihan_c,
pilihan_d,
jawaban,
level
)
VALUES
(
:kategori,
:pertanyaan,
:a,
:b,
:c,
:d,
:jawaban,
:level
)";

$stmt = $pdo->prepare($sql);

$total = 0;

try {

    $pdo->beginTransaction();

    foreach ($data['questions'] as $q) {

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

        // ubah jawaban menjadi enum a,b,c,d
        if ($correct == $a)
            $jawaban = "a";
        elseif ($correct == $b)
            $jawaban = "b";
        elseif ($correct == $c)
            $jawaban = "c";
        elseif ($correct == $d)
            $jawaban = "d";
        else
            continue;

        $stmt->execute([
            ":kategori"   => $kategori,
            ":pertanyaan" => trim($q['question_text']),
            ":a"          => $a,
            ":b"          => $b,
            ":c"          => $c,
            ":d"          => $d,
            ":jawaban"    => $jawaban,
            ":level"      => $level
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
