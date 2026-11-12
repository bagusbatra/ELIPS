// ============================================================
// ELIPS LMS - staff-recap.js
// Rekap absensi karyawan untuk halaman Admin: filter tanggal,
// karyawan, role, ringkasan, tabel per hari, dan ekspor CSV.
// ============================================================

(function (ELIPS) {
    'use strict';

    const ATTENDANCE_TIMEZONE = 'Asia/Jakarta';
    const ROLE_LABELS = { admin: 'Admin', karyawan: 'Karyawan' };
    const STATUS_LABELS = {
        lengkap: 'Lengkap',
        belum_pulang: 'Belum pulang',
        tanpa_masuk: 'Tanpa masuk'
    };

    const state = {
        rows: [],
        entries: [],
        from: '',
        to: '',
        loading: false
    };

    function $(id) {
        return document.getElementById(id);
    }

    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function todayAttendanceDate(date) {
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

    function formatTime(iso) {
        if (!iso) return '';
        const date = new Date(iso);
        if (isNaN(date.getTime())) return '';
        try {
            return new Intl.DateTimeFormat('en-GB', {
                timeZone: ATTENDANCE_TIMEZONE,
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).format(date);
        } catch (err) {
            return pad(date.getHours()) + ':' + pad(date.getMinutes());
        }
    }

    function formatDuration(inIso, outIso) {
        if (!inIso || !outIso) return '-';
        const start = new Date(inIso).getTime();
        const end = new Date(outIso).getTime();
        if (isNaN(start) || isNaN(end) || end < start) return '-';
        const minutes = Math.round((end - start) / 60000);
        const hours = Math.floor(minutes / 60);
        const rest = minutes % 60;
        if (hours <= 0) return rest + ' mnt';
        return hours + ' jam ' + rest + ' mnt';
    }

    function initials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        const first = parts[0].charAt(0);
        const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
        return (first + last).toUpperCase();
    }

    function statusLabel(status) {
        return STATUS_LABELS[status] || status || '-';
    }

    function statusBadge(status) {
        if (status === 'lengkap') {
            return '<span class="badge badge-success">Lengkap</span>';
        }
        if (status === 'belum_pulang') {
            return '<span class="badge badge-warning">Belum pulang</span>';
        }
        return '<span class="badge" style="background:#fee2e2;color:#991b1b;">Tanpa masuk</span>';
    }

    function locationText(entry) {
        const record = entry.inRecord || entry.outRecord;
        if (!record) return '-';
        if (record.locationLabel) return record.locationLabel;
        if (typeof record.latitude === 'number' && typeof record.longitude === 'number') {
            return record.latitude.toFixed(5) + ', ' + record.longitude.toFixed(5);
        }
        return '-';
    }

    function aggregate(rows) {
        const map = {};
        const entries = [];
        (Array.isArray(rows) ? rows : []).forEach(function (row) {
            if (!row || row.attendanceDate === undefined) return;
            const key = row.userId + '|' + row.attendanceDate;
            let entry = map[key];
            if (!entry) {
                entry = {
                    userId: row.userId,
                    userName: row.userName || ('Pengguna #' + row.userId),
                    userEmail: row.userEmail || '',
                    userRole: row.userRole || '',
                    attendanceDate: row.attendanceDate,
                    inRecord: null,
                    outRecord: null,
                    status: 'tanpa_masuk'
                };
                map[key] = entry;
                entries.push(entry);
            }
            if (row.kind === 'in' && !entry.inRecord) entry.inRecord = row;
            if (row.kind === 'out' && !entry.outRecord) entry.outRecord = row;
        });

        entries.forEach(function (entry) {
            if (entry.inRecord && entry.outRecord) entry.status = 'lengkap';
            else if (entry.inRecord) entry.status = 'belum_pulang';
            else entry.status = 'tanpa_masuk';
        });

        entries.sort(function (a, b) {
            if (a.attendanceDate !== b.attendanceDate) {
                return a.attendanceDate < b.attendanceDate ? 1 : -1;
            }
            return String(a.userName).localeCompare(String(b.userName));
        });
        return entries;
    }

    function setText(id, value) {
        const node = $(id);
        if (node) node.textContent = String(value);
    }

    function renderSummary(entries) {
        const users = {};
        let complete = 0;
        let incomplete = 0;
        entries.forEach(function (entry) {
            users[entry.userId] = true;
            if (entry.status === 'lengkap') complete++;
            else incomplete++;
        });
        setText('recapStatEmployees', Object.keys(users).length);
        setText('recapStatDays', entries.length);
        setText('recapStatComplete', complete);
        setText('recapStatMissing', incomplete);
    }

    function renderTable(entries) {
        const body = $('recapTableBody');
        if (!body) return;
        if (!entries.length) {
            body.innerHTML = '<tr><td colspan="7"><div class="attendance-empty">'
                + 'Belum ada absensi pada rentang tanggal ini.</div></td></tr>';
            return;
        }
        body.innerHTML = entries.map(function (entry) {
            const role = ROLE_LABELS[entry.userRole] || entry.userRole || '';
            const email = entry.userEmail || '-';
            const meta = role ? email + ' \u2022 ' + role : email;
            const inTime = entry.inRecord ? formatTime(entry.inRecord.takenAt) : '';
            const outTime = entry.outRecord ? formatTime(entry.outRecord.takenAt) : '';
            return '<tr>'
                + '<td>' + ELIPS.escapeHtml(formatDateOnly(entry.attendanceDate)) + '</td>'
                + '<td><div class="table-course">'
                + '<div class="dashboard-avatar" style="width:36px;height:36px;font-size:0.875rem;">'
                + ELIPS.escapeHtml(initials(entry.userName)) + '</div>'
                + '<div class="table-course-info">'
                + '<div class="table-course-title">' + ELIPS.escapeHtml(entry.userName) + '</div>'
                + '<div class="table-course-meta">' + ELIPS.escapeHtml(meta) + '</div>'
                + '</div></div></td>'
                + '<td>' + (inTime ? ELIPS.escapeHtml(inTime) : '<span style="color:var(--text-secondary);">-</span>') + '</td>'
                + '<td>' + (outTime ? ELIPS.escapeHtml(outTime) : '<span style="color:var(--text-secondary);">-</span>') + '</td>'
                + '<td>' + ELIPS.escapeHtml(formatDuration(
                    entry.inRecord && entry.inRecord.takenAt,
                    entry.outRecord && entry.outRecord.takenAt
                )) + '</td>'
                + '<td>' + ELIPS.escapeHtml(locationText(entry)) + '</td>'
                + '<td>' + statusBadge(entry.status) + '</td>'
                + '</tr>';
        }).join('');
    }

    function render(rows) {
        state.rows = Array.isArray(rows) ? rows : [];
        state.entries = aggregate(state.rows);
        renderSummary(state.entries);
        renderTable(state.entries);
        const range = $('recapRangeLabel');
        if (range) {
            range.textContent = formatDateOnly(state.from) + ' \u2014 ' + formatDateOnly(state.to);
        }
    }

    function showError(message) {
        state.entries = [];
        renderSummary([]);
        const body = $('recapTableBody');
        if (body) {
            body.innerHTML = '<tr><td colspan="7"><div class="attendance-empty">'
                + ELIPS.escapeHtml(message || 'Rekap absensi gagal dimuat.') + '</div></td></tr>';
        }
        if (typeof ELIPS.toast === 'function') ELIPS.toast(message || 'Rekap absensi gagal dimuat.');
    }

    function populateEmployees() {
        const select = $('recapEmployee');
        if (!select) return;
        const current = select.value;
        const users = (typeof ELIPS.getUsers === 'function' ? ELIPS.getUsers() : [])
            .filter(function (user) {
                return user && (user.role === 'admin' || user.role === 'karyawan');
            })
            .sort(function (a, b) {
                return String(a.name || '').localeCompare(String(b.name || ''));
            });
        select.innerHTML = '<option value="">Semua staf</option>' + users.map(function (user) {
            const label = (user.name || ('Pengguna #' + user.id))
                + ' \u2014 ' + (ROLE_LABELS[user.role] || user.role);
            return '<option value="' + Number(user.id) + '">' + ELIPS.escapeHtml(label) + '</option>';
        }).join('');
        if (current) select.value = current;
    }

    function readFilters() {
        return {
            from: $('recapFrom') ? $('recapFrom').value : '',
            to: $('recapTo') ? $('recapTo').value : '',
            userId: $('recapEmployee') ? $('recapEmployee').value : '',
            role: $('recapRole') ? $('recapRole').value : ''
        };
    }

    function setInputs(from, to) {
        if ($('recapFrom')) $('recapFrom').value = from || '';
        if ($('recapTo')) $('recapTo').value = to || '';
    }

    function localRows(filters) {
        const users = typeof ELIPS.getUsers === 'function' ? ELIPS.getUsers() : [];
        const byId = {};
        users.forEach(function (user) { byId[user.id] = user; });
        const targetId = filters.userId ? Number(filters.userId) : 0;
        const role = filters.role || '';
        const list = typeof ELIPS.getStaffAttendances === 'function' ? ELIPS.getStaffAttendances() : [];

        return list.filter(function (row) {
            if (!row || !row.attendanceDate) return false;
            if (row.attendanceDate < state.from || row.attendanceDate > state.to) return false;
            if (targetId && Number(row.userId) !== targetId) return false;
            const user = byId[row.userId] || {};
            if (role && user.role !== role) return false;
            return true;
        }).map(function (row) {
            const user = byId[row.userId] || {};
            return Object.assign({}, row, {
                userName: row.userName || user.name || '',
                userEmail: row.userEmail || user.email || '',
                userRole: row.userRole || user.role || ''
            });
        });
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
                if (filters.role) payload.role = filters.role;

                const result = ELIPS.api.call('staffAttendance.recap', payload);
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

            state.from = filters.from || firstDayOfMonth(todayAttendanceDate());
            state.to = filters.to || todayAttendanceDate();
            setInputs(state.from, state.to);
            render(localRows(filters));
        } finally {
            state.loading = false;
        }
    }

    function setThisMonth() {
        const today = todayAttendanceDate();
        setInputs(firstDayOfMonth(today), today);
        load();
    }

    function csvCell(value) {
        const text = value === undefined || value === null ? '' : String(value);
        return '"' + text.replace(/"/g, '""') + '"';
    }

    function exportCsv() {
        if (!state.entries.length) {
            if (typeof ELIPS.toast === 'function') ELIPS.toast('Tidak ada data untuk diekspor.');
            return;
        }
        const lines = [[
            'Tanggal', 'Nama', 'Email', 'Role', 'Masuk', 'Pulang', 'Durasi',
            'Status', 'Lokasi', 'Catatan masuk', 'Catatan pulang', 'Latitude', 'Longitude'
        ].map(csvCell).join(',')];

        state.entries.forEach(function (entry) {
            const inRec = entry.inRecord || {};
            const outRec = entry.outRecord || {};
            lines.push([
                entry.attendanceDate,
                entry.userName,
                entry.userEmail,
                ROLE_LABELS[entry.userRole] || entry.userRole || '',
                formatTime(inRec.takenAt),
                formatTime(outRec.takenAt),
                formatDuration(inRec.takenAt, outRec.takenAt),
                statusLabel(entry.status),
                locationText(entry),
                inRec.notes || '',
                outRec.notes || '',
                inRec.latitude === undefined || inRec.latitude === null ? '' : inRec.latitude,
                inRec.longitude === undefined || inRec.longitude === null ? '' : inRec.longitude
            ].map(csvCell).join(','));
        });

        const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'rekap-absensi-' + (state.from || 'awal') + '-sd-' + (state.to || 'akhir') + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 0);
        if (typeof ELIPS.toast === 'function') ELIPS.toast('Rekap absensi diekspor.');
    }

    function init() {
        if (!$('recapTableBody')) return;
        populateEmployees();

        const form = $('recapFilterForm');
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                load();
            });
        }
        const refreshBtn = $('recapRefreshBtn');
        if (refreshBtn) refreshBtn.addEventListener('click', load);
        const monthBtn = $('recapThisMonthBtn');
        if (monthBtn) monthBtn.addEventListener('click', setThisMonth);
        const exportBtn = $('recapExportBtn');
        if (exportBtn) exportBtn.addEventListener('click', exportCsv);

        const today = todayAttendanceDate();
        setInputs(firstDayOfMonth(today), today);
        load();
    }

    ELIPS.staffRecap = {
        init: init,
        refresh: load,
        exportCsv: exportCsv,
        aggregate: aggregate,
        statusLabel: statusLabel
    };
})(window.ELIPS);
