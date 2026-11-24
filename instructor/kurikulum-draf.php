<?php
// Direktori: instructor/kurikulum-draf.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/kurikulum-draf',
    'roles' => ['instruktur'],
    'title' => 'Isi Kurikulum - ELIPS LMS',
    'area' => 'instructor',
    'active' => 'instructor/kurikulum.php',
    'css' => ['tentor-app'],
    'scripts' => ['ui', 'pages/instructor/tp-core'],
    'sidebar' => 'instructor',
];
layout('app', $page);
?>
        <main class="dashboard-main tp-main">
            <header class="tp-head">
                <div>
                    <a class="tp-crumb" href="<?= url('instructor/kurikulum.php') ?>"><i class="bi bi-arrow-left"></i> Kurikulum</a>
                    <h1 class="tp-title" id="tpTitle">Memuat...</h1>
                    <p class="tp-sub" id="tpSub"></p>
                </div>
                <div class="tp-field" id="tpVersionBox" hidden style="min-width: 240px">
                    <label for="tpVersion">Versi kurikulum</label>
                    <select id="tpVersion" class="form-input"></select>
                </div>
            </header>

            <div id="tpError"></div>

            <div class="tp-split tp-split--left">
                <aside>
                    <div class="tp-card tp-sticky" style="padding: 0">
                        <div class="tp-tree" id="tpTree"><div class="tp-skel" style="height: 220px; margin: 0.5rem"></div></div>
                    </div>
                </aside>
                <section id="tpEditor"></section>
            </div>

            <div class="tp-savebar" id="tpSaveBar" hidden>
                <div>
                    <div class="tp-card-title" id="tpSaveTitle"></div>
                    <div class="tp-card-muted" id="tpSaveText"></div>
                </div>
                <div class="tp-row">
                    <button type="button" class="btn btn-ghost" id="tpDiscard">Batalkan</button>
                    <button type="button" class="btn btn-primary" id="tpSave"><i class="bi bi-check2"></i> Simpan perubahan</button>
                </div>
            </div>
        </main>
<?php end_layout(); ?>