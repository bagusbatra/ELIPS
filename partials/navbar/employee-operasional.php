<header class="navbar">
    <div class="container nav-container">
        <div class="nav-brand"><i class="bi bi-mortarboard-fill brand-icon"></i><span class="brand-text">ELIPS LMS</span></div>
        <nav class="nav-menu" id="navMenu">
            <a href="<?= url('index.php') ?>" class="nav-link<?= nav_active('index.php') ?>">Beranda</a>
            <a href="<?= url('employee/shared/dashboard.php') ?>" class="nav-link<?= nav_active('employee/shared/dashboard.php') ?>">Dashboard</a>
            <a href="<?= url('employee/operasional/siswa.php') ?>" class="nav-link<?= nav_active('employee/operasional/siswa.php') ?>">Siswa</a>
            <a href="<?= url('employee/operasional/tentor.php') ?>" class="nav-link<?= nav_active('employee/operasional/tentor.php') ?>">Tentor</a>
            <a href="<?= url('employee/operasional/akademik.php') ?>" class="nav-link<?= nav_active('employee/operasional/akademik.php') ?>">Akademik</a>
            <a href="<?= url('employee/operasional/jadwal.php') ?>" class="nav-link<?= nav_active('employee/operasional/jadwal.php') ?>">Jadwal</a>
        </nav>
        <div class="nav-actions">
            <span class="nav-link" style="display:inline-flex;align-items:center;gap:.5rem;"><i class="bi bi-person-badge"></i><span id="employeeName">Karyawan</span></span>
            <a href="<?= url('index.php') ?>" class="btn btn-outline" data-logout>Keluar</a>
        </div>
        <button class="nav-toggle" id="navToggle" aria-label="Buka menu"><i class="bi bi-list"></i></button>
    </div>
</header>
