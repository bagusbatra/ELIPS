<?php
// Direktori: certificate-view.php
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

            <div id="certFrame" class="certificate-frame" style="max-width: 1000px; margin: 0 auto 1.5rem;">
                <img id="certFront" alt="Sertifikat - halaman depan" style="width: 100%; height: auto; display: block; border-radius: 8px; box-shadow: 0 6px 24px rgba(0,0,0,.12); margin-bottom: 1.25rem;">
                <img id="certBack" alt="Transkrip nilai - halaman belakang" style="width: 100%; height: auto; display: block; border-radius: 8px; box-shadow: 0 6px 24px rgba(0,0,0,.12);">
            </div>

            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;" id="certActions">
                <a id="dlPdf" class="btn btn-primary btn-lg" href="#"><i class="bi bi-file-earmark-pdf"></i> Unduh PDF</a>
                <a id="dlJpg" class="btn btn-outline btn-lg" href="#"><i class="bi bi-file-earmark-image"></i> JPG (depan)</a>
                <a id="dlPng" class="btn btn-outline btn-lg" href="#"><i class="bi bi-file-earmark-image"></i> PNG (depan)</a>
                <a id="dlJpgB" class="btn btn-outline btn-lg" href="#"><i class="bi bi-file-earmark-image"></i> JPG (belakang)</a>
                <a id="dlPngB" class="btn btn-outline btn-lg" href="#"><i class="bi bi-file-earmark-image"></i> PNG (belakang)</a>
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