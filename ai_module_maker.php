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
set_time_limit(120);

const AIMM_PATH          = '/var/www/data_private/modul_ai/pending';
const AIMM_MAX_HTML_LEN  = 200000;   // ~200 KB batas HTML modul
const AIMM_COOLDOWN      = 10;       // detik antar generate
// Kategori 'category' di bawah mengikuti verifikasi DB staging:
// SELECT DISTINCT category -> 'Network', 'System Administration'.
// 'Cloud Computing' & 'DevOps' ada di kosakata README (belum ada barisnya).
// webdev -> 'DevOps' SEMENTARA (keputusan user, belum ada kategori web dev).
const AIMM_TOPICS = [
    'network'    => ['label' => 'Network',          'category' => 'Network'],
    'sysadmin'   => ['label' => 'Sysadmin',         'category' => 'System Administration'],
    'cloud'      => ['label' => 'Cloud Computing',  'category' => 'Cloud Computing'],
    'webdev'     => ['label' => 'Web Dev',          'category' => 'DevOps'],
];
const AIMM_TINGKAT = ['X', 'XI', 'XII', 'XIII'];

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

/**
 * Sanitasi XSS ketat untuk HTML modul hasil AI:
 * - buang <script>, <iframe>, <object>, <embed>, <style> berbahaya, dll.
 * - buang semua atribut on*
 * - buang javascript:/vbscript: & entity-encoded variants di URL atribut
 */
function aimmSanitizeHtml(string $html): string {
    // 1. Hapus blok elemen berbahaya seluruhnya (case-insensitive, multiline)
    $html = preg_replace('#<\s*(script|iframe|object|embed|form|input|button|link|meta)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
    // elemen void / tanpa tag penutup
    $html = preg_replace('#<\s*(script|iframe|object|embed|form|input|button|link|meta)\b[^>]*/?\s*>#is', '', $html);

    // 2. Hapus semua atribut event handler on*
    $html = preg_replace('/\son[a-z0-9_\-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

    // 3. Netralkan skema berbahaya pada atribut URL (termasuk versi di-encode)
    $html = preg_replace_callback(
        '/(\s(?:href|src|action|xlink:href)\s*=\s*)(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
        function ($m) {
            $attr = $m[2];
            // buang kutip pembungkus
            $val = trim($attr, "\"' ");
            $decoded = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decoded = preg_replace('/[\x00-\x20\x7F]+/', '', $decoded); // buang whitespace/control: "java\tscript:"
            if (preg_match('#^(?:javascript|vbscript|data\s*:\s*text/html)#i', $decoded)) {
                return $m[1] . '"#"';
            }
            return $m[1] . $attr;
        },
        $html
    );

    // 4. Buang komentar kondisional IE / komentar HTML yang bisa menyembunyikan markup
    $html = preg_replace('#<!--.*?-->#s', '', $html);

    return trim($html);
}

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

    $prompt = <<<TXT
Kamu adalah guru SMK jurusan TKJ/SIJA yang menyusun modul pembelajaran dalam Bahasa Indonesia.
Buat modul pembelajaran lengkap untuk topik: {$topicLabel}, untuk siswa kelas {$tingkat}.

Aturan WAJIB:
- Kembalikan JSON murni dengan struktur: {"title": "...", "html": "..."}.
- "html" berisi SATU dokumen HTML lengkap (dari <html> sampai </html>) yang bisa dirender standalone.
- Isi modul minimal memuat: judul, tujuan pembelajaran, penjelasan materi, contoh penerapan/perintah praktik, latihan/evaluasi singkat, dan rangkuman.
- KERASLARANG menyertakan: tag <script>, atribut event (onclick, onerror, dll.), iframe, form, javascript: URL, atau konten PHP.
- CSS inline via tag <style> dan class Bootstrap 5 boleh digunakan (CDN Bootstrap SUDAH tersedia di halaman preview).
- Bahasa santai edukatif yang cocok untuk siswa SMK.
TXT;

    try {
        $result = geminiJson($apiKey, $model, $prompt, $schema, 90, 0.7);
    } catch (Throwable $e) {
        error_log('[ai_module_maker] generate: ' . $e->getMessage());
        jsonOut(['status' => 'error', 'message' => 'AI gagal membuat modul (kuota habis atau koneksi bermasalah).'], 500);
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
        jsonOut(['status' => 'error', 'message' => 'Draft kedaluwarsa. Silakan generate ulang.'], 410);
    }

    $title    = $draft['title'];
    $category = $draft['category'];
    $tingkat  = $draft['tingkat'];
    $html     = aimmSanitizeHtml((string)$draft['html']);

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
        jsonOut(['status' => 'error', 'message' => 'Gagal menyiapkan folder penyimpanan modul.'], 500);
    }

    $filename = 'ai_' . aimmSlugify($title) . '_' . time() . '.html';
    $fullPath = rtrim(AIMM_PATH, '/') . '/' . $filename;

    if (file_put_contents($fullPath, $html, LOCK_EX) === false) {
        error_log('[ai_module_maker] submit: gagal menulis file ' . $fullPath);
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
        jsonOut(['status' => 'error', 'message' => 'Gagal menyimpan modul ke database.'], 500);
    }

    // Draft sudah terpakai — hapus agar tidak bisa di-submit dua kali
    unset($_SESSION['aimm_draft']);
    session_write_close();

    jsonOut([
        'status'    => 'success',
        'message'   => 'Modul tersimpan dengan status pending. Menunggu approval.',
        'module_id' => $moduleId,
        'file'      => $filename,
    ]);
}

jsonOut(['status' => 'error', 'message' => 'Action tidak dikenal. Gunakan generate atau submit.'], 400);
