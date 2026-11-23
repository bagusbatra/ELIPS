<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'roles' => ['admin', 'karyawan'],
    'divisions' => ['operasional'],
    'title' => 'Data Siswa - Operasional - ELIPS LMS',
    'scripts' => ['ui', 'employee/shared/common', 'employee/operasional/siswa'],
    'navbar' => 'employee-operasional',
    'sidebar' => 'employee-operasional',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Data Siswa</h1>
                    <p class="dashboard-subtitle">Pengelolaan data siswa (peserta) lintas semua cabang.</p>
                </div>
            </div>
            <div class="dashboard-section">
                <div class="dashboard-section-body">
                    <p style="margin:0; color: var(--text-secondary);">Modul <strong>Siswa &amp; Tentor</strong> (Langkah 2) sedang disiapkan. Halaman ini akan menyediakan tampilan dan pengelolaan penuh (tambah/ubah/hapus) data siswa di semua cabang menggunakan tabel yang sudah ada.</p>
                </div>
            </div>
        </main>
    </div>
    <button class="dashboard-sidebar-toggle" id="opsSidebarToggle" type="button" aria-label="Buka menu"><i class="bi bi-list"></i></button>
    <div class="dashboard-sidebar-backdrop" id="opsSidebarBackdrop">
<?php end_layout(); ?>
