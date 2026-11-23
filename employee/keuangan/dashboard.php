<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$page = [
    'roles' => ['admin', 'karyawan'],
    'divisions' => ['keuangan'],
    'title' => 'Keuangan - ELIPS LMS',
    'scripts' => ['ui', 'employee/shared/common', 'employee/keuangan/dashboard'],
    'navbar' => 'employee-keuangan',
    'sidebar' => 'employee-keuangan',
];
layout('app', $page);
?>
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1 class="dashboard-title">Dashboard Keuangan</h1>
                    <p class="dashboard-subtitle">Area kerja divisi Keuangan (dalam penyiapan).</p>
                </div>
            </div>
            <div class="dashboard-section">
                <div class="dashboard-section-body">
                    <p style="margin:0 0 .75rem; color: var(--text-secondary);">Struktur folder dan navigasi untuk divisi <strong>Keuangan</strong> telah disiapkan. Tambahkan modul keuangan di folder ini, misalnya:</p>
                    <ul style="margin:0; color: var(--text-secondary); padding-left: 1.25rem;">
                        <li><code>employee/keuangan/&lt;modul&gt;.php</code> untuk halaman</li>
                        <li><code>assets/js/employee/keuangan/&lt;modul&gt;.js</code> untuk logika</li>
                        <li>Gunakan tabel/data &amp; API yang sudah ada bila relevan, atau tambahkan skema/API baru sesuai kebutuhan.</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    <button class="dashboard-sidebar-toggle" id="keuSidebarToggle" type="button" aria-label="Buka menu"><i class="bi bi-list"></i></button>
    <div class="dashboard-sidebar-backdrop" id="keuSidebarBackdrop">
<?php end_layout(); ?>
