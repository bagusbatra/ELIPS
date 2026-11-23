<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/certificates',
    'roles' => ['admin'],
    'title' => 'Kelola Sertifikat - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Kelola Sertifikat</h1>
                    <p class="dashboard-subtitle">Setujui e-sertifikat berstatus Draft menjadi Publish. Hanya sertifikat Publish yang muncul di halaman siswa dan dapat diverifikasi.</p>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statDraft">0</div>
                        <div class="stat-card-label">Menunggu Persetujuan (Draft)</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-award"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statPublish">0</div>
                        <div class="stat-card-label">Terbit (Publish)</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statTotalCert">0</div>
                        <div class="stat-card-label">Total</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Daftar E-Sertifikat</h2>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button class="btn btn-primary" id="filterAllCert" onclick="filterAdminCerts('all')">Semua</button>
                        <button class="btn btn-ghost" id="filterDraftCert" onclick="filterAdminCerts('draft')">Menunggu Persetujuan (Draft)</button>
                        <button class="btn btn-ghost" id="filterPublishCert" onclick="filterAdminCerts('publish')">Terbit (Publish)</button>
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>No. Sertifikat</th>
                                <th>Peserta</th>
                                <th>Kursus</th>
                                <th>Nilai</th>
                                <th>Tanggal Diajukan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="adminCertsTable"></tbody>
                    </table>
                </div>
            </div>
        </main>
<?php end_layout(); ?>
