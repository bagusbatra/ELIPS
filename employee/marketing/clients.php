<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'page_script' => 'employee/marketing/clients',
    'roles' => ['karyawan'],
    'divisions' => ['marketing'],
    'title' => 'Clients Marketing - ELIPS LMS',
    'scripts' => ['@crud', 'crud-marketing', 'ui', 'employee/marketing/clients'],
    'navbar' => 'employee-marketing',
    'sidebar' => 'employee-marketing',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Clients Marketing</h1>
                <p class="dashboard-subtitle" id="clientGreeting">
                    Kelola data klien/pelanggan dan hubungannya dengan campaign.
                </p>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="clientStatTotal">0</div>
                        <div class="stat-card-label">Total Client</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="clientStatActive">0</div>
                        <div class="stat-card-label">Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="clientStatProspect">0</div>
                        <div class="stat-card-label">Prospek</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-person-dash"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="clientStatInactive">0</div>
                        <div class="stat-card-label">Nonaktif</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <div>
                        <h2 class="dashboard-section-title">Daftar Client</h2>
                        <p class="form-hint" style="margin: 0.25rem 0 0;">
                            Simpan data klien/pelanggan dan tautkan ke campaign bila relevan.
                        </p>
                    </div>
                    <div class="modal-actions">
                        <button class="btn btn-ghost" type="button" id="clientRefresh">
                            <i class="bi bi-arrow-clockwise"></i> Muat Ulang
                        </button>
                        <button class="btn btn-primary" type="button" id="clientAddButton">
                            <i class="bi bi-plus-lg"></i> Tambah Client
                        </button>
                    </div>
                </div>
                <div class="dashboard-section-body">
                    <div class="task-toolbar">
                        <div class="task-search">
                            <i class="bi bi-search"></i>
                            <input class="form-input" type="search" id="clientSearch" maxlength="120"
                                placeholder="Cari nama, perusahaan, PIC, kontak, atau kota">
                        </div>
                        <select class="form-input task-filter" id="clientStatusFilter">
                            <option value="">Semua status</option>
                            <option value="prospek">Prospek</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <div class="task-grid" id="clientList">
                        <div class="task-empty">Memuat client&hellip;</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <button class="dashboard-sidebar-toggle" id="clientSidebarToggle" type="button" aria-label="Buka menu">
        <i class="bi bi-list"></i>
    </button>
    <div class="dashboard-sidebar-backdrop" id="clientSidebarBackdrop"></div>

    <!-- Modal Client -->
    <div class="attendance-modal" id="clientModal" style="display: none;">
        <div class="attendance-modal-card marketing-modal-card">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title" id="clientModalTitle">Tambah Client</div>
                    <div class="attendance-modal-subtitle">Catat informasi klien/pelanggan dan statusnya.</div>
                </div>
                <button class="attendance-modal-close" id="clientModalClose" type="button" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="clientForm">
                <div class="attendance-modal-body marketing-modal-body">
                    <input type="hidden" id="clientId">
                    <div class="form-group marketing-span-2">
                        <label class="form-label" for="clientName">Nama Client / Perusahaan Utama *</label>
                        <input class="form-input" type="text" id="clientName" maxlength="150"
                            placeholder="Contoh: PT Maju Bersama" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="clientCompany">Perusahaan</label>
                        <input class="form-input" type="text" id="clientCompany" maxlength="150"
                            placeholder="Nama perusahaan (opsional)">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="clientPic">PIC / Kontak</label>
                        <input class="form-input" type="text" id="clientPic" maxlength="150"
                            placeholder="Nama penanggung jawab">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="clientPhone">Telepon</label>
                        <input class="form-input" type="text" id="clientPhone" maxlength="30"
                            placeholder="0812xxxxxxx">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="clientEmail">Email</label>
                        <input class="form-input" type="email" id="clientEmail" maxlength="190"
                            placeholder="nama@perusahaan.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="clientCity">Kota</label>
                        <input class="form-input" type="text" id="clientCity" maxlength="100"
                            placeholder="Contoh: Bandung">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="clientStatus">Status</label>
                        <select class="form-input" id="clientStatus">
                            <option value="prospek">Prospek</option>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <div class="form-group marketing-span-2">
                        <label class="form-label" for="clientAddress">Alamat</label>
                        <input class="form-input" type="text" id="clientAddress" maxlength="255"
                            placeholder="Alamat lengkap (opsional)">
                    </div>
                    <div class="form-group marketing-span-2">
                        <label class="form-label" for="clientCampaign">Campaign Terkait</label>
                        <select class="form-input" id="clientCampaign">
                            <option value="">Tanpa campaign</option>
                        </select>
                    </div>
                    <div class="form-group marketing-span-2">
                        <label class="form-label" for="clientNotes">Catatan</label>
                        <textarea class="form-input marketing-textarea" id="clientNotes" maxlength="1000"
                            rows="3" placeholder="Kebutuhan, riwayat singkat, atau catatan lain."></textarea>
                    </div>
                </div>
                <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                    <button class="btn btn-outline" id="clientCancel" type="button">Batal</button>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-check2-circle"></i> Simpan Client
                    </button>
                </div>
            </form>
        </div>
<?php end_layout(); ?>
