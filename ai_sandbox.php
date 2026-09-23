<?php
// ai_sandbox_v4.php - AI Learning Navigator + Animasi Clipper AI Mode
// Logika PHP identik dengan ai_sandbox.php (bug-free). Hanya layer frontend yang ditambah.
include 'koneksi.php';

// BACKEND HANDLER FOR AJAX REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_ai') {
    header('Content-Type: application/json');
    $userPrompt  = trim($_POST['prompt'] ?? '');
    $userTingkat = trim($_POST['tingkat'] ?? 'X');
    $searchMode  = trim($_POST['search_mode'] ?? 'ai'); // 'ai' ATAU 'sql'

    if (empty($userPrompt)) {
        echo json_encode(['status' => 'error', 'message' => 'Hey, kata kunci pencarian kosong nih! 🫣 Tulis dulu apa yang mau dicari.']);
        exit;
    }

    // ============================================================
    // MODE 1: PENCARIAN SQL BIASA (Searchbox Standar Presisi)
    // ============================================================
    if ($searchMode === 'sql') {
        $whereConditions = [];
        $queryParams     = [];

        $whereConditions[] = "(
            kelas_target IS NULL 
            OR kelas_target = '' 
            OR LOWER(kelas_target) = 'semua' 
            OR kelas_target LIKE ? 
            OR kelas_target LIKE ? 
            OR kelas_target LIKE ?
        )";
        $queryParams[] = "%{$userTingkat}%";
        $queryParams[] = "{$userTingkat},%";
        $queryParams[] = "%,{$userTingkat}";

        $whereConditions[] = "(LOWER(title) LIKE ? OR LOWER(description) LIKE ? OR LOWER(category) LIKE ?)";
        $queryParams[]     = "%" . strtolower($userPrompt) . "%";
        $queryParams[]     = "%" . strtolower($userPrompt) . "%";
        $queryParams[]     = "%" . strtolower($userPrompt) . "%";

        $sql  = "SELECT id, title, category, jenis_resource, description, file_path, image_path, kelas_target 
                FROM modules 
                WHERE " . implode(" AND ", $whereConditions) . " 
                ORDER BY id DESC LIMIT 20";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($queryParams);
        $recommendedModules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status'  => 'success',
            'message' => '🔎 Menampilkan hasil pencarian presisi berdasarkan kata kunci.',
            'meta'    => [
                'tingkat_user' => $userTingkat,
                'search_mode'  => 'sql',
                'keywords'     => [$userPrompt]
            ],
            'modules' => $recommendedModules
        ]);
        exit;
    }

    // ============================================================
    // MODE 2: PENCARIAN AI LEARNING NAVIGATOR (Smart Intent Search)
    // ============================================================
    $dbCategories = $pdo->query("SELECT DISTINCT category FROM modules WHERE category IS NOT NULL AND category <> ''")->fetchAll(PDO::FETCH_COLUMN);
    $catListStr   = "'" . implode("', '", $dbCategories) . "'";

    if (!getenv('GEMINI_API_KEY') && file_exists(__DIR__ . '/.env')) {
        $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($envLines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . "=" . trim($value, " \t\n\r\0\x0B\"'"));
        }
    }

    $apiKey    = getenv('GEMINI_API_KEY') ?: '';
    $modelName = 'gemini-3.5-flash-lite';
    $apiUrl    = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . $apiKey;

    $systemInstruction = "Kamu adalah AI Learning Navigator yang asyik, santai, dan menyemangati untuk LMS SMK IT SIJA! 🚀\n"
    . "Daftar Category Valid saat ini di DB: [{$catListStr}].\n"
    . "Daftar Jenis Resource Valid HANYA: ['modul', 'media', 'video'].\n\n"
    . "Tugasmu mengekstrak intent user menjadi JSON murni:\n"
    . "1. Reject (kembalikan {\"error\": \"invalid_topic\"}) HANYA jika prompt benar-benar di luar IT/Computer Science.\n"
    . "2. Jika user sebut profesi/cita-cita (misal 'web developer', 'sysadmin', 'network engineer', 'devops'), petakan ke kata kunci teknis turunan:\n"
    . "   - web developer → ['html', 'css', 'javascript', 'php', 'bootstrap', 'frontend', 'backend', 'web']\n"
    . "   - programmer → ['pemrograman', 'python', 'java', 'c++', 'algoritma', 'struktur data']\n"
    . "   - sysadmin / DevOps → ['devops', 'linux', 'server', 'docker', 'cloud', 'deploy', 'administrasi server']\n"
    . "   - network engineer → ['jaringan', 'network', 'kabel', 'utp', 'ip', 'router', 'switch', 'wifi', 'lan', 'osi', 'tcp/ip', 'vlan', 'subnetting']\n"
    . "3. 'keywords': Array kata kunci BHS INDONESIA & INGGRIS (sinonim) pencarian teks.\n"
    . "4. 'category': Isi jika sebut Category Valid di atas, selain itu null.\n"
    . "5. 'jenis_resource': Isi jika sebut tipe media spesifik, selain itu null.\n"
    . "6. 'cross_level_note': Tulislah 1 kalimat saran/pesan motivasi santai jika topik yang dicari siswa ({$userTingkat}) biasanya diajarkan di tingkat yang lebih tinggi.\n\n"
    . "Format JSON Wajib: {\"keywords\": [...], \"category\": string|null, \"jenis_resource\": string|null, \"cross_level_note\": string|null}";

    $payload = [
        "contents" => [["parts" => [["text" => $systemInstruction . "\n\nUser Input: " . $userPrompt]]]],
        "generationConfig" => ["response_mime_type" => "application/json"]
    ];

    $aiFailed     = false;
    $aiFailReason = '';
    $aiResult     = null;

    if (empty($apiKey)) {
        $aiFailed     = true;
        $aiFailReason = 'GEMINI_API_KEY belum terisi di .env';
    } else {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            $aiFailed     = true;
            $aiFailReason = $curlErr ? "cURL Error: $curlErr" : "HTTP $httpCode";
        } else {
            $responseData = json_decode($response, true);
            $jsonText     = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
            $aiResult     = json_decode($jsonText, true);
            if (!is_array($aiResult)) {
                $aiFailed     = true;
                $aiFailReason = 'Response JSON Invalid';
            }
        }
    }

    // Fallback Engine
    $aiMode = 'ai';
    if ($aiFailed) {
        $aiMode = 'fallback';
        $stopwords = ['aku','saya','mau','ingin','pengen','belajar','tentang','apa','yang','cara','bagaimana','dong','tolong','sih','ya','yg'];
        $rawWords  = preg_split('/\s+/', strtolower(trim($userPrompt)));
        $extractedKeywords = [];
        foreach ($rawWords as $w) {
            $w = preg_replace('/[^a-z0-9]/', '', $w);
            if (strlen($w) >= 3 && !in_array($w, $stopwords, true)) {
                $extractedKeywords[] = $w;
            }
        }
        $extractedKeywords = array_values(array_unique($extractedKeywords));
        $targetCategory    = null;
        $targetJenis       = null;
    } else {
        if (isset($aiResult['error'])) {
            echo json_encode(['status' => 'warning', 'message' => 'Hmm, topik ini agaknya di luar lingkup pelajaran IT/SIJA nih. 🤔 Coba cari topik IT lainnya ya!']);
            exit;
        }
        $extractedKeywords = $aiResult['keywords'] ?? [];
        $targetCategory    = $aiResult['category'] ?? null;
        $targetJenis       = $aiResult['jenis_resource'] ?? null;
    }

    // ============================================================
    // MariaDB Search Query untuk Mode AI
    // ============================================================
    $whereConditions = [];
    $queryParams     = [];

    if (!empty($targetCategory)) {
        $whereConditions[] = "LOWER(category) = LOWER(?)";
        $queryParams[]     = $targetCategory;
    }

    if (!empty($targetJenis)) {
        $whereConditions[] = "LOWER(jenis_resource) = LOWER(?)";
        $queryParams[]     = $targetJenis;
    }

    if (!empty($extractedKeywords)) {
        $kwClauses = [];
        foreach ($extractedKeywords as $kw) {
            $kw = trim($kw);
            if (empty($kw)) continue;

            $words = explode(' ', $kw);
            foreach ($words as $w) {
                $w = trim($w);
                if (strlen($w) < 2) continue;

                $kwClauses[]   = "LOWER(title) LIKE ?";
                $queryParams[] = "%" . strtolower($w) . "%";

                $kwClauses[]   = "LOWER(description) LIKE ?";
                $queryParams[] = "%" . strtolower($w) . "%";

                $kwClauses[]   = "LOWER(category) LIKE ?";
                $queryParams[] = "%" . strtolower($w) . "%";
            }
        }
        if (!empty($kwClauses)) {
            $whereConditions[] = "(" . implode(" OR ", $kwClauses) . ")";
        }
    }

    $whereClauseStr = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    $sql = "SELECT id, title, category, jenis_resource, description, file_path, image_path, kelas_target 
            FROM modules 
            {$whereClauseStr} 
            ORDER BY id DESC LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $recommendedModules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $crossLevelNote = $aiResult['cross_level_note'] ?? null;

    if (!empty($crossLevelNote)) {
        $friendlyMessage = $crossLevelNote;
    } else {
        $greetings = [
            'Wah, pencarianmu keren nih! 🎉 Yuk, kita cari modul yang pas buat kamu!',
            'Yuk, kita cari modul yang pas buat kamu! 💡',
            'Nih, ada beberapa rekomendasi buatmu! ✨',
            'AI nemuin modul yang cocok nih, guys! 🚀',
            'Langkah pertama udah kamu lakukan, lanjutin! 🔥',
        ];
        $friendlyMessage = $greetings[array_rand($greetings)];
    }

    echo json_encode([
        'status'  => 'success',
        'message' => $friendlyMessage,
        'meta'    => [
            'tingkat_user'    => $userTingkat,
            'search_mode'     => 'ai',
            'keywords'        => $extractedKeywords,
            'category_filter' => $targetCategory,
            'jenis_filter'    => $targetJenis,
            'ai_mode'         => $aiMode,
            'fail_reason'     => $aiFailReason
        ],
        'modules' => $recommendedModules
    ]);
    exit;
}
?>

<!-- ============================================================
     FRONTEND UI SANDBOX V4 — Animasi Clipper AI Mode
     ============================================================ -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sandbox V4 - Hybrid Searchbox LMS (Animated AI Mode)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .debug-box { background: #1e1e1e; color: #00ff66; font-family: monospace; font-size: 12px; }

        /* ============================================================
           SEARCH CARD — container utama dengan animasi clipper AI mode
           ============================================================ */
        .search-card {
            position: relative;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            transition: border-color 0.45s ease, box-shadow 0.45s ease;
            will-change: box-shadow, border-color;
        }

        /* AI Mode Aktif: border biru + pulse glow */
        .search-card.ai-active {
            border-color: #0d6efd;
            animation: aiPulse 3s ease-in-out infinite;
        }

        @keyframes aiPulse {
            0%, 100% {
                box-shadow:
                    0 0 0 2px rgba(13, 110, 253, 0.12),
                    0 0 20px rgba(13, 110, 253, 0.22),
                    inset 0 0 12px rgba(13, 110, 253, 0.04);
            }
            50% {
                box-shadow:
                    0 0 0 3px rgba(13, 110, 253, 0.22),
                    0 0 32px rgba(111, 66, 193, 0.32),
                    inset 0 0 18px rgba(13, 110, 253, 0.08);
            }
        }

        /* Garis gradient berjalan di bagian atas card (hanya AI mode) */
        .ai-gradient-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(
                90deg,
                #0d6efd, #6f42c1, #d63384, #0dcaf0, #0d6efd
            );
            background-size: 300% 100%;
            animation: gradientShift 5s linear infinite;
            opacity: 0;
            transition: opacity 0.5s ease;
            z-index: 4;
            pointer-events: none;
        }
        .search-card.ai-active .ai-gradient-bar { opacity: 1; }

        @keyframes gradientShift {
            0%   { background-position: 0%   50%; }
            100% { background-position: 300% 50%; }
        }

        /* Ripple wave - melingkar dari tombol toggle */
        .ai-ripple {
            position: absolute;
            border-radius: 50%;
            transform: scale(0);
            pointer-events: none;
            z-index: 0;
            animation: rippleExpand 0.9s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
        }
        .ai-ripple.ripple-ai {
            background: radial-gradient(
                circle,
                rgba(13, 110, 253, 0.35) 0%,
                rgba(111, 66, 193, 0.18) 40%,
                transparent 72%
            );
        }
        .ai-ripple.ripple-sql {
            background: radial-gradient(
                circle,
                rgba(108, 117, 125, 0.30) 0%,
                rgba(108, 117, 125, 0.12) 40%,
                transparent 72%
            );
        }

        @keyframes rippleExpand {
            0%   { transform: scale(0);   opacity: 0.85; }
            100% { transform: scale(1);   opacity: 0;    }
        }

        /* Light sweep diagonal - efek "clipper" */
        .ai-sweep {
            position: absolute;
            top: -50%;
            left: -120%;
            width: 80%;
            height: 200%;
            background: linear-gradient(
                115deg,
                transparent 0%,
                rgba(255, 255, 255, 0.0) 25%,
                rgba(13, 110, 253, 0.18) 45%,
                rgba(111, 66, 193, 0.28) 50%,
                rgba(13, 110, 253, 0.18) 55%,
                rgba(255, 255, 255, 0.0) 75%,
                transparent 100%
            );
            transform: rotate(0deg);
            pointer-events: none;
            z-index: 1;
            animation: sweepAcross 1.1s ease-out forwards;
        }

        @keyframes sweepAcross {
            0%   { left: -120%; }
            100% { left: 120%;  }
        }

        /* Konten card di atas animasi */
        .search-card .card-body {
            position: relative;
            z-index: 2;
        }

        /* ============================================================
           TOGGLE SWITCH & BUTTON
           ============================================================ */
        .form-switch .form-check-input {
            transform: scale(1.3);
            cursor: pointer;
            transition: background-color 0.35s ease, box-shadow 0.35s ease;
        }
        .form-switch .form-check-input:checked {
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.18);
        }

        .btn-ai-pulse {
            animation: btnPulse 2.4s ease-in-out infinite;
        }
        @keyframes btnPulse {
            0%, 100% { box-shadow: 0 0 0 0    rgba(13, 110, 253, 0.45); }
            50%      { box-shadow: 0 0 0 10px rgba(13, 110, 253, 0);    }
        }

        /* Sparkles icon berputar saat AI aktif */
        .search-card.ai-active #inputLabel::after {
            content: ' ✨';
            animation: sparkle 1.6s ease-in-out infinite;
            display: inline-block;
        }
        @keyframes sparkle {
            0%, 100% { transform: scale(1)   rotate(0deg);   opacity: 1;   }
            50%      { transform: scale(1.2) rotate(15deg);  opacity: 0.7; }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .search-card.ai-active,
            .btn-ai-pulse,
            .search-card.ai-active #inputLabel::after,
            .ai-gradient-bar {
                animation: none !important;
            }
        }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 900px;">
    <h3 class="fw-bold"><i class="bi bi-cpu text-primary"></i> Hybrid Searchbox Sandbox V4</h3>
    <p class="text-muted">Pilih mode pencarian menggunakan Toggle Switch di bawah ini.</p>

    <!-- CATATAN: class 'border-0' SENGAJA dihapus agar animasi border AI mode terlihat -->
    <div class="card shadow-sm mb-4 search-card ai-active" id="searchCard">
        <div class="ai-gradient-bar"></div>
        <div class="card-body">
            <div class="row g-3 mb-3 align-items-center">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Kelas Siswa:</label>
                    <select id="userTingkat" class="form-select">
                        <option value="X">Kelas X</option>
                        <option value="XI">Kelas XI</option>
                        <option value="XII">Kelas XII</option>
                    </select>
                </div>
                <div class="col-md-8 pt-md-4">
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" id="modeToggle" checked>
                        <label class="form-check-label fw-bold ms-2" for="modeToggle" id="modeLabel" style="cursor: pointer;">
                            <span class="badge bg-primary"><i class="bi bi-robot"></i> Mode AI Navigator</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold" id="inputLabel">Pertanyaan / Cita-cita Siswa (AI Mode):</label>
                <textarea id="userPrompt" class="form-control" rows="3" placeholder="Contoh: Aku pengen jadi network engineer handal..."></textarea>
            </div>

            <button onclick="searchModeHandler()" id="btnSearch" class="btn btn-primary fw-bold px-4 btn-ai-pulse">
                <i class="bi bi-sparkles"></i> Cari Modul via AI Navigator
            </button>
        </div>
    </div>

    <div id="output"></div>
</div>

<script>
/* ============================================================
   TOGGLE HANDLER + ANIMASI CLIPPER
   ============================================================ */
const searchCard  = document.getElementById('searchCard');
const modeToggle  = document.getElementById('modeToggle');

modeToggle.addEventListener('change', function () {
    const isAi = this.checked;
    triggerRipple(searchCard, modeToggle, isAi);
    triggerSweep(searchCard);
    setTimeout(() => {
        searchCard.classList.toggle('ai-active', isAi);
        updateSearchPlaceholder();
    }, 80);
});

/* Ripple melingkar dari posisi elemen toggle */
function triggerRipple(parent, originEl, isAi) {
    const parentRect = parent.getBoundingClientRect();
    const originRect = originEl.getBoundingClientRect();

    const cx = originRect.left + originRect.width  / 2;
    const cy = originRect.top  + originRect.height / 2;

    // Ukuran ripple = diagonal card * 1.6 agar menutupi seluruh card
    const diag = Math.hypot(parentRect.width, parentRect.height) * 1.6;

    const ripple = document.createElement('span');
    ripple.className = 'ai-ripple ' + (isAi ? 'ripple-ai' : 'ripple-sql');
    ripple.style.width  = diag + 'px';
    ripple.style.height = diag + 'px';
    ripple.style.left   = (cx - parentRect.left - diag / 2) + 'px';
    ripple.style.top    = (cy - parentRect.top  - diag / 2) + 'px';

    parent.appendChild(ripple);
    setTimeout(() => ripple.remove(), 950);
}

/* Light sweep diagonal melintasi card */
function triggerSweep(parent) {
    const sweep = document.createElement('div');
    sweep.className = 'ai-sweep';
    parent.appendChild(sweep);
    setTimeout(() => sweep.remove(), 1200);
}

/* Update label, placeholder, dan style tombol */
function updateSearchPlaceholder() {
    const isAi       = modeToggle.checked;
    const modeLabel  = document.getElementById('modeLabel');
    const inputLabel = document.getElementById('inputLabel');
    const userPrompt = document.getElementById('userPrompt');
    const btnSearch  = document.getElementById('btnSearch');

    if (isAi) {
        modeLabel.innerHTML   = '<span class="badge bg-primary"><i class="bi bi-robot"></i> Mode AI Navigator</span>';
        inputLabel.innerText  = 'Pertanyaan / Cita-cita Siswa (AI Mode):';
        userPrompt.placeholder = 'Contoh: Aku pengen jadi network engineer handal...';
        btnSearch.className   = 'btn btn-primary fw-bold px-4 btn-ai-pulse';
        btnSearch.innerHTML   = '<i class="bi bi-sparkles"></i> Cari Modul via AI Navigator';
    } else {
        modeLabel.innerHTML   = '<span class="badge bg-secondary"><i class="bi bi-search"></i> Mode Searchbox Biasa (SQL)</span>';
        inputLabel.innerText  = 'Kata Kunci Judul / Deskripsi (SQL Mode):';
        userPrompt.placeholder = 'Contoh: Subnetting, Router, LKPD...';
        btnSearch.className   = 'btn btn-secondary fw-bold px-4';
        btnSearch.innerHTML   = '<i class="bi bi-search"></i> Cari Presisi (SQL)';
    }
}

/* ============================================================
   CARI MODUL — kirim request ke backend
   ============================================================ */
function searchModeHandler() {
    const prompt  = document.getElementById('userPrompt').value;
    const tingkat = document.getElementById('userTingkat').value;
    const isAi    = modeToggle.checked;
    const output  = document.getElementById('output');

    if (!prompt.trim()) {
        alert('Isi kata kunci pencarian dulu!');
        return;
    }

    output.innerHTML = isAi
        ? '<div class="alert alert-info"><i class="bi bi-sparkles"></i> AI sedang ngelakuin sihirnya nih, bentar lagi! ✨</div>'
        : '<div class="alert alert-secondary"><i class="bi bi-search"></i> Mencari langsung di MariaDB...</div>';

    const formData = new FormData();
    formData.append('action', 'search_ai');
    formData.append('prompt', prompt);
    formData.append('tingkat', tingkat);
    formData.append('search_mode', isAi ? 'ai' : 'sql');

    fetch('ai_sandbox.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'warning') {
            output.innerHTML = `<div class="alert alert-warning">⚠️ ${data.message}</div>`;
            return;
        }
        if (data.status === 'error') {
            output.innerHTML = `<div class="alert alert-danger">❌ ${data.message}</div>`;
            return;
        }

        let debugHtml = `
            <div class="card debug-box p-3 mb-3 rounded-3">
                <div><strong>[SEARCHBOX METADATA]</strong></div>
                <div>- Active Mode: <span class="badge ${data.meta.search_mode === 'ai' ? 'bg-primary' : 'bg-secondary'}">${data.meta.search_mode.toUpperCase()}</span></div>
                ${data.meta.search_mode === 'ai' ? `<div>- AI System Mode: <span class="badge ${data.meta.ai_mode === 'ai' ? 'bg-success' : 'bg-warning text-dark'}">${data.meta.ai_mode.toUpperCase()}</span></div>` : ''}
                ${data.meta.fail_reason ? `<div>- Fallback Cause: ${data.meta.fail_reason}</div>` : ''}
                <div>- User Level: ${data.meta.tingkat_user}</div>
                <div>- Keywords Evaluated: ${JSON.stringify(data.meta.keywords)}</div>
            </div>
        `;

        let html = debugHtml;

        if (data.message) {
            html += `<div class="alert alert-success fw-bold mb-3">${data.message}</div>`;
        }

        if (!data.modules || data.modules.length === 0) {
            html += '<div class="alert alert-secondary">Yah, tidak ada modul yang cocok dengan kata kunci pencarianmu. 🥲 Coba ubah kata kuncinya!</div>';
        } else {
            html += `<h5 class="fw-bold mb-3">Ditemukan ${data.modules.length} Modul Pembelajaran:</h5>`;
            data.modules.forEach(m => {
                html += `
                    <div class="card shadow-sm border-0 mb-2">
                        <div class="card-body">
                            <h5 class="card-title fw-bold text-primary mb-1">${m.title}</h5>
                            <div class="mb-2">
                                <span class="badge bg-secondary">${m.category || 'Umum'}</span>
                                <span class="badge bg-info text-dark">${m.jenis_resource || 'modul'}</span>
                                <span class="badge bg-success">Target: ${m.kelas_target || 'Semua'}</span>
                            </div>
                            <p class="card-text text-muted small mb-2">${m.description}</p>
                            ${m.file_path ? `<a href="${m.file_path}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-arrow-down"></i> Buka Resource</a>` : ''}
                        </div>
                    </div>
                `;
            });
        }
        output.innerHTML = html;
    })
    .catch(err => {
        output.innerHTML = `<div class="alert alert-danger">Error Sistem: ${err.message}</div>`;
    });
}
</script>
</body>
</html>