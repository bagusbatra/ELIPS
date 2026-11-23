<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'page_script' => 'employee/marketing/campaigns',
    'roles' => ['karyawan'],
    'divisions' => ['marketing'],
    'title' => 'Campaigns Marketing - ELIPS LMS',
    'scripts' => ['@crud', 'crud-marketing', 'ui', 'employee/marketing/campaigns'],
    'navbar' => 'employee-marketing',
    'sidebar' => 'employee-marketing',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Campaigns Marketing</h1>
                <p class="dashboard-subtitle" id="campaignGreeting">
                    Kelola campaign dan pantau capaian lead yang tertaut.
                </p>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-megaphone"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="campaignStatTotal">0</div>
                        <div class="stat-card-label">Total Campaign</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-play-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="campaignStatActive">0</div>
                        <div class="stat-card-label">Campaign Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="campaignStatLeads">0</div>
                        <div class="stat-card-label">Lead Terkumpul</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-patch-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="campaignStatEnrollments">0</div>
                        <div class="stat-card-label">Pendaftaran</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <div>
                        <h2 class="dashboard-section-title">Daftar Campaign</h2>
                        <p class="form-hint" style="margin: 0.25rem 0 0;">
                            Capaian dihitung otomatis dari lead yang ditautkan ke campaign.
                        </p>
                    </div>
                    <div class="modal-actions">
                        <button class="btn btn-ghost" type="button" id="campaignRefresh">
                            <i class="bi bi-arrow-clockwise"></i> Muat Ulang
                        </button>
                        <button class="btn btn-primary" type="button" id="campaignAddButton">
                            <i class="bi bi-plus-lg"></i> Tambah Campaign
                        </button>
                    </div>
                </div>
                <div class="dashboard-section-body">
                    <div class="campaign-toolbar">
                        <div class="campaign-search">
                            <i class="bi bi-search"></i>
                            <input class="form-input" type="search" id="campaignSearch" maxlength="100"
                                placeholder="Cari nama campaign, kanal, atau catatan">
                        </div>
                        <select class="form-input campaign-filter" id="campaignStatusFilter">
                            <option value="">Semua status</option>
                            <option value="draft">Draft</option>
                            <option value="aktif">Aktif</option>
                            <option value="selesai">Selesai</option>
                            <option value="batal">Batal</option>
                        </select>
                    </div>
                    <div class="campaign-grid" id="campaignList">
                        <div class="campaign-empty">Memuat campaign&hellip;</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <button class="dashboard-sidebar-toggle" id="campaignSidebarToggle" type="button" aria-label="Buka menu">
        <i class="bi bi-list"></i>
    </button>
    <div class="dashboard-sidebar-backdrop" id="campaignSidebarBackdrop"></div>

    <!-- Modal Campaign -->
    <div class="attendance-modal" id="campaignModal" style="display: none;">
        <div class="attendance-modal-card marketing-modal-card">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title" id="campaignModalTitle">Tambah Campaign</div>
                    <div class="attendance-modal-subtitle">Tetapkan kanal, periode, target, dan budget campaign.</div>
                </div>
                <button class="attendance-modal-close" id="campaignModalClose" type="button" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="campaignForm">
                <div class="attendance-modal-body marketing-modal-body">
                    <input type="hidden" id="campaignId">
                    <div class="form-group marketing-span-2">
                        <label class="form-label" for="campaignName">Nama Campaign *</label>
                        <input class="form-input" type="text" id="campaignName" maxlength="150"
                            placeholder="Contoh: Promo Kelas Bahasa Inggris November" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="campaignChannel">Kanal</label>
                        <select class="form-input" id="campaignChannel">
                            <option value="sosial_media">Sosial Media</option>
                            <option value="email">Email</option>
                            <option value="pameran">Pameran</option>
                            <option value="sekolah">Sekolah</option>
                            <option value="komunitas">Komunitas</option>
                            <option value="telemarketing">Telemarketing</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="campaignStatus">Status</label>
                        <select class="form-input" id="campaignStatus">
                            <option value="draft">Draft</option>
                            <option value="aktif">Aktif</option>
                            <option value="selesai">Selesai</option>
                            <option value="batal">Batal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="campaignStartDate">Tanggal Mulai</label>
                        <input class="form-input" type="date" id="campaignStartDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="campaignEndDate">Tanggal Selesai</label>
                        <input class="form-input" type="date" id="campaignEndDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="campaignBudget">Budget (Rp)</label>
                        <input class="form-input" type="number" id="campaignBudget" min="0" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="campaignTargetLeads">Target Lead</label>
                        <input class="form-input" type="number" id="campaignTargetLeads" min="0" step="1" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="campaignTargetEnrollments">Target Pendaftaran</label>
                        <input class="form-input" type="number" id="campaignTargetEnrollments" min="0" step="1" value="0">
                    </div>
                    <div class="form-group marketing-span-2">
                        <label class="form-label" for="campaignNotes">Catatan</label>
                        <textarea class="form-input marketing-textarea" id="campaignNotes" maxlength="1000"
                            rows="3" placeholder="Tujuan campaign, penawaran, atau catatan pelaksanaan."></textarea>
                    </div>
                </div>
                <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                    <button class="btn btn-outline" id="campaignCancel" type="button">Batal</button>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-check2-circle"></i> Simpan Campaign
                    </button>
                </div>
            </form>
        </div>
<?php end_layout(); ?>
