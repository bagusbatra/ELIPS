<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'page_script' => 'certificate-view',
    'area' => 'student',
    'title' => 'Lihat Sertifikat - ELIPS LMS',
    'scripts' => ['@crud', 'ui'],
    'navbar' => 'app',
    'footer' => 'public',
];
layout('page', $page);
?>
    <main>
        <section class="page-header">
            <div class="container">
                <div class="breadcrumb">
                    <a href="<?= url('student/dashboard.php') ?>">Dashboard</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="<?= url('student/certificates.php') ?>">Sertifikat Saya</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>Lihat Sertifikat</span>
                </div>
            </div>
        </section>

        <section class="certificate-view-container">
            <div id="certUnavailable" style="display: none; max-width: 560px; margin: 0 auto; text-align: center; background: white; border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 3rem 2rem;">
                <i class="bi bi-shield-lock" style="font-size: 3rem; color: var(--warning);"></i>
                <h2 style="margin: 1rem 0 0.5rem; font-size: 1.25rem;">Sertifikat Tidak Dapat Ditampilkan</h2>
                <p id="certUnavailableMessage" style="color: var(--text-secondary); margin-bottom: 1.5rem;">Sertifikat tidak ditemukan atau belum berstatus Publish.</p>
                <a href="<?= url('verify-certificate.php') ?>" class="btn btn-primary"><i class="bi bi-patch-check"></i> Verifikasi Sertifikat</a>
            </div>

            <div id="certFrame" class="certificate-frame">
                <div class="certificate-image">
                    <div class="certificate-content">
                        <div class="certificate-logo">
                            <i class="bi bi-mortarboard-fill"></i>
                            <span>ELIPS LMS</span>
                        </div>
                        <div class="certificate-title">SERTIFIKAT</div>
                        <div class="certificate-subtitle">Diberikan Kepada</div>
                        <div class="certificate-name" id="certName">Budi Santoso</div>
                        <div class="certificate-text">
                            Telah berhasil menyelesaikan seluruh materi pelatihan dengan predikat<br>
                            <strong id="certGrade">LULUS</strong> pada pelatihan
                        </div>
                        <div class="certificate-course" id="certCourse">Pelatihan Manajemen Dasar</div>
                        <div class="certificate-text">yang dilaksanakan secara daring melalui ELIPS LMS</div>
                        <div class="certificate-footer">
                            <div class="certificate-signature">
                                <div class="certificate-signature-line"></div>
                                <div class="certificate-signature-name" id="certInstructor">Ahmad Fauzi, M.M.</div>
                                <div class="certificate-signature-title">Instruktur</div>
                            </div>
                            <div class="certificate-signature">
                                <div class="certificate-signature-line"></div>
                                <div class="certificate-signature-name" id="certAdmin">Administrator</div>
                                <div class="certificate-signature-title">Administrator</div>
                            </div>
                        </div>
                    </div>
                    <div class="certificate-number" id="certNumber">No. Sertifikat: -</div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;" id="certActions">
                <button id="downloadCertBtn" class="btn btn-primary btn-lg">
                    <i class="bi bi-printer"></i>
                    Cetak / Simpan PDF
                </button>
                <a href="<?= url('verify-certificate.php') ?>" class="btn btn-outline btn-lg">
                    <i class="bi bi-patch-check"></i>
                    Verifikasi Sertifikat
                </a>
                <a href="<?= url('student/certificates.php') ?>" class="btn btn-ghost btn-lg">
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Daftar
                </a>
            </div>
        </section>
    </main>
<?php end_layout(); ?>
