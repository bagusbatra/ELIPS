<aside class="dashboard-sidebar">
    <div class="dashboard-sidebar-header">
        <div class="dashboard-user">
            <div class="dashboard-avatar" id="studentAvatar">BS</div>
            <div class="dashboard-user-info">
                <div class="dashboard-user-name" id="studentName">Budi Santoso</div>
                <div class="dashboard-user-role">Peserta</div>
            </div>
        </div>
    </div>
    <nav class="dashboard-nav">
        <a href="<?= url('student/dashboard.php') ?>" class="dashboard-nav-link<?= nav_active('student/dashboard.php') ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= url('student/courses.php') ?>" class="dashboard-nav-link<?= nav_active('student/courses.php') ?>">
            <i class="bi bi-journal-bookmark"></i>
            <span>Kursus Saya</span>
        </a>
        <a href="<?= url('index.php') ?>#courses" class="dashboard-nav-link">
            <i class="bi bi-compass"></i>
            <span>Jelajahi Kursus</span>
        </a>
        <a href="<?= url('student/certificates.php') ?>" class="dashboard-nav-link<?= nav_active('student/certificates.php') ?>">
            <i class="bi bi-award"></i>
            <span>Sertifikat Saya</span>
        </a>
        <a href="<?= url('student/profile.php') ?>" class="dashboard-nav-link<?= nav_active('student/profile.php') ?>">
            <i class="bi bi-person"></i>
            <span>Profil Saya</span>
        </a>
    </nav>
</aside>
