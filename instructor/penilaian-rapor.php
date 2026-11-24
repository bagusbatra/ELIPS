<?php
// Direktori: instructor/penilaian-rapor.php
require_once __DIR__ . '/../includes/bootstrap.php';
$page = [
    'page_script' => 'instructor/penilaian-rapor',
    'roles' => ['instruktur'],
    'title' => 'Nilai Akhir Siswa - ELIPS LMS',
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
                    <a class="tp-crumb" id="tpCrumb" href="<?= url('instructor/penilaian.php') ?>"><i class="bi bi-arrow-left"></i> Penilaian akhir</a>
                    <h1 class="tp-title"><span id="tpName">Memuat...</span> <span id="tpBadge"></span></h1>
                    <p class="tp-sub" id="tpSub"></p>
                </div>
                <div class="tp-actions">
                    <button type="button" class="btn btn-ghost" id="tpPrev" disabled>Sebelumnya</button>
                    <button type="button" class="btn btn-ghost" id="tpNext" disabled>Berikutnya</button>
                </div>
            </header>

            <div id="tpError"></div>
            <div id="tpBanner"></div>

            <div class="tp-split">
                <section class="tp-card" style="padding: 0; overflow: hidden">
                    <div class="tp-toolbar" id="tpToolbar">
                        <button type="button" class="btn btn-primary" id="tpAddGroup"><i class="bi bi-plus-lg"></i> MATERI</button>
                        <button type="button" class="btn btn-outline" id="tpAddItem"><i class="bi bi-plus-lg"></i> SUB MATERI</button>
                        <span class="tp-saved"><i class="bi bi-check2-circle"></i> Format tersimpan otomatis sebagai template</span>
                    </div>
                    <div class="tp-table-wrap">
                        <table class="tp-table tp-rapor" id="tpTable">
                            <thead>
                                <tr><th>Materi / sub materi</th><th class="is-narrow">Nilai</th><th class="is-narrow">Keterangan</th><th class="is-jp">JP</th></tr>
                            </thead>
                            <tbody id="tpRows"><tr><td colspan="4"><div class="tp-skel" style="height: 220px"></div></td></tr></tbody>
                            <tfoot>
                                <tr class="tp-rapor-foot">
                                    <td>Rata-rata dan total JP</td>
                                    <td id="tpAvg">-</td>
                                    <td id="tpPred">-</td>
                                    <td class="is-num" id="tpJp">0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <aside class="tp-stack">
                    <div class="tp-card" style="padding: 1rem">
                        <div class="tp-row tp-row--between" style="padding: 0 0.25rem 0.5rem">
                            <h2 class="tp-card-title">Siswa angkatan</h2>
                            <span class="tp-card-muted" id="tpCountDone"></span>
                        </div>
                        <div class="tp-student-list" id="tpStudents"></div>
                    </div>

                    <div class="tp-card tp-stack">
                        <h2 class="tp-card-title">Ringkasan</h2>
                        <div class="tp-meter"><span>Sub materi terisi</span><span id="sFilled">0 dari 0</span></div>
                        <div id="sBar"></div>
                        <div class="tp-meter"><span>Rata-rata</span><span id="sAvg">-</span></div>
                        <div class="tp-meter"><span>Keterangan</span><span id="sPred">-</span></div>
                        <div class="tp-meter"><span>Total JP</span><span id="sJp">0</span></div>
                    </div>

                    <div class="tp-card tp-stack">
                        <h2 class="tp-card-title">Keterangan nilai</h2>
                        <div class="tp-stack" id="tpRanges" style="gap: 0.5rem"></div>
                        <span class="tp-hint">Rentang diatur oleh admin.</span>
                    </div>

                    <div class="tp-stack" id="tpButtons">
                        <button type="button" class="btn btn-ghost" id="tpSaveDraft">Simpan draf</button>
                        <button type="button" class="btn btn-primary" id="tpSaveNext">Simpan dan lanjut ke siswa berikutnya</button>
                    </div>
                </aside>
            </div>
        </main>
<?php end_layout(); ?>