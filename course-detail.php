<?php
// Direktori: course-detail.php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'page_script' => 'course-detail',
    'title' => 'Detail Kursus - ELIPS LMS',
    'description' => 'Lihat detail pelatihan ELIPS LMS: materi, instruktur, jadwal, dan pendaftaran kursus.',
    'css' => ['index', 'course-detail'],
    'body_class' => 'landing',
    'scripts' => ['@crud', 'ui', 'landing'],
    'navbar' => 'landing',
    'footer' => 'landing',
];
layout('page', $page);
?>
    <!-- Navbar mengikuti halaman index: berubah jadi bar oren saat di-scroll (landing.js) -->
    <main>
        <!-- Header detail: latar tint + dekorasi minimalis seperti section di index -->
        <section class="lp-detail-hero" aria-labelledby="courseTitle">
            <div class="lp-container">
                <nav class="lp-crumb" aria-label="Breadcrumb">
                    <a href="<?= url('index.php') ?>">Beranda</a>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                    <a href="<?= url('index.php') ?>#pelatihan">Pelatihan</a>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                    <span>Detail Kursus</span>
                </nav>

                <div class="lp-detail-hero-grid lp-detail-reveal">
                    <div class="lp-detail-hero-copy">
                        <span class="lp-detail-tag" id="courseCategory">Pelatihan</span>
                        <h1 class="lp-detail-title" id="courseTitle">Pelatihan Manajemen Dasar</h1>
                        <p class="lp-detail-lead" id="courseDesc">Pelatihan ini membahas konsep dasar manajemen, perencanaan, organisasi, kepemimpinan, dan pengendalian untuk meningkatkan efektivitas kerja.</p>

                        <div class="lp-detail-facts">
                            <div class="lp-detail-fact">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                                <span><small>Instruktur</small><strong id="courseInstructor">Ahmad Fauzi, M.M.</strong></span>
                            </div>
                            <div class="lp-detail-fact">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 10h18"/></svg>
                                <span><small>Materi</small><strong id="courseMeta">8 Modul • 24 Pelajaran</strong></span>
                            </div>
                            <div class="lp-detail-fact">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                                <span><small>Lokasi</small><strong id="courseLocation">-</strong></span>
                            </div>
                            <div class="lp-detail-fact">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                                <span><small>Mulai</small><strong id="courseStartDate">-</strong></span>
                            </div>
                            <div class="lp-detail-fact">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                <span><small>Durasi</small><strong id="courseMeetings">-</strong></span>
                            </div>
                        </div>

                        <!-- Harga dan tombol aksi disatukan di hero -->
                        <div class="lp-detail-cta">
                            <div class="lp-detail-price">
                                <span class="lp-detail-price-label">Biaya pelatihan</span>
                                <div class="lp-detail-price-row">
                                    <span class="lp-price-old" id="coursePriceOld" hidden></span>
                                    <span class="lp-price-now" id="coursePrice">Gratis</span>
                                    <span class="lp-badge" id="courseDiscount" hidden></span>
                                </div>
                            </div>
                            <button id="enrollBtn" class="lp-btn lp-btn-solid lp-btn-lg lp-enroll-btn" type="button">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                <span id="enrollBtnText">Daftar Sekarang</span>
                            </button>
                        </div>
                    </div>

                    <div class="lp-detail-hero-media">
                        <div class="lp-detail-hero-frame">
                            <img id="courseImage" src="<?= url('assets/img/course-laptop.svg') ?>" width="1200" height="1200"
                                 alt="Foto pelatihan ELIPS"
                                 onerror="this.onerror=null;this.src='<?= url('assets/img/course-laptop.svg') ?>'">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="lp-section lp-detail-section">
            <div class="lp-container">
                <div class="lp-detail-layout">
                    <div class="lp-detail-main">
                        <article class="lp-detail-panel lp-detail-reveal">
                            <h2 class="lp-detail-h2">Tentang Kursus</h2>
                            <p class="lp-detail-text">Kursus ini dirancang untuk memberikan pemahaman komprehensif tentang manajemen dasar. Peserta akan mempelajari fungsi-fungsi manajemen, pengambilan keputusan, komunikasi efektif, serta manajemen waktu dan sumber daya.</p>
                        </article>

                        <article class="lp-detail-panel lp-detail-reveal">
                            <h2 class="lp-detail-h2">Daftar Modul &amp; Pelajaran</h2>
                            <p class="lp-detail-hint" id="moduleHint">Klik modul untuk melihat daftar pelajarannya.</p>
                            <div class="lp-modules" id="courseModulesList">
                                <div class="lp-module">
                                    <button class="lp-module-head" type="button" aria-expanded="false">
                                        <span class="lp-module-info"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg><span class="lp-module-title">Modul 1: Pengantar Manajemen</span></span>
                                        <span class="lp-module-right"><span class="lp-module-count">1 Pelajaran</span><svg class="lp-module-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></span>
                                    </button>
                                </div>
                            </div>
                        </article>

                        <nav class="lp-detail-pager lp-detail-reveal" aria-label="Navigasi pelatihan">
                            <button class="lp-btn lp-btn-line lp-pager-btn" id="prevCourse" type="button">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                                <span>Sebelumnya</span>
                            </button>
                            <button class="lp-btn lp-btn-line lp-pager-btn" id="nextCourse" type="button">
                                <span>Selanjutnya</span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </button>
                        </nav>
                    </div>

                    <aside class="lp-detail-side" aria-label="Pelatihan lainnya">
                        <div class="lp-detail-related lp-detail-reveal">
                            <h2 class="lp-detail-related-title">Pelatihan Lainnya</h2>
                            <div class="lp-related-list" id="relatedCourses"></div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </main>

    <!-- Popup formulir pendaftaran untuk pengunjung yang belum login -->
    <div class="lp-modal" id="daftarModal" hidden>
        <div class="lp-modal-backdrop" data-modal-close></div>
        <div class="lp-modal-dialog" role="dialog" aria-modal="true"
             aria-labelledby="daftarModalTitle" aria-describedby="daftarModalDesc">
            <div class="lp-modal-head">
                <div>
                    <span class="lp-modal-eyebrow">Formulir pendaftaran</span>
                    <h2 id="daftarModalTitle">Daftar pelatihan</h2>
                    <p id="daftarModalDesc">Lengkapi data berikut untuk <strong id="daftarModalCourse">pelatihan ini</strong>.</p>
                </div>
                <button class="lp-modal-x" type="button" data-modal-close aria-label="Tutup formulir">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <form class="lp-modal-form" id="daftarForm">
                <p class="lp-modal-error" id="daftarError" role="alert" hidden></p>

                <div class="lp-form-row">
                    <label class="lp-form-field" for="daftarNama">
                        <span>Nama</span>
                        <input type="text" id="daftarNama" name="nama" placeholder="Nama lengkap" autocomplete="name" required>
                    </label>
                    <label class="lp-form-field" for="daftarTempatLahir">
                        <span>Tempat Lahir</span>
                        <input type="text" id="daftarTempatLahir" name="tempatLahir" placeholder="Contoh: Jakarta" required>
                    </label>
                </div>

                <div class="lp-form-row lp-form-row-single">
                    <label class="lp-form-field" for="daftarTanggalLahir">
                        <span>Tanggal Lahir</span>
                        <input type="date" id="daftarTanggalLahir" name="tanggalLahir" required>
                    </label>
                </div>

                <label class="lp-form-field lp-form-field-full" for="daftarAlamat">
                    <span>Alamat Lengkap</span>
                    <textarea id="daftarAlamat" name="alamat" rows="3" placeholder="Alamat sesuai identitas" required></textarea>
                </label>

                <div class="lp-form-row">
                    <label class="lp-form-field" for="daftarPhone">
                        <span>No Telpon/WhatsApp</span>
                        <input type="tel" id="daftarPhone" name="phone" placeholder="08xxxxxxxxxx" autocomplete="tel" required>
                    </label>
                    <label class="lp-form-field" for="daftarEmail">
                        <span>Email Aktif</span>
                        <input type="email" id="daftarEmail" name="email" placeholder="nama@example.com" autocomplete="email" required>
                    </label>
                </div>

                <fieldset class="lp-form-field lp-form-field-full lp-class-picker" id="daftarKelasWrap" hidden>
                    <legend>Pilih Kelas</legend>
                    <div class="lp-class-list" id="daftarKelasList" role="radiogroup" aria-label="Pilih kelas"></div>
                    <p class="lp-class-empty" id="daftarKelasEmpty" hidden></p>
                </fieldset>

                <input type="hidden" id="daftarCourseId" name="courseId">
                <input type="hidden" id="daftarCourseTitle" name="courseTitle">
                <input class="lp-hp" type="text" id="daftarWebsite" name="website" tabindex="-1"
                       autocomplete="off" aria-hidden="true">

                <div class="lp-modal-actions">
                    <button class="lp-btn lp-btn-line" type="button" data-modal-close>Tutup</button>
                    <button class="lp-btn lp-btn-solid" type="submit" id="daftarSubmit">Kirim Data</button>
                </div>
            </form>
        </div>
    </div>
<?php end_layout(); ?>