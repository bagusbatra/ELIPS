// ELIPS LMS - skrip halaman admin/certificates.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
function renderAdminCerts() {
    const tbody = document.getElementById('adminCertsTable');
    const certificates = ELIPS.getCertificates();
    const users = ELIPS.getUsers();
    const courses = ELIPS.getCourses();

    document.getElementById('statDraft').textContent = certificates.filter(function (c) { return c.status === 'draft'; }).length;
    document.getElementById('statPublish').textContent = certificates.filter(function (c) { return c.status === 'publish'; }).length;
    document.getElementById('statTotalCert').textContent = certificates.length;

    tbody.innerHTML = '';
    if (certificates.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-secondary);">Belum ada sertifikat. Sertifikat dibuat otomatis saat instruktur memberi nilai.</td></tr>';
        return;
    }

    certificates.slice().sort(function (a, b) {
        return new Date(b.issuedByInstructorAt || b.issuedAt || 0) - new Date(a.issuedByInstructorAt || a.issuedAt || 0);
    }).forEach(function (cert) {
        const student = users.find(function (u) { return u.id === cert.studentId; }) || {};
        const course = courses.find(function (c) { return c.id === cert.courseId; }) || {};
        const isDraft = cert.status === 'draft';
        const tr = document.createElement('tr');
        tr.setAttribute('data-status', cert.status);
        tr.innerHTML = [
            '<td><span style="font-family: monospace; font-weight: 600;">' + ELIPS.escapeHtml(cert.certificateNumber) + '</span></td>',
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
            '<td><span>' + ELIPS.escapeHtml(cert.grade !== undefined && cert.grade !== null ? cert.grade : '-') + (cert.score !== undefined && cert.score !== null ? ' (' + ELIPS.escapeHtml(cert.score) + ')' : '') + '</span></td>',
            '<td><span style="color: var(--text-secondary);">' + ELIPS.formatDate(cert.issuedByInstructorAt || cert.issuedAt) + '</span></td>',
            '<td><span class="badge ' + (isDraft ? 'badge-warning' : 'badge-success') + '">' + (isDraft ? 'Menunggu Persetujuan (Draft)' : 'Terbit (Publish)') + '</span>' +
            (!isDraft && cert.approvedAt ? '<div class="table-course-meta">Disetujui ' + ELIPS.formatDate(cert.approvedAt) + '</div>' : '') + '</td>',
            '<td>',
            '  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">',
            isDraft
                ? '    <button class="btn btn-primary" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" onclick="approveCert(' + cert.id + ')"><i class="bi bi-check-lg"></i> Setujui &amp; Terbitkan</button>'
                : '    <a href="../certificate-view.php?no=' + encodeURIComponent(cert.certificateNumber) + '" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;"><i class="bi bi-eye"></i> Lihat</a>',
            '  </div>',
            '</td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

function approveCert(id) {
    if (!confirm('Setujui dan terbitkan e-sertifikat ini? Sertifikat akan berstatus Publish dan muncul di halaman siswa.')) return;

    const admin = ELIPS.getCurrentUser();
    const result = ELIPS.approveCertificate(id, admin ? admin.id : 1);
    ELIPS.toast(result.message);
    renderAdminCerts();
}

function filterAdminCerts(status) {
    const rows = document.querySelectorAll('#adminCertsTable tr');
    const buttons = document.querySelectorAll('#filterAllCert, #filterDraftCert, #filterPublishCert');
    buttons.forEach(function (btn) {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-ghost');
    });

    const activeId = status === 'all' ? 'filterAllCert' : status === 'draft' ? 'filterDraftCert' : 'filterPublishCert';
    document.getElementById(activeId).classList.remove('btn-ghost');
    document.getElementById(activeId).classList.add('btn-primary');

    rows.forEach(function (row) {
        if (!row.getAttribute('data-status')) {
            row.style.display = status === 'all' ? '' : 'none';
            return;
        }
        row.style.display = status === 'all' || row.getAttribute('data-status') === status ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;
    renderAdminCerts();
});

