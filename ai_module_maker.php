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

// Halaman UI testing (GET) memakai Content-Type HTML, bukan JSON.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
}

const AIMM_PATH          = '/var/www/data_private/modul_ai/pending';
const AIMM_MAX_HTML_LEN  = 200000;   // ~200 KB batas HTML modul
const AIMM_COOLDOWN      = 10;       // detik antar generate
const AIMM_MIN_TEXT_LEN  = 1500;     // teks terlihat minimum setelah sanitasi
const AIMM_MIN_HEADINGS  = 3;        // minimal h1-h4 setelah sanitasi
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
// GET: hanya menampilkan UI testing (tanpa menjalankan handler apa pun).
// Handler generate/submit tetap WAJIB POST + CSRF.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>AI Module Maker — UI Test</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="bg-light">
        <div class="container py-4" style="max-width: 900px;">
            <div class="alert alert-warning small">
                <strong>UI testing saja.</strong> Form ini memanggil handler <code>action=generate</code>.
                Submit modul tidak tersedia di halaman ini.
            </div>

            <div class="card shadow-sm border-0 rounded-3 mb-3">
                <div class="card-header bg-dark text-white py-3 fw-bold">Generate Draft Modul</div>
                <div class="card-body p-4">
                    <form id="aimmTestForm" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold" for="aimmTopic">Topik</label>
                            <select class="form-select" id="aimmTopic" name="topic" required>
                                <?php foreach (AIMM_TOPICS as $key => $t): ?>
                                    <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="aimmTingkat">Tingkat</label>
                            <select class="form-select" id="aimmTingkat" name="tingkat" required>
                                <?php foreach (AIMM_TINGKAT as $tk): ?>
                                    <option value="<?= htmlspecialchars($tk, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= $tk === 'semua' ? 'Semua (X–XII)' : 'Kelas ' . htmlspecialchars($tk, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-grid align-self-end">
                            <button type="submit" class="btn btn-primary fw-bold" id="aimmGenBtn">Generate</button>
                        </div>
                    </form>
                    <div class="form-text mt-2">Generate memanggil Gemini (kuota + cooldown 10 detik).</div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-secondary text-white py-2 fw-bold">Hasil / Error</div>
                <div class="card-body p-0">
                    <pre id="aimmOut" class="m-0 p-3 small" style="min-height: 120px; max-height: 60vh; overflow: auto;">Belum ada hasil.</pre>
                </div>
            </div>
        </div>

        <script>
        (function () {
            'use strict';
            const form = document.getElementById('aimmTestForm');
            const out  = document.getElementById('aimmOut');
            const btn  = document.getElementById('aimmGenBtn');

            function esc(s) {
                return String(s ?? '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                }[c]));
            }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                btn.disabled = true;
                out.textContent = 'Meminta generate ke Gemini... bisa sampai ~100 detik.';

                const fd = new FormData(form);
                fd.set('action', 'generate');

                const t0 = Date.now();
                try {
                    const res  = await fetch(window.location.pathname, {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    });
                    const text = await res.text();
                    const secs = ((Date.now() - t0) / 1000).toFixed(1);

                    let data;
                    try { data = JSON.parse(text); }
                    catch (err) { data = { _non_json: text }; }

                    const head = 'HTTP ' + res.status + '  (' + secs + 's)';

                    // Tampilkan seluruh JSON untuk diagnostik
                    let body = JSON.stringify(data, null, 2);

                    // Jika draft punya html, tampilkan ringkasan panjang
                    if (data && data.status === 'success' && data.draft) {
                        body += '\n\n--- draft ---\n'
                             + 'title: ' + (data.draft.title || '')
                             + '\ntopic: ' + (data.draft.topic || '')
                             + '\ntingkat: ' + (data.draft.tingkat || '');
                    }

                    out.textContent = head + '\n\n' + body;
                } catch (err) {
                    out.textContent = 'Fetch gagal: ' + (err && err.message ? err.message : err);
                } finally {
                    btn.disabled = false;
                }
            });
        })();
        </script>
        </body>
        </html>
        <?php
        exit;
    }

    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_POST['action'])) {
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

    // Validasi kelengkapan hasil AI SEBELUM draft disimpan.
    // Pengecekan memakai hasil sanitasi (draft yang disimpan tetap fragmen mentah).
    $sanitized   = aimmSanitizeHtml($html);
    $visibleText = trim(strip_tags($sanitized));
    $textLen     = mb_strlen($visibleText);
    $headingCount = preg_match_all('/<h[1-4]\b/i', $sanitized);
    $paraCount    = preg_match_all('/<p\b/i', $sanitized);
    $hangingTag   = (bool)preg_match('/<[^>]*$/', rtrim($html));

    $alasan = [];
    if ($textLen < AIMM_MIN_TEXT_LEN) {
        $alasan[] = "teks {$textLen} < " . AIMM_MIN_TEXT_LEN;
    }
    if ($headingCount < AIMM_MIN_HEADINGS) {
        $alasan[] = "heading {$headingCount} < " . AIMM_MIN_HEADINGS;
    }
    if ($paraCount < 1) {
        $alasan[] = 'tidak ada <p>';
    }
    if ($hangingTag) {
        $alasan[] = 'tag menggantung di akhir fragmen';
    }

    if ($alasan) {
        error_log(sprintf(
            '[ai_module_maker] generate: hasil tidak lolos validasi (%s) rawLen=%d sanitizedLen=%d textLen=%d',
            implode('; ', $alasan),
            strlen($html),
            strlen($sanitized),
            $textLen
        ));
        jsonOut(['status' => 'error', 'message' => 'Hasil AI terlalu pendek atau tidak lengkap, coba generate ulang.'], 502);
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
