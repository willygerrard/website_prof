<?php
/**
 * seed_data.php
 * Membuat data dummy di database STAGING.
 *
 * Sifat: IDEMPOTEN. Setiap kali dijalankan, data dummy lama (bertanda
 * dummy_% / [DUMMY]%) dihapus dulu, lalu dibuat ulang. Data asli TIDAK disentuh.
 *
 * Jalankan (dari host):
 *   bash seed/run.sh seed_data.php
 * atau langsung di dalam container php:
 *   docker exec -e APP_ENV=staging website_prof_staging php /var/www/html/seed/seed_data.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("seed_data.php hanya untuk CLI, bukan lewat URL.\n");
}

require __DIR__ . '/seed_common.php';

// =====================================================================
// Konfigurasi dummy (ubah sesuai kebutuhan)
// =====================================================================
const SEED_STUDENT_COUNT = 6;
const SEED_PASSWORD      = 'dummy12345';   // password login siswa dummy
const SEED_RANDOM_SEED   = 20261002;       // agar nilai acak stabil tiap run

$kelas_list = ['X TKJ 1', 'X TKJ 2', 'X TKJ 3', 'X TKJ 4', 'XI SIJA', 'XII SIJA', 'XIII SIJA'];

// =====================================================================

$pdo = get_pdo();
mt_srand(SEED_RANDOM_SEED);

$cfg = seed_config();
echo "=== Seed data dummy LMS ===\n";
echo "Target : {$cfg['user']}@{$cfg['host']}:{$cfg['port']}/{$cfg['db']}\n";

// 1. Bersihkan dummy lama supaya tidak menumpuk (idempoten).
echo "Membersihkan dummy lama...\n";
$purged = purge_all_dummy($pdo);
$purged_total = array_sum($purged);
echo "  $purged_total baris lama dihapus.\n\n";

/** Helper insert sederhana -> lastInsertId. */
$insert = function (string $table, array $data) use ($pdo): int {
    $cols = array_keys($data);
    $ph   = array_map(static fn($c) => ':' . $c, $cols);
    $sql  = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $ph) . ')';
    $stmt = $pdo->prepare($sql);
    foreach ($data as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
    return (int)$pdo->lastInsertId();
};

$report = [];
$now = date('Y-m-d H:i:s');

begin_transaction($pdo);
try {
    // -------------------------------------------------------------
    // 2. USERS (siswa dummy)
    // -------------------------------------------------------------
    $student_ids = [];
    for ($i = 1; $i <= SEED_STUDENT_COUNT; $i++) {
        $num = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $student_ids[] = $insert('users', [
            'username'   => 'dummy_siswa' . $num,
            'nama_asli'  => 'Siswa Dummy ' . $num,
            'password'   => hash_password(SEED_PASSWORD),
            'no_wa_ortu' => '08120000' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
            'kelas'      => $kelas_list[($i - 1) % count($kelas_list)],
            'role'       => 'siswa',
            'status'     => 'aktif',
            'created_at' => $now,
        ]);
    }
    $report['users'] = count($student_ids);

    // -------------------------------------------------------------
    // 3. MODULES + checkpoint
    // -------------------------------------------------------------
    $modules = [
        [
            'title'       => DUMMY_TITLE_PREFIX . ' Dasar Jaringan Komputer',
            'category'    => 'Network',
            'description' => 'Modul dummy pengenalan topologi, IP addressing, dan perangkat jaringan.',
            'file_path'   => 'uploads/dummy/modul_dasar_jaringan.pdf',
            'image_path'  => null,
            'kelas_target'=> 'semua',
            'checkpoint'  => [
                'pertanyaan' => DUMMY_TITLE_PREFIX . ' Perangkat yang menghubungkan dua jaringan berbeda adalah?',
                'opsi_a' => 'Switch', 'opsi_b' => 'Router', 'jawaban_benar' => 'b',
            ],
        ],
        [
            'title'       => DUMMY_TITLE_PREFIX . ' Administrasi Sistem Linux',
            'category'    => 'System Administration',
            'description' => 'Modul dummy perintah dasar Linux, user management, dan service.',
            'file_path'   => 'uploads/dummy/modul_linux.pdf',
            'image_path'  => null,
            'kelas_target'=> 'XI',
            'checkpoint'  => [
                'pertanyaan' => DUMMY_TITLE_PREFIX . ' Perintah untuk melihat daftar direktori di Linux adalah?',
                'opsi_a' => 'ls', 'opsi_b' => 'dir', 'jawaban_benar' => 'a',
            ],
        ],
    ];

    $module_ids = [];
    $checkpoint_by_module = [];
    foreach ($modules as $m) {
        $module_ids[] = $insert('modules', [
            'title'         => $m['title'],
            'category'      => $m['category'],
            'jenis_resource'=> 'modul',
            'description'   => $m['description'],
            'file_path'     => $m['file_path'],
            'image_path'    => $m['image_path'],
            'kelas_target'  => $m['kelas_target'],
            'created_at'    => $now,
        ]);
    }
    $report['modules'] = count($module_ids);

    foreach ($module_ids as $idx => $mid) {
        $cp = $modules[$idx]['checkpoint'];
        $checkpoint_by_module[$mid] = $insert('checkpoint_modul', [
            'modul_id'      => $mid,
            'pertanyaan'    => $cp['pertanyaan'],
            'opsi_a'        => $cp['opsi_a'],
            'opsi_b'        => $cp['opsi_b'],
            'jawaban_benar' => $cp['jawaban_benar'],
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }
    $report['checkpoint_modul'] = count($checkpoint_by_module);

    // -------------------------------------------------------------
    // 4. TUGAS + tugas_kelas
    // -------------------------------------------------------------
    $tugas_defs = [
        [
            'judul'       => DUMMY_TITLE_PREFIX . ' Latihan IP Addressing',
            'deskripsi'   => 'Kerjakan konversi IP dan subnetting, kumpulkan dalam bentuk PDF.',
            'folder_path' => 'uploads/dummy/tugas_ip_addressing',
            'kelas'       => ['X TKJ 1', 'X TKJ 2'],
        ],
        [
            'judul'       => DUMMY_TITLE_PREFIX . ' Praktik Instalasi Linux',
            'deskripsi'   => 'Dokumentasikan langkah instalasi Linux pada virtual machine.',
            'folder_path' => 'uploads/dummy/tugas_linux',
            'kelas'       => ['XI SIJA', 'XII SIJA'],
        ],
    ];

    $tugas_ids = [];
    $tugas_kelas_map = [];
    foreach ($tugas_defs as $t) {
        $tid = $insert('tugas', [
            'judul'       => $t['judul'],
            'deskripsi'   => $t['deskripsi'],
            'folder_path' => $t['folder_path'],
            'created_at'  => $now,
            'deadline'    => date('Y-m-d H:i:s', strtotime('+7 days')),
            'link_lkpd'   => null,
        ]);
        $tugas_ids[] = $tid;
        $tugas_kelas_map[$tid] = $t['kelas'];
        foreach ($t['kelas'] as $k) {
            $insert('tugas_kelas', ['tugas_id' => $tid, 'kelas' => $k]);
        }
    }
    $report['tugas'] = count($tugas_ids);

    // -------------------------------------------------------------
    // 5. KUIS: soal (pilgan + isian) + alternatif
    // -------------------------------------------------------------
    $pilgan = [
        ['Perangkat yang menghubungkan dua jaringan berbeda adalah...', 'Switch', 'Router', 'Hub', 'Repeater', 'b'],
        ['Alamat IP 192.168.1.1 termasuk kelas...', 'A', 'B', 'C', 'D', 'c'],
        ['Protokol yang menerjemahkan nama domain ke alamat IP adalah...', 'DHCP', 'DNS', 'FTP', 'SMTP', 'b'],
        ['Subnet mask default untuk kelas C adalah...', '255.0.0.0', '255.255.0.0', '255.255.255.0', '255.255.255.255', 'c'],
    ];
    $isian = [
        ['Sebutkan kepanjangan dari DHCP', ['Dynamic Host Configuration Protocol', 'Dynamic Host Config Protocol']],
        ['Berapa jumlah host maksimal pada subnet /24?', ['254', '254 host']],
    ];

    $soal_pilgan_ids = [];
    foreach ($pilgan as $s) {
        $soal_pilgan_ids[] = $insert('kuis_soal', [
            'kategori'    => 'Network',
            'pertanyaan'  => DUMMY_TITLE_PREFIX . ' ' . $s[0],
            'pilihan_a'   => $s[1],
            'pilihan_b'   => $s[2],
            'pilihan_c'   => $s[3],
            'pilihan_d'   => $s[4],
            'jawaban'     => $s[5],
            'level'       => 'pemula',
            'jenis_soal'  => 'pilgan',
            'materi'      => 'Dasar Jaringan',
        ]);
    }

    $soal_isian_ids = [];
    foreach ($isian as $s) {
        $sid = $insert('kuis_soal', [
            'kategori'    => 'Network',
            'pertanyaan'  => DUMMY_TITLE_PREFIX . ' ' . $s[0],
            'pilihan_a'   => null,
            'pilihan_b'   => null,
            'pilihan_c'   => null,
            'pilihan_d'   => null,
            'jawaban'     => null,
            'level'       => 'pemula',
            'jenis_soal'  => 'isian',
            'materi'      => 'Dasar Jaringan',
        ]);
        $soal_isian_ids[] = $sid;
        foreach ($s[1] as $alt) {
            $insert('kuis_soal_alternatif_isian', ['soal_id' => $sid, 'jawaban_alternatif' => $alt]);
        }
    }
    $report['kuis_soal'] = count($soal_pilgan_ids) + count($soal_isian_ids);

    // -------------------------------------------------------------
    // 6. KUIS SESI + kelas/materi target
    // -------------------------------------------------------------
    $sesi_id = $insert('kuis_sesi', [
        'kategori'    => DUMMY_KATEGORI_PREFIX . ' Network',
        'level'       => 'pemula',
        'materi'      => 'Dasar Jaringan',
        'status'      => 'aktif',
        'durasi_menit'=> 30,
        'dibuka_at'   => $now,
        'ditutup_at'  => null,
    ]);
    foreach (['X TKJ 1', 'X TKJ 2'] as $k) {
        $insert('kuis_sesi_kelas', ['sesi_id' => $sesi_id, 'kelas' => $k]);
    }
    $insert('kuis_sesi_materi', ['sesi_id' => $sesi_id, 'materi' => 'Dasar Jaringan']);
    $report['kuis_sesi'] = 1;

    // -------------------------------------------------------------
    // 7. HASIL KUIS + jawaban isian
    // -------------------------------------------------------------
    $hasil_count = 0;
    $jawaban_count = 0;
    $total_soal = count($soal_pilgan_ids) + count($soal_isian_ids);

    foreach ($student_ids as $uid) {
        $hasil_id = $insert('kuis_hasil', [
            'user_id'      => $uid,
            'kategori'     => 'Network',
            'skor'         => mt_rand(60, 100),
            'total_soal'   => $total_soal,
            'durasi_detik' => mt_rand(120, 600),
            'tab_switch'   => mt_rand(0, 1),
            'dikerjakan_at'=> $now,
            'level'        => 'pemula',
            'sesi_id'      => $sesi_id,
            'attempt'      => 1,
        ]);
        $hasil_count++;

        foreach ($soal_isian_ids as $i => $sid) {
            $benar = mt_rand(0, 1) === 1;
            $insert('kuis_jawaban_isian', [
                'hasil_id'       => $hasil_id,
                'soal_id'        => $sid,
                'jawaban_siswa'  => $benar ? $isian[$i][1][0] : 'Jawaban dummy salah',
                'cocok_otomatis' => $benar ? 1 : 0,
                'dicatat_at'     => $now,
            ]);
            $jawaban_count++;
        }
    }
    $report['kuis_hasil'] = $hasil_count;
    $report['kuis_jawaban_isian'] = $jawaban_count;

    // -------------------------------------------------------------
    // 8. CHECKPOINT HASIL
    // -------------------------------------------------------------
    $cp_count = 0;
    foreach ($student_ids as $uid) {
        foreach ($checkpoint_by_module as $mid => $cp_id) {
            $insert('checkpoint_hasil', [
                'user_id'            => $uid,
                'modul_id'           => $mid,
                'modul_checkpoint_id'=> $cp_id,
                'is_correct'         => mt_rand(0, 1),
                'processed_at'       => $now,
            ]);
            $cp_count++;
        }
    }
    $report['checkpoint_hasil'] = $cp_count;

    // -------------------------------------------------------------
    // 9. PENGUMPULAN TUGAS (hanya untuk kelas yang cocok)
    // -------------------------------------------------------------
    $kelas_by_user = [];
    foreach ($student_ids as $uid) {
        $row = seed_row($pdo, 'SELECT kelas FROM users WHERE id = ?', [$uid]);
        $kelas_by_user[$uid] = $row['kelas'] ?? '';
    }

    $kumpul_count = 0;
    foreach ($student_ids as $uid) {
        foreach ($tugas_ids as $tid) {
            if (!in_array($kelas_by_user[$uid], $tugas_kelas_map[$tid], true)) {
                continue;
            }
            $dinilai = mt_rand(0, 1) === 1;
            $insert('pengumpulan_tugas', [
                'tugas_id'    => $tid,
                'user_id'     => $uid,
                'file_path'   => 'uploads/dummy/kumpul/tugas' . $tid . '_user' . $uid . '.pdf',
                'uploaded_at' => $now,
                'nilai'       => $dinilai ? mt_rand(70, 98) : null,
                'catatan_guru'=> $dinilai ? 'Kerja bagus, pertahankan.' : null,
                'dinilai_at'  => $dinilai ? $now : null,
            ]);
            $kumpul_count++;
        }
    }
    $report['pengumpulan_tugas'] = $kumpul_count;

    // -------------------------------------------------------------
    // 10. NOTIFIKASI MODUL
    // -------------------------------------------------------------
    $notif_count = 0;
    foreach ($student_ids as $uid) {
        foreach ($module_ids as $mid) {
            $insert('notifikasi_modul', [
                'user_id'     => $uid,
                'modul_id'    => $mid,
                'terkirim_at' => $now,
            ]);
            $notif_count++;
        }
    }
    $report['notifikasi_modul'] = $notif_count;

    // -------------------------------------------------------------
    // 11. GAMES
    // -------------------------------------------------------------
    $insert('games', [
        'title'       => DUMMY_TITLE_PREFIX . ' Tebak Perangkat Jaringan',
        'description' => 'Game dummy untuk mengenali perangkat jaringan.',
        'category'    => 'Teknologi & Jaringan',
        'image_path'  => null,
        'file_path'   => 'games_data/dummy_game.html',
        'created_at'  => $now,
    ]);
    $report['games'] = 1;

    commit_transaction($pdo);
} catch (Throwable $e) {
    rollback_transaction($pdo);
    fwrite(STDERR, "GAGAL seed: " . $e->getMessage() . "\n");
    exit(1);
}

// =====================================================================
// Laporan
// =====================================================================
echo "=== Selesai. Baris dibuat per tabel ===\n";
foreach ($report as $table => $count) {
    printf("  %-22s %d\n", $table, $count);
}
echo "\nLogin siswa dummy: dummy_siswa01 .. dummy_siswa" . str_pad((string)SEED_STUDENT_COUNT, 2, '0', STR_PAD_LEFT)
   . " / password: " . SEED_PASSWORD . "\n";
