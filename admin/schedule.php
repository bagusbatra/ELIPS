<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/schedule',
    'roles' => ['admin'],
    'title' => 'Kalender &amp; Jadwal - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['@crud', 'crud-academic', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Kalender &amp; Jadwal</h1>
                    <p class="dashboard-subtitle">Pantau jadwal kelas dan sesi pertemuan berdasarkan cabang.</p>
                </div>
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="openSessionForm()">
                        <i class="bi bi-plus-lg"></i>
                        Tambah Sesi
                    </button>
                    <a href="<?= url('admin/academic.php') ?>" class="btn btn-ghost">
                        <i class="bi bi-diagram-3"></i>
                        Kurikulum &amp; Kelas
                    </a>
                    <a href="<?= url('admin/settings.php') ?>" class="btn btn-ghost">
                        <i class="bi bi-gear"></i>
                        Kelola Cabang
                    </a>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Konteks Cabang</h2>
                </div>
                <div class="dashboard-section-body schedule-context">
                    <div class="form-group" style="min-width: 200px;">
                        <label class="form-label">Cabang</label>
                        <select id="branchFilter" class="form-input" onchange="changeBranch()"></select>
                    </div>
                    <div class="form-group" style="min-width: 220px;">
                        <label class="form-label">Kelas</label>
                        <select id="classFilter" class="form-input" onchange="changeClass()"></select>
                    </div>
                    <div class="form-group" style="min-width: 200px;">
                        <label class="form-label">Instruktur</label>
                        <select id="instructorFilter" class="form-input" onchange="renderAllViews()"></select>
                    </div>
                    <div class="form-group" style="min-width: 180px;">
                        <label class="form-label">Lokasi</label>
                        <select id="locationFilter" class="form-input" onchange="renderAllViews()"></select>
                    </div>
                    <button type="button" class="btn btn-ghost" onclick="resetScheduleFilters()" title="Reset semua filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Reset
                    </button>
                    <div class="schedule-context-info" id="branchInfo"></div>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue"><i class="bi bi-calendar3"></i></div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statMonthSessions">0</div>
                        <div class="stat-card-label">Sesi Bulan Ini</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green"><i class="bi bi-check2-circle"></i></div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statCompleted">0</div>
                        <div class="stat-card-label">Sesi Selesai</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange"><i class="bi bi-clock"></i></div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statUpcoming">0</div>
                        <div class="stat-card-label">Sesi Terjadwal</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple"><i class="bi bi-people"></i></div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statClasses">0</div>
                        <div class="stat-card-label">Kelas Aktif</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Kalender Bulanan</h2>
                    <div class="calendar-toolbar">
                        <button class="btn btn-ghost" title="Bulan sebelumnya" onclick="shiftMonth(-1)"><i class="bi bi-chevron-left"></i></button>
                        <span class="calendar-month-label" id="monthLabel">-</span>
                        <button class="btn btn-ghost" title="Bulan berikutnya" onclick="shiftMonth(1)"><i class="bi bi-chevron-right"></i></button>
                        <button class="btn btn-outline" onclick="goToday()">Hari Ini</button>
                    </div>
                </div>
                <div class="dashboard-section-body">
                    <div class="calendar-weekdays">
                        <div>Sen</div><div>Sel</div><div>Rab</div><div>Kam</div><div>Jum</div><div>Sab</div><div>Min</div>
                    </div>
                    <div class="calendar-grid" id="calendarGrid"></div>
                    <div class="calendar-legend">
                        <span><span class="calendar-dot dot-scheduled"></span> Terjadwal</span>
                        <span><span class="calendar-dot dot-confirmed"></span> Terkonfirmasi</span>
                        <span><span class="calendar-dot dot-completed"></span> Selesai</span>
                        <span><span class="calendar-dot dot-cancelled"></span> Batal / Tidak Hadir</span>
                    </div>
                </div>
            </div>

            <div class="schedule-detail-grid">
                <div class="dashboard-section">
                    <div class="dashboard-section-header">
                        <h2 class="dashboard-section-title">Agenda <span id="selectedDateLabel" class="schedule-muted"></span></h2>
                    </div>
                    <div class="dashboard-section-body" id="dayPanel"></div>
                </div>
                <div class="dashboard-section">
                    <div class="dashboard-section-header">
                        <h2 class="dashboard-section-title">Ringkasan <span id="monthSummaryLabel" class="schedule-muted"></span></h2>
                    </div>
                    <div class="dashboard-section-body" id="monthSummary"></div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Daftar Sesi</h2>
                    <div class="schedule-status-filter-wrap">
                        <span class="schedule-filter-label"><i class="bi bi-funnel"></i> Status</span>
                        <select class="form-input" id="statusFilter" multiple style="display: none;" aria-hidden="true">
                            <option value="all">Semua Status</option>
                            <option value="scheduled">Terjadwal</option>
                            <option value="confirmed">Terkonfirmasi</option>
                            <option value="completed">Selesai</option>
                            <option value="rescheduled">Dijadwalkan Ulang</option>
                            <option value="cancelled">Dibatalkan</option>
                            <option value="no_show">Tidak Hadir</option>
                        </select>
                        <div class="schedule-status-filter" id="statusFilterChips"></div>
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Kelas</th>
                                <th>Cabang</th>
                                <th>Modul</th>
                                <th>Tentor</th>
                                <th>JP</th>
                                <th>Presensi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="scheduleTable"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Detail Sesi -->
    <div id="sessionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 620px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="sessionModalTitle">Detail Sesi</h3>
                <button onclick="closeSessionModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem;" id="sessionModalBody"></div>
            <div style="padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: space-between; flex-wrap: wrap;">
                <div class="modal-actions" id="sessionModalActions"></div>
                <div class="modal-actions">
                    <a href="<?= url('admin/academic.php') ?>" class="btn btn-ghost"><i class="bi bi-diagram-3"></i> Kelola di Kurikulum</a>
                    <button class="btn btn-primary" onclick="closeSessionModal()">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Form Sesi -->
    <div id="sessionFormModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1001; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 760px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="sessionFormTitle">Buat Sesi Pertemuan</h3>
                <button type="button" onclick="closeSessionForm()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="sessionFormId">
                <div class="session-form-notice" id="sessionFormNotice" style="display: none;"></div>
                <div class="form-group">
                    <label class="form-label">Kelas *</label>
                    <select id="sessionFormClass" class="form-input" onchange="refreshFormModules()"></select>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Nomor Pertemuan</label>
                        <input type="number" id="sessionFormNumber" class="form-input" min="1" placeholder="Otomatis">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal *</label>
                        <input type="date" id="sessionFormDate" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Judul Sesi</label>
                    <input type="text" id="sessionFormTitleInput" class="form-input" placeholder="Otomatis: Pertemuan N">
                </div>
                <div class="form-group">
                    <label class="form-label">Modul</label>
                    <select id="sessionFormModule" class="form-input"></select>
                    <small class="form-hint">Modul mengikuti kurikulum yang dikunci pada kelas.</small>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Waktu Mulai *</label>
                        <input type="time" id="sessionFormStart" class="form-input" value="09:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Waktu Selesai *</label>
                        <input type="time" id="sessionFormEnd" class="form-input" value="11:00">
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Lokasi</label>
                        <input type="text" id="sessionFormLocation" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ruangan</label>
                        <input type="text" id="sessionFormRoom" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tentor Sesi</label>
                    <select id="sessionFormInstructor" class="form-input"></select>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Rencana JP</label>
                        <input type="number" id="sessionFormPlannedJp" class="form-input" min="0" step="0.5" value="4">
                    </div>
                    <div class="form-group">
                        <label class="form-label">JP Aktual</label>
                        <input type="number" id="sessionFormActualJp" class="form-input" min="0" step="0.5" value="0">
                        <small class="form-hint">Terisi otomatis saat status Selesai.</small>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="sessionFormStatus" class="form-input"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea id="sessionFormNotes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" class="btn btn-ghost" onclick="closeSessionForm()">Batal</button>
                <button type="button" class="btn btn-primary" id="sessionFormSubmit" onclick="saveSessionForm()">Simpan Sesi</button>
            </div>
        </div>
<?php end_layout(); ?>
