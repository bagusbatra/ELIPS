<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'page_script' => 'employee/marketing/reports',
    'roles' => ['karyawan'],
    'divisions' => ['marketing'],
    'title' => 'Laporan Marketing - ELIPS LMS',
    'scripts' => ['@crud', 'crud-marketing', 'ui', 'employee/marketing/reports'],
    'navbar' => 'employee-marketing',
    'sidebar' => 'employee-marketing',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Laporan Marketing</h1>
                    <p class="dashboard-subtitle" id="reportGreeting">Ringkasan lengkap kinerja marketing berdasarkan periode dan filter.</p>
                </div>
                <div class="modal-actions" style="flex-wrap: wrap;">
                    <button class="btn btn-outline" type="button" id="reportExportCsv"><i class="bi bi-filetype-csv"></i> Ekspor CSV</button>
                    <button class="btn btn-outline" type="button" id="reportExportExcel"><i class="bi bi-file-earmark-spreadsheet"></i> Ekspor Excel</button>
                    <button class="btn btn-outline" type="button" id="reportExportPdf"><i class="bi bi-file-earmark-pdf"></i> Ekspor PDF</button>
                    <button class="btn btn-primary" type="button" id="reportRefresh"><i class="bi bi-arrow-clockwise"></i> Muat Ulang</button>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Filter Laporan</h2>
                    <span class="badge badge-primary" id="reportScopeLabel">Data Saya</span>
                </div>
                <div class="dashboard-section-body">
                    <div class="task-toolbar" style="flex-wrap: wrap; gap: .75rem;">
                        <div class="form-group" id="reportOwnerGroup" style="min-width: 220px; margin: 0; display: none;">
                            <label class="form-label" for="reportOwner">Karyawan (Admin)</label>
                            <select class="form-input" id="reportOwner"></select>
                        </div>
                        <div class="form-group" style="min-width: 170px; margin: 0;">
                            <label class="form-label" for="reportPeriodMode">Mode Periode</label>
                            <select class="form-input" id="reportPeriodMode">
                                <option value="month">Bulanan</option>
                                <option value="range">Rentang Tanggal</option>
                                <option value="week">Mingguan</option>
                                <option value="day">Harian</option>
                            </select>
                        </div>
                        <div class="form-group report-period-field" data-mode="month" style="min-width: 170px; margin: 0;">
                            <label class="form-label" for="reportMonth">Bulan</label>
                            <input class="form-input" type="month" id="reportMonth">
                        </div>
                        <div class="form-group report-period-field" data-mode="day" style="min-width: 170px; margin: 0; display: none;">
                            <label class="form-label" for="reportDay">Tanggal</label>
                            <input class="form-input" type="date" id="reportDay">
                        </div>
                        <div class="form-group report-period-field" data-mode="week" style="min-width: 170px; margin: 0; display: none;">
                            <label class="form-label" for="reportWeek">Minggu</label>
                            <input class="form-input" type="week" id="reportWeek">
                        </div>
                        <div class="form-group report-period-field" data-mode="range" style="min-width: 170px; margin: 0; display: none;">
                            <label class="form-label" for="reportFrom">Dari</label>
                            <input class="form-input" type="date" id="reportFrom">
                        </div>
                        <div class="form-group report-period-field" data-mode="range" style="min-width: 170px; margin: 0; display: none;">
                            <label class="form-label" for="reportTo">Sampai</label>
                            <input class="form-input" type="date" id="reportTo">
                        </div>
                        <div class="form-group" style="min-width: 200px; margin: 0;">
                            <label class="form-label" for="reportCampaign">Campaign</label>
                            <select class="form-input" id="reportCampaign">
                                <option value="">Semua campaign</option>
                            </select>
                        </div>
                        <div class="form-group" style="min-width: 170px; margin: 0;">
                            <label class="form-label" for="reportLeadStatus">Status Lead</label>
                            <select class="form-input" id="reportLeadStatus">
                                <option value="">Semua status</option>
                                <option value="baru">Baru</option>
                                <option value="dihubungi">Dihubungi</option>
                                <option value="prospek">Prospek</option>
                                <option value="terdaftar">Terdaftar</option>
                                <option value="batal">Batal</option>
                            </select>
                        </div>
                        <div class="form-group" style="min-width: 170px; margin: 0;">
                            <label class="form-label" for="reportTaskStatus">Status Task</label>
                            <select class="form-input" id="reportTaskStatus">
                                <option value="">Semua status</option>
                                <option value="belum">Belum</option>
                                <option value="proses">Proses</option>
                                <option value="selesai">Selesai</option>
                                <option value="batal">Batal</option>
                            </select>
                        </div>
                    </div>
                    <p class="form-hint" id="reportPeriodLabel" style="margin-top: .75rem;">Periode: -</p>
                </div>
            </div>

            <div class="dashboard-stats" id="reportSummaryCards"></div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Grafik Ringkasan</h2>
                </div>
                <div class="dashboard-section-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                        <div style="padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius-lg);">
                            <h3 style="margin: 0 0 .75rem; font-size: 1rem;">Pipeline Lead</h3>
                            <div id="reportChartPipeline" class="report-chart"></div>
                        </div>
                        <div style="padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius-lg);">
                            <h3 style="margin: 0 0 .75rem; font-size: 1rem;">Status Task</h3>
                            <div id="reportChartTasks" class="report-chart"></div>
                        </div>
                        <div style="padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius-lg);">
                            <h3 style="margin: 0 0 .75rem; font-size: 1rem;">Client per Status</h3>
                            <div id="reportChartClients" class="report-chart"></div>
                        </div>
                        <div style="padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius-lg);">
                            <h3 style="margin: 0 0 .75rem; font-size: 1rem;">Campaign (Lead / Pendaftaran)</h3>
                            <div id="reportChartCampaigns" class="report-chart"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header"><h2 class="dashboard-section-title">Laporan Lead</h2><span class="badge badge-primary" id="reportLeadCount">0 data</span></div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead><tr><th>Nama</th><th>Kontak</th><th>Sumber</th><th>Status</th><th>Campaign</th><th>Follow-up</th><th>Nilai</th></tr></thead>
                        <tbody id="reportLeadBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header"><h2 class="dashboard-section-title">Laporan Campaign</h2><span class="badge badge-primary" id="reportCampaignCount">0 data</span></div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead><tr><th>Campaign</th><th>Kanal</th><th>Status</th><th>Periode</th><th>Lead</th><th>Pendaftaran</th><th>Pendapatan</th></tr></thead>
                        <tbody id="reportCampaignBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header"><h2 class="dashboard-section-title">Laporan Task</h2><span class="badge badge-primary" id="reportTaskCount">0 data</span></div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead><tr><th>Task</th><th>Jenis</th><th>Prioritas</th><th>Status</th><th>Tenggat</th><th>Campaign</th><th>Lead</th></tr></thead>
                        <tbody id="reportTaskBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header"><h2 class="dashboard-section-title">Laporan Client</h2><span class="badge badge-primary" id="reportClientCount">0 data</span></div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead><tr><th>Client</th><th>PIC</th><th>Kontak</th><th>Kota</th><th>Status</th><th>Campaign</th></tr></thead>
                        <tbody id="reportClientBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header"><h2 class="dashboard-section-title">Laporan Follow-up</h2><span class="badge badge-primary" id="reportFollowupCount">0 data</span></div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead><tr><th>Lead</th><th>Tanggal</th><th>Kanal</th><th>Hasil</th><th>Berikutnya</th><th>Catatan</th></tr></thead>
                        <tbody id="reportFollowupBody"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <button class="dashboard-sidebar-toggle" id="reportSidebarToggle" type="button" aria-label="Buka menu">
        <i class="bi bi-list"></i>
    </button>
    <div class="dashboard-sidebar-backdrop" id="reportSidebarBackdrop">
<?php end_layout(); ?>
