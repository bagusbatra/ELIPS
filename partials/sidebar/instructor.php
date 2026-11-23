<aside class="dashboard-sidebar">
    <div class="dashboard-sidebar-header">
        <div class="dashboard-user">
            <div class="dashboard-avatar" id="instructorAvatar">AF</div>
            <div class="dashboard-user-info">
                <div class="dashboard-user-name" id="instructorName">Ahmad Fauzi, M.M.</div>
                <div class="dashboard-user-role">Instruktur</div>
            </div>
        </div>
    </div>
    <nav class="dashboard-nav">
        <a href="<?= url('instructor/dashboard.php') ?>" class="dashboard-nav-link<?= nav_active('instructor/dashboard.php') ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= url('instructor/manage-course.php') ?>" class="dashboard-nav-link<?= nav_active('instructor/manage-course.php') ?>">
            <i class="bi bi-journal-plus"></i>
            <span>Kelola Kursus</span>
        </a>
        <a href="<?= url('instructor/students.php') ?>" class="dashboard-nav-link<?= nav_active('instructor/students.php') ?>">
            <i class="bi bi-people"></i>
            <span>Peserta &amp; Penilaian</span>
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
