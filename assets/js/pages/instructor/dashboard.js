// Direktori: assets/js/pages/instructor/dashboard.js
// ELIPS LMS - dashboard tentor (instructor/dashboard.php)
document.addEventListener('DOMContentLoaded', function () {
    const user = TP.init();
    if (!user) return;

    const $ = function (id) { return document.getElementById(id); };

    TP.data('tp.dashboard').then(function (res) {
        render(res.data);
    }).catch(function (err) {
        $('tpGreeting').textContent = 'Ringkasan belum dapat dimuat.';
        $('tpStats').innerHTML = '';
        $('tpError').innerHTML = TP.errorBox(err.message);
    });

    function render(d) {
        const first = String(d.name || '').split(',')[0];
        $('tpGreeting').textContent = 'Halo, ' + first + '. ' + TP.date(d.today) + '.';

        const stats = [
            { value: d.stats.classes, label: 'Angkatan diampu', hint: 'Berjalan dan pendaftaran' },
            { value: d.stats.sessionsToday, label: 'Sesi hari ini', hint: d.stats.sessionsToday ? 'Lihat jadwal di bawah' : 'Tidak ada jadwal' },
            { value: d.stats.courses, label: 'Kursus diampu', hint: 'Kursus berbeda' },
            { value: d.stats.pending, label: 'Tugas perlu dinilai', hint: d.stats.pending ? 'Menunggu nilai Anda' : 'Semua sudah dinilai', accent: d.stats.pending > 0 }
        ];
        $('tpStats').innerHTML = stats.map(function (s, i) {
            return '<div class="tp-card tp-stat' + (s.accent ? ' tp-stat--accent' : '') + ' tp-rise" style="animation-delay:' + (i * 60) + 'ms">' +
                '<span class="tp-stat-label">' + TP.esc(s.label) + '</span>' +
                '<span class="tp-stat-value">' + TP.esc(s.value) + '</span>' +
                '<span class="tp-stat-hint">' + TP.esc(s.hint) + '</span></div>';
        }).join('');

        renderToday(d.todaySessions);
        renderClasses(d.classes);
        renderTodo(d);
        TP.animateBars(document);
    }

    function renderToday(list) {
        const box = $('tpToday');
        if (!list.length) {
            box.innerHTML = '<div class="tp-card">' + TP.empty('bi-calendar-check', 'Tidak ada sesi hari ini', 'Jadwal pertemuan Anda akan muncul di sini.') + '</div>';
            return;
        }
        box.innerHTML = list.map(function (s) {
            return '<div class="tp-card tp-row tp-row--between tp-rise" style="margin-bottom:.75rem">' +
                '<div class="tp-grow"><div class="tp-card-title">Sesi ' + s.number + (s.title && !/^sesi\s*\d+$/i.test(s.title) ? ' · ' + TP.esc(s.title) : '') + '</div>' +
                '<div class="tp-card-muted">' + TP.esc(s.className) + (s.start ? ' · ' + TP.esc(s.start) + (s.end ? '–' + TP.esc(s.end) : '') : '') + '</div></div>' +
                (s.confirmed ? TP.badge('Kelas dikonfirmasi', 'teal') : TP.badge('Menunggu konfirmasi', 'amber')) +
                '<a class="btn btn-ghost tp-btn-sm" href="angkatan-detail.php?id=' + s.classId + '">Lihat sesi</a></div>';
        }).join('');
    }

    function renderClasses(list) {
        const box = $('tpClasses');
        if (!list.length) {
            box.innerHTML = '<div class="tp-card">' + TP.empty('bi-people', 'Belum ada angkatan', 'Angkatan yang ditugaskan admin akan muncul di sini.') + '</div>';
            return;
        }
        box.innerHTML = '<div class="tp-card" style="padding:0"><div class="tp-table-wrap"><table class="tp-table"><thead><tr>' +
            '<th>Angkatan</th><th>Status</th><th>Siswa</th><th>Sesi</th><th></th></tr></thead><tbody>' +
            list.map(function (c) {
                const quota = c.status === 'open' || c.status === 'ready' ? c.students + ' dari ' + (c.quota || '∞') : String(c.students);
                return '<tr><td><div class="tp-table-title">' + TP.esc(c.name) + '</div><div class="tp-table-sub">' + TP.esc(c.courseTitle) + '</div></td>' +
                    '<td>' + TP.classBadge(c) + '</td><td>' + quota + '</td>' +
                    '<td>' + (c.sessionsTotal ? c.sessionsConfirmed + ' dari ' + c.sessionsTotal : '-') + '</td>' +
                    '<td class="is-num"><a class="tp-link" href="angkatan-detail.php?id=' + c.id + '">Buka <i class="bi bi-arrow-right"></i></a></td></tr>';
            }).join('') + '</tbody></table></div></div>';
    }

    function todo(icon, tone, title, text, href) {
        return '<a class="tp-card tp-card--link tp-row tp-rise" style="text-decoration:none;color:inherit;flex-wrap:nowrap" href="' + href + '">' +
            '<span class="tp-badge tp-badge--' + tone + '" style="padding:.5rem"><i class="bi ' + icon + '"></i></span>' +
            '<span class="tp-grow"><span class="tp-card-title" style="display:block">' + TP.esc(title) + '</span>' +
            '<span class="tp-card-muted">' + TP.esc(text) + '</span></span><i class="bi bi-chevron-right" style="color:var(--text-light)"></i></a>';
    }

    function renderTodo(d) {
        const items = [];
        if (d.stats.pending > 0) {
            items.push(todo('bi-clipboard-check', 'amber', d.stats.pending + ' tugas perlu dinilai', 'Buka halaman Penilaian', 'penilaian.php'));
        }
        d.finalOpen.forEach(function (c) {
            items.push(todo('bi-award', 'teal', 'Penilaian akhir ' + c.name, 'Semua sesi sudah dikonfirmasi. Isi nilai akhir siswa.', 'penilaian-akhir.php?id=' + c.id));
        });
        d.needsTask.forEach(function (n) {
            items.push(todo('bi-journal-plus', 'blue', 'Sesi ' + n.number + ' belum punya penugasan', n.title, 'kurikulum-draf.php?course=' + n.courseId + '&version=' + n.versionId + '&lesson=' + n.lessonId));
        });
        d.classes.filter(function (c) { return c.status === 'open' || c.status === 'ready'; }).forEach(function (c) {
            items.push(todo('bi-person-plus', 'coral', c.name + ' pendaftaran ' + c.students + (c.quota ? '/' + c.quota : ''), 'Pantau jumlah pendaftar', 'angkatan-detail.php?id=' + c.id));
        });
        $('tpTodo').innerHTML = items.length ? items.join('') : '<div class="tp-card">' + TP.empty('bi-check2-circle', 'Semua beres', 'Tidak ada yang perlu ditindaklanjuti.') + '</div>';
    }
});