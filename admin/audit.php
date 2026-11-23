<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/audit',
    'roles' => ['admin'],
    'title' => 'Audit Trail - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Audit Trail</h1>
                    <p class="dashboard-subtitle">Jejak perubahan kursus, nilai, dan status sertifikat. Setiap entri mencatat aktor, aksi, waktu, dan detail perubahan.</p>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-primary" id="exportAudit">
                        <i class="bi bi-download"></i> Ekspor CSV
                    </button>
                    <button type="button" class="btn btn-ghost" id="clearAudit">
                        <i class="bi bi-trash"></i> Bersihkan Log
                    </button>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statAuditTotal">0</div>
                        <div class="stat-card-label">Total Aktivitas</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statAuditToday">0</div>
                        <div class="stat-card-label">Aktivitas Hari Ini</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statAuditActors">0</div>
                        <div class="stat-card-label">Aktor Terlibat</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Filter Aktivitas</h2>
                </div>
                <div class="dashboard-section-body">
                    <form id="auditFilterForm">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="auditSearch">Cari</label>
                                <input class="form-input" type="search" id="auditSearch" placeholder="Aktor, objek, atau detail...">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="filterAction">Aksi</label>
                                <select class="form-input" id="filterAction">
                                    <option value="">Semua aksi</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="filterEntity">Entitas</label>
                                <select class="form-input" id="filterEntity">
                                    <option value="">Semua entitas</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="filterActor">Aktor</label>
                                <select class="form-input" id="filterActor">
                                    <option value="">Semua aktor</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="filterFrom">Dari Tanggal</label>
                                <input class="form-input" type="date" id="filterFrom">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="filterTo">Sampai Tanggal</label>
                                <input class="form-input" type="date" id="filterTo">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="filterLimit">Tampilkan</label>
                                <select class="form-input" id="filterLimit">
                                    <option value="50">50 entri</option>
                                    <option value="200" selected>200 entri</option>
                                    <option value="500">500 entri</option>
                                    <option value="1000">1000 entri</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem;">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Terapkan Filter</button>
                            <button type="button" class="btn btn-ghost" id="resetAuditFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Riwayat Aktivitas</h2>
                    <span id="auditCount" style="color: var(--text-secondary); font-size: 0.875rem;"></span>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Aktor</th>
                                <th>Aksi</th>
                                <th>Entitas</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody id="auditTable"></tbody>
                    </table>
                </div>
            </div>
        </main>
<?php end_layout(); ?>
