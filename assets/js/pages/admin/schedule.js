// Direktori: assets/js/pages/admin/schedule.js
// ELIPS LMS - skrip halaman admin/schedule.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
const MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const SESSION_STATUS_LABELS = {
    scheduled: 'Terjadwal',
    confirmed: 'Terkonfirmasi',
    completed: 'Selesai',
    rescheduled: 'Dijadwalkan Ulang',
    cancelled: 'Dibatalkan',
    no_show: 'Tidak Hadir'
};

const SESSION_STATUS_ORDER = ['scheduled', 'confirmed', 'completed', 'rescheduled', 'cancelled', 'no_show'];
const ALL_STATUS_VALUE = 'all';

let currentMonth = new Date();
currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
let selectedDate = dateKey(new Date());
let currentDetailId = null;
let sessionFormMode = 'create';
let sessionFormId = null;
let rescheduleSourceId = null;

function pad(value) {
    return String(value).padStart(2, '0');
}

function dateKey(date) {
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
}

function parseDateKey(value) {
    const parts = String(value || '').split('-');
    if (parts.length !== 3) return null;
    return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
}

function formatDate(value) {
    const date = parseDateKey(value);
    if (!date) return value || '-';
    return DAY_NAMES[date.getDay()] + ', ' + date.getDate() + ' ' + MONTH_NAMES[date.getMonth()] + ' ' + date.getFullYear();
}

function formatTime(start, end) {
    if (!start) return '-';
    return String(start).slice(0, 5) + (end ? ' - ' + String(end).slice(0, 5) : '');
}

function sessionStatusBadge(status) {
    const className = status === 'completed' ? 'badge-success'
        : (status === 'scheduled' || status === 'confirmed') ? 'badge-primary'
        : (status === 'cancelled' || status === 'no_show') ? 'badge-warning'
        : 'badge';
    return '<span class="badge ' + className + '">' + ELIPS.escapeHtml(SESSION_STATUS_LABELS[status] || status) + '</span>';
}

function sessionStatusColor(status) {
    if (status === 'completed') return 'completed';
    if (status === 'confirmed') return 'confirmed';
    if (status === 'cancelled' || status === 'no_show') return 'cancelled';
    return 'scheduled';
}

function getBranchId() {
    const value = document.getElementById('branchFilter').value;
    return value === 'all' || value === '' ? null : Number(value);
}

function getClassId() {
    const value = document.getElementById('classFilter').value;
    return value === 'all' || value === '' ? null : Number(value);
}

function getInstructorFilter() {
    const select = document.getElementById('instructorFilter');
    if (!select) return null;
    return select.value === 'all' || select.value === '' ? null : Number(select.value);
}

function getLocationFilter() {
    const select = document.getElementById('locationFilter');
    if (!select) return null;
    return select.value === 'all' || select.value === '' ? null : select.value;
}

function selectedStatuses() {
    const select = document.getElementById('statusFilter');
    if (!select) return [];
    return Array.from(select.options)
        .filter(function (option) { return option.selected; })
        .map(function (option) { return option.value; })
        .filter(function (value) { return value && value !== ALL_STATUS_VALUE; });
}

function setStatusFilter(statuses) {
    const select = document.getElementById('statusFilter');
    if (!select) return;
    const wanted = Array.isArray(statuses) ? statuses.filter(function (value) {
        return SESSION_STATUS_ORDER.indexOf(value) !== -1;
    }) : [];
    Array.from(select.options).forEach(function (option) {
        option.selected = wanted.length === 0
            ? option.value === ALL_STATUS_VALUE
            : wanted.indexOf(option.value) !== -1;
    });
    renderStatusChips();
}

function toggleStatusFilter(status) {
    if (status === ALL_STATUS_VALUE) {
        setStatusFilter([]);
        renderAllViews();
        return;
    }
    const current = selectedStatuses();
    const index = current.indexOf(status);
    if (index === -1) current.push(status);
    else current.splice(index, 1);
    setStatusFilter(current);
    renderAllViews();
}

function renderStatusChips() {
    const wrap = document.getElementById('statusFilterChips');
    if (!wrap) return;
    const selected = selectedStatuses();
    const values = [ALL_STATUS_VALUE].concat(SESSION_STATUS_ORDER);
    wrap.innerHTML = values.map(function (status) {
        const active = status === ALL_STATUS_VALUE
            ? selected.length === 0
            : selected.indexOf(status) !== -1;
        const label = status === ALL_STATUS_VALUE ? 'Semua' : SESSION_STATUS_LABELS[status];
        return '<button type="button" class="status-chip' + (active ? ' active' : '') + '"' +
            ' aria-pressed="' + (active ? 'true' : 'false') + '"' +
            ' onclick="toggleStatusFilter(\'' + status + '\')">' +
            ELIPS.escapeHtml(label) + '</button>';
    }).join('');
}

function optionExists(selectId, value) {
    const select = document.getElementById(selectId);
    if (!select) return false;
    return Array.from(select.options).some(function (option) {
        return option.value === String(value);
    });
}

function branchClasses() {
    const branchId = getBranchId();
    return ELIPS.getClasses().filter(function (item) {
        if (branchId !== null && Number(item.branchId) !== branchId) return false;
        if (item.status === 'archived' || item.status === 'cancelled') return false;
        return true;
    });
}

function contextClasses() {
    const classId = getClassId();
    return branchClasses().filter(function (item) {
        return classId === null || Number(item.id) === classId;
    });
}

function contextSessions() {
    const classIds = {};
    contextClasses().forEach(function (item) { classIds[item.id] = true; });
    return ELIPS.getClassSessions().filter(function (session) {
        return !!classIds[Number(session.classId)];
    });
}

function sessionMatchesInstructor(session) {
    const instructorId = getInstructorFilter();
    if (instructorId === null) return true;
    return Number(session.instructorId) === instructorId;
}

function sessionMatchesLocation(session) {
    const location = getLocationFilter();
    if (location === null) return true;
    const value = String(session.location || '').trim();
    if (location === '__none__') return value === '';
    return value === location;
}

function sessionMatchesStatus(session) {
    const statuses = selectedStatuses();
    return statuses.length === 0 || statuses.indexOf(session.status) !== -1;
}

function sessionsInContext() {
    return contextSessions().filter(function (session) {
        return sessionMatchesInstructor(session) &&
            sessionMatchesLocation(session) &&
            sessionMatchesStatus(session);
    });
}

function sessionsForMonth() {
    const year = currentMonth.getFullYear();
    const month = currentMonth.getMonth();
    return sessionsInContext().filter(function (session) {
        const date = parseDateKey(session.sessionDate);
        return date && date.getFullYear() === year && date.getMonth() === month;
    }).sort(function (a, b) {
        const dateCompare = String(a.sessionDate || '').localeCompare(String(b.sessionDate || ''));
        if (dateCompare !== 0) return dateCompare;
        return String(a.startTime || '').localeCompare(String(b.startTime || ''));
    });
}

function changeBranch() {
    fillClassFilter();
    fillInstructorFilter(null);
    fillLocationFilter(null);
    renderAllViews();
}

function changeClass() {
    fillInstructorFilter(null);
    fillLocationFilter(null);
    renderAllViews();
}

function resetScheduleFilters() {
    document.getElementById('branchFilter').value = 'all';
    fillClassFilter();
    fillInstructorFilter(null);
    fillLocationFilter(null);
    setStatusFilter([]);
    renderAllViews();
}

function fillBranchFilter() {
    const select = document.getElementById('branchFilter');
    const branches = ELIPS.getBranches().slice().sort(function (a, b) {
        return String(a.name || '').localeCompare(String(b.name || ''));
    });
    select.innerHTML = '<option value="all">Semua Cabang</option>' + branches.map(function (branch) {
        return '<option value="' + branch.id + '">' + ELIPS.escapeHtml(branch.name) + '</option>';
    }).join('');
    select.value = 'all';
}

function fillClassFilter(preferred) {
    const select = document.getElementById('classFilter');
    const classes = branchClasses().slice().sort(function (a, b) {
        return String(a.name || '').localeCompare(String(b.name || ''));
    });
    select.innerHTML = '<option value="all">Semua Kelas</option>' + classes.map(function (item) {
        return '<option value="' + item.id + '">' + ELIPS.escapeHtml(item.name) + '</option>';
    }).join('');
    select.value = preferred !== undefined && optionExists('classFilter', preferred)
        ? String(preferred)
        : 'all';
}

function fillInstructorFilter(preferred) {
    const select = document.getElementById('instructorFilter');
    const current = preferred !== undefined ? preferred : select.value;
    const ids = {};
    contextClasses().forEach(function (item) {
        if (item.instructorId) ids[item.instructorId] = true;
    });
    contextSessions().forEach(function (session) {
        if (session.instructorId) ids[session.instructorId] = true;
    });
    const instructors = Object.keys(ids).map(function (id) {
        return ELIPS.getUserById(id);
    }).filter(Boolean).sort(function (a, b) {
        return String(a.name || '').localeCompare(String(b.name || ''));
    });
    select.innerHTML = '<option value="all">Semua Tentor</option>' + instructors.map(function (user) {
        return '<option value="' + user.id + '">' + ELIPS.escapeHtml(user.name) + '</option>';
    }).join('');
    select.value = current && optionExists('instructorFilter', current) ? String(current) : 'all';
}

function fillLocationFilter(preferred) {
    const select = document.getElementById('locationFilter');
    const current = preferred !== undefined ? preferred : select.value;
    const values = {};
    contextSessions().forEach(function (session) {
        values[String(session.location || '').trim()] = true;
    });
    const locations = Object.keys(values).sort(function (a, b) {
        if (a === '') return 1;
        if (b === '') return -1;
        return a.localeCompare(b);
    });
    select.innerHTML = '<option value="all">Semua Lokasi</option>' + locations.map(function (location) {
        if (location === '') return '<option value="__none__">Tanpa lokasi</option>';
        return '<option value="' + ELIPS.escapeHtml(location) + '">' + ELIPS.escapeHtml(location) + '</option>';
    }).join('');
    select.value = current && optionExists('locationFilter', current) ? String(current) : 'all';
}

function renderBranchInfo() {
    const branchId = getBranchId();
    const classes = branchClasses();
    const branch = branchId !== null ? ELIPS.getBranchById(branchId) : null;
    if (branch) {
        document.getElementById('branchInfo').innerHTML =
            '<div class="schedule-branch-name">' + ELIPS.escapeHtml(branch.name) + '</div>' +
            '<div class="schedule-branch-meta">' +
            '<span><i class="bi bi-geo-alt"></i> ' + ELIPS.escapeHtml(branch.city || 'Kota belum diisi') + '</span>' +
            '<span><i class="bi bi-person-badge"></i> ' + ELIPS.escapeHtml(branch.manager || 'Manajer belum diisi') + '</span>' +
            '<span><i class="bi bi-collection"></i> ' + classes.length + ' kelas</span>' +
            '</div>';
    } else {
        const activeBranches = ELIPS.getBranches().filter(function (item) { return item.status === 'active'; }).length;
        document.getElementById('branchInfo').innerHTML =
            '<div class="schedule-branch-name">Semua Cabang</div>' +
            '<div class="schedule-branch-meta">' +
            '<span><i class="bi bi-building"></i> ' + activeBranches + ' cabang aktif</span>' +
            '<span><i class="bi bi-collection"></i> ' + classes.length + ' kelas</span>' +
            '</div>';
    }
}

function renderStats() {
    const monthSessions = sessionsForMonth();
    document.getElementById('statMonthSessions').textContent = monthSessions.length;
    document.getElementById('statCompleted').textContent = monthSessions.filter(function (s) { return s.status === 'completed'; }).length;
    document.getElementById('statUpcoming').textContent = monthSessions.filter(function (s) {
        return s.status === 'scheduled' || s.status === 'confirmed';
    }).length;
    document.getElementById('statClasses').textContent = contextClasses().filter(function (item) {
        return item.status === 'ready' || item.status === 'ongoing' || item.status === 'open';
    }).length;
}

function renderCalendar() {
    const year = currentMonth.getFullYear();
    const month = currentMonth.getMonth();
    document.getElementById('monthLabel').textContent = MONTH_NAMES[month] + ' ' + year;

    const firstDay = new Date(year, month, 1);
    const offset = (firstDay.getDay() + 6) % 7;
    const gridStart = new Date(year, month, 1 - offset);
    const sessions = sessionsInContext();
    const byDate = {};
    sessions.forEach(function (session) {
        const key = session.sessionDate;
        if (!key) return;
        if (!byDate[key]) byDate[key] = [];
        byDate[key].push(session);
    });
    Object.keys(byDate).forEach(function (key) {
        byDate[key].sort(function (a, b) {
            return String(a.startTime || '').localeCompare(String(b.startTime || ''));
        });
    });

    const todayKey = dateKey(new Date());
    const cells = [];
    for (let index = 0; index < 42; index++) {
        const date = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + index);
        const key = dateKey(date);
        const daySessions = (byDate[key] || []).slice();
        const chips = daySessions.slice(0, 3).map(function (session) {
            return '<button type="button" class="calendar-chip chip-' + sessionStatusColor(session.status) + '" onclick="event.stopPropagation(); openSessionModal(' + session.id + ')" title="' + ELIPS.escapeHtml(session.className + ' — ' + (SESSION_STATUS_LABELS[session.status] || session.status)) + '">' +
                '<span class="calendar-chip-time">' + ELIPS.escapeHtml(String(session.startTime || '').slice(0, 5)) + '</span>' +
                '<span class="calendar-chip-label">' + ELIPS.escapeHtml(session.classCode || session.className || '') + '</span>' +
                '</button>';
        }).join('');
        const more = daySessions.length > 3 ? '<div class="calendar-more">+' + (daySessions.length - 3) + ' sesi lain</div>' : '';
        const classes = ['calendar-day'];
        if (date.getMonth() !== month) classes.push('outside');
        if (key === todayKey) classes.push('today');
        if (key === selectedDate) classes.push('selected');
        cells.push(
            '<div class="' + classes.join(' ') + '" onclick="selectDay(\'' + key + '\')">' +
            '<div class="calendar-day-header"><span class="calendar-day-number">' + date.getDate() + '</span>' +
            '<span class="calendar-day-tools">' +
            (daySessions.length ? '<span class="calendar-day-count">' + daySessions.length + '</span>' : '') +
            '<button type="button" class="calendar-add" title="Tambah sesi pada tanggal ini" onclick="event.stopPropagation(); openSessionForm(null, \'' + key + '\')"><i class="bi bi-plus"></i></button>' +
            '</span></div>' + chips + more + '</div>'
        );
    }
    document.getElementById('calendarGrid').innerHTML = cells.join('');
}

function selectDay(key) {
    selectedDate = key;
    const date = parseDateKey(key);
    if (date && date.getMonth() !== currentMonth.getMonth()) {
        currentMonth = new Date(date.getFullYear(), date.getMonth(), 1);
    }
    renderAllViews();
}

function sessionsOnSelectedDate() {
    return sessionsInContext().filter(function (session) {
        return session.sessionDate === selectedDate;
    }).sort(function (a, b) {
        return String(a.startTime || '').localeCompare(String(b.startTime || ''));
    });
}

function renderDayPanel() {
    const sessions = sessionsOnSelectedDate();
    document.getElementById('selectedDateLabel').textContent = '— ' + formatDate(selectedDate);
    const panel = document.getElementById('dayPanel');
    if (sessions.length === 0) {
        panel.innerHTML = '<div class="schedule-empty"><i class="bi bi-calendar-x"></i>' +
            '<p>Tidak ada sesi pada tanggal ini.</p>' +
            '<button type="button" class="btn btn-primary" onclick="openSessionForm(null, \'' + selectedDate + '\')">' +
            '<i class="bi bi-plus-lg"></i> Tambah Sesi</button></div>';
        return;
    }
    panel.innerHTML = sessions.map(function (session) {
        const classItem = ELIPS.getClassById(session.classId);
        return '<div class="agenda-card" onclick="openSessionModal(' + session.id + ')">' +
            '<div class="agenda-time">' + ELIPS.escapeHtml(formatTime(session.startTime, session.endTime)) + '</div>' +
            '<div class="agenda-main">' +
            '<div class="agenda-title">' + ELIPS.escapeHtml(session.title || ('Pertemuan ' + session.sessionNumber)) + '</div>' +
            '<div class="agenda-meta">' + ELIPS.escapeHtml((classItem ? classItem.name : session.className || '') + (session.branchName ? ' • ' + session.branchName : '')) + '</div>' +
            '<div class="agenda-meta">' + ELIPS.escapeHtml(session.moduleTitle || 'Modul belum ditentukan') + ' • ' + ELIPS.escapeHtml(session.instructorName || 'Tentor belum ditetapkan') + '</div>' +
            '</div>' +
            '<div class="agenda-status">' + sessionStatusBadge(session.status) + '</div>' +
            '</div>';
    }).join('');
}

function renderMonthSummary() {
    const sessions = sessionsForMonth();
    document.getElementById('monthSummaryLabel').textContent = '— ' + MONTH_NAMES[currentMonth.getMonth()] + ' ' + currentMonth.getFullYear();
    const counts = {
        scheduled: 0, confirmed: 0, completed: 0, rescheduled: 0, cancelled: 0, no_show: 0
    };
    let plannedJp = 0;
    let actualJp = 0;
    sessions.forEach(function (session) {
        if (counts[session.status] !== undefined) counts[session.status]++;
        if (['cancelled', 'no_show', 'rescheduled'].indexOf(session.status) === -1) {
            plannedJp += Number(session.plannedJp) || 0;
            actualJp += Number(session.actualJp) || 0;
        }
    });
    const rows = Object.keys(counts).map(function (status) {
        return '<div class="summary-row"><span>' + ELIPS.escapeHtml(SESSION_STATUS_LABELS[status]) + '</span><strong>' + counts[status] + '</strong></div>';
    }).join('');
    document.getElementById('monthSummary').innerHTML =
        '<div class="summary-list">' + rows + '</div>' +
        '<div class="summary-total"><span>Total JP rencana</span><strong>' + plannedJp + '</strong></div>' +
        '<div class="summary-total"><span>Total JP aktual</span><strong>' + actualJp + '</strong></div>';
}

function renderScheduleTable() {
    const tbody = document.getElementById('scheduleTable');
    const sessions = sessionsForMonth();

    if (sessions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; color: var(--text-secondary);">Belum ada sesi pada bulan ini.</td></tr>';
        return;
    }

    tbody.innerHTML = sessions.map(function (session) {
        const present = (Number(session.presentCount) || 0) + (Number(session.lateCount) || 0);
        const attended = present + ' / ' + (Number(session.attendanceCount) || 0);
        return '<tr>' +
            '<td><div class="table-course-title" style="font-size: 0.875rem;">' + ELIPS.escapeHtml(formatDate(session.sessionDate)) + '</div><div class="table-course-meta">Pertemuan ' + (Number(session.sessionNumber) || '-') + '</div></td>' +
            '<td>' + ELIPS.escapeHtml(formatTime(session.startTime, session.endTime)) + '</td>' +
            '<td><div class="table-course-title" style="font-size: 0.875rem;">' + ELIPS.escapeHtml(session.className || '-') + '</div><div class="table-course-meta">' + ELIPS.escapeHtml(session.classCode || '') + '</div></td>' +
            '<td>' + ELIPS.escapeHtml(session.branchName || 'Tanpa cabang') + '</td>' +
            '<td>' + ELIPS.escapeHtml(session.moduleTitle || '-') + '</td>' +
            '<td>' + ELIPS.escapeHtml(session.instructorName || '-') + '</td>' +
            '<td>' + (Number(session.plannedJp) || 0) + ' / ' + (Number(session.actualJp) || 0) + '</td>' +
            '<td>' + attended + '</td>' +
            '<td>' + sessionStatusBadge(session.status) + '</td>' +
            '<td style="white-space: nowrap;">' +
            '<button class="btn btn-ghost" style="padding: 0.375rem 0.5rem; font-size: 0.8125rem;" title="Lihat detail" onclick="openSessionModal(' + session.id + ')"><i class="bi bi-eye"></i></button>' +
            '<button class="btn btn-ghost" style="padding: 0.375rem 0.5rem; font-size: 0.8125rem;" title="Edit sesi" onclick="openSessionForm(' + session.id + ')"><i class="bi bi-pencil"></i></button>' +
            '</td>' +
            '</tr>';
    }).join('');
}

function renderAllViews() {
    renderBranchInfo();
    renderStatusChips();
    renderStats();
    renderCalendar();
    renderDayPanel();
    renderMonthSummary();
    renderScheduleTable();
    syncUrlState();
}

function shiftMonth(delta) {
    currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + delta, 1);
    selectedDate = dateKey(currentMonth);
    renderAllViews();
}

function goToday() {
    const today = new Date();
    currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    selectedDate = dateKey(today);
    renderAllViews();
}

function openSessionModal(id) {
    const session = ELIPS.getClassSessionById(id);
    if (!session) return;
    currentDetailId = Number(session.id);
    const classItem = ELIPS.getClassById(session.classId);
    const attendanceRows = [
        ['Hadir', session.presentCount, 'badge-success'],
        ['Terlambat', session.lateCount, 'badge-warning'],
        ['Izin', session.permissionCount, 'badge-warning'],
        ['Sakit', session.sickCount, 'badge-warning'],
        ['Alpa', session.absentCount, 'badge'],
        ['Dispensasi', session.excusedCount, 'badge']
    ].map(function (row) {
        return '<div class="detail-stat"><span>' + row[0] + '</span><strong>' + (Number(row[1]) || 0) + '</strong></div>';
    }).join('');

    document.getElementById('sessionModalTitle').textContent = session.title || ('Pertemuan ' + session.sessionNumber);
    document.getElementById('sessionModalBody').innerHTML =
        '<div class="detail-grid">' +
        '<div class="detail-item"><span>Kelas</span><strong>' + ELIPS.escapeHtml(classItem ? classItem.name : session.className || '-') + '</strong></div>' +
        '<div class="detail-item"><span>Kode Kelas</span><strong>' + ELIPS.escapeHtml(session.classCode || '-') + '</strong></div>' +
        '<div class="detail-item"><span>Nomor Pertemuan</span><strong>' + (Number(session.sessionNumber) || '-') + '</strong></div>' +
        '<div class="detail-item"><span>Cabang</span><strong>' + ELIPS.escapeHtml(session.branchName || 'Tanpa cabang') + (session.branchCity ? ' — ' + ELIPS.escapeHtml(session.branchCity) : '') + '</strong></div>' +
        '<div class="detail-item"><span>Kursus</span><strong>' + ELIPS.escapeHtml(session.courseTitle || '-') + '</strong></div>' +
        '<div class="detail-item"><span>Tanggal</span><strong>' + ELIPS.escapeHtml(formatDate(session.sessionDate)) + '</strong></div>' +
        '<div class="detail-item"><span>Waktu</span><strong>' + ELIPS.escapeHtml(formatTime(session.startTime, session.endTime)) + '</strong></div>' +
        '<div class="detail-item"><span>Modul</span><strong>' + ELIPS.escapeHtml(session.moduleTitle || '-') + '</strong></div>' +
        '<div class="detail-item"><span>Tentor</span><strong>' + ELIPS.escapeHtml(session.instructorName || '-') + '</strong></div>' +
        '<div class="detail-item"><span>JP Rencana / Aktual</span><strong>' + (Number(session.plannedJp) || 0) + ' / ' + (Number(session.actualJp) || 0) + '</strong></div>' +
        '<div class="detail-item"><span>Status</span><strong>' + sessionStatusBadge(session.status) + '</strong></div>' +
        '<div class="detail-item"><span>Lokasi</span><strong>' + ELIPS.escapeHtml((session.location || '-') + (session.room ? ' • ' + session.room : '')) + '</strong></div>' +
        '<div class="detail-item"><span>Presensi tercatat</span><strong>' + (Number(session.attendanceCount) || 0) + ' peserta</strong></div>' +
        '</div>' +
        '<div class="detail-attendance">' + attendanceRows + '</div>' +
        (session.notes ? '<div class="detail-note"><strong>Catatan</strong><p>' + ELIPS.escapeHtml(session.notes) + '</p></div>' : '');
    renderSessionModalActions(session);
    document.getElementById('sessionModal').style.display = 'flex';
}

function renderSessionModalActions(session) {
    const buttons = [];
    buttons.push('<button type="button" class="btn btn-ghost" onclick="openSessionForm(' + session.id + ')"><i class="bi bi-pencil"></i> Edit</button>');
    if (session.status !== 'completed') {
        buttons.push('<button type="button" class="btn btn-outline" onclick="openRescheduleForm(' + session.id + ')"><i class="bi bi-arrow-repeat"></i> Jadwalkan Ulang</button>');
    }
    if (session.status !== 'completed' && session.status !== 'cancelled') {
        buttons.push('<button type="button" class="btn btn-outline" onclick="cancelSessionFromDetail()"><i class="bi bi-x-circle"></i> Batalkan</button>');
    }
    if (session.status !== 'completed') {
        buttons.push('<button type="button" class="btn btn-outline" onclick="completeSessionFromDetail()"><i class="bi bi-check2-circle"></i> Tandai Selesai</button>');
    }
    if (session.status !== 'completed' && (Number(session.attendanceCount) || 0) === 0) {
        buttons.push('<button type="button" class="btn btn-danger" onclick="deleteSessionFromDetail()"><i class="bi bi-trash"></i> Hapus</button>');
    }
    document.getElementById('sessionModalActions').innerHTML = buttons.join('');
}

function closeSessionModal() {
    document.getElementById('sessionModal').style.display = 'none';
    currentDetailId = null;
}

// ---------- Form sesi (buat / edit / jadwalkan ulang) ----------

function openSessionForm(id, presetDate) {
    sessionFormMode = id ? 'edit' : 'create';
    sessionFormId = id ? Number(id) : null;
    rescheduleSourceId = null;
    const item = id ? ELIPS.getClassSessionById(id) : null;
    if (id && !item) {
        alert('Sesi tidak ditemukan.');
        return;
    }
    closeSessionModal();
    showSessionForm(item, presetDate || null);
}

function openRescheduleForm(id) {
    const item = ELIPS.getClassSessionById(id);
    if (!item) {
        alert('Sesi tidak ditemukan.');
        return;
    }
    if (item.status === 'completed') {
        alert('Sesi yang sudah selesai tidak dapat dijadwalkan ulang.');
        return;
    }
    sessionFormMode = 'reschedule';
    sessionFormId = null;
    rescheduleSourceId = Number(item.id);
    closeSessionModal();
    showSessionForm(item, item.sessionDate);
}

function showSessionForm(item, presetDate) {
    const isEdit = sessionFormMode === 'edit';
    const isReschedule = sessionFormMode === 'reschedule';
    const source = isEdit || isReschedule ? item : null;
    let classId = source ? Number(source.classId) : (getClassId() || '');
    if (!classId) {
        const classes = branchClasses();
        if (classes.length === 1) classId = Number(classes[0].id);
    }
    const classItem = classId ? ELIPS.getClassById(classId) : null;

    document.getElementById('sessionFormTitle').textContent = isEdit
        ? 'Edit Sesi Pertemuan'
        : (isReschedule ? 'Jadwalkan Ulang Sesi' : 'Buat Sesi Pertemuan');
    document.getElementById('sessionFormId').value = isEdit ? sessionFormId : '';
    fillFormClassSelect(classId);
    document.getElementById('sessionFormClass').disabled = isEdit || isReschedule;
    document.getElementById('sessionFormNumber').value = isEdit ? (source.sessionNumber || '') : '';
    document.getElementById('sessionFormDate').value = source
        ? (source.sessionDate || '')
        : (presetDate || selectedDate || dateKey(new Date()));
    document.getElementById('sessionFormTitleInput').value = isEdit ? (source.title || '') : '';
    fillFormModuleSelect(classId, isEdit ? source.moduleId : null);
    document.getElementById('sessionFormStart').value = source ? (source.startTime || '09:00') : '09:00';
    document.getElementById('sessionFormEnd').value = source ? (source.endTime || '11:00') : '11:00';
    document.getElementById('sessionFormLocation').value = source
        ? (source.location || '')
        : (classItem ? (classItem.location || '') : '');
    document.getElementById('sessionFormRoom').value = source
        ? (source.room || '')
        : (classItem ? (classItem.room || '') : '');
    fillFormInstructorSelect(source ? source.instructorId : (classItem ? classItem.instructorId : null));
    document.getElementById('sessionFormPlannedJp').value = source
        ? (Number(source.plannedJp) || 0)
        : (classItem && Number(classItem.plannedJp) > 0 ? Number(classItem.plannedJp) : 4);
    document.getElementById('sessionFormActualJp').value = source ? (Number(source.actualJp) || 0) : 0;

    const statusSelect = document.getElementById('sessionFormStatus');
    statusSelect.innerHTML = SESSION_STATUS_ORDER.map(function (status) {
        return '<option value="' + status + '">' + ELIPS.escapeHtml(SESSION_STATUS_LABELS[status]) + '</option>';
    }).join('');
    statusSelect.value = isEdit ? source.status : 'scheduled';
    document.getElementById('sessionFormNotes').value = isEdit ? (source.notes || '') : '';

    const notice = document.getElementById('sessionFormNotice');
    if (isReschedule) {
        notice.style.display = '';
        notice.innerHTML = '<i class="bi bi-info-circle"></i> Sesi asal <strong>' +
            ELIPS.escapeHtml(source.title || ('Pertemuan ' + source.sessionNumber)) + '</strong> (' +
            ELIPS.escapeHtml(formatDate(source.sessionDate)) +
            ') akan ditandai <em>Dijadwalkan Ulang</em> dan sesi baru dibuat pada tanggal yang Anda pilih.';
    } else {
        notice.style.display = 'none';
        notice.innerHTML = '';
    }

    document.getElementById('sessionFormSubmit').textContent = isEdit
        ? 'Simpan Perubahan'
        : (isReschedule ? 'Jadwalkan Ulang' : 'Buat Sesi');
    document.getElementById('sessionFormModal').style.display = 'flex';
}

function closeSessionForm() {
    document.getElementById('sessionFormModal').style.display = 'none';
    document.getElementById('sessionFormClass').disabled = false;
}

function fillFormClassSelect(selectedId) {
    const select = document.getElementById('sessionFormClass');
    const classes = branchClasses().slice().sort(function (a, b) {
        return String(a.name || '').localeCompare(String(b.name || ''));
    });
    select.innerHTML = '<option value="">-- Pilih Kelas --</option>' + classes.map(function (item) {
        return '<option value="' + item.id + '">' + ELIPS.escapeHtml(item.name + ' (' + item.code + ')') + '</option>';
    }).join('');
    if (selectedId) select.value = String(selectedId);
}

function fillFormModuleSelect(classId, selectedId) {
    const select = document.getElementById('sessionFormModule');
    const classItem = ELIPS.getClassById(classId);
    if (!classItem) {
        select.innerHTML = '<option value="">-- Tanpa modul --</option>';
        return;
    }
    const modules = ELIPS.getModules().filter(function (module) {
        return Number(module.curriculumVersionId) === Number(classItem.curriculumVersionId);
    }).sort(function (a, b) {
        return (Number(a.order) || 0) - (Number(b.order) || 0);
    });
    select.innerHTML = '<option value="">-- Tanpa modul --</option>' + modules.map(function (module) {
        return '<option value="' + module.id + '">' + ELIPS.escapeHtml(module.title) + '</option>';
    }).join('');
    if (selectedId) select.value = String(selectedId);
}

function fillFormInstructorSelect(selectedId) {
    const select = document.getElementById('sessionFormInstructor');
    const instructors = ELIPS.getUsersByRole('instruktur').filter(function (user) {
        return user.status === 'aktif';
    });
    select.innerHTML = '<option value="">-- Belum ditetapkan --</option>' + instructors.map(function (user) {
        return '<option value="' + user.id + '">' + ELIPS.escapeHtml(user.name) + '</option>';
    }).join('');
    if (selectedId) select.value = String(selectedId);
}

function refreshFormModules() {
    const classId = document.getElementById('sessionFormClass').value;
    fillFormModuleSelect(classId, null);
    if (sessionFormMode !== 'create') return;
    const classItem = ELIPS.getClassById(classId);
    if (!classItem) return;
    document.getElementById('sessionFormLocation').value = classItem.location || '';
    document.getElementById('sessionFormRoom').value = classItem.room || '';
    fillFormInstructorSelect(classItem.instructorId);
    if (Number(classItem.plannedJp) > 0) {
        document.getElementById('sessionFormPlannedJp').value = Number(classItem.plannedJp);
    }
}

function saveSessionForm() {
    const classId = Number(document.getElementById('sessionFormClass').value);
    if (!classId) {
        alert('Pilih kelas terlebih dahulu.');
        return;
    }
    const data = {
        classId: classId,
        sessionNumber: Number(document.getElementById('sessionFormNumber').value) || 0,
        title: document.getElementById('sessionFormTitleInput').value.trim(),
        moduleId: document.getElementById('sessionFormModule').value || null,
        sessionDate: document.getElementById('sessionFormDate').value,
        startTime: document.getElementById('sessionFormStart').value,
        endTime: document.getElementById('sessionFormEnd').value,
        location: document.getElementById('sessionFormLocation').value.trim(),
        room: document.getElementById('sessionFormRoom').value.trim(),
        instructorId: document.getElementById('sessionFormInstructor').value || null,
        plannedJp: document.getElementById('sessionFormPlannedJp').value,
        actualJp: document.getElementById('sessionFormActualJp').value,
        status: document.getElementById('sessionFormStatus').value,
        notes: document.getElementById('sessionFormNotes').value.trim()
    };

    let result;
    if (sessionFormMode === 'edit') {
        result = ELIPS.updateSession(sessionFormId, data);
    } else if (sessionFormMode === 'reschedule') {
        result = ELIPS.createSession(Object.assign({}, data, {
            rescheduledFromId: rescheduleSourceId
        }));
    } else {
        result = ELIPS.createSession(data);
    }
    if (!result.ok) {
        alert(result.message);
        return;
    }

    ELIPS.toast(result.message);
    closeSessionForm();
    ensureSessionVisible(classId);
    if (!sessionMatchesStatus({ status: data.status })) setStatusFilter([]);
    renderAllViews();
    const savedId = result.data && result.data.id ? Number(result.data.id) : sessionFormId;
    if (savedId) openSessionModal(savedId);
}

function ensureSessionVisible(classId) {
    const classItem = ELIPS.getClassById(classId);
    if (!classItem) return;
    const branchSelect = document.getElementById('branchFilter');
    const branchValue = classItem.branchId ? String(classItem.branchId) : 'all';
    if (branchSelect.value !== branchValue && optionExists('branchFilter', branchValue)) {
        branchSelect.value = branchValue;
        fillClassFilter();
    }
    if (optionExists('classFilter', classId)) {
        document.getElementById('classFilter').value = String(classId);
    }
    fillInstructorFilter(null);
    fillLocationFilter(null);
}

function cancelSessionFromDetail() {
    const id = currentDetailId;
    if (!id) return;
    if (!confirm('Batalkan sesi ini? Sesi batal tidak dihitung dalam JP aktual dan rekap wajib.')) return;
    const reason = window.prompt('Alasan pembatalan (opsional):') || '';
    const result = ELIPS.setSessionStatus(id, 'cancelled', { reason: reason });
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    renderAllViews();
    openSessionModal(id);
}

function completeSessionFromDetail() {
    const id = currentDetailId;
    if (!id) return;
    if (!confirm('Tandai sesi ini selesai? JP aktual diisi otomatis bila masih kosong.')) return;
    const result = ELIPS.setSessionStatus(id, 'completed');
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    renderAllViews();
    openSessionModal(id);
}

function deleteSessionFromDetail() {
    const id = currentDetailId;
    if (!id) return;
    if (!confirm('Hapus sesi ini? Sesi yang sudah berlangsung atau memiliki presensi tidak dapat dihapus.')) return;
    const result = ELIPS.deleteSession(id);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    closeSessionModal();
    renderAllViews();
}

// ---------- Filter di URL ----------

function readUrlState() {
    const params = new URLSearchParams(window.location.search);
    return {
        branch: params.get('branch') || 'all',
        classId: params.get('class') || 'all',
        instructor: params.get('instructor') || 'all',
        location: params.get('location') || 'all',
        statuses: (params.get('status') || '').split(',').filter(Boolean),
        month: params.get('month'),
        date: params.get('date')
    };
}

function applyUrlState() {
    const state = readUrlState();
    if (state.branch && optionExists('branchFilter', state.branch)) {
        document.getElementById('branchFilter').value = state.branch;
    }
    fillClassFilter(state.classId);
    fillInstructorFilter(state.instructor);
    fillLocationFilter(state.location);
    setStatusFilter(state.statuses);
    if (state.month && /^\d{4}-\d{2}$/.test(state.month)) {
        const parts = state.month.split('-');
        const year = Number(parts[0]);
        const month = Number(parts[1]) - 1;
        if (month >= 0 && month <= 11) currentMonth = new Date(year, month, 1);
    }
    if (state.date && /^\d{4}-\d{2}-\d{2}$/.test(state.date)) {
        const date = parseDateKey(state.date);
        if (date) {
            selectedDate = state.date;
            currentMonth = new Date(date.getFullYear(), date.getMonth(), 1);
        }
    }
}

function syncUrlState() {
    const params = new URLSearchParams();
    const branchId = getBranchId();
    if (branchId !== null) params.set('branch', String(branchId));
    const classId = getClassId();
    if (classId !== null) params.set('class', String(classId));
    const instructorSelect = document.getElementById('instructorFilter');
    if (instructorSelect && instructorSelect.value !== 'all') {
        params.set('instructor', instructorSelect.value);
    }
    const locationSelect = document.getElementById('locationFilter');
    if (locationSelect && locationSelect.value !== 'all') {
        params.set('location', locationSelect.value);
    }
    const statuses = selectedStatuses();
    if (statuses.length) params.set('status', statuses.join(','));
    params.set('month', currentMonth.getFullYear() + '-' + pad(currentMonth.getMonth() + 1));
    if (selectedDate) params.set('date', selectedDate);
    const query = params.toString();
    try {
        window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
    } catch (error) {
        // Mode file:// dapat menolak perubahan URL; filter tetap berjalan di halaman.
    }
}

document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;
    fillBranchFilter();
    applyUrlState();
    renderAllViews();
});