# PRD: Learning Management System (LMS) ELIPS

**Versi:** 2.0  
**Tanggal:** 2026-11-04  
**Status:** Implemented (Demo - Vanilla HTML/CSS/JS + localStorage); seluruh aturan alur kursus telah diterapkan, JavaScript telah dimodularisasi, audit trail P1 aktif, notifikasi email P2 disimulasikan melalui outbox, dan hardening P0 demo (guard sesi, verifikasi password, sanitasi materi) aktif  
**Log pengembangan:** `log.md`
**Nama Aplikasi:** ELIPS LMS

---

## 1. Tujuan Proyek

Membangun sistem Learning Management System (LMS) berbasis web untuk pelatihan dan kursus. Pada fase demo ini, sistem dibangun menggunakan vanilla HTML, CSS, dan JavaScript dengan penyimpanan data menggunakan `localStorage` untuk mensimulasikan alur pendaftaran, progres belajar, dan penerbitan sertifikat.

---

## 2. Target Pengguna

| Role | Deskripsi | Kebutuhan Utama |
|---|---|---|
| **Admin** | Pengelola sistem | Menambah kursus (Draft), menugaskan 1 instruktur per kursus, menambah pengguna, menugaskan siswa ke satu/beberapa kursus, menyetujui sertifikat Draft → Publish |
| **Instruktur/Trainer** | Pengajar kursus | Login, mengelola modul/materi pada kursus yang diampu, memantau progres, memberi nilai akhir |
| **Peserta/Student** | Peserta pelatihan | Mengakses materi dari kursus yang ditugaskan, menandai pelajaran selesai, melihat sertifikat yang sudah Publish |

---

## 3. Alur Sistem Kursus (Business Flow)

Alur berikut menjadi acuan utama untuk pengelolaan kursus, penugasan instruktur dan siswa, penilaian, serta penerbitan sertifikat.

### 3.1 Ringkasan Alur

```text
Admin ── tambah kursus ──────────────▶ Kursus [Draft]
Admin ── tambah instruktur ──────────▶ Instruktur
Admin ── kaitkan instruktur ─────────▶ Kursus [Draft]   (1 kursus = 1 instruktur)
Admin ── tambah siswa ───────────────▶ Siswa
Admin ── kaitkan siswa ──────────────▶ 1..N Kursus      (1 siswa boleh banyak kursus)
Instruktur ── login ─────────────────▶ Buat modul & materi pada kursus yang diampu
Siswa ── belajar ────────────────────▶ Hanya modul pada kursus yang ditugaskan
Instruktur ── beri nilai akhir ──────▶ Nilai
Nilai ── terintegrasi ───────────────▶ E-Sertifikat [Draft]
E-Sertifikat [Draft] ── tidak tampil ▶ Halaman siswa
Admin ── approve ────────────────────▶ E-Sertifikat [Publish] ── tampil ▶ Halaman siswa
Sistem ── kursus Publish / sertifikat terbit ▶ Notifikasi email (simulasi) ── outbox ▶ Admin
```

### 3.2 Langkah Detail

1. **Admin menambah kursus baru** melalui `admin/courses.html`.
   - Kursus baru dibuat dengan status **Draft**.
   - Kursus Draft belum dapat diakses oleh siswa.
   - Kursus baru wajib memiliki satu instruktur penanggung jawab.

2. **Admin menambah akun instruktur baru** melalui `admin/users.html`.
   - Role pengguna diatur sebagai **Instruktur**.
   - Akun instruktur harus berstatus **Aktif** agar dapat masuk dan mengelola kursus.

3. **Admin mengaitkan instruktur ke kursus Draft**.
   - Relasi bersifat **1 kursus = 1 instruktur** (`course.instructorId`).
   - Satu instruktur boleh mengampu lebih dari satu kursus.
   - Jika kursus sudah memiliki instruktur, penugasan baru akan menggantikan instruktur sebelumnya.

4. **Admin menambah akun siswa baru** melalui `admin/users.html` atau halaman `register.html`.
   - Role pengguna adalah **Peserta**.
   - Akun siswa harus berstatus **Aktif** agar dapat mengakses materi.

5. **Admin mengaitkan siswa ke kursus** melalui `admin/enrollment.html`.
   - Satu siswa boleh dikaitkan ke **beberapa kursus** dalam satu waktu, sesuai kursus yang diikuti.
   - Satu kursus boleh memiliki banyak siswa.
   - Relasi disimpan sebagai enrollment dengan status **Aktif**.
   - Siswa hanya dapat membuka modul dan materi dari kursus yang ditugaskan kepadanya **dan** sudah berstatus **Published**.

6. **Instruktur masuk ke sistem**.
   - Instruktur hanya melihat kursus yang ditugaskan kepadanya.
   - Instruktur tidak dapat mengubah kursus milik instruktur lain.

7. **Instruktur membuat modul dan materi** melalui `instructor/manage-course.html`.
   - Modul dan materi hanya dapat dibuat pada kursus yang diampu.
   - Materi mendukung tipe teks, video, file, dan link eksternal.

8. **Siswa mempelajari materi** melalui `learning.html`.
   - Hanya kursus yang ditugaskan dan sudah Published yang dapat diakses.
   - Materi dibuka secara berurutan; pelajaran berikutnya terkunci sampai pelajaran sebelumnya selesai.
   - Progres belajar dicatat per pelajaran.

9. **Di akhir kursus, instruktur memberi nilai** melalui `instructor/students.html`.
   - Nilai berupa angka 0–100 dan otomatis dikonversi menjadi grade huruf (A–E).
   - Instruktur dapat menambahkan catatan nilai.

10. **Nilai terintegrasi dengan sertifikat**.
    - Saat nilai disimpan, sistem membuat atau memperbarui e-sertifikat peserta untuk kursus tersebut.
    - E-sertifikat baru selalu berstatus **Draft**.

11. **Sertifikat Draft tidak ditampilkan ke siswa**.
    - Halaman siswa hanya menampilkan e-sertifikat berstatus **Publish**.
    - Sertifikat Draft tidak boleh muncul di daftar, tidak dapat dilihat, dan tidak dapat diverifikasi oleh siswa.

12. **Admin menyetujui sertifikat** melalui `admin/certificates.html`.
    - Admin menekan **Setujui & Terbitkan** pada sertifikat Draft.
    - Status berubah menjadi **Publish**, nomor sertifikat final dan tanggal persetujuan disimpan.
    - Setelah Publish, sertifikat tampil di halaman siswa dan dapat diverifikasi publik.

### 3.3 Aturan Status

| Entitas | Status | Makna | Transisi |
|---|---|---|---|
| **Kursus** | `draft` | Baru dibuat, belum dapat diakses siswa | `draft` → `published` oleh Admin |
| **Kursus** | `published` | Aktif dan dapat diakses siswa yang ditugaskan | `published` → `draft` oleh Admin |
| **Enrollment** | `active` | Siswa ditugaskan dan dapat mengakses kursus | `active` ↔ `dihentikan` |
| **Enrollment** | `dihentikan` | Akses siswa ke kursus dihentikan sementara | `dihentikan` → `active` |
| **Sertifikat** | `draft` | Sudah dinilai instruktur, menunggu persetujuan Admin | `draft` → `publish` |
| **Sertifikat** | `publish` | Disetujui Admin, tampil di halaman siswa, dapat diverifikasi | Final |
| **Pengguna** | `aktif` | Dapat login dan menggunakan sistem | `aktif` ↔ `nonaktif` |
| **Pengguna** | `nonaktif` | Tidak dapat login | `nonaktif` → `aktif` |

### 3.4 Matriks Hak Akses

| Aksi | Admin | Instruktur | Siswa |
|---|---|---|---|
| Menambah/mengubah kursus | ✅ | ❌ | ❌ |
| Menugaskan instruktur ke kursus | ✅ | ❌ | ❌ |
| Publikasi / menarik kursus ke Draft | ✅ | ❌ | ❌ |
| Menambah/mengubah pengguna | ✅ | ❌ | ❌ |
| Menugaskan siswa ke kursus | ✅ | ❌ | ❌ |
| Melihat kursus yang diampu | ✅ (semua) | ✅ (miliknya) | ❌ |
| Membuat modul & materi | ❌ | ✅ (kursus sendiri) | ❌ |
| Memberi nilai | ❌ | ✅ (kursus sendiri) | ❌ |
| Melihat materi kursus | ✅ (pratinjau) | ✅ (pratinjau) | ✅ (kursus ditugaskan & Published) |
| Melihat sertifikat Draft | ✅ | ✅ (kursus sendiri) | ❌ |
| Menyetujui sertifikat | ✅ | ❌ | ❌ |
| Melihat sertifikat Publish | ✅ | ✅ | ✅ (milik sendiri) |

### 3.5 Status Implementasi Demo

| Langkah | Status | Catatan Implementasi |
|---|---|---|
| Admin menambah kursus baru berstatus Draft | ✅ Selesai | `ELIPS.createCourse` selalu memaksa status `draft`; form tambah tidak lagi bisa memilih Published |
| Admin menambah akun instruktur | ✅ Selesai | `admin/users.html` - tambah pengguna role Instruktur |
| 1 kursus = 1 instruktur | ✅ Selesai | `course.instructorId`; penugasan ulang menggantikan instruktur lama |
| Admin menambah akun siswa | ✅ Selesai | `admin/users.html` dan `register.html` |
| 1 siswa dapat dikaitkan ke beberapa kursus | ✅ Selesai | `admin/enrollment.html` - checkbox multi-kursus |
| Instruktur login dan hanya melihat kursus sendiri | ✅ Selesai | `instructor/dashboard.html`, `instructor/manage-course.html` |
| Instruktur membuat modul dan materi | ✅ Selesai | `instructor/manage-course.html` |
| Siswa hanya mengakses kursus yang ditugaskan | ✅ Selesai | `learning.html` memvalidasi enrollment, status kursus, dan urutan materi |
| Instruktur memberi nilai di akhir kursus | ✅ Selesai | `instructor/students.html` |
| Nilai terintegrasi ke e-sertifikat Draft | ✅ Selesai | Nilai disimpan di `elips_grades`; Draft dibuat di `elips_certificates` |
| Sertifikat Draft tidak tampil di halaman siswa | ✅ Selesai | `student/certificates.html` hanya membaca `ELIPS.getPublishedCertificatesByStudent`; section Draft dihapus; dashboard siswa tidak lagi memberi notifikasi Draft; status/ID data lama dinormalkan dan tampilan disegarkan otomatis saat data berubah dari tab lain |
| Admin menyetujui Draft menjadi Publish | ✅ Selesai | `admin/certificates.html` memanggil `ELIPS.approveCertificate`; nomor final dan tanggal approval disimpan |
| Sertifikat Publish tampil di halaman siswa | ✅ Selesai | `student/certificates.html` - hanya section Sertifikat Terbit |
| Publikasi kursus tervalidasi | ✅ Selesai | `ELIPS.setCourseStatus` menolak publikasi tanpa instruktur aktif atau tanpa modul/materi |
| Penghapusan instruktur mengembalikan kursus ke Draft | ✅ Selesai | `ELIPS.deleteUser`/`updateUser` melepas kursus dan memaksa status Draft |
| CRUD terpusat di JavaScript | ✅ Selesai | Seluruh CRUD pengguna, kursus, enrollment, modul, materi, nilai, dan sertifikat memakai modul CRUD di `assets/js/` (lihat §4) |
| Audit trail perubahan kursus, nilai, dan sertifikat | ✅ Selesai | `assets/js/audit.js` mencatat aktor, aksi, entitas, waktu, dan detail; halaman `admin/audit.html` menyediakan filter, ekspor CSV, dan pembersihan log |
| Notifikasi email simulasi | ✅ Selesai | `assets/js/notifications.js` mencatat outbox saat kursus dipublikasikan atau sertifikat terbit; `admin/notifications.html` menyediakan filter, ekspor CSV, dan pembersihan outbox |

---

## 4. Arsitektur Saat Ini (Demo)

- **Frontend:** HTML5, CSS3 (custom dengan variabel CSS), Vanilla JavaScript
- **Business Logic & CRUD Layer:** JavaScript modular di `assets/js/` menjadi satu-satunya lapisan data dan aturan bisnis:
  - `core.js` - namespace, storage helper, result object, validasi dasar;
  - `data.js` - getter/setter seluruh koleksi data;
  - `seed.js` - data demo awal dan migrasi data lama;
  - `sync.js` - sinkronisasi nama instruktur, kepemilikan konten, dan counter kursus;
  - `progress.js` - struktur kursus, progres, dan ringkasan siswa/instruktur;
  - `auth.js` - session dan role guard;
  - `audit.js` - pencatatan audit trail, filter log, dan daftar aktor;
  - `notifications.js` - outbox notifikasi email simulasi, filter, daftar penerima, dan pembersihan outbox;
  - `crud-users.js`, `crud-courses.js`, `crud-enrollments.js`, `crud-content.js`, `crud-grades.js` - CRUD dan aturan bisnis per domain;
  - `utils.js` - helper format, escape HTML, dan toast;
  - `ui.js` - perilaku UI bersama (navigasi, sidebar, katalog, login, logout).
  - Halaman hanya menangani rendering dan interaksi UI, tidak menulis langsung ke `localStorage`.
  - Setiap fungsi CRUD mengembalikan `{ ok, message, data }` agar pesan sukses/gagal konsisten.
  - Modul dimuat berurutan: `core → utils → data → seed → sync → progress → auth → audit → notifications → crud-* → ui`.
- **Penyimpanan Data:** `localStorage` (demo)
  - `elips_users` - Data pengguna (admin, instruktur, peserta)
  - `elips_courses` - Data kursus beserta instruktur pengampu dan status
  - `elips_enrollments` - Data penugasan peserta ke kursus
  - `elips_modules` - Modul pada setiap kursus
  - `elips_lessons` - Materi/pelajaran pada setiap modul
  - `elips_grades` - Nilai peserta per kursus
  - `elips_certificates` - Data e-sertifikat (Draft/Terbit)
  - `elips_completedLessons` - Tracking pelajaran yang diselesaikan
  - `elips_auditLogs` - Jejak audit perubahan kursus, nilai, sertifikat, pengguna, penugasan, dan konten
  - `elips_notifications` - Outbox notifikasi email simulasi (publikasi kursus dan penerbitan sertifikat)
  - `elips_session` - Sesi pengguna yang sedang masuk
- **Styling:** Custom CSS (`assets/css/style.css`), Bootstrap Icons, Google Fonts (Inter)
- **Struktur Folder:** Berbasis role (`admin/`, `instructor/`, `student/`)

---

## 5. Struktur File yang Telah Dikembangkan

### Halaman Publik
| File | Status | Deskripsi |
|---|---|---|
| `index.html` | ✅ Selesai | Beranda dengan daftar kursus |
| `course-detail.html` | ✅ Selesai | Detail kursus, tombol enrolment dinamis (berubah menjadi "Sudah Terdaftar - Lanjutkan Belajar") |
| `login.html` | ✅ Selesai | Halaman login dengan validasi email + password terhadap `elips_users` dan redirect berbasis role |
| `register.html` | ✅ Selesai | Halaman registrasi |
| `verify-certificate.html` | ✅ Selesai | Verifikasi keaslian sertifikat publik |
| `certificate-view.html` | ✅ Selesai | Tampilan sertifikat dinamis dari URL params/localStorage |
| `learning.html` | ✅ Selesai | Halaman belajar dengan tracking pelajaran selesai; mendukung mode pratinjau untuk instruktur/admin tanpa mengubah progres peserta |

### Role Admin (`admin/`)
| File | Status | Deskripsi |
|---|---|---|
| `admin/dashboard.html` | ✅ Selesai | Dashboard admin dengan ringkasan statistik |
| `admin/users.html` | ✅ Selesai | Tambah pengguna diawali modal pilihan role Admin/Instruktur/Peserta, edit/hapus pengguna, filter role & pencarian, serta penugasan instruktur ke kursus |
| `admin/courses.html` | ✅ Selesai | Tambah/edit kursus, tugaskan 1 instruktur per kursus, publikasi/Draft, filter status & pencarian |
| `admin/enrollment.html` | ✅ Selesai | Penugasan siswa ke satu atau beberapa kursus, filter kursus/status, aktif/nonaktifkan penugasan |
| `admin/certificates.html` | ✅ Selesai | Persetujuan e-sertifikat: Draft → Publish, filter status, dan tampilan nomor/tanggal persetujuan |
| `admin/notifications.html` | ✅ Selesai | Outbox notifikasi email simulasi: statistik, filter jenis/penerima/tanggal/pencarian, detail isi, ekspor CSV, dan pembersihan outbox |
| `admin/audit.html` | ✅ Selesai | Audit trail: statistik aktivitas, filter aktor/aksi/entitas/tanggal/pencarian, ekspor CSV, dan pembersihan log |
| `admin/reports.html` | ✅ Selesai | Laporan progres peserta per kursus, statistik per kursus, export PDF/Excel (UI) |
| `admin/settings.html` | ✅ Selesai | Pengaturan umum, pengaturan sertifikat, pengaturan pembelajaran |

### Role Instruktur (`instructor/`)
| File | Status | Deskripsi |
|---|---|---|
| `instructor/dashboard.html` | ✅ Selesai | Dashboard dinamis: statistik kursus/peserta/pelajaran/sertifikat, notifikasi draft & penilaian tertunda, daftar kursus, dan aktivitas peserta terbaru |
| `instructor/manage-course.html` | ✅ Selesai | Kelola struktur kursus: tambah/ubah/hapus modul dan materi (teks, video, file, link) dengan validasi kepemilikan kursus |
| `instructor/students.html` | ✅ Selesai | Daftar peserta per kursus dengan filter status/pencarian, progres, riwayat nilai, serta modal penilaian yang otomatis membuat e-sertifikat Draft |
| `instructor/certificates.html` | ✅ Selesai | Monitoring e-sertifikat peserta (Draft/Terbit), filter kursus & status, pengajuan sertifikat untuk peserta yang sudah dinilai namun belum memiliki sertifikat |
| `instructor/profile.html` | ✅ Selesai | Profil instruktur: ringkasan statistik, informasi akun, pembaruan nama/no. telepon, dan sinkronisasi nama pada kursus yang diampu |

### Role Peserta (`student/`)
| File | Status | Deskripsi |
|---|---|---|
| `student/dashboard.html` | ✅ Selesai | Dashboard peserta dengan kursus yang diikuti, progres, nilai, status sertifikat, dan ringkasan statistik |
| `student/courses.html` | ✅ Selesai | Daftar seluruh kursus peserta dengan filter status, pencarian, progres, nilai, dan akses belajar |
| `student/certificates.html` | ✅ Selesai | Daftar sertifikat peserta; hanya sertifikat **Publish** yang ditampilkan, Draft sepenuhnya disembunyikan |
| `student/profile.html` | ✅ Selesai | Profil peserta dan pembaruan nama/no. telepon |

### Assets
| File | Status | Deskripsi |
|---|---|---|
| `assets/css/style.css` | ✅ Selesai | Styling utama untuk seluruh aplikasi |
| `assets/js/core.js` | ✅ Selesai | Namespace, storage helper, result object, dan validasi dasar |
| `assets/js/utils.js` | ✅ Selesai | Helper grade, nomor sertifikat, escape HTML, format tanggal, toast |
| `assets/js/data.js` | ✅ Selesai | Getter/setter seluruh koleksi data |
| `assets/js/seed.js` | ✅ Selesai | Data demo awal dan normalisasi/migrasi data lama |
| `assets/js/sync.js` | ✅ Selesai | Sinkronisasi nama instruktur, kepemilikan konten, counter kursus |
| `assets/js/progress.js` | ✅ Selesai | Struktur kursus, progres belajar, overview siswa/instruktur |
| `assets/js/auth.js` | ✅ Selesai | Session, user aktif, redirect, dan role guard |
| `assets/js/audit.js` | ✅ Selesai | Pencatatan audit trail, filter log, daftar aktor, dan pembersihan log |
| `assets/js/notifications.js` | ✅ Selesai | Outbox notifikasi email simulasi, filter, daftar penerima, dan pembersihan outbox |
| `assets/js/crud-users.js` | ✅ Selesai | CRUD pengguna dan penugasan instruktur |
| `assets/js/crud-courses.js` | ✅ Selesai | CRUD kursus dan aturan Draft/Publish |
| `assets/js/crud-enrollments.js` | ✅ Selesai | CRUD penugasan peserta ke kursus |
| `assets/js/crud-content.js` | ✅ Selesai | CRUD modul dan materi dengan validasi kepemilikan |
| `assets/js/crud-grades.js` | ✅ Selesai | Input nilai dan alur sertifikat Draft → Publish |
| `assets/js/ui.js` | ✅ Selesai | Perilaku UI bersama: navigasi, sidebar, katalog, login, logout |

---

## 6. Fitur yang Telah Diimplementasikan

### 6.1 Autentikasi & Role-Based Access
| Fitur | Status | Keterangan |
|---|---|---|
| Login form | ✅ Selesai | Form login di `login.html`, email + password diverifikasi melalui `ELIPS.authenticateUser()`; akun demo memakai password `elips123` |
| Role redirect berdasarkan role akun | ✅ Selesai | `admin` → `admin/dashboard.html`, `instruktur` → `instructor/dashboard.html`, `peserta` → `student/dashboard.html` |
| Role guard halaman | ✅ Selesai | Halaman admin memanggil `ELIPS.requireRole('admin')`; halaman instruktur memanggil `ELIPS.requireRole('instruktur')`; halaman peserta memeriksa sesi/role. `getCurrentUser()` hanya membaca sesi, tanpa fallback path |
| Password akun | ✅ Selesai | Password wajib minimal 8 karakter saat Admin membuat pengguna/registrasi; akun demo dan akun lama dimigrasikan ke password demo `elips123` |
| Sanitasi materi teks | ✅ Selesai | `ELIPS.sanitizeHtml()` memakai allowlist tag/atribut, diterapkan saat menyimpan dan merender konten materi untuk mencegah stored XSS |
| Struktur folder per role | ✅ Selesai | Semua halaman role dipindahkan ke folder terpisah |

### 6.2 Manajemen Kursus
| Fitur | Status | Keterangan |
|---|---|---|
| Daftar kursus (beranda) | ✅ Selesai | Ditampilkan di `index.html` (hanya kursus Publish) |
| Detail kursus | ✅ Selesai | `course-detail.html` |
| Tambah/edit kursus (Admin) | ✅ Selesai | `admin/courses.html` - kursus baru berstatus Draft, publikasi dilakukan Admin |
| Penugasan instruktur (Admin) | ✅ Selesai | Satu kursus hanya memiliki satu instruktur; dapat diubah melalui form kursus atau menu pengguna |
| Kelola kursus (Instruktur) | ✅ Selesai | `instructor/manage-course.html` - daftar kursus yang diampu, deep link `?course=id` |
| CRUD modul & materi (Instruktur) | ✅ Selesai | Tambah/ubah/hapus modul dan pelajaran, tipe konten teks/video/file/link, validasi kepemilikan kursus, sinkronisasi jumlah modul/pelajaran pada data kursus, serta sanitasi HTML konten teks |

### 6.3 Enrolment & Progres Belajar
| Fitur | Status | Keterangan |
|---|---|---|
| Penugasan siswa ke kursus | ✅ Selesai | Dilakukan Admin melalui `admin/enrollment.html`, disimpan di `elips_enrollments` |
| Satu siswa ke beberapa kursus | ✅ Selesai | Form penugasan mendukung pilihan multi-kursus per siswa |
| Akses kursus oleh siswa | ✅ Selesai | Siswa hanya dapat membuka kursus yang ditugaskan dan berstatus Published |
| Status enrolment dinamis | ✅ Selesai | Tombol di `course-detail.html` berubah menjadi "Lanjutkan Belajar" bila sudah ditugaskan |
| Halaman belajar | ✅ Selesai | `learning.html` dengan sidebar modul/lesson dan penguncian materi berurutan |
| Tandai pelajaran selesai | ✅ Selesai | Menyimpan ke `localStorage` (`elips_completedLessons`) |
| Tracking progres | ✅ Selesai | Progres dihitung berdasarkan pelajaran selesai |
| Kelola enrolment (Admin) | ✅ Selesai | `admin/enrollment.html` - tambah penugasan, filter, aktif/nonaktifkan, hapus |

### 6.4 Sertifikat
| Fitur | Status | Keterangan |
|---|---|---|
| Alur e-sertifikat | ✅ Selesai | Instruktur memberi nilai → e-sertifikat otomatis dibuat berstatus **Draft** → Admin menyetujui di `admin/certificates.html` → status menjadi **Publish** |
| Integrasi nilai → sertifikat | ✅ Selesai | Nilai/grade dari `elips_grades` disalin ke e-sertifikat saat penilaian disimpan |
| Sertifikat Draft tidak tampil ke siswa | ✅ Selesai | `student/certificates.html` memakai `getPublishedCertificatesByStudent`; Draft tidak dirender dan notifikasinya dihapus dari dashboard |
| Sertifikat peserta (Instruktur) | ✅ Selesai | `instructor/certificates.html` menampilkan Draft/Publish, filter kursus & status, serta pengajuan Draft untuk peserta yang sudah dinilai |
| Tampilan sertifikat | ✅ Selesai | `certificate-view.html` menampilkan sertifikat dari URL params/localStorage |
| Verifikasi sertifikat publik | ✅ Selesai | `verify-certificate.html` - hanya sertifikat Publish yang dapat diverifikasi |
| Kelola sertifikat (Admin) | ✅ Selesai | `admin/certificates.html` - persetujuan Draft menjadi Publish |

### 6.5 Dashboard & Pelaporan
| Fitur | Status | Keterangan |
|---|---|---|
| Dashboard Admin | ✅ Selesai | Ringkasan pengguna, kursus, enrolment |
| Dashboard Instruktur | ✅ Selesai | Statistik kursus/peserta/pelajaran/sertifikat, notifikasi draft & penilaian tertunda, tabel kursus yang diampu, aktivitas peserta terbaru |
| Dashboard Peserta | ✅ Selesai | Kursus diikuti, progres, akses cepat ke sertifikat |
| Peserta & Penilaian (Instruktur) | ✅ Selesai | Filter kursus/status/pencarian, modal penilaian dengan peringatan progres, catatan nilai, dan pembuatan e-sertifikat Draft |
| Profil Instruktur | ✅ Selesai | Ringkasan kursus, pembaruan data akun, sinkronisasi nama instruktur ke kursus |
| Laporan (Admin) | ✅ Selesai | `admin/reports.html` - progres per peserta, statistik per kursus |
| Pengaturan (Admin) | ✅ Selesai | `admin/settings.html` - pengaturan umum, sertifikat, pembelajaran |

### 6.6 Manajemen Pengguna
| Fitur | Status | Keterangan |
|---|---|---|
| Daftar pengguna | ✅ Selesai | `admin/users.html` |
| Tambah/edit/hapus pengguna | ✅ Selesai | Form pengguna dengan nama, email, telepon, role, dan status |
| Pilihan role saat tambah pengguna | ✅ Selesai | Klik **Tambah Pengguna** membuka modal pertama berisi 3 tombol: Admin, Instruktur, dan Peserta; role terpilih langsung mengisi form |
| Penugasan instruktur ke kursus | ✅ Selesai | Modal penugasan pada `admin/users.html`; satu kursus hanya punya satu instruktur |
| Filter role & pencarian | ✅ Selesai | Filter Semua/Admin/Instruktur/Peserta + search |
| Registrasi mandiri peserta | ✅ Selesai | `register.html` membuat akun role Peserta, lalu diarahkan ke dashboard siswa |

### 6.7 Audit Trail
| Fitur | Status | Keterangan |
|---|---|---|
| Pencatatan perubahan kursus | ✅ Selesai | Create, update, delete, publish, dan unpublish kursus tercatat di `elips_auditLogs` |
| Pencatatan nilai | ✅ Selesai | Setiap penyimpanan nilai mencatat aktor, skor, grade, catatan, peserta, dan kursus |
| Pencatatan status sertifikat | ✅ Selesai | Pengajuan Draft dan persetujuan Draft → Publish tercatat |
| Pencatatan domain lain | ✅ Selesai | Pengguna, penugasan peserta, modul, dan materi juga tercatat |
| Resolusi aktor | ✅ Selesai | Prioritas: `actorId` eksplisit → user sesi → "Sistem"; tidak memakai fallback berbasis path halaman |
| Filter & ekspor | ✅ Selesai | `admin/audit.html` memfilter berdasarkan aktor, aksi, entitas, tanggal, pencarian, dan limit; dapat diekspor ke CSV |
| Batas penyimpanan | ✅ Selesai | Maksimal 1000 entri terbaru disimpan agar `localStorage` tidak tumbuh tanpa kendali |

### 6.8 Notifikasi Email (Simulasi)
| Fitur | Status | Keterangan |
|---|---|---|
| Notifikasi publikasi kursus | ✅ Selesai | Saat kursus berhasil Published, outbox mencatat email untuk peserta aktif yang terdaftar dan instruktur aktif pengampu |
| Notifikasi sertifikat terbit | ✅ Selesai | Saat Admin menyetujui sertifikat Draft → Publish, outbox mencatat email untuk peserta |
| Outbox notifikasi (Admin) | ✅ Selesai | `admin/notifications.html` menampilkan waktu, penerima, jenis, subjek, isi, dan status simulasi |
| Filter & ekspor | ✅ Selesai | Filter jenis, penerima, rentang tanggal, pencarian, dan limit; hasil dapat diekspor ke CSV |
| Batas penyimpanan | ✅ Selesai | Maksimal 1000 notifikasi terbaru disimpan di `elips_notifications` |
| Pengiriman email sungguhan | ⏳ Belum | Demo tanpa backend hanya mencatat email ke outbox; pengiriman nyata memerlukan SMTP/email service di produksi |

---

## 7. Logika Bisnis Penting

### Login Redirect
```javascript
// Login diverifikasi melalui API terpusat (email + password)
const result = ELIPS.authenticateUser(email, password);
if (!result.ok) tampilkan pesan;
const user = result.data;
ELIPS.setCurrentUser(user.id);
if (user.role === 'admin')      -> admin/dashboard.html
else if (user.role === 'instruktur') -> instructor/dashboard.html
else                            -> student/dashboard.html
```

### Role Guard
- Halaman admin memanggil `ELIPS.requireRole('admin')`; halaman instruktur memanggil `ELIPS.requireRole('instruktur')`; halaman peserta memeriksa sesi dan role sebelum render.
- Bila belum ada sesi, pengguna diarahkan ke `login.html`.
- Bila role tidak sesuai, pengguna diarahkan ke dashboard sesuai role akunnya.
- `getCurrentUser()` hanya mengembalikan user dari `elips_session`; tidak ada lagi fallback demo berdasarkan path halaman.

### Keamanan Konten Materi
- Konten `lesson.content` bertipe `text` dianggap tidak tepercaya.
- `ELIPS.sanitizeHtml()` menghapus `script`, `iframe`, event handler (`on*`), dan URL berbahaya (`javascript:`, `data:`, `vbscript:`), serta hanya mengizinkan tag/atribut aman.
- Sanitasi dilakukan saat menyimpan materi (create/update), saat migrasi data lama, dan saat render di `learning.html` (defense in depth).

### Enrolment
- Data disimpan di `localStorage` key `elips_enrollments` (array of objects).
- Penugasan siswa ke kursus hanya dilakukan oleh Admin melalui `admin/enrollment.html`.
- Satu siswa dapat memiliki banyak enrollment (satu baris per kursus); satu kursus dapat memiliki banyak siswa.
- Akses siswa divalidasi di `course-detail.html` dan `learning.html`:
  - enrollment harus ada dan berstatus `active`;
  - kursus harus berstatus `published`;
  - siswa hanya melihat modul/materi dari kursus yang ditugaskan.
- Enrollment berstatus `dihentikan` mencabut akses siswa tanpa menghapus riwayat progres dan nilai.

### Progres Belajar
- Pelajaran selesai disimpan di `localStorage` key `elips_completedLessons`
- Progres dihitung berdasarkan jumlah pelajaran selesai vs total pelajaran
- Materi terkunci sampai pelajaran sebelumnya selesai (instruktur/admin dapat melewati saat pratinjau)

### Penilaian & Sertifikat
- Nilai disimpan di `localStorage` key `elips_grades` dan dihitung hurufnya otomatis (A–E).
- Instruktur hanya dapat menilai peserta pada kursus yang diampu.
- Saat instruktur menyimpan nilai, sistem membuat/memperbarui e-sertifikat dengan status **Draft** dan menyalin nilai/grade ke sertifikat.
- Sertifikat **Draft**:
  - hanya terlihat oleh Admin dan Instruktur pengampu;
  - **tidak ditampilkan** di halaman siswa;
  - belum dapat dilihat melalui `certificate-view.html` maupun diverifikasi melalui `verify-certificate.html`.
- Admin menyetujui Draft di `admin/certificates.html`; status berubah menjadi **Publish**, `approvedByAdminId` dan `approvedAt` diisi, dan nomor sertifikat final disimpan.
- Sertifikat **Publish**:
  - tampil di `student/certificates.html`;
  - dapat dilihat di `certificate-view.html`;
  - dapat diverifikasi publik di `verify-certificate.html`.

### Audit Trail
- Setiap operasi bisnis yang berhasil memanggil `ELIPS.logAudit()` dan menulis satu entri ke `localStorage` key `elips_auditLogs`.
- Entri audit menyimpan `action`, `entity`, `entityId`, `entityLabel`, `actorId`, `actorName`, `actorRole`, `detail`, dan `createdAt`.
- Resolusi aktor: `actorId` eksplisit dari pemanggil → user sesi (`elips_session`) → "Sistem". Fallback `getCurrentUser()` tidak dipakai agar tidak salah atribusi saat tidak ada sesi.
- Operasi yang gagal (validasi ditolak) tidak dicatat.
- Aksi yang dicatat: `create`, `update`, `delete`, `publish`, `unpublish`, `grade`, `submit`, `approve`, `assign`, dan `status`.
- Entitas yang dicatat: `course`, `grade`, `certificate`, `user`, `enrollment`, `module`, dan `lesson`.
- Log diurutkan terbaru lebih dulu, dibatasi 1000 entri, dan dapat difilter berdasarkan aktor, aksi, entitas, rentang tanggal, pencarian, serta limit.
- Admin dapat mengekspor hasil filter ke CSV dan membersihkan seluruh log dari `admin/audit.html`.

### Notifikasi Email (Simulasi)
- Demo berjalan tanpa backend, sehingga email **tidak benar-benar dikirim**. Setiap notifikasi disimpan sebagai entri outbox di `localStorage` key `elips_notifications` dengan `channel: 'email'`, `status: 'sent'`, dan `simulated: true`.
- Notifikasi publikasi kursus dibuat setelah `setCourseStatus` berhasil mengubah status menjadi `published`:
  - penerima: peserta dengan enrollment `active` pada kursus tersebut dan akun berstatus `aktif`;
  - ditambah instruktur pengampu yang berstatus `aktif`;
  - penerima duplikat dihindari.
- Notifikasi sertifikat terbit dibuat setelah `approveCertificate` berhasil mengubah status Draft → Publish; penerimanya adalah peserta pemilik sertifikat.
- Operasi yang gagal (publikasi ditolak atau approval ditolak) tidak membuat notifikasi.
- Outbox diurutkan terbaru lebih dulu, dibatasi 1000 entri, dan dapat difilter berdasarkan jenis, penerima, rentang tanggal, pencarian, serta limit.
- Admin dapat mengekspor hasil filter ke CSV dan membersihkan seluruh outbox dari `admin/notifications.html`.

---

## 8. Testing Checklist

| Skenario | Status | Catatan |
|---|---|---|
| Login redirect berdasarkan role akun | ✅ Lulus uji | Validasi email + password terhadap `elips_users`, redirect admin/instruktur/peserta |
| Password salah / kosong ditolak | ✅ Lulus uji | `authenticateUser()` menolak kredensial tidak valid dan akun nonaktif |
| Halaman role tanpa sesi dialihkan ke login | ✅ Lulus uji | `getCurrentUser()` null tanpa sesi; halaman admin memakai `requireRole('admin')` |
| Sanitasi HTML materi | ✅ Lulus uji | `script`, event handler, dan URL `javascript:` dibuang; markup aman dipertahankan |
| Admin menambah kursus baru | ✅ Lulus uji | Kursus baru tersimpan dan berstatus Draft |
| Admin menambah instruktur | ✅ Lulus uji | Akun instruktur aktif dapat dipilih pada form kursus |
| Modal pilih role tambah pengguna | ✅ Lulus uji | Modal pertama menampilkan 3 tombol role dan mempreset role pada form pengguna |
| Penugasan 1 kursus = 1 instruktur | ✅ Lulus uji | Penugasan ulang menggantikan instruktur sebelumnya |
| Admin menambah siswa | ✅ Lulus uji | Akun peserta aktif dapat ditugaskan ke kursus |
| Admin mengaitkan siswa ke beberapa kursus | ✅ Lulus uji | Multi-checkbox pada `admin/enrollment.html` |
| Siswa hanya mengakses kursus yang ditugaskan | ✅ Lulus uji | `learning.html` menolak kursus tanpa enrollment aktif |
| Belajar & tandai selesai | ✅ Lulus uji | Progres ter-update di localStorage dan materi berurutan |
| Instruktur membuat modul & materi | ✅ Lulus uji | Tambah/ubah/hapus modul dan materi tersimpan di localStorage |
| Instruktur memberi nilai | ✅ Lulus uji | Nilai tersimpan, grade otomatis, e-sertifikat Draft dibuat |
| Sertifikat Draft tidak tampil di halaman siswa | ✅ Lulus uji | Hanya sertifikat Publish yang dirender; sertifikat Draft tidak bocor ke halaman siswa |
| Sertifikat tampil setelah persetujuan Admin | ✅ Lulus uji | Sertifikat Publish tampil di halaman peserta, termasuk data lama dengan ID string/status kapital, dan tampilan menyegarkan otomatis saat berubah dari tab lain |
| Validasi publikasi kursus | ✅ Lulus uji | Publikasi ditolak bila tanpa instruktur aktif atau tanpa modul/materi |
| CRUD terpusat di modul `assets/js/` | ✅ Lulus uji | create/update/delete pengguna, kursus, enrollment, modul, materi, nilai, dan sertifikat |
| Admin menyetujui sertifikat | ✅ Lulus uji | Draft → Publish, `approvedAt` dan approver tersimpan |
| Sertifikat Publish tampil dan dapat diverifikasi | ✅ Lulus uji | Muncul di halaman siswa, `certificate-view.html`, dan `verify-certificate.html` |
| Filter & search di admin/users.html | ✅ Lulus uji | Berbagai kombinasi filter |
| Filter & search di admin/courses.html | ✅ Lulus uji | Filter status dan pencarian |
| Dashboard instruktur | ✅ Lulus uji | Statistik, notifikasi, tabel kursus, aktivitas peserta |
| Profil instruktur | ✅ Lulus uji | Nama/telepon tersimpan dan tersinkron ke kursus |
| Mode pratinjau materi instruktur | ✅ Lulus uji | Progres peserta tidak berubah |
| Navigasi setelah folder restructuring | ✅ Lulus uji | Semua relative path |
| Audit trail perubahan kursus/nilai/sertifikat | ✅ Lulus uji | `test-audit` memverifikasi aktor, aksi, detail, filter, fallback "Sistem", dan halaman `admin/audit.html` |
| Operasi gagal tidak dicatat | ✅ Lulus uji | Publikasi kursus kosong ditolak tanpa menambah entri audit |
| Ekspor & pembersihan log | ✅ Lulus uji | Tombol CSV dan bersihkan log tersedia di `admin/audit.html` |
| Notifikasi publikasi kursus | ✅ Lulus uji | `test-notifications` memverifikasi notifikasi untuk peserta aktif dan instruktur, termasuk isi subjek/body |
| Notifikasi sertifikat terbit | ✅ Lulus uji | Approval Draft → Publish membuat satu notifikasi untuk peserta dengan nomor sertifikat final |
| Operasi gagal tidak membuat notifikasi | ✅ Lulus uji | Approval ulang dan publikasi tanpa perubahan status tidak menambah outbox |
| Filter, ekspor & pembersihan outbox | ✅ Lulus uji | Filter jenis/penerima/tanggal/pencarian dan tombol CSV/bersihkan tersedia di `admin/notifications.html` |

---

## 9. Catatan Pengembangan

- **Mode Demo:** Menggunakan vanilla HTML/CSS/JS tanpa backend. Cocok untuk prototyping dan demo.
- **Data Persisten:** Menggunakan localStorage browser - data akan hilang jika localStorage dibersihkan.
- **Struktur Rapi:** File terorganisir berdasarkan role untuk kemudahan navigasi.
- **Responsive:** Menggunakan CSS custom yang responsif.
- **UI Konsisten:** Menggunakan komponen seragam (navbar, sidebar, cards, tables, badges).
- **Keamanan Demo:** Password diverifikasi di client dan tersimpan apa adanya di `localStorage`; guard role juga client-side. Keduanya bukan pengganti autentikasi/middleware server-side untuk produksi.

---

## 10. Rencana Pengembangan Lanjutan (Opsional)

Jika ingin dikembangkan menjadi aplikasi production:

Audit trail (P1) dan notifikasi email (P2) sudah diimplementasikan dalam bentuk simulasi pada versi demo. Untuk production, audit perlu dipindahkan ke server agar immutable, sedangkan notifikasi perlu dihubungkan ke layanan email sungguhan.

| Fitur | Prioritas | Keterangan |
|---|---|---|
| Backend (Laravel/Node.js) | P0 | Ganti localStorage dengan database |
| Autentikasi sesungguhnya | P0 | Demo sudah memverifikasi password di client; produksi perlu validasi server-side dan hash password |
| Upload file materi | P0 | Upload PDF, video, gambar |
| Backend validasi status & kepemilikan | P0 | Validasi 1 kursus = 1 instruktur, enrollment, approval, dan CRUD di server |
| Audit trail server-side | P1 | Versi demo sudah mencatat audit di localStorage; produksi perlu log immutable di server dengan retensi dan akses terbatas |
| Notifikasi email | P2 | Versi demo mencatat email simulasi di outbox `localStorage`; produksi perlu integrasi SMTP/email service saat kursus dipublikasikan atau sertifikat terbit |
| Generate PDF sertifikat | P0 | Generate PDF yang sebenarnya |
| Role middleware | P0 | Guard client-side demo sudah aktif; produksi perlu middleware server-side |
| Database relasional | P0 | Users, Courses, Modules, Lessons, Enrollments, Progress, Certificates |
