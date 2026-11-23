// ELIPS LMS - skrip halaman verify-certificate.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const verifyBtn = document.getElementById('verifyBtn');
    const verifyInput = document.getElementById('verifyInput');
    const verifyResult = document.getElementById('verifyResult');

    function showInvalid(message) {
        verifyResult.className = 'verify-result show invalid';
        verifyResult.innerHTML = [
            '<div class="verify-result-header">',
            '  <i class="bi bi-x-circle-fill" style="font-size: 1.25rem;"></i>',
            '  <span>Sertifikat Tidak Valid</span>',
            '</div>',
            '<div class="verify-result-details">',
            '  <div>' + ELIPS.escapeHtml(message) + '</div>',
            '</div>'
        ].join('');
    }

    function verifyCertificate() {
        const certNumber = verifyInput.value.trim().toUpperCase();
        if (!certNumber) {
            ELIPS.toast('Masukkan nomor sertifikat terlebih dahulu');
            return;
        }

        const cert = ELIPS.getCertificateByNumber(certNumber);
        if (!cert || cert.status !== 'publish') {
            showInvalid(cert
                ? 'Sertifikat ditemukan namun masih berstatus Draft (belum disetujui Admin). Sertifikat Draft tidak dapat diverifikasi.'
                : 'Nomor sertifikat tidak ditemukan atau tidak valid.');
            ELIPS.toast('Sertifikat tidak valid');
            return;
        }

        const student = ELIPS.getUserById(cert.studentId) || {};
        const course = ELIPS.getCourseById(cert.courseId) || {};
        const instructor = ELIPS.getUserById(cert.instructorId || course.instructorId);
        const issuedDate = cert.approvedAt || cert.issuedByInstructorAt || cert.issuedAt;

        verifyResult.className = 'verify-result show valid';
        verifyResult.innerHTML = [
            '<div class="verify-result-header">',
            '  <i class="bi bi-check-circle-fill" style="font-size: 1.25rem;"></i>',
            '  <span>Sertifikat Valid</span>',
            '</div>',
            '<div class="verify-result-details">',
            '  <div><strong>Nomor Sertifikat:</strong> ' + ELIPS.escapeHtml(cert.certificateNumber) + '</div>',
            '  <div><strong>Nama Peserta:</strong> ' + ELIPS.escapeHtml(student.name || '-') + '</div>',
            '  <div><strong>Nama Kursus:</strong> ' + ELIPS.escapeHtml(course.title || cert.courseName || '-') + '</div>',
            '  <div><strong>Instruktur:</strong> ' + ELIPS.escapeHtml(instructor ? instructor.name : (course.instructorName || '-')) + '</div>',
            '  <div><strong>Grade:</strong> ' + ELIPS.escapeHtml(cert.grade || '-') + '</div>',
            '  <div><strong>Tanggal Terbit:</strong> ' + ELIPS.formatDate(issuedDate) + '</div>',
            '  <div><strong>Status:</strong> Terbit &amp; Valid</div>',
            '  <div style="margin-top: 0.75rem;"><a class="btn btn-primary" href="certificate-view.php?no=' + encodeURIComponent(cert.certificateNumber) + '"><i class="bi bi-eye"></i> Lihat Sertifikat</a></div>',
            '</div>'
        ].join('');
        ELIPS.toast('Sertifikat berhasil diverifikasi');
    }

    verifyBtn.addEventListener('click', verifyCertificate);
    verifyInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') verifyCertificate();
    });

    const requestedNumber = new URLSearchParams(window.location.search).get('no');
    if (requestedNumber) {
        verifyInput.value = requestedNumber;
        verifyCertificate();
    }
});

