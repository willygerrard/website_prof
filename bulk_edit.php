<?php
include 'koneksi.php';
include 'csrf_helper.php';
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php");
    exit();
}

csrf_require_valid_post();

if (strpos($_SERVER['REQUEST_URI'], 'pintu-rahasia-sija') === false && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // biarkan lolos kalau diakses via POST dari management_kuis.php,
    // tapi tetap tolak akses langsung via GET tanpa lewat rute rahasia
}

$ids = $_POST['ids'] ?? [];
$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

if (empty($ids)) {
    header("Location: /pintu-rahasia-sija");
    exit();
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM kuis_soal WHERE id IN ($placeholders) ORDER BY id ASC");
$stmt->execute($ids);
$soal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$kategori = '';
$level = '';
$rawtext_prefill = '';
$original_data = [];
$original_ids = [];

if (empty($soal_list)) {
    $error = 'Soal yang dipilih tidak ditemukan di database (mungkin sudah terhapus).';
} else {
    $kategori_set = array_values(array_unique(array_column($soal_list, 'kategori')));
    $level_set    = array_values(array_unique(array_column($soal_list, 'level')));

    if (count($kategori_set) > 1 || count($level_set) > 1) {
        $error = 'Soal yang dipilih berasal dari kategori dan/atau level yang berbeda-beda. '
               . 'Edit massal hanya bisa dilakukan untuk soal dengan kategori & level yang sama. '
               . 'Silakan gunakan filter Kategori/Level di halaman Manage Kuis sebelum memilih soal.';
    } else {
        $kategori = $kategori_set[0];
        $level = $level_set[0];

        $rawtext_blocks = [];
        $no = 1;
        foreach ($soal_list as $s) {
            $rawtext_blocks[] = "{$no}. {$s['pertanyaan']}\n"
                . "A) {$s['pilihan_a']}\n"
                . "B) {$s['pilihan_b']}\n"
                . "C) {$s['pilihan_c']}\n"
                . "D) {$s['pilihan_d']}\n"
                . "Answer: " . strtoupper($s['jawaban']);

            $original_ids[] = (int)$s['id'];
            $original_data[] = [
                'id'         => (int)$s['id'],
                'pertanyaan' => $s['pertanyaan'],
                'pilihan_a'  => $s['pilihan_a'],
                'pilihan_b'  => $s['pilihan_b'],
                'pilihan_c'  => $s['pilihan_c'],
                'pilihan_d'  => $s['pilihan_d'],
                'jawaban'    => strtoupper($s['jawaban']),
            ];
            $no++;
        }
        $rawtext_prefill = implode("\n\n", $rawtext_blocks);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Massal Soal - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card { border-radius: 15px; }
        .card-header { font-weight: 600; }
        #quizRawText { min-height: 350px; resize: vertical; white-space: pre-wrap; }
        #previewContainer { max-height: 500px; overflow-y: auto; }
        .diff-old { color: #dc3545; text-decoration: line-through; }
        .diff-new { color: #198754; font-weight: 600; }
        .field-unchanged { color: #6c757d; }
    </style>
</head>
<body class="bg-light">

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="index.php">Modul Pembelajaran SIJA</a>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4"></ul>
                <div class="d-flex align-items-center gap-3">
                    <?php if (isset($_SESSION['username'])) : ?>
                        <span class="text-secondary fw-medium d-none d-md-inline small">
                            👋 Hai, <strong class="text-dark"><?= htmlspecialchars($_SESSION['username']); ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- HEADER -->
    <header class="py-5" style="
        background: linear-gradient(to bottom, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.9)),
                    url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?q=80&w=800');
        background-size: cover;
        background-position: center;">
        <div class="container px-4 px-lg-5 my-5">
            <div class="text-center text-white">
                <h1 class="display-4 fw-bolder">Pusat Pembelajaran SIJA</h1>
                <p class="lead fw-normal text-white-50 mb-0">Edit soal secara massal</p>
            </div>
        </div>
    </header>

    <div class="container mt-4 mb-5">
        <div class="mb-3">
            <a href="/pintu-rahasia-sija" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Kembali ke Manage Kuis
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php else: ?>

        <div class="alert alert-secondary d-flex align-items-center gap-3">
            <div>
                <strong><?= count($soal_list) ?> soal</strong> terpilih untuk diedit massal.
            </div>
            <span class="badge bg-info text-dark"><?= htmlspecialchars($kategori) ?></span>
            <span class="badge bg-warning text-dark"><?= htmlspecialchars(ucfirst($level)) ?></span>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-pencil-square"></i> Edit Massal Soal
            </div>
            <div class="card-body">

                <div id="editSection">
                    <div class="alert alert-info py-2 px-3 small">
                        <i class="bi bi-info-circle-fill"></i> <strong>Cara Pakai:</strong><br>
                        Format soal di bawah sudah otomatis terisi dari data yang ada. Edit langsung teksnya
                        (pertanyaan, pilihan, atau jawaban), lalu klik <strong>Generate Preview</strong>.
                        Jangan menghapus atau menambah blok soal — jumlah blok harus tetap
                        <?= count($soal_list) ?> soal, dipisah 1 baris kosong antar soal.
                    </div>

                    <textarea id="quizRawText" class="form-control mb-3"><?= htmlspecialchars($rawtext_prefill) ?></textarea>

                    <button class="btn btn-warning w-100" onclick="generatePreviewFromText()">
                        <i class="bi bi-eye"></i> Generate Preview
                    </button>
                </div>

                <div id="previewSection" style="display:none;">
                    <h6 class="fw-bold mb-3">Preview Perubahan (sebelum → sesudah)</h6>
                    <div id="previewContainer" class="border p-2 mb-3 bg-light rounded"></div>

                    <form id="updateForm" action="update_quiz.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" id="finalJsonData" name="update_json">
                        <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori) ?>">
                        <input type="hidden" name="level" value="<?= htmlspecialchars($level) ?>">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary" onclick="backToEdit()">
                                <i class="bi bi-arrow-left"></i> Edit Kembali
                            </button>
                            <button type="submit" class="btn btn-success flex-grow-1">
                                <i class="bi bi-check2-circle"></i> Simpan Perubahan ke Database
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="py-5 bg-dark">
        <div class="container"><p class="m-0 text-center text-white">Copyright &copy; SIJA Website 2026</p></div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (!$error): ?>
    <script>
        // Data asli dari DB, dipakai untuk validasi jumlah blok & diff preview
        const originalData = <?= json_encode($original_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>;
        const originalIds  = <?= json_encode($original_ids) ?>;

        // Parser sama persis dengan tambah_soal.php (AI Quiz Import) supaya format konsisten
        function parseQuizText(rawText) {
            const questionBlocks = rawText.split(/\n\s*\n/);
            const questionsArray = [];

            for (let block of questionBlocks) {
                block = block.trim();
                if (!block) continue;
                const lines = block.split('\n').map(line => line.trim()).filter(line => line.length > 0);

                let questionText = "";
                let options = [];
                let correctAnswer = "";

                lines.forEach(line => {
                    if (line.match(/^(ans|answer|correct|key)\s*:\s*/i)) {
                        correctAnswer = line.replace(/^(ans|answer|correct|key)\s*:\s*/i, '').trim();
                    } else if (line.match(/^[A-E][\)|\]\.]\s*/i)) {
                        options.push(line.replace(/^[A-E][\)|\]\.]\s*/i, '').trim());
                    } else if (line.match(/^\d+[\.\)]\s*/)) {
                        questionText = line.replace(/^\d+[\.\)]\s*/, '').trim();
                    } else {
                        if (!questionText) questionText = line;
                    }
                });

                // Match shorthand (A/B/C/D) ke teks pilihan
                if (correctAnswer.length === 1 && ['A', 'B', 'C', 'D'].includes(correctAnswer.toUpperCase())) {
                    const idx = correctAnswer.toUpperCase().charCodeAt(0) - 65;
                    if (options[idx]) correctAnswer = options[idx];
                }

                if (questionText && options.length > 0) {
                    questionsArray.push({ question_text: questionText, options: options, correct_answer: correctAnswer });
                }
            }
            return questionsArray;
        }

        function diffSpan(oldVal, newVal) {
            if (oldVal === newVal) {
                return `<span class="field-unchanged">${escapeHtml(newVal)}</span>`;
            }
            return `<span class="diff-old">${escapeHtml(oldVal)}</span> → <span class="diff-new">${escapeHtml(newVal)}</span>`;
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.innerText = str ?? '';
            return div.innerHTML;
        }

        function generatePreviewFromText() {
            const rawText = document.getElementById('quizRawText').value.trim();
            if (!rawText) {
                alert('Textbox tidak boleh kosong!');
                return;
            }

            const parsed = parseQuizText(rawText);

            if (parsed.length !== originalData.length) {
                alert('Jumlah blok soal (' + parsed.length + ') tidak cocok dengan jumlah soal terpilih ('
                    + originalData.length + '). Pastikan tidak ada blok soal yang terhapus/tertambah, '
                    + 'dan setiap soal dipisah tepat 1 baris kosong.');
                return;
            }

            const huruf = ['A', 'B', 'C', 'D'];
            const updatePayload = [];
            const previewContainer = document.getElementById('previewContainer');
            previewContainer.innerHTML = '';

            parsed.forEach((q, i) => {
                const orig = originalData[i];
                const opts = q.options.slice(0, 4);
                while (opts.length < 4) opts.push(''); // jaga-jaga kalau pilihan kurang dari 4

                const idxJawaban = opts.indexOf(q.correct_answer);
                const jawabanBaru = idxJawaban !== -1 ? huruf[idxJawaban] : '';

                if (!jawabanBaru) {
                    previewContainer.innerHTML += `<div class="mb-3 p-2 border border-danger rounded">
                        <strong>Soal #${i + 1} (id: ${orig.id})</strong><br>
                        <span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> 
                        Jawaban benar tidak ditemukan di antara pilihan A-D. Periksa kembali baris "Answer:".</span>
                    </div>`;
                    return;
                }

                updatePayload.push({
                    id: orig.id,
                    pertanyaan: q.question_text,
                    pilihan_a: opts[0],
                    pilihan_b: opts[1],
                    pilihan_c: opts[2],
                    pilihan_d: opts[3],
                    jawaban: jawabanBaru
                });

                previewContainer.innerHTML += `
                    <div class="mb-3 p-2 border rounded">
                        <strong>Soal #${i + 1} (id: ${orig.id})</strong><br>
                        <div class="small mt-1"><strong>Pertanyaan:</strong> ${diffSpan(orig.pertanyaan, q.question_text)}</div>
                        <ul class="small mb-1">
                            <li>A) ${diffSpan(orig.pilihan_a, opts[0])}</li>
                            <li>B) ${diffSpan(orig.pilihan_b, opts[1])}</li>
                            <li>C) ${diffSpan(orig.pilihan_c, opts[2])}</li>
                            <li>D) ${diffSpan(orig.pilihan_d, opts[3])}</li>
                        </ul>
                        <div class="small"><strong>Jawaban:</strong> ${diffSpan(orig.jawaban, jawabanBaru)}</div>
                    </div>`;
            });

            if (updatePayload.length !== originalData.length) {
                // Ada soal dengan jawaban tidak valid, jangan lanjut ke tahap simpan
                return;
            }

            document.getElementById('finalJsonData').value = JSON.stringify(updatePayload);
            document.getElementById('editSection').style.display = 'none';
            document.getElementById('previewSection').style.display = 'block';
        }

        function backToEdit() {
            document.getElementById('editSection').style.display = 'block';
            document.getElementById('previewSection').style.display = 'none';
        }

        document.getElementById('updateForm').addEventListener('submit', function (e) {
            if (!confirm('Simpan perubahan ke database untuk ' + originalData.length + ' soal ini?')) {
                e.preventDefault();
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>