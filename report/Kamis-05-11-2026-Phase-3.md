# Laporan Perkembangan Pengembangan Sistem — ELIPS LMS

**Hari/Tanggal:** Kamis, 5 November 2026
**Disusun oleh:** Tim Pengembang ELIPS
**Versi PRD:** 4.0
**Versi Log:** 4.0
**Commit terakhir:** `7cf0c4a` — feat: add session scheduling and attendance management for instructors and students
**Status proyek:** Dual-mode — PHP 8/MySQL 8 sebagai mode utama + fallback `localStorage`; v4.0 Phase 1–3 selesai

---

## 1. Ringkasan Eksekutif

Increment **v4.0 Phase 3 — Jadwal dan Presensi Offline** telah selesai diimplementasikan di atas fondasi Phase 1–2. Fokus increment ini adalah penyelenggaraan pertemuan per kelas: menyusun jadwal tunggal maupun berulang, mencatat JP rencana/aktual, mencatat presensi peserta per sesi, mengoreksi presensi dengan jejak audit, dan menghitung rekap kehadiran.

Perubahan menyentuh seluruh lapisan aplikasi secara konsisten:

1. **Skema MySQL** bertambah tiga tabel: `class_sessions`, `attendances`, dan `attendance_corrections` (total 17 tabel).
2. **API PHP** bertambah sepuluh aksi `session.*` dan `attendance.*` (total 49 aksi), dengan guard role dan kepemilikan di server.
3. **Mode lokal** memiliki perilaku setara melalui `crud-academic.js`, tiga key `localStorage` baru, data seed, dan audit koreksi.
4. **UI Admin, Instruktur, dan Peserta** diperluas dengan tabel sesi, modal jadwal tunggal/berulang, modal presensi, modal koreksi, dan rekap kehadiran.
5. **Dokumentasi** (`prd.md`, `log.md`, `erd.md`) diperbarui ke Phase 1–3.

Seluruh suite Phase 3 dan regresi lama lulus, termasuk 47 pemeriksaan UI lokal, 20 pemeriksaan UI server, serta 37 dan 47 pemeriksaan API/mode lokal.

---

## 2. Perubahan Utama

### 2.1 Skema Basis Data

Tiga tabel baru ditambahkan secara aditif tanpa mengubah semantik 14 tabel lama:

| Tabel | Fungsi | Constraint kunci |
|---|---|---|
| `class_sessions` | Sesi pertemuan per kelas: tanggal, jam, lokasi/ruang, modul, tentor, JP rencana/aktual, status, sesi pengganti | Unique `(class_id, session_number)`; index tanggal/status/modul/tentor; FK cascade ke kelas |
| `attendances` | Satu baris presensi per sesi dan enrollment | Unique `(session_id, enrollment_id)`; FK cascade ke sesi/enrollment/peserta |
| `attendance_corrections` | Audit koreksi presensi: status lama/baru, aktor, role, alasan, waktu | Append-only; FK cascade ke presensi/sesi/enrollment, `SET NULL` ke aktor |

Aturan hapus dijaga berlapis: aplikasi menolak penghapusan sesi `completed` dan sesi yang sudah memiliki presensi, sementara foreign key memastikan tidak ada baris presensi/koreksi yang menggantung.

### 2.2 Kebijakan Kehadiran

`api/config.php` menambahkan dua kunci kebijakan yang dapat diubah tanpa menyentuh logika:

- `attendance_present_statuses = ['present', 'late']` — status yang dihitung hadir;
- `attendance_counted_statuses = ['confirmed', 'completed']` — status sesi yang masuk perhitungan JP wajib.

Rekap dihitung sebagai **JP hadir ÷ JP wajib × 100**, dibulatkan dua desimal. Sesi `cancelled`, `no_show`, dan `rescheduled` tidak dihitung sebagai JP aktual maupun JP wajib.

### 2.3 Backend PHP

- `api/helpers.php` menambahkan:
  - helper baris `elips_session_row`, `elips_attendance_row`, dan `elips_attendance_correction_row`;
  - guard `elips_can_manage_session` (Admin semua kelas, Instruktur kelas yang diampu, Peserta hanya data sendiri);
  - `elips_attendance_policy` dan `elips_session_recap_weight` untuk perhitungan rekap;
  - serializer `elips_session_public`, `elips_attendance_public`, `elips_attendance_correction_public`, dan `elips_attendance_recap`;
  - validasi status, tanggal/jam, JP, modul dari versi kurikulum kelas, serta pencatatan audit koreksi.
- `api/actions.php` menambahkan:
  - `session.create`, `session.createBulk`, `session.update`, `session.status`, `session.delete`, `session.detail`;
  - `attendance.saveBulk`, `attendance.correct`, `attendance.recap`, `attendance.myRecap`.
- `api/snapshot.php` memuat koleksi `classSessions`, `attendances`, dan `attendanceCorrections`.
- `api/install.php` menambahkan seed 4 sesi untuk kelas migrasi (3 `completed`, 1 `scheduled`) dan 9 baris presensi untuk enrollment 1–3.

### 2.4 Jadwal Tunggal dan Berulang

- `session.create` menyimpan satu sesi dengan nomor pertemuan berikutnya yang unik per kelas.
- `session.createBulk` menerima salah satu dari:
  - daftar `dates[]` eksplisit, atau
  - `startDate` + `weekdays` ISO (1=Senin … 7=Minggu) + `occurrences` (maksimal 52).
- Waktu selesai wajib setelah waktu mulai; modul sesi harus berasal dari versi kurikulum kelas.
- Reschedule membuat sesi pengganti baru dan menandai sesi asal `rescheduled`; sesi baru menyimpan `rescheduledFromId`.

### 2.5 Presensi dan Koreksi

- `attendance.saveBulk` menyimpan seluruh baris satu sesi dan memperbarui baris yang sudah ada, sehingga unique `(sessionId, enrollmentId)` terjaga.
- Status presensi: `present`, `late`, `permission`, `sick`, `absent`, `excused`.
- Perubahan status yang sudah tercatat dianggap koreksi dan **wajib menyertakan alasan**. Server menulis baris `attendance_corrections` berisi nilai lama/baru, aktor, role, alasan, dan waktu.
- `attendance.recap` tersedia untuk Admin dan Instruktur pengampu; `attendance.myRecap` hanya mengembalikan data peserta yang sedang login.

### 2.6 Client dan Mode Lokal

- `assets/js/core.js`: key `elips_classSessions`, `elips_attendances`, `elips_attendanceCorrections`.
- `assets/js/data.js`: accessor sesi per kelas, presensi per sesi/enrollment/peserta, dan audit koreksi.
- `assets/js/seed.js`: data demo sesi dan presensi dengan baseline rekap Budi 100% (12 JP) dan Rina 66,67% (1 `absent`, 1 `late`).
- `assets/js/audit.js`: label aksi `attendance`/`correct` dan entitas `session`/`attendance`.
- `assets/js/crud-academic.js`: `getAttendancePolicy`, `createSession`, `createBulkSessions`, `updateSession`, `setSessionStatus`, `deleteSession`, `getSessionDetail`, `saveAttendanceBulk`, `correctAttendance`, `getAttendanceRecap`, dan `getMyAttendanceRecap`.

### 2.7 UI per Role

- **Admin (`admin/academic.html`)**: tabel sesi dengan ringkasan dan filter kelas, modal sesi tunggal, modal jadwal berulang, modal presensi dengan alasan wajib saat koreksi, serta modal rekap per peserta/sesi.
- **Instruktur (`instructor/dashboard.html`)**: tabel sesi khusus kelas yang diampu, buat/ubah/batalkan sesi, pencatatan presensi, dan rekap.
- **Peserta (`student/dashboard.html`)**: kartu statistik kehadiran, badge kebijakan, dan rekap per kelas dengan detail sesi milik sendiri.

### 2.8 Aturan Bisnis yang Ditegakkan

- Sesi `completed` tidak dapat dihapus.
- Sesi yang sudah memiliki presensi tidak dapat dihapus; gunakan pembatalan agar riwayat tetap utuh.
- Sesi `cancelled`, `no_show`, dan `rescheduled` tidak dihitung sebagai JP aktual.
- Satu enrollment hanya memiliki satu baris presensi per sesi.
- Koreksi presensi tanpa alasan ditolak di kedua mode.
- Peserta tidak dapat membaca presensi atau rekap peserta lain.
- Admin dapat mengelola seluruh kelas; Instruktur hanya kelas yang diampu.

---

## 3. Statistik Proyek

| Komponen | Jumlah |
|---|---|
| Halaman HTML | 26 |
| Modul JavaScript | 18 |
| File CSS | 1 (`assets/css/style.css`) |
| Tabel MySQL | 17 (15 domain inti + 2 operasional) |
| Aksi API | 49 |
| Key `localStorage` | 18 |
| Role pengguna | 3 (Admin, Instruktur, Peserta) |
| Commit git | 17 (termasuk commit laporan ini) |

### 3.1 Sebaran Halaman

| Area | Jumlah | Halaman |
|---|---|---|
| Publik (root) | 7 | `index`, `login`, `register`, `course-detail`, `learning`, `certificate-view`, `verify-certificate` |
| Admin | 10 | `dashboard`, `courses`, `academic`, `users`, `enrollment`, `certificates`, `notifications`, `reports`, `audit`, `settings` |
| Instruktur | 5 | `dashboard`, `manage-course`, `students`, `certificates`, `profile` |
| Peserta | 4 | `dashboard`, `courses`, `certificates`, `profile` |

### 3.2 Urutan Modul JavaScript

```text
config → core → api → utils → data → seed → sync → progress → auth → audit → notifications
→ crud-users → crud-courses → crud-enrollments
→ crud-content → crud-academic → crud-grades → ui
```

### 3.3 Key Penyimpanan

| Key | Fungsi |
|---|---|
| `elips_users` | Data pengguna (Admin, Instruktur, Peserta) |
| `elips_categories` | Kategori kursus |
| `elips_courses` | Kursus dan instruktur pengampu |
| `elips_curriculumVersions` | Versi kurikulum per kursus |
| `elips_classes` | Kelas/angkatan |
| `elips_classInstructorHistories` | Histori pergantian tentor |
| `elips_enrollments` | Pendaftaran peserta ke kelas |
| `elips_classSessions` | Sesi pertemuan per kelas |
| `elips_attendances` | Presensi peserta per sesi |
| `elips_attendanceCorrections` | Audit koreksi presensi |
| `elips_modules` | Modul per versi kurikulum |
| `elips_lessons` | Materi per modul/versi |
| `elips_grades` | Nilai per enrollment |
| `elips_certificates` | E-sertifikat Draft/Publish |
| `elips_completedLessons` | Tracking pelajaran selesai |
| `elips_auditLogs` | Jejak audit perubahan |
| `elips_notifications` | Outbox notifikasi email simulasi |
| `elips_session` | Sesi pengguna aktif (mode lokal) |

### 3.4 Tabel MySQL

| # | Tabel | Fungsi |
|---|---|---|
| 1 | `categories` | Kategori kursus |
| 2 | `users` | Akun pengguna |
| 3 | `courses` | Kursus dan instruktur pengampu |
| 4 | `curriculum_versions` | Versi kurikulum per kursus |
| 5 | `classes` | Kelas/angkatan |
| 6 | `class_instructor_histories` | Histori pergantian tentor |
| 7 | `modules` | Modul per versi kurikulum |
| 8 | `lessons` | Materi per modul |
| 9 | `enrollments` | Pendaftaran peserta ke kelas |
| 10 | `class_sessions` | Sesi pertemuan per kelas |
| 11 | `attendances` | Presensi peserta per sesi |
| 12 | `attendance_corrections` | Audit koreksi presensi |
| 13 | `grades` | Nilai per enrollment |
| 14 | `certificates` | E-sertifikat Draft/Publish |
| 15 | `completed_lessons` | Progres materi peserta |
| 16 | `audit_logs` | Jejak audit operasional |
| 17 | `notifications` | Outbox notifikasi email simulasi |

### 3.5 Data Demo (Seed)

| Entitas | Jumlah |
|---|---|
| Pengguna | 7 |
| Kategori | 4 |
| Kursus | 4 |
| Versi kurikulum | 4 |
| Modul | 25 |
| Materi | 75 |
| Kelas | 1 (`ANG-2026-MNJ-01`, kelas migrasi) |
| Histori tentor | 1 |
| Enrollment | 3 |
| Sesi pertemuan | 4 (3 `completed`, 1 `scheduled`) |
| Presensi | 9 |

Baseline rekap seed: Budi Santoso 100% (12 JP), Rina Setiawati 66,67% (1 `absent`, 1 `late`).

### 3.6 Akun Demo

| ID | Nama | Role |
|---|---|---|
| 1 | Administrator | Admin |
| 2 | Ahmad Fauzi | Instruktur |
| 3 | Siti Nurhaliza | Instruktur |
| 4 | Budi Santoso | Instruktur |
| 5 | Budi Santoso | Peserta |
| 6 | Ani Nurhayati | Peserta |
| 7 | Rina Setiawati | Peserta |

Email demo: Admin `admin@example.com`; Instruktur `ahmad@example.com` / `siti@example.com`; Peserta `budi.student@example.com` / `ani@example.com` / `rina@example.com`. Password semua akun demo: `elips123`.

---

## 4. Fitur yang Sudah Selesai

### 4.1 Autentikasi & Hak Akses
- Login dengan verifikasi password hash di server.
- Sesi PHP native dengan cookie `HttpOnly`/`SameSite=Lax`.
- Redirect otomatis sesuai role.
- Role guard di halaman dan pengecekan role/kepemilikan di setiap aksi API.
- Fallback mode lokal tetap tersedia untuk demo dan pengujian JSDOM.

### 4.2 Manajemen Kursus & Kurikulum
- Daftar kursus publik hanya menampilkan status Published.
- Kursus baru selalu Draft; publikasi divalidasi (instruktur aktif, modul, dan materi).
- Penugasan satu instruktur per kursus.
- Versi kurikulum: buat, ubah, publish, clone, retire, dan hapus Draft.
- Penguncian versi otomatis saat kelas dibuat.
- Penolakan perubahan pada versi Locked/Retired.
- Kategori kursus terstruktur.

### 4.3 Kelas/Angkatan & Tentor
- CRUD kelas dengan kode unik, periode, kuota, lokasi/ruang, catatan jadwal, dan JP rencana.
- Pemilihan versi kurikulum Published/Locked.
- Satu tentor aktif per kelas; satu tentor dapat mengampu banyak kelas.
- Histori pergantian tentor dengan alasan dan aktor.
- Status kelas `draft/open/ready/ongoing/completed/cancelled/archived`.
- Validasi periode dan kuota.
- Kelas dengan enrollment tidak dapat dihapus.

### 4.4 Pengguna & Enrollment
- CRUD pengguna dengan filter role dan pencarian.
- Modal pilihan role saat menambah pengguna.
- Pendaftaran peserta ke kelas melalui `admin/enrollment.html` atau roster `admin/academic.html`.
- Pendaftaran duplikat pada kelas yang sama dilewati (idempoten).
- Kuota kelas ditegakkan saat pendaftaran dan saat update kuota.
- Status enrollment `active/completed/withdrawn/cancelled` (plus status lama yang dinormalisasi).
- `withdrawn` mencabut akses tanpa menghapus riwayat nilai/sertifikat.

### 4.5 Jadwal Sesi Pertemuan
- Penjadwalan sesi tunggal per kelas.
- Penjadwalan berulang dengan `dates[]` atau `startDate` + `weekdays` + `occurrences` (maks. 52).
- Nomor pertemuan unik per kelas dan penomoran otomatis berurutan.
- Tanggal, jam mulai/selesai, lokasi, ruang, modul, dan tentor sesi.
- JP rencana dan JP aktual; sesi `completed` tanpa JP aktual diisi dari JP rencana.
- Lifecycle `scheduled → confirmed → completed`, serta `cancelled`, `no_show`, dan `rescheduled`.
- Reschedule dengan sesi pengganti bertaut `rescheduledFromId`.
- Sesi `completed`/berpresensi tidak dapat dihapus; pembatalan mempertahankan riwayat.

### 4.6 Presensi & Rekap Kehadiran
- Pencatatan presensi per sesi dan per enrollment dengan enam status.
- Satu baris per `(sesi, enrollment)`; penyimpanan ulang memperbarui baris.
- Koreksi presensi wajib alasan dan menghasilkan baris audit.
- Rekap kehadiran per kelas dan per peserta sesuai kebijakan JP.
- Peserta hanya melihat presensi dan rekap miliknya sendiri.
- Tampilan rekap pada Admin, Instruktur, dan Peserta.

### 4.7 Konten & Pembelajaran
- CRUD modul dan materi (teks, video, file, link eksternal).
- Modul/materi terikat ke versi kurikulum.
- Validasi kepemilikan konten oleh instruktur.
- Penolakan perubahan pada versi Locked/Retired.
- Pembelajaran berurutan; materi berikutnya terkunci sampai sebelumnya selesai.
- Materi ditampilkan dari versi kurikulum yang terkunci pada kelas peserta.
- Mode pratinjau untuk Admin/Instruktur tanpa mengubah progres peserta.
- Tracking `completed_lessons`.

### 4.8 Penilaian & Sertifikat
- Input nilai 0–100 dengan konversi grade A–E dan catatan.
- Nilai terhubung ke enrollment, sehingga angkatan berbeda memiliki nilai terpisah.
- Nilai otomatis membuat/memperbarui e-sertifikat Draft.
- Sertifikat Draft tidak tampil dan tidak dapat diverifikasi peserta.
- Admin menyetujui Draft → Publish; nomor final dan tanggal approval disimpan.
- Sertifikat Publish tampil di halaman peserta dan dapat diverifikasi publik.

### 4.9 Operasional Admin
- Dashboard statistik.
- Laporan progres peserta per kursus (ekspor PDF/Excel masih berupa UI).
- Pengaturan umum, sertifikat, dan pembelajaran.
- Audit trail dengan filter dan ekspor CSV.
- Outbox notifikasi dengan filter dan ekspor CSV.
- Halaman akademik untuk kurikulum, kelas, jadwal sesi, presensi, dan rekap.

---

## 5. Arsitektur Saat Ini

- **Frontend:** HTML5, CSS3 (custom properties), Vanilla JavaScript.
- **Backend:** PHP 8 native, tanpa framework.
- **Database:** MySQL 8, database `elips_lms`, 17 tabel InnoDB/utf8mb4.
- **Autentikasi:** sesi PHP native; password di-hash dengan `password_hash()`.
- **Lapisan data & bisnis:** `window.ELIPS` pada 18 modul `assets/js/`.
- **Mode data:**
  - **Server:** API + MySQL, snapshot cache di client.
  - **Lokal:** `localStorage` sebagai fallback demo/uji.
- **Struktur:** berbasis role (`admin/`, `instructor/`, `student/`) dan backend terpisah (`api/`).
- **Aset eksternal:** Google Fonts dan Bootstrap Icons CDN, sehingga memerlukan koneksi internet.

### 5.1 Struktur Backend

```text
api/
├── index.php      # front controller dan routing aksi
├── actions.php    # 49 aksi bisnis
├── helpers.php    # auth, serializer, sanitizer, audit, notifikasi, helper kurikulum/kelas/sesi/presensi
├── snapshot.php   # pembangun snapshot untuk cache client
├── schema.sql     # skema MySQL 8 v4.0 Phase 1-3
├── install.php    # installer + seed data demo
├── db.php         # koneksi PDO
└── config.php     # konfigurasi database/sesi/kebijakan kehadiran
```

---

## 6. Hasil Pengujian

Pengujian dijalankan pada 5 November 2026 setelah implementasi v4.0 Phase 3.

| Pengujian | Cakupan | Hasil |
|---|---|---|
| `php api/install.php --fresh` | Pembuatan database, skema, dan seed sesi/presensi | ✅ Berhasil |
| `php -l api/*.php` | Pemeriksaan sintaks seluruh file PHP | ✅ 7 file tanpa error |
| `api-v4.js` | Lifecycle kurikulum, penguncian, kelas, kuota, histori, guard role | ✅ 39/39 |
| `api-v4-sessions.js` | Jadwal, JP, reschedule, presensi, koreksi, rekap, guard role | ✅ 37/37 |
| `local-v4.js` | Aturan kurikulum/kelas/enrollment di mode lokal | ✅ 36/36 |
| `local-v4-sessions.js` | Jadwal, presensi, koreksi, dan rekap di mode lokal | ✅ 47/47 |
| `test-sessions-page.js` | UI admin/instruktur/peserta, modal sesi, presensi, koreksi, rekap | ✅ 47/47 |
| `test-sessions-server.js` | Alur jadwal/presensi terhadap API PHP/MySQL | ✅ 20/20 |
| `test-academic-page.js` | Regresi halaman akademik | ✅ Lulus |
| `test-render.js` | Regresi render halaman peserta | ✅ Lulus |
| `test-instructor.js` | Regresi alur instruktur | ✅ Lulus |
| `test-server-mode.js` | Bootstrap, login API, CRUD via server, audit cache | ✅ 18/18 |

### 6.1 Catatan Pengujian

- Database dikembalikan ke kondisi seed bersih setelah seluruh suite server selesai.
- Suite server yang bergantung pada seed state dijalankan setelah `php api/install.php --fresh`.
- Pengujian UI mode server melakukan autentikasi ulang di setiap dokumen JSDOM karena cookie POST tidak diteruskan antar-instance.
- Baseline rekap seed diverifikasi: Budi 100% (12 JP) dan Rina 66,67% (1 `absent`, 1 `late`).
- Tidak ada error runtime pada seluruh halaman yang diuji.

---

## 7. Risiko & Keterbatasan

| Risiko/Keterbatasan | Dampak | Rekomendasi |
|---|---|---|
| Jadwal belum tersinkron kalender eksternal | Tidak ada ekspor iCal/Google Calendar | Tambahkan integrasi kalender pada phase berikutnya |
| Belum ada pengingat otomatis | Peserta/instruktur harus memantau jadwal manual | Tambahkan notifikasi terjadwal melalui outbox |
| Kebijakan kehadiran hanya lewat konfigurasi | Admin belum dapat mengubah kebijakan dari UI | Sediakan halaman pengaturan kehadiran |
| Rekap belum diekspor PDF/Excel | Rekap hanya dapat dilihat di layar | Implementasikan endpoint/library ekspor |
| Snapshot masih memuat koleksi secara luas | Belum optimal untuk dataset besar | Terapkan scoping snapshot dan pagination |
| Email masih simulasi (outbox) | Pengguna tidak menerima email nyata | Integrasikan SMTP/email service |
| Ekspor laporan PDF/Excel baru berupa UI | Belum menghasilkan file nyata | Implementasikan library ekspor atau endpoint |
| Belum ada rate limiting, CSRF token, dan rotasi sesi | Permukaan serangan belum sepenuhnya tertutup | Hardening keamanan sebelum produksi |
| HTTPS belum diwajibkan | Cookie sesi berisiko pada jaringan tidak aman | Wajibkan HTTPS di produksi |
| Aset CDN memerlukan internet | Tampilan ikon/font terganggu saat offline | Self-host font dan ikon |
| Belum ada pipeline CI | Regresi bergantung pada eksekusi manual | Tambahkan CI untuk seluruh suite |
| Mode lokal tidak aman untuk produksi | Password tersimpan di client | Gunakan hanya untuk demo/uji |

---

## 8. Rencana Selanjutnya

### 8.1 Phase 4–10

| Phase | Fokus |
|---|---|
| **Phase 4** | Materi dan akses per kelas, materi tambahan, file privat |
| **Phase 5** | Tugas, submission, review/revisi, ujian, rubrik |
| **Phase 6** | Skema penilaian, bobot komponen, kalkulasi nilai akhir, finalisasi |
| **Phase 7** | Kelayakan sertifikat, approval, PDF, QR/verifikasi, revokasi |
| **Phase 8** | Dashboard baru, laporan akademik, notifikasi lengkap, hardening, UAT, deployment |
| **Phase 9–10** | Integrasi lanjutan dan penyempurnaan operasional sesuai `ELIPS-LMS-PRD-v4.0.md` |

### 8.2 Prioritas Lintas Phase
1. **Hardening keamanan:** rate limiting login, CSRF token, HTTPS wajib, rotasi session ID.
2. **CI/CD:** jalankan seluruh suite lokal dan server pada setiap push.
3. **Ekspor laporan nyata:** PDF/Excel, termasuk rekap kehadiran.
4. **Self-host aset:** font dan ikon.
5. **Peningkatan UX:** pencarian global, pagination tabel, notifikasi in-app.

---

## 9. Cara Menjalankan

### 9.1 Mode Server (utama)

1. Pastikan MySQL 8 berjalan.
2. Dari root proyek, jalankan:

   ```powershell
   php api/install.php --fresh
   ```

3. Sajikan folder proyek:

   ```powershell
   php -S 127.0.0.1:8099 -t .
   ```

4. Buka `http://127.0.0.1:8099/` dan login dengan akun demo.
5. Untuk mereset data demo, ulangi langkah 2.

### 9.2 Mode Lokal (fallback/demo)

1. Buka `index.html` langsung di browser modern.
2. Data demo akan di-seed otomatis ke `localStorage`.
3. Untuk mereset, hapus seluruh key `elips_*` pada Application → Local Storage, lalu muat ulang.

---

## 10. Kesimpulan

Increment **v4.0 Phase 3** melengkapi fondasi akademik dengan penyelenggaraan pertemuan yang dapat dijadwalkan, dihitung JP-nya, dan dipresensi secara konsisten di mode server maupun lokal. Tiga tabel baru, sepuluh aksi API, dan perluasan UI pada ketiga role membuat alur jadwal–presensi–rekap dapat digunakan end-to-end tanpa merusak alur v3.0 maupun Phase 1–2.

Koreksi presensi yang wajib beralasan dan tersimpan sebagai audit append-only menjaga integritas data akademik, sementara rekap berbasis kebijakan JP memudahkan pemantauan kehadiran peserta. Dengan seluruh pengujian Phase 3 dan regresi lama lulus, langkah berikutnya adalah **Phase 4: materi dan akses per kelas**, diikuti Phase 5–10 sesuai roadmap v4.0.
