<?php
// export_tugas.php - Rekap Nilai Tugas
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("Akses ditolak.");
}
include 'koneksi.php';

$tugasId = (int)($_GET['tugas_id'] ?? 0);
if ($tugasId <= 0) die("ID Tugas tidak valid!");

// 1. Detail tugas + kelas target
$stmtTugas = $pdo->prepare("
    SELECT t.id, t.judul, t.deskripsi, t.deadline,
           GROUP_CONCAT(DISTINCT tk.kelas ORDER BY tk.kelas SEPARATOR ', ') AS kelas_target
    FROM tugas t
    LEFT JOIN tugas_kelas tk ON t.id = tk.tugas_id
    WHERE t.id = ?
    GROUP BY t.id
");
$stmtTugas->execute([$tugasId]);
$tugas = $stmtTugas->fetch(PDO::FETCH_ASSOC);
if (!$tugas) die("Tugas tidak ditemukan!");

// 2. Daftar pengumpulan (FIX nama kolom)
$stmtPengumpulan = $pdo->prepare("
    SELECT COALESCE(NULLIF(TRIM(u.nama_asli), ''), u.username) AS nama_tampil,
           u.kelas,
           pt.uploaded_at AS waktu_kumpul,
           pt.nilai,
           pt.catatan_guru
    FROM pengumpulan_tugas pt
    JOIN users u ON pt.user_id = u.id
    WHERE pt.tugas_id = ?
    ORDER BY u.kelas ASC, nama_tampil ASC
");
$stmtPengumpulan->execute([$tugasId]);
$daftarNilai = $stmtPengumpulan->fetchAll(PDO::FETCH_ASSOC);

// 3. Hitung ringkasan
$totalKumpul  = count($daftarNilai);
$sudahDinilai = array_filter($daftarNilai, fn($r) => $r['nilai'] !== null);
$rataRata     = count($sudahDinilai) > 0
              ? round(array_sum(array_column($sudahDinilai, 'nilai')) / count($sudahDinilai), 2)
              : 0;

// 4. Header download
$cleanJudul = trim(preg_replace('/[^a-zA-Z0-9_]+/', '_', $tugas['judul']), '_');
$filename   = "Nilai_" . $cleanJudul . ".xls";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000000; padding: 6px 10px; }
        th { background-color: #0d6efd; color: #ffffff; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .info { margin-bottom: 4px; }
    </style>
</head>
<body>

    <h3>REKAP NILAI TUGAS: <?= htmlspecialchars($tugas['judul']) ?></h3>
    <p class="info"><b>Kelas Target:</b> <?= htmlspecialchars($tugas['kelas_target'] ?: 'Semua') ?></p>
    <?php if (!empty($tugas['deadline'])): ?>
        <p class="info"><b>Deadline:</b> <?= date('d/m/Y H:i', strtotime($tugas['deadline'])) ?></p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 30%;">Nama Siswa</th>
                <th style="width: 12%;">Kelas</th>
                <th style="width: 18%;">Waktu Pengumpulan</th>
                <th style="width: 10%;">Nilai</th>
                <th style="width: 25%;">Catatan Guru</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($daftarNilai)): ?>
                <tr>
                    <td colspan="6" class="text-center">Belum ada siswa yang mengumpulkan tugas ini.</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($daftarNilai as $row): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nama_tampil']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['kelas'] ?? '-') ?></td>
                        <td class="text-center">
                            <?= $row['waktu_kumpul'] ? date('d/m/Y H:i', strtotime($row['waktu_kumpul'])) : '-' ?>
                        </td>
                        <td class="text-center text-bold" style="font-size: 11pt;">
                            <?= $row['nilai'] !== null ? $row['nilai'] : '-' ?>
                        </td>
                        <td><?= htmlspecialchars($row['catatan_guru'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background-color: #f0f0f0; font-weight: bold;">
                    <td colspan="4" class="text-center">TOTAL & RATA-RATA</td>
                    <td class="text-center"><?= $rataRata ?></td>
                    <td class="text-center"><?= $totalKumpul ?> siswa mengumpulkan</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>