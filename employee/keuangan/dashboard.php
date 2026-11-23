<?php
// Direktori: employee/keuangan/dashboard.php
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
                    <p class="dashboard-subtitle">Area kerja divisi Keuangan.</p>
                </div>
            </div>
            <div class="dashboard-section">
                <div class="dashboard-section-body">
                    <p style="margin:0 0 1rem; color: var(--text-secondary);">Verifikasi bukti pembayaran siswa, catat pembayaran sebagian, dan lunasi tagihan di menu Pembayaran.</p>
                    <a class="btn btn-primary" href="<?= url('employee/keuangan/payments.php') ?>"><i class="bi bi-receipt"></i> Buka Pembayaran Siswa</a>
                </div>
            </div>
        </main>
    </div>
    <button class="dashboard-sidebar-toggle" id="keuSidebarToggle" type="button" aria-label="Buka menu"><i class="bi bi-list"></i></button>
    <div class="dashboard-sidebar-backdrop" id="keuSidebarBackdrop">
<?php end_layout(); ?>