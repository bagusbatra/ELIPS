<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'student/certificates',
    'roles' => ['peserta'],
    'title' => 'Sertifikat Saya - ELIPS LMS',
    'area' => 'student',
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'student',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Sertifikat Saya</h1>
                <p class="dashboard-subtitle">Pantau status e-sertifikat Anda. Sertifikat terbit dapat dilihat dan diverifikasi secara publik.</p>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-patch-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statPublished">0</div>
                        <div class="stat-card-label">Sertifikat Terbit</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-collection"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statTotal">0</div>
                        <div class="stat-card-label">Total Sertifikat</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section" id="publishedSection" style="display: none;">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Sertifikat Terbit</h2>
                    <span class="badge badge-success"><i class="bi bi-patch-check-fill"></i> Publish</span>
                </div>
                <div class="dashboard-section-body">
                    <div id="publishedList" class="cert-grid"></div>
                </div>
            </div>

            <div id="certificatesEmpty" class="dashboard-section" style="display: none;">
                <div class="dashboard-section-body" style="text-align: center; padding: 3rem;">
                    <i class="bi bi-award" style="font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                    <p style="margin-bottom: 0.5rem; font-weight: 600; color: var(--text-primary);">Belum ada sertifikat</p>
                    <p style="font-size: 0.875rem;">Sertifikat akan muncul setelah instruktur memberi nilai dan Admin menyetujui penerbitannya.</p>
                    <p id="studentAccountInfo" style="font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.75rem;"></p>
                    <p style="font-size: 0.8125rem; color: var(--text-secondary);">Jika Admin baru saja menyetujui sertifikat, muat ulang halaman ini.</p>
                    <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap; margin-top: 1rem;">
                        <button type="button" class="btn btn-outline" onclick="window.location.reload()">
                            <i class="bi bi-arrow-clockwise"></i>
                            Muat Ulang
                        </button>
                        <a href="<?= url('student/courses.php') ?>" class="btn btn-primary">
                            <i class="bi bi-journal-bookmark"></i>
                            Lihat Kursus Saya
                        </a>
                    </div>
                </div>
            </div>
        </main>
<?php end_layout(); ?>
