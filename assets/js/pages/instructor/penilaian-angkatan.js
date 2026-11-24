// Direktori: assets/js/pages/instructor/penilaian-angkatan.js
// ELIPS LMS - daftar tugas yang dinilai per angkatan (instructor/penilaian-angkatan.php)
document.addEventListener('DOMContentLoaded', function () {
    if (!TP.init()) return;
    const $ = function (id) { return document.getElementById(id); };
    const classId = TP.id('id');

    if (!classId) {
        $('tpName').textContent = 'Angkatan tidak dipilih';
        $('tpError').innerHTML = TP.errorBox('Pilih angkatan dari halaman Penilaian.');
        return;
    }

    TP.data('tp.grading.tasks', { classId: classId }).then(function (res) {
        const c = res.data.class;
        const tasks = res.data.tasks;
        document.title = 'Penilaian ' + c.name + ' - ELIPS LMS';
        $('tpName').textContent = c.name;
        $('tpBadge').innerHTML = TP.classBadge(c);
        $('tpSub').textContent = c.courseTitle + ' · ' + c.students + ' siswa';

        const stats = [
            { v: c.pending, l: 'Perlu dinilai', accent: c.pending > 0 },
            { v: c.average === null ? '-' : TP.num(c.average, 1), l: 'Rata-rata tugas' },
            { v: c.graded + ' / ' + c.submissions, l: 'Tugas dinilai' },
            { v: tasks.length, l: 'Jumlah tugas' }
        ];
        $('tpStats').innerHTML = stats.map(function (s, i) {
            return '<div class="tp-card tp-stat' + (s.accent ? ' tp-stat--accent' : '') + ' tp-rise" style="animation-delay:' + (i * 50) + 'ms"><span class="tp-stat-label">' + s.l + '</span><span class="tp-stat-value">' + TP.esc(s.v) + '</span></div>';
        }).join('');

        if (!tasks.length) {
            $('tpTasks').innerHTML = '<div class="tp-card">' + TP.empty('bi-card-checklist', 'Belum ada tugas', 'Tambahkan penugasan pada sesi di halaman Kurikulum.') + '</div>';
        } else {
            $('tpTasks').innerHTML = '<div class="tp-card" style="padding:0"><div class="tp-table-wrap"><table class="tp-table"><thead><tr>' +
                '<th>Tugas</th><th>Tenggat</th><th>Terkumpul</th><th>Status</th><th></th></tr></thead><tbody>' +
                tasks.map(function (t) {
                    let status;
                    if (t.notOpen || (t.total === 0 && !t.pending)) status = TP.badge('Belum dibuka', 'gray');
                    else if (t.pending > 0) status = TP.badge(t.pending + ' perlu dinilai', 'amber');
                    else status = TP.badge('Selesai dinilai', 'teal');
                    return '<tr><td><div class="tp-table-title">' + TP.esc(t.title) + '</div>' + (t.sessionNumber ? '<div class="tp-table-sub">Sesi ' + t.sessionNumber + '</div>' : '') + '</td>' +
                        '<td>' + TP.date(t.dueAt) + '</td>' +
                        '<td style="min-width:8rem">' + TP.progress(t.graded, t.total, 'teal') + '<div class="tp-table-sub">' + t.graded + ' dari ' + t.total + ' dinilai</div></td>' +
                        '<td>' + status + '</td><td class="is-num"><a class="btn btn-primary tp-btn-sm" href="penilaian-tugas.php?assignment=' + t.id + '">Nilai</a></td></tr>';
                }).join('') + '</tbody></table></div></div>';
        }

        let final = '<div class="tp-card-title">Penilaian akhir</div>';
        if (c.finalState === 'sent') {
            final += '<div class="tp-note tp-note--teal"><i class="bi bi-check2-circle"></i><span>Nilai akhir sudah dikirim ke operasional.</span></div>' +
                '<a class="btn btn-ghost" href="penilaian-akhir.php?id=' + c.id + '">Lihat nilai akhir</a>';
        } else if (c.finalState === 'open') {
            final += '<div class="tp-note tp-note--teal"><i class="bi bi-unlock"></i><span>Semua ' + c.sessionsTotal + ' sesi sudah dikonfirmasi admin.</span></div>' +
                '<div class="tp-meter"><span>Siswa lengkap</span><span>' + c.finalComplete + ' dari ' + c.students + '</span></div>' +
                TP.progress(c.finalComplete, c.students, 'teal') +
                '<a class="btn btn-primary" href="penilaian-akhir.php?id=' + c.id + '">Isi penilaian akhir</a>';
        } else {
            final += '<div class="tp-note tp-note--amber"><i class="bi bi-lock"></i><span>Belum tersedia. Terbuka setelah semua sesi dikonfirmasi admin (' + c.sessionsConfirmed + ' dari ' + c.sessionsTotal + ').</span></div>' +
                TP.progress(c.sessionsConfirmed, c.sessionsTotal);
        }
        $('tpFinal').innerHTML = final;
        TP.animateBars(document);
    }).catch(function (err) {
        $('tpName').textContent = 'Penilaian tidak dapat dibuka';
        $('tpError').innerHTML = TP.errorBox(err.message);
    });
});