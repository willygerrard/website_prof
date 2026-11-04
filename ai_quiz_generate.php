<?php
/**
 * ai_quiz_generate.php — AI Quiz Maker (KHUSUS ADMIN)
 */
require_once 'session.php';
require_once 'csrf_helper.php';
require 'koneksi.php';

ini_set('display_errors', 0); // Biar warning PHP gak ngerusak JSON

checkLogin();
checkRole(['admin']);

header('Content-Type: application/json; charset=utf-8');
set_time_limit(120);

const AIQ_MAX_SOAL     = 30;
const AIQ_GEN_TIMEOUT  = 45;  
const AIQ_VERIFY_TIMEOUT = 20;
const AIQ_COOLDOWN     = 8;   

function jsonOut(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function aiqEnvFiles(): array {
    $staging = (getenv('APP_ENV') === 'staging')
        || (strpos($_SERVER['HTTP_HOST'] ?? '', 'staging') !== false);
    $files = $staging ? ['.env.staging', '.env'] : ['.env', '.env.staging'];
    return array_map(fn($f) => __DIR__ . '/' . $f, $files);
}

function aiqEnvInfo(string $k): array {
    foreach ([getenv($k), $_ENV[$k] ?? null, $_SERVER[$k] ?? null] as $v) {
        if (is_string($v) && $v !== '') return [$v, 'environment'];
    }
    foreach (aiqEnvFiles() as $f) {
        if (!is_readable($f)) continue;
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            if (strpos($line, 'export ') === 0) $line = substr($line, 7);
            [$n, $val] = explode('=', $line, 2);
            if (trim($n) === $k) {
                $val = trim($val, " \t\n\r\0\x0B\"'");
                if ($val !== '') return [$val, basename($f)];
            }
        }
    }
    return ['', ''];
}

function aiqEnv(string $k): string { return aiqEnvInfo($k)[0]; }

function geminiJson(string $key, string $model, string $text, array $schema, int $timeout, float $temp): array {
    $payload = [
        'contents' => [['parts' => [['text' => $text]]]],
        'generationConfig' => [
            'response_mime_type' => 'application/json',
            'response_schema'    => $schema,
            'temperature'        => $temp,
        ],
    ];
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

    for ($try = 1; $try <= 2; $try++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if (!$err && $code === 200) break;
        if ($try < 2 && in_array($code, [429, 503], true)) { sleep(2); continue; }
        throw new RuntimeException($err ?: "HTTP $code: " . mb_substr((string)$resp, 0, 300));
    }

    $data = json_decode((string)$resp, true);
    $txt  = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    $out  = $txt ? json_decode($txt, true) : null;
    if (!is_array($out)) throw new RuntimeException('Respons AI bukan JSON yang valid');
    return $out;
}

// ---------------------------------------------------------------- validasi request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['status' => 'error', 'message' => 'Metode tidak valid.'], 405);
}
csrf_require_valid_post();

$now = time();
if ($now - (int)($_SESSION['aiq_last'] ?? 0) < AIQ_COOLDOWN) {
    jsonOut(['status' => 'error', 'message' => 'Tunggu beberapa detik sebelum generate lagi.'], 429);
}
$_SESSION['aiq_last'] = $now;
session_write_close();

$prompt      = mb_substr(trim($_POST['prompt'] ?? ''), 0, 500);
$sumber      = mb_substr(trim($_POST['sumber'] ?? ''), 0, 12000);
$modulId     = (int)($_POST['modul_id'] ?? 0);
$kategori    = mb_substr(trim($_POST['kategori'] ?? ''), 0, 100);
$materi      = mb_substr(trim($_POST['materi'] ?? ''), 0, 150);
$level       = in_array($_POST['level'] ?? '', ['pemula', 'menengah', 'mahir'], true) ? $_POST['level'] : 'pemula';
$verify      = ($_POST['verify'] ?? '0') === '1';
$jenis_soal  = ($_POST['jenis_soal'] ?? '') === 'isian' ? 'isian' : 'pilgan';

if (mb_strlen($sumber) < 100 && $modulId === 0) {
    jsonOut(['status' => 'error', 'message' => 'Pilih modul dari dropdown, atau tempel isi materi modul (minimal 100 karakter).'], 400);
}

$n = preg_match('/(\d{1,3})\s*soal/i', $prompt, $m) ? (int)$m[1] : 10;
$n = max(1, min(AIQ_MAX_SOAL, $n));

$apiKey = aiqEnv('GEMINI_API_KEY');
if ($apiKey === '') jsonOut(['status' => 'error', 'message' => 'GEMINI_API_KEY tidak terbaca oleh PHP.'], 500);
$model = aiqEnv('GEMINI_QUIZ_MODEL') ?: 'gemini-1.5-flash';

// Konteks modul dari database
$modulInfo = '';
if ($modulId > 0) {
    $st = $pdo->prepare('SELECT title, category, description FROM modules WHERE id = ?');
    $st->execute([$modulId]);
    if ($mod = $st->fetch(PDO::FETCH_ASSOC)) {
        $modulInfo = "Judul modul: {$mod['title']}\nKategori modul: {$mod['category']}\nDeskripsi modul: {$mod['description']}";
    }
}

// ---------------------------------------------------------------- prompt generator
$konteks = [];
if ($kategori !== '') $konteks[] = "Kategori: {$kategori}";
if ($materi !== '' && $materi !== '__baru__') $konteks[] = "Materi: {$materi}";
if ($modulInfo !== '')  $konteks[] = $modulInfo;
if ($prompt !== '')     $konteks[] = "Permintaan guru: {$prompt}";
$konteksStr = implode("\n", $konteks);

// Tentukan aturan sumber & required fields dasar
if (mb_strlen($sumber) >= 100) {
    $aturanSumber = "SUMBER MATERI (satu-satunya dasar soal):\n\"\"\"\n{$sumber}\n\"\"\"";
    $baseRequired = ['pertanyaan', 'jawaban_benar', 'penjelasan', 'bukti'];
} else {
    $aturanSumber = "SUMBER MATERI:\nKarena teks lengkap modul tidak tersedia, buat soal berdasarkan Judul Modul, Deskripsi, dan Permintaan Guru di atas. Gunakan pengetahuan umummu tentang topik ini.";
    $baseRequired = ['pertanyaan', 'jawaban_benar', 'penjelasan'];
}

// BLOK PENENTUAN JENIS SOAL (INI KUNCI PERUBAHANNYA)
if ($jenis_soal === 'isian') {
    $genPrompt = <<<TXT
Kamu adalah guru SMK jurusan TKJ/SIJA yang menyusun soal ujian.
Buat TEPAT {$n} soal ISIAN PENDEK dalam Bahasa Indonesia.

Aturan:
- Jawaban harus singkat: 1 sampai 3 kata (misal: "mkdir", "IP address", "VLSM").
- "jawaban_benar" adalah kunci jawaban utamanya.
- "alternatif" diisi ARRAY (daftar) kata/kalimat lain yang juga dianggap benar (ejaan lain, singkatan, sinonim).
- "penjelasan": 1-2 kalimat alasan kenapa jawaban itu benar.
- Tingkat kesulitan: {$level}.

{$konteksStr}

{$aturanSumber}
TXT;

    $schemaSoal = [
        'type' => 'ARRAY',
        'items' => [
            'type' => 'OBJECT',
            'properties' => [
                'pertanyaan'    => ['type' => 'STRING'],
                'jawaban_benar' => ['type' => 'STRING'],
                'alternatif'    => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'penjelasan'    => ['type' => 'STRING'],
                'bukti'         => ['type' => 'STRING'],
            ],
            'required' => $baseRequired, // TIDAK ADA 'opsi' DI SINI
        ],
    ];
} else {
    $genPrompt = <<<TXT
Kamu adalah guru SMK jurusan TKJ/SIJA yang menyusun soal ujian berkualitas dalam Bahasa Indonesia.
Buat TEPAT {$n} soal pilihan ganda.

Aturan:
- Tepat 4 opsi per soal dan hanya 1 yang benar. Pengecoh harus masuk akal.
- Dilarang opsi seperti "semua benar", "semua salah", atau "A dan B benar".
- Teks opsi TANPA awalan huruf (jangan tulis "A)", "B." dan sejenisnya).
- "jawaban_benar" diisi PERSIS sama dengan salah satu teks opsi.
- "penjelasan": 1-3 kalimat alasan jawaban benar.
- "bukti": salin PERSIS satu kutipan 8-40 kata dari SUMBER MATERI (jika materi tersedia).
- Tingkat kesulitan: {$level}.

{$konteksStr}

{$aturanSumber}
TXT;

    $schemaSoal = [
        'type' => 'ARRAY',
        'items' => [
            'type' => 'OBJECT',
            'properties' => [
                'pertanyaan'    => ['type' => 'STRING'],
                'opsi'          => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'jawaban_benar' => ['type' => 'STRING'],
                'penjelasan'    => ['type' => 'STRING'],
                'bukti'         => ['type' => 'STRING'],
            ],
            'required' => array_merge($baseRequired, ['opsi']), // WAJIB ADA 'opsi' UNTUK PILGAN
        ],
    ];
}

$t0 = microtime(true);
try {
    $raw = geminiJson($apiKey, $model, $genPrompt, $schemaSoal, AIQ_GEN_TIMEOUT, 0.7);
} catch (Throwable $e) {
    error_log('[ai_quiz_generate] ' . $e->getMessage());
    jsonOut(['status' => 'error', 'message' => 'AI gagal membuat soal (kuota habis atau koneksi bermasalah).'], 500);
}

// ---------------------------------------------------------------- validasi & normalisasi hasil
$norm = fn(string $s): string => preg_replace('/[^a-z0-9]+/', '', mb_strtolower($s));

$existing = [];
if ($kategori !== '') {
    $st = $pdo->prepare('SELECT pertanyaan FROM kuis_soal WHERE kategori = ? LIMIT 3000');
    $st->execute([$kategori]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $p) $existing[$norm((string)$p)] = true;
}

$srcNorm = $norm($sumber);
$soal = [];
$seen = [];
$dropped = 0;
$tanpaBukti = 0;

foreach ($raw as $r) {
    if (!is_array($r)) { $dropped++; continue; }
    $q = trim((string)($r['pertanyaan'] ?? ''));
    $bukti = trim((string)($r['bukti'] ?? ''));

    // Validasi Bukti (berlaku untuk pilgan & isian)
    if (mb_strlen($sumber) >= 100) {
        $nb = $norm($bukti);
        if (strlen($nb) < 25 || strpos($srcNorm, $nb) === false) {
            $tanpaBukti++; continue;
        }
    } else {
        $bukti = "Berdasarkan deskripsi modul / pengetahuan umum.";
    }

    // ==========================================
    // VALIDASI ISIAN PENDEK
    // ==========================================
    if ($jenis_soal === 'isian') {
        $key = trim((string)($r['jawaban_benar'] ?? ''));
        $alts = array_map('trim', (array)($r['alternatif'] ?? []));
        
        if ($q === '' || $key === '' || isset($seen[$norm($q)])) {
            $dropped++; continue;
        }
        
        $seen[$norm($q)] = true;
        $soal[] = [
            'question_text'  => $q,
            'options'        => [], // Opsi sengaja dikosongkan
            'correct_answer' => $key,
            'alternatif'     => array_values(array_unique(array_filter($alts))),
            'materi'         => '',
            'penjelasan'     => trim((string)($r['penjelasan'] ?? '')),
            'bukti'          => $bukti,
            'cek'            => null,
            'duplikat'       => false,
        ];
        continue; // Lanjut ke soal berikutnya
    }

    // ==========================================
    // VALIDASI PILIHAN GANDA
    // ==========================================
    $opts = [];
    foreach ((array)($r['opsi'] ?? []) as $o) {
        $o = trim(preg_replace('/^[A-Da-d][\)\.]\s+/', '', trim((string)$o)));
        if ($o !== '') $opts[] = $o;
    }
    $key = trim((string)($r['jawaban_benar'] ?? ''));
    if (preg_match('/^[A-Da-d]$/', $key) && isset($opts[ord(strtoupper($key)) - 65])) {
        $key = $opts[ord(strtoupper($key)) - 65];
    }

    $keyIdx = null;
    foreach ($opts as $i => $o) {
        if (mb_strtolower($o) === mb_strtolower($key)) { $keyIdx = $i; break; }
    }
    $lower = array_map('mb_strtolower', $opts);
    $nq = $norm($q);

    if ($q === '' || count($opts) !== 4 || count(array_unique($lower)) !== 4
        || $keyIdx === null || isset($seen[$nq])) {
        $dropped++;
        continue;
    }
    
    $seen[$nq] = true;

    $correct = $opts[$keyIdx];
    shuffle($opts);

    $soal[] = [
        'question_text'  => $q,
        'options'        => $opts,
        'correct_answer' => $correct,
        'materi'         => '',
        'penjelasan'     => trim((string)($r['penjelasan'] ?? '')),
        'bukti'          => $bukti,
        'cek'            => null,
        'duplikat'       => isset($existing[$nq]),
    ];
}
$soal = array_slice($soal, 0, $n);

// ---------------------------------------------------------------- verifikasi buta (Hanya Pilgan)
$verified = false;
if ($verify && $jenis_soal === 'pilgan' && $soal && (microtime(true) - $t0) < 55) {
    try {
        $items = [];
        foreach ($soal as $i => $s) {
            $items[] = ['i' => $i, 'pertanyaan' => $s['question_text'], 'opsi' => $s['options']];
        }
        $schemaVerify = [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => ['i' => ['type' => 'INTEGER'], 'pilihan' => ['type' => 'INTEGER']],
                'required' => ['i', 'pilihan'],
            ],
        ];
        
        $verPrompt = "Kerjakan setiap soal pilihan ganda berikut secara mandiri dan teliti. "
                   . "Jawab dengan nomor urut opsi yang benar (0 sampai 3) untuk setiap 'i'.\n\n";
        if (mb_strlen($sumber) >= 100) {
            $verPrompt .= "SUMBER:\n\"\"\"\n{$sumber}\n\"\"\"\n\n";
        }
        $verPrompt .= "SOAL:\n" . json_encode($items, JSON_UNESCAPED_UNICODE);

        $hasil = geminiJson($apiKey, $model, $verPrompt, $schemaVerify, AIQ_VERIFY_TIMEOUT, 0.0);
        foreach ($hasil as $row) {
            $i = (int)($row['i'] ?? -1);
            if (!isset($soal[$i])) continue;
            $pick = (int)($row['pilihan'] ?? -1);
            $soal[$i]['cek'] = (($soal[$i]['options'][$pick] ?? null) !== $soal[$i]['correct_answer']);
        }
        $verified = true;
    } catch (Throwable $e) {
        error_log('[ai_quiz_generate] verifikasi gagal: ' . $e->getMessage());
    }
}

jsonOut([
    'status'    => 'success',
    'questions' => $soal,
    'meta'      => [
        'diminta'      => $n,
        'diterima'     => count($soal),
        'dibuang'      => $dropped,
        'tanpa_bukti'  => $tanpaBukti,
        'diverifikasi' => $verified,
        'model'        => $model,
    ],
]);