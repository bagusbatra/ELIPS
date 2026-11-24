// Direktori: assets/js/pages/instructor/angkatan-detail.js
// ELIPS LMS - detail angkatan tentor (instructor/angkatan-detail.php)
document.addEventListener('DOMContentLoaded', function () {
    if (!TP.init()) return;

    const classId = TP.id('id');
    const $ = function (id) { return document.getElementById(id); };
    let data = null;

    if (!classId) {
        $('tpName').textContent = 'Angkatan tidak ditemukan';
        $('tpError').innerHTML = TP.errorBox('Pilih angkatan dari halaman Angkatan saya.');
        return;
    }

    TP.data('tp.class.detail', { classId: classId }).then(function (res) {
        data = res.data;
        render();
        const hash = (window.location.hash || '').replace('#', '');
        if (['sesi', 'siswa', 'tugas', 'nilai'].indexOf(hash) >= 0) showTab(hash);
    }).catch(function (err) {
        $('tpName').textContent = 'Angkatan tidak dapat dibuka';
        $('tpError').innerHTML = TP.errorBox(err.message);
    });

    $('tpTabs').addEventListener('click', function (event) {
        const btn = event.target.closest('[data-t]');
        if (btn) showTab(btn.getAttribute('data-t'));
    });

    function showTab(name) {
        $('tpTabs').querySelectorAll('.tp-tab').forEach(function (b) {
            b.setAttribute('aria-selected', b.getAttribute('data-t') === name ? 'true' : 'false');
        });
        ['sesi', 'siswa', 'tugas', 'nilai'].forEach(function (key) { $('panel-' + key).hidden = key !== name; });
        if (history.replaceState) history.replaceState(null, '', '#' + name);
    }

    function lockedRange(sessions) {
        const nums = sessions.filter(function (s) { return s.locked; }).map(function (s) { return s.number; });
        if (!nums.length) return '';
        return nums.length === 1 ? 'Sesi ' + nums[0] : 'Sesi ' + nums[0] + '–' + nums[nums.length - 1];
    }

    function render() {
        const c = data.class;
        document.title = c.name + ' - ELIPS LMS';
        $('tpName').textContent = c.name;
        $('tpBadge').innerHTML = TP.classBadge(c);
        $('tpSub').textContent = c.courseTitle + ' · ' + TP.period(c.periodStart, c.periodEnd) + (c.location ? ' · ' + c.location : '');

        const acts = [];
        if (c.group !== 'pendaftaran') acts.push('<a class="btn btn-ghost" href="penilaian-angkatan.php?id=' + c.id + '"><i class="bi bi-clipboard-check"></i> Penilaian</a>');
        acts.push('<a class="btn btn-primary" href="kurikulum-draf.php?course=' + c.courseId + '&version=' + c.versionId + '"><i class="bi bi-pencil-square"></i> Isi kurikulum</a>');
        $('tpHeadActions').innerHTML = acts.join('');

        const range = lockedRange(data.sessions);
        const editable = data.sessions.filter(function (s) { return !s.locked; });
        $('tpBanner').innerHTML = range ?
            '<div class="tp-note tp-note--amber" style="margin-bottom:1.25rem"><i class="bi bi-lock"></i><span>' + range + ' sudah dikonfirmasi admin dan terkunci. ' +
            (editable.length ? 'Isi sesi lainnya masih bisa Anda edit.' : 'Semua sesi sudah terkunci.') + '</span></div>' : '';

        renderSessions();
        renderStudents();
        renderTasks();
        renderGrades();
        renderSummary();
        TP.animateBars(document);
    }

    function renderSessions() {
        const c = data.class;
        if (!data.sessions.length) {
            $('panel-sesi').innerHTML = '<div class="tp-card">' + TP.empty('bi-journal', 'Kurikulum belum memiliki sesi', 'Admin perlu menambahkan modul dan sesi lebih dulu.') + '</div>';
            return;
        }
        $('panel-sesi').innerHTML = '<div class="tp-card" style="padding:0"><div class="tp-table-wrap"><table class="tp-table"><thead><tr>' +
            '<th>No</th><th>Sesi</th><th>Jadwal</th><th class="is-num">JP</th><th>Isi</th><th>Status</th><th></th></tr></thead><tbody>' +
            data.sessions.map(function (s) {
                const link = 'kurikulum-draf.php?course=' + c.courseId + '&version=' + c.versionId + '&lesson=' + s.lessonId;
                return '<tr><td>' + s.number + '</td><td><div class="tp-table-title">' + TP.esc(s.title) + '</div><div class="tp-table-sub">' + TP.esc(s.moduleTitle) + '</div></td>' +
                    '<td>' + (s.date ? TP.date(s.date) + (s.start ? ' · ' + TP.esc(s.start) : '') : '<span class="tp-card-muted">Belum dijadwalkan</span>') + '</td>' +
                    '<td class="is-num">' + (s.durationJp === null ? '-' : TP.num(s.durationJp)) + '</td>' +
                    '<td class="tp-card-muted">' + s.materials + ' materi · ' + s.tasks + ' tugas</td>' +
                    '<td>' + (s.locked ? TP.badge('Terkonfirmasi', 'gray') : TP.badge('Dapat diedit', 'teal')) + '</td>' +
                    '<td class="is-num"><a class="tp-link" href="' + link + '">' + (s.locked ? TP.lockIcon() + ' Lihat' : 'Edit isi') + '</a></td></tr>';
            }).join('') + '</tbody></table></div></div>';
    }

    function renderStudents() {
        if (!data.students.length) {
            $('panel-siswa').innerHTML = '<div class="tp-card">' + TP.empty('bi-people', 'Belum ada siswa', 'Siswa yang mendaftar dan diaktifkan akan muncul di sini.') + '</div>';
            return;
        }
        $('panel-siswa').innerHTML = '<div class="tp-card" style="padding:0"><div class="tp-table-wrap"><table class="tp-table"><thead><tr>' +
            '<th>Siswa</th><th>Status</th><th class="is-num">Tugas terkumpul</th></tr></thead><tbody>' +
            data.students.map(function (s) {
                return '<tr><td><div class="tp-row"><span class="tp-avatar">' + TP.esc(TP.initials(s.name)) + '</span><div><div class="tp-table-title">' + TP.esc(s.name) + '</div>' +
                    '<div class="tp-table-sub">' + TP.esc(s.email) + '</div></div></div></td>' +
                    '<td>' + (s.status === 'active' ? TP.badge('Aktif', 'teal') : s.status === 'completed' ? TP.badge('Selesai', 'gray') : TP.badge('Menunggu', 'amber')) + '</td>' +
                    '<td class="is-num">' + s.submitted + '</td></tr>';
            }).join('') + '</tbody></table></div></div>';
    }

    function renderTasks() {
        if (!data.tasks.length) {
            $('panel-tugas').innerHTML = '<div class="tp-card">' + TP.empty('bi-card-checklist', 'Belum ada tugas', 'Tambahkan penugasan pada sesi di halaman Kurikulum.') + '</div>';
            return;
        }
        $('panel-tugas').innerHTML = '<div class="tp-card" style="padding:0"><div class="tp-table-wrap"><table class="tp-table"><thead><tr>' +
            '<th>Tugas</th><th>Tenggat</th><th class="is-num">Terkumpul</th><th class="is-num">Perlu dinilai</th><th></th></tr></thead><tbody>' +
            data.tasks.map(function (t) {
                return '<tr><td><div class="tp-table-title">' + TP.esc(t.title) + '</div>' + (t.sessionNumber ? '<div class="tp-table-sub">Sesi ' + t.sessionNumber + '</div>' : '') + '</td>' +
                    '<td>' + TP.date(t.dueAt) + '</td><td class="is-num">' + t.total + '</td>' +
                    '<td class="is-num">' + (t.pending ? TP.badge(String(t.pending), 'amber') : '0') + '</td>' +
                    '<td class="is-num"><a class="tp-link" href="penilaian-tugas.php?assignment=' + t.id + '">Nilai <i class="bi bi-arrow-right"></i></a></td></tr>';
            }).join('') + '</tbody></table></div></div>';
    }

    function renderGrades() {
        const c = data.class;
        let html = '';
        if (c.finalState === 'open') {
            html += '<div class="tp-note tp-note--teal" style="margin-bottom:1rem"><i class="bi bi-award"></i><span>Semua sesi sudah dikonfirmasi. <a class="tp-link" href="penilaian-akhir.php?id=' + c.id + '">Isi penilaian akhir</a></span></div>';
        } else if (c.finalState === 'sent') {
            html += '<div class="tp-note tp-note--teal" style="margin-bottom:1rem"><i class="bi bi-check2-circle"></i><span>Nilai akhir sudah dikirim ke operasional.</span></div>';
        }
        if (!data.students.length) {
            $('panel-nilai').innerHTML = html + '<div class="tp-card">' + TP.empty('bi-bar-chart', 'Belum ada nilai', 'Nilai muncul setelah siswa mengumpulkan tugas.') + '</div>';
            return;
        }
        $('panel-nilai').innerHTML = html + '<div class="tp-card" style="padding:0"><div class="tp-table-wrap"><table class="tp-table"><thead><tr>' +
            '<th>Siswa</th><th class="is-num">Tugas terkumpul</th><th class="is-num">Perlu dinilai</th><th class="is-num">Rata-rata tugas</th></tr></thead><tbody>' +
            data.students.map(function (s) {
                return '<tr><td class="tp-table-title">' + TP.esc(s.name) + '</td><td class="is-num">' + s.submitted + '</td>' +
                    '<td class="is-num">' + s.pending + '</td><td class="is-num">' + (s.average === null ? '-' : TP.num(s.average, 1)) + '</td></tr>';
            }).join('') + '</tbody></table></div></div>';
    }

    function renderSummary() {
        const c = data.class;
        $('tpSummary').innerHTML = '<div class="tp-card-title">Ringkasan</div>' +
            '<div class="tp-meter"><span>Siswa</span><span>' + c.students + (c.quota ? ' / ' + c.quota : '') + '</span></div>' +
            '<div class="tp-meter"><span>Sesi terkonfirmasi</span><span>' + c.sessionsConfirmed + ' dari ' + c.sessionsTotal + '</span></div>' +
            TP.progress(c.sessionsConfirmed, c.sessionsTotal, 'teal') +
            '<div class="tp-meter"><span>Tugas perlu dinilai</span><span>' + c.pending + '</span></div>' +
            '<div class="tp-meter"><span>Rata-rata tugas</span><span>' + (c.average === null ? '-' : TP.num(c.average, 1)) + '</span></div>';
    }
});