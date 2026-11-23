<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'page_script' => 'verify-certificate',
    'title' => 'Verifikasi Sertifikat - ELIPS LMS',
    'scripts' => ['@crud', 'ui'],
    'navbar' => 'public',
    'footer' => 'public',
];
layout('page', $page);
?>
    <main>
        <section class="page-header">
            <div class="container">
                <div class="breadcrumb">
                    <a href="<?= url('index.php') ?>">Beranda</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>Verifikasi Sertifikat</span>
                </div>
            </div>
        </section>

        <section class="verify-container">
            <div class="verify-card">
                <div class="verify-header">
                    <div class="verify-icon">
                        <i class="bi bi-patch-check"></i>
                    </div>
                    <h1 class="verify-title">Verifikasi Keaslian Sertifikat</h1>
                    <p class="verify-subtitle">Masukkan nomor sertifikat untuk memeriksa keabsahannya</p>
                </div>
                <div class="verify-body">
                    <div class="verify-form">
                        <input type="text" id="verifyInput" class="verify-input" placeholder="Contoh: CERT-2026-ABC123" maxlength="40">
                        <button id="verifyBtn" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                            Verifikasi
                        </button>
                    </div>
                    <div id="verifyResult" class="verify-result">
                    </div>
                </div>
            </div>
            <div style="text-align: center; margin-top: 2rem; color: var(--text-secondary); font-size: 0.875rem;">
                <p>Hanya sertifikat dengan status <strong>Publish</strong> yang dapat diverifikasi.</p>
            </div>
        </section>
    </main>
<?php end_layout(); ?>
