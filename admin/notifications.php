<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/notifications',
    'roles' => ['admin'],
    'title' => 'Notifikasi Email - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Notifikasi Email</h1>
                    <p class="dashboard-subtitle">Outbox simulasi email untuk publikasi kursus dan penerbitan sertifikat. Demo berjalan tanpa backend, sehingga email tidak benar-benar dikirim dan seluruh isi disimpan di localStorage.</p>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-primary" id="exportNotifications">
                        <i class="bi bi-download"></i> Ekspor CSV
                    </button>
                    <button type="button" class="btn btn-ghost" id="clearNotifications">
                        <i class="bi bi-trash"></i> Bersihkan Outbox
                    </button>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-envelope-paper"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statNotifTotal">0</div>
                        <div class="stat-card-label">Total Notifikasi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statNotifToday">0</div>
                        <div class="stat-card-label">Terkirim Hari Ini</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="statNotifRecipients">0</div>
                        <div class="stat-card-label">Penerima Unik</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Filter Notifikasi</h2>
                </div>
                <div class="dashboard-section-body">
                    <form id="notificationFilterForm">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="notificationSearch">Cari</label>
                                <input class="form-input" type="search" id="notificationSearch" placeholder="Penerima, subjek, atau isi...">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="filterType">Jenis</label>
                                <select class="form-input" id="filterType">
                                    <option value="">Semua jenis</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="filterRecipient">Penerima</label>
                                <select class="form-input" id="filterRecipient">
                                    <option value="">Semua penerima</option>
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
                            <button type="button" class="btn btn-ghost" id="resetNotificationFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Outbox Notifikasi</h2>
                    <span id="notificationCount" style="color: var(--text-secondary); font-size: 0.875rem;"></span>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Penerima</th>
                                <th>Jenis</th>
                                <th>Subjek &amp; Isi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="notificationTable"></tbody>
                    </table>
                </div>
            </div>
        </main>
<?php end_layout(); ?>
