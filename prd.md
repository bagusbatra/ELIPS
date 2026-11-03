# PRD: Learning Management System (LMS) ELIPS

**Versi:** 1.1  
**Tanggal:** 2026-11-02  
**Status:** Implemented (Demo - Vanilla HTML/CSS/JS + localStorage)  
**Nama Aplikasi:** ELIPS LMS

---

## 1. Tujuan Proyek

Membangun sistem Learning Management System (LMS) berbasis web untuk pelatihan dan kursus. Pada fase demo ini, sistem dibangun menggunakan vanilla HTML, CSS, dan JavaScript dengan penyimpanan data menggunakan `localStorage` untuk mensimulasikan alur pendaftaran, progres belajar, dan penerbitan sertifikat.

---

## 2. Target Pengguna

| Role | Deskripsi | Kebutuhan Utama |
|---|---|---|
| **Admin** | Pengelola sistem | Manajemen pengguna, melihat daftar kursus, manajemen enrolment, laporan, pengaturan sistem, manajemen sertifikat |
| **Instruktur/Trainer** | Pengajar kursus | Mengelola kursus dan materi, melihat daftar peserta, memantau progres peserta |
| **Peserta/Student** | Peserta pelatihan | Mendaftar kursus, mengakses materi pembelajaran, menandai selesai, melihat sertifikat |

---

## 3. Arsitektur Saat Ini (Demo)

- **Frontend:** HTML5, CSS3 (custom dengan variabel CSS), Vanilla JavaScript
- **Penyimpanan Data:** `localStorage` (demo)
  - `elips_enrolled` - Data pendaftaran kursus peserta
  - `elips_completedLessons` - Tracking pelajaran yang diselesaikan
  - `elips_certificates` - Data sertifikat yang diterbitkan
- **Styling:** Custom CSS (`assets/css/style.css`), Bootstrap Icons, Google Fonts (Inter)
- **Struktur Folder:** Berbasis role (`admin/`, `instructor/`, `student/`)

---

## 4. Struktur File yang Telah Dikembangkan

### Halaman Publik
| File | Status | Deskripsi |
|---|---|---|
| `index.html` | ✅ Selesai | Beranda dengan daftar kursus |
| `course-detail.html` | ✅ Selesai | Detail kursus, tombol enrolment dinamis (berubah menjadi "Sudah Terdaftar - Lanjutkan Belajar") |
| `login.html` | ✅ Selesai | Halaman login dengan redirect berbasis role (deteksi dari keyword email) |
| `register.html` | ✅ Selesai | Halaman registrasi |
| `verify-certificate.html` | ✅ Selesai | Verifikasi keaslian sertifikat publik |
| `certificate-view.html` | ✅ Selesai | Tampilan sertifikat dinamis dari URL params/localStorage |
| `learning.html` | ✅ Selesai | Halaman belajar dengan tracking pelajaran selesai |

### Role Admin (`admin/`)
| File | Status | Deskripsi |
|---|---|---|
| `admin/dashboard.html` | ✅ Selesai | Dashboard admin dengan ringkasan statistik |
| `admin/users.html` | ✅ Selesai | Kelola pengguna dengan filter role (Semua/Admin/Instruktur/Peserta) dan pencarian |
| `admin/courses.html` | ✅ Selesai | Daftar kursus (hanya view) dengan filter status & pencarian |
| `admin/enrollment.html` | ✅ Selesai | Kelola enrolment peserta ke kursus, filter by kursus/status, tambah enrolment |
| `admin/certificates.html` | ✅ Selesai | Kelola sertifikat |
| `admin/reports.html` | ✅ Selesai | Laporan progres peserta per kursus, statistik per kursus, export PDF/Excel (UI) |
| `admin/settings.html` | ✅ Selesai | Pengaturan umum, pengaturan sertifikat, pengaturan pembelajaran |

### Role Instruktur (`instructor/`)
| File | Status | Deskripsi |
|---|---|---|
| `instructor/dashboard.html` | ✅ Selesai | Dashboard instruktur |
| `instructor/manage-course.html` | ✅ Selesai | Kelola kursus, modul dan materi |
| `instructor/students.html` | ✅ Selesai | Daftar peserta per kursus dan progres mereka |

### Role Peserta (`student/`)
| File | Status | Deskripsi |
|---|---|---|
| `student/dashboard.html` | ✅ Selesai | Dashboard peserta dengan kursus yang diikuti, progres, nilai, status sertifikat, dan ringkasan statistik |
| `student/courses.html` | ✅ Selesai | Daftar seluruh kursus peserta dengan filter status, pencarian, progres, nilai, dan akses belajar |
| `student/certificates.html` | ✅ Selesai | Daftar sertifikat peserta (Terbit & Draft/menunggu persetujuan) dari localStorage |
| `student/profile.html` | ✅ Selesai | Profil peserta dan pembaruan nama/no. telepon |

### Assets
| File | Status | Deskripsi |
|---|---|---|
| `assets/css/style.css` | ✅ Selesai | Styling utama untuk seluruh aplikasi |
| `assets/js/main.js` | ✅ Selesai | Logika umum, login redirect, localStorage helpers, toast notifications |

---

## 5. Fitur yang Telah Diimplementasikan

### 5.1 Autentikasi & Role-Based Access
| Fitur | Status | Keterangan |
|---|---|---|
| Login form | ✅ Selesai | Form login di `login.html` |
| Role redirect berdasarkan email | ✅ Selesai | `admin` → `admin/dashboard.html`, mengandung `instruktur/ahmad/guru/trainer` → `instructor/dashboard.html`, lainnya → `student/dashboard.html` |
| Struktur folder per role | ✅ Selesai | Semua halaman role dipindahkan ke folder terpisah |

### 5.2 Manajemen Kursus
| Fitur | Status | Keterangan |
|---|---|---|
| Daftar kursus (beranda) | ✅ Selesai | Ditampilkan di `index.html` |
| Detail kursus | ✅ Selesai | `course-detail.html` |
| Kelola kursus (Instruktur) | ✅ Selesai | `instructor/manage-course.html` |
| Daftar kursus (Admin - view only) | ✅ Selesai | `admin/courses.html` dengan filter status & pencarian |

### 5.3 Enrolment & Progres Belajar
| Fitur | Status | Keterangan |
|---|---|---|
| Enrolment kursus | ✅ Selesai | Tombol daftar di `course-detail.html` menyimpan ke `localStorage` (`elips_enrolled`) |
| Status enrolment dinamis | ✅ Selesai | Tombol berubah jadi "Sudah Terdaftar - Lanjutkan Belajar" |
| Halaman belajar | ✅ Selesai | `learning.html` dengan sidebar modul/lesson |
| Tandai pelajaran selesai | ✅ Selesai | Menyimpan ke `localStorage` (`elips_completedLessons`) |
| Tracking progres | ✅ Selesai | Progres dihitung berdasarkan pelajaran selesai |
| Kelola enrolment (Admin) | ✅ Selesai | `admin/enrollment.html` - tambah enrolment, filter, aksi pause/stop |

### 5.4 Sertifikat
| Fitur | Status | Keterangan |
|---|---|---|
| Penerbitan otomatis (simulasi) | ✅ Selesai | Sertifikat otomatis terbit saat semua pelajaran selesai (simulasi 24 pelajaran) |
| Daftar sertifikat peserta | ✅ Selesai | `student/certificates.html` membaca dari `localStorage` |
| Tampilan sertifikat | ✅ Selesai | `certificate-view.html` menampilkan sertifikat dari URL params/localStorage |
| Verifikasi sertifikat publik | ✅ Selesai | `verify-certificate.html` |
| Kelola sertifikat (Admin) | ✅ Selesai | `admin/certificates.html` |

### 5.5 Dashboard & Pelaporan
| Fitur | Status | Keterangan |
|---|---|---|
| Dashboard Admin | ✅ Selesai | Ringkasan pengguna, kursus, enrolment |
| Dashboard Instruktur | ✅ Selesai | Kursus yang diampu, daftar peserta |
| Dashboard Peserta | ✅ Selesai | Kursus diikuti, progres, akses cepat ke sertifikat |
| Laporan (Admin) | ✅ Selesai | `admin/reports.html` - progres per peserta, statistik per kursus |
| Pengaturan (Admin) | ✅ Selesai | `admin/settings.html` - pengaturan umum, sertifikat, pembelajaran |

### 5.6 Manajemen Pengguna
| Fitur | Status | Keterangan |
|---|---|---|
| Daftar pengguna | ✅ Selesai | `admin/users.html` |
| Filter role & pencarian | ✅ Selesai | Filter Semua/Admin/Instruktur/Peserta + search |

---

## 6. Logika Bisnis Penting

### Login Redirect
```javascript
// Berdasarkan email di login.html
if (email.includes('admin')) -> admin/dashboard.html
else if (email.includes('instruktur') || email.includes('ahmad') || email.includes('guru') || email.includes('trainer')) -> instructor/dashboard.html
else -> student/dashboard.html
```

### Enrolment
- Data disimpan di `localStorage` key `elips_enrolled` (array of objects)
- Status enrolment dicek di `course-detail.html` untuk mengubah tampilan tombol

### Progres Belajar
- Pelajaran selesai disimpan di `localStorage` key `elips_completedLessons`
- Progres dihitung berdasarkan jumlah pelajaran selesai vs total pelajaran
- Sertifikat otomatis terbit saat semua pelajaran selesai (simulasi)

### Sertifikat
- Disimpan di `localStorage` key `elips_certificates`
- Ditampilkan di `student/certificates.html` dan bisa dilihat di `certificate-view.html`
- Dapat diverifikasi melalui `verify-certificate.html`

---

## 7. Testing Checklist

| Skenario | Status | Catatan |
|---|---|---|
| Login redirect per role | ✅ Perlu diuji | Berdasarkan keyword email |
| Enrolment kursus | ✅ Perlu diuji | Enroll → tombol berubah → bisa lanjut belajar |
| Belajar & tandai selesai | ✅ Perlu diuji | Progress ter-update di localStorage |
| Auto-sertifikat | ✅ Perlu diuji | Muncul setelah semua pelajaran selesai |
| Lihat sertifikat | ✅ Perlu diuji | Dari dashboard/list sertifikat |
| Verifikasi sertifikat publik | ✅ Perlu diuji | Halaman verifikasi |
| Filter & search di admin/users.html | ✅ Perlu diuji | Berbagai kombinasi filter |
| Filter & search di admin/courses.html | ✅ Perlu diuji | Filter status dan pencarian |
| Navigasi setelah folder restructuring | ✅ Perlu diuji | Semua relative path |

---

## 8. Catatan Pengembangan

- **Mode Demo:** Menggunakan vanilla HTML/CSS/JS tanpa backend. Cocok untuk prototyping dan demo.
- **Data Persisten:** Menggunakan localStorage browser - data akan hilang jika localStorage dibersihkan.
- **Struktur Rapi:** File terorganisir berdasarkan role untuk kemudahan navigasi.
- **Responsive:** Menggunakan CSS custom yang responsif.
- **UI Konsisten:** Menggunakan komponen seragam (navbar, sidebar, cards, tables, badges).

---

## 9. Rencana Pengembangan Lanjutan (Opsional)

Jika ingin dikembangkan menjadi aplikasi production:

| Fitur | Prioritas | Keterangan |
|---|---|---|
| Backend (Laravel/Node.js) | P0 | Ganti localStorage dengan database |
| Autentikasi sesungguhnya | P0 | Login dengan validasi server-side |
| Upload file materi | P0 | Upload PDF, video, gambar |
| CRUD penuh di Admin | P0 | Saat ini admin/courses hanya view, perlu CRUD penuh |
| Generate PDF sertifikat | P0 | Generate PDF yang sebenarnya |
| Role middleware | P0 | Proteksi halaman berdasarkan role |
| Database relasional | P0 | Users, Courses, Modules, Lessons, Enrollments, Progress, Certificates |
