// Direktori: assets/js/pages/instructor/kurikulum.js
// ELIPS LMS - pilih kursus untuk kurikulum (instructor/kurikulum.php)
document.addEventListener('DOMContentLoaded', function () {
    if (!TP.init()) return;
    const $ = function (id) { return document.getElementById(id); };

    TP.data('tp.curriculum.courses').then(function (res) {
        const courses = res.data.courses;
        if (!courses.length) {
            $('tpCards').innerHTML = '<div class="tp-card" style="grid-column:1/-1">' + TP.empty('bi-journal-text', 'Belum ada kursus', 'Kursus yang Anda ampu akan muncul di sini.') + '</div>';
            return;
        }
        $('tpCards').innerHTML = courses.map(function (c, i) {
            const has = c.classes.length > 0;
            const rows = has ? c.classes.map(function (cl) {
                return '<div class="tp-row tp-row--between"><span style="font-weight:500">' + TP.esc(cl.name) + '</span>' + TP.classBadge(cl) + '</div>';
            }).join('') : '<div class="tp-card-muted">Belum ada angkatan berjalan atau pendaftaran.</div>';
            return '<article class="tp-card tp-card--link tp-stack tp-rise" style="animation-delay:' + (i * 50) + 'ms">' +
                '<div><div class="tp-card-title" style="font-size:1.125rem">' + TP.esc(c.title) + '</div>' +
                '<div class="tp-card-muted">' + c.modules + ' modul · ' + c.sessions + ' sesi</div></div>' +
                '<div class="tp-stack" style="gap:.5rem">' + rows + '</div>' +
                (has ? '<a class="btn btn-primary tp-btn-sm" style="align-self:flex-start" href="kurikulum-draf.php?course=' + c.courseId + '">Buka kurikulum</a>' : '') +
                '</article>';
        }).join('');
    }).catch(function (err) {
        $('tpCards').innerHTML = '';
        $('tpError').innerHTML = TP.errorBox(err.message);
    });
});