<?php
// Direktori: instructor/kurikulum.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/kurikulum',
    'roles' => ['instruktur'],
    'title' => 'Kurikulum - ELIPS LMS',
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
                    <h1 class="tp-title">Kurikulum</h1>
                    <p class="tp-sub">Pilih kursus untuk mengisi tujuan, materi, penugasan, dan durasi tiap sesi. Modul dan judul sesi diatur admin.</p>
                </div>
            </header>

            <div id="tpError"></div>
            <div class="tp-cards" id="tpCards"><div class="tp-skel" style="height: 240px"></div><div class="tp-skel" style="height: 240px"></div></div>
        </main>
<?php end_layout(); ?>