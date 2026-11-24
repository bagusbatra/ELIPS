<?php
// Direktori: admin/reports.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/reports',
    'roles' => ['admin'],
    'title' => 'Laporan - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Laporan</h1>
                    <p class="dashboard-subtitle">Laporan statistik dan aktivitas sistem LMS</p>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value">4</div>
                        <div class="stat-card-label">Total Kursus</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value">3</div>
                        <div class="stat-card-label">Total Pengguna</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value">3</div>
                        <div class="stat-card-label">Enrolment</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-award"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value">1</div>
                        <div class="stat-card-label">Sertifikat Terbit</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Laporan Progres Peserta</h2>
                    <div style="display: flex; gap: 0.75rem;">
                        <select class="form-input" style="max-width: 200px;">
                            <option>Semua Kursus</option>
                            <option>Pelatihan Manajemen Dasar</option>
                            <option>Komunikasi Efektif</option>
                        </select>
                        <button class="btn btn-primary">
                            <i class="bi bi-file-earmark-pdf"></i>
                            Export PDF
                        </button>
                        <button class="btn btn-outline">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                            Export Excel
                        </button>
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Peserta</th>
                                <th>Kursus</th>
                                <th>Tentor</th>
                                <th>Progres</th>
                                <th>Terakhir Akses</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="table-course">
                                        <div class="dashboard-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">BS</div>
                                        <div class="table-course-info">
                                            <div class="table-course-title">Budi Santoso</div>
                                            <div class="table-course-meta">budi@example.com</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span>Pelatihan Manajemen Dasar</span></td>
                                <td><span>Ahmad Fauzi, M.M.</span></td>
                                <td>
                                    <div class="progress-inline">
                                        <div class="progress-inline-bar">
                                            <div class="progress-inline-fill" style="width: 50%"></div>
                                        </div>
                                        <span class="progress-inline-text">50%</span>
                                    </div>
                                </td>
                                <td><span style="color: var(--text-secondary);">02 Nov 2026</span></td>
                                <td><span class="badge badge-warning">Sedang Belajar</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="table-course">
                                        <div class="dashboard-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">AN</div>
                                        <div class="table-course-info">
                                            <div class="table-course-title">Ani Nurhayati</div>
                                            <div class="table-course-meta">ani@example.com</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span>Komunikasi Efektif di Tempat Kerja</span></td>
                                <td><span>Siti Nurhaliza, S.Psi.</span></td>
                                <td>
                                    <div class="progress-inline">
                                        <div class="progress-inline-bar">
                                            <div class="progress-inline-fill" style="width: 33%"></div>
                                        </div>
                                        <span class="progress-inline-text">33%</span>
                                    </div>
                                </td>
                                <td><span style="color: var(--text-secondary);">01 Nov 2026</span></td>
                                <td><span class="badge badge-warning">Sedang Belajar</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="table-course">
                                        <div class="dashboard-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">RS</div>
                                        <div class="table-course-info">
                                            <div class="table-course-title">Rina Setiawati</div>
                                            <div class="table-course-meta">rina@example.com</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span>Pelatihan Manajemen Dasar</span></td>
                                <td><span>Ahmad Fauzi, M.M.</span></td>
                                <td>
                                    <div class="progress-inline">
                                        <div class="progress-inline-bar">
                                            <div class="progress-inline-fill" style="width: 75%"></div>
                                        </div>
                                        <span class="progress-inline-text">75%</span>
                                    </div>
                                </td>
                                <td><span style="color: var(--text-secondary);">02 Nov 2026</span></td>
                                <td><span class="badge badge-warning">Sedang Belajar</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Statistik per Kursus</h2>
                </div>
                <div class="dashboard-section-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                        <div style="padding: 1.25rem; border: 1px solid var(--border); border-radius: var(--radius-lg);">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                                <h3 style="font-weight: 600; color: var(--text-primary);">Pelatihan Manajemen Dasar</h3>
                                <span class="badge badge-success">Dipublikasi</span>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Total Peserta</span>
                                    <strong style="color: var(--text-primary);">2</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Rata-rata Progres</span>
                                    <strong style="color: var(--primary);">62.5%</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Sertifikat Terbit</span>
                                    <strong style="color: var(--success);">0</strong>
                                </div>
                            </div>
                        </div>
                        <div style="padding: 1.25rem; border: 1px solid var(--border); border-radius: var(--radius-lg);">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                                <h3 style="font-weight: 600; color: var(--text-primary);">Komunikasi Efektif</h3>
                                <span class="badge badge-success">Dipublikasi</span>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Total Peserta</span>
                                    <strong style="color: var(--text-primary);">1</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Rata-rata Progres</span>
                                    <strong style="color: var(--primary);">33%</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Sertifikat Terbit</span>
                                    <strong style="color: var(--success);">0</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
<?php end_layout(); ?>