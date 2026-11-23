<aside class="dashboard-sidebar">
    <div class="dashboard-sidebar-header">
        <div class="dashboard-user">
            <div class="dashboard-avatar" id="employeeAvatar">K</div>
            <div class="dashboard-user-info">
                <div class="dashboard-user-name" id="employeeSidebarName">Karyawan</div>
                <div class="dashboard-user-role" id="employeeDivision">Karyawan</div>
            </div>
        </div>
    </div>
    <nav class="dashboard-nav">
        <a href="<?= url('employee/shared/dashboard.php') ?>" class="dashboard-nav-link<?= nav_active('employee/shared/dashboard.php') ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= url('employee/shared/attendance.php') ?>" class="dashboard-nav-link<?= nav_active('employee/shared/attendance.php') ?>">
            <i class="bi bi-camera"></i>
            <span>Absensi Hari Ini</span>
        </a>
        <a href="<?= url('employee/shared/attendance.php') ?>#attendanceHistorySection" class="dashboard-nav-link">
            <i class="bi bi-clock-history"></i>
            <span>Riwayat Absensi</span>
        </a>
        <a href="<?= url('employee/marketing/campaigns.php') ?>" class="dashboard-nav-link<?= nav_active('employee/marketing/campaigns.php') ?>" id="marketingCampaignNavLink" style="display: none;">
            <i class="bi bi-megaphone"></i>
            <span>Campaigns</span>
        </a>
        <a href="<?= url('employee/marketing/tasks.php') ?>" class="dashboard-nav-link<?= nav_active('employee/marketing/tasks.php') ?>" id="marketingTaskNavLink" style="display: none;">
            <i class="bi bi-check2-square"></i>
            <span>Task</span>
        </a>
        <a href="<?= url('employee/marketing/clients.php') ?>" class="dashboard-nav-link<?= nav_active('employee/marketing/clients.php') ?>" id="marketingClientNavLink" style="display: none;">
            <i class="bi bi-people"></i>
            <span>Clients</span>
        </a>
        <a href="<?= url('employee/marketing/reports.php') ?>" class="dashboard-nav-link<?= nav_active('employee/marketing/reports.php') ?>" id="marketingReportNavLink" style="display: none;">
            <i class="bi bi-file-earmark-bar-graph"></i>
            <span>Report</span>
        </a>
        <a href="#operasionalArea" class="dashboard-nav-link" id="operasionalNavLink" style="display: none;">
            <i class="bi bi-diagram-3"></i>
            <span>Operasional</span>
        </a>
        <a href="<?= url('employee/operasional/siswa.php') ?>" class="dashboard-nav-link<?= nav_active('employee/operasional/siswa.php') ?>" id="operasionalSiswaNavLink" style="display: none;">
            <i class="bi bi-mortarboard"></i>
            <span>Siswa</span>
        </a>
        <a href="<?= url('employee/operasional/tentor.php') ?>" class="dashboard-nav-link<?= nav_active('employee/operasional/tentor.php') ?>" id="operasionalTentorNavLink" style="display: none;">
            <i class="bi bi-easel"></i>
            <span>Tentor</span>
        </a>
        <a href="<?= url('employee/operasional/akademik.php') ?>" class="dashboard-nav-link<?= nav_active('employee/operasional/akademik.php') ?>" id="operasionalAkademikNavLink" style="display: none;">
            <i class="bi bi-journal-bookmark"></i>
            <span>Akademik</span>
        </a>
        <a href="<?= url('employee/operasional/jadwal.php') ?>" class="dashboard-nav-link<?= nav_active('employee/operasional/jadwal.php') ?>" id="operasionalJadwalNavLink" style="display: none;">
            <i class="bi bi-calendar-week"></i>
            <span>Kalender &amp; Jadwal</span>
        </a>
        <a href="#keuanganArea" class="dashboard-nav-link" id="keuanganNavLink" style="display: none;">
            <i class="bi bi-cash-coin"></i>
            <span>Keuangan</span>
        </a>
        <a href="#marketingArea" class="dashboard-nav-link" id="marketingNavLink" style="display: none;">
            <i class="bi bi-funnel"></i>
            <span>Lead Marketing</span>
        </a>
    </nav>
</aside>
