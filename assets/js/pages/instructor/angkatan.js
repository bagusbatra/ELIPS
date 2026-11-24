// Direktori: assets/js/pages/instructor/angkatan.js
// ELIPS LMS - daftar angkatan tentor (instructor/angkatan.php)
document.addEventListener('DOMContentLoaded', function () {
    if (!TP.init()) return;

    let classes = [];
    let filter = 'all';
    const $ = function (id) { return document.getElementById(id); };

    TP.data('tp.classes').then(function (res) {
        classes = res.data.classes;
        ['all', 'berjalan', 'pendaftaran', 'selesai'].forEach(function (key) {
            const n = key === 'all' ? classes.length : classes.filter(function (c) { return c.group === key; }).length;
            const el = $('c' + key.charAt(0).toUpperCase() + key.slice(1));
            if (el) el.textContent = '(' + n + ')';
        });
        render();
    }).catch(function (err) {
        $('tpCards').innerHTML = '';
        $('tpError').innerHTML = TP.errorBox(err.message);
    });

    $('tpFilter').addEventListener('click', function (event) {
        const btn = event.target.closest('[data-f]');
        if (!btn) return;
        filter = btn.getAttribute('data-f');
        $('tpFilter').querySelectorAll('.tp-tab').forEach(function (b) {
            b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
        });
        render();
    });

    function render() {
        const list = classes.filter(function (c) { return filter === 'all' || c.group === filter; });
        if (!list.length) {
            $('tpCards').innerHTML = '<div class="tp-card" style="grid-column:1/-1">' + TP.empty('bi-people', 'Belum ada angkatan', 'Angkatan yang ditugaskan admin akan muncul di sini.') + '</div>';
            return;
        }
        $('tpCards').innerHTML = list.map(function (c, i) {
            const registering = c.group === 'pendaftaran';
            const done = c.group === 'selesai';
            const quotaText = registering ? c.students + ' dari ' + (c.quota || '∞') + ' terdaftar' : c.students + ' siswa';
            let bar = '';
            if (registering && c.quota) bar = TP.progress(c.students, c.quota) + '<div class="tp-card-muted">Kuota terisi</div>';
            else if (c.sessionsTotal) bar = TP.progress(c.sessionsConfirmed, c.sessionsTotal, done ? 'gray' : 'teal') +
                '<div class="tp-card-muted">Sesi ' + c.sessionsConfirmed + ' dari ' + c.sessionsTotal + ' terkonfirmasi</div>';
            return '<article class="tp-card tp-card--link tp-stack tp-rise" style="animation-delay:' + (i * 50) + 'ms">' +
                '<div class="tp-row tp-row--between" style="align-items:flex-start"><div><div class="tp-card-title" style="font-size:1.125rem">' + TP.esc(c.name) + '</div>' +
                '<div class="tp-card-muted">' + TP.esc(c.courseTitle) + '</div></div>' + TP.classBadge(c) + '</div>' +
                '<div class="tp-card-muted"><i class="bi bi-calendar3"></i> ' + TP.esc(TP.period(c.periodStart, c.periodEnd)) + '</div>' +
                '<div class="tp-card-muted"><i class="bi bi-people"></i> ' + quotaText + '</div>' +
                bar +
                (c.pending > 0 ? '<div>' + TP.badge(c.pending + ' tugas perlu dinilai', 'amber') + '</div>' : '') +
                '<div class="tp-row" style="margin-top:.25rem"><a class="btn btn-primary tp-btn-sm" href="angkatan-detail.php?id=' + c.id + '">Buka angkatan</a>' +
                (!registering && !done && c.pending > 0 ? '<a class="btn btn-ghost tp-btn-sm" href="penilaian-angkatan.php?id=' + c.id + '">Nilai tugas</a>' : '') + '</div></article>';
        }).join('');
        TP.animateBars($('tpCards'));
    }
});