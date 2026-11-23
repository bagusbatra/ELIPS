<header class="navbar">
    <div class="container nav-container">
        <div class="nav-brand">
            <i class="bi bi-mortarboard-fill brand-icon"></i>
            <span class="brand-text">ELIPS LMS</span>
        </div>
        <nav class="nav-menu" id="navMenu">
            <a href="<?= url('index.php') ?>" class="nav-link<?= nav_active('index.php') ?>">Beranda</a>
            <a href="<?= url('verify-certificate.php') ?>" class="nav-link<?= nav_active('verify-certificate.php') ?>">Verifikasi Sertifikat</a>
        </nav>
        <div class="nav-actions">
            <a href="<?= url('employee/shared/dashboard.php') ?>" class="btn btn-ghost">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= url('employee/marketing/clients.php') ?>" class="btn btn-ghost" id="attendanceClientsLink" style="display: none;">
                <i class="bi bi-people"></i> Clients
            </a>
            <a href="<?= url('employee/marketing/reports.php') ?>" class="btn btn-ghost" id="attendanceReportsLink" style="display: none;">
                <i class="bi bi-file-earmark-bar-graph"></i> Report
            </a>
            <span class="nav-link" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="bi bi-person-badge"></i>
                <span class="dashboard-user-name" id="employeeName">Karyawan</span>
            </span>
            <a href="<?= url('index.php') ?>" class="btn btn-outline" data-logout>Keluar</a>
        </div>
        <button class="nav-toggle" id="navToggle" aria-label="Buka menu">
            <i class="bi bi-list"></i>
        </button>
    </div>
</header>
