// Direktori: assets/js/pages/instructor/penilaian-rapor.js
// ============================================================
// ELIPS LMS - nilai akhir per siswa (instructor/penilaian-rapor.php)
// Tentor menyusun materi/sub materi/JP sendiri lalu mengisi nilai.
// Struktur yang disimpan otomatis menjadi template kursus (server).
// Keterangan, rata-rata, dan total JP dihitung otomatis.
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    if (!TP.init()) return;
    const $ = function (id) { return document.getElementById(id); };
    const classId = TP.id('id');
    const enrollmentId = TP.id('student');

    let info = null;          // jawaban server
    let state = [];           // [{title, items:[{title, jp, score}]}]  (score berupa teks)
    let origSig = '';
    let ranges = [];
    let activeGroup = -1;
    let readOnly = false;
    let saving = false;

    if (!classId || !enrollmentId) {
        $('tpName').textContent = 'Siswa tidak dipilih';
        $('tpRows').innerHTML = '';
        $('tpError').innerHTML = TP.errorBox('Pilih siswa dari halaman Penilaian akhir.');
        return;
    }

    TP.data('tp.final.get', { classId: classId, enrollmentId: enrollmentId }).then(function (res) {
        info = res.data;
        ranges = info.ranges || [];
        readOnly = !(info.gate && info.gate.canEdit);
        state = info.structure.map(function (g) {
            return {
                title: g.title,
                items: g.items.map(function (it) {
                    return { title: it.title, jp: String(it.jp).replace('.', ','), score: it.score === null || it.score === undefined ? '' : String(it.score).replace('.', ',') };
                })
            };
        });
        origSig = sig();
        activeGroup = state.length ? state.length - 1 : -1;
        renderHeader();
        renderRows();
        renderAside();
        updateSummary();
    }).catch(function (err) {
        $('tpName').textContent = 'Nilai tidak dapat dibuka';
        $('tpRows').innerHTML = '';
        $('tpError').innerHTML = TP.errorBox(err.message);
    });

    // ---------- Utilitas nilai ----------

    function parseNum(text) {
        const raw = String(text === null || text === undefined ? '' : text).trim().replace(',', '.');
        if (raw === '') return null;
        const n = Number(raw);
        return isNaN(n) ? NaN : n;
    }

    function letterFor(score) {
        for (let i = 0; i < ranges.length; i++) {
            if (score >= ranges[i].minScore && score <= ranges[i].maxScore) return ranges[i].letter;
        }
        return '';
    }

    function sig() { return JSON.stringify(state); }
    function dirty() { return sig() !== origSig; }

    function predBadge(text) {
        const tone = text && ranges.length && text === ranges[0].letter ? 'teal' : 'blue';
        return text ? TP.badge(text, tone) : '<span class="tp-card-muted">-</span>';
    }

    // ---------- Render ----------

    function renderHeader() {
        const s = info.student;
        const c = info.class;
        document.title = s.name + ' - Nilai akhir - ELIPS LMS';
        $('tpName').textContent = s.name;
        $('tpCrumb').innerHTML = '<i class="bi bi-arrow-left"></i> Penilaian akhir ' + TP.esc(c.name);
        $('tpCrumb').href = 'penilaian-akhir.php?id=' + c.id;
        $('tpSub').textContent = c.name + ' · siswa ' + info.position + ' dari ' + info.students.length;
        $('tpBadge').innerHTML = info.sentAt ? TP.badge('Terkirim', 'teal') : readOnly ? TP.badge('Hanya lihat', 'gray') : (s.status === 'lengkap' ? TP.badge('Lengkap', 'teal') : TP.badge('Draf', 'amber'));
        $('tpPrev').disabled = !info.prevId;
        $('tpNext').disabled = !info.nextId;

        const notes = {
            student: '', assignments: 'Sub materi disusun dari penugasan Anda; pertemuan tanpa tugas tidak dimasukkan. Nilai diisi dari rata-rata nilai tugas siswa dan bisa Anda ubah. Sub materi yang tidak perlu dinilai bisa dihapus.', template: 'Struktur diambil dari template terakhir Anda. Ubah bila perlu, lalu simpan.',
            curriculum: 'Struktur awal diambil dari kurikulum. Sesuaikan materi, sub materi, dan JP sesuai transkrip.'
        };
        let banner = '';
        if (readOnly) banner = '<div class="tp-note ' + (info.sentAt ? 'tp-note--teal' : 'tp-note--amber') + '" style="margin-bottom:1.25rem"><i class="bi bi-lock"></i><span>' + TP.esc(info.gate.reason || 'Data hanya bisa dilihat.') + '</span></div>';
        else if (notes[info.source]) banner = '<div class="tp-note" style="margin-bottom:1.25rem"><i class="bi bi-info-circle"></i><span>' + notes[info.source] + '</span></div>';
        $('tpBanner').innerHTML = banner;

        if (readOnly) {
            $('tpToolbar').hidden = true;
            $('tpButtons').hidden = true;
        }
    }

    function removeBtn(kind, g, i) {
        return readOnly ? '' : '<button type="button" class="tp-icon-btn" data-remove="' + kind + '" data-g="' + g + '"' + (i === undefined ? '' : ' data-i="' + i + '"') + ' aria-label="Hapus"><i class="bi bi-x-lg"></i></button>';
    }

    function renderRows() {
        const ro = readOnly ? ' disabled' : '';
        let html = '';
        let row = 0;
        state.forEach(function (g, gi) {
            html += '<tr class="tp-rapor-group" data-g="' + gi + '"><td colspan="3"><input class="tp-ghost-input" data-f="gtitle" data-g="' + gi + '" placeholder="NAMA MATERI" value="' + TP.esc(g.title) + '" aria-label="Nama materi"' + ro + '></td>' +
                '<td class="is-num">' + removeBtn('group', gi) + '</td></tr>';
            g.items.forEach(function (it, ii) {
                const n = parseNum(it.score);
                const bad = n !== null && (isNaN(n) || n < 0 || n > 100);
                html += '<tr data-g="' + gi + '" data-i="' + ii + '">' +
                    '<td><div class="tp-row" style="flex-wrap:nowrap;gap:.25rem"><span class="tp-tree-no">' + (ii + 1) + '.</span><input class="tp-ghost-input" data-f="title" data-g="' + gi + '" data-i="' + ii + '" placeholder="Nama sub materi" value="' + TP.esc(it.title) + '" aria-label="Nama sub materi"' + ro + '></div></td>' +
                    '<td><input class="tp-score' + (bad ? ' is-bad' : '') + '" data-f="score" data-g="' + gi + '" data-i="' + ii + '" data-r="' + row + '" inputmode="decimal" autocomplete="off" value="' + TP.esc(it.score) + '" aria-label="Nilai ' + TP.esc(it.title) + '"' + ro + '></td>' +
                    '<td data-k="pred">' + predBadge(!bad && n !== null ? letterFor(n) : '') + '</td>' +
                    '<td class="is-num"><div class="tp-row" style="flex-wrap:nowrap;justify-content:flex-end;gap:.125rem"><input class="tp-ghost-input" data-f="jp" data-g="' + gi + '" data-i="' + ii + '" style="width:3.25rem;text-align:right" inputmode="decimal" value="' + TP.esc(it.jp) + '" aria-label="JP"' + ro + '>' + removeBtn('item', gi, ii) + '</div></td></tr>';
                row++;
            });
        });
        $('tpRows').innerHTML = html || '<tr><td colspan="4">' + TP.empty('bi-list-check', 'Belum ada materi', 'Klik MATERI untuk menambah materi pertama.') + '</td></tr>';
    }

    function renderAside() {
        $('tpStudents').innerHTML = info.students.map(function (s) {
            const dot = s.status === 'lengkap' ? 'tp-dot--teal' : s.status === 'draf' ? 'tp-dot--amber' : '';
            const active = s.enrollmentId === enrollmentId;
            return '<a class="tp-student' + (active ? ' is-active' : '') + '" data-nav="' + s.enrollmentId + '" href="penilaian-rapor.php?id=' + classId + '&student=' + s.enrollmentId + '">' +
                '<span class="tp-dot ' + dot + '"></span><span class="tp-grow" style="overflow:hidden;text-overflow:ellipsis">' + TP.esc(s.name) + '</span>' +
                (active ? '<span style="font-size:.6875rem;font-weight:600">Aktif</span>' : '<span class="tp-card-muted" style="font-size:.6875rem">' + (s.status === 'lengkap' ? 'Lengkap' : s.status === 'draf' ? 'Draf' : 'Belum diisi') + '</span>') + '</a>';
        }).join('');
        const done = info.students.filter(function (s) { return s.status === 'lengkap'; }).length;
        $('tpCountDone').textContent = done + ' dari ' + info.students.length + ' lengkap';
        $('tpRanges').innerHTML = ranges.map(function (r) {
            return '<div class="tp-row">' + TP.badge(r.letter, 'blue') + '<span class="tp-card-muted">' + TP.num(r.minScore, 2) + ' – ' + TP.num(r.maxScore, 2) + '</span></div>';
        }).join('');
    }

    function metrics() {
        let sum = 0, filled = 0, count = 0, jp = 0, invalid = 0;
        state.forEach(function (g) {
            g.items.forEach(function (it) {
                count++;
                const j = parseNum(it.jp);
                if (j !== null && !isNaN(j)) jp += j;
                const n = parseNum(it.score);
                if (n !== null) {
                    if (isNaN(n) || n < 0 || n > 100) invalid++;
                    else { filled++; sum += n; }
                }
            });
        });
        return { count: count, filled: filled, invalid: invalid, jp: jp, avg: filled ? sum / filled : null };
    }

    function updateSummary() {
        const m = metrics();
        const avgText = m.avg === null ? '-' : TP.num(m.avg);
        const letter = m.avg === null ? '' : letterFor(m.avg);
        $('tpAvg').textContent = avgText;
        $('tpPred').innerHTML = predBadge(letter);
        $('tpJp').textContent = TP.num(m.jp);
        $('sFilled').textContent = m.filled + ' dari ' + m.count;
        $('sBar').innerHTML = TP.progress(m.filled, m.count, 'teal');
        $('sAvg').textContent = avgText;
        $('sPred').textContent = letter || '-';
        $('sJp').textContent = TP.num(m.jp);
        TP.animateBars($('sBar'));
    }

    // ---------- Interaksi ----------

    $('tpRows').addEventListener('focusin', function (event) {
        const g = event.target.getAttribute && event.target.getAttribute('data-g');
        if (g !== null && g !== undefined && g !== '') activeGroup = Number(g);
    });

    $('tpRows').addEventListener('input', function (event) {
        const el = event.target;
        const f = el.getAttribute('data-f');
        if (!f || readOnly) return;
        const g = Number(el.getAttribute('data-g'));
        if (f === 'gtitle') {
            state[g].title = el.value;
            return;
        }
        const it = state[g].items[Number(el.getAttribute('data-i'))];
        it[f] = el.value;
        if (f === 'score') {
            const n = parseNum(el.value);
            const bad = n !== null && (isNaN(n) || n < 0 || n > 100);
            el.classList.toggle('is-bad', bad);
            el.closest('tr').querySelector('[data-k="pred"]').innerHTML = predBadge(!bad && n !== null ? letterFor(n) : '');
        }
        updateSummary();
    });

    // Enter pada kolom nilai pindah ke nilai berikutnya.
    $('tpRows').addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || event.target.getAttribute('data-f') !== 'score') return;
        event.preventDefault();
        const next = document.querySelector('[data-f="score"][data-r="' + (Number(event.target.getAttribute('data-r')) + 1) + '"]');
        if (next) { next.focus(); next.select(); }
        else $('tpSaveNext').focus();
    });

    $('tpRows').addEventListener('click', function (event) {
        const rem = event.target.closest('[data-remove]');
        if (!rem || readOnly) return;
        const g = Number(rem.getAttribute('data-g'));
        if (rem.getAttribute('data-remove') === 'group') {
            state.splice(g, 1);
            activeGroup = Math.min(activeGroup, state.length - 1);
        } else {
            state[g].items.splice(Number(rem.getAttribute('data-i')), 1);
            if (!state[g].items.length) { state.splice(g, 1); activeGroup = Math.min(activeGroup, state.length - 1); }
        }
        renderRows();
        updateSummary();
    });

    $('tpAddGroup').addEventListener('click', function () {
        if (readOnly) return;
        state.push({ title: '', items: [{ title: '', jp: '2', score: '' }] });
        activeGroup = state.length - 1;
        renderRows();
        updateSummary();
        const input = document.querySelector('[data-f="gtitle"][data-g="' + activeGroup + '"]');
        if (input) input.focus();
    });

    $('tpAddItem').addEventListener('click', function () {
        if (readOnly) return;
        if (!state.length) { $('tpAddGroup').click(); return; }
        const g = activeGroup >= 0 && activeGroup < state.length ? activeGroup : state.length - 1;
        const items = state[g].items;
        const lastJp = items.length ? items[items.length - 1].jp : '2';
        items.push({ title: '', jp: lastJp || '2', score: '' });
        activeGroup = g;
        renderRows();
        updateSummary();
        const input = document.querySelector('[data-f="title"][data-g="' + g + '"][data-i="' + (items.length - 1) + '"]');
        if (input) input.focus();
    });

    // ---------- Simpan ----------

    function payloadStructure() {
        return state.map(function (g) {
            return {
                title: g.title,
                items: g.items.map(function (it) {
                    return { title: it.title, jp: parseNum(it.jp) || 0, score: parseNum(it.score) };
                })
            };
        });
    }

    function validate() {
        if (!state.length) return 'Tambahkan minimal satu materi dan satu sub materi.';
        for (let g = 0; g < state.length; g++) {
            if (!state[g].title.trim()) return 'Nama materi tidak boleh kosong.';
            for (let i = 0; i < state[g].items.length; i++) {
                const it = state[g].items[i];
                if (!it.title.trim()) return 'Nama sub materi pada "' + state[g].title + '" tidak boleh kosong.';
                const j = parseNum(it.jp);
                if (isNaN(j) || (j !== null && (j < 0 || j > 99))) return 'JP pada "' + it.title + '" harus angka 0 sampai 99.';
                const n = parseNum(it.score);
                if (n !== null && (isNaN(n) || n < 0 || n > 100)) return 'Nilai pada "' + it.title + '" harus antara 0 dan 100.';
            }
        }
        return '';
    }

    function save(advance) {
        if (saving || readOnly) return;
        const problem = validate();
        if (problem) { TP.toast(problem); return; }
        saving = true;
        const buttons = [$('tpSaveDraft'), $('tpSaveNext')];
        buttons.forEach(function (b) { b.classList.add('is-disabled'); });
        TP.data('tp.final.save', { classId: classId, enrollmentId: enrollmentId, structure: payloadStructure() }).then(function (res) {
            origSig = sig();
            const mine = info.students.find(function (s) { return s.enrollmentId === enrollmentId; });
            if (mine) {
                mine.status = res.data.status;
                mine.average = res.data.average;
                mine.predicate = res.data.predicate;
            }
            $('tpBadge').innerHTML = res.data.status === 'lengkap' ? TP.badge('Lengkap', 'teal') : TP.badge('Draf', 'amber');
            renderAside();
            TP.toast(res.message);
            if (advance) {
                window.location.href = info.nextId ?
                    'penilaian-rapor.php?id=' + classId + '&student=' + info.nextId :
                    'penilaian-akhir.php?id=' + classId;
            }
        }).catch(function (err) {
            TP.toast(err.message);
        }).then(function () {
            saving = false;
            buttons.forEach(function (b) { b.classList.remove('is-disabled'); });
        });
    }

    $('tpSaveDraft').addEventListener('click', function () { save(false); });
    $('tpSaveNext').addEventListener('click', function () { save(true); });

    function go(id) {
        const url = 'penilaian-rapor.php?id=' + classId + '&student=' + id;
        if (!dirty()) { window.location.href = url; return; }
        TP.confirm('Perubahan belum disimpan', 'Pindah ke siswa lain akan membuang perubahan pada halaman ini.', 'Pindah tanpa menyimpan').then(function (ok) {
            if (ok) { origSig = sig(); window.location.href = url; }
        });
    }

    $('tpPrev').addEventListener('click', function () { if (info && info.prevId) go(info.prevId); });
    $('tpNext').addEventListener('click', function () { if (info && info.nextId) go(info.nextId); });
    $('tpStudents').addEventListener('click', function (event) {
        const a = event.target.closest('[data-nav]');
        if (!a) return;
        event.preventDefault();
        const id = Number(a.getAttribute('data-nav'));
        if (id !== enrollmentId) go(id);
    });

    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            save(false);
        }
    });

    TP.guardUnsaved(function () { return info && !readOnly && dirty(); });
});