<?php
/**
 * ai_helper.php — Shared helper untuk fitur AI (Gemini).
 * Diekstrak dari ai_quiz_generate.php agar bisa dipakai
 * ai_quiz_generate.php, ai_module_maker.php, dll.
 */

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


/**
 * Panggil Gemini dengan JSON schema. Backward compatible:
 * $maxOutputTokens dan $maxTries opsional di akhir (default = perilaku lama).
 *
 * Throws RuntimeException dengan pesan "Output AI terpotong, coba generate
 * ulang" bila respons berakhir karena MAX_TOKENS / JSON tak bisa di-parse.
 */
function geminiJson(
    string $key,
    string $model,
    string $text,
    array $schema,
    int $timeout,
    float $temp,
    int $maxOutputTokens = 0,
    int $maxTries = 2
): array {
    $genConfig = [
        'response_mime_type' => 'application/json',
        'response_schema'    => $schema,
        'temperature'        => $temp,
    ];
    if ($maxOutputTokens > 0) {
        $genConfig['maxOutputTokens'] = $maxOutputTokens;
    }

    $payload = [
        'contents'         => [['parts' => [['text' => $text]]]],
        'generationConfig' => $genConfig,
    ];
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

    $resp = '';
    $code = 0;
    $err  = '';
    for ($try = 1; $try <= max(1, $maxTries); $try++) {
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
        if ($try < $maxTries && in_array($code, [429, 503], true)) { sleep(2); continue; }
        throw new RuntimeException($err ?: "HTTP $code: " . mb_substr((string)$resp, 0, 300));
    }

    $data         = json_decode((string)$resp, true);
    $cand         = $data['candidates'][0] ?? [];
    $txt          = $cand['content']['parts'][0]['text'] ?? null;
    $finishReason = $cand['finishReason'] ?? null;

    if ($finishReason === 'MAX_TOKENS') {
        throw new RuntimeException('Output AI terpotong, coba generate ulang');
    }

    $out = $txt ? json_decode($txt, true) : null;
    if (!is_array($out)) {
        // JSON tidak valid tapi finishReason bukan MAX_TOKENS — bisa saja
        // respons terpotong tanpa flag; laporkan sebagai truncation bila ada
        // teks yang gagal di-parse, bukan sekadar respons kosong.
        if (is_string($txt) && trim($txt) !== '' && json_decode($txt) === null) {
            throw new RuntimeException('Output AI terpotong atau tidak valid, coba generate ulang');
        }
        throw new RuntimeException('Respons AI bukan JSON yang valid');
    }
    return $out;
}

// ============================================================
// SANITASI HTML MODUL AI (allowlist DOMDocument)
// ============================================================

const AIMM_ALLOWED_TAGS = [
    'h1','h2','h3','h4','h5','h6','p','br','hr','ul','ol','li','strong','b','em','i','u',
    'code','pre','blockquote','table','thead','tbody','tr','th','td','a','img','div','span',
    'section','small','sup','sub',
];

// Tag berbahaya dibuang BESERTA isinya; tag lain di luar allowlist di-unwrap.
const AIMM_DANGEROUS_TAGS = [
    'script','style','iframe','object','embed','form','base','link','meta','svg','math',
];

function aimmAllowedAttrs(): array {
    return [
        'a'   => ['href', 'title', 'class'],
        'img' => ['src', 'alt', 'width', 'height', 'class'],
        '*'   => ['class'],
    ];
}

/**
 * Normalisasi nilai atribut URL: decode entity, buang whitespace & karakter
 * kontrol (untuk menangkal "jav&#x09;ascript:" dsb), lalu cek skema.
 */
function aimmNormalizeUrl(string $val): string {
    $decoded = html_entity_decode(trim($val), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = preg_replace('/[\x00-\x20\x7F]+/', '', $decoded);
    return strtolower($decoded);
}

/**
 * Sanitasi FRAGMEN HTML (bukan dokumen penuh) pakai allowlist DOMDocument.
 * - Tag berbahaya (script, style, iframe, object, embed, form, base, link,
 *   meta, svg, math) dibuang beserta isinya.
 * - Tag di luar allowlist lain di-unwrap (isinya dipertahankan).
 * - Atribut dibatasi allowlist; href hanya http/https/mailto/#, src img hanya
 *   https atau data:image/png|jpeg|gif|webp. Semua atribut on* dan style dibuang.
 * - <a> selalu mendapat rel="noopener noreferrer".
 * - Tidak menambahkan <html>/<body> — keluaran tetap fragmen.
 */
function aimmSanitizeHtml(string $html): string {
    $allowed  = AIMM_ALLOWED_TAGS;
    $allowedL = array_map('strtolower', $allowed);
    $danger   = array_map('strtolower', AIMM_DANGEROUS_TAGS);
    $attrMap  = aimmAllowedAttrs();

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    // Trick encoding UTF-8: prefix XML declaration agar tidak dianggap ISO-8859-1.
    $ok = $doc->loadHTML(
        '<?xml encoding="utf-8"?><div id="aimm-root">' . $html . '</div>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();
    if (!$ok) {
        return ''; // gagal parse -> buang semua (fail closed)
    }

    $root = $doc->getElementById('aimm-root');
    if ($root === null) return '';

    $clean = function (DOMNode $parent) use ($doc, &$clean, $allowedL, $danger, $attrMap) {
        // Snapshot dulu — childNodes berubah saat modifikasi
        $children = [];
        foreach ($parent->childNodes as $c) $children[] = $c;

        foreach ($children as $node) {
            if ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);
                continue;
            }
            if ($node->nodeType !== XML_ELEMENT_NODE) continue;

            $tag = strtolower($node->nodeName);

            // 1) Tag berbahaya: buang beserta seluruh isi
            if (in_array($tag, $danger, true)) {
                $parent->removeChild($node);
                continue;
            }

            // 2) Tag di luar allowlist: unwrap — proses isinya, angkat ke atas
            if (!in_array($tag, $allowedL, true)) {
                $clean($node);
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            // 3) Tag diizinkan: filter atribut
            $tagAttrs = $attrMap[$tag] ?? $attrMap['*'];
            $attrs = [];
            foreach ($node->attributes as $a) $attrs[] = $a->nodeName;
            foreach ($attrs as $name) {
                $lname = strtolower($name);
                if (!in_array($lname, $tagAttrs, true)) {
                    $node->removeAttribute($name); // termasuk semua on* dan style
                    continue;
                }
                if ($tag === 'a' && $lname === 'href') {
                    $val = aimmNormalizeUrl($node->getAttribute($name));
                    if (!preg_match('@^(https?://|mailto:|#)@', $val)) {
                        $node->removeAttribute($name);
                    }
                }
                if ($tag === 'img' && $lname === 'src') {
                    $val = aimmNormalizeUrl($node->getAttribute($name));
                    if (!preg_match('#^(https://|data:image/(png|jpeg|gif|webp);)#', $val)) {
                        $node->removeAttribute($name);
                    }
                }
            }
            if ($tag === 'a') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }

            // 4) Rekursi ke anak
            $clean($node);
        }
    };

    $clean($root);

    // Serialisasi hanya anak-anak root — fragmen, tanpa wrapper <div>
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

/**
 * Bungkus fragmen yang SUDAH disanitasi menjadi dokumen HTML standalone
 * dengan Bootstrap 5 (versi dipin) untuk disimpan ke disk / dirender iframe.
 */
function aimmWrapHtml(string $title, string $bodyHtml): string {
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$titleEsc}</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
{$bodyHtml}
</div>
</body>
</html>
HTML;
}
