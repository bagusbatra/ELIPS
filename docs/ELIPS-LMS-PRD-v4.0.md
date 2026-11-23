# PRD: Learning Management System (LMS) ELIPS

**Versi dokumen:** 4.0\
**Tanggal pembaruan:** 2026-11-04\
**Status dokumen:** Updated Product Requirements / Baseline
Pengembangan\
**Status implementasi:** Campuran --- fitur eksisting v3.0 telah
didokumentasikan sebagai riwayat; fitur akademik, angkatan, jadwal,
presensi, dan penilaian terstruktur pada v4.0 merupakan kebutuhan
pengembangan kecuali dinyatakan telah diverifikasi\
**Teknologi:** PHP Native 8.x, MySQL 8, HTML5, CSS3, Vanilla JavaScript\
**Dokumen terkait:** `log.md`, `erd.md` (perlu diselaraskan dengan model
v4.0)\
**Nama aplikasi:** ELIPS LMS

------------------------------------------------------------------------

# BAGIAN I --- KETENTUAN DOKUMEN DAN ARAH PRODUK

## 1. Riwayat Versi dan Prinsip Pemeliharaan Dokumen

  ----------------------------------------------------------------------------------------------
  Versi             Tanggal           Ringkasan                                Perlakuan
  ----------------- ----------------- ---------------------------------------- -----------------
  3.0               2026-11-04        LMS dual-mode PHP Native/MySQL dan       Dipertahankan
                                      fallback localStorage; autentikasi,      utuh pada
                                      role, kursus, instruktur, enrollment,    Lampiran A
                                      modul, materi, progres, nilai akhir,     
                                      sertifikat, audit, notifikasi simulasi,  
                                      dashboard, dan laporan telah             
                                      didokumentasikan                         

  4.0               2026-11-04        Perubahan model dari penugasan peserta   Menjadi baseline
                                      langsung ke kursus menjadi kursus--versi kebutuhan terbaru
                                      kurikulum--angkatan/kelas--enrollment;   
                                      pembelajaran tatap muka; satu tentor per 
                                      kelas; presensi; jadwal; tugas; ujian;   
                                      skema nilai; kelayakan sertifikat; dan   
                                      penguncian kurikulum per angkatan        
  ----------------------------------------------------------------------------------------------

### 1.1 Prinsip tidak menghilangkan riwayat

1.  Isi PRD v3.0 dipertahankan secara utuh pada Lampiran A.
2.  Status implementasi yang tercatat pada v3.0 adalah catatan historis
    berdasarkan dokumentasi saat itu, bukan bukti bahwa fitur tersebut
    telah diuji ulang pada struktur v4.0.
3.  Penambahan kebutuhan v4.0 tidak boleh otomatis diberi label
    "selesai".
4.  Perubahan implementasi harus dicatat pada `log.md`, termasuk
    tanggal, alasan, modul terdampak, perubahan database, dan hasil
    pengujian.
5.  Apabila implementasi aktual berbeda dari dokumen, lakukan audit kode
    dan database sebelum mengubah status implementasi.

## 2. Ringkasan Produk

ELIPS LMS adalah sistem pengelolaan pelatihan dan kursus berbasis web
untuk mengelola proses akademik lembaga pelatihan, mulai dari
pengelolaan pengguna, program kursus, kurikulum, angkatan/kelas,
penugasan tentor, pendaftaran peserta, jadwal pembelajaran tatap muka,
presensi, materi, tugas, ujian, penilaian, hingga penerbitan sertifikat.

Sistem berfungsi sebagai **academic management system untuk pelatihan
offline**, bukan platform kelas daring sebagai metode utama. Materi
digital tetap disediakan sebagai pendukung pembelajaran, referensi,
instruksi tugas, dan dokumentasi akademik.

### 2.1 Tujuan utama

-   Memusatkan data peserta, tentor, kursus, angkatan, kurikulum,
    jadwal, presensi, nilai, dan sertifikat.
-   Memastikan peserta hanya terdaftar pada angkatan yang benar.
-   Menjaga konsistensi kurikulum setiap angkatan.
-   Memberikan tentor ruang untuk mengelola materi dan penilaian kelas
    yang menjadi tanggung jawabnya.
-   Menyediakan data perkembangan akademik yang dapat dipantau Admin.
-   Mengurangi proses administrasi manual dan duplikasi pencatatan.
-   Menjaga riwayat akademik agar tidak hilang ketika data operasional
    berubah.

### 2.2 Di luar cakupan utama

Kecuali ditambahkan pada versi berikutnya, sistem tidak ditujukan
untuk: - Menggantikan proses belajar tatap muka dengan konferensi
video. - Menyediakan live streaming kelas. - Menyediakan sistem payroll
dan penggajian tentor. - Menjadi sistem akuntansi penuh. - Menyediakan
aplikasi mobile native. - Mengelola ujian adaptif atau proctoring
online. - Mengubah kurikulum angkatan lama setelah angkatan tersebut
menggunakannya.

## 3. Prinsip dan Keputusan Bisnis yang Mengikat

  -----------------------------------------------------------------------
  ID                      Keputusan               Aturan final
  ----------------------- ----------------------- -----------------------
  BR-001                  Metode belajar          Pembelajaran utama
                                                  dilakukan offline/tatap
                                                  muka

  BR-002                  Relasi peserta          Satu peserta dapat
                                                  mengikuti banyak kursus
                                                  melalui enrollment pada
                                                  kelas/angkatan yang
                                                  berbeda

  BR-003                  Relasi tentor dan kelas Satu kelas hanya
                                                  memiliki satu tentor
                                                  penanggung jawab

  BR-004                  Beban tentor            Satu tentor dapat
                                                  ditugaskan ke banyak
                                                  kelas

  BR-005                  Versi kurikulum         Setiap angkatan
                                                  mengunci satu versi
                                                  kurikulum

  BR-006                  Perubahan kurikulum     Kurikulum yang telah
                                                  digunakan angkatan
                                                  tidak boleh diubah

  BR-007                  Pengembangan kurikulum  Perubahan kurikulum
                                                  dilakukan dengan
                                                  membuat versi baru

  BR-008                  Akses materi            Tentor dapat mengatur
                                                  mode akses materi
                                                  sesuai konfigurasi yang
                                                  diizinkan

  BR-009                  Penilaian               Nilai terdiri dari
                                                  komponen tugas, ujian,
                                                  dan penilaian manual

  BR-010                  Bobot penilaian         Bobot komponen dapat
                                                  diatur per versi
                                                  kurikulum/kelas dan
                                                  totalnya harus 100%

  BR-011                  Sertifikat              Kelayakan dihitung
                                                  otomatis; penerbitan
                                                  final memerlukan
                                                  persetujuan Admin

  BR-012                  Teknologi               Tetap menggunakan PHP
                                                  Native dan MySQL

  BR-013                  Riwayat                 Data presensi, nilai,
                                                  enrollment, dan
                                                  sertifikat tidak
                                                  dihapus secara permanen
                                                  melalui operasi normal

  BR-014                  Isolasi kelas           Tentor hanya dapat
                                                  mengelola kelas yang
                                                  ditugaskan kepadanya

  BR-015                  Kepemilikan akademik    Enrollment peserta
                                                  selalu mengacu pada
                                                  kelas/angkatan, bukan
                                                  hanya kursus
  -----------------------------------------------------------------------

## 4. Istilah dan Definisi

  -----------------------------------------------------------------------
  Istilah                             Definisi
  ----------------------------------- -----------------------------------
  Kursus                              Program pelatihan yang ditawarkan
                                      lembaga, misalnya Web Programming
                                      atau Desain Grafis

  Versi Kurikulum                     Snapshot struktur akademik kursus
                                      pada revisi tertentu

  Modul                               Kelompok materi atau unit
                                      pembelajaran di dalam kurikulum

  Materi/Lesson                       Satuan pembelajaran yang memiliki
                                      konten, tujuan, urutan, dan
                                      pengaturan akses

  Angkatan                            Periode penyelenggaraan suatu
                                      kursus

  Kelas                               Kelompok peserta pada angkatan yang
                                      sama; pada sistem ini istilah kelas
                                      dan angkatan dapat ditampilkan
                                      bersama

  Tentor                              Pengajar/pelatih yang ditugaskan
                                      sebagai satu-satunya penanggung
                                      jawab kelas

  Peserta                             Pengguna yang mengikuti kursus
                                      melalui kelas/angkatan

  Enrollment                          Catatan pendaftaran peserta pada
                                      satu kelas

  Sesi Pertemuan                      Pertemuan tatap muka yang
                                      dijadwalkan pada tanggal dan waktu
                                      tertentu

  JP                                  Jam Pelajaran; satuan durasi
                                      akademik yang ditetapkan lembaga

  Presensi                            Catatan kehadiran peserta pada
                                      sesi/pertemuan

  Tugas                               Aktivitas penilaian yang dikerjakan
                                      peserta

  Ujian                               Penilaian terstruktur yang
                                      diselenggarakan pada waktu/periode
                                      tertentu

  Penilaian Manual                    Penilaian tentor terhadap aspek
                                      praktik, sikap kerja, partisipasi,
                                      atau kompetensi

  Skema Penilaian                     Pengaturan komponen dan bobot untuk
                                      menghasilkan nilai akhir

  Predikat                            Kategori capaian berdasarkan
                                      rentang nilai

  Kelayakan Sertifikat                Status hasil pemeriksaan syarat
                                      akademik sebelum persetujuan Admin
  -----------------------------------------------------------------------

------------------------------------------------------------------------

# BAGIAN II --- PENGGUNA, HAK AKSES, DAN ONBOARDING

## 5. Peran Pengguna

### 5.1 Admin

Admin bertanggung jawab atas administrasi dan pengendalian seluruh
sistem.

Hak dan tanggung jawab: - Mengelola akun Admin, Tentor, dan Peserta. -
Mengaktifkan atau menonaktifkan akun. - Mengelola kategori dan kursus. -
Membuat serta menerbitkan versi kurikulum. - Membuat angkatan/kelas. -
Menetapkan satu tentor untuk setiap kelas. - Mengatur periode, kuota,
lokasi, dan jadwal kelas. - Mendaftarkan peserta ke satu atau beberapa
kelas. - Mengelola pengaturan presensi dan kelulusan. - Memantau progres
akademik. - Memeriksa dan menyetujui sertifikat. - Melihat laporan dan
audit trail. - Mengelola pengaturan sistem.

### 5.2 Tentor

Tentor adalah pengajar yang ditugaskan pada kelas tertentu.

Hak dan tanggung jawab: - Melihat kelas yang ditugaskan kepadanya. -
Melihat daftar peserta pada kelas yang diampu. - Melihat versi kurikulum
yang digunakan kelas. - Mengelola materi sesuai hak akses dan status
kurikulum. - Mengatur akses materi sesuai konfigurasi yang tersedia. -
Melihat jadwal dan sesi pertemuan kelas. - Mencatat atau mengonfirmasi
presensi sesuai kebijakan. - Membuat dan mengelola tugas. - Membuat dan
mengelola ujian apabila diizinkan. - Memberikan penilaian tugas, ujian,
dan manual. - Melihat rekap nilai peserta. - Mengajukan penyelesaian
akademik/kelayakan sertifikat. - Tidak dapat mengakses atau mengubah
kelas tentor lain.

**Batasan penting:** Tentor tidak dapat mengubah kurikulum yang telah
dikunci oleh angkatan. Tentor dapat melakukan aktivitas pembelajaran
operasional yang memang diizinkan tanpa mengubah snapshot kurikulum.

### 5.3 Peserta

Peserta adalah pengguna yang mengikuti satu atau beberapa kelas.

Hak dan tanggung jawab: - Melengkapi profil. - Melihat kelas yang
diikuti. - Melihat informasi kursus dan angkatan. - Melihat jadwal
pertemuan. - Melihat materi yang sudah dibuka sesuai aturan akses. -
Melihat tugas dan instruksi pengerjaan. - Mengumpulkan tugas jika
diminta. - Mengikuti ujian yang dijadwalkan. - Melihat presensi
miliknya. - Melihat nilai dan umpan balik yang telah dipublikasikan. -
Melihat status kelayakan sertifikat. - Mengunduh sertifikat yang telah
diterbitkan.

Peserta tidak dapat melihat data pribadi, tugas, nilai, atau presensi
peserta lain.

## 6. Status Akun dan Onboarding

Status akun, kelengkapan profil, dan status enrollment merupakan tiga
hal yang berbeda dan tidak boleh digabungkan.

### 6.1 Status akun

-   `pending`: akun baru dibuat dan menunggu aktivasi atau proses awal.
-   `active`: akun dapat login.
-   `inactive`: akun dinonaktifkan dan tidak dapat login.
-   `suspended`: akun dibatasi karena pemeriksaan atau kebijakan
    lembaga.

### 6.2 Kelengkapan profil

-   `incomplete`: data profil wajib belum lengkap.
-   `complete`: seluruh data wajib telah dilengkapi.

### 6.3 Alur akun Tentor

1.  Admin membuat akun Tentor.
2.  Akun dapat berstatus `pending` atau `inactive`.
3.  Admin melengkapi/meninjau profil dan mengaktifkan akun.
4.  Admin menugaskan Tentor pada kelas.
5.  Tentor dapat mengakses kelas yang ditugaskan setelah akun aktif.

Penugasan kelas tidak otomatis mengaktifkan akun yang dinonaktifkan.

### 6.4 Alur akun Peserta

1.  Admin membuat akun Peserta atau Peserta melakukan registrasi jika
    fitur registrasi mandiri diaktifkan.
2.  Peserta login sesuai status akun.
3.  Pada login awal, Peserta diarahkan untuk melengkapi profil wajib.
4.  Admin memeriksa dan mengaktifkan akun jika kebijakan lembaga
    mensyaratkan persetujuan.
5.  Admin mendaftarkan Peserta ke kelas.
6.  Akses akademik diberikan jika akun aktif dan enrollment kelas
    berstatus aktif.

**Aturan:** Akun aktif tidak otomatis berarti Peserta sudah terdaftar
pada kelas. Enrollment aktif tidak boleh melewati pemeriksaan status
akun.

## 7. Matriks Hak Akses

  ------------------------------------------------------------------------------
  Aktivitas                          Admin             Tentor            Peserta
  ---------------- ----------------------- ------------------ ------------------
  Mengelola akun                        Ya              Tidak     Profil sendiri
  pengguna                                                    

  Mengelola kursus                      Ya              Tidak              Tidak

  Membuat versi                         Ya              Tidak              Tidak
  kurikulum                                                   

  Mengunci versi                        Ya              Tidak              Tidak
  kurikulum pada                                              
  angkatan                                                    

  Membuat                               Ya              Tidak              Tidak
  angkatan/kelas                                              

  Menetapkan                            Ya              Tidak              Tidak
  tentor kelas                                                

  Melihat seluruh                       Ya              Tidak              Tidak
  kelas                                                       

  Melihat kelas                         Ya                 Ya      Kelas sendiri
  yang ditugaskan                                             

  Mengelola materi   Pratinjau/pengelolaan   Sesuai penugasan  Baca sesuai akses
  kelas                                      dan status versi 

  Mengatur akses          Kebijakan sistem    Ya, dalam batas              Tidak
  materi                                                kelas 

  Mengelola jadwal                      Ya        Lihat; usul       Lihat jadwal
                                               perubahan jika            sendiri
                                                   diaktifkan 

  Mengelola             Koreksi/monitoring       Catat sesuai        Lihat milik
  presensi                                         kewenangan            sendiri

  Membuat tugas         Ya jika diperlukan                 Ya              Tidak

  Mengumpulkan                       Tidak              Tidak                 Ya
  tugas                                                       

  Mengelola ujian       Ya jika diperlukan    Ya sesuai kelas          Mengikuti

  Memberi nilai           Koreksi/override                 Ya              Tidak
                              terotorisasi                    

  Melihat nilai                      Semua      Kelas sendiri      Milik sendiri

  Menyetujui                            Ya              Tidak              Tidak
  sertifikat                                                  

  Melihat                            Semua      Kelas sendiri      Milik sendiri
  sertifikat                                 sesuai kebijakan 

  Melihat audit                         Ya              Tidak              Tidak
  trail                                                       
  ------------------------------------------------------------------------------

Semua hak akses wajib diperiksa di backend PHP. Menyembunyikan tombol
pada frontend bukan mekanisme otorisasi.

------------------------------------------------------------------------

# BAGIAN III --- KURSUS, KURIKULUM, DAN ANGKATAN

## 8. Manajemen Kursus

Kursus adalah master program pelatihan yang dapat diselenggarakan
berulang kali dalam angkatan berbeda.

Data kursus minimal: - ID unik. - Kode kursus. - Nama kursus. -
Kategori. - Deskripsi. - Tujuan pembelajaran. - Gambar/thumbnail
opsional. - Status. - Durasi standar. - Satuan durasi
(JP/pertemuan/bulan). - Informasi prasyarat jika ada. - Tanggal dibuat
dan diperbarui. - Pembuat dan pengubah terakhir.

Status kursus: - `draft`: belum siap digunakan. - `active`: dapat
dibuatkan versi kurikulum dan angkatan. - `inactive`: tidak ditawarkan
untuk angkatan baru. - `archived`: arsip; riwayat kelas lama tetap dapat
dibaca.

Kursus tidak boleh dihapus permanen apabila sudah memiliki versi
kurikulum, kelas, enrollment, nilai, atau sertifikat.

## 9. Versi Kurikulum

### 9.1 Prinsip versioning

Setiap kursus memiliki satu atau lebih versi kurikulum. Contoh:

-   Web Programming --- v1.0
-   Web Programming --- v1.1
-   Web Programming --- v2.0

Versi kurikulum adalah snapshot akademik yang memuat: - Nama dan
deskripsi versi. - Tujuan pembelajaran. - Daftar modul. - Daftar materi
setiap modul. - Urutan modul dan materi. - JP rencana per
materi/modul. - Tugas yang menjadi bagian kurikulum jika ditetapkan. -
Ujian yang menjadi bagian kurikulum jika ditetapkan. - Skema penilaian
dan bobot. - Predikat dan ambang kelulusan. - Aturan kehadiran minimum
jika ditetapkan. - Aturan kelayakan sertifikat. - Tanggal efektif. -
Pembuat dan catatan perubahan.

### 9.2 Status versi kurikulum

-   `draft`: sedang disusun; dapat diubah.
-   `published`: telah disetujui dan dapat digunakan untuk angkatan
    baru.
-   `locked`: telah dipakai oleh setidaknya satu angkatan; tidak dapat
    diubah.
-   `retired`: tidak dipakai untuk angkatan baru; tetap tersedia untuk
    riwayat.

Transisi utama:

``` text
Draft → Published → Locked → Retired
```

Versi yang sudah digunakan angkatan tidak boleh dikembalikan menjadi
Draft untuk diedit.

### 9.3 Penguncian kurikulum per angkatan

Ketika Admin membuat angkatan: 1. Admin memilih kursus. 2. Sistem
menampilkan versi kurikulum yang berstatus `published`. 3. Admin memilih
satu versi. 4. Sistem menyimpan `curriculum_version_id` pada angkatan.
5. Versi tersebut dikunci dari perubahan struktural. 6. Angkatan dan
seluruh peserta di dalamnya menggunakan versi yang sama.

**Larangan mutlak:** perubahan pada kurikulum untuk kebutuhan angkatan
baru tidak boleh mengubah kurikulum angkatan lama.

### 9.4 Perubahan kurikulum

Jika ada perubahan materi, urutan, JP, tugas wajib, ujian, atau bobot
nilai: 1. Admin membuat versi baru dari versi sebelumnya. 2. Sistem
menyalin struktur sebagai draft baru. 3. Admin mengubah draft baru. 4.
Admin meninjau dan memublikasikan versi baru. 5. Angkatan berikutnya
dapat menggunakan versi baru. 6. Angkatan lama tetap menggunakan versi
yang telah dikunci.

Perubahan koreksi typo yang tidak mengubah substansi juga harus
mengikuti mekanisme revisi versi agar audit dan snapshot tetap
konsisten. Jika lembaga membutuhkan catatan editorial yang tidak
mengubah konten akademik, catat sebagai metadata/annotation terpisah,
bukan mengubah isi snapshot.

### 9.5 Snapshot dan integritas historis

Data versi kurikulum yang sudah digunakan tidak boleh diubah melalui
CRUD biasa. Pengamanan dilakukan melalui: - validasi status di
backend; - foreign key dari kelas ke versi kurikulum; - larangan
update/delete terhadap versi `locked`; - audit log; - transaksi database
saat membuat angkatan dan mengunci versi.

Jika ditemukan kesalahan akademik yang berdampak pada angkatan berjalan,
sistem tidak mengedit versi lama. Admin harus membuat proses koreksi
resmi yang memiliki alasan, persetujuan, tanggal efektif, dan catatan
perubahan. Kebijakan koreksi ini tidak boleh digunakan untuk mengganti
kurikulum secara diam-diam.

## 10. Modul dan Materi

### 10.1 Struktur

``` text
Kursus
└── Versi Kurikulum
    ├── Modul 1
    │   ├── Materi 1
    │   ├── Materi 2
    │   └── Materi 3
    ├── Modul 2
    │   ├── Materi 1
    │   └── Materi 2
    └── Modul 3
        └── Materi 1
```

### 10.2 Data modul

-   Nama modul.
-   Deskripsi.
-   Urutan.
-   Target JP.
-   Tujuan modul.
-   Status publikasi pada versi draft.
-   Relasi ke versi kurikulum.

### 10.3 Data materi

-   Judul materi.
-   Ringkasan.
-   Konten teks.
-   Jenis materi.
-   File lampiran.
-   Link eksternal.
-   Urutan.
-   JP rencana.
-   Tujuan pembelajaran.
-   Instruksi kelas/praktik.
-   Status wajib/opsional.
-   Relasi ke modul.

Jenis materi yang didukung: - `text`: rich text yang disanitasi. -
`document`: PDF atau dokumen. - `video`: tautan atau file video sebagai
bahan pendukung. - `link`: tautan eksternal. - `file`: lampiran lain
yang diizinkan.

Materi digital berfungsi sebagai pendukung pertemuan tatap muka, bukan
indikator bahwa pembelajaran harus dilakukan secara online.

### 10.4 Pengelolaan materi setelah versi dikunci

Materi yang termasuk snapshot kurikulum terkunci tidak dapat diedit
langsung. Jika tentor perlu menambahkan bahan tambahan untuk kebutuhan
kelas: - gunakan fitur **Materi Tambahan Kelas** yang terpisah dari
kurikulum inti; - catat pembuat, waktu, alasan, dan kelas tujuan; -
materi tambahan tidak mengubah versi kurikulum; - Admin dapat mengatur
apakah materi tambahan wajib, opsional, atau hanya referensi; - materi
tambahan tidak boleh diam-diam mengubah tugas wajib, ujian, bobot, atau
syarat kelulusan versi kurikulum.

## 11. Pengaturan Akses Materi

Tentor dapat mengatur akses materi untuk kelas yang menjadi tanggung
jawabnya, dalam batas kebijakan versi kurikulum dan pengaturan Admin.

Mode akses: 1. `open`: semua materi yang ditandai tersedia dapat dibuka.
2. `sequential`: materi dibuka sesuai urutan; materi berikutnya terbuka
setelah materi sebelumnya ditandai selesai atau dikonfirmasi tentor. 3.
`scheduled`: materi dibuka berdasarkan tanggal atau sesi pertemuan. 4.
`conditional`: materi dibuka jika syarat tertentu terpenuhi, misalnya
sesi sebelumnya telah berlangsung atau tugas prasyarat telah dinyatakan
selesai.

Aturan: - Pengaturan akses berlaku pada kelas, bukan mengubah master
kurikulum. - Tentor tidak dapat membuka materi yang belum boleh diakses
jika ada larangan administratif. - Admin dapat melakukan override dengan
alasan dan audit log. - Peserta hanya dapat melihat materi yang memenuhi
aturan akses. - Perubahan aturan akses dicatat, termasuk nilai sebelum
dan sesudah.

------------------------------------------------------------------------

# BAGIAN IV --- ANGKATAN, KELAS, DAN ENROLLMENT

## 12. Manajemen Angkatan/Kelas

Kelas merupakan penyelenggaraan satu kursus pada satu periode dengan
satu versi kurikulum tertentu.

Data kelas minimal: - ID kelas. - Kode kelas/angkatan. - Nama kelas. -
Kursus. - Versi kurikulum yang dikunci. - Periode mulai dan selesai. -
Kuota peserta. - Lokasi/cabang. - Ruang kelas jika tersedia. - Hari dan
jam pembelajaran. - Target total JP. - Satu tentor penanggung jawab. -
Status kelas. - Catatan administratif.

Status kelas: - `draft`: sedang disiapkan. - `open`: pendaftaran
dibuka. - `ready`: siap berjalan. - `ongoing`: pembelajaran
berlangsung. - `completed`: seluruh proses akademik selesai. -
`cancelled`: kelas dibatalkan. - `archived`: kelas telah diarsipkan.

### 12.1 Aturan penugasan tentor

-   Satu kelas wajib memiliki tepat satu tentor penanggung jawab sebelum
    kelas berstatus `ready` atau `ongoing`.
-   Satu tentor dapat ditugaskan ke banyak kelas.
-   Satu kelas tidak boleh memiliki dua tentor utama aktif.
-   Pergantian tentor dilakukan Admin.
-   Pergantian tentor menyimpan histori tentor lama, tentor baru, waktu
    efektif, alasan, dan aktor.
-   Tentor yang sudah tidak ditugaskan tidak lagi memiliki hak untuk
    mengubah data kelas setelah tanggal efektif pergantian, kecuali
    diberi akses administratif khusus.
-   Jika tentor dinonaktifkan, Admin wajib menetapkan pengganti sebelum
    kelas dapat melanjutkan aktivitas yang membutuhkan tentor aktif.

Relasi inti: - `classes.instructor_id` menunjuk satu tentor aktif. -
Histori penugasan disimpan pada tabel riwayat terpisah, misalnya
`class_instructor_histories`.

### 12.2 Pembuatan kelas

1.  Admin memilih kursus.
2.  Admin memilih versi kurikulum `published`.
3.  Admin mengisi identitas angkatan, periode, kuota, lokasi, dan jadwal
    awal.
4.  Admin menetapkan satu tentor.
5.  Sistem memvalidasi tentor aktif.
6.  Sistem mengunci referensi versi kurikulum untuk kelas.
7.  Admin menyimpan kelas sebagai `draft` atau `open`.
8.  Peserta dapat didaftarkan sesuai kebijakan.

### 12.3 Kuota

-   Kuota maksimum ditentukan Admin.
-   Jumlah enrollment aktif tidak boleh melebihi kuota.
-   Peserta yang dibatalkan pendaftarannya tidak dihitung sebagai
    enrollment aktif.
-   Jika kuota penuh, sistem menolak pendaftaran baru atau memasukkannya
    ke daftar tunggu jika fitur waitlist diaktifkan.

## 13. Enrollment Peserta

Enrollment adalah hubungan Peserta dengan kelas tertentu.

**Model relasi final:**

``` text
Peserta ──< Enrollment >── Kelas/Angkatan
                              │
                              ├── Kursus
                              ├── Versi Kurikulum (terkunci)
                              └── Satu Tentor
```

Aturan: - Satu Peserta boleh memiliki banyak enrollment aktif pada kelas
yang berbeda. - Satu kelas boleh memiliki banyak Peserta. - Satu
enrollment hanya mengacu pada satu kelas. - Satu peserta tidak boleh
memiliki enrollment ganda aktif pada kelas yang sama. - Peserta yang
mengikuti kursus yang sama pada periode berbeda harus memiliki
enrollment terpisah. - Enrollment menyimpan snapshot identitas akademik
yang diperlukan untuk menjaga riwayat. - Nilai, presensi, progres, dan
sertifikat dihubungkan ke enrollment agar tidak tertukar antarangkatan.

Status enrollment: - `pending`: menunggu konfirmasi. - `active`:
terdaftar dan dapat mengikuti kelas. - `completed`: proses akademik
selesai. - `withdrawn`: mengundurkan diri. - `suspended`: akses akademik
dihentikan sementara. - `cancelled`: pendaftaran dibatalkan.

Enrollment yang sudah memiliki presensi atau nilai tidak boleh dihapus
permanen. Perubahan status harus tercatat.

------------------------------------------------------------------------

# BAGIAN V --- PEMBELAJARAN TATAP MUKA, JADWAL, DAN PRESENSI

## 14. Model Pembelajaran Offline

Sistem mengelola kegiatan belajar tatap muka dengan pencatatan jadwal,
sesi, JP rencana, JP aktual, lokasi, materi, dan presensi.

Setiap kelas memiliki: - rencana pertemuan; - tanggal dan jam; - lokasi
dan ruang; - tentor penanggung jawab; - materi/modul yang dibahas; - JP
rencana; - JP aktual; - status pelaksanaan.

Materi digital dapat digunakan sebelum atau sesudah pertemuan untuk
referensi, tetapi kehadiran fisik tetap dicatat sebagai aktivitas
akademik utama.

## 15. Jadwal dan Sesi Pertemuan

Jadwal berulang dapat digunakan untuk membentuk sesi pertemuan. Sesi
yang telah dibuat harus menjadi catatan tersendiri agar perubahan jadwal
berikutnya tidak mengubah histori sesi yang telah berlangsung.

Data sesi: - Kelas. - Nomor pertemuan. - Tanggal. - Waktu mulai dan
selesai. - Lokasi. - Ruang. - Tentor. - Materi/modul terkait. - JP
rencana. - JP aktual. - Status. - Catatan pelaksanaan.

Status sesi: - `scheduled`: dijadwalkan. - `confirmed`: dikonfirmasi. -
`completed`: telah berlangsung. - `rescheduled`: dijadwalkan ulang. -
`cancelled`: dibatalkan. - `no_show`: tidak terlaksana tanpa pengganti.

Aturan: - Sesi yang telah selesai tidak boleh dihapus. - Perubahan
jadwal harus menyimpan nilai lama dan baru. - Sesi yang dibatalkan tidak
dihitung sebagai JP aktual. - Sesi pengganti dibuat sebagai sesi baru
dan dapat ditautkan ke sesi yang digantikan. - Tentor hanya dapat
mengubah sesi pada kelas yang ditugaskan, sesuai batas waktu dan
kebijakan Admin. - Admin dapat mengoreksi jadwal dengan alasan dan audit
trail.

## 16. Jam Pelajaran (JP)

JP adalah satuan durasi akademik yang digunakan lembaga. Durasi satu JP
ditetapkan pada pengaturan sistem atau konfigurasi program, bukan
diasumsikan selalu sama.

Sistem harus membedakan: - `planned_jp`: JP yang direncanakan. -
`actual_jp`: JP yang benar-benar terlaksana. - `material_jp`: estimasi
durasi akademik suatu materi. - `assessment_weight`: bobot kontribusi
nilai.

JP tidak sama dengan bobot nilai. JP tidak otomatis menentukan
persentase nilai akhir.

Rekap JP dapat ditampilkan per: - sesi; - materi; - modul; - kelas; -
peserta (jika kebijakan kehadiran menghitung JP hadir); - tentor.

## 17. Presensi Peserta

Presensi dicatat per sesi pertemuan dan per peserta.

Status presensi: - `present`: hadir. - `late`: terlambat. -
`permission`: izin. - `sick`: sakit. - `absent`: tanpa keterangan. -
`excused`: ketidakhadiran yang disetujui sesuai kebijakan.

Data presensi: - sesi; - enrollment; - peserta; - status; - waktu
pencatatan; - pencatat; - catatan; - waktu koreksi; - alasan koreksi.

Aturan: - Satu enrollment hanya memiliki satu catatan presensi utama per
sesi. - Tentor dapat mengisi presensi kelas yang ditugaskan. - Admin
dapat mengoreksi presensi dengan alasan. - Peserta hanya dapat melihat
presensi miliknya. - Perubahan presensi setelah sesi ditutup memerlukan
hak khusus dan tercatat. - Sistem menghitung jumlah hadir, izin, sakit,
alfa, dan persentase kehadiran. - Persentase kehadiran dihitung dari
sesi/JP yang memenuhi kebijakan perhitungan; kebijakan harus konsisten
dan ditampilkan pada laporan.

Contoh perhitungan berbasis JP:

`Persentase Kehadiran = Total JP Hadir / Total JP Wajib × 100%`

Kebijakan apakah izin/sakit dihitung sebagai hadir atau tidak hadir
harus ditentukan Admin pada pengaturan akademik. Default yang
disarankan: hanya `present` dan `late` yang dihitung sebagai JP hadir;
status lain tetap dilaporkan terpisah.

------------------------------------------------------------------------

# BAGIAN VI --- TUGAS, UJIAN, DAN PENILAIAN

## 18. Tugas

Tugas merupakan komponen penilaian yang terkait dengan kelas dan dapat
merujuk ke materi atau modul pada versi kurikulum.

Data tugas: - kelas; - versi kurikulum; - materi/modul terkait; -
judul; - instruksi; - jenis tugas; - tanggal dibuka; - tenggat; - nilai
maksimum; - bobot atau komponen penilaian; - apakah unggahan file
diwajibkan; - tipe file yang diizinkan; - ukuran maksimum; - status
publikasi; - pembuat; - rubrik penilaian.

Jenis tugas: - `file_upload`: peserta mengunggah file. -
`text_submission`: peserta mengisi jawaban teks. - `in_class_practice`:
praktik dikerjakan di kelas dan dicatat tentor. - `external_reference`:
tugas menggunakan tautan atau media eksternal dengan bukti penyelesaian
sesuai kebijakan.

Aturan: - Tentor hanya dapat membuat tugas pada kelas yang ditugaskan. -
Tugas yang menjadi bagian kurikulum terkunci tidak boleh mengubah
snapshot kurikulum. - Tugas operasional kelas dapat dibuat sebagai
aktivitas kelas tambahan. - Tentor dapat menentukan apakah pengumpulan
file wajib. - Peserta hanya dapat mengirim tugas untuk enrollment
miliknya. - Sistem menyimpan waktu pengumpulan dan riwayat revisi. -
Keterlambatan ditandai otomatis berdasarkan tenggat. - Tentor dapat
memberi nilai, catatan, dan status revisi. - Perubahan nilai setelah
finalisasi harus memerlukan alasan dan audit.

Status tugas: - `draft`, `published`, `closed`, `archived`.

Status submission: - `not_submitted`, `submitted`, `late`, `reviewed`,
`revision_requested`, `resubmitted`, `accepted`.

## 19. Pengumpulan dan Penilaian Tugas

Satu submission terhubung ke: - satu tugas; - satu enrollment; - satu
peserta; - satu atau beberapa file/jawaban; - histori pengumpulan; -
hasil review.

Fitur: - unggah file privat; - validasi tipe dan ukuran file di
server; - nama file disimpan secara aman dan tidak menggunakan nama asli
sebagai path; - unduh hanya oleh pemilik, tentor kelas, dan Admin
berwenang; - catatan penilaian; - nilai numerik; - rubrik jika
tersedia; - permintaan revisi; - histori perubahan.

Nilai tugas disimpan sebagai skor mentah dan dikonversi ke skala
komponen. Nilai tugas tidak langsung dianggap sebagai nilai akhir
kursus.

## 20. Ujian

Ujian merupakan evaluasi terstruktur yang dapat diselenggarakan secara
offline, praktik langsung, atau bentuk lain yang ditetapkan lembaga.

Data ujian: - kelas; - versi kurikulum; - judul; - deskripsi; - jenis
ujian; - tanggal; - durasi jika relevan; - nilai maksimum; - komponen
penilaian; - bobot; - syarat peserta; - instruksi; - status.

Jenis ujian: - `written`: ujian tertulis/offline. - `practical`: ujian
praktik. - `oral`: ujian lisan/presentasi. - `project`: penilaian proyek
akhir. - `other`: bentuk lain yang ditentukan Admin.

Sistem mencatat peserta yang mengikuti ujian, status keikutsertaan,
nilai, penilai, dan catatan. Sistem tidak wajib menyediakan mesin ujian
online pada versi ini.

## 21. Penilaian Manual

Penilaian manual digunakan untuk aspek yang dinilai langsung oleh
tentor, misalnya: - praktik kerja; - ketepatan prosedur; - pemahaman
konsep; - partisipasi; - kedisiplinan; - kualitas hasil; - komunikasi
atau presentasi.

Komponen manual harus memiliki: - nama komponen; - deskripsi; - skala; -
bobot; - rubrik atau indikator; - nilai; - penilai; - waktu penilaian; -
catatan.

Komponen penilaian manual tidak boleh menjadi kolom bebas tanpa
definisi. Setiap komponen harus masuk ke skema penilaian yang jelas.

## 22. Skema Penilaian dan Nilai Akhir

### 22.1 Komponen

Skema nilai menggabungkan: 1. Tugas. 2. Ujian. 3. Penilaian manual.

Bobot dapat dikonfigurasi. Contoh awal yang dapat digunakan sebagai
template, bukan angka wajib: - Tugas: 30%. - Ujian: 40%. - Penilaian
manual: 30%.

Total bobot harus tepat 100% sebelum skema dipublikasikan atau
digunakan.

### 22.2 Rumus nilai

Nilai komponen dihitung berdasarkan skor aktivitas yang dinormalisasi ke
skala 0--100.

`Nilai Akhir = (Nilai Tugas × Bobot Tugas) + (Nilai Ujian × Bobot Ujian) + (Nilai Manual × Bobot Manual)`

Bobot dalam rumus menggunakan bentuk desimal. Contoh: 30% = 0,30.

Jika satu komponen berisi beberapa aktivitas, skema harus menentukan
agregasi, misalnya rata-rata berbobot atau rata-rata sederhana. Default
yang disarankan: rata-rata berbobot sesuai bobot aktivitas yang
ditetapkan; jika tidak ada bobot aktivitas, gunakan rata-rata sederhana.

### 22.3 Nilai kosong

-   Nilai yang belum diberikan tidak boleh otomatis dianggap 0.
-   Komponen wajib yang belum dinilai menyebabkan nilai akhir berstatus
    `incomplete`.
-   Admin dapat menetapkan pengecualian dengan alasan dan audit.
-   Nilai 0 hanya digunakan jika penilai secara eksplisit memberikan
    skor 0 atau aturan akademik yang telah disetujui menyatakan
    demikian.
-   Sistem harus membedakan `not_assessed`, `excused`, `missing`, dan
    nilai numerik 0.

### 22.4 Predikat

Predikat A--E menggunakan rentang yang dapat diatur Admin. Rentang
default harus dikonfigurasi sebelum sistem digunakan dan tidak boleh
diasumsikan universal.

Contoh template yang perlu disetujui lembaga:

  Predikat     Rentang contoh
  ---------- ----------------
  A                   90--100
  B                    80--89
  C                    70--79
  D                    60--69
  E                     0--59

Sistem harus memastikan rentang tidak tumpang tindih dan mencakup
seluruh skala.

### 22.5 Finalisasi nilai

-   Tentor memasukkan nilai.
-   Sistem menghitung nilai sementara.
-   Tentor memeriksa kelengkapan.
-   Tentor mengajukan finalisasi.
-   Sistem mengunci hasil nilai untuk proses sertifikasi.
-   Koreksi setelah finalisasi memerlukan otorisasi dan alasan.
-   Setiap perubahan setelah finalisasi menyimpan nilai sebelum/sesudah,
    aktor, waktu, dan alasan.

------------------------------------------------------------------------

# BAGIAN VII --- KELULUSAN DAN SERTIFIKAT

## 23. Aturan Kelayakan Akademik

Kelayakan sertifikat dihitung otomatis berdasarkan aturan pada versi
kurikulum yang digunakan kelas.

Kriteria dapat mencakup: - enrollment masih valid; - kelas telah
mencapai status akademik selesai; - persentase kehadiran memenuhi batas
minimum; - seluruh tugas wajib telah dinilai atau dinyatakan
dikecualikan secara sah; - seluruh ujian wajib telah dinilai atau
dinyatakan dikecualikan secara sah; - nilai akhir telah difinalisasi; -
nilai akhir memenuhi batas kelulusan; - kewajiban akademik lain telah
dipenuhi.

Aturan kelayakan disimpan sebagai snapshot pada versi kurikulum.
Perubahan kebijakan untuk angkatan baru tidak mengubah kriteria angkatan
lama.

Status kelayakan: - `not_evaluated`: belum diperiksa. - `ineligible`:
belum memenuhi syarat. - `eligible`: seluruh syarat terpenuhi. -
`exception_review`: terdapat pengecualian yang perlu ditinjau Admin. -
`approved`: telah disetujui Admin. - `rejected`: pengajuan ditolak
dengan alasan.

Sistem harus menampilkan alasan rinci ketika peserta belum eligible,
misalnya: - kehadiran belum memenuhi minimum; - tugas wajib belum
dinilai; - ujian belum lengkap; - nilai akhir belum difinalisasi; -
nilai di bawah batas lulus.

## 24. Alur Sertifikat Hybrid

``` text
Data Akademik Kelas
       │
       ▼
Pemeriksaan Kelayakan Otomatis
       │
       ├── Belum memenuhi → Ineligible + alasan
       │
       └── Memenuhi syarat
                  │
                  ▼
             Eligible
                  │
                  ▼
          Antrian Persetujuan
                  │
                  ▼
            Admin Review
             /       \
        Approve      Reject
           │           │
           ▼           ▼
       Published     Rejected
           │
           ▼
   Nomor & PDF Sertifikat
           │
           ▼
   Peserta dapat mengunduh
```

### 24.1 Pemeriksaan otomatis

Pemeriksaan dijalankan ketika: - nilai difinalisasi; - presensi
diperbarui; - tugas/ujian wajib dinilai; - kelas ditandai selesai; -
Admin meminta pemeriksaan ulang.

Hasil pemeriksaan disimpan dengan waktu evaluasi dan detail alasan.

### 24.2 Persetujuan Admin

Admin dapat: - menyetujui kelayakan; - menolak dengan alasan; - meminta
perbaikan data akademik; - meninjau ringkasan nilai dan kehadiran.

Admin tidak boleh mengubah nilai secara langsung dari layar persetujuan
sertifikat. Koreksi nilai harus melalui proses koreksi nilai.

### 24.3 Penerbitan

Saat disetujui: - sistem membuat nomor sertifikat unik; - sistem
menyimpan tanggal penerbitan; - sistem menyimpan identitas peserta,
kursus, kelas/angkatan, tentor, nilai, predikat, dan versi kurikulum
sebagai snapshot; - sistem menghasilkan PDF; - sistem menyediakan QR
atau kode verifikasi; - sertifikat tampil pada akun peserta; -
sertifikat dapat diverifikasi melalui halaman publik.

Draft/eligible/rejected tidak boleh ditampilkan sebagai sertifikat
terbit kepada publik.

### 24.4 Revisi atau pembatalan sertifikat

Sertifikat yang telah diterbitkan tidak boleh dihapus. Jika terjadi
kesalahan: - buat proses pembatalan/revokasi dengan alasan; - simpan
siapa yang menyetujui; - pertahankan data sertifikat lama; - terbitkan
sertifikat pengganti dengan referensi ke sertifikat sebelumnya; -
halaman verifikasi publik menampilkan status valid/dibatalkan sesuai
kebijakan.

------------------------------------------------------------------------

# BAGIAN VIII --- PENGUMUMAN, NOTIFIKASI, LAPORAN, DAN AUDIT

## 25. Pengumuman Kelas

Admin dan Tentor dapat membuat pengumuman sesuai kewenangan.

Target pengumuman: - seluruh pengguna; - seluruh Tentor; - seluruh
Peserta; - kursus tertentu; - kelas tertentu; - peserta tertentu.

Data pengumuman: - judul; - isi; - pembuat; - target; - waktu
publikasi; - masa berlaku; - status.

Peserta hanya melihat pengumuman yang ditujukan kepadanya.

## 26. Notifikasi

Notifikasi in-app dan email dapat digunakan untuk: - akun
dibuat/diaktifkan; - peserta ditambahkan ke kelas; - jadwal
dibuat/diubah/dibatalkan; - pengumuman kelas; - tugas baru dan
tenggat; - tugas dinilai atau perlu revisi; - jadwal ujian; - nilai
difinalisasi; - kelayakan sertifikat berubah; - sertifikat diterbitkan.

Status outbox harus membedakan simulasi dan pengiriman aktual. Status
`sent` tidak boleh digunakan untuk email simulasi seolah-olah
benar-benar terkirim. Gunakan misalnya `queued`, `simulated`, `sent`,
`failed`.

## 27. Dashboard

### 27.1 Dashboard Admin

Ringkasan: - jumlah peserta aktif; - jumlah tentor aktif; - jumlah
kursus aktif; - jumlah versi kurikulum; - jumlah kelas berjalan; - kelas
yang akan dimulai; - kelas yang hampir selesai; - jumlah enrollment; -
rata-rata kehadiran; - peserta dengan presensi bermasalah; - tugas/ujian
belum dinilai; - sertifikat menunggu persetujuan.

### 27.2 Dashboard Tentor

Ringkasan: - kelas yang diampu; - jumlah peserta per kelas; - jadwal
terdekat; - sesi yang belum diisi presensinya; - tugas yang belum
diperiksa; - ujian yang belum dinilai; - peserta dengan progres akademik
tertinggal; - peserta yang memenuhi syarat sertifikat.

### 27.3 Dashboard Peserta

Ringkasan: - kelas yang diikuti; - informasi kursus dan angkatan; -
jadwal berikutnya; - rekap presensi; - materi yang tersedia; - tugas dan
tenggat; - ujian; - nilai sementara/final; - status kelayakan
sertifikat; - sertifikat terbit.

## 28. Laporan

Laporan Admin minimal: - daftar peserta per kelas; - daftar tentor dan
kelas yang diampu; - enrollment per kursus dan angkatan; - kapasitas
kelas; - jadwal dan realisasi pertemuan; - rencana dan realisasi JP; -
presensi per peserta dan kelas; - rekap nilai per kelas; - rekap
predikat; - peserta belum memenuhi syarat kelulusan; - daftar sertifikat
eligible/menunggu persetujuan/terbit; - histori perubahan data akademik.

Filter laporan: - kursus; - versi kurikulum; - angkatan; - tentor; -
periode; - status; - peserta.

Ekspor PDF/CSV/Excel dapat dikembangkan bertahap. Data laporan harus
berasal dari query server dan dibatasi sesuai hak akses.

## 29. Audit Trail

Aktivitas yang wajib dicatat: - create/update/disable user; - perubahan
profil administratif; - pembuatan dan publikasi kursus; - pembuatan dan
publikasi versi kurikulum; - penguncian versi kurikulum; - pembuatan
kelas; - penugasan/pergantian tentor; - enrollment peserta; - perubahan
jadwal; - presensi dan koreksinya; - pembuatan/perubahan tugas dan
ujian; - pengumpulan dan review tugas; - perubahan nilai; - finalisasi
nilai; - evaluasi kelayakan sertifikat; - approval/rejection/revocation
sertifikat.

Data audit minimal: - aktor; - role; - aksi; - entitas; - ID entitas; -
waktu; - nilai sebelum; - nilai sesudah; - alasan; - alamat
IP/user-agent jika kebijakan privasi mengizinkan.

Audit trail tidak boleh dapat diubah oleh Tentor atau Peserta.
Penghapusan audit tidak disediakan pada penggunaan operasional normal;
retensi dan pemusnahan harus mengikuti kebijakan lembaga.

------------------------------------------------------------------------

# BAGIAN IX --- MODEL DATA DAN RELASI

## 30. Prinsip Desain Database

-   MySQL 8 sebagai sumber data utama.
-   Gunakan InnoDB dan foreign key.
-   Gunakan primary key yang konsisten.
-   Gunakan unique constraint untuk aturan unik.
-   Gunakan transaksi untuk operasi multi-tabel yang harus atomik.
-   Gunakan soft delete atau status arsip untuk data akademik.
-   Simpan timestamp `created_at` dan `updated_at`.
-   Gunakan `created_by` dan `updated_by` pada entitas penting.
-   Gunakan tipe DECIMAL untuk skor, bobot, dan JP bila diperlukan.
-   Jangan menyimpan nilai akademik penting hanya di localStorage.
-   Validasi seluruh aturan bisnis di server.

## 31. Entitas Utama yang Direkomendasikan

Daftar berikut merupakan rancangan konseptual v4.0. Nama kolom dan tipe
final harus diselaraskan pada dokumen ERD dan skema SQL.

### 31.1 Identitas dan profil

-   `users`: akun, email, password hash, role/status akun.
-   `student_profiles`: data profil Peserta.
-   `instructor_profiles`: data profil Tentor.
-   `roles` dan `permissions` atau mekanisme role sederhana yang sudah
    digunakan, dengan pemisahan otorisasi yang jelas.

### 31.2 Kursus dan kurikulum

-   `categories`
-   `courses`
-   `curriculum_versions`
-   `modules`
-   `lessons`
-   `lesson_attachments`
-   `curriculum_assessment_schemes`
-   `curriculum_grading_components`
-   `curriculum_completion_rules`

### 31.3 Kelas dan enrollment

-   `classes`
-   `class_instructor_histories`
-   `enrollments`
-   `class_lesson_settings`
-   `class_supplementary_materials`

### 31.4 Jadwal dan presensi

-   `locations`
-   `rooms`
-   `class_schedules`
-   `class_sessions`
-   `session_lessons`
-   `attendances`
-   `attendance_corrections`

### 31.5 Tugas dan ujian

-   `assignments`
-   `assignment_files`
-   `submissions`
-   `submission_files`
-   `submission_reviews`
-   `exams`
-   `exam_participants`
-   `exam_results`

### 31.6 Penilaian

-   `grading_schemes`
-   `grading_components`
-   `manual_assessments`
-   `manual_assessment_scores`
-   `grade_entries`
-   `final_grades`
-   `grade_scales`

### 31.7 Sertifikat

-   `certificate_templates`
-   `certificate_eligibility_checks`
-   `certificates`
-   `certificate_approvals`
-   `certificate_revocations`

### 31.8 Pendukung sistem

-   `announcements`
-   `announcement_targets`
-   `notifications`
-   `audit_logs`
-   `system_settings`

## 32. Relasi dan Constraint Penting

``` text
users
 ├── student_profiles
 └── instructor_profiles

courses
 └── curriculum_versions
      └── modules
           └── lessons

courses
 └── classes
      ├── curriculum_version_id → curriculum_versions.id
      ├── instructor_id → users.id (tepat satu tentor aktif)
      ├── class_sessions
      │    └── attendances
      └── enrollments
           ├── student_id → users.id
           ├── submissions
           ├── exam_results
           ├── grade_entries
           ├── final_grades
           └── certificates
```

Constraint: 1. `classes.curriculum_version_id` wajib menunjuk versi
kurikulum yang sudah `published` pada saat kelas dibuat. 2. Versi
kurikulum berubah menjadi `locked` ketika dipakai kelas. 3. Satu kelas
memiliki satu `instructor_id` aktif. 4. Satu tentor boleh memiliki
banyak kelas. 5. Unique `(class_id, student_id)` pada enrollment
aktif/identitas pendaftaran untuk mencegah duplikasi. 6. Unique
`(session_id, enrollment_id)` pada presensi. 7. Unique
`(task_id, enrollment_id)` untuk submission utama, dengan tabel histori
bila revisi berulang. 8. Satu enrollment hanya memiliki satu final grade
aktif per skema penilaian yang berlaku. 9. Sertifikat memiliki nomor
unik. 10. Versi kurikulum `locked` tidak boleh di-update atau di-delete
melalui aplikasi.

Catatan implementasi: MySQL tidak memiliki partial unique index seperti
beberapa DBMS lain. Jika constraint unik enrollment aktif memerlukan
kondisi status, desain dapat menggunakan unique key pada pasangan
peserta-kelas dan mengelola perubahan status pada baris yang sama, atau
strategi lain yang konsisten.

## 33. Data Historis dan Snapshot

Data yang perlu menjaga snapshot: - versi kurikulum yang dipakai
kelas; - identitas tentor saat periode penugasan; - identitas peserta
pada saat enrollment; - skema penilaian; - aturan kelulusan; - hasil
final grade; - isi sertifikat saat diterbitkan.

Jangan mengambil ulang data master yang dapat berubah untuk menampilkan
dokumen historis tanpa snapshot. Contohnya, sertifikat lama harus tetap
menampilkan nama kursus, kelas, nilai, predikat, dan tanggal sesuai saat
sertifikat diterbitkan.

------------------------------------------------------------------------

# BAGIAN X --- ARSITEKTUR TEKNOLOGI

## 34. Stack Teknologi

Teknologi yang dipertahankan untuk tahap ini:

  -----------------------------------------------------------------------
  Lapisan                             Teknologi
  ----------------------------------- -----------------------------------
  Backend                             PHP Native 8.x

  Database                            MySQL 8

  Frontend                            HTML5, CSS3, Vanilla JavaScript

  Styling                             CSS custom yang sudah digunakan;
                                      Bootstrap Icons/Google Fonts sesuai
                                      kebutuhan

  API                                 PHP Native endpoint dengan JSON

  Authentication                      PHP Session

  Password                            `password_hash()` dan
                                      `password_verify()`

  Database access                     PDO dan prepared statements

  File storage                        Penyimpanan privat di server

  PDF sertifikat                      Library PDF PHP yang dipilih saat
                                      implementasi

  QR/verifikasi                       Library QR atau token verifikasi

  Testing                             PHPUnit atau pengujian PHP/JS yang
                                      sesuai struktur eksisting;
                                      regression test tetap dipertahankan
  -----------------------------------------------------------------------

Tidak ada kewajiban menggunakan Laravel pada versi ini. Arsitektur dapat
dirapikan secara modular tanpa mengganti PHP Native.

## 35. Arsitektur Aplikasi

``` text
Browser
  │
  ├── HTML / CSS / Vanilla JavaScript
  │
  ▼
PHP Native Application
  ├── Authentication & Session
  ├── Authorization / Role Guard
  ├── Request Validation
  ├── Business Services
  │    ├── User
  │    ├── Course & Curriculum
  │    ├── Class & Enrollment
  │    ├── Schedule & Attendance
  │    ├── Learning Content
  │    ├── Assignment & Exam
  │    ├── Assessment
  │    ├── Certification
  │    └── Reporting
  ├── Data Access / Repository
  └── Audit & Notification
         │
         ▼
       MySQL 8
```

### 35.1 Struktur folder yang disarankan

Struktur ini merupakan arah penataan bertahap, bukan klaim bahwa semua
folder sudah ada:

``` text
/
├── index.html
├── login.html
├── register.html
├── course-detail.html
├── verify-certificate.php
├── certificate-view.php
├── admin/
├── instructor/
├── student/
├── assets/
│   ├── css/
│   ├── js/
│   ├── icons/
│   └── images/
├── api/
│   ├── index.php
│   ├── config/
│   ├── middleware/
│   ├── controllers/
│   ├── services/
│   ├── repositories/
│   ├── validators/
│   ├── helpers/
│   └── routes/
├── storage/
│   ├── private/
│   │   ├── materials/
│   │   ├── submissions/
│   │   └── certificates/
│   └── logs/
├── database/
│   ├── schema.sql
│   ├── migrations/
│   └── seeds/
├── tests/
├── docs/
│   ├── prd.md
│   ├── erd.md
│   └── log.md
└── README.md
```

File storage privat tidak boleh dapat diakses langsung melalui URL
publik tanpa pemeriksaan hak akses.

### 35.2 Pola pemrosesan request

1.  Request diterima endpoint PHP.
2.  Session diperiksa.
3.  Role dan ownership diperiksa.
4.  Input divalidasi di server.
5.  Business service menjalankan aturan domain.
6.  Repository menjalankan query PDO prepared statement.
7.  Operasi multi-tabel memakai transaksi.
8.  Audit dicatat.
9.  Response JSON konsisten dikembalikan.

Format response standar:

``` json
{
  "ok": true,
  "message": "Data berhasil disimpan.",
  "data": {}
}
```

Untuk kegagalan, gunakan `ok: false`, pesan yang aman, dan kode error
yang konsisten. Jangan mengirim stack trace atau kredensial database ke
browser.

## 36. Keamanan

Persyaratan minimum: - Password di-hash menggunakan `password_hash`. -
Session ID diregenerasi setelah login. - Cookie session `HttpOnly`,
`Secure` pada HTTPS, dan `SameSite` sesuai kebutuhan. - Proteksi CSRF
untuk request yang mengubah data. - Rate limit login. - Validasi dan
normalisasi input di server. - Prepared statements untuk seluruh query
dinamis. - Escape output HTML. - Sanitasi rich text menggunakan
allowlist. - Validasi file upload berdasarkan MIME, ekstensi, ukuran,
dan isi file bila memungkinkan. - File tugas/materi privat tidak
disajikan langsung dari folder publik. - Authorization berdasarkan role
dan ownership di server. - Snapshot API tidak boleh mengirim seluruh
data sensitif kepada semua role. - Rahasia konfigurasi tidak disimpan di
repository publik. - Backup database dan uji pemulihan berkala. - Audit
untuk perubahan akademik penting. - HTTPS wajib pada lingkungan
produksi.

### 36.1 LocalStorage

Mode `localStorage` yang sudah ada pada v3.0 dipertahankan hanya sebagai
mode demo/regression test jika masih dibutuhkan. `localStorage`: - bukan
sumber data produksi; - tidak boleh menyimpan password atau data
sensitif; - tidak boleh menjadi jalur alternatif yang melewati otorisasi
server; - tidak boleh dipakai untuk menyatakan bahwa data akademik sudah
tersimpan di server.

Mode produksi menggunakan MySQL sebagai sumber data utama.

------------------------------------------------------------------------

# BAGIAN XI --- ALUR END-TO-END

## 37. Alur Persiapan Kursus

1.  Admin membuat kursus Draft.
2.  Admin menyusun versi kurikulum Draft.
3.  Admin menambahkan modul dan materi.
4.  Admin mengatur JP, tugas, ujian, skema penilaian, predikat, dan
    aturan kelulusan.
5.  Admin meninjau dan memublikasikan versi kurikulum.
6.  Versi siap dipakai angkatan baru.

## 38. Alur Pembukaan Angkatan

1.  Admin memilih kursus.
2.  Admin memilih versi kurikulum Published.
3.  Admin mengisi kode dan nama kelas.
4.  Admin mengisi periode, kuota, lokasi, dan jadwal.
5.  Admin menetapkan satu tentor.
6.  Sistem mengunci versi kurikulum yang dipakai.
7.  Admin membuka pendaftaran atau mendaftarkan peserta.
8.  Kelas menjadi Ready/Ongoing sesuai kondisi.

## 39. Alur Peserta

1.  Admin membuat atau memverifikasi akun peserta.
2.  Peserta melengkapi profil.
3.  Admin mendaftarkan peserta ke kelas.
4.  Sistem membuat enrollment.
5.  Peserta melihat detail kelas dan jadwal.
6.  Peserta mengikuti pertemuan tatap muka.
7.  Presensi dicatat per sesi.
8.  Peserta mengakses materi digital sesuai aturan.
9.  Peserta mengerjakan tugas dan ujian.
10. Peserta melihat nilai dan umpan balik yang dipublikasikan.

## 40. Alur Tentor

1.  Tentor login.
2.  Sistem menampilkan kelas yang ditugaskan.
3.  Tentor membuka kelas dan melihat versi kurikulum terkunci.
4.  Tentor mengelola pengaturan akses materi kelas.
5.  Tentor mempersiapkan sesi dan materi tambahan bila diperlukan.
6.  Tentor mengisi presensi.
7.  Tentor membuat/mengelola tugas dan ujian.
8.  Tentor memeriksa submission dan memberi nilai.
9.  Tentor mengisi penilaian manual.
10. Tentor memeriksa rekap dan mengajukan finalisasi nilai.

## 41. Alur Penyelesaian Akademik

1.  Sesi pembelajaran selesai.
2.  Tentor/Admin memastikan presensi lengkap.
3.  Seluruh tugas wajib telah dinilai atau diberi status pengecualian
    sah.
4.  Seluruh ujian wajib telah dinilai atau diberi status pengecualian
    sah.
5.  Nilai akhir dihitung.
6.  Tentor mengajukan finalisasi.
7.  Sistem mengunci nilai final.
8.  Sistem mengevaluasi kelayakan sertifikat.
9.  Admin meninjau peserta eligible.
10. Admin menyetujui atau menolak.
11. Sistem menerbitkan sertifikat jika disetujui.

------------------------------------------------------------------------

# BAGIAN XII --- STATUS IMPLEMENTASI DAN MIGRASI

## 42. Pemetaan Fitur v3.0 ke v4.0

  -----------------------------------------------------------------------
  Fitur v3.0              Perlakuan pada v4.0     Catatan
  ----------------------- ----------------------- -----------------------
  Login dan role guard    Dipertahankan           Perlu audit untuk role
                                                  dan ownership kelas

  CRUD pengguna           Dipertahankan           Tambahkan onboarding
                                                  dan pemisahan status
                                                  akun/profil

  Kursus                  Dipertahankan dan       Kursus menjadi master
                          diperluas               program

  Satu instruktur per     Diganti untuk           Satu kelas satu tentor;
  kursus                  operasional kelas       satu tentor banyak
                                                  kelas

  Enrollment langsung ke  Diganti                 Enrollment mengacu pada
  kursus                                          kelas/angkatan

  Modul dan materi pada   Diperluas               Struktur ditempatkan di
  kursus                                          versi kurikulum

  Akses materi sequential Diperluas               Open, sequential,
                                                  scheduled, conditional

  Progress lesson         Dipertahankan sebagai   Progres tidak
                          fitur pendukung         menggantikan presensi
                                                  offline

  Nilai akhir satu angka  Diperluas               Tugas, ujian, manual,
                                                  bobot, finalisasi

  Sertifikat Draft →      Diperluas               Eligible otomatis →
  Publish                                         approval Admin → terbit

  Audit trail             Dipertahankan dan       Tambahkan kelas,
                          diperluas               kurikulum, jadwal,
                                                  presensi, submission,
                                                  nilai

  Notifikasi simulasi     Dipertahankan sebagai   Status simulasi harus
                          outbox                  eksplisit

  Dashboard dan laporan   Diperluas               Tambahkan data
                                                  angkatan, jadwal, JP,
                                                  presensi, nilai

  PHP Native + MySQL      Dipertahankan           Tidak migrasi Laravel

  localStorage fallback   Demo/regression saja    Bukan mode produksi
  -----------------------------------------------------------------------

## 43. Strategi Migrasi Data dari Model Lama

Migrasi harus dilakukan setelah ERD aktual dan database v3.0 diperiksa.

Pemetaan konseptual: - `courses` lama tetap menjadi master kursus. -
Struktur `modules` dan `lessons` lama dipetakan ke versi kurikulum
awal. - Setiap kursus lama dibuatkan `curriculum_versions` awal,
misalnya `1.0`. - Enrollment lama yang langsung mengacu ke kursus harus
dipetakan ke kelas migrasi. - Untuk setiap kursus lama, buat kelas
migrasi/angkatan historis yang merepresentasikan enrollment lama. -
Tetapkan tentor pada kelas migrasi berdasarkan data instruktur yang
sebelumnya terhubung ke kursus. - Nilai dan sertifikat lama dipetakan ke
enrollment kelas hasil migrasi. - Progres lesson lama dipertahankan dan
dihubungkan ke enrollment baru. - Audit log lama tidak dihapus. - ID
lama disimpan sebagai referensi migrasi bila diperlukan.

### 43.1 Migrasi yang tidak boleh dilakukan otomatis tanpa verifikasi

-   Menebak angkatan peserta jika data periode tidak tersedia.
-   Menggabungkan dua enrollment yang tampak mirip.
-   Mengubah nilai lama menjadi komponen tugas/ujian/manual tanpa sumber
    data.
-   Mengubah status sertifikat yang sudah terbit.
-   Menghapus data lama karena tidak sesuai struktur baru.

Jika data lama tidak cukup untuk menentukan angkatan, gunakan kelas
migrasi dengan label historis dan tandai metadata sebagai
`legacy_import` agar tidak disalahartikan sebagai data periode yang
lengkap.

## 44. Rencana Pengembangan Bertahap

Prioritas berikut adalah usulan urutan implementasi. Fitur v3.0 yang
sudah berjalan tetap harus melalui regression test.

### Phase 0 --- Audit dan fondasi

-   Audit source code PHP dan JavaScript.
-   Audit `api/schema.sql` dan database aktif.
-   Audit `erd.md`.
-   Inventarisasi endpoint dan aturan otorisasi.
-   Backup database.
-   Susun migration plan.
-   Pastikan regression test v3.0 dapat dijalankan.

### Phase 1 --- Data master dan versioning

-   Kursus sebagai master.
-   Curriculum version.
-   Modul dan materi terhubung ke versi.
-   Status draft/published/locked/retired.
-   Mekanisme clone versi.
-   Proteksi versi terkunci.
-   Audit perubahan kurikulum.

### Phase 2 --- Kelas dan enrollment

-   CRUD kelas/angkatan.
-   Pilih versi kurikulum.
-   Satu tentor per kelas.
-   Satu tentor banyak kelas.
-   Histori pergantian tentor.
-   Enrollment berbasis kelas.
-   Kuota dan status pendaftaran.

### Phase 3 --- Jadwal dan presensi offline

-   Lokasi dan ruang.
-   Jadwal kelas.
-   Sesi pertemuan.
-   JP rencana dan aktual.
-   Presensi peserta.
-   Koreksi presensi.
-   Rekap kehadiran.

### Phase 4 --- Materi dan akses kelas

-   Materi versi kurikulum.
-   Pengaturan akses per kelas.
-   Materi tambahan kelas.
-   Progress materi sebagai data pendukung.
-   File privat dan kontrol unduh.

### Phase 5 --- Tugas dan ujian

-   Pembuatan tugas.
-   Upload submission.
-   Review dan revisi.
-   Pembuatan ujian.
-   Pencatatan hasil ujian.
-   Rubrik penilaian.

### Phase 6 --- Penilaian dan finalisasi

-   Skema penilaian.
-   Bobot komponen.
-   Penilaian manual.
-   Kalkulasi nilai akhir.
-   Predikat.
-   Validasi kelengkapan.
-   Finalisasi dan koreksi nilai.

### Phase 7 --- Sertifikasi

-   Aturan kelayakan.
-   Evaluasi otomatis.
-   Antrian approval.
-   Persetujuan Admin.
-   Generate PDF.
-   Nomor sertifikat.
-   QR/verifikasi publik.
-   Revokasi dan penerbitan pengganti.

### Phase 8 --- Laporan, notifikasi, dan hardening

-   Dashboard baru.
-   Laporan akademik.
-   Notifikasi jadwal/tugas/nilai/sertifikat.
-   Hardening keamanan.
-   Backup/restore.
-   Monitoring dan logging.
-   UAT dan deployment produksi.

------------------------------------------------------------------------

# BAGIAN XIII --- PENGUJIAN DAN KRITERIA PENERIMAAN

## 45. Acceptance Criteria Utama

### 45.1 Kursus dan versi kurikulum

-   [ ] Admin dapat membuat kursus.
-   [ ] Admin dapat membuat beberapa versi kurikulum pada satu kursus.
-   [ ] Draft dapat diedit.
-   [ ] Versi Published dapat dipilih untuk kelas.
-   [ ] Ketika dipakai kelas, versi menjadi Locked.
-   [ ] Sistem menolak perubahan struktur versi Locked.
-   [ ] Pembuatan versi baru tidak mengubah versi lama.
-   [ ] Kelas lama tetap menampilkan kurikulum sesuai snapshot.

### 45.2 Kelas dan tentor

-   [ ] Admin dapat membuat kelas dari satu kursus dan satu versi
    kurikulum.
-   [ ] Kelas tidak dapat Ready/Ongoing tanpa satu tentor aktif.
-   [ ] Satu kelas tidak dapat memiliki dua tentor utama aktif.
-   [ ] Satu tentor dapat ditugaskan pada banyak kelas.
-   [ ] Tentor hanya dapat mengakses kelas yang ditugaskan.
-   [ ] Pergantian tentor menyimpan histori dan alasan.
-   [ ] Penonaktifan tentor tidak menghapus histori kelas.

### 45.3 Enrollment

-   [ ] Satu peserta dapat terdaftar pada banyak kelas.
-   [ ] Peserta tidak dapat memiliki enrollment ganda aktif pada kelas
    yang sama.
-   [ ] Enrollment selalu menunjuk kelas.
-   [ ] Kuota kelas divalidasi.
-   [ ] Enrollment withdrawn/cancelled tidak menghapus histori.
-   [ ] Peserta hanya melihat kelas miliknya.

### 45.4 Jadwal dan presensi

-   [ ] Admin dapat membuat jadwal.
-   [ ] Sesi pertemuan dapat dibuat dari jadwal.
-   [ ] Sesi menyimpan JP rencana dan aktual.
-   [ ] Tentor dapat mengisi presensi kelasnya.
-   [ ] Satu peserta hanya memiliki satu presensi utama per sesi.
-   [ ] Koreksi presensi menyimpan nilai lama, nilai baru, aktor, dan
    alasan.
-   [ ] Peserta hanya melihat presensinya sendiri.
-   [ ] Rekap persentase kehadiran sesuai aturan konfigurasi.

### 45.5 Tugas, ujian, dan penilaian

-   [ ] Tentor dapat membuat tugas untuk kelasnya.
-   [ ] Tugas dapat mewajibkan atau tidak mewajibkan upload file.
-   [ ] Peserta hanya dapat mengirim submission untuk enrollment
    sendiri.
-   [ ] Tentor dapat memberi nilai dan umpan balik.
-   [ ] Ujian dapat dicatat untuk kelas.
-   [ ] Penilaian manual memiliki komponen dan rubrik.
-   [ ] Bobot skema harus tepat 100%.
-   [ ] Nilai kosong tidak dianggap nol otomatis.
-   [ ] Nilai akhir tidak dapat difinalisasi jika komponen wajib belum
    lengkap, kecuali ada pengecualian resmi.
-   [ ] Perubahan nilai final tercatat.

### 45.6 Sertifikat

-   [ ] Sistem mengevaluasi kelayakan berdasarkan aturan kurikulum
    kelas.
-   [ ] Sistem menampilkan alasan ketika peserta belum eligible.
-   [ ] Eligible belum berarti sertifikat diterbitkan.
-   [ ] Hanya Admin yang dapat menyetujui.
-   [ ] Sertifikat baru diterbitkan setelah approval.
-   [ ] Sertifikat memiliki nomor unik.
-   [ ] PDF dan verifikasi publik tersedia.
-   [ ] Draft/rejected tidak dapat diverifikasi sebagai sertifikat
    valid.
-   [ ] Revokasi tidak menghapus histori sertifikat.

### 45.7 Keamanan dan regresi

-   [ ] Role guard tetap berjalan.
-   [ ] Ownership diperiksa di backend.
-   [ ] CSRF dan validasi input diterapkan.
-   [ ] File privat tidak dapat diakses tanpa otorisasi.
-   [ ] Snapshot dibatasi per role.
-   [ ] Audit log mencatat perubahan kritis.
-   [ ] Fitur lama v3.0 yang dipertahankan tidak mengalami regresi.

## 46. Skenario Uji Integrasi Kritis

1.  Buat kursus, versi kurikulum v1, dan kelas A.
2.  Pastikan kelas A mengunci v1.
3.  Buat versi v2 dari v1 dan ubah struktur v2.
4.  Pastikan kelas A tetap menggunakan v1.
5.  Buat kelas B menggunakan v2.
6.  Tetapkan Tentor X pada kelas A dan kelas B.
7.  Pastikan Tentor X dapat melihat dua kelas.
8.  Pastikan kelas A tidak dapat memiliki Tentor Y secara bersamaan.
9.  Ganti tentor kelas A ke Tentor Y dan pastikan histori tersimpan.
10. Daftarkan Peserta P pada kelas A dan kelas B.
11. Pastikan enrollment terpisah dan data akademik tidak tercampur.
12. Buat sesi kelas A, isi presensi, dan hitung kehadiran.
13. Buat tugas dan ujian, lakukan submission/penilaian.
14. Isi penilaian manual dan hitung nilai akhir.
15. Pastikan nilai belum lengkap tidak difinalisasi.
16. Finalisasi nilai setelah seluruh komponen lengkap.
17. Evaluasi kelayakan sertifikat.
18. Pastikan status eligible menunggu approval Admin.
19. Approve dan terbitkan sertifikat.
20. Verifikasi bahwa sertifikat memuat snapshot kelas, kursus, nilai,
    dan versi kurikulum yang benar.

------------------------------------------------------------------------

# BAGIAN XIV --- RISIKO DAN KEPUTUSAN YANG MASIH PERLU DIKONFIGURASI

## 47. Risiko Utama

  -----------------------------------------------------------------------
  Risiko                  Dampak                  Mitigasi
  ----------------------- ----------------------- -----------------------
  Versi kurikulum lama    Riwayat akademik tidak  Lock version, validasi
  berubah                 konsisten               backend, audit

  Enrollment tetap        Peserta antarangkatan   Migrasi ke enrollment
  langsung ke kursus      tercampur               berbasis kelas

  Satu tentor per kursus  Tentor tidak dapat      Pindahkan penugasan
  masih dipakai sebagai   ditata sesuai kelas     utama ke kelas
  model utama                                     

  Presensi tidak lengkap  Kelayakan sertifikat    Presensi per sesi,
                          salah                   laporan sesi kosong

  Nilai kosong dianggap   Nilai akhir tidak adil  Status penilaian
  nol                                             eksplisit

  File tugas dapat        Kebocoran dokumen       Private storage dan
  diakses publik          peserta                 authorization

  Perubahan nilai tanpa   Sengketa akademik       Finalisasi dan audit
  audit                                           before/after

  Snapshot API membuka    Kebocoran data lintas   Endpoint dan proyeksi
  seluruh data            role                    data per role

  Migrasi data lama salah Riwayat peserta salah   Audit dan kelas migrasi
  memetakan angkatan                              historis
  -----------------------------------------------------------------------

## 48. Parameter Akademik yang Harus Ditetapkan Admin

Parameter berikut harus menjadi pengaturan sistem atau template
akademik, bukan angka yang ditanam permanen di kode: - Durasi satu JP. -
Batas minimum kehadiran. - Kebijakan status izin/sakit terhadap
persentase kehadiran. - Rentang predikat A--E. - Nilai minimum
kelulusan. - Bobot tugas, ujian, dan manual. - Agregasi beberapa
tugas/ujian dalam satu komponen. - Toleransi keterlambatan submission. -
Jenis dan ukuran file yang diizinkan. - Kriteria wajib sertifikat. -
Pola nomor sertifikat. - Masa retensi data akademik.

------------------------------------------------------------------------

# LAMPIRAN A --- ARSIP PRD VERSI 3.0

Bagian berikut mempertahankan isi PRD v3.0 sebagai catatan perkembangan
sistem. Isinya tidak dihapus atau ditimpa. Jika terdapat aturan yang
berbeda dengan Bagian I--XIV, aturan Bagian I--XIV (v4.0) menjadi
spesifikasi terbaru, sedangkan Lampiran A tetap menjadi rekam historis
kondisi dan implementasi yang pernah didokumentasikan.

------------------------------------------------------------------------

# PRD: Learning Management System (LMS) ELIPS

**Versi:** 3.0\
**Tanggal:** 2026-11-04\
**Status:** Implemented (Dual-mode - PHP 8/MySQL 8 + fallback
`localStorage`); seluruh aturan alur kursus diterapkan di client dan
server, JavaScript dimodularisasi, audit trail P1 aktif, notifikasi
email P2 disimulasikan melalui outbox database, dan hardening P0 (guard
sesi, hash password, sanitasi materi) aktif\
**Log pengembangan:** `log.md` **ERD:** `erd.md` **Nama Aplikasi:**
ELIPS LMS

------------------------------------------------------------------------

## 1. Tujuan Proyek

Membangun sistem Learning Management System (LMS) berbasis web untuk
pelatihan dan kursus. Aplikasi memakai vanilla HTML, CSS, dan JavaScript
di sisi client, dengan backend PHP 8 + MySQL 8 sebagai penyimpanan utama
dan `localStorage` sebagai fallback demo saat API tidak tersedia.

------------------------------------------------------------------------

## 2. Target Pengguna

  ------------------------------------------------------------------------
  Role                     Deskripsi               Kebutuhan Utama
  ------------------------ ----------------------- -----------------------
  **Admin**                Pengelola sistem        Menambah kursus
                                                   (Draft), menugaskan 1
                                                   instruktur per kursus,
                                                   menambah pengguna,
                                                   menugaskan siswa ke
                                                   satu/beberapa kursus,
                                                   menyetujui sertifikat
                                                   Draft → Publish

  **Instruktur/Trainer**   Pengajar kursus         Login, mengelola
                                                   modul/materi pada
                                                   kursus yang diampu,
                                                   memantau progres,
                                                   memberi nilai akhir

  **Peserta/Student**      Peserta pelatihan       Mengakses materi dari
                                                   kursus yang ditugaskan,
                                                   menandai pelajaran
                                                   selesai, melihat
                                                   sertifikat yang sudah
                                                   Publish
  ------------------------------------------------------------------------

------------------------------------------------------------------------

## 3. Alur Sistem Kursus (Business Flow)

Alur berikut menjadi acuan utama untuk pengelolaan kursus, penugasan
instruktur dan siswa, penilaian, serta penerbitan sertifikat.

### 3.1 Ringkasan Alur

``` text
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

1.  **Admin menambah kursus baru** melalui `admin/courses.html`.
    -   Kursus baru dibuat dengan status **Draft**.
    -   Kursus Draft belum dapat diakses oleh siswa.
    -   Kursus baru wajib memiliki satu instruktur penanggung jawab.
2.  **Admin menambah akun instruktur baru** melalui `admin/users.html`.
    -   Role pengguna diatur sebagai **Instruktur**.
    -   Akun instruktur harus berstatus **Aktif** agar dapat masuk dan
        mengelola kursus.
3.  **Admin mengaitkan instruktur ke kursus Draft**.
    -   Relasi bersifat **1 kursus = 1 instruktur**
        (`course.instructorId`).
    -   Satu instruktur boleh mengampu lebih dari satu kursus.
    -   Jika kursus sudah memiliki instruktur, penugasan baru akan
        menggantikan instruktur sebelumnya.
4.  **Admin menambah akun siswa baru** melalui `admin/users.html` atau
    halaman `register.html`.
    -   Role pengguna adalah **Peserta**.
    -   Akun siswa harus berstatus **Aktif** agar dapat mengakses
        materi.
5.  **Admin mengaitkan siswa ke kursus** melalui
    `admin/enrollment.html`.
    -   Satu siswa boleh dikaitkan ke **beberapa kursus** dalam satu
        waktu, sesuai kursus yang diikuti.
    -   Satu kursus boleh memiliki banyak siswa.
    -   Relasi disimpan sebagai enrollment dengan status **Aktif**.
    -   Siswa hanya dapat membuka modul dan materi dari kursus yang
        ditugaskan kepadanya **dan** sudah berstatus **Published**.
6.  **Instruktur masuk ke sistem**.
    -   Instruktur hanya melihat kursus yang ditugaskan kepadanya.
    -   Instruktur tidak dapat mengubah kursus milik instruktur lain.
7.  **Instruktur membuat modul dan materi** melalui
    `instructor/manage-course.html`.
    -   Modul dan materi hanya dapat dibuat pada kursus yang diampu.
    -   Materi mendukung tipe teks, video, file, dan link eksternal.
8.  **Siswa mempelajari materi** melalui `learning.html`.
    -   Hanya kursus yang ditugaskan dan sudah Published yang dapat
        diakses.
    -   Materi dibuka secara berurutan; pelajaran berikutnya terkunci
        sampai pelajaran sebelumnya selesai.
    -   Progres belajar dicatat per pelajaran.
9.  **Di akhir kursus, instruktur memberi nilai** melalui
    `instructor/students.html`.
    -   Nilai berupa angka 0--100 dan otomatis dikonversi menjadi grade
        huruf (A--E).
    -   Instruktur dapat menambahkan catatan nilai.
10. **Nilai terintegrasi dengan sertifikat**.
    -   Saat nilai disimpan, sistem membuat atau memperbarui
        e-sertifikat peserta untuk kursus tersebut.
    -   E-sertifikat baru selalu berstatus **Draft**.
11. **Sertifikat Draft tidak ditampilkan ke siswa**.
    -   Halaman siswa hanya menampilkan e-sertifikat berstatus
        **Publish**.
    -   Sertifikat Draft tidak boleh muncul di daftar, tidak dapat
        dilihat, dan tidak dapat diverifikasi oleh siswa.
12. **Admin menyetujui sertifikat** melalui `admin/certificates.html`.
    -   Admin menekan **Setujui & Terbitkan** pada sertifikat Draft.
    -   Status berubah menjadi **Publish**, nomor sertifikat final dan
        tanggal persetujuan disimpan.
    -   Setelah Publish, sertifikat tampil di halaman siswa dan dapat
        diverifikasi publik.

### 3.3 Aturan Status

  -----------------------------------------------------------------------
  Entitas           Status            Makna             Transisi
  ----------------- ----------------- ----------------- -----------------
  **Kursus**        `draft`           Baru dibuat,      `draft` →
                                      belum dapat       `published` oleh
                                      diakses siswa     Admin

  **Kursus**        `published`       Aktif dan dapat   `published` →
                                      diakses siswa     `draft` oleh
                                      yang ditugaskan   Admin

  **Enrollment**    `active`          Siswa ditugaskan  `active` ↔
                                      dan dapat         `dihentikan`
                                      mengakses kursus  

  **Enrollment**    `dihentikan`      Akses siswa ke    `dihentikan` →
                                      kursus dihentikan `active`
                                      sementara         

  **Sertifikat**    `draft`           Sudah dinilai     `draft` →
                                      instruktur,       `publish`
                                      menunggu          
                                      persetujuan Admin 

  **Sertifikat**    `publish`         Disetujui Admin,  Final
                                      tampil di halaman 
                                      siswa, dapat      
                                      diverifikasi      

  **Pengguna**      `aktif`           Dapat login dan   `aktif` ↔
                                      menggunakan       `nonaktif`
                                      sistem            

  **Pengguna**      `nonaktif`        Tidak dapat login `nonaktif` →
                                                        `aktif`
  -----------------------------------------------------------------------

### 3.4 Matriks Hak Akses

  -------------------------------------------------------------------------
  Aksi                Admin             Instruktur        Siswa
  ------------------- ----------------- ----------------- -----------------
  Menambah/mengubah   ✅                ❌                ❌
  kursus                                                  

  Menugaskan          ✅                ❌                ❌
  instruktur ke                                           
  kursus                                                  

  Publikasi / menarik ✅                ❌                ❌
  kursus ke Draft                                         

  Menambah/mengubah   ✅                ❌                ❌
  pengguna                                                

  Menugaskan siswa ke ✅                ❌                ❌
  kursus                                                  

  Melihat kursus yang ✅ (semua)        ✅ (miliknya)     ❌
  diampu                                                  

  Membuat modul &     ❌                ✅ (kursus        ❌
  materi                                sendiri)          

  Memberi nilai       ❌                ✅ (kursus        ❌
                                        sendiri)          

  Melihat materi      ✅ (pratinjau)    ✅ (pratinjau)    ✅ (kursus
  kursus                                                  ditugaskan &
                                                          Published)

  Melihat sertifikat  ✅                ✅ (kursus        ❌
  Draft                                 sendiri)          

  Menyetujui          ✅                ❌                ❌
  sertifikat                                              

  Melihat sertifikat  ✅                ✅                ✅ (milik
  Publish                                                 sendiri)
  -------------------------------------------------------------------------

### 3.5 Status Implementasi Demo

  --------------------------------------------------------------------------------------------
  Langkah                 Status                  Catatan Implementasi
  ----------------------- ----------------------- --------------------------------------------
  Admin menambah kursus   ✅ Selesai              `ELIPS.createCourse` selalu memaksa status
  baru berstatus Draft                            `draft`; form tambah tidak lagi bisa memilih
                                                  Published

  Admin menambah akun     ✅ Selesai              `admin/users.html` - tambah pengguna role
  instruktur                                      Instruktur

  1 kursus = 1 instruktur ✅ Selesai              `course.instructorId`; penugasan ulang
                                                  menggantikan instruktur lama

  Admin menambah akun     ✅ Selesai              `admin/users.html` dan `register.html`
  siswa                                           

  1 siswa dapat dikaitkan ✅ Selesai              `admin/enrollment.html` - checkbox
  ke beberapa kursus                              multi-kursus

  Instruktur login dan    ✅ Selesai              `instructor/dashboard.html`,
  hanya melihat kursus                            `instructor/manage-course.html`
  sendiri                                         

  Instruktur membuat      ✅ Selesai              `instructor/manage-course.html`
  modul dan materi                                

  Siswa hanya mengakses   ✅ Selesai              `learning.html` memvalidasi enrollment,
  kursus yang ditugaskan                          status kursus, dan urutan materi

  Instruktur memberi      ✅ Selesai              `instructor/students.html`
  nilai di akhir kursus                           

  Nilai terintegrasi ke   ✅ Selesai              Nilai disimpan di `elips_grades`; Draft
  e-sertifikat Draft                              dibuat di `elips_certificates`

  Sertifikat Draft tidak  ✅ Selesai              `student/certificates.html` hanya membaca
  tampil di halaman siswa                         `ELIPS.getPublishedCertificatesByStudent`;
                                                  section Draft dihapus; dashboard siswa tidak
                                                  lagi memberi notifikasi Draft; status/ID
                                                  data lama dinormalkan dan tampilan
                                                  disegarkan otomatis saat data berubah dari
                                                  tab lain

  Admin menyetujui Draft  ✅ Selesai              `admin/certificates.html` memanggil
  menjadi Publish                                 `ELIPS.approveCertificate`; nomor final dan
                                                  tanggal approval disimpan

  Sertifikat Publish      ✅ Selesai              `student/certificates.html` - hanya section
  tampil di halaman siswa                         Sertifikat Terbit

  Publikasi kursus        ✅ Selesai              `ELIPS.setCourseStatus` menolak publikasi
  tervalidasi                                     tanpa instruktur aktif atau tanpa
                                                  modul/materi

  Penghapusan instruktur  ✅ Selesai              `ELIPS.deleteUser`/`updateUser` melepas
  mengembalikan kursus ke                         kursus dan memaksa status Draft
  Draft                                           

  CRUD terpusat di        ✅ Selesai              Seluruh CRUD pengguna, kursus, enrollment,
  JavaScript                                      modul, materi, nilai, dan sertifikat memakai
                                                  modul CRUD di `assets/js/` (lihat §4)

  Audit trail perubahan   ✅ Selesai              `assets/js/audit.js` mencatat aktor, aksi,
  kursus, nilai, dan                              entitas, waktu, dan detail; halaman
  sertifikat                                      `admin/audit.html` menyediakan filter,
                                                  ekspor CSV, dan pembersihan log

  Notifikasi email        ✅ Selesai              `assets/js/notifications.js` mencatat outbox
  simulasi                                        saat kursus dipublikasikan atau sertifikat
                                                  terbit; `admin/notifications.html`
                                                  menyediakan filter, ekspor CSV, dan
                                                  pembersihan outbox

  Backend PHP + MySQL     ✅ Selesai              `api/` menyediakan autentikasi sesi, CRUD,
                                                  snapshot, audit, notifikasi, dan seeding
                                                  data demo

  Dual-mode client        ✅ Selesai              `assets/js/api.js` memakai API saat tersedia
                                                  dan otomatis fallback ke `localStorage`
  --------------------------------------------------------------------------------------------

------------------------------------------------------------------------

## 4. Arsitektur (Dual-Mode: Server PHP/MySQL + Demo `localStorage`)

Aplikasi berjalan dalam dua mode yang memakai UI dan aturan bisnis yang
sama:

-   **Mode server (utama):** halaman disajikan melalui HTTP/HTTPS dan
    `assets/js/config.js` menyediakan `window.ELIPS_CONFIG.apiBase`.
    `assets/js/api.js` mengambil snapshot awal dari API, menyimpan cache
    sinkron di memori, lalu setiap operasi tulis memanggil aksi API.
    Backend PHP memvalidasi sesi, role, kepemilikan kursus, status, dan
    aturan bisnis sebelum menulis ke MySQL.
-   **Mode lokal (fallback):** jika halaman dibuka lewat `file://` atau
    backend tidak tersedia, aplikasi otomatis memakai `localStorage`
    seperti demo lama. Mode ini dipertahankan agar demo tetap dapat
    dijalankan tanpa server dan seluruh regression test lama tetap
    valid.

### 4.1 Frontend

-   **Frontend:** HTML5, CSS3 (custom dengan variabel CSS), Vanilla
    JavaScript
-   **Business Logic & CRUD Layer:** JavaScript modular di `assets/js/`:
    -   `core.js` - namespace, storage helper, result object, validasi
        dasar;
    -   `config.js` - menentukan `apiBase` relatif terhadap halaman;
    -   `api.js` - jembatan dual-mode, cache snapshot, `ELIPS.api.call`,
        dan `ELIPS.api.refresh`;
    -   `data.js` - getter/setter seluruh koleksi data (dari cache pada
        mode server);
    -   `seed.js` - data demo awal dan migrasi data lama (dilewati pada
        mode server);
    -   `sync.js` - sinkronisasi nama instruktur, kepemilikan konten,
        dan counter kursus;
    -   `progress.js` - struktur kursus, progres, dan ringkasan
        siswa/instruktur;
    -   `auth.js` - session, role guard, dan logout (memanggil
        `auth.logout` pada mode server);
    -   `audit.js` - audit trail; pada mode server pencatatan dilakukan
        backend;
    -   `notifications.js` - outbox notifikasi; pada mode server
        notifikasi dibuat backend;
    -   `crud-users.js`, `crud-courses.js`, `crud-enrollments.js`,
        `crud-content.js`, `crud-grades.js` - CRUD dan aturan bisnis per
        domain; pada mode server fungsi ini meneruskan aksi ke API;
    -   `utils.js` - helper format, escape HTML, dan toast;
    -   `ui.js` - perilaku UI bersama (navigasi, sidebar, katalog,
        login, logout).
    -   Halaman hanya menangani rendering dan interaksi UI.
    -   Setiap fungsi CRUD mengembalikan `{ ok, message, data }` agar
        pesan sukses/gagal konsisten.
    -   Modul dimuat berurutan:
        `config → core → api → utils → data → seed → sync → progress → auth → audit → notifications → crud-* → ui`.

### 4.2 Backend PHP/MySQL

Backend berada di folder `api/` dan terdiri dari front controller,
konfigurasi, koneksi, helper, snapshot, aksi bisnis, skema, dan
installer. Daftar file lengkap ada di §5 bagian **Backend (`api/`)**;
detail tabel database ada di `erd.md` §16.

-   **Autentikasi:** sesi PHP native (`$_SESSION`) dengan cookie
    `HttpOnly`, `SameSite=Lax`; password disimpan dengan
    `password_hash()` dan diverifikasi dengan `password_verify()`.
-   **Otorisasi:** setiap aksi memeriksa role dan kepemilikan di server.
    Contoh: instruktur hanya boleh mengelola kursus yang diampu; peserta
    hanya boleh menandai progres miliknya; hanya Admin yang boleh
    publish kursus, menyetujui sertifikat, atau membersihkan
    audit/notifikasi.
-   **Snapshot:** respons tulis menyertakan snapshot terbaru dan data
    sesi, sehingga cache client selalu identik dengan database tanpa
    reload halaman.
-   **Sanitasi:** konten materi tipe teks disanitasi di server dengan
    allowlist tag/atribut sebelum disimpan.

### 4.3 Penyimpanan Data

-   **MySQL (mode server):** tabel `users`, `courses`, `modules`,
    `lessons`, `enrollments`, `grades`, `certificates`,
    `completed_lessons`, `audit_logs`, `notifications` (lihat `erd.md`).
-   **`localStorage` (mode lokal/fallback):** key `elips_users`,
    `elips_courses`, `elips_enrollments`, `elips_modules`,
    `elips_lessons`, `elips_grades`, `elips_certificates`,
    `elips_completedLessons`, `elips_auditLogs`, `elips_notifications`,
    dan `elips_session`.
-   **Styling:** Custom CSS (`assets/css/style.css`), Bootstrap Icons,
    Google Fonts (Inter)
-   **Struktur Folder:** Berbasis role (`admin/`, `instructor/`,
    `student/`)

------------------------------------------------------------------------

## 5. Struktur File yang Telah Dikembangkan

### Halaman Publik

  ---------------------------------------------------------------------------
  File                        Status                  Deskripsi
  --------------------------- ----------------------- -----------------------
  `index.html`                ✅ Selesai              Beranda dengan daftar
                                                      kursus

  `course-detail.html`        ✅ Selesai              Detail kursus, tombol
                                                      enrolment dinamis
                                                      (berubah menjadi "Sudah
                                                      Terdaftar - Lanjutkan
                                                      Belajar")

  `login.html`                ✅ Selesai              Halaman login dengan
                                                      validasi email +
                                                      password; mode server
                                                      diverifikasi PHP
                                                      (`password_verify`),
                                                      mode lokal terhadap
                                                      `elips_users`, lalu
                                                      redirect berbasis role

  `register.html`             ✅ Selesai              Halaman registrasi

  `verify-certificate.html`   ✅ Selesai              Verifikasi keaslian
                                                      sertifikat publik

  `certificate-view.html`     ✅ Selesai              Tampilan sertifikat
                                                      dinamis dari URL params
                                                      dan snapshot/cache data

  `learning.html`             ✅ Selesai              Halaman belajar dengan
                                                      tracking pelajaran
                                                      selesai; mendukung mode
                                                      pratinjau untuk
                                                      instruktur/admin tanpa
                                                      mengubah progres
                                                      peserta
  ---------------------------------------------------------------------------

### Role Admin (`admin/`)

  --------------------------------------------------------------------------------------------
  File                         Status                  Deskripsi
  ---------------------------- ----------------------- ---------------------------------------
  `admin/dashboard.html`       ✅ Selesai              Dashboard admin dengan ringkasan
                                                       statistik

  `admin/users.html`           ✅ Selesai              Tambah pengguna diawali modal pilihan
                                                       role Admin/Instruktur/Peserta,
                                                       edit/hapus pengguna, filter role &
                                                       pencarian, serta penugasan instruktur
                                                       ke kursus

  `admin/courses.html`         ✅ Selesai              Tambah/edit kursus, tugaskan 1
                                                       instruktur per kursus, publikasi/Draft,
                                                       filter status & pencarian

  `admin/enrollment.html`      ✅ Selesai              Penugasan siswa ke satu atau beberapa
                                                       kursus, filter kursus/status,
                                                       aktif/nonaktifkan penugasan

  `admin/certificates.html`    ✅ Selesai              Persetujuan e-sertifikat: Draft →
                                                       Publish, filter status, dan tampilan
                                                       nomor/tanggal persetujuan

  `admin/notifications.html`   ✅ Selesai              Outbox notifikasi email simulasi:
                                                       statistik, filter
                                                       jenis/penerima/tanggal/pencarian,
                                                       detail isi, ekspor CSV, dan pembersihan
                                                       outbox

  `admin/audit.html`           ✅ Selesai              Audit trail: statistik aktivitas,
                                                       filter
                                                       aktor/aksi/entitas/tanggal/pencarian,
                                                       ekspor CSV, dan pembersihan log

  `admin/reports.html`         ✅ Selesai              Laporan progres peserta per kursus,
                                                       statistik per kursus, export PDF/Excel
                                                       (UI)

  `admin/settings.html`        ✅ Selesai              Pengaturan umum, pengaturan sertifikat,
                                                       pengaturan pembelajaran
  --------------------------------------------------------------------------------------------

### Role Instruktur (`instructor/`)

  ------------------------------------------------------------------------------------------------
  File                              Status                  Deskripsi
  --------------------------------- ----------------------- --------------------------------------
  `instructor/dashboard.html`       ✅ Selesai              Dashboard dinamis: statistik
                                                            kursus/peserta/pelajaran/sertifikat,
                                                            notifikasi draft & penilaian tertunda,
                                                            daftar kursus, dan aktivitas peserta
                                                            terbaru

  `instructor/manage-course.html`   ✅ Selesai              Kelola struktur kursus:
                                                            tambah/ubah/hapus modul dan materi
                                                            (teks, video, file, link) dengan
                                                            validasi kepemilikan kursus

  `instructor/students.html`        ✅ Selesai              Daftar peserta per kursus dengan
                                                            filter status/pencarian, progres,
                                                            riwayat nilai, serta modal penilaian
                                                            yang otomatis membuat e-sertifikat
                                                            Draft

  `instructor/certificates.html`    ✅ Selesai              Monitoring e-sertifikat peserta
                                                            (Draft/Terbit), filter kursus &
                                                            status, pengajuan sertifikat untuk
                                                            peserta yang sudah dinilai namun belum
                                                            memiliki sertifikat

  `instructor/profile.html`         ✅ Selesai              Profil instruktur: ringkasan
                                                            statistik, informasi akun, pembaruan
                                                            nama/no. telepon, dan sinkronisasi
                                                            nama pada kursus yang diampu
  ------------------------------------------------------------------------------------------------

### Role Peserta (`student/`)

  -----------------------------------------------------------------------------
  File                          Status                  Deskripsi
  ----------------------------- ----------------------- -----------------------
  `student/dashboard.html`      ✅ Selesai              Dashboard peserta
                                                        dengan kursus yang
                                                        diikuti, progres,
                                                        nilai, status
                                                        sertifikat, dan
                                                        ringkasan statistik

  `student/courses.html`        ✅ Selesai              Daftar seluruh kursus
                                                        peserta dengan filter
                                                        status, pencarian,
                                                        progres, nilai, dan
                                                        akses belajar

  `student/certificates.html`   ✅ Selesai              Daftar sertifikat
                                                        peserta; hanya
                                                        sertifikat **Publish**
                                                        yang ditampilkan, Draft
                                                        sepenuhnya
                                                        disembunyikan

  `student/profile.html`        ✅ Selesai              Profil peserta dan
                                                        pembaruan nama/no.
                                                        telepon
  -----------------------------------------------------------------------------

### Assets

  ---------------------------------------------------------------------------------
  File                              Status                  Deskripsi
  --------------------------------- ----------------------- -----------------------
  `assets/css/style.css`            ✅ Selesai              Styling utama untuk
                                                            seluruh aplikasi

  `assets/js/config.js`             ✅ Selesai              Menentukan `apiBase`
                                                            relatif terhadap
                                                            halaman (dilewati pada
                                                            bundle uji JSDOM)

  `assets/js/api.js`                ✅ Selesai              Jembatan dual-mode:
                                                            bootstrap snapshot,
                                                            cache sinkron,
                                                            panggilan aksi API, dan
                                                            fallback lokal

  `assets/js/core.js`               ✅ Selesai              Namespace, storage
                                                            helper, result object,
                                                            dan validasi dasar

  `assets/js/utils.js`              ✅ Selesai              Helper grade, nomor
                                                            sertifikat, escape
                                                            HTML, format tanggal,
                                                            toast

  `assets/js/data.js`               ✅ Selesai              Getter/setter seluruh
                                                            koleksi data

  `assets/js/seed.js`               ✅ Selesai              Data demo awal dan
                                                            normalisasi/migrasi
                                                            data lama

  `assets/js/sync.js`               ✅ Selesai              Sinkronisasi nama
                                                            instruktur, kepemilikan
                                                            konten, counter kursus

  `assets/js/progress.js`           ✅ Selesai              Struktur kursus,
                                                            progres belajar,
                                                            overview
                                                            siswa/instruktur

  `assets/js/auth.js`               ✅ Selesai              Session, user aktif,
                                                            redirect, dan role
                                                            guard

  `assets/js/audit.js`              ✅ Selesai              Pencatatan audit trail,
                                                            filter log, daftar
                                                            aktor, dan pembersihan
                                                            log

  `assets/js/notifications.js`      ✅ Selesai              Outbox notifikasi email
                                                            simulasi, filter,
                                                            daftar penerima, dan
                                                            pembersihan outbox

  `assets/js/crud-users.js`         ✅ Selesai              CRUD pengguna dan
                                                            penugasan instruktur

  `assets/js/crud-courses.js`       ✅ Selesai              CRUD kursus dan aturan
                                                            Draft/Publish

  `assets/js/crud-enrollments.js`   ✅ Selesai              CRUD penugasan peserta
                                                            ke kursus

  `assets/js/crud-content.js`       ✅ Selesai              CRUD modul dan materi
                                                            dengan validasi
                                                            kepemilikan

  `assets/js/crud-grades.js`        ✅ Selesai              Input nilai dan alur
                                                            sertifikat Draft →
                                                            Publish

  `assets/js/ui.js`                 ✅ Selesai              Perilaku UI bersama:
                                                            navigasi, sidebar,
                                                            katalog, login, logout
  ---------------------------------------------------------------------------------

### Backend (`api/`)

  -----------------------------------------------------------------------
  File                    Status                  Deskripsi
  ----------------------- ----------------------- -----------------------
  `api/index.php`         ✅ Selesai              Front controller API
                                                  dan pengaturan sesi PHP

  `api/config.php`        ✅ Selesai              Konfigurasi database
                                                  (override via
                                                  environment variable)

  `api/db.php`            ✅ Selesai              Koneksi PDO MySQL dan
                                                  timezone UTC

  `api/helpers.php`       ✅ Selesai              Sesi, otorisasi,
                                                  serializer, sanitasi
                                                  HTML, audit, dan
                                                  notifikasi

  `api/snapshot.php`      ✅ Selesai              Snapshot koleksi untuk
                                                  cache client

  `api/actions.php`       ✅ Selesai              Aksi bisnis dan
                                                  validasi
                                                  role/kepemilikan di
                                                  server

  `api/schema.sql`        ✅ Selesai              Skema MySQL 8 dan
                                                  foreign key

  `api/install.php`       ✅ Selesai              Installer/seeder data
                                                  demo (`--fresh` untuk
                                                  reset)
  -----------------------------------------------------------------------

------------------------------------------------------------------------

## 6. Fitur yang Telah Diimplementasikan

### 6.1 Autentikasi & Role-Based Access

  ------------------------------------------------------------------------------------
  Fitur                   Status                  Keterangan
  ----------------------- ----------------------- ------------------------------------
  Login form              ✅ Selesai              Form login di `login.html`, email +
                                                  password diverifikasi melalui
                                                  `ELIPS.authenticateUser()`; pada
                                                  mode server verifikasi dilakukan PHP
                                                  (`password_verify`) dan sesi
                                                  disimpan di `$_SESSION`. Akun demo
                                                  memakai password `elips123`

  Role redirect           ✅ Selesai              `admin` → `admin/dashboard.html`,
  berdasarkan role akun                           `instruktur` →
                                                  `instructor/dashboard.html`,
                                                  `peserta` → `student/dashboard.html`

  Role guard halaman      ✅ Selesai              Halaman admin memanggil
                                                  `ELIPS.requireRole('admin')`;
                                                  halaman instruktur memanggil
                                                  `ELIPS.requireRole('instruktur')`;
                                                  halaman peserta memeriksa sesi/role.
                                                  `getCurrentUser()` hanya membaca
                                                  sesi, tanpa fallback path. API juga
                                                  menolak aksi yang tidak sesuai role

  Password akun           ✅ Selesai              Password wajib minimal 8 karakter
                                                  saat Admin membuat
                                                  pengguna/registrasi; pada mode
                                                  server disimpan sebagai hash
                                                  `password_hash()` dan tidak pernah
                                                  dikirim ke client

  Sanitasi materi teks    ✅ Selesai              `ELIPS.sanitizeHtml()` pada client
                                                  dan `elips_sanitize_html()` pada
                                                  server memakai allowlist tag/atribut
                                                  untuk mencegah stored XSS

  Struktur folder per     ✅ Selesai              Semua halaman role dipindahkan ke
  role                                            folder terpisah
  ------------------------------------------------------------------------------------

### 6.2 Manajemen Kursus

  -----------------------------------------------------------------------------------
  Fitur                   Status                  Keterangan
  ----------------------- ----------------------- -----------------------------------
  Daftar kursus (beranda) ✅ Selesai              Ditampilkan di `index.html` (hanya
                                                  kursus Publish)

  Detail kursus           ✅ Selesai              `course-detail.html`

  Tambah/edit kursus      ✅ Selesai              `admin/courses.html` - kursus baru
  (Admin)                                         berstatus Draft, publikasi
                                                  dilakukan Admin

  Penugasan instruktur    ✅ Selesai              Satu kursus hanya memiliki satu
  (Admin)                                         instruktur; dapat diubah melalui
                                                  form kursus atau menu pengguna

  Kelola kursus           ✅ Selesai              `instructor/manage-course.html` -
  (Instruktur)                                    daftar kursus yang diampu, deep
                                                  link `?course=id`

  CRUD modul & materi     ✅ Selesai              Tambah/ubah/hapus modul dan
  (Instruktur)                                    pelajaran, tipe konten
                                                  teks/video/file/link, validasi
                                                  kepemilikan kursus, sinkronisasi
                                                  jumlah modul/pelajaran pada data
                                                  kursus, serta sanitasi HTML konten
                                                  teks
  -----------------------------------------------------------------------------------

### 6.3 Enrolment & Progres Belajar

  ----------------------------------------------------------------------------
  Fitur                   Status                  Keterangan
  ----------------------- ----------------------- ----------------------------
  Penugasan siswa ke      ✅ Selesai              Dilakukan Admin melalui
  kursus                                          `admin/enrollment.html`,
                                                  disimpan di
                                                  `elips_enrollments`

  Satu siswa ke beberapa  ✅ Selesai              Form penugasan mendukung
  kursus                                          pilihan multi-kursus per
                                                  siswa

  Akses kursus oleh siswa ✅ Selesai              Siswa hanya dapat membuka
                                                  kursus yang ditugaskan dan
                                                  berstatus Published

  Status enrolment        ✅ Selesai              Tombol di
  dinamis                                         `course-detail.html` berubah
                                                  menjadi "Lanjutkan Belajar"
                                                  bila sudah ditugaskan

  Halaman belajar         ✅ Selesai              `learning.html` dengan
                                                  sidebar modul/lesson dan
                                                  penguncian materi berurutan

  Tandai pelajaran        ✅ Selesai              Mode server: tabel
  selesai                                         `completed_lessons`; mode
                                                  lokal: `localStorage`
                                                  (`elips_completedLessons`)

  Tracking progres        ✅ Selesai              Progres dihitung berdasarkan
                                                  pelajaran selesai

  Kelola enrolment        ✅ Selesai              `admin/enrollment.html` -
  (Admin)                                         tambah penugasan, filter,
                                                  aktif/nonaktifkan, hapus
  ----------------------------------------------------------------------------

### 6.4 Sertifikat

  --------------------------------------------------------------------------------------
  Fitur                   Status                  Keterangan
  ----------------------- ----------------------- --------------------------------------
  Alur e-sertifikat       ✅ Selesai              Instruktur memberi nilai →
                                                  e-sertifikat otomatis dibuat berstatus
                                                  **Draft** → Admin menyetujui di
                                                  `admin/certificates.html` → status
                                                  menjadi **Publish**

  Integrasi nilai →       ✅ Selesai              Nilai/grade dari tabel `grades` (atau
  sertifikat                                      `elips_grades` pada mode lokal)
                                                  disalin ke e-sertifikat saat penilaian
                                                  disimpan

  Sertifikat Draft tidak  ✅ Selesai              `student/certificates.html` memakai
  tampil ke siswa                                 `getPublishedCertificatesByStudent`;
                                                  Draft tidak dirender dan notifikasinya
                                                  dihapus dari dashboard

  Sertifikat peserta      ✅ Selesai              `instructor/certificates.html`
  (Instruktur)                                    menampilkan Draft/Publish, filter
                                                  kursus & status, serta pengajuan Draft
                                                  untuk peserta yang sudah dinilai

  Tampilan sertifikat     ✅ Selesai              `certificate-view.html` menampilkan
                                                  sertifikat dari URL params dan data
                                                  snapshot/cache

  Verifikasi sertifikat   ✅ Selesai              `verify-certificate.html` - hanya
  publik                                          sertifikat Publish yang dapat
                                                  diverifikasi

  Kelola sertifikat       ✅ Selesai              `admin/certificates.html` -
  (Admin)                                         persetujuan Draft menjadi Publish
  --------------------------------------------------------------------------------------

### 6.5 Dashboard & Pelaporan

  --------------------------------------------------------------------------------------
  Fitur                   Status                  Keterangan
  ----------------------- ----------------------- --------------------------------------
  Dashboard Admin         ✅ Selesai              Ringkasan pengguna, kursus, enrolment

  Dashboard Instruktur    ✅ Selesai              Statistik
                                                  kursus/peserta/pelajaran/sertifikat,
                                                  notifikasi draft & penilaian tertunda,
                                                  tabel kursus yang diampu, aktivitas
                                                  peserta terbaru

  Dashboard Peserta       ✅ Selesai              Kursus diikuti, progres, akses cepat
                                                  ke sertifikat

  Peserta & Penilaian     ✅ Selesai              Filter kursus/status/pencarian, modal
  (Instruktur)                                    penilaian dengan peringatan progres,
                                                  catatan nilai, dan pembuatan
                                                  e-sertifikat Draft

  Profil Instruktur       ✅ Selesai              Ringkasan kursus, pembaruan data akun,
                                                  sinkronisasi nama instruktur ke kursus

  Laporan (Admin)         ✅ Selesai              `admin/reports.html` - progres per
                                                  peserta, statistik per kursus

  Pengaturan (Admin)      ✅ Selesai              `admin/settings.html` - pengaturan
                                                  umum, sertifikat, pembelajaran
  --------------------------------------------------------------------------------------

### 6.6 Manajemen Pengguna

  ----------------------------------------------------------------------------------
  Fitur                   Status                  Keterangan
  ----------------------- ----------------------- ----------------------------------
  Daftar pengguna         ✅ Selesai              `admin/users.html`

  Tambah/edit/hapus       ✅ Selesai              Form pengguna dengan nama, email,
  pengguna                                        telepon, role, dan status

  Pilihan role saat       ✅ Selesai              Klik **Tambah Pengguna** membuka
  tambah pengguna                                 modal pertama berisi 3 tombol:
                                                  Admin, Instruktur, dan Peserta;
                                                  role terpilih langsung mengisi
                                                  form

  Penugasan instruktur ke ✅ Selesai              Modal penugasan pada
  kursus                                          `admin/users.html`; satu kursus
                                                  hanya punya satu instruktur

  Filter role & pencarian ✅ Selesai              Filter
                                                  Semua/Admin/Instruktur/Peserta +
                                                  search

  Registrasi mandiri      ✅ Selesai              `register.html` membuat akun role
  peserta                                         Peserta, lalu diarahkan ke
                                                  dashboard siswa
  ----------------------------------------------------------------------------------

### 6.7 Audit Trail

  -----------------------------------------------------------------------
  Fitur                   Status                  Keterangan
  ----------------------- ----------------------- -----------------------
  Pencatatan perubahan    ✅ Selesai              Create, update, delete,
  kursus                                          publish, dan unpublish
                                                  kursus tercatat di
                                                  `elips_auditLogs`

  Pencatatan nilai        ✅ Selesai              Setiap penyimpanan
                                                  nilai mencatat aktor,
                                                  skor, grade, catatan,
                                                  peserta, dan kursus

  Pencatatan status       ✅ Selesai              Pengajuan Draft dan
  sertifikat                                      persetujuan Draft →
                                                  Publish tercatat

  Pencatatan domain lain  ✅ Selesai              Pengguna, penugasan
                                                  peserta, modul, dan
                                                  materi juga tercatat

  Resolusi aktor          ✅ Selesai              Prioritas: `actorId`
                                                  eksplisit → user sesi →
                                                  "Sistem"; tidak memakai
                                                  fallback berbasis path
                                                  halaman

  Filter & ekspor         ✅ Selesai              `admin/audit.html`
                                                  memfilter berdasarkan
                                                  aktor, aksi, entitas,
                                                  tanggal, pencarian, dan
                                                  limit; dapat diekspor
                                                  ke CSV

  Batas penyimpanan       ✅ Selesai              Maksimal 1000 entri
                                                  terbaru disimpan;
                                                  backend memangkas tabel
                                                  `audit_logs`, mode
                                                  lokal membatasi
                                                  `localStorage`
  -----------------------------------------------------------------------

### 6.8 Notifikasi Email (Simulasi)

  ----------------------------------------------------------------------------
  Fitur                   Status                  Keterangan
  ----------------------- ----------------------- ----------------------------
  Notifikasi publikasi    ✅ Selesai              Saat kursus berhasil
  kursus                                          Published, outbox mencatat
                                                  email untuk peserta aktif
                                                  yang terdaftar dan
                                                  instruktur aktif pengampu

  Notifikasi sertifikat   ✅ Selesai              Saat Admin menyetujui
  terbit                                          sertifikat Draft → Publish,
                                                  outbox mencatat email untuk
                                                  peserta

  Outbox notifikasi       ✅ Selesai              `admin/notifications.html`
  (Admin)                                         menampilkan waktu, penerima,
                                                  jenis, subjek, isi, dan
                                                  status simulasi

  Filter & ekspor         ✅ Selesai              Filter jenis, penerima,
                                                  rentang tanggal, pencarian,
                                                  dan limit; hasil dapat
                                                  diekspor ke CSV

  Batas penyimpanan       ✅ Selesai              Maksimal 1000 notifikasi
                                                  terbaru disimpan di
                                                  `elips_notifications`

  Pengiriman email        ⏳ Belum                Mode server menyimpan outbox
  sungguhan                                       di tabel `notifications`;
                                                  pengiriman nyata memerlukan
                                                  SMTP/email service di
                                                  produksi
  ----------------------------------------------------------------------------

------------------------------------------------------------------------

## 7. Logika Bisnis Penting

### Login Redirect

``` javascript
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

-   Halaman admin memanggil `ELIPS.requireRole('admin')`; halaman
    instruktur memanggil `ELIPS.requireRole('instruktur')`; halaman
    peserta memeriksa sesi dan role sebelum render.
-   Bila belum ada sesi, pengguna diarahkan ke `login.html`.
-   Bila role tidak sesuai, pengguna diarahkan ke dashboard sesuai role
    akunnya.
-   `getCurrentUser()` hanya mengembalikan user dari `elips_session`;
    tidak ada lagi fallback demo berdasarkan path halaman.

### Keamanan Konten Materi

-   Konten `lesson.content` bertipe `text` dianggap tidak tepercaya.
-   `ELIPS.sanitizeHtml()` menghapus `script`, `iframe`, event handler
    (`on*`), dan URL berbahaya (`javascript:`, `data:`, `vbscript:`),
    serta hanya mengizinkan tag/atribut aman.
-   Sanitasi dilakukan saat menyimpan materi (create/update), saat
    migrasi data lama, dan saat render di `learning.html` (defense in
    depth).

### Enrolment

-   Data disimpan di tabel `enrollments` (mode server) atau
    `localStorage` key `elips_enrollments` (mode lokal), berupa array of
    objects.
-   Penugasan siswa ke kursus hanya dilakukan oleh Admin melalui
    `admin/enrollment.html`.
-   Satu siswa dapat memiliki banyak enrollment (satu baris per kursus);
    satu kursus dapat memiliki banyak siswa.
-   Akses siswa divalidasi di `course-detail.html` dan `learning.html`:
    -   enrollment harus ada dan berstatus `active`;
    -   kursus harus berstatus `published`;
    -   siswa hanya melihat modul/materi dari kursus yang ditugaskan.
-   Enrollment berstatus `dihentikan` mencabut akses siswa tanpa
    menghapus riwayat progres dan nilai.

### Progres Belajar

-   Pelajaran selesai disimpan di tabel `completed_lessons` (mode
    server) atau `localStorage` key `elips_completedLessons` (mode
    lokal)
-   Progres dihitung berdasarkan jumlah pelajaran selesai vs total
    pelajaran
-   Materi terkunci sampai pelajaran sebelumnya selesai
    (instruktur/admin dapat melewati saat pratinjau)

### Penilaian & Sertifikat

-   Nilai disimpan di tabel `grades` (mode server) atau `localStorage`
    key `elips_grades` (mode lokal), dan hurufnya dihitung otomatis
    (A--E).
-   Instruktur hanya dapat menilai peserta pada kursus yang diampu.
-   Saat instruktur menyimpan nilai, sistem membuat/memperbarui
    e-sertifikat dengan status **Draft** dan menyalin nilai/grade ke
    sertifikat.
-   Sertifikat **Draft**:
    -   hanya terlihat oleh Admin dan Instruktur pengampu;
    -   **tidak ditampilkan** di halaman siswa;
    -   belum dapat dilihat melalui `certificate-view.html` maupun
        diverifikasi melalui `verify-certificate.html`.
-   Admin menyetujui Draft di `admin/certificates.html`; status berubah
    menjadi **Publish**, `approvedByAdminId` dan `approvedAt` diisi, dan
    nomor sertifikat final disimpan.
-   Sertifikat **Publish**:
    -   tampil di `student/certificates.html`;
    -   dapat dilihat di `certificate-view.html`;
    -   dapat diverifikasi publik di `verify-certificate.html`.

### Audit Trail

-   Pada mode server, setiap operasi bisnis yang berhasil dicatat
    backend ke tabel `audit_logs`; pada mode lokal ditulis ke
    `localStorage` key `elips_auditLogs` (client tidak menulis duplikat
    di mode server).
-   Entri audit menyimpan `action`, `entity`, `entityId`, `entityLabel`,
    `actorId`, `actorName`, `actorRole`, `detail`, dan `createdAt`.
-   Resolusi aktor: `actorId` eksplisit dari pemanggil → user sesi
    (`elips_session`) → "Sistem". Fallback `getCurrentUser()` tidak
    dipakai agar tidak salah atribusi saat tidak ada sesi.
-   Operasi yang gagal (validasi ditolak) tidak dicatat.
-   Aksi yang dicatat: `create`, `update`, `delete`, `publish`,
    `unpublish`, `grade`, `submit`, `approve`, `assign`, dan `status`.
-   Entitas yang dicatat: `course`, `grade`, `certificate`, `user`,
    `enrollment`, `module`, dan `lesson`.
-   Log diurutkan terbaru lebih dulu, dibatasi 1000 entri, dan dapat
    difilter berdasarkan aktor, aksi, entitas, rentang tanggal,
    pencarian, serta limit.
-   Admin dapat mengekspor hasil filter ke CSV dan membersihkan seluruh
    log dari `admin/audit.html`.

### Notifikasi Email (Simulasi)

-   Pada mode server, notifikasi disimpan backend di tabel
    `notifications`; pada mode fallback disimpan di `localStorage` key
    `elips_notifications`. Pada kedua mode email **tidak benar-benar
    dikirim** (`channel: 'email'`, `status: 'sent'`, `simulated: true`).
-   Notifikasi publikasi kursus dibuat setelah `setCourseStatus`
    berhasil mengubah status menjadi `published`:
    -   penerima: peserta dengan enrollment `active` pada kursus
        tersebut dan akun berstatus `aktif`;
    -   ditambah instruktur pengampu yang berstatus `aktif`;
    -   penerima duplikat dihindari.
-   Notifikasi sertifikat terbit dibuat setelah `approveCertificate`
    berhasil mengubah status Draft → Publish; penerimanya adalah peserta
    pemilik sertifikat.
-   Operasi yang gagal (publikasi ditolak atau approval ditolak) tidak
    membuat notifikasi.
-   Outbox diurutkan terbaru lebih dulu, dibatasi 1000 entri, dan dapat
    difilter berdasarkan jenis, penerima, rentang tanggal, pencarian,
    serta limit.
-   Admin dapat mengekspor hasil filter ke CSV dan membersihkan seluruh
    outbox dari `admin/notifications.html`.

------------------------------------------------------------------------

## 8. Testing Checklist

  ------------------------------------------------------------------------------------
  Skenario                  Status                  Catatan
  ------------------------- ----------------------- ----------------------------------
  Login redirect            ✅ Lulus uji            Validasi email + password terhadap
  berdasarkan role akun                             `elips_users`, redirect
                                                    admin/instruktur/peserta

  Password salah / kosong   ✅ Lulus uji            `authenticateUser()` menolak
  ditolak                                           kredensial tidak valid dan akun
                                                    nonaktif

  Halaman role tanpa sesi   ✅ Lulus uji            `getCurrentUser()` null tanpa
  dialihkan ke login                                sesi; halaman admin memakai
                                                    `requireRole('admin')`

  Sanitasi HTML materi      ✅ Lulus uji            `script`, event handler, dan URL
                                                    `javascript:` dibuang; markup aman
                                                    dipertahankan

  Admin menambah kursus     ✅ Lulus uji            Kursus baru tersimpan dan
  baru                                              berstatus Draft

  Admin menambah instruktur ✅ Lulus uji            Akun instruktur aktif dapat
                                                    dipilih pada form kursus

  Modal pilih role tambah   ✅ Lulus uji            Modal pertama menampilkan 3 tombol
  pengguna                                          role dan mempreset role pada form
                                                    pengguna

  Penugasan 1 kursus = 1    ✅ Lulus uji            Penugasan ulang menggantikan
  instruktur                                        instruktur sebelumnya

  Admin menambah siswa      ✅ Lulus uji            Akun peserta aktif dapat
                                                    ditugaskan ke kursus

  Admin mengaitkan siswa ke ✅ Lulus uji            Multi-checkbox pada
  beberapa kursus                                   `admin/enrollment.html`

  Siswa hanya mengakses     ✅ Lulus uji            `learning.html` menolak kursus
  kursus yang ditugaskan                            tanpa enrollment aktif

  Belajar & tandai selesai  ✅ Lulus uji            Progres ter-update di tabel
                                                    `completed_lessons`/localStorage
                                                    dan materi berurutan

  Instruktur membuat modul  ✅ Lulus uji            Tambah/ubah/hapus modul dan materi
  & materi                                          tersimpan di database/localStorage

  Instruktur memberi nilai  ✅ Lulus uji            Nilai tersimpan, grade otomatis,
                                                    e-sertifikat Draft dibuat

  Sertifikat Draft tidak    ✅ Lulus uji            Hanya sertifikat Publish yang
  tampil di halaman siswa                           dirender; sertifikat Draft tidak
                                                    bocor ke halaman siswa

  Sertifikat tampil setelah ✅ Lulus uji            Sertifikat Publish tampil di
  persetujuan Admin                                 halaman peserta, termasuk data
                                                    lama dengan ID string/status
                                                    kapital, dan tampilan menyegarkan
                                                    otomatis saat berubah dari tab
                                                    lain

  Validasi publikasi kursus ✅ Lulus uji            Publikasi ditolak bila tanpa
                                                    instruktur aktif atau tanpa
                                                    modul/materi

  CRUD terpusat di modul    ✅ Lulus uji            create/update/delete pengguna,
  `assets/js/`                                      kursus, enrollment, modul, materi,
                                                    nilai, dan sertifikat

  Admin menyetujui          ✅ Lulus uji            Draft → Publish, `approvedAt` dan
  sertifikat                                        approver tersimpan

  Sertifikat Publish tampil ✅ Lulus uji            Muncul di halaman siswa,
  dan dapat diverifikasi                            `certificate-view.html`, dan
                                                    `verify-certificate.html`

  Filter & search di        ✅ Lulus uji            Berbagai kombinasi filter
  admin/users.html                                  

  Filter & search di        ✅ Lulus uji            Filter status dan pencarian
  admin/courses.html                                

  Dashboard instruktur      ✅ Lulus uji            Statistik, notifikasi, tabel
                                                    kursus, aktivitas peserta

  Profil instruktur         ✅ Lulus uji            Nama/telepon tersimpan dan
                                                    tersinkron ke kursus

  Mode pratinjau materi     ✅ Lulus uji            Progres peserta tidak berubah
  instruktur                                        

  Navigasi setelah folder   ✅ Lulus uji            Semua relative path
  restructuring                                     

  Audit trail perubahan     ✅ Lulus uji            `test-audit` memverifikasi aktor,
  kursus/nilai/sertifikat                           aksi, detail, filter, fallback
                                                    "Sistem", dan halaman
                                                    `admin/audit.html`

  Operasi gagal tidak       ✅ Lulus uji            Publikasi kursus kosong ditolak
  dicatat                                           tanpa menambah entri audit

  Ekspor & pembersihan log  ✅ Lulus uji            Tombol CSV dan bersihkan log
                                                    tersedia di `admin/audit.html`

  Notifikasi publikasi      ✅ Lulus uji            `test-notifications` memverifikasi
  kursus                                            notifikasi untuk peserta aktif dan
                                                    instruktur, termasuk isi
                                                    subjek/body

  Notifikasi sertifikat     ✅ Lulus uji            Approval Draft → Publish membuat
  terbit                                            satu notifikasi untuk peserta
                                                    dengan nomor sertifikat final

  Operasi gagal tidak       ✅ Lulus uji            Approval ulang dan publikasi tanpa
  membuat notifikasi                                perubahan status tidak menambah
                                                    outbox

  Filter, ekspor &          ✅ Lulus uji            Filter
  pembersihan outbox                                jenis/penerima/tanggal/pencarian
                                                    dan tombol CSV/bersihkan tersedia
                                                    di `admin/notifications.html`
  ------------------------------------------------------------------------------------

------------------------------------------------------------------------

## 9. Catatan Pengembangan

-   **Dual-mode:** Mode server memakai PHP 8 + MySQL 8; mode lokal
    `localStorage` tetap tersedia sebagai fallback dan untuk regression
    test JSDOM.
-   **Menjalankan backend:** jalankan MySQL, lalu
    `php api/install.php --fresh`, lalu sajikan folder proyek lewat web
    server (mis. `php -S 127.0.0.1:8099 -t .`). Halaman akan otomatis
    memakai API karena `config.js` aktif pada HTTP/HTTPS.
-   **Data Persisten:** Mode server menyimpan data di MySQL; mode lokal
    menyimpan di `localStorage` browser.
-   **Struktur Rapi:** File terorganisir berdasarkan role untuk
    kemudahan navigasi, dengan lapisan backend terpisah di `api/`.
-   **Responsive:** Menggunakan CSS custom yang responsif.
-   **UI Konsisten:** Menggunakan komponen seragam (navbar, sidebar,
    cards, tables, badges).
-   **Keamanan:** Pada mode server password di-hash, sesi memakai cookie
    `HttpOnly`/`SameSite=Lax`, dan otorisasi role/kepemilikan ditegakkan
    di server. Mode lokal tetap bersifat demo dan tidak boleh dianggap
    aman untuk produksi.

------------------------------------------------------------------------

## 10. Rencana Pengembangan Lanjutan (Opsional)

Backend PHP/MySQL, autentikasi sesi, validasi role/kepemilikan, audit
server-side, dan outbox notifikasi simulasi sudah diimplementasikan.
Untuk production, pengembangan berikut masih diperlukan:

  -----------------------------------------------------------------------
  Fitur                   Prioritas               Keterangan
  ----------------------- ----------------------- -----------------------
  Hardening API           P0                      Rate limiting login,
                                                  CSRF token, HTTPS
                                                  wajib, dan rotasi
                                                  session ID

  Pembatasan baca         P0                      Snapshot publik saat
  snapshot                                        ini memuat seluruh
                                                  koleksi demo; produksi
                                                  perlu proyeksi terbatas
                                                  per role dan endpoint
                                                  khusus verifikasi
                                                  sertifikat

  Upload file materi      P0                      Upload PDF, video,
                                                  gambar ke object
                                                  storage

  Generate PDF sertifikat P0                      Generate PDF yang
                                                  sebenarnya

  Notifikasi email        P2                      Integrasi SMTP/email
                                                  service saat kursus
                                                  dipublikasikan atau
                                                  sertifikat terbit

  Migrasi database        P1                      Gunakan migration tool
                                                  dan backup otomatis

  Observability           P2                      Structured logging,
                                                  monitoring, dan retensi
                                                  audit
  -----------------------------------------------------------------------
