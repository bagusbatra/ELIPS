<?php
// Direktori: admin/registrations.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/registrations',
    'roles' => ['admin'],
    'title' => 'Pendaftaran Siswa - ELIPS LMS',
    'area' => 'admin',
    'css' => ['admin-registrations'],
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Pendaftaran Siswa</h1>
                        <p class="dashboard-subtitle">Data calon siswa dari formulir pendaftaran. Tekan “Kirim link aktivasi” lalu kirim link-nya lewat WhatsApp; akun aktif setelah siswa mengisi biodata dan membuat sandi.</p>
                    </div>
                    <button class="btn btn-primary" type="button" onclick="refreshRegistrations()">
                        <i class="bi bi-arrow-clockwise"></i>
                        Muat ulang
                    </button>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-person-lines-fill"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="regStatTotal">0</div>
                        <div class="stat-card-label">Total Pendaftar</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="regStatBaru">0</div>
                        <div class="stat-card-label">Pendaftar Baru</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="regStatTerdaftar">0</div>
                        <div class="stat-card-label">Akun Sudah Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="regStatKursus">0</div>
                        <div class="stat-card-label">Pelatihan Dipinati</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Daftar Pendaftaran</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <select class="form-input" id="filterRegCourse" style="max-width: 240px;" onchange="renderRegistrations()">
                            <option value="all">Semua Pelatihan</option>
                        </select>
                        <select class="form-input" id="filterRegStatus" style="max-width: 170px;" onchange="renderRegistrations()">
                            <option value="all">Semua Status</option>
                            <option value="baru">Baru</option>
                            <option value="dihubungi">Dihubungi</option>
                            <option value="menunggu">Menunggu aktivasi</option>
                            <option value="terdaftar">Terdaftar</option>
                            <option value="batal">Batal</option>
                        </select>
                        <input type="text" id="searchRegistrations" class="form-input" style="max-width: 220px;"
                               placeholder="Cari nama, email, atau telepon..." onkeyup="renderRegistrations()">
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Calon Siswa</th>
                                <th>Pelatihan</th>
                                <th>Tempat / Tanggal Lahir</th>
                                <th>Alamat</th>
                                <th>Status</th>
                                <th>Dikirim</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="registrationsTable"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Detail pendaftaran -->
    <div class="attendance-modal" id="regDetailModal" style="display: none;">
        <div class="attendance-modal-card attendance-detail-card">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title" id="regDetailTitle">Detail Pendaftaran</div>
                    <div class="attendance-modal-subtitle" id="regDetailSubtitle">Data calon siswa dari formulir publik.</div>
                </div>
                <button class="attendance-modal-close" type="button" onclick="closeRegDetailModal()" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="attendance-modal-body" id="regDetailBody"></div>
            <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                <button class="btn btn-outline" type="button" onclick="closeRegDetailModal()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Link aktivasi -->
    <div class="attendance-modal" id="regLinkModal" style="display: none;">
        <div class="attendance-modal-card">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title" id="regLinkTitle">Link Aktivasi</div>
                    <div class="attendance-modal-subtitle" id="regLinkSubtitle"></div>
                </div>
                <button class="attendance-modal-close" type="button" onclick="closeRegLinkModal()" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="attendance-modal-body">
                <div class="reg-convert-info" id="regLinkInfo"></div>
                <div class="form-group" id="regLinkGroup">
                    <label class="form-label" for="regLinkInput">Link unik (sekali pakai)</label>
                    <input class="form-input" type="text" id="regLinkInput" readonly>
                    <div class="form-hint">Link hanya tampil sekarang. Bila hilang, gunakan “Kirim ulang” untuk membuat link baru.</div>
                </div>
            </div>
            <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem; flex-wrap: wrap;">
                <button class="btn btn-outline" type="button" onclick="closeRegLinkModal()">Tutup</button>
                <button class="btn btn-outline" type="button" id="regLinkCopy"><i class="bi bi-clipboard"></i> Salin link</button>
                <a class="btn btn-primary" id="regLinkWa" href="#" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-whatsapp"></i> Kirim lewat WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Batalkan pendaftaran -->
    <div class="attendance-modal" id="regCancelModal" style="display: none;">
        <div class="attendance-modal-card">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title">Batalkan Pendaftaran</div>
                    <div class="attendance-modal-subtitle">Kursi kelas yang ditahan akan dilepas dan link aktivasi dicabut.</div>
                </div>
                <button class="attendance-modal-close" type="button" onclick="closeRegCancelModal()" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="regCancelForm">
                <div class="attendance-modal-body">
                    <div class="reg-convert-info" id="regCancelInfo"></div>
                    <p class="reg-inline-error" id="regCancelError" role="alert" hidden></p>
                    <div class="form-group">
                        <label class="form-label" for="regCancelReason">Alasan pembatalan</label>
                        <textarea class="form-input" id="regCancelReason" rows="3" maxlength="300" required></textarea>
                    </div>
                </div>
                <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                    <button class="btn btn-outline" type="button" onclick="closeRegCancelModal()">Kembali</button>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-x-circle"></i> Batalkan Pendaftaran</button>
                </div>
            </form>
        </div>
    </div>
<?php end_layout(); ?>