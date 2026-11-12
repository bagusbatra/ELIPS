# ERD — ELIPS LMS

**Versi dokumen:** 4.0  
**Tanggal:** 2026-11-11  
**Status:** Dual-mode - skema fisik MySQL 8 v4.0 Phase 1-6 + absensi karyawan (role `karyawan`, unique key harian, modul absensi bersama, halaman `employee/attendance.html`) + fallback koleksi `localStorage`  
**Dokumen terkait:** `prd.md`, `log.md`, `api/schema.sql`

---

## 1. Ringkasan

ELIPS LMS adalah aplikasi Learning Management System dengan empat role: **Admin**, **Instruktur**, **Peserta**, dan **Karyawan**. Pada mode server, data disimpan di **MySQL 8** dengan 39 tabel (34 domain inti + 5 operasional/pribadi) dan foreign key fisik (lihat §13 dan §16). Pada mode fallback, data disimpan sebagai koleksi JSON di `localStorage` dengan model logis yang sama. Sesi login pada mode server disimpan di sesi PHP native; token **"ingat saya"** disimpan sebagai hash di tabel `user_devices`, bukan di `localStorage`.

Sejak v4.0, kursus dipisahkan dari penyelenggaraannya:

```text
Kursus → Versi Kurikulum → Modul → Materi
Kursus → Kelas/Angkatan → Sesi Pertemuan → Presensi
Kursus → Kelas/Angkatan → Enrollment → Progres Materi → Nilai → Sertifikat
Kursus → Kelas/Angkatan → Tugas/Submission dan Ujian/Hasil Ujian
Kursus → Kelas/Angkatan → Materi Tambahan Kelas
Cabang → Kelas/Angkatan → Sesi Pertemuan
Kelas → Skema Penilaian → Komponen → Nilai Komponen → Finalisasi → Koreksi
```

Relasi utama:

```text
Admin ── mengelola ──► Pengguna, Kategori, Kursus, Versi Kurikulum, Kelas, Sertifikat
Instruktur ── mengampu ──► Kelas ── memakai ──► Versi Kurikulum ── berisi ──► Modul ──► Materi
Instruktur ── menyusun ──► Sesi Pertemuan ── mencatat ──► Presensi
Instruktur ── mengatur ──► Pengaturan Akses Materi ── menaungi ──► Materi Tambahan Kelas
Instruktur ── membuat ──► Tugas & Ujian ── menghasilkan ──► Submission & Hasil Ujian
Instruktur ── menyusun ──► Skema Penilaian ── menghitung ──► Nilai Akhir ── finalisasi ──► Koreksi beralasan
Peserta ── terdaftar melalui ──► Enrollment (kelas) ──► Progres Materi ──► Nilai ──► Sertifikat
Peserta ── menyelesaikan ──► ClassLessonProgress / ClassMaterialProgress (dan CompletedLesson legacy)
Semua aktivitas penting ── dicatat ──► AuditLog
Publikasi kursus / penerbitan sertifikat ── menghasilkan ──► Notification
```

### 1.1 Cakupan

| Termasuk ERD | Tidak termasuk |
|---|---|
| 34 tabel domain MySQL + 5 tabel operasional (audit, notifikasi, absensi staf, jadwal shift, perangkat pengguna) dan koleksi `localStorage` yang setara | Halaman statis dan tampilan UI |
| Relasi, kardinalitas, foreign key, dan aturan hapus | Data pengaturan halaman Admin (belum dipersistensi) |
| Enum status dan lifecycle (kursus, kurikulum, kelas, sesi, presensi, enrollment, akses materi, tugas, ujian, skema nilai, finalisasi, sertifikat) | Data laporan/statistik turunan (dihitung saat runtime) |
| Sesi PHP native (bukan tabel) | Integrasi email nyata (masih simulasi/outbox) |

Catatan: 34 tabel domain inti mencakup tabel akademik Phase 1-4, sembilan tabel Phase 5 (`assignments`, `assignment_files`, `assignment_rubrics`, `submissions`, `submission_files`, `submission_reviews`, `exams`, `exam_participants`, `exam_results`), dan lima tabel Phase 6 (`predicate_ranges`, `grade_schemes`, `grade_scheme_components`, `grade_component_scores`, `grade_corrections`). Lima tabel operasional/pribadi adalah `audit_logs`, `notifications`, `staff_attendances`, `staff_shifts`, dan `user_devices` (token "ingat saya" per perangkat), sehingga total 39 tabel. Phase 5 dan Phase 6 sebelumnya belum tercatat pada dokumen ini; angka 23 tabel adalah dokumentasi lama yang sudah dikoreksi.

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

- Pada **mode lokal/fallback** tidak ada constraint database fisik; integritas referensi dijaga oleh fungsi CRUD di `assets/js/crud-*.js`. Pada **mode server**, integritas ditegakkan oleh foreign key MySQL 8 (lihat §16.4) ditambah validasi aplikasi.
- ID setiap koleksi dibuat dengan `nextId()` = `max(id) + 1`, sehingga **ID tidak global** dan bisa dipakai ulang jika record dengan ID terbesar dihapus.
- Tipe data di bawah adalah tipe logis JSON: `number`, `string`, `boolean`, `date` (`YYYY-MM-DD`), `datetime` (ISO 8601), `enum`, `text`, `object`.

---

## 3. Diagram ERD

```mermaid
erDiagram
    CATEGORIES {
        number id PK
        string name
        string slug UK
        enum status
        datetime createdAt
        datetime updatedAt
    }

    USERS {
        number id PK
        string name
        string email UK
        string phone
        enum role
        enum status
        number sessionVersion
        string password
        date createdAt
        datetime updatedAt
    }

    USER_DEVICES {
        number id PK
        number userId FK
        string tokenHash UK
        string deviceLabel
        string userAgent
        string ipAddress
        datetime createdAt
        datetime lastUsedAt
        datetime expiresAt
        datetime revokedAt
        string revokedReason
    }

    BRANCHES {
        number id PK
        string code UK
        string name
        string address
        string city
        string phone
        string manager
        enum status
        text notes
        datetime createdAt
        datetime updatedAt
    }

    COURSES {
        number id PK
        string title
        string category
        number categoryId FK
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

    CURRICULUM_VERSIONS {
        number id PK
        number courseId FK
        string code UK
        string name
        text description
        enum status
        date effectiveDate
        text notes
        number createdBy FK
        number updatedBy FK
        datetime createdAt
        datetime updatedAt
    }

    CLASSES {
        number id PK
        string code UK
        string name
        number courseId FK
        number curriculumVersionId FK
        number branchId FK
        number instructorId FK
        date periodStart
        date periodEnd
        number quota
        string location
        string room
        string scheduleNote
        number plannedJp
        enum status
        boolean legacyImport
        text notes
        number createdBy FK
        number updatedBy FK
        datetime createdAt
        datetime updatedAt
    }

    CLASS_INSTRUCTOR_HISTORIES {
        number id PK
        number classId FK
        number instructorId FK
        date startedAt
        date endedAt
        text reason
        number actorId FK
        datetime createdAt
    }

    ENROLLMENTS {
        number id PK
        number studentId FK
        number courseId FK
        number classId FK
        string enrolledBy
        date enrolledAt
        enum status
        datetime createdAt
        datetime updatedAt
    }

    CLASS_SESSIONS {
        number id PK
        number classId FK
        number sessionNumber UK
        string title
        number moduleId FK
        date sessionDate
        string startTime
        string endTime
        string location
        string room
        number instructorId FK
        number plannedJp
        number actualJp
        enum status
        number rescheduledFromId FK
        text notes
        number createdBy FK
        number updatedBy FK
        datetime createdAt
        datetime updatedAt
    }

    ATTENDANCES {
        number id PK
        number sessionId FK
        number enrollmentId FK
        number studentId FK
        enum status
        number recordedBy FK
        datetime recordedAt
        text note
        datetime correctedAt
        text correctionReason
        datetime createdAt
        datetime updatedAt
    }

    ATTENDANCE_CORRECTIONS {
        number id PK
        number attendanceId FK
        number sessionId FK
        number enrollmentId FK
        string oldStatus
        string newStatus
        number actorId FK
        string actorName
        string actorRole
        text reason
        datetime correctedAt
        datetime createdAt
    }

    MODULES {
        number id PK
        number courseId FK
        number curriculumVersionId FK
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
        number curriculumVersionId FK
        number instructorId FK
        string title
        enum type
        text content
        number order
        datetime createdAt
        datetime updatedAt
    }

    PREDICATE_RANGES {
        number id PK
        string letter UK
        string label
        number minScore
        number maxScore
        number sortOrder
        boolean isActive
        datetime createdAt
        datetime updatedAt
    }

    GRADE_SCHEMES {
        number id PK
        number courseId FK
        number classId FK, UK
        string name
        enum status
        number passScore
        enum rounding
        text notes
        number createdBy FK
        number updatedBy FK
        datetime createdAt
        datetime updatedAt
    }

    GRADE_SCHEME_COMPONENTS {
        number id PK
        number schemeId FK
        enum type
        string name
        text description
        number weight
        enum aggregation
        number maxScore
        boolean isRequired
        number sortOrder
        datetime createdAt
        datetime updatedAt
    }

    GRADES {
        number id PK
        number enrollmentId FK, UK
        number schemeId FK
        number courseId FK
        number studentId FK
        number instructorId FK
        number score
        number finalScore
        string grade
        enum status
        longtext componentsSnapshot
        text note
        number finalizedBy FK
        datetime finalizedAt
        datetime computedAt
        datetime createdAt
        datetime updatedAt
    }

    GRADE_COMPONENT_SCORES {
        number id PK
        number componentId FK, UK
        number enrollmentId FK, UK
        number studentId FK
        number score
        enum status
        number assessorId FK
        datetime assessedAt
        text note
        datetime createdAt
        datetime updatedAt
    }

    GRADE_CORRECTIONS {
        number id PK
        number gradeId FK
        number enrollmentId FK
        number previousScore
        number newScore
        string previousLetter
        string newLetter
        text reason
        number actorId FK
        datetime createdAt
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

    CLASS_MATERIAL_SETTINGS {
        number id PK
        number classId FK, UK
        enum accessMode
        boolean requireInstructorApproval
        text notes
        number updatedBy FK
        datetime createdAt
        datetime updatedAt
    }

    CLASS_MATERIALS {
        number id PK
        number classId FK
        number lessonId FK
        number sessionId FK
        string title
        text description
        enum type
        text content
        string fileName
        string filePath
        number fileSize
        string mimeType
        enum accessLevel
        date availableFrom
        enum status
        text reason
        number sortOrder
        number createdBy FK
        number updatedBy FK
        datetime createdAt
        datetime updatedAt
    }

    CLASS_LESSON_PROGRESS {
        number id PK
        number classId FK
        number enrollmentId FK, UK
        number studentId FK
        number lessonId FK, UK
        number completedBy FK
        datetime completedAt
        datetime createdAt
    }

    CLASS_MATERIAL_PROGRESS {
        number id PK
        number classId FK
        number enrollmentId FK, UK
        number studentId FK
        number materialId FK, UK
        number completedBy FK
        datetime completedAt
        datetime createdAt
    }

    ASSIGNMENTS {
        number id PK
        number classId FK
        number curriculumVersionId FK
        number moduleId FK
        number lessonId FK
        string title
        longtext instructions
        enum type
        datetime openAt
        datetime dueAt
        number maxScore
        number weight
        boolean requireFile
        string allowedTypes
        number maxFileSize
        enum status
        number createdBy FK
        number updatedBy FK
        datetime createdAt
        datetime updatedAt
    }

    ASSIGNMENT_FILES {
        number id PK
        number assignmentId FK
        string fileName
        string filePath
        number fileSize
        string mimeType
        datetime createdAt
    }

    ASSIGNMENT_RUBRICS {
        number id PK
        number assignmentId FK
        string criterion
        text description
        number maxScore
        number sortOrder
        datetime createdAt
        datetime updatedAt
    }

    SUBMISSIONS {
        number id PK
        number assignmentId FK, UK
        number enrollmentId FK, UK
        number studentId FK
        enum status
        longtext textAnswer
        datetime submittedAt
        boolean isLate
        number score
        text feedback
        number reviewedBy FK
        datetime reviewedAt
        number revisionCount
        datetime createdAt
        datetime updatedAt
    }

    SUBMISSION_FILES {
        number id PK
        number submissionId FK
        string fileName
        string filePath
        number fileSize
        string mimeType
        datetime createdAt
    }

    SUBMISSION_REVIEWS {
        number id PK
        number submissionId FK
        number reviewerId FK
        enum action
        number score
        text note
        datetime createdAt
    }

    EXAMS {
        number id PK
        number classId FK
        number curriculumVersionId FK
        string title
        text description
        enum type
        date examDate
        string startTime
        number durationMinutes
        number maxScore
        number weight
        longtext instructions
        enum status
        number createdBy FK
        number updatedBy FK
        datetime createdAt
        datetime updatedAt
    }

    EXAM_PARTICIPANTS {
        number id PK
        number examId FK, UK
        number enrollmentId FK, UK
        number studentId FK
        enum status
        text note
        datetime createdAt
        datetime updatedAt
    }

    EXAM_RESULTS {
        number id PK
        number examId FK, UK
        number enrollmentId FK, UK
        number studentId FK
        number score
        enum status
        number gradedBy FK
        datetime gradedAt
        text note
        datetime createdAt
        datetime updatedAt
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

    STAFF_ATTENDANCES {
        number id PK
        number userId FK
        enum kind
        date attendanceDate
        datetime takenAt
        string timezone
        longtext photo
        string photoMime
        number latitude
        number longitude
        number accuracyM
        string locationLabel
        enum locationSource
        string ipAddress
        string userAgent
        string platform
        string language
        string screen
        number deviceMemory
        number hardwareConcurrency
        string connectionType
        boolean online
        text notes
        datetime createdAt
        datetime updatedAt
    }

    STAFF_SHIFTS {
        number id PK
        number userId FK
        date shiftDate
        enum shiftType
        time startTime
        time endTime
        string note
        number createdBy FK
        datetime createdAt
        datetime updatedAt
    }

    CATEGORIES |o--o{ COURSES : "mengklasifikasikan"
    USERS |o--o{ COURSES : "mengampu sebagai instruktur"
    COURSES ||--o{ CURRICULUM_VERSIONS : "memiliki versi"
    COURSES ||--o{ CLASSES : "diselenggarakan sebagai"
    CURRICULUM_VERSIONS ||--o{ CLASSES : "dipakai kelas"
    BRANCHES |o--o{ CLASSES : "menaungi kelas"
    CURRICULUM_VERSIONS ||--o{ MODULES : "berisi modul"
    CURRICULUM_VERSIONS ||--o{ LESSONS : "berisi materi"
    USERS |o--o{ CLASSES : "mengampu kelas"
    CLASSES ||--o{ CLASS_INSTRUCTOR_HISTORIES : "memiliki histori tentor"
    USERS |o--o{ CLASS_INSTRUCTOR_HISTORIES : "tercatat sebagai tentor"
    USERS ||--o{ ENROLLMENTS : "terdaftar sebagai peserta"
    COURSES ||--o{ ENROLLMENTS : "memiliki peserta"
    CLASSES ||--o{ ENROLLMENTS : "memiliki roster"
    CLASSES ||--o{ CLASS_SESSIONS : "memiliki sesi"
    MODULES |o--o{ CLASS_SESSIONS : "menjadi modul sesi"
    USERS |o--o{ CLASS_SESSIONS : "mengajar sesi"
    CLASS_SESSIONS |o--o{ CLASS_SESSIONS : "digantikan sesi"
    CLASS_SESSIONS ||--o{ ATTENDANCES : "memiliki presensi"
    ENROLLMENTS ||--o{ ATTENDANCES : "mencatat kehadiran"
    USERS ||--o{ ATTENDANCES : "hadir sebagai peserta"
    USERS |o--o{ ATTENDANCES : "mencatat presensi"
    ATTENDANCES ||--o{ ATTENDANCE_CORRECTIONS : "dikoreksi"
    CLASS_SESSIONS ||--o{ ATTENDANCE_CORRECTIONS : "memiliki koreksi"
    ENROLLMENTS ||--o{ ATTENDANCE_CORRECTIONS : "memiliki koreksi"
    USERS |o--o{ ATTENDANCE_CORRECTIONS : "melakukan koreksi"
    COURSES ||--o{ MODULES : "memiliki modul"
    MODULES ||--o{ LESSONS : "berisi materi"
    COURSES ||--o{ LESSONS : "memiliki materi denormalisasi"
    ENROLLMENTS ||--o| GRADES : "menghasilkan nilai"
    ENROLLMENTS ||--o| CERTIFICATES : "menghasilkan sertifikat"
    USERS ||--o{ GRADES : "menerima nilai sebagai peserta"
    USERS |o--o{ GRADES : "memberi nilai sebagai instruktur"
    COURSES ||--o{ GRADES : "memiliki nilai"
    GRADE_SCHEMES |o--o{ GRADES : "menjadi dasar nilai"
    USERS |o--o{ GRADES : "memfinalisasi nilai"
    CLASSES ||--o| GRADE_SCHEMES : "memiliki satu skema nilai"
    COURSES ||--o{ GRADE_SCHEMES : "memiliki skema nilai"
    USERS |o--o{ GRADE_SCHEMES : "membuat/memperbarui skema"
    GRADE_SCHEMES ||--o{ GRADE_SCHEME_COMPONENTS : "berisi komponen"
    GRADE_SCHEME_COMPONENTS ||--o{ GRADE_COMPONENT_SCORES : "dinilai per enrollment"
    ENROLLMENTS ||--o{ GRADE_COMPONENT_SCORES : "memiliki nilai komponen"
    USERS ||--o{ GRADE_COMPONENT_SCORES : "menerima nilai komponen"
    USERS |o--o{ GRADE_COMPONENT_SCORES : "menilai komponen"
    GRADES ||--o{ GRADE_CORRECTIONS : "dikoreksi"
    ENROLLMENTS ||--o{ GRADE_CORRECTIONS : "memiliki koreksi nilai"
    USERS |o--o{ GRADE_CORRECTIONS : "melakukan koreksi nilai"
    USERS ||--o{ CERTIFICATES : "menerima sertifikat"
    USERS |o--o{ CERTIFICATES : "menyetujui sertifikat sebagai admin"
    USERS ||--o{ COMPLETED_LESSONS : "menyelesaikan materi"
    LESSONS ||--o{ COMPLETED_LESSONS : "diselesaikan pada"
    CLASSES ||--o| CLASS_MATERIAL_SETTINGS : "memiliki pengaturan akses"
    USERS |o--o{ CLASS_MATERIAL_SETTINGS : "memperbarui pengaturan"
    CLASSES ||--o{ CLASS_MATERIALS : "memiliki materi tambahan"
    LESSONS |o--o{ CLASS_MATERIALS : "menjadi materi terkait"
    CLASS_SESSIONS |o--o{ CLASS_MATERIALS : "menjadi sesi terkait"
    USERS |o--o{ CLASS_MATERIALS : "membuat/memperbarui materi"
    ENROLLMENTS ||--o{ CLASS_LESSON_PROGRESS : "memiliki progres pelajaran"
    CLASSES ||--o{ CLASS_LESSON_PROGRESS : "mencatat progres kelas"
    USERS ||--o{ CLASS_LESSON_PROGRESS : "menyelesaikan pelajaran"
    LESSONS ||--o{ CLASS_LESSON_PROGRESS : "diselesaikan pada"
    USERS |o--o{ CLASS_LESSON_PROGRESS : "mengonfirmasi progres"
    ENROLLMENTS ||--o{ CLASS_MATERIAL_PROGRESS : "memiliki progres materi"
    CLASSES ||--o{ CLASS_MATERIAL_PROGRESS : "mencatat progres kelas"
    USERS ||--o{ CLASS_MATERIAL_PROGRESS : "menyelesaikan materi"
    CLASS_MATERIALS ||--o{ CLASS_MATERIAL_PROGRESS : "diselesaikan pada"
    USERS |o--o{ CLASS_MATERIAL_PROGRESS : "mengonfirmasi progres"
    USERS |o--o{ AUDIT_LOGS : "menjadi aktor"
    USERS |o--o{ NOTIFICATIONS : "menerima notifikasi"
    COURSES |o--o{ NOTIFICATIONS : "direferensikan notifikasi"
    CERTIFICATES |o--o{ NOTIFICATIONS : "direferensikan notifikasi"
    USERS ||--o| SESSION : "memiliki sesi aktif"
    USERS ||--o{ STAFF_ATTENDANCES : "mencatat absensi pribadi"
    USERS ||--o{ STAFF_SHIFTS : "dijadwalkan shift"
    USERS |o--o{ STAFF_SHIFTS : "membuat jadwal shift"
    USERS ||--o{ USER_DEVICES : "mengingat perangkat"
    CLASSES ||--o{ ASSIGNMENTS : "memiliki tugas"
    CURRICULUM_VERSIONS ||--o{ ASSIGNMENTS : "menjadi acuan tugas"
    MODULES |o--o{ ASSIGNMENTS : "menjadi modul tugas"
    LESSONS |o--o{ ASSIGNMENTS : "menjadi materi tugas"
    USERS |o--o{ ASSIGNMENTS : "membuat/memperbarui tugas"
    ASSIGNMENTS ||--o{ ASSIGNMENT_FILES : "memiliki lampiran"
    ASSIGNMENTS ||--o{ ASSIGNMENT_RUBRICS : "memiliki rubrik"
    ASSIGNMENTS ||--o{ SUBMISSIONS : "menerima submission"
    ENROLLMENTS ||--o{ SUBMISSIONS : "mengumpulkan submission"
    USERS ||--o{ SUBMISSIONS : "mengumpulkan sebagai peserta"
    USERS |o--o{ SUBMISSIONS : "meninjau submission"
    SUBMISSIONS ||--o{ SUBMISSION_FILES : "memiliki berkas jawaban"
    SUBMISSIONS ||--o{ SUBMISSION_REVIEWS : "memiliki riwayat review"
    USERS |o--o{ SUBMISSION_REVIEWS : "menjadi reviewer"
    CLASSES ||--o{ EXAMS : "memiliki ujian"
    CURRICULUM_VERSIONS ||--o{ EXAMS : "menjadi acuan ujian"
    USERS |o--o{ EXAMS : "membuat/memperbarui ujian"
    EXAMS ||--o{ EXAM_PARTICIPANTS : "memiliki peserta"
    ENROLLMENTS ||--o{ EXAM_PARTICIPANTS : "terdaftar sebagai peserta ujian"
    USERS ||--o{ EXAM_PARTICIPANTS : "mengikuti ujian"
    EXAMS ||--o{ EXAM_RESULTS : "memiliki hasil"
    ENROLLMENTS ||--o{ EXAM_RESULTS : "memperoleh hasil ujian"
    USERS ||--o{ EXAM_RESULTS : "menerima hasil ujian"
    USERS |o--o{ EXAM_RESULTS : "menilai hasil ujian"
```

> **Cara membaca:** `USERS |o--o{ CLASSES` berarti satu instruktur dapat mengampu nol atau banyak kelas, sedangkan satu kelas memiliki nol atau satu instruktur (boleh `null` saat draft atau setelah dilepas). Relasi peserta ke kelas bersifat **N—N** dan dipecah melalui `ENROLLMENTS`. Setiap kelas menunjuk tepat satu `curriculum_versions`; begitu kelas dibuat, versi tersebut berstatus `locked`. Sejak Phase 4, setiap kelas dapat memiliki satu pengaturan akses materi dan banyak materi tambahan; progres materi disimpan per enrollment agar peserta yang mengikuti beberapa kelas tidak saling menimpa. Sejak Phase 5, tugas (`assignments`) dan ujian (`exams`) terikat ke kelas dan versi kurikulum, dengan submission/review dan peserta/hasil ujian sebagai entitas turunan. Sejak Phase 6, satu kelas memiliki tepat satu skema penilaian (`grade_schemes`), skema berisi komponen berbobot, nilai komponen disimpan per enrollment, dan nilai akhir yang telah difinalisasi dapat dikoreksi melalui `grade_corrections` beralasan. Notasi `|o` di sisi `USERS` menandai FK yang boleh `null`; `||` menandai FK yang wajib terisi.

---

## 4. Entitas dan Storage Key (Mode Fallback `localStorage`)

Pada mode server, entitas yang sama disimpan di tabel MySQL bernama snake_case (mis. `USERS` → `users`). Pemetaan lengkap tabel, kolom, dan relasi fisik ada di §16; tabel berikut adalah key yang dipakai pada mode fallback.

| No | Entitas | Storage Key | Bentuk | Jumlah Relasi Utama |
|---|---|---|---|---|
| 1 | `CATEGORIES` | `elips_categories` | Array of object | Klasifikasi kursus |
| 2 | `USERS` | `elips_users` | Array of object | Pusat referensi role dan aktor |
| 3 | `BRANCHES` | `elips_branches` | Array of object | Cabang penyelenggara kelas dan konteks jadwal |
| 4 | `COURSES` | `elips_courses` | Array of object | Kategori, instruktur, versi, kelas, enrollment |
| 5 | `CURRICULUM_VERSIONS` | `elips_curriculumVersions` | Array of object | Milik kursus, berisi modul/materi, dipakai kelas |
| 6 | `MODULES` | `elips_modules` | Array of object | Milik versi kursus, berisi materi |
| 7 | `LESSONS` | `elips_lessons` | Array of object | Milik modul dan versi |
| 8 | `CLASSES` | `elips_classes` | Array of object | Kursus, versi kurikulum, cabang, tentor, roster, sesi |
| 9 | `CLASS_INSTRUCTOR_HISTORIES` | `elips_classInstructorHistories` | Array of object | Histori tentor per kelas |
| 10 | `ENROLLMENTS` | `elips_enrollments` | Array of object | Penghubung peserta ↔ kelas/kursus |
| 11 | `CLASS_SESSIONS` | `elips_classSessions` | Array of object | Kelas, modul, tentor, sesi pengganti |
| 12 | `ATTENDANCES` | `elips_attendances` | Array of object | Sesi, enrollment, peserta, pencatat |
| 13 | `ATTENDANCE_CORRECTIONS` | `elips_attendanceCorrections` | Array of object | Presensi, sesi, enrollment, aktor koreksi |
| 14 | `CLASS_MATERIAL_SETTINGS` | `elips_classMaterialSettings` | Array of object | Pengaturan akses materi per kelas |
| 15 | `CLASS_MATERIALS` | `elips_classMaterials` | Array of object | Materi tambahan per kelas |
| 16 | `CLASS_LESSON_PROGRESS` | `elips_classLessonProgress` | Array of object | Progres pelajaran per enrollment |
| 17 | `CLASS_MATERIAL_PROGRESS` | `elips_classMaterialProgress` | Array of object | Progres materi tambahan per enrollment |
| 18 | `ASSIGNMENTS` | `elips_assignments` | Array of object | Kelas, versi, modul/materi, pembuat |
| 19 | `ASSIGNMENT_FILES` | `elips_assignmentFiles` | Array of object | Lampiran tugas |
| 20 | `ASSIGNMENT_RUBRICS` | `elips_assignmentRubrics` | Array of object | Rubrik penilaian tugas |
| 21 | `SUBMISSIONS` | `elips_submissions` | Array of object | Tugas, enrollment, peserta, reviewer |
| 22 | `SUBMISSION_FILES` | `elips_submissionFiles` | Array of object | Berkas jawaban submission |
| 23 | `SUBMISSION_REVIEWS` | `elips_submissionReviews` | Array of object | Riwayat review submission |
| 24 | `EXAMS` | `elips_exams` | Array of object | Kelas, versi, pembuat |
| 25 | `EXAM_PARTICIPANTS` | `elips_examParticipants` | Array of object | Ujian, enrollment, peserta |
| 26 | `EXAM_RESULTS` | `elips_examResults` | Array of object | Ujian, enrollment, peserta, penilai |
| 27 | `PREDICATE_RANGES` | `elips_predicateRanges` | Array of object | Rentang nilai huruf global (tanpa relasi) |
| 28 | `GRADE_SCHEMES` | `elips_gradeSchemes` | Array of object | Kelas, kursus, pembuat/pembaru |
| 29 | `GRADE_SCHEME_COMPONENTS` | `elips_gradeSchemeComponents` | Array of object | Skema dan komponen berbobot |
| 30 | `GRADES` | `elips_grades` | Array of object | Enrollment/peserta/kursus, skema, finalisasi |
| 31 | `GRADE_COMPONENT_SCORES` | `elips_gradeComponentScores` | Array of object | Komponen, enrollment, peserta, penilai |
| 32 | `GRADE_CORRECTIONS` | `elips_gradeCorrections` | Array of object | Nilai, enrollment, aktor koreksi |
| 33 | `CERTIFICATES` | `elips_certificates` | Array of object | Milik peserta/kursus/enrollment |
| 34 | `COMPLETED_LESSONS` | `elips_completedLessons` | Array of object (tanpa `id` di mode lokal) | Progres peserta per materi (legacy) |
| 35 | `AUDIT_LOGS` | `elips_auditLogs` | Array of object | Aktor dan entitas polimorfik |
| 36 | `NOTIFICATIONS` | `elips_notifications` | Array of object | Penerima dan entitas polimorfik |
| 37 | `STAFF_ATTENDANCES` | `elips_staffAttendances` | Array of object | Absensi harian pribadi staf/admin: maks. satu `in` + satu `out` per hari, foto, lokasi, dan metadata perangkat |
| 38 | `STAFF_SHIFTS` | `elips_staffShifts` | Array of object | Jadwal shift per karyawan per tanggal: preset Pagi/Sore atau jam custom, satu shift per tanggal |
| 39 | `SESSION` | `elips_session` | Object tunggal | Sesi user yang sedang login (mode server: sesi PHP) |

Key lama yang dimigrasikan: `elips_enrolled` (self-enrollment) dipindahkan ke `elips_enrollments` oleh `seed.js`, lalu dihapus. Pada migrasi v3 → v4, `seed.js` membuat entri `elips_categories`/`elips_curriculumVersions`/`elips_classes`/`elips_branches` bila belum ada, mengisi `curriculumVersionId` pada modul/materi lama, lalu menambahkan sesi dan presensi demo untuk kelas migrasi. Kelas lama tanpa cabang diberi `branchId = null` agar tetap kompatibel. Sejak Phase 4, `seed.js` juga menormalisasi empat koleksi materi/progres; kelas yang belum memiliki pengaturan akses **tidak** dibuatkan baris, sehingga default implisit `open` tetap sama dengan mode server. Sejak Phase 5, `seed.js` menambahkan koleksi tugas/ujian beserta submission, review, peserta, dan hasil ujian demo. Sejak Phase 6, `seed.js` menambahkan sembilan predikat default, satu skema penilaian demo untuk kelas 1, tiga komponen (tugas 30%, ujian 40%, manual 30%), nilai komponen manual, dan koleksi koreksi kosong.

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
| `role` | enum | — | Ya | `peserta` | `admin`, `instruktur`, `peserta`, `karyawan` (absensi saja) |
| `status` | enum | — | Ya | `aktif` | `aktif`, `nonaktif` |
| `sessionVersion` | number | — | Ya | `0` di mode server | Dinaikkan saat login baru, ganti password, akun dinonaktifkan, atau reset perangkat; sesi PHP lama dengan versi berbeda otomatis tidak berlaku. Tidak dipakai mode lokal. |
| `password` | string | — | Ya | `elips123` untuk seed/akun lama | Mode lokal: disimpan apa adanya, minimal 8 karakter saat dibuat/diubah, diverifikasi `authenticateUser()`. Mode server: kolom `password_hash` di MySQL (bcrypt/argon2 via `password_hash()`), diverifikasi `password_verify()` di PHP, dan tidak pernah dikirim ke client |
| `createdAt` | date | — | Ya | hari ini | `YYYY-MM-DD` |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 saat diubah |

**Aturan:**

- `email` unik; `createUser` dan `updateUser` menolak duplikat.
- `password` wajib diisi saat `createUser` dan minimal 8 karakter; `seed.js` mengisi password demo untuk akun lama yang belum memilikinya.
- `authenticateUser(email, password)` menolak email tidak terdaftar, password kosong/salah, dan akun `nonaktif`.
- `id = 1` (Administrator utama) tidak dapat dihapus.
- Minimal satu akun `admin` harus tersisa.
- Menghapus peserta menghapus enrollment, nilai, sertifikat, dan progresnya.
- Menghapus instruktur melepas semua kursus yang diampu dan mengubah kursus Publish menjadi Draft.
- Mengubah role instruktur menjadi role lain melepas seluruh kursusnya.
- Menonaktifkan instruktur mengubah kursus Publish menjadi Draft, tetapi penugasan `instructorId` tetap tersimpan sampai diubah.

---

### 5.1a BRANCHES — `elips_branches`

Cabang/unit penyelenggara yang menjadi konteks kelas dan jadwal.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik cabang |
| `code` | string | UK | Ya | dibuat sistem (`CBG-...`) | Kode cabang unik |
| `name` | string | — | Ya | — | Nama cabang |
| `address` | string | — | Tidak | `''` | Alamat |
| `city` | string | — | Tidak | `''` | Kota |
| `phone` | string | — | Tidak | `''` | Telepon |
| `manager` | string | — | Tidak | `''` | Nama penanggung jawab |
| `status` | enum | — | Ya | `active` | `active`, `inactive` |
| `notes` | text | — | Tidak | `''` | Catatan |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | `null` | ISO 8601 saat berubah |

**Aturan:**

- `code` unik; kode kosong dibuat otomatis dengan prefiks `CBG-`.
- Cabang `inactive` tidak dapat dipilih saat membuat/mengubah kelas.
- `deleteBranch` ditolak bila masih ada kelas yang merujuk cabang tersebut.
- Perubahan nama/kode/kota disinkronkan ke field denormalisasi pada kelas dan sesi di mode lokal; mode server memakai JOIN saat snapshot.

---

### 5.2 COURSES — `elips_courses`

Menyimpan kursus beserta instruktur pengampu dan status publikasinya.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik kursus |
| `title` | string | — | Ya | — | Judul kursus |
| `category` | string | — | Ya | — | Nama kategori (denormalisasi dari `categories.name`), mis. `Pelatihan`, `Soft Skill`, `Administrasi`, `Reguler Class` |
| `categoryId` | number | FK → `categories.id` | Tidak | `null` | Kategori terstruktur; boleh `null` untuk data lama |
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

### 5.2a CATEGORIES — `elips_categories`

Kategori kursus terstruktur. `courses.categoryId` menunjuk ke sini, sedangkan `courses.category` menyimpan nama untuk kompatibilitas tampilan.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik kategori |
| `name` | string | — | Ya | — | Nama kategori |
| `slug` | string | UK | Ya | dari nama | Slug unik, huruf kecil dan tanda hubung |
| `status` | enum | — | Ya | `active` | `active`, `inactive` |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 |

**Aturan:**

- `slug` unik; kategori `inactive` tidak ditawarkan pada form kursus baru.
- Menghapus kategori tidak menghapus kursus; `courses.categoryId` menjadi `null` (FK `SET NULL`).

---

### 5.2b CURRICULUM_VERSIONS — `elips_curriculumVersions`

Versi kurikulum milik satu kursus. Modul dan materi selalu terhubung ke versi, bukan langsung ke kursus.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik versi |
| `courseId` | number | FK → `courses.id` | Ya | — | Kursus pemilik versi |
| `code` | string | UK per kursus | Ya | kode berikutnya | Kode versi unik per kursus, mis. `1.0`, `2.0` |
| `name` | string | — | Ya | `Kurikulum <code> — <judul>` | Nama versi |
| `description` | text | — | Tidak | `''` | Deskripsi versi |
| `status` | enum | — | Ya | `draft` | `draft`, `published`, `locked`, `retired` |
| `effectiveDate` | date | — | Tidak | `null` | Tanggal efektif (`YYYY-MM-DD`) |
| `notes` | text | — | Tidak | `''` | Catatan perubahan |
| `createdBy` | number | FK → `users.id` | Tidak | user sesi | Pembuat versi |
| `updatedBy` | number | FK → `users.id` | Tidak | user sesi | Pengubah terakhir |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 |

**Aturan:**

- Kombinasi `(courseId, code)` unik.
- Versi baru selalu `draft`.
- Hanya versi `published`/`locked` yang dapat dipilih saat membuat kelas.
- `draft` → `published` oleh Admin; `published` → `locked` otomatis saat kelas dibuat; `published`/`locked` → `retired` oleh Admin.
- Versi `locked`/`retired` tidak dapat diubah atau dihapus; revisi dilakukan dengan `clone` ke Draft baru.
- Hanya versi `draft` tanpa kelas yang boleh dihapus.

**Lifecycle status:**

```text
draft ── publish ──► published ── kelas dibuat ──► locked
   │                     │
   └── delete (draft)     └── retire ──► retired
locked ── retire ──► retired
```

---

### 5.2c CLASSES — `elips_classes`

Kelas/angkatan yang menyelenggarakan satu versi kurikulum untuk sekelompok peserta.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik kelas |
| `code` | string | UK | Ya | dibuat sistem | Kode kelas unik, mis. `ANG-2026-MNJ-01` |
| `name` | string | — | Ya | — | Nama kelas/angkatan |
| `courseId` | number | FK → `courses.id` | Ya | — | Kursus yang diselenggarakan |
| `curriculumVersionId` | number | FK → `curriculum_versions.id` | Ya | — | Versi kurikulum; harus `published`/`locked` |
| `branchId` | number | FK → `branches.id` | Tidak | `null` | Cabang penyelenggara; harus cabang `active` bila diisi |
| `instructorId` | number | FK → `users.id` | Tidak | `null` | Tentor aktif; wajib untuk status `ready`/`ongoing` |
| `periodStart` | date | — | Tidak | `null` | Tanggal mulai |
| `periodEnd` | date | — | Tidak | `null` | Tanggal selesai; tidak boleh sebelum `periodStart` |
| `quota` | number | — | Ya | `0` | Kuota peserta |
| `location` | string | — | Tidak | `''` | Lokasi |
| `room` | string | — | Tidak | `''` | Ruang |
| `scheduleNote` | string | — | Tidak | `''` | Catatan jadwal |
| `plannedJp` | number | — | Ya | `0` | JP rencana |
| `status` | enum | — | Ya | `draft` | `draft`, `open`, `ready`, `ongoing`, `completed`, `cancelled`, `archived` |
| `legacyImport` | boolean | — | Ya | `false` | Penanda kelas migrasi v3 |
| `notes` | text | — | Tidak | `''` | Catatan |
| `createdBy` | number | FK → `users.id` | Tidak | user sesi | Pembuat |
| `updatedBy` | number | FK → `users.id` | Tidak | user sesi | Pengubah terakhir |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 |

**Aturan:**

- `code` unik. `curriculumVersionId` harus milik `courseId` yang sama.
- Pembuatan kelas mengunci versi kurikulum secara transaksional.
- `instructorId`, bila diisi, harus akun `instruktur` aktif.
- `branchId`, bila diisi, harus cabang berstatus `active`; menghapus cabang membuat `branchId` menjadi `null`.
- Kuota tidak boleh diturunkan di bawah jumlah enrollment aktif.
- Kelas dengan enrollment tidak dapat dihapus.
- `cancelled`/`archived` tidak menerima pendaftaran baru.

**Lifecycle status:**

```text
draft ──► open ──► ongoing ──► completed ──► archived
   │         │          │
   └─────────┴──────────┴──► cancelled
```

---

### 5.2d CLASS_INSTRUCTOR_HISTORIES — `elips_classInstructorHistories`

Riwayat penugasan tentor per kelas. Setiap pergantian menutup entri lama dan membuka entri baru.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik histori |
| `classId` | number | FK → `classes.id` | Ya | — | Kelas terkait |
| `instructorId` | number | FK → `users.id` | Tidak | `null` | Tentor pada periode ini |
| `startedAt` | date | — | Ya | hari ini | Tanggal mulai |
| `endedAt` | date | — | Tidak | `null` | Tanggal selesai; `null` berarti masih aktif |
| `reason` | text | — | Tidak | `''` | Alasan pergantian |
| `actorId` | number | FK → `users.id` | Tidak | user sesi | Admin yang melakukan perubahan |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |

**Aturan:**

- Penugasan tentor yang sama bersifat idempoten (tidak membuat histori baru).
- Saat tentor diganti, entri lama diisi `endedAt`, entri baru dibuat dengan `endedAt = null`.
- Menghapus kelas menghapus seluruh hist histonya (`CASCADE`).

---

### 5.3 ENROLLMENTS — `elips_enrollments`

Tabel penghubung **N—N** antara peserta dan kelas/kursus.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik penugasan |
| `studentId` | number | FK → `users.id` | Ya | — | Harus user ber-role `peserta` dan `aktif` |
| `courseId` | number | FK → `courses.id` | Ya | — | Kursus yang diikuti (denormalisasi dari kelas) |
| `classId` | number | FK → `classes.id` | Tidak | `null` | Kelas/angkatan; `null` untuk data migrasi yang belum dipetakan |
| `enrolledBy` | string | — | Ya | `admin` | Pencatat penugasan; data lama dapat berisi nilai lain |
| `enrolledAt` | date | — | Ya | hari ini | `YYYY-MM-DD` |
| `status` | enum | — | Ya | `active` | `pending`, `active`, `completed`, `withdrawn`, `suspended`, `cancelled`, `dihentikan` (alias lama) |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 saat status berubah |

**Aturan:**

- Kombinasi `(studentId, classId)` unik; pendaftaran ganda pada kelas yang sama dilewati.
- Satu peserta boleh memiliki banyak enrollment (banyak kelas/kursus).
- Satu kelas boleh memiliki banyak enrollment (banyak peserta), dibatasi `classes.quota`.
- Enrollment aktif `active` menjadi syarat akses materi.
- Menghapus enrollment juga menghapus nilai, sertifikat, dan progres peserta pada kelas tersebut.
- Status `withdrawn`/`dihentikan` menghentikan akses peserta, tetapi data nilai/sertifikat tidak otomatis dihapus.
- Enrollment lama v3 dipetakan ke kelas migrasi (`legacyImport = true`) agar tetap valid.

---

### 5.3a CLASS_SESSIONS — `elips_classSessions`

Sesi pertemuan per kelas/angkatan. Satu kelas memiliki banyak sesi; nomor pertemuan unik di dalam kelas.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik sesi |
| `classId` | number | FK → `classes.id` | Ya | — | Kelas pemilik sesi |
| `sessionNumber` | number | UK per kelas | Ya | nomor berikutnya | Nomor pertemuan unik per kelas |
| `title` | string | — | Ya | `Pertemuan <n>` | Judul/topik sesi |
| `moduleId` | number | FK → `modules.id` | Tidak | `null` | Modul terkait; harus dari versi kurikulum kelas |
| `sessionDate` | date | — | Ya | — | Tanggal sesi (`YYYY-MM-DD`) |
| `startTime` | string | — | Ya | — | Jam mulai `HH:MM` |
| `endTime` | string | — | Ya | — | Jam selesai `HH:MM`, harus setelah mulai |
| `location` | string | — | Tidak | lokasi kelas | Lokasi pertemuan |
| `room` | string | — | Tidak | ruang kelas | Ruang pertemuan |
| `instructorId` | number | FK → `users.id` | Tidak | tentor kelas | Tentor sesi; harus instruktur aktif |
| `plannedJp` | number | — | Ya | `0` | JP rencana |
| `actualJp` | number | — | Ya | `0` | JP aktual; `completed` tanpa nilai diisi dari JP rencana |
| `status` | enum | — | Ya | `scheduled` | `scheduled`, `confirmed`, `completed`, `rescheduled`, `cancelled`, `no_show` |
| `rescheduledFromId` | number | FK → `class_sessions.id` | Tidak | `null` | Sesi asal bila sesi ini adalah pengganti |
| `notes` | text | — | Tidak | `''` | Catatan sesi |
| `createdBy` | number | FK → `users.id` | Tidak | user sesi | Pembuat |
| `updatedBy` | number | FK → `users.id` | Tidak | user sesi | Pengubah terakhir |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 saat berubah |

**Field turunan (dihitung saat snapshot/serialisasi):** `classCode`, `className`, `courseId`, `courseTitle`, `branchId`, `branchCode`, `branchName`, `branchCity`, `moduleTitle`, `instructorName`, `presentCount`, `lateCount`, `permissionCount`, `sickCount`, `absentCount`, `excusedCount`, `attendanceCount`.

**Aturan:**

- `(classId, sessionNumber)` unik; pembuatan massal memberi nomor berurutan.
- Sesi `completed` tidak dapat dihapus; sesi yang memiliki presensi juga tidak dapat dihapus (batalkan).
- Sesi `cancelled`, `no_show`, dan `rescheduled` tidak dihitung sebagai JP aktual maupun JP wajib rekap.
- Reschedule membuat sesi baru dan menandai sesi asal `rescheduled`; sesi baru menyimpan `rescheduledFromId`.
- Admin dapat mengelola semua kelas; Instruktur hanya kelas yang diampu atau sesi dengan `instructorId` miliknya.

---

### 5.3b ATTENDANCES — `elips_attendances`

Presensi peserta per sesi. Satu baris mewakili satu enrollment pada satu sesi.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik presensi |
| `sessionId` | number | FK → `class_sessions.id` | Ya | — | Sesi terkait |
| `enrollmentId` | number | FK → `enrollments.id` | Ya | — | Enrollment peserta pada kelas |
| `studentId` | number | FK → `users.id` | Ya | — | Peserta (denormalisasi dari enrollment) |
| `status` | enum | — | Ya | `absent` | `present`, `late`, `permission`, `sick`, `absent`, `excused` |
| `recordedBy` | number | FK → `users.id` | Tidak | user sesi | Pencatat presensi |
| `recordedAt` | datetime | — | Tidak | sekarang | Waktu pencatatan |
| `note` | text | — | Tidak | `''` | Catatan presensi |
| `correctedAt` | datetime | — | Tidak | `null` | Waktu koreksi terakhir |
| `correctionReason` | text | — | Tidak | `''` | Alasan koreksi terakhir (ringkasan) |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 saat berubah |

**Field turunan:** `studentName`, `sessionDate`, `sessionNumber`, `classId`, dan label kelas/sesi dari JOIN.

**Aturan:**

- `(sessionId, enrollmentId)` unik; penyimpanan massal memperbarui baris yang ada.
- Presensi hanya dapat dicatat untuk sesi yang belum `cancelled`/`no_show`/`rescheduled` dan enrollment yang valid pada kelas tersebut.
- Perubahan status yang sudah tercatat dianggap koreksi dan wajib alasan; detailnya disimpan di `attendance_corrections`.
- Peserta hanya dapat membaca presensi miliknya sendiri.

---

### 5.3c ATTENDANCE_CORRECTIONS — `elips_attendanceCorrections`

Audit koreksi presensi. Baris bersifat append-only dan menyimpan snapshot aktor.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik koreksi |
| `attendanceId` | number | FK → `attendances.id` | Ya | — | Baris presensi yang dikoreksi |
| `sessionId` | number | FK → `class_sessions.id` | Ya | — | Sesi terkait (denormalisasi) |
| `enrollmentId` | number | FK → `enrollments.id` | Ya | — | Enrollment terkait (denormalisasi) |
| `oldStatus` | string | — | Ya | `''` | Status sebelum koreksi (`''` bila belum ada) |
| `newStatus` | string | — | Ya | — | Status setelah koreksi |
| `actorId` | number | FK → `users.id` | Tidak | user sesi | Pelaku koreksi |
| `actorName` | string | — | Ya | `Sistem` | Snapshot nama aktor |
| `actorRole` | string | — | Ya | `sistem` | Snapshot role aktor |
| `reason` | text | — | Ya | — | Alasan koreksi (wajib) |
| `correctedAt` | datetime | — | Ya | sekarang | Waktu koreksi |
| `createdAt` | datetime | — | Ya | sekarang | Waktu dibuat |

**Aturan:** koreksi tanpa alasan ditolak; baris tidak diubah/dihapus oleh operasi normal sehingga riwayat tetap utuh.

---

### 5.4 MODULES — `elips_modules`

Modul pembelajaran di dalam versi kurikulum.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik modul |
| `courseId` | number | FK → `courses.id` | Ya | — | Kursus pemilik modul |
| `curriculumVersionId` | number | FK → `curriculum_versions.id` | Ya | — | Versi kurikulum pemilik modul |
| `instructorId` | number | FK → `users.id` | Tidak | dari kursus | Denormalisasi pemilik konten; disinkronkan dengan `course.instructorId` |
| `title` | string | — | Ya | — | Judul modul |
| `description` | text | — | Tidak | `''` | Deskripsi modul |
| `order` | number | — | Ya | `max(order)+1` per versi | Urutan modul di dalam versi, mulai dari 1 |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 |

**Aturan:**

- Instruktur hanya dapat mengelola modul pada kursus yang diampu dan versi yang `draft`/`published`.
- Versi `locked`/`retired` menolak create/update/delete modul.
- Menghapus modul menghapus seluruh materi di dalamnya beserta progres `completedLessons` terkait.
- `order` menentukan urutan tampilan modul di dalam versinya.
- `curriculumVersionId` harus milik `courseId` yang sama.

---

### 5.5 LESSONS — `elips_lessons`

Materi/pelajaran di dalam modul pada versi kurikulum tertentu.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik materi |
| `moduleId` | number | FK → `modules.id` | Ya | — | Modul pemilik materi |
| `courseId` | number | FK → `courses.id` | Ya | dari modul | Denormalisasi untuk query cepat per kursus |
| `curriculumVersionId` | number | FK → `curriculum_versions.id` | Ya | dari modul | Versi kurikulum pemilik materi |
| `instructorId` | number | FK → `users.id` | Tidak | dari modul | Denormalisasi pemilik konten |
| `title` | string | — | Ya | — | Judul materi |
| `type` | enum | — | Ya | `text` | `text`, `video`, `file`, `link` |
| `content` | text | — | Bersyarat | — | HTML untuk `text` (boleh kosong, disanitasi dengan `sanitizeHtml`); URL/nama file **wajib** untuk `video`, `file`, dan `link` |
| `order` | number | — | Ya | `max(order)+1` per modul | Urutan materi di dalam modul |
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

- `moduleId` harus milik `courseId` dan `curriculumVersionId` yang sama.
- Memindahkan materi ke modul lain hanya diizinkan dalam kursus dan versi yang sama.
- Versi `locked`/`retired` menolak create/update/delete materi.
- Menghapus materi menghapus progres penyelesaian materi tersebut.
- Materi hanya dapat diakses peserta jika enrollment kelas `active`, kursus `published`, dan urutan sebelumnya sudah selesai.

---

### 5.5a ASSIGNMENTS — `elips_assignments`

Tugas per kelas yang menjadi salah satu sumber nilai Phase 5/6.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik tugas |
| `classId` | number | FK → `classes.id` | Ya | — | Kelas pemilik tugas |
| `curriculumVersionId` | number | FK → `curriculum_versions.id` | Ya | dari kelas | Versi kurikulum acuan |
| `moduleId` | number | FK → `modules.id` | Tidak | `null` | Modul terkait |
| `lessonId` | number | FK → `lessons.id` | Tidak | `null` | Materi terkait |
| `title` | string | — | Ya | — | Judul tugas |
| `instructions` | longtext | — | Ya | — | Instruksi tugas |
| `type` | enum | — | Ya | `text_submission` | `file_upload`, `text_submission`, `in_class_practice`, `external_reference` |
| `openAt` | datetime | — | Tidak | `null` | Waktu buka |
| `dueAt` | datetime | — | Tidak | `null` | Tenggat; wajib setelah `openAt` |
| `maxScore` | number | — | Ya | `100` | Skor maksimum |
| `weight` | number | — | Ya | `0` | Bobot legacy (skema Phase 6 menyimpan bobot komponen) |
| `requireFile` | boolean | — | Ya | `false` | Wajib lampiran |
| `allowedTypes` | string | — | Tidak | `''` | Ekstensi yang diizinkan |
| `maxFileSize` | number | — | Tidak | `null` | Ukuran maksimum berkas |
| `status` | enum | — | Ya | `draft` | `draft`, `published`, `closed`, `archived` |
| `createdBy` / `updatedBy` | number | FK → `users.id` | Tidak | aktor | Pembuat/pembaru |
| `createdAt` / `updatedAt` | datetime | — | Ya/Tidak | sekarang/`null` | ISO 8601 |

**Aturan:**

- Hanya Admin atau instruktur pemilik kelas yang dapat membuat/mengubah/menghapus.
- `in_class_practice` tidak menerima submission daring; `external_reference` hanya menautkan sumber luar.
- Submission ditolak sebelum `openAt`, setelah `dueAt` dianggap terlambat, dan setelah status `closed`/`archived`.

---

### 5.5b ASSIGNMENT_FILES — `elips_assignmentFiles`

Lampiran berkas milik tugas.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik |
| `assignmentId` | number | FK → `assignments.id` | Ya | — | Tugas pemilik |
| `fileName` | string | — | Ya | — | Nama tampil |
| `filePath` | string | — | Ya | — | Lokasi penyimpanan; tidak diekspos ke peserta |
| `fileSize` | number | — | Tidak | `null` | Ukuran byte |
| `mimeType` | string | — | Tidak | `''` | MIME |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |

---

### 5.5c ASSIGNMENT_RUBRICS — `elips_assignmentRubrics`

Rubrik/kriteria penilaian tugas.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik |
| `assignmentId` | number | FK → `assignments.id` | Ya | — | Tugas pemilik |
| `criterion` | string | — | Ya | — | Nama kriteria |
| `description` | text | — | Tidak | `''` | Penjelasan |
| `maxScore` | number | — | Ya | `100` | Skor maksimum kriteria |
| `sortOrder` | number | — | Ya | urutan input | Urutan tampil |
| `createdAt` / `updatedAt` | datetime | — | Ya/Tidak | sekarang/`null` | ISO 8601 |

---

### 5.5d SUBMISSIONS — `elips_submissions`

Jawaban peserta untuk satu tugas; satu baris per (tugas, enrollment).

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik submission |
| `assignmentId` | number | FK → `assignments.id`, UK | Ya | — | Tugas |
| `enrollmentId` | number | FK → `enrollments.id`, UK | Ya | — | Enrollment |
| `studentId` | number | FK → `users.id` | Ya | — | Denormalisasi peserta |
| `status` | enum | — | Ya | `submitted` | `submitted`, `late`, `reviewed`, `revision_requested`, `resubmitted`, `accepted` |
| `textAnswer` | longtext | — | Tidak | `''` | Jawaban teks |
| `submittedAt` | datetime | — | Ya | sekarang | Waktu kirim |
| `isLate` | boolean | — | Ya | `false` | Ditandai terlambat |
| `score` | number | — | Tidak | `null` | Nilai 0–`maxScore` |
| `feedback` | text | — | Tidak | `''` | Umpan balik |
| `reviewedBy` | number | FK → `users.id` | Tidak | `null` | Peninjau |
| `reviewedAt` | datetime | — | Tidak | `null` | Waktu review |
| `revisionCount` | number | — | Ya | `0` | Jumlah revisi |
| `createdAt` / `updatedAt` | datetime | — | Ya/Tidak | sekarang/`null` | ISO 8601 |

**Aturan:**

- Submission ulang diizinkan sebelum diterima atau saat status `revision_requested`.
- `accepted` mengunci submission; pengiriman berikutnya ditolak.
- Submission wajib memiliki isi teks atau berkas sesuai konfigurasi tugas.
- Hanya Admin/instruktur pemilik kelas yang dapat menilai atau meminta revisi.

---

### 5.5e SUBMISSION_FILES — `elips_submissionFiles`

Berkas jawaban peserta.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik |
| `submissionId` | number | FK → `submissions.id` | Ya | — | Submission pemilik |
| `fileName` | string | — | Ya | — | Nama tampil |
| `filePath` | string | — | Ya | — | Lokasi penyimpanan privat |
| `fileSize` | number | — | Tidak | `null` | Ukuran byte |
| `mimeType` | string | — | Tidak | `''` | MIME |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |

---

### 5.5f SUBMISSION_REVIEWS — `elips_submissionReviews`

Riwayat aksi review submission (submit, score, revision, accept).

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik |
| `submissionId` | number | FK → `submissions.id` | Ya | — | Submission |
| `reviewerId` | number | FK → `users.id` | Tidak | `null` | Aktor |
| `action` | enum | — | Ya | `submit` | `submit`, `score`, `revision`, `accept` |
| `score` | number | — | Tidak | `null` | Nilai saat aksi |
| `note` | text | — | Tidak | `''` | Catatan |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |

---

### 5.5g EXAMS — `elips_exams`

Ujian per kelas; sumber nilai ujian Phase 5/6.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik ujian |
| `classId` | number | FK → `classes.id` | Ya | — | Kelas pemilik |
| `curriculumVersionId` | number | FK → `curriculum_versions.id` | Ya | dari kelas | Versi acuan |
| `title` | string | — | Ya | — | Judul ujian |
| `description` | text | — | Tidak | `''` | Deskripsi |
| `type` | enum | — | Ya | `written` | `written`, `practical`, `oral`, `project`, `other` |
| `examDate` | date | — | Tidak | `null` | Tanggal ujian |
| `startTime` | string | — | Tidak | `null` | Waktu mulai |
| `durationMinutes` | number | — | Tidak | `null` | Durasi |
| `maxScore` | number | — | Ya | `100` | Skor maksimum |
| `weight` | number | — | Ya | `0` | Bobot legacy |
| `instructions` | longtext | — | Tidak | `''` | Instruksi |
| `status` | enum | — | Ya | `draft` | `draft`, `published`, `closed`, `archived` |
| `createdBy` / `updatedBy` | number | FK → `users.id` | Tidak | aktor | Pembuat/pembaru |
| `createdAt` / `updatedAt` | datetime | — | Ya/Tidak | sekarang/`null` | ISO 8601 |

---

### 5.5h EXAM_PARTICIPANTS — `elips_examParticipants`

Roster peserta ujian; satu baris per (ujian, enrollment).

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik |
| `examId` | number | FK → `exams.id`, UK | Ya | — | Ujian |
| `enrollmentId` | number | FK → `enrollments.id`, UK | Ya | — | Enrollment |
| `studentId` | number | FK → `users.id` | Ya | — | Denormalisasi peserta |
| `status` | enum | — | Ya | `registered` | `registered`, `attended`, `absent`, `excused` |
| `note` | text | — | Tidak | `''` | Catatan |
| `createdAt` / `updatedAt` | datetime | — | Ya/Tidak | sekarang/`null` | ISO 8601 |

---

### 5.5i EXAM_RESULTS — `elips_examResults`

Hasil ujian per peserta; sumber nilai komponen ujian.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik hasil |
| `examId` | number | FK → `exams.id`, UK | Ya | — | Ujian |
| `enrollmentId` | number | FK → `enrollments.id`, UK | Ya | — | Enrollment |
| `studentId` | number | FK → `users.id` | Ya | — | Denormalisasi peserta |
| `score` | number | — | Tidak | `null` | Nilai 0–`maxScore` |
| `status` | enum | — | Ya | `not_assessed` | `not_assessed`, `excused`, `missing`, `graded` |
| `gradedBy` | number | FK → `users.id` | Tidak | `null` | Penilai |
| `gradedAt` | datetime | — | Tidak | `null` | Waktu penilaian |
| `note` | text | — | Tidak | `''` | Catatan |
| `createdAt` / `updatedAt` | datetime | — | Ya/Tidak | sekarang/`null` | ISO 8601 |

**Aturan:**

- Nilai `missing` **tidak** dikonversi menjadi 0; komponen wajib yang `missing` membuat nilai akhir `incomplete`.
- `excused` mengeluarkan komponen dari perhitungan dan menormalisasi ulang bobot.

---

### 5.6 GRADES — `elips_grades`

Nilai akhir peserta per enrollment. Sejak Phase 6, baris ini menyimpan hasil komputasi skema penilaian dan status finalisasi; alur manual legacy (`grade.save`) tetap didukung dan menulis `score`/`finalScore` yang sama.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik nilai |
| `enrollmentId` | number | FK → `enrollments.id`, UK | Ya | — | Penugasan yang dinilai |
| `schemeId` | number | FK → `grade_schemes.id` | Tidak | `null` | Skema penilaian yang dipakai saat komputasi/finalisasi |
| `courseId` | number | FK → `courses.id` | Ya | — | Denormalisasi untuk query |
| `studentId` | number | FK → `users.id` | Ya | — | Denormalisasi peserta |
| `instructorId` | number | FK → `users.id` | Tidak | `course.instructorId` | Instruktur pemberi nilai/finalisasi |
| `score` | number | — | Ya | — | Nilai legacy 0–100 (dibulatkan) |
| `finalScore` | number | — | Tidak | `null` | Nilai akhir desimal hasil komputasi skema (0–100) |
| `grade` | string(2) | — | Ya | turunan skor | Huruf predikat dari `predicate_ranges` (default `A`–`E`) |
| `status` | enum | — | Ya | `draft` | `draft`, `incomplete`, `finalized` |
| `componentsSnapshot` | longtext | — | Tidak | `null` | JSON komponen dan bobot saat finalisasi dikunci |
| `note` | text | — | Tidak | `''` | Catatan instruktur |
| `finalizedBy` | number | FK → `users.id` | Tidak | `null` | Aktor finalisasi |
| `finalizedAt` | datetime | — | Tidak | `null` | Waktu finalisasi |
| `computedAt` | datetime | — | Tidak | `null` | Waktu komputasi terakhir |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | — | ISO 8601 |

**Konversi grade default:**

| Skor | Grade |
|---|---|
| 90–100 | A |
| 80–89.99 | A- |
| 75–79.99 | B+ |
| 70–74.99 | B |
| 65–69.99 | B- |
| 60–64.99 | C+ |
| 55–59.99 | C |
| 50–54.99 | D |
| 0–49.99 | E |

Huruf tidak lagi hardcoded di UI: `predicateLetter(score)` memilih rentang aktif dari `predicate_ranges`. Admin dapat mengganti label/rentang selama seluruh skor 0–100 tercakup tanpa celah/tumpang tindih.

**Aturan:**

- Hanya boleh menilai jika enrollment valid dan berstatus `active`/`completed`.
- Instruktur tidak dapat menilai kelas/kursus yang bukan miliknya.
- Kombinasi `enrollmentId` **unik**; penyimpanan ulang memperbarui nilai yang sama.
- Peserta yang mengikuti kursus yang sama pada kelas berbeda memiliki nilai terpisah.
- `grade.finalize` menolak baris yang masih `incomplete` dan mengunci nilai (`status = finalized`).
- Nilai yang sudah `finalized` tidak dapat diubah lewat `grade.save` maupun `grade.manualSave`; perubahan hanya lewat `grade.correct` dengan alasan minimal 5 karakter.
- Menyimpan nilai legacy tetap membuat/memperbarui e-sertifikat **Draft** untuk enrollment tersebut; finalisasi Phase 6 **tidak** otomatis membuat sertifikat (sertifikasi tetap Phase 7).
- Jika sertifikat sudah `publish`, perubahan nilai memperbarui data sertifikat tetapi **tidak** mengembalikan status ke Draft.

---

### 5.6a PREDICATE_RANGES — `elips_predicateRanges`

Rentang predikat nilai huruf global yang dapat dikonfigurasi Admin.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik rentang |
| `letter` | string(4) | UK | Ya | — | Huruf predikat, mis. `A`, `A-`, `B+` |
| `label` | string | — | Tidak | `''` | Deskripsi, mis. `Sangat Baik` |
| `minScore` | number | — | Ya | — | Batas bawah inklusif (0–100) |
| `maxScore` | number | — | Ya | — | Batas atas inklusif (0–100) |
| `sortOrder` | number | — | Ya | urutan input | Urutan tampil |
| `isActive` | boolean | — | Ya | `true` | Rentang nonaktif diabaikan saat pemetaan |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | `null` | ISO 8601 |

**Aturan:**

- Hanya Admin yang boleh menyimpan; Instruktur/Peserta ditolak di server dan mode lokal.
- Himpunan rentang aktif wajib menutup 0–100 tanpa celah dan tanpa tumpang tindih; validasi dilakukan sebelum menyimpan.
- Default seed: A 90–100, A- 80–89.99, B+ 75–79.99, B 70–74.99, B- 65–69.99, C+ 60–64.99, C 55–59.99, D 50–54.99, E 0–49.99.
- Perubahan predikat berlaku saat komputasi/transkrip berikutnya; nilai yang sudah difinalisasi tetap menyimpan huruf hasil finalisasi sampai dikoreksi.

---

### 5.6b GRADE_SCHEMES — `elips_gradeSchemes`

Skema penilaian per kelas; satu kelas memiliki tepat satu skema.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik skema |
| `courseId` | number | FK → `courses.id` | Ya | dari kelas | Denormalisasi kursus |
| `classId` | number | FK → `classes.id`, UK | Ya | — | Kelas pemilik skema |
| `name` | string | — | Ya | `Skema Penilaian Kelas` | Nama skema |
| `status` | enum | — | Ya | `draft` | `draft`, `published`, `archived` |
| `passScore` | number | — | Ya | `60` | Ambang lulus 0–100 |
| `rounding` | enum | — | Ya | `half_up` | `half_up`, `half_down`, `floor`, `ceil` |
| `notes` | text | — | Tidak | `''` | Catatan |
| `createdBy` | number | FK → `users.id` | Tidak | aktor | Pembuat |
| `updatedBy` | number | FK → `users.id` | Tidak | aktor | Pembaru terakhir |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | `null` | ISO 8601 |

**Aturan:**

- Hanya Admin atau instruktur pemilik kelas yang dapat menyimpan/mengubah status/menghapus skema.
- `classId` unik; menyimpan skema untuk kelas yang sama memperbarui baris yang ada.
- Publikasi (`status = published`) mensyaratkan minimal satu komponen dan total bobot tepat 100%.
- Skema `published`/`archived` **tidak dapat** dikembalikan ke `draft`; arsipkan lalu buat skema pengganti.
- Skema `published`/`archived` tidak dapat dihapus.
- Komponen dikirim bersama simpanan skema; komponen lama diganti dalam transaksi yang sama.

---

### 5.6c GRADE_SCHEME_COMPONENTS — `elips_gradeSchemeComponents`

Komponen penilaian berbobot milik satu skema.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik komponen |
| `schemeId` | number | FK → `grade_schemes.id` | Ya | — | Skema pemilik |
| `type` | enum | — | Ya | `manual` | `assignment`, `exam`, `manual` |
| `name` | string | — | Ya | — | Nama komponen, mis. `Tugas`, `Ujian`, `Praktik` |
| `description` | text | — | Tidak | `''` | Deskripsi |
| `weight` | number | — | Ya | `0` | Bobot 0–100; total wajib 100 saat publish |
| `aggregation` | enum | — | Ya | `weighted` | `weighted` atau `average` untuk komponen bertipe tugas/ujian dengan banyak sumber |
| `maxScore` | number | — | Ya | `100` | Skor maksimum komponen |
| `isRequired` | boolean | — | Ya | `true` | Komponen wajib; bila kosong => `incomplete` |
| `sortOrder` | number | — | Ya | urutan input | Urutan tampil |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | `null` | ISO 8601 |

**Aturan:**

- Tipe `assignment`/`exam` mengambil nilai dari submission/hasil ujian yang dipetakan; tipe `manual` diisi langsung pada roster.
- Bobot boleh 0 pada draft, tetapi publikasi menolak total ≠ 100.
- Komponen `isRequired = false` yang tidak memiliki nilai dikeluarkan dari perhitungan dan bobotnya dinormalisasi ulang.
- Komponen `excused` juga dikeluarkan dan bobotnya dinormalisasi ulang.
- Skor `0` hanya dihitung bila benar-benar diisi; nilai kosong tidak pernah dianggap nol implisit.

---

### 5.6d GRADE_COMPONENT_SCORES — `elips_gradeComponentScores`

Nilai per komponen per enrollment, termasuk status pengecualian.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik nilai komponen |
| `componentId` | number | FK → `grade_scheme_components.id`, UK | Ya | — | Komponen |
| `enrollmentId` | number | FK → `enrollments.id`, UK | Ya | — | Enrollment |
| `studentId` | number | FK → `users.id` | Ya | — | Denormalisasi peserta |
| `score` | number | — | Tidak | `null` | 0–`maxScore`; `null` = belum dinilai |
| `status` | enum | — | Ya | `not_assessed` | `not_assessed`, `graded`, `excused`, `missing` |
| `assessorId` | number | FK → `users.id` | Tidak | aktor | Penilai |
| `assessedAt` | datetime | — | Tidak | `null` | Waktu penilaian |
| `note` | text | — | Tidak | `''` | Catatan/alasan pengecualian |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |
| `updatedAt` | datetime | — | Tidak | `null` | ISO 8601 |

**Aturan:**

- Pasangan (`componentId`, `enrollmentId`) unik; penyimpanan ulang memperbarui baris yang sama.
- Nilai tidak dapat disimpan bila enrollment sudah difinalisasi.
- Pengecualian (`grade.exempt`) membutuhkan alasan minimal 5 karakter, menulis status `excused`, dan mencatat audit.
- Nilai komponen tugas/ujian dapat disinkronkan dari submission/hasil ujian; nilai manual diisi melalui `grade.manualSave`.

---

### 5.6e GRADE_CORRECTIONS — `elips_gradeCorrections`

Jejak audit koreksi nilai yang sudah difinalisasi.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik koreksi |
| `gradeId` | number | FK → `grades.id` | Ya | — | Nilai yang dikoreksi |
| `enrollmentId` | number | FK → `enrollments.id` | Tidak | dari nilai | Denormalisasi |
| `previousScore` | number | — | Tidak | `null` | Nilai akhir sebelum koreksi |
| `newScore` | number | — | Tidak | `null` | Nilai akhir sesudah koreksi |
| `previousLetter` | string(4) | — | Tidak | `null` | Huruf sebelum koreksi |
| `newLetter` | string(4) | — | Tidak | `null` | Huruf sesudah koreksi |
| `reason` | text | — | Ya | — | Alasan koreksi, minimal 5 karakter |
| `actorId` | number | FK → `users.id` | Tidak | aktor | Pelaku koreksi |
| `createdAt` | datetime | — | Ya | sekarang | ISO 8601 |

**Aturan:**

- Hanya nilai berstatus `finalized` yang dapat dikoreksi.
- Koreksi dapat berupa perubahan skor komponen (lalu dihitung ulang) atau override `finalScore` langsung.
- `finalScore` wajib 0–100; huruf dihitung ulang dari predikat aktif.
- Koreksi berjalan dalam transaksi: nilai diperbarui, snapshot komponen diperbarui, dan baris koreksi ditulis.
- Koreksi tidak mengubah status `finalized`; nilai tetap terkunci dan setiap perubahan berikutnya memerlukan koreksi baru.

---

### 5.7 CERTIFICATES — `elips_certificates`

E-sertifikat peserta per kursus.

| Field | Tipe | Key | Wajib | Default | Keterangan |
|---|---|---|---|---|---|
| `id` | number | PK | Ya | `max(id)+1` | ID unik sertifikat |
| `studentId` | number | FK → `users.id` | Ya | — | Peserta penerima |
| `courseId` | number | FK → `courses.id` | Ya | — | Kursus terkait |
| `enrollmentId` | number | FK → `enrollments.id` | Ya | — | Enrollment terkait; unik per enrollment |
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

- Satu sertifikat per `enrollmentId`.
- Sertifikat hanya dibuat jika peserta sudah memiliki nilai dan enrollment.
- Sertifikat baru selalu `draft`.
- Hanya Admin yang dapat mengubah `draft` → `publish`.
- Sertifikat `publish` bersifat final dari sisi status; nilai masih dapat diperbarui.
- Saat `publish`, nomor Draft diganti nomor final, `approvedByAdminId` dan `approvedAt` diisi.
- Sertifikat `draft` tidak boleh tampil di halaman peserta dan tidak dapat diverifikasi.
- Menghapus peserta/enrollment/kelas/kursus ikut menghapus sertifikat terkait.

**Lifecycle status:**

```text
(nilai disimpan) ──► draft ── approve Admin ──► publish
                         ▲
                         └── nilai diperbarui (tetap draft)
publish ── nilai diperbarui ──► tetap publish (data grade/score diperbarui)
```

---

### 5.8 COMPLETED_LESSONS — `elips_completedLessons`

Mencatat materi yang sudah diselesaikan peserta pada model lama. **Tidak memiliki `id`**; identitasnya adalah kombinasi tiga field. Sejak Phase 4, sumber utama progres per kelas adalah `CLASS_LESSON_PROGRESS`; koleksi ini tetap dibaca untuk kompatibilitas data v3/v4 awal.

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
- `ELIPS.isLessonCompleted()` menganggap pelajaran selesai bila ada baris di koleksi ini **atau** di `elips_classLessonProgress` untuk enrollment yang sesuai.

---

### 5.8a CLASS_MATERIAL_SETTINGS — `elips_classMaterialSettings`

Menyimpan kebijakan akses materi per kelas. **Satu baris per kelas**; kelas yang belum dikonfigurasi memakai default implisit `open` tanpa baris.

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `id` | number | PK | Ya | ID pengaturan |
| `classId` | number | FK → `classes.id`, UK | Ya | Kelas pemilik pengaturan |
| `accessMode` | enum | — | Ya | `open`, `sequential`, `scheduled`, `conditional` |
| `requireInstructorApproval` | boolean | — | Ya | Bila `true`, peserta tidak dapat menandai selesai sendiri |
| `notes` | text | — | Tidak | Catatan kebijakan |
| `updatedBy` | number | FK → `users.id` | Tidak | Aktor terakhir |
| `createdAt` / `updatedAt` | datetime | — | Ya | ISO 8601 |

**Aturan:**

- Hanya Admin (semua kelas) dan instruktur pengampu (kelasnya) yang dapat mengubah.
- Default implisit: `accessMode = 'open'`, `requireInstructorApproval = false`, `id = null`.
- Perubahan oleh Admin pada baris yang sudah ada wajib menyertakan `reason`; audit mencatat nilai sebelum/sesudah.
- Menghapus kelas menghapus pengaturan (cascade).

---

### 5.8b CLASS_MATERIALS — `elips_classMaterials`

Materi tambahan di luar kurikulum inti, melekat pada kelas.

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `id` | number | PK | Ya | ID materi |
| `classId` | number | FK → `classes.id` | Ya | Kelas tujuan |
| `lessonId` | number | FK → `lessons.id` | Tidak | Pelajaran kurikulum terkait; menjadi prasyarat bila diisi |
| `sessionId` | number | FK → `class_sessions.id` | Tidak | Sesi terkait; tanggal/status sesi memengaruhi akses |
| `title` | string | — | Ya | Judul materi |
| `description` | text | — | Tidak | Deskripsi singkat |
| `type` | enum | — | Ya | `text`, `document`, `video`, `link`, `file` |
| `content` | text | — | Tidak | Teks/URL/konten; tidak dimasukkan ke snapshot |
| `fileName` | string | — | Tidak | Nama file asli |
| `filePath` | string | — | Tidak | Lokasi file privat; **tidak pernah** diekspos snapshot |
| `fileSize` | number | — | Tidak | Ukuran byte |
| `mimeType` | string | — | Tidak | MIME type |
| `accessLevel` | enum | — | Ya | `required`, `optional`, `reference` |
| `availableFrom` | date | — | Tidak | Tanggal minimum materi dapat dibuka |
| `status` | enum | — | Ya | `active`, `hidden` |
| `reason` | text | — | Tidak | Alasan perubahan status |
| `sortOrder` | number | — | Ya | Urutan tampil dalam kelas |
| `createdBy` / `updatedBy` | number | FK → `users.id` | Tidak | Aktor |
| `createdAt` / `updatedAt` | datetime | — | Ya | ISO 8601 |

**Aturan:**

- Hanya Admin dan instruktur pengampu yang dapat mengelola.
- `status = 'hidden'` menyembunyikan materi dari peserta; staf masih melihat metadata.
- Peserta hanya menerima `content` untuk materi yang tidak terkunci.
- Materi terkunci bila `availableFrom` belum tiba, pelajaran terkait belum selesai, atau sesi terkait belum tiba/dibatalkan.
- Menghapus materi menghapus progres terkait (cascade).

---

### 5.8c CLASS_LESSON_PROGRESS — `elips_classLessonProgress`

Progres pelajaran per enrollment (per kelas), menggantikan ketergantungan pada `completed_lessons` global.

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `id` | number | PK | Ya | ID progres |
| `classId` | number | FK → `classes.id` | Ya | Kelas |
| `enrollmentId` | number | FK → `enrollments.id`, UK | Ya | Enrollment peserta |
| `studentId` | number | FK → `users.id` | Ya | Peserta (denormalisasi) |
| `lessonId` | number | FK → `lessons.id`, UK | Ya | Pelajaran yang selesai |
| `completedBy` | number | FK → `users.id` | Tidak | Peserta atau staf yang mengonfirmasi |
| `completedAt` / `createdAt` | datetime | — | Ya | ISO 8601 |

**Aturan:**

- Unik per `(enrollmentId, lessonId)`; penandaan ulang idempoten.
- Peserta hanya dapat menulis untuk enrollment miliknya; staf pengampu/Admin dapat mengonfirmasi untuk enrollment kelas terkait.
- Bila `requireInstructorApproval` aktif, penulisan oleh peserta ditolak; staf yang mengonfirmasi.
- Menghapus enrollment/pelajaran/kelas menghapus progres terkait.

---

### 5.8d CLASS_MATERIAL_PROGRESS — `elips_classMaterialProgress`

Progres penyelesaian materi tambahan per enrollment.

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `id` | number | PK | Ya | ID progres |
| `classId` | number | FK → `classes.id` | Ya | Kelas |
| `enrollmentId` | number | FK → `enrollments.id`, UK | Ya | Enrollment peserta |
| `studentId` | number | FK → `users.id` | Ya | Peserta (denormalisasi) |
| `materialId` | number | FK → `class_materials.id`, UK | Ya | Materi tambahan |
| `completedBy` | number | FK → `users.id` | Tidak | Peserta atau staf yang mengonfirmasi |
| `completedAt` / `createdAt` | datetime | — | Ya | ISO 8601 |

**Aturan:**

- Unik per `(enrollmentId, materialId)`.
- Mengikuti aturan akses dan persetujuan yang sama dengan progres pelajaran.
- Menghapus materi/enrollment/kelas menghapus progres terkait.

---

### 5.9 AUDIT_LOGS — `elips_auditLogs`

Jejak audit perubahan data penting. Bersifat **append-only** dari sudut pandang aplikasi (hanya bisa dibersihkan seluruhnya dari halaman Admin).

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `id` | number | PK | Ya | ID unik log |
| `action` | enum | — | Ya | Lihat daftar aksi di bawah |
| `entity` | enum | — | Ya | `course`, `curriculum`, `class`, `grade`, `certificate`, `user`, `enrollment`, `module`, `lesson`, `class_material`, `class_material_settings` |
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
| `status` | Ubah Status | Perubahan status enrollment, sesi, atau materi tambahan |
| `progress` | Progres | Penyelesaian/konfirmasi progres pelajaran atau materi |

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
- `getCurrentUser()` adalah alias `getSessionUser()`; **tidak ada** fallback berdasarkan path halaman. Tanpa sesi, hasilnya `null`.
- `requireRole(role)` hanya memakai `getSessionUser()`; tanpa sesi diarahkan ke `login.html`, role tidak sesuai diarahkan ke dashboard role-nya.
- Sesi tidak otomatis dihapus ketika user dihapus; ini dapat menghasilkan sesi yang menunjuk user tidak valid.

### 5.12 STAFF_ATTENDANCES - `elips_staffAttendances`

Absensi harian pribadi admin dan karyawan yang dicatat dari dashboard Admin atau halaman absen karyawan (`employee/attendance.html`) melalui modul bersama `assets/js/attendance.js`. Bukan presensi peserta per sesi. Setiap pengguna memiliki paling banyak satu catatan `in` dan satu catatan `out` per hari; aturan ini dijaga unique key database dan validasi server.

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `id` | number | PK | Ya | ID catatan |
| `userId` | number | FK -> `users.id` | Ya | Pemilik catatan (`admin` atau `karyawan` yang login) |
| `userName` | string | - | Tidak | Nama pemilik (hasil JOIN, bukan input client) |
| `kind` | enum `in`/`out` | - | Ya | Jenis absensi masuk/pulang |
| `attendanceDate` | date `YYYY-MM-DD` | - | Ya | Tanggal kalender server (`Asia/Jakarta`); nilai dari klien diabaikan |
| `takenAt` | datetime ISO | - | Ya | Waktu server (disimpan UTC); nilai dari klien diabaikan |
| `timezone` | string | - | Tidak | Zona waktu server (`Asia/Jakarta`) |
| `photo` | string (data URL) | - | Ya | Foto JPEG/PNG/WebP; base64 disimpan di MySQL |
| `photoMime` | string | - | Ya | MIME foto terdeteksi |
| `latitude` | number | - | Ya | Lintang; wajib berpasangan dengan `longitude` (GPS wajib) |
| `longitude` | number | - | Ya | Bujur; wajib berpasangan dengan `latitude` (GPS wajib) |
| `accuracyM` | number/null | - | Tidak | Akurasi GPS dalam meter |
| `locationLabel` | string | - | Tidak | Label koordinat otomatis; tidak ada input manual |
| `locationSource` | enum `gps`/`manual`/`none` | - | Ya | Selalu `gps` untuk data baru; nilai lain hanya untuk data lama |
| `ipAddress` | string | - | Tidak | Diisi server dari `REMOTE_ADDR` |
| `userAgent` | string | - | Tidak | User agent browser |
| `platform` | string | - | Tidak | Platform perangkat |
| `language` | string | - | Tidak | Bahasa browser |
| `screen` | string | - | Tidak | Ukuran layar, mis. `1920x1080` |
| `deviceMemory` | number/null | - | Tidak | Perkiraan memori perangkat (GB) |
| `hardwareConcurrency` | number/null | - | Tidak | Jumlah CPU core logis |
| `connectionType` | string | - | Tidak | Tipe koneksi efektif |
| `online` | boolean | - | Ya | Status jaringan saat mencatat |
| `notes` | string | - | Tidak | Catatan tambahan (maks. 2000 karakter) |
| `createdAt` | datetime ISO | - | Ya | Waktu dibuat |
| `updatedAt` | datetime/null | - | Tidak | Waktu diperbarui |

**Aturan:**

- Hanya role `admin` dan `karyawan` yang dapat membuat/membaca absensi. Karyawan hanya dapat membaca catatannya sendiri, sedangkan Admin dapat membaca catatan pengguna lain lewat parameter `userId` dan menghapus catatan mana pun dengan alasan.
- Foto wajib dan harus berupa data URL gambar valid (JPEG/PNG/WebP) dengan ukuran terbatas.
- Koordinat harus lengkap berpasangan dan berada dalam rentang valid; GPS wajib untuk data baru dan `locationSource` selalu `gps`.
- Alur harian: dashboard hanya menampilkan tombol **Absen Masuk** bila belum ada catatan `in` hari ini, hanya **Absen Pulang** bila `in` sudah ada dan `out` belum, dan badge "Absensi hari ini lengkap" bila keduanya sudah ada. Tidak ada tombol saat lengkap.
- Satu `in` dan satu `out` per pengguna per `attendanceDate` ditegakkan oleh validasi API dan unique key database `uq_staff_attendances_user_day_kind` (`user_id`, `attendance_date`, `kind`).
- Tanggal, jam, dan lokasi dikunci otomatis saat tombol Simpan ditekan: `attendanceDate` = tanggal server (`Asia/Jakarta`), `takenAt` = waktu server, dan koordinat berasal dari GPS perangkat (tanpa input manual). Absensi tanpa GPS ditolak.
- Modal ambil foto hanya berisi layar kamera, tombol Ambil Foto/Ulangi/Nyalakan Kamera/Unggah Foto, Catatan, Batal, dan Simpan Absensi. Setelah simpan, modal sukses menampilkan foto, identitas akun, dan seluruh metadata dengan satu tombol **Selesai**; modal sukses tidak dapat ditutup dengan Escape/backdrop.
- Mode lokal menyimpan data URL terkompresi dan memangkas riwayat per pengguna menjadi 20 catatan; mode server menyimpan seluruh riwayat yang belum dihapus.
- Snapshot bootstrap tidak memuat koleksi ini maupun foto; data dibaca lewat `staffAttendance.list` atau, untuk rekap Admin, `staffAttendance.recap` (per rentang tanggal, tanpa foto).

### 5.12a STAFF_SHIFTS - `elips_staffShifts`

Jadwal shift per karyawan per tanggal yang dikelola Admin melalui halaman `admin/shifts.html` dan modul `assets/js/shift-schedule.js`. Karyawan yang dapat dijadwalkan adalah pengguna aktif dengan role `admin` atau `karyawan`. Setiap karyawan memiliki paling banyak satu shift per tanggal; aturan ini dijaga unique key database dan validasi API.

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `id` | number | PK | Ya | ID jadwal shift |
| `userId` | number | FK -> `users.id` | Ya | Karyawan yang dijadwalkan; `ON DELETE CASCADE` |
| `userName` | string | - | Tidak | Nama karyawan (hasil JOIN, bukan input client) |
| `userEmail` | string | - | Tidak | Email karyawan (hasil JOIN) |
| `userRole` | enum `admin`/`karyawan` | - | Tidak | Role karyawan (hasil JOIN) |
| `shiftDate` | date (`YYYY-MM-DD`) | UK (dengan `userId`) | Ya | Tanggal shift |
| `shiftType` | enum `pagi`/`sore`/`custom` | - | Ya | Jenis shift; default `pagi` |
| `startTime` | string `HH:MM` | - | Ya | Jam mulai; preset Pagi `08:00`, Sore `16:00` |
| `endTime` | string `HH:MM` | - | Ya | Jam selesai; preset Pagi `16:00`, Sore `00:00` |
| `note` | string | - | Tidak | Catatan shift (maks. 255 karakter) |
| `createdBy` | number/null | FK -> `users.id` | Tidak | Admin pembuat jadwal; `ON DELETE SET NULL` |
| `createdAt` | datetime ISO | - | Ya | Waktu dibuat |
| `updatedAt` | datetime/null | - | Tidak | Waktu diperbarui |

**Aturan:**

- Hanya role `admin` yang dapat membaca dan mengubah jadwal shift. Instruktur, peserta, dan karyawan ditolak oleh API.
- Jenis `pagi` dan `sore` memakai jam preset; nilai jam dari klien diabaikan. Jenis `custom` menerima jam `HH:MM` dan menolak jam mulai = jam selesai.
- Unique key `(user_id, shift_date)` menegakkan satu shift per karyawan per tanggal; API mengembalikan pesan konflik saat duplikat.
- Pengguna nonaktif, tidak ditemukan, atau berrole selain `admin`/`karyawan` tidak dapat dijadwalkan.
- Rentang daftar default adalah seluruh bulan berjalan, dibatasi maksimal 366 hari, dan tanggal awal tidak boleh melewati tanggal akhir.
- Jadwal shift tidak dimasukkan ke snapshot bootstrap; data dibaca lewat `staffShift.list` dan ditulis lewat `staffShift.save`/`staffShift.delete`. Mode lokal memakai key `elips_staffShifts`.
- Setiap create/update/delete dicatat sebagai audit entitas `staff_shift`.

---

### 5.13 USER_DEVICES — tabel server `user_devices` (tanpa key `localStorage`)

Menyimpan token **"ingat saya"** per perangkat. Hanya tersedia di mode server; mode lokal mengabaikan token dan tidak memiliki koleksi `localStorage` yang setara. Token mentah tidak pernah disimpan—yang disimpan adalah hash SHA-256.

| Field | Tipe | Key | Wajib | Keterangan |
|---|---|---|---|---|
| `id` | number | PK | Ya | ID baris perangkat |
| `userId` | number | FK → `users.id` | Ya | Pemilik token; `ON DELETE CASCADE` |
| `tokenHash` | string (64) | UK | Ya | SHA-256 token mentah; unik |
| `deviceLabel` | string | - | Tidak | Label ringkas, mis. `Android • Chrome` |
| `userAgent` | string | - | Tidak | User agent saat token dibuat |
| `ipAddress` | string | - | Tidak | IP saat token dibuat |
| `createdAt` | datetime | - | Ya | Waktu token dibuat (UTC) |
| `lastUsedAt` | datetime | - | Ya | Waktu token terakhir dipakai/dirotasi |
| `expiresAt` | datetime | - | Ya | Batas sliding 30 hari |
| `revokedAt` | datetime/null | - | Tidak | Waktu token dicabut |
| `revokedReason` | string | - | Ya | `rotated`, `superseded`, `logout`, `admin`, `security`, `expired`, atau `reuse` |

**Aturan:**

- Satu perangkat aktif per pengguna: login baru mencabut token lama dengan alasan `superseded`.
- Setiap `auth.resume` merotasi token: baris lama dicabut (`rotated`), baris baru diterbitkan untuk label perangkat yang sama.
- Token berstatus `rotated` yang dipakai ulang dianggap pencurian: semua perangkat pengguna dicabut (`reuse`), `users.session_version` dinaikkan, dan percobaan dicatat di Audit Trail.
- Token yang dicabut karena `logout`, `admin`, `security`, atau `expired` hanya ditolak tanpa mencabut perangkat baru.
- Baris yang sudah dicabut lebih dari 30 hari dipangkas otomatis. Token dan hash tidak pernah masuk snapshot bootstrap.

---

## 6. Relasi Lengkap

| No | Relasi | Kardinalitas | FK | Perilaku saat data induk dihapus | Catatan |
|---|---|---|---|---|---|
| R1 | `USERS` (instruktur) → `COURSES` | 1 — N | `courses.instructorId` | Instruktur dihapus/dilepas: `instructorId=null`, `instructorName=''`, kursus Publish → Draft | Satu kursus hanya satu instruktur; satu instruktur banyak kursus |
| R2 | `USERS` (peserta) → `ENROLLMENTS` | 1 — N | `enrollments.studentId` | Peserta dihapus: seluruh enrollment dihapus | Hanya untuk role `peserta` |
| R3 | `CLASSES` → `ENROLLMENTS` | 1 — N | `enrollments.classId` | Kelas dihapus: enrollment dihapus (namun kelas berisi peserta tidak boleh dihapus) | Relasi N—N peserta ↔ kelas |
| R3a | `COURSES` → `ENROLLMENTS` | 1 — N | `enrollments.courseId` | Kursus dihapus: seluruh enrollment dihapus | Denormalisasi dari kelas |
| R4 | `COURSES` → `MODULES` | 1 — N | `modules.courseId` | Kursus dihapus: seluruh modul dihapus | Modul tidak dapat berdiri sendiri |
| R5 | `MODULES` → `LESSONS` | 1 — N | `lessons.moduleId` | Modul dihapus: seluruh materi + progresnya dihapus | Satu materi hanya milik satu modul |
| R6 | `COURSES` → `LESSONS` | 1 — N | `lessons.courseId` | Kursus dihapus: seluruh materi dihapus | Denormalisasi dari modul |
| R7 | `ENROLLMENTS` → `GRADES` | 1 — 0..1 | `grades.enrollmentId` | Enrollment dihapus: nilai dihapus | Satu nilai per enrollment/kelas |
| R8 | `ENROLLMENTS` → `CERTIFICATES` | 1 — 0..1 | `certificates.enrollmentId` | Enrollment dihapus: sertifikat dihapus | Satu sertifikat per enrollment/kelas |
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
| R19 | `CATEGORIES` → `COURSES` | 1 — N | `courses.categoryId` | Kategori dihapus: `categoryId=null`, nama kategori tetap | FK `SET NULL` |
| R20 | `COURSES` → `CURRICULUM_VERSIONS` | 1 — N | `curriculum_versions.courseId` | Kursus dihapus: versi dihapus | Kode versi unik per kursus |
| R21 | `CURRICULUM_VERSIONS` → `MODULES` | 1 — N | `modules.curriculumVersionId` | Versi dihapus (hanya draft): modul dihapus | Versi locked/retired tidak boleh dihapus |
| R22 | `CURRICULUM_VERSIONS` → `CLASSES` | 1 — N | `classes.curriculumVersionId` | Versi tidak dapat dihapus selama dipakai kelas | FK `RESTRICT`; versi terkunci saat kelas dibuat |
| R23 | `USERS` (instruktur) → `CLASSES` | 1 — N | `classes.instructorId` | Instruktur dihapus: `instructorId=null` | Satu kelas satu tentor aktif |
| R24 | `CLASSES` → `CLASS_INSTRUCTOR_HISTORIES` | 1 — N | `class_instructor_histories.classId` | Kelas dihapus: histori dihapus | Histori pergantian tentor |
| R25 | `USERS` (instruktur) → `CLASS_INSTRUCTOR_HISTORIES` | 1 — N | `class_instructor_histories.instructorId` | Instruktur dihapus: `instructorId=null` | Riwayat tetap ada |
| R26 | `CLASSES` → `CLASS_SESSIONS` | 1 — N | `class_sessions.classId` | Kelas dihapus: sesi dan presensinya dihapus (kelas berisi enrollment tidak boleh dihapus) | Sesi unik per nomor dalam kelas |
| R27 | `MODULES` → `CLASS_SESSIONS` | 1 — N | `class_sessions.moduleId` | Modul dihapus: `moduleId=null` | Modul harus dari versi kurikulum kelas |
| R28 | `USERS` (instruktur) → `CLASS_SESSIONS` | 1 — N | `class_sessions.instructorId` | Instruktur dihapus: `instructorId=null` | Default tentor kelas |
| R29 | `CLASS_SESSIONS` → `CLASS_SESSIONS` | 1 — N | `class_sessions.rescheduledFromId` | Sesi asal dihapus: `rescheduledFromId=null` | Sesi pengganti hasil reschedule |
| R30 | `CLASS_SESSIONS` → `ATTENDANCES` | 1 — N | `attendances.sessionId` | Sesi dihapus: presensi dihapus | Sesi berpresensi tidak boleh dihapus via aplikasi |
| R31 | `ENROLLMENTS` → `ATTENDANCES` | 1 — N | `attendances.enrollmentId` | Enrollment dihapus: presensi dihapus | Satu baris per enrollment per sesi |
| R32 | `USERS` (peserta) → `ATTENDANCES` | 1 — N | `attendances.studentId` | Peserta dihapus: presensi dihapus | Denormalisasi dari enrollment |
| R33 | `USERS` (pencatat) → `ATTENDANCES` | 1 — N | `attendances.recordedBy` | User dihapus: `recordedBy=null` | Snapshot nama tidak disimpan di presensi |
| R34 | `ATTENDANCES` → `ATTENDANCE_CORRECTIONS` | 1 — N | `attendance_corrections.attendanceId` | Presensi dihapus: koreksi dihapus | Audit koreksi per baris presensi |
| R35 | `CLASS_SESSIONS` → `ATTENDANCE_CORRECTIONS` | 1 — N | `attendance_corrections.sessionId` | Sesi dihapus: koreksi dihapus | Denormalisasi untuk query rekap |
| R36 | `ENROLLMENTS` → `ATTENDANCE_CORRECTIONS` | 1 — N | `attendance_corrections.enrollmentId` | Enrollment dihapus: koreksi dihapus | Denormalisasi untuk query peserta |
| R37 | `USERS` (aktor) → `ATTENDANCE_CORRECTIONS` | 1 — N | `attendance_corrections.actorId` | User dihapus: `actorId=null`, snapshot nama/role tetap | Audit historis |
| R38 | `BRANCHES` → `CLASSES` | 1 — N | `classes.branchId` | Cabang dihapus: `branchId=null` (FK `SET NULL`) | Cabang opsional; hapus ditolak aplikasi bila masih dipakai |
| R39 | `BRANCHES` → `CLASS_SESSIONS` | 1 — N | `class_sessions.branchId` (turunan) | Mengikuti kelas; bukan FK fisik | Mode lokal menyimpan `branchId` denormalisasi, mode server memakai JOIN kelas |
| R40 | `USERS` (admin/karyawan) -> `STAFF_ATTENDANCES` | 1 -> N | `staff_attendances.userId` | User dihapus: catatan absensi dihapus (`ON DELETE CASCADE`) | Absensi harian pribadi (maks. 1 `in` + 1 `out` per hari, dijaga unique key); pemilik membaca sendiri, admin dapat membaca/menghapus dengan alasan |
| R40a | `USERS` → `USER_DEVICES` | 1 — N | `user_devices.userId` | User dihapus: seluruh token perangkat dihapus (`ON DELETE CASCADE`) | Token "ingat saya" dirotasi dan dicabut; hash token unik |
| R40b | `USERS` (admin/karyawan) → `STAFF_SHIFTS` | 1 — N | `staff_shifts.userId` | User dihapus: seluruh jadwal shift dihapus (`ON DELETE CASCADE`) | Satu shift per karyawan per tanggal, dijaga unique key (`user_id`, `shift_date`); pembuat jadwal (`createdBy`) menjadi `NULL` bila admin dihapus |
| R41 | `CLASSES` → `ASSIGNMENTS` | 1 — N | `assignments.classId` | Kelas dihapus: tugas dihapus | Tugas milik satu kelas |
| R42 | `CURRICULUM_VERSIONS` → `ASSIGNMENTS` | 1 — N | `assignments.curriculumVersionId` | Versi dihapus: tugas dihapus | Acuan versi kelas |
| R43 | `MODULES`/`LESSONS` → `ASSIGNMENTS` | 1 — N | `assignments.moduleId`, `assignments.lessonId` | Modul/materi dihapus: FK `SET NULL` | Opsional |
| R44 | `ASSIGNMENTS` → `ASSIGNMENT_FILES` | 1 — N | `assignment_files.assignmentId` | Tugas dihapus: lampiran dihapus | — |
| R45 | `ASSIGNMENTS` → `ASSIGNMENT_RUBRICS` | 1 — N | `assignment_rubrics.assignmentId` | Tugas dihapus: rubrik dihapus | — |
| R46 | `ASSIGNMENTS` → `SUBMISSIONS` | 1 — N | `submissions.assignmentId` | Tugas dihapus: submission dihapus | Unik per (tugas, enrollment) |
| R47 | `ENROLLMENTS` → `SUBMISSIONS` | 1 — N | `submissions.enrollmentId` | Enrollment dihapus: submission dihapus | — |
| R48 | `USERS` (peserta) → `SUBMISSIONS` | 1 — N | `submissions.studentId` | Peserta dihapus: submission dihapus | Denormalisasi |
| R49 | `USERS` (reviewer) → `SUBMISSIONS` | 1 — N | `submissions.reviewedBy` | User dihapus: FK `SET NULL` | — |
| R50 | `SUBMISSIONS` → `SUBMISSION_FILES` | 1 — N | `submission_files.submissionId` | Submission dihapus: berkas dihapus | — |
| R51 | `SUBMISSIONS` → `SUBMISSION_REVIEWS` | 1 — N | `submission_reviews.submissionId` | Submission dihapus: review dihapus | — |
| R52 | `USERS` (reviewer) → `SUBMISSION_REVIEWS` | 1 — N | `submission_reviews.reviewerId` | User dihapus: FK `SET NULL` | — |
| R53 | `CLASSES` → `EXAMS` | 1 — N | `exams.classId` | Kelas dihapus: ujian dihapus | Ujian milik satu kelas |
| R54 | `CURRICULUM_VERSIONS` → `EXAMS` | 1 — N | `exams.curriculumVersionId` | Versi dihapus: ujian dihapus | — |
| R55 | `EXAMS` → `EXAM_PARTICIPANTS` | 1 — N | `exam_participants.examId` | Ujian dihapus: peserta dihapus | Unik per (ujian, enrollment) |
| R56 | `ENROLLMENTS` → `EXAM_PARTICIPANTS` | 1 — N | `exam_participants.enrollmentId` | Enrollment dihapus: peserta dihapus | — |
| R57 | `EXAMS` → `EXAM_RESULTS` | 1 — N | `exam_results.examId` | Ujian dihapus: hasil dihapus | Unik per (ujian, enrollment) |
| R58 | `ENROLLMENTS` → `EXAM_RESULTS` | 1 — N | `exam_results.enrollmentId` | Enrollment dihapus: hasil dihapus | — |
| R59 | `USERS` (penilai) → `EXAM_RESULTS` | 1 — N | `exam_results.gradedBy` | User dihapus: FK `SET NULL` | — |
| R60 | `CLASSES` → `GRADE_SCHEMES` | 1 — 0..1 | `grade_schemes.classId` | Kelas dihapus: skema dan komponennya dihapus | Satu skema per kelas |
| R61 | `COURSES` → `GRADE_SCHEMES` | 1 — N | `grade_schemes.courseId` | Kursus dihapus: skema dihapus | Denormalisasi |
| R62 | `GRADE_SCHEMES` → `GRADE_SCHEME_COMPONENTS` | 1 — N | `grade_scheme_components.schemeId` | Skema dihapus: komponen dihapus | Bobot total 100 saat publish |
| R63 | `GRADE_SCHEME_COMPONENTS` → `GRADE_COMPONENT_SCORES` | 1 — N | `grade_component_scores.componentId` | Komponen dihapus: nilai komponen dihapus | Unik per (komponen, enrollment) |
| R64 | `ENROLLMENTS` → `GRADE_COMPONENT_SCORES` | 1 — N | `grade_component_scores.enrollmentId` | Enrollment dihapus: nilai komponen dihapus | — |
| R65 | `GRADES` → `GRADE_SCHEMES` | N — 0..1 | `grades.schemeId` | Skema dihapus: FK `SET NULL` | Jejak skema saat finalisasi |
| R66 | `GRADES` → `GRADE_CORRECTIONS` | 1 — N | `grade_corrections.gradeId` | Nilai dihapus: koreksi dihapus | Koreksi hanya untuk nilai finalized |
| R67 | `ENROLLMENTS` → `GRADE_CORRECTIONS` | 1 — N | `grade_corrections.enrollmentId` | Enrollment dihapus: koreksi dihapus | Denormalisasi |
| R68 | `USERS` (aktor) → `GRADE_CORRECTIONS` | 1 — N | `grade_corrections.actorId` | User dihapus: FK `SET NULL` | Audit koreksi |

### 6.1 Relasi Banyak-ke-Banyak

| Relasi | Tabel Penghubung | Atribut Penghubung | Keunikan |
|---|---|---|---|
| Peserta ↔ Kelas | `ENROLLMENTS` | `status`, `enrolledAt`, `enrolledBy` | `(studentId, classId)` |
| Peserta ↔ Materi (progres) | `COMPLETED_LESSONS` | `completedAt` | `(studentId, courseId, lessonId)` |
| Peserta ↔ Komponen Nilai | `GRADE_COMPONENT_SCORES` | `score`, `status`, `assessedAt` | `(componentId, enrollmentId)` |
| Peserta ↔ Ujian | `EXAM_PARTICIPANTS` + `EXAM_RESULTS` | `status`, `score` | `(examId, enrollmentId)` |

---

## 7. Aturan Integritas dan Validasi

### 7.1 Integritas Referensi

| Aturan | Penegak |
|---|---|
| Email pengguna unik | `crud-users.js` |
| Role hanya `admin`/`instruktur`/`peserta` | `core.isValidRole` + `crud-users.js` |
| Status user hanya `aktif`/`nonaktif` | `core.isValidUserStatus` |
| Password wajib minimal 8 karakter saat create; opsional saat update | `crud-users.js` |
| Login hanya untuk email/password valid dan akun aktif | `crud-users.js` (`authenticateUser`) |
| Halaman role hanya dapat dibuka dengan sesi dan role yang sesuai | `auth.js` (`requireRole`) |
| Konten materi teks disanitasi sebelum disimpan/dirender | `utils.js` (`sanitizeHtml`), `crud-content.js`, `learning.html` |
| Kursus wajib punya instruktur aktif saat dibuat/diubah | `crud-courses.js` + `core.isActiveInstructor` |
| Publikasi kursus wajib punya instruktur aktif, modul, dan materi | `crud-courses.js` |
| Kode versi kurikulum unik per kursus | `crud-academic.js` + `api/actions.php` |
| Versi kurikulum hanya dapat diedit pada status `draft`/`published` | `crud-academic.js` + `api/helpers.php` |
| Kelas hanya boleh memakai versi `published`/`locked` | `crud-academic.js` + `api/actions.php` |
| Pembuatan kelas mengunci versi secara transaksional | `api/actions.php` |
| Kelas hanya boleh memakai tentor aktif | `crud-academic.js` + `api/actions.php` |
| Kuota kelas tidak boleh kurang dari peserta aktif | `crud-academic.js` + `api/actions.php` |
| Kelas dengan enrollment tidak dapat dihapus | `crud-academic.js` + `api/actions.php` |
| Nomor sesi unik per kelas | `crud-academic.js` + unique `(class_id, session_number)` |
| Waktu sesi valid dan modul sesuai versi kelas | `crud-academic.js` + `api/actions.php` |
| Sesi completed / berpresensi tidak dapat dihapus | `crud-academic.js` + `api/actions.php` |
| Presensi unik per sesi + enrollment | `crud-academic.js` + unique `(session_id, enrollment_id)` |
| Koreksi presensi wajib alasan dan tercatat | `crud-academic.js` + `api/actions.php` |
| Presensi hanya untuk enrollment kelas yang valid | `crud-academic.js` + `api/actions.php` |
| Peserta hanya membaca presensi/rekap sendiri | `api/helpers.php` + `crud-academic.js` |
| Enrollment hanya untuk peserta aktif | `crud-enrollments.js` |
| Enrollment unik per peserta+kelas | `crud-enrollments.js` |
| Nilai hanya untuk enrollment yang valid | `crud-grades.js` |
| Skor 0–100 | `crud-grades.js` |
| Instruktur hanya mengelola/menilai kursus sendiri | `crud-content.js`, `crud-grades.js` |
| Sertifikat hanya untuk peserta bernilai | `crud-grades.js` |
| Approve hanya untuk sertifikat Draft | `crud-grades.js` |
| Sertifikat Publish yang terlihat peserta | `data.js` + filter halaman peserta |
| Rentang predikat menutup 0–100 tanpa celah/tumpang tindih | `admin/settings.html` + `api/actions.php` (`predicate.save`) |
| Hanya Admin yang dapat mengubah predikat | `api/actions.php`, `crud-grades.js` |
| Satu skema penilaian per kelas | unique `(class_id)` + `classId` |
| Publikasi skema wajib ≥1 komponen dan total bobot 100% | `api/actions.php`, `crud-grades.js`, `gradeWeightTotal` |
| Skema published/archived tidak boleh kembali ke draft atau dihapus | `api/actions.php`, `crud-grades.js` |
| Nilai komponen hanya untuk komponen + enrollment valid | `api/actions.php`, `crud-grades.js` |
| Nilai komponen/tugas/ujian kosong tidak dianggap 0 | `elips_grade_compute_enrollment`, `computeEnrollmentGrade` |
| Komponen wajib kosong → status `incomplete` | engine server + lokal |
| Finalisasi hanya untuk nilai lengkap dan mengunci nilai | `grade.finalize` |
| Nilai finalized tidak dapat diubah lewat simpan biasa | `grade.save`, `grade.manualSave` |
| Koreksi hanya untuk nilai finalized, alasan ≥5 karakter, tercatat | `grade.correct`, `grade_corrections` |
| Tugas/ujian hanya dikelola Admin atau instruktur pemilik kelas | `api/actions.php`, `crud-tasks.js` |
| Submission unik per (tugas, enrollment) dan tunduk pada status tugas | `api/actions.php`, `crud-tasks.js` |
| Submission `accepted` tidak dapat dikirim ulang | `api/actions.php`, `crud-tasks.js` |
| Hasil ujian unik per (ujian, enrollment) dan skor ≤ `maxScore` | `api/actions.php`, `crud-tasks.js` |
| Nilai `missing` pada ujian tidak dikonversi menjadi 0 | engine grading |

### 7.2 Integritas pada Mode Fallback

Pada mode fallback `localStorage` tidak ada database relasional, sehingga hal berikut hanya dijaga oleh kode dan dapat rusak jika penyimpanan diedit manual:

- Referensi `instructorId`, `courseId`, `moduleId`, `lessonId`, `studentId`, `enrollmentId`, `schemeId`, `componentId`, `assignmentId`, `examId` tidak memiliki constraint FK.
- Tidak ada `ON DELETE CASCADE` otomatis; cascade dilakukan manual di fungsi CRUD, termasuk komponen skema, nilai komponen, submission, hasil ujian, dan koreksi.
- Tidak ada transaksi; operasi multi-koleksi (mis. finalisasi/koreksi nilai) dapat berhenti di tengah jika terjadi error. Mode server memakai transaksi untuk komputasi dan koreksi.
- ID dapat dipakai ulang setelah record ber-ID terbesar dihapus.
- Tipe data tidak dipaksa saat data dibaca dari `localStorage`; validasi bobot/predikat murni dilakukan kode.

Pada mode server, integritas referensial ditegakkan MySQL melalui foreign key dan `ON DELETE` (lihat §16.4), sedangkan operasi multi-tabel memakai transaksi eksplisit di `api/actions.php`.

### 7.3 Aturan Akses Peserta

Peserta dapat membuka materi jika **semua** kondisi terpenuhi:

1. Ada sesi peserta yang valid.
2. Ada enrollment berstatus `active` pada sebuah kelas.
3. Kursus berstatus `published`.
4. Materi berasal dari versi kurikulum yang terkunci pada kelas tersebut.
5. Materi berada pada urutan yang sudah terbuka; materi berikutnya terkunci sampai materi sebelumnya selesai.

Sertifikat hanya terlihat jika `status = publish` dan milik peserta yang sedang login.

Presensi dan rekap kehadiran hanya dapat dibaca peserta untuk enrollment miliknya sendiri; `attendance.myRecap` memfilter berdasarkan sesi kelas yang diikuti dan menolak permintaan kelas lain. Admin dan Instruktur pengampu dapat membaca rekap seluruh peserta pada kelas yang dikelolanya.

Peserta dapat membaca transkrip nilainya sendiri melalui `grade.transcript`: skor akhir, huruf, status, komponen, dan riwayat koreksi. Peserta lain dan pengguna tanpa hak ditolak. Instruktur/Admin pengampu kelas dapat membaca transkrip peserta pada kelas yang dikelolanya. Peserta tidak dapat membaca nilai komponen mentah kelas lain maupun daftar roster.

Tugas (`assignment.classroom`/`detail`/`submit`) dan ujian (`exam.classroom`/`detail`) hanya dapat diakses peserta yang memiliki enrollment aktif pada kelas terkait. Submission milik peserta lain tidak pernah dikirim ke client; path berkas privat tidak diekspos.

---

## 8. Status Lifecycle

| Entitas | Field | Nilai | Transisi yang Diizinkan |
|---|---|---|---|
| Pengguna | `status` | `aktif`, `nonaktif` | aktif ↔ nonaktif |
| Pengguna | `role` | `admin`, `instruktur`, `peserta` | Dapat diubah; perubahan dari instruktur melepas kursus |
| Kategori | `status` | `active`, `inactive` | active ↔ inactive |
| Kursus | `status` | `draft`, `published` | draft → published (validasi), published → draft |
| Versi Kurikulum | `status` | `draft`, `published`, `locked`, `retired` | draft → published; published → locked (saat kelas dibuat); published/locked → retired; draft → deleted |
| Kelas | `status` | `draft`, `open`, `ready`, `ongoing`, `completed`, `cancelled`, `archived` | draft → open → ongoing → completed → archived; draft/open/ongoing → cancelled |
| Sesi Pertemuan | `status` | `scheduled`, `confirmed`, `completed`, `rescheduled`, `cancelled`, `no_show` | scheduled → confirmed/completed/cancelled/no_show/rescheduled; confirmed → completed/cancelled/no_show/rescheduled; completed final |
| Presensi | `status` | `present`, `late`, `permission`, `sick`, `absent`, `excused` | Dapat berubah melalui koreksi beralasan |
| Enrollment | `status` | `pending`, `active`, `completed`, `withdrawn`, `suspended`, `cancelled`, `dihentikan` | pending → active; active → completed/withdrawn/suspended/cancelled; withdrawn → active |
| Tugas | `status` | `draft`, `published`, `closed`, `archived` | draft → published → closed → archived; published → draft selama belum ada submission |
| Submission | `status` | `submitted`, `late`, `reviewed`, `revision_requested`, `resubmitted`, `accepted` | submitted/late → reviewed/revision_requested; revision_requested → resubmitted; reviewed/resubmitted → accepted; accepted final |
| Ujian | `status` | `draft`, `published`, `closed`, `archived` | draft → published → closed → archived; published → draft selama belum ada hasil |
| Peserta Ujian | `status` | `registered`, `attended`, `absent`, `excused` | registered → attended/absent/excused; attended final |
| Hasil Ujian | `status` | `not_assessed`, `graded`, `excused`, `missing` | not_assessed → graded/excused/missing; graded dapat dinilai ulang sebelum nilai difinalisasi |
| Skema Nilai | `status` | `draft`, `published`, `archived` | draft → published; published → archived; published/archived tidak dapat kembali ke draft |
| Nilai | `status` | `draft`, `incomplete`, `finalized` | draft ↔ incomplete (hasil komputasi); draft/incomplete → finalized; finalized hanya berubah melalui koreksi |
| Nilai | `grade` | A … E (konfigurabel) | Dihitung ulang dari `predicate_ranges` setiap skor/komputasi berubah |
| Koreksi Nilai | — | append-only | Setiap koreksi menambah baris, tidak menghapus histori |
| Sertifikat | `status` | `draft`, `publish` | draft → publish; publish tidak kembali ke draft |
| Notifikasi | `status` | `sent` | Tidak berubah (simulasi) |

---

## 9. Denormalisasi dan Snapshot

| Lokasi | Data | Sumber | Sinkronisasi |
|---|---|---|---|
| `courses.instructorName` | Nama instruktur | `users.name` | `syncInstructorNameOnCourses` saat user diubah |
| `courses.category` | Nama kategori | `categories.name` | Diset saat create/update kursus |
| `courses.modules` | Jumlah modul | `modules` | `syncCourseCounters` |
| `courses.lessons` | Jumlah materi | `lessons` | `syncCourseCounters` |
| `courses.totalLessons` | Duplikat jumlah materi | `courses.lessons` | `syncCourseCounters` |
| `modules.curriculumVersionId` | Versi kurikulum modul | `modules.courseId` → versi aktif | Diset saat create/update |
| `lessons.curriculumVersionId` | Versi kurikulum materi | `modules.curriculumVersionId` | Diset saat create/update |
| `modules.instructorId` | Pemilik konten | `courses.instructorId` | `syncAllContentOwners` |
| `lessons.instructorId` | Pemilik konten | `courses.instructorId` | `syncAllContentOwners` |
| `lessons.courseId` | Kursus materi | `modules.courseId` | Diset saat create/update |
| `enrollments.courseId` | Kursus enrollment | `classes.courseId` | Diset saat pendaftaran kelas |
| `grades.studentId`, `grades.courseId` | Pasangan peserta+kursus | `enrollments` | Diset saat nilai disimpan |
| `grades.enrollmentId` | Enrollment | `enrollments` | Diset saat nilai disimpan |
| `grades.schemeId` | Skema saat komputasi/finalisasi | `grade_schemes` | Diset saat finalisasi/koreksi |
| `grades.finalScore`, `grades.grade`, `grades.status` | Hasil komputasi | `grade_component_scores` + `predicate_ranges` | Dihitung ulang saat skor berubah; dikunci saat finalisasi |
| `grades.componentsSnapshot` | Komponen + bobot saat finalisasi | `grade_scheme_components` | Snapshot JSON saat finalisasi; diperbarui saat koreksi |
| `grades.finalizedBy`, `grades.finalizedAt` | Aktor/waktu finalisasi | aktor | Snapshot historis |
| `gradeComponentScores.studentId` | Peserta | `enrollments.studentId` | Diset saat nilai komponen disimpan |
| `gradeCorrections.previousScore`, `newScore`, `previousLetter`, `newLetter` | Nilai sebelum/sesudah | `grades` | Snapshot saat koreksi |
| `assignments.curriculumVersionId` | Versi kelas | `classes.curriculumVersionId` | Diset saat create/update |
| `submissions.studentId` | Peserta | `enrollments.studentId` | Diset saat submission disimpan |
| `examParticipants.studentId`, `examResults.studentId` | Peserta | `enrollments.studentId` | Diset saat peserta/hasil dibuat |
| `certificates.studentId`, `certificates.courseId` | Pasangan peserta+kursus | `enrollments` | Diset saat sertifikat dibuat |
| `certificates.enrollmentId` | Enrollment | `enrollments` | Diset saat sertifikat dibuat |
| `certificates.grade`, `certificates.score` | Nilai saat terbit | `grades` | Snapshot; diperbarui saat nilai disimpan ulang |
| `classSessions.classCode`, `className`, `courseId`, `courseTitle` | Identitas kelas | `classes`/`courses` | Diset saat serialisasi/snapshot |
| `classSessions.moduleTitle`, `instructorName` | Label modul/tentor | `modules`/`users` | Diset saat serialisasi/snapshot |
| `classSessions.presentCount` … `attendanceCount` | Hitungan presensi | `attendances` | Dihitung ulang saat snapshot/serialisasi |
| `attendances.studentId` | Peserta | `enrollments.studentId` | Diset saat presensi disimpan |
| `attendances.studentName` | Nama peserta | `users.name` | Diset saat serialisasi/snapshot |
| `attendanceCorrections.actorName`, `actorRole` | Identitas aktor | `users` | Snapshot historis |
| `attendanceCorrections.sessionId`, `enrollmentId` | Konteks koreksi | `attendances` | Denormalisasi untuk query rekap |
| `auditLogs.actorName`, `actorRole`, `entityLabel` | Identitas saat kejadian | `users`/entitas | Snapshot historis |
| `notifications.recipientName`, `recipientEmail`, `recipientRole`, `entityLabel` | Data saat notifikasi dibuat | `users`/entitas | Snapshot historis |

**Tujuan:**

- Denormalisasi mempercepat pembacaan tanpa join.
- Snapshot menjaga histori tetap akurat meskipun nama pengguna atau judul kursus berubah.

---

## 10. Matriks Cascade

| Operasi | CATEGORIES | CURRICULUM_VERSIONS | CLASSES | CLASS_HISTORIES | COURSES | ENROLLMENTS | MODULES | LESSONS | GRADES | CERTIFICATES | COMPLETED_LESSONS | AUDIT_LOGS | NOTIFICATIONS |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Hapus **User peserta** | — | — | — | — | — | Hapus milik peserta | — | — | Hapus milik peserta | Hapus milik peserta | Hapus milik peserta | Tetap | Tetap |
| Hapus **User instruktur** | — | — | Tentor → `null` | Tentor → `null` | Lepas & Draft | — | Owner di-reset | Owner di-reset | Tetap | Tetap | — | Tetap | Tetap |
| Hapus **User admin** | — | — | — | — | — | — | — | — | — | Tetap | — | Tetap | Tetap |
| **Nonaktifkan User instruktur** | — | — | — | — | Publish → Draft (owner tetap) | — | — | — | — | — | — | Tambah log | Tetap |
| Hapus **Kursus** | — | Ditolak bila ada kelas; jika tidak hapus | Ditolak bila ada kelas | — | Hapus | Hapus | Hapus | Hapus | Hapus | Hapus | Hapus | Tetap | Tetap |
| Hapus **Versi kurikulum Draft** | — | Hapus | — | — | — | — | Hapus | Hapus | — | — | — | Tetap | Tetap |
| **Retire versi kurikulum** | — | Status → `retired` | — | — | — | — | — | — | — | — | — | Tambah log | — |
| Hapus **Kelas** | — | — | Ditolak bila ada enrollment; jika kosong hapus | Hapus | — | — | — | — | — | — | — | Tetap | Tetap |
| **Ganti tentor kelas** | — | — | `instructorId` berubah | Tutup lama + buka baru | — | — | — | — | — | — | — | Tambah log | — |
| Hapus **Enrollment** | — | — | — | — | — | Hapus | — | — | Hapus pasangan | Hapus pasangan | Hapus pasangan | Tetap | Tetap |
| Hapus **Modul** | — | — | — | — | Counter update | — | Hapus | Hapus isi modul | Tetap | Tetap | Hapus milik materi | Tetap | Tetap |
| Hapus **Materi** | — | — | — | — | Counter update | — | — | Hapus | Tetap | Tetap | Hapus milik materi | Tetap | Tetap |
| **Approve sertifikat** | — | — | — | — | — | — | — | — | — | Draft → Publish | — | Tambah log | Tambah notifikasi |
| **Publikasi kursus** | — | — | — | — | Draft → Publish | — | — | — | — | — | — | Tambah log | Tambah notifikasi |

Keterangan:

- “Tetap” berarti record tidak dihapus dan dapat menyimpan referensi yang sudah tidak valid (yatim).
- `AUDIT_LOGS` dan `NOTIFICATIONS` sengaja tidak di-cascade agar histori operasional tetap utuh.
- Data akademik tidak dihapus permanen: kelas dengan enrollment dan versi yang sudah dipakai tidak dapat dihapus.
- `SESSION` tidak memiliki cascade; sesi dapat menunjuk user yang sudah dihapus sampai logout.

---

### 10.1 Cascade Sesi & Presensi

| Operasi | CLASS_SESSIONS | ATTENDANCES | ATTENDANCE_CORRECTIONS | Catatan |
|---|---|---|---|---|
| Hapus **Kelas** | Dihapus | Dihapus melalui sesi | Dihapus melalui sesi/presensi | Aplikasi menolak hapus kelas yang masih memiliki enrollment |
| Hapus **Enrollment** | — | Dihapus milik enrollment | Dihapus milik enrollment | Riwayat presensi peserta pada kelas ikut hilang |
| Hapus **Sesi** | Dihapus | Dihapus milik sesi | Dihapus milik sesi | Aplikasi menolak hapus sesi `completed` atau yang sudah berpresensi |
| Hapus **Modul** | `moduleId` → `null` | — | — | Sesi tetap ada tanpa modul |
| Hapus **User peserta** | — | Dihapus milik peserta | Dihapus melalui presensi/enrollment | — |
| Hapus **User instruktur** | `instructorId` → `null` | `recordedBy` → `null` | `actorId` → `null`, snapshot tetap | Riwayat tetap terbaca |
| **Koreksi presensi** | — | `status`, `correctedAt`, `correctionReason` diperbarui | Tambah baris baru | Baris koreksi append-only |
| **Reschedule** | Sesi asal → `rescheduled`; sesi baru dibuat | — | — | Sesi baru menyimpan `rescheduledFromId` |

---

### 10.2 Cascade Tugas, Submission, dan Ujian

| Operasi | ASSIGNMENTS | ASSIGNMENT_FILES | ASSIGNMENT_RUBRICS | SUBMISSIONS | SUBMISSION_FILES | SUBMISSION_REVIEWS | EXAMS | EXAM_PARTICIPANTS | EXAM_RESULTS | Catatan |
|---|---|---|---|---|---|---|---|---|---|---|
| Hapus **Kelas** | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Aplikasi menolak hapus kelas berisi enrollment |
| Hapus **Tugas** | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | — | — | — | Hanya saat tugas belum dipakai atau sesuai aturan status |
| Hapus **Submission** | — | — | — | Dihapus | Dihapus | Dihapus | — | — | — | Nilai komponen terkait dihitung ulang |
| Hapus **Ujian** | — | — | — | — | — | — | Dihapus | Dihapus | Dihapus | Hasil ujian ikut hilang |
| Hapus **Enrollment** | — | — | — | Dihapus milik enrollment | Dihapus | Dihapus | — | Dihapus milik enrollment | Dihapus milik enrollment | Nilai komponen/koreksi juga dihapus |
| Hapus **User peserta** | — | — | — | Dihapus milik peserta | Dihapus | Dihapus | — | Dihapus milik peserta | Dihapus milik peserta | Cascade melalui student/enrollment |
| Hapus **User instruktur** | Tetap | — | — | `reviewedBy` → `null` | — | `reviewerId` → `null` | Tetap | — | `gradedBy` → `null` | Riwayat tetap terbaca |
| Hapus **Versi kurikulum** | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | Versi terkunci tidak dapat dihapus |

---

### 10.3 Cascade Nilai dan Koreksi

| Operasi | GRADE_SCHEMES | GRADE_SCHEME_COMPONENTS | GRADE_COMPONENT_SCORES | GRADES | GRADE_CORRECTIONS | PREDICATE_RANGES | Catatan |
|---|---|---|---|---|---|---|---|
| Hapus **Kelas** | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | — | Aplikasi menolak hapus kelas berisi enrollment |
| Hapus **Kursus** | Dihapus | Dihapus | Dihapus | Dihapus | Dihapus | — | — |
| Hapus **Enrollment** | — | — | Dihapus milik enrollment | Dihapus | Dihapus | — | — |
| Hapus **Skema** | Dihapus | Dihapus | Dihapus | `schemeId` → `null` | Tetap (via grade) | — | Skema published/archived tidak dapat dihapus |
| Hapus **Komponen** | — | Dihapus | Dihapus | Snapshot tetap | — | — | Hanya lewat simpan ulang skema |
| Hapus **Nilai** | — | — | — | Dihapus | Dihapus | — | — |
| Hapus **Predikat** | — | — | — | — | — | Dihapus | Huruf tersimpan tetap ada pada nilai finalized |
| **Finalisasi** | — | — | — | `status` → `finalized`, snapshot ditulis | — | — | Tidak membuat sertifikat otomatis |
| **Koreksi** | — | — | Opsional diperbarui | `finalScore`/`grade` diperbarui | Tambah baris | — | Append-only, alasan wajib |

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
  "categoryId": 1,
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

### 11.2a Kategori, Versi Kurikulum, Kelas, dan Histori

```json
{
  "id": 1,
  "name": "Pelatihan",
  "slug": "pelatihan",
  "status": "active",
  "createdAt": "2026-09-20T00:00:00.000Z"
}
```

```json
{
  "id": 1,
  "courseId": 1,
  "code": "1.0",
  "name": "Kurikulum 1.0 — Pelatihan Manajemen Dasar",
  "description": "Versi awal kurikulum Pelatihan Manajemen Dasar.",
  "status": "locked",
  "effectiveDate": "2026-09-20",
  "notes": "Migrasi struktur modul dan materi v3.0.",
  "createdBy": 1,
  "updatedBy": 1,
  "createdAt": "2026-09-20T00:00:00.000Z"
}
```

```json
{
  "id": 1,
  "code": "ANG-2026-MNJ-01",
  "name": "Angkatan 2026 — Pelatihan Manajemen Dasar",
  "courseId": 1,
  "courseTitle": "Pelatihan Manajemen Dasar",
  "curriculumVersionId": 1,
  "curriculumVersionCode": "1.0",
  "curriculumVersionName": "Kurikulum 1.0 — Pelatihan Manajemen Dasar",
  "instructorId": 2,
  "instructorName": "Ahmad Fauzi, M.M.",
  "periodStart": "2026-10-01",
  "periodEnd": "2026-12-31",
  "quota": 30,
  "location": "Kampus Pusat",
  "room": "R-101",
  "scheduleNote": "Senin & Rabu, 09.00-11.00",
  "plannedJp": 32,
  "status": "ongoing",
  "legacyImport": true,
  "notes": "Kelas migrasi dari enrollment kursus v3.0.",
  "createdAt": "2026-10-01T00:00:00.000Z"
}
```

```json
{
  "id": 1,
  "classId": 1,
  "instructorId": 2,
  "startedAt": "2026-10-01",
  "endedAt": null,
  "reason": "Penugasan awal kelas migrasi.",
  "actorId": 1,
  "createdAt": "2026-10-01T00:00:00.000Z"
}
```

### 11.2b Sesi Pertemuan dan Presensi

```json
{
  "id": 4,
  "classId": 1,
  "classCode": "ANG-2026-MNJ-01",
  "className": "Angkatan 2026 — Manajemen Dasar",
  "courseId": 1,
  "courseTitle": "Pelatihan Manajemen Dasar",
  "sessionNumber": 4,
  "title": "Pertemuan 4",
  "moduleId": 3,
  "moduleTitle": "Fungsi Manajemen",
  "sessionDate": "2026-10-14",
  "startTime": "09:00",
  "endTime": "11:00",
  "location": "Ruang Training A",
  "room": "A-101",
  "instructorId": 2,
  "instructorName": "Ahmad Fauzi, M.M.",
  "plannedJp": 4,
  "actualJp": 0,
  "status": "scheduled",
  "rescheduledFromId": null,
  "notes": "",
  "createdBy": 1,
  "updatedBy": 1,
  "createdAt": "2026-10-01T08:00:00.000Z",
  "updatedAt": null
}
```

```json
{
  "id": 1,
  "sessionId": 1,
  "enrollmentId": 1,
  "studentId": 5,
  "studentName": "Budi Santoso",
  "status": "present",
  "recordedBy": 2,
  "recordedAt": "2026-10-05T11:05:00.000Z",
  "note": "",
  "correctedAt": null,
  "correctionReason": "",
  "createdAt": "2026-10-05T11:05:00.000Z",
  "updatedAt": null
}
```

```json
{
  "id": 1,
  "attendanceId": 3,
  "sessionId": 3,
  "enrollmentId": 3,
  "oldStatus": "absent",
  "newStatus": "late",
  "actorId": 2,
  "actorName": "Ahmad Fauzi, M.M.",
  "actorRole": "instruktur",
  "reason": "Peserta hadir terlambat dan menunjukkan bukti",
  "correctedAt": "2026-10-13T09:15:00.000Z",
  "createdAt": "2026-10-13T09:15:00.000Z"
}
```

### 11.3 Enrollment

```json
{
  "id": 1,
  "studentId": 5,
  "courseId": 1,
  "classId": 1,
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
  "curriculumVersionId": 1,
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
  "curriculumVersionId": 1,
  "instructorId": 2,
  "title": "1.1 Pengantar Manajemen - Konsep Dasar",
  "type": "text",
  "content": "<p>Materi pengantar manajemen.</p>",
  "order": 1,
  "createdAt": "2026-09-20T00:00:00.000Z"
}
```

### 11.5 Nilai

Nilai legacy/manual (kompatibel Phase 1–5):

```json
{
  "id": 1,
  "enrollmentId": 1,
  "schemeId": null,
  "courseId": 1,
  "studentId": 5,
  "instructorId": 2,
  "score": 88,
  "finalScore": 88,
  "grade": "A-",
  "status": "draft",
  "componentsSnapshot": null,
  "note": "Pemahaman materi baik.",
  "finalizedBy": null,
  "finalizedAt": null,
  "computedAt": "2026-11-10T09:00:00.000Z",
  "createdAt": "2026-11-03T07:25:19.822Z",
  "updatedAt": "2026-11-03T07:25:19.822Z"
}
```

Nilai hasil finalisasi skema (enrollment 1 setelah komponen Tugas dikecualikan):

```json
{
  "id": 7,
  "enrollmentId": 1,
  "schemeId": 1,
  "courseId": 1,
  "studentId": 5,
  "instructorId": 2,
  "score": 89,
  "finalScore": 88.86,
  "grade": "A-",
  "status": "finalized",
  "componentsSnapshot": "[{\"componentId\":2,\"name\":\"Ujian\",\"weight\":40,\"score\":88},{\"componentId\":3,\"name\":\"Penilaian Manual\",\"weight\":30,\"score\":90}]",
  "note": "Difinalisasi dari skema penilaian kelas.",
  "finalizedBy": 2,
  "finalizedAt": "2026-11-10T09:05:00.000Z",
  "computedAt": "2026-11-10T09:05:00.000Z",
  "createdAt": "2026-11-10T09:05:00.000Z",
  "updatedAt": null
}
```

### 11.5a Predikat dan Skema Penilaian

```json
{
  "id": 1,
  "letter": "A",
  "label": "Sangat Baik",
  "minScore": 90,
  "maxScore": 100,
  "sortOrder": 1,
  "isActive": true
}
```

```json
{
  "id": 1,
  "courseId": 1,
  "classId": 1,
  "name": "Skema Penilaian Pelatihan Manajemen Dasar",
  "status": "published",
  "passScore": 60,
  "rounding": "half_up",
  "notes": "",
  "createdBy": 2,
  "updatedBy": 2
}
```

```json
[
  {
    "id": 1,
    "schemeId": 1,
    "type": "assignment",
    "name": "Tugas",
    "weight": 30,
    "aggregation": "weighted",
    "maxScore": 100,
    "isRequired": true,
    "sortOrder": 1
  },
  {
    "id": 2,
    "schemeId": 1,
    "type": "exam",
    "name": "Ujian",
    "weight": 40,
    "aggregation": "weighted",
    "maxScore": 100,
    "isRequired": true,
    "sortOrder": 2
  },
  {
    "id": 3,
    "schemeId": 1,
    "type": "manual",
    "name": "Penilaian Manual",
    "weight": 30,
    "aggregation": "weighted",
    "maxScore": 100,
    "isRequired": true,
    "sortOrder": 3
  }
]
```

```json
{
  "id": 5,
  "componentId": 3,
  "enrollmentId": 1,
  "studentId": 5,
  "score": 90,
  "status": "graded",
  "assessorId": 2,
  "assessedAt": "2026-11-10T08:30:00.000Z",
  "note": ""
}
```

```json
{
  "id": 1,
  "gradeId": 7,
  "enrollmentId": 1,
  "previousScore": 88.86,
  "newScore": 92,
  "previousLetter": "A-",
  "newLetter": "A",
  "reason": "Verifikasi ulang nilai praktik oleh tim akademik.",
  "actorId": 2,
  "createdAt": "2026-11-10T10:00:00.000Z"
}
```

### 11.5b Tugas, Submission, dan Ujian

```json
{
  "id": 1,
  "classId": 1,
  "curriculumVersionId": 1,
  "title": "Studi Kasus Manajemen",
  "type": "text_submission",
  "openAt": "2026-10-01T00:00:00.000Z",
  "dueAt": "2026-10-20T23:59:00.000Z",
  "maxScore": 100,
  "status": "published"
}
```

```json
{
  "id": 1,
  "assignmentId": 1,
  "enrollmentId": 1,
  "studentId": 5,
  "status": "reviewed",
  "textAnswer": "Analisis studi kasus...",
  "score": 85,
  "reviewedBy": 2,
  "reviewedAt": "2026-10-21T09:00:00.000Z",
  "revisionCount": 0
}
```

```json
{
  "id": 1,
  "classId": 1,
  "curriculumVersionId": 1,
  "title": "Ujian Akhir",
  "type": "written",
  "examDate": "2026-10-25",
  "maxScore": 100,
  "status": "published"
}
```

```json
{
  "id": 1,
  "examId": 1,
  "enrollmentId": 1,
  "studentId": 5,
  "score": 88,
  "status": "graded",
  "gradedBy": 2,
  "gradedAt": "2026-10-25T10:00:00.000Z",
  "note": "Pemahaman konsep baik."
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
| Versi kurikulum kursus | `CURRICULUM_VERSIONS` | Filter `courseId`, urutkan `id`; versi aktif = non-retired terbaru |
| Struktur versi | `MODULES` + `LESSONS` | Filter `curriculumVersionId`, urutkan `order` |
| Kelas per kursus | `CLASSES` | Filter `courseId`, `status` |
| Kelas per tentor | `CLASSES` | Filter `instructorId` |
| Histori tentor | `CLASS_INSTRUCTOR_HISTORIES` | Filter `classId`, urutkan `startedAt` |
| Roster kelas | `ENROLLMENTS` | Filter `classId`, lalu ambil `USERS` |
| Sesi per kelas | `CLASS_SESSIONS` | Filter `classId`, urutkan `sessionDate`, `sessionNumber` |
| Presensi per sesi | `ATTENDANCES` | Filter `sessionId`, lalu ambil `ENROLLMENTS`/`USERS` |
| Presensi peserta | `ATTENDANCES` | Filter `studentId` atau `enrollmentId` |
| Rekap kehadiran | `CLASS_SESSIONS` + `ATTENDANCES` | Filter `classId`, hitung JP dari status sesi dan status presensi |
| Audit koreksi presensi | `ATTENDANCE_CORRECTIONS` | Filter `attendanceId`/`sessionId`/`enrollmentId`, urutkan `correctedAt` |
| Kursus peserta | `ENROLLMENTS` | Filter `studentId`, lalu ambil `COURSES` |
| Peserta kursus | `ENROLLMENTS` | Filter `courseId`, lalu ambil `USERS` |
| Materi per modul | `LESSONS` | Filter `moduleId`, urutkan `order` |
| Pengaturan akses kelas | `CLASS_MATERIAL_SETTINGS` | Cari `classId` unik; bila tidak ada, pakai default `open` |
| Materi tambahan kelas | `CLASS_MATERIALS` | Filter `classId` + `status`/`sortOrder`; sembunyikan `hidden` dari peserta |
| Prasyarat & jadwal materi | `CLASS_MATERIALS` + `CLASS_LESSON_PROGRESS` + `CLASS_SESSIONS` | Cek pelajaran tertaut selesai, `availableFrom`, dan tanggal/status sesi |
| Progres pelajaran kelas | `CLASS_LESSON_PROGRESS` | Filter `enrollmentId`, `classId`, atau `lessonId`; unik per enrollment+lesson |
| Progres materi tambahan | `CLASS_MATERIAL_PROGRESS` | Filter `enrollmentId` atau `materialId`; unik per enrollment+material |
| Progres gabungan | `CLASS_LESSON_PROGRESS` + `COMPLETED_LESSONS` | Baca keduanya agar data lama tetap terhitung |
| Nilai peserta | `GRADES` | Filter `enrollmentId` (atau `studentId` + `courseId`) |
| Roster nilai kelas | `GRADES` + `ENROLLMENTS` | Filter `enrollments.classId`, lalu komputasi per enrollment |
| Skema per kelas | `GRADE_SCHEMES` | Cari `classId` unik |
| Komponen skema | `GRADE_SCHEME_COMPONENTS` | Filter `schemeId`, urut `sortOrder` |
| Nilai komponen | `GRADE_COMPONENT_SCORES` | Filter `enrollmentId` atau `componentId`; unik per pasangan |
| Predikat nilai | `PREDICATE_RANGES` | Filter `isActive`, urut `sortOrder`, cari rentang skor |
| Koreksi nilai | `GRADE_CORRECTIONS` | Filter `gradeId`/`enrollmentId`, urut `createdAt` |
| Tugas per kelas | `ASSIGNMENTS` | Filter `classId` + `status`, urut `dueAt` |
| Submission per tugas | `SUBMISSIONS` | Filter `assignmentId`, atau `enrollmentId`/`studentId` |
| Riwayat review | `SUBMISSION_REVIEWS` | Filter `submissionId`, urut `createdAt` |
| Ujian per kelas | `EXAMS` | Filter `classId` + `status`, urut `examDate` |
| Hasil ujian | `EXAM_RESULTS` | Filter `examId` atau `enrollmentId`; unik per pasangan |
| Sertifikat peserta | `CERTIFICATES` | Filter `enrollmentId` (atau `studentId` + `courseId`) |
| Verifikasi sertifikat | `CERTIFICATES` | Cari `certificateNumber` (uppercase), cek `publish` |
| Progres | `COMPLETED_LESSONS` | Kombinasi `studentId` + `courseId` + `lessonId` |
| Audit | `AUDIT_LOGS` | Urut `createdAt` desc, filter aksi/entitas/aktor/tanggal |
| Notifikasi | `NOTIFICATIONS` | Urut `createdAt` desc, filter tipe/penerima/tanggal |

### 12.1 Index yang Dipakai di MySQL

- `categories(slug)` unik, `categories(status)`
- `users(email)` unik, `users(role)`
- `courses(instructor_id)`, `courses(status)`, `courses(category_id)`
- `curriculum_versions(course_id, code)` unik, `curriculum_versions(course_id, status)`, `curriculum_versions(status)`
- `classes(code)` unik, `classes(course_id)`, `classes(curriculum_version_id)`, `classes(instructor_id)`, `classes(status)`
- `class_instructor_histories(class_id, started_at)`, `class_instructor_histories(instructor_id)`
- `enrollments(student_id, class_id)` unik, `enrollments(student_id, course_id)`, `enrollments(course_id)`, `enrollments(class_id)`
- `class_sessions(class_id, session_number)` unik, `class_sessions(class_id, session_date)`, `class_sessions(status)`, `class_sessions(module_id)`, `class_sessions(instructor_id)`, `class_sessions(rescheduled_from_id)`
- `attendances(session_id, enrollment_id)` unik, `attendances(student_id)`, `attendances(enrollment_id)`, `attendances(status)`
- `attendance_corrections(attendance_id)`, `attendance_corrections(session_id)`, `attendance_corrections(enrollment_id)`
- `modules(course_id, sort_order)`, `modules(curriculum_version_id, sort_order)`, `modules(instructor_id)`
- `lessons(module_id, sort_order)`, `lessons(course_id)`, `lessons(curriculum_version_id, sort_order)`, `lessons(instructor_id)`
- `grades(enrollment_id)` unik, `grades(student_id, course_id)`, `grades(instructor_id)`, `grades(scheme_id)`, `grades(status)`
- `predicate_ranges(letter)` unik, `predicate_ranges(sort_order, min_score)`
- `grade_schemes(class_id)` unik, `grade_schemes(course_id)`, `grade_schemes(status)`
- `grade_scheme_components(scheme_id, sort_order)`, `grade_scheme_components(type)`
- `grade_component_scores(component_id, enrollment_id)` unik, `grade_component_scores(enrollment_id)`, `grade_component_scores(student_id)`, `grade_component_scores(status)`
- `grade_corrections(grade_id)`, `grade_corrections(enrollment_id)`
- `assignments(class_id, status)`, `assignments(curriculum_version_id)`, `assignments(module_id)`, `assignments(lesson_id)`, `assignments(created_by)`
- `assignment_files(assignment_id)`, `assignment_rubrics(assignment_id, sort_order)`
- `submissions(assignment_id, enrollment_id)` unik, `submissions(student_id)`, `submissions(status)`
- `submission_files(submission_id)`, `submission_reviews(submission_id, created_at)`
- `exams(class_id, status)`, `exams(curriculum_version_id)`, `exams(exam_date)`
- `exam_participants(exam_id, enrollment_id)` unik, `exam_participants(student_id)`
- `exam_results(exam_id, enrollment_id)` unik, `exam_results(student_id)`, `exam_results(status)`
- `certificates(certificate_number)` unik, `certificates(enrollment_id)` unik, `certificates(student_id, course_id)`, `certificates(status)`
- `completed_lessons(student_id, lesson_id)` unik, `completed_lessons(course_id)`
- `class_material_settings(class_id)` unik, `class_material_settings(updated_by)`
- `class_materials(class_id, status, sort_order)`, `class_materials(lesson_id)`, `class_materials(session_id)`, `class_materials(created_by)`
- `class_lesson_progress(enrollment_id, lesson_id)` unik, `class_lesson_progress(class_id)`, `class_lesson_progress(student_id)`, `class_lesson_progress(lesson_id)`
- `class_material_progress(enrollment_id, material_id)` unik, `class_material_progress(class_id)`, `class_material_progress(student_id)`, `class_material_progress(material_id)`
- `audit_logs(created_at)`, `audit_logs(actor_id)`, `audit_logs(entity)`
- `notifications(created_at)`, `notifications(type)`, `notifications(recipient_id)`

---

## 13. Skema Fisik MySQL (Terimplementasi)

Skema fisik ada di `api/schema.sql` dan dibuat oleh `api/install.php`. Pemetaan entitas logis ke tabel:

| Entitas Logis | Tabel | Primary Key | Foreign Key / Unique |
|---|---|---|---|
| `CATEGORIES` | `categories` | `id` | `slug` UNIQUE |
| `USERS` | `users` | `id` | `email` UNIQUE |
| `BRANCHES` | `branches` | `id` | `code` UNIQUE |
| `COURSES` | `courses` | `id` | `instructor_id` → `users.id` SET NULL; `category_id` → `categories.id` SET NULL |
| `CURRICULUM_VERSIONS` | `curriculum_versions` | `id` | `course_id` → `courses.id` CASCADE; `created_by`/`updated_by` → `users.id` SET NULL; UNIQUE(`course_id`, `code`) |
| `CLASSES` | `classes` | `id` | `course_id` → `courses.id` RESTRICT; `curriculum_version_id` → `curriculum_versions.id` RESTRICT; `branch_id` → `branches.id` SET NULL; `instructor_id` → `users.id` SET NULL; `code` UNIQUE |
| `CLASS_INSTRUCTOR_HISTORIES` | `class_instructor_histories` | `id` | `class_id` → `classes.id` CASCADE; `instructor_id`/`actor_id` → `users.id` SET NULL |
| `CLASS_MATERIAL_SETTINGS` | `class_material_settings` | `id` | `class_id` → `classes.id` CASCADE, UNIQUE; `updated_by` → `users.id` SET NULL |
| `CLASS_MATERIALS` | `class_materials` | `id` | `class_id` → `classes.id` CASCADE; `lesson_id` → `lessons.id` SET NULL; `session_id` → `class_sessions.id` SET NULL; `created_by`/`updated_by` → `users.id` SET NULL |
| `CLASS_LESSON_PROGRESS` | `class_lesson_progress` | `id` | `class_id`/`enrollment_id`/`student_id`/`lesson_id` → tabel terkait CASCADE; `completed_by` → `users.id` SET NULL; UNIQUE(`enrollment_id`, `lesson_id`) |
| `CLASS_MATERIAL_PROGRESS` | `class_material_progress` | `id` | `class_id`/`enrollment_id`/`student_id`/`material_id` → tabel terkait CASCADE; `completed_by` → `users.id` SET NULL; UNIQUE(`enrollment_id`, `material_id`) |
| `ENROLLMENTS` | `enrollments` | `id` | `student_id` → `users.id` CASCADE; `course_id` → `courses.id` CASCADE; `class_id` → `classes.id` CASCADE; UNIQUE(`student_id`, `class_id`) |
| `CLASS_SESSIONS` | `class_sessions` | `id` | `class_id` → `classes.id` CASCADE; `module_id` → `modules.id` SET NULL; `instructor_id`/`created_by`/`updated_by` → `users.id` SET NULL; `rescheduled_from_id` → `class_sessions.id` SET NULL; UNIQUE(`class_id`, `session_number`) |
| `ATTENDANCES` | `attendances` | `id` | `session_id` → `class_sessions.id` CASCADE; `enrollment_id` → `enrollments.id` CASCADE; `student_id` → `users.id` CASCADE; `recorded_by` → `users.id` SET NULL; UNIQUE(`session_id`, `enrollment_id`) |
| `ATTENDANCE_CORRECTIONS` | `attendance_corrections` | `id` | `attendance_id` → `attendances.id` CASCADE; `session_id` → `class_sessions.id` CASCADE; `enrollment_id` → `enrollments.id` CASCADE; `actor_id` → `users.id` SET NULL |
| `MODULES` | `modules` | `id` | `course_id` → `courses.id` CASCADE; `curriculum_version_id` → `curriculum_versions.id` CASCADE; `instructor_id` → `users.id` SET NULL |
| `LESSONS` | `lessons` | `id` | `module_id` → `modules.id` CASCADE; `course_id` → `courses.id` CASCADE; `curriculum_version_id` → `curriculum_versions.id` CASCADE; `instructor_id` SET NULL |
| `GRADES` | `grades` | `id` | `enrollment_id` → `enrollments.id` CASCADE; `scheme_id` → `grade_schemes.id` SET NULL; `student_id`/`course_id` CASCADE; `instructor_id`/`finalized_by` → `users.id` SET NULL; UNIQUE(`enrollment_id`) |
| `PREDICATE_RANGES` | `predicate_ranges` | `id` | `letter` UNIQUE; index (`sort_order`, `min_score`) |
| `GRADE_SCHEMES` | `grade_schemes` | `id` | `class_id` → `classes.id` CASCADE, UNIQUE; `course_id` → `courses.id` CASCADE; `created_by`/`updated_by` → `users.id` SET NULL |
| `GRADE_SCHEME_COMPONENTS` | `grade_scheme_components` | `id` | `scheme_id` → `grade_schemes.id` CASCADE |
| `GRADE_COMPONENT_SCORES` | `grade_component_scores` | `id` | `component_id` → `grade_scheme_components.id` CASCADE; `enrollment_id`/`student_id` CASCADE; `assessor_id` → `users.id` SET NULL; UNIQUE(`component_id`, `enrollment_id`) |
| `GRADE_CORRECTIONS` | `grade_corrections` | `id` | `grade_id` → `grades.id` CASCADE; `enrollment_id` → `enrollments.id` CASCADE; `actor_id` → `users.id` SET NULL |
| `ASSIGNMENTS` | `assignments` | `id` | `class_id`/`curriculum_version_id` CASCADE; `module_id`/`lesson_id`/`created_by`/`updated_by` SET NULL |
| `ASSIGNMENT_FILES` | `assignment_files` | `id` | `assignment_id` → `assignments.id` CASCADE |
| `ASSIGNMENT_RUBRICS` | `assignment_rubrics` | `id` | `assignment_id` → `assignments.id` CASCADE |
| `SUBMISSIONS` | `submissions` | `id` | `assignment_id`/`enrollment_id`/`student_id` CASCADE; `reviewed_by` SET NULL; UNIQUE(`assignment_id`, `enrollment_id`) |
| `SUBMISSION_FILES` | `submission_files` | `id` | `submission_id` → `submissions.id` CASCADE |
| `SUBMISSION_REVIEWS` | `submission_reviews` | `id` | `submission_id` CASCADE; `reviewer_id` SET NULL |
| `EXAMS` | `exams` | `id` | `class_id`/`curriculum_version_id` CASCADE; `created_by`/`updated_by` SET NULL |
| `EXAM_PARTICIPANTS` | `exam_participants` | `id` | `exam_id`/`enrollment_id`/`student_id` CASCADE; UNIQUE(`exam_id`, `enrollment_id`) |
| `EXAM_RESULTS` | `exam_results` | `id` | `exam_id`/`enrollment_id`/`student_id` CASCADE; `graded_by` SET NULL; UNIQUE(`exam_id`, `enrollment_id`) |
| `CERTIFICATES` | `certificates` | `id` | `enrollment_id` → `enrollments.id` CASCADE; `student_id`/`course_id` CASCADE; `approved_by_admin_id` SET NULL; UNIQUE(`enrollment_id`), `certificate_number` UNIQUE |
| `COMPLETED_LESSONS` | `completed_lessons` | `id` | UNIQUE(`student_id`, `lesson_id`); FK ke `users`, `courses`, `lessons` CASCADE |
| `AUDIT_LOGS` | `audit_logs` | `id` | `actor_id` → `users.id` SET NULL (polimorfik `entity` + `entity_id`) |
| `NOTIFICATIONS` | `notifications` | `id` | `recipient_id` → `users.id` SET NULL |
| `STAFF_ATTENDANCES` | `staff_attendances` | `id` | `user_id` → `users.id` CASCADE; unique (`user_id`, `attendance_date`, `kind`); index (`user_id`, `attendance_date`) |
| `STAFF_SHIFTS` | `staff_shifts` | `id` | `user_id` → `users.id` CASCADE; `created_by` → `users.id` SET NULL; unique (`user_id`, `shift_date`); index (`shift_date`), (`shift_type`) |
| `USER_DEVICES` | `user_devices` | `id` | `user_id` → `users.id` CASCADE; `token_hash` UNIQUE; index (`user_id`), (`expires_at`) |
| `SESSION` | Sesi PHP native | — | Tidak ada tabel `sessions`; cookie sesi hanya menyimpan ID sesi |

**Catatan implementasi:**

- `entity` + `entity_id` pada audit/notifikasi tetap polimorfik.
- Password disimpan sebagai `password_hash` (bcrypt/argon2 via `password_hash()`); login divalidasi di server.
- Operasi cascade multi-tabel memakai foreign key dan transaksi eksplisit (mis. hapus enrollment, pembuatan kelas + penguncian versi).
- `created_at`/`updated_at` memakai `DATE`/`DATETIME` UTC; snapshot mengirim ISO 8601.
- Nama instruktur, nama kategori, dan counter modul/materi tidak didenormalisasi di tabel; nilainya dihitung lewat JOIN/subquery saat snapshot dibangun.
- `classes.course_id` dan `classes.curriculum_version_id` memakai `ON DELETE RESTRICT` untuk melindungi riwayat akademik; penghapusan harus melalui alur aplikasi.
- Materi tambahan menyimpan `file_path` untuk file privat, tetapi serializer/snapshot hanya mengirim metadata (`fileName`, `fileSize`, `mimeType`) tanpa path maupun `content`.

---

## 14. Catatan Implementasi Mode Fallback `localStorage`

| Aspek | Perilaku Mode Fallback |
|---|---|
| Pembacaan | `ELIPS.read(key, fallback)` melakukan `JSON.parse`; error dikembalikan sebagai fallback |
| Penulisan | `ELIPS.write(key, value)` melakukan `JSON.stringify` |
| ID | `ELIPS.nextId(list)` = `max(id)+1` per koleksi |
| Normalisasi | `seed.js` menjalankan migrasi saat modul dimuat, termasuk normalisasi sertifikat, migrasi v3 → v4 (kategori, versi kurikulum, kelas migrasi, `curriculumVersionId` pada modul/materi), normalisasi Phase 4 (`classMaterialSettings`, `classMaterials`, `classLessonProgress`, `classMaterialProgress`), seed Phase 5 (tugas/ujian beserta submission, review, peserta, hasil), dan seed Phase 6 (`predicateRanges`, `gradeSchemes`, `gradeSchemeComponents`, `gradeComponentScores`, `gradeCorrections`); dilewati pada mode server |
| Default akses materi | Kelas tanpa baris pengaturan memakai default implisit `open`; `seed.js` tidak membuat baris untuk setiap kelas |
| File privat | Mode lokal tidak menyimpan file biner; hanya metadata. Path/konten materi tetap mengikuti aturan akses yang sama |
| Batas log | Audit dan notifikasi maksimal 1000 entri, terbaru di depan |
| Isolasi | Data per browser/perangkat; tidak sinkron antar perangkat |
| Keamanan | Tidak ada enkripsi; password demo diverifikasi di client tetapi tersimpan apa adanya di `localStorage`. Guard role juga client-side. Mode ini bukan untuk produksi |
| Sinkronisasi tab | Halaman peserta menyegarkan tampilan saat event `storage` atau saat tab aktif kembali |

Pada mode server, `api.js` mengganti `ELIPS.read`/`ELIPS.write` dengan cache snapshot dari MySQL, sehingga modul data dan UI tetap sinkron tanpa perubahan besar.

---

## 15. Ringkasan Relasi Inti

```text
CATEGORIES         1 ─── N COURSES
COURSES            1 ─── N CURRICULUM_VERSIONS 1 ─── N MODULES 1 ─── N LESSONS
COURSES            1 ─── N CLASSES N ─── 1 CURRICULUM_VERSIONS
USERS (instruktur) 1 ─── N CLASSES
CLASSES            1 ─── N CLASS_INSTRUCTOR_HISTORIES
CLASSES            1 ─── N CLASS_SESSIONS 1 ─── N ATTENDANCES 1 ─── N ATTENDANCE_CORRECTIONS
ENROLLMENTS        1 ─── N ATTENDANCES
USERS (peserta)    1 ─── N ATTENDANCES
USERS (peserta)    1 ─── N ENROLLMENTS N ─── 1 CLASSES
USERS (peserta)    1 ─── N ENROLLMENTS N ─── 1 COURSES
ENROLLMENTS        1 ─── 0..1 GRADES N ─── 0..1 GRADE_SCHEMES
CLASSES            1 ─── 0..1 GRADE_SCHEMES 1 ─── N GRADE_SCHEME_COMPONENTS 1 ─── N GRADE_COMPONENT_SCORES
ENROLLMENTS        1 ─── N GRADE_COMPONENT_SCORES / GRADE_CORRECTIONS
GRADES             1 ─── N GRADE_CORRECTIONS
ENROLLMENTS        1 ─── 0..1 CERTIFICATES
USERS (peserta)    1 ─── N GRADES / CERTIFICATES / COMPLETED_LESSONS
LESSONS            1 ─── N COMPLETED_LESSONS
CLASSES            1 ─── N ASSIGNMENTS 1 ─── N SUBMISSIONS 1 ─── N SUBMISSION_FILES / SUBMISSION_REVIEWS
CLASSES            1 ─── N EXAMS 1 ─── N EXAM_PARTICIPANTS / EXAM_RESULTS
ASSIGNMENTS        1 ─── N ASSIGNMENT_FILES / ASSIGNMENT_RUBRICS
ENROLLMENTS        1 ─── N SUBMISSIONS / EXAM_PARTICIPANTS / EXAM_RESULTS
CLASSES            1 ─── 0..1 CLASS_MATERIAL_SETTINGS
CLASSES            1 ─── N CLASS_MATERIALS
CLASS_MATERIALS    1 ─── N CLASS_MATERIAL_PROGRESS
ENROLLMENTS        1 ─── N CLASS_LESSON_PROGRESS / CLASS_MATERIAL_PROGRESS
CLASSES            1 ─── N CLASS_LESSON_PROGRESS / CLASS_MATERIAL_PROGRESS
LESSONS            1 ─── N CLASS_LESSON_PROGRESS
USERS              1 ─── N CLASS_LESSON_PROGRESS / CLASS_MATERIAL_PROGRESS
USERS              1 ─── N AUDIT_LOGS / NOTIFICATIONS
USERS (admin)      1 ─── N STAFF_ATTENDANCES
USERS (admin)      1 ─── N STAFF_SHIFTS
USERS              1 ─── N USER_DEVICES
USERS              1 ─── 0..1 SESSION
```

Dengan demikian, inti model data ELIPS LMS adalah:

- **Kursus** sebagai wadah jangka panjang, diklasifikasikan oleh **Kategori**.
- **Versi Kurikulum** sebagai struktur konten yang dapat direvisi; modul/materi terikat ke versi dan dikunci saat dipakai kelas.
- **Kelas/Angkatan** sebagai penyelenggaraan nyata, dengan satu versi kurikulum terkunci dan satu tentor aktif.
- **Sesi Pertemuan** sebagai jadwal pertemuan per kelas, dengan JP rencana/aktual dan status kehadiran sesi.
- **Presensi** sebagai catatan kehadiran peserta per sesi, dilengkapi audit koreksi beralasan.
- **Enrollment** sebagai penghubung peserta ke kelas/kursus.
- **Pengaturan Akses Materi** sebagai kebijakan per kelas (`open`/`sequential`/`scheduled`/`conditional`) dan kontrol persetujuan instruktur.
- **Materi Tambahan Kelas** sebagai bahan pendukung di luar kurikulum, dengan file privat dan status wajib/opsional/referensi.
- **Progres Kelas** (`ClassLessonProgress`/`ClassMaterialProgress`) sebagai bukti penyelesaian per enrollment.
- **Tugas** dan **Ujian** sebagai instrumen penilaian per kelas, dengan submission/review dan peserta/hasil ujian sebagai entitas turunan.
- **Predikat Nilai** sebagai konfigurasi global rentang huruf A–E yang dapat diubah Admin.
- **Skema Penilaian** sebagai konfigurasi bobot per kelas; **Komponen** mendefinisikan sumber nilai (tugas/ujian/manual), dan **Nilai Komponen** menyimpan skor per enrollment.
- **Nilai** dan **Sertifikat** sebagai hasil akhir pembelajaran per enrollment (per angkatan); nilai memiliki status `draft`/`incomplete`/`finalized` dan **Koreksi Nilai** append-only untuk perubahan setelah finalisasi.
- **CompletedLessons** sebagai bukti progres legacy yang tetap dibaca untuk kompatibilitas.
- **AuditLogs** dan **Notifications** sebagai catatan operasional yang bersifat historis.
- **StaffAttendances** sebagai absensi harian pribadi admin (maks. satu `in` + satu `out` per hari di UI), lengkap dengan foto, lokasi GPS otomatis, dan metadata perangkat.

---

## 16. Skema Fisik MySQL 8 (Terimplementasi)

Bagian ini mendokumentasikan skema yang benar-benar dieksekusi oleh `api/schema.sql` (database `elips_lms`, `ENGINE=InnoDB`, `utf8mb4_unicode_ci`, timezone koneksi UTC). Installer `api/install.php` membuat database, menjalankan skema, lalu mengisi data demo.

### 16.1 Ringkasan Tabel

| # | Tabel | Fungsi | Kolom kunci | Relasi utama |
|---|---|---|---|---|
| 1 | `categories` | Kategori kursus | `id`, `name`, `slug`, `status` | — |
| 2 | `users` | Akun Admin, Instruktur, Peserta | `id`, `email`, `password_hash`, `role`, `status` | — |
| 3 | `branches` | Cabang/unit penyelenggara | `id`, `code`, `name`, `city`, `status` | — |
| 4 | `courses` | Kursus dan instruktur pengampu | `id`, `title`, `category_id`, `instructor_id`, `status` | `category_id` → `categories.id`; `instructor_id` → `users.id` |
| 5 | `curriculum_versions` | Versi kurikulum per kursus | `id`, `course_id`, `code`, `status` | `course_id` → `courses.id`; `created_by`/`updated_by` → `users.id` |
| 6 | `modules` | Modul di dalam versi kurikulum | `id`, `course_id`, `curriculum_version_id`, `instructor_id`, `sort_order` | `course_id` → `courses.id`; `curriculum_version_id` → `curriculum_versions.id` |
| 7 | `lessons` | Materi di dalam modul | `id`, `module_id`, `course_id`, `curriculum_version_id`, `type`, `content`, `sort_order` | `module_id` → `modules.id`; `course_id` → `courses.id`; `curriculum_version_id` → `curriculum_versions.id` |
| 8 | `classes` | Kelas/angkatan | `id`, `code`, `course_id`, `curriculum_version_id`, `branch_id`, `instructor_id`, `status` | `course_id` → `courses.id`; `curriculum_version_id` → `curriculum_versions.id`; `branch_id` → `branches.id`; `instructor_id` → `users.id` |
| 9 | `enrollments` | Pendaftaran peserta ke kelas | `id`, `student_id`, `course_id`, `class_id`, `enrolled_at`, `status` | `student_id` → `users.id`; `course_id` → `courses.id`; `class_id` → `classes.id` |
| 10 | `class_instructor_histories` | Histori pergantian tentor | `id`, `class_id`, `instructor_id`, `started_at`, `ended_at` | `class_id` → `classes.id`; `instructor_id`/`actor_id` → `users.id` |
| 11 | `predicate_ranges` | Rentang predikat nilai huruf global | `id`, `letter`, `min_score`, `max_score`, `is_active` | `letter` UNIQUE |
| 12 | `grade_schemes` | Skema penilaian per kelas | `id`, `course_id`, `class_id`, `status`, `pass_score`, `rounding` | `class_id` → `classes.id` (unik); `course_id` → `courses.id`; `created_by`/`updated_by` → `users.id` |
| 13 | `grade_scheme_components` | Komponen berbobot skema | `id`, `scheme_id`, `type`, `name`, `weight`, `is_required` | `scheme_id` → `grade_schemes.id` |
| 14 | `grades` | Nilai akhir per enrollment + status finalisasi | `id`, `enrollment_id`, `scheme_id`, `score`, `final_score`, `grade`, `status` | `enrollment_id` → `enrollments.id` (unik); `scheme_id` → `grade_schemes.id`; `finalized_by` → `users.id` |
| 15 | `grade_component_scores` | Nilai komponen per enrollment | `id`, `component_id`, `enrollment_id`, `score`, `status` | `component_id` → `grade_scheme_components.id`; `enrollment_id` → `enrollments.id`; unik(`component_id`, `enrollment_id`) |
| 16 | `grade_corrections` | Audit koreksi nilai finalized | `id`, `grade_id`, `previous_score`, `new_score`, `reason`, `actor_id` | `grade_id` → `grades.id`; `enrollment_id` → `enrollments.id`; `actor_id` → `users.id` |
| 17 | `certificates` | E-sertifikat Draft/Publish | `id`, `enrollment_id`, `student_id`, `course_id`, `certificate_number`, `status` | `enrollment_id` → `enrollments.id`; `approved_by_admin_id` → `users.id` |
| 18 | `completed_lessons` | Progres materi per peserta (legacy) | `id`, `student_id`, `course_id`, `lesson_id`, `completed_at` | `student_id` → `users.id`; `lesson_id` → `lessons.id` |
| 19 | `audit_logs` | Jejak audit operasional | `id`, `action`, `entity`, `entity_id`, `actor_id`, `created_at` | `actor_id` → `users.id` |
| 20 | `class_sessions` | Sesi pertemuan per kelas | `id`, `class_id`, `session_number`, `session_date`, `planned_jp`, `actual_jp`, `status` | `class_id` → `classes.id`; `module_id` → `modules.id`; `instructor_id` → `users.id`; `rescheduled_from_id` → `class_sessions.id` |
| 21 | `attendances` | Presensi peserta per sesi | `id`, `session_id`, `enrollment_id`, `student_id`, `status` | `session_id` → `class_sessions.id`; `enrollment_id` → `enrollments.id`; `student_id` → `users.id`; `recorded_by` → `users.id` |
| 22 | `attendance_corrections` | Audit koreksi presensi | `id`, `attendance_id`, `old_status`, `new_status`, `reason`, `corrected_at` | `attendance_id` → `attendances.id`; `session_id`/`enrollment_id` → tabel terkait; `actor_id` → `users.id` |
| 23 | `class_material_settings` | Pengaturan akses materi per kelas | `id`, `class_id`, `access_mode`, `require_instructor_approval` | `class_id` → `classes.id` (unik); `updated_by` → `users.id` |
| 24 | `class_materials` | Materi tambahan kelas | `id`, `class_id`, `lesson_id`, `session_id`, `type`, `status`, `sort_order` | `class_id` → `classes.id`; `lesson_id` → `lessons.id`; `session_id` → `class_sessions.id` |
| 25 | `class_lesson_progress` | Progres pelajaran per enrollment | `id`, `class_id`, `enrollment_id`, `lesson_id`, `completed_by` | `enrollment_id` → `enrollments.id`; `lesson_id` → `lessons.id`; unik(`enrollment_id`, `lesson_id`) |
| 26 | `class_material_progress` | Progres materi tambahan per enrollment | `id`, `class_id`, `enrollment_id`, `material_id`, `completed_by` | `enrollment_id` → `enrollments.id`; `material_id` → `class_materials.id`; unik(`enrollment_id`, `material_id`) |
| 27 | `assignments` | Tugas per kelas | `id`, `class_id`, `curriculum_version_id`, `title`, `type`, `status` | `class_id` → `classes.id`; `curriculum_version_id` → `curriculum_versions.id`; `module_id`/`lesson_id` → tabel terkait |
| 28 | `assignment_files` | Lampiran tugas | `id`, `assignment_id`, `file_name`, `file_path` | `assignment_id` → `assignments.id` |
| 29 | `assignment_rubrics` | Rubrik penilaian tugas | `id`, `assignment_id`, `criterion`, `max_score`, `sort_order` | `assignment_id` → `assignments.id` |
| 30 | `submissions` | Submission peserta per tugas | `id`, `assignment_id`, `enrollment_id`, `student_id`, `status`, `score` | `assignment_id`/`enrollment_id`/`student_id` → tabel terkait; unik(`assignment_id`, `enrollment_id`) |
| 31 | `submission_files` | Berkas jawaban submission | `id`, `submission_id`, `file_name`, `file_path` | `submission_id` → `submissions.id` |
| 32 | `submission_reviews` | Riwayat review submission | `id`, `submission_id`, `reviewer_id`, `action`, `score` | `submission_id` → `submissions.id`; `reviewer_id` → `users.id` |
| 33 | `exams` | Ujian per kelas | `id`, `class_id`, `curriculum_version_id`, `title`, `type`, `status` | `class_id` → `classes.id`; `curriculum_version_id` → `curriculum_versions.id` |
| 34 | `exam_participants` | Roster peserta ujian | `id`, `exam_id`, `enrollment_id`, `student_id`, `status` | `exam_id`/`enrollment_id`/`student_id` → tabel terkait; unik(`exam_id`, `enrollment_id`) |
| 35 | `exam_results` | Hasil ujian per peserta | `id`, `exam_id`, `enrollment_id`, `student_id`, `score`, `status` | `exam_id`/`enrollment_id`/`student_id` → tabel terkait; unik(`exam_id`, `enrollment_id`) |
| 36 | `notifications` | Outbox notifikasi email simulasi | `id`, `type`, `recipient_id`, `recipient_email`, `subject`, `created_at` | `recipient_id` → `users.id` |
| 37 | `staff_attendances` | Absensi harian pribadi staf/admin (kamera + metadata) | `id`, `user_id`, `kind`, `attendance_date`, `taken_at`, `photo`, `latitude`, `longitude` | `user_id` → `users.id` (`ON DELETE CASCADE`) |
| 38 | `staff_shifts` | Jadwal shift per karyawan per tanggal (Pagi/Sore/custom) | `id`, `user_id`, `shift_date`, `shift_type`, `start_time`, `end_time`, `note` | `user_id` → `users.id` (`ON DELETE CASCADE`); `created_by` → `users.id` (`SET NULL`); unik(`user_id`, `shift_date`) |
| 39 | `user_devices` | Token "ingat saya" per perangkat (hash SHA-256) | `id`, `user_id`, `token_hash`, `device_label`, `expires_at`, `revoked_at`, `revoked_reason` | `user_id` → `users.id` (`ON DELETE CASCADE`); `token_hash` UNIQUE |

Sesi login **tidak** memakai tabel: autentikasi memakai sesi PHP native (`$_SESSION`) dengan cookie `HttpOnly` dan `SameSite=Lax`. Token "ingat saya" disimpan di `user_devices` (hanya hash) dan dikirim sebagai cookie `elips_remember` yang juga `HttpOnly` (+ `Secure` saat HTTPS).

### 16.2 Definisi Kolom

**`users`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `name` | `VARCHAR(150)` | tidak | — | Nama lengkap |
| `email` | `VARCHAR(190)` | tidak | — | Unik, dipakai login |
| `phone` | `VARCHAR(40)` | tidak | `''` | Nomor telepon |
| `password_hash` | `VARCHAR(255)` | tidak | — | `password_hash()`; tidak pernah masuk snapshot |
| `role` | `ENUM('admin','instruktur','peserta','karyawan')` | tidak | `'peserta'` | Role akun; `karyawan` hanya untuk absensi |
| `status` | `ENUM('aktif','nonaktif')` | tidak | `'aktif'` | Status akun |
| `session_version` | `INT UNSIGNED` | tidak | `0` | Versi sesi; naik saat login baru/ganti password/nonaktif/reset perangkat |
| `created_at` | `DATE` | tidak | `CURRENT_DATE` | Tanggal dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir (UTC) |

**`categories`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `name` | `VARCHAR(100)` | tidak | — | Nama kategori |
| `slug` | `VARCHAR(120)` | tidak | — | Slug unik |
| `status` | `ENUM('active','inactive')` | tidak | `'active'` | Status kategori |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

**`branches`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `code` | `VARCHAR(40)` | tidak | — | Kode cabang unik |
| `name` | `VARCHAR(150)` | tidak | — | Nama cabang |
| `address` | `VARCHAR(255)` | tidak | `''` | Alamat |
| `city` | `VARCHAR(100)` | tidak | `''` | Kota |
| `phone` | `VARCHAR(40)` | tidak | `''` | Telepon |
| `manager` | `VARCHAR(150)` | tidak | `''` | Manajer/penanggung jawab |
| `status` | `ENUM('active','inactive')` | tidak | `'active'` | Status cabang |
| `notes` | `TEXT` | tidak | — | Catatan |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

**`courses`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `title` | `VARCHAR(200)` | tidak | — | Judul kursus |
| `category` | `VARCHAR(100)` | tidak | — | Nama kategori (denormalisasi) |
| `category_id` | `INT UNSIGNED` | ya | `NULL` | Kategori terstruktur |
| `description` | `TEXT` | tidak | — | Deskripsi |
| `instructor_id` | `INT UNSIGNED` | ya | `NULL` | Satu instruktur pengampu |
| `status` | `ENUM('draft','published')` | tidak | `'draft'` | Lifecycle kursus |
| `created_at` | `DATE` | tidak | `CURRENT_DATE` | Tanggal dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

**`curriculum_versions`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `course_id` | `INT UNSIGNED` | tidak | — | Kursus pemilik |
| `code` | `VARCHAR(30)` | tidak | — | Kode versi unik per kursus |
| `name` | `VARCHAR(150)` | tidak | — | Nama versi |
| `description` | `TEXT` | tidak | — | Deskripsi |
| `status` | `ENUM('draft','published','locked','retired')` | tidak | `'draft'` | Lifecycle versi |
| `effective_date` | `DATE` | ya | `NULL` | Tanggal efektif |
| `notes` | `TEXT` | tidak | — | Catatan perubahan |
| `created_by` | `INT UNSIGNED` | ya | `NULL` | Pembuat |
| `updated_by` | `INT UNSIGNED` | ya | `NULL` | Pengubah terakhir |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

**`classes`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `code` | `VARCHAR(40)` | tidak | — | Kode kelas unik |
| `name` | `VARCHAR(150)` | tidak | — | Nama kelas/angkatan |
| `course_id` | `INT UNSIGNED` | tidak | — | Kursus |
| `curriculum_version_id` | `INT UNSIGNED` | tidak | — | Versi kurikulum terkunci |
| `branch_id` | `INT UNSIGNED` | ya | `NULL` | Cabang penyelenggara; FK `SET NULL` |
| `instructor_id` | `INT UNSIGNED` | ya | `NULL` | Tentor aktif |
| `period_start` | `DATE` | ya | `NULL` | Tanggal mulai |
| `period_end` | `DATE` | ya | `NULL` | Tanggal selesai |
| `quota` | `INT UNSIGNED` | tidak | `0` | Kuota peserta |
| `location` | `VARCHAR(150)` | tidak | `''` | Lokasi |
| `room` | `VARCHAR(100)` | tidak | `''` | Ruang |
| `schedule_note` | `VARCHAR(255)` | tidak | `''` | Catatan jadwal |
| `planned_jp` | `DECIMAL(6,2)` | tidak | `0` | JP rencana |
| `status` | `ENUM('draft','open','ready','ongoing','completed','cancelled','archived')` | tidak | `'draft'` | Lifecycle kelas |
| `legacy_import` | `TINYINT(1)` | tidak | `0` | Penanda kelas migrasi |
| `notes` | `TEXT` | tidak | — | Catatan |
| `created_by` | `INT UNSIGNED` | ya | `NULL` | Pembuat |
| `updated_by` | `INT UNSIGNED` | ya | `NULL` | Pengubah terakhir |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

**`class_instructor_histories`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `class_id` | `INT UNSIGNED` | tidak | — | Kelas |
| `instructor_id` | `INT UNSIGNED` | ya | `NULL` | Tentor pada periode ini |
| `started_at` | `DATE` | tidak | — | Tanggal mulai |
| `ended_at` | `DATE` | ya | `NULL` | Tanggal selesai; `NULL` = masih aktif |
| `reason` | `TEXT` | tidak | — | Alasan pergantian |
| `actor_id` | `INT UNSIGNED` | ya | `NULL` | Admin pelaku |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |

**`modules`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `course_id` | `INT UNSIGNED` | tidak | — | Kursus induk |
| `curriculum_version_id` | `INT UNSIGNED` | tidak | — | Versi kurikulum pemilik modul |
| `instructor_id` | `INT UNSIGNED` | ya | `NULL` | Instruktur pemilik konten |
| `title` | `VARCHAR(200)` | tidak | — | Judul modul |
| `description` | `TEXT` | tidak | — | Deskripsi |
| `sort_order` | `INT UNSIGNED` | tidak | `1` | Urutan dalam versi |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

**`lessons`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `module_id` | `INT UNSIGNED` | tidak | — | Modul induk |
| `course_id` | `INT UNSIGNED` | tidak | — | Kursus induk (denormalisasi untuk query cepat) |
| `curriculum_version_id` | `INT UNSIGNED` | tidak | — | Versi kurikulum pemilik materi |
| `instructor_id` | `INT UNSIGNED` | ya | `NULL` | Instruktur pemilik konten |
| `title` | `VARCHAR(200)` | tidak | — | Judul materi |
| `type` | `ENUM('text','video','file','link')` | tidak | `'text'` | Jenis materi |
| `content` | `LONGTEXT` | tidak | — | HTML/URL; HTML teks disanitasi server |
| `sort_order` | `INT UNSIGNED` | tidak | `1` | Urutan dalam modul |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

**`enrollments`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta |
| `course_id` | `INT UNSIGNED` | tidak | — | Kursus |
| `class_id` | `INT UNSIGNED` | ya | `NULL` | Kelas/angkatan; `NULL` untuk data migrasi yang belum dipetakan |
| `enrolled_by` | `VARCHAR(50)` | tidak | `'admin'` | Sumber penugasan |
| `enrolled_at` | `DATE` | tidak | `CURRENT_DATE` | Tanggal penugasan |
| `status` | `ENUM('pending','active','completed','withdrawn','suspended','cancelled','dihentikan')` | tidak | `'active'` | Status penugasan; `dihentikan` = alias lama |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

Catatan: unique key `uq_enrollments_student_class (student_id, class_id)` membuat satu peserta hanya terdaftar sekali per kelas; `course_id` didenormalisasi dari `classes.course_id`.

**`class_sessions`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `class_id` | `INT UNSIGNED` | tidak | — | Kelas pemilik sesi |
| `session_number` | `INT UNSIGNED` | tidak | — | Nomor pertemuan unik per kelas |
| `title` | `VARCHAR(200)` | tidak | `''` | Judul/topik sesi |
| `module_id` | `INT UNSIGNED` | ya | `NULL` | Modul terkait |
| `session_date` | `DATE` | tidak | — | Tanggal sesi |
| `start_time` | `TIME` | ya | `NULL` | Jam mulai |
| `end_time` | `TIME` | ya | `NULL` | Jam selesai |
| `location` | `VARCHAR(150)` | tidak | `''` | Lokasi |
| `room` | `VARCHAR(100)` | tidak | `''` | Ruang |
| `instructor_id` | `INT UNSIGNED` | ya | `NULL` | Tentor sesi |
| `planned_jp` | `DECIMAL(6,2)` | tidak | `0` | JP rencana |
| `actual_jp` | `DECIMAL(6,2)` | tidak | `0` | JP aktual |
| `status` | `ENUM('scheduled','confirmed','completed','rescheduled','cancelled','no_show')` | tidak | `'scheduled'` | Lifecycle sesi |
| `rescheduled_from_id` | `INT UNSIGNED` | ya | `NULL` | Sesi asal untuk sesi pengganti |
| `notes` | `TEXT` | tidak | — | Catatan |
| `created_by` | `INT UNSIGNED` | ya | `NULL` | Pembuat |
| `updated_by` | `INT UNSIGNED` | ya | `NULL` | Pengubah terakhir |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

Catatan: `uq_class_sessions_number (class_id, session_number)` menjaga nomor pertemuan unik per kelas.

**`attendances`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `session_id` | `INT UNSIGNED` | tidak | — | Sesi terkait |
| `enrollment_id` | `INT UNSIGNED` | tidak | — | Enrollment peserta |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta (denormalisasi) |
| `status` | `ENUM('present','late','permission','sick','absent','excused')` | tidak | `'absent'` | Status presensi |
| `recorded_by` | `INT UNSIGNED` | ya | `NULL` | Pencatat presensi |
| `recorded_at` | `DATETIME` | ya | `NULL` | Waktu pencatatan |
| `note` | `TEXT` | tidak | — | Catatan |
| `corrected_at` | `DATETIME` | ya | `NULL` | Waktu koreksi terakhir |
| `correction_reason` | `TEXT` | tidak | — | Alasan koreksi terakhir |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

Catatan: `uq_attendances_session_enrollment (session_id, enrollment_id)` mencegah duplikasi presensi.

**`attendance_corrections`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `attendance_id` | `INT UNSIGNED` | tidak | — | Presensi yang dikoreksi |
| `session_id` | `INT UNSIGNED` | tidak | — | Sesi terkait (denormalisasi) |
| `enrollment_id` | `INT UNSIGNED` | tidak | — | Enrollment terkait (denormalisasi) |
| `old_status` | `VARCHAR(20)` | tidak | `''` | Status sebelum koreksi |
| `new_status` | `VARCHAR(20)` | tidak | — | Status setelah koreksi |
| `actor_id` | `INT UNSIGNED` | ya | `NULL` | Pelaku koreksi |
| `actor_name` | `VARCHAR(150)` | tidak | `'Sistem'` | Snapshot nama aktor |
| `actor_role` | `VARCHAR(30)` | tidak | `'sistem'` | Snapshot role aktor |
| `reason` | `TEXT` | tidak | — | Alasan koreksi |
| `corrected_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu koreksi |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |

**`grades`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `enrollment_id` | `INT UNSIGNED` | ya | `NULL` | Enrollment terkait; unik per enrollment |
| `scheme_id` | `INT UNSIGNED` | ya | `NULL` | Skema penilaian saat komputasi/finalisasi |
| `course_id` | `INT UNSIGNED` | tidak | — | Kursus |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta |
| `instructor_id` | `INT UNSIGNED` | ya | `NULL` | Instruktur penilai |
| `score` | `TINYINT UNSIGNED` | tidak | — | Nilai legacy 0–100 (dibulatkan) |
| `final_score` | `DECIMAL(6,2)` | ya | `NULL` | Nilai akhir desimal hasil komputasi skema |
| `grade` | `CHAR(2)` | tidak | — | Huruf predikat (default A, A-, B+, …) |
| `status` | `ENUM('draft','incomplete','finalized')` | tidak | `'draft'` | Status komputasi/finalisasi |
| `components_snapshot` | `LONGTEXT` | ya | `NULL` | JSON komponen+bobot saat finalisasi |
| `note` | `TEXT` | tidak | — | Catatan penilaian |
| `finalized_by` | `INT UNSIGNED` | ya | `NULL` | Aktor finalisasi |
| `finalized_at` | `DATETIME` | ya | `NULL` | Waktu finalisasi |
| `computed_at` | `DATETIME` | ya | `NULL` | Waktu komputasi terakhir |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

Catatan: `uq_grades_enrollment (enrollment_id)` menggantikan keunikan lama `(student_id, course_id)`, sehingga nilai untuk angkatan berbeda tersimpan terpisah. `status = finalized` mengunci nilai; perubahan hanya lewat `grade_corrections`.

**`predicate_ranges`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `letter` | `VARCHAR(4)` | tidak | — | Huruf predikat; UNIQUE |
| `label` | `VARCHAR(60)` | tidak | `''` | Deskripsi |
| `min_score` | `DECIMAL(5,2)` | tidak | — | Batas bawah inklusif |
| `max_score` | `DECIMAL(5,2)` | tidak | — | Batas atas inklusif |
| `sort_order` | `INT UNSIGNED` | tidak | `0` | Urutan tampil |
| `is_active` | `TINYINT(1)` | tidak | `1` | Aktif/tidak |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu diperbarui |

**`grade_schemes`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `course_id` | `INT UNSIGNED` | tidak | — | Kursus (denormalisasi) |
| `class_id` | `INT UNSIGNED` | tidak | — | Kelas; UNIQUE (satu skema per kelas) |
| `name` | `VARCHAR(150)` | tidak | — | Nama skema |
| `status` | `ENUM('draft','published','archived')` | tidak | `'draft'` | Lifecycle skema |
| `pass_score` | `DECIMAL(5,2)` | tidak | `60.00` | Ambang lulus |
| `rounding` | `ENUM('half_up','half_down','floor','ceil')` | tidak | `'half_up'` | Mode pembulatan nilai akhir |
| `notes` | `TEXT` | tidak | — | Catatan |
| `created_by` | `INT UNSIGNED` | ya | `NULL` | Pembuat |
| `updated_by` | `INT UNSIGNED` | ya | `NULL` | Pengubah terakhir |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu diperbarui |

**`grade_scheme_components`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `scheme_id` | `INT UNSIGNED` | tidak | — | Skema pemilik |
| `type` | `ENUM('assignment','exam','manual')` | tidak | `'manual'` | Sumber nilai |
| `name` | `VARCHAR(150)` | tidak | — | Nama komponen |
| `description` | `TEXT` | tidak | — | Deskripsi |
| `weight` | `DECIMAL(5,2)` | tidak | `0.00` | Bobot; total 100 saat publish |
| `aggregation` | `ENUM('weighted','average')` | tidak | `'weighted'` | Agregasi sumber |
| `max_score` | `DECIMAL(6,2)` | tidak | `100.00` | Skor maksimum |
| `is_required` | `TINYINT(1)` | tidak | `1` | Wajib/nilai opsional |
| `sort_order` | `INT UNSIGNED` | tidak | `1` | Urutan tampil |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu diperbarui |

**`grade_component_scores`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `component_id` | `INT UNSIGNED` | tidak | — | Komponen; unik bersama enrollment |
| `enrollment_id` | `INT UNSIGNED` | tidak | — | Enrollment |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta |
| `score` | `DECIMAL(6,2)` | ya | `NULL` | Skor; `NULL` = belum dinilai |
| `status` | `ENUM('not_assessed','graded','excused','missing')` | tidak | `'not_assessed'` | Status nilai komponen |
| `assessor_id` | `INT UNSIGNED` | ya | `NULL` | Penilai |
| `assessed_at` | `DATETIME` | ya | `NULL` | Waktu dinilai |
| `note` | `TEXT` | tidak | — | Catatan/alasan pengecualian |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu diperbarui |

Catatan: `uq_grade_component_scores_component_enrollment (component_id, enrollment_id)` memastikan satu nilai per komponen per enrollment.

**`grade_corrections`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `grade_id` | `INT UNSIGNED` | tidak | — | Nilai yang dikoreksi |
| `enrollment_id` | `INT UNSIGNED` | ya | `NULL` | Enrollment (denormalisasi) |
| `previous_score` | `DECIMAL(6,2)` | ya | `NULL` | Nilai akhir sebelum koreksi |
| `new_score` | `DECIMAL(6,2)` | ya | `NULL` | Nilai akhir sesudah koreksi |
| `previous_letter` | `VARCHAR(4)` | ya | `NULL` | Huruf sebelum koreksi |
| `new_letter` | `VARCHAR(4)` | ya | `NULL` | Huruf sesudah koreksi |
| `reason` | `TEXT` | tidak | — | Alasan, minimal 5 karakter |
| `actor_id` | `INT UNSIGNED` | ya | `NULL` | Aktor koreksi |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu koreksi |

**`certificates`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta |
| `course_id` | `INT UNSIGNED` | tidak | — | Kursus |
| `enrollment_id` | `INT UNSIGNED` | ya | `NULL` | Enrollment terkait; unik per enrollment |
| `certificate_number` | `VARCHAR(80)` | tidak | — | Nomor unik (`CERT-DRAFT-*` → `CERT-YYYY-*`) |
| `status` | `ENUM('draft','publish')` | tidak | `'draft'` | Hanya `publish` tampil ke peserta |
| `grade` | `CHAR(2)` | ya | `NULL` | Huruf mutu saat terbit |
| `score` | `TINYINT UNSIGNED` | ya | `NULL` | Nilai saat terbit |
| `issued_by_instructor_at` | `DATETIME` | ya | `NULL` | Waktu draft dibuat instruktur |
| `approved_by_admin_id` | `INT UNSIGNED` | ya | `NULL` | Admin penyetuju |
| `approved_at` | `DATETIME` | ya | `NULL` | Waktu approval |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu perubahan terakhir |

Catatan: `uq_certificates_enrollment (enrollment_id)` memastikan satu sertifikat per enrollment/angkatan, sedangkan `uq_certificates_number` menjaga nomor sertifikat tetap unik.

**`completed_lessons`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta |
| `course_id` | `INT UNSIGNED` | tidak | — | Kursus |
| `lesson_id` | `INT UNSIGNED` | tidak | — | Materi yang diselesaikan |
| `completed_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu selesai |

**`audit_logs`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `action` | `VARCHAR(30)` | tidak | — | Aksi (`create`, `update`, `publish`, …) |
| `entity` | `VARCHAR(30)` | tidak | — | Jenis entitas (polimorfik) |
| `entity_id` | `INT UNSIGNED` | ya | `NULL` | ID entitas (polimorfik) |
| `entity_label` | `VARCHAR(255)` | tidak | `''` | Label entitas untuk tampilan |
| `actor_id` | `INT UNSIGNED` | ya | `NULL` | Aktor; `NULL` = Sistem |
| `actor_name` | `VARCHAR(150)` | tidak | `'Sistem'` | Snapshot nama aktor |
| `actor_role` | `VARCHAR(30)` | tidak | `'sistem'` | Snapshot role aktor |
| `detail` | `TEXT` | tidak | — | Keterangan perubahan |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu kejadian |

**`notifications`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `type` | `VARCHAR(40)` | tidak | — | `course_published`, `certificate_published` |
| `channel` | `VARCHAR(20)` | tidak | `'email'` | Kanal |
| `simulated` | `TINYINT(1)` | tidak | `1` | Penanda email simulasi |
| `status` | `VARCHAR(20)` | tidak | `'sent'` | Status kirim |
| `recipient_id` | `INT UNSIGNED` | ya | `NULL` | Penerima |
| `recipient_name` | `VARCHAR(150)` | tidak | `''` | Snapshot nama penerima |
| `recipient_email` | `VARCHAR(190)` | tidak | `''` | Snapshot email penerima |
| `recipient_role` | `VARCHAR(30)` | tidak | `'peserta'` | Snapshot role penerima |
| `subject` | `VARCHAR(255)` | tidak | `''` | Subjek email |
| `body` | `TEXT` | tidak | — | Isi email |
| `entity` | `VARCHAR(30)` | tidak | `''` | Entitas terkait (polimorfik) |
| `entity_id` | `INT UNSIGNED` | ya | `NULL` | ID entitas terkait |
| `entity_label` | `VARCHAR(255)` | tidak | `''` | Label entitas |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |

**`class_material_settings`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `class_id` | `INT UNSIGNED` | tidak | — | Kelas; unik (satu pengaturan per kelas) |
| `access_mode` | `ENUM('open','sequential','scheduled','conditional')` | tidak | `'open'` | Mode akses materi |
| `require_instructor_approval` | `TINYINT(1)` | tidak | `0` | Wajib konfirmasi instruktur |
| `notes` | `TEXT` | tidak | `''` | Catatan kebijakan |
| `updated_by` | `INT UNSIGNED` | ya | `NULL` | Aktor terakhir |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu diperbarui |

**`class_materials`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `class_id` | `INT UNSIGNED` | tidak | — | Kelas tujuan |
| `lesson_id` | `INT UNSIGNED` | ya | `NULL` | Pelajaran kurikulum terkait |
| `session_id` | `INT UNSIGNED` | ya | `NULL` | Sesi kelas terkait |
| `title` | `VARCHAR(200)` | tidak | — | Judul materi |
| `description` | `TEXT` | tidak | `''` | Deskripsi |
| `type` | `ENUM('text','document','video','link','file')` | tidak | `'text'` | Jenis materi |
| `content` | `LONGTEXT` | tidak | `''` | Konten/URL; tidak masuk snapshot |
| `file_name` | `VARCHAR(255)` | tidak | `''` | Nama file |
| `file_path` | `VARCHAR(500)` | tidak | `''` | Path file privat; tidak diekspos |
| `file_size` | `BIGINT UNSIGNED` | ya | `NULL` | Ukuran byte |
| `mime_type` | `VARCHAR(120)` | tidak | `''` | MIME type |
| `access_level` | `ENUM('required','optional','reference')` | tidak | `'optional'` | Status materi |
| `available_from` | `DATE` | ya | `NULL` | Tanggal minimum akses |
| `status` | `ENUM('active','hidden')` | tidak | `'active'` | Status tampil |
| `reason` | `TEXT` | tidak | `''` | Alasan perubahan status |
| `sort_order` | `INT UNSIGNED` | tidak | `1` | Urutan tampil |
| `created_by` | `INT UNSIGNED` | ya | `NULL` | Pembuat |
| `updated_by` | `INT UNSIGNED` | ya | `NULL` | Pengubah terakhir |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu diperbarui |

**`class_lesson_progress`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `class_id` | `INT UNSIGNED` | tidak | — | Kelas |
| `enrollment_id` | `INT UNSIGNED` | tidak | — | Enrollment |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta (denormalisasi) |
| `lesson_id` | `INT UNSIGNED` | tidak | — | Pelajaran selesai |
| `completed_by` | `INT UNSIGNED` | ya | `NULL` | Peserta/staf yang mengonfirmasi |
| `completed_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu selesai |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |

**`class_material_progress`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `class_id` | `INT UNSIGNED` | tidak | — | Kelas |
| `enrollment_id` | `INT UNSIGNED` | tidak | — | Enrollment |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta (denormalisasi) |
| `material_id` | `INT UNSIGNED` | tidak | — | Materi tambahan selesai |
| `completed_by` | `INT UNSIGNED` | ya | `NULL` | Peserta/staf yang mengonfirmasi |
| `completed_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu selesai |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |

**`assignments`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `class_id` | `INT UNSIGNED` | tidak | — | Kelas pemilik |
| `curriculum_version_id` | `INT UNSIGNED` | tidak | — | Versi kurikulum acuan |
| `module_id` / `lesson_id` | `INT UNSIGNED` | ya | `NULL` | Modul/materi terkait |
| `title` | `VARCHAR(200)` | tidak | — | Judul tugas |
| `instructions` | `LONGTEXT` | tidak | — | Instruksi |
| `type` | `ENUM('file_upload','text_submission','in_class_practice','external_reference')` | tidak | `'text_submission'` | Jenis tugas |
| `open_at` / `due_at` | `DATETIME` | ya | `NULL` | Jendela pengumpulan |
| `max_score` | `DECIMAL(6,2)` | tidak | `100.00` | Skor maksimum |
| `weight` | `DECIMAL(5,2)` | tidak | `0.00` | Bobot legacy |
| `require_file` | `TINYINT(1)` | tidak | `0` | Wajib lampiran |
| `allowed_types` | `VARCHAR(255)` | tidak | `''` | Ekstensi diizinkan |
| `max_file_size` | `INT UNSIGNED` | ya | `NULL` | Ukuran maksimum |
| `status` | `ENUM('draft','published','closed','archived')` | tidak | `'draft'` | Lifecycle tugas |
| `created_by` / `updated_by` | `INT UNSIGNED` | ya | `NULL` | Aktor |
| `created_at` / `updated_at` | `DATETIME` | tidak/ya | `CURRENT_TIMESTAMP`/`NULL` | Waktu |

**`assignment_files`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `assignment_id` | `INT UNSIGNED` | tidak | — | Tugas |
| `file_name` / `file_path` | `VARCHAR` | tidak | `''` | Nama & path privat |
| `file_size` | `BIGINT UNSIGNED` | ya | `NULL` | Ukuran byte |
| `mime_type` | `VARCHAR(120)` | tidak | `''` | MIME |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |

**`assignment_rubrics`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `assignment_id` | `INT UNSIGNED` | tidak | — | Tugas |
| `criterion` | `VARCHAR(200)` | tidak | — | Kriteria |
| `description` | `TEXT` | tidak | — | Penjelasan |
| `max_score` | `DECIMAL(6,2)` | tidak | `100.00` | Skor maksimum |
| `sort_order` | `INT UNSIGNED` | tidak | `1` | Urutan |
| `created_at` / `updated_at` | `DATETIME` | tidak/ya | `CURRENT_TIMESTAMP`/`NULL` | Waktu |

**`submissions`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `assignment_id` | `INT UNSIGNED` | tidak | — | Tugas; unik bersama enrollment |
| `enrollment_id` | `INT UNSIGNED` | tidak | — | Enrollment |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta |
| `status` | `ENUM('submitted','late','reviewed','revision_requested','resubmitted','accepted')` | tidak | `'submitted'` | Status submission |
| `text_answer` | `LONGTEXT` | tidak | — | Jawaban teks |
| `submitted_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu kirim |
| `is_late` | `TINYINT(1)` | tidak | `0` | Penanda terlambat |
| `score` | `DECIMAL(6,2)` | ya | `NULL` | Nilai |
| `feedback` | `TEXT` | tidak | — | Umpan balik |
| `reviewed_by` / `reviewed_at` | `INT UNSIGNED`/`DATETIME` | ya | `NULL` | Peninjau & waktu |
| `revision_count` | `INT UNSIGNED` | tidak | `0` | Jumlah revisi |
| `created_at` / `updated_at` | `DATETIME` | tidak/ya | `CURRENT_TIMESTAMP`/`NULL` | Waktu |

**`submission_files`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `submission_id` | `INT UNSIGNED` | tidak | — | Submission |
| `file_name` / `file_path` | `VARCHAR` | tidak | `''` | Nama & path privat |
| `file_size` | `BIGINT UNSIGNED` | ya | `NULL` | Ukuran byte |
| `mime_type` | `VARCHAR(120)` | tidak | `''` | MIME |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |

**`submission_reviews`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `submission_id` | `INT UNSIGNED` | tidak | — | Submission |
| `reviewer_id` | `INT UNSIGNED` | ya | `NULL` | Aktor |
| `action` | `ENUM('submit','score','revision','accept')` | tidak | `'submit'` | Jenis aksi |
| `score` | `DECIMAL(6,2)` | ya | `NULL` | Nilai saat aksi |
| `note` | `TEXT` | tidak | — | Catatan |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu aksi |

**`exams`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `class_id` | `INT UNSIGNED` | tidak | — | Kelas pemilik |
| `curriculum_version_id` | `INT UNSIGNED` | tidak | — | Versi acuan |
| `title` | `VARCHAR(200)` | tidak | — | Judul ujian |
| `description` | `TEXT` | tidak | — | Deskripsi |
| `type` | `ENUM('written','practical','oral','project','other')` | tidak | `'written'` | Jenis ujian |
| `exam_date` / `start_time` | `DATE`/`TIME` | ya | `NULL` | Jadwal |
| `duration_minutes` | `INT UNSIGNED` | ya | `NULL` | Durasi |
| `max_score` | `DECIMAL(6,2)` | tidak | `100.00` | Skor maksimum |
| `weight` | `DECIMAL(5,2)` | tidak | `0.00` | Bobot legacy |
| `instructions` | `LONGTEXT` | tidak | — | Instruksi |
| `status` | `ENUM('draft','published','closed','archived')` | tidak | `'draft'` | Lifecycle ujian |
| `created_by` / `updated_by` | `INT UNSIGNED` | ya | `NULL` | Aktor |
| `created_at` / `updated_at` | `DATETIME` | tidak/ya | `CURRENT_TIMESTAMP`/`NULL` | Waktu |

**`exam_participants`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `exam_id` | `INT UNSIGNED` | tidak | — | Ujian; unik bersama enrollment |
| `enrollment_id` | `INT UNSIGNED` | tidak | — | Enrollment |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta |
| `status` | `ENUM('registered','attended','absent','excused')` | tidak | `'registered'` | Status keikutsertaan |
| `note` | `TEXT` | tidak | — | Catatan |
| `created_at` / `updated_at` | `DATETIME` | tidak/ya | `CURRENT_TIMESTAMP`/`NULL` | Waktu |

**`exam_results`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `exam_id` | `INT UNSIGNED` | tidak | — | Ujian; unik bersama enrollment |
| `enrollment_id` | `INT UNSIGNED` | tidak | — | Enrollment |
| `student_id` | `INT UNSIGNED` | tidak | — | Peserta |
| `score` | `DECIMAL(6,2)` | ya | `NULL` | Nilai; `NULL` = belum ada |
| `status` | `ENUM('not_assessed','excused','missing','graded')` | tidak | `'not_assessed'` | Status hasil |
| `graded_by` / `graded_at` | `INT UNSIGNED`/`DATETIME` | ya | `NULL` | Penilai & waktu |
| `note` | `TEXT` | tidak | — | Catatan |
| `created_at` / `updated_at` | `DATETIME` | tidak/ya | `CURRENT_TIMESTAMP`/`NULL` | Waktu |

**`staff_attendances`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `user_id` | `INT UNSIGNED` | tidak | - | Pemilik catatan (FK `users.id`) |
| `kind` | `ENUM('in','out')` | tidak | `'in'` | Absensi masuk/pulang |
| `attendance_date` | `DATE` | tidak | - | Tanggal kalender server (`Asia/Jakarta`); nilai klien diabaikan |
| `taken_at` | `DATETIME` | tidak | - | Waktu server (UTC); nilai klien diabaikan |
| `timezone` | `VARCHAR(64)` | tidak | `''` | Zona waktu server (`Asia/Jakarta`) |
| `photo` | `LONGTEXT` | ya | `NULL` | Base64 foto |
| `photo_mime` | `VARCHAR(50)` | tidak | `'image/jpeg'` | MIME terdeteksi |
| `latitude` | `DECIMAL(10,7)` | tidak | - | Lintang (wajib) |
| `longitude` | `DECIMAL(10,7)` | tidak | - | Bujur (wajib) |
| `accuracy_m` | `DECIMAL(10,2)` | ya | `NULL` | Akurasi GPS (meter) |
| `location_label` | `VARCHAR(255)` | tidak | `''` | Label koordinat otomatis (tanpa input manual) |
| `location_source` | `ENUM('gps','manual','none')` | tidak | `'none'` | Selalu `gps` untuk data baru |
| `ip_address` | `VARCHAR(45)` | tidak | `''` | IP dari server |
| `user_agent` | `VARCHAR(255)` | tidak | `''` | User agent |
| `platform` | `VARCHAR(120)` | tidak | `''` | Platform perangkat |
| `language` | `VARCHAR(30)` | tidak | `''` | Bahasa browser |
| `screen` | `VARCHAR(30)` | tidak | `''` | Ukuran layar |
| `device_memory` | `DECIMAL(6,2)` | ya | `NULL` | Memori perangkat (GB) |
| `hardware_concurrency` | `INT UNSIGNED` | ya | `NULL` | Jumlah CPU core |
| `connection_type` | `VARCHAR(30)` | tidak | `''` | Tipe koneksi |
| `online` | `TINYINT(1)` | tidak | `1` | Status jaringan |
| `notes` | `TEXT` | tidak | - | Catatan tambahan |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu diperbarui |

**`staff_shifts`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `user_id` | `INT UNSIGNED` | tidak | - | Karyawan yang dijadwalkan (FK `users.id`, CASCADE) |
| `shift_date` | `DATE` | tidak | - | Tanggal shift; unik per karyawan |
| `shift_type` | `ENUM('pagi','sore','custom')` | tidak | `'pagi'` | Jenis shift |
| `start_time` | `TIME` | tidak | - | Jam mulai (`HH:MM`); preset Pagi `08:00`, Sore `16:00` |
| `end_time` | `TIME` | tidak | - | Jam selesai (`HH:MM`); preset Pagi `16:00`, Sore `00:00` |
| `note` | `VARCHAR(255)` | tidak | `''` | Catatan shift |
| `created_by` | `INT UNSIGNED` | ya | `NULL` | Admin pembuat (FK `users.id`, SET NULL) |
| `created_at` | `DATETIME` | tidak | `CURRENT_TIMESTAMP` | Waktu dibuat |
| `updated_at` | `DATETIME` | ya | `NULL` | Waktu diperbarui |

**`user_devices`**

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | tidak | AUTO_INCREMENT | Primary key |
| `user_id` | `INT UNSIGNED` | tidak | - | Pemilik token (FK `users.id`, CASCADE) |
| `token_hash` | `CHAR(64)` | tidak | - | SHA-256 token mentah; UNIQUE |
| `device_label` | `VARCHAR(120)` | tidak | `''` | Label ringkas perangkat |
| `user_agent` | `VARCHAR(255)` | tidak | `''` | User agent saat dibuat |
| `ip_address` | `VARCHAR(45)` | tidak | `''` | IP saat dibuat |
| `created_at` | `DATETIME` | tidak | - | Waktu token dibuat (UTC) |
| `last_used_at` | `DATETIME` | tidak | - | Waktu terakhir dipakai/dirotasi |
| `expires_at` | `DATETIME` | tidak | - | Batas sliding 30 hari |
| `revoked_at` | `DATETIME` | ya | `NULL` | Waktu dicabut |
| `revoked_reason` | `VARCHAR(40)` | tidak | `''` | `rotated`/`superseded`/`logout`/`admin`/`security`/`expired`/`reuse` |

### 16.3 Index dan Constraint

| Tabel | Index / Constraint | Tipe |
|---|---|---|
| `categories` | `uq_categories_slug (slug)` | UNIQUE |
| `categories` | `idx_categories_status (status)` | INDEX |
| `users` | `uq_users_email (email)` | UNIQUE |
| `users` | `idx_users_role (role)` | INDEX |
| `branches` | `uq_branches_code (code)` | UNIQUE |
| `branches` | `idx_branches_status (status)`, `idx_branches_city (city)` | INDEX |
| `courses` | `idx_courses_instructor (instructor_id)`, `idx_courses_status (status)`, `idx_courses_category (category_id)` | INDEX |
| `curriculum_versions` | `uq_curriculum_course_code (course_id, code)` | UNIQUE |
| `curriculum_versions` | `idx_curriculum_course_status (course_id, status)`, `idx_curriculum_status (status)` | INDEX |
| `classes` | `uq_classes_code (code)` | UNIQUE |
| `classes` | `idx_classes_course (course_id)`, `idx_classes_version (curriculum_version_id)`, `idx_classes_branch (branch_id)`, `idx_classes_instructor (instructor_id)`, `idx_classes_status (status)` | INDEX |
| `class_instructor_histories` | `idx_class_history_class (class_id, started_at)`, `idx_class_history_instructor (instructor_id)` | INDEX |
| `enrollments` | `uq_enrollments_student_class (student_id, class_id)` | UNIQUE |
| `enrollments` | `idx_enrollments_student_course (student_id, course_id)`, `idx_enrollments_course (course_id)`, `idx_enrollments_class (class_id)` | INDEX |
| `class_sessions` | `uq_class_sessions_number (class_id, session_number)` | UNIQUE |
| `class_sessions` | `idx_class_sessions_class_date (class_id, session_date)`, `idx_class_sessions_status (status)`, `idx_class_sessions_module (module_id)`, `idx_class_sessions_instructor (instructor_id)`, `idx_class_sessions_replaced (rescheduled_from_id)` | INDEX |
| `attendances` | `uq_attendances_session_enrollment (session_id, enrollment_id)` | UNIQUE |
| `attendances` | `idx_attendances_student (student_id)`, `idx_attendances_enrollment (enrollment_id)`, `idx_attendances_status (status)` | INDEX |
| `attendance_corrections` | `idx_attendance_corrections_attendance (attendance_id)`, `idx_attendance_corrections_session (session_id)`, `idx_attendance_corrections_enrollment (enrollment_id)` | INDEX |
| `class_material_settings` | `uq_class_material_settings_class (class_id)` | UNIQUE |
| `class_materials` | `idx_class_materials_class_status (class_id, status, sort_order)`, `idx_class_materials_lesson (lesson_id)`, `idx_class_materials_session (session_id)`, `idx_class_materials_created_by (created_by)` | INDEX |
| `class_lesson_progress` | `uq_class_lesson_progress_enrollment_lesson (enrollment_id, lesson_id)` | UNIQUE |
| `class_lesson_progress` | `idx_class_lesson_progress_class (class_id)`, `idx_class_lesson_progress_student (student_id)`, `idx_class_lesson_progress_lesson (lesson_id)` | INDEX |
| `class_material_progress` | `uq_class_material_progress_enrollment_material (enrollment_id, material_id)` | UNIQUE |
| `class_material_progress` | `idx_class_material_progress_class (class_id)`, `idx_class_material_progress_student (student_id)`, `idx_class_material_progress_material (material_id)` | INDEX |
| `modules` | `idx_modules_course (course_id, sort_order)`, `idx_modules_version (curriculum_version_id, sort_order)`, `idx_modules_instructor (instructor_id)` | INDEX |
| `lessons` | `idx_lessons_module (module_id, sort_order)`, `idx_lessons_course (course_id)`, `idx_lessons_version (curriculum_version_id, sort_order)`, `idx_lessons_instructor (instructor_id)` | INDEX |
| `grades` | `uq_grades_enrollment (enrollment_id)` | UNIQUE |
| `grades` | `idx_grades_student_course (student_id, course_id)`, `idx_grades_instructor (instructor_id)`, `idx_grades_scheme (scheme_id)`, `idx_grades_status (status)` | INDEX |
| `predicate_ranges` | `uq_predicate_ranges_letter (letter)` | UNIQUE |
| `predicate_ranges` | `idx_predicate_ranges_order (sort_order, min_score)` | INDEX |
| `grade_schemes` | `uq_grade_schemes_class (class_id)` | UNIQUE |
| `grade_schemes` | `idx_grade_schemes_course (course_id)`, `idx_grade_schemes_status (status)` | INDEX |
| `grade_scheme_components` | `idx_grade_scheme_components_scheme (scheme_id, sort_order)`, `idx_grade_scheme_components_type (type)` | INDEX |
| `grade_component_scores` | `uq_grade_component_scores_component_enrollment (component_id, enrollment_id)` | UNIQUE |
| `grade_component_scores` | `idx_grade_component_scores_enrollment (enrollment_id)`, `idx_grade_component_scores_student (student_id)`, `idx_grade_component_scores_status (status)` | INDEX |
| `grade_corrections` | `idx_grade_corrections_grade (grade_id)`, `idx_grade_corrections_enrollment (enrollment_id)` | INDEX |
| `assignments` | `idx_assignments_class_status (class_id, status)`, `idx_assignments_version (curriculum_version_id)`, `idx_assignments_module (module_id)`, `idx_assignments_lesson (lesson_id)`, `idx_assignments_created_by (created_by)` | INDEX |
| `assignment_files` | `idx_assignment_files_assignment (assignment_id)` | INDEX |
| `assignment_rubrics` | `idx_assignment_rubrics_assignment (assignment_id, sort_order)` | INDEX |
| `submissions` | `uq_submissions_assignment_enrollment (assignment_id, enrollment_id)` | UNIQUE |
| `submissions` | `idx_submissions_student (student_id)`, `idx_submissions_status (status)` | INDEX |
| `submission_files` | `idx_submission_files_submission (submission_id)` | INDEX |
| `submission_reviews` | `idx_submission_reviews_submission (submission_id, created_at)` | INDEX |
| `exams` | `idx_exams_class_status (class_id, status)`, `idx_exams_version (curriculum_version_id)`, `idx_exams_date (exam_date)` | INDEX |
| `exam_participants` | `uq_exam_participants_exam_enrollment (exam_id, enrollment_id)` | UNIQUE |
| `exam_participants` | `idx_exam_participants_student (student_id)` | INDEX |
| `exam_results` | `uq_exam_results_exam_enrollment (exam_id, enrollment_id)` | UNIQUE |
| `exam_results` | `idx_exam_results_student (student_id)`, `idx_exam_results_status (status)` | INDEX |
| `certificates` | `uq_certificates_number (certificate_number)`, `uq_certificates_enrollment (enrollment_id)` | UNIQUE |
| `certificates` | `idx_certificates_student_course (student_id, course_id)`, `idx_certificates_course (course_id)`, `idx_certificates_status (status)` | INDEX |
| `completed_lessons` | `uq_completed_student_lesson (student_id, lesson_id)` | UNIQUE |
| `completed_lessons` | `idx_completed_course (course_id)` | INDEX |
| `audit_logs` | `idx_audit_created (created_at)`, `idx_audit_actor (actor_id)`, `idx_audit_entity (entity)` | INDEX |
| `notifications` | `idx_notifications_created (created_at)`, `idx_notifications_type (type)`, `idx_notifications_recipient (recipient_id)` | INDEX |
| `staff_attendances` | `uq_staff_attendances_user_day_kind (user_id, attendance_date, kind)`, `idx_staff_attendances_user_date (user_id, attendance_date)`, `idx_staff_attendances_date (attendance_date)`, `idx_staff_attendances_taken (taken_at)` | UNIQUE + INDEX |
| `staff_shifts` | `uq_staff_shifts_user_date (user_id, shift_date)` | UNIQUE |
| `staff_shifts` | `idx_staff_shifts_date (shift_date)`, `idx_staff_shifts_type (shift_type)` | INDEX |
| `user_devices` | `uq_user_devices_token (token_hash)` | UNIQUE |
| `user_devices` | `idx_user_devices_user (user_id)`, `idx_user_devices_expires (expires_at)` | INDEX |

Catatan: `staff_attendances` memiliki unique key pada (`user_id`, `attendance_date`, `kind`), sehingga satu pengguna hanya dapat memiliki satu absen masuk dan satu absen pulang per hari. API `staffAttendance.create` juga memeriksa aturan ini sebelum insert, mewajibkan GPS, memakai jam server (`Asia/Jakarta`), dan menolak absen pulang sebelum absen masuk. Penghapusan hanya oleh admin dengan alasan minimal 5 karakter yang dicatat di Audit Trail.

### 16.4 Foreign Key dan Aturan Hapus

| Constraint | Kolom | Referensi | ON DELETE |
|---|---|---|---|
| `fk_courses_instructor` | `courses.instructor_id` | `users.id` | `SET NULL` |
| `fk_courses_category` | `courses.category_id` | `categories.id` | `SET NULL` |
| `fk_curriculum_course` | `curriculum_versions.course_id` | `courses.id` | `CASCADE` |
| `fk_curriculum_created_by` | `curriculum_versions.created_by` | `users.id` | `SET NULL` |
| `fk_curriculum_updated_by` | `curriculum_versions.updated_by` | `users.id` | `SET NULL` |
| `fk_classes_course` | `classes.course_id` | `courses.id` | `RESTRICT` |
| `fk_classes_version` | `classes.curriculum_version_id` | `curriculum_versions.id` | `RESTRICT` |
| `fk_classes_branch` | `classes.branch_id` | `branches.id` | `SET NULL` |
| `fk_classes_instructor` | `classes.instructor_id` | `users.id` | `SET NULL` |
| `fk_classes_created_by` | `classes.created_by` | `users.id` | `SET NULL` |
| `fk_classes_updated_by` | `classes.updated_by` | `users.id` | `SET NULL` |
| `fk_class_history_class` | `class_instructor_histories.class_id` | `classes.id` | `CASCADE` |
| `fk_class_history_instructor` | `class_instructor_histories.instructor_id` | `users.id` | `SET NULL` |
| `fk_class_history_actor` | `class_instructor_histories.actor_id` | `users.id` | `SET NULL` |
| `fk_modules_course` | `modules.course_id` | `courses.id` | `CASCADE` |
| `fk_modules_version` | `modules.curriculum_version_id` | `curriculum_versions.id` | `CASCADE` |
| `fk_modules_instructor` | `modules.instructor_id` | `users.id` | `SET NULL` |
| `fk_lessons_module` | `lessons.module_id` | `modules.id` | `CASCADE` |
| `fk_lessons_course` | `lessons.course_id` | `courses.id` | `CASCADE` |
| `fk_lessons_version` | `lessons.curriculum_version_id` | `curriculum_versions.id` | `CASCADE` |
| `fk_lessons_instructor` | `lessons.instructor_id` | `users.id` | `SET NULL` |
| `fk_enrollments_student` | `enrollments.student_id` | `users.id` | `CASCADE` |
| `fk_enrollments_course` | `enrollments.course_id` | `courses.id` | `CASCADE` |
| `fk_enrollments_class` | `enrollments.class_id` | `classes.id` | `CASCADE` |
| `fk_class_sessions_class` | `class_sessions.class_id` | `classes.id` | `CASCADE` |
| `fk_class_sessions_module` | `class_sessions.module_id` | `modules.id` | `SET NULL` |
| `fk_class_sessions_instructor` | `class_sessions.instructor_id` | `users.id` | `SET NULL` |
| `fk_class_sessions_created_by` | `class_sessions.created_by` | `users.id` | `SET NULL` |
| `fk_class_sessions_updated_by` | `class_sessions.updated_by` | `users.id` | `SET NULL` |
| `fk_class_sessions_replaced` | `class_sessions.rescheduled_from_id` | `class_sessions.id` | `SET NULL` |
| `fk_attendances_session` | `attendances.session_id` | `class_sessions.id` | `CASCADE` |
| `fk_attendances_enrollment` | `attendances.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_attendances_student` | `attendances.student_id` | `users.id` | `CASCADE` |
| `fk_attendances_recorded_by` | `attendances.recorded_by` | `users.id` | `SET NULL` |
| `fk_attendance_corrections_attendance` | `attendance_corrections.attendance_id` | `attendances.id` | `CASCADE` |
| `fk_attendance_corrections_session` | `attendance_corrections.session_id` | `class_sessions.id` | `CASCADE` |
| `fk_attendance_corrections_enrollment` | `attendance_corrections.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_attendance_corrections_actor` | `attendance_corrections.actor_id` | `users.id` | `SET NULL` |
| `fk_class_material_settings_class` | `class_material_settings.class_id` | `classes.id` | `CASCADE` |
| `fk_class_material_settings_updated_by` | `class_material_settings.updated_by` | `users.id` | `SET NULL` |
| `fk_class_materials_class` | `class_materials.class_id` | `classes.id` | `CASCADE` |
| `fk_class_materials_lesson` | `class_materials.lesson_id` | `lessons.id` | `SET NULL` |
| `fk_class_materials_session` | `class_materials.session_id` | `class_sessions.id` | `SET NULL` |
| `fk_class_materials_created_by` | `class_materials.created_by` | `users.id` | `SET NULL` |
| `fk_class_materials_updated_by` | `class_materials.updated_by` | `users.id` | `SET NULL` |
| `fk_class_lesson_progress_class` | `class_lesson_progress.class_id` | `classes.id` | `CASCADE` |
| `fk_class_lesson_progress_enrollment` | `class_lesson_progress.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_class_lesson_progress_student` | `class_lesson_progress.student_id` | `users.id` | `CASCADE` |
| `fk_class_lesson_progress_lesson` | `class_lesson_progress.lesson_id` | `lessons.id` | `CASCADE` |
| `fk_class_lesson_progress_completed_by` | `class_lesson_progress.completed_by` | `users.id` | `SET NULL` |
| `fk_class_material_progress_class` | `class_material_progress.class_id` | `classes.id` | `CASCADE` |
| `fk_class_material_progress_enrollment` | `class_material_progress.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_class_material_progress_student` | `class_material_progress.student_id` | `users.id` | `CASCADE` |
| `fk_class_material_progress_material` | `class_material_progress.material_id` | `class_materials.id` | `CASCADE` |
| `fk_class_material_progress_completed_by` | `class_material_progress.completed_by` | `users.id` | `SET NULL` |
| `fk_grades_enrollment` | `grades.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_grades_scheme` | `grades.scheme_id` | `grade_schemes.id` | `SET NULL` |
| `fk_grades_student` | `grades.student_id` | `users.id` | `CASCADE` |
| `fk_grades_course` | `grades.course_id` | `courses.id` | `CASCADE` |
| `fk_grades_instructor` | `grades.instructor_id` | `users.id` | `SET NULL` |
| `fk_grades_finalized_by` | `grades.finalized_by` | `users.id` | `SET NULL` |
| `fk_grade_schemes_course` | `grade_schemes.course_id` | `courses.id` | `CASCADE` |
| `fk_grade_schemes_class` | `grade_schemes.class_id` | `classes.id` | `CASCADE` |
| `fk_grade_schemes_created_by` | `grade_schemes.created_by` | `users.id` | `SET NULL` |
| `fk_grade_schemes_updated_by` | `grade_schemes.updated_by` | `users.id` | `SET NULL` |
| `fk_grade_scheme_components_scheme` | `grade_scheme_components.scheme_id` | `grade_schemes.id` | `CASCADE` |
| `fk_grade_component_scores_component` | `grade_component_scores.component_id` | `grade_scheme_components.id` | `CASCADE` |
| `fk_grade_component_scores_enrollment` | `grade_component_scores.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_grade_component_scores_student` | `grade_component_scores.student_id` | `users.id` | `CASCADE` |
| `fk_grade_component_scores_assessor` | `grade_component_scores.assessor_id` | `users.id` | `SET NULL` |
| `fk_grade_corrections_grade` | `grade_corrections.grade_id` | `grades.id` | `CASCADE` |
| `fk_grade_corrections_enrollment` | `grade_corrections.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_grade_corrections_actor` | `grade_corrections.actor_id` | `users.id` | `SET NULL` |
| `fk_assignments_class` | `assignments.class_id` | `classes.id` | `CASCADE` |
| `fk_assignments_version` | `assignments.curriculum_version_id` | `curriculum_versions.id` | `CASCADE` |
| `fk_assignments_module` | `assignments.module_id` | `modules.id` | `SET NULL` |
| `fk_assignments_lesson` | `assignments.lesson_id` | `lessons.id` | `SET NULL` |
| `fk_assignments_created_by` | `assignments.created_by` | `users.id` | `SET NULL` |
| `fk_assignments_updated_by` | `assignments.updated_by` | `users.id` | `SET NULL` |
| `fk_assignment_files_assignment` | `assignment_files.assignment_id` | `assignments.id` | `CASCADE` |
| `fk_assignment_rubrics_assignment` | `assignment_rubrics.assignment_id` | `assignments.id` | `CASCADE` |
| `fk_submissions_assignment` | `submissions.assignment_id` | `assignments.id` | `CASCADE` |
| `fk_submissions_enrollment` | `submissions.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_submissions_student` | `submissions.student_id` | `users.id` | `CASCADE` |
| `fk_submissions_reviewed_by` | `submissions.reviewed_by` | `users.id` | `SET NULL` |
| `fk_submission_files_submission` | `submission_files.submission_id` | `submissions.id` | `CASCADE` |
| `fk_submission_reviews_submission` | `submission_reviews.submission_id` | `submissions.id` | `CASCADE` |
| `fk_submission_reviews_reviewer` | `submission_reviews.reviewer_id` | `users.id` | `SET NULL` |
| `fk_exams_class` | `exams.class_id` | `classes.id` | `CASCADE` |
| `fk_exams_version` | `exams.curriculum_version_id` | `curriculum_versions.id` | `CASCADE` |
| `fk_exams_created_by` | `exams.created_by` | `users.id` | `SET NULL` |
| `fk_exams_updated_by` | `exams.updated_by` | `users.id` | `SET NULL` |
| `fk_exam_participants_exam` | `exam_participants.exam_id` | `exams.id` | `CASCADE` |
| `fk_exam_participants_enrollment` | `exam_participants.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_exam_participants_student` | `exam_participants.student_id` | `users.id` | `CASCADE` |
| `fk_exam_results_exam` | `exam_results.exam_id` | `exams.id` | `CASCADE` |
| `fk_exam_results_enrollment` | `exam_results.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_exam_results_student` | `exam_results.student_id` | `users.id` | `CASCADE` |
| `fk_exam_results_graded_by` | `exam_results.graded_by` | `users.id` | `SET NULL` |
| `fk_certificates_enrollment` | `certificates.enrollment_id` | `enrollments.id` | `CASCADE` |
| `fk_certificates_student` | `certificates.student_id` | `users.id` | `CASCADE` |
| `fk_certificates_course` | `certificates.course_id` | `courses.id` | `CASCADE` |
| `fk_certificates_admin` | `certificates.approved_by_admin_id` | `users.id` | `SET NULL` |
| `fk_completed_student` | `completed_lessons.student_id` | `users.id` | `CASCADE` |
| `fk_completed_course` | `completed_lessons.course_id` | `courses.id` | `CASCADE` |
| `fk_completed_lesson` | `completed_lessons.lesson_id` | `lessons.id` | `CASCADE` |
| `fk_staff_attendances_user` | `staff_attendances.user_id` | `users.id` | `CASCADE` |
| `fk_staff_shifts_user` | `staff_shifts.user_id` | `users.id` | `CASCADE` |
| `fk_staff_shifts_created_by` | `staff_shifts.created_by` | `users.id` | `SET NULL` |
| `fk_user_devices_user` | `user_devices.user_id` | `users.id` | `CASCADE` |

Catatan: `audit_logs` dan `notifications` sengaja tidak memiliki foreign key fisik ke `users`. Keduanya menyimpan snapshot nama/role/email agar riwayat tetap terbaca meski akun asal dihapus, dan penghapusan pengguna tidak terblokir oleh riwayat. `classes.course_id` dan `classes.curriculum_version_id` memakai `RESTRICT` agar penghapusan kursus/versi yang sudah dipakai kelas ditolak di level database, bukan hanya di level aplikasi.

### 16.5 Pemetaan Tabel ke Koleksi Client

Snapshot API memakai nama koleksi yang sama dengan model `localStorage`, sehingga modul client tidak perlu diubah:

| Tabel MySQL | Koleksi snapshot / key `localStorage` | Catatan transformasi |
|---|---|---|
| `users` | `users` | `password_hash` tidak pernah diserialisasi; `created_at` → `createdAt` (ISO) |
| `categories` | `categories` | `created_at`/`updated_at` → camelCase |
| `branches` | `branches` | `created_at`/`updated_at` → camelCase; dipakai sebagai konteks kelas dan jadwal |
| `courses` | `courses` | `instructor_name` dihitung dari JOIN; `moduleCount`/`lessonCount` dari subquery; `category_id` → `categoryId` |
| `curriculum_versions` | `curriculumVersions` | `course_id`/`created_by`/`updated_by` → camelCase; `effective_date` → `YYYY-MM-DD` |
| `classes` | `classes` | `course_id`/`curriculum_version_id`/`branch_id`/`instructor_id` → camelCase; `courseTitle`, `curriculumVersionCode`, `curriculumVersionName`, `branchCode`, `branchName`, `branchCity`, `instructorName` dari JOIN |
| `class_instructor_histories` | `classInstructorHistories` | `class_id`/`instructor_id`/`actor_id` → camelCase; `started_at`/`ended_at` → `YYYY-MM-DD` |
| `enrollments` | `enrollments` | `student_id`/`course_id`/`class_id` → camelCase; tanggal → `YYYY-MM-DD` |
| `class_sessions` | `classSessions` | `class_id`/`session_number`/`module_id`/`instructor_id`/`planned_jp`/`actual_jp`/`rescheduled_from_id` → camelCase; `session_date` → `YYYY-MM-DD`; `start_time`/`end_time` → `HH:MM`; label kelas/modul/tentor, cabang (`branchId`/`branchCode`/`branchName`/`branchCity` via JOIN kelas), dan hitungan presensi dari JOIN/subquery |
| `attendances` | `attendances` | `session_id`/`enrollment_id`/`student_id`/`recorded_by` → camelCase; `recorded_at`/`corrected_at` → ISO; `studentName` dari JOIN |
| `attendance_corrections` | `attendanceCorrections` | `attendance_id`/`session_id`/`enrollment_id`/`old_status`/`new_status`/`actor_id`/`actor_name`/`actor_role` → camelCase; `corrected_at`/`created_at` → ISO |
| `modules` | `modules` | `course_id`/`curriculum_version_id` → camelCase; `sort_order` → `order` |
| `lessons` | `lessons` | `course_id`/`module_id`/`curriculum_version_id` → camelCase; `sort_order` → `order` |
| `grades` | `grades` | `enrollment_id`/`scheme_id`/`finalized_by` → camelCase; `final_score`/`finalized_at`/`computed_at` → camelCase/ISO; `components_snapshot` → `componentsSnapshot` (JSON string); nilai dibaca via `grade.roster`/`transcript` |
| `predicate_ranges` | `predicateRanges` | `min_score`/`max_score`/`sort_order`/`is_active` → camelCase |
| `grade_schemes` | `gradeSchemes` | `course_id`/`class_id`/`pass_score`/`created_by`/`updated_by` → camelCase; label kelas/kursus dan komponen dari JOIN/agregasi |
| `grade_scheme_components` | `gradeSchemeComponents` | `scheme_id`/`max_score`/`is_required`/`sort_order` → camelCase |
| `grade_component_scores` | `gradeComponentScores` | `component_id`/`enrollment_id`/`student_id`/`assessor_id`/`assessed_at` → camelCase/ISO |
| `grade_corrections` | `gradeCorrections` | `grade_id`/`enrollment_id`/`previous_score`/`new_score`/`previous_letter`/`new_letter`/`actor_id` → camelCase; `actorName` dari JOIN/agregasi |
| `assignments` | `assignments` | `class_id`/`curriculum_version_id`/`module_id`/`lesson_id`/`open_at`/`due_at`/`max_score`/`require_file`/`allowed_types`/`max_file_size`/`created_by`/`updated_by` → camelCase/ISO |
| `assignment_files` | `assignmentFiles` | `assignment_id`/`file_name`/`file_path`/`file_size`/`mime_type` → camelCase; `file_path` tidak diekspos ke peserta |
| `assignment_rubrics` | `assignmentRubrics` | `assignment_id`/`max_score`/`sort_order` → camelCase |
| `submissions` | `submissions` | `assignment_id`/`enrollment_id`/`student_id`/`text_answer`/`submitted_at`/`is_late`/`reviewed_by`/`reviewed_at`/`revision_count` → camelCase/ISO |
| `submission_files` | `submissionFiles` | `submission_id`/`file_name`/`file_path`/`file_size`/`mime_type` → camelCase; path privat tidak diekspos |
| `submission_reviews` | `submissionReviews` | `submission_id`/`reviewer_id` → camelCase; `created_at` → ISO |
| `exams` | `exams` | `class_id`/`curriculum_version_id`/`exam_date`/`start_time`/`duration_minutes`/`max_score`/`created_by`/`updated_by` → camelCase/format tanggal-jam |
| `exam_participants` | `examParticipants` | `exam_id`/`enrollment_id`/`student_id` → camelCase |
| `exam_results` | `examResults` | `exam_id`/`enrollment_id`/`student_id`/`graded_by`/`graded_at`/`graded_by_name` → camelCase; snapshot peserta tidak memuat roster ujian |
| `certificates` | `certificates` | `approved_by_admin_id` → `approvedByAdminId`; `approved_at` → ISO |
| `completed_lessons` | `completedLessons` | `student_id`/`course_id`/`lesson_id` → camelCase |
| `class_material_settings` | `classMaterialSettings` | `class_id`/`access_mode`/`require_instructor_approval`/`updated_by` → camelCase; `updated_at` → ISO |
| `class_materials` | `classMaterials` | `class_id`/`lesson_id`/`session_id`/`access_level`/`available_from`/`sort_order`/`created_by`/`updated_by` → camelCase; `content` dan `file_path` **tidak** diserialisasi |
| `class_lesson_progress` | `classLessonProgress` | `class_id`/`enrollment_id`/`student_id`/`lesson_id`/`completed_by` → camelCase; `completed_at`/`created_at` → ISO |
| `class_material_progress` | `classMaterialProgress` | `class_id`/`enrollment_id`/`student_id`/`material_id`/`completed_by` → camelCase; `completed_at`/`created_at` → ISO |
| `audit_logs` | `auditLogs` | `actor_id`/`actor_name`/`actor_role` → camelCase; `created_at` → ISO |
| `staff_attendances` | `staffAttendances` (tidak di snapshot) | Dibaca lewat `staffAttendance.list`; `user_id`/`attendance_date`/`taken_at`/`photo_mime`/`accuracy_m`/`location_label`/`location_source`/`ip_address`/`user_agent`/`device_memory`/`hardware_concurrency`/`connection_type`/`created_at` → camelCase; `photo` base64 tetap data URL. Karyawan hanya menerima baris miliknya; admin dapat meminta `userId` lain |
| `staff_shifts` | `staffShifts` (tidak di snapshot) | Dibaca lewat `staffShift.list`; `user_id`/`shift_date`/`shift_type`/`start_time`/`end_time`/`created_by`/`created_at`/`updated_at` → camelCase/ISO; `userName`/`userEmail`/`userRole` dari JOIN. Khusus Admin |
| `user_devices` | — (tidak ada koleksi client) | Server-only; token/hash tidak pernah masuk snapshot, cookie, atau `localStorage` |
| `notifications` | `notifications` | `recipient_id`/`recipient_email` → camelCase; `created_at` → ISO |
| Sesi PHP | `session` (cache di `api.js`) | `{ userId }` dari `$_SESSION`, bukan tabel |

Snapshot hanya memuat modul/materi dari versi kurikulum aktif (non-`retired`) tiap kursus. Struktur versi lain diambil lewat aksi `curriculum.detail`. Sejak Phase 4, snapshot juga memuat `classMaterialSettings`, `classMaterials`, `classLessonProgress`, dan `classMaterialProgress`; `classMaterials` sengaja hanya berisi metadata tanpa `content`/`file_path`. Koleksi `staffAttendances` sengaja tidak dimasukkan ke snapshot bootstrap karena memuat foto pribadi dan metadata perangkat; data hanya dikembalikan oleh aksi `staffAttendance.list` milik pengguna yang login. Koleksi `staffShifts` juga tidak masuk snapshot; jadwal dibaca khusus Admin lewat aksi `staffShift.list` agar data shift tidak terekspos ke role lain. Pada UI, `attendance_date`, `taken_at`, dan koordinat diisi otomatis saat simpan (tanggal/jam `Asia/Jakarta` dan GPS perangkat), sehingga pengguna tidak dapat mengubahnya; dashboard Admin dan halaman karyawan memakai modul bersama `attendance.js`, dan satu pengguna hanya dapat mencatat satu `in` dan satu `out` per hari. Tabel `user_devices` tidak pernah masuk snapshot: token mentah hanya ada di cookie `elips_remember`, sedangkan server menyimpan hash-nya dan mengelola rotasi/pencabutan sepenuhnya di backend.

### 16.6 Catatan Operasional

- **Instalasi:** `php api/install.php --fresh` menghapus database lama, membuat ulang seluruh 39 tabel, dan mengisi data demo: 9 predikat, 7 pengguna, 4 kategori, 3 cabang, 4 kursus, 4 versi kurikulum, 25 modul, 75 materi, 1 kelas migrasi, 1 histori tentor, 3 enrollment, 4 sesi pertemuan, 9 baris presensi, 2 tugas, 3 rubrik, 1 submission, 2 riwayat review, 2 ujian, 6 peserta ujian, 6 hasil ujian, 1 skema penilaian, 3 komponen, dan 2 nilai komponen manual. Koleksi `grade_corrections` sengaja kosong pada seed. Tabel `staff_attendances` dibuat (tabel ke-37) tetapi tidak diisi data demo karena absensi bersifat pribadi dan dibuat dari dashboard Admin atau halaman karyawan. Tabel `staff_shifts` dibuat (tabel ke-38) dan sengaja kosong karena jadwal shift diisi Admin melalui halaman jadwal shift. Tabel `user_devices` dibuat (tabel ke-39) dan sengaja kosong sampai ada login dengan "Ingat saya". Password demo `elips123` di-hash saat seeding.
- **Timezone:** koneksi PDO disetel UTC; kolom `DATE` untuk tanggal kalender dan `DATETIME` untuk timestamp.
- **Transaksi:** operasi multi-tabel dijalankan dalam transaksi eksplisit:
  - pembuatan kelas + penguncian versi kurikulum (`curriculum_versions.status = 'locked'`) + histori tentor awal;
  - pergantian tentor (tutup histori lama, buka histori baru, update `classes.instructor_id`);
  - clone kurikulum (versi baru + salinan modul/materi);
  - hapus enrollment (nilai + sertifikat + progres + submission/hasil ujian + nilai komponen + koreksi terkait);
  - simpan nilai legacy (nilai + sertifikat Draft);
  - simpan presensi massal (upsert `attendances` + audit koreksi bila status berubah);
  - reschedule (buat sesi baru + tandai sesi asal `rescheduled`);
  - simpan progres materi (upsert `class_lesson_progress`/`class_material_progress` + audit bila dikonfirmasi staf);
  - hapus materi tambahan (progres terkait ikut terhapus via cascade);
  - simpan skema penilaian (upsert skema + ganti komponen dalam satu transaksi);
  - finalisasi nilai (komputasi + upsert `grades` + snapshot komponen + audit);
  - koreksi nilai (update `grades` + tulis `grade_corrections` + audit).
- **Penguncian versi:** `class.create` membaca versi dengan `FOR UPDATE` di dalam transaksi; setelah kelas tersimpan, versi tidak dapat diubah/dihapus. `classes` memakai FK `RESTRICT` sebagai pengaman terakhir.
- **Penguncian nilai:** nilai berstatus `finalized` tidak dapat diubah lewat simpan biasa; koreksi wajib beralasan dan selalu menulis baris `grade_corrections`. Finalisasi tidak membuat sertifikat otomatis.
- **Retensi:** backend memangkas `audit_logs` dan `notifications` ke 1000 baris terbaru setelah penulisan.
- **Seed migrasi:** hanya satu kelas migrasi (`ANG-2026-MNJ-01`, `legacy_import = 1`) yang di-seed; seluruh enrollment v3 dipetakan ke kelas tersebut agar setiap kelas selalu merujuk versi kurikulum yang terkunci.
- **Ekstensi:** seluruh tabel memakai `InnoDB` dan `utf8mb4`; `AUTO_INCREMENT` dipakai untuk ID numerik yang juga dipakai client.
