// Direktori: assets/js/pages/admin/course-publish.js
// ============================================================
// ELIPS LMS - Publikasi Kursus (admin & Operasional)
// Admin menyusun struktur (modul + judul materi), tentor mengisi isinya,
// lalu admin mempublikasikan kursus dan membuat angkatan baru.
// Server tetap memeriksa semua aturan; JS hanya tampilan.
// ============================================================
(function (window) {
    'use strict';

    var ELIPS = window.ELIPS = window.ELIPS || {};
    var DAYS = [[1, 'Sen'], [2, 'Sel'], [3, 'Rab'], [4, 'Kam'], [5, 'Jum'], [6, 'Sab'], [7, 'Min']];
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    var courseId = 0;
    var server = null;     // data terakhir dari server
    var draft = [];        // pohon kerja: [{id, title, lessons:[{id,title,filled,locked,lockedBy}]}]
    var dirty = false;
    var saving = false;
    var selectedDays = {};
    var focusNext = null;  // {m, l} -> fokus setelah render

    function $(id) { return document.getElementById(id); }
    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function toast(msg) { if (ELIPS.toast) ELIPS.toast(msg); }
    function clone(v) { return JSON.parse(JSON.stringify(v)); }

    function fmtDate(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''));
        return m ? Number(m[3]) + ' ' + MONTHS[Number(m[2]) - 1] + ' ' + m[1] : '-';
    }

    // ---------- API ----------

    function call(action, payload) {
        var api = ELIPS.api || {};
        if (!api.enabled || !api.base) {
            return Promise.reject(new Error('Server tidak tersedia. Buka aplikasi lewat server web.'));
        }
        return fetch(api.base, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, payload: payload || {} })
        }).then(function (r) { return r.text(); }).then(function (text) {
            var body;
            try { body = JSON.parse(text); } catch (e) { throw new Error('Respons server tidak valid.'); }
            if (!body.ok) throw new Error(body.message || 'Permintaan gagal.');
            return body;
        }).catch(function (err) {
            if (err instanceof TypeError) throw new Error('Tidak dapat menghubungi server.');
            throw err;
        });
    }

    // ---------- Konfirmasi ----------

    function confirmBox(title, text, okLabel) {
        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.className = 'cpb-modal';
            overlay.setAttribute('role', 'dialog');
            overlay.innerHTML = '<div class="cpb-modal-box" style="max-width:440px"><h3>' + esc(title) + '</h3>' +
                '<p class="cpb-muted" style="margin-bottom:0">' + esc(text) + '</p>' +
                '<div class="cpb-modal-actions"><button type="button" class="btn btn-ghost" data-act="no">Batal</button>' +
                '<button type="button" class="btn btn-primary" data-act="yes">' + esc(okLabel || 'Lanjutkan') + '</button></div></div>';
            function close(v) { document.removeEventListener('keydown', onKey); overlay.remove(); resolve(v); }
            function onKey(e) { if (e.key === 'Escape') close(false); }
            overlay.addEventListener('click', function (e) {
                var act = e.target.getAttribute && e.target.getAttribute('data-act');
                if (act === 'yes') close(true);
                else if (act === 'no' || e.target === overlay) close(false);
            });
            document.addEventListener('keydown', onKey);
            document.body.appendChild(overlay);
            overlay.querySelector('[data-act="yes"]').focus();
        });
    }

    // ---------- Pemuatan ----------

    function load() {
        return call('cp.get', { courseId: courseId }).then(function (body) {
            applyServer(body.data);
        });
    }

    function applyServer(data) {
        server = data;
        draft = clone(data.modules);
        dirty = false;
        renderAll();
    }

    // ---------- Render ----------

    function renderAll() {
        renderHead();
        renderSteps();
        renderTree();
        renderAngkatan();
        renderSaveBar();
    }

    function renderHead() {
        var c = server.course;
        var badge = c.status === 'published' ? '<span class="cpb-badge cpb-badge--green">Dipublikasi</span>'
            : c.status === 'archived' ? '<span class="cpb-badge cpb-badge--gray">Arsip</span>'
            : '<span class="cpb-badge cpb-badge--amber">Draft</span>';
        $('cpbTitle').innerHTML = esc(c.title) + ' ' + badge;
        $('cpbSub').textContent = c.instructorName
            ? 'Tentor kursus: ' + c.instructorName + ' · Admin menyusun struktur, tentor mengisi isi tiap materi.'
            : 'Susun modul dan judul materi. Isi tiap materi diisi oleh tentor.';
        document.title = 'Publikasi ' + c.title + ' - ELIPS LMS';

        var actions = $('cpbHeadActions');
        if (c.status === 'draft') {
            actions.innerHTML = '<button type="button" class="btn btn-primary" id="cpbPublish"><i class="bi bi-send-check"></i> Publikasikan kursus</button>' +
                '<p class="cpb-action-hint" id="cpbPublishHint" hidden></p>';
            $('cpbPublish').addEventListener('click', publish);
            refreshPublishBtn();
        } else if (c.status === 'published') {
            actions.innerHTML = '<button type="button" class="btn btn-ghost cpb-btn-outline" id="cpbUnpublish"><i class="bi bi-eye-slash"></i> Kembalikan ke Draft</button>';
            $('cpbUnpublish').addEventListener('click', unpublish);
        } else {
            actions.innerHTML = '';
        }
    }

    // Tombol publikasi aktif hanya bila syarat terpenuhi dan struktur sudah tersimpan.
    function publishBlockReason() {
        var t = totals();
        if (t.modules === 0 || t.lessons === 0) return 'Tambahkan minimal satu modul dan satu materi.';
        if (dirty) return 'Simpan struktur terlebih dahulu.';
        var missing = server.checklist.filter(function (i) { return !i.ok; });
        if (missing.length) return 'Lengkapi: ' + missing.map(function (i) { return i.label.toLowerCase(); }).join(', ') + '.';
        return '';
    }

    function refreshPublishBtn() {
        var btn = $('cpbPublish');
        if (!btn) return;
        var reason = publishBlockReason();
        var hint = $('cpbPublishHint');
        btn.disabled = reason !== '';
        btn.title = reason;
        if (hint) {
            hint.hidden = reason === '';
            hint.textContent = reason;
        }
    }

    function renderSteps() {
        var t = server.totals;
        var html = server.checklist.map(function (item) {
            var ok = item.ok;
            return '<div class="cpb-step ' + (ok ? 'is-ok' : 'is-warn') + '">' +
                '<span class="cpb-step-ico"><i class="bi ' + (ok ? 'bi-check-lg' : 'bi-exclamation-lg') + '"></i></span>' +
                '<div><strong>' + esc(item.label) + '</strong><span>' + esc(ok ? 'Siap.' : item.hint) + '</span></div></div>';
        }).join('');

        var pct = t.lessons > 0 ? Math.round(t.filled / t.lessons * 100) : 0;
        html += '<div class="cpb-step ' + (t.lessons > 0 && t.filled === t.lessons ? 'is-ok' : '') + '">' +
            '<span class="cpb-step-ico"><i class="bi bi-person-workspace"></i></span>' +
            '<div style="flex:1"><strong>Isi materi oleh tentor</strong><span>' + t.filled + ' dari ' + t.lessons + ' materi terisi</span>' +
            '<div class="cpb-bar"><i style="width:' + pct + '%"></i></div></div></div>';
        $('cpbSteps').innerHTML = html;
    }

    function totals() {
        var l = 0;
        draft.forEach(function (m) { l += m.lessons.length; });
        return { modules: draft.length, lessons: l };
    }

    function updateCount() {
        var t = totals();
        $('cpbCount').textContent = t.modules + ' modul · ' + t.lessons + ' materi (sesi)';
    }

    function renderTree() {
        var tree = $('cpbTree');
        updateCount();
        $('cpbCopyBtn').hidden = !(draft.length === 0 && server.copySources.length > 0);

        if (!draft.length) {
            tree.innerHTML = '<div class="cpb-empty"><i class="bi bi-diagram-3"></i><strong>Struktur masih kosong</strong>' +
                '<span>Klik "Modul" untuk mulai, tempel daftar sekaligus, atau salin dari kursus lain.</span></div>';
            return;
        }

        var number = 0;
        var html = '';
        draft.forEach(function (m, mi) {
            html += '<div class="cpb-module" data-m="' + mi + '">' +
                '<div class="cpb-module-head">' +
                '<span class="cpb-module-no">Modul ' + (mi + 1) + '</span>' +
                '<input class="form-input" data-f="mtitle" maxlength="200" placeholder="Judul modul" value="' + esc(m.title) + '" aria-label="Judul modul ' + (mi + 1) + '">' +
                '<button type="button" class="cpb-icon-btn" data-act="m-up" title="Naikkan"' + (mi === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up"></i></button>' +
                '<button type="button" class="cpb-icon-btn" data-act="m-down" title="Turunkan"' + (mi === draft.length - 1 ? ' disabled' : '') + '><i class="bi bi-arrow-down"></i></button>' +
                '<button type="button" class="cpb-icon-btn danger" data-act="m-del" title="Hapus modul"' + (m.lessons.some(function (l) { return l.locked; }) ? ' disabled' : '') + '><i class="bi bi-trash"></i></button>' +
                '</div><div class="cpb-lessons">';

            m.lessons.forEach(function (l, li) {
                number++;
                var prev = m.lessons[li - 1];
                var next = m.lessons[li + 1];
                var fill;
                if (l.locked) {
                    fill = '<span class="cpb-fill cpb-fill--lock" title="Dikonfirmasi di ' + esc((l.lockedBy || []).join(', ')) + '"><i class="bi bi-lock"></i> Terkunci</span>';
                } else if (l.id && l.filled) {
                    fill = '<span class="cpb-fill cpb-fill--ok"><i class="bi bi-check2"></i> Terisi tentor</span>';
                } else {
                    fill = '<span class="cpb-fill cpb-fill--empty">Menunggu tentor</span>';
                }
                html += '<div class="cpb-lesson' + (l.locked ? ' is-locked' : '') + '" data-l="' + li + '">' +
                    '<span class="cpb-lesson-no">' + number + '</span>' +
                    '<input class="form-input" data-f="ltitle" maxlength="200" placeholder="Judul materi / sesi"' + (l.locked ? ' disabled' : '') +
                    ' value="' + esc(l.title) + '" aria-label="Judul materi ' + number + '">' + fill +
                    '<button type="button" class="cpb-icon-btn" data-act="l-up" title="Naikkan"' + (li === 0 || l.locked || (prev && prev.locked) ? ' disabled' : '') + '><i class="bi bi-arrow-up"></i></button>' +
                    '<button type="button" class="cpb-icon-btn" data-act="l-down" title="Turunkan"' + (li === m.lessons.length - 1 || l.locked || (next && next.locked) ? ' disabled' : '') + '><i class="bi bi-arrow-down"></i></button>' +
                    '<button type="button" class="cpb-icon-btn danger" data-act="l-del" title="Hapus materi"' + (l.locked ? ' disabled' : '') + '><i class="bi bi-x-lg"></i></button>' +
                    '</div>';
            });

            html += '<button type="button" class="cpb-add-lesson" data-act="l-add"><i class="bi bi-plus"></i> Tambah materi</button></div></div>';
        });
        tree.innerHTML = html;

        if (focusNext) {
            var sel = focusNext.l == null
                ? '.cpb-module[data-m="' + focusNext.m + '"] [data-f="mtitle"]'
                : '.cpb-module[data-m="' + focusNext.m + '"] .cpb-lesson[data-l="' + focusNext.l + '"] [data-f="ltitle"]';
            var el = tree.querySelector(sel);
            if (el) { el.focus(); if (el.scrollIntoView) el.scrollIntoView({ block: 'nearest' }); }
            focusNext = null;
        }
    }

    function renderAngkatan() {
        var c = server.course;
        var btn = $('cpbNewAngkatan');
        var note = $('cpbAngkatanNote');
        var ready = c.status === 'published';
        btn.disabled = !ready;
        btn.title = ready ? '' : 'Publikasikan kursus terlebih dahulu.';
        btn.innerHTML = '<i class="bi ' + (ready ? 'bi-plus-circle' : 'bi-lock') + '"></i> Buat angkatan baru';
        note.hidden = ready;
        note.innerHTML = '<i class="bi bi-info-circle"></i><span>' + esc(c.status === 'archived'
            ? 'Kursus diarsipkan. Aktifkan kembali di Kelola Kursus.'
            : 'Publikasikan kursus lebih dulu, setelah itu angkatan bisa dibuat.') + '</span>';

        var list = $('cpbAngkatan');
        if (!server.angkatan.length) {
            list.innerHTML = '<div class="cpb-ang-empty"><i class="bi bi-people"></i><span>Belum ada angkatan.</span></div>';
            return;
        }
        var label = { draft: 'Pendaftaran', open: 'Pendaftaran', ready: 'Pendaftaran', ongoing: 'Berlangsung', completed: 'Selesai', cancelled: 'Arsip', archived: 'Arsip' };
        list.innerHTML = server.angkatan.map(function (a) {
            var tone = a.status === 'ongoing' ? 'green' : (a.status === 'open' || a.status === 'draft' || a.status === 'ready') ? 'amber' : 'gray';
            var sessionText = a.sessions + ' sesi';
            if (a.missingSessions > 0 && a.sessions > 0) sessionText += ' (kurang ' + a.missingSessions + ')';
            if (a.sessions === 0) sessionText = 'jadwal belum dibuat';
            return '<div class="cpb-ang"><div class="cpb-ang-top"><strong>' + esc(a.name) + '</strong>' +
                '<span class="cpb-badge cpb-badge--' + tone + '">' + esc(label[a.status] || a.status) + '</span></div>' + (a.overdue ? '<div class="cpb-muted" style="color:#b91c1c;font-weight:600">Lewat perkiraan, belum ditutup</div>' : '') +
                '<div class="cpb-muted">' + esc(a.instructorName || 'Tentor belum dipilih') + ' · ' + a.students + '/' + a.quota + ' peserta</div>' +
                '<div class="cpb-muted">' + 'Mulai ' + fmtDate(a.periodStart) + (a.periodEnd ? ' · ' + (a.status === 'completed' ? 'ditutup ' + fmtDate(a.completedAt || a.periodEnd) : 'perkiraan selesai ' + fmtDate(a.periodEnd)) : '') + ' · ' + esc(sessionText) + '</div></div>';
        }).join('');
    }

    function renderSaveBar() {
        var bar = $('cpbSaveBar');
        bar.hidden = !dirty;
        if (dirty) {
            var t = totals();
            $('cpbSaveText').textContent = t.modules + ' modul · ' + t.lessons + ' materi';
        }
    }

    function markDirty() {
        dirty = true;
        renderSaveBar();
        updateCount();
        refreshPublishBtn();
    }

    // ---------- Edit struktur ----------

    function move(arr, from, to) {
        if (to < 0 || to >= arr.length) return;
        var item = arr.splice(from, 1)[0];
        arr.splice(to, 0, item);
    }

    function onTreeInput(e) {
        var f = e.target.getAttribute('data-f');
        if (!f) return;
        var mEl = e.target.closest('.cpb-module');
        var mi = Number(mEl.getAttribute('data-m'));
        if (f === 'mtitle') {
            draft[mi].title = e.target.value;
        } else if (f === 'ltitle') {
            var li = Number(e.target.closest('.cpb-lesson').getAttribute('data-l'));
            draft[mi].lessons[li].title = e.target.value;
        }
        markDirty();
    }

    function onTreeKey(e) {
        if (e.key !== 'Enter' || e.target.getAttribute('data-f') !== 'ltitle') return;
        e.preventDefault();
        var mi = Number(e.target.closest('.cpb-module').getAttribute('data-m'));
        var li = Number(e.target.closest('.cpb-lesson').getAttribute('data-l'));
        if (!e.target.value.trim()) return;
        draft[mi].lessons.splice(li + 1, 0, { id: 0, title: '', filled: false, locked: false, lockedBy: [] });
        focusNext = { m: mi, l: li + 1 };
        markDirty();
        renderTree();
    }

    function onTreeClick(e) {
        var btn = e.target.closest('[data-act]');
        if (!btn || btn.disabled) return;
        var mEl = btn.closest('.cpb-module');
        var mi = Number(mEl.getAttribute('data-m'));
        var lEl = btn.closest('.cpb-lesson');
        var li = lEl ? Number(lEl.getAttribute('data-l')) : -1;

        switch (btn.getAttribute('data-act')) {
            case 'm-up': move(draft, mi, mi - 1); break;
            case 'm-down': move(draft, mi, mi + 1); break;
            case 'm-del':
                var m = draft[mi];
                var hasContent = m.lessons.some(function (l) { return l.id && l.filled; });
                if (hasContent || m.lessons.length > 2) {
                    confirmBox('Hapus modul?', 'Modul "' + (m.title || 'tanpa judul') + '" beserta ' + m.lessons.length +
                        ' materinya akan dihapus' + (hasContent ? ', termasuk isi yang sudah diisi tentor' : '') + ' saat Anda menyimpan.', 'Hapus').then(function (ok) {
                        if (!ok) return;
                        draft.splice(mi, 1);
                        markDirty(); renderTree();
                    });
                    return;
                }
                draft.splice(mi, 1);
                break;
            case 'l-up': move(draft[mi].lessons, li, li - 1); break;
            case 'l-down': move(draft[mi].lessons, li, li + 1); break;
            case 'l-del':
                var lesson = draft[mi].lessons[li];
                if (lesson.id && lesson.filled) {
                    confirmBox('Hapus materi?', 'Materi "' + lesson.title + '" sudah diisi tentor. Isinya ikut terhapus saat Anda menyimpan.', 'Hapus').then(function (ok) {
                        if (!ok) return;
                        draft[mi].lessons.splice(li, 1);
                        markDirty(); renderTree();
                    });
                    return;
                }
                draft[mi].lessons.splice(li, 1);
                break;
            case 'l-add':
                draft[mi].lessons.push({ id: 0, title: '', filled: false, locked: false, lockedBy: [] });
                focusNext = { m: mi, l: draft[mi].lessons.length - 1 };
                break;
            default: return;
        }
        markDirty();
        renderTree();
    }

    function addModule() {
        draft.push({ id: 0, title: '', lessons: [{ id: 0, title: '', filled: false, locked: false, lockedBy: [] }] });
        focusNext = { m: draft.length - 1, l: null };
        markDirty();
        renderTree();
    }

    function save() {
        if (saving) return Promise.resolve(false);
        saving = true;
        var btn = $('cpbSave');
        btn.disabled = true;
        var payload = draft.map(function (m) {
            return { id: m.id || 0, title: m.title, lessons: m.lessons.map(function (l) { return { id: l.id || 0, title: l.title }; }) };
        });
        return call('cp.structure.save', { courseId: courseId, modules: payload }).then(function (body) {
            applyServer(body.data);
            toast(body.message);
            return true;
        }).catch(function (err) {
            toast(err.message);
            return false;
        }).then(function (ok) {
            saving = false;
            btn.disabled = false;
            return ok;
        });
    }

    // ---------- Tempel daftar ----------

    /** Teks -> [{title, lessons:[title]}]. */
    function parsePaste(text) {
        var modules = [];
        var current = null;
        String(text || '').split(/\r?\n/).forEach(function (raw) {
            var line = raw.replace(/\t/g, ' ').trim();
            if (!line) return;
            var mod = /^(#{1,6}\s*|modul\s*\d*\s*[:.\-–]\s*|modul\s+\d+\s+)(.+)$/i.exec(line);
            if (mod) {
                current = { title: mod[2].trim(), lessons: [] };
                modules.push(current);
                return;
            }
            var title = line.replace(/^([-*•]\s+|\d+\s*[.)]\s+)/, '').trim();
            if (!title) return;
            if (!current) {
                current = { title: 'Modul 1', lessons: [] };
                modules.push(current);
            }
            current.lessons.push(title);
        });
        return modules;
    }

    function pastePreview() {
        var parsed = parsePaste($('cpbPasteText').value);
        var n = 0;
        parsed.forEach(function (m) { n += m.lessons.length; });
        $('cpbPastePreview').textContent = parsed.length
            ? 'Terbaca: ' + parsed.length + ' modul, ' + n + ' materi.'
            : 'Belum ada baris yang terbaca.';
        return parsed;
    }

    function applyPaste() {
        var parsed = pastePreview();
        if (!parsed.length) { toast('Tidak ada baris yang bisa ditambahkan.'); return; }
        parsed.forEach(function (m) {
            draft.push({
                id: 0, title: m.title,
                lessons: m.lessons.map(function (t) { return { id: 0, title: t, filled: false, locked: false, lockedBy: [] }; })
            });
        });
        closeModal('cpbPasteModal');
        $('cpbPasteText').value = '';
        markDirty();
        renderTree();
        toast('Daftar ditambahkan. Periksa lalu klik Simpan struktur.');
    }

    // ---------- Salin struktur ----------

    function openCopy() {
        var sel = $('cpbCopySelect');
        sel.innerHTML = server.copySources.map(function (c) {
            return '<option value="' + c.id + '">' + esc(c.title) + ' — ' + c.modules + ' modul, ' + c.lessons + ' materi</option>';
        }).join('');
        openModal('cpbCopyModal');
    }

    function applyCopy() {
        var from = Number($('cpbCopySelect').value);
        if (!from) return;
        var btn = $('cpbCopyApply');
        btn.disabled = true;
        call('cp.structure.copy', { courseId: courseId, fromCourseId: from }).then(function (body) {
            closeModal('cpbCopyModal');
            applyServer(body.data);
            toast(body.message);
        }).catch(function (err) { toast(err.message); }).then(function () { btn.disabled = false; });
    }

    // ---------- Publikasi ----------

    function setStatus(status) {
        return call('course.status', { id: courseId, status: status }).then(function (body) {
            toast(body.message);
            return load();
        });
    }

    function publish() {
        if (dirty) {
            toast('Simpan struktur dulu, lalu publikasikan.');
            return;
        }
        if (!server.canPublish) {
            var missing = server.checklist.filter(function (i) { return !i.ok; }).map(function (i) { return i.label; });
            toast('Belum siap: ' + missing.join(', ') + '.');
            return;
        }
        var empty = server.totals.lessons - server.totals.filled;
        var text = 'Kursus akan tampil di pendaftaran publik.' +
            (empty > 0 ? ' ' + empty + ' materi belum diisi tentor; tentor tetap bisa melengkapinya setelah publikasi.' : '');
        confirmBox('Publikasikan "' + server.course.title + '"?', text, 'Publikasikan').then(function (ok) {
            if (ok) setStatus('published').catch(function (err) { toast(err.message); });
        });
    }

    function unpublish() {
        confirmBox('Kembalikan ke Draft?', 'Kursus tidak tampil lagi di pendaftaran publik. Angkatan dan data peserta tetap aman.', 'Kembalikan').then(function (ok) {
            if (ok) setStatus('draft').catch(function (err) { toast(err.message); });
        });
    }

    // ---------- Angkatan baru ----------

    function openModal(id) { $(id).hidden = false; }
    function closeModal(id) { $(id).hidden = true; }

    function dayChips() {
        $('angDays').innerHTML = DAYS.map(function (d) {
            return '<button type="button" class="cpb-day' + (selectedDays[d[0]] ? ' is-on' : '') + '" data-day="' + d[0] + '">' + d[1] + '</button>';
        }).join('');
    }

    function nextMonday() {
        var d = new Date();
        d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7));
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function openAngkatan() {
        var s = server.suggest;
        var d = s.defaults;
        $('cpbAngError').hidden = true;
        $('angName').value = s.name;
        $('angStart').value = nextMonday();
        $('angQuota').value = d.quota;
        $('angFrom').value = d.startTime;
        $('angTo').value = d.endTime;
        $('angJp').value = d.jp;
        $('angLocation').value = d.location;
        $('angRoom').value = d.room;
        $('angTentor').innerHTML = server.tentors.map(function (t) {
            return '<option value="' + t.id + '"' + (t.id === server.course.instructorId ? ' selected' : '') + '>' + esc(t.name) + '</option>';
        }).join('');
        selectedDays = {};
        (d.weekdays.length ? d.weekdays : [1, 3]).forEach(function (n) { selectedDays[n] = true; });
        dayChips();
        angPreview();
        openModal('cpbAngModal');
        $('angName').focus();
    }

    /** Tanggal sesi menurut hari terpilih, mulai dari tanggal mulai. */
    function previewDates(count) {
        var start = $('angStart').value;
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(start);
        if (!m) return [];
        var days = Object.keys(selectedDays).filter(function (k) { return selectedDays[k]; }).map(Number);
        if (!days.length) return [];
        var cur = new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])));
        var out = [];
        var guard = 0;
        while (out.length < count && guard < 1500) {
            var iso = cur.getUTCDay() === 0 ? 7 : cur.getUTCDay();
            if (days.indexOf(iso) !== -1) out.push(cur.toISOString().slice(0, 10));
            cur.setUTCDate(cur.getUTCDate() + 1);
            guard++;
        }
        return out;
    }

    function angPreview() {
        var n = server.totals.lessons;
        var dates = previewDates(n);
        var box = $('angPreview');
        if (!n) { box.textContent = 'Kurikulum belum memiliki materi.'; return; }
        if (!dates.length) { box.textContent = 'Pilih tanggal mulai dan minimal satu hari belajar untuk melihat jadwal.'; return; }
        box.innerHTML = '<strong>' + n + ' sesi</strong> dijadwalkan otomatis, dari ' + fmtDate(dates[0]) + ' sampai ' +
            fmtDate(dates[dates.length - 1]) + '. Sesi ke-N mengikuti materi ke-N, tanggal bisa diubah nanti di menu Jadwal.';
    }

    function submitAngkatan() {
        var err = $('cpbAngError');
        err.hidden = true;
        var days = Object.keys(selectedDays).filter(function (k) { return selectedDays[k]; }).map(Number);
        var btn = $('angSubmit');
        btn.disabled = true;
        call('cp.angkatan.create', {
            courseId: courseId,
            name: $('angName').value,
            periodStart: $('angStart').value,
            quota: Number($('angQuota').value),
            instructorId: Number($('angTentor').value),
            weekdays: days,
            startTime: $('angFrom').value,
            endTime: $('angTo').value,
            jp: Number($('angJp').value) || 0,
            location: $('angLocation').value,
            room: $('angRoom').value,
            generateSessions: true
        }).then(function (body) {
            closeModal('cpbAngModal');
            applyServer(body.data);
            toast(body.message);
        }).catch(function (e) {
            err.textContent = e.message;
            err.hidden = false;
        }).then(function () { btn.disabled = false; });
    }

    // ---------- Guard & inisialisasi ----------

    function guard() {
        var user = ELIPS.employee && ELIPS.employee.guardEmployee
            ? ELIPS.employee.guardEmployee(['operasional'])
            : ELIPS.requireRole('admin');
        if (user && user.role === 'karyawan' && ELIPS.employee.initEmployeeShell) {
            ELIPS.employee.initEmployeeShell(user, { toggleId: 'opsSidebarToggle', backdropId: 'opsSidebarBackdrop' });
        }
        return user;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!guard()) return;
        courseId = parseInt(new URLSearchParams(window.location.search).get('id'), 10) || 0;
        if (!courseId) {
            $('cpbTitle').textContent = 'Kursus tidak ditemukan';
            $('cpbError').innerHTML = '<p class="cpb-note">Buka halaman ini dari tombol Publikasi di Kelola Kursus.</p>';
            return;
        }

        var tree = $('cpbTree');
        tree.addEventListener('input', onTreeInput);
        tree.addEventListener('keydown', onTreeKey);
        tree.addEventListener('click', onTreeClick);
        $('cpbAddModule').addEventListener('click', addModule);
        $('cpbSave').addEventListener('click', save);
        $('cpbDiscard').addEventListener('click', function () {
            draft = clone(server.modules);
            dirty = false;
            renderTree();
            renderSaveBar();
            refreshPublishBtn();
        });
        $('cpbPasteBtn').addEventListener('click', function () { openModal('cpbPasteModal'); $('cpbPasteText').focus(); });
        $('cpbPasteText').addEventListener('input', pastePreview);
        $('cpbPasteApply').addEventListener('click', applyPaste);
        $('cpbCopyBtn').addEventListener('click', openCopy);
        $('cpbCopyApply').addEventListener('click', applyCopy);
        $('cpbNewAngkatan').addEventListener('click', openAngkatan);
        $('angSubmit').addEventListener('click', submitAngkatan);
        $('angDays').addEventListener('click', function (e) {
            var b = e.target.closest('[data-day]');
            if (!b) return;
            var n = Number(b.getAttribute('data-day'));
            selectedDays[n] = !selectedDays[n];
            dayChips();
            angPreview();
        });
        $('angStart').addEventListener('change', angPreview);

        document.querySelectorAll('.cpb-modal [data-close]').forEach(function (b) {
            b.addEventListener('click', function () { b.closest('.cpb-modal').hidden = true; });
        });
        document.querySelectorAll('.cpb-modal').forEach(function (m) {
            m.addEventListener('click', function (e) { if (e.target === m) m.hidden = true; });
        });

        window.addEventListener('beforeunload', function (e) {
            if (dirty) { e.preventDefault(); e.returnValue = ''; }
        });

        load().then(function () {
            if (new URLSearchParams(window.location.search).get('new') === '1' && server.course.status === 'published') {
                openAngkatan();
            }
        }).catch(function (err) {
            $('cpbTitle').textContent = 'Gagal memuat';
            $('cpbError').innerHTML = '<p class="cpb-note">' + esc(err.message) + '</p>';
        });
    });
})(window);