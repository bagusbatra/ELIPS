<?php
// Direktori: instructor/penilaian.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/penilaian',
    'roles' => ['instruktur'],
    'title' => 'Penilaian - ELIPS LMS',
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
                    <h1 class="tp-title">Penilaian</h1>
                    <p class="tp-sub">Nilai tugas siswa per angkatan. Data siswa dan progres selalu tampil; nilai akhir aktif setelah admin menutup pembelajaran dan materi siswa terkonfirmasi.</p>
                </div>
            </header>

            <div id="tpError"></div>
            <div class="tp-cards" id="tpCards"><div class="tp-skel" style="height: 260px"></div><div class="tp-skel" style="height: 260px"></div></div>
        </main>
<?php end_layout(); ?>