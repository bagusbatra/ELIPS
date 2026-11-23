<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/shifts',
    'roles' => ['admin'],
    'title' => 'Jadwal Shift - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['ui', 'shift-schedule'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Jadwal Shift Karyawan</h1>
                        <p class="dashboard-subtitle">Atur shift Pagi, Sore, atau jam custom untuk setiap staf pada tanggal tertentu.</p>
                    </div>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <button class="btn btn-outline" id="shiftRefreshBtn" type="button">
                            <i class="bi bi-arrow-clockwise"></i>
                            Muat Ulang
                        </button>
                        <button class="btn btn-primary" id="shiftAddBtn" type="button">
                            <i class="bi bi-plus-lg"></i>
                            Tambah Shift
                        </button>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Filter Jadwal</h2>
                    <span id="shiftRangeLabel" class="recap-range-label">-</span>
                </div>
                <div class="dashboard-section-body">
                    <form class="recap-filter" id="shiftFilterForm">
                        <div class="form-group">
                            <label class="form-label" for="shiftFrom">Dari Tanggal</label>
                            <input class="form-input" type="date" id="shiftFrom" name="from">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="shiftTo">Sampai Tanggal</label>
                            <input class="form-input" type="date" id="shiftTo" name="to">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="shiftEmployee">Karyawan</label>
                            <select class="form-input" id="shiftEmployee" name="userId">
                                <option value="">Semua staf</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="shiftType">Jenis Shift</label>
                            <select class="form-input" id="shiftType" name="shiftType">
                                <option value="">Semua shift</option>
                                <option value="pagi">Pagi</option>
                                <option value="sore">Sore</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="recap-filter-actions">
                            <button class="btn btn-outline" type="button" id="shiftThisMonthBtn">
                                <i class="bi bi-calendar-month"></i>
                                Bulan Ini
                            </button>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-funnel"></i>
                                Tampilkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-calendar3"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="shiftStatTotal">0</div>
                        <div class="stat-card-label">Total Shift</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-sunrise"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="shiftStatPagi">0</div>
                        <div class="stat-card-label">Shift Pagi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-sunset"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="shiftStatSore">0</div>
                        <div class="stat-card-label">Shift Sore</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-sliders"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="shiftStatCustom">0</div>
                        <div class="stat-card-label">Jam Custom</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Daftar Shift</h2>
                </div>
                <div class="dashboard-section-body" style="padding: 0;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Karyawan</th>
                                <th>Shift</th>
                                <th>Jam</th>
                                <th>Catatan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="shiftTableBody">
                            <tr>
                                <td colspan="6">
                                    <div class="attendance-empty">Memuat jadwal shift...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div class="attendance-modal" id="shiftModal" style="display: none;">
        <div class="attendance-modal-card shift-modal-card">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title" id="shiftModalTitle">Tambah Shift</div>
                    <div class="attendance-modal-subtitle">Pilih preset Pagi/Sore atau atur jam custom.</div>
                </div>
                <button class="attendance-modal-close" id="shiftModalClose" type="button" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="shiftForm">
                <div class="attendance-modal-body shift-modal-body">
                    <input type="hidden" id="shiftId">
                    <div class="form-group">
                        <label class="form-label" for="shiftFormEmployee">Karyawan</label>
                        <select class="form-input" id="shiftFormEmployee" required>
                            <option value="">Pilih staf</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="shiftFormDate">Tanggal</label>
                        <input class="form-input" type="date" id="shiftFormDate" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="shiftFormType">Jenis Shift</label>
                        <select class="form-input" id="shiftFormType">
                            <option value="pagi">Pagi (08:00 - 16:00)</option>
                            <option value="sore">Sore (16:00 - 00:00)</option>
                            <option value="custom">Custom (atur jam manual)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="shiftFormNote">Catatan</label>
                        <input class="form-input" type="text" id="shiftFormNote" maxlength="255" placeholder="Opsional, misalnya lokasi atau kebutuhan khusus">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="shiftFormStart">Jam Mulai</label>
                        <input class="form-input" type="time" id="shiftFormStart" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="shiftFormEnd">Jam Selesai</label>
                        <input class="form-input" type="time" id="shiftFormEnd" required>
                    </div>
                    <div class="shift-form-hint" id="shiftFormHint">
                        Shift Pagi memakai jam 08:00 - 16:00. Pilih Custom untuk mengubah jam secara manual.
                    </div>
                </div>
                <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                    <button class="btn btn-outline" id="shiftModalCancel" type="button">Batal</button>
                    <button class="btn btn-primary" id="shiftFormSubmit" type="submit">
                        <i class="bi bi-check2-circle"></i>
                        Simpan Shift
                    </button>
                </div>
            </form>
        </div>
<?php end_layout(); ?>
