<?php
/**
 * ai_module_maker.php — AI Module Maker (KHUSUS ADMIN, backend handler AJAX)
 *
 * Fase 3: handler generate & submit. UI HTML menyusul (Fase 4).
 *
 * action=generate : validasi topik/tingkat -> Gemini API -> simpan draft
 *                   {title, html} ke $_SESSION['aimm_draft']
 * action=submit   : ambil draft dari session -> sanitasi XSS -> tulis file
 *                   ke /var/www/data_private/modul_ai/pending/ -> INSERT
 *                   modules (status='pending', file_path=NULL)
 */

require_once 'session.php';
require_once 'csrf_helper.php';
require_once 'ai_helper.php';
require 'koneksi.php';

ini_set('display_errors', 0); // Warning PHP tidak boleh merusak JSON

checkLogin();
checkRole(['admin']);

header('Content-Type: application/json; charset=utf-8');
set_time_limit(100); // Cloudflared memutus request ~100 detik

const AIMM_PATH          = '/var/www/data_private/modul_ai/pending';
const AIMM_MAX_HTML_LEN  = 200000;   // ~200 KB batas HTML modul
const AIMM_COOLDOWN      = 10;       // detik antar generate
// AIMM_TOPICS dan AIMM_TINGKAT didefinisikan di ai_helper.php agar bisa
// dipakai bersama oleh handler ini dan halaman UI (Fase 4b-2).

function jsonOut(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function aimmSlugify(string $s): string {
    $s = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($s)));
    $s = trim($s, '-');
    return $s !== '' ? substr($s, 0, 60) : 'modul';
}

// Sanitasi HTML memakai aimmSanitizeHtml() dari ai_helper.php
// (allowlist DOMDocument). Pengecekan akhir tetap dilakukan di handler submit.

// ============================================================
// ROUTER AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    jsonOut(['status' => 'error', 'message' => 'Request tidak valid.'], 400);
}

$action = $_POST['action'];

// ------------------------------------------------------------
// HANDLER: action=generate
// ------------------------------------------------------------
if ($action === 'generate') {
    csrf_require_valid_post();

    $topic  = trim($_POST['topic'] ?? '');
    $tingkat = trim($_POST['tingkat'] ?? 'X');

    // Whitelist ketat topik — berasal dari konstanta server, bukan input bebas
    if (!isset(AIMM_TOPICS[$topic])) {
        jsonOut(['status' => 'error', 'message' => 'Topik tidak valid. Pilih: network, sysadmin, cloud, atau webdev.'], 400);
    }
    if (!in_array($tingkat, AIMM_TINGKAT, true)) {
        jsonOut(['status' => 'error', 'message' => 'Tingkat kelas tidak valid.'], 400);
    }

    // Cooldown per-session (hemat kuota API / anti spam) —
    // dihitung SETELAH validasi topik/tingkat lolos
    $now = time();
    if ($now - (int)($_SESSION['aimm_last'] ?? 0) < AIMM_COOLDOWN) {
        jsonOut(['status' => 'error', 'message' => 'Tunggu beberapa detik sebelum generate lagi.'], 429);
    }
    $_SESSION['aimm_last'] = $now;
    session_write_close();

    $topicLabel    = AIMM_TOPICS[$topic]['label'];
    $topicCategory = AIMM_TOPICS[$topic]['category'];

    $apiKey = aiqEnv('GEMINI_API_KEY');
    if ($apiKey === '') {
        jsonOut(['status' => 'error', 'message' => 'GEMINI_API_KEY tidak terbaca oleh PHP.'], 500);
    }
    $model = aiqEnv('GEMINI_MODULE_MODEL') ?: 'gemini-3.5-flash';

    $schema = [
        'type' => 'OBJECT',
        'properties' => [
            'title' => ['type' => 'STRING'],
            'html'  => ['type' => 'STRING'],
        ],
        'required' => ['title', 'html'],
    ];

    // Jika tingkat = 'semua', tampilkan label yang lebih deskriptif di prompt AI
    $tingkatLabel = ($tingkat === 'semua')
        ? 'siswa SMK kelas X sampai XII'
        : "siswa kelas {$tingkat}";

    $prompt = <<<TXT
Kamu adalah guru SMK jurusan TKJ/SIJA yang menyusun modul pembelajaran dalam Bahasa Indonesia.
Buat modul pembelajaran lengkap untuk topik: {$topicLabel}, untuk {$tingkatLabel}.

Aturan WAJIB:
- Kembalikan JSON murni dengan struktur: {"title": "...", "html": "..."}.
- "html" berisi FRAGMEN isi body saja: TANPA <html>, <head>, <body>, <style>, <script>, tanpa doctype.
- Gunakan HANYA class Bootstrap 5 (mis. container, card, table, alert, badge) — CSS dimuat oleh pembungkus di sisi server, jadi jangan tulis CSS sendiri.
- Isi modul minimal memuat: judul, tujuan pembelajaran, penjelasan materi, contoh penerapan/perintah praktik, latihan/evaluasi singkat, dan rangkuman.
- KERASLARANG menyertakan: tag <script>, atribut event (onclick, onerror, dll.), iframe, form, javascript: URL, atau konten PHP.
- Bahasa santai edukatif yang cocok untuk siswa SMK.
TXT;

    try {
        // timeout 80 + maxTries 1 + maxOutputTokens: total < batas Cloudflared ~100s
        $result = geminiJson($apiKey, $model, $prompt, $schema, 80, 0.7, 24000, 1, 'aimm');
    } catch (Throwable $e) {
        error_log('[ai_module_maker] generate: ' . $e->getMessage());
        $msg = (strpos($e->getMessage(), 'terpotong') !== false)
            ? $e->getMessage()
            : 'AI gagal membuat modul (kuota habis atau koneksi bermasalah).';
        jsonOut(['status' => 'error', 'message' => $msg], 500);
    }

    $title = mb_substr(trim((string)($result['title'] ?? '')), 0, 150);
    $html  = (string)($result['html'] ?? '');

    if ($title === '' || trim($html) === '') {
        jsonOut(['status' => 'error', 'message' => 'Respons AI tidak lengkap (title/html kosong).'], 502);
    }
    if (strlen($html) > AIMM_MAX_HTML_LEN) {
        jsonOut(['status' => 'error', 'message' => 'Modul hasil AI terlalu besar. Coba generate ulang.'], 502);
    }

    // Session ditutup selama panggilan API — buka kembali untuk menyimpan
    // draft. $_SESSION['aimm_last'] tetap terbaca karena tersimpan di storage.
    session_start();
    $_SESSION['aimm_draft'] = [
        'title'    => $title,
        'html'     => $html,
        'topic'    => $topic,
        'category' => $topicCategory,
        'tingkat'  => $tingkat,
        'created'  => time(),
    ];
    session_write_close();

    jsonOut([
        'status' => 'success',
        'draft'  => [
            'title'   => $title,
            'topic'   => $topic,
            'tingkat' => $tingkat,
            // HTML tidak dikirim ke klien untuk disimpan kembali —
            // preview dirender oleh endpoint terpisah (Fase 4)
        ],
    ]);
}

// ------------------------------------------------------------
// HANDLER: action=submit
// ------------------------------------------------------------
if ($action === 'submit') {
    csrf_require_valid_post();

    $draft = $_SESSION['aimm_draft'] ?? null;
    if (!is_array($draft) || empty($draft['html']) || empty($draft['title'])) {
        jsonOut(['status' => 'error', 'message' => 'Tidak ada draft modul. Generate dulu sebelum submit.'], 400);
    }

    // Draft kedaluwarsa (1 jam) — paksa generate ulang
    if (time() - (int)($draft['created'] ?? 0) > 3600) {
        unset($_SESSION['aimm_draft']);
        session_write_close();
        jsonOut(['status' => 'error', 'message' => 'Draft kedaluwarsa. Silakan generate ulang.'], 410);
    }

    // Kunci draft SEKARANG (unset + tutup session) sebelum operasi lambat
    // (tulis file, INSERT DB) — mencegah submit ganda paralel.
    // Jika salah satu gagal, draft dikembalikan ke session agar admin
    // bisa mencoba submit ulang.
    unset($_SESSION['aimm_draft']);
    session_write_close();

    $restoreDraft = function () use ($draft) {
        session_start();
        $_SESSION['aimm_draft'] = $draft;
        session_write_close();
    };

    $title    = $draft['title'];
    $category = $draft['category'];
    $tingkat  = $draft['tingkat'];

    // Urutan: sanitasi fragmen -> bungkus dokumen -> cek panjang -> tulis file.
    // Draft di session tetap menyimpan fragmen mentah (belum tersentuh).
    $bodyHtml = aimmSanitizeHtml((string)$draft['html']);
    $html     = aimmWrapHtml($title, $bodyHtml);

    // Validasi pasca-sanitasi
    if (strlen($html) < 100) {
        jsonOut(['status' => 'error', 'message' => 'Konten modul terlalu pendek setelah sanitasi. Generate ulang.'], 422);
    }
    if (stripos($html, '<script') !== false || stripos($html, 'javascript:') !== false) {
        error_log('[ai_module_maker] submit: payload berbahaya lolos sanitasi, ditolak.');
        jsonOut(['status' => 'error', 'message' => 'Konten modul mengandung skrip berbahaya. Ditolak.'], 422);
    }

    // Pastikan folder penyimpanan ada
    if (!is_dir(AIMM_PATH) && !mkdir(AIMM_PATH, 0775, true) && !is_dir(AIMM_PATH)) {
        error_log('[ai_module_maker] submit: gagal mkdir ' . AIMM_PATH);
        $restoreDraft();
        jsonOut(['status' => 'error', 'message' => 'Gagal menyiapkan folder penyimpanan modul.'], 500);
    }

    $filename = 'ai_' . aimmSlugify($title) . '_' . time() . '.html';
    $fullPath = rtrim(AIMM_PATH, '/') . '/' . $filename;

    if (file_put_contents($fullPath, $html, LOCK_EX) === false) {
        error_log('[ai_module_maker] submit: gagal menulis file ' . $fullPath);
        $restoreDraft();
        jsonOut(['status' => 'error', 'message' => 'Gagal menyimpan file modul ke disk.'], 500);
    }
    @chmod($fullPath, 0664);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO modules (title, category, description, image_path, ai_file, file_path, jenis_resource, kelas_target, status)
             VALUES (:title, :category, :description, :image_path, :ai_file, :file_path, :jenis, :kelas_target, :status)"
        );
        $stmt->execute([
            ':title'       => $title,
            ':category'    => $category,
            ':description' => 'Modul pembelajaran AI — topik ' . $category . ' (kelas ' . $tingkat . ')',
            ':image_path'  => 'img/debian.webp',
            ':ai_file'     => $filename,
            ':file_path'   => null,
            ':jenis'       => 'modul',
            ':kelas_target'=> $tingkat,
            ':status'      => 'pending',
        ]);
        $moduleId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('[ai_module_maker] submit: DB error: ' . $e->getMessage());
        @unlink($fullPath); // jangan sisakan file yatim jika DB gagal
        $restoreDraft();
        jsonOut(['status' => 'error', 'message' => 'Gagal menyimpan modul ke database.'], 500);
    }

    jsonOut([
        'status'    => 'success',
        'message'   => 'Modul tersimpan dengan status pending. Menunggu approval.',
        'module_id' => $moduleId,
        'file'      => $filename,
    ]);
}

jsonOut(['status' => 'error', 'message' => 'Action tidak dikenal. Gunakan generate atau submit.'], 400);
