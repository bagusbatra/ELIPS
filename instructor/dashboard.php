<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/dashboard',
    'roles' => ['instruktur'],
    'title' => 'Dashboard Instruktur - ELIPS LMS',
    'area' => 'instructor',
    'scripts' => ['@crud', 'crud-academic', 'ui'],
    'sidebar' => 'instructor',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Dashboard Instruktur</h1>
                        <p class="dashboard-subtitle" id="instructorGreeting">Pantau kursus dan perkembangan peserta Anda.</p>
                    </div>
                    <a href="<?= url('instructor/manage-course.php') ?>" class="btn btn-primary">
                        <i class="bi bi-journal-plus"></i>
                        Kelola Kursus
                    </a>
                </div>
            </div>

            <div id="instructorNotice" style="display: none; align-items: flex-start; gap: 0.75rem; padding: 1rem 1.25rem; margin-bottom: 1.5rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-lg); color: #92400e;">
                <i class="bi bi-exclamation-triangle" style="font-size: 1.25rem; margin-top: 0.125rem;"></i>
                <span id="instructorNoticeText"></span>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statCourses">0</div>
                        <div class="stat-card-label">Kursus Diampu</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statStudents">0</div>
                        <div class="stat-card-label">Peserta Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-collection-play"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statLessons">0</div>
                        <div class="stat-card-label">Total Pelajaran</div>
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
                    <h2 class="dashboard-section-title">Kursus yang Diampu</h2>
                    <a href="<?= url('instructor/manage-course.php') ?>" class="btn btn-ghost">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Kursus</th>
                                <th>Peserta</th>
                                <th>Modul / Pelajaran</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="instructorCoursesBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Kelas yang Diampu</h2>
                    <a href="<?= url('instructor/manage-course.php') ?>" class="btn btn-ghost">Kelola Materi <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th>Kursus</th>
                                <th>Kurikulum</th>
                                <th>Periode</th>
                                <th>Peserta</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="instructorClassesBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Jadwal &amp; Presensi Kelas Saya</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <select class="form-input" id="sessionClassFilter" style="max-width: 300px;" onchange="renderInstructorSessions()">
                            <option value="">Semua Kelas Saya</option>
                        </select>
                        <button class="btn btn-ghost" onclick="openRecapModal(document.getElementById('sessionClassFilter').value)">
                            <i class="bi bi-bar-chart"></i>
                            Rekap Kelas
                        </button>
                        <button class="btn btn-primary" onclick="openSessionModal()">
                            <i class="bi bi-calendar-plus"></i>
                            Buat Sesi
                        </button>
                    </div>
                </div>
                <div id="sessionSummary" style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); font-size: 0.875rem; color: var(--text-secondary);"></div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Pertemuan</th>
                                <th>Tanggal &amp; Waktu</th>
                                <th>Modul</th>
                                <th>JP</th>
                                <th>Presensi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="sessionTable"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Aktivitas Peserta Terbaru</h2>
                    <a href="<?= url('instructor/students.php') ?>" class="btn btn-ghost">Semua Peserta <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Peserta</th>
                                <th>Kursus</th>
                                <th>Progres</th>
                                <th>Status</th>
                                <th>Nilai</th>
                            </tr>
                        </thead>
                        <tbody id="recentStudentsBody"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Sesi Pertemuan -->
    <div id="sessionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="sessionModalTitle">Buat Sesi Pertemuan</h3>
                <button onclick="closeSessionModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="sessionId">
                <div class="form-group">
                    <label class="form-label">Kelas *</label>
                    <select id="sessionClass" class="form-input" onchange="refreshSessionModules()"></select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Nomor Pertemuan</label>
                        <input type="number" id="sessionNumber" class="form-input" min="1" placeholder="Otomatis">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal *</label>
                        <input type="date" id="sessionDate" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Judul Sesi</label>
                    <input type="text" id="sessionTitle" class="form-input" placeholder="Otomatis: Pertemuan N">
                </div>
                <div class="form-group">
                    <label class="form-label">Modul</label>
                    <select id="sessionModule" class="form-input"></select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Waktu Mulai *</label>
                        <input type="time" id="sessionStart" class="form-input" value="09:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Waktu Selesai *</label>
                        <input type="time" id="sessionEnd" class="form-input" value="11:00">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Lokasi</label>
                        <input type="text" id="sessionLocation" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ruangan</label>
                        <input type="text" id="sessionRoom" class="form-input">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Rencana JP</label>
                        <input type="number" id="sessionPlannedJp" class="form-input" min="0" step="0.5" value="4">
                    </div>
                    <div class="form-group">
                        <label class="form-label">JP Aktual</label>
                        <input type="number" id="sessionActualJp" class="form-input" min="0" step="0.5" value="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="sessionStatus" class="form-input"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea id="sessionNotes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeSessionModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveSession()">Simpan Sesi</button>
            </div>
        </div>
    </div>

    <!-- Modal Presensi -->
    <div id="attendanceModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 860px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 600;" id="attendanceTitle">Presensi Sesi</h3>
                    <p style="font-size: 0.875rem; color: var(--text-secondary);" id="attendanceMeta"></p>
                </div>
                <button onclick="closeAttendanceModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="attendanceSessionId">
                <div style="overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Peserta</th>
                                <th>Status</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody"></tbody>
                    </table>
                </div>
                <div class="form-group">
                    <label class="form-label">Alasan Perubahan Status</label>
                    <input type="text" id="attendanceReason" class="form-input" placeholder="Wajib diisi bila mengubah presensi yang sudah tersimpan">
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeAttendanceModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveAttendance()">Simpan Presensi</button>
            </div>
        </div>
    </div>

    <!-- Modal Rekap Kehadiran -->
    <div id="recapModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 600;" id="recapTitle">Rekap Kehadiran</h3>
                    <p style="font-size: 0.875rem; color: var(--text-secondary);" id="recapMeta"></p>
                </div>
                <button onclick="closeRecapModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;" id="recapBody"></div>
        </div>
<?php end_layout(); ?>
