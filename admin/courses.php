<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/courses',
    'roles' => ['admin'],
    'title' => 'Kelola Kursus - ELIPS LMS',
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
                        <h1 class="dashboard-title">Kelola Kursus</h1>
                        <p class="dashboard-subtitle">Tambah atau kelola kursus baru dan tugaskan instruktur.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openCourseModal()">
                        <i class="bi bi-plus-lg"></i>
                        Tambah Kursus
                    </button>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Daftar Kursus</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button class="btn btn-primary" id="filterAll" onclick="filterCourses('all')">Semua</button>
                            <button class="btn btn-ghost" id="filterPublished" onclick="filterCourses('published')">Dipublikasi</button>
                            <button class="btn btn-ghost" id="filterDraft" onclick="filterCourses('draft')">Draft</button>
                        </div>
                        <input type="text" id="searchCourse" class="form-input" style="max-width: 250px;" placeholder="Cari kursus..." onkeyup="searchCourses()">
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Kursus</th>
                                <th>Instruktur</th>
                                <th>Modul/Pelajaran</th>
                                <th>Peserta</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="coursesTableBody"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Tambah/Edit Kursus -->
    <div id="courseModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="courseModalTitle">Tambah Kursus Baru</h3>
                <button onclick="closeCourseModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Judul Kursus *</label>
                    <input type="text" id="courseTitle" class="form-input" placeholder="Masukkan judul kursus">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori *</label>
                    <input type="text" id="courseCategory" class="form-input" placeholder="Pelatihan / Soft Skill / Administrasi">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea id="courseDesc" class="form-input" rows="3" placeholder="Deskripsi singkat kursus"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Instruktur * (1 Kursus = 1 Instruktur)</label>
                    <select id="courseInstructor" class="form-input"></select>
                    <small class="form-hint">Pilih instruktur yang bertanggung jawab atas kursus ini</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <input type="text" class="form-input" value="Draft (otomatis)" disabled>
                    <small class="form-hint">Kursus baru selalu berstatus Draft. Publikasi dilakukan lewat tombol Publikasi setelah modul dan materi tersedia.</small>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeCourseModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveCourse()">Simpan Kursus</button>
            </div>
        </div>
<?php end_layout(); ?>
