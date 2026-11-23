// ELIPS LMS - skrip halaman instructor/profile.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
let currentInstructor = null;

document.addEventListener('DOMContentLoaded', function () {
    currentInstructor = ELIPS.requireRole('instruktur');
    if (!currentInstructor) return;

    fillProfile(currentInstructor);
    fillStats(currentInstructor);

    document.getElementById('profileForm').addEventListener('submit', saveProfile);
    document.getElementById('resetProfileBtn').addEventListener('click', function () {
        fillProfile(currentInstructor);
        ELIPS.toast('Perubahan dibatalkan');
    });
});

function fillProfile(instructor) {
    const name = instructor.name || 'Instruktur';
    const initials = name.substring(0, 2).toUpperCase();
    document.getElementById('instructorName').textContent = name;
    document.getElementById('instructorAvatar').textContent = initials;
    document.getElementById('profileAvatar').textContent = initials;
    document.getElementById('profileName').textContent = name;
    document.getElementById('profileEmail').textContent = instructor.email || '-';
    document.getElementById('profileId').textContent = '#' + instructor.id;
    document.getElementById('profilePhone').textContent = instructor.phone || '-';
    document.getElementById('profileCreatedAt').textContent = ELIPS.formatDate(instructor.createdAt);
    document.getElementById('profileStatus').innerHTML = instructor.status === 'aktif'
        ? '<i class="bi bi-check-circle"></i> Aktif'
        : '<i class="bi bi-slash-circle"></i> Nonaktif';

    document.getElementById('profileNameInput').value = instructor.name || '';
    document.getElementById('profileEmailInput').value = instructor.email || '';
    document.getElementById('profilePhoneInput').value = instructor.phone || '';
}

function fillStats(instructor) {
    const summary = ELIPS.getInstructorSummary(instructor.id);
    document.getElementById('statCourses').textContent = summary.totalCourses;
    document.getElementById('statStudents').textContent = summary.totalStudents;
    document.getElementById('statLessons').textContent = summary.totalLessons;
    document.getElementById('statCertificates').textContent = summary.publishedCertificates;

    const tbody = document.getElementById('profileCoursesBody');
    tbody.innerHTML = '';

    if (summary.courses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-secondary);">Belum ada kursus yang ditugaskan kepada Anda.</td></tr>';
        return;
    }

    summary.courses.forEach(function (course) {
        const modules = ELIPS.getModulesByCourse(course.id).length;
        const lessons = ELIPS.getLessonsByCourse(course.id).length;
        const participants = ELIPS.getEnrollmentsByCourse(course.id).filter(function (enrollment) {
            return enrollment.status === 'active';
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
            '<td><span>' + participants + ' Peserta</span></td>',
            '<td><span>' + modules + ' Modul &bull; ' + lessons + ' Pelajaran</span></td>',
            '<td><span class="badge ' + (published ? 'badge-success' : 'badge-warning') + '">' + (published ? 'Dipublikasi' : 'Draft') + '</span></td>',
            '<td><a href="manage-course.php?course=' + course.id + '" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;"><i class="bi bi-folder2-open"></i> Kelola</a></td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

function saveProfile(event) {
    event.preventDefault();
    if (!currentInstructor) return;

    const name = document.getElementById('profileNameInput').value.trim();
    const phone = document.getElementById('profilePhoneInput').value.trim();

    if (name.length < 2) {
        ELIPS.toast('Nama lengkap minimal 2 karakter');
        return;
    }

    const result = ELIPS.updateUser(currentInstructor.id, { name: name, phone: phone });
    if (!result.ok) {
        ELIPS.toast(result.message);
        return;
    }

    currentInstructor = result.data;
    fillProfile(currentInstructor);
    fillStats(currentInstructor);
    ELIPS.toast('Profil berhasil diperbarui');
}

