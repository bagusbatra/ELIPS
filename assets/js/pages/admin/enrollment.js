// ELIPS LMS - skrip halaman admin/enrollment.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
function renderEnrollments() {
    const tbody = document.getElementById('enrollmentTable');
    const enrollments = ELIPS.getEnrollments();
    const users = ELIPS.getUsers();
    const courses = ELIPS.getCourses();
    tbody.innerHTML = '';

    if (enrollments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-secondary);">Belum ada penugasan siswa</td></tr>';
        updateStats();
        populateFilters();
        return;
    }

    enrollments.slice().sort(function (a, b) { return Number(b.id) - Number(a.id); }).forEach(function (enr) {
        const student = users.find(function (u) { return u.id === enr.studentId; }) || {};
        const course = courses.find(function (c) { return c.id === enr.courseId; }) || {};
        const isActive = enr.status === 'active';
        const tr = document.createElement('tr');
        tr.setAttribute('data-course', enr.courseId);
        tr.setAttribute('data-status', enr.status);
        tr.innerHTML = [
            '<td>',
            '  <div class="table-course">',
            '    <div class="dashboard-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">' + ELIPS.escapeHtml((student.name || 'S').substring(0, 2).toUpperCase()) + '</div>',
            '    <div class="table-course-info">',
            '      <div class="table-course-title">' + ELIPS.escapeHtml(student.name || '-') + '</div>',
            '      <div class="table-course-meta">' + ELIPS.escapeHtml(student.email || '-') + '</div>',
            '    </div>',
            '  </div>',
            '</td>',
            '<td><span>' + ELIPS.escapeHtml(course.title || '-') + '</span></td>',
            '<td><span>' + ELIPS.escapeHtml(course.instructorName || '-') + '</span></td>',
            '<td><span style="color: var(--text-secondary);">' + ELIPS.escapeHtml(enr.enrolledAt) + '</span></td>',
            '<td><span>' + (enr.enrolledBy === 'admin' ? 'Admin' : ELIPS.escapeHtml(enr.enrolledBy || '-')) + '</span></td>',
            '<td><span class="badge ' + (isActive ? 'badge-success' : 'badge-warning') + '">' + (isActive ? 'Aktif' : 'Dihentikan') + '</span></td>',
            '<td>',
            '  <div style="display: flex; gap: 0.5rem;">',
            '    <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="' + (isActive ? 'Hentikan' : 'Aktifkan') + '" onclick="toggleEnrollment(' + enr.id + ')"><i class="bi ' + (isActive ? 'bi-pause-circle' : 'bi-play-circle') + '"></i></button>',
            '    <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--danger);" onclick="removeEnrollment(' + enr.id + ')"><i class="bi bi-trash"></i></button>',
            '  </div>',
            '</td>'
        ].join('');
        tbody.appendChild(tr);
    });

    updateStats();
    populateFilters();
}

function updateStats() {
    const enrollments = ELIPS.getEnrollments();
    document.getElementById('statTotal').textContent = enrollments.length;
    document.getElementById('statAktif').textContent = enrollments.filter(function (e) { return e.status === 'active'; }).length;
    document.getElementById('statSiswa').textContent = new Set(enrollments.map(function (e) { return e.studentId; })).size;
    document.getElementById('statKursus').textContent = new Set(enrollments.map(function (e) { return e.courseId; })).size;
}

function populateFilters() {
    const filterCourse = document.getElementById('filterCourse');
    const current = filterCourse.value;
    filterCourse.innerHTML = '<option value="all">Semua Kursus</option>';
    ELIPS.getCourses().forEach(function (c) {
        filterCourse.innerHTML += '<option value="' + c.id + '">' + ELIPS.escapeHtml(c.title) + '</option>';
    });
    filterCourse.value = current || 'all';
}

function openEnrollModal() {
    const studentSelect = document.getElementById('assignStudent');
    const students = ELIPS.getUsersByRole('peserta').filter(function (u) { return u.status === 'aktif'; });
    studentSelect.innerHTML = '<option value="">-- Pilih Siswa --</option>';
    students.forEach(function (s) {
        studentSelect.innerHTML += '<option value="' + s.id + '">' + ELIPS.escapeHtml(s.name) + ' (' + ELIPS.escapeHtml(s.email) + ')</option>';
    });
    document.getElementById('assignDate').value = new Date().toISOString().split('T')[0];
    refreshAssignCourses();
    document.getElementById('enrollModal').style.display = 'flex';
}

function refreshAssignCourses() {
    const studentId = parseInt(document.getElementById('assignStudent').value, 10);
    const container = document.getElementById('assignCourses');
    const courses = ELIPS.getCourses();
    const existing = studentId ? ELIPS.getEnrollmentsByStudent(studentId) : [];

    if (courses.length === 0) {
        container.innerHTML = '<span style="color: var(--text-secondary);">Belum ada kursus.</span>';
        return;
    }

    container.innerHTML = courses.map(function (c) {
        const already = existing.some(function (e) { return e.courseId === c.id; });
        return '<label style="display: flex; align-items: flex-start; gap: 0.5rem; ' + (already ? 'opacity: .6;' : '') + '">' +
            '<input type="checkbox" value="' + c.id + '" ' + (already ? 'checked disabled' : '') + ' style="margin-top: 0.25rem;">' +
            '<span>' + ELIPS.escapeHtml(c.title) +
            ' <span class="badge ' + (c.status === 'published' ? 'badge-success' : 'badge-warning') + '" style="font-size: 0.6875rem;">' + (c.status === 'published' ? 'Published' : 'Draft') + '</span>' +
            (already ? ' <small style="color: var(--success);">(sudah ditugaskan)</small>' : '') +
            '</span></label>';
    }).join('');
}

function closeEnrollModal() {
    document.getElementById('enrollModal').style.display = 'none';
}

function saveAssignment() {
    const studentId = parseInt(document.getElementById('assignStudent').value, 10);
    const date = document.getElementById('assignDate').value || new Date().toISOString().split('T')[0];
    const checked = Array.from(document.querySelectorAll('#assignCourses input[type="checkbox"]:checked:not(:disabled)'))
        .map(function (input) { return parseInt(input.value, 10); });

    if (!studentId || checked.length === 0) {
        alert('Pilih siswa dan minimal satu kursus.');
        return;
    }

    const result = ELIPS.assignStudentToCourses(studentId, checked, date);
    if (!result.ok) {
        alert(result.message);
        return;
    }

    ELIPS.toast(result.message);
    closeEnrollModal();
    renderEnrollments();
}

function toggleEnrollment(id) {
    const enrollment = ELIPS.getEnrollments().find(function (e) { return e.id === id; });
    if (!enrollment) return;
    const result = ELIPS.setEnrollmentStatus(id, enrollment.status === 'active' ? 'dihentikan' : 'active');
    ELIPS.toast(result.message);
    renderEnrollments();
}

function removeEnrollment(id) {
    if (!confirm('Hapus penugasan ini? Progres, nilai, dan sertifikat siswa pada kursus tersebut juga akan dihapus.')) return;
    const result = ELIPS.deleteEnrollment(id);
    ELIPS.toast(result.message);
    renderEnrollments();
}

function filterEnrollment() {
    const courseFilter = document.getElementById('filterCourse').value;
    const statusFilter = document.getElementById('filterStatus').value;
    const searchFilter = document.getElementById('searchEnrollment').value.toLowerCase();

    document.querySelectorAll('#enrollmentTable tr').forEach(function (row) {
        const courseId = row.getAttribute('data-course');
        const status = row.getAttribute('data-status');
        const nameEl = row.querySelector('.table-course-title');
        const name = nameEl ? nameEl.textContent.toLowerCase() : '';

        let show = true;
        if (courseFilter !== 'all' && courseId !== courseFilter) show = false;
        if (statusFilter !== 'all' && status !== statusFilter) show = false;
        if (searchFilter && !name.includes(searchFilter)) show = false;
        row.style.display = show ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;
    renderEnrollments();
});

