<?php
// ============================================================
// ai_sandbox.php — AI Learning Navigator
// - Handler AJAX di atas (untuk POST search_ai)
// - Widget HTML di bawah (bisa di-include index.php)
// - Bisa juga diakses standalone untuk testing
// ============================================================

require_once 'koneksi.php';
if (session_status() === PHP_SESSION_NONE) {
    require_once 'session.php';
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// ============================================================
// HANDLER AJAX: POST action=search_ai → JSON
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_ai') {
    header('Content-Type: application/json');

    $userPrompt  = trim($_POST['prompt'] ?? '');
    $userTingkat = trim($_POST['tingkat'] ?? ($_SESSION['tingkat'] ?? 'X'));
    $searchMode  = trim($_POST['search_mode'] ?? 'ai');

    if (empty($userPrompt)) {
        echo json_encode(['status' => 'error', 'message' => 'Kata kunci kosong nih. Tulis dulu apa yang mau dicari.']);
        exit;
    }

    // ---------- MODE SQL (presisi) ----------
    if ($searchMode === 'sql') {
        $whereConditions = [];
        $queryParams     = [];

        $whereConditions[] = "(
            kelas_target IS NULL OR kelas_target = ''
            OR LOWER(kelas_target) = 'semua'
            OR kelas_target LIKE ? OR kelas_target LIKE ? OR kelas_target LIKE ?
        )";
        $queryParams[] = "%{$userTingkat}%";
        $queryParams[] = "{$userTingkat},%";
        $queryParams[] = "%,{$userTingkat}";

        $whereConditions[] = "(LOWER(title) LIKE ? OR LOWER(description) LIKE ? OR LOWER(category) LIKE ?)";
        $queryParams[]     = "%" . strtolower($userPrompt) . "%";
        $queryParams[]     = "%" . strtolower($userPrompt) . "%";
        $queryParams[]     = "%" . strtolower($userPrompt) . "%";

        $sql = "SELECT id, title, category, jenis_resource, description, file_path, image_path, kelas_target
                FROM modules
                WHERE " . implode(" AND ", $whereConditions) . "
                ORDER BY id DESC LIMIT 20";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($queryParams);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Menampilkan hasil pencarian presisi.',
            'meta'    => ['tingkat_user' => $userTingkat, 'search_mode' => 'sql', 'keywords' => [$userPrompt]],
            'modules' => $modules
        ]);
        exit;
    }

    // ---------- MODE AI ----------
    $dbCategories = $pdo->query("SELECT DISTINCT category FROM modules WHERE category IS NOT NULL AND category <> ''")->fetchAll(PDO::FETCH_COLUMN);
    $catListStr   = "'" . implode("', '", $dbCategories) . "'";

    if (!getenv('GEMINI_API_KEY') && file_exists(__DIR__ . '/.env')) {
        $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($envLines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            [$name, $value] = explode('=', $line, 2);
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
    . "6. 'cross_level_note': Tulislah 1 kalimat saran/pesan motivasi santai jika topik yang dicari siswa ({$userTingkat}) biasanya diajarkan di tingkat yang lebih tinggi.\n"
    . "7. 'difficulty': String representing the difficulty level of the topic (e.g., 'dasar', 'menengah', 'lanjut') based on the user's input and the extracted keywords/category.\n\n"
    . "Format JSON Wajib: {\"keywords\": [...], \"category\": string|null, \"jenis_resource\": string|null, \"cross_level_note\": string|null, \"difficulty\": string}";

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
        // Retry logic + IPv4 force untuk hindari timeout intermitten
        $maxAttempts = 2;
        $response    = false;
        $httpCode    = 0;
        $curlErr     = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if (!$curlErr && $httpCode === 200) break;

            // Rate limit / server error → coba lagi setelah jeda
            if ($attempt < $maxAttempts && ($curlErr || in_array($httpCode, [429, 503], true))) {
                sleep(2);
                continue;
            }
            break;
        }

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

    // ---------- FALLBACK ENGINE ----------
    $aiMode = 'ai';
    if ($aiFailed) {
        $aiMode = 'fallback';
        $stopwords = ['aku','saya','mau','ingin','pengen','belajar','tentang','apa','yang','cara','bagaimana','dong','tolong','sih','ya','yg','jadi','gimana','belajarnya','nih','itu','ini','dan','atau','buat','untuk','dari','ke','di','dengan','ada','bisa','kalau','kalo','punya','cita'];
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
        // Set default difficulty for fallback mode
        $aiResult['difficulty'] = 'menengah';
    } else {
        if (isset($aiResult['error'])) {
            echo json_encode(['status' => 'warning', 'message' => 'Hmm, topik ini di luar lingkup pelajaran IT/SIJA nih. 🤔 Coba cari topik IT lainnya ya!']);
            exit;
        }
        $extractedKeywords = $aiResult['keywords'] ?? [];
        $targetCategory    = $aiResult['category'] ?? null;
        $targetJenis       = $aiResult['jenis_resource'] ?? null;
        // Ensure difficulty is set even if AI didn't provide it
        if (!isset($aiResult['difficulty'])) {
            $aiResult['difficulty'] = 'menengah';
        }
    }

    // ---------- QUERY KE MARIADB ----------
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
            foreach (explode(' ', trim($kw)) as $w) {
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
            FROM modules {$whereClauseStr} ORDER BY id DESC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---------- SORT MODULES BY DIFFICULTY (AI Step 7) ----------
    // AI returns difficulty: 'dasar', 'menengah', 'lanjut'
    // Sort modules from basic to advanced (left to right)
    $aiDifficulty = $aiResult['difficulty'] ?? null;
    if (!empty($aiDifficulty) && is_array($modules) && count($modules) > 0) {
        $difficultyOrder = ['dasar' => 1, 'menengah' => 2, 'lanjut' => 3];
        $aiDifficultyKey = strtolower(trim($aiDifficulty));
        
        // If AI provided a specific difficulty, we can prioritize modules
        // by checking if module title/description matches the difficulty level
        // For now, we'll use a simple heuristic based on keywords in title/description
        usort($modules, function($a, $b) use ($aiDifficultyKey, $difficultyOrder) {
            $getModuleDifficulty = function($module) {
                $text = strtolower(($module['title'] ?? '') . ' ' . ($module['description'] ?? '') . ' ' . ($module['category'] ?? ''));
                // Basic keywords
                if (preg_match('/\b(dasar|pemula|intro|pengenalan|basic|fundamental|awal|mula)\b/', $text)) return 1;
                // Advanced keywords
                if (preg_match('/\b(lanjut|advanced|mahir|expert|professional|kompleks|complex|tinggi)\b/', $text)) return 3;
                // Intermediate keywords
                if (preg_match('/\b(menengah|intermediate|lanjutan|level 2|level ii)\b/', $text)) return 2;
                return 2; // Default to intermediate
            };
            
            $diffA = $getModuleDifficulty($a);
            $diffB = $getModuleDifficulty($b);
            
            // Sort by difficulty: dasar (1) → menengah (2) → lanjut (3)
            return $diffA <=> $diffB;
        });
    }

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
            'fail_reason'     => $aiFailReason,
            'difficulty'      => $aiResult['difficulty'] ?? null
        ],
        'modules' => $modules
    ]);
    exit;
}

// ============================================================
// DETEKSI: di-include index.php atau diakses standalone?
// ============================================================
$is_embedded = (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__));
?>

<?php if (!$is_embedded): ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Sandbox — Testing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 900px;">
    <div class="alert alert-info">
        <strong>Mode Testing Standalone.</strong> Widget di bawah ini biasanya muncul di dalam <code>index.php</code>.
        Saat embedded, hasil modul akan menggantikan grid modul — di sini tidak ada grid, jadi cuma widget-nya.
    </div>
<?php endif; ?>

<!-- ============================================================
     WIDGET AI SANDBOX
     ============================================================ -->
<style>
    .search-card {
        position: relative;
        overflow: hidden;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        transition: border-color 0.45s ease, box-shadow 0.45s ease;
        will-change: box-shadow, border-color;
    }
    .search-card.ai-active {
        border-color: #0d6efd;
        animation: aiPulse 3s ease-in-out infinite;
    }
    @keyframes aiPulse {
        0%, 100% { box-shadow: 0 0 0 2px rgba(13,110,253,0.12), 0 0 20px rgba(13,110,253,0.22), inset 0 0 12px rgba(13,110,253,0.04); }
        50%      { box-shadow: 0 0 0 3px rgba(13,110,253,0.22), 0 0 32px rgba(111,66,193,0.32), inset 0 0 18px rgba(13,110,253,0.08); }
    }
    .ai-gradient-bar {
        position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, #0d6efd, #6f42c1, #d63384, #0dcaf0, #0d6efd);
        background-size: 300% 100%;
        animation: gradientShift 5s linear infinite;
        opacity: 0; transition: opacity 0.5s ease;
        z-index: 4; pointer-events: none;
    }
    .search-card.ai-active .ai-gradient-bar { opacity: 1; }
    @keyframes gradientShift { 0% { background-position: 0% 50%; } 100% { background-position: 300% 50%; } }

    .ai-ripple {
        position: absolute; border-radius: 50%; transform: scale(0);
        pointer-events: none; z-index: 0;
        animation: rippleExpand 0.9s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
    }
    .ai-ripple.ripple-ai  { background: radial-gradient(circle, rgba(13,110,253,0.35) 0%, rgba(111,66,193,0.18) 40%, transparent 72%); }
    .ai-ripple.ripple-sql { background: radial-gradient(circle, rgba(108,117,125,0.30) 0%, rgba(108,117,125,0.12) 40%, transparent 72%); }
    @keyframes rippleExpand { 0% { transform: scale(0); opacity: 0.85; } 100% { transform: scale(1); opacity: 0; } }

    .ai-sweep {
        position: absolute; top: -50%; left: -120%; width: 80%; height: 200%;
        background: linear-gradient(115deg, transparent 0%, rgba(255,255,255,0) 25%, rgba(13,110,253,0.18) 45%, rgba(111,66,193,0.28) 50%, rgba(13,110,253,0.18) 55%, rgba(255,255,255,0) 75%, transparent 100%);
        pointer-events: none; z-index: 1;
        animation: sweepAcross 1.1s ease-out forwards;
    }
    @keyframes sweepAcross { 0% { left: -120%; } 100% { left: 120%; } }

    .search-card .card-body { position: relative; z-index: 2; }

    .form-switch .form-check-input {
        transform: scale(1.3); cursor: pointer;
        transition: background-color 0.35s ease, box-shadow 0.35s ease;
    }
    .form-switch .form-check-input:checked { box-shadow: 0 0 0 4px rgba(13,110,253,0.18); }

    .btn-ai-pulse { animation: btnPulse 2.4s ease-in-out infinite; }
    @keyframes btnPulse {
        0%, 100% { box-shadow: 0 0 0 0    rgba(13,110,253,0.45); }
        50%      { box-shadow: 0 0 0 10px rgba(13,110,253,0);    }
    }

    .search-card.ai-active #inputLabel::after {
        content: ' ✨';
        animation: sparkle 1.6s ease-in-out infinite;
        display: inline-block;
    }
    @keyframes sparkle {
        0%, 100% { transform: scale(1)   rotate(0deg);  opacity: 1;   }
        50%      { transform: scale(1.2) rotate(15deg); opacity: 0.7; }
    }

    .debug-box { background: #1e1e1e; color: #00ff66; font-family: monospace; font-size: 12px; }
    .debug-box summary { color: #00ff66; cursor: pointer; }

    @media (prefers-reduced-motion: reduce) {
        .search-card.ai-active, .btn-ai-pulse,
        .search-card.ai-active #inputLabel::after, .ai-gradient-bar { animation: none !important; }
    }
</style>

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
                    <option value="XIII">Kelas XIII</option>
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

<div id="aiDebug"></div>

<script>
/* ============================================================
   AI SEARCH WIDGET — hasil render ke #modulGrid (bukan list terpisah)
   ============================================================ */
const searchCard = document.getElementById('searchCard');
const modeToggle = document.getElementById('modeToggle');

// Simpan HTML grid asli untuk fitur reset
let originalGridHTML = null;
window.addEventListener('DOMContentLoaded', function () {
    const gridEl = document.getElementById('modulGrid');
    if (gridEl) originalGridHTML = gridEl.innerHTML;
});

// XSS-safe escape
function esc(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

// Render kartu modul — SAMA style dengan grid index.php
function renderModuleCards(modules) {
    if (!modules || modules.length === 0) {
        return '<div class="col-12 text-center"><p class="text-muted">Yah, tidak ada modul yang cocok. 🥲 Coba ubah kata kuncinya.</p></div>';
    }
    let html = '';
    modules.forEach(m => {
        const imgSrc = m.image_path || 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg';
        html += `
            <div class="col mb-5">
                <div class="card h-100 shadow-sm">
                    <div class="badge bg-dark text-white position-absolute" style="top: 0.5rem; right: 0.5rem">
                        ${esc(m.category || 'Umum')}
                    </div>
                    <img class="card-img-top" src="${esc(imgSrc)}" alt="Ikon Modul" />
                    <div class="card-body p-4">
                        <div class="text-center">
                            <h5 class="fw-bolder">${esc(m.title)}</h5>
                            <p class="text-muted small mt-2">${esc(m.description || '')}</p>
                            <span class="badge bg-info text-dark">${esc(m.jenis_resource || 'modul')}</span>
                            <span class="badge bg-success">Target: ${esc(m.kelas_target || 'Semua')}</span>
                        </div>
                    </div>
                    <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-dark fw-bold buka-modul-btn"
                               href="${esc(m.file_path)}"
                               target="_blank" rel="noopener noreferrer"
                               data-id="${parseInt(m.id)}">
                                📂 Buka Modul
                            </a>
                            <a class="btn btn-success fw-bold cekpoint-btn disabled"
                               id="cekpoint-${parseInt(m.id)}"
                               style="pointer-events:none;" href="#">
                                ⏳ Buka dan Baca Modul Terlebih Dahulu
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    return html;
}

function resetModulGrid() {
    const gridEl  = document.getElementById('modulGrid');
    const debugEl = document.getElementById('aiDebug');
    const ta      = document.getElementById('userPrompt');
    if (gridEl && originalGridHTML !== null) gridEl.innerHTML = originalGridHTML;
    if (debugEl) debugEl.innerHTML = '';
    if (ta) ta.value = '';
}

/* ---------- TOGGLE + ANIMASI ---------- */
modeToggle.addEventListener('change', function () {
    const isAi = this.checked;
    triggerRipple(searchCard, modeToggle, isAi);
    triggerSweep(searchCard);
    setTimeout(() => {
        searchCard.classList.toggle('ai-active', isAi);
        updateSearchPlaceholder();
    }, 80);
});

function triggerRipple(parent, originEl, isAi) {
    const parentRect = parent.getBoundingClientRect();
    const originRect = originEl.getBoundingClientRect();
    const cx = originRect.left + originRect.width  / 2;
    const cy = originRect.top  + originRect.height / 2;
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

function triggerSweep(parent) {
    const sweep = document.createElement('div');
    sweep.className = 'ai-sweep';
    parent.appendChild(sweep);
    setTimeout(() => sweep.remove(), 1200);
}

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

/* ---------- SEARCH HANDLER — render ke #modulGrid ---------- */
function searchModeHandler() {
    const prompt  = document.getElementById('userPrompt').value;
    const tingkat = document.getElementById('userTingkat').value;
    const isAi    = modeToggle.checked;
    const debugEl = document.getElementById('aiDebug');
    const gridEl  = document.getElementById('modulGrid');

    if (!prompt.trim()) { alert('Isi kata kunci dulu ya!'); return; }
    if (!gridEl) { console.warn('#modulGrid tidak ditemukan, hasil cuma di debug.'); }

    // Loading
    debugEl.innerHTML = isAi
        ? '<div class="alert alert-info text-center mb-3"><i class="bi bi-sparkles"></i> AI sedang mencari modul, bentar ya... ✨</div>'
        : '<div class="alert alert-secondary text-center mb-3"><i class="bi bi-search"></i> Mencari di MariaDB...</div>';

    if (gridEl) {
        gridEl.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
    }

    const formData = new FormData();
    formData.append('action', 'search_ai');
    formData.append('prompt', prompt);
    formData.append('tingkat', tingkat);
    formData.append('search_mode', isAi ? 'ai' : 'sql');

    fetch('ai_sandbox.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        debugEl.innerHTML = '';

        if (data.status === 'warning' || data.status === 'error') {
            debugEl.innerHTML = `<div class="alert alert-${data.status === 'warning' ? 'warning' : 'danger'}">${esc(data.message)}</div>`;
            if (gridEl) gridEl.innerHTML = '<div class="col-12 text-center"><p class="text-muted">Tidak ada hasil.</p></div>';
            return;
        }

        // Debug collapsible + reset button + message
        let debugHtml = `
            <div class="debug-box p-3 mb-3 rounded-3">
                <details>
                    <summary>🔧 Detail AI Search (klik untuk lihat)</summary>
                    <div class="mt-2" style="font-size: 11px;">
                        <div>Mode: <span class="badge ${data.meta.search_mode === 'ai' ? 'bg-primary' : 'bg-secondary'}">${data.meta.search_mode.toUpperCase()}</span>
                        ${data.meta.search_mode === 'ai' ? ` | AI: <span class="badge ${data.meta.ai_mode === 'ai' ? 'bg-success' : 'bg-warning text-dark'}">${data.meta.ai_mode.toUpperCase()}</span>` : ''}</div>
                        ${data.meta.fail_reason ? `<div>Fallback: ${esc(data.meta.fail_reason)}</div>` : ''}
                        <div>User Level: ${esc(data.meta.tingkat_user)}</div>
                        <div>Keywords: ${esc(JSON.stringify(data.meta.keywords))}</div>
                        ${data.meta.difficulty ? `<div>Difficulty: <span class="badge bg-info text-dark">${esc(data.meta.difficulty)}</span> (Urutan: Dasar → Menengah → Lanjut)</div>` : ''}
                    </div>
                </details>
            </div>
        `;
        if (data.message) {
            debugHtml += `<div class="alert alert-success fw-bold mb-3">${esc(data.message)}</div>`;
        }
        debugHtml += `<div class="text-center mb-3">
            <button onclick="resetModulGrid()" class="btn btn-sm btn-outline-secondary">
                ✕ Reset & Tampilkan Semua Modul
            </button>
        </div>`;
        debugEl.innerHTML = debugHtml;

        // Ganti isi grid dengan hasil
        if (gridEl) gridEl.innerHTML = renderModuleCards(data.modules);
    })
    .catch(err => {
        debugEl.innerHTML = `<div class="alert alert-danger">Error: ${esc(err.message)}</div>`;
        if (gridEl) gridEl.innerHTML = '<div class="col-12 text-center"><p class="text-muted">Terjadi kesalahan.</p></div>';
    });
}
</script>

<?php if (!$is_embedded): ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php endif; ?>