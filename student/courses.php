<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'student/courses',
    'roles' => ['peserta'],
    'title' => 'Kursus Saya - ELIPS LMS',
    'area' => 'student',
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'student',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Kursus Saya</h1>
                        <p class="dashboard-subtitle">Semua kursus yang ditugaskan Admin kepada Anda beserta progres belajarnya.</p>
                    </div>
                    <a href="<?= url('index.php') ?>#courses" class="btn btn-outline"><i class="bi bi-compass"></i> Jelajahi Kursus</a>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statActiveCourses">0</div>
                        <div class="stat-card-label">Kursus Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statCompletedCourses">0</div>
                        <div class="stat-card-label">Materi Selesai</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-journal-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statCompletedLessons">0</div>
                        <div class="stat-card-label">Pelajaran Selesai</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-award"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statCertificates">0</div>
                        <div class="stat-card-label">Sertifikat Terbit</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 0.75rem;">
                    <h2 class="dashboard-section-title">Daftar Kursus</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <select id="courseFilter" class="form-input" style="max-width: 190px;" onchange="renderCourseRows()">
                            <option value="all">Semua Kursus</option>
                            <option value="active">Sedang Berjalan</option>
                            <option value="completed">Selesai</option>
                            <option value="inactive">Dihentikan</option>
                        </select>
                        <input type="text" id="courseSearch" class="form-input" style="max-width: 220px;" placeholder="Cari kursus / instruktur..." onkeyup="renderCourseRows()">
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Kursus</th>
                                <th>Instruktur</th>
                                <th>Progres</th>
                                <th>Nilai</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="studentCoursesBody"></tbody>
                    </table>
                </div>
            </div>
        </main>
<?php end_layout(); ?>
