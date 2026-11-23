<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'page_script' => 'employee/marketing/tasks',
    'roles' => ['karyawan'],
    'divisions' => ['marketing'],
    'title' => 'Task Marketing - ELIPS LMS',
    'scripts' => ['@crud', 'crud-marketing', 'ui', 'employee/marketing/tasks'],
    'navbar' => 'employee-marketing',
    'sidebar' => 'employee-marketing',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Task Marketing</h1>
                <p class="dashboard-subtitle" id="taskGreeting">
                    Kelola pekerjaan harian Marketing dan pantau tenggatnya.
                </p>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-card-icon orange">
                        <i class="bi bi-check2-square"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="taskStatTotal">0</div>
                        <div class="stat-card-label">Total Task</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="taskStatOpen">0</div>
                        <div class="stat-card-label">Belum Selesai</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon green">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="taskStatDone">0</div>
                        <div class="stat-card-label">Selesai</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-card-info">
                        <div class="stat-card-value" id="taskStatOverdue">0</div>
                        <div class="stat-card-label">Terlambat</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="dashboard-section-header">
                    <div>
                        <h2 class="dashboard-section-title">Daftar Task</h2>
                        <p class="form-hint" style="margin: 0.25rem 0 0;">
                            Task dapat ditautkan ke campaign dan lead agar pekerjaan lebih terarah.
                        </p>
                    </div>
                    <div class="modal-actions">
                        <button class="btn btn-ghost" type="button" id="taskRefresh">
                            <i class="bi bi-arrow-clockwise"></i> Muat Ulang
                        </button>
                        <button class="btn btn-primary" type="button" id="taskAddButton">
                            <i class="bi bi-plus-lg"></i> Tambah Task
                        </button>
                    </div>
                </div>
                <div class="dashboard-section-body">
                    <div class="task-toolbar">
                        <div class="task-search">
                            <i class="bi bi-search"></i>
                            <input class="form-input" type="search" id="taskSearch" maxlength="100"
                                placeholder="Cari judul, deskripsi, campaign, atau lead">
                        </div>
                        <select class="form-input task-filter" id="taskStatusFilter">
                            <option value="">Semua status</option>
                            <option value="belum">Belum</option>
                            <option value="proses">Proses</option>
                            <option value="selesai">Selesai</option>
                            <option value="batal">Batal</option>
                        </select>
                        <select class="form-input task-filter" id="taskPriorityFilter">
                            <option value="">Semua prioritas</option>
                            <option value="tinggi">Tinggi</option>
                            <option value="sedang">Sedang</option>
                            <option value="rendah">Rendah</option>
                        </select>
                    </div>
                    <div class="task-grid" id="taskList">
                        <div class="task-empty">Memuat task&hellip;</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <button class="dashboard-sidebar-toggle" id="taskSidebarToggle" type="button" aria-label="Buka menu">
        <i class="bi bi-list"></i>
    </button>
    <div class="dashboard-sidebar-backdrop" id="taskSidebarBackdrop"></div>

    <!-- Modal Task -->
    <div class="attendance-modal" id="taskModal" style="display: none;">
        <div class="attendance-modal-card marketing-modal-card">
            <div class="attendance-modal-header">
                <div>
                    <div class="attendance-modal-title" id="taskModalTitle">Tambah Task</div>
                    <div class="attendance-modal-subtitle">Tentukan jenis, prioritas, dan tenggat pekerjaan.</div>
                </div>
                <button class="attendance-modal-close" id="taskModalClose" type="button" aria-label="Tutup">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="taskForm">
                <div class="attendance-modal-body marketing-modal-body">
                    <input type="hidden" id="taskId">
                    <div class="form-group marketing-span-2">
                        <label class="form-label" for="taskTitle">Judul Task *</label>
                        <input class="form-input" type="text" id="taskTitle" maxlength="150"
                            placeholder="Contoh: Follow-up lead hasil pameran" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="taskType">Jenis</label>
                        <select class="form-input" id="taskType">
                            <option value="follow_up">Follow-up</option>
                            <option value="campaign">Campaign</option>
                            <option value="administrasi">Administrasi</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="taskPriority">Prioritas</label>
                        <select class="form-input" id="taskPriority">
                            <option value="rendah">Rendah</option>
                            <option value="sedang">Sedang</option>
                            <option value="tinggi">Tinggi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="taskStatus">Status</label>
                        <select class="form-input" id="taskStatus">
                            <option value="belum">Belum</option>
                            <option value="proses">Proses</option>
                            <option value="selesai">Selesai</option>
                            <option value="batal">Batal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="taskDueDate">Tenggat</label>
                        <input class="form-input" type="date" id="taskDueDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="taskCampaign">Campaign</label>
                        <select class="form-input" id="taskCampaign">
                            <option value="">Tanpa campaign</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="taskLead">Lead</label>
                        <select class="form-input" id="taskLead">
                            <option value="">Tanpa lead</option>
                        </select>
                    </div>
                    <div class="form-group marketing-span-2">
                        <label class="form-label" for="taskDescription">Deskripsi</label>
                        <textarea class="form-input marketing-textarea" id="taskDescription" maxlength="1000"
                            rows="3" placeholder="Langkah pekerjaan, hasil yang diharapkan, atau catatan."></textarea>
                    </div>
                </div>
                <div class="attendance-modal-actions" style="padding: 0 1.5rem 1.5rem;">
                    <button class="btn btn-outline" id="taskCancel" type="button">Batal</button>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-check2-circle"></i> Simpan Task
                    </button>
                </div>
            </form>
        </div>
<?php end_layout(); ?>
