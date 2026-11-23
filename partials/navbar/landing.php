<?php $lpHome = is_current('index.php') ? '' : url('index.php'); ?>
<header class="lp-nav" id="lpNav">
    <div class="lp-nav-bar">
        <a href="<?= url('index.php') ?>" class="lp-brand" aria-label="ELIPS LMS, ke Beranda">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11.5V16c0 1.5 3 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v6"/></svg>
            <span>ELIPS LMS</span>
        </a>
        <nav class="lp-menu" id="navMenu" aria-label="Menu utama">
            <a href="<?= url('index.php') ?>" class="lp-menu-link<?= is_current('index.php') ? ' is-current' : '' ?>"<?= is_current('index.php') ? ' aria-current="page"' : '' ?>>Beranda</a>
            <a href="<?= $lpHome ?>#pelatihan" class="lp-menu-link">Pelatihan</a>
            <a href="<?= $lpHome ?>#unggulan" class="lp-menu-link">Program Unggulan</a>
            <a href="<?= $lpHome ?>#sertifikat" class="lp-menu-link">Cek Sertifikat</a>
            <a href="<?= $lpHome ?>#profil" class="lp-menu-link">Profil</a>
            <a href="<?= $lpHome ?>#kontak" class="lp-menu-link">Kontak</a>
            <div class="lp-menu-actions lp-mobile-auth">
                <a href="<?= url('login.php') ?>" class="lp-btn lp-btn-line">Masuk</a>
                <a href="<?= url('register.php') ?>" class="lp-btn lp-btn-solid">Daftar</a>
            </div>
        </nav>
        <div class="lp-nav-actions nav-actions">
            <a href="<?= url('login.php') ?>" class="lp-btn lp-btn-ghost">Masuk</a>
            <a href="<?= url('register.php') ?>" class="lp-btn lp-btn-solid lp-btn-register">Daftar</a>
        </div>
        <button class="lp-menu-btn" id="navToggle" type="button" aria-label="Buka menu" aria-controls="navMenu" aria-expanded="false">
            <svg class="ic-open" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <svg class="ic-close" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>
</header>
