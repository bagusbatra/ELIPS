<?php
// Direktori: instructor/penilaian-tugas.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/penilaian-tugas',
    'roles' => ['instruktur'],
    'title' => 'Menilai Tugas - ELIPS LMS',
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
                    <h1 class="tp-title" id="tpTitle">Memuat...</h1>
                    <p class="tp-sub" id="tpSub"></p>
                </div>
            </header>

            <div id="tpError"></div>

            <div class="tp-split tp-split--left">
                <aside>
                    <div class="tp-card tp-stack tp-sticky">
                        <input type="search" class="form-input" id="tpSearch" placeholder="Cari siswa" aria-label="Cari siswa">
                        <div class="tp-row" id="tpFilters" role="group" aria-label="Filter siswa"></div>
                        <div class="tp-student-list" id="tpStudents"></div>
                    </div>
                </aside>
                <section id="tpDetail"></section>
            </div>
        </main>
<?php end_layout(); ?>