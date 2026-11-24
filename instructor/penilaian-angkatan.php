<?php
// Direktori: instructor/penilaian-angkatan.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/penilaian-angkatan',
    'roles' => ['instruktur'],
    'title' => 'Penilaian Angkatan - ELIPS LMS',
    'area' => 'instructor',
    'active' => 'instructor/penilaian.php',
    'css' => ['tentor-app'],
    'scripts' => ['ui', 'pages/instructor/tp-core'],
    'sidebar' => 'instructor',
];
layout('app', $page);
?>
        <main class="dashboard-main tp-main">
            <header class="tp-head">
                <div>
                    <a class="tp-crumb" href="<?= url('instructor/penilaian.php') ?>"><i class="bi bi-arrow-left"></i> Penilaian</a>
                    <h1 class="tp-title"><span id="tpName">Memuat...</span> <span id="tpBadge"></span></h1>
                    <p class="tp-sub" id="tpSub"></p>
                </div>
            </header>

            <div id="tpError"></div>
            <section class="tp-stats" id="tpStats"></section>

            <div class="tp-split">
                <section>
                    <div class="tp-section-head"><h2 class="tp-section-title">Tugas</h2></div>
                    <div id="tpTasks"></div>
                </section>
                <aside>
                    <div class="tp-card tp-stack" id="tpFinal"></div>
                </aside>
            </div>
        </main>
<?php end_layout(); ?>