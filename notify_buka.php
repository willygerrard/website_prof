<?php
// Endpoint ringan: dipanggil via fetch() dari index.php saat tombol "Buka Modul" diklik.
// Tugasnya cuma catat + kirim notifikasi WA ke ortu, tidak render HTML apa pun.

include 'koneksi.php';
require_once 'fonnte.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Belum login']);
    exit();
}

$user_id  = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? '';
$modul_id = (int) ($_GET['id'] ?? 0);

if (!$user_id || !$modul_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit();
}

// Ambil data modul (untuk judul di pesan WA)
$stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
$stmt->execute([$modul_id]);
$modul = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$modul) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Modul tidak ditemukan']);
    exit();
}

$cek_sesi = $pdo->query("SELECT status FROM notifikasi_sesi ORDER BY id DESC LIMIT 1")->fetchColumn();

// Notifikasi WA (logika sama persis seperti sebelumnya di buka_modul.php)
if ($cek_sesi === 'aktif') {
    $cek = $pdo->prepare("SELECT id FROM notifikasi_modul WHERE user_id = ? AND modul_id = ?");
    $cek->execute([$user_id, $modul_id]);

    if (!$cek->fetch()) {
        try {
            $insert = $pdo->prepare("INSERT INTO notifikasi_modul (user_id, modul_id, terkirim_at) VALUES (?, ?, NOW())");
            $insert->execute([$user_id, $modul_id]);

            $stmt_wa = $pdo->prepare("SELECT no_wa_ortu FROM users WHERE id = ?");
            $stmt_wa->execute([$user_id]);
            $no_wa = $stmt_wa->fetchColumn();

            if ($no_wa) {
                $tanggal = date('d M Y, H:i');
                $pesan = "📚 *Pusat Pembelajaran SIJA*\n\n" . htmlspecialchars($username) . " membuka modul:\n*" . htmlspecialchars($modul['title']) . "*\n\nPada: $tanggal";
                kirimWA($no_wa, $pesan);
            }
        } catch (PDOException $e) {
            error_log("Notifikasi modul gagal: " . $e->getMessage());
        }
    }
}

echo json_encode(['status' => 'ok']);
exit();