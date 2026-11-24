<?php // Direktori: partials/sidebar/instructor.php ?>
<aside class="dashboard-sidebar">
    <div class="dashboard-sidebar-header">
        <div class="dashboard-user">
            <div class="dashboard-avatar" id="instructorAvatar">AF</div>
            <div class="dashboard-user-info">
                <div class="dashboard-user-name" id="instructorName">Ahmad Fauzi, M.M.</div>
                <div class="dashboard-user-role">Tentor</div>
            </div>
        </div>
    </div>
    <nav class="dashboard-nav">
        <a href="<?= url('instructor/dashboard.php') ?>" class="dashboard-nav-link<?= nav_active('instructor/dashboard.php') ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= url('instructor/angkatan.php') ?>" class="dashboard-nav-link<?= nav_active('instructor/angkatan.php') ?>">
            <i class="bi bi-people"></i>
            <span>Angkatan saya</span>
        </a>
        <a href="<?= url('instructor/kurikulum.php') ?>" class="dashboard-nav-link<?= nav_active('instructor/kurikulum.php') ?>">
            <i class="bi bi-journal-text"></i>
            <span>Kurikulum</span>
        </a>
        <a href="<?= url('instructor/penilaian.php') ?>" class="dashboard-nav-link<?= nav_active('instructor/penilaian.php') ?>">
            <i class="bi bi-clipboard-check"></i>
            <span>Penilaian</span>
        </a>
        <a href="<?= url('instructor/certificates.php') ?>" class="dashboard-nav-link<?= nav_active('instructor/certificates.php') ?>">
            <i class="bi bi-award"></i>
            <span>Sertifikat Peserta</span>
        </a>
        <a href="<?= url('instructor/profile.php') ?>" class="dashboard-nav-link<?= nav_active('instructor/profile.php') ?>">
            <i class="bi bi-person"></i>
            <span>Profil Saya</span>
        </a>
    </nav>
</aside>