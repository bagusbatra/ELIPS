// Direktori: assets/js/pages/instructor/penilaian.js
// ELIPS LMS - kartu penilaian per angkatan (instructor/penilaian.php)
document.addEventListener('DOMContentLoaded', function () {
    if (!TP.init()) return;
    const $ = function (id) { return document.getElementById(id); };

    TP.data('tp.grading.classes').then(function (res) {
        const list = res.data.classes;
        if (!list.length) {
            $('tpCards').innerHTML = '<div class="tp-card" style="grid-column:1/-1">' + TP.empty('bi-clipboard-check', 'Belum ada angkatan yang dinilai', 'Angkatan yang ditugaskan kepada Anda akan muncul di sini.') + '</div>';
            return;
        }
        $('tpCards').innerHTML = list.map(function (c, i) {
            const gradedText = c.submissions ? 'Tugas dinilai ' + c.graded + ' dari ' + c.submissions : 'Belum ada tugas yang dikumpulkan';
            let final;
            if (c.finalState === 'sent') {
                final = '<div class="tp-note tp-note--teal"><i class="bi bi-check2-circle"></i><span>Nilai akhir sudah dikirim ke operasional.</span></div>';
            } else if (c.finalState === 'open') {
                final = '<div class="tp-note tp-note--amber"><i class="bi bi-award"></i><span>' + c.studentsReady + ' dari ' + c.students + ' siswa siap dinilai. Nilai lengkap: ' + c.finalComplete + '.</span></div>';
            } else {
                final = '<div class="tp-card-muted"><i class="bi bi-lock"></i> ' + (c.status === 'completed' ? 'Belum ada siswa yang semua materinya terkonfirmasi.' : 'Mengisi nilai akhir aktif setelah admin menutup pembelajaran (siswa siap: ' + c.studentsReady + ' dari ' + c.students + ').') + '</div>';
            }
            return '<article class="tp-card tp-card--link tp-stack tp-rise" style="animation-delay:' + (i * 50) + 'ms">' +
                '<div class="tp-row tp-row--between" style="align-items:flex-start"><div><div class="tp-card-title" style="font-size:1.125rem">' + TP.esc(c.name) + '</div>' +
                '<div class="tp-card-muted">' + TP.esc(c.courseTitle) + ' · ' + c.students + ' siswa</div></div>' + TP.classBadge(c) + '</div>' +
                '<div class="tp-grid-2"><div><div class="tp-stat-value" style="font-size:1.5rem">' + c.pending + '</div><div class="tp-stat-label">Perlu dinilai</div></div>' +
                '<div><div class="tp-stat-value" style="font-size:1.5rem">' + (c.average === null ? '-' : TP.num(c.average, 1)) + '</div><div class="tp-stat-label">Rata-rata tugas</div></div></div>' +
                '<div>' + TP.progress(c.graded, c.submissions, 'teal') + '<div class="tp-card-muted" style="margin-top:.375rem">' + gradedText + '</div></div>' +
                final +
                '<div class="tp-row"><a class="btn btn-primary tp-btn-sm" href="penilaian-angkatan.php?id=' + c.id + '">Buka penilaian</a>' +
                (c.finalState === 'open' ? '<a class="btn btn-outline tp-btn-sm" href="penilaian-akhir.php?id=' + c.id + '">Isi penilaian akhir</a>' : '') +
                (c.finalState === 'locked' ? '<a class="btn btn-ghost tp-btn-sm" href="penilaian-akhir.php?id=' + c.id + '">Lihat siswa &amp; progres</a>' : '') +
                (c.finalState === 'sent' ? '<a class="btn btn-ghost tp-btn-sm" href="penilaian-akhir.php?id=' + c.id + '">Lihat nilai akhir</a>' : '') + '</div></article>';
        }).join('');
        TP.animateBars($('tpCards'));
    }).catch(function (err) {
        $('tpCards').innerHTML = '';
        $('tpError').innerHTML = TP.errorBox(err.message);
    });
});