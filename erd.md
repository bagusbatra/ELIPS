# ERD — ELIPS LMS

**Versi dokumen:** 1.0  
**Tanggal:** 2026-11-03  
**Status:** Demo — Vanilla HTML/CSS/JavaScript + `localStorage`  
**Dokumen terkait:** `prd.md`, `log.md`

---

## 1. Ringkasan

ELIPS LMS adalah aplikasi Learning Management System dengan tiga role: **Admin**, **Instruktur**, dan **Peserta**. Data disimpan sebagai koleksi JSON di `localStorage`, sehingga ERD ini bersifat **logis (logical ERD)**, bukan skema basis data relasional yang sudah memiliki constraint fisik.

Relasi utama:

```text
Admin ── mengelola ──► Pengguna, Kursus, Penugasan, Sertifikat
Instruktur ── mengampu ──► Kursus ── berisi ──► Modul ── berisi ──► Materi
Peserta ── ditugaskan melalui ──► Enrollment ──► Nilai ──► Sertifikat
Peserta ── menyelesaikan ──► CompletedLesson
Semua aktivitas penting ── dicatat ──► AuditLog
Publikasi kursus / penerbitan sertifikat ── menghasilkan ──► Notification
```

### 1.1 Cakupan

| Termasuk ERD | Tidak termasuk |
|---|---|
| 10 koleksi data utama di `localStorage` | Halaman statis dan tampilan UI |
| Objek sesi tunggal `elips_session` | Data pengaturan halaman Admin (belum dipersistensi) |
| Relasi, kardinalitas, dan aturan hapus | Data laporan/statistik turunan (dihitung saat runtime) |
| Enum status dan lifecycle | Integrasi email nyata (masih simulasi) |

---

## 2. Konvensi Notasi

| Simbol/Istilah | Arti |
|---|---|
| **PK** | Primary Key logis; ID unik di dalam koleksinya |
| **FK** | Foreign Key logis; referensi ke `id` koleksi lain |
| **UK** | Unique Key; kombinasi/field yang dijaga unik oleh kode aplikasi |
| `1 — N` | Satu baris memiliki banyak baris relasi |
| `1 — 0..1` | Satu baris memiliki nol atau satu baris relasi |
| `N — N` | Relasi banyak-ke-banyak melalui tabel penghubung |
| **Nullable** | Field boleh `null` atau tidak ada |
| **Snapshot** | Nilai disalin saat kejadian dan tidak otomatis berubah |
| **Denormalisasi** | Nilai duplikat untuk mempercepat pembacaan/tampilan |

Catatan penting:

- Tidak ada constraint database fisik. Integritas referensi dijaga oleh fungsi CRUD di `assets/js/crud-*.js`.
- ID setiap koleksi dibuat dengan `nextId()` = `max(id) + 1`, sehingga **ID tidak global** dan bisa dipakai ulang jika record dengan ID terbesar dihapus.
- Tipe data di bawah adalah tipe logis JSON: `number`, `string`, `boolean`, `date` (`YYYY-MM-DD`), `datetime` (ISO 8601), `enum`, `text`, `object`.

---

## 3. Diagram ERD

```mermaid
erDiagram
    USERS {
        number id PK
        string name
        string email UK
        string phone
        enum role
        enum status
        string password
        date createdAt
        datetime updatedAt
    }

    COURSES {
        number id PK
        string title
        string category
        text description
        number instructorId FK
        string instructorName
        number modules
        number lessons
        number totalLessons
        enum status
        date createdAt
        datetime updatedAt
    }

    ENROLLMENTS {
        number id PK
        number studentId FK
        number courseId FK
        string enrolledBy
        date enrolledAt
        enum status
        datetime updatedAt
    }

    MODULES {
        number id PK
        number courseId FK
        number instructorId FK
        string title
        text description
        number order
        datetime createdAt
        datetime updatedAt
    }

    LESSONS {
        number id PK
        number moduleId FK
        number courseId FK
        number instructorId FK
        string title
        enum type
        text content
        number order
        datetime createdAt
        datetime updatedAt
    }

    GRADES {
        number id PK
        number enrollmentId FK
        number courseId FK
        number studentId FK
        number instructorId FK
        number score
        enum grade
        text note
        datetime createdAt
        datetime updatedAt
    }

    CERTIFICATES {
        number id PK
        number studentId FK
        number courseId FK
        number enrollmentId FK
        string certificateNumber UK
        enum status
        string grade
        number score
        datetime issuedByInstructorAt
        number approvedByAdminId FK
        datetime approvedAt
        datetime createdAt
        datetime updatedAt
    }

    COMPLETED_LESSONS {
        number studentId PK, FK
        number courseId PK, FK
        number lessonId PK, FK
        datetime completedAt
    }

    AUDIT_LOGS {
        number id PK
        enum action
        enum entity
        number entityId
        string entityLabel
        number actorId FK
        string actorName
        string actorRole
        text detail
        datetime createdAt
    }

    NOTIFICATIONS {
        number id PK
        enum type
        string channel
        boolean simulated
        string status
        number recipientId FK
        string recipientName
        string recipientEmail
        string recipientRole
        string subject
        text body
        string entity
        number entityId
        string entityLabel
        datetime createdAt
    }

    SESSION {
        number userId PK, FK
        datetime loggedInAt
    }

    USERS |o--o{ COURSES : "mengampu sebagai instruktur"
    USERS ||--o{ ENROLLMENTS : "ditugaskan sebagai peserta"
    COURSES ||--o{ ENROLLMENTS : "memiliki peserta"
    COURSES ||--o{ MODULES : "berisi modul"
    MODULES ||--o{ LESSONS : "berisi materi"
    COURSES ||--o{ LESSONS : "memiliki materi denormalisasi"
    ENROLLMENTS ||--o| GRADES : "menghasilkan nilai"
    ENROLLMENTS ||--o| CERTIFICATES : "menghasilkan sertifikat"
    USERS ||--o{ GRADES : "menerima nilai sebagai peserta"
    USERS |o--o{ GRADES : "memberi nilai sebagai instruktur"
    USERS ||--o{ CERTIFICATES : "menerima sertifikat"
    USERS |o--o{ CERTIFICATES : "menyetujui sertifikat sebagai admin"
    USERS ||--o{ COMPLETED_LESSONS : "menyelesaikan materi"
    LESSONS ||--o{ COMPLETED_LESSONS : "diselesaikan pada"
    USERS |o--o{ AUDIT_LOGS : "menjadi aktor"
    USERS |o--o{ NOTIFICATIONS : "menerima notifikasi"
    COURSES |o--o{ NOTIFICATIONS : "direferensikan notifikasi"
    CERTIFICATES |o--o{ NOTIFICATIONS : "direferensikan notifikasi"
    USERS ||--o| SESSION : "memiliki sesi aktif"
```

> **Cara membaca:** `USERS |o--o{ COURSES` berarti satu instruktur dapat mengampu nol atau banyak kursus, sedangkan satu kursus memiliki nol atau satu instruktur (boleh `null` setelah instruktur dilepas). Relasi peserta ke kursus bersifat **N—N** dan dipecah melalui `ENROLLMENTS`. Notasi `|o` di sisi `USERS` menandai FK yang boleh `null`; `||` menandai FK yang wajib terisi.

---

## 4. Entitas dan Storage Key

| No | Entitas | Storage Key | Bentuk | Jumlah Relasi Utama |
|---|---|---|---|---|
| 1 | `USERS` | `elips_users` | Array of object | Pusat referensi role dan aktor |
| 2 | `COURSES` | `elips_courses` | Array of object | Instruktur, enrollment, modul, materi |
| 3 | `ENROLLMENTS` | `elips_enrollments` | Array of object | Penghubung peserta ↔ kursus |
| 4 | `MODULES` | `elips_modules` | Array of object | Milik kursus, berisi materi |
| 5 | `LESSONS` | `elips_lessons` | Array of object | Milik modul dan kursus |
| 6 | `GRADES` | `elips_grades` | Array of object | Milik enrollment/peserta/kursus |
| 7 | `CERTIFICATES` | `elips_certificates` | Array of object | Milik peserta/kursus/enrollment |
| 8 | `COMPLETED_LESSONS` | `elips_completedLessons` | Array of object tanpa `id` | Progres peserta per materi |
| 9 | `AUDIT_LOGS` | `elips_auditLogs` | Array of object | Aktor dan entitas polimorfik |
| 10 | `NOTIFICATIONS` | `elips_notifications` | Array of object | Penerima dan entitas polimorfik |
| 11 | `SESSION` | `elips_session` | Object tunggal | Sesi user yang sedang login |

Key lama yang dimigrasikan: `elips_enrolled` (self-enrollment) dipindahkan ke `elips_enrollments` oleh `seed.js`, lalu dihapus.

---

## 5. Kamus Data

### 5.1 USERS — `elips_users`

Menyimpan seluruh akun: Admin, Instruktur, dan Peserta.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik pengguna |
| `name` | string | — | Ya | — | Nama lengkap |
| `email` | string | UK | Ya | — | Unik, dibandingkan case-insensitive |
| `phone` | string | — | Tidak | `''` | Nomor telepon |
| `role` | enum | — | Ya | `peserta` | `admin`, `instruktur`, `peserta` |
| `status` | enum | — | Ya | `aktif` | `aktif`, `nonaktif` |
| `password` | string | — | Tidak | — | Demo: disimpan apa adanya dan tidak divalidasi saat login |
| `createdAt` | date | — | Ya | hari ini | `YYYY-MM-DD` |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 saat diubah |

**Aturan:**

- `email` unik; `createUser` dan `updateUser` menolak duplikat.
- `id = 1` (Administrator utama) tidak dapat dihapus.
- Minimal satu akun `admin` harus tersisa.
- Menghapus peserta menghapus enrollment, nilai, sertifikat, dan progresnya.
- Menghapus instruktur melepas semua kursus yang diampu dan mengubah kursus Publish menjadi Draft.
- Mengubah role instruktur menjadi role lain melepas seluruh kursusnya.
- Menonaktifkan instruktur mengubah kursus Publish menjadi Draft, tetapi penugasan `instructorId` tetap tersimpan sampai diubah.

---

### 5.2 COURSES — `elips_courses`

Menyimpan kursus beserta instruktur pengampu dan status publikasinya.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik kursus |
| `title` | string | — | Ya | — | Judul kursus |
| `category` | string | — | Ya | — | Kategori bebas, mis. `Pelatihan`, `Soft Skill`, `Administrasi`, `Reguler Class` |
| `description` | text | — | Tidak | `''` | Deskripsi kursus |
| `instructorId` | number | FK → `users.id` | Ya saat dibuat | — | Harus akun ber-role `instruktur` dan `aktif`; dapat menjadi `null` setelah dilepas |
| `instructorName` | string | — | Ya | dari user | Denormalisasi nama instruktur; disinkronkan saat nama berubah |
| `modules` | number | — | Ya | `0` | Counter jumlah modul; dihitung ulang oleh `syncCourseCounters` |
| `lessons` | number | — | Ya | `0` | Counter jumlah materi |
| `totalLessons` | number | — | Ya | `0` | Duplikat dari `lessons` untuk kompatibilitas tampilan |
| `status` | enum | — | Ya | `draft` | `draft`, `published` |
| `createdAt` | date | — | Ya | hari ini | `YYYY-MM-DD` |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 |

**Aturan:**

- Kursus baru **selalu** dibuat `draft`; status tidak dapat diubah lewat form edit.
- Publikasi hanya boleh jika:
  1. `instructorId` menunjuk instruktur berstatus `aktif`, dan
  2. kursus memiliki minimal **1 modul** dan **1 materi**.
- Menarik kursus ke Draft selalu diperbolehkan.
- Menghapus kursus menghapus seluruh modul, materi, enrollment, nilai, sertifikat, dan progres terkait.

**Lifecycle status:**

```text
draft ── publikasi (validasi lolos) ──► published
published ── tarik ke draft ──► draft
published ── instruktur dilepas/dihapus/nonaktif ──► draft
```

---

### 5.3 ENROLLMENTS — `elips_enrollments`

Tabel penghubung **N—N** antara peserta dan kursus.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik penugasan |
| `studentId` | number | FK → `users.id` | Ya | — | Harus user ber-role `peserta` dan `aktif` |
| `courseId` | number | FK → `courses.id` | Ya | — | Kursus yang ditugaskan |
| `enrolledBy` | string | — | Ya | `admin` | Pencatat penugasan; data lama dapat berisi nilai lain |
| `enrolledAt` | date | — | Ya | hari ini | `YYYY-MM-DD` |
| `status` | enum | — | Ya | `active` | `active`, `dihentikan` |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 saat status berubah |

**Aturan:**

- Kombinasi `(studentId, courseId)` **unik**; penugasan ganda tidak dibuat.
- Satu peserta boleh memiliki banyak enrollment (banyak kursus).
- Satu kursus boleh memiliki banyak enrollment (banyak peserta).
- Menghapus enrollment juga menghapus nilai, sertifikat, dan progres peserta pada kursus tersebut.
- Status `dihentikan` menghentikan akses peserta, tetapi data nilai/sertifikat tidak otomatis dihapus.

---

### 5.4 MODULES — `elips_modules`

Modul pembelajaran di dalam kursus.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik modul |
| `courseId` | number | FK → `courses.id` | Ya | — | Kursus pemilik modul |
| `instructorId` | number | FK → `users.id` | Tidak | dari kursus | Denormalisasi pemilik konten; disinkronkan dengan `course.instructorId` |
| `title` | string | — | Ya | — | Judul modul |
| `description` | text | — | Tidak | `''` | Deskripsi modul |
| `order` | number | — | Ya | `max(order)+1` | Urutan modul di dalam kursus, mulai dari 1 |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 |

**Aturan:**

- Instruktur hanya dapat mengelola modul pada kursus yang diampu.
- Menghapus modul menghapus seluruh materi di dalamnya beserta progres `completedLessons` terkait.
- `order` menentukan urutan tampilan modul.

---

### 5.5 LESSONS — `elips_lessons`

Materi/pelajaran di dalam modul.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik materi |
| `moduleId` | number | FK → `modules.id` | Ya | — | Modul pemilik materi |
| `courseId` | number | FK → `courses.id` | Ya | dari modul | Denormalisasi untuk query cepat per kursus |
| `instructorId` | number | FK → `users.id` | Tidak | dari modul | Denormalisasi pemilik konten |
| `title` | string | — | Ya | — | Judul materi |
| `type` | enum | — | Ya | `text` | `text`, `video`, `file`, `link` |
| `content` | text | — | Bersyarat | — | HTML untuk `text` (boleh kosong); URL/nama file **wajib** untuk `video`, `file`, dan `link` |
| `order` | number | — | Ya | `max(order)+1` | Urutan materi di dalam modul |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 |

**Aturan tipe konten:**

| Tipe | Isi `content` | Cara ditampilkan |
|---|---|---|
| `text` | HTML materi | Dirender sebagai artikel |
| `video` | URL video/embed | Diubah menjadi embed atau tautan video |
| `file` | URL/nama file | Tautan unduh |
| `link` | URL eksternal | Tautan keluar |

**Aturan relasi:**

- `moduleId` harus milik `courseId` yang sama.
- Memindahkan materi ke modul lain hanya diizinkan dalam kursus yang sama.
- Menghapus materi menghapus progres penyelesaian materi tersebut.
- Materi hanya dapat diakses peserta jika enrollment `active`, kursus `published`, dan urutan sebelumnya sudah selesai.

---

### 5.6 GRADES — `elips_grades`

Nilai akhir peserta per kursus.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik nilai |
| `enrollmentId` | number | FK → `enrollments.id` | Ya | — | Penugasan yang dinilai |
| `courseId` | number | FK → `courses.id` | Ya | — | Denormalisasi untuk query |
| `studentId` | number | FK → `users.id` | Ya | — | Denormalisasi peserta |
| `instructorId` | number | FK → `users.id` | Tidak | `course.instructorId` | Instruktur pemberi nilai |
| `score` | number | — | Ya | — | Rentang 0–100 |
| `grade` | enum | — | Ya | turunan skor | `A`, `A-`, `B+`, `B`, `B-`, `C+`, `C`, `D`, `E` |
| `note` | text | — | Tidak | `''` | Catatan instruktur |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 |

**Konversi grade:**

| Skor | Grade |
|---|---|
| ≥ 90 | A |
| 80–89 | A- |
| 75–79 | B+ |
| 70–74 | B |
| 65–69 | B- |
| 60–64 | C+ |
| 55–59 | C |
| 50–54 | D |
| < 50 | E |

**Aturan:**

- Hanya boleh menilai jika enrollment `(studentId, courseId)` ada.
- Instruktur tidak dapat menilai kursus yang bukan miliknya.
- Kombinasi `(studentId, courseId)` dianggap unik; penyimpanan ulang memperbarui nilai yang sama.
- Menyimpan nilai otomatis membuat/memperbarui e-sertifikat **Draft**.
- Jika sertifikat sudah `publish`, perubahan nilai memperbarui data sertifikat tetapi **tidak** mengembalikan status ke Draft.

---

### 5.7 CERTIFICATES — `elips_certificates`

E-sertifikat peserta per kursus.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik sertifikat |
| `studentId` | number | FK → `users.id` | Ya | — | Peserta penerima |
| `courseId` | number | FK → `courses.id` | Ya | — | Kursus terkait |
| `enrollmentId` | number | FK → `enrollments.id` | Tidak | dicari dari pasangan | Nullable untuk data lama |
| `certificateNumber` | string | UK | Ya | dibuat sistem | Draft: `CERT-DRAFT-xxxxxx-XXX`; Publish: `CERT-YYYY-XXXXXX` |
| `status` | enum | — | Ya | `draft` | `draft`, `publish` |
| `grade` | string | — | Ya | dari nilai | **Snapshot** grade saat sertifikat dibuat/diperbarui |
| `score` | number | — | Ya | dari nilai | **Snapshot** skor |
| `issuedByInstructorAt` | datetime | — | Ya | sekarang | Waktu instruktur menyimpan nilai |
| `approvedByAdminId` | number | FK → `users.id` | Tidak | `null` | Admin yang menyetujui |
| `approvedAt` | datetime | — | Tidak | `null` | Waktu persetujuan |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 |

**Field lama yang masih dibaca (kompatibilitas):** `courseName`, `issuedAt`.
**Normalisasi status:** `published`, `Publish`, `terbit`, `approved` dianggap `publish`; `menunggu`, `pending` dianggap `draft`; status kosong dianggap `publish` untuk data lama.

**Aturan:**

- Satu sertifikat per `(studentId, courseId)`.
- Sertifikat hanya dibuat jika peserta sudah memiliki nilai dan enrollment.
- Sertifikat baru selalu `draft`.
- Hanya Admin yang dapat mengubah `draft` → `publish`.
- Sertifikat `publish` bersifat final dari sisi status; nilai masih dapat diperbarui.
- Saat `publish`, nomor Draft diganti nomor final, `approvedByAdminId` dan `approvedAt` diisi.
- Sertifikat `draft` tidak boleh tampil di halaman peserta dan tidak dapat diverifikasi.
- Menghapus peserta/enrollment/kursus ikut menghapus sertifikat terkait.

**Lifecycle status:**

```text
(nilai disimpan) ──► draft ── approve Admin ──► publish
                         ▲
                         └── nilai diperbarui (tetap draft)
publish ── nilai diperbarui ──► tetap publish (data grade/score diperbarui)
```

---

### 5.8 COMPLETED_LESSONS — `elips_completedLessons`

Mencatat materi yang sudah diselesaikan peserta. **Tidak memiliki `id`**; identitasnya adalah kombinasi tiga field.

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `studentId` | number | PK komposit, FK → `users.id` | Ya | Peserta |
| `courseId` | number | PK komposit, FK → `courses.id` | Ya | Kursus (denormalisasi) |
| `lessonId` | number | PK komposit, FK → `lessons.id` | Ya | Materi yang diselesaikan |
| `completedAt` | datetime | — | Ya | ISO 8601 saat ditandai selesai |

**Aturan:**

- Kombinasi `(studentId, courseId, lessonId)` unik; penandaan ulang tidak menambah baris.
- Penulisan dilakukan dari halaman `learning.html` setelah validasi enrollment, status kursus, dan urutan materi.
- Menghapus materi/modul/kursus/enrollment/peserta menghapus baris progres terkait.
- Progres dihitung sebagai `jumlah baris selesai / jumlah materi kursus`.

---

### 5.9 AUDIT_LOGS — `elips_auditLogs`

Jejak audit perubahan data penting. Bersifat **append-only** dari sudut pandang aplikasi (hanya bisa dibersihkan seluruhnya dari halaman Admin).

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `id` | number | PK | Ya | ID unik log |
| `action` | enum | — | Ya | Lihat daftar aksi di bawah |
| `entity` | enum | — | Ya | `course`, `grade`, `certificate`, `user`, `enrollment`, `module`, `lesson` |
| `entityId` | number | FK polimorfik | Tidak | ID entitas yang berubah; `null` jika tidak ada |
| `entityLabel` | string | — | Tidak | Label entitas saat kejadian (snapshot) |
| `actorId` | number | FK → `users.id` | Tidak | `null` berarti Sistem |
| `actorName` | string | — | Ya | Snapshot nama aktor |
| `actorRole` | string | — | Ya | `admin`, `instruktur`, `peserta`, atau `sistem` |
| `detail` | text | — | Tidak | Deskripsi perubahan |
| `createdAt` | datetime | — | Ya | ISO 8601 |

**Enum aksi:**

| Aksi | Label UI | Dipicu oleh |
|---|---|---|
| `create` | Tambah | Buat user, kursus, enrollment, modul, materi |
| `update` | Ubah | Ubah user, kursus, modul, materi |
| `delete` | Hapus | Hapus user, kursus, modul, materi, enrollment |
| `publish` | Publish | Kursus dipublikasikan |
| `unpublish` | Draft | Kursus dikembalikan ke Draft |
| `grade` | Nilai | Instruktur menyimpan/memperbarui nilai |
| `submit` | Ajukan Draft | Sertifikat Draft dibuat/diajukan |
| `approve` | Setujui | Admin menyetujui sertifikat |
| `assign` | Penugasan | Penugasan instruktur ke kursus |
| `status` | Ubah Status | Perubahan status enrollment |

**Aturan:**

- `actorId` diresolusikan dengan prioritas: `actorId` eksplisit → user sesi → `null` (Sistem).
- Maksimal **1000 entri**; entri terbaru disimpan di depan.
- Log bersifat historis: penghapusan user/kursus tidak menghapus log, sehingga `actorId`/`entityId` dapat menjadi referensi yatim.
- `entityId` adalah **polimorfik**, bukan FK fisik ke satu tabel.

---

### 5.10 NOTIFICATIONS — `elips_notifications`

Outbox notifikasi email **simulasi**. Tidak ada pengiriman email nyata.

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `id` | number | PK | Ya | ID unik notifikasi |
| `type` | enum | — | Ya | `course_published`, `certificate_published` |
| `channel` | string | — | Ya | Selalu `email` |
| `simulated` | boolean | — | Ya | Selalu `true` |
| `status` | string | — | Ya | Selalu `sent` (status outbox simulasi) |
| `recipientId` | number | FK → `users.id` | Tidak | Nullable untuk data lama |
| `recipientName` | string | — | Ya | Snapshot nama penerima |
| `recipientEmail` | string | — | Ya | Email tujuan; harus valid saat dibuat |
| `recipientRole` | string | — | Ya | `admin`, `instruktur`, `peserta` |
| `subject` | string | — | Ya | Subjek email |
| `body` | text | — | Ya | Isi email |
| `entity` | string | — | Tidak | `course` atau `certificate` |
| `entityId` | number | FK polimorfik | Tidak | ID kursus/sertifikat |
| `entityLabel` | string | — | Tidak | Label entitas (snapshot) |
| `createdAt` | datetime | — | Ya | ISO 8601 |

**Aturan:**

- Dibuat saat kursus dipublikasikan:
  - penerima = seluruh peserta dengan enrollment `active` dan email valid;
  - ditambah instruktur aktif pengampu kursus.
- Dibuat saat sertifikat disetujui:
  - penerima = peserta pemilik sertifikat (jika email valid).
- Maksimal **1000 entri**; terbaru di depan.
- `recipientEmail` divalidasi dengan pola email sederhana.
- `recipientId`/`entityId` bersifat historis dan dapat menjadi referensi yatim setelah data dihapus.

---

### 5.11 SESSION — `elips_session`

Bukan koleksi, melainkan **satu objek** yang merepresentasikan sesi login aktif di browser.

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `userId` | number | FK → `users.id` | Ya | User yang sedang login |
| `loggedInAt` | datetime | — | Tidak | ISO 8601 saat login; sesi lama dapat tidak memilikinya |

**Aturan:**

- Ditulis oleh `setCurrentUser(userId)` saat login/registrasi dalam bentuk `{ userId, loggedInAt }`.
- `getSessionUser()` juga menerima sesi lama yang hanya berisi angka (`userId`) langsung.
- Dihapus oleh `logout()`.
- `getSessionUser()` mengembalikan `null` jika `userId` tidak lagi ditemukan.
- `getCurrentUser()` memiliki fallback demo berdasarkan path halaman (admin → user 1, instruktur → instruktur pertama, peserta → user 5) ketika tidak ada sesi.
- Sesi tidak otomatis dihapus ketika user dihapus; ini dapat menghasilkan sesi yang menunjuk user tidak valid.

---

## 6. Relasi Lengkap

| No | Relasi | Kardinalitas | FK | Perilaku saat data induk dihapus | Catatan |
|---|---|---|---|---|---|
| R1 | `USERS` (instruktur) → `COURSES` | 1 — N | `courses.instructorId` | Instruktur dihapus/dilepas: `instructorId=null`, `instructorName=''`, kursus Publish → Draft | Satu kursus hanya satu instruktur; satu instruktur banyak kursus |
| R2 | `USERS` (peserta) → `ENROLLMENTS` | 1 — N | `enrollments.studentId` | Peserta dihapus: seluruh enrollment dihapus | Hanya untuk role `peserta` |
| R3 | `COURSES` → `ENROLLMENTS` | 1 — N | `enrollments.courseId` | Kursus dihapus: seluruh enrollment dihapus | Relasi N—N peserta ↔ kursus |
| R4 | `COURSES` → `MODULES` | 1 — N | `modules.courseId` | Kursus dihapus: seluruh modul dihapus | Modul tidak dapat berdiri sendiri |
| R5 | `MODULES` → `LESSONS` | 1 — N | `lessons.moduleId` | Modul dihapus: seluruh materi + progresnya dihapus | Satu materi hanya milik satu modul |
| R6 | `COURSES` → `LESSONS` | 1 — N | `lessons.courseId` | Kursus dihapus: seluruh materi dihapus | Denormalisasi dari modul |
| R7 | `ENROLLMENTS` → `GRADES` | 1 — 0..1 | `grades.enrollmentId` | Enrollment dihapus: nilai dihapus | Satu peserta satu nilai per kursus |
| R8 | `ENROLLMENTS` → `CERTIFICATES` | 1 — 0..1 | `certificates.enrollmentId` | Enrollment dihapus: sertifikat dihapus | Satu peserta satu sertifikat per kursus |
| R9 | `USERS` (peserta) → `GRADES` | 1 — N | `grades.studentId` | Peserta dihapus: seluruh nilai dihapus | Denormalisasi untuk query |
| R10 | `USERS` (instruktur) → `GRADES` | 1 — N | `grades.instructorId` | Instruktur dihapus: nilai tetap ada | Referensi dapat menjadi yatim |
| R11 | `USERS` (peserta) → `CERTIFICATES` | 1 — N | `certificates.studentId` | Peserta dihapus: seluruh sertifikat dihapus | Denormalisasi untuk query |
| R12 | `USERS` (admin) → `CERTIFICATES` | 1 — N | `certificates.approvedByAdminId` | Admin dihapus: sertifikat tetap ada | Riwayat approval dipertahankan |
| R13 | `USERS` (peserta) → `COMPLETED_LESSONS` | 1 — N | `completedLessons.studentId` | Peserta dihapus: seluruh progres dihapus | — |
| R14 | `LESSONS` → `COMPLETED_LESSONS` | 1 — N | `completedLessons.lessonId` | Materi/modul dihapus: progres terkait dihapus | Progres per materi |
| R15 | `USERS` → `AUDIT_LOGS` | 1 — N | `auditLogs.actorId` | User dihapus: log tetap ada | Snapshot aktor disimpan |
| R16 | `USERS` → `NOTIFICATIONS` | 1 — N | `notifications.recipientId` | User dihapus: notifikasi tetap ada | Snapshot penerima disimpan |
| R17 | `COURSES` / `CERTIFICATES` → `NOTIFICATIONS` | 1 — N | `notifications.entity` + `entityId` | Entitas dihapus: notifikasi tetap ada | Referensi polimorfik, bukan FK fisik |
| R18 | `USERS` → `SESSION` | 1 — 0..1 | `session.userId` | Tidak ada cascade | Sesi dihapus saat logout |

### 6.1 Relasi Banyak-ke-Banyak

| Relasi | Tabel Penghubung | Atribut Penghubung | Keunikan |
|---|---|---|---|
| Peserta ↔ Kursus | `ENROLLMENTS` | `status`, `enrolledAt`, `enrolledBy` | `(studentId, courseId)` |
| Peserta ↔ Materi (progres) | `COMPLETED_LESSONS` | `completedAt` | `(studentId, courseId, lessonId)` |

---

## 7. Aturan Integritas dan Validasi

### 7.1 Integritas Referensi

| Aturan | Penegak |
|---|---|
| Email pengguna unik | `crud-users.js` |
| Role hanya `admin`/`instruktur`/`peserta` | `core.isValidRole` + `crud-users.js` |
| Status user hanya `aktif`/`nonaktif` | `core.isValidUserStatus` |
| Kursus wajib punya instruktur aktif saat dibuat/diubah | `crud-courses.js` + `core.isActiveInstructor` |
| Publikasi kursus wajib punya instruktur aktif, modul, dan materi | `crud-courses.js` |
| Enrollment hanya untuk peserta aktif | `crud-enrollments.js` |
| Enrollment unik per peserta+kursus | `crud-enrollments.js` |
| Nilai hanya untuk enrollment yang valid | `crud-grades.js` |
| Skor 0–100 | `crud-grades.js` |
| Instruktur hanya mengelola/menilai kursus sendiri | `crud-content.js`, `crud-grades.js` |
| Sertifikat hanya untuk peserta bernilai | `crud-grades.js` |
| Approve hanya untuk sertifikat Draft | `crud-grades.js` |
| Sertifikat Publish yang terlihat peserta | `data.js` + filter halaman peserta |

### 7.2 Integritas yang Tidak Ditegakkan Database

Karena tidak ada database relasional, hal berikut hanya dijaga oleh kode dan dapat rusak jika `localStorage` diedit manual:

- Referensi `instructorId`, `courseId`, `moduleId`, `lessonId`, `studentId`, `enrollmentId` tidak memiliki constraint FK.
- Tidak ada `ON DELETE CASCADE` otomatis; cascade dilakukan manual di fungsi CRUD.
- Tidak ada transaksi; operasi multi-koleksi dapat berhenti di tengah jika terjadi error.
- ID dapat dipakai ulang setelah record ber-ID terbesar dihapus.
- Tipe data tidak dipaksa saat data dibaca dari `localStorage`.

### 7.3 Aturan Akses Peserta

Peserta dapat membuka materi jika **semua** kondisi terpenuhi:

1. Ada sesi peserta yang valid.
2. Enrollment `(studentId, courseId)` berstatus `active`.
3. Kursus berstatus `published`.
4. Materi berada pada urutan yang sudah terbuka; materi berikutnya terkunci sampai materi sebelumnya selesai.

Sertifikat hanya terlihat jika `status = publish` dan milik peserta yang sedang login.

---

## 8. Status Lifecycle

| Entitas | Field | Nilai | Transisi yang Diizinkan |
|---|---|---|---|
| Pengguna | `status` | `aktif`, `nonaktif` | aktif ↔ nonaktif |
| Pengguna | `role` | `admin`, `instruktur`, `peserta` | Dapat diubah; perubahan dari instruktur melepas kursus |
| Kursus | `status` | `draft`, `published` | draft → published (validasi), published → draft |
| Enrollment | `status` | `active`, `dihentikan` | active ↔ dihentikan |
| Sertifikat | `status` | `draft`, `publish` | draft → publish; publish tidak kembali ke draft |
| Nilai | `grade` | A … E | Dihitung ulang setiap skor disimpan |
| Notifikasi | `status` | `sent` | Tidak berubah (simulasi) |

---

## 9. Denormalisasi dan Snapshot

| Lokasi | Data | Sumber | Sinkronisasi |
|---|---|---|---|
| `courses.instructorName` | Nama instruktur | `users.name` | `syncInstructorNameOnCourses` saat user diubah |
| `courses.modules` | Jumlah modul | `modules` | `syncCourseCounters` |
| `courses.lessons` | Jumlah materi | `lessons` | `syncCourseCounters` |
| `courses.totalLessons` | Duplikat jumlah materi | `courses.lessons` | `syncCourseCounters` |
| `modules.instructorId` | Pemilik konten | `courses.instructorId` | `syncAllContentOwners` |
| `lessons.instructorId` | Pemilik konten | `courses.instructorId` | `syncAllContentOwners` |
| `lessons.courseId` | Kursus materi | `modules.courseId` | Diset saat create/update |
| `grades.studentId`, `grades.courseId` | Pasangan peserta+kursus | `enrollments` | Diset saat nilai disimpan |
| `grades.enrollmentId` | Enrollment | `enrollments` | Diset saat nilai disimpan |
| `certificates.studentId`, `certificates.courseId` | Pasangan peserta+kursus | `enrollments` | Diset saat sertifikat dibuat |
| `certificates.enrollmentId` | Enrollment | `enrollments` | Diset saat sertifikat dibuat |
| `certificates.grade`, `certificates.score` | Nilai saat terbit | `grades` | Snapshot; diperbarui saat nilai disimpan ulang |
| `auditLogs.actorName`, `actorRole`, `entityLabel` | Identitas saat kejadian | `users`/entitas | Snapshot historis |
| `notifications.recipientName`, `recipientEmail`, `recipientRole`, `entityLabel` | Data saat notifikasi dibuat | `users`/entitas | Snapshot historis |

**Tujuan:**

- Denormalisasi mempercepat pembacaan tanpa join.
- Snapshot menjaga histori tetap akurat meskipun nama pengguna atau judul kursus berubah.

---

## 10. Matriks Cascade

| Operasi | COURSES | ENROLLMENTS | MODULES | LESSONS | GRADES | CERTIFICATES | COMPLETED_LESSONS | AUDIT_LOGS | NOTIFICATIONS | SESSION |
|---|---|---|---|---|---|---|---|---|---|---|
| Hapus **User peserta** | — | Hapus milik peserta | — | — | Hapus milik peserta | Hapus milik peserta | Hapus milik peserta | Tetap | Tetap | Tetap |
| Hapus **User instruktur** | Lepas & Draft | — | Owner di-reset | Owner di-reset | Tetap | Tetap | — | Tetap | Tetap | Tetap |
| Hapus **User admin** | — | — | — | — | — | Tetap | — | Tetap | Tetap | Tetap |
| **Nonaktifkan User instruktur** | Publish → Draft (owner tetap) | — | — | — | — | — | — | Tambah log | Tetap | Tetap |
| Hapus **Kursus** | Hapus | Hapus | Hapus | Hapus | Hapus | Hapus | Hapus | Tetap | Tetap | — |
| Hapus **Enrollment** | — | Hapus | — | — | Hapus pasangan | Hapus pasangan | Hapus pasangan | Tetap | Tetap | — |
| Hapus **Modul** | Counter update | — | Hapus | Hapus isi modul | Tetap | Tetap | Hapus milik materi | Tetap | Tetap | — |
| Hapus **Materi** | Counter update | — | — | Hapus | Tetap | Tetap | Hapus milik materi | Tetap | Tetap | — |
| **Approve sertifikat** | — | — | — | — | — | Draft → Publish | — | Tambah log | Tambah notifikasi | — |
| **Publikasi kursus** | Draft → Publish | — | — | — | — | — | — | Tambah log | Tambah notifikasi | — |

Keterangan:

- “Tetap” berarti record tidak dihapus dan dapat menyimpan referensi yang sudah tidak valid (yatim).
- `AUDIT_LOGS` dan `NOTIFICATIONS` sengaja tidak di-cascade agar histori operasional tetap utuh.

---

## 11. Contoh Data

### 11.1 Pengguna

```json
{
  "id": 5,
  "name": "Budi Santoso",
  "email": "budi.student@example.com",
  "phone": "081234567894",
  "role": "peserta",
  "status": "aktif",
  "createdAt": "2026-10-01"
}
```

### 11.2 Kursus

```json
{
  "id": 1,
  "title": "Pelatihan Manajemen Dasar",
  "category": "Pelatihan",
  "description": "Pelatihan konsep dasar manajemen.",
  "instructorId": 2,
  "instructorName": "Ahmad Fauzi, M.M.",
  "modules": 8,
  "lessons": 24,
  "totalLessons": 24,
  "status": "published",
  "createdAt": "2026-09-20"
}
```

### 11.3 Enrollment

```json
{
  "id": 1,
  "studentId": 5,
  "courseId": 1,
  "enrolledBy": "admin",
  "enrolledAt": "2026-10-01",
  "status": "active"
}
```

### 11.4 Modul dan Materi

```json
{
  "id": 1,
  "courseId": 1,
  "instructorId": 2,
  "title": "Modul 1: Pengantar Manajemen",
  "description": "Materi pengantar manajemen.",
  "order": 1,
  "createdAt": "2026-09-20T00:00:00.000Z"
}
```

```json
{
  "id": 1,
  "moduleId": 1,
  "courseId": 1,
  "instructorId": 2,
  "title": "1.1 Pengantar Manajemen - Konsep Dasar",
  "type": "text",
  "content": "<p>Materi pengantar manajemen.</p>",
  "order": 1,
  "createdAt": "2026-09-20T00:00:00.000Z"
}
```

### 11.5 Nilai

```json
{
  "id": 1,
  "enrollmentId": 1,
  "courseId": 1,
  "studentId": 5,
  "instructorId": 2,
  "score": 88,
  "grade": "A-",
  "note": "Pemahaman materi baik.",
  "createdAt": "2026-11-03T07:25:19.822Z",
  "updatedAt": "2026-11-03T07:25:19.822Z"
}
```

### 11.6 Sertifikat

```json
{
  "id": 1,
  "studentId": 5,
  "courseId": 1,
  "enrollmentId": 1,
  "certificateNumber": "CERT-2026-86832A",
  "status": "publish",
  "grade": "A-",
  "score": 88,
  "issuedByInstructorAt": "2026-11-03T07:25:19.822Z",
  "approvedByAdminId": 1,
  "approvedAt": "2026-11-03T07:25:19.823Z",
  "createdAt": "2026-11-03T07:25:19.822Z"
}
```

### 11.7 Progres Materi

```json
{
  "studentId": 5,
  "courseId": 1,
  "lessonId": 1,
  "completedAt": "2026-11-03T08:00:00.000Z"
}
```

### 11.8 Audit Log

```json
{
  "id": 12,
  "action": "approve",
  "entity": "certificate",
  "entityId": 1,
  "entityLabel": "CERT-2026-86832A",
  "actorId": 1,
  "actorName": "Administrator",
  "actorRole": "admin",
  "detail": "Menyetujui e-sertifikat Draft menjadi Publish untuk Budi Santoso.",
  "createdAt": "2026-11-03T07:25:19.823Z"
}
```

### 11.9 Notifikasi

```json
{
  "id": 3,
  "type": "certificate_published",
  "channel": "email",
  "simulated": true,
  "status": "sent",
  "recipientId": 5,
  "recipientName": "Budi Santoso",
  "recipientEmail": "budi.student@example.com",
  "recipientRole": "peserta",
  "subject": "E-sertifikat \"Pelatihan Manajemen Dasar\" telah terbit",
  "body": "Halo Budi Santoso, ...",
  "entity": "certificate",
  "entityId": 1,
  "entityLabel": "CERT-2026-86832A",
  "createdAt": "2026-11-03T07:25:19.824Z"
}
```

### 11.10 Sesi

```json
{
  "userId": 5,
  "loggedInAt": "2026-11-03T08:00:00.000Z"
}
```

---

## 12. Pola Query dan Index Logis

| Kebutuhan | Sumber | Pola Pencarian |
|---|---|---|
| Login | `USERS` | Cari `email` (case-insensitive), cek `status` |
| Daftar peserta | `USERS` | Filter `role = peserta` |
| Instruktur aktif | `USERS` | Filter `role = instruktur` dan `status = aktif` |
| Kursus publik | `COURSES` | Filter `status = published` |
| Kursus per instruktur | `COURSES` | Filter `instructorId` |
| Kursus peserta | `ENROLLMENTS` | Filter `studentId`, lalu ambil `COURSES` |
| Peserta kursus | `ENROLLMENTS` | Filter `courseId`, lalu ambil `USERS` |
| Struktur kursus | `MODULES` + `LESSONS` | Filter `courseId`, urutkan `order` |
| Materi per modul | `LESSONS` | Filter `moduleId`, urutkan `order` |
| Nilai peserta | `GRADES` | Kombinasi `studentId` + `courseId` |
| Sertifikat peserta | `CERTIFICATES` | Kombinasi `studentId` + `courseId` |
| Verifikasi sertifikat | `CERTIFICATES` | Cari `certificateNumber` (uppercase), cek `publish` |
| Progres | `COMPLETED_LESSONS` | Kombinasi `studentId` + `courseId` + `lessonId` |
| Audit | `AUDIT_LOGS` | Urut `createdAt` desc, filter aksi/entitas/aktor/tanggal |
| Notifikasi | `NOTIFICATIONS` | Urut `createdAt` desc, filter tipe/penerima/tanggal |

### 12.1 Index yang Disarankan untuk Versi Backend

- `users(email)` unik
- `courses(instructorId, status)`
- `enrollments(studentId, courseId)` unik
- `modules(courseId, order)`
- `lessons(moduleId, order)`
- `lessons(courseId)`
- `grades(studentId, courseId)` unik
- `grades(enrollmentId)` unik
- `certificates(studentId, courseId)` unik
- `certificates(certificateNumber)` unik
- `certificates(status)`
- `completed_lessons(studentId, courseId, lessonId)` unik
- `audit_logs(createdAt desc, action, entity, actorId)`
- `notifications(createdAt desc, type, recipientId)`

---

## 13. Padanan Skema Relasional (Saran Produksi)

Jika aplikasi dimigrasikan ke database relasional, gunakan pemetaan berikut:

| Entitas Logis | Tabel | Primary Key | Foreign Key / Unique |
|---|---|---|---|
| `USERS` | `users` | `id` | `email` UNIQUE |
| `COURSES` | `courses` | `id` | `instructor_id` → `users.id` |
| `ENROLLMENTS` | `enrollments` | `id` | `student_id` → `users.id`; `course_id` → `courses.id`; UNIQUE(`student_id`, `course_id`) |
| `MODULES` | `modules` | `id` | `course_id` → `courses.id` ON DELETE CASCADE |
| `LESSONS` | `lessons` | `id` | `module_id` → `modules.id` ON DELETE CASCADE; `course_id` → `courses.id` |
| `GRADES` | `grades` | `id` | `enrollment_id` → `enrollments.id` ON DELETE CASCADE; UNIQUE(`student_id`, `course_id`) |
| `CERTIFICATES` | `certificates` | `id` | `enrollment_id` → `enrollments.id` ON DELETE CASCADE; `student_id`, `course_id`, `approved_by_admin_id` → `users.id`/`courses.id`; UNIQUE(`student_id`, `course_id`), `certificate_number` UNIQUE |
| `COMPLETED_LESSONS` | `completed_lessons` | (`student_id`, `course_id`, `lesson_id`) | FK ke `users`, `courses`, `lessons` ON DELETE CASCADE |
| `AUDIT_LOGS` | `audit_logs` | `id` | `actor_id` → `users.id` ON DELETE SET NULL |
| `NOTIFICATIONS` | `notifications` | `id` | `recipient_id` → `users.id` ON DELETE SET NULL |
| `SESSION` | `sessions` | `user_id` atau `id` | `user_id` → `users.id` ON DELETE CASCADE |
| Denormalisasi | — | — | `courses.instructor_name`, counter, dan snapshot dapat dihapus atau dijadikan kolom cache |

**Catatan migrasi:**

- `entity` + `entityId` pada audit/notifikasi sebaiknya tetap polimorfik atau dipecah menjadi kolom nullable per entitas.
- `password` wajib diganti hash (bcrypt/argon2) dan login divalidasi di server.
- Tambahkan transaksi untuk operasi cascade multi-tabel.
- Tambahkan `created_at`/`updated_at` dengan tipe `timestamp` dan timezone yang konsisten.

---

## 14. Catatan Implementasi `localStorage`

| Aspek | Perilaku Saat Ini |
|---|---|
| Pembacaan | `ELIPS.read(key, fallback)` melakukan `JSON.parse`; error dikembalikan sebagai fallback |
| Penulisan | `ELIPS.write(key, value)` melakukan `JSON.stringify` |
| ID | `ELIPS.nextId(list)` = `max(id)+1` per koleksi |
| Normalisasi | `seed.js` menjalankan migrasi saat modul dimuat, termasuk normalisasi sertifikat |
| Batas log | Audit dan notifikasi maksimal 1000 entri, terbaru di depan |
| Isolasi | Data per browser/perangkat; tidak sinkron antar perangkat |
| Keamanan | Tidak ada enkripsi; password demo tersimpan di `localStorage` |
| Sinkronisasi tab | Halaman peserta menyegarkan tampilan saat event `storage` atau saat tab aktif kembali |

---

## 15. Ringkasan Relasi Inti

```text
USERS (instruktur) 1 ─── N COURSES
USERS (peserta)    1 ─── N ENROLLMENTS N ─── 1 COURSES
COURSES            1 ─── N MODULES    1 ─── N LESSONS
COURSES            1 ─── N LESSONS    (denormalisasi)
ENROLLMENTS        1 ─── 0..1 GRADES
ENROLLMENTS        1 ─── 0..1 CERTIFICATES
USERS (peserta)    1 ─── N GRADES / CERTIFICATES / COMPLETED_LESSONS
LESSONS            1 ─── N COMPLETED_LESSONS
USERS              1 ─── N AUDIT_LOGS / NOTIFICATIONS
USERS              1 ─── 0..1 SESSION
```

Dengan demikian, inti model data ELIPS LMS adalah:

- **Kursus** sebagai pusat konten, dimiliki satu **Instruktur**.
- **Enrollment** sebagai penghubung peserta ke kursus.
- **Nilai** dan **Sertifikat** sebagai hasil akhir pembelajaran per pasangan peserta–kursus.
- **CompletedLessons** sebagai bukti progres per materi.
- **AuditLogs** dan **Notifications** sebagai catatan operasional yang bersifat historis.
