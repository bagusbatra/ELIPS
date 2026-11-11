# PRD: Learning Management System (LMS) ELIPS

**Versi:** 4.0  
**Tanggal:** 2026-11-11
**Status:** Implemented Phase 1–6 (Dual-mode - PHP 8/MySQL 8 + fallback `localStorage`). v3.0 tetap menjadi baseline alur kursus; v4.0 menambahkan versi kurikulum, angkatan/kelas, penguncian kurikulum per kelas, histori tentor, enrollment berbasis kelas, jadwal pertemuan dan presensi offline, **cabang** dengan halaman kalender/jadwal admin, **materi tambahan kelas dan mode akses materi**, **tugas & ujian**, **skema penilaian, finalisasi nilai, predikat konfigurabel, dan koreksi beraudit**, serta **fondasi server absensi karyawan** (role `karyawan`, jam server, GPS wajib, aturan harian di database).
**Baseline v4.0 lengkap:** `ELIPS-LMS-PRD-v4.0.md`  
**Log pengembangan:** `log.md`
**ERD:** `erd.md`
**Nama Aplikasi:** ELIPS LMS

---

## 1. Tujuan Proyek

Membangun sistem Learning Management System (LMS) berbasis web untuk pelatihan dan kursus. Aplikasi memakai vanilla HTML, CSS, dan JavaScript di sisi client, dengan backend PHP 8 + MySQL 8 sebagai penyimpanan utama dan `localStorage` sebagai fallback demo saat API tidak tersedia.

### 1.1 Cakupan v4.0 Phase 1–6

Model akademik v4.0 memisahkan **Kursus** dari penyelenggaraannya:

```text
Kursus
  └── Versi Kurikulum (draft → published → locked → retired)
        └── Modul → Materi
  └── Kelas/Angkatan (satu versi kurikulum terkunci, satu tentor, satu cabang)
        └── Sesi Pertemuan (jadwal, JP rencana/aktual, status)
              └── Presensi peserta
        └── Enrollment peserta
              └── Progres materi → Nilai → Sertifikat
        └── Materi Tambahan Kelas (di luar kurikulum)
        └── Tugas & Ujian (instrumen penilaian)
        └── Skema Penilaian → Komponen berbobot → Nilai komponen
              └── Nilai akhir (draft/incomplete/finalized) → Koreksi beraudit
```

Aturan inti yang sudah diterapkan:

- Versi kurikulum baru berstatus **Draft**; hanya versi **Published** yang dapat dipakai membuat kelas.
- Saat kelas dibuat, versi kurikulum langsung berubah menjadi **Locked** dalam transaksi yang sama.
- Versi **Locked/Retired** tidak boleh diubah atau dihapus; revisi dilakukan dengan **clone** menjadi Draft baru.
- Satu kelas hanya memiliki satu tentor aktif, tetapi satu tentor boleh mengampu banyak kelas. Pergantian tentor dicatat di histori.
- Enrollment terhubung ke kelas, memiliki status pendaftaran, dan tunduk pada kuota kelas.
- Jadwal pertemuan dibuat per kelas (single atau berulang), dengan JP rencana dan JP aktual; sesi yang dibatalkan/rescheduled tidak dihitung sebagai JP aktual.
- Setiap kelas dan sesi dapat dikaitkan ke **cabang**; cabang aktif wajib dipilih bila pengguna menetapkan cabang, dan cabang yang masih dipakai kelas tidak dapat dihapus.
- Admin memiliki halaman **Kalender & Jadwal** yang memfilter kalender bulanan, agenda harian, ringkasan JP, dan daftar sesi berdasarkan cabang dan kelas.
- Setiap kelas memiliki **mode akses materi** (`open`, `sequential`, `scheduled`, `conditional`) yang dapat diatur Admin atau tentor pengampu.
- Materi kurikulum dapat ditambah **materi tambahan kelas** tanpa mengubah versi kurikulum; materi tambahan mendukung status wajib/opsional/referensi, tanggal tersedia, dan file privat.
- Progres belajar dapat dicatat per kelas/enrollment (`class_lesson_progress`, `class_material_progress`), dan kelas dapat mewajibkan **konfirmasi instruktur** sebelum peserta menandai materi selesai.
- Presensi dicatat per sesi dan per enrollment; koreksi presensi wajib menyimpan alasan, aktor, nilai lama, dan nilai baru.
- Rekap kehadiran dihitung dari JP sesi yang dihitung dibagi JP wajib sesuai kebijakan kehadiran.
- **Tugas** dan **ujian** terikat ke kelas dan versi kurikulum; submission peserta, review, peserta ujian, dan hasil ujian disimpan terpisah.
- Satu kelas memiliki **satu skema penilaian** berisi komponen berbobot (`assignment`, `exam`, atau `manual`). Total bobot wajib 100% sebelum skema dipublikasikan.
- Nilai akhir dihitung dari komponen (bukan rata-rata nilai mentah); komponen wajib yang belum dinilai membuat status **`incomplete`**, dan nilai kosong tidak pernah dianggap **0**.
- Huruf mutu mengikuti **predikat A–E yang dapat dikonfigurasi** Admin, bukan tabel hardcoded.
- Nilai yang sudah **finalized** terkunci; perubahan hanya melalui **koreksi** beralasan yang tercatat append-only di `grade_corrections`.
- Sertifikat tetap mengikuti alur Draft → Publish Admin; finalisasi nilai Phase 6 **tidak** otomatis membuat sertifikat.
- Nilai dan sertifikat unik per enrollment, sehingga peserta dapat mengikuti kursus yang sama pada angkatan berbeda.
- Data akademik tidak dihapus permanen; kelas dengan enrollment tidak dapat dihapus dan versi terpakai harus dipensiunkan, bukan dihapus.

---

## 2. Target Pengguna

| Role | Deskripsi | Kebutuhan Utama |
|---|---|---|
| **Admin** | Pengelola sistem | Menambah kursus (Draft), mengelola versi kurikulum, mengelola cabang, membuat angkatan/kelas, menugaskan 1 tentor per kelas, menyusun jadwal sesi, memantau kalender/presensi/rekap, mengatur mode akses materi dan override dengan alasan, menambah pengguna, mendaftarkan siswa ke kelas, menyetujui sertifikat Draft → Publish |
| **Instruktur/Trainer** | Pengajar kursus/kelas | Login, melihat kelas yang diampu, mengelola modul/materi pada versi kurikulum yang masih dapat diubah, mengatur akses dan materi tambahan kelas, mengonfirmasi penyelesaian materi, menyusun jadwal, mencatat presensi dan koreksi, memantau progres, mengelola tugas/ujian, menyusun skema penilaian, memberi nilai komponen, memfinalisasi nilai, dan mengoreksi nilai dengan alasan |
| **Peserta/Student** | Peserta pelatihan | Mengakses materi dari kelas yang diikuti sesuai mode akses, menandai pelajaran/materi selesai bila diizinkan, melihat status persetujuan instruktur, melihat rekap kehadiran sendiri, mengumpulkan tugas, melihat hasil ujian, melihat transkrip nilai sendiri, dan melihat sertifikat yang sudah Publish |

---

## 3. Alur Sistem Kursus (Business Flow)

Alur berikut menjadi acuan utama untuk pengelolaan kursus, penugasan instruktur dan siswa, penilaian, serta penerbitan sertifikat.

### 3.1 Ringkasan Alur

```text
Admin ── tambah kursus ──────────────▶ Kursus [Draft]
Admin ── tambah instruktur ──────────▶ Instruktur
Admin ── kaitkan instruktur ─────────▶ Kursus [Draft]   (1 kursus = 1 instruktur)
Admin ── susun versi kurikulum ──────▶ Versi [Draft] → [Published]
Admin ── kelola cabang ──────────────▶ Cabang [Aktif/Nonaktif]
Admin ── buat angkatan/kelas ────────▶ Kelas (versi Published dikunci, 1 tentor, 1 cabang)
Admin ── tambah siswa ───────────────▶ Siswa
Admin ── daftarkan siswa ────────────▶ Kelas (kuota & status enrollment)
Admin/Instruktur ── susun jadwal ────▶ Sesi Pertemuan (single/berulang, JP rencana)
Admin ── pantau kalender ────────────▶ Kalender bulanan & agenda per cabang
Instruktur ── catat presensi ────────▶ Presensi per sesi & enrollment
Instruktur ── koreksi presensi ──────▶ Audit koreksi (alasan, aktor, nilai lama/baru)
Sistem ── hitung rekap ──────────────▶ Persentase kehadiran peserta
Instruktur ── login ─────────────────▶ Buat modul & materi pada versi yang belum terkunci
Siswa ── belajar ────────────────────▶ Hanya materi dari kelas yang diikuti
Instruktur ── beri nilai akhir ──────▶ Nilai per enrollment
Nilai ── terintegrasi ───────────────▶ E-Sertifikat [Draft]
E-Sertifikat [Draft] ── tidak tampil ▶ Halaman siswa
Admin ── approve ────────────────────▶ E-Sertifikat [Publish] ── tampil ▶ Halaman siswa
Sistem ── kursus Publish / sertifikat terbit ▶ Notifikasi email (simulasi) ── outbox ▶ Admin
```

### 3.2 Langkah Detail

1. **Admin menambah kursus baru** melalui `admin/courses.html`.
   - Kursus baru dibuat dengan status **Draft**.
   - Kursus Draft belum dapat diakses oleh siswa.
   - Kursus menjadi wadah jangka panjang; penyelenggaraan dilakukan melalui versi kurikulum dan kelas.

2. **Admin menambah akun instruktur baru** melalui `admin/users.html`.
   - Role pengguna diatur sebagai **Instruktur**.
   - Akun instruktur harus berstatus **Aktif** agar dapat masuk dan mengampu kelas.

3. **Admin menyusun versi kurikulum** melalui `admin/academic.html`.
   - Versi baru selalu berstatus **Draft** dan memiliki kode unik per kursus (mis. `2.0`).
   - Modul dan materi dibuat pada versi Draft melalui `instructor/manage-course.html` atau aksi API terkait.
   - Versi dapat **dipublikasikan** (`Published`) bila strukturnya siap dipakai.
   - Versi dapat **di-clone** menjadi Draft baru untuk revisi tanpa mengubah versi yang sudah dipakai.
   - Versi yang sudah dipakai kelas tidak dapat diubah atau dihapus; versi tersebut berstatus **Locked** dan hanya dapat **dipensiunkan** (`Retired`).

4. **Admin membuat angkatan/kelas** melalui `admin/academic.html`.
   - Kelas wajib memilih satu versi kurikulum berstatus **Published** (atau **Locked** yang masih aktif).
   - Saat kelas dibuat, versi kurikulum langsung berubah menjadi **Locked** dalam transaksi yang sama.
   - Satu kelas memiliki **satu tentor aktif** (`instructorId`); satu tentor boleh mengampu banyak kelas.
   - Kelas dapat dikaitkan ke satu cabang aktif (`branchId`); kolom cabang tampil di tabel dan dapat difilter.
   - Kelas menyimpan kode unik, periode, kuota, lokasi/ruang, catatan jadwal, dan JP rencana.
   - Pergantian tentor dicatat di `class_instructor_histories` (tentor lama ditutup, tentor baru dibuka).

4a. **Admin mengelola cabang** melalui `admin/settings.html`.
    - Cabang menyimpan kode unik, nama, alamat, kota, telepon, manajer, status (`active`/`inactive`), dan catatan.
    - Cabang nonaktif tidak dapat dipilih saat membuat/mengubah kelas; kelas lama tetap menyimpan referensinya.
    - Cabang yang masih dipakai kelas tidak dapat dihapus (integritas data); nonaktifkan bila tidak dipakai lagi.
    - Admin memantau jadwal lintas kelas per cabang melalui `admin/schedule.html` (kalender bulanan, agenda harian, ringkasan JP, dan daftar sesi).

5. **Admin menambah akun siswa baru** melalui `admin/users.html` atau halaman `register.html`.
   - Role pengguna adalah **Peserta**.
   - Akun siswa harus berstatus **Aktif** agar dapat mengakses materi.

6. **Admin mendaftarkan siswa ke kelas** melalui `admin/enrollment.html` atau modal roster di `admin/academic.html`.
   - Satu siswa boleh terdaftar pada **beberapa kelas/kursus**.
   - Satu kelas boleh memiliki banyak peserta, dibatasi **kuota** kelas.
   - Enrollment menyimpan `class_id`, `course_id`, dan status pendaftaran (`active`, `completed`, `withdrawn`, `cancelled`).
   - Pendaftaran duplikat pada kelas yang sama dilewati (idempoten), dan kuota tidak boleh diturunkan di bawah jumlah peserta aktif.
   - Siswa hanya dapat membuka materi dari kelas yang diikuti, pada kursus yang sudah **Published**.

7. **Instruktur masuk ke sistem**.
   - Instruktur hanya melihat kelas/kursus yang ditugaskan kepadanya.
   - Instruktur tidak dapat mengubah kelas atau kursus milik instruktur lain.

8. **Instruktur mengelola modul dan materi** melalui `instructor/manage-course.html`.
   - Modul dan materi hanya dapat dibuat pada kursus yang diampu **dan** versi kurikulum yang masih **Draft/Published**.
   - Versi Locked/Retired menolak perubahan; instruktur harus meminta Admin membuat versi baru.
   - Materi mendukung tipe teks, video, file, dan link eksternal.

8a. **Admin atau Instruktur menyusun jadwal pertemuan** melalui `admin/academic.html` atau `instructor/dashboard.html`.
    - Sesi dapat dibuat satu per satu atau berulang (mis. setiap Senin selama 8 pertemuan, maksimal 52 sesi per permintaan).
    - Setiap sesi menyimpan nomor pertemuan unik per kelas, tanggal, jam mulai/selesai, lokasi/ruang, modul terkait, serta JP rencana.
    - Sesi mewarisi `branchId`/nama cabang dari kelas dan menampilkannya pada kalender serta daftar jadwal.
    - Status sesi: `scheduled`, `confirmed`, `completed`, `rescheduled`, `cancelled`, atau `no_show`.
    - Reschedule membuat sesi pengganti baru yang tertaut ke sesi asal; sesi asal ditandai `rescheduled` dan tidak dihitung sebagai JP aktual.

8b. **Instruktur mencatat presensi** melalui modal presensi pada halaman akademik/dashboard instruktur.
    - Presensi dicatat per sesi dan per enrollment dengan status `present`, `late`, `permission`, `sick`, `absent`, atau `excused`.
    - Satu enrollment hanya memiliki satu baris presensi per sesi.
    - Perubahan status yang sudah tercatat wajib menyertakan alasan dan menghasilkan baris audit koreksi (nilai lama, nilai baru, aktor, waktu, alasan).
    - Peserta hanya dapat melihat presensi dan rekap miliknya sendiri.

9. **Siswa mempelajari materi** melalui `learning.html`.
   - Hanya kelas yang diikuti dan kursus berstatus Published yang dapat diakses.
   - Materi yang ditampilkan berasal dari versi kurikulum yang terkunci pada kelas tersebut.
   - Materi dibuka secara berurutan; pelajaran berikutnya terkunci sampai pelajaran sebelumnya selesai.
   - Progres belajar dicatat per pelajaran.

10. **Di akhir kursus, instruktur memberi nilai** melalui `instructor/students.html`.
    - Nilai berupa angka 0–100 dan otomatis dikonversi menjadi grade huruf (A–E).
    - Nilai terhubung ke **enrollment**, bukan hanya pasangan siswa–kursus, sehingga angkatan berbeda memiliki nilai terpisah.
    - Instruktur dapat menambahkan catatan nilai.

11. **Nilai terintegrasi dengan sertifikat**.
    - Saat nilai disimpan, sistem membuat atau memperbarui e-sertifikat untuk enrollment tersebut.
    - E-sertifikat baru selalu berstatus **Draft**.

12. **Sertifikat Draft tidak ditampilkan ke siswa**.
    - Halaman siswa hanya menampilkan e-sertifikat berstatus **Publish**.
    - Sertifikat Draft tidak boleh muncul di daftar, tidak dapat dilihat, dan tidak dapat diverifikasi oleh siswa.

13. **Admin menyetujui sertifikat** melalui `admin/certificates.html`.
    - Admin menekan **Setujui & Terbitkan** pada sertifikat Draft.
    - Status berubah menjadi **Publish**, nomor sertifikat final dan tanggal persetujuan disimpan.
    - Setelah Publish, sertifikat tampil di halaman siswa dan dapat diverifikasi publik.

14. **Data akademik tidak dihapus permanen**.
    - Kelas yang sudah memiliki enrollment tidak dapat dihapus.
    - Versi kurikulum yang sudah dipakai tidak dapat dihapus; gunakan status **Retired**.
    - Hanya versi **Draft** tanpa kelas yang boleh dihapus.

### 3.3 Aturan Status

| Entitas | Status | Makna | Transisi |
|---|---|---|---|
| **Kursus** | `draft` | Baru dibuat, belum dapat diakses siswa | `draft` → `published` oleh Admin |
| **Kursus** | `published` | Aktif dan dapat diakses siswa yang terdaftar pada kelas | `published` → `draft` oleh Admin |
| **Versi Kurikulum** | `draft` | Sedang disusun, belum dapat dipakai membuat kelas | `draft` → `published` |
| **Versi Kurikulum** | `published` | Siap dipakai; dapat dipilih saat membuat kelas | `published` → `locked` saat kelas dibuat; `published` → `retired` |
| **Versi Kurikulum** | `locked` | Sudah dipakai kelas; konten tidak boleh diubah | Final untuk kelas tersebut; versi baru dibuat via clone |
| **Versi Kurikulum** | `retired` | Dipensiunkan, tidak dipakai kelas baru | Final |
| **Kelas/Angkatan** | `draft` | Dalam persiapan, belum dibuka | `draft` → `open` |
| **Kelas/Angkatan** | `open` | Menerima pendaftaran peserta | `open` → `ongoing` → `completed` |
| **Kelas/Angkatan** | `ongoing` | Sedang berjalan | `ongoing` → `completed` |
| **Kelas/Angkatan** | `completed` | Selesai | `completed` → `archived` |
| **Kelas/Angkatan** | `cancelled` / `archived` | Dibatalkan / diarsipkan; tidak menerima pendaftaran | Final |
| **Enrollment** | `active` | Peserta terdaftar dan dapat mengakses kelas | `active` ↔ `withdrawn`, `active` → `completed` |
| **Enrollment** | `completed` | Peserta menyelesaikan kelas | Final |
| **Enrollment** | `withdrawn` | Peserta berhenti; akses dicabut | `withdrawn` → `active` |
| **Enrollment** | `cancelled` | Pendaftaran dibatalkan | Final |
| **Sesi Pertemuan** | `scheduled` | Sesi terjadwal, presensi belum tentu lengkap | `scheduled` → `confirmed`/`completed`/`cancelled`/`no_show`/`rescheduled` |
| **Sesi Pertemuan** | `confirmed` | Sesi dikonfirmasi akan berlangsung | `confirmed` → `completed`/`cancelled`/`no_show`/`rescheduled` |
| **Sesi Pertemuan** | `completed` | Sesi selesai; tidak dapat dihapus | Final (JP aktual dihitung) |
| **Sesi Pertemuan** | `rescheduled` | Diganti sesi lain; tidak dihitung JP aktual | Final |
| **Sesi Pertemuan** | `cancelled` / `no_show` | Dibatalkan / tidak terlaksana; tidak dihitung JP aktual | Final |
| **Presensi** | `present` / `late` | Hadir / terlambat; dihitung hadir sesuai kebijakan | Dapat dikoreksi dengan alasan |
| **Presensi** | `permission` / `sick` / `excused` | Izin / sakit / dispensasi | Dapat dikoreksi dengan alasan |
| **Presensi** | `absent` | Tidak hadir | Dapat dikoreksi dengan alasan |
| **Sertifikat** | `draft` | Sudah dinilai instruktur, menunggu persetujuan Admin | `draft` → `publish` |
| **Sertifikat** | `publish` | Disetujui Admin, tampil di halaman siswa, dapat diverifikasi | Final |
| **Pengguna** | `aktif` | Dapat login dan menggunakan sistem | `aktif` ↔ `nonaktif` |
| **Pengguna** | `nonaktif` | Tidak dapat login | `nonaktif` → `aktif` |
| **Cabang** | `active` | Dapat dipilih untuk kelas dan tampil sebagai konteks jadwal | `active` ↔ `inactive` |
| **Cabang** | `inactive` | Disimpan untuk riwayat, tidak dapat dipakai kelas baru | `inactive` → `active` |

Catatan: status kelas `ready`/`ongoing` memerlukan tentor aktif. Kelas dengan status `cancelled` atau `archived` tidak menerima pendaftaran baru. Transisi status kelas yang tidak valid ditolak server. Sesi `cancelled`/`no_show`/`rescheduled` menyimpan riwayat tetapi tidak dihitung sebagai JP aktual atau JP wajib rekap.

### 3.4 Matriks Hak Akses

| Aksi | Admin | Instruktur | Siswa |
|---|---|---|---|
| Menambah/mengubah kursus | ✅ | ❌ | ❌ |
| Mengelola cabang (buat/ubah/status/hapus) | ✅ | ❌ | ❌ |
| Melihat kalender & jadwal per cabang | ✅ (semua cabang) | ❌ | ❌ |
| Mengelola versi kurikulum (buat/publish/clone/retire/hapus draft) | ✅ | ❌ | ❌ |
| Membuat/mengubah angkatan/kelas | ✅ | ❌ | ❌ |
| Menyusun/mengubah jadwal sesi | ✅ (semua kelas) | ✅ (kelas diampu) | ❌ |
| Menghapus/membatalkan sesi | ✅ (semua kelas) | ✅ (kelas diampu, belum completed & belum berpresensi) | ❌ |
| Mencatat/mengoreksi presensi | ✅ (semua kelas) | ✅ (kelas diampu) | ❌ |
| Melihat presensi & rekap kehadiran | ✅ (semua kelas) | ✅ (kelas diampu) | ✅ (milik sendiri) |
| Menugaskan/mengganti tentor kelas | ✅ | ❌ | ❌ |
| Menambah/mengubah pengguna | ✅ | ❌ | ❌ |
| Mendaftarkan siswa ke kelas | ✅ | ❌ | ❌ |
| Melihat kelas/kursus yang diampu | ✅ (semua) | ✅ (miliknya) | ❌ |
| Membuat modul & materi | ❌ | ✅ (kursus sendiri, versi Draft/Published) | ❌ |
| Memberi nilai | ❌ | ✅ (kelas yang diampu) | ❌ |
| Melihat materi kelas | ✅ (pratinjau) | ✅ (pratinjau) | ✅ (kelas diikuti & kursus Published, sesuai mode akses) |
| Mengatur mode akses materi kelas | ✅ (semua kelas, override wajib alasan) | ✅ (kelas diampu) | ❌ |
| Mengelola materi tambahan kelas | ✅ (semua kelas) | ✅ (kelas diampu) | ❌ |
| Mengonfirmasi penyelesaian materi peserta | ✅ (semua kelas) | ✅ (kelas diampu) | ❌ |
| Menandai materi selesai | ❌ | ✅ (atas nama peserta di kelas diampu) | ✅ (bila kelas tidak mewajibkan persetujuan) |
| Melihat sertifikat Draft | ✅ | ✅ (kelas yang diampu) | ❌ |
| Menyetujui sertifikat | ✅ | ❌ | ❌ |
| Melihat sertifikat Publish | ✅ | ✅ | ✅ (milik sendiri) |

Seluruh aturan di atas ditegakkan **di server** (`api/actions.php` + `api/helpers.php`); UI hanya menyembunyikan aksi sebagai lapisan kenyamanan.

### 3.5 Status Implementasi Demo

| Langkah | Status | Catatan Implementasi |
|---|---|---|
| Admin menambah kursus baru berstatus Draft | ✅ Selesai | `ELIPS.createCourse` selalu memaksa status `draft`; form tambah tidak lagi bisa memilih Published |
| Admin menambah akun instruktur | ✅ Selesai | `admin/users.html` - tambah pengguna role Instruktur |
| Admin menyusun versi kurikulum | ✅ Selesai | `admin/academic.html` + `ELIPS.createCurriculumVersion/updateCurriculumVersion/publishCurriculumVersion/cloneCurriculumVersion/retireCurriculumVersion`; API `curriculum.*` |
| Modul/materi terhubung ke versi kurikulum | ✅ Selesai | `modules.curriculum_version_id`, `lessons.curriculum_version_id`; snapshot hanya memuat versi aktif per kursus |
| Versi Draft dapat dipublikasikan | ✅ Selesai | `curriculum.publish`; versi harus memiliki struktur yang valid |
| Versi terkunci saat kelas dibuat | ✅ Selesai | `class.create` mengunci versi dalam transaksi; `locked`/`retired` menolak perubahan modul/materi/versi |
| Revisi kurikulum via clone | ✅ Selesai | `curriculum.clone` menyalin modul dan materi ke Draft baru |
| Versi terpakai dipensiunkan, bukan dihapus | ✅ Selesai | `curriculum.retire`; `curriculum.delete` hanya untuk Draft tanpa kelas |
| Admin membuat angkatan/kelas | ✅ Selesai | `class.create/update/status/delete`; kode kelas unik, periode tervalidasi, kuota > 0 |
| Admin mengelola cabang | ✅ Selesai | `admin/settings.html` + `branch.create/update/status/delete`; kode cabang unik, status aktif/nonaktif |
| Kelas terkait cabang | ✅ Selesai | `classes.branch_id` (nullable, `ON DELETE SET NULL`); cabang nonaktif ditolak saat create/update |
| Kalender & jadwal per cabang | ✅ Selesai | `admin/schedule.html`; filter cabang/kelas/instruktur/lokasi dan multi-status, kalender bulanan, agenda harian, ringkasan JP, daftar sesi, detail sesi, aksi langsung (buat/edit/jadwalkan ulang/batalkan/selesai/hapus), serta filter tersimpan di URL |
| Satu kelas = satu tentor, histori pergantian | ✅ Selesai | `classes.instructor_id` + `class_instructor_histories`; `class.assignInstructor` menutup histori lama |
| Satu tentor dapat mengampu banyak kelas | ✅ Selesai | Dashboard instruktur menampilkan tabel “Kelas yang Diampu” |
| Enrollment berbasis kelas | ✅ Selesai | `enrollments.class_id`; `enrollment.assignClass` dan `enrollment.classStatus` |
| Kuota dan status enrollment | ✅ Selesai | Kuota dicek saat pendaftaran; kuota tidak boleh diturunkan di bawah peserta aktif; status `active/completed/withdrawn/cancelled` |
| Jadwal sesi tunggal & berulang | ✅ Selesai | `session.create`/`session.createBulk`; tanggal eksplisit atau `startDate` + `weekdays` + `occurrences` (maks. 52) |
| JP rencana/aktual & lifecycle sesi | ✅ Selesai | `session.update`/`session.status`; sesi `cancelled`/`no_show`/`rescheduled` tidak dihitung JP aktual; pembatalan dapat menyertakan `reason` yang disimpan ke catatan dan audit |
| Reschedule dengan sesi pengganti | ✅ Selesai | Sesi asal → `rescheduled`; sesi baru menyimpan `rescheduledFromId` |
| Presensi per sesi & enrollment | ✅ Selesai | `attendance.saveBulk`; unik `(sessionId, enrollmentId)`; status `present/late/permission/sick/absent/excused` |
| Koreksi presensi dengan audit | ✅ Selesai | `attendance.correct` + tabel `attendance_corrections` (nilai lama/baru, aktor, alasan, waktu) |
| Rekap kehadiran per kebijakan | ✅ Selesai | `attendance.recap`/`attendance.myRecap`; persentase = JP hadir ÷ JP wajib × 100 |
| UI jadwal & presensi admin/instruktur | ✅ Selesai | Tabel sesi, modal single/berulang, modal presensi dengan alasan koreksi, dan modal rekap |
| Peserta melihat rekap sendiri | ✅ Selesai | `student/dashboard.html` menampilkan statistik, kebijakan, dan detail sesi milik peserta |
| Mode akses materi per kelas | ✅ Selesai | `class_material_settings`; `material.settings`/`material.updateSettings`; mode `open`, `sequential`, `scheduled`, `conditional` |
| Override pengaturan akses oleh Admin | ✅ Selesai | `admin/academic.html` menyediakan modal per kelas; perubahan pada baris yang sudah ada wajib alasan dan dicatat ke audit |
| Materi tambahan kelas | ✅ Selesai | `class_materials`; `material.create/update/status/delete`; tipe teks/dokumen/video/link/file, status wajib/opsional/referensi |
| Progress materi sebagai data pendukung | ✅ Selesai | `class_lesson_progress` dan `class_material_progress`; `material.progress`; snapshot menyertakan koleksi progres |
| File privat & kontrol unduh | ✅ Selesai | `file_path` tidak pernah masuk snapshot; konten materi yang terkunci tidak dikirim ke peserta; unduhan mengikuti status akses |
| Persetujuan instruktur penyelesaian materi | ✅ Selesai | `class_material_settings.require_instructor_approval`; peserta tidak dapat menandai selesai sampai instruktur/Admin mengonfirmasi |
| UI materi Admin, Instruktur, Peserta | ✅ Selesai | Modal akses di `admin/academic.html`, panel materi di `instructor/manage-course.html`, sidebar & konten kelas di `learning.html` |
| Nilai & sertifikat per enrollment | ✅ Selesai | Unique per `enrollment_id`, sehingga angkatan berbeda punya nilai/sertifikat terpisah |
| Admin menambah akun siswa | ✅ Selesai | `admin/users.html` dan `register.html` |
| 1 siswa dapat dikaitkan ke beberapa kelas | ✅ Selesai | `admin/enrollment.html` dan modal roster `admin/academic.html` |
| Instruktur login dan hanya melihat kelas/kursus sendiri | ✅ Selesai | `instructor/dashboard.html`, `instructor/manage-course.html` |
| Instruktur membuat modul dan materi | ✅ Selesai | `instructor/manage-course.html`; ditolak pada versi Locked/Retired |
| Siswa hanya mengakses kelas yang diikuti | ✅ Selesai | `learning.html` memvalidasi enrollment kelas, status kursus, versi terkunci, dan urutan materi |
| Instruktur memberi nilai di akhir kursus | ✅ Selesai | `instructor/students.html`; nilai per enrollment |
| Nilai terintegrasi ke e-sertifikat Draft | ✅ Selesai | Nilai disimpan di `grades`; Draft dibuat di `certificates` |
| Sertifikat Draft tidak tampil di halaman siswa | ✅ Selesai | `student/certificates.html` hanya membaca `getPublishedCertificatesByStudent` |
| Admin menyetujui Draft menjadi Publish | ✅ Selesai | `admin/certificates.html` memanggil `ELIPS.approveCertificate` |
| Publikasi kursus tervalidasi | ✅ Selesai | `ELIPS.setCourseStatus` menolak publikasi tanpa tentor/kurikulum berisi materi |
| Audit trail perubahan kursus, kurikulum, kelas, nilai, dan sertifikat | ✅ Selesai | `assets/js/audit.js` dan tabel `audit_logs`; halaman `admin/audit.html` |
| Notifikasi email simulasi | ✅ Selesai | `assets/js/notifications.js` dan tabel `notifications`; halaman `admin/notifications.html` |
| Backend PHP + MySQL | ✅ Selesai | `api/` menyediakan autentikasi sesi, CRUD v3/v4, snapshot, audit, notifikasi, dan seeding data demo |
| Dual-mode client | ✅ Selesai | `assets/js/api.js` memakai API saat tersedia dan otomatis fallback ke `localStorage`; `crud-academic.js` menyediakan aturan v4 di kedua mode |
| Absensi staf dengan kamera di dashboard Admin | ✅ Selesai | Hero **Absensi Kehadiran** membuka kamera, mengambil foto, dan merekam waktu, lokasi GPS otomatis, serta metadata perangkat; tanggal/jam ditentukan server, GPS wajib, dengan fallback unggah foto, riwayat pribadi, dan modal berhasil. Role `karyawan` sudah didukung API (halaman karyawan menyusul Fase 3) |
| Tugas per kelas dengan lampiran & rubrik | ✅ Selesai | `assignments`, `assignment_files`, `assignment_rubrics`; `assignment.create/update/status/delete`; halaman instruktur & peserta |
| Submission, review, dan revisi tugas | ✅ Selesai | `submissions`, `submission_files`, `submission_reviews`; `submission.submit/review/revision/accept`; unik per `(assignmentId, enrollmentId)` |
| Ujian, peserta, dan hasil ujian | ✅ Selesai | `exams`, `exam_participants`, `exam_results`; `exam.create/update/status/delete/participants/result`; skor kosong tidak dianggap 0 |
| Skema penilaian per kelas | ✅ Selesai | `grade_schemes` + `grade_scheme_components`; `gradeScheme.save/status/delete`; total bobot wajib 100% saat publish |
| Roster & nilai komponen | ✅ Selesai | `grade.roster`, `grade.manualSave`, `grade.exempt`; komponen tugas/ujian/manual; pengecualian wajib alasan |
| Finalisasi nilai | ✅ Selesai | `grade.finalize`; status `draft`/`incomplete`/`finalized`; snapshot komponen dan penguncian nilai |
| Predikat A–E konfigurabel | ✅ Selesai | `predicate_ranges` + `predicate.list/save`; editor di `admin/settings.html`; validasi cakupan 0–100 tanpa celah/tumpang tindih |
| Koreksi nilai beraudit | ✅ Selesai | `grade.correct` + `grade_corrections`; wajib alasan ≥5 karakter; koreksi komponen atau override nilai akhir |
| Transkrip nilai peserta | ✅ Selesai | `grade.transcript`; panel transkrip di `learning.html`; peserta hanya melihat miliknya sendiri |

---

## 4. Arsitektur (Dual-Mode: Server PHP/MySQL + Demo `localStorage`)

Aplikasi berjalan dalam dua mode yang memakai UI dan aturan bisnis yang sama:

- **Mode server (utama):** halaman disajikan melalui HTTP/HTTPS dan `assets/js/config.js` menyediakan `window.ELIPS_CONFIG.apiBase`. `assets/js/api.js` mengambil snapshot awal dari API, menyimpan cache sinkron di memori, lalu setiap operasi tulis memanggil aksi API. Backend PHP memvalidasi sesi, role, kepemilikan kursus, status, dan aturan bisnis sebelum menulis ke MySQL.
- **Mode lokal (fallback):** jika halaman dibuka lewat `file://` atau backend tidak tersedia, aplikasi otomatis memakai `localStorage` seperti demo lama. Mode ini dipertahankan agar demo tetap dapat dijalankan tanpa server dan seluruh regression test lama tetap valid.

### 4.1 Frontend

- **Frontend:** HTML5, CSS3 (custom dengan variabel CSS), Vanilla JavaScript
- **Business Logic & CRUD Layer:** JavaScript modular di `assets/js/`:
  - `core.js` - namespace, storage helper, result object, validasi dasar;
  - `config.js` - menentukan `apiBase` relatif terhadap halaman;
  - `api.js` - jembatan dual-mode, cache snapshot, `ELIPS.api.call`, dan `ELIPS.api.refresh`;
  - `data.js` - getter/setter seluruh koleksi data (dari cache pada mode server);
  - `seed.js` - data demo awal dan migrasi data lama (dilewati pada mode server);
  - `sync.js` - sinkronisasi nama instruktur, kepemilikan konten, dan counter kursus;
  - `progress.js` - struktur kursus, progres, dan ringkasan siswa/instruktur;
  - `auth.js` - session, role guard, dan logout (memanggil `auth.logout` pada mode server);
  - `audit.js` - audit trail; pada mode server pencatatan dilakukan backend;
  - `notifications.js` - outbox notifikasi; pada mode server notifikasi dibuat backend;
  - `crud-users.js`, `crud-courses.js`, `crud-enrollments.js`, `crud-content.js`, `crud-academic.js`, `crud-grades.js` - CRUD dan aturan bisnis per domain; pada mode server fungsi ini meneruskan aksi ke API. `crud-academic.js` menangani versi kurikulum, kelas/angkatan, histori tentor, roster kelas, validasi kuota, jadwal sesi, presensi, koreksi, dan rekap kehadiran di kedua mode;
  - `utils.js` - helper format, escape HTML, dan toast;
  - `ui.js` - perilaku UI bersama (navigasi, sidebar, katalog, login, logout).
  - Halaman hanya menangani rendering dan interaksi UI.
  - Setiap fungsi CRUD mengembalikan `{ ok, message, data }` agar pesan sukses/gagal konsisten.
  - Modul dimuat berurutan: `config → core → api → utils → data → seed → sync → progress → auth → audit → notifications → crud-* → ui`.

### 4.2 Backend PHP/MySQL

Backend berada di folder `api/` dan terdiri dari front controller, konfigurasi, koneksi, helper, snapshot, aksi bisnis, skema, dan installer. Daftar file lengkap ada di §5 bagian **Backend (`api/`)**; detail tabel database ada di `erd.md` §16.

- **Autentikasi:** sesi PHP native (`$_SESSION`) dengan cookie `HttpOnly`, `SameSite=Lax`; password disimpan dengan `password_hash()` dan diverifikasi dengan `password_verify()`.
- **Otorisasi:** setiap aksi memeriksa role dan kepemilikan di server. Contoh: instruktur hanya boleh mengelola kursus/kelas yang diampu; peserta hanya boleh menandai progres miliknya; hanya Admin yang boleh membuat kurikulum/kelas, mempublikasikan kursus, menyetujui sertifikat, atau membersihkan audit/notifikasi.
- **Aturan akademik v4:** versi kurikulum dikunci secara transaksional saat kelas dibuat; perubahan pada versi `locked`/`retired` ditolak; kuota kelas dicek saat pendaftaran; kelas dengan enrollment tidak dapat dihapus; nilai/sertifikat unik per enrollment.
- **Snapshot:** respons tulis menyertakan snapshot terbaru dan data sesi, sehingga cache client selalu identik dengan database tanpa reload halaman. Snapshot memuat modul/materi dari versi kurikulum aktif tiap kursus, sedangkan struktur versi lengkap diambil melalui `curriculum.detail`.
- **Sanitasi:** konten materi tipe teks disanitasi di server dengan allowlist tag/atribut sebelum disimpan.

### 4.3 Penyimpanan Data

- **MySQL (mode server):** tabel `users`, `categories`, `courses`, `curriculum_versions`, `classes`, `class_instructor_histories`, `modules`, `lessons`, `enrollments`, `class_sessions`, `attendances`, `attendance_corrections`, `grades`, `certificates`, `completed_lessons`, `audit_logs`, `notifications` (lihat `erd.md`).
- **`localStorage` (mode lokal/fallback):** key `elips_users`, `elips_branches`, `elips_categories`, `elips_courses`, `elips_curriculumVersions`, `elips_classes`, `elips_classInstructorHistories`, `elips_enrollments`, `elips_classSessions`, `elips_attendances`, `elips_attendanceCorrections`, `elips_modules`, `elips_lessons`, `elips_grades`, `elips_certificates`, `elips_completedLessons`, `elips_auditLogs`, `elips_notifications`, dan `elips_session`.
- **Styling:** Custom CSS (`assets/css/style.css`), Bootstrap Icons, Google Fonts (Inter)
- **Struktur Folder:** Berbasis role (`admin/`, `instructor/`, `student/`)

---

## 5. Struktur File yang Telah Dikembangkan

### Halaman Publik
| File | Status | Deskripsi |
|---|---|---|
| `index.html` | ✅ Selesai | Beranda dengan daftar kursus |
| `course-detail.html` | ✅ Selesai | Detail kursus, tombol enrolment dinamis (berubah menjadi "Sudah Terdaftar - Lanjutkan Belajar") |
| `login.html` | ✅ Selesai | Halaman login dengan validasi email + password; mode server diverifikasi PHP (`password_verify`), mode lokal terhadap `elips_users`, lalu redirect berbasis role |
| `register.html` | ✅ Selesai | Halaman registrasi |
| `verify-certificate.html` | ✅ Selesai | Verifikasi keaslian sertifikat publik |
| `certificate-view.html` | ✅ Selesai | Tampilan sertifikat dinamis dari URL params dan snapshot/cache data |
| `learning.html` | ✅ Selesai | Halaman belajar dengan tracking pelajaran selesai, materi tambahan kelas, panel tugas/ujian peserta, dan transkrip nilai pribadi; mendukung mode pratinjau untuk instruktur/admin tanpa mengubah progres peserta |

### Role Admin (`admin/`)
| File | Status | Deskripsi |
|---|---|---|
| `admin/dashboard.html` | ✅ Selesai | Dashboard admin dengan ringkasan statistik |
| `admin/users.html` | ✅ Selesai | Tambah pengguna diawali modal pilihan role Admin/Instruktur/Peserta, edit/hapus pengguna, filter role & pencarian, serta penugasan instruktur ke kursus |
| `admin/courses.html` | ✅ Selesai | Tambah/edit kursus, tugaskan 1 instruktur per kursus, publikasi/Draft, filter status & pencarian |
| `admin/academic.html` | ✅ Selesai | Kelola versi kurikulum (buat/publish/clone/retire/hapus draft), kelas/angkatan (versi terkunci, cabang, tentor, kuota, periode), histori tentor, roster peserta, detail struktur kurikulum, serta jadwal sesi, presensi, koreksi, dan rekap kehadiran; filter cabang & kursus |
| `admin/schedule.html` | ✅ Selesai | Kalender & jadwal admin: filter cabang/kelas/instruktur/lokasi dan multi-status dengan sinkronisasi URL, kalender bulanan, agenda harian, ringkasan JP, daftar sesi, modal detail sesi dengan aksi langsung (buat, edit, jadwalkan ulang, batalkan dengan alasan, tandai selesai, hapus), serta tautan ke akademik/pengaturan |
| `admin/enrollment.html` | ✅ Selesai | Pendaftaran siswa ke kelas, filter kelas/status, aktif/nonaktifkan enrollment |
| `admin/certificates.html` | ✅ Selesai | Persetujuan e-sertifikat: Draft → Publish, filter status, dan tampilan nomor/tanggal persetujuan |
| `admin/notifications.html` | ✅ Selesai | Outbox notifikasi email simulasi: statistik, filter jenis/penerima/tanggal/pencarian, detail isi, ekspor CSV, dan pembersihan outbox |
| `admin/audit.html` | ✅ Selesai | Audit trail: statistik aktivitas, filter aktor/aksi/entitas/tanggal/pencarian, ekspor CSV, dan pembersihan log |
| `admin/reports.html` | ✅ Selesai | Laporan progres peserta per kursus, statistik per kursus, export PDF/Excel (UI) |
| `admin/settings.html` | ✅ Selesai | Pengaturan umum, manajemen cabang (tabel + modal tambah/edit/status/hapus), editor predikat nilai A–E dengan validasi cakupan 0–100, pengaturan sertifikat, pengaturan pembelajaran |

### Role Instruktur (`instructor/`)
| File | Status | Deskripsi |
|---|---|---|
| `instructor/dashboard.html` | ✅ Selesai | Dashboard dinamis: statistik kursus/peserta/pelajaran/sertifikat, notifikasi draft & penilaian tertunda, daftar kursus, aktivitas peserta terbaru, jadwal sesi kelas yang diampu, pencatatan presensi, dan rekap kehadiran |
| `instructor/manage-course.html` | ✅ Selesai | Kelola struktur kursus: tambah/ubah/hapus modul dan materi (teks, video, file, link) dengan validasi kepemilikan kursus |
| `instructor/students.html` | ✅ Selesai | Daftar peserta per kursus dengan filter status/pencarian, progres, editor skema penilaian dan komponen berbobot, roster nilai, finalisasi, koreksi beraudit, riwayat nilai, serta modal penilaian klasik yang otomatis membuat e-sertifikat Draft |
| `instructor/certificates.html` | ✅ Selesai | Monitoring e-sertifikat peserta (Draft/Terbit), filter kursus & status, pengajuan sertifikat untuk peserta yang sudah dinilai namun belum memiliki sertifikat |
| `instructor/profile.html` | ✅ Selesai | Profil instruktur: ringkasan statistik, informasi akun, pembaruan nama/no. telepon, dan sinkronisasi nama pada kursus yang diampu |

### Role Peserta (`student/`)
| File | Status | Deskripsi |
|---|---|---|
| `student/dashboard.html` | ✅ Selesai | Dashboard peserta dengan kursus yang diikuti, progres, nilai, status sertifikat, ringkasan statistik, serta persentase dan rekap kehadiran per kelas (hanya milik sendiri) |
| `student/courses.html` | ✅ Selesai | Daftar seluruh kursus peserta dengan filter status, pencarian, progres, nilai, dan akses belajar |
| `student/certificates.html` | ✅ Selesai | Daftar sertifikat peserta; hanya sertifikat **Publish** yang ditampilkan, Draft sepenuhnya disembunyikan |
| `student/profile.html` | ✅ Selesai | Profil peserta dan pembaruan nama/no. telepon |

### Assets
| File | Status | Deskripsi |
|---|---|---|
| `assets/css/style.css` | ✅ Selesai | Styling utama untuk seluruh aplikasi |
| `assets/js/config.js` | ✅ Selesai | Menentukan `apiBase` relatif terhadap halaman (dilewati pada bundle uji JSDOM) |
| `assets/js/api.js` | ✅ Selesai | Jembatan dual-mode: bootstrap snapshot, cache sinkron, panggilan aksi API, dan fallback lokal |
| `assets/js/core.js` | ✅ Selesai | Namespace, storage helper, result object, dan validasi dasar |
| `assets/js/utils.js` | ✅ Selesai | Helper grade, nomor sertifikat, escape HTML, format tanggal, toast |
| `assets/js/data.js` | ✅ Selesai | Getter/setter seluruh koleksi data, termasuk cabang, sesi, presensi, audit koreksi, tugas/ujian, predikat, skema penilaian, nilai komponen, dan koreksi nilai; mesin komputasi nilai lokal |
| `assets/js/seed.js` | ✅ Selesai | Data demo awal (termasuk cabang, sesi & presensi, tugas/ujian, dan skema penilaian) serta normalisasi/migrasi data lama |
| `assets/js/sync.js` | ✅ Selesai | Sinkronisasi nama instruktur, kepemilikan konten, counter kursus |
| `assets/js/progress.js` | ✅ Selesai | Struktur kursus, progres belajar, overview siswa/instruktur |
| `assets/js/auth.js` | ✅ Selesai | Session, user aktif, redirect, dan role guard |
| `assets/js/audit.js` | ✅ Selesai | Pencatatan audit trail, filter log, daftar aktor, dan pembersihan log |
| `assets/js/notifications.js` | ✅ Selesai | Outbox notifikasi email simulasi, filter, daftar penerima, dan pembersihan outbox |
| `assets/js/crud-users.js` | ✅ Selesai | CRUD pengguna dan penugasan instruktur |
| `assets/js/crud-courses.js` | ✅ Selesai | CRUD kursus dan aturan Draft/Publish |
| `assets/js/crud-enrollments.js` | ✅ Selesai | CRUD pendaftaran peserta ke kelas |
| `assets/js/crud-content.js` | ✅ Selesai | CRUD modul dan materi dengan validasi kepemilikan dan versi kurikulum |
| `assets/js/crud-academic.js` | ✅ Selesai | CRUD cabang, versi kurikulum, kelas/angkatan, histori tentor, roster, validasi kuota, jadwal sesi (tunggal/berulang), presensi, koreksi beralasan, dan rekap kehadiran |
| `assets/js/crud-tasks.js` | ✅ Selesai | CRUD tugas, lampiran, rubrik, submission, review/revisi, ujian, peserta, dan hasil ujian dengan validasi kepemilikan dan status |
| `assets/js/crud-grades.js` | ✅ Selesai | Skema penilaian, komponen berbobot, roster & nilai komponen, pengecualian, finalisasi, koreksi beraudit, transkrip, serta alur sertifikat Draft → Publish (paritas server/lokal) |
| `assets/js/ui.js` | ✅ Selesai | Perilaku UI bersama: navigasi, sidebar, katalog, login, logout |

### Backend (`api/`)
| File | Status | Deskripsi |
|---|---|---|
| `api/index.php` | ✅ Selesai | Front controller API dan pengaturan sesi PHP |
| `api/config.php` | ✅ Selesai | Konfigurasi database (override via environment variable) |
| `api/db.php` | ✅ Selesai | Koneksi PDO MySQL dan timezone UTC |
| `api/helpers.php` | ✅ Selesai | Sesi, otorisasi, serializer (termasuk cabang, sesi/presensi/koreksi, tugas/ujian, predikat, skema, dan nilai), mesin komputasi nilai, kebijakan kehadiran, rekap, sanitasi HTML, audit, dan notifikasi |
| `api/snapshot.php` | ✅ Selesai | Snapshot koleksi untuk cache client, termasuk `branches`, `classSessions`, `attendances`, `attendanceCorrections`, tugas/ujian, predikat, skema penilaian, nilai komponen, dan koreksi |
| `api/actions.php` | ✅ Selesai | 92 aksi bisnis dan validasi role/kepemilikan di server, termasuk `branch.*`, `session.*`, `attendance.*`, `assignment.*`, `submission.*`, `exam.*`, `predicate.*`, `gradeScheme.*`, dan aksi nilai Phase 6 |
| `api/schema.sql` | ✅ Selesai | Skema MySQL 8 (37 tabel), foreign key, dan index |
| `api/install.php` | ✅ Selesai | Installer/seeder data demo (`--fresh` untuk reset), termasuk cabang, sesi, presensi, tugas/ujian, predikat, dan skema penilaian; migrasi kolom Phase 4–6 untuk database lama |

---

## 6. Fitur yang Telah Diimplementasikan

### 6.1 Autentikasi & Role-Based Access
| Fitur | Status | Keterangan |
|---|---|---|
| Login form | ✅ Selesai | Form login di `login.html`, email + password diverifikasi melalui `ELIPS.authenticateUser()`; pada mode server verifikasi dilakukan PHP (`password_verify`) dan sesi disimpan di `$_SESSION`. Akun demo memakai password `elips123` |
| Role redirect berdasarkan role akun | ✅ Selesai | `admin` → `admin/dashboard.html`, `instruktur` → `instructor/dashboard.html`, `peserta` → `student/dashboard.html` |
| Role guard halaman | ✅ Selesai | Halaman admin memanggil `ELIPS.requireRole('admin')`; halaman instruktur memanggil `ELIPS.requireRole('instruktur')`; halaman peserta memeriksa sesi/role. `getCurrentUser()` hanya membaca sesi, tanpa fallback path. API juga menolak aksi yang tidak sesuai role |
| Password akun | ✅ Selesai | Password wajib minimal 8 karakter saat Admin membuat pengguna/registrasi; pada mode server disimpan sebagai hash `password_hash()` dan tidak pernah dikirim ke client |
| Sanitasi materi teks | ✅ Selesai | `ELIPS.sanitizeHtml()` pada client dan `elips_sanitize_html()` pada server memakai allowlist tag/atribut untuk mencegah stored XSS |
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
| Versi kurikulum per kursus | ✅ Selesai | `admin/academic.html`; status draft/published/locked/retired; kode versi unik per kursus |
| Penguncian kurikulum per kelas | ✅ Selesai | Versi berubah `locked` saat kelas dibuat; perubahan modul/materi/versi ditolak server |
| Clone & retire versi kurikulum | ✅ Selesai | Clone menyalin struktur ke Draft baru; versi terpakai dipensiunkan, bukan dihapus |

### 6.2a Manajemen Kelas/Angkatan
| Fitur | Status | Keterangan |
|---|---|---|
| CRUD kelas/angkatan | ✅ Selesai | `admin/academic.html` dan aksi API `class.create/update/status/delete` |
| Cabang pada kelas | ✅ Selesai | `classes.branch_id`; pilihan cabang pada modal kelas, kolom cabang, dan filter cabang di `admin/academic.html` |
| Manajemen cabang | ✅ Selesai | `admin/settings.html`; aksi API `branch.create/update/status/delete`; kode unik dan status aktif/nonaktif |
| Pilih versi kurikulum | ✅ Selesai | Hanya versi `published`/`locked` yang dapat dipilih; versi langsung dikunci |
| Satu tentor per kelas | ✅ Selesai | `classes.instructor_id`; tentor harus akun instruktur aktif |
| Histori pergantian tentor | ✅ Selesai | `class_instructor_histories`; entri lama ditutup saat `class.assignInstructor` |
| Kuota dan status kelas | ✅ Selesai | Kuota dicek saat pendaftaran; status draft/open/ongoing/completed/cancelled/archived |
| Kelas yang diampu (Instruktur) | ✅ Selesai | Dashboard instruktur menampilkan tabel “Kelas yang Diampu” |

### 6.2b Jadwal Sesi & Presensi

| Fitur | Status | Keterangan |
|---|---|---|
| Jadwal sesi tunggal | ✅ Selesai | `admin/academic.html` dan `instructor/dashboard.html`; aksi `session.create` |
| Kalender & jadwal admin | ✅ Selesai | `admin/schedule.html`; filter cabang/kelas/instruktur/lokasi dan multi-status, kalender bulanan, agenda harian, ringkasan JP, daftar sesi, modal detail sesi dengan aksi langsung |
| Jadwal berulang | ✅ Selesai | `session.createBulk` dengan `dates[]` atau `startDate` + `weekdays` (ISO 1–7) + `occurrences` (maks. 52) |
| Modul terkait sesi | ✅ Selesai | Sesi dapat merujuk modul dari versi kurikulum kelas; modul hanya informatif dan tidak mengubah struktur |
| JP rencana & aktual | ✅ Selesai | `plannedJp`/`actualJp`; sesi `cancelled`/`no_show`/`rescheduled` dikecualikan dari JP aktual |
| Lifecycle sesi | ✅ Selesai | `scheduled`, `confirmed`, `completed`, `rescheduled`, `cancelled`, `no_show`; sesi `completed` tidak dapat dihapus |
| Hapus vs batalkan sesi | ✅ Selesai | Sesi dengan presensi tidak dapat dihapus; gunakan status `cancelled` agar riwayat tetap utuh |
| Presensi per sesi & enrollment | ✅ Selesai | `attendance.saveBulk`; unik per `(sessionId, enrollmentId)`; status `present`, `late`, `permission`, `sick`, `absent`, `excused` |
| Koreksi presensi | ✅ Selesai | `attendance.correct` wajib alasan; baris `attendance_corrections` menyimpan nilai lama/baru, aktor, waktu, dan alasan |
| Rekap kehadiran | ✅ Selesai | `attendance.recap` (admin/instruktur) dan `attendance.myRecap` (peserta); persentase dibulatkan 2 desimal |
| Kebijakan kehadiran | ✅ Selesai | `attendance_present_statuses` = `present`+`late`; `attendance_counted_statuses` = `confirmed`+`completed`; dapat diubah di `api/config.php` |
| Akses peserta | ✅ Selesai | Peserta hanya membaca presensi/rekap miliknya; akses lintas peserta ditolak server |

### 6.2c Materi Kelas & Mode Akses

| Fitur | Status | Keterangan |
|---|---|---|
| Materi versi kurikulum | ✅ Selesai | Modul/materi tetap terikat `curriculum_version_id`; kelas memakai versi yang dikunci saat kelas dibuat |
| Pengaturan akses per kelas | ✅ Selesai | `class_material_settings` satu baris per kelas; kelas tanpa baris memakai default implisit `open` |
| Mode `open` | ✅ Selesai | Semua materi aktif dapat dibuka peserta |
| Mode `sequential` | ✅ Selesai | Pelajaran berikutnya terbuka setelah seluruh pelajaran sebelumnya selesai dikonfirmasi |
| Mode `scheduled` | ✅ Selesai | Pelajaran terbuka saat modulnya memiliki sesi non-cancelled/no-show/rescheduled yang tanggalnya sudah tiba |
| Mode `conditional` | ✅ Selesai | Menggabungkan prasyarat urutan dan tanggal sesi modul |
| Materi tambahan kelas | ✅ Selesai | `class_materials`; tidak mengubah kurikulum, dapat ditautkan ke pelajaran/sesi, memiliki `available_from` dan urutan |
| Status materi tambahan | ✅ Selesai | `active`/`hidden`; perubahan status wajib alasan dan dicatat audit |
| Persetujuan instruktur | ✅ Selesai | Bila aktif, peserta tidak dapat menandai selesai; instruktur/Admin dapat mengonfirmasi progres untuk enrollment |
| Pratinjau staf | ✅ Selesai | Admin/instruktur pengampu dapat melihat materi terkunci tanpa mengubah progres peserta |
| Snapshot aman | ✅ Selesai | Snapshot hanya menyimpan metadata materi; `content` dan `file_path` tidak dikirim |

### 6.2d Tugas & Ujian (Phase 5)

| Fitur | Status | Keterangan |
|---|---|---|
| CRUD tugas per kelas | ✅ Selesai | `assignments`; aksi `assignment.create/update/status/delete`; judul, instruksi, tipe, jendela waktu, skor maksimum, status |
| Tipe tugas | ✅ Selesai | `file_upload`, `text_submission`, `in_class_practice`, `external_reference`; tipe tertentu tidak menerima submission daring |
| Lampiran & rubrik tugas | ✅ Selesai | `assignment_files` dan `assignment_rubrics`; metadata file privat tidak diekspos ke peserta |
| Submission peserta | ✅ Selesai | `submission.submit`; unik per `(assignmentId, enrollmentId)`; menolak sebelum `openAt`, menandai `late`, dan menolak setelah tugas ditutup |
| Berkas jawaban | ✅ Selesai | `submission_files`; path privat tidak pernah masuk snapshot |
| Review, revisi, dan penerimaan | ✅ Selesai | `submission.review/revision/accept`; `submission_reviews` menyimpan riwayat aksi; submission `accepted` terkunci |
| CRUD ujian per kelas | ✅ Selesai | `exams`; aksi `exam.create/update/status/delete`; tipe tertulis/praktik/lisan/proyek/lainnya, jadwal, durasi, skor maksimum |
| Roster peserta ujian | ✅ Selesai | `exam_participants`; aksi `exam.participants`; unik per `(examId, enrollmentId)`; status `registered/attended/absent/excused` |
| Hasil ujian | ✅ Selesai | `exam_results`; aksi `exam.result`; unik per `(examId, enrollmentId)`; skor ≤ `maxScore`; nilai `missing` tidak menjadi 0 |
| UI tugas & ujian | ✅ Selesai | Panel instruktur di `instructor/manage-course.html`/`instructor/students.html` dan panel peserta di `learning.html` |

### 6.2e Skema Penilaian, Finalisasi & Koreksi (Phase 6)

| Fitur | Status | Keterangan |
|---|---|---|
| Predikat nilai konfigurabel | ✅ Selesai | `predicate_ranges`; `predicate.list/save`; default A 90–100 … E 0–49.99; editor di `admin/settings.html`; hanya Admin |
| Validasi cakupan predikat | ✅ Selesai | Rentang aktif wajib menutup 0–100 tanpa celah/tumpang tindih; penyimpanan ditolak bila tidak valid |
| Satu skema per kelas | ✅ Selesai | `grade_schemes` dengan `classId` unik; `gradeScheme.list/save/status/delete`; hanya Admin/instruktur pengampu |
| Komponen berbobot | ✅ Selesai | `grade_scheme_components`; tipe `assignment`, `exam`, `manual`; bobot, agregasi, `maxScore`, wajib/opsional, urutan |
| Publikasi skema | ✅ Selesai | Wajib minimal satu komponen dan total bobot tepat 100%; skema `published`/`archived` tidak dapat kembali ke `draft` atau dihapus |
| Roster nilai | ✅ Selesai | `grade.roster` mengembalikan peserta, komponen, nilai, status, dan hasil komputasi per enrollment |
| Nilai komponen manual | ✅ Selesai | `grade.manualSave`; nilai 0–`maxScore`; tidak menimpa nilai finalized |
| Pengecualian komponen | ✅ Selesai | `grade.exempt`; wajib alasan minimal 5 karakter; bobot komponen dikecualikan dan dinormalisasi ulang |
| Komputasi nilai akhir | ✅ Selesai | `Σ(skor × bobot) / Σ(bobot)` dengan pembulatan skema; komponen wajib kosong => `incomplete`; skor 0 hanya bila diisi eksplisit |
| Finalisasi nilai | ✅ Selesai | `grade.finalize`; menolak nilai `incomplete`, menulis snapshot komponen, mengisi `finalizedBy`/`finalizedAt`, dan mengunci nilai |
| Koreksi nilai beraudit | ✅ Selesai | `grade.correct`; hanya nilai finalized; alasan ≥5 karakter; koreksi komponen atau override `finalScore`; baris append-only di `grade_corrections` |
| Transkrip peserta | ✅ Selesai | `grade.transcript`; nilai akhir, huruf, status, komponen, peringatan komponen kosong, dan riwayat koreksi; peserta hanya melihat miliknya |
| Paritas mode lokal | ✅ Selesai | `ELIPS.computeEnrollmentGrade()` dan aksi `crud-grades.js` meniru aturan server, termasuk kunci finalisasi dan validasi bobot |

### 6.3 Enrolment & Progres Belajar
| Fitur | Status | Keterangan |
|---|---|---|
| Penugasan siswa ke kelas | ✅ Selesai | Dilakukan Admin melalui `admin/enrollment.html` atau roster `admin/academic.html`; disimpan di `enrollments` dengan `class_id` |
| Satu siswa ke beberapa kelas/kursus | ✅ Selesai | Form pendaftaran mendukung pilihan kelas dari beberapa kursus |
| Akses kursus oleh siswa | ✅ Selesai | Siswa hanya dapat membuka materi kelas yang diikuti dan kursus berstatus Published |
| Status enrolment dinamis | ✅ Selesai | Tombol di `course-detail.html` berubah menjadi "Lanjutkan Belajar" bila sudah terdaftar |
| Halaman belajar | ✅ Selesai | `learning.html` dengan sidebar modul/lesson, penguncian sesuai mode akses kelas, dan modul **Materi Tambahan Kelas** |
| Tandai pelajaran selesai | ✅ Selesai | Mode server: `class_lesson_progress` (dan `completed_lessons` legacy); mode lokal: key `elips_classLessonProgress`/`elips_completedLessons` |
| Tracking progres | ✅ Selesai | Progres dihitung dari pelajaran selesai per enrollment; `ELIPS.isLessonCompleted()` membaca progres kelas dan data legacy |
| Kelola enrolment (Admin) | ✅ Selesai | `admin/enrollment.html` dan `admin/academic.html` - tambah pendaftaran, filter, ubah status, hapus |
| Kuota kelas | ✅ Selesai | Pendaftaran melebihi kuota ditolak; kuota tidak boleh diturunkan di bawah peserta aktif |
| Status enrollment | ✅ Selesai | `active`, `completed`, `withdrawn`, `cancelled`; status `withdrawn` mencabut akses tanpa menghapus riwayat |

### 6.4 Sertifikat
| Fitur | Status | Keterangan |
|---|---|---|
| Alur e-sertifikat | ✅ Selesai | Instruktur memberi nilai → e-sertifikat otomatis dibuat berstatus **Draft** → Admin menyetujui di `admin/certificates.html` → status menjadi **Publish** |
| Integrasi nilai → sertifikat | ✅ Selesai | Nilai/grade dari tabel `grades` (atau `elips_grades` pada mode lokal) disalin ke e-sertifikat saat penilaian klasik disimpan; finalisasi skema Phase 6 tidak otomatis membuat sertifikat |
| Sertifikat Draft tidak tampil ke siswa | ✅ Selesai | `student/certificates.html` memakai `getPublishedCertificatesByStudent`; Draft tidak dirender dan notifikasinya dihapus dari dashboard |
| Sertifikat peserta (Instruktur) | ✅ Selesai | `instructor/certificates.html` menampilkan Draft/Publish, filter kursus & status, serta pengajuan Draft untuk peserta yang sudah dinilai |
| Tampilan sertifikat | ✅ Selesai | `certificate-view.html` menampilkan sertifikat dari URL params dan data snapshot/cache |
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

### 6.5a Absensi Staf & Karyawan (Kamera + Metadata)

Fitur absensi harian di `admin/dashboard.html` dan API absensi yang kini melayani role `karyawan` (halaman khusus karyawan menyusul pada Fase 3).

| Fitur | Status | Keterangan |
|---|---|---|
| Hero Absensi Kehadiran | Selesai | Hanya menampilkan **Absen Masuk** bila belum ada catatan hari ini, **Absen Pulang** setelah absen masuk, dan badge selesai bila masuk+pulang lengkap; status hari ini menampilkan jam masuk/pulang |
| Ambil foto via kamera | Selesai | `getUserMedia` dengan pratinjau video, tombol Ambil Foto, dan Ulangi; foto dikompres menjadi JPEG sebelum disimpan |
| Fallback unggah foto | Selesai | Kamera tidak tersedia/izin ditolak/konteks tidak aman (`file://` atau non-HTTPS) diarahkan ke tombol **Unggah Foto** |
| Lokasi GPS wajib | Selesai | Server menolak absensi tanpa koordinat. Geolokasi (lat/long/akurasi) diambil otomatis, harus berpasangan, dan `locationSource` selalu `gps` untuk data baru |
| Penguncian tanggal & jam | Selesai | Tanggal dan jam ditentukan server (`Asia/Jakarta`, disimpan UTC); nilai `attendanceDate`/`takenAt`/`timezone` dari klien diabaikan |
| Modal berhasil | Selesai | Setelah simpan tampil foto, identitas akun, dan seluruh metadata; hanya satu tombol **Selesai** dan modal tidak dapat ditutup dengan Escape/backdrop |
| Metadata perangkat | Selesai | User agent, platform, bahasa, ukuran layar, memori perangkat, CPU core, tipe koneksi, status online, dan IP (server) |
| Riwayat absensi pribadi | Selesai | Daftar 30 catatan terbaru dengan foto, jenis masuk/pulang, tanggal, jam, lokasi, dan perangkat |
| Detail & hapus catatan | Selesai | Modal detail menampilkan seluruh metadata dan foto. Hanya Admin yang dapat menghapus (miliknya atau karyawan) dengan alasan ≥5 karakter; karyawan tidak dapat menghapus |
| Role `karyawan` (server) | Selesai | `users.role` menerima `karyawan`; API absensi hanya untuk `admin` + `karyawan`; instruktur/peserta ditolak. Klien dan halaman karyawan menyusul Fase 3 |
| Aturan harian di server | Selesai | Maksimal 1 `in` + 1 `out` per pengguna per hari; absen pulang wajib didahului absen masuk; dijaga validasi API dan unique key `(user_id, attendance_date, kind)` |
| Mode server | Selesai | Aksi `staffAttendance.create`, `staffAttendance.list`, dan `staffAttendance.delete`; karyawan hanya membaca miliknya, Admin dapat meminta `userId` lain |
| Mode lokal | Selesai | Koleksi `elips_staffAttendances` masih memakai aturan lama (admin saja, GPS opsional) sampai modul bersama dibuat pada Fase 2–3 |

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
| Pencatatan tugas & ujian | ✅ Selesai | Pembuatan/perubahan tugas, submission, review, ujian, dan hasil ujian tercatat dengan aktor dan entitas terkait |
| Pencatatan skema & finalisasi | ✅ Selesai | Simpan skema, publikasi/arsip, nilai komponen, pengecualian, finalisasi, dan koreksi nilai tercatat dengan alasan/detail |
| Pencatatan status sertifikat | ✅ Selesai | Pengajuan Draft dan persetujuan Draft → Publish tercatat |
| Pencatatan domain lain | ✅ Selesai | Pengguna, penugasan peserta, modul, materi, sesi pertemuan, dan presensi juga tercatat |
| Pencatatan koreksi presensi | ✅ Selesai | Koreksi menyimpan alasan, aktor, status lama/baru, dan waktu di `attendance_corrections` |
| Resolusi aktor | ✅ Selesai | Prioritas: `actorId` eksplisit → user sesi → "Sistem"; tidak memakai fallback berbasis path halaman |
| Filter & ekspor | ✅ Selesai | `admin/audit.html` memfilter berdasarkan aktor, aksi, entitas, tanggal, pencarian, dan limit; dapat diekspor ke CSV |
| Batas penyimpanan | ✅ Selesai | Maksimal 1000 entri terbaru disimpan; backend memangkas tabel `audit_logs`, mode lokal membatasi `localStorage` |

### 6.8 Notifikasi Email (Simulasi)
| Fitur | Status | Keterangan |
|---|---|---|
| Notifikasi publikasi kursus | ✅ Selesai | Saat kursus berhasil Published, outbox mencatat email untuk peserta aktif yang terdaftar dan instruktur aktif pengampu |
| Notifikasi sertifikat terbit | ✅ Selesai | Saat Admin menyetujui sertifikat Draft → Publish, outbox mencatat email untuk peserta |
| Outbox notifikasi (Admin) | ✅ Selesai | `admin/notifications.html` menampilkan waktu, penerima, jenis, subjek, isi, dan status simulasi |
| Filter & ekspor | ✅ Selesai | Filter jenis, penerima, rentang tanggal, pencarian, dan limit; hasil dapat diekspor ke CSV |
| Batas penyimpanan | ✅ Selesai | Maksimal 1000 notifikasi terbaru disimpan di `elips_notifications` |
| Pengiriman email sungguhan | ⏳ Belum | Mode server menyimpan outbox di tabel `notifications`; pengiriman nyata memerlukan SMTP/email service di produksi |

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

### Versi Kurikulum
- Data disimpan di tabel `curriculum_versions` (mode server) atau `localStorage` key `elips_curriculumVersions` (mode lokal).
- Setiap kursus dapat memiliki banyak versi; kode versi unik per kursus.
- Status: `draft → published → locked → retired`.
- Versi baru selalu `draft`. Hanya versi `published` (atau `locked` yang masih aktif) yang dapat dipakai membuat kelas.
- `class.create` mengunci versi dalam transaksi yang sama: setelah kelas tersimpan, versi berubah `locked` dan tidak dapat diubah/dihapus.
- Perubahan modul/materi hanya diizinkan pada versi `draft`/`published`; versi `locked`/`retired` ditolak server.
- Revisi dilakukan dengan `curriculum.clone`: menyalin seluruh modul/materi ke versi `draft` baru.
- `curriculum.delete` hanya untuk versi `draft` yang belum dipakai kelas; versi terpakai harus `retired`.

### Kelas/Angkatan & Tentor
- Data disimpan di tabel `classes` dan `class_instructor_histories` (mode server) atau key `elips_classes`/`elips_classInstructorHistories` (mode lokal).
- Kelas wajib merujuk satu `curriculum_version_id` dan satu `instructor_id` (boleh kosong saat draft, wajib untuk status `ready`/`ongoing`).
- Satu kelas = satu tentor aktif; satu tentor boleh mengampu banyak kelas.
- Pergantian tentor melalui `class.assignInstructor`:
  - entri histori tentor lama diisi `ended_at`;
  - entri baru dibuat dengan `started_at` dan alasan opsional;
  - penugasan tentor yang sama bersifat idempoten.
- Kuota kelas divalidasi saat pendaftaran dan saat update kuota.
- Kelas dengan enrollment tidak dapat dihapus; status `cancelled`/`archived` tidak menerima pendaftaran baru.

### Cabang & Kalender Jadwal
- Data cabang disimpan di tabel `branches` (mode server) atau key `elips_branches` (mode lokal).
- Cabang menyimpan `code` unik, `name`, `address`, `city`, `phone`, `manager`, `status` (`active`/`inactive`), dan `notes`.
- Kode cabang dapat diisi manual atau dibuat otomatis dengan prefiks `CBG-`; duplikasi ditolak.
- `classes.branch_id` bersifat nullable dan memakai `ON DELETE SET NULL`, sehingga database lama dan kelas tanpa cabang tetap valid.
- Kelas hanya boleh merujuk cabang berstatus `active`; perubahan kelas ke cabang nonaktif ditolak server.
- `branch.delete` ditolak selama masih ada kelas yang merujuk cabang tersebut; gunakan status `inactive` bila cabang tidak dipakai lagi.
- Perubahan nama/kode/kota cabang disinkronkan ke field denormalisasi `branchCode`/`branchName`/`branchCity` pada kelas dan sesi.
- `admin/schedule.html` adalah halaman operasional kalender untuk admin:
  - filter cabang, kelas pada cabang tersebut, instruktur, lokasi, dan multi-status (chip); semua filter berlaku untuk kalender, agenda harian, ringkasan bulanan, statistik, dan tabel sesi;
  - nilai filter aktif (`branch`, `class`, `instructor`, `location`, `status`, `month`, `date`) disimpan pada query string melalui `history.replaceState` sehingga tampilan dapat dibuka ulang/dibagikan; pada mode `file://` kegagalan pembaruan URL diabaikan;
  - kalender bulanan dengan chip sesi berwarna sesuai status (`scheduled`, `confirmed`, `completed`, `cancelled`/`no_show`) dan tombol tambah sesi pada sel tanggal;
  - agenda harian saat tanggal dipilih, ringkasan jumlah sesi + JP rencana/aktual, dan daftar sesi bulan berjalan;
  - modal detail sesi menampilkan kelas, cabang, kursus, tanggal/waktu, modul, tentor, JP, status, lokasi, dan ringkasan presensi;
  - aksi langsung dari kalender/daftar/modal: buat sesi (tanggal terisi otomatis), edit, jadwalkan ulang (sesi baru bertaut `rescheduledFromId` dan sesi asal menjadi `rescheduled`), batalkan dengan alasan opsional, tandai selesai (JP aktual terisi otomatis), dan hapus;
  - tombol hapus hanya tersedia untuk sesi yang belum `completed` dan belum memiliki presensi; aturan akhir tetap ditegakkan server pada mode API;
  - tautan cepat ke `admin/academic.html` (kelola sesi/kelas) dan `admin/settings.html` (kelola cabang).
- Navigasi admin menampilkan tautan **Kalender & Jadwal** pada seluruh halaman admin, setelah menu **Kurikulum & Kelas**.

### Materi dan Akses Kelas
- Pengaturan akses disimpan di tabel `class_material_settings` (mode server) atau key `elips_classMaterialSettings` (mode lokal), satu baris per kelas.
- Bila kelas belum memiliki baris pengaturan, sistem memakai default implisit: `accessMode: 'open'`, `requireInstructorApproval: false`, `id: null`. Default ini **tidak** dibuatkan baris sampai pengguna menyimpan pengaturan.
- Mode akses:
  - `open`: semua materi kurikulum aktif dapat dibuka.
  - `sequential`: pelajaran ke-n terbuka hanya bila seluruh pelajaran sebelumnya sudah selesai.
  - `scheduled`: pelajaran terbuka bila modulnya memiliki sesi dengan status dihitung dan tanggal sesi sudah lewat/hari ini.
  - `conditional`: prasyarat urutan **dan** tanggal sesi modul (bila modul memiliki sesi) harus terpenuhi.
- Materi tambahan disimpan di `class_materials` dan tidak mengubah versi kurikulum. Materi dapat ditautkan ke `lesson_id` dan/atau `session_id`, memiliki `access_level` (`required`/`optional`/`reference`), `available_from`, `status` (`active`/`hidden`), dan `sort_order`.
- Materi tambahan terkunci bila:
  - statusnya `hidden` (bagi peserta);
  - `available_from` belum tiba;
  - pelajaran tertaut belum selesai;
  - sesi tertaut dibatalkan/rescheduled/no-show atau tanggalnya belum tiba.
- Peserta hanya menerima `content` untuk materi yang tidak terkunci. Staf (Admin/instruktur pengampu) dapat melihat pratinjau tanpa mengubah progres peserta.
- Bila `require_instructor_approval` aktif, peserta tidak dapat menandai pelajaran/materi selesai. Instruktur pengampu atau Admin dapat mengonfirmasi progres untuk enrollment tersebut.
- Perubahan pengaturan yang sudah tersimpan oleh Admin wajib menyertakan alasan; audit mencatat mode dan status persetujuan sebelum/sesudah.
- Perubahan status materi (`active`/`hidden`) wajib menyertakan alasan dan dicatat audit.
- Snapshot hanya memuat metadata materi (`classMaterials`), tanpa `content` dan tanpa `file_path`; file privat tidak pernah diekspos melalui snapshot.

### Enrolment
- Data disimpan di tabel `enrollments` (mode server) atau `localStorage` key `elips_enrollments` (mode lokal), berupa array of objects.
- Pendaftaran siswa ke kelas hanya dilakukan oleh Admin melalui `admin/enrollment.html` atau roster `admin/academic.html`.
- Setiap enrollment menyimpan `class_id`, `course_id`, `student_id`, dan status (`active`, `completed`, `withdrawn`, `cancelled`).
- Satu siswa dapat memiliki banyak enrollment (satu baris per kelas); satu kelas dapat memiliki banyak siswa.
- Pendaftaran duplikat pada kelas yang sama dilewati (idempoten); pendaftaran melebihi kuota ditolak.
- Akses siswa divalidasi di `course-detail.html` dan `learning.html`:
  - enrollment harus ada dan berstatus `active`;
  - kursus harus berstatus `published`;
  - siswa hanya melihat modul/materi dari versi kurikulum yang terkunci pada kelasnya.
- Enrollment berstatus `withdrawn` mencabut akses siswa tanpa menghapus riwayat progres, nilai, dan sertifikat.

### Jadwal Sesi Pertemuan
- Data disimpan di tabel `class_sessions` (mode server) atau key `elips_classSessions` (mode lokal).
- Admin dapat mengelola sesi seluruh kelas; Instruktur hanya kelas yang diampu (atau sesi dengan `instructorId` miliknya).
- Setiap sesi menyimpan `classId`, `sessionNumber` (unik per kelas), `sessionDate`, `startTime`, `endTime`, `moduleId`, `plannedJp`, `actualJp`, `status`, dan `rescheduledFromId`.
- Sesi mewarisi `branchId`/`branchCode`/`branchName` dari kelas saat dibuat dan menyimpannya untuk kebutuhan kalender/filter cabang.
- Waktu selesai harus setelah waktu mulai; modul sesi harus berasal dari versi kurikulum kelas.
- Jadwal berulang dibuat melalui `session.createBulk`:
  - daftar `dates[]` eksplisit, atau
  - `startDate` + `weekdays` ISO (1=Senin … 7=Minggu) + `occurrences` (1–52).
- Sesi berstatus `cancelled`, `no_show`, atau `rescheduled` tidak dihitung sebagai JP aktual.
- Sesi `completed` tidak dapat dihapus. Sesi yang sudah memiliki presensi juga tidak dapat dihapus; gunakan pembatalan agar riwayat tetap ada.
- Reschedule membuat sesi pengganti baru dan menandai sesi asal `rescheduled`; sesi pengganti menyimpan `rescheduledFromId`.
- `session.status` menerima `reason` opsional; alasan pembatalan ditambahkan ke `notes` sesi dan dicatat pada detail audit.

### Presensi & Rekap Kehadiran
- Presensi disimpan di tabel `attendances` (mode server) atau key `elips_attendances` (mode lokal), unik per `(sessionId, enrollmentId)`.
- Status presensi: `present`, `late`, `permission`, `sick`, `absent`, `excused`.
- `attendance.saveBulk` menyimpan seluruh baris satu sesi; baris baru mencatat `recordedBy` dan `recordedAt`.
- Perubahan status yang sudah tercatat dianggap koreksi dan wajib menyertakan alasan. Server menulis baris `attendance_corrections` berisi `oldStatus`, `newStatus`, `actorId`, `actorName`, `actorRole`, `reason`, dan `correctedAt`.
- Kebijakan kehadiran dibaca dari `api/config.php`:
  - `attendance_present_statuses` = `['present', 'late']` (status yang dihitung hadir);
  - `attendance_counted_statuses` = `['confirmed', 'completed']` (status sesi yang masuk JP wajib).
- Rekap per kelas menghitung JP wajib dari sesi yang dihitung, JP hadir dari sesi dengan status hadir, lalu `persentase = JP hadir ÷ JP wajib × 100` (dibulatkan 2 desimal).
- `attendance.recap` tersedia untuk Admin dan Instruktur pengampu; `attendance.myRecap` hanya mengembalikan data peserta yang sedang login.
- Peserta tidak dapat membaca presensi peserta lain; percobaan lintas peserta/kelas ditolak server.

### Progres Belajar
- Progres pelajaran disimpan per enrollment di tabel `class_lesson_progress` (mode server) atau key `elips_classLessonProgress` (mode lokal); tabel `completed_lessons`/key lama tetap dibaca untuk kompatibilitas.
- Progres materi tambahan disimpan di tabel `class_material_progress` atau key `elips_classMaterialProgress`.
- Unik per `(enrollment_id, lesson_id)` dan `(enrollment_id, material_id)`, sehingga peserta yang mengikuti beberapa kelas memperoleh progres terpisah.
- Progres kelas dihitung dari jumlah pelajaran yang selesai dibagi total pelajaran pada versi kurikulum kelas.
- Materi terkunci mengikuti mode akses kelas; instruktur/Admin dapat melewati kunci saat pratinjau tanpa mengubah progres peserta.
- Penyelesaian oleh peserta ditolak bila kelas mewajibkan persetujuan instruktur; staf dapat mengonfirmasi progres untuk enrollment terkait.

### Tugas & Ujian
- Data disimpan di tabel `assignments`/`assignment_files`/`assignment_rubrics` dan `exams`/`exam_participants`/`exam_results` (mode server), atau key `elips_assignments`, `elips_assignmentFiles`, `elips_assignmentRubrics`, `elips_exams`, `elips_examParticipants`, `elips_examResults` (mode lokal).
- Tugas dan ujian hanya dapat dikelola Admin atau instruktur pemilik kelas; `curriculumVersionId` mengikuti versi kelas.
- Submission unik per `(assignmentId, enrollmentId)`. Submission ditolak sebelum `openAt`, ditandai `late` setelah `dueAt`, dan ditolak saat tugas `closed`/`archived` atau sudah `accepted`.
- Submission wajib berisi teks atau berkas sesuai konfigurasi tugas; berkas privat hanya disimpan sebagai metadata dan tidak diekspos ke peserta lain.
- Review submission menulis `submission_reviews` (`submit`, `score`, `revision`, `accept`). Permintaan revisi membuka kembali pengiriman; `accepted` mengunci submission.
- Peserta ujian unik per `(examId, enrollmentId)`; hasil ujian unik untuk pasangan yang sama dan skornya tidak boleh melebihi `maxScore`.
- Hasil ujian berstatus `missing` **tidak** dikonversi menjadi 0; komponen wajib yang kosong membuat nilai akhir `incomplete`.
- Peserta hanya dapat melihat tugas/ujian dan submission miliknya pada kelas yang diikuti.

### Skema Penilaian & Komputasi
- Predikat huruf disimpan di tabel `predicate_ranges` (mode server) atau key `elips_predicateRanges` (mode lokal). Default: A 90–100, A- 80–89.99, B+ 75–79.99, B 70–74.99, B- 65–69.99, C+ 60–64.99, C 55–59.99, D 50–54.99, E 0–49.99.
- Hanya Admin yang dapat mengubah predikat. Himpunan rentang aktif wajib menutup 0–100 tanpa celah dan tanpa tumpang tindih; perubahan berlaku pada komputasi/transkrip berikutnya.
- Satu kelas memiliki tepat satu `grade_schemes` (`classId` unik). Skema berisi satu atau banyak `grade_scheme_components` bertipe `assignment`, `exam`, atau `manual`.
- Publikasi skema mensyaratkan minimal satu komponen dan total bobot tepat **100%**. Skema `published`/`archived` tidak dapat kembali ke `draft` atau dihapus; arsipkan lalu buat skema pengganti.
- Nilai komponen disimpan per `(componentId, enrollmentId)` di `grade_component_scores`, dengan status `not_assessed`, `graded`, `excused`, atau `missing`.
- Komponen `assignment`/`exam` mengambil nilai dari submission/hasil ujian yang dipetakan; komponen `manual` diisi melalui roster.
- Komputasi nilai akhir:
  - komponen wajib yang belum memiliki nilai => status nilai `incomplete`, bukan 0;
  - komponen opsional kosong atau `excused` dikeluarkan dari perhitungan dan bobotnya dinormalisasi ulang;
  - nilai `0` hanya dihitung bila benar-benar diisi;
  - `finalScore = Σ(skor × bobot) / Σ(bobot)` menggunakan bobot komponen yang dihitung, lalu dibulatkan sesuai `rounding` skema (`half_up`, `half_down`, `floor`, `ceil`);
  - huruf dihitung dari `predicate_ranges` aktif.
- `grade.roster` mengembalikan komponen, nilai, status komponen, total bobot, nilai akhir, huruf, dan status per enrollment. Server dan mode lokal memakai aturan yang sama (`elips_grade_compute_enrollment()` dan `ELIPS.computeEnrollmentGrade()`).
- `grade.exempt` mengecualikan komponen dengan alasan minimal 5 karakter dan mencatat audit; bobot komponen tersebut tidak dihitung.
- Finalisasi (`grade.finalize`) menolak nilai yang masih `incomplete`, menulis `schemeId`, `finalScore`, `grade`, `status = finalized`, `componentsSnapshot`, `finalizedBy`, `finalizedAt`, dan `computedAt`.
- Nilai `finalized` terkunci: `grade.save` dan `grade.manualSave` menolak perubahan. Satu-satunya jalur perubahan adalah `grade.correct`.

### Koreksi Nilai
- Koreksi hanya untuk nilai berstatus `finalized` dan wajib menyertakan alasan minimal 5 karakter.
- Koreksi dapat berupa perubahan skor komponen (lalu dihitung ulang) atau override `finalScore` langsung; `finalScore` wajib 0–100 dan huruf dihitung ulang dari predikat aktif.
- Koreksi berjalan dalam transaksi: `grades` diperbarui, snapshot komponen diperbarui, baris `grade_corrections` ditulis (`previousScore`, `newScore`, `previousLetter`, `newLetter`, `reason`, `actorId`), dan audit dicatat.
- Koreksi tidak mengubah status `finalized`; setiap perubahan berikutnya memerlukan koreksi baru. Histori koreksi bersifat append-only.

### Penilaian Klasik & Sertifikat
- Nilai klasik/legacy tetap disimpan di tabel `grades` (mode server) atau `localStorage` key `elips_grades` (mode lokal), dan hurufnya dihitung dari predikat aktif (fallback A–E).
- Nilai terhubung ke **enrollment**, sehingga peserta yang mengikuti kursus yang sama pada angkatan berbeda memiliki nilai terpisah.
- Instruktur hanya dapat menilai peserta pada kelas/kursus yang diampu.
- Saat instruktur menyimpan nilai klasik, sistem membuat/memperbarui e-sertifikat untuk enrollment tersebut dengan status **Draft** dan menyalin nilai/grade ke sertifikat. Finalisasi Phase 6 tidak membuat sertifikat otomatis.
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
- Pada mode server, setiap operasi bisnis yang berhasil dicatat backend ke tabel `audit_logs`; pada mode lokal ditulis ke `localStorage` key `elips_auditLogs` (client tidak menulis duplikat di mode server).
- Entri audit menyimpan `action`, `entity`, `entityId`, `entityLabel`, `actorId`, `actorName`, `actorRole`, `detail`, dan `createdAt`.
- Resolusi aktor: `actorId` eksplisit dari pemanggil → user sesi (`elips_session`) → "Sistem". Fallback `getCurrentUser()` tidak dipakai agar tidak salah atribusi saat tidak ada sesi.
- Operasi yang gagal (validasi ditolak) tidak dicatat.
- Aksi yang dicatat: `create`, `update`, `delete`, `publish`, `unpublish`, `grade`, `submit`, `review`, `revision`, `accept`, `approve`, `assign`, `status`, `attendance`, `correct`, `progress`, `finalize`, `exempt`, `predicate`, dan `grade_scheme`.
- Entitas yang dicatat: `course`, `branch`, `curriculum`, `class`, `grade`, `certificate`, `user`, `enrollment`, `module`, `lesson`, `session`, `attendance`, `class_material`, `class_material_settings`, `assignment`, `submission`, `exam`, `predicate_range`, `grade_scheme`, `grade_component`, dan `staff_attendance`.
- Log diurutkan terbaru lebih dulu, dibatasi 1000 entri, dan dapat difilter berdasarkan aktor, aksi, entitas, rentang tanggal, pencarian, serta limit.
- Admin dapat mengekspor hasil filter ke CSV dan membersihkan seluruh log dari `admin/audit.html`.

### Notifikasi Email (Simulasi)
- Pada mode server, notifikasi disimpan backend di tabel `notifications`; pada mode fallback disimpan di `localStorage` key `elips_notifications`. Pada kedua mode email **tidak benar-benar dikirim** (`channel: 'email'`, `status: 'sent'`, `simulated: true`).
- Notifikasi publikasi kursus dibuat setelah `setCourseStatus` berhasil mengubah status menjadi `published`:
  - penerima: peserta dengan enrollment `active` pada kursus tersebut dan akun berstatus `aktif`;
  - ditambah instruktur pengampu yang berstatus `aktif`;
  - penerima duplikat dihindari.
- Notifikasi sertifikat terbit dibuat setelah `approveCertificate` berhasil mengubah status Draft → Publish; penerimanya adalah peserta pemilik sertifikat.
- Operasi yang gagal (publikasi ditolak atau approval ditolak) tidak membuat notifikasi.
- Outbox diurutkan terbaru lebih dulu, dibatasi 1000 entri, dan dapat difilter berdasarkan jenis, penerima, rentang tanggal, pencarian, serta limit.
- Admin dapat mengekspor hasil filter ke CSV dan membersihkan seluruh outbox dari `admin/notifications.html`.

### Absensi Staf & Karyawan (Kamera + Metadata)
- Absensi adalah **absensi pribadi** admin/karyawan yang sedang login, bukan presensi peserta per sesi. Karyawan hanya dapat membuat dan membaca catatan miliknya; admin dapat membaca catatan pengguna lain secara eksplisit melalui `userId`.
- Dua jenis catatan: `in` (absen masuk) dan `out` (absen pulang). Setiap pengguna hanya memiliki maksimal satu catatan `in` dan satu `out` per hari. Aturan ini dijaga validasi server dan unique key `(user_id, attendance_date, kind)`; absen pulang ditolak bila belum ada absen masuk.
- Foto wajib ada. Server hanya menerima data URL `image/jpeg`, `image/png`, atau `image/webp`, memverifikasi isi biner dengan `getimagesizefromstring()`, dan membatasi ukuran foto (maks. 2 MB biner, 4 juta karakter data URL).
- Tanggal dan jam ditentukan **server**: `attendanceDate` memakai tanggal `Asia/Jakarta`, `takenAt` disimpan UTC, dan `timezone` diisi `Asia/Jakarta`. Nilai `attendanceDate`, `takenAt`, dan `timezone` dari klien diabaikan agar jam perangkat tidak dapat dimanipulasi.
- GPS **wajib** untuk semua role. Koordinat harus dikirim berpasangan dan berada dalam rentang lat -90..90 / lng -180..180; absensi tanpa koordinat ditolak server dan `locationSource` data baru selalu `gps`.
- IP diambil server dari `REMOTE_ADDR`; metadata perangkat lain berasal dari browser dan dibatasi panjangnya sebelum disimpan.
- Kamera memerlukan secure context (`localhost` atau HTTPS). Bila kamera tidak tersedia atau ditolak, pengguna dapat mengunggah foto; lokasi tetap wajib diperoleh dari GPS.
- Modal ambil foto hanya berisi layar kamera, tombol Ambil Foto/Ulangi/Nyalakan Kamera/Unggah Foto, Catatan, Batal, dan Simpan Absensi. Setelah berhasil, modal sukses menampilkan foto, identitas akun, dan seluruh metadata dengan satu tombol **Selesai** yang tidak dapat ditutup lewat Escape/backdrop.
- Hanya admin yang dapat menghapus catatan absensi (miliknya atau karyawan) dengan alasan minimal 5 karakter; detail penghapusan dan alasan dicatat di Audit Trail. Karyawan tidak memiliki aksi hapus.
- Mode lokal masih memakai perilaku lama (admin saja, GPS opsional) sampai modul absensi bersama dibuat pada Fase 2–3; mode server adalah acuan aturan.
- Snapshot bootstrap sengaja **tidak** memuat koleksi absensi staf maupun foto; dashboard memuat riwayat melalui `staffAttendance.list` agar foto tidak bocor ke halaman/role lain.
- Setiap create/delete mencatat audit dengan entitas `staff_attendance`; pada mode server audit ditulis backend, pada mode lokal ditulis client.

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
| Versi kurikulum baru berstatus Draft | ✅ Lulus uji | `curriculum.create` selalu `draft`; kode versi unik per kursus |
| Publikasi versi kurikulum | ✅ Lulus uji | `curriculum.publish` mengubah Draft → Published |
| Kelas hanya dapat dibuat dari versi Published | ✅ Lulus uji | `class.create` menolak versi Draft |
| Pembuatan kelas mengunci versi | ✅ Lulus uji | Status versi berubah `locked` dalam transaksi yang sama |
| Versi Locked menolak perubahan | ✅ Lulus uji | Create/update/delete modul dan update/delete versi ditolak server |
| Clone kurikulum menyalin struktur | ✅ Lulus uji | Modul dan materi tersalin ke Draft baru |
| Versi terpakai tidak dapat dihapus | ✅ Lulus uji | Hanya Draft tanpa kelas yang dapat dihapus; versi terpakai harus Retired |
| Satu kelas = satu tentor | ✅ Lulus uji | `instructor_id` divalidasi sebagai instruktur aktif |
| Satu tentor banyak kelas | ✅ Lulus uji | Dashboard instruktur menampilkan seluruh kelas yang diampu |
| Histori pergantian tentor | ✅ Lulus uji | Entri lama ditutup (`endedAt`), entri baru dibuka; penugasan sama idempoten |
| Validasi periode kelas | ✅ Lulus uji | Tanggal selesai sebelum tanggal mulai ditolak |
| Status kelas memerlukan tentor | ✅ Lulus uji | `ready`/`ongoing` ditolak tanpa tentor aktif |
| Pendaftaran siswa ke kelas | ✅ Lulus uji | `enrollment.assignClass` membuat enrollment dengan `classId` dan `courseId` |
| Pendaftaran duplikat dilewati | ✅ Lulus uji | `skipped` bertambah, tidak membuat baris ganda |
| Kuota kelas ditegakkan | ✅ Lulus uji | Pendaftaran melebihi kuota ditolak; update kuota di bawah peserta aktif ditolak |
| Status enrollment kelas | ✅ Lulus uji | `active` → `withdrawn` mencabut akses tanpa menghapus data |
| Kelas dengan peserta tidak dapat dihapus | ✅ Lulus uji | `class.delete` ditolak; kelas kosong Draft dapat dihapus |
| Sesi pertemuan tunggal dibuat | ✅ Lulus uji | `session.create` menyimpan nomor unik per kelas, tanggal, jam, JP rencana |
| Jadwal berulang dibuat | ✅ Lulus uji | `session.createBulk` menerima `dates[]` atau `startDate` + `weekdays` + `occurrences` (maks. 52) |
| Validasi waktu & modul sesi | ✅ Lulus uji | Waktu selesai harus setelah mulai; modul harus dari versi kurikulum kelas |
| Reschedule membuat sesi pengganti | ✅ Lulus uji | Sesi asal → `rescheduled`; sesi baru menyimpan `rescheduledFromId` |
| Sesi completed tidak dapat dihapus | ✅ Lulus uji | `session.delete` ditolak untuk status `completed` |
| Sesi berpresensi tidak dapat dihapus | ✅ Lulus uji | Harus dibatalkan (`cancelled`) agar riwayat presensi tetap utuh |
| Presensi per sesi & enrollment | ✅ Lulus uji | `attendance.saveBulk` menyimpan satu baris per enrollment; unique `(sessionId, enrollmentId)` |
| Koreksi presensi wajib alasan | ✅ Lulus uji | Perubahan status yang sudah tercatat tanpa alasan ditolak |
| Audit koreksi presensi | ✅ Lulus uji | Nilai lama/baru, aktor, alasan, dan waktu tersimpan di `attendance_corrections` |
| Rekap kehadiran sesuai kebijakan | ✅ Lulus uji | JP hadir ÷ JP wajib; sesi `cancelled`/`no_show`/`rescheduled` tidak dihitung |
| Peserta hanya melihat presensi sendiri | ✅ Lulus uji | `attendance.myRecap` mengembalikan data peserta yang login; akses lintas peserta ditolak |
| UI jadwal & presensi admin | ✅ Lulus uji | `test-sessions-page` memverifikasi tabel sesi, modal single/berulang, presensi, koreksi, dan rekap |
| UI jadwal & presensi instruktur | ✅ Lulus uji | Dashboard instruktur hanya menampilkan/mengelola kelas yang diampu |
| Rekap kehadiran peserta | ✅ Lulus uji | Statistik, badge kebijakan, dan detail sesi tampil di `student/dashboard.html` |
| Server-mode jadwal & presensi | ✅ Lulus uji | `test-sessions-server` 20/20 terhadap API PHP/MySQL |
| CRUD tugas & submission (server) | ✅ Lulus uji | `api-phase5` 52/52: tugas, lampiran/rubrik, submission, review/revisi, accept, guard role, dan validasi status |
| CRUD tugas & submission (lokal) | ✅ Lulus uji | `local-phase5` 34/34: paritas aturan tugas/submission/review pada `localStorage` |
| Ujian, peserta & hasil ujian | ✅ Lulus uji | `api-phase5`/`local-phase5`: unik per pasangan, skor ≤ maksimum, status peserta, dan nilai `missing` tidak menjadi 0 |
| Predikat nilai konfigurabel | ✅ Lulus uji | `api-phase6` 44/44 dan `local-phase6` 34/34: default A–E, validasi cakupan, guard Admin, dan pemetaan huruf |
| Skema penilaian & bobot | ✅ Lulus uji | `api-phase6`/`local-phase6`: satu skema per kelas, komponen, total bobot 100%, guard published/archived, dan validasi role |
| Komputasi nilai komponen | ✅ Lulus uji | `api-phase6`/`local-phase6`: nilai manual, pengecualian, normalisasi bobot, `incomplete`, dan skor 0 eksplisit |
| Finalisasi & penguncian nilai | ✅ Lulus uji | `api-phase6`/`local-phase6`: finalisasi menolak `incomplete`, snapshot komponen, dan simpan biasa ditolak setelah finalized |
| Koreksi nilai beraudit | ✅ Lulus uji | `api-phase6`/`local-phase6`: hanya finalized, alasan ≥5 karakter, override/komponen, dan baris `grade_corrections` |
| Transkrip peserta & privasi | ✅ Lulus uji | `api-phase6`/`local-phase6`: peserta hanya membaca transkrip sendiri; instruktur/Admin pengampu dapat membaca roster |
| Regresi halaman & skrip Phase 6 | ✅ Lulus uji | `test-all-pages` 25 halaman, `validate-scripts` 27 HTML/19 JS, dan `test-staff-attendance-*` tetap lulus |
| Nilai & sertifikat per enrollment | ✅ Lulus uji | Unique per `enrollment_id`; angkatan berbeda terpisah |
| Guard role aksi akademik | ✅ Lulus uji | Instruktur/peserta tidak dapat membuat kurikulum/kelas atau mendaftarkan peserta |
| Halaman akademik admin | ✅ Lulus uji | `test-academic-page` memverifikasi statistik, tabel versi/kelas, modal, roster, histori tentor, dan detail kurikulum |
| Cabang pada kelas | ✅ Lulus uji | `api-v4-branches`/`local-v4-branches` memverifikasi create/update/status/delete, kode unik, cabang nonaktif ditolak, dan hapus cabang terpakai ditolak |
| UI manajemen cabang | ✅ Lulus uji | `test-branch-pages` memverifikasi tabel cabang, modal tambah/edit, status, hapus, dan blokir hapus cabang seed |
| Kalender & jadwal admin | ✅ Lulus uji | `test-branch-pages` memverifikasi 42 sel kalender, agenda harian, ringkasan JP, filter cabang/kelas/status, modal sesi, dan navigasi bulan |
| Aksi langsung & filter kalender | ✅ Lulus uji | `test-schedule-actions` memverifikasi filter instruktur/lokasi/multi-status, pembuatan dari kalender, edit, reschedule, pembatalan beralasan, tandai selesai, hapus, dan pemulihan filter dari URL |
| Pengaturan akses materi kelas | ✅ Lulus uji | `api-v4-materials`/`local-v4-materials` memverifikasi default `open`, keempat mode, validasi mode, dan guard role |
| Prasyarat sequential & scheduled | ✅ Lulus uji | Pelajaran kedua terkunci sampai pelajaran pertama selesai; mode scheduled memakai tanggal sesi modul |
| Mode conditional | ✅ Lulus uji | Membutuhkan penyelesaian pelajaran sebelumnya dan tanggal sesi modul yang sudah tiba |
| Materi tambahan kelas | ✅ Lulus uji | Create/update/status/delete, relasi pelajaran/sesi, `available_from`, dan penyembunyian dari peserta |
| Persetujuan instruktur | ✅ Lulus uji | Peserta ditolak saat menandai selesai; staf dapat mengonfirmasi progres enrollment |
| Keamanan snapshot & konten terkunci | ✅ Lulus uji | Snapshot hanya metadata; materi terkunci tidak mengirim `content` |
| Audit materi | ✅ Lulus uji | Perubahan pengaturan, status materi, dan konfirmasi progres tercatat dengan alasan/aktor |
| UI materi Admin/Instruktur/Peserta | ✅ Lulus uji | `test-v4-material-ui` 25/25: modal akses, panel materi, sidebar tambahan, konten, progres, dan status persetujuan |
| Admin menambah siswa | ✅ Lulus uji | Akun peserta aktif dapat ditugaskan ke kursus |
| Admin mengaitkan siswa ke beberapa kursus | ✅ Lulus uji | Multi-checkbox pada `admin/enrollment.html` |
| Siswa hanya mengakses kursus yang ditugaskan | ✅ Lulus uji | `learning.html` menolak kursus tanpa enrollment aktif |
| Belajar & tandai selesai | ✅ Lulus uji | Progres ter-update di tabel `completed_lessons`/localStorage dan materi berurutan |
| Instruktur membuat modul & materi | ✅ Lulus uji | Tambah/ubah/hapus modul dan materi tersimpan di database/localStorage |
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
| Absensi staf dari dashboard Admin | Lulus uji | `test-staff-attendance-page` (80/80) memverifikasi tombol masuk/pulang sesuai status harian, modal kamera/fallback yang ringkas, penguncian tanggal, modal sukses dengan satu tombol Selesai, penyimpanan lokal, detail, audit, dan hapus catatan |
| API absensi staf & privasi | Lulus uji | `test-staff-attendance-api` (46/46) memverifikasi guard role, validasi foto/koordinat/GPS, jam server, duplikat harian, alasan hapus, persistensi metadata, dan snapshot tanpa foto |
| Role karyawan & absensi server | Lulus uji | `api-employee-fase1` (34/34) memverifikasi role `karyawan` di API, login karyawan, absen masuk/pulang, duplikat, privasi riwayat, akses admin via `userId`, hapus beralasan + audit, dan penolakan instruktur/peserta |
| Degradasi kamera & geolokasi | Lulus uji | Kamera tanpa dukungan/izin/secure context menampilkan fallback unggah; GPS gagal tidak memblokir penyimpanan |

---

## 9. Catatan Pengembangan

- **Dual-mode:** Mode server memakai PHP 8 + MySQL 8; mode lokal `localStorage` tetap tersedia sebagai fallback dan untuk regression test JSDOM.
- **Menjalankan backend:** jalankan MySQL, lalu `php api/install.php --fresh`, lalu sajikan folder proyek lewat web server (mis. `php -S 127.0.0.1:8099 -t .`). Halaman akan otomatis memakai API karena `config.js` aktif pada HTTP/HTTPS.
- **Data Persisten:** Mode server menyimpan data di MySQL; mode lokal menyimpan di `localStorage` browser.
- **Struktur Rapi:** File terorganisir berdasarkan role untuk kemudahan navigasi, dengan lapisan backend terpisah di `api/`.
- **Responsive:** Menggunakan CSS custom yang responsif.
- **UI Konsisten:** Menggunakan komponen seragam (navbar, sidebar, cards, tables, badges).
- **Keamanan:** Pada mode server password di-hash, sesi memakai cookie `HttpOnly`/`SameSite=Lax`, dan otorisasi role/kepemilikan ditegakkan di server. Mode lokal tetap bersifat demo dan tidak boleh dianggap aman untuk produksi.
- **Cakupan v4.0:** Increment ini mencakup **Phase 1–6** (versi kurikulum + kelas/angkatan + enrollment berbasis kelas + jadwal sesi/presensi offline + materi tambahan dan mode akses materi + cabang/kalender + absensi staf + tugas/ujian + skema penilaian/finalisasi/koreksi), ditambah **fondasi server absensi karyawan** (role `karyawan`, validasi aturan harian, jam server, GPS wajib), dengan 37 tabel MySQL, 92 aksi API, 38 storage key, 19 modul JS, dan 27 halaman HTML. Pembayaran dan sertifikat PDF mengikuti phase berikutnya pada `ELIPS-LMS-PRD-v4.0.md`.
- **Kompatibilitas v3.0:** Struktur v3 tetap dipertahankan. Setiap kursus lama mendapat versi kurikulum `1.0`; enrollment lama dipetakan ke kelas migrasi (`legacy_import`) agar tidak ada data yang hilang. Alur nilai manual klasik tetap tersedia di samping skema penilaian Phase 6.

---

## 10. Rencana Pengembangan Lanjutan (Opsional)

Backend PHP/MySQL, autentikasi sesi, validasi role/kepemilikan, audit server-side, outbox notifikasi simulasi, serta Phase 1–6 v4.0 (kurikulum, kelas, enrollment, jadwal & presensi, materi & akses kelas, tugas & ujian, skema penilaian/finalisasi/koreksi) sudah diimplementasikan. Untuk production dan phase v4 berikutnya, pengembangan berikut masih diperlukan:

| Fitur | Prioritas | Keterangan |
|---|---|---|
| Phase 7–10 v4.0 | P1 | Sertifikasi lanjutan/PDF, pembayaran, dan fitur lanjutan lain (lihat `ELIPS-LMS-PRD-v4.0.md`) |
| Hardening API | P0 | Rate limiting login, CSRF token, HTTPS wajib, dan rotasi session ID |
| Pembatasan baca snapshot | P0 | Snapshot publik saat ini memuat seluruh koleksi demo; produksi perlu proyeksi terbatas per role dan endpoint khusus verifikasi sertifikat |
| Upload file materi | P0 | Upload PDF, video, gambar ke object storage |
| Generate PDF sertifikat | P0 | Generate PDF yang sebenarnya |
| Notifikasi email | P2 | Integrasi SMTP/email service saat kursus dipublikasikan atau sertifikat terbit |
| Migrasi database | P1 | Gunakan migration tool dan backup otomatis |
| Observability | P2 | Structured logging, monitoring, dan retensi audit |
