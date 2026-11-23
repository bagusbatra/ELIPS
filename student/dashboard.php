<?php
// Direktori: student/dashboard.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'student/dashboard',
    'roles' => ['peserta'],
    'title' => 'Dashboard Peserta - ELIPS LMS',
    'area' => 'student',
    'css' => ['admin-registrations', 'finance'],
    'scripts' => ['@crud', 'crud-academic', 'ui', 'pages/student/billing'],
    'sidebar' => 'student',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Dashboard Peserta</h1>
                <p class="dashboard-subtitle" id="dashboardGreeting">Selamat datang! Lanjutkan pembelajaranmu</p>
            </div>

            <div id="studentNotice" style="display: none; align-items: flex-start; gap: 0.75rem; padding: 1rem 1.25rem; margin-bottom: 1.5rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-lg); color: #92400e;">
                <i class="bi bi-hourglass-split" style="font-size: 1.25rem; margin-top: 0.125rem;"></i>
                <span id="studentNoticeText"></span>
            </div>

            <div id="billingSection" style="margin-bottom: 1.5rem;"></div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statCourses">0</div>
                        <div class="stat-card-label">Kursus Ditugaskan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statLessons">0</div>
                        <div class="stat-card-label">Pelajaran Selesai</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statProgress">0%</div>
                        <div class="stat-card-label">Rata-rata Progres</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statAttendance">0%</div>
                        <div class="stat-card-label">Rata-rata Kehadiran</div>
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
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Kursus yang Ditugaskan</h2>
                    <a href="<?= url('student/courses.php') ?>" class="btn btn-ghost">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Kursus</th>
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

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Rekap Kehadiran Saya</h2>
                    <span class="badge badge-primary" id="attendancePolicyBadge">Hadir = present + late</span>
                </div>
                <div class="dashboard-section-body" id="attendanceRecapBody" style="padding: 1.5rem;">
                    <p style="color: var(--text-secondary);">Memuat rekap kehadiran...</p>
                </div>
            </div>
        </main>
<?php end_layout(); ?>