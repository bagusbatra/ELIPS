// ELIPS LMS - skrip halaman student/profile.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
let currentStudent = null;

document.addEventListener('DOMContentLoaded', function () {
    const sessionUser = ELIPS.getSessionUser();
    if (sessionUser && sessionUser.role !== 'peserta') {
        window.location.href = ELIPS.dashboardPathForRole(sessionUser.role);
        return;
    }

    currentStudent = ELIPS.getCurrentUser();
    if (!currentStudent) {
        window.location.href = '../login.php';
        return;
    }

    fillProfile(currentStudent);
    fillStats(currentStudent);

    document.getElementById('profileForm').addEventListener('submit', saveProfile);
    document.getElementById('resetProfileBtn').addEventListener('click', function () {
        fillProfile(currentStudent);
        ELIPS.toast('Perubahan dibatalkan');
    });
});

function fillProfile(student) {
    const initials = (student.name || 'S').substring(0, 2).toUpperCase();
    document.getElementById('studentName').textContent = student.name;
    document.getElementById('studentAvatar').textContent = initials;
    document.getElementById('profileAvatar').textContent = initials;
    document.getElementById('profileName').textContent = student.name;
    document.getElementById('profileEmail').textContent = student.email || '-';
    document.getElementById('profileId').textContent = '#' + student.id;
    document.getElementById('profilePhone').textContent = student.phone || '-';
    document.getElementById('profileCreatedAt').textContent = ELIPS.formatDate(student.createdAt);
    document.getElementById('profileStatus').innerHTML = student.status === 'aktif'
        ? '<i class="bi bi-check-circle"></i> Aktif'
        : '<i class="bi bi-slash-circle"></i> Nonaktif';

    document.getElementById('profileNameInput').value = student.name || '';
    document.getElementById('profileEmailInput').value = student.email || '';
    document.getElementById('profilePhoneInput').value = student.phone || '';
}

function fillStats(student) {
    const overview = ELIPS.getStudentCourseOverview(student.id).filter(function (item) {
        return item.enrollment.status === 'active';
    });
    const completedLessons = overview.reduce(function (sum, item) { return sum + item.progress.completed; }, 0);
    const publishedCerts = ELIPS.getPublishedCertificatesByStudent(student.id);

    document.getElementById('statCourses').textContent = overview.length;
    document.getElementById('statLessons').textContent = completedLessons;
    document.getElementById('statCertificates').textContent = publishedCerts.length;
}

function saveProfile(event) {
    event.preventDefault();
    if (!currentStudent) return;

    const name = document.getElementById('profileNameInput').value.trim();
    const phone = document.getElementById('profilePhoneInput').value.trim();

    if (name.length < 2) {
        ELIPS.toast('Nama lengkap minimal 2 karakter');
        return;
    }

    const result = ELIPS.updateUser(currentStudent.id, { name: name, phone: phone });
    if (!result.ok) {
        ELIPS.toast(result.message);
        return;
    }

    currentStudent = result.data;
    fillProfile(currentStudent);
    ELIPS.toast('Profil berhasil diperbarui');
}

