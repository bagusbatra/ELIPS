<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'admin/users',
    'roles' => ['admin'],
    'title' => 'Kelola Pengguna - ELIPS LMS',
    'area' => 'admin',
    'scripts' => ['@crud', 'ui'],
    'sidebar' => 'admin',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <h1 class="dashboard-title">Kelola Pengguna</h1>
                        <p class="dashboard-subtitle">Tambah Admin, Instruktur, Peserta, atau Karyawan. Instruktur juga bisa langsung ditugaskan ke kursus.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openUserTypeModal()">
                        <i class="bi bi-person-plus"></i>
                        Tambah Pengguna
                    </button>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header" style="flex-wrap: wrap; gap: 1rem;">
                    <h2 class="dashboard-section-title">Daftar Pengguna</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button class="btn btn-primary" id="filterAll" onclick="filterUsers('all')">Semua</button>
                            <button class="btn btn-ghost" id="filterAdmin" onclick="filterUsers('admin')">Admin</button>
                            <button class="btn btn-ghost" id="filterInstruktur" onclick="filterUsers('instruktur')">Instruktur</button>
                            <button class="btn btn-ghost" id="filterPeserta" onclick="filterUsers('peserta')">Peserta</button>
                            <button class="btn btn-ghost" id="filterKaryawan" onclick="filterUsers('karyawan')">Karyawan</button>
                        </div>
                        <input type="text" id="searchUser" class="form-input" style="max-width: 250px;" placeholder="Cari pengguna..." onkeyup="searchUsers()">
                    </div>
                </div>
                <div class="dashboard-section-body" style="padding: 0; overflow-x: auto;">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Divisi</th>
                                <th>Status</th>
                                <th>Tanggal Daftar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Pilih Role Pengguna -->
    <div id="userTypeModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 600;">Tambah Pengguna</h3>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">Pilih role pengguna yang akan ditambahkan.</p>
                </div>
                <button onclick="closeUserTypeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary); line-height: 1;">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                <button type="button" class="user-role-choice" onclick="chooseUserRole('admin')">
                    <span class="user-role-choice-icon" style="background: #fee2e2; color: #991b1b;">
                        <i class="bi bi-shield-lock"></i>
                    </span>
                    <span class="user-role-choice-info">
                        <span class="user-role-choice-title">Admin</span>
                        <span class="user-role-choice-desc">Akses penuh pengelolaan sistem dan persetujuan sertifikat.</span>
                    </span>
                    <i class="bi bi-chevron-right" style="color: var(--text-light);"></i>
                </button>
                <button type="button" class="user-role-choice" onclick="chooseUserRole('instruktur')">
                    <span class="user-role-choice-icon" style="background: #fef3c7; color: #b45309;">
                        <i class="bi bi-easel"></i>
                    </span>
                    <span class="user-role-choice-info">
                        <span class="user-role-choice-title">Instruktur</span>
                        <span class="user-role-choice-desc">Mengelola modul, materi, dan memberi nilai pada kursus yang diampu.</span>
                    </span>
                    <i class="bi bi-chevron-right" style="color: var(--text-light);"></i>
                </button>
                <button type="button" class="user-role-choice" onclick="chooseUserRole('peserta')">
                    <span class="user-role-choice-icon" style="background: #dbeafe; color: #1d4ed8;">
                        <i class="bi bi-mortarboard"></i>
                    </span>
                    <span class="user-role-choice-info">
                        <span class="user-role-choice-title">Peserta</span>
                        <span class="user-role-choice-desc">Mengikuti kursus yang ditugaskan dan melihat sertifikat yang terbit.</span>
                    </span>
                    <i class="bi bi-chevron-right" style="color: var(--text-light);"></i>
                </button>
                <button type="button" class="user-role-choice" onclick="chooseUserRole('karyawan')">
                    <span class="user-role-choice-icon" style="background: #e0e7ff; color: #3730a3;">
                        <i class="bi bi-person-badge"></i>
                    </span>
                    <span class="user-role-choice-info">
                        <span class="user-role-choice-title">Karyawan</span>
                        <span class="user-role-choice-desc">Membuka dashboard karyawan, absensi harian dengan foto dan GPS, serta riwayat absensi.</span>
                    </span>
                    <i class="bi bi-chevron-right" style="color: var(--text-light);"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal User -->
    <div id="userModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;" id="userModalTitle">Tambah Pengguna</h3>
                <button onclick="closeUserModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" id="userName" class="form-input" placeholder="Masukkan nama lengkap">
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" id="userEmail" class="form-input" placeholder="nama@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">No. Telepon</label>
                    <input type="tel" id="userPhone" class="form-input" placeholder="08xxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select id="userRole" class="form-input" onchange="syncDivisionField()">
                        <option value="peserta">Peserta</option>
                        <option value="karyawan">Karyawan</option>
                        <option value="instruktur">Instruktur</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" id="divisionGroup" style="display: none;">
                    <label class="form-label">Divisi</label>
                    <select id="userDivision" class="form-input">
                        <option value="">Tanpa divisi</option>
                        <option value="marketing">Marketing</option>
                        <option value="operasional">Operasional</option>
                        <option value="keuangan">Keuangan</option>
                    </select>
                    <small class="form-hint">Divisi hanya dipakai untuk role Karyawan.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="userStatus" class="form-input">
                        <option value="aktif" selected>Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
                <div class="form-group" id="passwordGroup">
                    <label class="form-label">Password</label>
                    <input type="password" id="userPassword" class="form-input" placeholder="Minimal 8 karakter">
                    <small class="form-hint">Wajib untuk pengguna baru; kosongkan saat edit jika tidak ingin mengubah.</small>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeUserModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveUser()">Simpan</button>
            </div>
        </div>
    </div>

    <!-- Modal Assign Instruktur ke Kursus -->
    <div id="assignModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div style="background: white; border-radius: var(--radius-lg); max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 600;">Tugaskan Instruktur ke Kursus</h3>
                <button onclick="closeAssignModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div><strong>Instruktur:</strong> <span id="assignInstructorName"></span></div>
                <div class="form-group">
                    <label class="form-label">Pilih Kursus yang Diampu</label>
                    <div id="assignCourseList" style="display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem; border: 1px solid var(--border); border-radius: var(--radius); max-height: 260px; overflow-y: auto;"></div>
                    <small class="form-hint">1 kursus hanya boleh memiliki 1 instruktur. Memilih kursus di sini akan menggantikan instruktur sebelumnya bila ada.</small>
                </div>
            </div>
            <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button class="btn btn-ghost" onclick="closeAssignModal()">Batal</button>
                <button class="btn btn-primary" onclick="saveAssignment()">Simpan Penugasan</button>
            </div>
        </div>
<?php end_layout(); ?>
