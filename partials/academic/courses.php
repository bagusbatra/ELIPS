<?php
// Direktori: partials/academic/courses.php
// Isi halaman Kelola Kursus (dipakai bersama oleh admin dan karyawan Operasional).
// Logika: assets/js/pages/admin/courses.js | Gaya: assets/css/academic.css
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Kelola Kursus</h1>
                        <p class="dashboard-subtitle">Buat kursus, atur harga dan tentor, lalu publikasikan. Kursus yang sudah pernah dipakai tidak dihapus, cukup diarsipkan.</p>
                    </div>
                    <button class="btn btn-primary" type="button" id="courseAddBtn"><i class="bi bi-plus-lg"></i> Tambah Kursus</button>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue"><i class="bi bi-journal-bookmark"></i></div>
                    <div class="stat-card-info"><div class="stat-card-value" id="crsStatAll">0</div><div class="stat-card-label">Total Kursus</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green"><i class="bi bi-broadcast"></i></div>
                    <div class="stat-card-info"><div class="stat-card-value" id="crsStatPublished">0</div><div class="stat-card-label">Dipublikasi</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange"><i class="bi bi-pencil-square"></i></div>
                    <div class="stat-card-info"><div class="stat-card-value" id="crsStatDraft">0</div><div class="stat-card-label">Draft</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple"><i class="bi bi-archive"></i></div>
                    <div class="stat-card-info"><div class="stat-card-value" id="crsStatArchived">0</div><div class="stat-card-label">Diarsipkan</div></div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Daftar Kursus</h2>
                    <div class="crs-toolbar">
                        <div class="crs-tabs" id="crsTabs" role="tablist">
                            <button type="button" class="crs-tab is-active" data-filter="all">Semua</button>
                            <button type="button" class="crs-tab" data-filter="published">Dipublikasi</button>
                            <button type="button" class="crs-tab" data-filter="draft">Draft</button>
                            <button type="button" class="crs-tab" data-filter="archived">Arsip</button>
                        </div>
                        <input type="text" id="searchCourse" class="form-input crs-search" placeholder="Cari kursus...">
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Kursus</th>
                                <th>Tentor</th>
                                <th>Harga</th>
                                <th>Isi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="coursesTableBody"></tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Modal Tambah/Edit Kursus -->
        <div id="courseModal" class="attendance-modal crs-modal" hidden>
            <div class="attendance-modal-card">
                <div class="attendance-modal-header">
                    <div>
                        <h3 class="attendance-modal-title" id="courseModalTitle">Tambah Kursus Baru</h3>
                        <p class="attendance-modal-subtitle">Kolom bertanda * wajib diisi.</p>
                    </div>
                    <button type="button" class="attendance-modal-close" id="courseModalClose" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="attendance-modal-body">
                    <p class="reg-inline-error" id="courseError" hidden></p>

                    <div class="crs-image-row">
                        <div class="crs-image-preview" id="courseImagePreview"><i class="bi bi-image"></i></div>
                        <div>
                            <label class="form-label">Gambar kursus</label>
                            <div class="crs-image-actions">
                                <label class="btn btn-outline" for="courseImageFile"><i class="bi bi-upload"></i> Pilih gambar</label>
                                <button type="button" class="btn btn-ghost" id="courseImageRemove" hidden><i class="bi bi-trash"></i> Hapus</button>
                            </div>
                            <input type="file" id="courseImageFile" accept="image/jpeg,image/png,image/webp" hidden>
                            <small class="form-hint">JPG, PNG, atau WebP, maksimal 2 MB (diperkecil otomatis).</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="courseTitle">Judul kursus *</label>
                        <input type="text" id="courseTitle" class="form-input" maxlength="200" placeholder="Masukkan judul kursus">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="courseCategory">Kategori *</label>
                        <input type="text" id="courseCategory" class="form-input" list="courseCategoryList" placeholder="Pelatihan / Soft Skill">
                        <datalist id="courseCategoryList"></datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="courseDesc">Deskripsi</label>
                        <textarea id="courseDesc" class="form-input" rows="3" placeholder="Deskripsi singkat kursus"></textarea>
                    </div>
                    <div class="crs-grid">
                        <div class="form-group">
                            <label class="form-label" for="coursePrice">Harga awal (Rp) *</label>
                            <input type="number" id="coursePrice" class="form-input" min="0" step="1000" placeholder="1200000">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="courseDiscount">Harga diskon (Rp) <span class="form-hint" style="display:inline">(jika ada)</span></label>
                            <input type="number" id="courseDiscount" class="form-input" min="0" step="1000" placeholder="Kosongkan bila tidak ada">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="courseMeetings">Jumlah pertemuan</label>
                            <input type="number" id="courseMeetings" class="form-input" min="0" max="999" placeholder="12">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="courseLocation">Lokasi</label>
                        <input type="text" id="courseLocation" class="form-input" maxlength="150" placeholder="Online / Cabang Bandung">
                    </div>
                </div>
                <div class="attendance-modal-actions">
                    <button type="button" class="btn btn-ghost" id="courseCancelBtn">Batal</button>
                    <button type="button" class="btn btn-primary" id="courseSaveBtn"><i class="bi bi-check2"></i> Simpan Kursus</button>
                </div>
            </div>
        </div>

        <!-- Modal Ubah Angkatan -->
        <div id="clsModal" class="attendance-modal crs-modal" hidden>
            <div class="attendance-modal-card">
                <div class="attendance-modal-header">
                    <div>
                        <h3 class="attendance-modal-title" id="clsModalTitle">Ubah Angkatan</h3>
                        <p class="attendance-modal-subtitle" id="clsModalSub"></p>
                    </div>
                    <button type="button" class="attendance-modal-close" id="clsModalClose" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="attendance-modal-body">
                    <p class="reg-inline-error" id="clsError" hidden></p>
                    <div class="form-group">
                        <label class="form-label" for="clsName">Nama angkatan *</label>
                        <input type="text" id="clsName" class="form-input" maxlength="150">
                    </div>
                    <div class="crs-grid">
                        <div class="form-group">
                            <label class="form-label" for="clsStart">Tanggal mulai</label>
                            <input type="date" id="clsStart" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Perkiraan selesai</label>
                            <div class="form-input" id="clsEnd" style="background:#faf6f1;color:#6b5a4d;"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="clsQuota">Kuota peserta</label>
                            <input type="number" id="clsQuota" class="form-input" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="clsTentor">Tentor</label>
                            <select id="clsTentor" class="form-input"></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="clsLocation">Lokasi</label>
                            <input type="text" id="clsLocation" class="form-input" maxlength="150">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="clsRoom">Ruang</label>
                            <input type="text" id="clsRoom" class="form-input" maxlength="100">
                        </div>
                    </div>
                </div>
                <div class="attendance-modal-actions">
                    <button type="button" class="btn btn-ghost" id="clsCancelBtn">Batal</button>
                    <button type="button" class="btn btn-primary" id="clsSaveBtn"><i class="bi bi-check2"></i> Simpan</button>
                </div>
            </div>
        </div>