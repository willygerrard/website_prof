# Recon Report - LMS PHP Seed Data

## 1. User Role & Class Format

- **users.role**: Valid values are `siswa` and `admin`. Default is `siswa`.
- **users.kelas**: Format strings from signup.php: `X TKJ 1`, `X TKJ 2`, `X TKJ 3`, `X TKJ 4`, `XI SIJA`, `XII SIJA`, `XIII SIJA`. Also stored in session as `$_SESSION['tingkat']`.
- **Password hashing**: Uses `password_hash($password, PASSWORD_DEFAULT)` for registration/login verification with `password_verify($password, $hash)`.

## 2. Foreign Key Relationships (from schema.sql)

### Core tables and their FK dependencies:

| Table | NOT NULL without default |
|-------|------------------------|
| **users** | id, username, password, role (default 'siswa'), status (default 'aktif') |
| **modules** | id, title, category, file_path, kelas_target |
| **checkpoint_modul** | id, modul_id, pertanyaan, opsi_a, opsi_b, jawaban_benar |
| **checkpoint_hasil** | id, user_id, modul_id, is_correct, processed_at |
| **games** | id, title, description, file_path |
| **kuis_sesi** | id, kategori (nullable), level (nullable), materi (nullable), status (default 'nonaktif'), durasi_menit (default 30), dibuka_at (nullable), ditutup_at (nullable) |
| **kuis_sesi_kelas** | id, sesi_id, kelas |
| **kuis_sesi_materi** | id, sesi_id, materi |
| **kuis_soal** | id, jenis_soal (default 'pilgan'), level (default 'pemula'), materi (nullable) |
| **kuis_soal_alternatif_isian** | id, soal_id, jawaban_alternatif |
| **kuis_hasil** | id, user_id (nullable), kategori (nullable), skor (nullable), total_soal (nullable), durasi_detik (nullable), tab_switch (default 0), dikerjakan_at, level (default 'pemula'), sesi_id (nullable), attempt (default 1) |
| **kuis_jawaban_isian** | id, hasil_id, soal_id, jawaban_siswa, cocok_otomatis (default 0), dicatat_at |
| **tugas** | id, judul, folder_path |
| **tugas_kelas** | tugas_id, kelas |
| **pengumpulan_tugas** | id, tugas_id, user_id, file_path, uploaded_at, nilai (nullable), catatan_guru (nullable), dinilai_at (nullable) |
| **notifikasi_modul** | id, user_id (nullable), modul_id (nullable), terkirim_at |
| **notifikasi_sesi** | id, status (default 'nonaktif'), diaktifkan_at (nullable), dinonaktifkan_at (nullable) |
| **riwayat_kelas** | id, user_id, kelas_lama (nullable), kelas_baru (nullable), aksi, dibuat_oleh (nullable), created_at |

### Foreign Key Summary (ON DELETE actions):

- **checkpoint_modul** → modules: CASCADE
- **checkpoint_hasil** → users: CASCADE
- **checkpoint_hasil** → modules: CASCADE
- **checkpoint_hasil** → checkpoint_modul: SET NULL
- **kuis_sesi_kelas** → kuis_sesi: CASCADE
- **kuis_sesi_materi** → kuis_sesi: CASCADE
- **kuis_soal_alternatif_isian** → kuis_soal: CASCADE
- **kuis_hasil** → kuis_sesi: SET NULL
- **kuis_jawaban_isian** → kuis_hasil: CASCADE
- **kuis_jawaban_isian** → kuis_soal: CASCADE
- **tugas_kelas** → tugas: CASCADE
- **pengumpulan_tugas** → tugas: CASCADE
- **pengumpulan_tugas** → users: CASCADE
- **notifikasi_modul** → users: SET NULL (via unique_notif)
- **notifikasi_modul** → modules: SET NULL (via unique_notif)
- **riwayat_kelas** → users: INDEX only (no ON DELETE specified)

### Tables with NO ON DELETE CASCADE (safe from dummy cleanup):
- `games` (no FK references)
- `modules` (no FK to users)
- `notifikasi_sesi` (standalone)
- `pengaturan` (standalone)
- `pengumpulan_tugas_backup_20260921` (marked safe - DO NOT TOUCH)

### Tables with NULLable FK that can be SET NULL:
- `checkpoint_hasil.modul_checkpoint_id` → checkpoint_modul: SET NULL
- `kuis_hasil.user_id` → users: SET NULL
- `kuis_hasil.sesi_id` → kuis_sesi: SET NULL
- `kuis_jawaban_isian.hasil_id` → kuis_hasil: CASCADE
- `kuis_jawaban_isian.soal_id` → kuis_soal: CASCADE
- `pengumpulan_tugas.user_id` → users: CASCADE
- `pengumpulan_tugas.tugas_id` → tugas: CASCADE
- `notifikasi_modul.user_id` → users: SET NULL
- `notifikasi_modul.modul_id` → modules: SET NULL

## 3. Valid Dummy Markers

- **username**: prefix with `dummy_`
- **modules.title**: prefix with `[DUMMY]`
- **users.role**: always `siswa` for dummy users
- **users.kelas**: use valid kelas format from daftar_kelas_sah (e.g., `X TKJ 1`, `XI SIJA`, etc.)
- **modules.jenis_resource**: default is `modul`
- **modules.kelas_target**: default is `semua`

## 4. Table Column NOT NULL without Default Summary

| Table | NOT NULL columns (no default) |
|-------|-------------------------------|
| users | id, username, password, role, status |
| modules | id, title, category, file_path, kelas_target |
| checkpoint_modul | id, modul_id, pertanyaan, opsi_a, opsi_b, jawaban_benar |
| checkpoint_hasil | id, user_id, modul_id, is_correct, processed_at |
| games | id, title, description, file_path |
| kuis_hasil | id, user_id, kategori, skor, total_soal, durasi_detik, dikerjakan_at, level, sesi_id, attempt |
| kuis_jawaban_isian | id, hasil_id, soal_id, jawaban_siswa, cocok_otomatis, dicatat_at |
| kuis_soal | id, jenis_soal, level, materi |
| kuis_soal_alternatif_isian | id, soal_id, jawaban_alternatif |
| kuis_sesi | id, kategori, level, materi, status, durasi_menit, dicatat_at |
| kuis_sesi_kelas | id, sesi_id, kelas |
| kuis_sesi_materi | id, sesi_id, materi |
| tugas | id, judul, folder_path |
| tugas_kelas | tugas_id, kelas |
| pengumpulan_tugas | id, tugas_id, user_id, file_path, uploaded_at |
| notifikasi_modul | id, user_id, modul_id, terkirim_at |
| notifikasi_sesi | id, status, diaktifkan_at, dinonaktifkan_at |
| riwayat_kelas | id, user_id, kelas_lama, kelas_baru, aksi, dibuat_oleh, created_at |