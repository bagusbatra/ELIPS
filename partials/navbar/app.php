<?php
$home = ['admin' => 'admin/dashboard.php', 'instructor' => 'instructor/dashboard.php', 'student' => 'student/dashboard.php'][$page['area'] ?? 'admin'];
?>
<header class="navbar">
    <div class="container nav-container">
        <div class="nav-brand">
            <i class="bi bi-mortarboard-fill brand-icon"></i>
            <span class="brand-text">ELIPS LMS</span>
        </div>
        <nav class="nav-menu" id="navMenu">
            <a href="<?= url('index.php') ?>" class="nav-link<?= nav_active('index.php') ?>">Beranda</a>
            <a href="<?= url('index.php') ?>#courses" class="nav-link">Kursus</a>
            <a href="<?= url('verify-certificate.php') ?>" class="nav-link<?= nav_active('verify-certificate.php') ?>">Verifikasi Sertifikat</a>
        </nav>
        <div class="nav-actions">
            <a href="<?= url($home) ?>" class="btn btn-ghost">Dashboard</a>
            <a href="<?= url('index.php') ?>" class="btn btn-outline" data-logout>Keluar</a>
        </div>
        <button class="nav-toggle" id="navToggle" aria-label="Buka menu">
            <i class="bi bi-list"></i>
        </button>
    </div>
</header>
