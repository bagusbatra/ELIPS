# Log Pengembangan ELIPS LMS

**Versi dokumen:** 1.1  
**Terakhir diperbarui:** 2026-11-03  
**Status proyek:** Demo — Vanilla HTML/CSS/JavaScript + `localStorage`  
**Dokumen terkait:** `prd.md`

---

## 1. Ringkasan Sistem

ELIPS LMS adalah aplikasi Learning Management System berbasis web untuk mengelola kursus, instruktur, peserta, materi, penilaian, dan e-sertifikat. Fase saat ini adalah demo tanpa backend; seluruh data disimpan di `localStorage` browser.

Tiga role utama:

| Role | Tanggung jawab |
|---|---|
| **Admin** | Membuat kursus Draft, menugaskan 1 instruktur per kursus, mengelola pengguna, menugaskan peserta ke kursus, menyetujui sertifikat Draft → Publish |
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
core → utils → data → seed → sync → progress → auth → audit
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
- Seluruh 24 halaman HTML memuat `audit.js` setelah `auth.js`; seluruh 7 halaman admin mendapat tautan sidebar "Audit Trail".
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

Pengujian dijalankan setelah modularisasi v1.5:

| Pemeriksaan | Hasil |
|---|---|
| Syntax 23 HTML + 13 JS | ✅ Lulus |
| CRUD & aturan bisnis (`test-crud`) | ✅ Lulus |
| Render halaman peserta (`test-render`) | ✅ Lulus |
| Alur instruktur (`test-instructor`) | ✅ Lulus |
| Alur belajar (`test-learning`) | ✅ Lulus |
| Halaman admin (`test-admin-pages`) | ✅ Lulus |
| Semua 23 halaman dimuat tanpa error (`test-all-pages`) | ✅ Lulus |
| Semua tautan lokal (`check-links`) | ✅ Lulus |
| Data layer (`test-data`) | ✅ Lulus |

Catatan penting yang diuji:

- Sertifikat Draft tidak muncul di halaman peserta.
- Sertifikat baru terlihat setelah Admin menyetujui.
- Kursus kosong tidak dapat dipublikasikan.
- Instruktur lain tidak dapat mengelola konten atau menilai kursus yang bukan miliknya.
- Menghapus instruktur mengembalikan kursus ke Draft.
- Menghapus kursus/enrollment membersihkan data turunannya.

---

## 7. Keterbatasan & Rencana Lanjutan

Keterbatasan saat ini:

- Data hanya tersimpan di `localStorage`, sehingga hilang jika penyimpanan browser dibersihkan.
- Belum ada backend, autentikasi server-side, atau validasi lintas perangkat.
- Belum ada upload file materi sungguhan.
- Belum ada audit trail dan notifikasi email.

Rencana lanjutan (opsional, sesuai `prd.md`):

| Prioritas | Item |
|---|---|
| P0 | Backend + database, autentikasi server, upload file, validasi status/kepemilikan di server |
| P1 | Audit trail perubahan kursus, nilai, dan sertifikat |
| P2 | Notifikasi email saat kursus dipublikasikan atau sertifikat terbit |

---

## 8. Cara Memperbarui Log Ini

1. Tambahkan entri baru di bagian **Riwayat Perubahan** dengan format `vX.Y — YYYY-MM-DD — Judul`.
2. Tuliskan tujuan perubahan, daftar perubahan, dan dampaknya.
3. Perbarui **Hasil Pengujian Terakhir** jika ada pengujian baru.
4. Perbarui **Struktur Proyek** dan **Tanggung Jawab Modul** jika ada file/modul baru.
5. Selaraskan `prd.md` bila perubahan menyentuh alur bisnis atau arsitektur.
