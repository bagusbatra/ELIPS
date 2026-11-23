// ELIPS LMS - skrip halaman admin/courses.php
// Diekstrak dari inline <script>; URL relatif terhadap halaman.
let editingCourseId = null;

function renderCourses() {
    const tbody = document.getElementById('coursesTableBody');
    const courses = ELIPS.getCourses();
    const enrollments = ELIPS.getEnrollments();
    tbody.innerHTML = '';

    if (courses.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-secondary);">Belum ada kursus</td></tr>';
        return;
    }

    courses.slice().sort(function (a, b) { return Number(b.id) - Number(a.id); }).forEach(function (course) {
        const modules = ELIPS.getModulesByCourse(course.id).length;
        const lessons = ELIPS.getLessonsByCourse(course.id).length;
        const participantCount = enrollments.filter(function (e) { return e.courseId === course.id; }).length;
        const instructor = course.instructorName || 'Belum ditugaskan';
        const tr = document.createElement('tr');
        tr.setAttribute('data-status', course.status);
        tr.innerHTML = [
            '<td>',
            '  <div class="table-course">',
            '    <div class="table-course-img"></div>',
            '    <div class="table-course-info">',
            '      <div class="table-course-title">' + ELIPS.escapeHtml(course.title) + '</div>',
            '      <div class="table-course-meta">' + ELIPS.escapeHtml(course.category) + '</div>',
            '    </div>',
            '  </div>',
            '</td>',
            '<td><span>' + ELIPS.escapeHtml(instructor) + '</span></td>',
            '<td><span>' + modules + ' Modul &bull; ' + lessons + ' Pelajaran</span></td>',
            '<td><span>' + participantCount + ' Peserta</span></td>',
            '<td><span class="badge ' + (course.status === 'published' ? 'badge-success' : 'badge-warning') + '">' + (course.status === 'published' ? 'Dipublikasi' : 'Draft') + '</span></td>',
            '<td>',
            '  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">',
            '    <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" onclick="openCourseModal(' + course.id + ')"><i class="bi bi-pencil"></i></button>',
            '    <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;" onclick="togglePublish(' + course.id + ')"><i class="bi ' + (course.status === 'published' ? 'bi-eye-slash' : 'bi-send-check') + '"></i> ' + (course.status === 'published' ? 'Draft' : 'Publikasi') + '</button>',
            '    <a href="../course-detail.php?id=' + course.id + '" class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem;"><i class="bi bi-eye"></i></a>',
            '    <button class="btn btn-ghost" style="padding: 0.375rem 0.625rem; font-size: 0.8125rem; color: var(--danger);" onclick="removeCourse(' + course.id + ')" title="Hapus kursus"><i class="bi bi-trash"></i></button>',
            '  </div>',
            '</td>'
        ].join('');
        tbody.appendChild(tr);
    });
}

function openCourseModal(courseId) {
    editingCourseId = courseId || null;
    const select = document.getElementById('courseInstructor');
    select.innerHTML = '<option value="">-- Pilih Instruktur --</option>';
    ELIPS.getUsersByRole('instruktur').filter(function (u) { return u.status === 'aktif'; }).forEach(function (inst) {
        select.innerHTML += '<option value="' + inst.id + '">' + ELIPS.escapeHtml(inst.name) + '</option>';
    });

    if (editingCourseId) {
        const course = ELIPS.getCourseById(editingCourseId);
        document.getElementById('courseModalTitle').textContent = 'Edit Kursus';
        document.getElementById('courseTitle').value = course.title || '';
        document.getElementById('courseCategory').value = course.category || '';
        document.getElementById('courseDesc').value = course.description || '';
        select.value = String(course.instructorId || '');
    } else {
        document.getElementById('courseModalTitle').textContent = 'Tambah Kursus Baru';
        document.getElementById('courseTitle').value = '';
        document.getElementById('courseCategory').value = '';
        document.getElementById('courseDesc').value = '';
        select.value = '';
    }
    document.getElementById('courseModal').style.display = 'flex';
}

function closeCourseModal() {
    document.getElementById('courseModal').style.display = 'none';
    editingCourseId = null;
}

function saveCourse() {
    const payload = {
        title: document.getElementById('courseTitle').value.trim(),
        category: document.getElementById('courseCategory').value.trim(),
        description: document.getElementById('courseDesc').value.trim(),
        instructorId: parseInt(document.getElementById('courseInstructor').value, 10)
    };

    const result = editingCourseId
        ? ELIPS.updateCourse(editingCourseId, payload)
        : ELIPS.createCourse(payload);

    if (!result.ok) {
        ELIPS.toast(result.message);
        return;
    }

    ELIPS.toast(result.message);
    closeCourseModal();
    renderCourses();
}

function togglePublish(courseId) {
    const course = ELIPS.getCourseById(courseId);
    if (!course) return;
    const nextStatus = course.status === 'published' ? 'draft' : 'published';
    const label = nextStatus === 'published' ? 'mempublikasikan' : 'mengubah ke draft';
    if (!confirm('Yakin ' + label + ' kursus "' + course.title + '"?')) return;

    const result = ELIPS.toggleCourseStatus(courseId);
    ELIPS.toast(result.message);
    renderCourses();
}

function removeCourse(courseId) {
    const course = ELIPS.getCourseById(courseId);
    if (!course) return;
    if (!confirm('Hapus kursus "' + course.title + '"? Modul, materi, penugasan, nilai, dan sertifikat terkait juga akan dihapus.')) return;

    const result = ELIPS.deleteCourse(courseId);
    ELIPS.toast(result.message);
    renderCourses();
}

function filterCourses(status) {
    const rows = document.querySelectorAll('#coursesTableBody tr');
    const buttons = document.querySelectorAll('#filterAll, #filterPublished, #filterDraft');
    buttons.forEach(function (btn) {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-ghost');
    });

    const activeBtn = status === 'all' ? 'filterAll' : status === 'published' ? 'filterPublished' : 'filterDraft';
    document.getElementById(activeBtn).classList.remove('btn-ghost');
    document.getElementById(activeBtn).classList.add('btn-primary');

    rows.forEach(function (row) {
        row.style.display = status === 'all' || row.getAttribute('data-status') === status ? '' : 'none';
    });
}

function searchCourses() {
    const input = document.getElementById('searchCourse').value.toLowerCase();
    document.querySelectorAll('#coursesTableBody tr').forEach(function (row) {
        const titleEl = row.querySelector('.table-course-title');
        const title = titleEl ? titleEl.textContent.toLowerCase() : '';
        row.style.display = title.includes(input) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function () {
    if (!ELIPS.requireRole('admin')) return;
    renderCourses();
});

