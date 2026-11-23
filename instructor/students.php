<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/students',
    'roles' => ['instruktur'],
    'title' => 'Peserta &amp; Penilaian - ELIPS LMS',
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
                        <h1 class="dashboard-title">Peserta &amp; Penilaian</h1>
                        <p class="dashboard-subtitle">Pantau progres peserta. Beri nilai di akhir kursus untuk membuat e-sertifikat Draft yang menunggu persetujuan Admin.</p>
                    </div>
                    <a href="<?= url('instructor/manage-course.php') ?>" class="btn btn-outline"><i class="bi bi-journal-plus"></i> Kelola Kursus</a>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statTotal">0</div>
                        <div class="stat-card-label">Total Peserta</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-play-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statLearning">0</div>
                        <div class="stat-card-label">Sedang Belajar</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statComplete">0</div>
                        <div class="stat-card-label">Selesai Materi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-award"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statGraded">0</div>
                        <div class="stat-card-label">Sudah Dinilai</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 0.75rem;">
                    <h2 class="dashboard-section-title">Skema Penilaian &amp; Finalisasi</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <select id="gradeClassFilter" class="form-input" style="max-width: 320px;" onchange="loadGradePanel()"></select>
                        <button type="button" class="btn btn-outline" onclick="loadGradePanel()"><i class="bi bi-arrow-clockwise"></i> Muat Ulang</button>
                    </div>
                </div>
                <div class="dashboard-section-body">
                    <div id="gradePanelMessage" class="form-hint" style="margin-bottom: 0.75rem;"></div>
                    <div id="gradeSchemeCard"></div>
                    <div id="gradeRosterCard" style="margin-top: 1.25rem;"></div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 0.75rem;">
                    <h2 class="dashboard-section-title">Daftar Peserta</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <select id="courseFilter" class="form-input" style="max-width: 220px;" onchange="refreshData()"></select>
                        <select id="statusFilter" class="form-input" style="max-width: 180px;" onchange="renderInstructorStudents()">
                            <option value="all">Semua Peserta</option>
                            <option value="learning">Sedang Belajar</option>
                            <option value="complete">Selesai Materi</option>
                            <option value="ungraded">Belum Dinilai</option>
                            <option value="graded">Sudah Dinilai</option>
                        </select>
                        <input type="text" id="studentSearch" class="form-input" style="max-width: 220px;" placeholder="Cari nama / email..." onkeyup="renderInstructorStudents()">
                        <button type="button" class="btn btn-ghost" onclick="resetFilters()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    </div>
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
                                <th>Sertifikat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="instructorStudentsBody"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Penilaian -->
    <div id="gradeModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;">Beri Nilai</h3>
                <button onclick="closeGradeModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div><strong>Peserta:</strong> <span id="gradeStudentName"></span></div>
                <div><strong>Kursus:</strong> <span id="gradeCourseName"></span></div>
                <div><strong>Progres:</strong> <span id="gradeProgress"></span></div>

                <div id="gradeProgressNotice" style="display: none; padding: 0.75rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius); font-size: 0.875rem; color: #92400e;"></div>

                <div class="form-group">
                    <label class="form-label" for="gradeScore">Nilai (0-100) *</label>
                    <input type="number" id="gradeScore" class="form-input" min="0" max="100" placeholder="Masukkan nilai" oninput="updateGradeHint()">
                    <small class="form-hint" id="gradeHint">Grade otomatis: A (90+), A- (80+), B+ (75+), B (70+), B- (65+), C+ (60+), C (55+), D (50+), E (&lt;50).</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="gradeNote">Catatan (Opsional)</label>
                    <input type="text" id="gradeNote" class="form-input" placeholder="Lulus / Memenuhi syarat">
                </div>
                <div id="gradeCertNote" style="padding: 0.75rem; background: #fef3c7; border-radius: var(--radius); font-size: 0.875rem; color: #92400e;"></div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeGradeModal()">Batal</button>
                <button class="btn btn-primary" id="gradeSaveBtn" onclick="saveGrade()">Simpan Nilai</button>
            </div>
        </div>
    </div>

    <!-- Modal Koreksi Nilai Final -->
    <div id="correctionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;">Koreksi Nilai Final</h3>
                <button onclick="closeCorrectionModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div><strong>Peserta:</strong> <span id="correctionStudentName"></span></div>
                <div id="correctionComponentInputs" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
                <div class="form-group">
                    <label class="form-label" for="correctionFinalScore">Nilai akhir pengganti (opsional)</label>
                    <input type="number" id="correctionFinalScore" class="form-input" min="0" max="100" placeholder="Kosongkan bila hanya mengoreksi komponen">
                    <small class="form-hint">Jika diisi, nilai ini dipakai langsung sebagai nilai akhir. Jika kosong, nilai dihitung ulang dari komponen.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="correctionReason">Alasan koreksi *</label>
                    <textarea id="correctionReason" class="form-input" rows="3" placeholder="Contoh: kesalahan input nilai praktik, revisi hasil verifikasi instruktur."></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeCorrectionModal()">Batal</button>
                <button class="btn btn-primary" onclick="submitCorrection()">Simpan Koreksi</button>
            </div>
        </div>
<?php end_layout(); ?>
