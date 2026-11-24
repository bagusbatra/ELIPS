<?php /* Direktori: partials/sidebar/employee-operasional.php */ ?>
<aside class="dashboard-sidebar">
    <div class="dashboard-sidebar-header">
        <div class="dashboard-user">
            <div class="dashboard-avatar" id="employeeAvatar">K</div>
            <div class="dashboard-user-info">
                <div class="dashboard-user-name" id="employeeSidebarName">Karyawan</div>
                <div class="dashboard-user-role" id="employeeDivision">Operasional</div>
            </div>
        </div>
    </div>
    <nav class="dashboard-nav">
        <a href="<?= url('employee/shared/dashboard.php') ?>" class="dashboard-nav-link<?= nav_active('employee/shared/dashboard.php') ?>"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
        <a href="<?= url('employee/operasional/siswa.php') ?>" class="dashboard-nav-link<?= nav_active('employee/operasional/siswa.php') ?>"><i class="bi bi-mortarboard"></i><span>Siswa</span></a>
        <a href="<?= url('employee/operasional/tentor.php') ?>" class="dashboard-nav-link<?= nav_active('employee/operasional/tentor.php') ?>"><i class="bi bi-easel"></i><span>Tentor</span></a>
        <a href="<?= url('employee/operasional/kursus.php') ?>" class="dashboard-nav-link<?= nav_active('employee/operasional/kursus.php') ?>"><i class="bi bi-collection"></i><span>Kursus</span></a>
        <a href="<?= url('employee/operasional/akademik.php') ?>" class="dashboard-nav-link<?= nav_active('employee/operasional/akademik.php') ?>"><i class="bi bi-journal-bookmark"></i><span>Kurikulum &amp; Kelas</span></a>
        <a href="<?= url('employee/operasional/jadwal.php') ?>" class="dashboard-nav-link<?= nav_active('employee/operasional/jadwal.php') ?>"><i class="bi bi-calendar-week"></i><span>Kalender &amp; Jadwal</span></a>
    </nav>
</aside>