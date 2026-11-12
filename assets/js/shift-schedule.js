// ============================================================
// ELIPS LMS - shift-schedule.js
// Jadwal shift karyawan untuk halaman Admin: filter tanggal/staf,
// preset Pagi/Sore, jam custom, serta tambah/ubah/hapus shift.
// ============================================================

(function (ELIPS) {
    'use strict';

    const ATTENDANCE_TIMEZONE = 'Asia/Jakarta';
    const ROLE_LABELS = { admin: 'Admin', karyawan: 'Karyawan' };
    const TYPE_LABELS = { pagi: 'Pagi', sore: 'Sore', custom: 'Custom' };
    const PRESETS = {
        pagi: { start: '08:00', end: '16:00' },
        sore: { start: '16:00', end: '00:00' }
    };

    const state = {
        rows: [],
        from: '',
        to: '',
        loading: false,
        editingId: 0
    };

    function $(id) {
        return document.getElementById(id);
    }

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function todayDate(date) {
        const value = date instanceof Date ? date : new Date();
        try {
            return new Intl.DateTimeFormat('en-CA', {
                timeZone: ATTENDANCE_TIMEZONE,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }).format(value);
        } catch (err) {
            return value.getFullYear() + '-' + pad(value.getMonth() + 1) + '-' + pad(value.getDate());
        }
    }

    function firstDayOfMonth(dateIso) {
        return String(dateIso || '').slice(0, 8) + '01';
    }

    function lastDayOfMonth(dateIso) {
        const parts = String(dateIso || '').split('-');
        const year = Number(parts[0]);
        const month = Number(parts[1]);
        if (!year || !month) return dateIso;
        const last = new Date(year, month, 0).getDate();
        return parts[0] + '-' + pad(month) + '-' + pad(last);
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

    function initials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        const first = parts[0].charAt(0);
        const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
        return (first + last).toUpperCase();
    }

    function setText(id, value) {
        const node = $(id);
        if (node) node.textContent = String(value);
    }

    function typeLabel(type) {
        return TYPE_LABELS[type] || type || '-';
    }

    function typeBadge(type) {
        if (type === 'pagi') return '<span class="badge badge-success">Pagi</span>';
        if (type === 'sore') return '<span class="badge badge-warning">Sore</span>';
        return '<span class="badge shift-badge-custom">Custom</span>';
    }

    function staffUsers() {
        return (typeof ELIPS.getUsers === 'function' ? ELIPS.getUsers() : [])
            .filter(function (user) {
                return user && (user.role === 'admin' || user.role === 'karyawan');
            })
            .sort(function (a, b) {
                return String(a.name || '').localeCompare(String(b.name || ''));
            });
    }

    function populateEmployeeSelects() {
        const users = staffUsers();
        const options = users.map(function (user) {
            const label = (user.name || ('Pengguna #' + user.id))
                + ' \u2014 ' + (ROLE_LABELS[user.role] || user.role);
            return '<option value="' + Number(user.id) + '">'
                + ELIPS.escapeHtml(label) + '</option>';
        }).join('');

        const filter = $('shiftEmployee');
        if (filter) {
            const current = filter.value;
            filter.innerHTML = '<option value="">Semua staf</option>' + options;
            if (current) filter.value = current;
        }

        const form = $('shiftFormEmployee');
        if (form) {
            const current = form.value;
            form.innerHTML = '<option value="">Pilih staf</option>' + options;
            if (current) form.value = current;
        }
    }

    function readFilters() {
        return {
            from: $('shiftFrom') ? $('shiftFrom').value : '',
            to: $('shiftTo') ? $('shiftTo').value : '',
            userId: $('shiftEmployee') ? $('shiftEmployee').value : '',
            shiftType: $('shiftType') ? $('shiftType').value : ''
        };
    }

    function setInputs(from, to) {
        if ($('shiftFrom')) $('shiftFrom').value = from || '';
        if ($('shiftTo')) $('shiftTo').value = to || '';
    }

    function localRows(filters) {
        const users = typeof ELIPS.getUsers === 'function' ? ELIPS.getUsers() : [];
        const byId = {};
        users.forEach(function (user) { byId[user.id] = user; });
        const targetId = filters.userId ? Number(filters.userId) : 0;
        const type = filters.shiftType || '';
        const list = typeof ELIPS.getStaffShifts === 'function' ? ELIPS.getStaffShifts() : [];

        return list.filter(function (row) {
            if (!row || !row.shiftDate) return false;
            if (row.shiftDate < state.from || row.shiftDate > state.to) return false;
            if (targetId && Number(row.userId) !== targetId) return false;
            if (type && row.shiftType !== type) return false;
            return true;
        }).map(function (row) {
            const user = byId[row.userId] || {};
            return Object.assign({}, row, {
                userName: row.userName || user.name || '',
                userEmail: row.userEmail || user.email || '',
                userRole: row.userRole || user.role || ''
            });
        }).sort(function (a, b) {
            if (a.shiftDate !== b.shiftDate) return a.shiftDate < b.shiftDate ? -1 : 1;
            return String(a.userName || '').localeCompare(String(b.userName || ''));
        });
    }

    function renderSummary(rows) {
        let pagi = 0;
        let sore = 0;
        let custom = 0;
        rows.forEach(function (row) {
            if (row.shiftType === 'pagi') pagi++;
            else if (row.shiftType === 'sore') sore++;
            else custom++;
        });
        setText('shiftStatTotal', rows.length);
        setText('shiftStatPagi', pagi);
        setText('shiftStatSore', sore);
        setText('shiftStatCustom', custom);
    }

    function renderTable(rows) {
        const body = $('shiftTableBody');
        if (!body) return;
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="6"><div class="attendance-empty">'
                + 'Belum ada jadwal shift pada rentang tanggal ini.</div></td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (row) {
            const role = ROLE_LABELS[row.userRole] || row.userRole || '';
            const email = row.userEmail || '-';
            const meta = role ? email + ' \u2022 ' + role : email;
            const start = String(row.startTime || '').slice(0, 5);
            const end = String(row.endTime || '').slice(0, 5);
            const overnight = start && end && end <= start
                ? '<span class="shift-overnight">+1 hari</span>' : '';
            return '<tr>'
                + '<td>' + ELIPS.escapeHtml(formatDateOnly(row.shiftDate)) + '</td>'
                + '<td><div class="table-course">'
                + '<div class="dashboard-avatar" style="width:36px;height:36px;font-size:0.875rem;">'
                + ELIPS.escapeHtml(initials(row.userName)) + '</div>'
                + '<div class="table-course-info">'
                + '<div class="table-course-title">' + ELIPS.escapeHtml(row.userName || '-') + '</div>'
                + '<div class="table-course-meta">' + ELIPS.escapeHtml(meta) + '</div>'
                + '</div></div></td>'
                + '<td>' + typeBadge(row.shiftType) + '</td>'
                + '<td>' + ELIPS.escapeHtml(start + ' \u2013 ' + end) + overnight + '</td>'
                + '<td>' + (row.note ? ELIPS.escapeHtml(row.note) : '<span class="schedule-muted">-</span>') + '</td>'
                + '<td><div class="shift-actions">'
                + '<button class="btn btn-ghost shift-action-btn" type="button" title="Ubah shift"'
                + ' data-shift-edit="' + Number(row.id) + '"><i class="bi bi-pencil"></i></button>'
                + '<button class="btn btn-ghost shift-action-btn shift-action-danger" type="button" title="Hapus shift"'
                + ' data-shift-delete="' + Number(row.id) + '"><i class="bi bi-trash"></i></button>'
                + '</div></td>'
                + '</tr>';
        }).join('');
    }

    function render(rows) {
        state.rows = Array.isArray(rows) ? rows : [];
        renderSummary(state.rows);
        renderTable(state.rows);
        const range = $('shiftRangeLabel');
        if (range) {
            range.textContent = formatDateOnly(state.from) + ' \u2014 ' + formatDateOnly(state.to);
        }
    }

    function showError(message) {
        state.rows = [];
        renderSummary([]);
        const body = $('shiftTableBody');
        if (body) {
            body.innerHTML = '<tr><td colspan="6"><div class="attendance-empty">'
                + ELIPS.escapeHtml(message || 'Jadwal shift gagal dimuat.') + '</div></td></tr>';
        }
        if (typeof ELIPS.toast === 'function') {
            ELIPS.toast(message || 'Jadwal shift gagal dimuat.');
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
                if (filters.from) payload.from = filters.from;
                if (filters.to) payload.to = filters.to;
                if (filters.userId) payload.userId = Number(filters.userId);
                if (filters.shiftType) payload.shiftType = filters.shiftType;

                const result = ELIPS.api.call('staffShift.list', payload);
                if (!result.ok) {
                    showError(result.message);
                    return;
                }
                const data = result.data || {};
                state.from = data.from || filters.from;
                state.to = data.to || filters.to;
                setInputs(state.from, state.to);
                render(data.rows || []);
                return;
            }

            const today = todayDate();
            state.from = filters.from || firstDayOfMonth(today);
            state.to = filters.to || lastDayOfMonth(today);
            setInputs(state.from, state.to);
            render(localRows(filters));
        } finally {
            state.loading = false;
        }
    }

    function setThisMonth() {
        const today = todayDate();
        setInputs(firstDayOfMonth(today), lastDayOfMonth(today));
        load();
    }

    function updateTimeInputState() {
        const type = $('shiftFormType') ? $('shiftFormType').value : 'pagi';
        const isCustom = type === 'custom';
        const start = $('shiftFormStart');
        const end = $('shiftFormEnd');
        if (start) start.readOnly = !isCustom;
        if (end) end.readOnly = !isCustom;
        const hint = $('shiftFormHint');
        if (hint) {
            hint.textContent = isCustom
                ? 'Isi jam mulai dan jam selesai secara manual. Shift yang melewati tengah malam tetap dapat disimpan.'
                : 'Shift ' + typeLabel(type) + ' memakai jam '
                    + PRESETS[type].start + ' - ' + PRESETS[type].end
                    + '. Pilih Custom untuk mengubah jam secara manual.';
        }
    }

    function applyTypePreset() {
        const type = $('shiftFormType') ? $('shiftFormType').value : 'pagi';
        if (type !== 'custom' && PRESETS[type]) {
            if ($('shiftFormStart')) $('shiftFormStart').value = PRESETS[type].start;
            if ($('shiftFormEnd')) $('shiftFormEnd').value = PRESETS[type].end;
        }
        updateTimeInputState();
    }

    function openModal() {
        const modal = $('shiftModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeModal() {
        const modal = $('shiftModal');
        if (modal) modal.style.display = 'none';
        state.editingId = 0;
    }

    function openCreate() {
        state.editingId = 0;
        const form = $('shiftForm');
        if (form) form.reset();
        if ($('shiftId')) $('shiftId').value = '';
        if ($('shiftModalTitle')) $('shiftModalTitle').textContent = 'Tambah Shift';
        if ($('shiftFormDate')) $('shiftFormDate').value = todayDate();
        if ($('shiftFormType')) $('shiftFormType').value = 'pagi';
        applyTypePreset();
        populateEmployeeSelects();
        openModal();
        if ($('shiftFormEmployee')) $('shiftFormEmployee').focus();
    }

    function openEdit(id) {
        const row = state.rows.filter(function (item) {
            return Number(item.id) === Number(id);
        })[0];
        if (!row) return;

        state.editingId = Number(row.id);
        if ($('shiftModalTitle')) $('shiftModalTitle').textContent = 'Ubah Shift';
        if ($('shiftId')) $('shiftId').value = Number(row.id);
        populateEmployeeSelects();
        if ($('shiftFormEmployee')) $('shiftFormEmployee').value = String(row.userId);
        if ($('shiftFormDate')) $('shiftFormDate').value = row.shiftDate || '';
        if ($('shiftFormType')) $('shiftFormType').value = row.shiftType || 'pagi';
        if ($('shiftFormStart')) $('shiftFormStart').value = String(row.startTime || '').slice(0, 5);
        if ($('shiftFormEnd')) $('shiftFormEnd').value = String(row.endTime || '').slice(0, 5);
        if ($('shiftFormNote')) $('shiftFormNote').value = row.note || '';
        updateTimeInputState();
        openModal();
    }

    function localSave(payload) {
        const list = (typeof ELIPS.getStaffShifts === 'function' ? ELIPS.getStaffShifts() : []).slice();
        const conflict = list.some(function (row) {
            return Number(row.userId) === Number(payload.userId)
                && row.shiftDate === payload.shiftDate
                && Number(row.id) !== Number(state.editingId);
        });
        if (conflict) {
            return { ok: false, message: 'Sudah ada shift untuk karyawan ini pada tanggal tersebut.' };
        }

        const users = typeof ELIPS.getUsers === 'function' ? ELIPS.getUsers() : [];
        const owner = users.filter(function (user) {
            return Number(user.id) === Number(payload.userId);
        })[0] || {};
        const now = new Date().toISOString();
        let saved = null;

        if (state.editingId > 0) {
            const index = list.findIndex(function (row) {
                return Number(row.id) === Number(state.editingId);
            });
            if (index === -1) {
                return { ok: false, message: 'Jadwal shift tidak ditemukan.' };
            }
            saved = Object.assign({}, list[index], payload, {
                userName: owner.name || list[index].userName || '',
                userEmail: owner.email || list[index].userEmail || '',
                userRole: owner.role || list[index].userRole || '',
                updatedAt: now
            });
            list[index] = saved;
        } else {
            saved = Object.assign({}, payload, {
                id: ELIPS.nextId(list),
                userName: owner.name || '',
                userEmail: owner.email || '',
                userRole: owner.role || '',
                createdAt: now,
                updatedAt: null
            });
            list.push(saved);
        }

        ELIPS.setStaffShifts(list);
        if (typeof ELIPS.logAudit === 'function') {
            ELIPS.logAudit({
                action: state.editingId > 0 ? 'update' : 'create',
                entity: 'staff_shift',
                entityId: saved.id,
                entityLabel: 'Shift ' + typeLabel(saved.shiftType) + ' \u2014 ' + saved.shiftDate,
                detail: (state.editingId > 0 ? 'Mengubah' : 'Menambahkan')
                    + ' shift ' + typeLabel(saved.shiftType)
                    + ' untuk ' + (saved.userName || ('Pengguna #' + saved.userId))
                    + ' pada ' + saved.shiftDate
                    + ' (' + saved.startTime + '\u2013' + saved.endTime + ').'
            });
        }
        return { ok: true, message: state.editingId > 0 ? 'Jadwal shift diperbarui.' : 'Jadwal shift ditambahkan.', data: saved };
    }

    function saveShift(event) {
        if (event) event.preventDefault();

        const userId = $('shiftFormEmployee') ? Number($('shiftFormEmployee').value) : 0;
        const shiftDate = $('shiftFormDate') ? $('shiftFormDate').value : '';
        const shiftType = $('shiftFormType') ? $('shiftFormType').value : 'pagi';
        const note = $('shiftFormNote') ? $('shiftFormNote').value.trim() : '';
        let startTime = $('shiftFormStart') ? $('shiftFormStart').value : '';
        let endTime = $('shiftFormEnd') ? $('shiftFormEnd').value : '';

        if (!userId) {
            ELIPS.toast('Pilih karyawan terlebih dahulu.');
            return;
        }
        if (!shiftDate) {
            ELIPS.toast('Tanggal shift wajib diisi.');
            return;
        }
        if (shiftType !== 'custom' && PRESETS[shiftType]) {
            startTime = PRESETS[shiftType].start;
            endTime = PRESETS[shiftType].end;
        }
        if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(startTime) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(endTime)) {
            ELIPS.toast('Jam shift wajib diisi dengan format HH:MM.');
            return;
        }
        if (startTime === endTime) {
            ELIPS.toast('Jam mulai dan jam selesai tidak boleh sama.');
            return;
        }

        const payload = {
            userId: userId,
            shiftDate: shiftDate,
            shiftType: shiftType,
            startTime: startTime,
            endTime: endTime,
            note: note
        };

        let result;
        if (ELIPS.api && ELIPS.api.enabled) {
            if (state.editingId > 0) payload.id = state.editingId;
            result = ELIPS.api.call('staffShift.save', payload);
        } else {
            result = localSave(payload);
        }

        if (!result || !result.ok) {
            ELIPS.toast((result && result.message) || 'Jadwal shift gagal disimpan.');
            return;
        }
        ELIPS.toast(result.message || 'Jadwal shift disimpan.');
        closeModal();
        load();
    }

    function deleteShift(id) {
        const row = state.rows.filter(function (item) {
            return Number(item.id) === Number(id);
        })[0];
        if (!row) return;
        const label = 'shift ' + typeLabel(row.shiftType) + ' '
            + formatDateOnly(row.shiftDate) + ' untuk ' + (row.userName || 'karyawan');
        if (!window.confirm('Hapus ' + label + '?')) return;

        let result;
        if (ELIPS.api && ELIPS.api.enabled) {
            result = ELIPS.api.call('staffShift.delete', { id: Number(id) });
        } else {
            const list = (typeof ELIPS.getStaffShifts === 'function' ? ELIPS.getStaffShifts() : [])
                .filter(function (item) { return Number(item.id) !== Number(id); });
            ELIPS.setStaffShifts(list);
            if (typeof ELIPS.logAudit === 'function') {
                ELIPS.logAudit({
                    action: 'delete',
                    entity: 'staff_shift',
                    entityId: Number(id),
                    entityLabel: 'Shift ' + typeLabel(row.shiftType) + ' \u2014 ' + row.shiftDate,
                    detail: 'Menghapus shift ' + typeLabel(row.shiftType)
                        + ' milik ' + (row.userName || ('Pengguna #' + row.userId))
                        + ' pada ' + row.shiftDate + '.'
                });
            }
            result = { ok: true, message: 'Jadwal shift dihapus.' };
        }

        if (!result || !result.ok) {
            ELIPS.toast((result && result.message) || 'Jadwal shift gagal dihapus.');
            return;
        }
        ELIPS.toast(result.message || 'Jadwal shift dihapus.');
        load();
    }

    function init() {
        if (!$('shiftTableBody')) return;
        populateEmployeeSelects();

        const form = $('shiftFilterForm');
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                load();
            });
        }
        const refreshBtn = $('shiftRefreshBtn');
        if (refreshBtn) refreshBtn.addEventListener('click', load);
        const monthBtn = $('shiftThisMonthBtn');
        if (monthBtn) monthBtn.addEventListener('click', setThisMonth);
        const addBtn = $('shiftAddBtn');
        if (addBtn) addBtn.addEventListener('click', openCreate);

        const typeSelect = $('shiftFormType');
        if (typeSelect) typeSelect.addEventListener('change', applyTypePreset);
        const shiftForm = $('shiftForm');
        if (shiftForm) shiftForm.addEventListener('submit', saveShift);

        const closeBtn = $('shiftModalClose');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        const cancelBtn = $('shiftModalCancel');
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        const modal = $('shiftModal');
        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal();
            });
        }
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && modal.style.display === 'flex') {
                closeModal();
            }
        });

        const tableBody = $('shiftTableBody');
        if (tableBody) {
            tableBody.addEventListener('click', function (event) {
                const editBtn = event.target.closest('[data-shift-edit]');
                if (editBtn) {
                    openEdit(Number(editBtn.getAttribute('data-shift-edit')));
                    return;
                }
                const deleteBtn = event.target.closest('[data-shift-delete]');
                if (deleteBtn) {
                    deleteShift(Number(deleteBtn.getAttribute('data-shift-delete')));
                }
            });
        }

        const today = todayDate();
        setInputs(firstDayOfMonth(today), lastDayOfMonth(today));
        load();
    }

    ELIPS.shiftSchedule = {
        init: init,
        refresh: load,
        typeLabel: typeLabel,
        presets: PRESETS
    };
})(window.ELIPS);
