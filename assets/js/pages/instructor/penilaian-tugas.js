// Direktori: assets/js/pages/instructor/penilaian-tugas.js
// ============================================================
// ELIPS LMS - menilai tugas siswa (instructor/penilaian-tugas.php)
// Satu isian: Nilai tugas. Enter menyimpan dan lanjut ke siswa berikutnya.
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    if (!TP.init()) return;
    const $ = function (id) { return document.getElementById(id); };
    const assignmentId = TP.id('assignment');

    let a = null;        // penugasan
    let students = [];
    let current = 0;     // enrollmentId aktif
    let filter = 'semua';
    let query = '';
    let saving = false;

    if (!assignmentId) {
        $('tpTitle').textContent = 'Tugas tidak dipilih';
        $('tpError').innerHTML = TP.errorBox('Pilih tugas dari halaman Penilaian angkatan.');
        return;
    }

    TP.data('tp.grading.submissions', { assignmentId: assignmentId }).then(function (res) {
        a = res.data.assignment;
        students = res.data.students;
        document.title = a.title + ' - ELIPS LMS';
        $('tpTitle').textContent = a.title;
        $('tpSub').textContent = a.className + (a.sessionNumber ? ' · Sesi ' + a.sessionNumber : '') + ' · Skala 0–' + TP.num(a.maxScore, 0);
        $('tpCrumb').innerHTML = '<i class="bi bi-arrow-left"></i> ' + TP.esc(a.className);
        $('tpCrumb').href = 'penilaian-angkatan.php?id=' + a.classId;

        const want = TP.id('student');
        const first = students.find(function (s) { return s.enrollmentId === want; }) ||
            students.find(function (s) { return s.state === 'perlu'; }) || students[0];
        current = first ? first.enrollmentId : 0;
        renderFilters();
        renderList();
        renderDetail();
    }).catch(function (err) {
        $('tpTitle').textContent = 'Tugas tidak dapat dibuka';
        $('tpError').innerHTML = TP.errorBox(err.message);
    });

    function count(state) {
        return students.filter(function (s) { return state === 'semua' || s.state === state; }).length;
    }

    function renderFilters() {
        const defs = [['semua', 'Semua'], ['perlu', 'Perlu dinilai'], ['dinilai', 'Dinilai'], ['belum', 'Belum mengumpulkan']];
        $('tpFilters').innerHTML = defs.map(function (d) {
            return '<button type="button" class="tp-badge' + (filter === d[0] ? ' tp-badge--amber' : '') + '" style="border:none;cursor:pointer" data-f="' + d[0] + '" aria-pressed="' + (filter === d[0]) + '">' + d[1] + ' ' + count(d[0]) + '</button>';
        }).join('');
    }

    function visible() {
        return students.filter(function (s) {
            return (filter === 'semua' || s.state === filter) && (!query || s.name.toLowerCase().indexOf(query) >= 0);
        });
    }

    function renderList() {
        const list = visible();
        $('tpStudents').innerHTML = list.length ? list.map(function (s) {
            const dot = s.state === 'dinilai' ? 'tp-dot--teal' : s.state === 'perlu' ? 'tp-dot--amber' : '';
            const meta = s.state === 'dinilai' ? TP.num(s.score) : s.state === 'perlu' ? 'Perlu dinilai' : 'Belum';
            return '<button type="button" class="tp-student' + (s.enrollmentId === current ? ' is-active' : '') + '" data-e="' + s.enrollmentId + '">' +
                '<span class="tp-dot ' + dot + '"></span><span class="tp-grow" style="overflow:hidden;text-overflow:ellipsis">' + TP.esc(s.name) + '</span>' +
                '<span class="tp-card-muted">' + meta + '</span></button>';
        }).join('') : TP.empty('bi-search', 'Tidak ada siswa', 'Ubah filter atau kata kunci.');
    }

    $('tpFilters').addEventListener('click', function (event) {
        const btn = event.target.closest('[data-f]');
        if (!btn) return;
        filter = btn.getAttribute('data-f');
        renderFilters();
        renderList();
    });
    $('tpSearch').addEventListener('input', TP.debounce(function () {
        query = $('tpSearch').value.trim().toLowerCase();
        renderList();
    }, 150));
    $('tpStudents').addEventListener('click', function (event) {
        const btn = event.target.closest('[data-e]');
        if (!btn) return;
        current = Number(btn.getAttribute('data-e'));
        renderList();
        renderDetail();
    });

    function fileHref(path) {
        if (/^(https?:|data:)/i.test(path)) return path;
        return '../' + String(path).replace(/^\/+/, '');
    }

    function nextPending(after) {
        const idx = students.findIndex(function (s) { return s.enrollmentId === after; });
        for (let i = idx + 1; i < students.length; i++) if (students[i].state === 'perlu') return students[i];
        for (let j = 0; j < idx; j++) if (students[j].state === 'perlu') return students[j];
        return null;
    }

    function renderDetail() {
        const s = students.find(function (x) { return x.enrollmentId === current; });
        if (!s) {
            $('tpDetail').innerHTML = '<div class="tp-card">' + TP.empty('bi-people', 'Belum ada siswa', 'Siswa yang aktif pada angkatan ini akan muncul di sini.') + '</div>';
            return;
        }
        const next = nextPending(s.enrollmentId);
        const state = s.state === 'dinilai' ? TP.badge('Sudah dinilai', 'teal') : s.state === 'perlu' ? TP.badge('Perlu dinilai', 'amber') : TP.badge('Belum mengumpulkan', 'gray');

        let work = '';
        if (s.state === 'belum') {
            work = TP.empty('bi-hourglass', 'Belum mengumpulkan', 'Nilai bisa diisi setelah siswa mengumpulkan tugas.');
        } else {
            work = '<div class="tp-row">' + (s.isLate ? TP.badge('Terlambat', 'coral') : '') + '<span class="tp-card-muted">Dikumpulkan ' + TP.date(s.submittedAt) + '</span></div>' +
                (s.files.length ? '<div class="tp-stack" style="gap:.5rem">' + s.files.map(function (f) {
                    return '<a class="tp-card tp-card--link tp-row" style="padding:.75rem 1rem;text-decoration:none;color:inherit" target="_blank" rel="noopener" href="' + TP.esc(fileHref(f.path)) + '"><i class="bi bi-file-earmark"></i><span class="tp-grow">' + TP.esc(f.name) + '</span><i class="bi bi-box-arrow-up-right"></i></a>';
                }).join('') + '</div>' : '') +
                (s.text ? '<div class="tp-note" style="background:#f7f5f1;color:inherit;white-space:pre-wrap">' + TP.esc(s.text) + '</div>' : '') +
                (!s.files.length && !s.text ? '<div class="tp-card-muted">Tidak ada berkas atau jawaban teks.</div>' : '');
        }

        const form = s.state === 'belum' ? '' :
            '<div class="tp-field" style="max-width:14rem"><label for="tpScore">Nilai tugas</label>' +
            '<div class="tp-row"><input id="tpScore" class="tp-score" inputmode="decimal" autocomplete="off" value="' + (s.score === null ? '' : TP.esc(String(s.score).replace('.', ','))) + '" aria-describedby="tpScoreHint">' +
            '<span class="tp-card-muted">/ ' + TP.num(a.maxScore, 0) + '</span></div>' +
            '<span class="tp-hint" id="tpScoreHint">Skala 0–' + TP.num(a.maxScore, 0) + '. Tekan Enter untuk simpan dan lanjut.</span></div>' +
            '<div class="tp-row"><button type="button" class="btn btn-primary" id="tpSaveNext" ' + (next ? '' : 'hidden') + '>Simpan dan lanjut ke ' + TP.esc(next ? next.name.split(' ')[0] : '') + '</button>' +
            '<button type="button" class="btn ' + (next ? 'btn-ghost' : 'btn-primary') + '" id="tpSaveOnly">Simpan nilai</button></div>';

        $('tpDetail').innerHTML = '<div class="tp-card tp-stack tp-rise">' +
            '<div class="tp-row tp-row--between"><div class="tp-row"><span class="tp-avatar">' + TP.esc(TP.initials(s.name)) + '</span><div class="tp-card-title" style="font-size:1.125rem">' + TP.esc(s.name) + '</div></div>' + state + '</div>' +
            (a.instructions ? '<div><div class="tp-label" style="margin-bottom:.25rem">Instruksi</div><div class="tp-card-muted" style="white-space:pre-wrap">' + TP.esc(a.instructions) + '</div></div>' : '') +
            work + form + '</div>';

        const input = $('tpScore');
        if (input) {
            input.focus();
            input.select();
            input.addEventListener('input', function () { input.classList.remove('is-bad'); });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); save(true); }
            });
        }
        const sn = $('tpSaveNext');
        if (sn) sn.addEventListener('click', function () { save(true); });
        const so = $('tpSaveOnly');
        if (so) so.addEventListener('click', function () { save(false); });
    }

    function save(advance) {
        if (saving) return;
        const s = students.find(function (x) { return x.enrollmentId === current; });
        const input = $('tpScore');
        if (!s || !input) return;
        const raw = input.value.trim().replace(',', '.');
        const value = Number(raw);
        if (raw === '' || isNaN(value) || value < 0 || value > a.maxScore) {
            input.classList.add('is-bad');
            TP.toast('Nilai harus angka 0–' + TP.num(a.maxScore, 0) + '.');
            input.focus();
            return;
        }
        saving = true;
        TP.data('tp.grading.score', { submissionId: s.submissionId, score: value, feedback: s.feedback }).then(function (res) {
            s.score = res.data.score;
            s.state = 'dinilai';
            TP.toast('Nilai ' + s.name.split(' ')[0] + ' tersimpan.');
            const next = advance ? nextPending(s.enrollmentId) : null;
            if (next) current = next.enrollmentId;
            renderFilters();
            renderList();
            renderDetail();
        }).catch(function (err) {
            TP.toast(err.message);
        }).then(function () { saving = false; });
    }
});