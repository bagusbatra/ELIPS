# Log Pengembangan ELIPS LMS

**Versi dokumen:** 1.4  
**Terakhir diperbarui:** 2026-11-03  
**Status proyek:** Demo — Vanilla HTML/CSS/JavaScript + `localStorage`  
**Dokumen terkait:** `prd.md`

---

## 1. Ringkasan Sistem

ELIPS LMS adalah aplikasi Learning Management System berbasis web untuk mengelola kursus, instruktur, peserta, materi, penilaian, dan e-sertifikat. Fase saat ini adalah demo tanpa backend; seluruh data disimpan di `localStorage` browser.

Tiga role utama:

| Role | Tanggung jawab |
|---|---|
| **Admin** | Membuat kursus Draft, menugaskan 1 instruktur per kursus, mengelola pengguna, menugaskan peserta ke kursus, menyetujui sertifikat Draft → Publish, menelusuri audit trail, dan memantau outbox notifikasi |
| **Instruktur** | Mengelola modul/materi pada kursus yang diampu, memantau progres peserta, memberi nilai akhir |
| **Peserta** | Belajar materi kursus yang ditugaskan, menandai pelajaran selesai, melihat sertifikat yang sudah Publish |

Alur utama:

```text
Admin buat kursus (Draft)
  → Admin tugaskan 1 instruktur
  → Admin tugaskan peserta ke 1..N kursus
  → Instruktur buat modul & materi
  → Peserta belajar (kursus harus Published)
  → Instruktur beri nilai
  → Sistem buat e-sertifikat Draft (tidak tampil ke peserta)
  → Admin setujui
  → E-sertifikat Publish (tampil & dapat diverifikasi)
  → Setiap perubahan kursus, nilai, dan sertifikat tercatat di audit trail
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
├── prd.md
├── log.md
├── admin/
│   ├── dashboard.html
│   ├── courses.html
│   ├── users.html
│   ├── enrollment.html
│   ├── certificates.html
│   ├── notifications.html
│   ├── reports.html
│   ├── audit.html
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
└── assets/
    ├── css/style.css
    └── js/
        ├── core.js
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
        ├── crud-grades.js
        └── ui.js
```

### 2.1 Tanggung Jawab Modul JavaScript

Semua halaman memuat modul dengan urutan berikut:

```text
core → utils → data → seed → sync → progress → auth → audit → notifications
→ crud-users → crud-courses → crud-enrollments
→ crud-content → crud-grades → ui
```

| Modul | Tanggung jawab | API utama |
|---|---|---|
| `core.js` | Namespace `window.ELIPS`, daftar key storage, helper baca/tulis, `nextId`, objek hasil `{ ok, message, data }`, validasi dasar | `ELIPS.read`, `ELIPS.write`, `ELIPS.nextId`, `ELIPS._` |
| `utils.js` | Konversi nilai ke huruf, nomor sertifikat, escape HTML, format tanggal, toast | `gradeLetter`, `generateCertificateNumber`, `escapeHtml`, `formatDate`, `toast` |
| `data.js` | Getter/setter seluruh koleksi data; tidak berisi aturan bisnis | `getUsers`, `getCourses`, `getEnrollments`, `getModules`, `getLessons`, `getGrades`, `getCertificates`, `getCompletedLessons`, `getPublishedCertificatesByStudent` |
| `seed.js` | Data demo awal dan migrasi/normalisasi data lama | dijalankan otomatis saat modul dimuat |
| `sync.js` | Sinkronisasi data turunan: nama instruktur, kepemilikan konten, counter modul/materi, pelepasan kursus | `syncCourseCounters` dan helper internal `syncAllContentOwners`, `releaseInstructorCourses` |
| `progress.js` | Struktur kursus, progres belajar, overview siswa dan instruktur | `getCourseStructure`, `getCourseProgress`, `isLessonUnlocked`, `getStudentCourseOverview`, `getInstructorCourses`, `getInstructorSummary` |
| `auth.js` | Session, user aktif, path dashboard per role, guard halaman | `getSessionUser`, `getCurrentUser`, `requireRole`, `setCurrentUser`, `logout` |
| `audit.js` | Audit trail: pencatatan aktor/aksi/entitas, filter log, daftar aktor, pembersihan log | `logAudit`, `getAuditLogs`, `getAuditActors`, `clearAuditLogs`, `auditActionLabel`, `auditEntityLabel` |
| `notifications.js` | Outbox notifikasi email simulasi: pembuatan saat kursus Published/sertifikat terbit, filter, penerima, pembersihan | `createNotification`, `notifyCoursePublished`, `notifyCertificatePublished`, `getNotifications`, `getNotificationRecipients`, `clearNotifications`, `notificationTypeLabel` |
| `crud-users.js` | CRUD pengguna dan penugasan instruktur ke kursus | `createUser`, `updateUser`, `deleteUser`, `assignInstructorToCourses` |
| `crud-courses.js` | CRUD kursus dan aturan Draft/Publish | `createCourse`, `updateCourse`, `deleteCourse`, `setCourseStatus`, `toggleCourseStatus` |
| `crud-enrollments.js` | CRUD penugasan peserta ke kursus | `assignStudentToCourses`, `setEnrollmentStatus`, `deleteEnrollment` |
| `crud-content.js` | CRUD modul dan materi dengan validasi kepemilikan | `createModule`, `updateModule`, `deleteModule`, `createLesson`, `updateLesson`, `deleteLesson` |
| `crud-grades.js` | Input nilai dan alur sertifikat Draft → Publish | `saveGrade`, `createDraftCertificateForEnrollment`, `approveCertificate` |
| `ui.js` | Perilaku UI bersama: navigasi mobile, sidebar dashboard, katalog beranda, login, logout, style toast | dijalankan pada `DOMContentLoaded` |

Prinsip yang dipakai:

- Halaman hanya melakukan **rendering dan interaksi UI**.
- Seluruh aturan bisnis dan penulisan data melewati fungsi `ELIPS.*`.
- Fungsi CRUD mengembalikan `{ ok, message, data }` agar halaman dapat menampilkan pesan yang konsisten.
- Helper lintas modul yang tidak perlu diekspos ke halaman disimpan di `ELIPS._`.

---

## 3. Riwayat Perubahan

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

---

## 5. Skema Data `localStorage`

| Key | Isi |
|---|---|
| `elips_users` | Pengguna: admin, instruktur, peserta |
| `elips_courses` | Kursus, instruktur pengampu, status, counter modul/materi |
| `elips_enrollments` | Penugasan peserta ke kursus |
| `elips_modules` | Modul per kursus |
| `elips_lessons` | Materi per modul |
| `elips_grades` | Nilai akhir peserta per kursus |
| `elips_certificates` | Sertifikat Draft/Publish, nomor, grade, approver |
| `elips_completedLessons` | Progres pelajaran yang diselesaikan |
| `elips_auditLogs` | Jejak audit perubahan kursus, nilai, sertifikat, pengguna, penugasan, modul, dan materi |
| `elips_notifications` | Outbox notifikasi email simulasi: penerima, subjek, isi, entitas, dan waktu |
| `elips_session` | Sesi pengguna yang sedang login |

Akun demo utama:

| ID | Nama | Role | Email |
|---|---|---|---|
| 1 | Administrator | admin | `admin@example.com` |
| 2 | Ahmad Fauzi, M.M. | instruktur | `ahmad@example.com` |
| 3 | Siti Nurhaliza, S.Psi. | instruktur | `siti@example.com` |
| 4 | Budi Santoso, A.Md. | instruktur | `budi@example.com` |
| 5 | Budi Santoso | peserta | `budi.student@example.com` |
| 6 | Ani Nurhayati | peserta | `ani@example.com` |
| 7 | Rina Setiawati | peserta | `rina@example.com` |

---

## 6. Hasil Pengujian Terakhir

Pengujian dijalankan setelah perbaikan visibilitas sertifikat peserta (v1.9):

| Pemeriksaan | Hasil |
|---|---|
| Syntax 25 HTML + 15 JS | ✅ Lulus |
| Notifikasi email (`test-notifications`) | ✅ Lulus |
| Audit trail (`test-audit`) | ✅ Lulus |
| CRUD & aturan bisnis (`test-crud`) | ✅ Lulus |
| Render halaman peserta (`test-render`) | ✅ Lulus |
| Alur instruktur (`test-instructor`) | ✅ Lulus |
| Alur belajar (`test-learning`) | ✅ Lulus |
| Halaman admin (`test-admin-pages`) | ✅ Lulus |
| Modal pilih role tambah pengguna | ✅ Lulus |
| Visibilitas sertifikat peserta & data lama | ✅ Lulus |
| Semua 25 halaman dimuat tanpa error (`test-all-pages`) | ✅ Lulus |
| Semua tautan lokal (`check-links`) | ✅ Lulus |
| Data layer (`test-data`) | ✅ Lulus |

Catatan penting yang diuji:

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

---

## 7. Keterbatasan & Rencana Lanjutan

Keterbatasan saat ini:

- Data hanya tersimpan di `localStorage`, sehingga hilang jika penyimpanan browser dibersihkan.
- Belum ada backend, autentikasi server-side, atau validasi lintas perangkat.
- Belum ada upload file materi sungguhan.
- Audit trail tersimpan di `localStorage`, sehingga dapat diubah/dihapus dari browser dan belum immutable seperti log server.
- Notifikasi email masih berupa simulasi/outbox di `localStorage`; email belum benar-benar dikirim karena belum ada backend.

Rencana lanjutan (opsional, sesuai `prd.md`):

| Prioritas | Item |
|---|---|
| P0 | Backend + database, autentikasi server, upload file, validasi status/kepemilikan di server |
| P1 | Audit trail server-side yang immutable dengan retensi dan akses terbatas (versi demo sudah mencatat audit di `localStorage`) |
| P2 | Notifikasi email nyata saat kursus dipublikasikan atau sertifikat terbit (demo sudah mencatat outbox simulasi) |

---

## 8. Cara Memperbarui Log Ini

1. Tambahkan entri baru di bagian **Riwayat Perubahan** dengan format `vX.Y — YYYY-MM-DD — Judul`.
2. Tuliskan tujuan perubahan, daftar perubahan, dan dampaknya.
3. Perbarui **Hasil Pengujian Terakhir** jika ada pengujian baru.
4. Perbarui **Struktur Proyek** dan **Tanggung Jawab Modul** jika ada file/modul baru.
5. Selaraskan `prd.md` bila perubahan menyentuh alur bisnis atau arsitektur.
