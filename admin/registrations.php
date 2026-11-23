<?php
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
                        <p class="dashboard-subtitle">Data calon siswa dari formulir “Daftar Sekarang”. Setelah diverifikasi, pendaftaran dapat dijadikan akun siswa (role peserta).</p>
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
                        <div class="stat-card-label">Menunggu Diverifikasi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="regStatTerdaftar">0</div>
                        <div class="stat-card-label">Sudah Menjadi Siswa</div>
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

    <!-- Konversi menjadi siswa -->
    <div class="attendance-modal" id="regConvertModal" style="display: none;">
        <div class="attendance-modal-card">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title">Jadikan Siswa</div>
                    <div class="attendance-modal-subtitle">Ubah pendaftaran menjadi akun dengan role peserta.</div>
                </div>
                <button class="attendance-modal-close" type="button" onclick="closeRegConvertModal()" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="regConvertForm">
                <div class="attendance-modal-body">
                    <input type="hidden" id="regConvertId">
                    <div class="reg-convert-info" id="regConvertInfo"></div>
                    <p class="reg-inline-error" id="regConvertError" role="alert" hidden></p>
                    <div class="form-group" id="regPasswordGroup">
                        <label class="form-label" for="regConvertPassword">Password awal siswa</label>
                        <input class="form-input" type="password" id="regConvertPassword" minlength="8"
                               placeholder="Minimal 8 karakter" autocomplete="new-password">
                        <div class="form-hint">Password ini hanya dipakai jika akun dengan email tersebut belum ada.</div>
                    </div>
                </div>
                <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                    <button class="btn btn-outline" type="button" onclick="closeRegConvertModal()">Batal</button>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-person-check"></i>
                        Jadikan Siswa
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php end_layout(); ?>
