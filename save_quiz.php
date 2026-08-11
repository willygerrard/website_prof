<?php

// 2. KONEKSI KE MARIADB (Sesuaikan konfigurasi database Anda)
include 'koneksi.php'; 
include __DIR__ . '/includes/soal_writer.php';
include 'csrf_helper.php';
session_start();

// Wajib login sebagai admin sebelum bisa menyimpan soal
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 404 Not Found");
    exit();
}

csrf_require_valid_post();

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
// kategori, level & materi
$kategori  = $_POST['kategori'] ?? 'Network'; // Default fallback
$level     = $_POST['level'] ?? 'pemula';    // Default fallback
$materi    = trim($_POST['materi'] ?? '') !== '' ? trim($_POST['materi']) : null;

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