user_invocable: true
disable_model_invocation: false
---

# Security Review Checklist (LMS)

Checklist verifikasi terakhir, dijalankan SEBELUM commit setiap kali ada
file PHP baru atau file yang diedit yang: (a) menerima `$_POST`/`$_GET`,
(b) query ke database, atau (c) menerima upload file. Tujuannya menangkap
kelas bug yang sudah pernah lolos di proyek ini — bukan mengajarkan
konsep keamanan dari nol, tapi memastikan pola yang sudah terbukti benar
di proyek ini benar-benar diterapkan konsisten, bukan terlewat di 1 titik.

Jalankan checklist ini per file yang diubah. Kalau ada poin yang "tidak
relevan" untuk file itu, tulis kenapa (misal "read-only, tidak ada
POST"), jangan cuma diskip diam-diam.

## 1. Auth & Authorization

- [ ] Ada `session_start()` sebelum akses `$_SESSION` apapun.
- [ ] Cek login: `!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true`
      → redirect ke `login.php`.
- [ ] **Kalau halaman ini khusus admin**: ada cek role TERPISAH dari cek
      login —
      `!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'` → 404.
      Cuma cek `is_login` TIDAK CUKUP; siswa yang login tetap lolos kalau
      role tidak dicek. (Insiden nyata: siswa bisa buka beberapa halaman
      admin sebelum ini ditambal.)
- [ ] Kalau endpoint ini dipanggil dari file lain / dari JS `fetch()`
      (bukan dibuka langsung lewat browser), cek juga: apakah endpoint
      ini bisa diakses langsung tanpa lewat alur yang seharusnya? Kalau
      bisa, apakah itu aman?

## 2. CSRF (untuk semua yang menerima POST)

- [ ] `include 'csrf_helper.php';` ada, ditaruh setelah `session_start()`.
- [ ] `csrf_require_valid_post();` dipanggil di baris PALING AWAL blok
      `if (POST)`, dan diletakkan **SETELAH** cek login/role (urutan:
      auth dulu baru CSRF — supaya orang yang belum login dapat pesan
      "harus login", bukan "token invalid" yang membocorkan mekanisme).
- [ ] Form HTML yang submit ke sini punya `<?= csrf_field() ?>` di dalam
      tag `<form>`.
- [ ] **Pengecualian yang MEMANG benar** (jangan tambah CSRF di sini):
      form login/signup — CSRF tidak melindungi kredensial yang memang
      rahasia milik user itu sendiri. (Insiden nyata: CSRF sempat
      ke-pasang keliru di form login, harus dicabut lagi.)
- [ ] Kalau aksinya trigger dari link `<a href="?aksi=...">` (GET) yang
      mengubah data — ini bug, harus diubah ke `<form method="POST">`
      kecil + CSRF, bukan ditambal dengan token di query string saja.

## 3. Database

- [ ] Semua query pakai `$pdo->prepare()` + `execute([...])`. Tidak ada
      variabel yang di-concatenate langsung ke string SQL.
- [ ] ID dari `$_GET`/`$_POST` di-cast `(int)` sebelum dipakai di query,
      walau sudah pakai prepared statement (defense in depth, dan
      mencegah nilai aneh masuk ke logic lain sebelum query jalan).
- [ ] Kalau ada operasi multi-step yang harus atomic (insert ke 2+ tabel
      terkait, atau update banyak baris sekaligus): dibungkus
      `beginTransaction()` / `commit()` / `rollBack()` dalam `try-catch`.
- [ ] Kalau nulis/update ke tabel `kuis_soal`: **tidak** query manual,
      pakai fungsi di `includes/soal_writer.php`.

## 4. Output & Input Handling

- [ ] Semua data dari DB atau input user yang dicetak ke HTML dibungkus
      `htmlspecialchars()`. Termasuk yang terasa "aman" seperti nama
      kategori/kelas — sumbernya tetap data yang bisa diedit lewat form.
- [ ] Kalau ada pesan error yang ditampilkan ke user, tidak membocorkan
      detail internal (query SQL mentah, path server, stack trace) di
      exception message yang dicetak ke browser.

## 5. Upload File (kalau relevan)

- [ ] Validasi ekstensi via whitelist (`in_array` ke daftar yang
      diizinkan), bukan blacklist.
- [ ] Validasi ukuran file (`$_FILES[...]['size']`) DAN pastikan
      `post_max_size`/`upload_max_filesize` di server sudah cukup besar
      untuk batas yang dijanjikan ke user — kalau server-side limit lebih
      kecil, validasi aplikasi tidak akan pernah sempat jalan.
- [ ] Validasi isi file beneran (misal `getimagesize()` untuk gambar),
      jangan percaya ekstensi nama file saja.
- [ ] Nama file hasil rename pakai kombinasi yang tidak bisa ditebak/
      tidak bisa dipakai path traversal (`user_id . '_' . time() . '_' .
      uniqid()`), bukan nama asli dari user apa adanya.
- [ ] Kalau upload gagal di tengah jalan (error apapun), pastikan TIDAK
      ada baris database yang ke-insert seolah-olah berhasil. (Insiden
      nyata: form "alasan tidak upload" yang terpisah dari form upload
      foto sempat bikin kolom "terakhir upload" di dashboard admin salah
      baca timestamp submission alasan sebagai timestamp upload foto.)

## 6. Password & Kredensial

- [ ] Password baru selalu lewat `password_hash($pw, PASSWORD_DEFAULT)`
      sebelum disimpan — tidak pernah plaintext.
- [ ] Verifikasi login/ganti password pakai `password_verify()`, bukan
      `===` perbandingan string.
- [ ] Tidak ada token/API key/password yang di-hardcode di kode — selalu
      lewat `.env` / `getenv()`.
- [ ] Kalau ada fitur reset password ke nilai default: dipastikan hanya
      admin yang bisa memicunya (bukan self-service tanpa verifikasi
      identitas), dan idealnya ada jalur ganti password sendiri untuk
      user setelah direset.

## 7. Setelah Semua Poin Dicek

- [ ] Baca ulang seluruh file yang berubah dari atas ke bawah sekali
      lagi — checklist ini menangkap kelas bug yang sudah dikenal, bukan
      jaminan lengkap. Kalau ada sesuatu yang terasa janggal tapi tidak
      masuk kategori di atas, tetap laporkan.