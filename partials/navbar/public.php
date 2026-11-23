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
            <a href="<?= url('login.php') ?>" class="btn btn-ghost">Masuk</a>
        </div> 
        <button class="nav-toggle" id="navToggle">
            <i class="bi bi-list"></i>
        </button>
    </div>
</header>
