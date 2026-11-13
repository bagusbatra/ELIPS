# PRD — Kemudahan Absensi Karyawan (ELIPS LMS)

| | |
|---|---|
| **Status** | Draft untuk disetujui |
| **Tanggal** | 12 November 2026 |
| **Cakupan** | Fitur 1 (sesi login diingat), 2 (PWA / ikon di layar utama), 3 (role & halaman absen khusus karyawan), 4 (jadwal shift karyawan oleh Admin) |
| **Di luar cakupan** | QR harian, passkey/biometrik, mode kiosk PIN, integrasi shift ke perhitungan keterlambatan (lihat bagian 11) |

---

## 1. Latar belakang

ELIPS LMS akan dipakai sebagai media absensi wajib seluruh karyawan. Fitur absensi (foto kamera, GPS, jam, metadata perangkat) sudah ada di dashboard admin. Kendalanya:

1. Setiap kali absen, karyawan harus login terlebih dahulu, sehingga terasa menyulitkan.
2. Fitur absen saat ini hanya ada di dashboard admin, sedangkan karyawan biasa tidak seharusnya mengakses menu admin/LMS.

Tujuan PRD ini: membuat absen terasa **satu ketuk dari HP**, tanpa mengorbankan keamanan identitas (tidak boleh titip absen).

## 2. Tujuan & ukuran keberhasilan

| Tujuan | Ukuran keberhasilan |
|---|---|
| Karyawan tidak perlu login berulang | Setelah login pertama, absen berikutnya tanpa form login selama ≤ 30 hari tidak aktif |
| Absen secepat mungkin dari HP | Dari ketuk ikon sampai kamera aktif ≤ 3 detik pada jaringan normal; total alur absen ≤ 4 ketukan |
| Karyawan hanya melihat yang relevan | Role `karyawan` hanya melihat halaman absen dan riwayat absennya sendiri |
| Identitas tetap terjaga | Satu akun hanya aktif di satu perangkat; aturan 1 masuk + 1 pulang per hari dijaga **di server** |

## 3. Pengguna & role

| Role | Peran dalam fitur ini |
|---|---|
| **Karyawan** (role baru `karyawan`) | Absen masuk/pulang, lihat riwayat sendiri |
| **Admin** | Membuat akun karyawan, mereset perangkat karyawan, mengelola jadwal shift karyawan (Pagi/Sore/custom), tetap bisa absen sendiri di dashboard admin |
| Instruktur / Peserta | Tidak berubah (lihat pertanyaan terbuka #1) |

## 4. Kondisi saat ini (hasil pembacaan kode)

- Frontend statis HTML/JS (`admin/`, `instructor/`, `student/`, `assets/js/*`), backend PHP/MySQL lewat `api/index.php`; mode cadangan `localStorage` saat API mati.
- Sesi: `ELIPS.api.session` / `elips_session`; guard halaman lewat `ELIPS.requireRole(role)` di `auth.js`.
- Role valid di `core.js`: `admin`, `instruktur`, `peserta`.
- Absensi staf: aksi API `staffAttendance.create | list | delete`, koleksi `staffAttendances`. Logika UI masih **inline** di `admin/dashboard.html`.
- Aturan absen sudah ada di UI: belum absen → hanya "Absen Masuk"; sudah masuk → hanya "Absen Pulang"; tanggal, jam, dan lokasi dikunci saat Simpan; modal sukses dengan satu tombol "Selesai".

## 5. Ruang lingkup

### 5.1 Fitur 1 — Sesi login diingat (persistent login)

**Cerita pengguna:** *Sebagai karyawan, saya login sekali di HP saya, lalu seterusnya cukup membuka halaman absen tanpa login lagi.*

Persyaratan fungsional:
- **F1.1** Halaman login memiliki opsi **"Ingat saya di perangkat ini"**, aktif secara default untuk role `karyawan`.
- **F1.2** Saat dicentang, server membuat *remember token* acak (≥ 32 byte), menyimpannya **dalam bentuk hash** di database, dan mengirimnya sebagai cookie `HttpOnly; Secure; SameSite=Lax`.
- **F1.3** Masa berlaku **sliding 30 hari**: setiap pemakaian memperpanjang masa berlaku. Setelah 30 hari tanpa dipakai, karyawan login ulang.
- **F1.4** Token **dirotasi** setiap dipakai (token lama tidak berlaku lagi) untuk membatasi dampak pencurian token.
- **F1.5** **Satu perangkat aktif per karyawan.** Login/ingat di perangkat baru otomatis mencabut perangkat lama. *(Keputusan default; bisa diubah, lihat pertanyaan terbuka #2.)*
- **F1.6** Token dicabut otomatis saat: logout, ganti password, akun dinonaktifkan, atau admin mereset perangkat.
- **F1.7** Admin bisa **Reset Perangkat** per karyawan di halaman Kelola Pengguna (untuk kasus ganti HP / HP hilang).
- **F1.8** Absen tetap memerlukan sesi valid; jika sesi habis, karyawan diarahkan ke login lalu kembali otomatis ke halaman absen.

### 5.2 Fitur 2 — PWA (ikon di layar utama)

**Cerita pengguna:** *Sebagai karyawan, saya ingin membuka absensi dari ikon di layar HP seperti aplikasi biasa.*

Persyaratan fungsional:
- **F2.1** `manifest.webmanifest`: nama "Absensi ELIPS", `display: standalone`, `start_url` ke halaman absen karyawan, warna tema sesuai brand (`#e98c1e`), ikon 192×192 dan 512×512 (+ maskable).
- **F2.2** Service worker minimal agar bisa dipasang ("Add to Home Screen"). **Strategi: network-first untuk HTML, JS, CSS, dan semua panggilan API; tidak ada cache agresif** (kita pernah mengalami halaman lama yang tersangkut cache, jangan terulang). Hanya ikon dan font yang di-cache.
- **F2.3** Banner/ajakan ringan di halaman absen: "Pasang di layar utama" (memakai `beforeinstallprompt` di Android; petunjuk manual 2 langkah untuk iOS Safari).
- **F2.4** Wajib **HTTPS** (syarat PWA, kamera, dan GPS).
- **F2.5** Halaman memberi pesan jelas bila offline: "Tidak ada koneksi. Absen memerlukan internet." (absen offline **tidak** didukung di versi ini).

Catatan platform: di iOS, PWA yang dipasang memiliki penyimpanan cookie terpisah dari Safari, sehingga karyawan login sekali **di dalam aplikasi yang sudah dipasang**. Ini perlu disebut di panduan karyawan.

### 5.3 Fitur 3 — Role & halaman absen khusus karyawan

**Cerita pengguna:** *Sebagai karyawan, saya hanya ingin melihat tombol absen dan riwayat saya, tanpa menu LMS atau admin.*

Persyaratan fungsional:
- **F3.1** Tambah role `karyawan` (validasi role di `core.js`, backend, dan form pengguna).
- **F3.2** Halaman baru `employee/attendance.html`: header singkat (nama, jam hari ini), status hari ini, **satu tombol besar** sesuai aturan (Masuk / Pulang / Lengkap), riwayat 30 hari terakhir, tombol Keluar.
- **F3.3** Setelah login, role `karyawan` diarahkan ke dashboard `employee/dashboard.html` (`dashboardPathForRole`), yang menautkan ke halaman absensi.
- **F3.4** Guard halaman: `requireRole('karyawan')`; role lain yang membuka halaman ini diarahkan ke dashboard role-nya, dan sebaliknya.
- **F3.5** Alur absen identik dengan versi admin: modal kamera (kamera, ambil foto, nyalakan kamera, unggah foto, catatan, batal, simpan) → modal sukses dengan detail lengkap dan satu tombol "Selesai".
- **F3.6** Admin dapat membuat akun karyawan di **Kelola Pengguna** (pilihan role "Karyawan" pada modal tambah pengguna), termasuk memilih divisi.
- **F3.7** Logika absen **dipindahkan** dari inline `admin/dashboard.html` ke modul bersama `assets/js/attendance.js` sehingga dipakai admin dan karyawan tanpa duplikasi.
- **F3.8** *(Tambahan)* Dashboard karyawan `employee/dashboard.html` menampilkan sapaan, nama/divisi, status absensi hari ini, rekap bulan berjalan, akses cepat, dan riwayat terbaru.

### 5.4 Fitur 4 — Jadwal shift karyawan (Admin) *(selesai)*

**Cerita pengguna:** *Sebagai Admin, saya ingin mengatur shift Pagi/Sore atau jam khusus untuk setiap karyawan per tanggal, agar rencana kehadiran jelas sebelum absensi berjalan.*

Persyaratan fungsional:
- **F4.1** Halaman baru `admin/shifts.html` dengan menu **Shift Karyawan** yang konsisten di seluruh sidebar Admin; hanya role `admin` yang dapat mengakses.
- **F4.2** Setiap karyawan memiliki paling banyak **satu shift per tanggal**. Duplikat `(karyawan, tanggal)` ditolak dengan pesan yang jelas.
- **F4.3** Jenis shift: **Pagi** (08:00–16:00), **Sore** (16:00–00:00), dan **Custom**. Untuk preset, jam diisi otomatis dan tidak dapat diubah; untuk Custom, Admin mengisi jam mulai/selesai `HH:MM`.
- **F4.4** Shift melewati tengah malam (mis. Sore berakhir 00:00) ditandai `+1 hari` pada tabel.
- **F4.5** Filter rentang tanggal (default seluruh bulan berjalan, maksimal 366 hari), filter karyawan, dan filter jenis shift; tombol **Bulan Ini** mengembalikan rentang awal.
- **F4.6** Ringkasan kartu menampilkan total shift, Pagi, Sore, dan Custom sesuai hasil filter.
- **F4.7** Admin dapat menambah, mengubah, dan menghapus shift; karyawan yang dapat dijadwalkan adalah pengguna aktif berrole `admin` atau `karyawan`.
- **F4.8** Semua perubahan dicatat di Audit Trail dengan entitas `staff_shift`.

## 6. Aturan bisnis absensi (berlaku di semua role)

1. Per akun per hari: maksimal **1 absen masuk** dan **1 absen pulang**.
2. Absen pulang hanya boleh jika absen masuk hari itu sudah ada.
3. Tanggal dan jam ditentukan **server** (bukan jam HP); zona waktu mengikuti pengaturan sistem (default `Asia/Jakarta`).
4. Lokasi diambil otomatis dari GPS; jika GPS ditolak, absen tetap tersimpan dengan lokasi "tidak tersedia" dan ditandai agar admin bisa melihatnya (lihat pertanyaan terbuka #3).
5. Foto wajib. Catatan opsional.
6. Catatan absen tidak dapat dihapus oleh karyawan. *(Penghapusan oleh admin: lihat pertanyaan terbuka #4.)*

### 6.1 Aturan jadwal shift

1. Shift bersifat **per karyawan per tanggal**; unique key `(user_id, shift_date)` menjaga satu shift per hari.
2. Hanya role `admin` dan `karyawan` aktif yang dapat dijadwalkan; instruktur/peserta ditolak.
3. Preset **Pagi** = 08:00–16:00 dan **Sore** = 16:00–00:00. Untuk preset, jam dari klien diabaikan agar konsisten.
4. **Custom** menerima jam `HH:MM`; jam mulai = jam selesai ditolak.
5. Rentang daftar maksimal 366 hari dan tanggal awal tidak boleh melewati tanggal akhir.
6. Jadwal shift hanya berupa rencana; perhitungan keterlambatan/kehadiran berbasis shift belum termasuk pada versi ini.

## 7. Perubahan teknis

### 7.1 Database (MySQL)

```sql
-- Role baru (sesuaikan dengan tipe kolom role yang ada; jika ENUM, tambahkan 'karyawan')
-- ALTER TABLE users MODIFY role ENUM('admin','instruktur','peserta','karyawan') NOT NULL;

CREATE TABLE user_devices (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id       BIGINT NOT NULL,
  token_hash    CHAR(64) NOT NULL UNIQUE,   -- SHA-256 dari remember token
  device_label  VARCHAR(120) NULL,          -- mis. "Android • Chrome"
  user_agent    VARCHAR(255) NULL,
  ip_address    VARCHAR(45)  NULL,
  created_at    DATETIME NOT NULL,
  last_used_at  DATETIME NOT NULL,
  expires_at    DATETIME NOT NULL,
  revoked_at    DATETIME NULL,
  INDEX idx_user (user_id),
  INDEX idx_expires (expires_at)
);

-- Pengaman aturan 1 masuk + 1 pulang per hari di level database
-- (nama tabel/kolom menyesuaikan tabel absensi yang sudah ada)
-- ALTER TABLE staff_attendance
--   ADD UNIQUE KEY uq_user_day_kind (user_id, attendance_date, kind);

-- Jadwal shift per karyawan per tanggal (Fitur 4)
CREATE TABLE staff_shifts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  shift_date  DATE NOT NULL,
  shift_type  ENUM('pagi','sore','custom') NOT NULL DEFAULT 'pagi',
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  note        VARCHAR(255) NOT NULL DEFAULT '',
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL,
  UNIQUE KEY uq_staff_shifts_user_date (user_id, shift_date),
  KEY idx_staff_shifts_date (shift_date),
  KEY idx_staff_shifts_type (shift_type),
  CONSTRAINT fk_staff_shifts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_staff_shifts_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 7.2 API (`api/index.php`)

| Aksi | Perubahan |
|---|---|
| `auth.login` | Terima `remember: true/false`; jika true, buat token, simpan hash di `user_devices`, set cookie; cabut perangkat lama (F1.5) |
| `auth.resume` *(baru)* | Validasi cookie remember token → buat sesi → rotasi token. Dipanggil otomatis saat sesi habis tetapi cookie masih ada |
| `auth.logout` | Cabut token perangkat saat ini |
| `users.resetDevice` *(baru, admin)* | Cabut semua token milik user tertentu |
| `staffAttendance.create` | **Validasi server**: aturan bisnis bagian 6; isi `takenAt` dan `attendanceDate` dari jam server; tolak dengan pesan jelas bila melanggar; kembalikan **record lengkap** (termasuk `id`, `ipAddress`, `createdAt`) untuk modal sukses |
| `staffAttendance.list` | Selalu difilter ke user sesi (kecuali admin dengan parameter eksplisit) |
| `staffAttendance.delete` | Tolak untuk role `karyawan` |
| Pengguna (`users.create/update`) | Terima role `karyawan` |
| `staffShift.list` *(baru, admin)* | Daftar shift per rentang tanggal (default bulan berjalan, maks. 366 hari) dengan filter karyawan dan jenis; khusus Admin |
| `staffShift.save` *(baru, admin)* | Tambah/ubah shift; normalisasi jam preset Custom, tolak duplikat tanggal, role tidak valid, staf nonaktif, dan jam mulai = selesai |
| `staffShift.delete` *(baru, admin)* | Hapus shift; khusus Admin dan tercatat di Audit Trail |

### 7.3 Frontend

| File | Perubahan |
|---|---|
| `assets/js/core.js` | `isValidRole` menerima `karyawan` |
| `assets/js/auth.js` | `dashboardPathForRole('karyawan')` → `employee/dashboard.html`; `basePathPrefix` mengenali folder `employee/`; coba `auth.resume` sebelum redirect ke login |
| `assets/js/api.js`, `config.js` | Regex path `(admin\|instructor\|student)` ditambah `employee` |
| `assets/js/attendance.js` *(baru)* | Logika absen yang dipindah dari `admin/dashboard.html` (modul bersama) |
| `admin/dashboard.html` | Memakai `attendance.js`; tampilan dan perilaku tidak berubah |
| `employee/attendance.html` *(baru)* | Halaman absen karyawan (F3.2) |
| `employee/dashboard.html` + `assets/js/employee-dashboard.js` *(baru)* | Dashboard karyawan (F3.8): sapaan, divisi, status hari ini, rekap bulanan, dan riwayat terbaru |
| `login.html` | Checkbox "Ingat saya"; redirect sesuai role |
| `admin/users.html` + `crud-users.js` | Role "Karyawan" di modal tambah/ubah; tombol "Reset Perangkat" |
| `manifest.webmanifest`, `sw.js`, `offline.html`, `assets/js/pwa.js`, `assets/icons/*` *(baru)* | PWA (F2.1–F2.3): manifest, service worker network-first, halaman offline, banner pemasangan, dan ikon |
| `assets/js/shift-schedule.js` *(baru)* | Modul jadwal shift Admin: filter, preset Pagi/Sore, jam Custom, CRUD server/lokal, validasi konflik, dan audit `staff_shift` |
| `admin/shifts.html` *(baru)* | Halaman **Shift Karyawan**: filter, ringkasan, tabel shift, dan modal tambah/ubah |
| `assets/css/style.css` | Gaya halaman karyawan, banner pasang aplikasi, sheet iOS, bilah offline, dan tabel/badge shift |
| 31 halaman aplikasi | Tag manifest, `theme-color`, meta mobile/apple, favicon, apple-touch-icon, dan `pwa.js` (`defer`) |

## 8. Keamanan & privasi

- Cookie token: `HttpOnly`, `Secure`, `SameSite=Lax`; token acak kriptografis, **hanya hash** yang disimpan.
- Rotasi token tiap pemakaian; deteksi pemakaian token lama → cabut semua perangkat user tersebut dan catat ke Audit Trail.
- Rate limit pada `auth.login` dan `auth.resume` (mis. 10 percobaan/menit/IP).
- Semua aturan absen dan waktu dijaga **di server**; validasi di browser hanya untuk kenyamanan.
- Foto dan lokasi adalah data pribadi karyawan: batasi akses hanya ke karyawan terkait dan admin; tentukan masa simpan (lihat pertanyaan terbuka #5); beri tahu karyawan bahwa foto dan lokasi direkam.
- Semua aksi penting (login perangkat baru, reset perangkat, absen) masuk **Audit Trail**.

## 9. Rencana pengembangan (step by step)

Setiap fase bisa dirilis dan diuji sendiri. Kerjakan berurutan.

### Fase 0 — Persiapan *(0,5 hari)*
- [ ] Pastikan situs berjalan di **HTTPS** (staging dan produksi).
- [ ] Kumpulkan file backend: `api/index.php`, handler `staffAttendance.*`, modul auth, skema tabel yang ada.
- [ ] Putuskan pertanyaan terbuka #1–#5.
- **Selesai bila:** keputusan tercatat dan backend bisa dijalankan di staging.

### Fase 1 — Fondasi server: role, validasi absen, migrasi *(1–1,5 hari)*
- [x] Migrasi DB: role `karyawan` dan unique key absensi (`user_devices` ditunda ke Fase 4).
- [x] `staffAttendance.create`: validasi aturan bisnis + waktu server + kembalikan record lengkap.
- [x] `staffAttendance.list/delete`: batasan per role.
- [x] `core.js`: `isValidRole` + form pengguna menerima `karyawan`.
- **Selesai bila:** mencoba absen masuk dua kali di hari yang sama ditolak server; absen pulang tanpa masuk ditolak; jam yang tersimpan adalah jam server.

### Fase 2 — Refactor modul absensi bersama *(0,5–1 hari)*
- [x] Pindahkan logika absen dari `admin/dashboard.html` ke `assets/js/attendance.js`.
- [x] `admin/dashboard.html` memanggil modul; perilaku tetap identik.
- **Selesai bila:** dashboard admin berfungsi seperti sebelum refactor (uji ulang 6 aturan absen admin).

### Fase 3 — Halaman & role Karyawan *(1–1,5 hari)*
- [x] Buat `employee/attendance.html` (F3.2) memakai `attendance.js`.
- [x] `auth.js`, `api.js`, `config.js`: dukung folder `employee/`; routing berdasarkan role.
- [x] `admin/users.html`: pilihan role Karyawan.
- **Selesai bila:** akun karyawan login → langsung ke halaman absen → absen masuk dan pulang berjalan → tidak bisa membuka halaman admin.

### Fase 4 — Sesi diingat (login sekali) *(1,5–2 hari)*
- [x] `auth.login` dengan `remember`, `auth.resume`, rotasi token, cabut saat logout/ganti password/nonaktif.
- [x] Satu perangkat aktif per karyawan; `users.resetDevice` + tombol admin.
- [x] Checkbox "Ingat saya" di `login.html`; resume otomatis di `auth.js`.
- [x] Rate limit dan catatan Audit Trail.
- **Selesai bila:** karyawan menutup browser, membuka lagi esok hari → langsung masuk tanpa login; login di HP kedua mencabut HP pertama; reset perangkat oleh admin bekerja.

Catatan implementasi:
- Tabel `user_devices` menyimpan **hash SHA-256** token (bukan token mentah), label perangkat, user agent, IP, waktu dibuat/dipakai/kedaluwarsa, serta `revoked_at` + `revoked_reason`.
- Rotasi token membuat baris baru dan mencabut baris lama dengan alasan `rotated`; pemakaian ulang token hasil rotasi mencabut **semua** perangkat pengguna dan menaikkan `users.session_version`. Token yang dicabut karena `superseded`/`logout`/`admin`/`security`/`expired` hanya ditolak tanpa mencabut perangkat baru.
- `users.session_version` membatalkan sesi PHP lama saat login baru, ganti password, akun dinonaktifkan, atau admin mereset perangkat.
- Rate limit 10 percobaan gagal per 60 detik per IP+email (`auth.login`) atau per hash token (`auth.resume`), disimpan di direktori temporer server.
- Mode lokal `localStorage` tetap kompatibel: `auth.resume` diabaikan dan Reset Perangkat hanya mencatat audit demo.

### Fase 5 — PWA *(0,5–1 hari)*
- [x] `manifest.webmanifest`, ikon, `sw.js` (network-first, tanpa cache halaman/API).
- [x] Banner "Pasang di layar utama" (Android) dan petunjuk iOS.
- [x] Pesan saat offline.
- **Selesai bila:** aplikasi bisa dipasang di Android (Chrome) dan iOS (Safari), terbuka standalone langsung ke halaman absen, dan perubahan file baru langsung terlihat tanpa menghapus cache manual.

Catatan implementasi:
- `manifest.webmanifest` berada di root dengan `start_url: "./employee/attendance.html"`, `scope: "./"`, `display: "standalone"`, serta ikon 192/512 dan maskable. Pengguna yang belum punya sesi tetap diarahkan ke `login.html` oleh `requireRole`; role selain `karyawan`/admin ditolak.
- Ikon PNG dibuat tanpa dependensi: `icon-192`, `icon-512`, `icon-maskable-192`, `icon-maskable-512`, `apple-touch-icon` 180, dan `favicon-32` di `assets/icons/`.
- `sw.js` memakai strategi **network-first**: halaman HTML aplikasi dan seluruh `/api/` tidak pernah disimpan ke cache, sehingga perubahan file baru langsung terlihat. Hanya `offline.html` + ikon yang di-precache, sedangkan aset statis (CSS/JS/gambar/font/webmanifest/JSON) dipakai sebagai fallback saat offline.
- `assets/js/pwa.js` mendaftarkan service worker (skip waiting + `clients.claim`, reload sekali saat ada versi baru), menampilkan banner pemasangan Android lewat `beforeinstallprompt`, sheet 3 langkah "Tambahkan ke Layar Utama" untuk iOS Safari, dan bilah offline dengan tombol coba lagi. Penolakan banner diingat 7 hari dan prompt dibatasi sekali per sesi.
- `attendance.js` menolak penyimpanan saat offline, baik sebelum validasi maupun di dalam `persistAttendance` (menutup celah GPS yang selesai setelah koneksi putus).
- `offline.html` berdiri sendiri (tanpa bundle aplikasi) dengan tombol muat ulang. Semua 31 halaman aplikasi memuat manifest, meta mobile/apple, favicon, apple-touch-icon, dan `pwa.js` (`defer`); server PHP bawaan menyajikan `.webmanifest` sebagai `application/manifest+json`.

### Fase 5b — Jadwal shift karyawan *(selesai)*
- [x] Tabel `staff_shifts` + unique key `(user_id, shift_date)`.
- [x] Aksi `staffShift.list/save/delete` khusus Admin + audit `staff_shift`.
- [x] `admin/shifts.html` + `assets/js/shift-schedule.js`: preset Pagi/Sore, jam Custom, filter, ringkasan, dan CRUD.
- [x] Menu **Shift Karyawan** di seluruh sidebar Admin dan dukungan mode lokal `elips_staffShifts`.
- **Selesai bila:** Admin dapat menjadwalkan shift Pagi/Sore/custom, duplikat tanggal ditolak, dan karyawan nonaktif/tidak valid tidak dapat dijadwalkan.

### Fase 6 — Uji menyeluruh & peluncuran *(1 hari)*
- [ ] Jalankan seluruh skenario uji (bagian 10) di Android, iOS, dan desktop.
- [ ] Panduan singkat untuk karyawan (1 halaman: login, pasang ke layar utama, cara absen).
- [ ] Pilot dengan 3–5 karyawan selama beberapa hari, perbaiki temuan.
- [ ] Rilis bertahap ke semua karyawan.
- **Selesai bila:** pilot berjalan tanpa masalah blocker dan semua akun karyawan sudah dibuat.

**Estimasi total: ± 6–8 hari kerja** (tanpa fitur lanjutan).

## 10. Skenario uji utama

| # | Skenario | Hasil yang diharapkan |
|---|---|---|
| 1 | Karyawan baru login dengan "Ingat saya", menutup browser, membuka lagi besok | Langsung ke halaman absen, tanpa login |
| 2 | Tidak membuka aplikasi 31 hari | Diminta login ulang |
| 3 | Login di HP kedua | HP pertama keluar otomatis |
| 4 | Admin menekan Reset Perangkat | Perangkat karyawan diminta login ulang |
| 5 | Akun dinonaktifkan | Sesi langsung tidak berlaku |
| 6 | Absen masuk dua kali di hari yang sama (termasuk lewat DevTools/API langsung) | Ditolak server |
| 7 | Absen pulang sebelum masuk | Ditolak server |
| 8 | Ubah jam HP lalu absen | Jam tersimpan tetap jam server |
| 9 | Izin GPS ditolak | Absen tersimpan, lokasi "tidak tersedia", ada penanda |
| 10 | Karyawan membuka URL halaman admin | Diarahkan ke halaman karyawan |
| 11 | PWA terpasang lalu file diperbarui | Versi baru terlihat tanpa hapus cache |
| 12 | Offline | Pesan jelas, tidak ada absen tersimpan setengah jalan |
| 13 | Admin menambah shift Pagi untuk karyawan | Tersimpan 08:00–16:00, muncul di tabel dan ringkasan Pagi |
| 14 | Admin menambah shift Sore | Tersimpan 16:00–00:00 dan ditandai `+1 hari` |
| 15 | Admin memilih Custom dengan jam 07:30–13:15 | Jam custom tersimpan dan tampil apa adanya |
| 16 | Admin menyimpan shift kedua untuk karyawan dan tanggal yang sama | Ditolak dengan pesan konflik |
| 17 | Admin memilih jam Custom dengan mulai = selesai | Ditolak dengan pesan validasi |
| 18 | Instruktur membuka API/aksi shift | Ditolak karena khusus Admin |

## 11. Di luar cakupan (kandidat tahap berikutnya)

- QR code harian di lokasi kerja (bukti hadir fisik) dan/atau pembatasan radius GPS (geofence).
- Login biometrik (passkey / sidik jari / Face ID).
- Mode kiosk: tablet bersama dengan NIK + PIN + selfie.
- Rekap absensi lanjutan: keterlambatan, jam kerja, izin/cuti/lembur, serta ekspor Excel/PDF. Rekap dasar per karyawan per hari dan ekspor CSV sudah tersedia sebagai perluasan di halaman Admin **Rekap Absensi** (`admin/attendance-recap.html`).
- Integrasi jadwal shift ke perhitungan keterlambatan/kehadiran (mis. membandingkan `takenAt` dengan `startTime` shift). Increment saat ini hanya menyimpan **rencana** shift di `admin/shifts.html`.
- Absen offline dengan antrean sinkronisasi.
- Notifikasi pengingat absen (push).

## 12. Pertanyaan terbuka (perlu keputusan sebelum Fase 1)

1. Apakah **instruktur** juga karyawan yang wajib absen lewat halaman karyawan? (Jika ya, mereka perlu akses halaman absen juga, atau diberi role ganda.)
2. **Satu perangkat per karyawan** sudah sesuai? Atau boleh dua perangkat (mis. HP cadangan)?
3. Jika **GPS ditolak**, absen boleh tetap tersimpan (default PRD ini) atau harus diblokir?
4. Apakah **admin boleh menghapus** catatan absen karyawan? Jika boleh, penghapusan harus tercatat di Audit Trail dan alasan wajib diisi.
5. **Berapa lama foto absen disimpan** (mis. 90 hari, 1 tahun)? Ini juga menentukan ukuran penyimpanan server.
6. **Zona waktu** acuan: `Asia/Jakarta` untuk semua, atau per cabang?
7. Jumlah karyawan dan estimasi absen per hari (untuk menentukan kapasitas penyimpanan foto dan server).

## 13. Risiko

| Risiko | Dampak | Mitigasi |
|---|---|---|
| HP dipinjamkan/dicuri saat sesi masih aktif | Absen atas nama orang lain | Satu perangkat per akun, foto wajib, admin bisa reset perangkat, pertimbangkan QR/geofence di tahap berikut |
| Cache lama menyebabkan tampilan/perilaku usang | Karyawan memakai versi lama | Service worker network-first; nomor versi pada file statis |
| Perilaku cookie di PWA iOS berbeda dari Safari | Karyawan bingung harus login ulang | Panduan: login di dalam aplikasi terpasang |
| Penyimpanan foto membengkak | Server penuh | Foto sudah dikompres (maks. 640 px); tentukan masa simpan dan pembersihan terjadwal |
| Backend belum terlihat penuh | Estimasi bisa meleset | Fase 0 mengumpulkan file backend sebelum mulai |
