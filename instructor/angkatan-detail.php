<?php
// Direktori: instructor/angkatan-detail.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/angkatan-detail',
    'roles' => ['instruktur'],
    'title' => 'Detail Angkatan - ELIPS LMS',
    'area' => 'instructor',
    'active' => 'instructor/angkatan.php',
    'css' => ['tentor-app'],
    'scripts' => ['ui', 'pages/instructor/tp-core'],
    'sidebar' => 'instructor',
];
layout('app', $page);
?>
        <main class="dashboard-main tp-main">
            <header class="tp-head">
                <div>
                    <a class="tp-crumb" href="<?= url('instructor/angkatan.php') ?>"><i class="bi bi-arrow-left"></i> Angkatan saya</a>
                    <h1 class="tp-title"><span id="tpName">Memuat...</span> <span id="tpBadge"></span></h1>
                    <p class="tp-sub" id="tpSub"></p>
                </div>
                <div class="tp-actions" id="tpHeadActions"></div>
            </header>

            <div id="tpError"></div>
            <div id="tpBanner"></div>

            <div class="tp-split">
                <div>
                    <div class="tp-tabs" role="tablist" id="tpTabs" aria-label="Bagian angkatan">
                        <button type="button" class="tp-tab" role="tab" data-t="sesi" aria-selected="true">Sesi dan kurikulum</button>
                        <button type="button" class="tp-tab" role="tab" data-t="siswa" aria-selected="false">Siswa</button>
                        <button type="button" class="tp-tab" role="tab" data-t="tugas" aria-selected="false">Tugas</button>
                        <button type="button" class="tp-tab" role="tab" data-t="nilai" aria-selected="false">Nilai</button>
                    </div>
                    <section class="tp-panel" id="panel-sesi"></section>
                    <section class="tp-panel" id="panel-siswa" hidden></section>
                    <section class="tp-panel" id="panel-tugas" hidden></section>
                    <section class="tp-panel" id="panel-nilai" hidden></section>
                </div>
                <aside>
                    <div class="tp-card tp-stack tp-sticky" id="tpSummary"></div>
                </aside>
            </div>
        </main>
<?php end_layout(); ?>