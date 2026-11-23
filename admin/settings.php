<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/settings',
    'roles' => ['admin'],
    'title' => 'Pengaturan - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['@crud', 'crud-academic', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Pengaturan Sistem</h1>
                    <p class="dashboard-subtitle">Konfigurasi umum aplikasi LMS</p>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Pengaturan Umum</h2>
                </div>
                <div class="dashboard-section-body">
                    <div style="display: flex; flex-direction: column; gap: 1.25rem; max-width: 600px;">
                        <div class="form-group">
                            <label class="form-label">Nama Aplikasi</label>
                            <input type="text" class="form-input" value="ELIPS LMS">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Deskripsi Aplikasi</label>
                            <textarea class="form-input" rows="3">Platform Learning Management System untuk pelatihan dan kursus</textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Kontak</label>
                            <input type="email" class="form-input" value="admin@elipslms.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="tel" class="form-input" value="081234567890">
                        </div>
                        <button class="btn btn-primary" style="align-self: flex-start;" onclick="alert('Pengaturan berhasil disimpan!')">
                            <i class="bi bi-save"></i>
                            Simpan Pengaturan
                        </button>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 class="dashboard-section-title">Manajemen Cabang</h2>
                        <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--text-secondary);">Cabang dipakai sebagai konteks kelas, jadwal, dan kalender akademik.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openBranchModal()">
                        <i class="bi bi-plus-lg"></i>
                        Tambah Cabang
                    </button>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Cabang</th>
                                <th>Kota</th>
                                <th>Manajer</th>
                                <th>Telepon</th>
                                <th>Kelas</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="branchTable"></tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Pengaturan Sertifikat</h2>
                </div>
                <div class="dashboard-section-body">
                    <div style="display: flex; flex-direction: column; gap: 1.25rem; max-width: 600px;">
                        <div class="form-group">
                            <label class="form-label">Format Nomor Sertifikat</label>
                            <input type="text" class="form-input" value="CERT-YYYY-XXXXXX">
                            <small style="color: var(--text-secondary); font-size: 0.8125rem;">YYYY = Tahun, XXXXXX = Nomor urut acak</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Penandatangan 1</label>
                            <input type="text" class="form-input" value="Ahmad Fauzi, M.M.">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jabatan Penandatangan 1</label>
                            <input type="text" class="form-input" value="Instruktur">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Penandatangan 2</label>
                            <input type="text" class="form-input" value="Dr. H. Suryanto, M.Pd.">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jabatan Penandatangan 2</label>
                            <input type="text" class="form-input" value="Direktur">
                        </div>
                        <button class="btn btn-primary" style="align-self: flex-start;" onclick="alert('Pengaturan sertifikat berhasil disimpan!')">
                            <i class="bi bi-save"></i>
                            Simpan Pengaturan
                        </button>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 0.75rem;">
                    <h2 class="dashboard-section-title">Predikat Nilai</h2>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline" onclick="addPredicateRow()"><i class="bi bi-plus-lg"></i> Rentang</button>
                        <button type="button" class="btn btn-outline" onclick="resetPredicateRanges()"><i class="bi bi-arrow-counterclockwise"></i> Default A-E</button>
                        <button type="button" class="btn btn-primary" onclick="savePredicates()"><i class="bi bi-save"></i> Simpan Predikat</button>
                    </div>
                </div>
                <div class="dashboard-section-body">
                    <p class="form-hint" style="margin-bottom: 0.75rem;">
                        Rentang predikat harus menutup skala 0-100 tanpa celah dan tanpa tumpang tindih.
                        Perubahan berlaku untuk perhitungan nilai berikutnya; nilai yang sudah difinalisasi tetap memakai huruf saat finalisasi.
                    </p>
                    <div style="overflow-x: auto;">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Huruf</th>
                                    <th>Label</th>
                                    <th>Skor minimum</th>
                                    <th>Skor maksimum</th>
                                    <th style="text-align: center;">Aktif</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="predicateTableBody"></tbody>
                        </table>
                    </div>
                    <div id="predicateCoverage" class="form-hint" style="margin-top: 0.5rem; font-weight: 600;"></div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Pengaturan Pembelajaran</h2>
                </div>
                <div class="dashboard-section-body">
                    <div style="display: flex; flex-direction: column; gap: 1rem; max-width: 600px;">
                        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                            <input type="checkbox" checked style="width: 18px; height: 18px;">
                            <div>
                                <div style="font-weight: 500; color: var(--text-primary);">Penerbitan sertifikat otomatis</div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Terbitkan sertifikat otomatis saat peserta menyelesaikan 100% kursus</div>
                            </div>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                            <input type="checkbox" checked style="width: 18px; height: 18px;">
                            <div>
                                <div style="font-weight: 500; color: var(--text-primary);">Izinkan pendaftaran mandiri</div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Peserta bisa mendaftar kursus sendiri tanpa persetujuan admin</div>
                            </div>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                            <input type="checkbox" style="width: 18px; height: 18px;">
                            <div>
                                <div style="font-weight: 500; color: var(--text-primary);">Wajib verifikasi email</div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Peserta harus verifikasi email saat mendaftar</div>
                            </div>
                        </label>
                        <button class="btn btn-primary" style="align-self: flex-start; margin-top: 0.5rem;" onclick="alert('Pengaturan pembelajaran berhasil disimpan!')">
                            <i class="bi bi-save"></i>
                            Simpan Pengaturan
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Cabang -->
    <div id="branchModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="branchModalTitle">Tambah Cabang</h3>
                <button onclick="closeBranchModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" id="branchId">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Kode Cabang</label>
                        <input type="text" id="branchCode" class="form-input" placeholder="CBG-JKT (otomatis bila kosong)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="branchStatus" class="form-input">
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Cabang *</label>
                    <input type="text" id="branchName" class="form-input" placeholder="Cabang Jakarta">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Kota</label>
                        <input type="text" id="branchCity" class="form-input" placeholder="Jakarta">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telepon</label>
                        <input type="text" id="branchPhone" class="form-input" placeholder="021-5550101">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat</label>
                    <input type="text" id="branchAddress" class="form-input" placeholder="Jl. Jenderal Sudirman No. 21">
                </div>
                <div class="form-group">
                    <label class="form-label">Manajer Cabang</label>
                    <input type="text" id="branchManager" class="form-input" placeholder="Nama penanggung jawab">
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea id="branchNotes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeBranchModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveBranch()">Simpan</button>
            </div>
        </div>
<?php end_layout(); ?>
