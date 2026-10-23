<?php
/**
 * ai_module_preview.php — Endpoint preview draft modul AI (KHUSUS ADMIN).
 *
 * Fase 4b-1: merender draft dari $_SESSION['aimm_draft'] dengan alur
 * sanitasi yang SAMA seperti submit (aimmSanitizeHtml + aimmWrapHtml).
 * Fase 4b-2: juga mendukung preview file pending via parameter ?file=
 *
 * Header keamanan dikirim sebelum output:
 *  - Content-Type: text/html; charset=utf-8
 *  - Cache-Control: no-store
 *  - X-Content-Type-Options: nosniff
 *  - Referrer-Policy: no-referrer
 *  - Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'
 *    https://cdn.jsdelivr.net; font-src https://cdn.jsdelivr.net;
 *    img-src data:; base-uri 'none'; form-action 'none';
 *    frame-ancestors 'self'; sandbox
 *
 * CSP mengaktifkan sandbox native browser; modul dibungkus aimmWrapHtml
 * yang memuat Bootstrap dari CDN (style-src/font-src). Tidak ada <script>
 * yang diizinkan.
 */

require_once 'session.php';
require_once 'csrf_helper.php';
require_once 'ai_helper.php';
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

const AIMM_PATH = '/var/www/data_private/modul_ai/pending';

/**
 * Kirim header keamanan preview sebelum output apa pun.
 */
function aimmPreviewSecurityHeaders(string $contentType): void {
    header('Content-Type: ' . $contentType);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline' https://cdn.jsdelivr.net; font-src https://cdn.jsdelivr.net; img-src data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox");
}

// Hanya GET; metode lain -> 405
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    aimmPreviewSecurityHeaders('text/plain; charset=utf-8');
    echo 'Metode tidak diizinkan.';
    exit;
}

$html = '';
$title = 'Preview Modul';

// Mode 1: Preview dari file pending (parameter ?file=)
$fileParam = $_GET['file'] ?? '';
if ($fileParam !== '') {
    // Validasi nama file: hanya alfanumerik, underscore, dash, titik
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $fileParam)) {
        http_response_code(400);
        aimmPreviewSecurityHeaders('text/plain; charset=utf-8');
        echo 'Nama file tidak valid.';
        exit;
    }
    
    $filePath = rtrim(AIMM_PATH, '/') . '/' . $fileParam;
    
    if (!file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        aimmPreviewSecurityHeaders('text/plain; charset=utf-8');
        echo 'File tidak ditemukan.';
        exit;
    }
    
    // Baca file HTML mentah
    $rawHtml = file_get_contents($filePath);
    if ($rawHtml === false) {
        http_response_code(500);
        aimmPreviewSecurityHeaders('text/plain; charset=utf-8');
        echo 'Gagal membaca file.';
        exit;
    }
    
    // Ekstrak title dari file (dokumen penuh hasil aimmWrapHtml)
    if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $rawHtml, $m)) {
        $title = strip_tags($m[1]);
    }

    // File tersimpan adalah DOKUMEN PENUH. Ambil isi <body> saja agar saat
    // dibungkus ulang tidak terjadi duplikasi judul/container. Jika body
    // hanya berisi satu div.container (wrapper aimmWrapHtml), ambil anak-anaknya.
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $rawHtml, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $inner = '';
    $body  = $doc->getElementsByTagName('body')->item(0);
    if ($body !== null) {
        $elementChildren = [];
        foreach ($body->childNodes as $c) {
            if ($c->nodeType === XML_ELEMENT_NODE) {
                $elementChildren[] = $c;
            }
        }
        $only = count($elementChildren) === 1 ? $elementChildren[0] : null;

        if ($only !== null
            && strtolower($only->nodeName) === 'div'
            && preg_match('/(^|\s)container(\s|$)/', $only->getAttribute('class'))) {
            foreach ($only->childNodes as $c) {
                $inner .= $doc->saveHTML($c);
            }
        } else {
            foreach ($body->childNodes as $c) {
                $inner .= $doc->saveHTML($c);
            }
        }
    }

    session_write_close();

    // Sanitasi ulang untuk keamanan (meski file seharusnya sudah bersih)
    $bodyHtml = aimmSanitizeHtml($inner);
    $html = aimmWrapHtml($title, $bodyHtml);

    aimmPreviewSecurityHeaders('text/html; charset=utf-8');
    echo $html;
    exit;
}

// Mode 2: Preview dari session draft (original behavior)
$draft = $_SESSION['aimm_draft'] ?? null;

// Draft harus ada dan belum kedaluwarsa (3600 detik)
$missing = !is_array($draft) || empty($draft['html']) || empty($draft['title']);
$expired = !$missing && (time() - (int)($draft['created'] ?? 0) > 3600);

if ($missing || $expired) {
    if (isset($_SESSION['aimm_draft'])) {
        unset($_SESSION['aimm_draft']);
    }
    session_write_close();

    http_response_code(404);
    aimmPreviewSecurityHeaders('text/plain; charset=utf-8');
    echo 'Draft tidak ditemukan atau kedaluwarsa.';
    exit;
}

// Tutup session sebelum output supaya tidak terkunci selama iframe dimuat
session_write_close();

// Render dengan alur SAMA seperti submit: sanitasi fragmen -> bungkus dokumen
$bodyHtml = aimmSanitizeHtml((string)$draft['html']);
$html     = aimmWrapHtml((string)$draft['title'], $bodyHtml);

aimmPreviewSecurityHeaders('text/html; charset=utf-8');
echo $html;
