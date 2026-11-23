// ELIPS LMS - skrip halaman certificate-view.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    const certNumber = params.get('no') || params.get('cert') || params.get('certificate');
    const cert = certNumber ? ELIPS.getCertificateByNumber(certNumber) : null;
    const frame = document.getElementById('certFrame');
    const unavailable = document.getElementById('certUnavailable');
    const actions = document.getElementById('certActions');

    if (!cert || cert.status !== 'publish') {
        frame.style.display = 'none';
        actions.style.display = 'none';
        unavailable.style.display = 'block';
        document.getElementById('certUnavailableMessage').textContent = cert
            ? 'Sertifikat ini masih berstatus Draft dan belum disetujui Admin. Sertifikat hanya dapat dilihat setelah berstatus Publish.'
            : 'Nomor sertifikat tidak ditemukan. Pastikan nomor yang dimasukkan benar.';
        return;
    }

    const student = ELIPS.getUserById(cert.studentId) || {};
    const course = ELIPS.getCourseById(cert.courseId) || {};
    const instructor = ELIPS.getUserById(cert.instructorId || course.instructorId);
    const admin = ELIPS.getUserById(cert.approvedByAdminId);
    const issuedDate = cert.approvedAt || cert.issuedByInstructorAt || cert.issuedAt;

    document.getElementById('certName').textContent = student.name || '-';
    document.getElementById('certCourse').textContent = course.title || cert.courseName || '-';
    document.getElementById('certGrade').textContent = cert.grade || '-';
    document.getElementById('certInstructor').textContent = instructor ? instructor.name : (course.instructorName || '-');
    document.getElementById('certAdmin').textContent = admin ? admin.name : 'Administrator';
    document.getElementById('certNumber').textContent =
        'No. Sertifikat: ' + cert.certificateNumber + ' • Terbit: ' + ELIPS.formatDate(issuedDate);

    document.getElementById('downloadCertBtn').addEventListener('click', function () {
        window.print();
    });
});

