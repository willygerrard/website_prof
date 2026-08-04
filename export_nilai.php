<?php
include 'koneksi.php';
session_start();

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 404 Not Found");
    exit();
}

const KKM = 75;

$kelas_filter    = $_GET['kelas'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$level_filter    = $_GET['level'] ?? '';

// Header Download File Excel (.xls)
$nama_file = "rekap_nilai_terstruk_" . ($kelas_filter ? "kelas_" . str_replace(' ', '_', $kelas_filter) . "_" : "") . date('Y-m-d') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=$nama_file");
header("Pragma: no-cache");
header("Expires: 0");

// Query Ringkasan Nilai Kuis per Siswa
$sql = "SELECT users.id as user_id, users.username, users.nama_asli, users.kelas,
               kuis_hasil.kategori, kuis_hasil.level, kuis_hasil.sesi_id,
               ks.dibuka_at AS sesi_dibuka,
               MAX(kuis_hasil.skor) as nilai_terbaik,
               COUNT(*) as total_attempt,
               (SELECT GROUP_CONCAT(materi SEPARATOR ', ') FROM kuis_sesi_materi WHERE sesi_id = ks.id) AS materi
        FROM kuis_hasil
        JOIN users ON kuis_hasil.user_id = users.id
        LEFT JOIN kuis_sesi ks ON kuis_hasil.sesi_id = ks.id
        WHERE 1=1";
$params = [];

if ($kelas_filter)    { $sql .= " AND users.kelas = ?"; $params[] = $kelas_filter; }
if ($kategori_filter) { $sql .= " AND kuis_hasil.kategori = ?"; $params[] = $kategori_filter; }
if ($level_filter)    { $sql .= " AND kuis_hasil.level = ?"; $params[] = $level_filter; }

$sql .= " GROUP BY users.id, kuis_hasil.kategori, kuis_hasil.level, kuis_hasil.sesi_id
          ORDER BY users.kelas ASC, users.nama_asli ASC, users.username ASC, ks.dibuka_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================================================
// GROUPING DATA BERDASARKAN USER_ID (Biar Nama Cukup Tampil 1x)
// =========================================================
$grouped_data = [];
foreach ($raw_data as $row) {
    $uid = $row['user_id'];
    if (!isset($grouped_data[$uid])) {
        $grouped_data[$uid] = [
            'kelas'      => $row['kelas'] ?? '-',
            'nama_siswa' => !empty($row['nama_asli']) ? $row['nama_asli'] : $row['username'],
            'username'   => $row['username'],
            'kuis_list'  => []
        ];
    }
    
    // Tentukan Status Lulus/Belum Tuntas
    if ($row['nilai_terbaik'] >= KKM) {
        $status = 'Lulus';
    } elseif ($row['total_attempt'] >= 4) {
        $status = 'Tidak Lulus';
    } else {
        $status = 'Belum Tuntas';
    }

    $tgl_sesi = $row['sesi_dibuka'] ? date('d M Y', strtotime($row['sesi_dibuka'])) : 'Riwayat lama';

    $grouped_data[$uid]['kuis_list'][] = [
        'kategori'      => $row['kategori'],
        'level'         => ucfirst($row['level']),
        'materi'        => $row['materi'] ?? '-',
        'tanggal'       => $tgl_sesi,
        'nilai_terbaik' => $row['nilai_terbaik'],
        'status'        => $status
    ];
}
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        th { background-color: #0f172a; color: #ffffff; border: 1px solid #000000; font-weight: bold; padding: 8px; text-align: center; }
        td { border: 1px solid #cccccc; padding: 6px; vertical-align: middle; }
        .text-center { text-align: center; }
        .mso-number { mso-number-format:"\@"; } /* Teks murni */
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>Kelas</th>
                <th>Nama Siswa</th>
                <th>Username</th>
                <th>Kategori</th>
                <th>Level</th>
                <th>Materi</th>
                <th>Tanggal Sesi</th>
                <th>Nilai Terbaik</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grouped_data as $siswa): 
                $kuis_items = $siswa['kuis_list'];
                $total_kuis = count($kuis_items);
            ?>
                <?php foreach ($kuis_items as $index => $k): ?>
                <tr>
                    <!-- CETAK KELAS, NAMA, & USERNAME HANYA PADA BARIS PERTAMA (PAKAI ROWSPAN) -->
                    <?php if ($index === 0): ?>
                        <td class="text-center" rowspan="<?= $total_kuis ?>"><?= htmlspecialchars($siswa['kelas']) ?></td>
                        <td rowspan="<?= $total_kuis ?>"><strong><?= htmlspecialchars($siswa['nama_siswa']) ?></strong></td>
                        <td class="mso-number text-center" rowspan="<?= $total_kuis ?>"><?= htmlspecialchars($siswa['username']) ?></td>
                    <?php endif; ?>

                    <!-- KOLOM KUIS (AKAN MENYESUAIKAN BANYAKNYA KUIS YANG DIIKUTI SISWA) -->
                    <td><?= htmlspecialchars($k['kategori']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($k['level']) ?></td>
                    <td><?= htmlspecialchars($k['materi']) ?></td>
                    <td class="text-center"><?= $k['tanggal'] ?></td>
                    <td class="text-center" style="font-weight:bold;"><?= $k['nilai_terbaik'] ?></td>
                    <td class="text-center"><?= $k['status'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>