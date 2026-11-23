// ============================================================
// ELIPS LMS - letter-archive.js
// Surat menyurat untuk halaman Admin: pencatatan surat masuk dan
// keluar, filter, detail lengkap, serta tambah/ubah/hapus.
// ============================================================

(function (ELIPS) {
    'use strict';

    const TIMEZONE = 'Asia/Jakarta';
    const DIRECTION_LABELS = { masuk: 'Surat Masuk', keluar: 'Surat Keluar' };
    const CLASSIFICATION_LABELS = {
        biasa: 'Biasa',
        penting: 'Penting',
        segera: 'Segera',
        rahasia: 'Rahasia'
    };

    const state = {
        rows: [],
        editingId: 0,
        editingRow: null,
        detailId: 0,
        loading: false
    };

    function $(id) {
        return document.getElementById(id);
    }

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function todayDate() {
        const now = new Date();
        try {
            return new Intl.DateTimeFormat('en-CA', {
                timeZone: TIMEZONE,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }).format(now);
        } catch (err) {
            return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
        }
    }

    function isIsoDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))) return false;
        const parts = String(value).split('-');
        const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return date.getFullYear() === Number(parts[0])
            && date.getMonth() === Number(parts[1]) - 1
            && date.getDate() === Number(parts[2]);
    }

    function formatDateOnly(iso) {
        if (!iso) return '-';
        const parts = String(iso).split('-');
        if (parts.length !== 3) return iso;
        const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (isNaN(date.getTime())) return iso;
        return date.toLocaleDateString('id-ID', {
            weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'
        });
    }

    function setText(id, value) {
        const node = $(id);
        if (node) node.textContent = String(value);
    }

    function directionLabel(direction) {
        return DIRECTION_LABELS[direction] || direction || '-';
    }

    function classificationLabel(classification) {
        return CLASSIFICATION_LABELS[classification] || classification || '-';
    }

    function directionBadge(direction) {
        if (direction === 'keluar') {
            return '<span class="badge letter-badge-keluar">Keluar</span>';
        }
        return '<span class="badge letter-badge-masuk">Masuk</span>';
    }

    function classificationBadge(classification) {
        if (classification === 'rahasia') {
            return '<span class="badge letter-badge-rahasia">Rahasia</span>';
        }
        if (classification === 'segera') {
            return '<span class="badge badge-warning">Segera</span>';
        }
        if (classification === 'penting') {
            return '<span class="badge badge-primary">Penting</span>';
        }
        return '<span class="badge letter-badge-biasa">Biasa</span>';
    }

    function readFilters() {
        return {
            direction: $('letterDirection') ? $('letterDirection').value : '',
            search: $('letterSearch') ? $('letterSearch').value.trim() : '',
            from: $('letterFrom') ? $('letterFrom').value : '',
            to: $('letterTo') ? $('letterTo').value : ''
        };
    }

    function localRows(filters) {
        const list = typeof ELIPS.getLetters === 'function' ? ELIPS.getLetters() : [];
        const search = String(filters.search || '').toLowerCase();

        return list.filter(function (row) {
            if (!row || !row.letterDate) return false;
            if (filters.direction && row.direction !== filters.direction) return false;
            if (filters.from && row.letterDate < filters.from) return false;
            if (filters.to && row.letterDate > filters.to) return false;
            if (search) {
                const haystack = [
                    row.letterNumber,
                    row.subject,
                    row.sender,
                    row.recipient,
                    row.category
                ].join(' ').toLowerCase();
                if (haystack.indexOf(search) === -1) return false;
            }
            return true;
        }).sort(function (a, b) {
            if (a.letterDate !== b.letterDate) return a.letterDate < b.letterDate ? 1 : -1;
            return Number(b.id) - Number(a.id);
        });
    }

    function renderSummary(rows) {
        let masuk = 0;
        let keluar = 0;
        rows.forEach(function (row) {
            if (row.direction === 'keluar') keluar++;
            else masuk++;
        });
        setText('letterStatTotal', rows.length);
        setText('letterStatMasuk', masuk);
        setText('letterStatKeluar', keluar);
    }

    function renderTable(rows) {
        const body = $('letterTableBody');
        if (!body) return;
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="6"><div class="attendance-empty">'
                + 'Belum ada surat yang cocok dengan filter ini.</div></td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (row) {
            return '<tr>'
                + '<td>' + ELIPS.escapeHtml(formatDateOnly(row.letterDate)) + '</td>'
                + '<td><div class="table-course-info">'
                + '<div class="table-course-title">' + ELIPS.escapeHtml(row.letterNumber || '-') + '</div>'
                + '<div class="table-course-meta">' + ELIPS.escapeHtml(row.subject || '-') + '</div>'
                + '</div></td>'
                + '<td>' + directionBadge(row.direction) + '</td>'
                + '<td><div class="letter-route">'
                + '<span>' + ELIPS.escapeHtml(row.sender || '-') + '</span>'
                + '<i class="bi bi-arrow-right"></i>'
                + '<span>' + ELIPS.escapeHtml(row.recipient || '-') + '</span>'
                + '</div></td>'
                + '<td>' + classificationBadge(row.classification) + '</td>'
                + '<td><div class="shift-actions">'
                + '<button class="btn btn-ghost shift-action-btn" type="button" title="Lihat detail"'
                + ' data-letter-detail="' + Number(row.id) + '"><i class="bi bi-eye"></i></button>'
                + '<button class="btn btn-ghost shift-action-btn" type="button" title="Ubah surat"'
                + ' data-letter-edit="' + Number(row.id) + '"><i class="bi bi-pencil"></i></button>'
                + '<button class="btn btn-ghost shift-action-btn shift-action-danger" type="button" title="Hapus surat"'
                + ' data-letter-delete="' + Number(row.id) + '"><i class="bi bi-trash"></i></button>'
                + '</div></td>'
                + '</tr>';
        }).join('');
    }

    function render(rows) {
        state.rows = Array.isArray(rows) ? rows : [];
        renderSummary(state.rows);
        renderTable(state.rows);
    }

    function showError(message) {
        state.rows = [];
        renderSummary([]);
        const body = $('letterTableBody');
        if (body) {
            body.innerHTML = '<tr><td colspan="6"><div class="attendance-empty">'
                + ELIPS.escapeHtml(message || 'Daftar surat gagal dimuat.') + '</div></td></tr>';
        }
        if (typeof ELIPS.toast === 'function') {
            ELIPS.toast(message || 'Daftar surat gagal dimuat.');
        }
    }

    function load() {
        if (state.loading) return;
        const filters = readFilters();
        if (filters.from && filters.to && filters.from > filters.to) {
            if (typeof ELIPS.toast === 'function') {
                ELIPS.toast('Tanggal awal tidak boleh setelah tanggal akhir.');
            }
            return;
        }

        state.loading = true;
        try {
            if (ELIPS.api && ELIPS.api.enabled) {
                const payload = {};
                if (filters.direction) payload.direction = filters.direction;
                if (filters.search) payload.search = filters.search;
                if (filters.from) payload.from = filters.from;
                if (filters.to) payload.to = filters.to;

                const result = ELIPS.api.call('letter.list', payload);
                if (!result.ok) {
                    showError(result.message);
                    return;
                }
                const data = result.data || {};
                render(data.rows || []);
                return;
            }

            render(localRows(filters));
        } finally {
            state.loading = false;
        }
    }

    function resetFilters() {
        ['letterDirection', 'letterSearch', 'letterFrom', 'letterTo'].forEach(function (id) {
            if ($(id)) $(id).value = '';
        });
        load();
    }

    function updateDirectionUi() {
        const direction = $('letterFormDirection') ? $('letterFormDirection').value : 'masuk';
        const handledLabel = $('letterHandledLabel');
        if (handledLabel) {
            handledLabel.textContent = direction === 'masuk' ? 'Tanggal Diterima' : 'Tanggal Dikirim';
        }
        const sender = $('letterFormSender');
        const recipient = $('letterFormRecipient');
        if (sender) {
            sender.placeholder = direction === 'masuk'
                ? 'Contoh: Dinas Pendidikan'
                : 'Contoh: ELIPS LMS';
        }
        if (recipient) {
            recipient.placeholder = direction === 'masuk'
                ? 'Contoh: ELIPS LMS'
                : 'Contoh: Dinas Pendidikan';
        }
        const hint = $('letterDirectionHint');
        if (hint) {
            hint.textContent = direction === 'masuk'
                ? 'Surat masuk: catat pengirim eksternal dan penerima internal, lalu simpan tanggal diterima.'
                : 'Surat keluar: catat pengirim internal dan penerima tujuan, lalu simpan tanggal dikirim.';
        }
    }

    function openModal() {
        const modal = $('letterModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeModal() {
        const modal = $('letterModal');
        if (modal) modal.style.display = 'none';
        state.editingId = 0;
        state.editingRow = null;
    }

    function openCreate() {
        state.editingId = 0;
        state.editingRow = null;
        const form = $('letterForm');
        if (form) form.reset();
        if ($('letterId')) $('letterId').value = '';
        if ($('letterModalTitle')) $('letterModalTitle').textContent = 'Tambah Surat';
        if ($('letterFormDirection')) $('letterFormDirection').value = 'masuk';
        if ($('letterFormDate')) $('letterFormDate').value = todayDate();
        if ($('letterFormHandled')) $('letterFormHandled').value = todayDate();
        if ($('letterFormClassification')) $('letterFormClassification').value = 'biasa';
        updateDirectionUi();
        openModal();
        if ($('letterFormNumber')) $('letterFormNumber').focus();
    }

    function openEdit(id) {
        const row = state.rows.filter(function (item) {
            return Number(item.id) === Number(id);
        })[0];
        if (!row) return;

        closeDetail();
        state.editingId = Number(row.id);
        state.editingRow = row;
        if ($('letterModalTitle')) $('letterModalTitle').textContent = 'Ubah Surat';
        if ($('letterId')) $('letterId').value = Number(row.id);
        if ($('letterFormDirection')) $('letterFormDirection').value = row.direction || 'masuk';
        if ($('letterFormNumber')) $('letterFormNumber').value = row.letterNumber || '';
        if ($('letterFormSubject')) $('letterFormSubject').value = row.subject || '';
        if ($('letterFormDate')) $('letterFormDate').value = row.letterDate || '';
        if ($('letterFormHandled')) $('letterFormHandled').value = row.handledDate || '';
        if ($('letterFormSender')) $('letterFormSender').value = row.sender || '';
        if ($('letterFormRecipient')) $('letterFormRecipient').value = row.recipient || '';
        if ($('letterFormCategory')) $('letterFormCategory').value = row.category || '';
        if ($('letterFormClassification')) $('letterFormClassification').value = row.classification || 'biasa';
        updateDirectionUi();
        openModal();
    }

    function detailItem(label, value, wide) {
        return '<div class="letter-detail-item' + (wide ? ' letter-detail-wide' : '') + '">'
            + '<div class="letter-detail-label">' + ELIPS.escapeHtml(label) + '</div>'
            + '<div class="letter-detail-value">' + value + '</div>'
            + '</div>';
    }

    function openDetail(id) {
        const row = state.rows.filter(function (item) {
            return Number(item.id) === Number(id);
        })[0];
        if (!row) return;

        state.detailId = Number(row.id);
        setText('letterDetailTitle', directionLabel(row.direction) + ' — ' + (row.letterNumber || '-'));
        setText('letterDetailSubtitle', row.subject || '-');

        const body = $('letterDetailBody');
        if (body) {
            body.innerHTML = detailItem('Jenis Surat', directionBadge(row.direction))
                + detailItem('Nomor Surat', ELIPS.escapeHtml(row.letterNumber || '-'))
                + detailItem('Tanggal Surat', ELIPS.escapeHtml(formatDateOnly(row.letterDate)))
                + detailItem(
                    row.direction === 'masuk' ? 'Tanggal Diterima' : 'Tanggal Dikirim',
                    row.handledDate ? ELIPS.escapeHtml(formatDateOnly(row.handledDate)) : '-'
                )
                + detailItem('Perihal', ELIPS.escapeHtml(row.subject || '-'), true)
                + detailItem('Pengirim', ELIPS.escapeHtml(row.sender || '-'))
                + detailItem('Penerima', ELIPS.escapeHtml(row.recipient || '-'))
                + detailItem('Kategori', ELIPS.escapeHtml(row.category || '-'))
                + detailItem('Sifat Surat', classificationBadge(row.classification))
                + detailItem('Dicatat oleh', ELIPS.escapeHtml(row.createdByName || '-'));
        }

        const detailEdit = $('letterDetailEdit');
        if (detailEdit) detailEdit.setAttribute('data-letter-detail-edit', String(row.id));
        const modal = $('letterDetailModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeDetail() {
        const modal = $('letterDetailModal');
        if (modal) modal.style.display = 'none';
        state.detailId = 0;
    }

    function localSave(payload) {
        const list = (typeof ELIPS.getLetters === 'function' ? ELIPS.getLetters() : []).slice();
        const user = typeof ELIPS.getCurrentUser === 'function' ? ELIPS.getCurrentUser() : null;
        const now = new Date().toISOString();
        const isEdit = state.editingId > 0;
        let saved = null;

        if (isEdit) {
            const index = list.findIndex(function (row) {
                return Number(row.id) === Number(state.editingId);
            });
            if (index === -1) {
                return { ok: false, message: 'Surat tidak ditemukan.' };
            }
            saved = Object.assign({}, list[index], payload, {
                updatedAt: now
            });
            list[index] = saved;
        } else {
            saved = Object.assign({}, payload, {
                id: ELIPS.nextId(list),
                createdBy: user ? Number(user.id) : null,
                createdByName: user ? (user.name || '') : '',
                createdAt: now,
                updatedAt: null
            });
            list.push(saved);
        }

        ELIPS.setLetters(list);
        if (typeof ELIPS.logAudit === 'function') {
            ELIPS.logAudit({
                action: isEdit ? 'update' : 'create',
                entity: 'letter',
                entityId: saved.id,
                entityLabel: directionLabel(saved.direction) + ' — ' + saved.letterNumber,
                detail: (isEdit ? 'Mengubah' : 'Menambahkan')
                    + ' ' + directionLabel(saved.direction).toLowerCase()
                    + ' nomor ' + saved.letterNumber
                    + ' perihal ' + saved.subject
                    + ' dari ' + saved.sender
                    + ' kepada ' + saved.recipient
                    + ' tanggal ' + saved.letterDate + '.'
            });
        }
        return {
            ok: true,
            message: isEdit ? 'Surat diperbarui.' : 'Surat ditambahkan.',
            data: saved
        };
    }

    function saveLetter(event) {
        if (event) event.preventDefault();

        const direction = $('letterFormDirection') ? $('letterFormDirection').value : 'masuk';
        const letterNumber = $('letterFormNumber') ? $('letterFormNumber').value.trim() : '';
        const subject = $('letterFormSubject') ? $('letterFormSubject').value.trim() : '';
        const letterDate = $('letterFormDate') ? $('letterFormDate').value : '';
        const handledDate = $('letterFormHandled') ? $('letterFormHandled').value : '';
        const sender = $('letterFormSender') ? $('letterFormSender').value.trim() : '';
        const recipient = $('letterFormRecipient') ? $('letterFormRecipient').value.trim() : '';
        const category = $('letterFormCategory') ? $('letterFormCategory').value.trim() : '';
        const classification = $('letterFormClassification') ? $('letterFormClassification').value : 'biasa';
        const legacy = state.editingRow || {};
        const status = legacy.status || 'baru';
        const summary = legacy.summary || '';
        const attachmentUrl = legacy.attachmentUrl || '';
        const notes = legacy.notes || '';

        if (!letterNumber) {
            ELIPS.toast('Nomor surat wajib diisi.');
            return;
        }
        if (!subject) {
            ELIPS.toast('Perihal surat wajib diisi.');
            return;
        }
        if (!sender) {
            ELIPS.toast('Pengirim surat wajib diisi.');
            return;
        }
        if (!recipient) {
            ELIPS.toast('Penerima surat wajib diisi.');
            return;
        }
        if (!isIsoDate(letterDate)) {
            ELIPS.toast('Tanggal surat wajib diisi dengan format yang valid.');
            return;
        }
        if (handledDate && !isIsoDate(handledDate)) {
            ELIPS.toast('Tanggal diterima/dikirim tidak valid.');
            return;
        }

        const payload = {
            direction: direction,
            letterNumber: letterNumber,
            subject: subject,
            sender: sender,
            recipient: recipient,
            letterDate: letterDate,
            handledDate: handledDate || '',
            category: category,
            classification: classification,
            status: status,
            summary: summary,
            attachmentUrl: attachmentUrl,
            notes: notes
        };

        let result;
        if (ELIPS.api && ELIPS.api.enabled) {
            if (state.editingId > 0) payload.id = state.editingId;
            result = ELIPS.api.call('letter.save', payload);
        } else {
            result = localSave(payload);
        }

        if (!result || !result.ok) {
            ELIPS.toast((result && result.message) || 'Surat gagal disimpan.');
            return;
        }
        ELIPS.toast(result.message || 'Surat disimpan.');
        closeModal();
        load();
    }

    function deleteLetter(id) {
        const row = state.rows.filter(function (item) {
            return Number(item.id) === Number(id);
        })[0];
        if (!row) return;
        const label = directionLabel(row.direction).toLowerCase()
            + ' nomor ' + row.letterNumber + ' perihal ' + row.subject;
        if (!window.confirm('Hapus ' + label + '?')) return;

        let result;
        if (ELIPS.api && ELIPS.api.enabled) {
            result = ELIPS.api.call('letter.delete', { id: Number(id) });
        } else {
            const list = (typeof ELIPS.getLetters === 'function' ? ELIPS.getLetters() : [])
                .filter(function (item) { return Number(item.id) !== Number(id); });
            ELIPS.setLetters(list);
            if (typeof ELIPS.logAudit === 'function') {
                ELIPS.logAudit({
                    action: 'delete',
                    entity: 'letter',
                    entityId: Number(id),
                    entityLabel: directionLabel(row.direction) + ' — ' + row.letterNumber,
                    detail: 'Menghapus ' + directionLabel(row.direction).toLowerCase()
                        + ' nomor ' + row.letterNumber
                        + ' perihal ' + row.subject
                        + ' dari ' + row.sender
                        + ' kepada ' + row.recipient + '.'
                });
            }
            result = { ok: true, message: 'Surat dihapus.' };
        }

        if (!result || !result.ok) {
            ELIPS.toast((result && result.message) || 'Surat gagal dihapus.');
            return;
        }
        ELIPS.toast(result.message || 'Surat dihapus.');
        load();
    }

    function init() {
        if (!$('letterTableBody')) return;

        const filterForm = $('letterFilterForm');
        if (filterForm) {
            filterForm.addEventListener('submit', function (event) {
                event.preventDefault();
                load();
            });
        }
        const resetBtn = $('letterResetBtn');
        if (resetBtn) resetBtn.addEventListener('click', resetFilters);
        const refreshBtn = $('letterRefreshBtn');
        if (refreshBtn) refreshBtn.addEventListener('click', load);
        const addBtn = $('letterAddBtn');
        if (addBtn) addBtn.addEventListener('click', openCreate);

        const directionSelect = $('letterFormDirection');
        if (directionSelect) directionSelect.addEventListener('change', updateDirectionUi);
        const letterForm = $('letterForm');
        if (letterForm) letterForm.addEventListener('submit', saveLetter);

        const closeBtn = $('letterModalClose');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        const cancelBtn = $('letterModalCancel');
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        const modal = $('letterModal');
        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal();
            });
        }

        const detailClose = $('letterDetailClose');
        if (detailClose) detailClose.addEventListener('click', closeDetail);
        const detailDone = $('letterDetailDone');
        if (detailDone) detailDone.addEventListener('click', closeDetail);
        const detailEdit = $('letterDetailEdit');
        if (detailEdit) {
            detailEdit.addEventListener('click', function () {
                const id = Number(detailEdit.getAttribute('data-letter-detail-edit') || 0);
                if (id) openEdit(id);
            });
        }
        const detailModal = $('letterDetailModal');
        if (detailModal) {
            detailModal.addEventListener('click', function (event) {
                if (event.target === detailModal) closeDetail();
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            if (detailModal && detailModal.style.display === 'flex') {
                closeDetail();
                return;
            }
            if (modal && modal.style.display === 'flex') {
                closeModal();
            }
        });

        const tableBody = $('letterTableBody');
        if (tableBody) {
            tableBody.addEventListener('click', function (event) {
                const detailBtn = event.target.closest('[data-letter-detail]');
                if (detailBtn) {
                    openDetail(Number(detailBtn.getAttribute('data-letter-detail')));
                    return;
                }
                const editBtn = event.target.closest('[data-letter-edit]');
                if (editBtn) {
                    openEdit(Number(editBtn.getAttribute('data-letter-edit')));
                    return;
                }
                const deleteBtn = event.target.closest('[data-letter-delete]');
                if (deleteBtn) {
                    deleteLetter(Number(deleteBtn.getAttribute('data-letter-delete')));
                }
            });
        }

        load();
    }

    ELIPS.letterArchive = {
        init: init,
        refresh: load,
        directionLabel: directionLabel,
        classificationLabel: classificationLabel
    };
})(window.ELIPS);
