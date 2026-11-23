<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/certificates',
    'roles' => ['instruktur'],
    'title' => 'Sertifikat Peserta - ELIPS LMS',
    'area' => 'instructor',
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'instructor',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Sertifikat Peserta</h1>
                        <p class="dashboard-subtitle">Pantau e-sertifikat peserta pada kursus Anda. Sertifikat berstatus Draft akan diterbitkan setelah disetujui Admin.</p>
                    </div>
                    <a href="<?= url('instructor/students.php') ?>" class="btn btn-outline"><i class="bi bi-award"></i> Beri Nilai Peserta</a>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statDraft">0</div>
                        <div class="stat-card-label">Menunggu Persetujuan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-patch-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statPublish">0</div>
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

            <div class="dashboard-section" id="missingSection" style="display: none;">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Peserta Sudah Dinilai, Belum Ada Sertifikat</h2>
                    <span class="badge badge-warning"><i class="bi bi-exclamation-triangle"></i> Perlu Diajukan</span>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Peserta</th>
                                <th>Kursus</th>
                                <th>Nilai</th>
                                <th>Progres</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="missingCertBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 0.75rem;">
                    <h2 class="dashboard-section-title">Daftar E-Sertifikat</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <select id="certCourseFilter" class="form-input" style="max-width: 220px;" onchange="renderAll()"></select>
                        <select id="certStatusFilter" class="form-input" style="max-width: 180px;" onchange="renderAll()">
                            <option value="all">Semua Status</option>
                            <option value="draft">Menunggu Persetujuan</option>
                            <option value="publish">Terbit</option>
                        </select>
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
                        <tbody id="instructorCertsBody"></tbody>
                    </table>
                </div>
            </div>
        </main>
<?php end_layout(); ?>
