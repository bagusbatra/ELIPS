<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/academic',
    'roles' => ['admin'],
    'title' => 'Kurikulum &amp; Kelas - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['@crud', 'crud-academic', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Kurikulum &amp; Kelas</h1>
                        <p class="dashboard-subtitle">Kelola versi kurikulum per kursus, buat angkatan/kelas, tetapkan satu tentor, dan daftarkan peserta. Versi kurikulum dikunci otomatis saat kelas dibuat.</p>
                    </div>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <button class="btn btn-outline" onclick="openVersionModal()">
                            <i class="bi bi-layers"></i>
                            Buat Versi Kurikulum
                        </button>
                        <button class="btn btn-primary" onclick="openClassModal()">
                            <i class="bi bi-people"></i>
                            Buat Kelas
                        </button>
                    </div>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statCourses">0</div>
                        <div class="stat-card-label">Kursus</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-layers"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statVersions">0</div>
                        <div class="stat-card-label">Versi Kurikulum</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statClasses">0</div>
                        <div class="stat-card-label">Kelas / Angkatan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statEnrollments">0</div>
                        <div class="stat-card-label">Peserta Terdaftar</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Versi Kurikulum</h2>
                    <select class="form-input" id="versionCourseFilter" style="max-width: 280px;" onchange="renderVersions()">
                        <option value="all">Semua Kursus</option>
                    </select>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Kursus</th>
                                <th>Versi</th>
                                <th>Nama</th>
                                <th>Modul/Materi</th>
                                <th>Kelas</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="versionTable"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Kelas / Angkatan</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <select class="form-input" id="classBranchFilter" style="max-width: 220px;" onchange="renderClasses()">
                            <option value="all">Semua Cabang</option>
                        </select>
                        <select class="form-input" id="classCourseFilter" style="max-width: 280px;" onchange="renderClasses()">
                            <option value="all">Semua Kursus</option>
                        </select>
                        <a href="<?= url('admin/settings.php') ?>" class="btn btn-ghost">
                            <i class="bi bi-gear"></i>
                            Kelola Cabang
                        </a>
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th>Kursus</th>
                                <th>Kurikulum</th>
                                <th>Cabang</th>
                                <th>Tentor</th>
                                <th>Periode</th>
                                <th>Kuota</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="classTable"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Jadwal &amp; Sesi Pertemuan</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <select class="form-input" id="sessionClassFilter" style="max-width: 320px;" onchange="renderSessions()">
                            <option value="">Semua Kelas</option>
                        </select>
                        <button class="btn btn-ghost" onclick="openRecapModal(document.getElementById('sessionClassFilter').value)">
                            <i class="bi bi-bar-chart"></i>
                            Rekap Kelas
                        </button>
                        <button class="btn btn-outline" onclick="openBulkSessionModal()">
                            <i class="bi bi-calendar3"></i>
                            Jadwal Berkala
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
                                <th>Tentor</th>
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
        </main>
    </div>

    <!-- Modal Versi Kurikulum -->
    <div id="versionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="versionModalTitle">Buat Versi Kurikulum</h3>
                <button onclick="closeVersionModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="versionId">
                <div class="form-group">
                    <label class="form-label">Kursus *</label>
                    <select id="versionCourse" class="form-input"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Versi</label>
                    <input type="text" id="versionCode" class="form-input" placeholder="1.0 (otomatis bila kosong)">
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Versi *</label>
                    <input type="text" id="versionName" class="form-input" placeholder="Kurikulum 1.0 — Nama Kursus">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea id="versionDescription" class="form-input" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Efektif</label>
                    <input type="date" id="versionEffectiveDate" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea id="versionNotes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeVersionModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveVersion()">Simpan</button>
            </div>
        </div>
    </div>

    <!-- Modal Kelas -->
    <div id="classModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 640px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="classModalTitle">Buat Kelas</h3>
                <button onclick="closeClassModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="classId">
                <div class="form-group">
                    <label class="form-label">Kursus *</label>
                    <select id="classCourse" class="form-input" onchange="refreshClassVersions()"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Versi Kurikulum *</label>
                    <select id="classVersion" class="form-input"></select>
                    <small class="form-hint">Hanya versi Published yang dapat dipakai. Versi akan dikunci setelah kelas dibuat.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Cabang</label>
                    <select id="classBranch" class="form-input"></select>
                    <small class="form-hint">Kosongkan bila kelas belum ditetapkan ke cabang.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Kelas / Angkatan *</label>
                    <input type="text" id="className" class="form-input" placeholder="Angkatan 2026 — Nama Kursus">
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Kelas</label>
                    <input type="text" id="classCode" class="form-input" placeholder="Otomatis bila kosong">
                </div>
                <div class="form-group">
                    <label class="form-label">Tentor Penanggung Jawab</label>
                    <select id="classInstructor" class="form-input"></select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" id="classStart" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" id="classEnd" class="form-input">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Kuota Peserta</label>
                        <input type="number" id="classQuota" class="form-input" min="0" value="0">
                        <small class="form-hint">0 = tanpa batas.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rencana JP</label>
                        <input type="number" id="classPlannedJp" class="form-input" min="0" step="0.5" value="0">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Lokasi</label>
                        <input type="text" id="classLocation" class="form-input" placeholder="Kampus Pusat">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ruangan</label>
                        <input type="text" id="classRoom" class="form-input" placeholder="R-101">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Jadwal</label>
                    <input type="text" id="classSchedule" class="form-input" placeholder="Senin &amp; Rabu, 09.00-11.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="classStatus" class="form-input">
                        <option value="draft">Draft</option>
                        <option value="open">Open</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea id="classNotes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeClassModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveClass()">Simpan Kelas</button>
            </div>
        </div>
    </div>

    <!-- Modal Pengaturan Akses Materi -->
    <div id="materialSettingsModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;">Akses Materi Kelas</h3>
                <button onclick="closeMaterialSettingsModal()" aria-label="Tutup" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="materialSettingsClassId">
                <div style="padding: 0.875rem 1rem; background: #eff6ff; border-radius: var(--radius); color: #1e40af; font-size: 0.875rem;" id="materialSettingsMeta"></div>
                <div class="form-group">
                    <label class="form-label" for="materialSettingsMode">Mode Akses *</label>
                    <select id="materialSettingsMode" class="form-input">
                        <option value="open">Open — semua materi tersedia dapat dibuka</option>
                        <option value="sequential">Sequential — materi dibuka berurutan</option>
                        <option value="scheduled">Scheduled — materi dibuka sesuai jadwal sesi</option>
                        <option value="conditional">Conditional — urutan + jadwal sesi</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; font-weight: 500;">
                        <input type="checkbox" id="materialSettingsApproval" style="width: auto;">
                        Wajib persetujuan instruktur sebelum peserta menandai selesai
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label" for="materialSettingsNotes">Catatan Kebijakan</label>
                    <textarea id="materialSettingsNotes" class="form-input" rows="2" placeholder="Opsional, misalnya alasan pengaturan khusus"></textarea>
                </div>
                <div class="form-group" id="materialSettingsReasonGroup">
                    <label class="form-label" for="materialSettingsReason">Alasan Override Admin *</label>
                    <textarea id="materialSettingsReason" class="form-input" rows="2" placeholder="Wajib diisi saat mengubah pengaturan yang sudah tersimpan"></textarea>
                    <small class="form-hint" id="materialSettingsReasonHint">Perubahan oleh Admin pada pengaturan yang sudah ada wajib memiliki alasan dan akan dicatat pada audit log.</small>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeMaterialSettingsModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveMaterialSettings()">Simpan Pengaturan</button>
            </div>
        </div>
    </div>

    <!-- Modal Tentor -->
    <div id="instructorModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 480px; width: 100%;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;">Tetapkan Tentor</h3>
                <button onclick="closeInstructorModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="instructorClassId">
                <div class="form-group">
                    <label class="form-label">Tentor Aktif *</label>
                    <select id="instructorSelect" class="form-input"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Alasan Pergantian</label>
                    <textarea id="instructorReason" class="form-input" rows="2" placeholder="Opsional"></textarea>
                </div>
                <div id="instructorHistory" style="font-size: 0.875rem; color: var(--text-secondary);"></div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeInstructorModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveInstructor()">Tetapkan</button>
            </div>
        </div>
    </div>

    <!-- Modal Roster Kelas -->
    <div id="rosterModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 720px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 600;" id="rosterTitle">Peserta Kelas</h3>
                    <p style="font-size: 0.875rem; color: var(--text-secondary);" id="rosterMeta"></p>
                </div>
                <button onclick="closeRosterModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                <div>
                    <label class="form-label">Tambah Peserta</label>
                    <div id="rosterCandidates" style="display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem; border: 1px solid var(--border); border-radius: var(--radius); max-height: 180px; overflow-y: auto;"></div>
                    <button class="btn btn-outline" style="margin-top: 0.75rem;" onclick="addRosterStudents()">
                        <i class="bi bi-person-plus"></i>
                        Daftarkan Peserta Terpilih
                    </button>
                </div>
                <div style="overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Peserta</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="rosterTable"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Kurikulum -->
    <div id="detailModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 720px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="detailTitle">Detail Kurikulum</h3>
                <button onclick="closeDetailModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;" id="detailBody"></div>
        </div>
    </div>

    <!-- Modal Sesi Pertemuan -->
    <div id="sessionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 720px; width: 100%; max-height: 90vh; overflow-y: auto;">
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
                    <small class="form-hint">Modul mengikuti kurikulum yang dikunci pada kelas.</small>
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
                <div class="form-group">
                    <label class="form-label">Tentor Sesi</label>
                    <select id="sessionInstructor" class="form-input"></select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Rencana JP</label>
                        <input type="number" id="sessionPlannedJp" class="form-input" min="0" step="0.5" value="4">
                    </div>
                    <div class="form-group">
                        <label class="form-label">JP Aktual</label>
                        <input type="number" id="sessionActualJp" class="form-input" min="0" step="0.5" value="0">
                        <small class="form-hint">Terisi otomatis saat status Selesai.</small>
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

    <!-- Modal Jadwal Berkala -->
    <div id="bulkSessionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 640px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;">Buat Jadwal Berkala</h3>
                <button onclick="closeBulkSessionModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Kelas *</label>
                    <select id="bulkClass" class="form-input" onchange="refreshBulkModules()"></select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Tanggal Awal *</label>
                        <input type="date" id="bulkStartDate" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jumlah Pertemuan *</label>
                        <input type="number" id="bulkOccurrences" class="form-input" min="1" max="52" value="8">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Hari dalam Seminggu *</label>
                    <div id="bulkWeekdays" style="display: flex; flex-wrap: wrap; gap: 0.75rem; padding: 0.75rem; border: 1px solid var(--border); border-radius: var(--radius);">
                        <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" value="1"> Sen</label>
                        <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" value="2"> Sel</label>
                        <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" value="3"> Rab</label>
                        <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" value="4"> Kam</label>
                        <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" value="5"> Jum</label>
                        <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" value="6"> Sab</label>
                        <label style="display:flex;align-items:center;gap:.375rem;"><input type="checkbox" value="7"> Min</label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Modul</label>
                    <select id="bulkModule" class="form-input"></select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Waktu Mulai *</label>
                        <input type="time" id="bulkStart" class="form-input" value="09:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Waktu Selesai *</label>
                        <input type="time" id="bulkEnd" class="form-input" value="11:00">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Lokasi</label>
                        <input type="text" id="bulkLocation" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ruangan</label>
                        <input type="text" id="bulkRoom" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tentor</label>
                    <select id="bulkInstructor" class="form-input"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Rencana JP per Sesi</label>
                    <input type="number" id="bulkPlannedJp" class="form-input" min="0" step="0.5" value="4">
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea id="bulkNotes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeBulkSessionModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveBulkSessions()">Buat Jadwal</button>
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
                    <small class="form-hint">Setiap perubahan status tersimpan sebagai koreksi dengan jejak audit.</small>
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
