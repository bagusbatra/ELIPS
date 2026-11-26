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

    function fileUrl(id, format, side) {
        return ELIPS.api.base + '?action=certificate.file&id=' + id + '&format=' + format + (side ? '&side=' + side : '');
    }

    function buildCard(cert) {
        const card = document.createElement('div');
        card.className = 'cert-card';
        const issued = cert.issuedAt ? ELIPS.formatDate(cert.issuedAt) : '-';
        card.innerHTML = [
            '<div class="cert-card-preview"><span class="badge badge-success" style="position: absolute; top: 0.75rem; right: 0.75rem;"><i class="bi bi-patch-check-fill"></i> Terbit</span></div>',
            '<div class="cert-card-body">',
            '  <h3 class="cert-card-title">' + ELIPS.escapeHtml(cert.course || '-') + '</h3>',
            '  <div class="cert-card-meta">',
            '    <div><strong>No. Sertifikat:</strong> ' + ELIPS.escapeHtml(cert.number || '-') + '</div>',
            '    <div><strong>Tanggal Terbit:</strong> ' + issued + '</div>',
            '    <div><strong>Predikat:</strong> ' + ELIPS.escapeHtml(cert.predicateLabel || '-') + ' (' + ELIPS.escapeHtml(String(Math.round(cert.average || 0))) + ')</div>',
            '    <div><strong>Berlaku s/d:</strong> ' + ELIPS.escapeHtml(cert.validText || '-') + '</div>',
            '  </div>',
            '  <div class="cert-card-actions" style="flex-wrap: wrap;">',
            '    <a href="../certificate-view.php?id=' + cert.id + '" class="btn btn-primary" style="flex: 1; padding: 0.5rem 0.875rem; font-size: 0.875rem;"><i class="bi bi-eye"></i> Lihat</a>',
            '    <a href="' + fileUrl(cert.id, 'pdf') + '" class="btn btn-outline" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;"><i class="bi bi-file-earmark-pdf"></i> PDF</a>',
            '    <a href="' + fileUrl(cert.id, 'jpg', 'front') + '" class="btn btn-outline" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;"><i class="bi bi-file-earmark-image"></i> JPG</a>',
            '    <a href="' + fileUrl(cert.id, 'png', 'front') + '" class="btn btn-outline" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;"><i class="bi bi-file-earmark-image"></i> PNG</a>',
            '    <a href="../verify-certificate.php?c=' + encodeURIComponent(cert.token) + '" class="btn btn-outline" style="padding: 0.5rem 0.875rem; font-size: 0.875rem;" title="Verifikasi"><i class="bi bi-patch-check"></i></a>',
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

    function renderCertificates() {
        const locked = lockedCourseIds();
        const result = ELIPS.api && ELIPS.api.enabled ? ELIPS.api.call('cert.mine', {}) : { ok: false };
        const all = result.ok && result.data ? result.data.certificates : [];
        // Nilai kursus terkunci (belum lunas) disembunyikan; server tetap yang memutuskan penerbitan.
        const certificates = all;

        const lockNotice = document.getElementById('certLockNotice');
        if (lockNotice) lockNotice.style.display = locked.length ? 'flex' : 'none';

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
        certificates.forEach(function (cert) {
            publishedList.appendChild(buildCard(cert));
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