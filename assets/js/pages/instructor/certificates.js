// ELIPS LMS - skrip halaman instructor/certificates.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
let currentInstructor = null;
let pendingRows = [];

function myCourses() {
    if (!currentInstructor) return [];
    return ELIPS.getInstructorCourses(currentInstructor.id);
}

document.addEventListener('DOMContentLoaded', function () {
    currentInstructor = ELIPS.requireRole('instruktur');
    if (!currentInstructor) return;

    const name = currentInstructor.name || 'Instruktur';
    document.getElementById('instructorName').textContent = name;
    document.getElementById('instructorAvatar').textContent = name.substring(0, 2).toUpperCase();

    const select = document.getElementById('certCourseFilter');
    select.innerHTML = '<option value="">Semua Kursus</option>';
    myCourses().forEach(function (course) {
        const option = document.createElement('option');
        option.value = String(course.id);
        option.textContent = course.title;
        select.appendChild(option);
    });

    const params = new URLSearchParams(window.location.search);
    const requestedCourseId = parseInt(params.get('course'), 10);
    if (requestedCourseId && myCourses().some(function (course) { return course.id === requestedCourseId; })) {
        select.value = String(requestedCourseId);
    }

    renderAll();
});

function selectedCourseId() {
    return parseInt(document.getElementById('certCourseFilter').value, 10) || null;
}

function renderAll() {
    const courseId = selectedCourseId();
    const courses = myCourses();
    const courseIds = courses.map(function (course) { return course.id; });
    const certificates = ELIPS.getCertificates().filter(function (cert) {
        return courseIds.indexOf(cert.courseId) !== -1 && (!courseId || cert.courseId === courseId);
    });

    document.getElementById('statDraft').textContent = certificates.filter(function (cert) { return cert.status === 'draft'; }).length;
    document.getElementById('statPublish').textContent = certificates.filter(function (cert) { return cert.status === 'publish'; }).length;
    document.getElementById('statTotal').textContent = certificates.length;

    renderCertificates(certificates);
    renderMissing(courseId);
}

function renderCertificates(certificates) {
    const tbody = document.getElementById('instructorCertsBody');
    const statusFilter = document.getElementById('certStatusFilter').value;
    const users = ELIPS.getUsers();
    const rows = certificates.filter(function (cert) {
        return statusFilter === 'all' || cert.status === statusFilter;
    }).sort(function (a, b) {
        return new Date(b.issuedByInstructorAt || b.issuedAt || 0) -
            new Date(a.issuedByInstructorAt || a.issuedAt || 0);
    });

    tbody.innerHTML = '';

    if (rows.length === 0) {
        tbody.innerHTML = [
            '<tr><td colspan="7" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-secondary);">',
            '  <i class="bi bi-award" style="display: block; font-size: 2.5rem; opacity: .45; margin-bottom: .75rem;"></i>',
            '  <div style="font-weight: 600; color: var(--text-primary); margin-bottom: .25rem;">Belum ada e-sertifikat</div>',
            '  <div style="font-size: .875rem;">Beri nilai peserta terlebih dahulu melalui halaman Peserta &amp; Penilaian.</div>',
            '</td></tr>'
        ].join('');
        return;
    }

    rows.forEach(function (cert) {
        const student = users.find(function (user) { return user.id === cert.studentId; }) || {};
        const isDraft = cert.status !== 'publish';
        const tr = document.createElement('tr');
        tr.innerHTML = [
            '<td><span style="font-family: monospace; font-weight: 600;">' + ELIPS.escapeHtml(cert.certificateNumber || '-') + '</span></td>',
            '<td>',
            '  <div class="table-course">',
            '    <div class="dashboard-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;">' + ELIPS.escapeHtml((student.name || 'P').substring(0, 2).toUpperCase()) + '</div>',
            '    <div class="table-course-info">',
            '      <div class="table-course-title">' + ELIPS.escapeHtml(student.name || '-') + '</div>',
            '      <div class="table-course-meta">' + ELIPS.escapeHtml(student.email || '-') + '</div>',
            '    </div>',
            '  </div>',
            '</td>',
            '<td><span>' + ELIPS.escapeHtml((ELIPS.getCourseById(cert.courseId) || {}).title || '-') + '</span></td>',
            '<td><span>' + ELIPS.escapeHtml(cert.grade || '-') + (cert.score !== undefined && cert.score !== null ? ' (' + ELIPS.escapeHtml(cert.score) + ')' : '') + '</span></td>',
            '<td><span style="color: var(--text-secondary);">' + ELIPS.formatDate(cert.issuedByInstructorAt || cert.issuedAt) + '</span></td>',
            '<td><span class="badge ' + (isDraft ? 'badge-warning' : 'badge-success') + '"><i class="bi ' + (isDraft ? 'bi-hourglass-split' : 'bi-patch-check-fill') + '"></i> ' + (isDraft ? 'Menunggu Admin' : 'Terbit') + '</span></td>',
            '<td>',
            isDraft
                ? '<span class="table-course-meta"><i class="bi bi-lock"></i> Menunggu persetujuan Admin</span>'
                : '<div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">' +
                  '<a href="../certificate-view.php?no=' + encodeURIComponent(cert.certificateNumber) + '" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;"><i class="bi bi-eye"></i> Lihat</a>' +
                  '<a href="../verify-certificate.php?no=' + encodeURIComponent(cert.certificateNumber) + '" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" title="Verifikasi"><i class="bi bi-patch-check"></i></a>' +
                  '</div>',
            '</td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

function renderMissing(courseId) {
    const section = document.getElementById('missingSection');
    const tbody = document.getElementById('missingCertBody');
    pendingRows = ELIPS.getInstructorStudentRows(currentInstructor.id, courseId).filter(function (row) {
        return !!row.grade && !row.certificate;
    });

    tbody.innerHTML = '';
    if (pendingRows.length === 0) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';
    pendingRows.forEach(function (row) {
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
            '<td><span>' + ELIPS.escapeHtml(row.grade.score) + ' (' + ELIPS.escapeHtml(row.grade.grade) + ')</span></td>',
            '<td>',
            '  <div class="progress-inline">',
            '    <div class="progress-inline-bar"><div class="progress-inline-fill" style="width: ' + row.progress.percentage + '%"></div></div>',
            '    <span class="progress-inline-text">' + row.progress.percentage + '%</span>',
            '  </div>',
            '</td>',
            '<td>',
            '  <button type="button" class="btn btn-primary" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" onclick="createDraftCertificate(' + row.enrollment.id + ')">',
            '    <i class="bi bi-file-earmark-plus"></i> Ajukan Sertifikat',
            '  </button>',
            '</td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

function createDraftCertificate(enrollmentId) {
    const row = pendingRows.find(function (item) { return item.enrollment.id === enrollmentId; });
    if (!row) return;
    if (!confirm('Ajukan e-sertifikat Draft untuk ' + (row.student.name || 'peserta') + '?')) return;

    const result = ELIPS.createDraftCertificateForEnrollment(enrollmentId, currentInstructor.id);
    ELIPS.toast(result.message);
    renderAll();
}

