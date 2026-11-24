<?php
// Direktori: instructor/dashboard.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/dashboard',
    'roles' => ['instruktur'],
    'title' => 'Dashboard Tentor - ELIPS LMS',
    'area' => 'instructor',
    'css' => ['tentor-app'],
    'scripts' => ['ui', 'pages/instructor/tp-core'],
    'sidebar' => 'instructor',
];
layout('app', $page);
?>
        <main class="dashboard-main tp-main">
            <header class="tp-head">
                <div>
                    <h1 class="tp-title">Dashboard</h1>
                    <p class="tp-sub" id="tpGreeting">Memuat ringkasan Anda...</p>
                </div>
                <div class="tp-actions">
                    <a href="<?= url('instructor/kurikulum.php') ?>" class="btn btn-primary"><i class="bi bi-journal-text"></i> Buka kurikulum</a>
                </div>
            </header>

            <div id="tpError"></div>

            <section class="tp-stats" id="tpStats" aria-label="Ringkasan">
                <div class="tp-skel" style="height: 96px"></div>
                <div class="tp-skel" style="height: 96px"></div>
                <div class="tp-skel" style="height: 96px"></div>
                <div class="tp-skel" style="height: 96px"></div>
            </section>

            <div class="tp-split">
                <div>
                    <section class="tp-section">
                        <div class="tp-section-head">
                            <h2 class="tp-section-title">Sesi hari ini</h2>
                        </div>
                        <div id="tpToday"></div>
                    </section>

                    <section class="tp-section">
                        <div class="tp-section-head">
                            <h2 class="tp-section-title">Angkatan saya</h2>
                            <a class="tp-link" href="<?= url('instructor/angkatan.php') ?>">Lihat semua <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <div id="tpClasses"></div>
                    </section>
                </div>

                <aside>
                    <section class="tp-section">
                        <div class="tp-section-head">
                            <h2 class="tp-section-title">Perlu tindakan</h2>
                        </div>
                        <div class="tp-stack" id="tpTodo"></div>
                    </section>
                </aside>
            </div>
        </main>
<?php end_layout(); ?>