// Direktori: assets/js/pages/student/certificates.js
// ELIPS LMS - skrip halaman student/certificates.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const sessionUser = ELIPS.getSessionUser();
    if (sessionUser && sessionUser.role !== 'peserta') {
        window.location.href = ELIPS.dashboardPathForRole(sessionUser.role);
        return;
    }

    const student = ELIPS.getCurrentUser();
    if (!student) {
        window.location.href = '../login.php';
        return;
    }

    document.getElementById('studentName').textContent = student.name;
    document.getElementById('studentAvatar').textContent = (student.name || 'S').substring(0, 2).toUpperCase();

    const publishedSection = document.getElementById('publishedSection');
    const publishedList = document.getElementById('publishedList');
    const emptySection = document.getElementById('certificatesEmpty');
    const accountInfo = document.getElementById('studentAccountInfo');

    function sortByDate(list) {
        return list.slice().sort(function (a, b) {
            return new Date(b.approvedAt || b.issuedByInstructorAt || b.issuedAt || 0) -
                new Date(a.approvedAt || a.issuedByInstructorAt || a.issuedAt || 0);
        });
    }

    function buildCard(cert, courses) {
        const course = courses.find(function (c) { return Number(c.id) === Number(cert.courseId); }) || {};
        const grade = ELIPS.getGrade(cert.studentId, cert.courseId);
        const issuedDate = cert.approvedAt || cert.issuedByInstructorAt || cert.issuedAt;
        const scoreText = cert.score !== undefined && cert.score !== null ? ' (' + ELIPS.escapeHtml(cert.score) + ')' : '';
        const note = grade && grade.note ? grade.note : '';

        const card = document.createElement('div');
        card.className = 'cert-card';

        card.innerHTML = [
            '<div class="cert-card-preview"><span class="badge badge-success" style="position: absolute; top: 0.75rem; right: 0.75rem;"><i class="bi bi-patch-check-fill"></i> Terbit</span></div>',
            '<div class="cert-card-body">',
            '  <h3 class="cert-card-title">' + ELIPS.escapeHtml(course.title || cert.courseName || '-') + '</h3>',
            '  <div class="cert-card-meta">',
            '    <div><strong>No. Sertifikat:</strong> ' + ELIPS.escapeHtml(cert.certificateNumber || '-') + '</div>',
            '    <div><strong>Tanggal Terbit:</strong> ' + ELIPS.formatDate(issuedDate) + '</div>',
            '    <div><strong>Peserta:</strong> ' + ELIPS.escapeHtml(student.name) + '</div>',
            '    <div><strong>Grade:</strong> ' + ELIPS.escapeHtml(cert.grade || '-') + scoreText + '</div>',
            '    <div><strong>Instruktur:</strong> ' + ELIPS.escapeHtml(course.instructorName || '-') + '</div>',
            note ? '    <div><strong>Catatan:</strong> ' + ELIPS.escapeHtml(note) + '</div>' : '',
            '  </div>',
            '  <div class="cert-card-actions">',
            '    <a href="../certificate-view.php?no=' + encodeURIComponent(cert.certificateNumber) + '" class="btn btn-primary" style="flex: 1; padding: 0.5rem 0.875rem; font-size: 0.875rem;"><i class="bi bi-eye"></i> Lihat Sertifikat</a>',
            '    <a href="../verify-certificate.php?no=' + encodeURIComponent(cert.certificateNumber) + '" class="btn btn-outline" style="padding: 0.5rem 0.875rem; font-size: 0.875rem;" title="Verifikasi"><i class="bi bi-patch-check"></i></a>',
            '  </div>',
            '</div>'
        ].join('');
        return card;
    }

    // Program yang tagihannya belum lunas: sertifikat dikunci (server juga menolak
    // penerbitannya selama belum lunas).
    function lockedCourseIds() {
        if (!(ELIPS.api && ELIPS.api.enabled)) return [];
        const result = ELIPS.api.call('payment.mine', {});
        if (!result.ok || !result.data) return [];
        return (result.data.certificateLockedCourseIds || []).map(Number);
    }

    // Hanya sertifikat Publish yang boleh tampil di halaman siswa.
    function renderCertificates() {
        const locked = lockedCourseIds();
        const all = ELIPS.getPublishedCertificatesByStudent(student.id);
        const certificates = all.filter(function (cert) { return locked.indexOf(Number(cert.courseId)) === -1; });
        const courses = ELIPS.getCourses();

        const lockNotice = document.getElementById('certLockNotice');
        if (lockNotice) lockNotice.style.display = all.length > certificates.length || locked.length ? 'flex' : 'none';

        document.getElementById('statPublished').textContent = certificates.length;
        document.getElementById('statTotal').textContent = certificates.length;

        publishedSection.style.display = 'none';
        emptySection.style.display = 'none';
        publishedList.innerHTML = '';

        if (certificates.length === 0) {
            accountInfo.textContent = 'Masuk sebagai: ' + (student.name || '-') +
                (student.email ? ' (' + student.email + ')' : '') + '.';
            emptySection.style.display = 'block';
            return;
        }

        publishedSection.style.display = 'block';
        sortByDate(certificates).forEach(function (cert) {
            publishedList.appendChild(buildCard(cert, courses));
        });
    }

    renderCertificates();

    // Segarkan otomatis ketika Admin menyetujui sertifikat dari tab lain
    // atau ketika halaman dipulihkan dari cache (tombol Back).
    window.addEventListener('storage', function (event) {
        if (!event.key || event.key === ELIPS.KEYS.session) {
            window.location.reload();
            return;
        }
        if (event.key.indexOf('elips_') === 0) renderCertificates();
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) renderCertificates();
    });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) renderCertificates();
    });
});