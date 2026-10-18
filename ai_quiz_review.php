<?php
require_once 'session.php';
require 'koneksi.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error', 'message'=>'Unauthorized']); exit; }

$user_id = $_SESSION['user_id'];
$sesi_id = (int)($_GET['sesi'] ?? 0);
$attempt = (int)($_GET['attempt'] ?? 1);

// 1. Ambil data hasil & detail jawaban dari DB
$stmt = $pdo->prepare("SELECT h.detail_jawaban, s.kategori, s.level FROM kuis_hasil h JOIN kuis_sesi s ON h.sesi_id = s.id WHERE h.user_id = ? AND h.sesi_id = ? AND h.attempt = ?");
$stmt->execute([$user_id, $sesi_id, $attempt]);
$hasil = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hasil || empty($hasil['detail_jawaban'])) {
    echo json_encode(['status'=>'error', 'message'=>'Data jawaban tidak ditemukan.']); exit;
}

$detail_jawaban = json_decode($hasil['detail_jawaban'], true);
$soal_ids = array_keys($detail_jawaban);

if (empty($soal_ids)) {
    echo json_encode(['status'=>'success', 'data'=>[], 'message'=>'Tidak ada jawaban yang tersimpan.']); exit;
}

// 2. Ambil data soal dari DB
$placeholders = implode(',', array_fill(0, count($soal_ids), '?'));
$stmt_soal = $pdo->prepare("SELECT id, pertanyaan, jenis_soal, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban FROM kuis_soal WHERE id IN ($placeholders)");
$stmt_soal->execute($soal_ids);
$soal_list = $stmt_soal->fetchAll(PDO::FETCH_ASSOC);

$data_untuk_ai = [];
foreach ($soal_list as $s) {
    $id_s = $s['id'];
    $jawab_siswa = $detail_jawaban[$id_s] ?? null;
    
    // Skip kalau siswa jawab bener
    if ($jawab_siswa === $s['jawaban']) continue;

    $teks_jawaban_siswa = '-';
    $teks_jawaban_benar = '-';

    if ($s['jenis_soal'] === 'pilgan') {
        $teks_jawaban_siswa = strtoupper($jawab_siswa) . ". " . $s['pilihan_'.strtolower($jawab_siswa)];
        $teks_jawaban_benar = strtoupper($s['jawaban']) . ". " . $s['pilihan_'.strtolower($s['jawaban'])];
    } else {
        // Kalau isian, kita butuh query ke kuis_jawaban_isian (bisa disesuaikan nanti)
        continue; 
    }

    $data_untuk_ai[] = [
        'soal' => $s['pertanyaan'],
        'jawaban_kamu' => $teks_jawaban_siswa,
        'jawaban_benar' => $teks_jawaban_benar
    ];
}

if (empty($data_untuk_ai)) {
    echo json_encode(['status'=>'success', 'data'=>[], 'message'=>'Sempurna! Tidak ada kesalahan yang perlu direview.']); exit;
}

// 3. PROMPT AI
$prompt = "Kamu adalah tutor yang ramah dan suportif. Siswa ini baru saja menyelesaikan kuis {$hasil['kategori']} ({$hasil['level']}). "
        . "Dia sudah lulus, tapi masih ada beberapa jawaban yang salah. Jelaskan letak kesalahannya dengan bahasa yang mudah dipahami dan berikan semangat.\n\n"
        . "Berikut soal yang salah:\n" . json_encode($data_untuk_ai, JSON_UNESCAPED_UNICODE) . "\n\n"
        . "Kembalikan output HANYA berupa JSON ARRAY murni tanpa markdown blok dengan struktur: "
        . '[{"soal": "...", "kesalahan": "...", "konsep_harus_dipelajari": "..."}]';

// 4. PANGGIL API GEMINI (Salin fungsi curl dari ai_quiz_generate.php ke sini)
// Karena ini cuma simulasi, gue tulis pseudocode-nya:
/* 
$apiKey = aiqEnv('GEMINI_API_KEY'); 
$model = 'gemini-1.5-flash';
$ai_response = geminiJson($apiKey, $model, $prompt, $schemaReview, 30, 0.5);

echo json_encode(['status' => 'success', 'data' => $ai_response]);
*/