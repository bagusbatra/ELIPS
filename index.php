<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'title' => 'ELIPS LMS - Platform Pelatihan dan Kursus Bersertifikat',
    'description' => 'ELIPS LMS: platform pelatihan dan kursus dengan program unggulan 12 bulan di bidang Teknologi Informasi dan Desain Grafis, progres belajar terpantau, dan sertifikat yang dapat diverifikasi.',
    'css' => ['index'],
    'body_class' => 'landing',
    'scripts' => ['@crud', 'ui', 'landing'],
    'navbar' => 'landing',
    'footer' => 'landing',
];
ob_start(); ?>
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="ELIPS LMS - Platform Pelatihan dan Kursus Bersertifikat">
    <meta property="og:description" content="Pelatihan terarah, program unggulan 12 bulan, dan sertifikat yang dapat diverifikasi.">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "ELIPS LMS",
        "description": "Platform Learning Management System untuk pelatihan dan kursus."
    }
    </script>
<?php $page['head_extra'] = ob_get_clean();
layout('page', $page);
?>
    <!-- Navbar: menempel di atas; saat di-scroll berubah menjadi bar oren melengkung (lihat landing.js) -->
    <main>
        <!-- Hero: tiga foto bergantian dengan crossfade 10 detik.
             Animasi diatur landing.js dan berhenti saat kursor berada di atas foto. -->
        <section class="lp-hero" aria-label="Foto utama">
            <h1 class="sr-only">ELIPS LMS: pelatihan terarah dengan sertifikat yang dapat diverifikasi</h1>
            <div class="lp-hero-slides" data-hero-slides>
                <img class="lp-hero-slide is-active"
                     src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1920&h=800&q=80"
                     width="1920" height="800" fetchpriority="high"
                     alt="Peserta pelatihan ELIPS sedang belajar bersama"
                     onerror="this.onerror=null;this.src='<?= url('assets/img/hero-dummy.svg') ?>'">
                <img class="lp-hero-slide"
                     src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=1920&h=800&q=80"
                     width="1920" height="800" loading="lazy"
                     alt="Diskusi tim dalam pelatihan ELIPS"
                     onerror="this.onerror=null;this.src='<?= url('assets/img/hero-dummy.svg') ?>'">
                <img class="lp-hero-slide"
                     src="https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1920&h=800&q=80"
                     width="1920" height="800" loading="lazy"
                     alt="Sesi kolaborasi peserta program ELIPS"
                     onerror="this.onerror=null;this.src='<?= url('assets/img/hero-dummy.svg') ?>'">
            </div>
        </section>

        <!-- Pelatihan: kartu dirender oleh assets/js/ui.js, filter lokasi oleh assets/js/landing.js -->
        <section class="lp-section lp-pelatihan" id="pelatihan" aria-labelledby="judul-pelatihan">
            <div class="lp-container">
                <div class="lp-pel-head">
                    <div>
                        <h2 class="lp-h2" id="judul-pelatihan">Pelatihan yang tersedia</h2>
                        <p class="lp-lead">Pilih kursus yang sesuai dengan kebutuhan pengembangan dirimu.</p>
                    </div>
                    <div class="lp-lok" id="lokasiWrap" hidden>
                        <div class="lp-lok-field" data-lok-field>
                            <span class="lp-lok-ic" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                            </span>
                            <!-- Select tetap ada sebagai sumber nilai; landing.js menggantinya dengan dropdown kustom. -->
                            <select class="lp-lok-select" id="lokasiFilter" aria-label="Filter lokasi pelatihan">
                                <option value="">Semua lokasi</option>
                            </select>
                            <button class="lp-lok-trigger" type="button" data-lok-trigger
                                    aria-haspopup="listbox" aria-expanded="false" aria-controls="lokasiOptions"
                                    aria-label="Filter lokasi pelatihan: Semua lokasi">
                                <span class="lp-lok-copy">
                                    <span class="lp-lok-caption">Lokasi</span>
                                    <span class="lp-lok-value" data-lok-value>Semua lokasi</span>
                                </span>
                                <span class="lp-lok-chev" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </span>
                            </button>
                            <div class="lp-lok-menu" id="lokasiOptions" role="listbox"
                                 aria-label="Pilih lokasi pelatihan" aria-hidden="true" tabindex="-1"></div>
                        </div>
                    </div>
                </div>
                <div class="lp-grid" id="coursesGrid"></div>
                <p class="lp-empty" id="coursesEmpty" hidden>Belum ada pelatihan di lokasi ini.</p>
            </div>
        </section>

        <!-- Program unggulan: pembelajaran 12 bulan, fokus TI dan Desain Grafis -->
        <section class="lp-section lp-unggulan" id="unggulan" aria-labelledby="judul-unggulan">
            <div class="lp-container">
                <h2 class="lp-h2 lp-h2-xl" id="judul-unggulan">Program unggulan, satu tahun belajar terarah.</h2>
                <p class="lp-lead lp-lead-lg">Dirancang untuk pembelajaran selama 12 bulan dengan dua bidang pilihan: Teknologi Informasi dan Desain Grafis.</p>

                <div class="lp-tracks">
                    <article class="lp-track">
                        <div class="lp-track-ic">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 8l-4 4 4 4"/><path d="M16 8l4 4-4 4"/><path d="M14 5l-4 14"/></svg>
                        </div>
                        <div>
                            <h3 class="lp-track-title">Teknologi Informasi</h3>
                            <p class="lp-track-desc">Bangun dasar yang kuat untuk bekerja di bidang teknologi, dari perangkat sampai aplikasi.</p>
                        </div>
                        <ul class="lp-checks">
                            <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Jaringan komputer dan dukungan teknis</li>
                            <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Pemrograman web dan aplikasi</li>
                            <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Basis data dan pengolahan data</li>
                            <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Keamanan digital dasar</li>
                        </ul>
                    </article>
                    <article class="lp-track">
                        <div class="lp-track-ic">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19l7-7 3 3-7 7z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18z"/><path d="M2 2l7.6 7.6"/><circle cx="11" cy="11" r="2"/></svg>
                        </div>
                        <div>
                            <h3 class="lp-track-title">Desain Grafis</h3>
                            <p class="lp-track-desc">Latih kepekaan visual dan keterampilan membuat karya yang siap dipakai klien.</p>
                        </div>
                        <ul class="lp-checks">
                            <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Prinsip desain, warna, dan tipografi</li>
                            <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Ilustrasi dan desain vektor</li>
                            <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Branding, logo, dan konten media sosial</li>
                            <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Editing foto dan video dasar</li>
                        </ul>
                    </article>
                </div>

                <h3 class="lp-h3">Perjalanan 12 bulan</h3>
                <p class="lp-lead">Tahapan yang sama berlaku untuk kedua bidang.</p>
                <ol class="lp-steps">
                    <li class="lp-step">
                        <span class="lp-step-when">Bulan 1 sampai 3</span>
                        <h4 class="lp-step-title">Fondasi</h4>
                        <p>Mengenal konsep dasar dan alat kerja sesuai bidang yang kamu pilih.</p>
                    </li>
                    <li class="lp-step">
                        <span class="lp-step-when">Bulan 4 sampai 6</span>
                        <h4 class="lp-step-title">Latihan terarah</h4>
                        <p>Praktik rutin lewat tugas mingguan dan umpan balik dari pengajar.</p>
                    </li>
                    <li class="lp-step">
                        <span class="lp-step-when">Bulan 7 sampai 9</span>
                        <h4 class="lp-step-title">Proyek nyata</h4>
                        <p>Mengerjakan proyek yang menyerupai kebutuhan di dunia kerja.</p>
                    </li>
                    <li class="lp-step">
                        <span class="lp-step-when">Bulan 10 sampai 12</span>
                        <h4 class="lp-step-title">Portofolio dan sertifikat</h4>
                        <p>Menyusun portofolio, mengikuti uji akhir, lalu menerima sertifikat yang bisa diverifikasi.</p>
                    </li>
                </ol>

                <a class="lp-btn lp-btn-solid lp-btn-lg lp-unggulan-cta" href="<?= url('register.php') ?>">Daftar program unggulan</a>
            </div>
        </section>

        <!-- Mitra kerja sama: marquee bergeser ke kanan tanpa henti.
             Logo sementara diambil dari layanan placeholder internet;
             ganti dengan logo mitra asli saat tersedia. Dua grup tetap identik. -->
        <section class="lp-mitra" aria-label="Perusahaan mitra">
            <div class="lp-marquee" role="group" aria-label="Logo perusahaan mitra">
                <div class="lp-marquee-track">
                    <div class="lp-marquee-group">
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Nusantara+Teknik" width="160" height="48" loading="lazy" alt="Nusantara Teknik"></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Surya+Mandiri" width="160" height="48" loading="lazy" alt="Surya Mandiri"></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Arunika+Digital" width="160" height="48" loading="lazy" alt="Arunika Digital"></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Kencana+Logistik" width="160" height="48" loading="lazy" alt="Kencana Logistik"></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Bumi+Raya" width="160" height="48" loading="lazy" alt="Bumi Raya"></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Cakra+Media" width="160" height="48" loading="lazy" alt="Cakra Media"></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Lentera+Finansial" width="160" height="48" loading="lazy" alt="Lentera Finansial"></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Wiguna+Konsultan" width="160" height="48" loading="lazy" alt="Wiguna Konsultan"></div>
                    </div>
                    <div class="lp-marquee-group" aria-hidden="true">
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Nusantara+Teknik" width="160" height="48" loading="lazy" alt=""></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Surya+Mandiri" width="160" height="48" loading="lazy" alt=""></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Arunika+Digital" width="160" height="48" loading="lazy" alt=""></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Kencana+Logistik" width="160" height="48" loading="lazy" alt=""></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Bumi+Raya" width="160" height="48" loading="lazy" alt=""></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Cakra+Media" width="160" height="48" loading="lazy" alt=""></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Lentera+Finansial" width="160" height="48" loading="lazy" alt=""></div>
                        <div class="lp-logo"><img src="https://placehold.co/160x48/e2e8f0/475569?text=Wiguna+Konsultan" width="160" height="48" loading="lazy" alt=""></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cek sertifikat: mengirim ke halaman verifikasi yang sudah ada -->
        <section class="lp-section lp-sertifikat" id="sertifikat" aria-labelledby="judul-sertifikat">
            <div class="lp-container">
                <div class="lp-cert-panel">
                    <div class="lp-cert-copy">
                        <h2 class="lp-h2 lp-h2-xl" id="judul-sertifikat">Cek keaslian sertifikat.</h2>
                        <p class="lp-lead lp-lead-lg">Masukkan nomor sertifikat untuk memastikan sertifikat ELIPS LMS asli dan tercatat di sistem kami.</p>
                    </div>
                    <form class="lp-cert-form" id="certForm" action="<?= url('verify-certificate.php') ?>" method="get">
                        <label for="nomor-sertifikat" class="lp-label">Nomor sertifikat</label>
                        <div class="lp-cert-row">
                            <input id="nomor-sertifikat" class="lp-input" type="text" name="no" placeholder="Contoh: CERT-2026-ABC123" autocomplete="off" required>
                            <button class="lp-btn lp-btn-solid lp-btn-lg" type="submit">Cek sertifikat</button>
                        </div>
                        <p class="lp-hint">Nomor sertifikat tertera di bagian bawah sertifikat.</p>
                    </form>
                </div>
            </div>
        </section>

        <!-- Profil singkat -->
        <section class="lp-section lp-profil" id="profil" aria-labelledby="judul-profil">
            <div class="lp-container lp-profil-grid">
                <div class="lp-profil-copy">
                    <h2 class="lp-h2 lp-h2-xl" id="judul-profil">Belajar jadi lebih terarah bersama ELIPS.</h2>
                    <p class="lp-lead lp-lead-lg">Kursus, jadwal, progres, dan sertifikat ada di satu tempat, jadi kamu bisa fokus pada hal yang paling penting: berkembang. Pilih ELIPS untuk belajar dengan arah yang jelas dan hasil yang bisa kamu buktikan.</p>
                    <ul class="lp-checks lp-checks-plain">
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Materi tersusun per modul dan pelajaran</li>
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Progres belajar terpantau di setiap kursus</li>
                        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Sertifikat resmi yang keasliannya bisa dicek</li>
                    </ul>
                    <!-- Arahkan ke halaman profil lengkap bila sudah dibuat -->
                    <a class="lp-btn lp-btn-slide lp-btn-lg" href="#profil"><span class="lp-slide-txt"><span class="lp-slide-in"><span>Lihat profil lengkap</span><span aria-hidden="true">Kenali ELIPS</span></span></span></a>
                </div>
                <div class="lp-profil-media">
                    <!-- Sementara memakai foto dari internet; ganti dengan aset sendiri saat tersedia. -->
                    <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1200&h=900&q=80"
                         width="1200" height="900" loading="lazy"
                         alt="Suasana kegiatan belajar di ELIPS"
                         onerror="this.onerror=null;this.src='<?= url('assets/img/course-laptop.svg') ?>'">
                </div>
            </div>
        </section>
    </main>
<?php /* <!-- landing.js harus dimuat SETELAH ui.js (kartu kursus dirender ui.js lebih dulu) --> */ ?>
<?php end_layout(); ?>
