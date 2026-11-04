<?php
/**
 * ai_module_maker.php — AI Module Maker (KHUSUS ADMIN).
 *
 * GET  : halaman UI (form kategori + topik spesifik + tingkat, preview, submit).
 * POST : handler AJAX (wajib CSRF).
 *   action=generate : validasi input -> Gemini (validasi semantik + buat modul)
 *                     -> validasi kelengkapan -> simpan draft di session.
 *   action=submit   : ambil draft session -> sanitasi -> bungkus -> tulis file
 *                     ke /var/www/data_private/modul_ai/pending/ -> INSERT
 *                     modules (status='pending', file_path=NULL).
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

// Halaman UI (GET) memakai Content-Type HTML, bukan JSON.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
}

const AIMM_PATH          = '/var/www/data_private/modul_ai/pending';
const AIMM_COOLDOWN      = 10;       // detik antar generate
const AIMM_MIN_TEXT_LEN  = 1500;     // teks terlihat minimum setelah sanitasi
const AIMM_MIN_HEADINGS  = 5;        // minimal heading level-2 pada Markdown mentah
const AIMM_MAX_TOPIK_LEN = 500;      // batas panjang topik spesifik

// Kategori DB yang boleh dipilih. Urutan = urutan dropdown.
// AIMM_TOPICS (label+category) tetap didefinisikan di ai_helper.php.
const AIMM_KATEGORI_DB = ['Network', 'System Administration', 'Cloud Computing', 'DevOps'];

function jsonOut(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lepas pembungkus code fence terluar (``` atau ```markdown) bila AI
 * mengembalikan seluruh modul di dalam satu blok kode.
 */
function aimmStripOuterFence(string $md): string {
    $md = trim($md);
    if (preg_match('/^```[A-Za-z0-9_-]*[ \t]*\r?\n([\s\S]*?)\r?\n```$/', $md, $m)) {
        return trim($m[1]);
    }
    return $md;
}

function aimmSlugify(string $s): string {
    $s = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($s)));
    $s = trim($s, '-');
    return $s !== '' ? substr($s, 0, 60) : 'modul';
}

/**
 * Cari key AIMM_TOPICS berdasarkan kategori DB. Dipakai agar draft tetap
 * menyimpan 'topic' internal seperti sebelumnya.
 */
function aimmTopicKeyByCategory(string $category): string {
    foreach (AIMM_TOPICS as $key => $t) {
        if ($t['category'] === $category) {
            return $key;
        }
    }
    return 'lainnya';
}

// Sanitasi HTML memakai aimmSanitizeHtml() dari ai_helper.php
// (allowlist DOMDocument). Pengecekan akhir tetap dilakukan di handler submit.

// ============================================================
// ROUTER AJAX
// ============================================================
// GET: halaman UI AI Module Maker (preview & submit).
// Handler generate/submit tetap WAJIB POST + CSRF.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $csrf = csrf_token();

        $topbar_page = 'AI Module Maker';

        $html_lang  = 'id';
        $page_title = 'AI Module Maker — Pusat Pembelajaran SIJA';
        $body_class = 'bg-light';
        $extra_head = '<link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
            <style>
                #aimmPreviewFrame { width: 100%; height: 70vh; min-height: 420px; border: 0; background: #fff; }
            </style>';

        include __DIR__ . '/includes/head.php';
        ?>

        <!-- Bungkus dengan shell untuk sidebar -->
        <div class="sj-shell flex-grow-1">

            <!-- Include Sidebar -->
            <?php include __DIR__ . '/includes/sidebar.php'; ?>

            <!-- Konten Utama -->
            <main class="sj-main bg-light pb-5">

                <?php include __DIR__ . '/includes/topbar.php'; ?>

                <div class="container-fluid px-4 pt-4 pb-5">

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="fw-bold mb-1">AI Module Maker</h4>
                    <div class="text-muted small">Buat modul dengan AI, tinjau, lalu simpan sebagai pending.</div>
                </div>
                <a href="gerbang-rahasia-sija" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Kelola Modul
                </a>
            </div>

            <!-- Pesan hasil generate/submit -->
            <div id="aimmAlert" class="alert d-none" role="alert"></div>

            <!-- ===================== FORM GENERATE ===================== -->
            <div class="card shadow-sm border-0 rounded-3 mb-4" id="aimmFormCard">
                <div class="card-header bg-primary text-white py-3 fw-bold">
                    <i class="bi bi-stars"></i> Generate Draft Modul
                </div>
                <div class="card-body p-4">
                    <form id="aimmForm" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold" for="aimmKategori">Kategori (Database)</label>
                                <select class="form-select" id="aimmKategori" name="kategori_db" required>
                                    <?php foreach (AIMM_KATEGORI_DB as $kat): ?>
                                        <option value="<?= htmlspecialchars($kat, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($kat, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold" for="aimmTingkat">Tingkat</label>
                                <select class="form-select" id="aimmTingkat" name="tingkat" required>
                                    <?php foreach (AIMM_TINGKAT as $tk): ?>
                                        <option value="<?= htmlspecialchars($tk, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= $tk === 'semua' ? 'Semua (X–XII)' : 'Kelas ' . htmlspecialchars($tk, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-grid align-self-end">
                                <button type="submit" class="btn btn-primary fw-bold py-2" id="aimmGenBtn">
                                    <i class="bi bi-stars"></i> Generate
                                </button>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold" for="aimmTopik">Topik Spesifik</label>
                                <textarea class="form-control" id="aimmTopik" name="topik_spesifik" rows="2"
                                          maxlength="<?= AIMM_MAX_TOPIK_LEN ?>"
                                          placeholder="Contoh: Konfigurasi VoIP Server dengan Asterisk untuk Kelas X"
                                          required></textarea>
                                <div class="form-text">
                                    Semakin spesifik, semakin baik hasil modulnya. AI akan menolak topik yang
                                    tidak relevan dengan kategori yang dipilih.
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="form-text mt-2">
                        Generate memanggil Gemini; cooldown 10 detik antar permintaan.
                    </div>
                </div>
            </div>

            <!-- ===================== PREVIEW + AKSI ===================== -->
            <div class="card shadow-sm border-0 rounded-3 d-none" id="aimmPreviewCard">
                <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-eye"></i> Preview Modul</span>
                    <span class="badge bg-light text-dark" id="aimmPreviewMeta"></span>
                </div>
                <div class="card-body p-0">
                    <!-- Preview menggunakan srcdoc dengan HTML yang sudah dibungkus theme -->
                    <iframe id="aimmPreviewFrame"
                            srcdoc=""
                            sandbox="allow-same-origin allow-forms"
                            title="Preview modul AI"
                            referrerpolicy="no-referrer"></iframe>
                </div>
                <div class="card-footer bg-white py-3">
                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary fw-semibold" id="aimmRegenBtn">
                            <i class="bi bi-arrow-clockwise"></i> Regenerate
                        </button>
                        <button type="button" class="btn btn-success fw-bold" id="aimmSubmitBtn">
                            <i class="bi bi-save"></i> Submit &amp; Simpan Modul
                        </button>
                    </div>
                </div>
            </div>

                </div>
            </main>
        </div>

        <script>
        (function () {
            'use strict';

            const form        = document.getElementById('aimmForm');
            const genBtn      = document.getElementById('aimmGenBtn');
            const formCard    = document.getElementById('aimmFormCard');
            const previewCard = document.getElementById('aimmPreviewCard');
            const frame       = document.getElementById('aimmPreviewFrame');
            const meta        = document.getElementById('aimmPreviewMeta');
            const regenBtn    = document.getElementById('aimmRegenBtn');
            const submitBtn   = document.getElementById('aimmSubmitBtn');
            const alertBox    = document.getElementById('aimmAlert');
            const csrfToken   = form.querySelector('input[name="csrf_token"]').value;
            const topikEl     = document.getElementById('aimmTopik');

            function showAlert(type, msg) {
                alertBox.className = 'alert alert-' + type;
                alertBox.textContent = msg;
                alertBox.classList.remove('d-none');
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            function hideAlert() {
                alertBox.classList.add('d-none');
                alertBox.textContent = '';
            }

            function showForm() {
                previewCard.classList.add('d-none');
                formCard.classList.remove('d-none');
                frame.srcdoc = '';
                frame.src = 'about:blank';
                hideAlert();
            }

            function showPreview() {
                formCard.classList.add('d-none');
                previewCard.classList.remove('d-none');
                previewCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            async function postAction(extra) {
                const fd = new FormData();
                fd.set('csrf_token', csrfToken);
                for (const [k, v] of Object.entries(extra)) fd.set(k, v);

                const res  = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); }
                catch (e) { throw new Error('Respons server tidak valid (HTTP ' + res.status + ')'); }
                return { ok: res.ok, status: res.status, data: data };
            }

            // ---- Generate ----
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                hideAlert();

                // Validasi sisi klien: topik spesifik wajib diisi
                const topik = topikEl.value.trim();
                if (topik === '') {
                    showAlert('danger', 'Topik Spesifik wajib diisi.');
                    topikEl.focus();
                    return;
                }

                const kategori = document.getElementById('aimmKategori').value;
                const tingkat  = document.getElementById('aimmTingkat').value;

                genBtn.disabled = true;
                const original = genBtn.innerHTML;
                genBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Membuat...';

                try {
                    const r = await postAction({
                        action: 'generate',
                        kategori_db: kategori,
                        topik_spesifik: topik,
                        tingkat: tingkat
                    });

                    // AI menolak secara semantik: status error -> alert merah, tanpa iframe
                    if (r.data.status === 'error') {
                        showAlert('danger', r.data.message || 'AI menolak permintaan ini.');
                        return;
                    }

                    if (r.ok && r.data.status === 'success' && r.data.draft) {
                        meta.textContent = r.data.draft.title + ' · ' + r.data.draft.category + ' · ' + r.data.draft.tingkat;
                        // Set wrapped HTML ke iframe via srcdoc
                        const wrappedHtml = r.data.draft.wrapped_html || '';
                        if (wrappedHtml) {
                            frame.srcdoc = wrappedHtml;
                        }
                        showPreview();
                    } else {
                        showAlert('danger', r.data.message || ('Generate gagal (HTTP ' + r.status + ').'));
                    }
                } catch (err) {
                    showAlert('danger', 'Generate gagal: ' + (err && err.message ? err.message : err));
                } finally {
                    genBtn.disabled = false;
                    genBtn.innerHTML = original;
                }
            });

            // ---- Regenerate (kembali ke form, input tetap) ----
            regenBtn.addEventListener('click', function () {
                showForm();
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });

            // ---- Submit ----
            submitBtn.addEventListener('click', async function () {
                if (!confirm('Simpan modul ini sebagai pending dan tunggu persetujuan?')) return;

                hideAlert();
                submitBtn.disabled = true;
                regenBtn.disabled = true;
                const original = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan...';

                try {
                    const r = await postAction({ action: 'submit' });

                    if (r.ok && r.data.status === 'success') {
                        showAlert('success', 'Modul berhasil disimpan sebagai Pending, menunggu persetujuan Admin.');
                        previewCard.classList.add('d-none');
                        formCard.classList.remove('d-none');
                        frame.src = 'about:blank';
                        form.reset();
                    } else {
                        showAlert('danger', r.data.message || ('Submit gagal (HTTP ' + r.status + ').'));
                    }
                } catch (err) {
                    showAlert('danger', 'Submit gagal: ' + (err && err.message ? err.message : err));
                } finally {
                    submitBtn.disabled = false;
                    regenBtn.disabled = false;
                    submitBtn.innerHTML = original;
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

    $kategoriDb     = trim($_POST['kategori_db'] ?? '');
    $topikSpesifik  = trim($_POST['topik_spesifik'] ?? '');
    $tingkat        = trim($_POST['tingkat'] ?? 'X');

    if (!in_array($kategoriDb, AIMM_KATEGORI_DB, true)) {
        jsonOut(['status' => 'error', 'message' => 'Kategori tidak valid.'], 400);
    }
    if ($topikSpesifik === '') {
        jsonOut(['status' => 'error', 'message' => 'Topik Spesifik wajib diisi.'], 400);
    }
    if (mb_strlen($topikSpesifik) > AIMM_MAX_TOPIK_LEN) {
        jsonOut(['status' => 'error', 'message' => 'Topik Spesifik terlalu panjang.'], 400);
    }
    if (!in_array($tingkat, AIMM_TINGKAT, true)) {
        jsonOut(['status' => 'error', 'message' => 'Tingkat kelas tidak valid.'], 400);
    }

    $now = time();
    if ($now - (int)($_SESSION['aimm_last'] ?? 0) < AIMM_COOLDOWN) {
        jsonOut(['status' => 'error', 'message' => 'Tunggu beberapa detik sebelum generate lagi.'], 429);
    }
    $_SESSION['aimm_last'] = $now;
    session_write_close();

    $topicKey      = aimmTopicKeyByCategory($kategoriDb);
    $topicCategory = $kategoriDb;

    $apiKey = aiqEnv('GEMINI_API_KEY');
    if ($apiKey === '') {
        jsonOut(['status' => 'error', 'message' => 'GEMINI_API_KEY tidak terbaca oleh PHP.'], 500);
    }
    $model = aiqEnv('GEMINI_MODULE_MODEL') ?: 'gemini-3.5-flash';

    $tingkatLabel = ($tingkat === 'semua')
        ? 'siswa SMK kelas X sampai XII'
        : "siswa kelas {$tingkat}";

    // PROMPT MARKDOWN MURNI
    $prompt = <<<TXT
Kamu adalah Asisten Kurikulum SMK jurusan TKJ dan SIJA. Tugasmu menyusun Modul Pembelajaran lengkap dalam format MARKDOWN.

Kategori DB: {$kategoriDb}
Topik Spesifik: {$topikSpesifik}
Tingkat Target: {$tingkat} ({$tingkatLabel})

[ATURAN PENOLAKAN SANGAT KETAT]
Jika Topik Spesifik TIDAK RELEVAN dengan Kategori "{$kategoriDb}", kamu WAJIB MENOLAK. Tulis baris pertama HANYA:
[ERROR]: Topik tidak relevan dengan kategori yang dipilih.

[ATURAN PENULISAN MODUL]
Jika topik relevan, tulis modul lengkap dalam struktur Markdown berikut:

# [Judul Modul Pembelajaran Lengkap & Menarik]

## 1. Pendahuluan & Tujuan Pembelajaran
- Konsep dasar secara ringkas & aplikatif khas anak SMK.
- Minimal 3 poin tujuan pembelajaran.

## 2. Pembahasan Materi Utama
- Penjelasan terstruktur, jelas, dan mendalam.
- Berikan contoh skenario atau penerapan di dunia industri/kerja (DUDI).

## 3. Langkah Praktik & Konfigurasi
- Berikan contoh perintah CLI / skrip / langkah praktikum konkret.
- Tulis langkah praktik sebagai DAFTAR BERNOMOR Markdown (1. 2. 3.), bukan bullet.
- Gunakan codeblock Markdown (```bash / ```routeros / ```php dll) untuk sintaks/perintah.

## 4. Latihan & Evaluasi Mandiri
- Minimal 3 pertanyaan analisis atau soal studi kasus praktikum.

## 5. Rangkuman
- Poin-poin kunci materi di atas.

Bahasa santai edukatif, teknis namun mudah dipahami siswa SMK. Tuliskan HANYA isi modul Markdown tanpa kata pengantar atau basa-basi tambahan.
TXT;

    try {
        // Menggunakan geminiText() tanpa JSON Schema constraint
        $rawMd = geminiText($apiKey, $model, $prompt, 45, 0.7, 16384, 2, 'aimm');
    } catch (Throwable $e) {
        error_log('[ai_module_maker] generate: ' . $e->getMessage());
        jsonOut(['status' => 'error', 'message' => 'AI gagal membuat modul: ' . $e->getMessage()], 500);
    }

    // Lepas pembungkus kode terluar sebelum diproses lebih lanjut
    $rawMd = aimmStripOuterFence($rawMd);

    // Penanda penolakan AI: cek regex pada 200 karakter pertama (setelah trim)
    $head200 = mb_substr(trim($rawMd), 0, 200);
    if (preg_match('/\[ERROR\]\s*:?\s*(.+)/iu', $head200, $mErr)) {
        $errMsg = trim($mErr[1]);
        jsonOut(['status' => 'error', 'message' => $errMsg ?: 'Topik tidak relevan dengan kategori yang dipilih.'], 422);
    }

    // Judul dari H1 Markdown: bersihkan tag & karakter penekanan, maks 150
    $title = 'Modul ' . $topikSpesifik;
    if (preg_match('/^#\s+(.+)$/m', $rawMd, $m)) {
        $titleRaw = strip_tags($m[1]);
        $titleRaw = str_replace(['*', '_', '`'], '', $titleRaw);
        $titleRaw = trim($titleRaw);
        if ($titleRaw !== '') {
            $title = mb_substr($titleRaw, 0, 150);
        }
    }

    // Konversi Markdown ke HTML
    $rawHtml = aimmMarkdownToHtml($rawMd);

    // Sanitasi HTML menggunakan DOMDocument allowlist bawaan sistem
    $sanitized    = aimmSanitizeHtml($rawHtml);
    $visibleText  = trim(strip_tags($sanitized));
    $textLen      = mb_strlen($visibleText);
    $h2Count      = preg_match_all('/^##[ \t]+\S/m', $rawMd);

    if ($textLen < AIMM_MIN_TEXT_LEN || $h2Count < AIMM_MIN_HEADINGS) {
        error_log(sprintf(
            '[ai_module_maker] Validasi gagal: textLen=%d (<%d), h2=%d (<%d)',
            $textLen,
            AIMM_MIN_TEXT_LEN,
            $h2Count,
            AIMM_MIN_HEADINGS
        ));
        jsonOut(['status' => 'error', 'message' => 'Hasil modul terlalu singkat atau struktur tidak lengkap. Coba perjelas topik spesifik.'], 502);
    }

    // Bungkus HTML yang sudah disanitasi dengan theme untuk preview
    $wrappedHtml = aimmWrapWithTheme($sanitized, $title);

    // Simpan ke Session Draft (HTML mentah AI untuk submit, wrapped untuk preview)
    session_start();
    $_SESSION['aimm_draft'] = [
        'title'           => $title,
        'html'            => $sanitized,        // HTML mentah AI (untuk disimpan ke file)
        'wrapped_html'    => $wrappedHtml,      // HTML sudah dibungkus theme (untuk preview)
        'topic'           => $topicKey,
        'category'        => $topicCategory,
        'topik_spesifik'  => $topikSpesifik,
        'tingkat'         => $tingkat,
        'created'         => time(),
    ];
    session_write_close();

    jsonOut([
        'status' => 'success',
        'draft'  => [
            'title'       => $title,
            'topic'       => $topicKey,
            'category'    => $topicCategory,
            'tingkat'     => $tingkat,
            'wrapped_html'=> $wrappedHtml,  // Kirim wrapped HTML ke frontend untuk preview
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

    $title          = $draft['title'];
    $category       = $draft['category'];
    $tingkat        = $draft['tingkat'];
    $topikSpesifik  = trim((string)($draft['topik_spesifik'] ?? ''));

    // SIMPAN HTML MENTAH AI (sudah disanitasi) KE FILE
    // BUKAN yang sudah dibungkus theme.
    // Nanti buka_modul.php yang akan membungkusnya dengan theme saat ditampilkan ke siswa.
    $bodyHtml = aimmSanitizeHtml((string)$draft['html']);
    $html     = $bodyHtml; // Simpan hanya fragmen HTML mentah

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

    // description menyimpan topik spesifik (kolom text yang sudah ada) agar
    // guru/siswa tahu cakupan modul. Fallback ke format lama bila kosong.
    $description = $topikSpesifik !== ''
        ? mb_substr($topikSpesifik, 0, 1000)
        : 'Modul pembelajaran AI — topik ' . $category . ' (kelas ' . $tingkat . ')';

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO modules (title, category, description, image_path, ai_file, file_path, jenis_resource, kelas_target, status)
             VALUES (:title, :category, :description, :image_path, :ai_file, :file_path, :jenis, :kelas_target, :status)"
        );
        $stmt->execute([
            ':title'       => $title,
            ':category'    => $category,
            ':description' => $description,
            ':image_path'  => 'img/debian.webp',
            ':ai_file'     => $filename,
            ':file_path'   => $fullPath, // Simpan path lengkap file HTML mentah
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
