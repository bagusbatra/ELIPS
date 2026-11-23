# ELIPS LMS (PHP berbasis komponen)

PHP 8.3 + MySQL (Laragon). Semua halaman `.php`, memakai layout & komponen bersama.

## Mulai cepat (Laragon)
```bash
php api/install.php          # buat DB + data demo (sekali; menulis storage/install.lock)
php api/seed-karyawan.php    # opsional: akun karyawan demo (password demo ada di api/config.php)
php tests/run.php            # pengujian otomatis (DB terpisah elips_lms_test)
```
Akses `http://elips.test/` atau `http://localhost/ELIPS/` (BASE_URL terdeteksi otomatis).

## Keamanan & produksi
- **Guard server**: `includes/auth.php`. Tiap halaman menyatakan `'roles' => [...]` (+ `'divisions'`); role salah dialihkan ke dashboard-nya, tanpa login ke `login.php`, sebelum HTML terkirim. Mendukung "ingat saya". `ELIPS_AUTH_GUARD=0` hanya untuk debugging.
- **install/seed dikunci**: hanya CLI (web = 404), `install.php` menolak berjalan lagi tanpa `--force` (lock `storage/install.lock`), `--fresh` wajib disertai `--force`, `seed-*.php` ditolak bila `ELIPS_ENV=production`. `api/.htaccess` hanya mengizinkan `index.php`.
- **Production**: set `ELIPS_ENV=production`, `ELIPS_DB_*`; jalankan `php api/install.php` lalu `php api/create-admin.php email "Nama"` (tanpa akun demo). Ganti `demo_password` bila akun demo pernah dibuat.

## Struktur
- `includes/` bootstrap, helper, layout engine, guard. `layouts/app.php` (dashboard) & `layouts/page.php` (umum). `partials/` head, navbar, sidebar, footer, scripts.
- `assets/js/pages/` skrip khusus tiap halaman (dulu inline). `assets/js/dist/` bundel (`php tools/build-assets.php`; minify bila `terser` terpasang). Bila sumber JS lebih baru dari bundel, halaman otomatis memuat berkas satuan.
- `seed.js` hanya dimuat pada mode demo lokal tanpa server (`ELIPS_LOCAL_DEMO=1`); bundel dimatikan dengan `ELIPS_BUNDLE=0`.
- `docs/` PRD, ERD, log, report.

## Halaman baru
```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'roles' => ['admin'], 'title' => 'Judul - ELIPS LMS', 'area' => 'admin',
    'sidebar' => 'admin', 'scripts' => ['@crud', 'ui'], 'page_script' => 'admin/halaman-baru',
];
layout('app', $page);
?>
<main class="dashboard-main"> ... </main>
<?php end_layout(); ?>
```
JS halaman: `assets/js/pages/admin/halaman-baru.js`. Login/register/learning memakai `layout('page', ...)`.

## Pendaftaran publik (calon student)
Formulir "Daftar Sekarang" di `course-detail.php` menyimpan data ke tabel `course_registrations` lewat aksi `course.register` (bisa dipanggil tanpa login). Status awalnya `baru`, jadi belum menjadi akun. Data bisa dilihat dan diverifikasi di menu **Admin > Pendaftaran Siswa** (`admin/registrations.php`). Dari halaman itu admin dapat membaca data lewat `course.registration.list` lalu memanggil `course.registration.convert` untuk membuat akun dengan `role='peserta'` (student) atau menghubungkan ke akun peserta yang sudah ada. Tabel sudah ada di `api/schema.sql` dan juga dibuat otomatis saat aksi pertama dijalankan pada database lama.

## Absensi karyawan (divalidasi di server)
Jam ditentukan server; foto harus gambar asli (min 200 px, bukan duplikat foto lama); GPS wajib & akurasi maks `max_accuracy_m`; geofence kantor opsional (`ELIPS_GEOFENCE` JSON, `ELIPS_GEOFENCE_ENFORCE=1` untuk menolak di luar radius, tanpa itu hanya diberi flag). Status terlambat/pulang cepat dihitung terhadap shift (atau jam default di `api/config.php`). Rekap otomatis: aksi API `staffAttendance.summary` / `mySummary`, tabel "Ringkasan Otomatis" di Admin > Rekap Absensi, dan CLI `php api/cron-attendance.php [--month=YYYY-MM]` (CSV di `storage/`). Jadwalkan harian lewat cron / Task Scheduler.

## Pengujian
- `php tests/run.php` – 149 cek: login per role, matriks akses, guard, "ingat saya", API, absensi, geofence, rekap, penguncian install/seed.
- `python3 tests/browser_smoke.py http://127.0.0.1:8099` – login lewat UI per role & buka semua halaman, laporkan error JS/jaringan (butuh playwright + Chrome).

## Catatan
- Upgrade DB lama: `php api/install.php --force` (menambah kolom absensi baru, tidak menghapus data).
- Setelah deploy, hard refresh sekali (service worker).
