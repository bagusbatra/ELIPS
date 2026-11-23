<?php /* Direktori: partials/navbar/employee-keuangan.php */ ?><header class="navbar">
    <div class="container nav-container">
        <div class="nav-brand"><i class="bi bi-mortarboard-fill brand-icon"></i><span class="brand-text">ELIPS LMS</span></div>
        <nav class="nav-menu" id="navMenu">
            <a href="<?= url('index.php') ?>" class="nav-link<?= nav_active('index.php') ?>">Beranda</a>
            <a href="<?= url('employee/shared/dashboard.php') ?>" class="nav-link<?= nav_active('employee/shared/dashboard.php') ?>">Dashboard</a>
            <a href="<?= url('employee/keuangan/dashboard.php') ?>" class="nav-link<?= nav_active('employee/keuangan/dashboard.php') ?>">Keuangan</a>
            <a href="<?= url('employee/keuangan/payments.php') ?>" class="nav-link<?= nav_active('employee/keuangan/payments.php') ?>">Pembayaran</a>
        </nav>
        <div class="nav-actions">
            <span class="nav-link" style="display:inline-flex;align-items:center;gap:.5rem;"><i class="bi bi-person-badge"></i><span id="employeeName">Karyawan</span></span>
            <a href="<?= url('index.php') ?>" class="btn btn-outline" data-logout>Keluar</a>
        </div>
        <button class="nav-toggle" id="navToggle" aria-label="Buka menu"><i class="bi bi-list"></i></button>
    </div>
</header>