<?php
// Direktori: instructor/angkatan.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/angkatan',
    'roles' => ['instruktur'],
    'title' => 'Angkatan Saya - ELIPS LMS',
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
                    <h1 class="tp-title">Angkatan saya</h1>
                    <p class="tp-sub">Angkatan yang ditugaskan admin kepada Anda. Satu angkatan diampu satu tentor.</p>
                </div>
            </header>

            <div class="tp-tabs" role="tablist" id="tpFilter" aria-label="Filter angkatan">
                <button type="button" class="tp-tab" role="tab" data-f="all" aria-selected="true">Semua <span id="cAll"></span></button>
                <button type="button" class="tp-tab" role="tab" data-f="berjalan" aria-selected="false">Berjalan <span id="cBerjalan"></span></button>
                <button type="button" class="tp-tab" role="tab" data-f="pendaftaran" aria-selected="false">Pendaftaran <span id="cPendaftaran"></span></button>
                <button type="button" class="tp-tab" role="tab" data-f="selesai" aria-selected="false">Selesai <span id="cSelesai"></span></button>
            </div>

            <div id="tpError"></div>
            <div class="tp-cards" id="tpCards"><div class="tp-skel" style="height: 220px"></div><div class="tp-skel" style="height: 220px"></div></div>
        </main>
<?php end_layout(); ?>