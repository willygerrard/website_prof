<?php
/**
 * seed_clean.php
 * Menghapus HANYA data dummy (users dummy_%, modules/tugas/kuis/games [DUMMY]%)
 * beserta seluruh turunannya. Data asli tidak disentuh.
 *
 * Jalankan (dari host):
 *   bash seed/run.sh seed_clean.php
 * atau di dalam container php:
 *   docker exec -e APP_ENV=staging website_prof_staging php /var/www/html/seed/seed_clean.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("seed_clean.php hanya untuk CLI, bukan lewat URL.\n");
}

require __DIR__ . '/seed_common.php';

$pdo = get_pdo();
$cfg = seed_config();

echo "=== Hapus data dummy ===\n";
echo "Target : {$cfg['user']}@{$cfg['host']}:{$cfg['port']}/{$cfg['db']}\n\n";

$report = purge_all_dummy($pdo);

echo "=== Laporan hapus per tabel ===\n";
foreach ($report as $table => $count) {
    printf("  %-24s %d\n", $table, $count);
}
echo "\nTotal baris terhapus: " . array_sum($report) . "\n";
echo "=== Selesai ===\n";
