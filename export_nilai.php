<?php
require_once 'session.php';
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

const KKM = 75;

$kelas_filter    = $_GET['kelas'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$level_filter    = $_GET['level'] ?? '';

// Header Download File Excel (.xls)
$nama_file = "rekap_nilai_terstruktur_" . ($kelas_filter ? "kelas_" . str_replace(' ', '_', $kelas_filter) . "_" : "") . date('Y-m-d') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=$nama_file");
header("Pragma: no-cache");
header("Expires: 0");

// Query Data
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
// GROUPING DATA & MEMBUAT MASTER SESI (KUNCI KOLOM)
// =========================================================
$grouped_data = [];
$all_sessions = []; // Array untuk menyimpan semua sesi unik

foreach ($raw_data as $row) {
    $uid = $row['user_id'];
    $tgl_sesi = $row['sesi_dibuka'] ? date('d M Y', strtotime($row['sesi_dibuka'])) : 'Riwayat lama';
    $raw_tgl = $row['sesi_dibuka'] ?: '1970-01-01'; // Untuk sorting
    
    // Buat kunci unik untuk sesi ini (prioritaskan sesi_id, jika null pakai hash materi+tanggal)
    $session_key = $row['sesi_id'] ?: md5($row['materi'] . $raw_tgl);

    // 1. Simpan info sesi ke Master Sesi (Jika belum ada)
    if (!isset($all_sessions[$session_key])) {
        $all_sessions[$session_key] = [
            'materi' => $row['materi'] ?? '-',
            'tanggal' => $tgl_sesi,
            'kategori' => $row['kategori'],
            'level' => ucfirst($row['level']),
            'raw_tgl' => $raw_tgl
        ];
    }

    // 2. Inisialisasi data siswa
    if (!isset($grouped_data[$uid])) {
        $grouped_data[$uid] = [
            'kelas'      => $row['kelas'] ?? '-',
            'nama_siswa' => !empty($row['nama_asli']) ? $row['nama_asli'] : $row['username'],
            'username'   => $row['username'],
            'kuis_map'   => [] // Ubah dari list ke map berdasarkan session_key
        ];
    }
    
    // Tentukan Status
    if ($row['nilai_terbaik'] >= KKM) {
        $status = 'Lulus';
    } elseif ($row['total_attempt'] >= 4) {
        $status = 'Tidak Lulus';
    } else {
        $status = 'Belum Tuntas';
    }

    // 3. Simpan nilai siswa berdasarkan kunci sesi
    $grouped_data[$uid]['kuis_map'][$session_key] = [
        'nilai'  => $row['nilai_terbaik'],
        'status' => $status
    ];
}

// 4. URUTKAN MASTER SESI BERDASARKAN TANGGAL
// Agar kolom "Materi 1, Materi 2, dst" berurutan secara kronologis
uasort($all_sessions, function($a, $b) {
    return strtotime($a['raw_tgl']) <=> strtotime($b['raw_tgl']);
});

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        th { background-color: #0f172a; color: #ffffff; border: 1px solid #000000; font-weight: bold; padding: 8px; text-align: center; }
        td { border: 1px solid #cccccc; padding: 6px; vertical-align: middle; }
        .text-center { text-align: center; }
        .mso-number { mso-number-format:"\@"; }
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
                
                <!-- Generate Header Kolom Berdasarkan Master Sesi -->
                <?php $col = 1; foreach ($all_sessions as $session): ?>
                    <th>Materi <?= $col ?></th>
                    <th>Tgl <?= $col ?></th>
                    <th>Nilai <?= $col ?></th>
                    <th>Status <?= $col ?></th>
                <?php $col++; endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grouped_data as $siswa): 
                // Ambil Kategori & Level dari ujian pertama siswa (asumsi 1 kelas sama)
                $first_session = reset($all_sessions);
                $kategori_utama = $first_session['kategori'] ?? '-';
                $level_utama    = $first_session['level'] ?? '-';
            ?>
                <tr>
                    <td class="text-center"><?= htmlspecialchars($siswa['kelas']) ?></td>
                    <td><strong><?= htmlspecialchars($siswa['nama_siswa']) ?></strong></td>
                    <td class="mso-number text-center"><?= htmlspecialchars($siswa['username']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($kategori_utama) ?></td>
                    <td class="text-center"><?= htmlspecialchars($level_utama) ?></td>

                    <!-- CETAK NILAI BERDASARKAN MASTER SESI -->
                    <?php foreach ($all_sessions as $session_key => $session): ?>
                        <?php if (isset($siswa['kuis_map'][$session_key])): ?>
                            <!-- Jika siswa mengerjakan sesi ini -->
                            <td><?= htmlspecialchars($session['materi']) ?></td>
                            <td class="text-center"><?= $session['tanggal'] ?></td>
                            <td class="text-center" style="font-weight:bold;"><?= $siswa['kuis_map'][$session_key]['nilai'] ?></td>
                            <td class="text-center"><?= $siswa['kuis_map'][$session_key]['status'] ?></td>
                        <?php else: ?>
                            <!-- Jika siswa SKIP / tidak mengerjakan sesi ini, isi dengan strip -->
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>