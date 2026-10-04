<?php
require_once 'session.php';
require 'koneksi.php';

checkLogin();

// Set header JSON agar response mudah dibaca oleh JavaScript
header('Content-Type: application/json');

$user_id  = $_SESSION['user_id'] ?? null;
$modul_id = (int)($_GET['modul_id'] ?? 0);

if (!$user_id || !$modul_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit();
}

try {
    // PERBAIKAN: Tambahkan tuntas_at = NOW()
    $stmt = $pdo->prepare("INSERT IGNORE INTO modul_tuntas (user_id, modul_id, tuntas_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $modul_id]);
    
    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    http_response_code(500);
    // Jangan tampilkan error detail ke user di production, cukup log di server
    error_log("Gagal simpan tuntas: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
exit();