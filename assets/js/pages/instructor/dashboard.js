// Direktori: assets/js/pages/instructor/dashboard.js
// ELIPS LMS - skrip halaman instructor/dashboard.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const instructor = ELIPS.requireRole('instruktur');
    if (!instructor) return;

    const name = instructor.name || 'Tentor';
    document.getElementById('instructorName').textContent = name;
    document.getElementById('instructorAvatar').textContent = name.substring(0, 2).toUpperCase();
    document.getElementById('instructorGreeting').textContent = 'Selamat datang, ' + name + '! Berikut ringkasan kursus dan peserta Anda.';

    const summary = ELIPS.getInstructorSummary(instructor.id);
    const rows = ELIPS.getInstructorStudentRows(instructor.id);

    document.getElementById('statCourses').textContent = summary.totalCourses;
    document.getElementById('statStudents').textContent = summary.totalStudents;
    document.getElementById('statLessons').textContent = summary.totalLessons;
    document.getElementById('statCertificates').textContent = summary.publishedCertificates;

    instructorSessionUserId = Number(instructor.id);
    renderCourses(summary);
    renderInstructorClasses(instructor.id);
    renderInstructorSessionFilters();
    renderInstructorSessions();
    renderRecentStudents(rows);
    renderNotice(summary);
});

function renderInstructorClasses(instructorId) {
    const tbody = document.getElementById('instructorClassesBody');
    const classes = ELIPS.getClassesByInstructor(instructorId).slice().sort(function (a, b) {
        return Number(b.id) - Number(a.id);
    });

    if (classes.length === 0) {
        tbody.innerHTML = [
            '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-secondary);">',
            '  <i class="bi bi-people" style="display: block; font-size: 2rem; opacity: .45; margin-bottom: .5rem;"></i>',
            '  Belum ada kelas yang ditugaskan kepada Anda.',
            '</td></tr>'
        ].join('');
        return;
    }

    tbody.innerHTML = classes.map(function (item) {
        const activeCount = ELIPS.getClassEnrollments(item.id).filter(function (enrollment) {
            return enrollment.status === 'active' || enrollment.status === 'pending';
        }).length;
        const quota = Number(item.quota) || 0;
        return '<tr>' +
            '<td><div class="table-course-title">' + ELIPS.escapeHtml(item.name) + '</div>' +
            '<div class="table-course-meta">' + ELIPS.escapeHtml(item.code) + '</div></td>' +
            '<td><span>' + ELIPS.escapeHtml(item.courseTitle || '-') + '</span></td>' +
            '<td><span class="badge badge-primary">' + ELIPS.escapeHtml(item.curriculumVersionCode || '-') + '</span></td>' +
            '<td><span style="color: var(--text-secondary);">' + ELIPS.escapeHtml(item.periodStart || '-') + ' s.d. ' + ELIPS.escapeHtml(item.periodEnd || '-') + '</span></td>' +
            '<td><span>' + (quota === 0 ? activeCount + ' peserta' : activeCount + ' / ' + quota) + '</span></td>' +
            '<td><span class="badge ' + (item.status === 'ongoing' || item.status === 'ready' ? 'badge-success' : 'badge-warning') + '">' + ELIPS.escapeHtml(item.status) + '</span></td>' +
            '</tr>';
    }).join('');
}

// ---------- Jadwal & presensi ----------
let instructorSessionUserId = null;
let editingSessionId = null;

const SESSION_STATUSES = ['scheduled', 'confirmed', 'completed', 'rescheduled', 'cancelled', 'no_show'];
const SESSION_STATUS_LABELS = {
    scheduled: 'Terjadwal',
    confirmed: 'Terkonfirmasi',
    completed: 'Selesai',
    rescheduled: 'Dijadwalkan Ulang',
    cancelled: 'Dibatalkan',
    no_show: 'Tidak Hadir'
};
const ATTENDANCE_STATUSES = ['present', 'late', 'permission', 'sick', 'absent', 'excused'];
const ATTENDANCE_STATUS_LABELS = {
    present: 'Hadir',
    late: 'Terlambat',
    permission: 'Izin',
    sick: 'Sakit',
    absent: 'Alpa',
    excused: 'Dispensasi'
};

function sessionStatusLabel(status) {
    return SESSION_STATUS_LABELS[status] || status;
}

function attendanceStatusLabel(status) {
    return ATTENDANCE_STATUS_LABELS[status] || status || '-';
}

function sessionStatusBadge(status) {
    const className = status === 'completed' ? 'badge-success'
        : (status === 'scheduled' || status === 'confirmed') ? 'badge-primary'
        : (status === 'cancelled' || status === 'no_show') ? 'badge-warning'
        : 'badge';
    return '<span class="badge ' + className + '">' + ELIPS.escapeHtml(sessionStatusLabel(status)) + '</span>';
}

function attendanceStatusBadge(status) {
    if (!status) return '<span class="badge" style="background:#e5e7eb;color:#374151;">Belum diisi</span>';
    const className = status === 'present' ? 'badge-success'
        : (status === 'late' || status === 'permission' || status === 'sick') ? 'badge-warning'
        : 'badge';
    return '<span class="badge ' + className + '">' + ELIPS.escapeHtml(attendanceStatusLabel(status)) + '</span>';
}

function formatSessionDate(value) {
    if (!value) return '-';
    const parts = String(value).split('-');
    if (parts.length !== 3) return value;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return Number(parts[2]) + ' ' + (months[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
}

function sessionActualWeight(session) {
    if (['cancelled', 'no_show', 'rescheduled'].indexOf(session.status) !== -1) return 0;
    return Number(session.actualJp) || Number(session.plannedJp) || 0;
}

function ownInstructorClasses() {
    return ELIPS.getClassesByInstructor(instructorSessionUserId).slice().sort(function (a, b) {
        return String(a.name || '').localeCompare(String(b.name || ''));
    });
}

function renderInstructorSessionFilters() {
    const select = document.getElementById('sessionClassFilter');
    const current = select.value;
    const classes = ownInstructorClasses();
    select.innerHTML = '<option value="">Semua Kelas Saya</option>' + classes.map(function (item) {
        return '<option value="' + item.id + '">' + ELIPS.escapeHtml(item.name + ' (' + item.code + ')') + '</option>';
    }).join('');
    if (current && ELIPS.getClassById(current)) select.value = current;
}

function fillInstructorClassSelect(selectId, selectedId) {
    const select = document.getElementById(selectId);
    const classes = ownInstructorClasses();
    select.innerHTML = '<option value="">-- Pilih Kelas --</option>' + classes.map(function (item) {
        return '<option value="' + item.id + '">' + ELIPS.escapeHtml(item.name + ' (' + item.code + ')') + '</option>';
    }).join('');
    if (selectedId) select.value = String(selectedId);
}

function fillInstructorModuleSelect(classId, selectedId) {
    const select = document.getElementById('sessionModule');
    const item = ELIPS.getClassById(classId);
    if (!item) {
        select.innerHTML = '<option value="">-- Tanpa modul --</option>';
        return;
    }
    const modules = ELIPS.getModules().filter(function (module) {
        return Number(module.curriculumVersionId) === Number(item.curriculumVersionId);
    }).sort(function (a, b) {
        return (Number(a.order) || 0) - (Number(b.order) || 0);
    });
    select.innerHTML = '<option value="">-- Tanpa modul --</option>' + modules.map(function (module) {
        return '<option value="' + module.id + '">' + ELIPS.escapeHtml(module.title) + '</option>';
    }).join('');
    if (selectedId) select.value = String(selectedId);
}

function refreshSessionModules() {
    fillInstructorModuleSelect(document.getElementById('sessionClass').value, null);
}

function renderInstructorSessions() {
    const filter = document.getElementById('sessionClassFilter').value;
    const classes = ownInstructorClasses().filter(function (item) {
        return !filter || Number(item.id) === Number(filter);
    });
    const summary = document.getElementById('sessionSummary');
    const tbody = document.getElementById('sessionTable');

    let sessions = [];
    classes.forEach(function (item) {
        sessions = sessions.concat(ELIPS.getSessionsByClass(item.id).map(function (session) {
            return { session: session, classItem: item };
        }));
    });
    sessions.sort(function (a, b) {
        const dateCompare = String(a.session.sessionDate || '').localeCompare(String(b.session.sessionDate || ''));
        if (dateCompare !== 0) return dateCompare;
        return (Number(a.session.sessionNumber) || 0) - (Number(b.session.sessionNumber) || 0);
    });

    const plannedJp = sessions.reduce(function (sum, entry) { return sum + (Number(entry.session.plannedJp) || 0); }, 0);
    const actualJp = sessions.reduce(function (sum, entry) { return sum + sessionActualWeight(entry.session); }, 0);
    const attendanceRows = sessions.reduce(function (sum, entry) {
        return sum + ELIPS.getSessionAttendances(entry.session.id).length;
    }, 0);
    summary.innerHTML = '<i class="bi bi-info-circle"></i> ' +
        ELIPS.escapeHtml(classes.length === 1 ? classes[0].name : classes.length + ' kelas') +
        ' &bull; ' + sessions.length + ' sesi' +
        ' &bull; ' + plannedJp + ' JP rencana / ' + actualJp + ' JP aktual' +
        ' &bull; ' + attendanceRows + ' baris presensi';

    if (sessions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-secondary);">Belum ada sesi pada kelas yang Anda ampu.</td></tr>';
        return;
    }

    tbody.innerHTML = sessions.map(function (entry) {
        const item = entry.session;
        const classItem = entry.classItem;
        const present = (Number(item.presentCount) || 0) + (Number(item.lateCount) || 0);
        const attendanceLabel = (Number(item.attendanceCount) || 0) === 0
            ? '<span style="color: var(--text-secondary);">Belum ada</span>'
            : present + '/' + item.attendanceCount + ' hadir' +
                '<div class="table-course-meta">H:' + (Number(item.presentCount) || 0) + ' T:' + (Number(item.lateCount) || 0) +
                ' I:' + (Number(item.permissionCount) || 0) + ' S:' + (Number(item.sickCount) || 0) +
                ' A:' + (Number(item.absentCount) || 0) + '</div>';
        const actions = [];
        actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Edit sesi" onclick="openSessionModal(' + item.id + ')"><i class="bi bi-pencil"></i></button>');
        actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Isi presensi" onclick="openAttendanceModal(' + item.id + ')"><i class="bi bi-clipboard-check"></i></button>');
        actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Rekap kehadiran" onclick="openRecapModal(' + item.classId + ')"><i class="bi bi-bar-chart"></i></button>');
        if (item.status !== 'cancelled' && item.status !== 'completed') {
            actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--warning);" title="Batalkan sesi" onclick="cancelSession(' + item.id + ')"><i class="bi bi-x-circle"></i></button>');
        }
        return '<tr>' +
            '<td><div class="table-course-title">Pertemuan ' + ELIPS.escapeHtml(item.sessionNumber) + ': ' + ELIPS.escapeHtml(item.title || '-') + '</div>' +
            '<div class="table-course-meta">' + ELIPS.escapeHtml(classItem.name) + '</div></td>' +
            '<td><span>' + ELIPS.escapeHtml(formatSessionDate(item.sessionDate)) + '</span>' +
            '<div class="table-course-meta">' + ELIPS.escapeHtml(item.startTime || '-') + ' - ' + ELIPS.escapeHtml(item.endTime || '-') + '</div></td>' +
            '<td><span>' + ELIPS.escapeHtml(item.moduleTitle || '-') + '</span></td>' +
            '<td><span>' + (Number(item.plannedJp) || 0) + ' / ' + (Number(item.actualJp) || 0) + ' JP</span></td>' +
            '<td>' + attendanceLabel + '</td>' +
            '<td>' + sessionStatusBadge(item.status) + '</td>' +
            '<td><div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">' + actions.join('') + '</div></td>' +
            '</tr>';
    }).join('');
}

function openSessionModal(id) {
    editingSessionId = id ? Number(id) : null;
    const item = id ? ELIPS.getClassSessionById(id) : null;
    const filterClassId = document.getElementById('sessionClassFilter').value;
    const classId = item ? item.classId : (filterClassId || (ownInstructorClasses()[0] || {}).id || '');
    const classItem = classId ? ELIPS.getClassById(classId) : null;

    document.getElementById('sessionModalTitle').textContent = item ? 'Edit Sesi Pertemuan' : 'Buat Sesi Pertemuan';
    fillInstructorClassSelect('sessionClass', classId);
    document.getElementById('sessionClass').disabled = !!item;
    document.getElementById('sessionId').value = item ? item.id : '';
    document.getElementById('sessionNumber').value = item ? item.sessionNumber : '';
    document.getElementById('sessionDate').value = item ? (item.sessionDate || '') : new Date().toISOString().slice(0, 10);
    document.getElementById('sessionTitle').value = item ? (item.title || '') : '';
    fillInstructorModuleSelect(classId, item ? item.moduleId : null);
    document.getElementById('sessionStart').value = item ? item.startTime : '09:00';
    document.getElementById('sessionEnd').value = item ? item.endTime : '11:00';
    document.getElementById('sessionLocation').value = item ? (item.location || '') : (classItem ? (classItem.location || '') : '');
    document.getElementById('sessionRoom').value = item ? (item.room || '') : (classItem ? (classItem.room || '') : '');
    document.getElementById('sessionPlannedJp').value = item ? item.plannedJp : (classItem && Number(classItem.plannedJp) > 0 ? classItem.plannedJp : 4);
    document.getElementById('sessionActualJp').value = item ? (item.actualJp || 0) : 0;
    const statusSelect = document.getElementById('sessionStatus');
    statusSelect.innerHTML = SESSION_STATUSES.map(function (status) {
        return '<option value="' + status + '">' + ELIPS.escapeHtml(sessionStatusLabel(status)) + '</option>';
    }).join('');
    statusSelect.value = item ? item.status : 'scheduled';
    document.getElementById('sessionNotes').value = item ? (item.notes || '') : '';
    document.getElementById('sessionModal').style.display = 'flex';
}

function closeSessionModal() {
    document.getElementById('sessionModal').style.display = 'none';
}

function saveSession() {
    const classId = Number(document.getElementById('sessionClass').value);
    if (!classId) {
        alert('Pilih kelas terlebih dahulu.');
        return;
    }
    const data = {
        classId: classId,
        sessionNumber: Number(document.getElementById('sessionNumber').value) || 0,
        title: document.getElementById('sessionTitle').value.trim(),
        moduleId: document.getElementById('sessionModule').value || null,
        sessionDate: document.getElementById('sessionDate').value,
        startTime: document.getElementById('sessionStart').value,
        endTime: document.getElementById('sessionEnd').value,
        location: document.getElementById('sessionLocation').value.trim(),
        room: document.getElementById('sessionRoom').value.trim(),
        plannedJp: document.getElementById('sessionPlannedJp').value,
        actualJp: document.getElementById('sessionActualJp').value,
        status: document.getElementById('sessionStatus').value,
        notes: document.getElementById('sessionNotes').value.trim()
    };
    const result = editingSessionId
        ? ELIPS.updateSession(editingSessionId, data)
        : ELIPS.createSession(data);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    closeSessionModal();
    document.getElementById('sessionClassFilter').value = String(classId);
    renderInstructorSessions();
}

function cancelSession(id) {
    if (!confirm('Batalkan sesi ini? Sesi batal tidak dihitung dalam JP aktual dan rekap wajib.')) return;
    const result = ELIPS.setSessionStatus(id, 'cancelled');
    if (!result.ok) alert(result.message);
    else ELIPS.toast(result.message);
    renderInstructorSessions();
}

function openAttendanceModal(sessionId) {
    const session = ELIPS.getClassSessionById(sessionId);
    if (!session) {
        alert('Sesi tidak ditemukan.');
        return;
    }
    const classItem = ELIPS.getClassById(session.classId);
    const enrollments = ELIPS.getClassEnrollments(session.classId).filter(function (enrollment) {
        return ['active', 'pending', 'completed'].indexOf(enrollment.status) !== -1;
    }).sort(function (a, b) {
        const userA = ELIPS.getUserById(a.studentId);
        const userB = ELIPS.getUserById(b.studentId);
        return String(userA ? userA.name : '').localeCompare(String(userB ? userB.name : ''));
    });

    document.getElementById('attendanceSessionId').value = session.id;
    document.getElementById('attendanceTitle').textContent = 'Presensi — Pertemuan ' + session.sessionNumber;
    document.getElementById('attendanceMeta').textContent = (classItem ? classItem.name + ' • ' : '') +
        formatSessionDate(session.sessionDate) + ' • ' + session.startTime + '-' + session.endTime +
        ' • ' + sessionStatusLabel(session.status);
    document.getElementById('attendanceReason').value = '';

    const tbody = document.getElementById('attendanceTableBody');
    if (enrollments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-secondary);">Belum ada peserta aktif pada kelas ini.</td></tr>';
    } else {
        tbody.innerHTML = enrollments.map(function (enrollment) {
            const student = ELIPS.getUserById(enrollment.studentId);
            const existing = ELIPS.getAttendance(session.id, enrollment.id);
            const status = existing ? existing.status : 'present';
            const options = ATTENDANCE_STATUSES.map(function (value) {
                return '<option value="' + value + '" ' + (value === status ? 'selected' : '') + '>' +
                    ELIPS.escapeHtml(attendanceStatusLabel(value)) + '</option>';
            }).join('');
            return '<tr data-enrollment-id="' + enrollment.id + '">' +
                '<td><div class="table-course-title">' + ELIPS.escapeHtml(student ? student.name : 'Peserta #' + enrollment.studentId) + '</div>' +
                '<div class="table-course-meta">' + ELIPS.escapeHtml(student ? student.email : '') + (existing ? ' • sudah tercatat' : '') + '</div></td>' +
                '<td><select class="form-input attendance-status" style="padding: 0.375rem 0.5rem; font-size: 0.8125rem;">' + options + '</select></td>' +
                '<td><input type="text" class="form-input attendance-note" value="' + ELIPS.escapeHtml(existing ? (existing.note || '') : '') + '" placeholder="Opsional"></td>' +
                '</tr>';
        }).join('');
    }
    document.getElementById('attendanceModal').style.display = 'flex';
}

function closeAttendanceModal() {
    document.getElementById('attendanceModal').style.display = 'none';
}

function saveAttendance() {
    const sessionId = Number(document.getElementById('attendanceSessionId').value);
    const session = ELIPS.getClassSessionById(sessionId);
    if (!session) {
        alert('Sesi tidak ditemukan.');
        return;
    }
    const rows = Array.from(document.querySelectorAll('#attendanceTableBody tr[data-enrollment-id]'));
    if (rows.length === 0) {
        alert('Tidak ada peserta pada kelas ini.');
        return;
    }
    const reason = document.getElementById('attendanceReason').value.trim();
    const records = [];
    let changed = false;
    rows.forEach(function (row) {
        const enrollmentId = Number(row.dataset.enrollmentId);
        const status = row.querySelector('.attendance-status').value;
        const note = row.querySelector('.attendance-note').value.trim();
        const existing = ELIPS.getAttendance(sessionId, enrollmentId);
        if (existing && existing.status !== status) changed = true;
        const record = { enrollmentId: enrollmentId, status: status, note: note };
        if (reason) record.reason = reason;
        records.push(record);
    });
    if (changed && !reason) {
        alert('Alasan perubahan wajib diisi karena ada status presensi yang dikoreksi.');
        return;
    }
    const result = ELIPS.saveAttendanceBulk(sessionId, records);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    closeAttendanceModal();
    renderInstructorSessions();
}

function openRecapModal(classId) {
    const id = Number(classId || document.getElementById('sessionClassFilter').value);
    if (!id) {
        alert('Pilih kelas terlebih dahulu pada filter, lalu buka rekap.');
        return;
    }
    const result = ELIPS.getAttendanceRecap(id);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    const recap = result.data;
    document.getElementById('recapTitle').textContent = 'Rekap Kehadiran — ' + recap.className;
    document.getElementById('recapMeta').textContent = recap.classCode + ' • ' +
        recap.countedSessions + '/' + recap.totalSessions + ' sesi dihitung • ' +
        recap.plannedJp + ' JP rencana / ' + recap.actualJp + ' JP aktual';

    const policy = recap.policy || ELIPS.getAttendancePolicy();
    const policyText = 'Persentase = JP hadir (' + policy.presentStatuses.map(attendanceStatusLabel).join(', ') +
        ') ÷ JP wajib (sesi ' + policy.countedStatuses.map(sessionStatusLabel).join(', ') + ') × 100.';
    const body = document.getElementById('recapBody');

    if (!recap.students || recap.students.length === 0) {
        body.innerHTML = '<p style="color: var(--text-secondary);">Belum ada peserta aktif pada kelas ini.</p>' +
            '<p style="font-size: 0.875rem; color: var(--text-secondary);">' + ELIPS.escapeHtml(policyText) + '</p>';
        document.getElementById('recapModal').style.display = 'flex';
        return;
    }

    const rows = recap.students.map(function (student) {
        const percentage = Number(student.percentage) || 0;
        const badgeClass = percentage >= 75 ? 'badge-success' : (percentage >= 50 ? 'badge-warning' : 'badge');
        return '<tr>' +
            '<td><div class="table-course-title">' + ELIPS.escapeHtml(student.studentName) + '</div>' +
            '<div class="table-course-meta">' + ELIPS.escapeHtml(student.studentEmail || '') + '</div></td>' +
            '<td>' + (Number(student.present) || 0) + '</td>' +
            '<td>' + (Number(student.late) || 0) + '</td>' +
            '<td>' + (Number(student.permission) || 0) + '</td>' +
            '<td>' + (Number(student.sick) || 0) + '</td>' +
            '<td>' + (Number(student.absent) || 0) + '</td>' +
            '<td>' + (Number(student.presentJp) || 0) + ' / ' + (Number(student.requiredJp) || 0) + '</td>' +
            '<td><span class="badge ' + badgeClass + '">' + percentage + '%</span></td>' +
            '</tr>';
    }).join('');

    body.innerHTML =
        '<div style="overflow-x: auto;"><table class="dashboard-table">' +
        '<thead><tr><th>Peserta</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>JP Hadir / Wajib</th><th>Kehadiran</th></tr></thead>' +
        '<tbody>' + rows + '</tbody></table></div>' +
        '<p style="font-size: 0.8125rem; color: var(--text-secondary);">' + ELIPS.escapeHtml(policyText) + '</p>';
    document.getElementById('recapModal').style.display = 'flex';
}

function closeRecapModal() {
    document.getElementById('recapModal').style.display = 'none';
}

function participantState(row) {
    if (row.certificate && row.certificate.status === 'publish') {
        return { label: 'Sertifikat Terbit', className: 'badge-success', icon: 'bi-patch-check-fill' };
    }
    if (row.grade) {
        return { label: 'Sudah Dinilai', className: 'badge-success', icon: 'bi-check-circle-fill' };
    }
    if (row.isComplete) {
        return { label: 'Perlu Dinilai', className: 'badge-warning', icon: 'bi-hourglass-split' };
    }
    if (row.progress.completed > 0) {
        return { label: 'Sedang Belajar', className: 'badge-primary', icon: 'bi-play-circle' };
    }
    return { label: 'Belum Mulai', className: 'badge-primary', icon: 'bi-circle' };
}

function renderCourses(summary) {
    const tbody = document.getElementById('instructorCoursesBody');
    tbody.innerHTML = '';

    if (summary.courses.length === 0) {
        tbody.innerHTML = [
            '<tr><td colspan="5" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-secondary);">',
            '  <i class="bi bi-journal-x" style="display: block; font-size: 2.5rem; opacity: .45; margin-bottom: .75rem;"></i>',
            '  <div style="font-weight: 600; color: var(--text-primary); margin-bottom: .25rem;">Belum ada kursus yang ditugaskan</div>',
            '  <div style="font-size: .875rem;">Hubungi Admin agar Anda ditugaskan sebagai tentor pada sebuah kursus.</div>',
            '</td></tr>'
        ].join('');
        return;
    }

    summary.courses.forEach(function (course) {
        const modules = ELIPS.getModulesByCourse(course.id).length;
        const lessons = ELIPS.getLessonsByCourse(course.id).length;
        const enrollments = ELIPS.getEnrollmentsByCourse(course.id).filter(function (enrollment) {
            return enrollment.status === 'active';
        });
        const completed = enrollments.filter(function (enrollment) {
            const progress = ELIPS.getCourseProgress(enrollment.studentId, course.id);
            return progress.total > 0 && progress.completed === progress.total;
        }).length;
        const published = course.status === 'published';
        const tr = document.createElement('tr');
        tr.innerHTML = [
            '<td>',
            '  <div class="table-course">',
            '    <div class="table-course-img"></div>',
            '    <div class="table-course-info">',
            '      <div class="table-course-title">' + ELIPS.escapeHtml(course.title) + '</div>',
            '      <div class="table-course-meta">' + ELIPS.escapeHtml(course.category || 'Kursus') + '</div>',
            '    </div>',
            '  </div>',
            '</td>',
            '<td>',
            '  <span>' + enrollments.length + ' Peserta</span>',
            '  <div class="table-course-meta">' + completed + ' selesai materi</div>',
            '</td>',
            '<td><span>' + modules + ' Modul &bull; ' + lessons + ' Pelajaran</span></td>',
            '<td><span class="badge ' + (published ? 'badge-success' : 'badge-warning') + '"><i class="bi ' + (published ? 'bi-eye' : 'bi-pencil') + '"></i> ' + (published ? 'Dipublikasi' : 'Draft') + '</span></td>',
            '<td>',
            '  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">',
            '    <a href="manage-course.php?course=' + course.id + '" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;"><i class="bi bi-folder2-open"></i> Kelola</a>',
            '    <a href="students.php?course=' + course.id + '" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;"><i class="bi bi-people"></i> Peserta</a>',
            '    <a href="../course-detail.php?id=' + course.id + '" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Pratinjau kursus"><i class="bi bi-eye"></i></a>',
            '  </div>',
            '</td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

function renderRecentStudents(rows) {
    const tbody = document.getElementById('recentStudentsBody');
    tbody.innerHTML = '';

    if (rows.length === 0) {
        tbody.innerHTML = [
            '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-secondary);">',
            '  <i class="bi bi-people" style="display: block; font-size: 2rem; opacity: .45; margin-bottom: .5rem;"></i>',
            '  Belum ada peserta yang ditugaskan ke kursus Anda.',
            '</td></tr>'
        ].join('');
        return;
    }

    rows.slice(0, 6).forEach(function (row) {
        const state = participantState(row);
        const gradeText = row.grade
            ? ELIPS.escapeHtml(row.grade.score) + ' (' + ELIPS.escapeHtml(row.grade.grade) + ')'
            : '-';
        const tr = document.createElement('tr');
        tr.innerHTML = [
            '<td>',
            '  <div class="table-course">',
            '    <div class="dashboard-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">' + ELIPS.escapeHtml((row.student.name || 'P').substring(0, 2).toUpperCase()) + '</div>',
            '    <div class="table-course-info">',
            '      <div class="table-course-title">' + ELIPS.escapeHtml(row.student.name || '-') + '</div>',
            '      <div class="table-course-meta">' + ELIPS.escapeHtml(row.student.email || '-') + '</div>',
            '    </div>',
            '  </div>',
            '</td>',
            '<td><span>' + ELIPS.escapeHtml(row.course.title) + '</span></td>',
            '<td>',
            '  <div class="progress-inline">',
            '    <div class="progress-inline-bar"><div class="progress-inline-fill" style="width: ' + row.progress.percentage + '%;' + (row.progress.percentage >= 100 ? '' : ' background: var(--primary);') + '"></div></div>',
            '    <span class="progress-inline-text">' + row.progress.percentage + '%</span>',
            '  </div>',
            '  <small style="color: var(--text-secondary);">' + row.progress.completed + '/' + row.progress.total + ' pelajaran</small>',
            '</td>',
            '<td><span class="badge ' + state.className + '"><i class="bi ' + state.icon + '"></i> ' + state.label + '</span></td>',
            '<td><span>' + gradeText + '</span></td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

function renderNotice(summary) {
    const notices = [];
    if (summary.draftCourses > 0) {
        notices.push(summary.draftCourses + ' kursus masih berstatus Draft. Lengkapi materinya, lalu minta Admin untuk mempublikasikan.');
    }
    if (summary.awaitingGrade > 0) {
        notices.push(summary.awaitingGrade + ' peserta sudah menyelesaikan seluruh materi dan menunggu penilaian Anda.');
    }
    if (summary.draftCertificates > 0) {
        notices.push(summary.draftCertificates + ' e-sertifikat menunggu persetujuan Admin.');
    }

    if (notices.length === 0) return;
    document.getElementById('instructorNoticeText').textContent = notices.join(' ');
    document.getElementById('instructorNotice').style.display = 'flex';
}