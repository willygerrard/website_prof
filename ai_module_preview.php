<?php
/**
 * ai_module_preview.php — Endpoint preview draft modul AI (KHUSUS ADMIN).
 *
 * Fase 4b-1: merender draft dari $_SESSION['aimm_draft'] dengan alur
 * sanitasi yang SAMA seperti submit (aimmSanitizeHtml + aimmWrapHtml).
 * Tidak ada logika generate/submit; hanya preview read-only.
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

checkLogin();
checkRole(['admin']);

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

// Ambil draft dari session
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
