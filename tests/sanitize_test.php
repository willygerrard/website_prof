<?php
/**
 * tests/sanitize_test.php — CLI test untuk aimmSanitizeHtml() (tanpa framework).
 * Jalankan: php tests/sanitize_test.php
 */
require __DIR__ . '/../ai_helper.php';

$failures = 0;
$total    = 0;

function check(string $name, string $out, array $mustNotContain, array $mustContain = []): void {
    global $failures, $total;
    $total++;
    $errors = [];

    foreach ($mustNotContain as $needle) {
        if (stripos($out, $needle) !== false) {
            $errors[] = "masih mengandung '$needle'";
        }
    }
    foreach ($mustContain as $needle) {
        if (stripos($out, $needle) === false) {
            $errors[] = "kehilangan '$needle'";
        }
    }

    if ($errors) {
        $failures++;
        echo "[GAGAL] $name\n";
        foreach ($errors as $e) echo "        - $e\n";
        echo "        output: " . substr($out, 0, 200) . "\n";
    } else {
        echo "[LULUS] $name\n";
    }
}

// 1. img dengan atribut event dipisah spasi parsing ("<img/src=x/onerror=...>")
check(
    'img/src onerror',
    aimmSanitizeHtml('<img src=x onerror=alert(1)>'),
    ['onerror', 'alert(1)']
);

// 2. svg onload
check(
    'svg onload',
    aimmSanitizeHtml('<svg onload=alert(1)></svg>'),
    ['svg', 'onload', 'alert(1)']
);

// 3. href javascript dengan entity + tab
check(
    'a href javascript entity',
    aimmSanitizeHtml('<a href=" jav&#x09;ascript:alert(1)">klik</a>'),
    ['javascript:', 'alert(1)']
);

// 4. base href
check(
    'base href',
    aimmSanitizeHtml('<base href=//evil.test/>'),
    ['base', 'evil.test']
);

// 5. style @import
check(
    'style @import',
    aimmSanitizeHtml('<style>@import url("//evil.test/x.css");</style>'),
    ['style', '@import', 'evil.test']
);

// 6. nested script injection — tag <script> yang terselubung harus hilang;
//    sisa teks "alert(1)" yang ter-escape (teks biasa) tidak berbahaya.
check(
    'nested script injection',
    aimmSanitizeHtml('<scr<script>ipt>alert(1)</scr</script>ipt>'),
    ['<script', '<scr', 'onerror=']
);

// 10. style attribute & on* dibuang, tag tak dikenal di-unwrap (isinya tetap)
check(
    'unwrap tag tak dikenal + buang style/on*',
    aimmSanitizeHtml('<article style="color:red" data-x="1"><p onclick="alert(1)">Isi <mark>materi</mark></p></article>'),
    ['style=', 'onclick', 'data-x', '<article', '<mark'],
    ['<p>Isi', 'materi']
);

// 7. iframe
check(
    'iframe',
    aimmSanitizeHtml('<iframe src="https://evil.test/x"></iframe>'),
    ['iframe', 'evil.test']
);

// 8. form
check(
    'form',
    aimmSanitizeHtml('<form action="https://evil.test/steal"><input name=q></form>'),
    ['<form', '<input', 'evil.test']
);

// 9. HTML normal harus tetap utuh
$normal = <<<'HTML'
<h2>Tujuan Pembelajaran</h2>
<p>Siswa mampu <strong>memahami subnetting</strong> dengan <em>benar</em>.</p>
<ul><li>Item satu</li><li>Item dua</li></ul>
<table class="table"><thead><tr><th>Alamat</th></tr></thead><tbody><tr><td>192.168.1.1</td></tr></tbody></table>
<pre><code>ip a
ping 192.168.1.1</code></pre>
<p><a href="https://example.com/docs">Referensi</a></p>
HTML;
check(
    'html normal utuh',
    aimmSanitizeHtml($normal),
    [],
    ['<h2>', '<strong>', '<em>', '<ul>', '<table class="table">', '<pre>', '<code>', '192.168.1.1', 'rel="noopener noreferrer"']
);

echo "\n";
if ($failures > 0) {
    echo "HASIL: $failures dari $total test GAGAL\n";
    exit(1);
}
echo "HASIL: semua $total test LULUS\n";
