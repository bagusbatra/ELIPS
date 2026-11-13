// ============================================================
// ELIPS LMS - employee-dashboard.js
// Dashboard sederhana karyawan: status absensi hari ini, rekap
// bulan berjalan, dan riwayat terbaru. Mendukung mode server
// (staffAttendance.list) dan fallback localStorage.
// ============================================================

(function (ELIPS) {
    'use strict';

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function formatDate(value) {
        const parts = String(value || '').split('-');
        if (parts.length !== 3) return value || '-';
        return Number(parts[2]) + ' ' + (MONTHS[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
    }

    function formatTime(value) {
        if (!value) return '-';
        const date = new Date(value);
        if (isNaN(date.getTime())) return '-';
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function todayString() {
        const now = new Date();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return now.getFullYear() + '-' + month + '-' + day;
    }

    function greetingForNow() {
        const hour = new Date().getHours();
        if (hour < 11) return 'Selamat pagi';
        if (hour < 15) return 'Selamat siang';
        if (hour < 19) return 'Selamat sore';
        return 'Selamat malam';
    }

    function loadRecords(user) {
        if (ELIPS.api && ELIPS.api.enabled) {
            const result = ELIPS.api.call('staffAttendance.list', { limit: 100 });
            if (result && result.ok && Array.isArray(result.data)) {
                return { records: result.data, error: '' };
            }
            return {
                records: [],
                error: (result && result.message) || 'Riwayat absensi gagal dimuat.'
            };
        }
        return { records: ELIPS.getStaffAttendancesByUser(user.id), error: '' };
    }

    function findKind(records, date, kind) {
        return records.find(function (record) {
            return record.attendanceDate === date && record.kind === kind;
        }) || null;
    }

    function renderRecent(records, error) {
        const tbody = document.getElementById('recentAttendanceBody');
        if (!tbody) return;

        if (error) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-secondary);">' +
                ELIPS.escapeHtml(error) + '</td></tr>';
            return;
        }

        const recent = records.slice(0, 5);
        if (recent.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem 1rem; color: var(--text-secondary);">' +
                '<i class="bi bi-calendar-x" style="display: block; font-size: 2rem; opacity: .45; margin-bottom: .5rem;"></i>' +
                'Belum ada catatan absensi. Mulai dengan absen masuk hari ini.</td></tr>';
            return;
        }

        tbody.innerHTML = recent.map(function (record) {
            const isIn = record.kind === 'in';
            return '<tr>' +
                '<td>' + ELIPS.escapeHtml(formatDate(record.attendanceDate)) + '</td>' +
                '<td><span class="badge ' + (isIn ? 'badge-success' : 'badge-warning') + '">' +
                    '<i class="bi ' + (isIn ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right') + '"></i> ' +
                    (isIn ? 'Masuk' : 'Pulang') + '</span></td>' +
                '<td>' + ELIPS.escapeHtml(formatTime(record.takenAt)) + '</td>' +
                '<td>' + (record.locationLabel
                    ? ELIPS.escapeHtml(record.locationLabel)
                    : '<span style="color: var(--text-secondary);">-</span>') + '</td>' +
                '</tr>';
        }).join('');
    }

    function render(user) {
        const today = todayString();
        const monthPrefix = today.slice(0, 7);
        const loaded = loadRecords(user);
        const records = loaded.records || [];

        const todayIn = findKind(records, today, 'in');
        const todayOut = findKind(records, today, 'out');
        const monthRecords = records.filter(function (record) {
            return String(record.attendanceDate || '').slice(0, 7) === monthPrefix;
        });
        const monthDays = new Set(monthRecords.map(function (record) {
            return record.attendanceDate;
        })).size;

        const statToday = document.getElementById('statToday');
        const statTodayLabel = document.getElementById('statTodayLabel');
        if (statToday) {
            statToday.textContent = todayIn && todayOut
                ? 'Lengkap'
                : (todayIn ? 'Sudah Masuk' : (todayOut ? 'Sudah Pulang' : 'Belum Absen'));
        }
        if (statTodayLabel) {
            const parts = [];
            if (todayIn) parts.push('Masuk ' + formatTime(todayIn.takenAt));
            if (todayOut) parts.push('Pulang ' + formatTime(todayOut.takenAt));
            statTodayLabel.textContent = parts.length > 0 ? parts.join(' • ') : 'Absen masuk belum tercatat';
        }

        const statMonthDays = document.getElementById('statMonthDays');
        if (statMonthDays) statMonthDays.textContent = String(monthDays);

        const statMonthRecords = document.getElementById('statMonthRecords');
        if (statMonthRecords) statMonthRecords.textContent = String(monthRecords.length);

        renderRecent(records, loaded.error);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const user = ELIPS.requireRole('karyawan');
        if (!user) return;

        const divisionLabel = ELIPS.divisionLabel(user.division) || 'Tanpa divisi';
        const initials = (user.name || 'K').substring(0, 2).toUpperCase();

        const nameEl = document.getElementById('employeeName');
        if (nameEl) nameEl.textContent = user.name;
        const avatarEl = document.getElementById('employeeAvatar');
        if (avatarEl) avatarEl.textContent = initials;
        const divisionEl = document.getElementById('employeeDivision');
        if (divisionEl) divisionEl.textContent = 'Karyawan • ' + divisionLabel;
        const greetingEl = document.getElementById('dashboardGreeting');
        if (greetingEl) {
            greetingEl.textContent = greetingForNow() + ', ' + user.name
                + '. Berikut ringkasan kehadiran Anda hari ini.';
        }
        const divisionStat = document.getElementById('statDivision');
        if (divisionStat) divisionStat.textContent = divisionLabel;

        render(user);

        window.addEventListener('storage', function (event) {
            if (!event.key || event.key === ELIPS.KEYS.session || event.key === ELIPS.KEYS.staffAttendances) {
                window.location.reload();
            }
        });
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) window.location.reload();
        });
    });
})(window.ELIPS);
