<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/letters',
    'roles' => ['admin'],
    'title' => 'Surat Menyurat - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['ui', 'letter-archive'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Surat Menyurat</h1>
                        <p class="dashboard-subtitle">Catat, telusuri, dan kelola surat masuk maupun surat keluar beserta detailnya.</p>
                    </div>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <button class="btn btn-outline" id="letterRefreshBtn" type="button">
                            <i class="bi bi-arrow-clockwise"></i>
                            Muat Ulang
                        </button>
                        <button class="btn btn-primary" id="letterAddBtn" type="button">
                            <i class="bi bi-plus-lg"></i>
                            Tambah Surat
                        </button>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Filter Surat</h2>
                    <span class="recap-range-label">Arsip surat masuk &amp; keluar</span>
                </div>
                <div class="dashboard-section-body">
                    <form class="recap-filter" id="letterFilterForm">
                        <div class="form-group">
                            <label class="form-label" for="letterDirection">Jenis Surat</label>
                            <select class="form-input" id="letterDirection" name="direction">
                                <option value="">Semua jenis</option>
                                <option value="masuk">Surat Masuk</option>
                                <option value="keluar">Surat Keluar</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="letterSearch">Cari</label>
                            <input class="form-input" type="search" id="letterSearch" name="search"
                                maxlength="100" placeholder="Nomor, perihal, pengirim, penerima">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="letterFrom">Dari Tanggal</label>
                            <input class="form-input" type="date" id="letterFrom" name="from">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="letterTo">Sampai Tanggal</label>
                            <input class="form-input" type="date" id="letterTo" name="to">
                        </div>
                        <div class="recap-filter-actions">
                            <button class="btn btn-outline" type="button" id="letterResetBtn">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                Reset
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
                        <i class="bi bi-envelope-paper"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="letterStatTotal">0</div>
                        <div class="stat-card-label">Total Surat</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="letterStatMasuk">0</div>
                        <div class="stat-card-label">Surat Masuk</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-box-arrow-up"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="letterStatKeluar">0</div>
                        <div class="stat-card-label">Surat Keluar</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Daftar Surat</h2>
                </div>
                <div class="dashboard-section-body" style="padding: 0;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Nomor &amp; Perihal</th>
                                <th>Jenis</th>
                                <th>Pengirim / Penerima</th>
                                <th>Sifat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="letterTableBody">
                            <tr>
                                <td colspan="6">
                                    <div class="attendance-empty">Memuat daftar surat...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div class="attendance-modal" id="letterModal" style="display: none;">
        <div class="attendance-modal-card letter-modal-card">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title" id="letterModalTitle">Tambah Surat</div>
                    <div class="attendance-modal-subtitle">Lengkapi detail surat masuk atau surat keluar.</div>
                </div>
                <button class="attendance-modal-close" id="letterModalClose" type="button" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="letterForm">
                <div class="attendance-modal-body letter-modal-body">
                    <input type="hidden" id="letterId">
                    <div class="form-group">
                        <label class="form-label" for="letterFormDirection">Jenis Surat</label>
                        <select class="form-input" id="letterFormDirection" required>
                            <option value="masuk">Surat Masuk</option>
                            <option value="keluar">Surat Keluar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="letterFormNumber">Nomor Surat</label>
                        <input class="form-input" type="text" id="letterFormNumber" maxlength="100"
                            placeholder="Contoh: 021/ELIPS/XI/2026" required>
                    </div>
                    <div class="form-group letter-span-2">
                        <label class="form-label" for="letterFormSubject">Perihal</label>
                        <input class="form-input" type="text" id="letterFormSubject" maxlength="255"
                            placeholder="Ringkas isi surat" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="letterFormDate">Tanggal Surat</label>
                        <input class="form-input" type="date" id="letterFormDate" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="letterFormHandled" id="letterHandledLabel">Tanggal Diterima</label>
                        <input class="form-input" type="date" id="letterFormHandled">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="letterFormSender">Pengirim</label>
                        <input class="form-input" type="text" id="letterFormSender" maxlength="150"
                            placeholder="Contoh: Dinas Pendidikan" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="letterFormRecipient">Penerima</label>
                        <input class="form-input" type="text" id="letterFormRecipient" maxlength="150"
                            placeholder="Contoh: ELIPS LMS" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="letterFormCategory">Kategori</label>
                        <input class="form-input" type="text" id="letterFormCategory" maxlength="80"
                            list="letterCategoryOptions" placeholder="Umum, Undangan, Permohonan...">
                        <datalist id="letterCategoryOptions">
                            <option value="Umum"></option>
                            <option value="Undangan"></option>
                            <option value="Permohonan"></option>
                            <option value="Pemberitahuan"></option>
                            <option value="Laporan"></option>
                            <option value="Keuangan"></option>
                            <option value="Kepegawaian"></option>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="letterFormClassification">Sifat Surat</label>
                        <select class="form-input" id="letterFormClassification">
                            <option value="biasa">Biasa</option>
                            <option value="penting">Penting</option>
                            <option value="segera">Segera</option>
                            <option value="rahasia">Rahasia</option>
                        </select>
                    </div>
                    <div class="shift-form-hint letter-span-2" id="letterDirectionHint">
                        Surat masuk: catat pengirim eksternal dan penerima internal, lalu simpan tanggal diterima.
                    </div>
                </div>
                <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                    <button class="btn btn-outline" id="letterModalCancel" type="button">Batal</button>
                    <button class="btn btn-primary" id="letterFormSubmit" type="submit">
                        <i class="bi bi-check2-circle"></i>
                        Simpan Surat
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="attendance-modal" id="letterDetailModal" style="display: none;">
        <div class="attendance-modal-card attendance-detail-card letter-detail-card">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title" id="letterDetailTitle">Detail Surat</div>
                    <div class="attendance-modal-subtitle" id="letterDetailSubtitle">-</div>
                </div>
                <button class="attendance-modal-close" id="letterDetailClose" type="button" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="letter-detail-body" id="letterDetailBody"></div>
            <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                <button class="btn btn-outline" id="letterDetailDone" type="button">Tutup</button>
                <button class="btn btn-primary" id="letterDetailEdit" type="button">
                    <i class="bi bi-pencil"></i>
                    Ubah Surat
                </button>
            </div>
        </div>
<?php end_layout(); ?>
