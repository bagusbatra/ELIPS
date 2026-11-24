<?php
// Direktori: admin/dashboard.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/dashboard',
    'roles' => ['admin'],
    'title' => 'Dashboard Admin - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['@crud', 'ui', 'attendance'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Dashboard Admin</h1>
                <p class="dashboard-subtitle">Ringkasan keseluruhan sistem LMS</p>
            </div>

            <section class="attendance-hero" aria-labelledby="attendanceHeroTitle">
                <div class="attendance-hero-content">
                    <div class="attendance-hero-icon">
                        <i class="bi bi-camera-video-fill"></i>
                    </div>
                    <div class="attendance-hero-text">
                        <h2 class="attendance-hero-title" id="attendanceHeroTitle">Absensi Kehadiran</h2>
                        <p class="attendance-hero-description">
                            Klik tombol absen untuk membuka kamera. Foto, jam, lokasi, dan informasi
                            perangkat akan direkam otomatis sebagai bukti kehadiran.
                        </p>
                        <div class="attendance-hero-status" id="attendanceHeroStatus">
                            Memuat status absensi hari ini&hellip;
                        </div>
                        <div class="attendance-hero-today">
                            <span class="attendance-pill" id="attendanceTodayIn">
                                <i class="bi bi-box-arrow-in-right"></i> Masuk: &ndash;
                            </span>
                            <span class="attendance-pill" id="attendanceTodayOut">
                                <i class="bi bi-box-arrow-right"></i> Pulang: &ndash;
                            </span>
                        </div>
                    </div>
                </div>
                <div class="attendance-hero-actions" id="attendanceHeroActions">
                    <button type="button" class="btn btn-primary btn-lg" id="attendanceBtnIn"
                        onclick="openAttendanceModal('in')" style="display: none;">
                        <i class="bi bi-camera"></i> Absen Masuk
                    </button>
                    <button type="button" class="btn btn-primary btn-lg" id="attendanceBtnOut"
                        onclick="openAttendanceModal('out')" style="display: none;">
                        <i class="bi bi-camera-fill"></i> Absen Pulang
                    </button>
                    <div class="attendance-done" id="attendanceDone" style="display: none;">
                        <i class="bi bi-check-circle-fill"></i> Absensi hari ini lengkap
                    </div>
                </div>
            </section>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value">4</div>
                        <div class="stat-card-label">Total Kursus</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value">3</div>
                        <div class="stat-card-label">Total Pengguna</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value">2</div>
                        <div class="stat-card-label">Enrolment Aktif</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-award"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value">0</div>
                        <div class="stat-card-label">Sertifikat Terbit</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section" id="attendanceHistorySection">
                <div class="dashboard-section-header">
                    <div>
                        <h2 class="dashboard-section-title">Riwayat Absensi Saya</h2>
                        <p class="attendance-history-hint">
                            Catatan terbaru lengkap dengan foto dan metadata perangkat.
                        </p>
                    </div>
                    <div class="attendance-history-tools">
                        <span class="attendance-history-count" id="attendanceHistoryCount">0 catatan</span>
                        <button type="button" class="btn btn-ghost" onclick="loadAttendanceHistory()">
                            <i class="bi bi-arrow-clockwise"></i> Muat Ulang
                        </button>
                    </div>
                </div>
                <div class="dashboard-section-body">
                    <div class="attendance-history" id="attendanceHistory">
                        <div class="attendance-empty">Memuat riwayat absensi&hellip;</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Daftar Pengguna</h2>
                    <a href="<?= url('admin/users.php') ?>" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i>
                        Tambah Pengguna
                    </a>
                </div>
                <div class="dashboard-section-body" style="padding: 0;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="table-course">
                                        <div class="dashboard-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">BS</div>
                                        <div class="table-course-info">
                                            <div class="table-course-title">Budi Santoso</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span>budi@example.com</span>
                                </td>
                                <td>
                                    <span class="badge badge-primary">Peserta</span>
                                </td>
                                <td>
                                    <span class="badge badge-success">Aktif</span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="<?= url('admin/users.php') ?>" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="table-course">
                                        <div class="dashboard-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">AF</div>
                                        <div class="table-course-info">
                                            <div class="table-course-title">Ahmad Fauzi, M.M.</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span>ahmad@example.com</span>
                                </td>
                                <td>
                                    <span class="badge badge-warning">Tentor</span>
                                </td>
                                <td>
                                    <span class="badge badge-success">Aktif</span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="<?= url('admin/users.php') ?>" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="table-course">
                                        <div class="dashboard-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">AD</div>
                                        <div class="table-course-info">
                                            <div class="table-course-title">Administrator</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span>admin@example.com</span>
                                </td>
                                <td>
                                    <span class="badge" style="background: #fee2e2; color: #991b1b;">Admin</span>
                                </td>
                                <td>
                                    <span class="badge badge-success">Aktif</span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="<?= url('admin/users.php') ?>" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <h2 class="dashboard-section-title">Kursus Terbaru</h2>
                    <a href="<?= url('admin/courses.php') ?>" class="btn btn-ghost">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="dashboard-section-body" style="padding: 0;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Kursus</th>
                                <th>Tentor</th>
                                <th>Peserta</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="table-course">
                                        <div class="table-course-img"></div>
                                        <div class="table-course-info">
                                            <div class="table-course-title">Pelatihan Manajemen Dasar</div>
                                            <div class="table-course-meta">8 Modul • 24 Pelajaran</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span>Ahmad Fauzi, M.M.</span>
                                </td>
                                <td>
                                    <span>2</span>
                                </td>
                                <td>
                                    <span class="badge badge-success">Dipublikasi</span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="table-course">
                                        <div class="table-course-img"></div>
                                        <div class="table-course-info">
                                            <div class="table-course-title">Komunikasi Efektif di Tempat Kerja</div>
                                            <div class="table-course-meta">6 Modul • 18 Pelajaran</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span>Siti Nurhaliza, S.Psi.</span>
                                </td>
                                <td>
                                    <span>0</span>
                                </td>
                                <td>
                                    <span class="badge badge-warning">Draft</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Absensi: kamera, foto, dan catatan -->
    <div id="attendanceModal" class="attendance-modal" style="display: none;">
        <div class="attendance-modal-card attendance-capture-card" role="dialog" aria-modal="true"
            aria-labelledby="attendanceModalTitle">
            <div class="attendance-modal-header">
                <div>
                    <h3 class="attendance-modal-title" id="attendanceModalTitle">Absen Masuk</h3>
                    <p class="attendance-modal-subtitle" id="attendanceModalSubtitle">
                        Ambil foto melalui kamera atau unggah foto sebagai alternatif.
                    </p>
                </div>
            </div>
            <div class="attendance-modal-body attendance-capture-body">
                <div class="attendance-camera-panel">
                    <div class="attendance-camera-stage">
                        <video id="attendanceVideo" autoplay playsinline muted style="display: none;"></video>
                        <canvas id="attendanceCanvas" style="display: none;"></canvas>
                        <img id="attendancePreview" alt="Pratinjau foto absensi" style="display: none;">
                        <div class="attendance-camera-placeholder" id="attendanceCameraPlaceholder">
                            <i class="bi bi-camera-video"></i>
                            <p>Kamera belum aktif</p>
                            <small>Klik &ldquo;Nyalakan Kamera&rdquo; atau unggah foto sebagai alternatif.</small>
                        </div>
                        <div class="attendance-camera-marker" id="attendanceCameraMarker" style="display: none;">
                            <span><i class="bi bi-record-circle"></i> Kamera aktif</span>
                        </div>
                    </div>
                    <div class="attendance-camera-controls">
                        <button type="button" class="btn btn-primary" id="attendanceCaptureBtn"
                            onclick="captureAttendancePhoto()" disabled>
                            <i class="bi bi-camera"></i> Ambil Foto
                        </button>
                        <button type="button" class="btn btn-ghost" id="attendanceRetakeBtn"
                            onclick="retakeAttendancePhoto()" style="display: none;">
                            <i class="bi bi-arrow-counterclockwise"></i> Ulangi
                        </button>
                        <button type="button" class="btn btn-outline" id="attendanceStartBtn"
                            onclick="startAttendanceCamera()">
                            <i class="bi bi-camera-video"></i> Nyalakan Kamera
                        </button>
                        <label class="btn btn-ghost attendance-upload-btn">
                            <i class="bi bi-upload"></i> Unggah Foto
                            <input type="file" id="attendanceFile" accept="image/*" capture="user"
                                onchange="handleAttendanceFile(event)" hidden>
                        </label>
                    </div>
                </div>
                <form class="attendance-meta-panel" id="attendanceForm" onsubmit="return saveStaffAttendance(event)">
                    <div class="form-group">
                        <label class="form-label" for="attendanceNotes">Catatan</label>
                        <textarea id="attendanceNotes" class="form-input" rows="2" maxlength="2000"
                            placeholder="Catatan tambahan (opsional)"></textarea>
                    </div>
                    <div class="attendance-modal-actions">
                        <button type="button" class="btn btn-ghost" onclick="closeAttendanceModal()">Batal</button>
                        <button type="submit" class="btn btn-primary" id="attendanceSaveBtn" disabled>
                            <i class="bi bi-check2-circle"></i> Simpan Absensi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detail Absensi -->
    <div id="attendanceDetailModal" class="attendance-modal" style="display: none;">
        <div class="attendance-modal-card attendance-detail-card" role="dialog" aria-modal="true"
            aria-labelledby="attendanceDetailTitle">
            <div class="attendance-modal-header">
                <div>
                    <h3 class="attendance-modal-title" id="attendanceDetailTitle">Detail Absensi</h3>
                    <p class="attendance-modal-subtitle" id="attendanceDetailSubtitle">Metadata lengkap catatan absensi.</p>
                </div>
                <button type="button" class="attendance-modal-close" onclick="closeAttendanceDetail()" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="attendance-detail-body" id="attendanceDetailBody"></div>
        </div>
    </div>

    <!-- Modal Absensi Berhasil: hanya bisa ditutup lewat tombol Selesai -->
    <div id="attendanceSuccessModal" class="attendance-modal" style="display: none;">
        <div class="attendance-modal-card attendance-detail-card attendance-success-card" role="alertdialog"
            aria-modal="true" aria-labelledby="attendanceSuccessTitle">
            <div class="attendance-success-header">
                <div class="attendance-success-icon"><i class="bi bi-check-lg"></i></div>
                <h3 class="attendance-modal-title" id="attendanceSuccessTitle">Absensi Berhasil Disimpan</h3>
                <p class="attendance-modal-subtitle" id="attendanceSuccessSubtitle"></p>
            </div>
            <div class="attendance-detail-body" id="attendanceSuccessBody"></div>
            <div class="attendance-success-footer">
                <button type="button" class="btn btn-primary btn-lg" onclick="closeAttendanceSuccess()">Selesai</button>
            </div>
        </div>
<?php end_layout(); ?>