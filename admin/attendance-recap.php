<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/attendance-recap',
    'roles' => ['admin'],
    'title' => 'Rekap Absensi - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['ui', 'staff-recap'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Rekap Absensi Karyawan</h1>
                        <p class="dashboard-subtitle">Lihat kehadiran harian karyawan dan admin, lalu ekspor rekapnya ke CSV.</p>
                    </div>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <button class="btn btn-outline" id="recapRefreshBtn" type="button">
                            <i class="bi bi-arrow-clockwise"></i>
                            Muat Ulang
                        </button>
                        <button class="btn btn-primary" id="recapExportBtn" type="button">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                            Ekspor CSV
                        </button>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Filter Rekap</h2>
                    <span id="recapRangeLabel" class="recap-range-label">-</span>
                </div>
                <div class="dashboard-section-body">
                    <form class="recap-filter" id="recapFilterForm">
                        <div class="form-group">
                            <label class="form-label" for="recapFrom">Dari Tanggal</label>
                            <input class="form-input" type="date" id="recapFrom" name="from">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="recapTo">Sampai Tanggal</label>
                            <input class="form-input" type="date" id="recapTo" name="to">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="recapEmployee">Karyawan</label>
                            <select class="form-input" id="recapEmployee" name="userId">
                                <option value="">Semua staf</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="recapRole">Role</label>
                            <select class="form-input" id="recapRole" name="role">
                                <option value="">Semua</option>
                                <option value="karyawan">Karyawan</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="recap-filter-actions">
                            <button class="btn btn-outline" type="button" id="recapThisMonthBtn">
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
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="recapStatEmployees">0</div>
                        <div class="stat-card-label">Staf Absen</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-calendar2-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="recapStatDays">0</div>
                        <div class="stat-card-label">Hari Tercatat</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="recapStatComplete">0</div>
                        <div class="stat-card-label">Absen Lengkap</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="recapStatMissing">0</div>
                        <div class="stat-card-label">Belum Lengkap</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Ringkasan Otomatis per Karyawan</h2>
                </div>
                <div class="dashboard-section-body" style="padding: 0;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th>Hadir</th>
                                <th>Terlambat</th>
                                <th>Pulang Cepat</th>
                                <th>Lupa Absen Pulang</th>
                                <th>Alpa (sesuai shift)</th>
                                <th>Total Jam Kerja</th>
                            </tr>
                        </thead>
                        <tbody id="recapSummaryBody">
                            <tr>
                                <td colspan="7">
                                    <div class="attendance-empty">Memuat ringkasan...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Rekap Harian</h2>
                </div>
                <div class="dashboard-section-body" style="padding: 0;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Karyawan</th>
                                <th>Masuk</th>
                                <th>Pulang</th>
                                <th>Durasi</th>
                                <th>Lokasi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="recapTableBody">
                            <tr>
                                <td colspan="7">
                                    <div class="attendance-empty">Memuat rekap absensi...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
<?php end_layout(); ?>
