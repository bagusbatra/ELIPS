<?php
// Direktori: partials/sidebar/employee-keuangan.php
$__badges = ['unpaidInvoices' => 0];
try {
    $__badges = array_merge($__badges, elips_badge_counts(elips_current_user()));
} catch (Throwable $e) {
    error_log('[ELIPS badge] ' . $e->getMessage());
}
?>
<aside class="dashboard-sidebar">
    <div class="dashboard-sidebar-header">
        <div class="dashboard-user">
            <div class="dashboard-avatar" id="employeeAvatar">K</div>
            <div class="dashboard-user-info">
                <div class="dashboard-user-name" id="employeeSidebarName">Karyawan</div>
                <div class="dashboard-user-role" id="employeeDivision">Keuangan</div>
            </div>
        </div>
    </div>
    <nav class="dashboard-nav">
        <a href="<?= url('employee/shared/dashboard.php') ?>" class="dashboard-nav-link<?= nav_active('employee/shared/dashboard.php') ?>"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
        <a href="<?= url('employee/shared/attendance.php') ?>" class="dashboard-nav-link<?= nav_active('employee/shared/attendance.php') ?>"><i class="bi bi-camera"></i><span>Absensi</span></a>
        <a href="<?= url('employee/keuangan/dashboard.php') ?>" class="dashboard-nav-link<?= nav_active('employee/keuangan/dashboard.php') ?>"><i class="bi bi-cash-coin"></i><span>Keuangan</span></a>
        <a href="<?= url('employee/keuangan/payments.php') ?>" class="dashboard-nav-link<?= nav_active('employee/keuangan/payments.php') ?>"><i class="bi bi-receipt"></i><span>Pembayaran</span>
            <span class="nav-badge nav-badge-yellow" data-badge="unpaidInvoices" data-count="<?= (int) $__badges['unpaidInvoices'] ?>"<?= $__badges['unpaidInvoices'] > 0 ? '' : ' hidden' ?> title="Siswa aktif belum lunas"><?= (int) $__badges['unpaidInvoices'] ?></span>
        </a>
    </nav>
</aside>
<script src="<?= asset('js/admin-badges.js') ?>" defer></script>