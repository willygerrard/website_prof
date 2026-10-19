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

