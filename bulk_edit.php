<?php
include 'koneksi.php';
include 'csrf_helper.php';
session_start();

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: login.php"); exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 404 Not Found"); exit();
}
csrf_require_valid_post();

$ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []))));
if (empty($ids)) { header("Location: /pintu-rahasia-sija"); exit(); }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM kuis_soal WHERE id IN ($placeholders) ORDER BY id ASC");
$stmt->execute($ids);
$soal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = ''; $kategori = ''; $level = '';
$rawtext_prefill = ''; $original_data = []; $original_ids = [];

if (empty($soal_list)) {
    $error = 'Soal tidak ditemukan.';
} else {
    $kategori_set = array_values(array_unique(array_column($soal_list, 'kategori')));
    $level_set    = array_values(array_unique(array_column($soal_list, 'level')));

    if (count($kategori_set) > 1 || count($level_set) > 1) {
        $error = 'Soal terpilih harus dari kategori & level yang sama. '
               . 'Gunakan filter Kategori/Level dulu sebelum memilih.';
    } else {
        $kategori = $kategori_set[0];
        $level    = $level_set[0];

        // Prefill rawtext — beda format untuk pilgan vs isian
        $stmt_alt = $pdo->prepare("SELECT jawaban_alternatif FROM kuis_soal_alternatif_isian WHERE soal_id = ? ORDER BY id ASC");
        $rawtext_blocks = [];
        $no = 1;

        foreach ($soal_list as $s) {
            $jenis = $s['jenis_soal'] ?? 'pilgan';

            if ($jenis === 'isian') {
                $stmt_alt->execute([$s['id']]);
                $alts = $stmt_alt->fetchAll(PDO::FETCH_COLUMN);

                $block = "{$no}. {$s['pertanyaan']}\n";
                $block .= "Answer: " . ($alts[0] ?? '') . "\n";
                for ($i = 1; $i < count($alts); $i++) {
                    $block .= "Alt: " . $alts[$i] . "\n";
                }
                $block .= "Materi: " . ($s['materi'] ?? '');

                $original_data[] = [
                    'id'         => (int)$s['id'],
                    'jenis'      => 'isian',
                    'pertanyaan' => $s['pertanyaan'],
                    'alternatif' => $alts,
                    'materi'     => $s['materi'] ?? '',
                ];
            } else {
                $block = "{$no}. {$s['pertanyaan']}\n"
                    . "A) {$s['pilihan_a']}\n"
                    . "B) {$s['pilihan_b']}\n"
                    . "C) {$s['pilihan_c']}\n"
                    . "D) {$s['pilihan_d']}\n"
                    . "Answer: " . strtoupper($s['jawaban']) . "\n"
                    . "Materi: " . ($s['materi'] ?? '');

                $original_data[] = [
                    'id'         => (int)$s['id'],
                    'jenis'      => 'pilgan',
                    'pertanyaan' => $s['pertanyaan'],
                    'pilihan_a'  => $s['pilihan_a'],
                    'pilihan_b'  => $s['pilihan_b'],
                    'pilihan_c'  => $s['pilihan_c'],
                    'pilihan_d'  => $s['pilihan_d'],
                    'jawaban'    => strtoupper($s['jawaban']),
                    'materi'     => $s['materi'] ?? '',
                ];
            }

            $rawtext_blocks[] = $block;
            $original_ids[] = (int)$s['id'];
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
    <title>Edit Massal Soal - SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card { border-radius: 15px; } .card-header { font-weight: 600; }
        #quizRawText { min-height: 350px; resize: vertical; white-space: pre-wrap; font-family: monospace; font-size: 13px; }
        #previewContainer { max-height: 500px; overflow-y: auto; }
        .diff-old { color: #dc3545; text-decoration: line-through; }
        .diff-new { color: #198754; font-weight: 600; }
        .field-unchanged { color: #6c757d; }
        .badge-isian  { background: #0d6efd; }
        .badge-pilgan { background: #6c757d; }
    </style>
</head>
<body class="bg-light">
    <?php $hero_subtitle = 'Edit soal massal (pilgan & isian)'; include __DIR__ . '/includes/admin_header.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="mb-3">
            <a href="/pintu-rahasia-sija" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php else: ?>

        <div class="alert alert-secondary d-flex align-items-center gap-3 flex-wrap">
            <strong><?= count($soal_list) ?> soal</strong> terpilih.
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
                        <i class="bi bi-info-circle-fill"></i> <strong>Format otomatis terisi.</strong>
                        Edit langsung teksnya, lalu klik <strong>Generate Preview</strong>.
                        <br>
                        <span class="badge badge-pilgan">Pilgan</span> Ada baris <code>A)</code> <code>B)</code> <code>C)</code> <code>D)</code> + <code>Answer: A</code>.
                        <br>
                        <span class="badge badge-isian">Isian</span> Ada <code>Answer: jawaban utama</code> + baris <code>Alt: alternatif</code>.
                        <br>
                        <strong>Jangan hapus/tambah blok soal</strong> — jumlah harus tepat <?= count($soal_list) ?>, dipisah 1 baris kosong.
                    </div>

                    <textarea id="quizRawText" class="form-control mb-3"><?= htmlspecialchars($rawtext_prefill) ?></textarea>

                    <button class="btn btn-warning w-100" onclick="generatePreviewFromText()">
                        <i class="bi bi-eye"></i> Generate Preview
                    </button>
                </div>

                <div id="previewSection" style="display:none;">
                    <h6 class="fw-bold mb-3">Preview Perubahan</h6>
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
                                <i class="bi bi-check2-circle"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (!$error): ?>
    <script>
        const originalData = <?= json_encode($original_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>;

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.innerText = s ?? '';
            return d.innerHTML;
        }

        function diffSpan(oldVal, newVal) {
            if (oldVal === newVal) return `<span class="field-unchanged">${escapeHtml(newVal)}</span>`;
            return `<span class="diff-old">${escapeHtml(oldVal)}</span> → <span class="diff-new">${escapeHtml(newVal)}</span>`;
        }

        // ============================================================
        // Parser: deteksi jenis soal per blok (pilgan vs isian)
        // ============================================================
        function parseBlock(blockText) {
            const lines = blockText.split('\n').map(l => l.trim()).filter(l => l !== '');
            if (lines.length === 0) return null;

            const result = {
                pertanyaan: '',
                pilihan_a: '', pilihan_b: '', pilihan_c: '', pilihan_d: '',
                jawaban: '',
                alternatif: [],
                materi: ''
            };

            // Baris pertama: "1. Pertanyaan" atau langsung pertanyaan
            const firstLine = lines[0].replace(/^\d+\.\s*/, '');
            result.pertanyaan = firstLine;

            let pilihan = {};
            let isian = false;

            for (let i = 1; i < lines.length; i++) {
                const line = lines[i];

                // Pilihan A) / A. / A:
                const mPil = line.match(/^([A-D])[\)\.\:]\s*(.+)$/i);
                if (mPil) {
                    pilihan[mPil[1].toUpperCase()] = mPil[2].trim();
                    continue;
                }

                // Answer:
                const mAns = line.match(/^Answer\s*:\s*(.+)$/i);
                if (mAns) {
                    result.jawaban = mAns[1].trim();
                    continue;
                }

                // Alt:
                const mAlt = line.match(/^Alt\s*:\s*(.+)$/i);
                if (mAlt) {
                    result.alternatif.push(mAlt[1].trim());
                    isian = true;
                    continue;
                }

                // Materi:
                const mMat = line.match(/^Materi\s*:\s*(.*)$/i);
                if (mMat) {
                    result.materi = mMat[1].trim();
                    continue;
                }
            }

            // Deteksi jenis dari struktur
            const hasPilgan = Object.keys(pilihan).length > 0;
            if (hasPilgan && isian) throw new Error('Blok mengandung pilihan A-D DAN Alt: — ambigu.');
            if (hasPilgan) {
                result.jenis = 'pilgan';
                result.pilihan_a = pilihan['A'] || '';
                result.pilihan_b = pilihan['B'] || '';
                result.pilihan_c = pilihan['C'] || '';
                result.pilihan_d = pilihan['D'] || '';
                if (!result.jawaban || !['A','B','C','D'].includes(result.jawaban.toUpperCase())) {
                    throw new Error('Pilgan harus punya "Answer: A/B/C/D".');
                }
            } else if (isian) {
                result.jenis = 'isian';
                // Jawaban utama (Answer) masuk sebagai alternatif pertama
                if (result.jawaban) {
                    result.alternatif.unshift(result.jawaban);
                }
                if (result.alternatif.length === 0) {
                    throw new Error('Isian harus punya minimal 1 "Answer:" atau "Alt:".');
                }
            } else {
                throw new Error('Blok tidak dikenali — tidak ada pilihan A-D maupun Alt:.');
            }

            return result;
        }

        function parseAllBlocks(rawText) {
            // Pisah per blok: 1 baris kosong (atau lebih)
            const blocks = rawText.split(/\n\s*\n/).filter(b => b.trim() !== '');
            return blocks.map((b, i) => {
                try {
                    return parseBlock(b);
                } catch (e) {
                    throw new Error('Blok #' + (i + 1) + ': ' + e.message);
                }
            });
        }

        function generatePreviewFromText() {
            const rawText = document.getElementById('quizRawText').value.trim();
            if (!rawText) { alert('Textbox kosong!'); return; }

            let parsed;
            try {
                parsed = parseAllBlocks(rawText);
            } catch (e) {
                alert('❌ ' + e.message);
                return;
            }

            if (parsed.length !== originalData.length) {
                alert('Jumlah blok (' + parsed.length + ') ≠ jumlah soal terpilih ('
                    + originalData.length + '). Jangan hapus/tambah blok.');
                return;
            }

            const container = document.getElementById('previewContainer');
            container.innerHTML = '';
            const payload = [];
            let hasError = false;

            parsed.forEach((q, i) => {
                const orig = originalData[i];

                if (q.jenis !== orig.jenis) {
                    container.innerHTML += `<div class="mb-3 p-2 border border-danger rounded">
                        <strong>Soal #${i+1} (id: ${orig.id})</strong><br>
                        <span class="text-danger">Jenis berubah dari <strong>${orig.jenis}</strong> ke <strong>${q.jenis}</strong>. Tidak boleh — hapus & buat soal baru kalau ingin ganti jenis.</span>
                    </div>`;
                    hasError = true;
                    return;
                }

                let html = `<div class="mb-3 p-2 border rounded">
                    <strong>Soal #${i+1} (id: ${orig.id})</strong>
                    <span class="badge ${q.jenis === 'isian' ? 'badge-isian' : 'badge-pilgan'}">${q.jenis.toUpperCase()}</span><br>
                    <div class="small mt-1"><strong>Pertanyaan:</strong> ${diffSpan(orig.pertanyaan, q.pertanyaan)}</div>`;

                if (q.jenis === 'pilgan') {
                    html += `<ul class="small mb-1">
                        <li>A) ${diffSpan(orig.pilihan_a, q.pilihan_a)}</li>
                        <li>B) ${diffSpan(orig.pilihan_b, q.pilihan_b)}</li>
                        <li>C) ${diffSpan(orig.pilihan_c, q.pilihan_c)}</li>
                        <li>D) ${diffSpan(orig.pilihan_d, q.pilihan_d)}</li>
                    </ul>
                    <div class="small"><strong>Jawaban:</strong> ${diffSpan(orig.jawaban, q.jawaban.toUpperCase())}</div>`;

                    payload.push({
                        id: orig.id,
                        jenis: 'pilgan',
                        pertanyaan: q.pertanyaan,
                        pilihan_a: q.pilihan_a, pilihan_b: q.pilihan_b,
                        pilihan_c: q.pilihan_c, pilihan_d: q.pilihan_d,
                        jawaban: q.jawaban.toUpperCase(),
                        materi: q.materi
                    });
                } else {
                    html += `<div class="small"><strong>Alternatif Jawaban:</strong><br>`;
                    q.alternatif.forEach((alt, idx) => {
                        const oldAlt = orig.alternatif[idx] || '(baru)';
                        html += `&nbsp;&nbsp;• ${diffSpan(oldAlt, alt)}<br>`;
                    });
                    if (q.alternatif.length < orig.alternatif.length) {
                        for (let j = q.alternatif.length; j < orig.alternatif.length; j++) {
                            html += `&nbsp;&nbsp;• <span class="diff-old">${escapeHtml(orig.alternatif[j])}</span> (dihapus)<br>`;
                        }
                    }
                    html += `</div>`;

                    payload.push({
                        id: orig.id,
                        jenis: 'isian',
                        pertanyaan: q.pertanyaan,
                        alternatif: q.alternatif,
                        materi: q.materi
                    });
                }

                html += `<div class="small mt-1"><strong>Materi:</strong> ${diffSpan(orig.materi || '(kosong)', q.materi || '(kosong)')}</div></div>`;
                container.innerHTML += html;
            });

            if (hasError) {
                alert('Ada masalah pada preview. Perbaiki dulu sebelum simpan.');
                return;
            }

            document.getElementById('finalJsonData').value = JSON.stringify(payload);
            document.getElementById('editSection').style.display = 'none';
            document.getElementById('previewSection').style.display = 'block';
        }

        function backToEdit() {
            document.getElementById('editSection').style.display = 'block';
            document.getElementById('previewSection').style.display = 'none';
        }

        document.getElementById('updateForm').addEventListener('submit', function (e) {
            if (!confirm('Simpan perubahan untuk ' + originalData.length + ' soal?')) {
                e.preventDefault();
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>