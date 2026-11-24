<?php
// Direktori: instructor/penilaian-akhir.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/penilaian-akhir',
    'roles' => ['instruktur'],
    'title' => 'Penilaian Akhir - ELIPS LMS',
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
                    <a class="tp-crumb" id="tpCrumb" href="<?= url('instructor/penilaian.php') ?>"><i class="bi bi-arrow-left"></i> Penilaian</a>
                    <h1 class="tp-title">Penilaian akhir <span id="tpBadge"></span></h1>
                    <p class="tp-sub" id="tpSub">Memuat...</p>
                </div>
                <div class="tp-actions">
                    <button type="button" class="btn btn-primary is-disabled" id="tpSend" disabled><i class="bi bi-send"></i> Kirim nilai akhir ke operasional</button>
                </div>
            </header>

            <div id="tpError"></div>
            <div id="tpBanner"></div>
            <section class="tp-stats" id="tpStats"></section>
            <div id="tpList"><div class="tp-skel" style="height: 280px"></div></div>
        </main>
<?php end_layout(); ?>