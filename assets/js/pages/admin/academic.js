// ELIPS LMS - skrip halaman admin/academic.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
let editingVersionId = null;
let editingClassId = null;
let activeRosterClassId = null;

function currentActorId() {
    const user = ELIPS.getCurrentUser();
    return user ? user.id : null;
}

function versionBadge(status) {
    if (status === 'published') return '<span class="badge badge-success">Published</span>';
    if (status === 'locked') return '<span class="badge badge-primary">Locked</span>';
    if (status === 'retired') return '<span class="badge" style="background:#e5e7eb;color:#374151;">Retired</span>';
    return '<span class="badge badge-warning">Draft</span>';
}

function classBadge(status) {
    if (status === 'ongoing' || status === 'ready') return '<span class="badge badge-success">' + ELIPS.escapeHtml(status) + '</span>';
    if (status === 'open') return '<span class="badge badge-primary">Open</span>';
    if (status === 'draft') return '<span class="badge badge-warning">Draft</span>';
    return '<span class="badge" style="background:#e5e7eb;color:#374151;">' + ELIPS.escapeHtml(status) + '</span>';
}

function renderStats() {
    document.getElementById('statCourses').textContent = ELIPS.getCourses().length;
    document.getElementById('statVersions').textContent = ELIPS.getCurriculumVersions().length;
    document.getElementById('statClasses').textContent = ELIPS.getClasses().length;
    document.getElementById('statEnrollments').textContent = ELIPS.getEnrollments().filter(function (e) {
        return e.status === 'active' || e.status === 'pending';
    }).length;
}

function renderFilters() {
    const courses = ELIPS.getCourses();
    ['versionCourseFilter', 'classCourseFilter'].forEach(function (id) {
        const select = document.getElementById(id);
        const current = select.value;
        select.innerHTML = '<option value="all">Semua Kursus</option>' + courses.map(function (course) {
            return '<option value="' + course.id + '">' + ELIPS.escapeHtml(course.title) + '</option>';
        }).join('');
        select.value = current || 'all';
    });

    const branchSelect = document.getElementById('classBranchFilter');
    const currentBranch = branchSelect.value;
    const branches = ELIPS.getBranches().slice().sort(function (a, b) {
        return String(a.name || '').localeCompare(String(b.name || ''));
    });
    branchSelect.innerHTML = '<option value="all">Semua Cabang</option>' + branches.map(function (branch) {
        return '<option value="' + branch.id + '">' + ELIPS.escapeHtml(branch.name) + '</option>';
    }).join('');
    branchSelect.value = currentBranch || 'all';
}

function renderVersions() {
    const filter = document.getElementById('versionCourseFilter').value;
    const tbody = document.getElementById('versionTable');
    const versions = ELIPS.getCurriculumVersions().slice().sort(function (a, b) {
        if (a.courseId !== b.courseId) return Number(a.courseId) - Number(b.courseId);
        return Number(a.id) - Number(b.id);
    }).filter(function (version) {
        return filter === 'all' || Number(version.courseId) === Number(filter);
    });

    if (versions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-secondary);">Belum ada versi kurikulum.</td></tr>';
        return;
    }

    tbody.innerHTML = versions.map(function (version) {
        const course = ELIPS.getCourseById(version.courseId);
        const actions = [];
        if (version.status === 'draft') {
            actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Edit" onclick="openVersionModal(' + version.id + ')"><i class="bi bi-pencil"></i></button>');
            actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--success);" title="Publikasikan" onclick="publishVersion(' + version.id + ')"><i class="bi bi-send-check"></i></button>');
        }
        actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Lihat isi" onclick="viewVersion(' + version.id + ')"><i class="bi bi-eye"></i></button>');
        if (version.status !== 'retired') {
            actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Salin versi" onclick="cloneVersion(' + version.id + ')"><i class="bi bi-files"></i></button>');
        }
        if (version.status === 'published' || version.status === 'locked') {
            actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--warning);" title="Pensiunkan" onclick="retireVersion(' + version.id + ')"><i class="bi bi-archive"></i></button>');
        }
        if (version.status === 'draft') {
            actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--danger);" title="Hapus" onclick="removeVersion(' + version.id + ')"><i class="bi bi-trash"></i></button>');
        }

        return '<tr>' +
            '<td><span>' + ELIPS.escapeHtml(course ? course.title : 'Kursus #' + version.courseId) + '</span></td>' +
            '<td><span class="badge badge-primary">' + ELIPS.escapeHtml(version.code) + '</span></td>' +
            '<td><span>' + ELIPS.escapeHtml(version.name) + '</span></td>' +
            '<td><span>' + (version.modules || 0) + ' / ' + (version.lessons || 0) + '</span></td>' +
            '<td><span>' + (version.classes || 0) + '</span></td>' +
            '<td>' + versionBadge(version.status) + '</td>' +
            '<td><div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">' + actions.join('') + '</div></td>' +
            '</tr>';
    }).join('');
}

function renderClasses() {
    const filter = document.getElementById('classCourseFilter').value;
    const branchFilter = document.getElementById('classBranchFilter').value;
    const tbody = document.getElementById('classTable');
    const classes = ELIPS.getClasses().slice().sort(function (a, b) {
        return Number(b.id) - Number(a.id);
    }).filter(function (item) {
        const matchCourse = filter === 'all' || Number(item.courseId) === Number(filter);
        const matchBranch = branchFilter === 'all' || Number(item.branchId) === Number(branchFilter);
        return matchCourse && matchBranch;
    });

    if (classes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: var(--text-secondary);">Belum ada kelas/angkatan.</td></tr>';
        return;
    }

    tbody.innerHTML = classes.map(function (item) {
        const course = ELIPS.getCourseById(item.courseId);
        const version = ELIPS.getCurriculumVersionById(item.curriculumVersionId);
        const activeCount = ELIPS.activeClassEnrollmentCount(item.id);
        const quotaLabel = (Number(item.quota) || 0) === 0 ? 'Tanpa batas' : (activeCount + ' / ' + item.quota);
        const statusOptions = ['draft', 'open', 'ready', 'ongoing', 'completed', 'cancelled', 'archived']
            .map(function (status) {
                return '<option value="' + status + '" ' + (status === item.status ? 'selected' : '') + '>' + status + '</option>';
            }).join('');

        return '<tr>' +
            '<td><div class="table-course-title">' + ELIPS.escapeHtml(item.name) + '</div><div class="table-course-meta">' + ELIPS.escapeHtml(item.code) + '</div></td>' +
            '<td><span>' + ELIPS.escapeHtml(course ? course.title : 'Kursus #' + item.courseId) + '</span></td>' +
            '<td><span class="badge badge-primary">' + ELIPS.escapeHtml(version ? version.code : item.curriculumVersionCode || '-') + '</span></td>' +
            '<td>' + (item.branchName
                ? '<div class="table-course-title" style="font-size: 0.875rem;">' + ELIPS.escapeHtml(item.branchName) + '</div><div class="table-course-meta">' + ELIPS.escapeHtml(item.branchCode || '') + '</div>'
                : '<span style="color: var(--text-secondary);">Tanpa cabang</span>') + '</td>' +
            '<td><span>' + ELIPS.escapeHtml(item.instructorName || 'Belum ditetapkan') + '</span></td>' +
            '<td><span style="color: var(--text-secondary);">' + ELIPS.escapeHtml(item.periodStart || '-') + ' s.d. ' + ELIPS.escapeHtml(item.periodEnd || '-') + '</span></td>' +
            '<td><span>' + quotaLabel + '</span></td>' +
            '<td>' +
            '  <div style="display: flex; flex-direction: column; gap: 0.25rem;">' +
            '    ' + classBadge(item.status) +
            '    <select class="form-input" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onchange="changeClassStatus(' + item.id + ', this.value)">' + statusOptions + '</select>' +
            '  </div>' +
            '</td>' +
            '<td><div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">' +
            '  <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Edit kelas" onclick="openClassModal(' + item.id + ')"><i class="bi bi-pencil"></i></button>' +
            '  <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Peserta" onclick="openRosterModal(' + item.id + ')"><i class="bi bi-people"></i></button>' +
            '  <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Tentor" onclick="openInstructorModal(' + item.id + ')"><i class="bi bi-person-badge"></i></button>' +
            '  <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Akses materi" onclick="openMaterialSettingsModal(' + item.id + ')"><i class="bi bi-journal-lock"></i></button>' +
            ((item.status === 'draft' || item.status === 'cancelled')
                ? '  <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--danger);" title="Hapus" onclick="removeClass(' + item.id + ')"><i class="bi bi-trash"></i></button>'
                : '') +
            '</div></td>' +
            '</tr>';
    }).join('');
}

// ---------- Session & attendance helpers ----------
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
    const className = (status === 'present') ? 'badge-success'
        : (status === 'late' || status === 'permission' || status === 'sick') ? 'badge-warning'
        : 'badge';
    return '<span class="badge ' + className + '">' + ELIPS.escapeHtml(attendanceStatusLabel(status)) + '</span>';
}

function formatSessionDate(value) {
    if (!value) return '-';
    const parts = String(value).split('-');
    if (parts.length !== 3) return value;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const month = Number(parts[1]) - 1;
    return Number(parts[2]) + ' ' + (months[month] || parts[1]) + ' ' + parts[0];
}

function sessionCounted(session) {
    const policy = ELIPS.getAttendancePolicy();
    return policy.countedStatuses.indexOf(session.status) !== -1;
}

function sessionActualWeight(session) {
    if (['cancelled', 'no_show', 'rescheduled'].indexOf(session.status) !== -1) return 0;
    return Number(session.actualJp) || Number(session.plannedJp) || 0;
}

function sessionPresentTotal(item) {
    return (Number(item.presentCount) || 0) + (Number(item.lateCount) || 0);
}

function fillSessionClassSelect(selectId, selectedId) {
    const select = document.getElementById(selectId);
    const classes = ELIPS.getClasses().slice().sort(function (a, b) {
        return String(a.name || '').localeCompare(String(b.name || ''));
    });
    select.innerHTML = '<option value="">-- Pilih Kelas --</option>' + classes.map(function (item) {
        return '<option value="' + item.id + '">' + ELIPS.escapeHtml(item.name + ' (' + item.code + ')') + '</option>';
    }).join('');
    if (selectedId) select.value = String(selectedId);
}

function fillSessionModuleSelect(classId, selectId, selectedId) {
    const select = document.getElementById(selectId);
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
    const classId = document.getElementById('sessionClass').value;
    fillSessionModuleSelect(classId, 'sessionModule', null);
}

function refreshBulkModules() {
    const classId = document.getElementById('bulkClass').value;
    fillSessionModuleSelect(classId, 'bulkModule', null);
}

function renderSessionFilters() {
    const select = document.getElementById('sessionClassFilter');
    const current = select.value;
    const classes = ELIPS.getClasses().slice().sort(function (a, b) {
        return String(a.name || '').localeCompare(String(b.name || ''));
    });
    select.innerHTML = '<option value="">Semua Kelas</option>' + classes.map(function (item) {
        return '<option value="' + item.id + '">' + ELIPS.escapeHtml(item.name + ' (' + item.code + ')') + '</option>';
    }).join('');
    if (current && ELIPS.getClassById(current)) select.value = current;
}

function renderSessionSummary(classes) {
    const summary = document.getElementById('sessionSummary');
    let sessions = [];
    classes.forEach(function (item) {
        sessions = sessions.concat(ELIPS.getSessionsByClass(item.id));
    });
    const finished = sessions.filter(function (session) { return session.status === 'completed'; }).length;
    const plannedJp = sessions.reduce(function (sum, session) { return sum + (Number(session.plannedJp) || 0); }, 0);
    const actualJp = sessions.reduce(function (sum, session) { return sum + sessionActualWeight(session); }, 0);
    const attendanceRows = sessions.reduce(function (sum, session) {
        return sum + (ELIPS.getSessionAttendances(session.id).length);
    }, 0);
    const scope = classes.length === 1 ? classes[0].name : classes.length + ' kelas';
    summary.innerHTML = '<i class="bi bi-info-circle"></i> ' + ELIPS.escapeHtml(scope) +
        ' &bull; ' + sessions.length + ' sesi (' + finished + ' selesai)' +
        ' &bull; ' + plannedJp + ' JP rencana / ' + actualJp + ' JP aktual' +
        ' &bull; ' + attendanceRows + ' baris presensi';
}

function renderSessions() {
    const filter = document.getElementById('sessionClassFilter').value;
    const tbody = document.getElementById('sessionTable');
    const classes = ELIPS.getClasses().filter(function (item) {
        return !filter || Number(item.id) === Number(filter);
    });
    renderSessionSummary(classes);

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

    if (sessions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--text-secondary);">Belum ada sesi pertemuan untuk filter ini.</td></tr>';
        return;
    }

    tbody.innerHTML = sessions.map(function (entry) {
        const item = entry.session;
        const classItem = entry.classItem;
        const actions = [];
        actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Edit sesi" onclick="openSessionModal(' + item.id + ')"><i class="bi bi-pencil"></i></button>');
        actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Isi presensi" onclick="openAttendanceModal(' + item.id + ')"><i class="bi bi-clipboard-check"></i></button>');
        actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Rekap kehadiran" onclick="openRecapModal(' + item.classId + ')"><i class="bi bi-bar-chart"></i></button>');
        if (item.status !== 'cancelled' && item.status !== 'completed') {
            actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--warning);" title="Batalkan sesi" onclick="cancelSession(' + item.id + ')"><i class="bi bi-x-circle"></i></button>');
        }
        if (item.status !== 'completed' && (Number(item.attendanceCount) || 0) === 0) {
            actions.push('<button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--danger);" title="Hapus sesi" onclick="removeSession(' + item.id + ')"><i class="bi bi-trash"></i></button>');
        }
        const attendanceLabel = (Number(item.attendanceCount) || 0) === 0
            ? '<span style="color: var(--text-secondary);">Belum ada</span>'
            : sessionPresentTotal(item) + '/' + item.attendanceCount + ' hadir' +
                '<div class="table-course-meta">H:' + (Number(item.presentCount) || 0) + ' T:' + (Number(item.lateCount) || 0) +
                ' I:' + (Number(item.permissionCount) || 0) + ' S:' + (Number(item.sickCount) || 0) +
                ' A:' + (Number(item.absentCount) || 0) + '</div>';

        return '<tr>' +
            '<td><div class="table-course-title">Pertemuan ' + ELIPS.escapeHtml(item.sessionNumber) + ': ' + ELIPS.escapeHtml(item.title || '-') + '</div>' +
            '<div class="table-course-meta">' + ELIPS.escapeHtml(classItem.name + ' • ' + classItem.code) + '</div></td>' +
            '<td><span>' + ELIPS.escapeHtml(formatSessionDate(item.sessionDate)) + '</span>' +
            '<div class="table-course-meta">' + ELIPS.escapeHtml(item.startTime || '-') + ' - ' + ELIPS.escapeHtml(item.endTime || '-') + '</div></td>' +
            '<td><span>' + ELIPS.escapeHtml(item.moduleTitle || '-') + '</span></td>' +
            '<td><span>' + ELIPS.escapeHtml(item.instructorName || 'Belum ditetapkan') + '</span></td>' +
            '<td><span>' + (Number(item.plannedJp) || 0) + ' / ' + (Number(item.actualJp) || 0) + ' JP</span></td>' +
            '<td>' + attendanceLabel + '</td>' +
            '<td>' + sessionStatusBadge(item.status) + '</td>' +
            '<td><div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">' + actions.join('') + '</div></td>' +
            '</tr>';
    }).join('');
}

function renderAll() {
    renderStats();
    renderFilters();
    renderVersions();
    renderClasses();
    renderSessionFilters();
    renderSessions();
}

// ---------- Version modal ----------
function fillCourseSelect(select, selectedId) {
    const courses = ELIPS.getCourses();
    select.innerHTML = '<option value="">-- Pilih Kursus --</option>' + courses.map(function (course) {
        return '<option value="' + course.id + '">' + ELIPS.escapeHtml(course.title) + '</option>';
    }).join('');
    if (selectedId) select.value = String(selectedId);
}

function openVersionModal(id) {
    editingVersionId = id ? Number(id) : null;
    const version = id ? ELIPS.getCurriculumVersionById(id) : null;
    document.getElementById('versionModalTitle').textContent = version ? 'Edit Versi Kurikulum' : 'Buat Versi Kurikulum';
    fillCourseSelect(document.getElementById('versionCourse'), version ? version.courseId : null);
    document.getElementById('versionCourse').disabled = !!version;
    document.getElementById('versionId').value = version ? version.id : '';
    document.getElementById('versionCode').value = version ? version.code : '';
    document.getElementById('versionName').value = version ? version.name : '';
    document.getElementById('versionDescription').value = version ? (version.description || '') : '';
    document.getElementById('versionEffectiveDate').value = version ? (version.effectiveDate || '') : '';
    document.getElementById('versionNotes').value = version ? (version.notes || '') : '';
    document.getElementById('versionModal').style.display = 'flex';
}

function closeVersionModal() {
    document.getElementById('versionModal').style.display = 'none';
}

function saveVersion() {
    const courseId = Number(document.getElementById('versionCourse').value);
    const data = {
        code: document.getElementById('versionCode').value.trim(),
        name: document.getElementById('versionName').value.trim(),
        description: document.getElementById('versionDescription').value.trim(),
        effectiveDate: document.getElementById('versionEffectiveDate').value || null,
        notes: document.getElementById('versionNotes').value.trim(),
        actorId: currentActorId()
    };
    if (!courseId) {
        alert('Pilih kursus terlebih dahulu.');
        return;
    }
    const result = editingVersionId
        ? ELIPS.updateCurriculumVersion(editingVersionId, data)
        : ELIPS.createCurriculumVersion(courseId, data);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    closeVersionModal();
    renderAll();
}

function publishVersion(id) {
    const result = ELIPS.publishCurriculumVersion(id);
    ELIPS.toast(result.message);
    renderAll();
}

function cloneVersion(id) {
    if (!confirm('Salin versi kurikulum ini menjadi Draft baru?')) return;
    const result = ELIPS.cloneCurriculumVersion(id, { actorId: currentActorId() });
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    renderAll();
}

function retireVersion(id) {
    if (!confirm('Pensiunkan versi ini? Kelas lama tetap memakai versi ini.')) return;
    const result = ELIPS.retireCurriculumVersion(id);
    ELIPS.toast(result.message);
    renderAll();
}

function removeVersion(id) {
    if (!confirm('Hapus versi kurikulum Draft ini beserta modul dan materinya?')) return;
    const result = ELIPS.deleteCurriculumVersion(id);
    ELIPS.toast(result.message);
    renderAll();
}

function viewVersion(id) {
    const result = ELIPS.getCurriculumDetail(id);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    const version = result.data;
    document.getElementById('detailTitle').textContent = version.name + ' (' + version.code + ')';
    const modules = version.modules || [];
    document.getElementById('detailBody').innerHTML = modules.length === 0
        ? '<p style="color: var(--text-secondary);">Belum ada modul pada versi ini.</p>'
        : modules.map(function (module, index) {
            const lessons = module.lessons || [];
            return '<div style="border: 1px solid var(--border); border-radius: var(--radius); padding: 0.75rem;">' +
                '<div style="font-weight: 600;">' + (index + 1) + '. ' + ELIPS.escapeHtml(module.title) + '</div>' +
                (lessons.length === 0
                    ? '<div style="font-size: 0.875rem; color: var(--text-secondary);">Belum ada materi.</div>'
                    : '<ul style="margin: 0.5rem 0 0 1.25rem; font-size: 0.875rem;">' +
                        lessons.map(function (lesson) {
                            return '<li>' + ELIPS.escapeHtml(lesson.title) + ' <span style="color: var(--text-secondary);">(' + ELIPS.escapeHtml(lesson.type) + ')</span></li>';
                        }).join('') + '</ul>') +
                '</div>';
        }).join('');
    document.getElementById('detailModal').style.display = 'flex';
}

function closeDetailModal() {
    document.getElementById('detailModal').style.display = 'none';
}

// ---------- Class modal ----------
function fillInstructorSelect(select, selectedId) {
    const instructors = ELIPS.getUsersByRole('instruktur').filter(function (user) {
        return user.status === 'aktif';
    });
    select.innerHTML = '<option value="">-- Belum ditetapkan --</option>' + instructors.map(function (user) {
        return '<option value="' + user.id + '">' + ELIPS.escapeHtml(user.name) + '</option>';
    }).join('');
    if (selectedId) select.value = String(selectedId);
}

function fillBranchSelect(select, selectedId) {
    const branches = ELIPS.getBranches().slice().sort(function (a, b) {
        return String(a.name || '').localeCompare(String(b.name || ''));
    });
    select.innerHTML = '<option value="">-- Tanpa Cabang --</option>' + branches.map(function (branch) {
        const label = branch.name + (branch.city ? ' — ' + branch.city : '') + (branch.status === 'inactive' ? ' (nonaktif)' : '');
        return '<option value="' + branch.id + '">' + ELIPS.escapeHtml(label) + '</option>';
    }).join('');
    if (selectedId) select.value = String(selectedId);
}

function refreshClassVersions(selectedVersionId) {
    const courseId = Number(document.getElementById('classCourse').value);
    const select = document.getElementById('classVersion');
    const versions = ELIPS.getCurriculumVersionsByCourse(courseId).filter(function (version) {
        return version.status === 'published' || version.status === 'locked';
    });
    select.innerHTML = '<option value="">-- Pilih Versi --</option>' + versions.map(function (version) {
        return '<option value="' + version.id + '">' + ELIPS.escapeHtml(version.code + ' — ' + version.name) + ' (' + version.status + ')</option>';
    }).join('');
    if (selectedVersionId) select.value = String(selectedVersionId);
}

// ---------- Pengaturan akses materi kelas (Phase 4) ----------
let materialSettingsClassId = null;
let materialSettingsOriginal = null;

const MATERIAL_MODE_LABELS = {
    open: 'Open',
    sequential: 'Sequential',
    scheduled: 'Scheduled',
    conditional: 'Conditional'
};

function openMaterialSettingsModal(classId) {
    const classItem = ELIPS.getClassById(classId);
    if (!classItem) return;
    const result = ELIPS.getMaterialSettings(classId);
    if (!result.ok) {
        ELIPS.toast(result.message);
        return;
    }

    materialSettingsClassId = Number(classId);
    materialSettingsOriginal = result.data;
    const settings = result.data;

    document.getElementById('materialSettingsClassId').value = String(classId);
    document.getElementById('materialSettingsMeta').innerHTML =
        '<strong>' + ELIPS.escapeHtml(classItem.name) + '</strong> (' + ELIPS.escapeHtml(classItem.code) + ')<br>' +
        'Mode saat ini: <strong>' + ELIPS.escapeHtml(MATERIAL_MODE_LABELS[settings.accessMode] || settings.accessMode) + '</strong>' +
        (settings.id === null ? ' — pengaturan bawaan sistem (belum disimpan khusus untuk kelas ini).' : '');
    document.getElementById('materialSettingsMode').value = settings.accessMode;
    document.getElementById('materialSettingsApproval').checked = !!settings.requireInstructorApproval;
    document.getElementById('materialSettingsNotes').value = settings.notes || '';
    document.getElementById('materialSettingsReason').value = '';
    document.getElementById('materialSettingsReasonGroup').style.display = settings.id === null ? 'none' : 'block';
    document.getElementById('materialSettingsModal').style.display = 'flex';
}

function closeMaterialSettingsModal() {
    document.getElementById('materialSettingsModal').style.display = 'none';
    materialSettingsClassId = null;
    materialSettingsOriginal = null;
}

function materialSettingsChanged() {
    if (!materialSettingsOriginal) return false;
    const mode = document.getElementById('materialSettingsMode').value;
    const approval = document.getElementById('materialSettingsApproval').checked;
    const notes = document.getElementById('materialSettingsNotes').value.trim();
    return mode !== materialSettingsOriginal.accessMode ||
        approval !== !!materialSettingsOriginal.requireInstructorApproval ||
        notes !== String(materialSettingsOriginal.notes || '');
}

function saveMaterialSettings() {
    if (!materialSettingsClassId) return;
    if (!materialSettingsChanged()) {
        ELIPS.toast('Tidak ada perubahan pengaturan.');
        closeMaterialSettingsModal();
        return;
    }
    const reason = document.getElementById('materialSettingsReason').value.trim();
    if (materialSettingsOriginal && materialSettingsOriginal.id !== null && reason === '') {
        ELIPS.toast('Alasan override Admin wajib diisi.');
        return;
    }
    const result = ELIPS.updateMaterialSettings(materialSettingsClassId, {
        accessMode: document.getElementById('materialSettingsMode').value,
        requireInstructorApproval: document.getElementById('materialSettingsApproval').checked,
        notes: document.getElementById('materialSettingsNotes').value,
        reason: reason
    });
    ELIPS.toast(result.message);
    if (result.ok) {
        closeMaterialSettingsModal();
        renderAll();
    }
}

function openClassModal(id) {
    editingClassId = id ? Number(id) : null;
    const item = id ? ELIPS.getClassById(id) : null;
    document.getElementById('classModalTitle').textContent = item ? 'Edit Kelas' : 'Buat Kelas';
    fillCourseSelect(document.getElementById('classCourse'), item ? item.courseId : null);
    document.getElementById('classCourse').disabled = !!item;
    document.getElementById('classId').value = item ? item.id : '';
    refreshClassVersions(item ? item.curriculumVersionId : null);
    document.getElementById('classVersion').disabled = !!item;
    fillBranchSelect(document.getElementById('classBranch'), item ? item.branchId : null);
    document.getElementById('className').value = item ? item.name : '';
    document.getElementById('classCode').value = item ? item.code : '';
    document.getElementById('classCode').disabled = !!item;
    fillInstructorSelect(document.getElementById('classInstructor'), item ? item.instructorId : null);
    document.getElementById('classStart').value = item ? (item.periodStart || '') : '';
    document.getElementById('classEnd').value = item ? (item.periodEnd || '') : '';
    document.getElementById('classQuota').value = item ? item.quota : 0;
    document.getElementById('classPlannedJp').value = item ? item.plannedJp : 0;
    document.getElementById('classLocation').value = item ? (item.location || '') : '';
    document.getElementById('classRoom').value = item ? (item.room || '') : '';
    document.getElementById('classSchedule').value = item ? (item.scheduleNote || '') : '';
    document.getElementById('classStatus').value = item && (item.status === 'open' || item.status === 'draft') ? item.status : 'draft';
    document.getElementById('classStatus').disabled = !!item;
    document.getElementById('classNotes').value = item ? (item.notes || '') : '';
    document.getElementById('classModal').style.display = 'flex';
}

function closeClassModal() {
    document.getElementById('classModal').style.display = 'none';
}

function saveClass() {
    const courseId = Number(document.getElementById('classCourse').value);
    const versionId = Number(document.getElementById('classVersion').value);
    if (!courseId || !versionId) {
        alert('Pilih kursus dan versi kurikulum.');
        return;
    }
    const data = {
        courseId: courseId,
        curriculumVersionId: versionId,
        branchId: document.getElementById('classBranch').value || null,
        name: document.getElementById('className').value.trim(),
        code: document.getElementById('classCode').value.trim(),
        instructorId: document.getElementById('classInstructor').value || null,
        periodStart: document.getElementById('classStart').value || null,
        periodEnd: document.getElementById('classEnd').value || null,
        quota: document.getElementById('classQuota').value,
        plannedJp: document.getElementById('classPlannedJp').value,
        location: document.getElementById('classLocation').value.trim(),
        room: document.getElementById('classRoom').value.trim(),
        scheduleNote: document.getElementById('classSchedule').value.trim(),
        status: document.getElementById('classStatus').value,
        notes: document.getElementById('classNotes').value.trim(),
        actorId: currentActorId()
    };
    const result = editingClassId
        ? ELIPS.updateClass(editingClassId, data)
        : ELIPS.createClass(data);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    closeClassModal();
    renderAll();
}

function changeClassStatus(id, status) {
    const result = ELIPS.setClassStatus(id, status);
    if (!result.ok) {
        alert(result.message);
        renderClasses();
        return;
    }
    ELIPS.toast(result.message);
    renderAll();
}

function removeClass(id) {
    if (!confirm('Hapus kelas ini? Kelas yang sudah memiliki peserta tidak dapat dihapus.')) return;
    const result = ELIPS.deleteClass(id);
    ELIPS.toast(result.message);
    renderAll();
}

// ---------- Instructor modal ----------
function openInstructorModal(classId) {
    const item = ELIPS.getClassById(classId);
    if (!item) return;
    document.getElementById('instructorClassId').value = item.id;
    fillInstructorSelect(document.getElementById('instructorSelect'), item.instructorId);
    document.getElementById('instructorReason').value = '';
    const histories = ELIPS.getClassInstructorHistoriesByClass(item.id);
    document.getElementById('instructorHistory').innerHTML = histories.length === 0
        ? 'Belum ada histori tentor.'
        : '<strong>Histori tentor:</strong><ul style="margin: 0.5rem 0 0 1.25rem;">' + histories.map(function (history) {
            const instructor = ELIPS.getUserById(history.instructorId);
            return '<li>' + ELIPS.escapeHtml(instructor ? instructor.name : 'Tentor #' + history.instructorId) +
                ' (' + ELIPS.escapeHtml(history.startedAt || '-') + ' s.d. ' + ELIPS.escapeHtml(history.endedAt || 'sekarang') + ')</li>';
        }).join('') + '</ul>';
    document.getElementById('instructorModal').style.display = 'flex';
}

function closeInstructorModal() {
    document.getElementById('instructorModal').style.display = 'none';
}

function saveInstructor() {
    const classId = Number(document.getElementById('instructorClassId').value);
    const instructorId = Number(document.getElementById('instructorSelect').value);
    if (!instructorId) {
        alert('Pilih tentor aktif.');
        return;
    }
    const result = ELIPS.assignClassInstructor(classId, instructorId,
        document.getElementById('instructorReason').value.trim(), currentActorId());
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    closeInstructorModal();
    renderAll();
}

// ---------- Roster modal ----------
function openRosterModal(classId) {
    activeRosterClassId = Number(classId);
    renderRoster();
    document.getElementById('rosterModal').style.display = 'flex';
}

function closeRosterModal() {
    activeRosterClassId = null;
    document.getElementById('rosterModal').style.display = 'none';
}

function renderRoster() {
    const item = ELIPS.getClassById(activeRosterClassId);
    if (!item) return;
    const enrollments = ELIPS.getClassEnrollments(item.id);
    document.getElementById('rosterTitle').textContent = 'Peserta — ' + item.name;
    const quota = Number(item.quota) || 0;
    const activeCount = ELIPS.activeClassEnrollmentCount(item.id);
    document.getElementById('rosterMeta').textContent = item.code + ' • ' +
        (quota === 0 ? 'Tanpa batas kuota' : activeCount + ' / ' + quota + ' peserta aktif');

    const enrolledIds = enrollments.map(function (e) { return Number(e.studentId); });
    const candidates = ELIPS.getUsersByRole('peserta').filter(function (user) {
        return user.status === 'aktif' && enrolledIds.indexOf(user.id) === -1;
    });
    document.getElementById('rosterCandidates').innerHTML = candidates.length === 0
        ? '<span style="color: var(--text-secondary); font-size: 0.875rem;">Semua peserta aktif sudah terdaftar atau belum ada peserta baru.</span>'
        : candidates.map(function (user) {
            return '<label style="display: flex; align-items: center; gap: 0.5rem;">' +
                '<input type="checkbox" value="' + user.id + '">' +
                '<span>' + ELIPS.escapeHtml(user.name) + ' <small style="color: var(--text-secondary);">' + ELIPS.escapeHtml(user.email) + '</small></span>' +
                '</label>';
        }).join('');

    const statuses = ['pending', 'active', 'completed', 'withdrawn', 'suspended', 'cancelled'];
    const tbody = document.getElementById('rosterTable');
    if (enrollments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-secondary);">Belum ada peserta di kelas ini.</td></tr>';
        return;
    }
    tbody.innerHTML = enrollments.map(function (enrollment) {
        const student = ELIPS.getUserById(enrollment.studentId);
        const options = statuses.map(function (status) {
            return '<option value="' + status + '" ' + (status === enrollment.status ? 'selected' : '') + '>' + status + '</option>';
        }).join('');
        return '<tr>' +
            '<td><div class="table-course-title">' + ELIPS.escapeHtml(student ? student.name : 'Peserta #' + enrollment.studentId) + '</div>' +
            '<div class="table-course-meta">' + ELIPS.escapeHtml(student ? student.email : '') + '</div></td>' +
            '<td><span style="color: var(--text-secondary);">' + ELIPS.escapeHtml(enrollment.enrolledAt || '-') + '</span></td>' +
            '<td><select class="form-input" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onchange="changeEnrollmentStatus(' + enrollment.id + ', this.value)">' + options + '</select></td>' +
            '<td><button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--danger);" onclick="removeRosterEnrollment(' + enrollment.id + ')"><i class="bi bi-trash"></i></button></td>' +
            '</tr>';
    }).join('');
}

function addRosterStudents() {
    const checked = Array.from(document.querySelectorAll('#rosterCandidates input[type="checkbox"]:checked'))
        .map(function (input) { return Number(input.value); });
    if (checked.length === 0) {
        alert('Pilih minimal satu peserta.');
        return;
    }
    const result = ELIPS.assignStudentsToClass(activeRosterClassId, checked, null);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    renderRoster();
    renderAll();
}

function changeEnrollmentStatus(id, status) {
    const result = ELIPS.setEnrollmentClassStatus(id, status);
    if (!result.ok) {
        alert(result.message);
    } else {
        ELIPS.toast(result.message);
    }
    renderRoster();
    renderAll();
}

function removeRosterEnrollment(id) {
    if (!confirm('Hapus enrollment peserta ini dari kelas?')) return;
    const result = ELIPS.deleteEnrollment(id);
    ELIPS.toast(result.message);
    renderRoster();
    renderAll();
}

// ---------- Session modal ----------
let editingSessionId = null;

function openSessionModal(id) {
    editingSessionId = id ? Number(id) : null;
    const item = id ? ELIPS.getClassSessionById(id) : null;
    const filterClassId = document.getElementById('sessionClassFilter').value;
    const classId = item ? item.classId : (filterClassId || '');
    const classItem = classId ? ELIPS.getClassById(classId) : null;

    document.getElementById('sessionModalTitle').textContent = item ? 'Edit Sesi Pertemuan' : 'Buat Sesi Pertemuan';
    fillSessionClassSelect('sessionClass', classId);
    document.getElementById('sessionClass').disabled = !!item;
    document.getElementById('sessionId').value = item ? item.id : '';
    document.getElementById('sessionNumber').value = item ? item.sessionNumber : '';
    document.getElementById('sessionDate').value = item ? (item.sessionDate || '') : new Date().toISOString().slice(0, 10);
    document.getElementById('sessionTitle').value = item ? (item.title || '') : '';
    fillSessionModuleSelect(classId, 'sessionModule', item ? item.moduleId : null);
    document.getElementById('sessionStart').value = item ? item.startTime : '09:00';
    document.getElementById('sessionEnd').value = item ? item.endTime : '11:00';
    document.getElementById('sessionLocation').value = item ? (item.location || '') : (classItem ? (classItem.location || '') : '');
    document.getElementById('sessionRoom').value = item ? (item.room || '') : (classItem ? (classItem.room || '') : '');
    fillInstructorSelect(document.getElementById('sessionInstructor'),
        item ? item.instructorId : (classItem ? classItem.instructorId : null));
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
        instructorId: document.getElementById('sessionInstructor').value || null,
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
    renderAll();
}

// ---------- Bulk schedule modal ----------
function openBulkSessionModal() {
    const filterClassId = document.getElementById('sessionClassFilter').value;
    fillSessionClassSelect('bulkClass', filterClassId || '');
    const classItem = filterClassId ? ELIPS.getClassById(filterClassId) : null;
    document.getElementById('bulkStartDate').value = new Date().toISOString().slice(0, 10);
    document.getElementById('bulkOccurrences').value = 8;
    Array.from(document.querySelectorAll('#bulkWeekdays input[type="checkbox"]')).forEach(function (input) {
        input.checked = Number(input.value) === new Date().getDay() || (new Date().getDay() === 0 && Number(input.value) === 7);
    });
    fillSessionModuleSelect(filterClassId || null, 'bulkModule', null);
    document.getElementById('bulkStart').value = '09:00';
    document.getElementById('bulkEnd').value = '11:00';
    document.getElementById('bulkLocation').value = classItem ? (classItem.location || '') : '';
    document.getElementById('bulkRoom').value = classItem ? (classItem.room || '') : '';
    fillInstructorSelect(document.getElementById('bulkInstructor'), classItem ? classItem.instructorId : null);
    document.getElementById('bulkPlannedJp').value = classItem && Number(classItem.plannedJp) > 0 ? classItem.plannedJp : 4;
    document.getElementById('bulkNotes').value = '';
    document.getElementById('bulkSessionModal').style.display = 'flex';
}

function closeBulkSessionModal() {
    document.getElementById('bulkSessionModal').style.display = 'none';
}

function saveBulkSessions() {
    const classId = Number(document.getElementById('bulkClass').value);
    if (!classId) {
        alert('Pilih kelas terlebih dahulu.');
        return;
    }
    const weekdays = Array.from(document.querySelectorAll('#bulkWeekdays input[type="checkbox"]:checked'))
        .map(function (input) { return Number(input.value); });
    if (weekdays.length === 0) {
        alert('Pilih minimal satu hari dalam seminggu.');
        return;
    }
    const data = {
        classId: classId,
        startDate: document.getElementById('bulkStartDate').value,
        weekdays: weekdays,
        occurrences: Number(document.getElementById('bulkOccurrences').value) || 1,
        moduleId: document.getElementById('bulkModule').value || null,
        startTime: document.getElementById('bulkStart').value,
        endTime: document.getElementById('bulkEnd').value,
        location: document.getElementById('bulkLocation').value.trim(),
        room: document.getElementById('bulkRoom').value.trim(),
        instructorId: document.getElementById('bulkInstructor').value || null,
        plannedJp: document.getElementById('bulkPlannedJp').value,
        status: 'scheduled',
        notes: document.getElementById('bulkNotes').value.trim()
    };
    const result = ELIPS.createBulkSessions(data);
    if (!result.ok) {
        alert(result.message);
        return;
    }
    ELIPS.toast(result.message);
    closeBulkSessionModal();
    document.getElementById('sessionClassFilter').value = String(classId);
    renderAll();
}

function cancelSession(id) {
    if (!confirm('Batalkan sesi ini? Sesi batal tidak dihitung dalam JP aktual dan rekap wajib.')) return;
    const result = ELIPS.setSessionStatus(id, 'cancelled');
    if (!result.ok) alert(result.message);
    else ELIPS.toast(result.message);
    renderAll();
}

function removeSession(id) {
    if (!confirm('Hapus sesi ini? Sesi yang sudah berlangsung atau memiliki presensi tidak dapat dihapus.')) return;
    const result = ELIPS.deleteSession(id);
    if (!result.ok) alert(result.message);
    else ELIPS.toast(result.message);
    renderAll();
}

// ---------- Attendance modal ----------
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
            const status = existing ? existing.status : (session.status === 'completed' ? 'present' : 'present');
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
    renderAll();
}

// ---------- Recap modal ----------
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

    if (!recap.students || recap.students.length === 0) {
        document.getElementById('recapBody').innerHTML = '<p style="color: var(--text-secondary);">Belum ada peserta aktif pada kelas ini.</p>' +
            '<p style="font-size: 0.875rem; color: var(--text-secondary);">' + ELIPS.escapeHtml(policyText) + '</p>';
        document.getElementById('recapModal').style.display = 'flex';
        return;
    }

    const rows = recap.students.map(function (student) {
        const percentage = Number(student.percentage) || 0;
        const badgeClass = percentage >= 75 ? 'badge-success' : (percentage >= 50 ? 'badge-warning' : 'badge');
        const details = (student.sessions || []).map(function (detail) {
            return '<tr>' +
                '<td>Pertemuan ' + ELIPS.escapeHtml(detail.sessionNumber) + '</td>' +
                '<td>' + ELIPS.escapeHtml(formatSessionDate(detail.sessionDate)) + '</td>' +
                '<td>' + ELIPS.escapeHtml(sessionStatusLabel(detail.sessionStatus)) + '</td>' +
                '<td>' + attendanceStatusBadge(detail.status) + '</td>' +
                '<td>' + (Number(detail.actualJp) || Number(detail.plannedJp) || 0) + ' JP</td>' +
                '</tr>';
        }).join('');
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
            '</tr>' +
            '<tr><td colspan="8" style="background: #f9fafb; padding: 0.5rem 1rem;">' +
            '<details><summary style="cursor: pointer; font-size: 0.8125rem; color: var(--text-secondary);">Detail per pertemuan</summary>' +
            '<table class="dashboard-table" style="margin-top: 0.5rem;"><thead><tr><th>Pertemuan</th><th>Tanggal</th><th>Status Sesi</th><th>Presensi</th><th>Bobot</th></tr></thead>' +
            '<tbody>' + (details || '<tr><td colspan="5">Belum ada sesi.</td></tr>') + '</tbody></table></details></td></tr>';
    }).join('');

    document.getElementById('recapBody').innerHTML =
        '<div style="overflow-x: auto;"><table class="dashboard-table">' +
        '<thead><tr><th>Peserta</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>JP Hadir / Wajib</th><th>Kehadiran</th></tr></thead>' +
        '<tbody>' + rows + '</tbody></table></div>' +
        '<p style="font-size: 0.8125rem; color: var(--text-secondary);">' + ELIPS.escapeHtml(policyText) + '</p>';
    document.getElementById('recapModal').style.display = 'flex';
}

function closeRecapModal() {
    document.getElementById('recapModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;
    renderAll();
});

