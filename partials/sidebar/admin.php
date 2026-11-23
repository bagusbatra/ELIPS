<aside class="dashboard-sidebar">
    <div class="dashboard-sidebar-header">
        <div class="dashboard-user">
            <div class="dashboard-avatar">AD</div>
            <div class="dashboard-user-info">
                <div class="dashboard-user-name">Administrator</div>
                <div class="dashboard-user-role">Admin</div>
            </div>
        </div>
    </div>
    <nav class="dashboard-nav">
        <a href="<?= url('admin/dashboard.php') ?>" class="dashboard-nav-link<?= nav_active('admin/dashboard.php') ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= url('admin/users.php') ?>" class="dashboard-nav-link<?= nav_active('admin/users.php') ?>">
            <i class="bi bi-people"></i>
            <span>Kelola Pengguna</span>
        </a>
        <a href="<?= url('admin/courses.php') ?>" class="dashboard-nav-link<?= nav_active('admin/courses.php') ?>">
            <i class="bi bi-journal-bookmark"></i>
            <span>Kelola Kursus</span>
        </a>
        <a href="<?= url('admin/academic.php') ?>" class="dashboard-nav-link<?= nav_active('admin/academic.php') ?>">
            <i class="bi bi-diagram-3"></i>
            <span>Kurikulum &amp; Kelas</span>
        </a>
        <a href="<?= url('admin/schedule.php') ?>" class="dashboard-nav-link<?= nav_active('admin/schedule.php') ?>">
            <i class="bi bi-calendar-week"></i>
            <span>Kalender &amp; Jadwal</span>
        </a>
        <a href="<?= url('admin/enrollment.php') ?>" class="dashboard-nav-link<?= nav_active('admin/enrollment.php') ?>">
            <i class="bi bi-person-check"></i>
            <span>Penugasan Siswa</span>
        </a>
        <a href="<?= url('admin/certificates.php') ?>" class="dashboard-nav-link<?= nav_active('admin/certificates.php') ?>">
            <i class="bi bi-award"></i>
            <span>Kelola Sertifikat</span>
        </a>
        <a href="<?= url('admin/reports.php') ?>" class="dashboard-nav-link<?= nav_active('admin/reports.php') ?>">
            <i class="bi bi-file-earmark-bar-graph"></i>
            <span>Laporan</span>
        </a>
        <a href="<?= url('admin/attendance-recap.php') ?>" class="dashboard-nav-link<?= nav_active('admin/attendance-recap.php') ?>">
            <i class="bi bi-calendar2-check"></i>
            <span>Rekap Absensi</span>
        </a>
        <a href="<?= url('admin/shifts.php') ?>" class="dashboard-nav-link<?= nav_active('admin/shifts.php') ?>">
            <i class="bi bi-clock-history"></i>
            <span>Shift Karyawan</span>
        </a>
        <a href="<?= url('admin/letters.php') ?>" class="dashboard-nav-link<?= nav_active('admin/letters.php') ?>">
            <i class="bi bi-envelope-paper"></i>
            <span>Surat Menyurat</span>
        </a>
        <a href="<?= url('admin/notifications.php') ?>" class="dashboard-nav-link<?= nav_active('admin/notifications.php') ?>">
            <i class="bi bi-envelope"></i>
            <span>Notifikasi</span>
        </a>
        <a href="<?= url('admin/audit.php') ?>" class="dashboard-nav-link<?= nav_active('admin/audit.php') ?>">
            <i class="bi bi-clock-history"></i>
            <span>Audit Trail</span>
        </a>
        <a href="<?= url('admin/settings.php') ?>" class="dashboard-nav-link<?= nav_active('admin/settings.php') ?>">
            <i class="bi bi-gear"></i>
            <span>Pengaturan</span>
        </a>
    </nav>
</aside>
