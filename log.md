# Log Pengembangan ELIPS LMS

**Versi dokumen:** 4.0  
**Terakhir diperbarui:** 2026-11-13
**Status proyek:** Dual-mode - PHP 8/MySQL 8 + fallback `localStorage`; v4.0 Phase 1-6 (kurikulum, kelas/angkatan, enrollment berbasis kelas, jadwal & presensi offline, materi tambahan & mode akses materi, tugas & ujian, skema penilaian/finalisasi/koreksi) plus cabang, kalender jadwal Admin, aksi langsung dan filter jadwal lanjutan, absensi staf berbasis kamera, absensi karyawan (role `karyawan`, jam server, GPS wajib, aturan harian, modul bersama, halaman `employee/attendance.html`, dan sesi diingat dengan rotasi token per perangkat), PWA (manifest, service worker network-first, banner pemasangan, dan mode offline), rekap absensi karyawan untuk Admin (filter, ringkasan status, dan ekspor CSV), jadwal shift karyawan (Pagi/Sore/custom, satu shift per tanggal, dan audit), surat menyurat (surat masuk/keluar beserta detail ringkas, filter, audit, dan 14 surat dummy), divisi karyawan (`users.division`, pilihan Marketing/Operasional/Keuangan/HRD, seed akun Marketing, dan dashboard `employee/dashboard.html`), serta dashboard Marketing (lead, follow-up, target bulanan, pipeline, aksi hari ini, dan seed 12 lead/12 follow-up/6 target)
**Dokumen terkait:** `prd.md`, `erd.md`, `ELIPS-LMS-PRD-v4.0.md`

---

## 1. Ringkasan Sistem

ELIPS LMS adalah aplikasi Learning Management System berbasis web untuk mengelola kursus, instruktur, peserta, materi, penilaian, dan e-sertifikat. Aplikasi berjalan dual-mode: mode utama memakai backend PHP 8 + MySQL 8, sedangkan mode fallback memakai `localStorage` saat API tidak tersedia (mis. `file://` atau regression test JSDOM).

Empat role utama:

| Role | Tanggung jawab |
|---|---|
| **Admin** | Membuat kursus Draft, mengelola versi kurikulum, membuat angkatan/kelas, menugaskan 1 tentor per kelas, mengatur mode akses materi dan override dengan alasan, mengelola pengguna, mendaftarkan peserta ke kelas, menyetujui sertifikat Draft → Publish, menelusuri audit trail, memantau outbox notifikasi, mencatat absensi pribadi, mengelola jadwal shift karyawan (Pagi/Sore/custom) dan rekap absensi, serta mencatat surat masuk/keluar |
| **Instruktur** | Mengelola modul/materi pada versi kurikulum yang belum terkunci, mengatur akses dan materi tambahan kelas, mengonfirmasi penyelesaian materi, memantau progres peserta, mengelola tugas/ujian, menyusun skema penilaian, memberi nilai komponen, memfinalisasi nilai, dan mengoreksi nilai dengan alasan |
| **Peserta** | Belajar materi dari kelas yang diikuti sesuai mode akses, menandai pelajaran/materi selesai bila diizinkan, melihat status persetujuan instruktur, mengumpulkan tugas, melihat hasil ujian dan transkrip nilai sendiri, serta melihat sertifikat yang sudah Publish |
| **Karyawan** | Absen masuk/pulang harian dengan foto dan GPS, melihat riwayat absensi sendiri, memasang aplikasi PWA, serta—khusus divisi Marketing—mengelola lead, follow-up, dan target pendaftaran/pendapatan bulanan; jadwal shift dikelola Admin |

Alur utama:

```text
Admin buat kursus (Draft)
  → Admin susun versi kurikulum (Draft → Published)
  → Admin buat angkatan/kelas (versi dikunci, 1 tentor)
  → Admin daftarkan peserta ke kelas (kuota & status)
  → Instruktur buat modul & materi pada versi yang dapat diubah
  → Peserta belajar (kelas diikuti & kursus Published)
  → Instruktur mengelola tugas/ujian dan skema penilaian
  → Peserta mengumpulkan tugas / mengikuti ujian
  → Instruktur memberi nilai komponen, memfinalisasi nilai (terkunci)
  → Koreksi beralasan bila diperlukan (append-only)
  → Instruktur beri nilai per enrollment (alur klasik)
  → Sistem buat e-sertifikat Draft (tidak tampil ke peserta)
  → Admin setujui
  → E-sertifikat Publish (tampil & dapat diverifikasi)
  → Setiap perubahan kursus, kurikulum, kelas, nilai, dan sertifikat tercatat di audit trail
  → Notifikasi email simulasi tercatat saat kursus Publish / sertifikat terbit
```

---

## 2. Struktur Proyek

```text
ELIPS/
├── index.html
├── login.html
├── register.html
├── course-detail.html
├── learning.html
├── certificate-view.html
├── verify-certificate.html
├── offline.html
├── manifest.webmanifest
├── sw.js
├── prd.md
├── log.md
├── erd.md
├── api/
│   ├── index.php
│   ├── config.php
│   ├── db.php
│   ├── helpers.php
│   ├── snapshot.php
│   ├── actions.php
│   ├── schema.sql
│   ├── install.php
│   ├── seed-karyawan.php
│   └── seed-letters.php
├── admin/
│   ├── dashboard.html
│   ├── courses.html
│   ├── academic.html
│   ├── schedule.html
│   ├── users.html
│   ├── enrollment.html
│   ├── certificates.html
│   ├── notifications.html
│   ├── reports.html
│   ├── audit.html
│   ├── attendance-recap.html
│   ├── shifts.html
│   ├── letters.html
│   └── settings.html
├── instructor/
│   ├── dashboard.html
│   ├── manage-course.html
│   ├── students.html
│   ├── certificates.html
│   └── profile.html
├── student/
│   ├── dashboard.html
│   ├── courses.html
│   ├── certificates.html
│   └── profile.html
├── employee/
│   └── attendance.html
└── assets/
    ├── css/style.css
    ├── icons/
    │   ├── icon-192.png
    │   ├── icon-512.png
    │   ├── icon-maskable-192.png
    │   ├── icon-maskable-512.png
    │   ├── apple-touch-icon.png
    │   └── favicon-32.png
    └── js/
        ├── config.js
        ├── core.js
        ├── api.js
        ├── utils.js
        ├── data.js
        ├── seed.js
        ├── sync.js
        ├── progress.js
        ├── auth.js
        ├── audit.js
        ├── notifications.js
        ├── crud-users.js
        ├── crud-courses.js
        ├── crud-enrollments.js
        ├── crud-content.js
        ├── crud-academic.js
        ├── crud-tasks.js
        ├── crud-grades.js
        ├── ui.js
        ├── attendance.js
        ├── staff-recap.js
        ├── shift-schedule.js
        ├── letter-archive.js
        └── pwa.js
```

### 2.1 Tanggung Jawab Modul JavaScript

Semua halaman memuat modul dengan urutan berikut:

```text
config → core → api → utils → data → seed → sync → progress → auth → audit → notifications
→ crud-users → crud-courses → crud-enrollments
→ crud-content → crud-academic → crud-tasks → crud-grades → crud-marketing → ui → attendance → staff-recap → shift-schedule → letter-archive
```

`pwa.js` tidak termasuk urutan bundle di atas; modul ini dimuat terpisah dengan `defer` di `<head>` setiap halaman aplikasi (dan sengaja tidak dimasukkan ke bundle uji JSDOM). `employee-dashboard.js` juga berada di luar bundle bersama dan hanya dimuat oleh `employee/dashboard.html` setelah `ui.js`.

| Modul | Tanggung jawab | API utama |
|---|---|---|
| `config.js` | Menentukan `window.ELIPS_CONFIG.apiBase` relatif terhadap halaman | `ELIPS_CONFIG.apiBase` |
| `core.js` | Namespace `window.ELIPS`, daftar key storage, helper baca/tulis, `nextId`, objek hasil `{ ok, message, data }`, validasi dasar termasuk divisi karyawan, password demo | `ELIPS.read`, `ELIPS.write`, `ELIPS.nextId`, `ELIPS.DEFAULT_PASSWORD`, `ELIPS.USER_DIVISIONS`, `ELIPS.divisionLabel`, `ELIPS._` |
| `api.js` | Jembatan dual-mode: bootstrap snapshot, cache sinkron, panggilan aksi tulis, fallback lokal | `ELIPS.api.enabled`, `ELIPS.api.call`, `ELIPS.api.refresh` |
| `utils.js` | Konversi nilai ke huruf, nomor sertifikat, escape HTML, sanitasi HTML materi, format tanggal, toast | `gradeLetter`, `generateCertificateNumber`, `escapeHtml`, `sanitizeHtml`, `formatDate`, `toast` |
| `data.js` | Getter/setter seluruh koleksi data termasuk tugas/ujian, predikat, skema nilai, nilai komponen, koreksi, dan Marketing (lead/follow-up/target); mesin komputasi nilai lokal; tidak berisi aturan bisnis | `getUsers`, `getBranches`, `getCourses`, `getCurriculumVersions`, `getClasses`, `getClassInstructorHistories`, `getEnrollments`, `getClassSessions`, `getSessionsByClass`, `getAttendances`, `getSessionAttendances`, `getAttendanceCorrections`, `getModules`, `getLessons`, `getGrades`, `getPredicateRanges`, `getGradeSchemes`, `getGradeSchemeComponents`, `getGradeComponentScores`, `getGradeCorrections`, `getAssignments`, `getSubmissions`, `getExams`, `getExamResults`, `getCertificates`, `getCompletedLessons`, `getPublishedCertificatesByStudent`, `getStaffAttendances`, `getStaffAttendancesByUser`, `getMarketingLeads`, `getMarketingFollowups`, `getMarketingTargets`, `getMarketingTarget`, `predicateLetter`, `computeEnrollmentGrade` |
| `seed.js` | Data demo awal (termasuk sesi & presensi) dan migrasi/normalisasi data lama; dilewati pada mode server | dijalankan otomatis saat modul dimuat (kecuali mode server) |
| `sync.js` | Sinkronisasi data turunan: nama instruktur, kepemilikan konten, counter modul/materi, pelepasan kursus | `syncCourseCounters` dan helper internal `syncAllContentOwners`, `releaseInstructorCourses` |
| `progress.js` | Struktur kursus, progres belajar, overview siswa dan instruktur | `getCourseStructure`, `getCourseProgress`, `isLessonUnlocked`, `getStudentCourseOverview`, `getInstructorCourses`, `getInstructorSummary` |
| `auth.js` | Session, user aktif (tanpa fallback path), path dashboard per role, guard halaman | `getSessionUser`, `getCurrentUser`, `requireRole`, `setCurrentUser`, `logout` |
| `audit.js` | Audit trail: pencatatan aktor/aksi/entitas, filter log, daftar aktor, pembersihan log | `logAudit`, `getAuditLogs`, `getAuditActors`, `clearAuditLogs`, `auditActionLabel`, `auditEntityLabel` |
| `notifications.js` | Outbox notifikasi email simulasi: pembuatan saat kursus Published/sertifikat terbit, filter, penerima, pembersihan | `createNotification`, `notifyCoursePublished`, `notifyCertificatePublished`, `getNotifications`, `getNotificationRecipients`, `clearNotifications`, `notificationTypeLabel` |
| `crud-users.js` | CRUD pengguna, autentikasi email/password, validasi/pengosongan divisi karyawan, dan penugasan instruktur ke kursus | `createUser`, `updateUser`, `deleteUser`, `authenticateUser`, `assignInstructorToCourses` |
| `crud-courses.js` | CRUD kursus dan aturan Draft/Publish | `createCourse`, `updateCourse`, `deleteCourse`, `setCourseStatus`, `toggleCourseStatus` |
| `crud-enrollments.js` | CRUD pendaftaran peserta ke kelas | `assignStudentToCourses`, `assignStudentsToClass`, `setEnrollmentClassStatus`, `setEnrollmentStatus`, `deleteEnrollment` |
| `crud-content.js` | CRUD modul dan materi dengan validasi kepemilikan dan versi kurikulum | `createModule`, `updateModule`, `deleteModule`, `createLesson`, `updateLesson`, `deleteLesson` |
| `crud-academic.js` | CRUD cabang, versi kurikulum, kelas/angkatan dengan penugasan cabang, histori tentor, roster, validasi kuota, jadwal sesi (tunggal/berulang), presensi, koreksi beralasan, dan rekap kehadiran | `createBranch`, `updateBranch`, `setBranchStatus`, `deleteBranch`, `createCurriculumVersion`, `updateCurriculumVersion`, `publishCurriculumVersion`, `cloneCurriculumVersion`, `retireCurriculumVersion`, `deleteCurriculumVersion`, `getCurriculumDetail`, `createClass`, `updateClass`, `setClassStatus`, `assignClassInstructor`, `deleteClass`, `assignStudentsToClass`, `setEnrollmentClassStatus`, `getAttendancePolicy`, `createSession`, `createBulkSessions`, `updateSession`, `setSessionStatus`, `deleteSession`, `getSessionDetail`, `saveAttendanceBulk`, `correctAttendance`, `getAttendanceRecap`, `getMyAttendanceRecap` |
| `crud-tasks.js` | CRUD tugas, lampiran, rubrik, submission peserta, review/revisi, ujian, peserta ujian, dan hasil ujian dengan validasi kepemilikan serta status; paritas server/lokal | `createAssignment`, `updateAssignment`, `setAssignmentStatus`, `deleteAssignment`, `submitAssignment`, `reviewSubmission`, `requestRevision`, `acceptSubmission`, `createExam`, `updateExam`, `setExamStatus`, `deleteExam`, `saveExamParticipants`, `saveExamResult` |
| `crud-grades.js` | Skema penilaian, komponen berbobot, roster & nilai komponen, pengecualian, finalisasi, koreksi beraudit, transkrip, serta alur sertifikat Draft → Publish | `saveGrade`, `listPredicates`, `savePredicates`, `listGradeSchemes`, `saveGradeScheme`, `setGradeSchemeStatus`, `deleteGradeScheme`, `getGradeRoster`, `saveManualGrade`, `exemptGradeComponent`, `finalizeGrade`, `correctGrade`, `getGradeTranscript`, `createDraftCertificateForEnrollment`, `approveCertificate` |
| `ui.js` | Perilaku UI bersama: navigasi mobile, sidebar dashboard, katalog beranda, login (email + password), logout, style toast | dijalankan pada `DOMContentLoaded` |
| `attendance.js` | Modul absensi harian bersama untuk Admin dan Karyawan: hero status, kamera/unggah foto, GPS wajib, aturan masuk/pulang, riwayat pribadi, detail, modal berhasil, dan hapus oleh Admin dengan alasan | `ELIPS.attendance.init`, `ELIPS.attendance.refresh`, `openAttendanceModal`, `saveStaffAttendance`, `loadAttendanceHistory`, `openAttendanceDetail`, `deleteStaffAttendance` |
| `staff-recap.js` | Rekap absensi Admin: filter tanggal/karyawan/role, agregasi per karyawan per hari, ringkasan status, dan ekspor CSV | `ELIPS.staffRecap.init`, `ELIPS.staffRecap.refresh`, `ELIPS.staffRecap.exportCsv` |
| `shift-schedule.js` | Jadwal shift Admin: filter tanggal/karyawan/jenis, preset Pagi/Sore, jam Custom, validasi konflik, CRUD server/lokal, dan audit `staff_shift` | `ELIPS.shiftSchedule.init`, `ELIPS.shiftSchedule.refresh`, `ELIPS.shiftSchedule.presets` |
| `letter-archive.js` | Surat menyurat Admin: filter jenis/tanggal/pencarian, ringkasan statistik, tabel surat masuk/keluar, detail ringkas, CRUD server/lokal, dan audit `letter` | `ELIPS.letterArchive.init`, `ELIPS.letterArchive.refresh`, `ELIPS.letterArchive.directionLabel`, `ELIPS.letterArchive.classificationLabel` |
| `crud-marketing.js` | Logika Marketing: CRUD lead/follow-up/target, validasi status/sumber/kanal/periode, sinkronisasi jadwal follow-up, agregasi dashboard (pipeline, aksi hari ini, capaian target), audit, dan jalur server/lokal | `ELIPS.marketingDashboard`, `ELIPS.saveMarketingLead`, `ELIPS.deleteMarketingLead`, `ELIPS.saveMarketingFollowup`, `ELIPS.deleteMarketingFollowup`, `ELIPS.saveMarketingTarget`, `ELIPS.marketingStatusLabel`, `ELIPS.marketingSourceLabel`, `ELIPS.marketingChannelLabel` |
| `employee-dashboard.js` | Dashboard karyawan: sapaan/nama/divisi, status absensi hari ini, rekap hari/catatan bulan berjalan, riwayat terbaru, serta widget Marketing (statistik, target, pipeline, aksi hari ini, tabel lead, dan modal) untuk divisi Marketing | dijalankan pada `DOMContentLoaded` halaman `employee/dashboard.html` |
| `pwa.js` | Pendaftaran service worker (skip waiting + `clients.claim`, reload sekali saat versi baru), banner pemasangan Android (`beforeinstallprompt`), sheet 3 langkah iOS Safari, penolakan 7 hari, prompt sekali per sesi, dan bilah offline dengan tombol coba lagi | `navigator.serviceWorker.register`, `beforeinstallprompt`, `online`/`offline` |

Prinsip yang dipakai:

- Halaman hanya melakukan **rendering dan interaksi UI**.
- Seluruh aturan bisnis dan penulisan data melewati fungsi `ELIPS.*`.
- Fungsi CRUD mengembalikan `{ ok, message, data }` agar halaman dapat menampilkan pesan yang konsisten.
- Pada mode server, CRUD meneruskan aksi ke API dan mengganti cache dari snapshot terbaru; backend menegakkan role/kepemilikan.
- Helper lintas modul yang tidak perlu diekspos ke halaman disimpan di `ELIPS._`.

---

## 3. Riwayat Perubahan

### v4.0 - 2026-11-13 - Dashboard Marketing (lead, follow-up, target bulanan)

**Tujuan:** memberi karyawan divisi Marketing area kerja di `employee/dashboard.html` untuk mengelola lead, mencatat follow-up, dan memantau target pendaftaran/pendapatan bulanan, tanpa mengubah alur absensi yang sudah ada.

Perubahan file:

- `api/schema.sql`: tiga tabel baru `marketing_leads`, `marketing_followups`, dan `marketing_targets` beserta index dan foreign key `ON DELETE CASCADE`; total skema menjadi 43 tabel.
- `api/install.php`: tidak perlu logika khusus karena installer mengeksekusi `schema.sql` dengan `CREATE TABLE IF NOT EXISTS`, sehingga tiga tabel Marketing ikut dibuat pada instalasi baru maupun database lama.
- `api/helpers.php`: guard `elips_require_marketing_access()`, validasi status/sumber/kanal/periode, helper baris dan serializer lead/follow-up/target, serta scope pemilik untuk Admin.
- `api/actions.php`: enam aksi baru `marketing.dashboard`, `marketing.lead.save`, `marketing.lead.delete`, `marketing.followup.save`, `marketing.followup.delete`, dan `marketing.target.save` (total 108 aksi).
- `api/seed-marketing-leads.php` (baru): seed idempoten 12 lead, 12 follow-up, dan 6 target periode `2026-11` untuk enam akun Marketing (`andi`, `bella`, `candra`, `dinda`, `fajar`, `gita`).
- `assets/js/core.js`: key `marketingLeads`, `marketingFollowups`, dan `marketingTargets` (total 43 storage key).
- `assets/js/data.js`: getter/setter lead, follow-up, dan target beserta filter per pemilik/lead/periode.
- `assets/js/audit.js`: label entitas `marketing_lead`, `marketing_followup`, dan `marketing_target`.
- `assets/js/crud-marketing.js` (baru, modul JS ke-26): CRUD dual-mode, validasi, sinkronisasi jadwal follow-up, konversi lead, agregasi dashboard, dan audit.
- `employee/dashboard.html`: widget Marketing (ringkasan, progres target, aksi hari ini, pipeline, tabel lead) dan tiga modal lead/follow-up/target; hanya tampil untuk divisi Marketing.
- `assets/js/employee-dashboard.js`: render dan event widget Marketing, cakupan Admin, serta pemuatan data via `marketing.dashboard` atau `localStorage`.
- `assets/css/style.css`: gaya kartu statistik, progres target, pipeline, tabel lead, dan badge status/sumber Marketing.
- `prd.md`, `erd.md`, `log.md`, `report/2026-11-12-kamis.md`: dokumentasi skema, aksi, aturan bisnis, angka cakupan, dan hasil uji Marketing.

Perilaku:

- Widget Marketing hanya dirender bila `division === 'marketing'`; divisi lain tetap memakai dashboard absensi saja.
- Karyawan selalu melihat dan mengubah data miliknya. Admin dapat memantau seluruh data atau memilih satu karyawan; role/divisi lain ditolak guard.
- Status lead: `baru`, `dihubungi`, `prospek`, `terdaftar`, `batal`. Sumber: `walk_in`, `referensi`, `sekolah`, `sosial_media`, `pameran`, `telemarketing`, `lainnya`.
- Follow-up memakai kanal `telepon`, `whatsapp`, `email`, `kunjungan`, `lainnya`; mencatat follow-up menyinkronkan `nextFollowupDate` lead dan menaikkan status `baru` menjadi `dihubungi`.
- Status `terdaftar` mengisi `convertedAt` sekali dan nilai `enrollmentValue` dihitung ke capaian pendaftaran/pendapatan pada periode berjalan.
- Target disimpan sebagai satu baris per karyawan + periode `YYYY-MM` melalui upsert; menghapus lead ikut menghapus follow-up terkait.
- Data Marketing tidak dimasukkan ke snapshot bootstrap karena memuat kontak pribadi; mode lokal memakai tiga key terpisah dengan validasi dan audit yang sama.

Pengujian:

- `test-marketing-api` 24/24: login Marketing, dashboard milik sendiri, CRUD lead/follow-up/target, konversi `terdaftar`, sinkronisasi jadwal, penolakan lintas pemilik, cakupan Admin, dan penolakan karyawan divisi lain.
- `test-marketing-dashboard` 27/27: area hanya tampil untuk divisi Marketing, statistik/pipeline/aksi hari ini, CRUD lead/follow-up/target, sinkronisasi status & jadwal, hapus berantai, dan divisi lain tetap tanpa widget Marketing.
- Regresi lulus: `php -l` pada PHP yang diubah, `node --check` pada JS yang diubah, `validate-scripts` 33 HTML/27 JS, `test-all-pages` 30 halaman, `test-employee-dashboard` 19/19, `test-employee-attendance-page`, `test-employee-routing`, `test-division-api` 9/9, dan `test-pwa` 50/50.
- Database demo terverifikasi berisi 43 tabel, 12 lead, 12 follow-up, dan 6 target Marketing.

Dampak angka: 43 tabel MySQL, 108 aksi API, 43 storage key, 26 modul JS di `assets/js/`, 33 halaman HTML, dan 27 file JavaScript (termasuk `sw.js`).

Batasan:

- Marketing MVP belum mencakup pipeline multi-tahap lanjutan, penugasan lead antar-karyawan, impor/ekspor CSV, pengingat otomatis, atau integrasi kampanye iklan.
- Perubahan parsial lead berbeda antar-mode: server mengharapkan payload lengkap saat update, sedangkan CRUD lokal menggabungkan nilai lama.
- Target bersifat manual per periode; belum ada persetujuan atasan atau histori perubahan target.

---

### v4.0 - 2026-11-12 - Divisi karyawan + dashboard karyawan

**Tujuan:** menambahkan divisi pada role `karyawan` (dimulai dari Marketing), menyediakan akun dummy divisi Marketing, dan memberi karyawan dashboard sederhana sebelum masuk ke halaman absensi.

Perubahan file:

- `api/schema.sql`, `api/install.php`: kolom `users.division VARCHAR(40) NOT NULL DEFAULT ''` setelah `role` dan index `idx_users_division`; migrasi idempoten untuk database lama.
- `api/helpers.php`: `elips_serialize_user()` mengekspos `division`; helper `elips_user_divisions()` dan `elips_normalize_division()`.
- `api/actions.php`: `user.create`/`user.update` menerima dan memvalidasi `division`, menolak divisi tak dikenal, mengosongkan divisi untuk role selain `karyawan`, serta mencatat perubahan divisi di audit.
- `assets/js/core.js`: daftar `USER_DIVISIONS`, `isValidDivision()`, `divisionLabel()`, dan ekspos `ELIPS.USER_DIVISIONS`/`ELIPS.divisionLabel`.
- `assets/js/crud-users.js`: validasi, penyimpanan lokal, pengosongan otomatis, dan audit divisi.
- `admin/users.html`: select divisi hanya tampil untuk role Karyawan, kolom **Divisi** pada tabel, dan sinkronisasi saat role berubah.
- `api/seed-karyawan.php`: 10 akun dummy lama diberi divisi bervariasi (marketing/operasional/keuangan/hrd).
- `api/seed-marketing.php` (baru): seed idempoten 6 akun karyawan divisi Marketing (`*.marketing@example.com`, password `elips123`).
- `employee/dashboard.html` (baru, halaman aplikasi ke-33): sapaan + nama/divisi, status absensi hari ini, jumlah hari/catatan absen bulan berjalan, divisi, akses cepat, dan lima absensi terbaru.
- `assets/js/employee-dashboard.js` (baru, modul JS ke-25): render dashboard, pemuatan riwayat via `staffAttendance.list` atau `localStorage`, dan label divisi.
- `assets/js/auth.js`, `assets/js/ui.js`: redirect login karyawan diarahkan ke `employee/dashboard.html`.
- `employee/attendance.html`: tombol navigasi **Dashboard**.
- `prd.md`, `erd.md`, `log.md`, `report/2026-11-12-kamis.md`: dokumentasi divisi, dashboard, angka cakupan, dan hasil uji.

Perilaku:

- Divisi hanya bermakna untuk role `karyawan`; role lain selalu menyimpan divisi kosong, termasuk saat role diubah dari Karyawan.
- Nilai divisi yang sah: `marketing`, `operasional`, `keuangan`, `hrd`. Nilai di luar daftar ditolak API dan CRUD lokal.
- Karyawan dapat memiliki divisi kosong (ditampilkan sebagai **Tanpa divisi**).
- Login karyawan diarahkan ke dashboard, lalu dashboard menautkan ke halaman absensi dan riwayat.
- Dashboard membaca data absensi sendiri; Admin tetap memakai rekap absensi terpisah.

Pengujian:

- `test-division-api` 9/9: login Admin, create/update divisi, penolakan nilai tak dikenal, pengosongan saat role berubah, snapshot memuat divisi, dan pembersihan data uji.
- `test-employee-dashboard` 19/19: helper divisi, sapaan/nama/divisi, status hari ini, rekap hari/catatan bulanan, riwayat, serta CRUD lokal divisi.
- `test-employee-attendance-page` dan `test-employee-routing` disesuaikan ke `employee/dashboard.html` dan lulus.
- `test-all-pages` 30 halaman (termasuk `employee/dashboard.html`) lulus; `validate-scripts` 33 HTML/26 JS lulus.
- `php -l` untuk `api/install.php`, `api/helpers.php`, `api/actions.php`, `api/seed-karyawan.php`, dan `api/seed-marketing.php` lulus.

Dampak angka: 40 tabel MySQL, 101 aksi API, 40 storage key, 25 modul JS di `assets/js/`, 33 halaman HTML, dan 26 file JavaScript (termasuk `sw.js`). Database demo kini berisi 23 pengguna: 9 Marketing, 3 Operasional, 2 Keuangan, 2 HRD, dan 7 tanpa divisi (non-karyawan).

Batasan:

- Divisi masih berupa teks bebas terbatas (bukan tabel relasi), sehingga penambahan divisi baru memerlukan perubahan daftar di API dan client.
- Saat increment divisi, dashboard karyawan masih ringkas: belum ada target/penilaian kinerja, pengajuan izin/cuti, atau integrasi jadwal shift. Target kinerja Marketing kemudian ditambahkan pada increment 2026-11-13; izin/cuti dan integrasi shift masih terbuka.

---

### v4.0 - 2026-11-12 - Penyederhanaan surat menyurat + 14 surat dummy

**Tujuan:** menyederhanakan popup surat dengan menghapus field yang tidak dipakai (status, ringkasan/isi, tautan, catatan) dan menyediakan 14 surat contoh untuk pengujian/demo.

Perubahan file:

- `admin/letters.html`: menghapus select **Status** pada filter, kartu **Selesai**, kolom **Status** pada tabel (colspan 7 → 6), serta field status, ringkasan/isi, tautan lampiran, dan catatan pada modal tambah/ubah.
- `assets/js/letter-archive.js`: menghapus filter status, badge/status helper, tautan lampiran aman, validasi ringkasan/catatan/tautan, dan baris detail terkait; detail kini hanya menampilkan jenis, nomor, tanggal, perihal, pengirim, penerima, kategori, sifat, dan pembuat. Saat mengubah surat lama, nilai `status`/`summary`/`attachmentUrl`/`notes` dipertahankan; surat baru memakai `status = 'baru'` dan field lain kosong.
- `api/seed-letters.php` (baru): seed idempoten 14 surat dummy (7 masuk + 7 keluar) dengan tanggal 2026-10-05 s/d 2026-11-04, kategori dan sifat bervariasi; pencocokan `direction` + `letter_number` + `letter_date`.
- `test-admin-letters`: disesuaikan menjadi 103/103 (field yang dihapus diuji ketiadaannya).
- `prd.md`, `erd.md`, `log.md`, `report/2026-11-12-kamis.md`: dokumentasi field, seed, dan hasil uji.

Perilaku:

- Popup surat tidak lagi memuat status, ringkasan/isi, tautan, dan catatan.
- Tabel, filter, kartu ringkasan, dan detail tidak lagi menampilkan keempat field tersebut.
- `letters.status`/`summary`/`attachment_url`/`notes` tetap ada di skema dan API untuk kompatibilitas data lama; API `letter.save` mengisi default `status = 'baru'` bila tidak dikirim.
- Tersedia 14 surat dummy di mode server; menjalankan `php api/seed-letters.php` berulang tidak membuat duplikat (run kedua: 0 dibuat, 14 diperbarui).

Pengujian:

- `test-admin-letters` 103/103.
- `validate-scripts` 32 HTML/25 JS.
- Uji API manual: login Admin, `letter.list` 14 baris, `letter.save` tanpa field yang dihapus menghasilkan `status = 'baru'` dengan ringkasan/catatan kosong, `letter.delete` membersihkan data uji, dan jumlah akhir tetap 14.
- `php -l api/seed-letters.php` dan `node --check assets/js/letter-archive.js` lulus.

Batasan:

- Field lama tidak ditampilkan lagi sehingga data warisan hanya dapat dilihat lewat database/API.

---

### v4.0 - 2026-11-12 - Surat menyurat (surat masuk & keluar) untuk Admin

**Tujuan:** memberi Admin halaman untuk mencatat dan menelusuri surat masuk maupun surat keluar dengan detail lengkap, tanpa mencampurkannya ke snapshot publik.

Perubahan file:

- `admin/letters.html` (baru, halaman aplikasi ke-31): sidebar **Surat Menyurat** setelah **Shift Karyawan**, filter jenis/status/tanggal/pencarian, kartu ringkasan total/masuk/keluar/selesai, tabel surat dengan rute pengirim → penerima, modal tambah/ubah, dan modal detail lengkap.
- `assets/js/letter-archive.js` (baru, modul JS ke-24): CRUD server/lokal, filter, ringkasan, detail, validasi field wajib, sanitasi tautan lampiran, dan audit `letter`.
- `api/actions.php`: aksi `letter.list`, `letter.save`, dan `letter.delete` (aksi API ke-99–101) khusus Admin; filter jenis/status/rentang tanggal/pencarian, validasi tanggal, sifat, status, dan tautan lampiran.
- `api/schema.sql`: tabel `letters` (tabel ke-39) dengan index `direction`, `letter_date`, `status`, `letter_number` dan FK `created_by`.
- `api/helpers.php`: serializer `elips_letter_public()`.
- `assets/js/core.js`, `assets/js/data.js`: key `elips_letters` + accessor `getLetters`/`getLettersByDirection`.
- `assets/js/audit.js`: label entitas `letter` → **Surat Menyurat**.
- 13 halaman admin lain: menu **Surat Menyurat** ditambahkan setelah **Shift Karyawan**.
- `assets/css/style.css`: gaya badge jenis/sifat, rute surat, modal, dan detail surat.
- `prd.md`, `erd.md`, `PRD_karyawan.md`, `log.md`: dokumentasi fitur, skema, dan angka cakupan.
- `report/2026-11-12-kamis.md`: bagian surat menyurat dan hasil pengujiannya.

Perilaku:

- Surat hanya dapat dibaca/diubah Admin; instruktur, peserta, dan karyawan ditolak.
- Dua jenis surat: `masuk` dan `keluar`; label tanggal otomatis menjadi **Tanggal Diterima** atau **Tanggal Dikirim**.
- Detail mencakup nomor, perihal, pengirim, penerima, tanggal surat, tanggal diterima/dikirim, kategori, sifat (Biasa/Penting/Segera/Rahasia), status (Baru/Diproses/Selesai/Arsip), ringkasan, tautan lampiran, dan catatan. **Sejak penyederhanaan 12 November 2026, status/ringkasan/tautan/catatan dihapus dari UI (lihat entri terbaru di atas).**
- Nomor surat tidak dibuat unik karena surat nyata dari pengirim berbeda dapat memiliki nomor yang sama.
- Tautan lampiran hanya menerima `http`/`https` atau tautan relatif; skema berbahaya ditolak.
- Surat tidak dimasukkan ke snapshot bootstrap; mode lokal memakai `elips_letters` dengan aturan validasi dan audit yang sama.

Pengujian:

- `test-admin-letters` (baru) 106/106 saat itu: render/filter, ringkasan statistik, detail lengkap, CRUD lokal, validasi field wajib & tautan berbahaya, audit, jalur server `letter.*`, dan empty state. Setelah penyederhanaan UI, suite menjadi 103/103.
- Uji API manual: login Admin, create/list/update/delete, nama pembuat, instruktur ditolak, tanggal tidak valid ditolak, tautan `javascript:` ditolak, dan data uji dibersihkan.
- Regresi: `test-all-pages` 29 halaman (termasuk `admin/letters.html`), `test-admin-pages`, `test-p0`, `test-admin-shifts` 89/89, dan `validate-scripts` 32 HTML/25 JS.
- `php api/install.php` menambahkan tabel `letters` ke database lama; verifikasi **40 tabel** dengan tabel `letters` kosong.

Dampak angka: 40 tabel MySQL, 101 aksi API, 40 storage key, 24 modul JS di `assets/js/`, 32 halaman HTML, dan 25 file JavaScript (termasuk `sw.js`).

Batasan:

- Surat belum ditautkan ke kelas, peserta, atau kursus tertentu.
- Lampiran hanya berupa tautan; belum ada unggah berkas langsung.

---

### v4.0 - 2026-11-12 - Seed 10 akun karyawan dummy

**Tujuan:** menyediakan data karyawan contoh untuk mencoba absensi, rekap, dan jadwal shift tanpa mengubah seed demo utama (tetap 7 pengguna agar regresi tidak berubah).

Perubahan file:

- `api/seed-karyawan.php` (baru): skrip seed idempoten untuk 10 akun berrole `karyawan`; mencocokkan akun lewat email sehingga aman dijalankan berulang (buat bila belum ada, perbarui nama/telepon/role/status bila sudah ada).
- `log.md`: struktur proyek dan riwayat perubahan.

Perilaku:

- Akun memakai password demo `elips123` (mengikuti `demo_password` pada `api/config.php`), status `aktif`, dan email berakhiran `@example.com`.
- Daftar: Dewi Lestari, Agus Prasetyo, Sri Wahyuni, Joko Susilo, Ratna Sari, Hendra Gunawan, Maya Putri, Eko Nugroho, Nur Aisyah, Bayu Pratama.
- Skrip tidak mengubah `api/install.php` maupun `assets/js/seed.js`, sehingga jumlah pengguna seed tetap 7.

Pengujian:

- `php api/seed-karyawan.php` pertama: 10 akun dibuat; eksekusi kedua: 0 dibuat, 10 diperbarui (tanpa duplikat).
- Verifikasi database: 17 pengguna total, 10 berrole `karyawan`.
- Login API `dewi.karyawan@example.com` / `elips123` berhasil (`userId` 10, sesi aktif).

Batasan:

- Akun dummy hanya ada di database server; mode `localStorage` tetap memakai seed 7 pengguna.

---

### v4.0 - 2026-11-12 - Jadwal shift karyawan oleh Admin

**Tujuan:** memberi Admin halaman untuk menjadwalkan shift karyawan per tanggal dengan preset **Pagi**/**Sore** atau jam **Custom**, tanpa mencampurkannya ke snapshot publik.

Perubahan file:

- `admin/shifts.html` (baru, halaman aplikasi ke-30): sidebar **Shift Karyawan** setelah **Rekap Absensi**, filter tanggal/karyawan/jenis, kartu ringkasan total/Pagi/Sore/Custom, tabel shift, serta modal tambah/ubah.
- `assets/js/shift-schedule.js` (baru, modul JS ke-23): CRUD server/lokal, preset Pagi `08:00–16:00` dan Sore `16:00–00:00`, jam Custom, validasi konflik, penanda `+1 hari`, dan audit `staff_shift`.
- `api/actions.php`: aksi `staffShift.list`, `staffShift.save`, dan `staffShift.delete` (aksi API ke-96–98) khusus Admin; default rentang bulan berjalan, maksimal 366 hari, filter karyawan/jenis.
- `api/schema.sql`: tabel `staff_shifts` (tabel ke-38) dengan unique key `(user_id, shift_date)` dan FK `user_id`/`created_by`.
- `api/helpers.php`: serializer `elips_staff_shift_public()`.
- `assets/js/core.js`, `assets/js/data.js`: key `elips_staffShifts` + accessor `getStaffShifts`/`getStaffShiftsByUser`.
- `assets/js/audit.js`: label entitas `staff_shift` → **Shift Karyawan**.
- 13 halaman admin: menu **Shift Karyawan** ditambahkan setelah **Rekap Absensi**.
- `assets/css/style.css`: gaya badge/ringkasan/tabel shift.
- `prd.md`, `erd.md`, `PRD_karyawan.md`, `log.md`: dokumentasi fitur, skema, dan angka cakupan.
- `report/2026-11-12-kamis.md`: bagian jadwal shift dan hasil pengujiannya.

Perilaku:

- Jadwal shift hanya dapat dibaca/diubah Admin; instruktur, peserta, dan karyawan ditolak.
- Satu karyawan maksimal satu shift per tanggal; duplikat ditolak validasi API dan unique key database.
- Preset Pagi/Sore mengabaikan jam dari klien; Custom menormalisasi `HH:MM` dan menolak jam mulai = selesai.
- Pengguna nonaktif atau berrole selain `admin`/`karyawan` tidak dapat dijadwalkan.
- Shift tidak dimasukkan ke snapshot bootstrap; mode lokal memakai `elips_staffShifts` dengan aturan validasi dan audit yang sama.

Pengujian:

- `test-admin-shifts` (baru) 89/89: render/filter, preset, Custom, CRUD lokal, konflik, audit, jalur server `staffShift.*`, dan empty state.
- Uji API manual: list/save/update/delete, filter karyawan/jenis/tanggal, duplikat, role instruktur ditolak, rentang/tipe/jam tidak valid, dan pembersihan data (0 baris tersisa).
- Regresi: `api-employee-fase1` 34/34, `api-staff-recap` 33/33, `test-server-mode` 18/18, `validate-scripts` 31 HTML/24 JS, `test-all-pages` 28 halaman (termasuk `admin/shifts.html`), `test-pwa` 50/50, `test-pwa-server` 16/16, `test-admin-pages`, `test-staff-attendance-page` 84/84, dan `test-employee-attendance-page` 49/49.
- `php api/install.php --fresh` menghasilkan **39 tabel** dengan tabel `staff_shifts` kosong.

Dampak angka: 39 tabel MySQL, 98 aksi API, 39 storage key, 23 modul JS di `assets/js/`, 31 halaman HTML, dan 24 file JavaScript (termasuk `sw.js`).

Batasan:

- Shift masih berupa rencana; perhitungan keterlambatan atau rekap berbasis shift belum diimplementasikan.

---

### v4.0 - 2026-11-12 - Rekap absensi karyawan untuk Admin

**Tujuan:** memberi Admin satu halaman untuk melihat rekapitulasi kehadiran karyawan dan admin per hari, memfilter data, lalu mengekspornya ke CSV.

Perubahan file:

- `admin/attendance-recap.html` (baru, halaman aplikasi ke-29): sidebar **Rekap Absensi** setelah **Laporan**, filter tanggal/karyawan/role, kartu ringkasan staf-hari-status, tabel harian, serta tombol Muat Ulang, Bulan Ini, dan Ekspor CSV.
- `assets/js/staff-recap.js` (baru, modul JS ke-22): agregasi per karyawan + tanggal, klasifikasi `lengkap`/`belum_pulang`/`tanpa_masuk`, ekspor CSV, dan paritas mode server/lokal.
- `api/actions.php`: aksi `staffAttendance.recap` (aksi API ke-95) khusus Admin; default bulan berjalan sampai hari ini, maksimal 366 hari, filter `userId`/`role`, dan respons tanpa foto.
- 11 halaman admin lain: menu **Rekap Absensi** ditambahkan setelah **Laporan**.
- `assets/css/style.css`: gaya filter rekap dan label rentang tanggal.
- `prd.md`, `log.md`, `PRD_karyawan.md`: dokumentasi fitur dan angka cakupan.
- `report/2026-11-12-kamis.md` (baru): laporan perkembangan increment absensi karyawan Fase 1-5 dan rekap absensi Admin per 12 November 2026.

Perilaku:

- Rekap hanya dapat dibuka Admin. Instruktur/peserta ditolak guard akses, sedangkan karyawan yang boleh mengakses absensi tetap ditolak oleh guard khusus rekap.
- Server menentukan rentang default (`Asia/Jakarta`, awal bulan sampai hari ini) dan menolak tanggal awal setelah tanggal akhir atau rentang lebih dari 366 hari.
- Setiap baris rekap menggabungkan absen masuk/pulang per karyawan per hari, menampilkan jam, durasi, lokasi, dan status; foto sengaja tidak diambil agar respons ringan.
- Mode lokal menghitung rekap dari `elips_staffAttendances` yang di-join dengan data pengguna, memakai aturan dan label status yang sama.
- Ekspor CSV memakai BOM UTF-8, seluruh sel dikutip, dan nama file mengikuti rentang tanggal yang dipilih.

Pengujian:

- `api-staff-recap` (baru) 33/33: guard Admin-only, rentang default, batas 366 hari, filter karyawan/role, validasi tanggal, dan respons tanpa foto.
- `test-admin-attendance-recap` (baru) 53/53: render agregasi, kartu ringkasan, filter, shortcut bulan ini, empty state, status, dan ekspor CSV.
- `test-admin-recap-server` (baru) 19/19: alur login server, rekap dari API, filter role, refresh, ekspor CSV, dan pengalihan non-admin.
- Regresi: `validate-scripts` 30 HTML/23 JS, `test-all-pages` 29 halaman, `test-admin-pages`, `test-pwa` 50/50, `test-pwa-server` 16/16, `api-employee-fase1` 34/34, dan `test-employee-server` lulus.
- `php api/install.php --fresh` tetap menghasilkan 38 tabel tanpa perubahan skema.

Dampak angka: 38 tabel MySQL, 95 aksi API, 38 storage key, 22 modul JS di `assets/js/`, 30 halaman HTML, dan 23 file JavaScript (termasuk `sw.js`).

Batasan:

- Belum ada perhitungan keterlambatan, jam kerja terjadwal, izin/cuti/lembur, atau penandaan alpha untuk hari tanpa catatan.
- Ekspor masih CSV; ekspor Excel/PDF menunggu kebutuhan lanjutan.

---

### v4.0 - 2026-11-12 - Fase 5 absensi karyawan: PWA, pemasangan, dan mode offline

**Tujuan:** menyelesaikan `PRD_karyawan.md` Fase 5 sehingga aplikasi dapat dipasang di layar utama Android/iOS, terbuka standalone langsung ke halaman absen, dan perubahan file baru tetap langsung terlihat.

Perubahan file:

- `manifest.webmanifest` (baru) di root: `start_url: "./employee/attendance.html"`, `scope: "./"`, `display: "standalone"`, `theme_color`/`background_color`, ikon 192/512 dan maskable, serta pintasan ke halaman absen.
- `assets/icons/` (baru): enam PNG tanpa dependensi — `icon-192`, `icon-512`, `icon-maskable-192`, `icon-maskable-512`, `apple-touch-icon` 180, dan `favicon-32`.
- `sw.js` (baru): service worker **network-first**. Halaman HTML aplikasi dan seluruh `/api/` tidak pernah masuk cache; hanya `offline.html` + ikon yang di-precache, sedangkan aset statis dipakai sebagai fallback offline.
- `assets/js/pwa.js` (baru, modul JS ke-21): registrasi service worker, `skipWaiting` + `clients.claim`, reload sekali saat ada versi baru, banner pemasangan Android, sheet 3 langkah iOS Safari, penolakan 7 hari, prompt sekali per sesi, dan bilah offline.
- `offline.html` (baru, halaman HTML ke-29): halaman offline mandiri tanpa bundle aplikasi dengan tombol muat ulang.
- `assets/js/attendance.js`: menolak penyimpanan saat offline, baik sebelum validasi maupun di dalam `persistAttendance`.
- `assets/css/style.css`: gaya banner pemasangan, sheet iOS, dan bilah offline dengan safe-area + aturan cetak.
- 28 halaman aplikasi: tag manifest, `theme-color`, meta mobile/apple, favicon, apple-touch-icon, dan `pwa.js` (`defer`).

Perilaku:

- Pengguna tanpa sesi tetap diarahkan `requireRole` ke `login.html`; role selain `karyawan`/admin tidak dapat membuka halaman absen. Aplikasi terpasang tetap membuka halaman absen sebagai `start_url`.
- Rotasi versi memakai strategi network-first sehingga file baru langsung terlihat tanpa menghapus cache manual; service worker hanya menyajikan aset statis lama saat jaringan gagal.
- Simpan absensi saat offline ditolak dengan pesan `Anda sedang offline. Sambungkan internet untuk menyimpan absensi.`

Pengujian:

- `test-pwa` (baru) 50/50: manifest/`start_url`/`scope`, tag PWA di 28 halaman, network-first tanpa cache halaman/API, banner Android/iOS, penolakan 7 hari, dan guard simpan offline.
- `test-pwa-server` (baru) 16/16: `manifest.webmanifest` tersaji `application/manifest+json`, `sw.js`, `offline.html`, dan seluruh ikon dapat diunduh.
- Regresi: `validate-scripts` 29 HTML/22 JS, `test-all-pages` 28 halaman, `test-employee-server`, `test-server-mode` 18/18, `test-remember-login` 37/37, `api-employee-fase1` 34/34, `api-integration` 55/55, `api-v4` 39/39, branches 17/17, materials 42/42, sessions 37/37, phase5 52/52, phase6 44/44.
- `php api/install.php --fresh` tetap menghasilkan 38 tabel tanpa perubahan skema.

Dampak angka: 38 tabel MySQL, 94 aksi API, 38 storage key, 21 modul JS di `assets/js/`, 29 halaman HTML, dan 22 file JavaScript (termasuk `sw.js`).

Batasan:

- Uji pemasangan pada perangkat Android/iOS nyata, retensi foto, dan kapasitas penyimpanan tetap menjadi pekerjaan Fase 6.
- `offline.html` sengaja tidak memuat `pwa.js` dan tidak termasuk 28 halaman aplikasi pada `test-all-pages`.

---

### v4.0 - 2026-11-11 - Fase 4 absensi karyawan: sesi diingat dan satu perangkat aktif

**Tujuan:** menyelesaikan `PRD_karyawan.md` Fase 4 sehingga karyawan cukup login sekali di perangkatnya, sementara administrator tetap dapat mencabut akses perangkat yang hilang atau berganti.

Perubahan skema:

- Tabel baru `user_devices` (tabel ke-38) menyimpan **hash SHA-256** token "ingat saya", label perangkat, user agent, IP, waktu dibuat/dipakai/kedaluwarsa, `revoked_at`, dan `revoked_reason`; `token_hash` unik dan `user_id` CASCADE ke `users`.
- `users.session_version` ditambahkan (default `0`) untuk membatalkan sesi PHP lama saat login baru, ganti password, akun dinonaktifkan, atau reset perangkat.
- `api/install.php` menambahkan migrasi additive untuk `session_version` dan `revoked_reason`; pemasangan ulang tetap aman untuk database lama.

Perubahan backend:

- `auth.login` menerima `remember`; token acak 32 byte diterbitkan, hash disimpan, cookie `elips_remember` dikirim `HttpOnly; SameSite=Lax; Secure` saat HTTPS dengan masa sliding 30 hari. Login baru menaikkan `session_version` dan mencabut token perangkat lama (`superseded`).
- `auth.resume` baru: memvalidasi cookie, membuat sesi PHP, merotasi token (`rotated`), dan memperpanjang masa berlaku. Token hasil rotasi yang dipakai ulang mencabut **semua** perangkat pengguna (`reuse`) + menaikkan `session_version` + audit; token yang dicabut karena logout/reset/kedaluwarsa hanya ditolak.
- `auth.logout` mencabut token perangkat saat ini (`logout`) dan membersihkan cookie.
- `user.resetDevice` (alias `users.resetDevice`, admin) mencabut semua token pengguna (`admin`), menaikkan `session_version`, dan mencatat audit.
- Ganti password dan penonaktifan akun mencabut seluruh token (`security`) dengan detail audit; sesi admin yang mengubah profilnya sendiri tetap dipertahankan.
- Rate limit 10 percobaan gagal per 60 detik per IP+email (`auth.login`) atau per hash token (`auth.resume`), disimpan di direktori temporer server. Percobaan berhasil membersihkan hitungan.
- Helper baru di `api/helpers.php`: cookie/token remember, deteksi label perangkat, pencarian/rotasi/pencabutan token, pemangkasan riwayat >30 hari, penegakan `session_version`, dan rate limit.

Perubahan frontend:

- `login.html`: checkbox **"Ingat saya di perangkat ini"** (aktif secara default); `ui.js` meneruskan nilainya.
- `crud-users.js`: `authenticateUser(email, password, { remember })` dan `resetUserDevice(userId)` (mode lokal mencatat audit demo).
- `auth.js`: `resumeSession()` dan fallback pada `requireRole`; `api.js` otomatis memanggil `auth.resume` setelah bootstrap tanpa sesi.
- `admin/users.html`: tombol **Reset Perangkat** per pengguna dengan konfirmasi.
- `audit.js`: label aksi `revoke` dan entitas `user_device`.

Pengujian:

- `test-remember-login.js` (baru) 37/37: cookie + rotasi, sliding resume, logout, token lama, satu perangkat aktif, reset admin, auto-resume klien tanpa sesi PHP, pencabutan saat ganti password/nonaktif, dan rate limit.
- Regresi: `api-integration` 55/55, `test-staff-attendance-api` 46/46, `api-employee-fase1` 34/34, `api-v4` 39/39, branches 17/17, materials 42/42, sessions 37/37, phase5 52/52, phase6 44/44; `test-employee-server` 17/17, `test-server-mode` 18/18, `test-sessions-server`, serta seluruh suite lokal (28 halaman, 20 modul JS) lulus.
- `php api/install.php --fresh` menghasilkan 38 tabel; `user_devices` dan `staff_attendances` kosong pada seed.

Dampak angka: 38 tabel MySQL, 94 aksi API, 38 storage key, 20 modul JS, dan 28 halaman HTML.

Batasan:

- PWA, retensi foto, kapasitas penyimpanan, dan uji perangkat nyata tetap menjadi pekerjaan Fase 5-6.
- Mode lokal tidak memakai token perangkat; Reset Perangkat hanya mencatat audit demo.

---

### v4.0 - 2026-11-11 - Fase 2-3 absensi karyawan: modul bersama dan halaman karyawan

**Tujuan:** menyelesaikan `PRD_karyawan.md` Fase 2-3 dengan memindahkan logika absensi ke modul bersama, menyelaraskan aturan mode lokal dengan server, lalu membuka halaman absensi khusus role `karyawan`.

Perubahan frontend:

- `assets/js/attendance.js` (baru) memuat seluruh alur absensi: hero status harian, kamera + unggah foto, GPS wajib, metadata perangkat, aturan maksimal satu `in` + satu `out` per hari, riwayat pribadi, detail, modal berhasil, dan hapus oleh Admin dengan alasan ≥5 karakter.
- `admin/dashboard.html` tidak lagi menyimpan ±900 baris skrip inline; halaman memuat `attendance.js` dan memanggil `ELIPS.attendance.init({ role: 'admin' })`. Tampilan dan perilaku tetap sama.
- `employee/attendance.html` (baru) memakai modul yang sama dengan role `karyawan`, tanpa sidebar admin dan tanpa tombol hapus.
- `core.js`: `isValidRole` menerima `karyawan`; `auth.js`: prefix path `employee/` dan `dashboardPathForRole('karyawan')`; `config.js`/`api.js`: `apiBase` mendukung folder `employee/`; `ui.js`: login karyawan langsung ke halaman absensi.
- `admin/users.html`: kartu pilihan role Karyawan, opsi role pada form, filter, badge, dan label.

Penyelarasan mode lokal:

- GPS wajib seperti server; absensi tanpa koordinat ditolak sebelum disimpan.
- Tanggal acuan memakai `Asia/Jakarta` (bukan zona waktu perangkat) agar status hero konsisten dengan tanggal server.
- Duplikat harian, absen pulang sebelum masuk, dan hapus hanya oleh Admin dengan alasan minimal 5 karakter diterapkan di `localStorage`; detail hapus tetap masuk Audit Trail.
- Karyawan tidak melihat tombol hapus pada riwayat maupun modal detail.

Pengujian:

- `test-staff-attendance-page.js` diperbarui (84/84): GPS mock, tanggal Jakarta, alasan hapus, dan alur masuk/pulang/hapus.
- `test-employee-attendance-page.js` (baru) 49/49: routing role, greeting, privasi riwayat, absensi masuk/pulang, detail tanpa hapus, data `out` lama, dan penolakan hapus oleh karyawan.
- `test-all-pages.js` mencakup `employee/attendance.html` (28 halaman); `validate-scripts` melaporkan 28 HTML dan 20 JS valid.
- Regresi lokal dan server (API Phase 1-6, v4, sesi, materi, presensi, jadwal, admin, instruktur, peserta, P0) tetap lulus. `test-server-mode` dan `test-sessions-server` dijalankan pada database segar agar hitungan seed valid.

Batasan:

- Remember-me, `user_devices`, PWA, retensi foto, dan uji perangkat nyata tetap menjadi pekerjaan Fase 4-6.

---

### v4.0 - 2026-11-11 - Fase 1 absensi karyawan: role, validasi server, dan aturan harian

**Tujuan:** menyiapkan fondasi server untuk absensi karyawan sesuai `PRD_karyawan.md` Fase 1, tanpa perubahan UI (halaman karyawan menyusul Fase 3).

Perubahan skema:

- `users.role` diperluas menjadi `ENUM('admin','instruktur','peserta','karyawan')`; role `karyawan` hanya untuk absensi dan belum dipakai klien.
- `staff_attendances` mendapat unique key `uq_staff_attendances_user_day_kind (user_id, attendance_date, kind)` sehingga aturan satu absen masuk dan satu absen pulang per hari dijaga database.
- `api/install.php` menambahkan migrasi additive: mengubah ENUM role dan, sebelum menambah unique key, merapikan duplikat lama dengan menyisakan baris ber-id terkecil.

Perubahan backend:

- `api/helpers.php`: guard `elips_require_attendance_access()` (hanya `admin` dan `karyawan`) serta helper `elips_attendance_timezone()` yang mengembalikan `Asia/Jakarta`.
- `api/actions.php`:
  - `staffAttendance.create` memakai jam dan tanggal **server** (`Asia/Jakarta`, disimpan UTC), mengabaikan `attendanceDate`/`takenAt`/`timezone` dari klien, mewajibkan GPS untuk semua role, menolak absen masuk/pulang ganda, menolak absen pulang sebelum absen masuk, dan menangani pelanggaran unique key sebagai pesan yang ramah.
  - `staffAttendance.list` hanya mengembalikan catatan sendiri untuk karyawan; admin dapat meminta catatan pengguna lain dengan parameter `userId`.
  - `staffAttendance.delete` hanya untuk admin, wajib alasan minimal 5 karakter, dan mencatat detail lama + alasan ke Audit Trail. Karyawan tidak dapat menghapus.
  - `user.create`/`user.update` menerima role `karyawan`; pendaftaran mandiri tetap hanya boleh membuat `peserta`.
- Snapshot tetap tidak memuat koleksi absensi maupun foto.

Keputusan yang diambil:

- Instruktur tidak memakai halaman absensi karyawan dan ditolak API.
- GPS wajib untuk semua role; data lama tanpa GPS tetap dapat dibaca.
- Tabel `user_devices` dan remember-me ditunda ke Fase 4.
- Role `karyawan` baru diaktifkan di client pada Fase 3 agar login tidak diarahkan ke halaman yang belum ada.

Pengujian:

- `api-employee-fase1.js` 34/34 lulus: role karyawan, login, absen masuk/pulang, GPS wajib, duplikat, privasi, akses admin, hapus beralasan, audit, dan penolakan role lain.
- `test-staff-attendance-api.js` diperbarui menjadi 46/46: jam server, GPS wajib, duplikat harian, dan alasan hapus.
- Regresi: `test-staff-attendance-page` (80/80), `api-phase5` 52/52, `local-phase5` 34/34, `api-phase6` 44/44, `local-phase6` 34/34, `test-all-pages` 25 halaman, dan `validate-scripts` 27 HTML/19 JS tetap lulus.
- `php api/install.php --fresh` berhasil; PHP lint bersih.

Batasan:

- UI client belum berubah: dashboard admin tetap memakai alur lama, sedangkan API server sudah menolak absensi tanpa GPS dan duplikat. Mode lokal masih memakai aturan lama sampai Fase 2–3.
- Belum ada halaman `employee/attendance.html`, remember-me, atau PWA.

---

### v4.0 - 2026-11-11 - Phase 6: skema penilaian, finalisasi nilai, predikat, dan koreksi beraudit

**Tujuan:** melengkapi Phase 6 v4.0 dengan penilaian berbasis skema per kelas: komponen berbobot (tugas/ujian/manual), komputasi nilai akhir yang tidak menganggap nilai kosong sebagai 0, predikat huruf A–E yang dapat dikonfigurasi Admin, validasi kelengkapan, penguncian nilai setelah finalisasi, serta koreksi beralasan yang tercatat append-only.

Perubahan skema (5 tabel baru, `grades` diperluas):

- `predicate_ranges`: rentang huruf global (`letter` unik, `label`, `min_score`, `max_score`, `sort_order`, `is_active`).
- `grade_schemes`: satu skema per kelas (`class_id` unik), status `draft`/`published`/`archived`, `pass_score`, `rounding`, pembuat/pembaru.
- `grade_scheme_components`: komponen `assignment`/`exam`/`manual`, bobot, agregasi, `max_score`, `is_required`, urutan.
- `grade_component_scores`: nilai per `(component_id, enrollment_id)`, status `not_assessed`/`graded`/`excused`/`missing`, penilai, waktu, catatan.
- `grade_corrections`: jejak koreksi nilai finalized (`previous_score`, `new_score`, `previous_letter`, `new_letter`, `reason`, `actor_id`).
- `grades` ditambah `scheme_id`, `final_score`, `status` (`draft`/`incomplete`/`finalized`), `components_snapshot`, `finalized_by`, `finalized_at`, `computed_at`.
- `api/install.php` menambahkan migrasi kolom additive, seed 9 predikat default, skema demo kelas 1, 3 komponen (tugas 30%, ujian 40%, manual 30%), dan nilai komponen manual; `--fresh` berhasil.

Perubahan backend:

- `api/helpers.php`: `elips_grade_compute_enrollment()` (komputasi berbobot, normalisasi komponen opsional/excused, deteksi `incomplete`, pembulatan), pemetaan predikat, serializer skema/komponen/nilai/koreksi, dan perluasan `elips_grade_public()`.
- `api/actions.php`: 12 aksi Phase 6 — `predicate.list/save`, `gradeScheme.list/save/status/delete`, `grade.roster`, `grade.manualSave`, `grade.exempt`, `grade.finalize`, `grade.correct`, dan `grade.transcript`.
- Guard: hanya Admin yang dapat mengubah predikat; hanya Admin/instruktur pemilik kelas yang dapat mengelola skema dan menilai; skema `published`/`archived` tidak dapat kembali ke `draft` atau dihapus; skema published wajib memiliki komponen dengan total bobot 100%.
- `grade.save` dan `grade.manualSave` menolak perubahan nilai yang sudah `finalized`; satu-satunya jalur perubahan adalah `grade.correct` dengan alasan minimal 5 karakter.
- Finalisasi dan koreksi berjalan dalam transaksi; koreksi menulis `grade_corrections` + audit dan memperbarui snapshot komponen. Finalisasi **tidak** membuat sertifikat otomatis (tetap Phase 7).
- `api/snapshot.php`: menambahkan koleksi `predicateRanges`, `gradeSchemes`, `gradeSchemeComponents`, `gradeComponentScores`, `gradeCorrections`, dan memperluas `grades`.

Perubahan client:

- `assets/js/core.js`: 5 storage key baru (`elips_predicateRanges`, `elips_gradeSchemes`, `elips_gradeSchemeComponents`, `elips_gradeComponentScores`, `elips_gradeCorrections`) sehingga total 38 key.
- `assets/js/data.js`: accessor koleksi Phase 6, `predicateLetter()`, `gradeRound()`, `gradeWeightTotal()`, dan `computeEnrollmentGrade()` sebagai mesin komputasi mode lokal.
- `assets/js/seed.js`: seed predikat default, skema/komponen demo, nilai manual, dan koleksi koreksi kosong.
- `assets/js/crud-grades.js`: paritas seluruh aksi Phase 6, termasuk guard published/archived, kunci finalisasi, pengecualian beralasan, koreksi dengan `actorName`, dan transkrip; memperbaiki pemanggilan roster agar memakai `getClassEnrollments`.
- `assets/js/utils.js`: `gradeLetter()` didelegasikan ke `ELIPS.predicateLetter()`.
- `assets/js/audit.js`: label aksi/entitas `finalize`, `exempt`, `predicate`, `grade_scheme`, dan `grade_component`.

Perubahan UI:

- `instructor/students.html`: editor skema dan komponen berbobot, panel roster/nilai/finalisasi, modal pengecualian, dan modal koreksi beralasan; opsi Draft dinonaktifkan untuk skema non-draft.
- `admin/settings.html`: editor predikat A–E dengan validasi cakupan 0–100, tambah/hapus baris, reset default, dan simpan.
- `learning.html`: panel transkrip peserta berisi nilai akhir, huruf, status, tabel komponen/aktivitas, peringatan komponen kosong, dan riwayat koreksi.

Aturan bisnis:

- Nilai akhir = `Σ(skor × bobot) / Σ(bobot)` dengan bobot komponen yang dihitung; komponen wajib kosong menghasilkan `incomplete`, komponen opsional kosong/excused dikeluarkan dan bobotnya dinormalisasi.
- Nilai `0` hanya dihitung bila diisi eksplisit; nilai kosong tidak pernah menjadi 0 implisit.
- Huruf dihitung dari predikat aktif, bukan tabel hardcoded; perubahan predikat berlaku pada komputasi/transkrip berikutnya.
- Satu kelas hanya memiliki satu skema; skema terbit/arsip tidak dapat dikembalikan ke draft.
- Nilai finalized terkunci; koreksi wajib alasan, tercatat append-only, dan tidak mengubah status finalized.

Pengujian:

- `api-phase6.js` 44/44 lulus: predikat, cakupan, guard role, skema/komponen, bobot 100%, published/archived, roster, nilai manual, exempt, incomplete, finalisasi, penguncian, koreksi, transkrip, dan privasi.
- `local-phase6.js` 34/34 lulus: paritas aturan dan komputasi pada `localStorage`.
- Regresi: `api-phase5` 52/52, `local-phase5` 34/34, `test-staff-attendance-api` 41/41, `test-staff-attendance-page`, `test-all-pages` (25 halaman), dan `validate-scripts` (27 HTML/19 JS) tetap lulus.
- `php api/install.php --fresh` berhasil; PHP lint dan `node --check` bersih.

Dampak angka: 37 tabel MySQL, 92 aksi API, 38 storage key, 19 modul JS, dan 27 halaman HTML. Dokumentasi `erd.md`, `prd.md`, dan `log.md` diselaraskan untuk menutup tunggakan Phase 5 dan Phase 6.

Batasan:

- Finalisasi tidak membuat sertifikat otomatis; sertifikasi lanjutan tetap Phase 7.
- Mode lokal tidak memiliki transaksi, sehingga kegagalan di tengah finalisasi/koreksi dapat meninggalkan data parsial; mode server memakai transaksi.
- Attendance satu masuk/satu pulang per hari tetap dijaga UI, bukan unique constraint database.

---

### v4.0 - 2026-11-10 - Phase 5: tugas, submission, review, ujian, dan hasil ujian

**Tujuan:** menambahkan instrumen penilaian Phase 5 v4.0 berupa tugas dan ujian per kelas, lengkap dengan lampiran/rubrik, submission peserta, review/revisi, roster peserta ujian, dan hasil ujian sebagai sumber nilai skema Phase 6.

Perubahan skema (9 tabel baru):

- `assignments` (kelas, versi kurikulum, modul/materi opsional, tipe, jendela waktu, skor maksimum, bobot legacy, status) dan `assignment_files`, `assignment_rubrics`.
- `submissions` (unik per tugas+enrollment, status, jawaban teks, keterlambatan, nilai, feedback, reviewer, revisi) serta `submission_files` dan `submission_reviews`.
- `exams` (kelas, versi, tipe, jadwal, durasi, skor maksimum, status), `exam_participants` (unik per ujian+enrollment), dan `exam_results` (unik per ujian+enrollment, status `not_assessed`/`graded`/`excused`/`missing`).

Perubahan backend:

- `api/actions.php` menambahkan 16 aksi: `assignment.create/update/status/delete`, `submission.submit/review/revision/accept`, `exam.create/update/status/delete`, `exam.participants`, dan `exam.result`.
- Validasi kepemilikan kelas, status tugas/ujian, jendela waktu, kelengkapan jawaban/berkas, skor ≤ maksimum, dan larangan submission ulang setelah `accepted`.
- Hasil ujian `missing` tidak dikonversi menjadi 0; komponen wajib yang kosong akan membuat nilai akhir `incomplete` pada Phase 6.
- `api/helpers.php` menambahkan serializer tugas, submission, ujian, peserta, dan hasil ujian; `api/snapshot.php` memuat koleksinya.
- `api/install.php` menambahkan seed demo: 2 tugas, 3 rubrik, 1 submission, 2 riwayat review, 2 ujian, 6 peserta, dan 6 hasil ujian.

Perubahan client:

- `assets/js/crud-tasks.js` baru: paritas CRUD tugas/ujian, submission, review/revisi, peserta, dan hasil ujian untuk mode server dan lokal.
- `assets/js/data.js` dan `assets/js/seed.js` menambahkan koleksi tugas/ujian beserta data demo.
- `assets/js/audit.js` menambahkan label aksi/entitas tugas, submission, review, dan ujian.
- UI instruktur di `instructor/manage-course.html` dan `instructor/students.html`; UI peserta di `learning.html`.

Pengujian:

- `api-phase5.js` 52/52 lulus: CRUD tugas/ujian, lampiran/rubrik, submission, review/revisi/accept, roster/hasil ujian, guard role, dan validasi status.
- `local-phase5.js` 34/34 lulus: paritas aturan pada `localStorage`.
- Regresi Phase 1–4 tetap lulus.

Dampak angka saat itu: 32 tabel MySQL, 80 aksi API, dan 33 storage key.

Batasan:

- Tugas `in_class_practice` dan `external_reference` tidak menerima submission daring.
- Lampiran hanya menyimpan metadata/path; belum ada penyajian biner/object storage.

---

### v4.0 - 2026-11-10 - Penyempurnaan alur absensi harian dan modal berhasil

**Tujuan:** menyelaraskan fitur absensi dashboard dengan alur kerja harian: satu absen masuk dan satu absen pulang per admin per hari, tombol yang hanya menampilkan aksi berikutnya, penguncian tanggal/jam/lokasi otomatis saat simpan, tampilan kamera yang ringkas, dan modal konfirmasi berhasil dengan satu tombol Selesai.

Perubahan client:

- `admin/dashboard.html`:
  - Hero hanya menampilkan **Absen Masuk** bila belum ada catatan `in` hari ini, **Absen Pulang** bila `in` sudah ada dan `out` belum, dan badge **Absensi hari ini lengkap** bila keduanya sudah tercatat; tombol disembunyikan saat status belum dimuat atau gagal dimuat.
  - Aturan harian `allowedKind()` memfilter catatan berdasarkan tanggal lokal hari ini dan `userId` pengguna aktif; modal menolak dibuka atau disimpan bila status berubah di tengah proses.
  - Modal ambil foto disederhanakan menjadi layar kamera, tombol **Ambil Foto**, **Ulangi**, **Nyalakan Kamera**, **Unggah Foto**, kolom **Catatan**, **Batal**, dan **Simpan Absensi**; input tanggal/jam/lokasi manual dan panel metadata dihapus.
  - Tanggal, jam, dan lokasi dikunci otomatis saat simpan: `attendanceDate` = tanggal lokal hari ini, `takenAt` = waktu simpan, koordinat GPS diambil saat modal dibuka atau dicoba ulang saat simpan; kegagalan GPS menyimpan `locationSource = none` tanpa memblokir absensi.
  - Modal **Absensi Berhasil** menampilkan foto, identitas akun (nama, email, role, ID), seluruh metadata absensi dan perangkat, serta hanya satu tombol **Selesai**; modal tidak dapat ditutup dengan Escape/backdrop. Setelah ditutup, riwayat dan status hero dimuat ulang.
  - Pengaman ganda mencegah penyimpanan ganda selama proses simpan (`saving`), menonaktifkan tombol saat menyimpan, dan menangani penutupan modal saat izin kamera masih menunggu.
- `assets/css/style.css`: menambahkan gaya kartu modal ambil foto yang ringkas, badge status selesai, header/ikon modal sukses, dan penyesuaian responsif.

Tidak ada perubahan backend/skema: `staff_attendances`, serializer, dan aksi `staffAttendance.create/list/delete` tetap sama. Aturan satu masuk + satu pulang per hari ditegakkan di UI, bukan unique constraint database.

Aturan bisnis:

- Setiap admin memiliki catatan absensi harian sendiri; role lain tetap ditolak server.
- Tombol aksi mengikuti status hari ini dan tidak menampilkan aksi yang tidak valid.
- Tanggal, jam, dan lokasi tidak dapat diedit pengguna dan dikunci pada saat simpan.
- Modal sukses hanya dapat ditutup melalui tombol **Selesai**.

Pengujian:

- `node --check` skrip inline dashboard lulus; `test-staff-attendance-page.js` diperbarui dan lulus 80 pemeriksaan: tombol awal/berganti, komposisi modal ringkas, penguncian tanggal, modal sukses lengkap dengan satu tombol, Escape tidak menutup modal sukses, guard status lengkap, detail, audit, dan hapus.
- `test-staff-attendance-api.js` tetap lulus 41/41 pemeriksaan (guard role, validasi, persistensi metadata, kepemilikan, snapshot tanpa foto).
- `php api/install.php --fresh` berhasil; database demo ditinggalkan bersih setelah pengujian.

Batasan:

- Aturan satu masuk/pulang per hari belum menjadi constraint database, sehingga pemanggilan API langsung masih dapat membuat duplikat.
- Lokasi hanya mengandalkan GPS perangkat; belum ada reverse geocoding/pemetaan label alamat.

---

### v4.0 - 2026-11-09 - Absensi staf dengan kamera dan metadata kehadiran

**Tujuan:** menambahkan absensi kehadiran staf/admin sebagai fitur utama dashboard Admin, dengan pengambilan foto via kamera, pratinjau, fallback unggah, serta perekaman metadata waktu, lokasi, dan perangkat yang konsisten antara mode server dan `localStorage`.

Perubahan backend:

- `api/schema.sql` menambahkan tabel `staff_attendances` (tabel ke-23) dengan kolom identitas pengguna, jenis `in/out`, tanggal dan waktu absensi, zona waktu, foto base64 + `photo_mime`, koordinat dan akurasi, label/sumber lokasi, IP, user agent, platform, bahasa, layar, memori perangkat, CPU core, tipe koneksi, status online, catatan, dan timestamp. Foreign key `user_id` memakai `ON DELETE CASCADE`, dengan index per pengguna/tanggal, tanggal, dan waktu.
- `api/actions.php` menambahkan aksi `staffAttendance.create`, `staffAttendance.list`, dan `staffAttendance.delete`. Ketiganya memakai `elips_require_admin()` dan hanya mengoperasikan catatan milik admin yang login.
- Validasi server mencakup jenis absensi, tanggal kalender (`checkdate`), waktu `takenAt`, foto wajib berupa data URL gambar yang isinya diverifikasi `getimagesizefromstring()` dengan batas ukuran, koordinat berpasangan dan dalam rentang valid, serta pembatasan panjang metadata.
- IP dicatat server dari `REMOTE_ADDR`; `locationSource` diturunkan menjadi `gps`, `manual`, atau `none` bila tidak dikirim konsisten.
- `api/helpers.php` menambahkan `elips_staff_attendance_public()` untuk serialisasi camelCase dan foto opsional.
- Snapshot bootstrap **tidak** memuat koleksi absensi staf maupun foto; data hanya dibaca melalui `staffAttendance.list`.

Perubahan client:

- `admin/dashboard.html`: hero **Absensi Kehadiran** dengan tombol Absen Masuk/Pulang, status hari ini, serta ringkasan jam masuk/pulang; bagian riwayat absensi pribadi; modal kamera dengan video pratinjau, Ambil Foto, Ulangi, Nyalakan Kamera, dan fallback Unggah Foto; form tanggal/jam/lokasi/catatan; modal detail metadata lengkap; aksi hapus dengan konfirmasi.
- `assets/js/core.js` menambahkan key `staffAttendances: 'elips_staffAttendances'`.
- `assets/js/data.js` menambahkan `getStaffAttendances`, `setStaffAttendances`, dan `getStaffAttendancesByUser` (urut `takenAt` terbaru).
- `assets/js/audit.js` menambahkan label entitas `staff_attendance: 'Absensi Staf'`.
- `assets/css/style.css` menambahkan gaya hero absensi, riwayat, modal kamera/detail, chip metadata, dan aturan responsif.
- Foto kamera/berkas dikompres menjadi JPEG di client sebelum disimpan. Mode lokal menyimpan data URL dan memangkas riwayat per pengguna menjadi 20 catatan; mode server menyimpan base64 di `staff_attendances.photo`.

Aturan bisnis utama:

- Absensi dashboard adalah absensi pribadi admin/staf yang login, bukan presensi peserta per sesi; akses lintas pengguna ditolak server.
- Jenis `in` dan `out` boleh tercatat lebih dari sekali dalam sehari sebagai catatan terpisah.
- Kamera memerlukan secure context (`localhost`/HTTPS); bila tidak tersedia, izin ditolak, atau dibuka via `file://`, pengguna diarahkan memakai unggah foto.
- Geolokasi bersifat opsional; kegagalan/keterbatasan GPS tidak memblokir penyimpanan dan lokasi dapat diisi manual.
- Create/delete mencatat audit entitas `staff_attendance`; operasi yang ditolak tidak menambah audit.

Pengujian:

- `php -l api/actions.php`, `php -l api/helpers.php`, dan `node --check` untuk `core.js`, `data.js`, `audit.js`, serta skrip inline dashboard lulus.
- `php api/install.php --fresh` berhasil membuat 23 tabel termasuk `staff_attendances`.
- `test-staff-attendance-page.js` mencakup hero, status hari ini, modal kamera/fallback, penyimpanan lokal, metadata, riwayat, detail, audit, hapus, dan empty state.
- `test-staff-attendance-api.js` lulus 41 pemeriksaan: guard role, validasi foto/koordinat/tanggal/jenis, persistensi metadata, kepemilikan hapus, urutan riwayat, dan snapshot tanpa foto.
- Regresi UI/API/mode lokal tetap lulus: `test-all-pages`, `test-admin-pages`, `test-p0`, `test-schedule-actions`, `test-sessions-page`, `test-branch-pages`, `test-learning`, `test-instructor`; `api-phase5` 52/52, `api-v4-sessions` 37/37, `api-v4-branches` 17/17, `api-v4-materials` 42/42; `local-phase5` 34/34, `local-v4-sessions` 47/47, `local-v4-branches` 18/18, `local-v4-materials` 35/35.

Batasan increment ini:

- Absensi belum terhubung ke rekap/payroll dan belum memiliki aturan jam kerja atau keterlambatan.
- Foto disimpan sebagai base64 di database; produksi sebaiknya memindahkan berkas ke object storage dan menyimpan URL/referensi.
- Belum ada koreksi/approval absensi oleh admin lain; catatan hanya dapat dibuat dan dihapus oleh pemiliknya.

---

### v4.0 - 2026-11-09 - Aksi langsung kalender dan filter jadwal lanjutan

**Tujuan:** menjadikan `admin/schedule.html` sebagai pusat operasi sesi pertemuan, bukan hanya tampilan baca, dengan filter yang lebih lengkap dan aksi langsung yang konsisten pada mode server maupun `localStorage`, tanpa perubahan skema database.

Perubahan backend:

- `session.status` menerima parameter opsional `reason`. Bila diisi, alasan ditambahkan ke `notes` sesi dan disertakan pada detail audit; status, JP aktual, dan guard kepemilikan tetap mengikuti aturan sebelumnya.
- Tidak ada perubahan `api/schema.sql`; data alasan memakai kolom `notes` yang sudah ada agar tetap kompatibel dengan database lama.
- Aksi `session.create`, `session.update`, dan `session.delete` yang dipakai form/aksi kalender tetap memakai validasi dan otorisasi server yang sama dengan halaman akademik.

Perubahan client:

- `admin/schedule.html`:
  - filter baru untuk instruktur, lokasi, dan multi-status berbasis chip (Semua, Terjadwal, Terkonfirmasi, Selesai, Dijadwalkan Ulang, Dibatalkan, Tidak Hadir);
  - seluruh filter (cabang, kelas, instruktur, lokasi, status) berlaku untuk kalender bulanan, agenda harian, ringkasan JP, statistik, dan tabel sesi;
  - filter, bulan, dan tanggal aktif disinkronkan ke query string (`branch`, `class`, `instructor`, `location`, `status`, `month`, `date`) dan dipulihkan saat halaman dibuka kembali;
  - tombol **Tambah Sesi** pada header, sel kalender, dan agenda kosong membuka form dengan tanggal terisi otomatis;
  - modal detail sesi menyediakan aksi **Edit**, **Jadwalkan Ulang**, **Batalkan**, **Tandai Selesai**, dan **Hapus** sesuai status/presensi sesi;
  - form sesi memakai konvensi `admin/academic.html` (kelas, nomor pertemuan, tanggal, modul, waktu, lokasi/ruang, tentor, JP, status, catatan) dan menampilkan pemberitahuan khusus saat menjadwalkan ulang;
  - tabel sesi mendapat tombol aksi lihat/edit; tombol aksi disembunyikan atau ditolak bila sesi sudah selesai atau berpresensi.
- `assets/js/crud-academic.js`: `setSessionStatus` meneruskan `reason` ke API dan, pada mode lokal, menambahkan alasan ke `notes` serta detail audit.
- `assets/css/style.css`: gaya tombol bahaya, chip status, aksi modal, grid form dua kolom, tombol tambah pada kalender, dan penyesuaian responsif.

Aturan bisnis utama:

- Aksi kalender tidak melewati aturan yang sudah ada: sesi `completed` tidak dapat dihapus/dijadwalkan ulang, sesi berpresensi tidak dapat dihapus, dan instruktur non-pengampu tetap ditolak server.
- Pembatalan bersifat opsional alasannya; bila diisi, alasan tersimpan di catatan sesi dan audit.
- Reschedule membuat sesi pengganti baru bertaut `rescheduledFromId` dan menandai sesi asal `rescheduled`; sesi asal tidak dihitung sebagai JP aktual.
- Menandai selesai mengisi JP aktual dari JP rencana bila masih kosong.
- Filter status kosong berarti semua status; memilih chip status menerapkan filter ke seluruh tampilan.

Pengujian:

- `php -l api/actions.php` dan `node --check assets/js/crud-academic.js` lulus.
- `test-schedule-actions.js` lulus seluruh pemeriksaan: filter instruktur/lokasi/multi-status, pembuatan sesi dari kalender, edit, reschedule, pembatalan beralasan, tandai selesai, hapus, sinkronisasi URL, dan pemulihan filter dari URL.
- Regresi UI tetap lulus: `test-branch-pages`, `test-sessions-page`, `test-learning`, dan `test-instructor`.
- Regresi backend/mode lokal tetap lulus: `api-phase5` 52/52, `api-v4-sessions` 37/37, `local-phase5` 34/34, dan `local-v4-sessions` 47/47.

Batasan increment ini:

- Belum ada drag-and-drop atau pengubahan tanggal langsung pada kalender; perubahan tanggal memakai form edit/reschedule.
- Aksi massal (mis. batalkan beberapa sesi sekaligus) belum tersedia.
- Query string menyimpan filter untuk kemudahan berbagi tampilan, bukan sebagai otorisasi; akses tetap ditentukan role pengguna.

---

### v4.0 - 2026-11-06 - Phase 4: materi tambahan, mode akses kelas, dan progress pendukung

**Tujuan:** menyelesaikan Phase 4 v4.0 dengan menghubungkan materi kurikulum ke kelas, menambahkan materi tambahan di luar kurikulum, serta menerapkan mode akses materi dan progres per enrollment tanpa merusak alur Phase 1–3 maupun fallback `localStorage`.

Perubahan backend:

- `api/schema.sql` menambahkan empat tabel:
  - `class_material_settings`: satu baris per kelas (`class_id` unik), `access_mode` (`open`/`sequential`/`scheduled`/`conditional`), `require_instructor_approval`, `notes`, `updated_by`, timestamp;
  - `class_materials`: materi tambahan kelas dengan `lesson_id`/`session_id` opsional, tipe `text`/`document`/`video`/`link`/`file`, metadata file (`file_name`, `file_path`, `file_size`, `mime_type`), `access_level` (`required`/`optional`/`reference`), `available_from`, `status` (`active`/`hidden`), `reason`, `sort_order`, aktor/timestamp;
  - `class_lesson_progress`: progres pelajaran per enrollment, unik `(enrollment_id, lesson_id)`;
  - `class_material_progress`: progres materi tambahan per enrollment, unik `(enrollment_id, material_id)`.
- Index baru: `class_material_settings(class_id)` unik, `class_materials(class_id, status, sort_order)`, index pelajaran/sesi/pembuat, unique dan index progres per enrollment serta per pelajaran/materi.
- FK: cascade ke kelas/enrollment/pelajaran/materi; `SET NULL` untuk pelajaran/sesi/pengguna pada materi tambahan dan aktor konfirmasi.
- `api/helpers.php` menambahkan serializer pengaturan/materi/progres, query daftar materi kelas, urutan pelajaran kurikulum, pencarian tanggal sesi modul, dan evaluator `elips_class_material_state()` yang menghitung kunci akses per peserta.
- `api/actions.php` menambahkan aksi:
  - `material.settings`, `material.updateSettings`;
  - `material.create`, `material.update`, `material.status`, `material.delete`;
  - `material.classroom`, `material.progress`.
  Total aksi API menjadi **61**.
- Guard server-side: Admin untuk semua kelas, instruktur hanya kelas yang diampu, peserta hanya enrollment miliknya; perubahan pengaturan oleh Admin pada baris yang sudah ada wajib `reason`; konfirmasi progres staf dicatat audit.
- Aturan akses `open`/`sequential`/`scheduled`/`conditional`; materi tambahan terkunci oleh status `hidden`, `available_from`, pelajaran tertaut, atau sesi tertaut. Peserta tidak menerima `content` untuk materi terkunci; staf mendapat pratinjau.
- Bila `require_instructor_approval` aktif, peserta ditolak saat menandai selesai; instruktur/Admin dapat mengonfirmasi progres untuk enrollment.
- `api/snapshot.php` menambahkan koleksi `classMaterialSettings`, `classMaterials`, `classLessonProgress`, dan `classMaterialProgress`. Snapshot materi hanya metadata: `content` dan `file_path` tidak diserialisasi.
- `api/install.php` tetap memakai seed yang ada; kelas tanpa baris pengaturan memakai default implisit `open` sehingga data lama kompatibel.

Perubahan client:

- `assets/js/core.js`: key `elips_classMaterialSettings`, `elips_classMaterials`, `elips_classLessonProgress`, `elips_classMaterialProgress`.
- `assets/js/data.js`: accessor pengaturan/materi/progres dan `isLessonCompleted()` yang membaca progres kelas + data legacy.
- `assets/js/seed.js`: normalisasi empat koleksi Phase 4; tidak membuat baris pengaturan untuk setiap kelas agar default implisit sama dengan server.
- `assets/js/crud-academic.js`: implementasi lokal `getMaterialSettings`, `updateMaterialSettings`, `createClassMaterial`, `updateClassMaterial`, `setClassMaterialStatus`, `deleteClassMaterial`, `getClassroom`, `saveMaterialProgress`, serta evaluator kunci akses dan persetujuan.
- `assets/js/audit.js`: label aksi `progress` dan entitas `class_material`/`class_material_settings`.
- `admin/academic.html`: aksi **Akses materi** per kelas dan modal pengaturan mode/persetujuan dengan alasan wajib untuk override.
- `instructor/manage-course.html`: pemilih kelas, panel pengaturan akses, serta CRUD/status materi tambahan.
- `learning.html`: daftar materi kelas, kunci akses, konten, tombol selesai, status persetujuan, dan materi tambahan di sidebar.

Aturan bisnis utama:

- Mode `open`: semua materi aktif tersedia.
- Mode `sequential`: pelajaran ke-n terbuka hanya setelah seluruh pelajaran sebelumnya selesai.
- Mode `scheduled`: pelajaran terbuka bila modulnya memiliki sesi non-`cancelled`/`no_show`/`rescheduled` yang tanggalnya sudah tiba.
- Mode `conditional`: prasyarat urutan **dan** tanggal sesi modul (bila ada) harus terpenuhi.
- Materi tambahan mengikuti `available_from`, pelajaran tertaut, dan sesi tertaut; peserta hanya melihat materi `active`.
- Staf dapat melewati kunci saat pratinjau tanpa mengubah progres peserta.
- Penyelesaian peserta ditolak saat persetujuan instruktur aktif; staf mengonfirmasi progres per enrollment.
- Perubahan pengaturan dan status materi wajib alasan serta menghasilkan entri audit dengan nilai sebelum/sesudah.

Pengujian:

- `php api/install.php --fresh` berhasil; `php -l` lulus untuk `actions.php`, `helpers.php`, `snapshot.php`, dan `install.php`.
- API: `api-v4-materials.js` 42/42; `api-v4-branches.js` 17/17; `api-v4-sessions.js` 37/37; `api-v4.js` 39/39.
- Mode lokal: `local-v4-materials.js` 35/35; `local-v4-branches.js` 18/18; `local-v4-sessions.js` 47/47; `local-v4.js` 36/36.
- UI materi: `test-v4-material-ui.js` 25/25 (modal akses Admin, panel materi Instruktur, sidebar/konten/progres/persetujuan Peserta).
- Regresi halaman dan data tetap lulus: `test-academic-page`, `test-branch-pages`, `test-sessions-page`, `test-instructor`, `test-admin-pages`, `test-extra-pages`, `test-render`, `test-all-pages`, `test-learning`, `test-crud`, `test-data`, `test-audit`, `test-notifications`, `test-p0`, `test-import`, `test-public`.

Batasan increment ini:

- File materi masih berupa metadata/path; belum ada endpoint upload dan penyajian file biner.
- Mode `scheduled`/`conditional` bergantung pada jadwal sesi yang sudah dibuat; belum ada pengingat otomatis.
- Progres materi tambahan belum masuk perhitungan nilai akhir/sertifikat.
- Phase 5–10 (tugas/ujian, pembayaran, sertifikat PDF) mengikuti `ELIPS-LMS-PRD-v4.0.md`.

---

### v4.0 - 2026-11-06 - Cabang, pengaturan cabang, dan kalender jadwal Admin

**Tujuan:** menambahkan cabang sebagai konteks penyelenggaraan kelas dan jadwal, lengkap dengan pengelolaan cabang di halaman pengaturan Admin serta halaman kalender/jadwal yang dapat difilter per cabang, tanpa mengubah perilaku akademik yang sudah ada.

Perubahan backend:

- `api/schema.sql`:
  - tabel baru `branches` (`code` unik, nama, alamat, kota, telepon, manajer, status, catatan);
  - kolom nullable `classes.branch_id` dengan index `idx_classes_branch` dan FK `fk_classes_branch` (`ON DELETE SET NULL`) agar data lama tetap kompatibel.
- `api/helpers.php`: serializer cabang; field cabang pada kelas dan sesi; query sesi mem-JOIN kelas/cabang sehingga snapshot sesi memuat `branchId`, `branchCode`, `branchName`, dan `branchCity`.
- `api/actions.php`: aksi `branch.create`, `branch.update`, `branch.status`, dan `branch.delete`; `class.create`/`class.update` memvalidasi cabang harus ada dan berstatus `active`, lalu menyimpan `branch_id`. Total aksi API menjadi **53**.
- `api/snapshot.php`: koleksi `branches`; snapshot kelas memuat field cabang hasil JOIN.
- `api/install.php`: migrasi aditif untuk database lama (`branch_id`, index, FK) dan seed 3 cabang (`CBG-JKT` Jakarta, `CBG-BDG` Bandung, `CBG-SBY` Surabaya); kelas migrasi memakai cabang Jakarta.

Perubahan client:

- `assets/js/core.js`: key `elips_branches`.
- `assets/js/data.js`: accessor cabang dan ekspor koleksi.
- `assets/js/seed.js`: seed cabang, normalisasi `branchId` kelas lama menjadi `null`, serta propagasi field cabang ke kelas dan sesi.
- `assets/js/crud-academic.js`: `createBranch`, `updateBranch`, `setBranchStatus`, `deleteBranch`, validasi/penyimpanan cabang pada kelas, dan dekorasi cabang pada sesi.
- `assets/js/audit.js`: label entitas `branch: 'Cabang'`.
- `admin/settings.html`: tabel pengelolaan cabang beserta modal tambah/ubah/status/hapus.
- `admin/academic.html`: filter cabang, kolom cabang pada tabel kelas, dan pilihan cabang pada modal kelas.
- `admin/schedule.html`: halaman kalender/jadwal Admin dengan filter cabang dan kelas, kalender bulanan, agenda harian, ringkasan bulan, tabel jadwal, serta modal detail sesi.
- Tautan navigasi **Kalender & Jadwal** ditambahkan ke seluruh halaman Admin.

Aturan bisnis utama:

- Kode cabang unik; kode kosong dibuat otomatis dengan prefiks `CBG-`.
- Cabang `inactive` tidak dapat dipilih saat membuat atau mengubah kelas.
- Penghapusan cabang ditolak aplikasi bila masih dirujuk kelas; di level database FK memakai `SET NULL`.
- Cabang opsional pada kelas lama; data v3 tanpa cabang tetap valid.
- Kalender hanya membaca data sesi yang sudah ada dan mengikuti filter cabang/kelas; perubahan jadwal tetap dilakukan dari halaman akademik.

Pengujian:

- `php api/install.php --fresh` berhasil dengan 3 cabang dan kelas bercabang Jakarta.
- `php -l` lulus untuk seluruh file PHP yang berubah.
- `api-v4.js` 39/39 dan `api-v4-sessions.js` 37/37 setelah reset seed.
- `api-v4-branches.js` 17/17 dan `local-v4-branches.js` 18/18.
- `local-v4.js` 36/36 dan `local-v4-sessions.js` 47/47.
- `test-branch-pages.js` lulus untuk pengaturan cabang, akademik, dan halaman kalender.
- Regresi lama tetap lulus: `test-academic-page.js`, `test-render.js`, `test-instructor.js`, dan `test-server-mode.js` 18/18.

---

### v4.0 - 2026-11-05 - Phase 3: jadwal pertemuan dan presensi offline

**Tujuan:** menambahkan penyelenggaraan pertemuan per kelas dan pencatatan presensi peserta, termasuk koreksi beralasan dan rekap kehadiran, tanpa mengubah alur kursus/nilai/sertifikat yang sudah ada.

Perubahan backend:

- `api/schema.sql`:
  - tabel baru `class_sessions` (jadwal per kelas, JP rencana/aktual, status, sesi pengganti);
  - tabel baru `attendances` (satu baris per sesi + enrollment, status presensi, alasan koreksi terakhir);
  - tabel baru `attendance_corrections` (audit koreksi: nilai lama/baru, aktor, alasan, waktu);
  - unique `(class_id, session_number)`, unique `(session_id, enrollment_id)`, index tanggal/status/kelas, serta FK cascade ke kelas/enrollment dan `SET NULL` ke modul/instruktur/aktor.
- `api/config.php` menambahkan kebijakan kehadiran:
  - `attendance_present_statuses = ['present', 'late']`;
  - `attendance_counted_statuses = ['confirmed', 'completed']`.
- `api/helpers.php`:
  - serializer sesi (termasuk hitungan presensi) dan presensi;
  - resolusi kelas dari sesi/enrollment, cek akses admin/instruktur/peserta;
  - validasi status sesi/presensi, JP rencana/aktual, tanggal/jam, dan modul sesi;
  - perhitungan rekap berbasis JP dan pembulatan 2 desimal;
  - pencatatan audit koreksi presensi.
- `api/actions.php` menambahkan aksi:
  - `session.create`, `session.createBulk`, `session.update`, `session.status`, `session.delete`, `session.detail`;
  - `attendance.saveBulk`, `attendance.correct`, `attendance.recap`, `attendance.myRecap`;
  - guard Admin untuk semua kelas, Instruktur untuk kelas yang diampu, Peserta hanya data sendiri.
- `api/snapshot.php` memuat koleksi `classSessions`, `attendances`, dan `attendanceCorrections`.
- `api/install.php` menambahkan seed 4 sesi untuk kelas migrasi (3 `completed`, 1 `scheduled`) dan 9 baris presensi untuk enrollment 1–3.

Perubahan client:

- `assets/js/core.js`: key `elips_classSessions`, `elips_attendances`, `elips_attendanceCorrections`.
- `assets/js/data.js`: accessor sesi, presensi per sesi/enrollment/peserta, dan audit koreksi.
- `assets/js/seed.js`: data demo sesi dan presensi dengan baseline rekap (Budi 100%, Rina 66,67%).
- `assets/js/audit.js`: label aksi `attendance`/`correct` dan entitas `session`/`attendance`.
- `assets/js/crud-academic.js`: implementasi lokal `createSession`, `createBulkSessions`, `updateSession`, `setSessionStatus`, `deleteSession`, `getSessionDetail`, `saveAttendanceBulk`, `correctAttendance`, `getAttendanceRecap`, `getMyAttendanceRecap`, dan `getAttendancePolicy`.
- `admin/academic.html`: tabel sesi + ringkasan, filter kelas, modal sesi tunggal/berulang, modal presensi dengan alasan wajib saat koreksi, dan modal rekap per peserta/sesi.
- `instructor/dashboard.html`: tabel sesi kelas yang diampu, buat/ubah/batalkan sesi, presensi, dan rekap.
- `student/dashboard.html`: kartu statistik kehadiran, badge kebijakan, dan rekap per kelas dengan detail sesi milik sendiri.

Aturan bisnis utama:

- Sesi dengan status `completed` tidak dapat dihapus; sesi yang sudah memiliki presensi juga tidak dapat dihapus (batalkan saja).
- Status `cancelled`, `no_show`, dan `rescheduled` tidak dihitung sebagai JP aktual maupun JP wajib rekap.
- Reschedule membuat sesi baru yang tertaut `rescheduled_from_id`; sesi lama menjadi `rescheduled`.
- Presensi unik per sesi + enrollment; perubahan status yang sudah tercatat wajib alasan dan menghasilkan baris audit koreksi.
- Persentase kehadiran = JP hadir ÷ JP wajib × 100, dengan status hadir dan status sesi mengikuti konfigurasi.

Pengujian:

- `php api/install.php --fresh` berhasil; seed sesi/presensi konsisten.
- `php -l` lulus untuk seluruh `api/*.php`.
- `api-v4.js` 39/39, `api-v4-sessions.js` 37/37.
- `local-v4.js` 36/36, `local-v4-sessions.js` 47/47.
- `test-sessions-page.js` 47/47 (UI admin/instruktur/peserta, koreksi, rekap).
- `test-sessions-server.js` 20/20 terhadap API PHP/MySQL.
- Regresi lama tetap lulus: `test-academic-page.js`, `test-render.js`, `test-instructor.js`, dan `test-server-mode.js` 18/18.

Batasan increment ini:

- Jadwal belum tersinkron ke kalender eksternal (Google Calendar/iCal) dan belum ada pengingat otomatis.
- Kebijakan kehadiran masih diubah lewat konfigurasi server, belum ada halaman pengaturan khusus.
- Koreksi presensi menyimpan audit terpisah; laporan rekap belum diekspor ke PDF/Excel.
- Phase 4–10 (materi per kelas, tugas/ujian, pembayaran, sertifikat PDF) mengikuti `ELIPS-LMS-PRD-v4.0.md`.

---

### v4.0 - 2026-11-05 - Phase 1–2: versi kurikulum, kelas/angkatan, enrollment berbasis kelas

**Tujuan:** memisahkan kursus dari penyelenggaraannya sehingga satu kursus dapat memiliki beberapa versi kurikulum dan beberapa angkatan/kelas, tanpa menghapus riwayat akademik dan tanpa merusak alur v3.0.

Perubahan backend:

- `api/schema.sql`:
  - tabel baru `categories`, `curriculum_versions`, `classes`, `class_instructor_histories`;
  - `modules` dan `lessons` mendapat `curriculum_version_id` (FK ke versi);
  - `enrollments` mendapat `class_id` serta status diperluas (`active`, `completed`, `withdrawn`, `cancelled`);
  - `grades` dan `certificates` memakai unique per `enrollment_id` agar angkatan berbeda terpisah;
  - foreign key, index, dan constraint MySQL 8 untuk menjaga integritas.
- `api/helpers.php`:
  - helper kurikulum (baris versi, kode versi berikutnya, cek dapat diedit, kunci transaksional);
  - helper kelas (baris kelas, kode kelas, hitung enrollment, cek tentor aktif, histori tentor);
  - serializer v4 untuk kategori, versi kurikulum, kelas, histori tentor, dan enrollment;
  - sanitasi, audit, dan notifikasi tetap kompatibel v3.
- `api/actions.php` menambahkan aksi:
  - `curriculum.create`, `curriculum.update`, `curriculum.publish`, `curriculum.clone`, `curriculum.retire`, `curriculum.delete`, `curriculum.detail`;
  - `class.create`, `class.update`, `class.status`, `class.assignInstructor`, `class.delete`;
  - `enrollment.assignClass`, `enrollment.classStatus`;
  - `module.create`/`lesson.create` menerima `curriculumVersionId` dan menolak versi terkunci;
  - guard role Admin untuk aksi akademik, guard kepemilikan untuk instruktur.
- `api/snapshot.php`:
  - memuat koleksi `categories`, `curriculumVersions`, `classes`, `classInstructorHistories`;
  - hanya mengekspos modul/materi dari versi kurikulum aktif (non-retired) tiap kursus;
  - struktur versi lengkap tersedia via `curriculum.detail`.
- `api/install.php`:
  - seed kategori, versi kurikulum `1.0` untuk setiap kursus, dan kelas migrasi `ANG-2026-MNJ-01`;
  - enrollment v3 dipetakan ke kelas migrasi (`legacy_import`);
  - seed dijaga konsisten agar setiap kelas merujuk versi yang terkunci.

Perubahan client:

- `assets/js/core.js`, `data.js`, `seed.js`: key dan accessor baru (`elips_categories`, `elips_curriculumVersions`, `elips_classes`, `elips_classInstructorHistories`), seed v4, dan migrasi data v3.
- `assets/js/crud-content.js`: modul/materi terhubung ke versi kurikulum; auto-create versi Draft bila kursus belum punya versi; menolak perubahan pada versi Locked/Retired.
- `assets/js/crud-courses.js`: publikasi kursus mempertimbangkan kurikulum.
- `assets/js/crud-enrollments.js`: pendaftaran berbasis kelas dan status enrollment.
- `assets/js/crud-academic.js` (baru): implementasi lokal untuk seluruh aturan kurikulum/kelas, termasuk penguncian versi, clone, retire, histori tentor, kuota, dan roster.
- `admin/academic.html` (baru): UI versi kurikulum, kelas/angkatan, penugasan tentor, roster, dan modal detail struktur.
- Navigasi `academic.html` ditambahkan ke seluruh halaman admin.
- `instructor/dashboard.html`: tabel “Kelas yang Diampu”.

Perubahan data demo:

- 7 pengguna, 4 kategori, 4 kursus, 4 versi kurikulum, 25 modul, 75 pelajaran, 1 kelas migrasi, 1 histori tentor, dan 3 enrollment.
- Hanya kelas migrasi yang di-seed sehingga setiap kelas pasti memakai versi kurikulum Published yang sudah dikunci.

Pengujian:

- `php api/install.php --fresh` berhasil; seed konsisten.
- `php -l` lulus untuk seluruh `api/*.php`.
- `test-server-mode.js` 18/18, `api-integration.js` 55/55.
- Suite v4 baru: `api-v4.js` 39/39 dan `local-v4.js` 36/36.
- Seluruh 17 suite lokal `test-*.js` lulus, termasuk `test-academic-page.js`.
- Dua pengujian v3 disesuaikan dengan semantik v4: `test-instructor.js` memverifikasi kurikulum kursus 1 terkunci lalu mengelola kursus 4; `test-p0.js` membuat kursus/modul Draft baru untuk menguji sanitasi materi.

Batasan increment ini:

- Baru mencakup Phase 1–2; jadwal/presensi offline, materi per kelas, tugas/ujian, pembayaran, dan sertifikat PDF mengikuti phase berikutnya.
- Enrollment lama dipetakan ke satu kelas migrasi per kursus; pemetaan kelas historis yang lebih rinci memerlukan data sumber tambahan.
- Snapshot masih memuat koleksi demo secara luas; pembatasan per role direncanakan pada hardening produksi.

---

### v3.0 - 2026-11-04 - Backend PHP + MySQL dan client dual-mode

**Tujuan:** mengganti ketergantungan tunggal pada `localStorage` dengan backend PHP/MySQL yang menegakkan autentikasi, role, kepemilikan, dan aturan bisnis di server, tanpa mengubah UI atau alur role yang sudah ada.

Perubahan:

- Menambahkan folder `api/`:
  - `index.php` sebagai front controller (`GET ?action=bootstrap/ping`, `POST { action, payload }`);
  - `config.php` dan `db.php` untuk konfigurasi serta koneksi PDO MySQL (timezone UTC);
  - `helpers.php` untuk sesi PHP, otorisasi, serializer, sanitasi HTML server-side, audit, notifikasi, dan sinkronisasi data turunan;
  - `snapshot.php` untuk membangun snapshot koleksi yang kompatibel dengan model data client;
  - `actions.php` untuk seluruh aksi bisnis dan validasi role/kepemilikan;
  - `schema.sql` dan `install.php` untuk skema MySQL 8 dan seeding data demo.
- Menambahkan `assets/js/config.js` dan `assets/js/api.js`:
  - `config.js` menentukan `apiBase` relatif terhadap halaman;
  - `api.js` mengambil snapshot awal, menyimpan cache sinkron, memanggil aksi tulis, dan otomatis fallback ke `localStorage` bila API tidak tersedia.
- Seluruh 25 halaman HTML memuat `config.js` sebelum `core.js` dan `api.js` setelah `core.js`.
- Modul CRUD dan data menambahkan short-circuit ke API pada mode server: `user.*`, `auth.login`, `course.*`, `enrollment.*`, `module.*`, `lesson.*`, `grade.save`, `certificate.draft`, `certificate.approve`, `progress.complete`, `audit.clear`, dan `notification.clear`.
- `auth.js` logout memanggil `auth.logout` dan membersihkan cache sesi pada mode server.
- `audit.js` dan `notifications.js` tidak lagi menulis duplikat di client pada mode server; pencatatan dilakukan backend.
- `seed.js` dilewati pada mode server agar tidak menimpa snapshot database.
- Password disimpan dengan `password_hash()` dan diverifikasi dengan `password_verify()`; hash tidak pernah dikirim ke client.
- Sanitasi konten materi tipe teks dilakukan di client dan server dengan allowlist yang sama.
- Regression test lama tetap berjalan dalam mode lokal; ditambahkan pengujian integrasi API dan smoke test server-mode JSDOM.

Dampak:

- Data domain tersimpan di MySQL dan dapat diakses lintas perangkat melalui API.
- Aturan bisnis utama tidak lagi hanya bergantung pada JavaScript client.
- Demo tanpa server tetap berfungsi melalui fallback `localStorage`.

---

### v2.0 - 2026-11-04 - Hardening P0: guard sesi, password, sanitasi materi

**Tujuan:** menutup celah akses halaman role tanpa login, memverifikasi password saat login, dan mencegah stored XSS dari konten materi.

Perubahan:

- `assets/js/core.js` menambahkan konstanta `ELIPS.DEFAULT_PASSWORD = 'elips123'` untuk akun demo dan migrasi akun lama.
- `assets/js/auth.js`:
  - `getCurrentUser()` sekarang hanya membaca `elips_session`; fallback user berdasarkan path halaman dihapus.
  - `requireRole(role)` hanya memakai `getSessionUser()`; tanpa sesi diarahkan ke `login.html`, role tidak sesuai diarahkan ke dashboard role-nya.
- Seluruh halaman `admin/*.html` sekarang memanggil `ELIPS.requireRole('admin')` sebelum render; halaman instruktur tetap memakai `requireRole('instruktur')`, dan halaman peserta memeriksa sesi/role.
- `assets/js/crud-users.js`:
  - `authenticateUser(email, password)` memverifikasi email, password, dan status akun.
  - `createUser()` mewajibkan password minimal 8 karakter; `updateUser()` memvalidasi password baru bila diisi.
- `assets/js/seed.js` menambahkan password demo ke akun seed dan memigrasikan akun lama tanpa password ke `elips123`, serta menyanitasi konten materi teks lama.
- `assets/js/utils.js` menambahkan `sanitizeHtml()` berbasis allowlist tag/atribut; `script`, `iframe`, event handler, dan URL `javascript:`/`data:`/`vbscript:` dibuang.
- `assets/js/crud-content.js` menyanitasi `lesson.content` bertipe `text` saat create/update; `learning.html` juga menyanitasi saat render sebagai defense in depth.
- `assets/js/ui.js` login sekarang memakai `ELIPS.authenticateUser()` dan menampilkan pesan gagal yang sesuai.
- `login.html` menampilkan password demo `elips123`; form pengguna Admin mewajibkan password untuk akun baru.
- PRD diperbarui ke v2.0 dan ERD diselaraskan dengan aturan password, guard sesi, dan sanitasi konten.

Dampak:

- Halaman admin/instruktur/peserta tidak dapat dibuka tanpa sesi yang valid, termasuk melalui URL langsung.
- Login dengan password salah atau kosong ditolak; akun nonaktif tetap tidak dapat masuk.
- Konten materi berbahaya tidak lagi tersimpan atau dirender.
- Guard dan verifikasi password masih berjalan di client dan bukan pengganti autentikasi server-side.

---

### v1.9 - 2026-11-03 - Perbaikan visibilitas sertifikat peserta

**Tujuan:** memastikan sertifikat yang sudah disetujui Admin langsung terbaca di halaman peserta, termasuk saat data berasal dari versi lama atau diubah dari tab lain.

Perubahan:

- `assets/js/data.js` menambahkan normalisasi status sertifikat (`publish`, `published`, `Publish`, `terbit`, `approved` dianggap Publish; `draft`, `menunggu`, `pending` dianggap Draft) dan perbandingan ID numerik agar data lama tetap terbaca.
- `assets/js/seed.js` menormalkan `studentId`, `courseId`, `enrollmentId`, dan status sertifikat saat migrasi data.
- `student/certificates.html` memisahkan proses render ke fungsi `renderCertificates()` dan menyegarkan tampilan otomatis saat ada perubahan `localStorage` dari tab lain, saat halaman dipulihkan dari cache, atau saat tab kembali aktif.
- Empty state halaman peserta sekarang menampilkan akun yang sedang masuk beserta tombol **Muat Ulang** untuk membantu memastikan akun peserta yang benar.
- `student/dashboard.html` memuat ulang data saat nilai/sertifikat/enrollment berubah dari tab lain atau halaman dipulihkan dari cache.
- PRD diperbarui ke v1.9.

Dampak:

- Sertifikat Publish langsung terlihat tanpa perlu menutup browser.
- Data sertifikat lama dengan ID string atau status berhuruf kapital tetap tampil.
- Sertifikat Draft tetap tidak dirender dan tidak bocor ke halaman peserta.

---

### v1.8 - 2026-11-03 - Modal pilih role tambah pengguna

**Tujuan:** memudahkan Admin memilih jenis akun yang akan dibuat sebelum mengisi form pengguna.

Perubahan:

- Tombol **Tambah Pengguna** pada `admin/users.html` sekarang membuka modal pertama berisi 3 pilihan role: Admin, Instruktur, dan Peserta.
- Setiap pilihan menampilkan ikon dan deskripsi singkat tanggung jawab role.
- Fungsi `chooseUserRole(role)` menutup modal pilihan, membuka form pengguna, dan mempreset role serta judul form sesuai pilihan.
- `openUserModal(userId, presetRole)` mendukung preset role untuk penambahan; alur edit pengguna tetap memakai data pengguna yang dipilih.
- Ditambahkan style `.user-role-choice` pada `assets/css/style.css` untuk tombol pilihan role.
- PRD diperbarui ke v1.8.

Dampak:

- Admin tidak lagi langsung masuk ke form kosong; role dipilih lebih dulu.
- Role masih dapat diubah melalui dropdown pada form sebelum disimpan.
- Tidak ada perubahan pada API CRUD maupun alur edit/hapus pengguna.

---

### v1.7 — 2026-11-03 — P2 notifikasi email (simulasi)

**Tujuan:** mencatat pemberitahuan email saat kursus dipublikasikan atau sertifikat terbit, tanpa memerlukan backend pada fase demo.

Perubahan:

- Ditambahkan key `elips_notifications` pada `core.js` dan normalisasi data pada `seed.js`.
- Ditambahkan modul `assets/js/notifications.js`:
  - `notifyCoursePublished(course)` membuat notifikasi untuk peserta aktif yang terdaftar dan instruktur aktif pengampu;
  - `notifyCertificatePublished(certificate)` membuat notifikasi untuk peserta saat sertifikat Draft disetujui;
  - `getNotifications(filters)` mendukung filter jenis, penerima, rentang tanggal, pencarian, dan limit;
  - `getNotificationRecipients()` menyediakan daftar penerima untuk dropdown;
  - `clearNotifications()` membersihkan seluruh outbox;
  - batas 1000 notifikasi terbaru agar `localStorage` tetap terkendali.
- Notifikasi memakai `channel: 'email'`, `status: 'sent'`, dan `simulated: true` karena email tidak benar-benar dikirim pada demo.
- Hook notifikasi ditambahkan ke `setCourseStatus` (hanya saat berubah menjadi Published) dan `approveCertificate` (Draft → Publish). Operasi gagal tidak membuat notifikasi.
- Ditambahkan halaman `admin/notifications.html`:
  - statistik total notifikasi, notifikasi hari ini, dan penerima unik;
  - filter pencarian, jenis, penerima, rentang tanggal, dan limit;
  - tabel outbox dengan detail subjek/isi, ekspor CSV, dan tombol bersihkan outbox.
- Seluruh 24 halaman lama memuat `notifications.js` setelah `audit.js`; 8 halaman admin lama mendapat tautan sidebar "Notifikasi", sehingga total 9 halaman admin (termasuk halaman baru) memiliki tautan tersebut.
- PRD diperbarui ke v1.7 dan roadmap P2 ditandai selesai untuk versi demo (pengiriman email nyata tetap memerlukan backend).

Dampak:

- Publikasi kursus dan approval sertifikat kini menghasilkan entri outbox tanpa mengubah alur bisnis utama.
- Kegagalan notifikasi tidak menggagalkan publikasi atau approval.
- Seluruh regresi lama tetap lulus; ditambahkan pengujian `test-notifications`.

---

### v1.6 — 2026-11-03 — P1 audit trail

**Tujuan:** mencatat siapa mengubah kursus, nilai, dan status sertifikat, serta menyediakan halaman admin untuk menelusuri jejak tersebut.

Perubahan:

- Ditambahkan key `elips_auditLogs` pada `core.js` dan normalisasi data pada `seed.js`.
- Ditambahkan modul `assets/js/audit.js`:
  - `logAudit(entry)` mencatat aktor, aksi, entitas, objek, detail, dan waktu;
  - `getAuditLogs(filters)` mendukung filter aktor, aksi, entitas, rentang tanggal, pencarian, dan limit;
  - `getAuditActors()` menyediakan daftar aktor untuk dropdown;
  - `clearAuditLogs()` membersihkan seluruh log;
  - batas 1000 entri terbaru agar `localStorage` tetap terkendali.
- Resolusi aktor: `actorId` eksplisit → user sesi (`elips_session`) → "Sistem". Fallback `getCurrentUser()` tidak dipakai agar tidak salah atribusi saat tidak ada sesi.
- Audit logging ditambahkan ke seluruh CRUD:
  - kursus: create, update, delete, publish, unpublish;
  - nilai: setiap `saveGrade`;
  - sertifikat: pengajuan Draft (`submit`) dan persetujuan (`approve`);
  - pengguna: create, update, delete, penugasan instruktur;
  - penugasan peserta: create, ubah status, delete;
  - modul dan materi: create, update, delete.
- Ditambahkan halaman `admin/audit.html`:
  - statistik total aktivitas, aktivitas hari ini, dan jumlah aktor;
  - filter pencarian, aksi, entitas, aktor, rentang tanggal, dan limit;
  - tabel riwayat, ekspor CSV, dan tombol bersihkan log.
- Seluruh 24 halaman HTML memuat `audit.js` setelah `auth.js`; 7 halaman admin lama mendapat tautan sidebar "Audit Trail" (ditambah halaman audit baru).
- PRD diperbarui ke v1.6 dan roadmap P1 ditandai selesai untuk versi demo.

Dampak:

- Operasi yang gagal tidak menambah log.
- Tidak ada perubahan perilaku CRUD utama; seluruh regresi lama tetap lulus.
- Jejak perubahan dapat diaudit tanpa membuka data mentah di `localStorage`.

---

### v1.5 — 2026-11-03 — Modularisasi JavaScript & log pengembangan

**Tujuan:** memisahkan satu file monolitik menjadi modul sesuai fungsi dan menyediakan catatan perkembangan.

Perubahan:

- `assets/js/main.js` dipecah menjadi 13 modul: `core`, `utils`, `data`, `seed`, `sync`, `progress`, `auth`, `crud-users`, `crud-courses`, `crud-enrollments`, `crud-content`, `crud-grades`, dan `ui`.
- Seluruh 23 halaman HTML diperbarui untuk memuat modul-modul tersebut dengan urutan dependensi yang benar.
- `main.js` dihapus karena seluruh isinya sudah terdistribusi ke modul baru.
- Dokumentasi PRD diperbarui agar merujuk pada struktur modular, bukan satu file `main.js`.
- Ditambahkan `log.md` sebagai catatan ringkasan perubahan dan perkembangan sistem.

Dampak:

- Tidak ada perubahan perilaku aplikasi; seluruh regresi CRUD, render, role flow, dan pemuatan halaman tetap lulus.
- Perawatan lebih mudah karena perubahan pada satu domain (misalnya sertifikat) terisolasi di modulnya sendiri.

---

### v1.4 — 2026-11-03 — P0 sertifikat Draft & sentralisasi CRUD

**Tujuan:** memastikan sertifikat Draft tidak bocor ke halaman peserta, dan memusatkan seluruh logika bisnis/CRUD di JavaScript.

Perubahan P0:

- `student/certificates.html` hanya menampilkan sertifikat `publish` melalui `ELIPS.getPublishedCertificatesByStudent`.
- Section Draft, statistik Draft, dan kartu Draft dihapus dari halaman peserta.
- `student/dashboard.html` tidak lagi menampilkan notifikasi atau hitungan sertifikat Draft.
- `student/courses.html` dan `student/profile.html` memakai helper Publish-only.
- State `pending-approval` dihapus; kursus yang sudah selesai dan dinilai tampil sebagai `completed` sampai sertifikat disetujui Admin.

Perubahan sentralisasi:

- Ditambahkan lapisan CRUD dan aturan bisnis terpusat:
  - pengguna: `createUser`, `updateUser`, `deleteUser`, `assignInstructorToCourses`;
  - kursus: `createCourse`, `updateCourse`, `deleteCourse`, `setCourseStatus`, `toggleCourseStatus`, `syncCourseCounters`;
  - enrollment: `assignStudentToCourses`, `setEnrollmentStatus`, `deleteEnrollment`;
  - konten: `createModule`, `updateModule`, `deleteModule`, `createLesson`, `updateLesson`, `deleteLesson`;
  - nilai/sertifikat: `saveGrade`, `createDraftCertificateForEnrollment`, `approveCertificate`.
- Halaman admin, instruktur, profil, dan registrasi diubah agar memanggil fungsi CRUD, bukan menulis langsung ke `localStorage`.
- Form kursus baru tidak lagi dapat memilih status Published; kursus baru selalu Draft.
- Publikasi kursus divalidasi: wajib instruktur aktif dan minimal 1 modul + 1 materi.
- Menghapus/menonaktifkan/mengganti role instruktur akan melepas kepemilikan kursus dan mengembalikan kursus ke Draft.
- Cascade delete diterapkan untuk kursus, enrollment, modul, materi, dan pengguna.

---

### v1.3 — 2026-11-03 — Penyempurnaan alur bisnis di PRD

- Alur kursus, status, hak akses, dan checklist pengujian dirinci di `prd.md`.
- Ditambahkan aturan bahwa peserta hanya dapat mengakses kursus yang ditugaskan dan sudah Published.

---

### v1.2 — 2026-11-03 — Halaman peserta dan instruktur

- Ditambahkan dashboard peserta, daftar kursus, profil peserta, serta halaman sertifikat peserta.
- Ditambahkan halaman sertifikat dan profil instruktur.
- Navigasi dan struktur folder dipisahkan per role: `admin/`, `instructor/`, `student/`.

---

### v1.1 — 2026-11-03 — Autentikasi dan registrasi

- Ditambahkan halaman login dengan validasi terhadap data pengguna.
- Ditambahkan halaman registrasi peserta.
- Redirect otomatis berdasarkan role setelah login.

---

### v1.0 — Fondasi aplikasi

- Struktur awal LMS: halaman publik, dashboard admin, data demo pengguna/kursus.
- Penyimpanan data berbasis `localStorage`.
- Komponen UI dasar: navbar, sidebar, card, tabel, badge, modal, toast.

---

## 4. Aturan Bisnis Kunci

| Aturan | Implementasi |
|---|---|
| Kursus baru selalu Draft | `createCourse` memaksa `status: 'draft'` |
| Publikasi hanya oleh Admin | `setCourseStatus`/`toggleCourseStatus` dipanggil dari halaman admin |
| Publikasi wajib instruktur aktif | `isActiveInstructor` diperiksa sebelum publish |
| Publikasi wajib punya konten | Minimal 1 modul dan 1 materi |
| 1 kursus = 1 instruktur | `course.instructorId` tunggal; penugasan baru menggantikan yang lama |
| 1 peserta = banyak kursus | `assignStudentToCourses` membuat banyak enrollment |
| Instruktur hanya mengelola kursusnya | `canManageCourse` memeriksa `course.instructorId` |
| Nilai hanya oleh instruktur pengampu | `saveGrade` memeriksa `instructorId` |
| Nilai menciptakan sertifikat Draft | `saveGrade` membuat/memperbarui sertifikat `draft` |
| Draft tidak tampil ke peserta | Halaman peserta memakai `getPublishedCertificatesByStudent` |
| Hanya Admin yang menyetujui sertifikat | `approveCertificate` mengubah `draft` → `publish` |
| Nomor final dibuat saat approval | Nomor `CERT-DRAFT-*` diganti `CERT-YYYY-*` |
| Hapus kursus = hapus data terkait | `deleteCourse` menghapus modul, materi, enrollment, nilai, sertifikat, progres |
| Hapus enrollment = hapus data peserta di kursus | `deleteEnrollment` menghapus nilai, sertifikat, dan progres terkait |
| Operasi berhasil dicatat ke audit | Setiap CRUD memanggil `ELIPS.logAudit` setelah data tersimpan |
| Operasi gagal tidak dicatat | Validasi mengembalikan `failure` sebelum `logAudit` dipanggil |
| Aktor audit tidak salah atribusi | Prioritas `actorId` eksplisit → sesi → "Sistem"; tidak memakai fallback path halaman |
| Log audit dibatasi | Maksimal 1000 entri terbaru disimpan di `elips_auditLogs` |
| Publikasi kursus membuat notifikasi | `setCourseStatus` memanggil `notifyCoursePublished` hanya saat status berubah menjadi `published` |
| Penerima notifikasi kursus | Peserta dengan enrollment `active` dan akun `aktif`, ditambah instruktur aktif pengampu |
| Approval sertifikat membuat notifikasi | `approveCertificate` memanggil `notifyCertificatePublished` setelah Draft → Publish |
| Penerima notifikasi sertifikat | Peserta pemilik sertifikat yang akunnya aktif dan memiliki email valid |
| Notifikasi gagal tidak menggagalkan bisnis | Pembuatan notifikasi dibungkus try/catch dan tidak mengubah hasil CRUD |
| Outbox notifikasi dibatasi | Maksimal 1000 notifikasi terbaru disimpan di `elips_notifications` |
| Sesi unik per kelas | Nomor pertemuan unik per kelas; jadwal berulang maksimal 52 sesi per permintaan |
| Sesi completed tidak dapat dihapus | `deleteSession` menolak status `completed` |
| Sesi berpresensi tidak dapat dihapus | Harus dibatalkan; riwayat presensi dipertahankan |
| JP aktual mengecualikan sesi batal | `cancelled`, `no_show`, dan `rescheduled` tidak dihitung |
| Reschedule tertaut | Sesi pengganti menyimpan `rescheduledFromId`; sesi asal menjadi `rescheduled` |
| Presensi unik per sesi + enrollment | `saveAttendanceBulk` memperbarui baris yang ada, bukan membuat duplikat |
| Koreksi presensi wajib alasan | `correctAttendance` menolak alasan kosong dan menulis audit koreksi |
| Rekap kehadiran berbasis JP | JP hadir ÷ JP wajib × 100; status hadir/sesi mengikuti kebijakan |
| Peserta hanya melihat presensi sendiri | `getMyAttendanceRecap`/`attendance.myRecap` memfilter berdasarkan sesi peserta |
| Otorisasi server-side | Aksi API memeriksa sesi, role, dan kepemilikan kursus sebelum menulis; contoh: `elips_require_admin`, `elips_can_manage_course` |
| Password aman di server | Password disimpan dengan `password_hash()` dan diverifikasi dengan `password_verify()`; hash tidak pernah masuk snapshot |
| Snapshot konsisten | Respons tulis menyertakan snapshot terbaru dan sesi; `api.js` mengganti seluruh cache dari respons tersebut |
| Sanitasi server-side | `elips_sanitize_html()` membuang tag/atribut berbahaya sebelum materi teks disimpan |
| Aksi kalender mengikuti aturan sesi | Edit, reschedule, batalkan, selesai, dan hapus memakai `session.update`/`session.status`/`session.delete`; sesi selesai atau berpresensi tidak dapat dihapus |
| Alasan pembatalan tercatat | `reason` opsional pada `session.status` ditambahkan ke `notes` dan detail audit |
| Filter kalender konsisten | Filter cabang/kelas/instruktur/lokasi/multi-status berlaku untuk kalender, agenda, ringkasan, statistik, dan tabel; state disimpan di URL |
| Submission unik per tugas + enrollment | `submissions` memperbarui baris yang ada; `accepted` mengunci submission |
| Submission tunduk pada status & waktu | Ditolak sebelum `openAt`, ditandai `late` setelah `dueAt`, ditolak saat `closed`/`archived` |
| Hasil ujian unik per ujian + enrollment | `exam_results` unik; skor tidak boleh melebihi `maxScore` |
| Nilai ujian kosong bukan nol | Status `missing`/`not_assessed` tidak dikonversi menjadi 0 |
| Satu skema penilaian per kelas | `classId` unik pada `grade_schemes`; simpan ulang memperbarui baris yang sama |
| Publikasi skema wajib 100% | Minimal 1 komponen dan total bobot tepat 100%; skema draft boleh bobot 0 sementara |
| Skema terbit/arsip terkunci | Tidak dapat kembali ke `draft` atau dihapus; harus diarsipkan lalu diganti |
| Hanya Admin yang mengubah predikat | `predicate.save` memakai guard Admin; cakupan 0–100 wajib tanpa celah/tumpang tindih |
| Nilai kosong bukan nol | Komponen wajib kosong → `incomplete`; komponen opsional kosong/excused dikeluarkan dan bobot dinormalisasi |
| Komputasi berbobot | `Σ(skor × bobot) / Σ(bobot)` lalu dibulatkan sesuai `rounding` skema |
| Finalisasi hanya untuk nilai lengkap | `grade.finalize` menolak `incomplete`, menulis snapshot, dan mengunci nilai |
| Nilai finalized tidak dapat diubah biasa | `grade.save`/`grade.manualSave` menolak; perubahan hanya lewat `grade.correct` |
| Koreksi wajib alasan & tercatat | Alasan ≥5 karakter; baris `grade_corrections` append-only + audit |
| Finalisasi tidak membuat sertifikat | Sertifikasi lanjutan tetap Phase 7; alur Draft → Publish tidak berubah |
| Peserta hanya membaca transkrip sendiri | `grade.transcript` memfilter enrollment milik peserta; instruktur/Admin pengampu boleh membaca roster |
| Divisi hanya untuk karyawan | `user.create`/`user.update` dan CRUD lokal mengosongkan `division` untuk role selain `karyawan` |
| Nilai divisi dibatasi | Hanya `marketing`, `operasional`, `keuangan`, `hrd`; nilai lain ditolak |
| Dashboard karyawan hanya milik sendiri | `employee/dashboard.html` memakai `staffAttendance.list` tanpa `userId` atau filter `localStorage` berdasarkan user sesi |

---

## 5. Skema Data

### 5.1 MySQL (mode server)

Tabel: `users`, `user_devices`, `categories`, `branches`, `courses`, `curriculum_versions`, `classes`, `class_instructor_histories`, `modules`, `lessons`, `class_material_settings`, `class_materials`, `enrollments`, `class_sessions`, `attendances`, `attendance_corrections`, `class_lesson_progress`, `class_material_progress`, `assignments`, `assignment_files`, `assignment_rubrics`, `submissions`, `submission_files`, `submission_reviews`, `exams`, `exam_participants`, `exam_results`, `predicate_ranges`, `grade_schemes`, `grade_scheme_components`, `grades`, `grade_component_scores`, `grade_corrections`, `certificates`, `completed_lessons`, `audit_logs`, `notifications`, `staff_attendances`, `staff_shifts`, `letters`, `marketing_leads`, `marketing_followups`, dan `marketing_targets` (43 tabel). Detail kolom, relasi, dan foreign key ada di `erd.md` §16. Sesi login disimpan di sesi PHP native; token "ingat saya" disimpan sebagai hash di `user_devices`.

### 5.2 `localStorage` (mode fallback)

| Key | Isi |
|---|---|
| `elips_users` | Pengguna: admin, instruktur, peserta, karyawan (termasuk `division`) |
| `elips_categories` | Kategori kursus |
| `elips_branches` | Cabang/unit penyelenggara (kode, kota, manajer, status) |
| `elips_courses` | Kursus, instruktur pengampu, status, counter modul/materi |
| `elips_curriculumVersions` | Versi kurikulum per kursus (draft/published/locked/retired) |
| `elips_classes` | Kelas/angkatan, versi terkunci, tentor, kuota, periode |
| `elips_classInstructorHistories` | Histori pergantian tentor per kelas |
| `elips_classMaterialSettings` | Pengaturan akses materi per kelas (mode, persetujuan instruktur, catatan) |
| `elips_classMaterials` | Materi tambahan kelas (tipe, status, akses, file metadata, tanggal tersedia) |
| `elips_classLessonProgress` | Progres pelajaran per enrollment/kelas |
| `elips_classMaterialProgress` | Progres materi tambahan per enrollment/kelas |
| `elips_enrollments` | Penugasan peserta ke kelas/kursus |
| `elips_classSessions` | Sesi pertemuan per kelas (tanggal, jam, JP, status, sesi pengganti) |
| `elips_attendances` | Presensi peserta per sesi |
| `elips_attendanceCorrections` | Audit koreksi presensi (status lama/baru, aktor, alasan) |
| `elips_modules` | Modul per versi kurikulum |
| `elips_lessons` | Materi per modul |
| `elips_assignments` | Tugas per kelas (tipe, jendela waktu, skor maksimum, status) |
| `elips_assignmentFiles` | Lampiran tugas (metadata file privat) |
| `elips_assignmentRubrics` | Rubrik/kriteria penilaian tugas |
| `elips_submissions` | Submission peserta per tugas (status, jawaban, nilai, reviewer) |
| `elips_submissionFiles` | Berkas jawaban submission |
| `elips_submissionReviews` | Riwayat review/revisi submission |
| `elips_exams` | Ujian per kelas (tipe, jadwal, durasi, skor maksimum) |
| `elips_examParticipants` | Roster peserta ujian |
| `elips_examResults` | Hasil ujian per peserta |
| `elips_predicateRanges` | Rentang predikat huruf A–E global |
| `elips_gradeSchemes` | Skema penilaian per kelas (status, ambang lulus, pembulatan) |
| `elips_gradeSchemeComponents` | Komponen berbobot skema (tugas/ujian/manual) |
| `elips_grades` | Nilai akhir peserta per enrollment + status finalisasi + snapshot komponen |
| `elips_gradeComponentScores` | Nilai per komponen per enrollment (graded/excused/missing) |
| `elips_gradeCorrections` | Jejak koreksi nilai finalized (alasan, aktor, nilai lama/baru) |
| `elips_certificates` | Sertifikat Draft/Publish, nomor, grade, approver |
| `elips_completedLessons` | Progres pelajaran legacy yang tetap dibaca untuk kompatibilitas |
| `elips_auditLogs` | Jejak audit perubahan kursus, nilai, sertifikat, pengguna, penugasan, modul, materi, sesi, presensi, dan materi kelas |
| `elips_notifications` | Outbox notifikasi email simulasi: penerima, subjek, isi, entitas, dan waktu |
| `elips_staffAttendances` | Absensi harian pribadi admin/karyawan pada mode lokal: maks. satu masuk + satu pulang per hari, tanggal/waktu terisi otomatis, GPS wajib, foto, dan metadata perangkat |
| `elips_staffShifts` | Jadwal shift per karyawan per tanggal pada mode lokal: Pagi/Sore/custom, satu shift per tanggal, catatan, dan pembuat |
| `elips_letters` | Surat masuk/keluar pada mode lokal: jenis, nomor, perihal, pengirim, penerima, tanggal, kategori, sifat, dan pembuat; status/ringkasan/lampiran/catatan dipertahankan untuk data lama |
| `elips_marketingLeads` | Lead milik karyawan Marketing: kontak, sumber, status, minat, catatan, jadwal follow-up, konversi, dan nilai pendaftaran |
| `elips_marketingFollowups` | Catatan follow-up per lead: kanal, tanggal, catatan, hasil, dan jadwal berikutnya |
| `elips_marketingTargets` | Target bulanan per karyawan dan periode: lead, follow-up, pendaftaran, dan pendapatan |
| `elips_session` | Sesi pengguna yang sedang login |

Akun demo utama:

| ID | Nama | Role | Email | Password |
|---|---|---|---|---|
| 1 | Administrator | admin | `admin@example.com` | `elips123` |
| 2 | Ahmad Fauzi, M.M. | instruktur | `ahmad@example.com` | `elips123` |
| 3 | Siti Nurhaliza, S.Psi. | instruktur | `siti@example.com` | `elips123` |
| 4 | Budi Santoso, A.Md. | instruktur | `budi@example.com` | `elips123` |
| 5 | Budi Santoso | peserta | `budi.student@example.com` | `elips123` |
| 6 | Ani Nurhayati | peserta | `ani@example.com` | `elips123` |
| 7 | Rina Setiawati | peserta | `rina@example.com` | `elips123` |

Akun lama tanpa password otomatis dimigrasikan ke password demo `elips123` saat `seed.js` dimuat pada mode lokal. Pada mode server, password disimpan sebagai hash dan diverifikasi di PHP; password demo hanya dipakai installer untuk mengisi data awal.

---

## 6. Hasil Pengujian Terakhir

Pengujian dijalankan setelah backend PHP/MySQL, client dual-mode (v3.0), increment v4.0 Phase 1-6, penambahan cabang/kalender jadwal, aksi langsung/filter jadwal lanjutan, absensi staf dan penyempurnaan alur hariannya, Fase 1 absensi karyawan (role, jam server, GPS wajib, aturan harian database), Fase 4-5 (sesi diingat, perangkat aktif, dan PWA), rekap absensi karyawan untuk Admin, jadwal shift karyawan, surat menyurat, divisi dan dashboard karyawan, serta dashboard Marketing:

| Pemeriksaan | Hasil |
|---|---|
| Syntax 33 HTML + 27 JS + PHP yang diubah | ✅ Lulus |
| Guard sesi, password, sanitasi (`test-p0`) | ✅ Lulus |
| Notifikasi email (`test-notifications`) | ✅ Lulus |
| Audit trail (`test-audit`) | ✅ Lulus |
| CRUD & aturan bisnis (`test-crud`) | ✅ Lulus |
| Render halaman peserta (`test-render`) | ✅ Lulus |
| Alur instruktur (`test-instructor`) | ✅ Lulus |
| Alur belajar (`test-learning`) | ✅ Lulus |
| Halaman admin (`test-admin-pages`) | ✅ Lulus |
| Modal pilih role tambah pengguna | ✅ Lulus |
| Visibilitas sertifikat peserta & data lama | ✅ Lulus |
| Semua halaman dimuat tanpa error (`test-all-pages`) | ✅ Lulus |
| Semua tautan lokal (`check-links`) | ✅ Lulus |
| Data layer (`test-data`) | ✅ Lulus |
| Integrasi API PHP/MySQL (55 pemeriksaan) | ✅ Lulus |
| API v4 Phase 1–2 (`api-v4`) | ✅ 39/39 |
| API v4 Phase 3 (`api-v4-sessions`) | ✅ 37/37 |
| API v4 Phase 4 (`api-v4-materials`) | ✅ 42/42 |
| API cabang (`api-v4-branches`) | ✅ 17/17 |
| Mode lokal v4 Phase 1–2 (`local-v4`) | ✅ 36/36 |
| Mode lokal v4 Phase 3 (`local-v4-sessions`) | ✅ 47/47 |
| Mode lokal v4 Phase 4 (`local-v4-materials`) | ✅ 35/35 |
| Mode lokal cabang (`local-v4-branches`) | ✅ 18/18 |
| API Phase 5 tugas/ujian (`api-phase5`) | ✅ 52/52 |
| Mode lokal Phase 5 (`local-phase5`) | ✅ 34/34 |
| API Phase 6 penilaian (`api-phase6`) | ✅ 44/44 |
| Mode lokal Phase 6 (`local-phase6`) | ✅ 34/34 |
| UI materi Admin/Instruktur/Peserta (`test-v4-material-ui`) | ✅ 25/25 |
| Aksi langsung & filter kalender (`test-schedule-actions`) | ✅ Lulus |
| Absensi staf halaman lokal (`test-staff-attendance-page`) | Lulus 80/80 |
| API absensi staf (`test-staff-attendance-api`) | Lulus 46/46 |
| API absensi karyawan Fase 1 (`api-employee-fase1`) | Lulus 34/34 |
| Sesi diingat & perangkat aktif (`test-remember-login`) | Lulus 37/37 |
| PWA manifest & service worker (`test-pwa`) | Lulus 50/50 |
| PWA mode server (`test-pwa-server`) | Lulus 16/16 |
| API rekap absensi Admin (`api-staff-recap`) | Lulus 33/33 |
| UI rekap absensi lokal (`test-admin-attendance-recap`) | Lulus 53/53 |
| Rekap absensi mode server (`test-admin-recap-server`) | Lulus 19/19 |
| UI jadwal shift Admin (`test-admin-shifts`) | Lulus 89/89 |
| Surat menyurat Admin (`test-admin-letters`) | Lulus 103/103 |
| Divisi & dashboard karyawan (`test-division-api`, `test-employee-dashboard`) | Lulus 9/9 dan 19/19 |
| API Marketing (`test-marketing-api`) | Lulus 24/24 |
| Dashboard Marketing lokal (`test-marketing-dashboard`) | Lulus 27/27 |
| Routing & halaman absensi karyawan (`test-employee-routing`, `test-employee-attendance-page`) | ✅ Lulus |
| UI akademik admin (`test-academic-page`) | ✅ Lulus |
| UI cabang & kalender admin (`test-branch-pages`) | ✅ Lulus |
| UI jadwal/presensi lokal (`test-sessions-page`) | ✅ 47/47 |
| UI jadwal/presensi server (`test-sessions-server`) | ✅ 20/20 |
| Smoke test server-mode JSDOM (18 pemeriksaan) | ✅ Lulus |

Catatan penting yang diuji:

- Halaman admin/instruktur/peserta tanpa sesi dialihkan ke login; akses role yang salah diarahkan ke dashboard role-nya.
- Login dengan password salah/kosong ditolak; akun demo `elips123` dapat masuk.
- `createUser` menolak password kosong/kurang dari 8 karakter.
- `sanitizeHtml` membuang `script`, event handler, dan URL `javascript:` tetapi mempertahankan markup aman.
- Sertifikat Draft tidak muncul di halaman peserta.
- Sertifikat baru terlihat setelah Admin menyetujui.
- Kursus kosong tidak dapat dipublikasikan.
- Instruktur lain tidak dapat mengelola konten atau menilai kursus yang bukan miliknya.
- Menghapus instruktur mengembalikan kursus ke Draft.
- Menghapus kursus/enrollment membersihkan data turunannya.
- Audit mencatat aktor yang benar untuk aksi admin, instruktur, dan sistem.
- Filter audit (aktor, aksi, entitas, tanggal, pencarian) bekerja dan hasilnya dapat diekspor.
- Operasi yang ditolak tidak menghasilkan entri audit.
- Publikasi kursus membuat notifikasi untuk peserta aktif dan instruktur pengampu, tanpa duplikasi penerima.
- Approval sertifikat membuat tepat satu notifikasi untuk peserta dengan nomor sertifikat final.
- Publikasi/approval yang ditolak atau diulang tidak menambah outbox.
- Filter notifikasi (jenis, penerima, tanggal, pencarian) bekerja dan hasilnya dapat diekspor.
- Halaman `admin/notifications.html` dapat membersihkan outbox dan menampilkan empty state.
- API menolak login salah, aksi lintas role, instruktur non-pengampu, dan peserta yang menandai progres milik orang lain.
- API menolak publikasi kursus tanpa modul/materi dan approval sertifikat dua kali.
- API menyanitasi HTML materi teks sebelum menyimpan.
- Snapshot server tidak pernah memuat hash password.
- Mode server memuat katalog dari MySQL tanpa menjalankan seed `localStorage`.
- Logout API menghapus sesi dan aksi tulis berikutnya ditolak.
- Sesi pertemuan dapat dibuat tunggal maupun berulang; nomor pertemuan unik per kelas dan jadwal berulang dibatasi 52 sesi.
- Sesi `completed` dan sesi berpresensi tidak dapat dihapus; pembatalan mempertahankan riwayat.
- Reschedule membuat sesi pengganti bertaut `rescheduledFromId` dan menandai sesi asal `rescheduled`.
- Presensi unik per sesi + enrollment; koreksi tanpa alasan ditolak, koreksi beralasan menghasilkan baris audit.
- Rekap kehadiran memakai JP hadir ÷ JP wajib dan mengabaikan sesi `cancelled`/`no_show`/`rescheduled`.
- Peserta hanya dapat membaca rekap miliknya; akses lintas peserta ditolak server.
- Cabang dengan kode duplikat ditolak; kode kosong dibuat otomatis.
- Kelas tidak dapat diarahkan ke cabang `inactive`; penghapusan cabang yang masih dipakai kelas ditolak.
- Snapshot kelas dan sesi memuat field cabang yang konsisten pada mode server dan lokal.
- Halaman kalender Admin menampilkan sesi sesuai filter cabang/kelas dan berpindah bulan tanpa kehilangan ringkasan.
- Kelas tanpa baris pengaturan akses memakai default implisit `open` pada mode server maupun lokal.
- Mode `sequential` menolak peserta membuka pelajaran berikutnya sebelum pelajaran sebelumnya selesai.
- Mode `scheduled` membuka pelajaran setelah tanggal sesi modul tiba dan tetap mengunci modul yang sesinya belum berlangsung.
- Mode `conditional` membutuhkan penyelesaian pelajaran sebelumnya sekaligus tanggal sesi modul.
- Materi tambahan dengan `available_from` di masa depan, pelajaran tertaut belum selesai, atau sesi tertaut belum tiba tetap terkunci.
- Materi `hidden` tidak muncul untuk peserta, tetapi metadatanya tetap terlihat staf.
- Peserta tidak menerima `content` materi terkunci; staf dapat pratinjau tanpa mengubah progres peserta.
- Persetujuan instruktur memblokir peserta menandai selesai sampai staf mengonfirmasi progres enrollment.
- Snapshot materi hanya metadata; `file_path` dan `content` tidak diekspos.
- Perubahan pengaturan/status materi tanpa alasan yang diwajibkan ditolak dan tidak menambah audit; perubahan sah mencatat nilai sebelum/sesudah.
- Tugas dengan tipe `in_class_practice`/`external_reference` tidak menerima submission daring; submission sebelum `openAt` ditolak dan setelah `dueAt` ditandai terlambat.
- Submission unik per tugas + enrollment; submission `accepted` tidak dapat dikirim ulang; permintaan revisi membuka kembali pengiriman.
- Peserta ujian dan hasil ujian unik per ujian + enrollment; skor di atas `maxScore` ditolak; `missing` tidak menjadi 0.
- Skema penilaian hanya satu per kelas; publikasi tanpa komponen atau total bobot ≠ 100% ditolak; skema published/archived tidak dapat kembali ke draft atau dihapus.
- Komponen wajib kosong menghasilkan `incomplete`; komponen opsional kosong/excused dikeluarkan dan bobot dinormalisasi ulang.
- Nilai `0` yang diisi eksplisit dihitung, sedangkan nilai kosong tidak pernah dianggap 0.
- Finalisasi menolak nilai `incomplete`, menulis snapshot komponen, dan mengunci nilai; simpan biasa setelah finalized ditolak.
- Koreksi hanya untuk nilai finalized, wajib alasan ≥5 karakter, menulis `grade_corrections`, dan tidak mengubah status finalized.
- Predikat default A–E dapat diubah Admin; rentang yang tidak menutup 0–100 tanpa celah/tumpang tindih ditolak.
- Peserta hanya membaca transkrip nilainya sendiri; instruktur/Admin pengampu dapat membaca roster dan transkrip kelas yang dikelola.
- Absensi hanya dapat dibuat/dibaca oleh admin dan karyawan; instruktur dan peserta ditolak. Karyawan hanya membaca catatannya sendiri, sedangkan admin dapat membaca catatan pengguna lain lewat parameter `userId`.
- Tombol hero mengikuti status harian: hanya Absen Masuk saat belum ada catatan, hanya Absen Pulang setelah absen masuk, dan badge selesai saat masuk+pulang lengkap.
- Modal ambil foto hanya memuat kamera, tombol foto, catatan, batal, dan simpan; tanggal/jam/lokasi tidak dapat diedit dan dikunci otomatis saat simpan.
- Modal berhasil menampilkan identitas akun dan seluruh metadata dengan satu tombol Selesai; Escape/backdrop tidak menutup modal tersebut.
- Foto absensi wajib berupa gambar valid; data URL non-gambar ditolak server.
- Tanggal dan jam ditentukan server (`Asia/Jakarta`, disimpan UTC); `attendanceDate`/`takenAt`/`timezone` dari klien diabaikan.
- GPS wajib untuk semua role; koordinat harus berpasangan dan dalam rentang valid, dan `locationSource` data baru selalu `gps`.
- Satu pengguna maksimal memiliki satu absen masuk dan satu absen pulang per hari; duplikat ditolak validasi server dan unique key database, dan absen pulang wajib didahului absen masuk.
- Penghapusan hanya oleh admin, wajib alasan minimal 5 karakter, dan mencatat detail lama + alasan di Audit Trail; karyawan tidak dapat menghapus.
- Metadata absensi (zona waktu, lokasi, perangkat, koneksi, catatan, IP server) tersimpan konsisten dan muncul di detail.
- Snapshot bootstrap tidak memuat koleksi/foto absensi staf; riwayat hanya dimuat lewat aksi khusus.
- Kamera tanpa dukungan/izin/secure context menampilkan fallback unggah foto; GPS tetap wajib pada mode server.
- Manifest PWA memakai `start_url` halaman absen, `scope` root, dan `display: standalone`; seluruh 32 halaman aplikasi memuat manifest, ikon, dan `pwa.js`.
- Service worker network-first tidak menyimpan halaman HTML aplikasi maupun `/api/`, sehingga perubahan file langsung terlihat; hanya aset statis dan `offline.html` yang menjadi fallback.
- Banner Android memakai `beforeinstallprompt`; iOS Safari menampilkan sheet 3 langkah; penolakan diingat 7 hari dan prompt dibatasi sekali per sesi.
- Penyimpanan absensi ditolak saat offline sebelum validasi maupun saat GPS selesai setelah koneksi putus.
- Server PHP bawaan menyajikan `.webmanifest` sebagai `application/manifest+json`, dan seluruh ikon dapat diunduh.
- Rekap absensi hanya dapat dibuka Admin; instruktur/peserta ditolak guard akses dan karyawan ditolak guard khusus rekap.
- Rentang rekap default adalah awal bulan berjalan sampai hari ini; tanggal awal setelah tanggal akhir dan rentang lebih dari 366 hari ditolak.
- Rekap menggabungkan absen masuk/pulang per karyawan per hari dan menandai `lengkap`, `belum_pulang`, atau `tanpa_masuk`, tanpa mengambil foto.
- Filter karyawan dan role bekerja di server maupun lokal, dan ekspor CSV memakai BOM UTF-8 dengan seluruh sel dikutip.
- Jadwal shift hanya dapat dikelola Admin; satu karyawan maksimal satu shift per tanggal, preset Pagi/Sore mengabaikan jam klien, Custom menolak jam mulai = selesai, dan shift tidak dimasukkan ke snapshot bootstrap.
- Surat menyurat hanya dapat dikelola Admin; jenis `masuk`/`keluar`, nomor surat tidak unik, field wajib divalidasi server, dan tanggal kalender tidak valid ditolak. Status, ringkasan/isi, tautan, dan catatan tidak lagi diisi/ditampilkan di UI, tetapi kolomnya tetap disimpan untuk kompatibilitas data lama.
- Detail surat menampilkan field yang tersisa beserta pembuat, dan surat tidak dimasukkan ke snapshot bootstrap (dibaca lewat `letter.list`).
- Mode server saat ini memuat 14 surat dummy (7 masuk + 7 keluar) dari `api/seed-letters.php`; seed idempoten sehingga aman dijalankan berulang.
- Divisi hanya tersimpan untuk role `karyawan`; role lain selalu kosong dan divisi otomatis dikosongkan saat role berubah.
- Nilai divisi di luar `marketing`/`operasional`/`keuangan`/`hrd` ditolak API maupun CRUD lokal.
- Login karyawan diarahkan ke `employee/dashboard.html`, dan dashboard hanya membaca absensi milik karyawan yang sedang login.
- Dashboard menampilkan status hari ini (belum/sudah masuk/lengkap), jumlah hari unik dan jumlah catatan bulan berjalan, serta lima absensi terbaru.
- Seed Marketing bersifat idempoten: akun `*.marketing@example.com` yang sudah ada diperbarui, bukan diduplikasi.
- Database demo berisi 23 pengguna: 9 Marketing, 3 Operasional, 2 Keuangan, 2 HRD, dan 7 tanpa divisi (non-karyawan).
- Widget Marketing hanya muncul untuk karyawan divisi Marketing; role/divisi lain tetap melihat dashboard absensi tanpa data Marketing.
- Karyawan Marketing tidak dapat membaca atau mengubah lead/follow-up/target milik karyawan lain; Admin dapat memantau seluruh data atau memilih satu karyawan.
- Mencatat follow-up menyinkronkan `nextFollowupDate` lead dan mengubah status `baru` menjadi `dihubungi`; menghapus follow-up menghitung ulang jadwal dari data tersisa.
- Status `terdaftar` mengisi `convertedAt` sekali dan nilai `enrollmentValue` masuk ke capaian pendaftaran/pendapatan hanya pada periode konversi.
- Menghapus lead menghapus seluruh follow-up terkait (CASCADE server atau pembersihan eksplisit mode lokal).
- Target di-upsert per karyawan + periode `YYYY-MM`; nilai negatif dan periode tidak valid ditolak.
- Aksi `marketing.*` tidak masuk snapshot bootstrap dan memakai audit `marketing_lead`/`marketing_followup`/`marketing_target`.
- Seed Marketing menghasilkan 12 lead, 12 follow-up, dan 6 target periode `2026-11`; menjalankan ulang seed tidak membuat duplikat.

---

## 7. Keterbatasan & Rencana Lanjutan

Keterbatasan saat ini:

- Mode server membutuhkan PHP 8 + MySQL 8 yang berjalan dan database yang sudah diinstal.
- Mode fallback `localStorage` tetap tidak aman untuk produksi (password demo diverifikasi di client).
- Snapshot publik masih memuat seluruh koleksi demo; produksi perlu proyeksi terbatas per role dan endpoint verifikasi sertifikat khusus.
- Rate limit baru tersedia untuk `auth.login`/`auth.resume`; belum ada CSRF token atau kewajiban HTTPS.
- Belum ada upload file materi sungguhan; materi bertipe file baru menyimpan metadata/path dan belum ada endpoint penyajian biner.
- Notifikasi email masih berupa simulasi/outbox di database; email belum benar-benar dikirim.
- Finalisasi nilai tidak membuat sertifikat otomatis; sertifikasi/PDF tetap phase berikutnya.
- Mode lokal tidak memiliki transaksi, sehingga finalisasi/koreksi yang gagal di tengah dapat meninggalkan data parsial.
- Absensi staf satu masuk + satu pulang per hari dijaga unique key database dan validasi server; mode lokal kini memakai aturan yang sama (GPS wajib, tanggal `Asia/Jakarta`, hapus beralasan oleh Admin).
- Role `karyawan`, halaman `employee/attendance.html`, remember-me (`user_devices`), dan PWA sudah dibuat (Fase 1–5). Retensi foto, kapasitas penyimpanan, uji perangkat Android/iOS nyata, panduan karyawan, dan pilot tersisa untuk Fase 6.
- Rekap absensi Admin sudah tersedia (filter tanggal/karyawan/role, ringkasan status, dan ekspor CSV); keterlambatan, jam kerja terjadwal, izin/cuti/lembur, alpha otomatis, serta ekspor Excel/PDF belum dibuat.
- Jadwal shift Admin sudah tersedia (Pagi/Sore/custom dan satu shift per tanggal), tetapi belum diintegrasikan ke perhitungan keterlambatan/rekap berbasis jam kerja.
- Surat menyurat Admin sudah tersedia (surat masuk/keluar beserta detail, filter, dan audit), tetapi belum ditautkan ke kelas/peserta/kursus. Field status, ringkasan/isi, tautan, dan catatan tidak lagi tersedia di UI; data warisan hanya dapat diakses lewat database/API.
- Divisi karyawan masih berupa kolom teks terbatas, bukan tabel relasi; divisi baru memerlukan pembaruan daftar di API dan client. Dashboard karyawan non-Marketing belum memuat target kinerja, izin/cuti, atau integrasi shift.
- Dashboard Marketing masih MVP: belum ada penugasan lead antar-karyawan, impor/ekspor CSV, pengingat otomatis, kampanye iklan, persetujuan target oleh atasan, atau histori perubahan target.
- Belum ada migration tool, backup otomatis, atau monitoring produksi.

Rencana lanjutan (opsional, sesuai `prd.md`):

| Prioritas | Item |
|---|---|
| P0 | Hardening API: rate limiting login, CSRF token, HTTPS, rotasi session ID |
| P0 | Pembatasan snapshot per role dan endpoint verifikasi sertifikat publik yang minim data |
| P0 | Upload file materi dan generate PDF sertifikat |
| P1 | Migration tool, backup otomatis, dan audit immutable dengan retensi |
| P1 | Fase 6 absensi karyawan: retensi foto & kapasitas penyimpanan, uji perangkat Android/iOS, panduan karyawan, dan pilot |
| P1 | Marketing fase 2: impor/ekspor lead, penugasan lead, pengingat follow-up, dan persetujuan target |
| P2 | Notifikasi email nyata saat kursus dipublikasikan atau sertifikat terbit |

---

## 8. Cara Memperbarui Log Ini

1. Tambahkan entri baru di bagian **Riwayat Perubahan** dengan format `vX.Y — YYYY-MM-DD — Judul`.
2. Tuliskan tujuan perubahan, daftar perubahan, dan dampaknya.
3. Perbarui **Hasil Pengujian Terakhir** jika ada pengujian baru.
4. Perbarui **Struktur Proyek** dan **Tanggung Jawab Modul** jika ada file/modul baru.
5. Selaraskan `prd.md` bila perubahan menyentuh alur bisnis atau arsitektur.
