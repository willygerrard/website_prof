<?php
require_once 'session.php';
require 'koneksi.php';

checkLogin();
checkRole(['admin']);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(404);
    exit();
}

$status_filter = $_GET['status'] ?? 'unmatched';
if (!is_string($status_filter) || !in_array($status_filter, ['unmatched', 'matched', 'all'], true)) {
    $status_filter = 'unmatched';
}

$kelas_filter = $_GET['kelas'] ?? '';
if (!is_string($kelas_filter)) {
    $kelas_filter = '';
}

$search = $_GET['q'] ?? '';
if (!is_string($search)) {
    $search = '';
}
$search = trim(mb_substr($search, 0, 100));

$requested_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = is_int($requested_page) && $requested_page > 0 ? $requested_page : 1;
$page_size = 25;

$stats = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN cocok_otomatis = 0 THEN 1 ELSE 0 END) AS unmatched
     FROM kuis_jawaban_isian"
)->fetch(PDO::FETCH_ASSOC);
$total_responses = (int)($stats['total'] ?? 0);
$unmatched_responses = (int)($stats['unmatched'] ?? 0);

$class_stmt = $pdo->query(
    "SELECT DISTINCT kelas
     FROM users
     WHERE kelas IS NOT NULL AND kelas <> ''
     ORDER BY kelas"
);
$classes = $class_stmt->fetchAll(PDO::FETCH_COLUMN);

$from_sql = "
    FROM kuis_jawaban_isian j
    LEFT JOIN kuis_hasil h ON h.id = j.hasil_id
    LEFT JOIN users u ON u.id = h.user_id
    LEFT JOIN kuis_soal s ON s.id = j.soal_id
";
$conditions = [];
$params = [];

if ($status_filter === 'unmatched') {
    $conditions[] = 'j.cocok_otomatis = 0';
} elseif ($status_filter === 'matched') {
    $conditions[] = 'j.cocok_otomatis = 1';
}

if ($kelas_filter !== '') {
    $conditions[] = 'u.kelas = ?';
    $params[] = $kelas_filter;
}

if ($search !== '') {
    $conditions[] = "(
        COALESCE(u.nama_asli, '') LIKE ?
        OR COALESCE(u.username, '') LIKE ?
        OR COALESCE(s.pertanyaan, '') LIKE ?
        OR j.jawaban_siswa LIKE ?
        OR COALESCE(h.kategori, '') LIKE ?
    )";
    $search_term = '%' . $search . '%';
    array_push($params, $search_term, $search_term, $search_term, $search_term, $search_term);
}

$where_sql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$count_stmt = $pdo->prepare('SELECT COUNT(*) ' . $from_sql . $where_sql);
$count_stmt->execute($params);
$filtered_total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($filtered_total / $page_size));
$page = min($page, $total_pages);
$offset = ($page - 1) * $page_size;

$response_sql = "
    SELECT j.id, j.jawaban_siswa, j.cocok_otomatis, j.dicatat_at,
           h.kategori, h.level, h.skor, h.attempt, h.sesi_id,
           u.nama_asli, u.username, u.kelas,
           s.pertanyaan, s.materi,
           (
               SELECT GROUP_CONCAT(a.jawaban_alternatif ORDER BY a.id SEPARATOR ' / ')
               FROM kuis_soal_alternatif_isian a
               WHERE a.soal_id = j.soal_id
           ) AS jawaban_diterima
    " . $from_sql . $where_sql . "
    ORDER BY j.dicatat_at DESC, j.id DESC
           LIMIT ? OFFSET ?
";
$response_stmt = $pdo->prepare($response_sql);
foreach ($params as $index => $value) {
    $response_stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
}
$response_stmt->bindValue(count($params) + 1, $page_size, PDO::PARAM_INT);
$response_stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$response_stmt->execute();
$responses = $response_stmt->fetchAll(PDO::FETCH_ASSOC);

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$pagination_params = [
    'status' => $status_filter,
    'kelas' => $kelas_filter,
    'q' => $search,
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Jawaban Isian - Pusat Pembelajaran SIJA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php
    $hero_title = 'Review Jawaban Isian';
    $hero_subtitle = 'Tinjau jawaban siswa yang belum cocok otomatis';
    include __DIR__ . '/includes/admin_header.php';
    ?>

    <main class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 fw-bold mb-0">📝 Review Jawaban Isian</h2>
            <a href="pintu-rahasia-sija" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Kembali ke Soal
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total jawaban tersimpan</div>
                        <div class="fs-3 fw-bold"><?= $total_responses ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Belum cocok otomatis</div>
                        <div class="fs-3 fw-bold text-warning"><?= $unmatched_responses ?></div>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" class="row g-2 align-items-end bg-white p-3 rounded-3 shadow-sm border mb-4">
            <div class="col-md-3">
                <label for="status" class="form-label small fw-semibold">Status jawaban</label>
                <select class="form-select" id="status" name="status">
                    <option value="unmatched" <?= $status_filter === 'unmatched' ? 'selected' : '' ?>>Belum cocok otomatis</option>
                    <option value="matched" <?= $status_filter === 'matched' ? 'selected' : '' ?>>Cocok otomatis</option>
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua jawaban</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="kelas" class="form-label small fw-semibold">Kelas</label>
                <select class="form-select" id="kelas" name="kelas">
                    <option value="">Semua kelas</option>
                    <?php foreach ($classes as $kelas): ?>
                        <option value="<?= $escape($kelas) ?>" <?= $kelas_filter === $kelas ? 'selected' : '' ?>>
                            <?= $escape($kelas) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="q" class="form-label small fw-semibold">Cari siswa, soal, atau jawaban</label>
                <input class="form-control" id="q" name="q" type="search" maxlength="100" value="<?= $escape($search) ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small">
                Menampilkan <?= $filtered_total === 0 ? 0 : $offset + 1 ?>–<?= min($offset + $page_size, $filtered_total) ?>
                dari <?= $filtered_total ?> jawaban
            </span>
            <span class="text-muted small">Halaman <?= $page ?> dari <?= $total_pages ?></span>
        </div>

        <div class="table-responsive bg-white rounded-3 shadow-sm border">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Siswa</th>
                        <th>Kuis</th>
                        <th>Pertanyaan & Jawaban yang Diterima</th>
                        <th>Jawaban Siswa</th>
                        <th>Status</th>
                        <th>Dicatat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$responses): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">Tidak ada jawaban yang sesuai dengan filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($responses as $response): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= $escape($response['nama_asli'] ?: $response['username'] ?: 'Akun tidak tersedia') ?></div>
                                    <div class="small text-muted">
                                        <?= $escape($response['kelas'] ?: 'Kelas tidak tersedia') ?>
                                        <?php if (!empty($response['username'])): ?>
                                            · <?= $escape($response['username']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?= $escape($response['kategori'] ?: 'Kuis tidak tersedia') ?></div>
                                    <div class="small text-muted">
                                        <?= $escape($response['level'] ?: '-') ?>
                                        · Attempt <?= (int)($response['attempt'] ?? 0) ?>
                                        <?php if ($response['skor'] !== null): ?>
                                            · Nilai <?= (int)$response['skor'] ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?= nl2br($escape($response['pertanyaan'] ?: 'Pertanyaan tidak tersedia')) ?></div>
                                    <div class="small text-success mt-1">
                                        Diterima: <?= $escape($response['jawaban_diterima'] ?: 'Belum ada alternatif tersimpan') ?>
                                    </div>
                                    <?php if (!empty($response['materi'])): ?>
                                        <div class="small text-muted">Materi: <?= $escape($response['materi']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-break"><?= nl2br($escape($response['jawaban_siswa'])) ?></td>
                                <td>
                                    <?php if ((int)$response['cocok_otomatis'] === 1): ?>
                                        <span class="badge bg-success">Cocok otomatis</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Perlu ditinjau</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap small"><?= $escape($response['dicatat_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="mt-3" aria-label="Navigasi halaman jawaban">
                <ul class="pagination justify-content-center">
                    <?php
                    $first_page = max(1, $page - 2);
                    $last_page = min($total_pages, $page + 2);
                    ?>
                    <?php if ($page > 1): ?>
                        <?php
                        $pagination_params['page'] = $page - 1;
                        $previous_url = '?' . http_build_query($pagination_params);
                        ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $escape($previous_url) ?>" aria-label="Halaman sebelumnya">&laquo;</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($target_page = $first_page; $target_page <= $last_page; $target_page++): ?>
                        <?php
                        $pagination_params['page'] = $target_page;
                        $page_url = '?' . http_build_query($pagination_params);
                        ?>
                        <li class="page-item <?= $target_page === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $escape($page_url) ?>"><?= $target_page ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <?php
                        $pagination_params['page'] = $page + 1;
                        $next_url = '?' . http_build_query($pagination_params);
                        ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $escape($next_url) ?>" aria-label="Halaman berikutnya">&raquo;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </main>
</body>
</html>
