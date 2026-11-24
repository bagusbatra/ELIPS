// Direktori: assets/js/pages/instructor/kurikulum-draf.js
// ============================================================
// ELIPS LMS - editor isi kurikulum per sesi (instructor/kurikulum-draf.php)
// Tentor mengubah tujuan, materi, penugasan, dan durasi. Modul dan judul
// sesi hanya tampil (diatur admin). Sesi terkunci tidak bisa diedit.
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    if (!TP.init()) return;

    const $ = function (id) { return document.getElementById(id); };
    const FORMATS = {
        file_upload: 'Unggah berkas',
        text_submission: 'Jawaban teks',
        in_class_practice: 'Praktik di kelas',
        external_reference: 'Tautan eksternal'
    };
    const KINDS = { teks: 'Teks', dokumen: 'Dokumen', video: 'Video', tautan: 'Tautan' };

    const courseId = TP.id('course');
    let versionId = TP.id('version');
    let wantLesson = TP.id('lesson');

    let payload = null;      // jawaban server
    let lessons = {};        // id -> {meta, orig (signature), work}
    let order = [];          // urutan id
    let currentId = 0;
    let saving = false;

    if (!courseId) {
        $('tpTitle').textContent = 'Kursus tidak dipilih';
        $('tpError').innerHTML = TP.errorBox('Pilih kursus dari halaman Kurikulum.');
        return;
    }

    load();

    function load() {
        TP.data('tp.curriculum.get', { courseId: courseId, versionId: versionId || 0 }).then(function (res) {
            payload = res.data;
            versionId = payload.version.id;
            lessons = {};
            order = [];
            payload.modules.forEach(function (m) {
                m.lessons.forEach(function (l) {
                    lessons[l.id] = { meta: l, orig: sig(l), work: clone(l) };
                    order.push(l.id);
                });
            });
            const pick = wantLesson && lessons[wantLesson] ? wantLesson : firstEditable();
            currentId = pick;
            renderHeader();
            renderTree();
            renderEditor();
            updateSaveBar();
        }).catch(function (err) {
            $('tpTitle').textContent = 'Kurikulum tidak dapat dibuka';
            $('tpTree').innerHTML = '';
            $('tpError').innerHTML = TP.errorBox(err.message);
        });
    }

    function clone(obj) { return JSON.parse(JSON.stringify(obj)); }

    function firstEditable() {
        for (let i = 0; i < order.length; i++) {
            if (!lessons[order[i]].meta.locked) return order[i];
        }
        return order[0] || 0;
    }

    /** Tanda tangan isi untuk mendeteksi perubahan (tanpa id & metadata). */
    function sig(l) {
        return JSON.stringify([
            l.objective || '',
            l.durationJp === null || l.durationJp === '' || l.durationJp === undefined ? null : Number(l.durationJp),
            (l.materials || []).map(function (m) { return [m.title, m.kind, m.content]; }),
            (l.tasks || []).map(function (t) { return [t.title, t.instructions, Number(t.dueDays), t.format, Number(t.maxScore), Number(t.weight)]; })
        ]);
    }

    function isDirty(id) { return sig(lessons[id].work) !== lessons[id].orig; }
    function dirtyIds() { return order.filter(isDirty); }

    // ---------- Kepala & pohon ----------

    function renderHeader() {
        $('tpTitle').textContent = payload.courseTitle;
        const v = payload.version;
        $('tpSub').textContent = 'Versi ' + v.code + ' · berlaku untuk ' + v.classes.map(function (c) { return c.name; }).join(', ');
        const box = $('tpVersionBox');
        if (payload.versions.length > 1) {
            box.hidden = false;
            $('tpVersion').innerHTML = payload.versions.map(function (x) {
                return '<option value="' + x.id + '"' + (x.id === v.id ? ' selected' : '') + '>' + TP.esc(x.code + ' - ' + x.name) + '</option>';
            }).join('');
        }
        if (!v.editable) {
            $('tpError').innerHTML = '<div class="tp-note tp-note--amber" style="margin-bottom:1rem"><i class="bi bi-lock"></i><span>Kurikulum ini dikunci oleh admin. Anda hanya dapat melihatnya.</span></div>';
        }
    }

    $('tpVersion').addEventListener('change', function () {
        if (dirtyIds().length) {
            TP.confirm('Buang perubahan?', 'Ada perubahan yang belum disimpan pada versi ini.', 'Buang dan pindah').then(function (ok) {
                if (!ok) { $('tpVersion').value = String(versionId); return; }
                versionId = Number($('tpVersion').value);
                wantLesson = 0;
                load();
            });
            return;
        }
        versionId = Number($('tpVersion').value);
        wantLesson = 0;
        load();
    });

    function renderTree() {
        let html = '';
        payload.modules.forEach(function (m) {
            html += '<div class="tp-tree-module">' + TP.esc(m.title) + '</div>';
            m.lessons.forEach(function (l) {
                const st = lessons[l.id];
                html += '<button type="button" class="tp-tree-item' + (l.id === currentId ? ' is-active' : '') + (l.locked ? ' is-locked' : '') + '" data-id="' + l.id + '">' +
                    '<span class="tp-tree-no">' + l.number + '</span><span class="tp-tree-name">' + TP.esc(l.title) + '</span>' +
                    (l.locked ? TP.lockIcon('Sesi terkunci') : (isDirty(l.id) ? '<span class="tp-dirty-dot" title="Belum disimpan"></span>' : '')) +
                    '</button>';
            });
        });
        $('tpTree').innerHTML = html || TP.empty('bi-journal', 'Belum ada sesi', 'Admin perlu menambahkan modul dan sesi.');
    }

    $('tpTree').addEventListener('click', function (event) {
        const btn = event.target.closest('[data-id]');
        if (!btn) return;
        currentId = Number(btn.getAttribute('data-id'));
        renderTree();
        renderEditor();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // ---------- Editor ----------

    function field(label, inner, hint) {
        return '<div class="tp-field"><span class="tp-label">' + label + '</span>' + inner + (hint ? '<span class="tp-hint">' + hint + '</span>' : '') + '</div>';
    }

    function materialHtml(m, i, ro) {
        return '<div class="tp-item" data-kind="material" data-i="' + i + '">' +
            '<div class="tp-item-head"><input class="form-input" data-f="title" placeholder="Judul materi" value="' + TP.esc(m.title) + '"' + ro + ' aria-label="Judul materi">' +
            '<select class="form-input" data-f="kind" style="max-width:9rem"' + ro + ' aria-label="Jenis materi">' +
            Object.keys(KINDS).map(function (k) { return '<option value="' + k + '"' + (m.kind === k ? ' selected' : '') + '>' + KINDS[k] + '</option>'; }).join('') + '</select>' +
            (ro ? '' : '<button type="button" class="tp-icon-btn" data-remove="material" data-i="' + i + '" aria-label="Hapus materi"><i class="bi bi-trash"></i></button>') + '</div>' +
            '<input class="form-input" data-f="content" placeholder="Isi singkat, tautan, atau nama berkas" value="' + TP.esc(m.content) + '"' + ro + ' aria-label="Isi materi"></div>';
    }

    function taskHtml(t, i, ro) {
        return '<div class="tp-item" data-kind="task" data-i="' + i + '">' +
            '<div class="tp-item-head"><input class="form-input" data-f="title" placeholder="Judul penugasan" value="' + TP.esc(t.title) + '"' + ro + ' aria-label="Judul penugasan">' +
            (ro ? '' : '<button type="button" class="tp-icon-btn" data-remove="task" data-i="' + i + '" aria-label="Hapus penugasan"><i class="bi bi-trash"></i></button>') + '</div>' +
            '<textarea class="form-input" data-f="instructions" rows="3" placeholder="Instruksi untuk siswa"' + ro + ' aria-label="Instruksi">' + TP.esc(t.instructions) + '</textarea>' +
            '<div class="tp-grid-3">' +
            field('Tenggat', '<input type="number" min="0" max="365" class="form-input" data-f="dueDays" value="' + TP.esc(t.dueDays) + '"' + ro + '>', 'hari setelah sesi') +
            field('Format pengumpulan', '<select class="form-input" data-f="format"' + ro + '>' +
                Object.keys(FORMATS).map(function (k) { return '<option value="' + k + '"' + (t.format === k ? ' selected' : '') + '>' + FORMATS[k] + '</option>'; }).join('') + '</select>') +
            field('Bobot', '<input type="number" min="0" max="100" step="0.5" class="form-input" data-f="weight" value="' + TP.esc(t.weight) + '"' + ro + '>', 'persen') +
            '</div></div>';
    }

    function renderEditor() {
        const st = lessons[currentId];
        if (!st) {
            $('tpEditor').innerHTML = '<div class="tp-card">' + TP.empty('bi-journal', 'Pilih sesi', 'Pilih sesi di sebelah kiri untuk mengisinya.') + '</div>';
            return;
        }
        const l = st.work;
        const m = st.meta;
        const locked = m.locked || !payload.version.editable;
        const ro = locked ? ' disabled' : '';
        const lockNote = m.locked ?
            '<div class="tp-note tp-note--amber"><i class="bi bi-lock"></i><span>Sesi ini sudah dikonfirmasi admin (' + TP.esc(m.lockedBy.join(', ')) + ') dan tidak bisa diubah.</span></div>' : '';

        $('tpEditor').innerHTML = '<div class="tp-card tp-stack tp-rise" id="tpForm">' + lockNote +
            '<div><div class="tp-card-muted">Sesi ' + m.number + '</div></div>' +
            field('Judul sesi', '<input class="form-input tp-input-locked" value="' + TP.esc(m.title) + '" readonly aria-readonly="true">', 'Diatur admin atau operasional') +
            field('Tujuan pembelajaran', '<textarea class="form-input" data-f="objective" rows="3" placeholder="Apa yang dapat siswa lakukan setelah sesi ini?"' + ro + '>' + TP.esc(l.objective) + '</textarea>') +
            '<div><div class="tp-row tp-row--between" style="margin-bottom:.5rem"><span class="tp-label">Materi</span>' +
            (locked ? '' : '<button type="button" class="btn btn-ghost tp-btn-sm" data-add="material"><i class="bi bi-plus-lg"></i> Tambah materi</button>') + '</div>' +
            '<div class="tp-list" id="tpMaterials">' + (l.materials.length ? l.materials.map(function (x, i) { return materialHtml(x, i, ro); }).join('') : '<div class="tp-card-muted">Belum ada materi.</div>') + '</div></div>' +
            '<div><div class="tp-row tp-row--between" style="margin-bottom:.5rem"><span class="tp-label">Penugasan</span>' +
            (locked ? '' : '<button type="button" class="btn btn-ghost tp-btn-sm" data-add="task"><i class="bi bi-plus-lg"></i> Tambah penugasan</button>') + '</div>' +
            '<div class="tp-list" id="tpTasks">' + (l.tasks.length ? l.tasks.map(function (x, i) { return taskHtml(x, i, ro); }).join('') : '<div class="tp-card-muted">Belum ada penugasan.</div>') + '</div></div>' +
            '<div style="max-width:12rem">' + field('Durasi', '<input type="number" min="0" max="99" step="0.5" class="form-input" data-f="durationJp" value="' + (l.durationJp === null ? '' : TP.esc(l.durationJp)) + '"' + ro + '>', 'jam pelajaran (JP)') + '</div>' +
            '</div>';
    }

    // Perubahan isian: perbarui salinan kerja tanpa render ulang.
    $('tpEditor').addEventListener('input', function (event) {
        const el = event.target;
        const f = el.getAttribute('data-f');
        if (!f || !lessons[currentId]) return;
        const work = lessons[currentId].work;
        const item = el.closest('[data-kind]');
        if (item) {
            const list = item.getAttribute('data-kind') === 'task' ? work.tasks : work.materials;
            list[Number(item.getAttribute('data-i'))][f] = el.value;
        } else if (f === 'durationJp') {
            work.durationJp = el.value === '' ? null : Number(el.value);
        } else {
            work[f] = el.value;
        }
        markDirty();
    });

    $('tpEditor').addEventListener('click', function (event) {
        const add = event.target.closest('[data-add]');
        const rem = event.target.closest('[data-remove]');
        const work = lessons[currentId] && lessons[currentId].work;
        if (!work) return;
        if (add) {
            if (add.getAttribute('data-add') === 'material') work.materials.push({ id: 0, title: '', kind: 'teks', content: '' });
            else work.tasks.push({ id: 0, title: '', instructions: '', dueDays: 7, format: 'file_upload', maxScore: 100, weight: 0 });
            renderEditor();
            markDirty();
            const last = document.querySelector(add.getAttribute('data-add') === 'material' ? '#tpMaterials .tp-item:last-child input' : '#tpTasks .tp-item:last-child input');
            if (last) last.focus();
        } else if (rem) {
            const i = Number(rem.getAttribute('data-i'));
            (rem.getAttribute('data-remove') === 'task' ? work.tasks : work.materials).splice(i, 1);
            renderEditor();
            markDirty();
        }
    });

    function markDirty() {
        renderTreeDots();
        updateSaveBar();
    }

    function renderTreeDots() {
        $('tpTree').querySelectorAll('[data-id]').forEach(function (btn) {
            const id = Number(btn.getAttribute('data-id'));
            const meta = lessons[id].meta;
            if (meta.locked) return;
            const dot = btn.querySelector('.tp-dirty-dot');
            if (isDirty(id) && !dot) {
                const span = document.createElement('span');
                span.className = 'tp-dirty-dot';
                span.title = 'Belum disimpan';
                btn.appendChild(span);
            } else if (!isDirty(id) && dot) {
                dot.remove();
            }
        });
    }

    // ---------- Simpan ----------

    function updateSaveBar() {
        const ids = dirtyIds();
        const bar = $('tpSaveBar');
        if (!ids.length) { bar.hidden = true; return; }
        bar.hidden = false;
        const tasksNew = ids.reduce(function (n, id) {
            return n + lessons[id].work.tasks.filter(function (t) { return !t.id; }).length;
        }, 0);
        $('tpSaveTitle').textContent = ids.length + ' sesi diedit' + (tasksNew ? ' · ' + tasksNew + ' penugasan baru' : '');
        const classes = payload.version.classes.map(function (c) { return c.name; }).join(', ');
        $('tpSaveText').textContent = 'Berlaku untuk ' + classes + ' (sesi yang belum terkonfirmasi).';
    }

    function validate(ids) {
        for (let i = 0; i < ids.length; i++) {
            const w = lessons[ids[i]].work;
            const n = lessons[ids[i]].meta.number;
            for (let t = 0; t < w.tasks.length; t++) {
                if (!String(w.tasks[t].title).trim()) return 'Sesi ' + n + ': judul penugasan wajib diisi.';
            }
            if (w.durationJp !== null && (isNaN(Number(w.durationJp)) || Number(w.durationJp) < 0 || Number(w.durationJp) > 99)) {
                return 'Sesi ' + n + ': durasi harus 0 sampai 99 JP.';
            }
        }
        return '';
    }

    function save() {
        if (saving) return;
        const ids = dirtyIds();
        if (!ids.length) return;
        const problem = validate(ids);
        if (problem) { TP.toast(problem); return; }
        saving = true;
        const btn = $('tpSave');
        btn.classList.add('is-disabled');
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Menyimpan...';
        TP.data('tp.curriculum.save', {
            courseId: courseId,
            versionId: versionId,
            lessons: ids.map(function (id) {
                const w = lessons[id].work;
                return {
                    id: id, objective: w.objective, durationJp: w.durationJp,
                    materials: w.materials, tasks: w.tasks.map(function (t) {
                        return { id: t.id, title: t.title, instructions: t.instructions, dueDays: Number(t.dueDays), format: t.format, maxScore: Number(t.maxScore), weight: Number(t.weight) };
                    })
                };
            })
        }).then(function (res) {
            res.data.lessons.forEach(function (fresh) {
                const st = lessons[fresh.id];
                Object.assign(st.meta, fresh);
                st.work = clone(st.meta);
                st.orig = sig(st.meta);
            });
            TP.toast(res.message || 'Perubahan disimpan.');
            renderTree();
            renderEditor();
            updateSaveBar();
        }).catch(function (err) {
            TP.toast(err.message);
        }).then(function () {
            saving = false;
            btn.classList.remove('is-disabled');
            btn.innerHTML = '<i class="bi bi-check2"></i> Simpan perubahan';
        });
    }

    $('tpSave').addEventListener('click', save);

    $('tpDiscard').addEventListener('click', function () {
        TP.confirm('Batalkan perubahan?', 'Semua isian yang belum disimpan akan dikembalikan.', 'Batalkan perubahan').then(function (ok) {
            if (!ok) return;
            order.forEach(function (id) { lessons[id].work = clone(lessons[id].meta); });
            renderTree();
            renderEditor();
            updateSaveBar();
        });
    });

    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            save();
        }
    });

    TP.guardUnsaved(function () { return payload && dirtyIds().length > 0; });
});