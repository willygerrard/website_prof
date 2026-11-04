<?php
/**
 * includes/sidebar.php — navigasi utama berbentuk sidebar, menggantikan includes/navbar.php.
 *
 * Syarat sebelum di-include:
 *   - session sudah dimulai dan user sudah login ($_SESSION['role'] terisi)
 *   - $pdo tersedia (dari koneksi.php)
 *   - Bootstrap 5 JS dan Bootstrap Icons sudah dimuat di halaman (seperti di index.php)
 *
 * Opsional, di-set oleh halaman SEBELUM include:
 *   $sidebar_active  string  paksa menu aktif (nama file tanpa .php, mis. 'management_tugas')
 *   $link_tugas      string  link pengumpulan tugas; kalau belum ada dihitung di sini
 *
 * Pemakaian di halaman (body dari head.php sudah d-flex flex-column min-vh-100):
 *   <div class="sj-shell flex-grow-1">
 *       <?php include __DIR__ . '/includes/sidebar.php'; ?>
 *       <main class="sj-main"> ...isi halaman... </main>
 *   </div>
 */

if (!isset($_SESSION['role'])) {
    return;
}

$sj_role     = $_SESSION['role'];
$sj_is_admin = ($sj_role === 'admin');
$sj_is_siswa = ($sj_role === 'siswa');

// --- Link pengumpulan tugas (sama persis dengan fallback di navbar.php) ---------------
if (!isset($link_tugas)) {
    $link_tugas_default   = 'https://acesse.one/3xcdcbh';
    $link_tugas_per_kelas = [
        'X TKJ 1'  => 'https://tinyurl.com/4fhc6pkh',
        'X TKJ 2'  => 'https://tinyurl.com/utfrsmta',
        'X TKJ 3'  => 'https://tinyurl.com/8wz4d5ym',
        'X TKJ 4'  => 'https://tinyurl.com/4hk9vwwh',
        'XI SIJA'  => 'https://tinyurl.com/4awhwbfh',
        'XII SIJA' => 'https://tinyurl.com/2ztnwwyd',
    ];

    $link_tugas = $link_tugas_default;
    if ($sj_is_siswa && isset($pdo)) {
        $user_id_navbar = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
        if ($user_id_navbar) {
            $stmtKelasNavbar = $pdo->prepare("SELECT kelas FROM users WHERE id = ?");
            $stmtKelasNavbar->execute([$user_id_navbar]);
            $kelas_navbar = $stmtKelasNavbar->fetchColumn();
            if ($kelas_navbar && isset($link_tugas_per_kelas[$kelas_navbar])) {
                $link_tugas = $link_tugas_per_kelas[$kelas_navbar];
            }
        }
    }
}

// --- Menu aktif: dari nama file (SCRIPT_NAME) atau path URL (untuk rute "pintu-..." dkk) ---
$sj_cur   = [];
$sj_cur[] = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
$sj_cur[] = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '.php');
if (!empty($sidebar_active)) {
    $sj_cur = [$sidebar_active];
}

// --- Jumlah modul AI yang menunggu review (khusus admin) --------------------------------
$sj_pending = 0;
if ($sj_is_admin && isset($pdo)) {
    try {
        $sj_pending = (int) $pdo->query("SELECT COUNT(*) FROM modules WHERE status = 'pending'")->fetchColumn();
    } catch (Throwable $e) {
        $sj_pending = 0;
    }
}

// --- Definisi menu ---------------------------------------------------------------------
$sj_menu = [];

if ($sj_is_admin) {
    $sj_top = [];
    // Item Dashboard baru muncul setelah dashboard_admin.php benar-benar ada.
    $sj_has_dashboard = is_file(dirname(__DIR__) . '/dashboard_admin.php');
    if ($sj_has_dashboard) {
        $sj_top[] = ['label' => 'Dashboard', 'href' => 'dashboard_admin.php', 'icon' => 'grid', 'match' => ['dashboard_admin']];
    }
    $sj_top[] = ['label' => 'Daftar Materi', 'href' => 'index.php', 'icon' => 'book', 'match' => ['index']];
    $sj_menu[] = ['title' => null, 'items' => $sj_top];

    $sj_menu[] = ['title' => 'Konten', 'items' => [
        ['label' => 'Manage Module',     'href' => 'gerbang-rahasia-sija', 'icon' => 'collection',       'match' => ['gerbang-rahasia-sija', 'edit_modul', 'tambah_modul']],
        ['label' => 'Modul Pending (AI)', 'href' => 'management_pending.php', 'icon' => 'hourglass-split', 'match' => ['management_pending', 'ai_module_maker'], 'badge' => $sj_pending],
        ['label' => 'Manage Kuis',       'href' => 'pintu-rahasia-sija',   'icon' => 'question-circle',  'match' => ['pintu-rahasia-sija', 'edit_soal', 'tambah_soal']],
        ['label' => 'Manage Cekpoint',   'href' => 'pintu-rahasia-modul',  'icon' => 'flag',             'match' => ['pintu-rahasia-modul']],
        ['label' => 'Manage Game',       'href' => 'pintu-game-sija',      'icon' => 'controller',       'match' => ['pintu-game-sija']],
    ]];

    $sj_menu[] = ['title' => 'Siswa', 'items' => [
        ['label' => 'Manage User',       'href' => 'pintu-belakang-sija',  'icon' => 'people',           'match' => ['pintu-belakang-sija']],
        ['label' => 'Manage Tugas',      'href' => 'management_tugas.php', 'icon' => 'clipboard-check',  'match' => ['management_tugas']],
        ['label' => 'Manage Absensi',    'href' => 'absensi_sesi.php',     'icon' => 'calendar-check',   'match' => ['absensi_sesi', 'laporan_silang']],
        ['label' => 'Pengumpulan Tugas', 'href' => $link_tugas,            'icon' => 'box-arrow-up-right', 'external' => true],
    ]];

    $sj_menu[] = ['title' => 'Sistem', 'items' => [
        ['label' => 'Toggle Notifikasi WA', 'href' => 'toggle_notif.php',         'icon' => 'bell',   'match' => ['toggle_notif']],
        ['label' => 'Buka/Tutup Registrasi', 'href' => 'pintu-pendaftaran-sija', 'icon' => 'unlock', 'match' => ['pintu-pendaftaran-sija']],
    ]];
} elseif ($sj_is_siswa) {
    $sj_menu[] = ['title' => null, 'items' => [
        ['label' => 'Materi',     'href' => 'index.php',      'icon' => 'book',             'match' => ['index']],
        ['label' => 'Akun Saya',  'href' => 'akun_saya.php',  'icon' => 'person-circle',    'match' => ['akun_saya']],
        ['label' => 'Rapor Saya', 'href' => 'rapor_siswa.php', 'icon' => 'file-earmark-text', 'match' => ['rapor_siswa']],
    ]];

    $sj_menu[] = ['title' => 'Evaluasi', 'items' => [
        ['label' => 'Kuis',                'href' => 'kuis.php',               'icon' => 'question-circle',    'match' => ['kuis']],
        ['label' => 'Tugas Aktif',         'href' => 'daftar_tugas_siswa.php', 'icon' => 'clipboard-check',    'match' => ['daftar_tugas_siswa']],
        ['label' => 'Arsip Tugas (Drive)', 'href' => $link_tugas,              'icon' => 'box-arrow-up-right', 'external' => true],
    ]];

    $sj_menu[] = ['title' => 'Belajar', 'items' => [
        ['label' => 'Game Edukasi', 'href' => 'game_edukasi.php', 'icon' => 'controller', 'match' => ['game_edukasi']],
    ]];
}

// --- Helper render (dijaga supaya aman kalau file ini di-include lebih dari sekali) -------
if (!function_exists('sj_render_item')) {
    function sj_render_item(array $it, array $cur): void
    {
        $active   = !empty($it['match']) && array_intersect($it['match'], $cur);
        $external = !empty($it['external']);
        ?>
        <a class="sj-item<?= $active ? ' active' : '' ?>"
           href="<?= htmlspecialchars($it['href']) ?>"
           <?= $active ? 'aria-current="page"' : '' ?>
           <?= $external ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <i class="bi bi-<?= htmlspecialchars($it['icon']) ?>" aria-hidden="true"></i>
            <span class="sj-label"><?= htmlspecialchars($it['label']) ?></span>
            <?php if (!empty($it['badge'])): ?>
                <span class="sj-badge"><?= (int) $it['badge'] ?></span>
            <?php endif; ?>
        </a>
        <?php
    }
}

$sj_brand_href = ($sj_is_admin && !empty($sj_has_dashboard)) ? 'dashboard_admin.php' : 'index.php';
?>
<style>
    .sj-shell { display: block; }
    .sj-main  { min-width: 0; }
    .sj-sidebar { background: #fff; border-right: 1px solid #e3e8ef; }
    .sj-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; }
    .sj-brand { text-decoration: none; display: flex; align-items: center; gap: 10px; min-width: 0; }
    .sj-brand-logo { width: 32px; height: 32px; flex: 0 0 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; background: #f1f4f8; overflow: hidden; }
    .sj-brand-logo img { width: 100%; height: 100%; object-fit: contain; }
    .sj-brand-logo .bi { font-size: 22px; color: #1a5fd0; }
    .sj-brand-text { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
    .sj-brand-text strong { font-size: 16px; font-weight: 700; color: #1b2430; letter-spacing: -0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sj-brand-text small { font-size: 12px; font-weight: 500; color: #5b6675; }
    .sj-toggle { min-height: 40px; min-width: 40px; border: 1px solid #c9d1dc; border-radius: 8px; background: #fff; color: #1b2430; font-size: 20px; line-height: 1; }
    .sj-nav { padding: 0 14px 14px; display: flex; flex-direction: column; gap: 2px; }
    .sj-title { padding: 16px 12px 6px; font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #5b6675; }
    .sj-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; color: #3a4553; text-decoration: none; font-size: 14px; font-weight: 500; line-height: 20px; }
    .sj-item:hover { background: #f1f4f8; color: #1b2430; }
    .sj-item.active { background: #e8f0fd; color: #1a5fd0; font-weight: 600; }
    .sj-item .bi { font-size: 17px; }
    .sj-label { flex: 1; min-width: 0; }
    .sj-badge { background: #fff1d6; color: #7a4300; font-size: 12px; font-weight: 700; padding: 1px 8px; border-radius: 999px; }
    .sj-topbar { position: sticky; top: 0; z-index: 1030; padding: 10px 24px; background: #fff; border-bottom: 1px solid #e5e7eb; }
    .sj-topbar-crumb { font-size: 13px; min-width: 0; }
    .sj-topbar-portal { color: #5b6675; font-weight: 500; }
    .sj-topbar-sep { font-size: .65rem; color: #9aa5b1; }
    .sj-topbar-page { color: #1b2430; font-weight: 600; white-space: nowrap; }
    .sj-env-badge { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; border: 1px solid transparent; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .sj-env-production { background: #e7f6ec; color: #177245; border-color: #bfe5cd; }
    .sj-env-staging { background: #fff4e5; color: #8a5300; border-color: #f3d9ae; }
    .sj-env-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .sj-topbar-icon { position: relative; width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; color: #3a4553; font-size: 17px; }
    .sj-topbar-icon:hover { background: #f1f4f8; color: #1b2430; }
    .sj-topbar-dot { position: absolute; top: 8px; right: 9px; width: 8px; height: 8px; border-radius: 50%; background: #dc3545; border: 2px solid #fff; }
    .sj-topbar-avatar { width: 34px; height: 34px; border-radius: 50%; background: #e8f0fd; color: #1a5fd0; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; flex: 0 0 auto; }
    .sj-topbar-name { font-size: 14px; font-weight: 600; color: #1b2430; white-space: nowrap; max-width: 220px; overflow: hidden; text-overflow: ellipsis; }
    .sj-topbar :focus-visible { outline: 2px solid #1a5fd0; outline-offset: 2px; }
    .sj-sidebar :focus-visible { outline: 2px solid #1a5fd0; outline-offset: 2px; }

    @media (min-width: 992px) {
        .sj-shell { display: flex; align-items: stretch; }
        .sj-sidebar { flex: 0 0 260px; width: 260px; position: sticky; top: 0; height: 100vh; overflow-y: auto; display: flex; flex-direction: column; }
        .sj-toggle { display: none; }
        .sj-menu { display: flex !important; flex-direction: column; flex: 1; }
        .sj-nav { flex: 1; }
        .sj-main { flex: 1 1 0; }
        .sj-shell.sj-collapsed .sj-sidebar { display: none; }
    }

    @media (max-width: 575.98px) {
        .sj-topbar { padding: 10px 16px; }
    }
</style>

<aside class="sj-sidebar" id="sjSidebar" aria-label="Menu utama">
    <div class="sj-head">
        <a class="sj-brand" href="<?= htmlspecialchars($sj_brand_href) ?>">
            <span class="sj-brand-logo">
                <img src="images.jpeg" alt="Logo SMKN 11 Malang" width="32" height="32"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                <i class="bi bi-mortarboard-fill" aria-hidden="true" style="display: none;"></i>
            </span>
            <span class="sj-brand-text">
                <strong>SIJA Learning Hub</strong>
                <small class="text-muted">SMKN 11 Malang</small>
            </span>
        </a>
        <button class="sj-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#sjMenu"
                aria-controls="sjMenu" aria-expanded="false" aria-label="Buka atau tutup menu">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
    </div>

    <div class="collapse sj-menu" id="sjMenu">
        <nav class="sj-nav">
            <?php foreach ($sj_menu as $sj_group): ?>
                <?php if (!empty($sj_group['title'])): ?>
                    <div class="sj-title"><?= htmlspecialchars($sj_group['title']) ?></div>
                <?php endif; ?>
                <?php foreach ($sj_group['items'] as $sj_item) { sj_render_item($sj_item, $sj_cur); } ?>
            <?php endforeach; ?>
        </nav>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var shell = document.querySelector('.sj-shell');
    var btn = document.getElementById('sjSidebarToggle');
    if (!shell || !btn) return;

    var KEY = 'sj-sidebar-collapsed';
    var collapsed = false;
    try { collapsed = localStorage.getItem(KEY) === '1'; } catch (e) {}

    function apply(state) {
        shell.classList.toggle('sj-collapsed', state);
        btn.setAttribute('aria-expanded', state ? 'false' : 'true');
        btn.title = state ? 'Tampilkan sidebar' : 'Sembunyikan sidebar';
        try { localStorage.setItem(KEY, state ? '1' : '0'); } catch (e) {}
    }

    apply(collapsed);

    btn.addEventListener('click', function () {
        apply(!shell.classList.contains('sj-collapsed'));
    });
});
</script>
