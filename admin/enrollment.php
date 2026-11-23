<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/enrollment',
    'roles' => ['admin'],
    'title' => 'Penugasan Siswa ke Kursus - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Penugasan Siswa ke Kursus</h1>
                        <p class="dashboard-subtitle">Admin menugaskan siswa ke kursus (satu siswa bisa ditugaskan ke banyak kursus). Siswa hanya dapat mengakses kursus yang ditugaskan.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openEnrollModal()">
                        <i class="bi bi-person-plus"></i>
                        Tugaskan Siswa
                    </button>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statTotal">0</div>
                        <div class="stat-card-label">Total Penugasan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statAktif">0</div>
                        <div class="stat-card-label">Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statSiswa">0</div>
                        <div class="stat-card-label">Jumlah Siswa Ditugaskan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statKursus">0</div>
                        <div class="stat-card-label">Kursus yang Memiliki Peserta</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Daftar Penugasan Siswa</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <select class="form-input" id="filterCourse" style="max-width: 220px;" onchange="filterEnrollment()">
                            <option value="all">Semua Kursus</option>
                        </select>
                        <select class="form-input" id="filterStatus" style="max-width: 150px;" onchange="filterEnrollment()">
                            <option value="all">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="dihentikan">Dihentikan</option>
                        </select>
                        <input type="text" id="searchEnrollment" class="form-input" style="max-width: 200px;" placeholder="Cari peserta..." onkeyup="filterEnrollment()">
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Peserta</th>
                                <th>Kursus</th>
                                <th>Instruktur</th>
                                <th>Tanggal Penugasan</th>
                                <th>Ditugaskan Oleh</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="enrollmentTable"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Penugasan Siswa -->
    <div id="enrollModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;">Tugaskan Siswa ke Kursus</h3>
                <button onclick="closeEnrollModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Pilih Siswa (Peserta) *</label>
                    <select id="assignStudent" class="form-input" onchange="refreshAssignCourses()"></select>
                    <small class="form-hint">Satu siswa bisa ditugaskan ke beberapa kursus sekaligus.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Pilih Kursus * (bisa lebih dari satu)</label>
                    <div id="assignCourses" style="display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem; border: 1px solid var(--border); border-radius: var(--radius); max-height: 240px; overflow-y: auto;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Penugasan</label>
                    <input type="date" id="assignDate" class="form-input">
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeEnrollModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveAssignment()">Tugaskan</button>
            </div>
        </div>
<?php end_layout(); ?>
