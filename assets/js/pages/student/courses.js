// ELIPS LMS - skrip halaman student/courses.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
let studentOverview = [];

const stateMap = {
    'locked': { label: 'Belum Dibuka', className: 'badge-warning', icon: 'bi-lock-fill', group: 'active' },
    'certified': { label: 'Sertifikat Terbit', className: 'badge-success', icon: 'bi-patch-check-fill', group: 'completed' },
    'completed': { label: 'Selesai', className: 'badge-success', icon: 'bi-check-circle-fill', group: 'completed' },
    'awaiting-grade': { label: 'Menunggu Penilaian', className: 'badge-warning', icon: 'bi-hourglass-split', group: 'completed' },
    'learning': { label: 'Sedang Belajar', className: 'badge-warning', icon: 'bi-play-circle', group: 'active' },
    'not-started': { label: 'Belum Dimulai', className: 'badge-primary', icon: 'bi-circle', group: 'active' }
};

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

    studentOverview = ELIPS.getStudentCourseOverview(student.id);
    const activeOverview = studentOverview.filter(function (item) { return item.enrollment.status === 'active'; });
    const completedCourses = activeOverview.filter(function (item) { return item.isComplete; });
    const completedLessons = activeOverview.reduce(function (sum, item) { return sum + item.progress.completed; }, 0);
    const publishedCerts = ELIPS.getPublishedCertificatesByStudent(student.id);

    document.getElementById('statActiveCourses').textContent = activeOverview.length;
    document.getElementById('statCompletedCourses').textContent = completedCourses.length;
    document.getElementById('statCompletedLessons').textContent = completedLessons;
    document.getElementById('statCertificates').textContent = publishedCerts.length;

    renderCourseRows();
});

function renderCourseRows() {
    const tbody = document.getElementById('studentCoursesBody');
    const filter = document.getElementById('courseFilter').value;
    const search = document.getElementById('courseSearch').value.trim().toLowerCase();

    const filtered = studentOverview.filter(function (item) {
        const course = item.course;
        const isActive = item.enrollment.status === 'active';
        const state = stateMap[item.state] || stateMap['not-started'];
        const group = isActive ? state.group : 'inactive';

        if (filter === 'active' && group !== 'active') return false;
        if (filter === 'completed' && group !== 'completed') return false;
        if (filter === 'inactive' && group !== 'inactive') return false;

        if (search) {
            const haystack = (course.title + ' ' + (course.instructorName || '') + ' ' + (course.category || '')).toLowerCase();
            if (haystack.indexOf(search) === -1) return false;
        }
        return true;
    });

    tbody.innerHTML = '';

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-secondary);">Tidak ada kursus yang sesuai dengan filter.</td></tr>';
        return;
    }

    filtered.forEach(function (item) {
        const course = item.course;
        const progress = item.progress;
        const grade = item.grade;
        const certificate = item.certificate;
        const isActive = item.enrollment.status === 'active';
        const state = stateMap[item.state] || stateMap['not-started'];
        const modules = ELIPS.getModulesByCourse(course.id).length;
        const gradeText = grade
            ? ELIPS.escapeHtml(grade.score) + ' (' + ELIPS.escapeHtml(grade.grade) + ')'
            : '-';
        const statusClass = isActive ? state.className : '';
        const statusStyle = isActive ? '' : ' style="background: #fee2e2; color: #991b1b;"';
        const statusIcon = isActive ? state.icon : 'bi-slash-circle';
        const statusLabel = isActive ? state.label : 'Dihentikan';

        let action = '';
        if (!isActive) {
            action = '<span class="badge" style="background: #fee2e2; color: #991b1b;"><i class="bi bi-slash-circle"></i> Akses ditutup</span>';
        } else if (course.status !== 'published') {
            action = '<span class="badge badge-warning"><i class="bi bi-lock-fill"></i> Belum Dibuka</span>';
        } else {
            const learnLabel = item.isComplete ? 'Lihat Materi' : (progress.completed > 0 ? 'Lanjutkan' : 'Mulai');
            const learnIcon = item.isComplete ? 'bi-journal-check' : 'bi-play-fill';
            action = '<div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">' +
                '<a href="../learning.php?id=' + course.id + '" class="btn btn-primary" style="padding: 0.5rem 0.875rem; font-size: 0.875rem;"><i class="bi ' + learnIcon + '"></i> ' + learnLabel + '</a>' +
                '<a href="../course-detail.php?id=' + course.id + '" class="btn btn-ghost" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;" title="Detail Kursus"><i class="bi bi-info-circle"></i></a>' +
                (certificate && certificate.status === 'publish'
                    ? '<a href="../certificate-view.php?no=' + encodeURIComponent(certificate.certificateNumber) + '" class="btn btn-ghost" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;" title="Lihat Sertifikat"><i class="bi bi-award"></i></a>'
                    : '') +
                '</div>';
        }

        const tr = document.createElement('tr');
        tr.innerHTML = [
            '<td>',
            '  <div class="table-course">',
            '    <div class="table-course-img"></div>',
            '    <div class="table-course-info">',
            '      <div class="table-course-title">' + ELIPS.escapeHtml(course.title) + '</div>',
            '      <div class="table-course-meta">' + ELIPS.escapeHtml(course.category || 'Kursus') + ' &bull; ' + modules + ' Modul &bull; ' + progress.total + ' Pelajaran</div>',
            '    </div>',
            '  </div>',
            '</td>',
            '<td><span>' + ELIPS.escapeHtml(course.instructorName || '-') + '</span></td>',
            '<td>',
            '  <div class="progress-inline">',
            '    <div class="progress-inline-bar"><div class="progress-inline-fill" style="width: ' + progress.percentage + '%;' + (progress.percentage >= 100 ? ' background: var(--success);' : '') + '"></div></div>',
            '    <span class="progress-inline-text">' + progress.percentage + '%</span>',
            '  </div>',
            '  <small style="color: var(--text-secondary);">' + progress.completed + '/' + progress.total + ' pelajaran</small>',
            '</td>',
            '<td><span>' + gradeText + '</span></td>',
            '<td><span class="badge ' + statusClass + '"' + statusStyle + '><i class="bi ' + statusIcon + '"></i> ' + statusLabel + '</span></td>',
            '<td>' + action + '</td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

