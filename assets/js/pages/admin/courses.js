// Direktori: assets/js/pages/admin/courses.js
// ELIPS LMS - Kelola Kursus (dipakai admin dan karyawan Operasional).
// Server tetap memeriksa hak akses dan semua aturan; JS hanya tampilan.
(function (ELIPS) {
    'use strict';

    var editingId = null;
    var filter = 'all';
    var pendingImage = null;   // data URL gambar baru, atau null
    var removeImage = false;   // true bila gambar lama dihapus
    var MAX_IMAGE_SIDE = 1200;

    var esc = function (v) { return ELIPS.escapeHtml(String(v == null ? '' : v)); };
    var $ = function (id) { return document.getElementById(id); };
    var rp = function (n) { return 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID'); };
    var LABEL = { published: 'Dipublikasi', draft: 'Draft', archived: 'Arsip' };

    function imageStyle(course) {
        if (!course.image) return '';
        return ' style="background-image:url(\'' + ELIPS.basePathPrefix() + esc(course.image) + '\')"';
    }

    function actionButtons(course) {
        var b = [];
        b.push('<button type="button" class="btn btn-ghost" data-act="edit" title="Ubah"><i class="bi bi-pencil"></i></button>');
        if (course.status === 'published') {
            b.push('<button type="button" class="btn btn-ghost" data-act="draft" title="Kembalikan ke Draft"><i class="bi bi-eye-slash"></i> Draft</button>');
        } else if (course.status === 'draft') {
            b.push('<button type="button" class="btn btn-ghost" data-act="publish" title="Publikasikan"><i class="bi bi-send-check"></i> Publikasi</button>');
        } else {
            b.push('<button type="button" class="btn btn-ghost" data-act="draft" title="Aktifkan kembali sebagai Draft"><i class="bi bi-arrow-counterclockwise"></i> Aktifkan</button>');
        }
        if (course.status !== 'archived') {
            b.push('<button type="button" class="btn btn-ghost" data-act="archive" title="Arsipkan"><i class="bi bi-archive"></i></button>');
        }
        b.push('<a class="btn btn-ghost" href="' + ELIPS.basePathPrefix() + 'course-detail.php?id=' + course.id + '" title="Lihat"><i class="bi bi-eye"></i></a>');
        var unused = !course.classes && !course.enrollments;
        if (unused && course.status !== 'published') {
            b.push('<button type="button" class="btn btn-ghost crs-danger" data-act="delete" title="Hapus (hanya kursus yang belum pernah dipakai)"><i class="bi bi-trash"></i></button>');
        }
        return b.join('');
    }

    function renderStats(courses) {
        var count = function (s) { return courses.filter(function (c) { return c.status === s; }).length; };
        $('crsStatAll').textContent = courses.length;
        $('crsStatPublished').textContent = count('published');
        $('crsStatDraft').textContent = count('draft');
        $('crsStatArchived').textContent = count('archived');
    }

    function render() {
        var tbody = $('coursesTableBody');
        var courses = ELIPS.getCourses();
        var q = $('searchCourse').value.trim().toLowerCase();
        renderStats(courses);

        var rows = courses.filter(function (c) {
            return (filter === 'all' || c.status === filter)
                && (!q || (c.title + ' ' + c.category).toLowerCase().indexOf(q) !== -1);
        }).sort(function (a, b) { return Number(b.id) - Number(a.id); });

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="crs-empty">' + (courses.length ? 'Tidak ada kursus yang cocok.' : 'Belum ada kursus. Klik "Tambah Kursus" untuk memulai.') + '</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (c, i) {
            var price = Number(c.price) > 0
                ? (Number(c.discountPrice) > 0 ? rp(c.discountPrice) + '<s>' + rp(c.price) + '</s>' : rp(c.price))
                : '<span class="crs-meta">Belum diisi</span>';
            return '<tr data-id="' + c.id + '" style="animation-delay:' + Math.min(i, 12) * 0.03 + 's">'
                + '<td><div class="crs-cell"><div class="crs-thumb"' + imageStyle(c) + '></div><div>'
                + '<div class="crs-title">' + esc(c.title) + '</div>'
                + '<div class="crs-meta">' + esc(c.category) + (c.location ? ' · ' + esc(c.location) : '') + '</div></div></div></td>'
                + '<td>' + (c.instructorName ? esc(c.instructorName) : '<span class="crs-meta">Belum ditugaskan</span>') + '</td>'
                + '<td class="crs-price">' + price + '</td>'
                + '<td><span class="crs-meta">' + (c.modules || 0) + ' modul · ' + (c.lessons || 0) + ' materi<br>' + (c.classes || 0) + ' kelas · ' + (c.enrollments || 0) + ' peserta</span></td>'
                + '<td><span class="crs-pill crs-pill-' + esc(c.status) + '">' + (LABEL[c.status] || esc(c.status)) + '</span></td>'
                + '<td><div class="crs-actions">' + actionButtons(c) + '</div></td></tr>';
        }).join('');
    }

    // ---------- Modal ----------

    function showError(message) {
        var el = $('courseError');
        el.textContent = message || '';
        el.hidden = !message;
    }

    function setPreview(url) {
        var box = $('courseImagePreview');
        box.style.backgroundImage = url ? 'url("' + url + '")' : '';
        box.classList.toggle('has-image', !!url);
        $('courseImageRemove').hidden = !url;
    }

    function openModal(courseId) {
        editingId = courseId || null;
        pendingImage = null;
        removeImage = false;
        showError('');
        $('courseImageFile').value = '';

        var select = $('courseInstructor');
        select.innerHTML = '<option value="">-- Pilih tentor --</option>' + ELIPS.getUsersByRole('instruktur')
            .filter(function (u) { return u.status === 'aktif'; })
            .map(function (u) { return '<option value="' + u.id + '">' + esc(u.name) + '</option>'; }).join('');
        $('courseCategoryList').innerHTML = (ELIPS.getCategories ? ELIPS.getCategories() : [])
            .map(function (c) { return '<option value="' + esc(c.name) + '"></option>'; }).join('');

        var c = editingId ? ELIPS.getCourseById(editingId) : null;
        $('courseModalTitle').textContent = c ? 'Ubah Kursus' : 'Tambah Kursus Baru';
        $('courseTitle').value = c ? c.title || '' : '';
        $('courseCategory').value = c ? c.category || '' : '';
        $('courseDesc').value = c ? c.description || '' : '';
        $('coursePrice').value = c && Number(c.price) > 0 ? Math.round(c.price) : '';
        $('courseDiscount').value = c && Number(c.discountPrice) > 0 ? Math.round(c.discountPrice) : '';
        $('courseMeetings').value = c && c.meetings ? c.meetings : '';
        $('courseStart').value = c && c.startDate ? c.startDate : '';
        $('courseLocation').value = c ? c.location || '' : '';
        select.value = c ? String(c.instructorId || '') : '';
        setPreview(c && c.image ? ELIPS.basePathPrefix() + c.image : '');
        $('courseModal').hidden = false;
        $('courseTitle').focus();
    }

    function closeModal() {
        $('courseModal').hidden = true;
        editingId = null;
    }

    function resizeImage(file, cb) {
        if (file.type.indexOf('image/') !== 0) { cb(null, 'Pilih berkas gambar.'); return; }
        var img = new Image();
        var url = URL.createObjectURL(file);
        img.onload = function () {
            var scale = Math.min(1, MAX_IMAGE_SIDE / Math.max(img.width, img.height));
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            cb(canvas.toDataURL('image/jpeg', 0.85));
        };
        img.onerror = function () { URL.revokeObjectURL(url); cb(null, 'Gambar tidak dapat dibaca.'); };
        img.src = url;
    }

    function onPickImage() {
        var file = $('courseImageFile').files[0];
        if (!file) return;
        resizeImage(file, function (data, problem) {
            if (!data) { showError(problem); return; }
            showError('');
            pendingImage = data;
            removeImage = false;
            setPreview(data);
        });
    }

    function save() {
        showError('');
        var payload = {
            title: $('courseTitle').value.trim(),
            category: $('courseCategory').value.trim(),
            description: $('courseDesc').value.trim(),
            instructorId: parseInt($('courseInstructor').value, 10) || 0,
            price: $('coursePrice').value === '' ? 0 : Number($('coursePrice').value),
            discountPrice: $('courseDiscount').value === '' ? 0 : Number($('courseDiscount').value),
            meetings: $('courseMeetings').value === '' ? 0 : Number($('courseMeetings').value),
            startDate: $('courseStart').value,
            location: $('courseLocation').value.trim()
        };

        var button = $('courseSaveBtn');
        button.disabled = true;
        var result = editingId
            ? ELIPS.updateCourse(editingId, payload)
            : ELIPS.createCourse(payload);
        if (!result.ok) {
            button.disabled = false;
            showError(result.message);
            return;
        }
        var courseId = editingId || (result.data && result.data.id);

        var imageResult = null;
        if (courseId && pendingImage) {
            imageResult = ELIPS.api.call('course.image', { id: courseId, dataUrl: pendingImage });
        } else if (courseId && removeImage) {
            imageResult = ELIPS.api.call('course.image', { id: courseId, remove: true });
        }
        button.disabled = false;

        closeModal();
        render();
        ELIPS.toast(imageResult && !imageResult.ok
            ? 'Kursus tersimpan, tetapi gambar gagal: ' + imageResult.message
            : result.message);
    }

    // ---------- Aksi baris ----------

    function runStatus(course, status, question) {
        if (!confirm(question)) return;
        var result = ELIPS.api.call('course.status', { id: course.id, status: status });
        ELIPS.toast(result.message);
        render();
    }

    function onRowAction(event) {
        var button = event.target.closest('[data-act]');
        if (!button) return;
        var row = button.closest('tr');
        var course = ELIPS.getCourseById(Number(row.getAttribute('data-id')));
        if (!course) return;

        switch (button.getAttribute('data-act')) {
            case 'edit': openModal(course.id); break;
            case 'publish':
                runStatus(course, 'published', 'Publikasikan kursus "' + course.title + '"? Kursus akan tampil di pendaftaran publik.');
                break;
            case 'draft':
                runStatus(course, 'draft', course.status === 'archived'
                    ? 'Aktifkan kembali kursus "' + course.title + '" sebagai Draft?'
                    : 'Kembalikan kursus "' + course.title + '" ke Draft? Kursus tidak tampil lagi di pendaftaran publik.');
                break;
            case 'archive':
                runStatus(course, 'archived', 'Arsipkan kursus "' + course.title + '"? Kursus hilang dari pendaftaran publik, data siswa dan tagihan tetap aman.');
                break;
            case 'delete':
                if (!confirm('Hapus permanen kursus "' + course.title + '"? Modul dan materi di dalamnya ikut terhapus.')) return;
                ELIPS.toast(ELIPS.deleteCourse(course.id).message);
                render();
                break;
        }
    }

    function guard() {
        var user = ELIPS.employee && ELIPS.employee.guardEmployee
            ? ELIPS.employee.guardEmployee(['operasional'])
            : ELIPS.requireRole('admin');
        if (user && user.role === 'karyawan' && ELIPS.employee.initEmployeeShell) {
            ELIPS.employee.initEmployeeShell(user, { toggleId: 'opsSidebarToggle', backdropId: 'opsSidebarBackdrop' });
        }
        return user;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!guard()) return;

        $('courseAddBtn').addEventListener('click', function () { openModal(); });
        $('courseModalClose').addEventListener('click', closeModal);
        $('courseCancelBtn').addEventListener('click', closeModal);
        $('courseSaveBtn').addEventListener('click', save);
        $('courseImageFile').addEventListener('change', onPickImage);
        $('courseImageRemove').addEventListener('click', function () {
            pendingImage = null;
            removeImage = true;
            $('courseImageFile').value = '';
            setPreview('');
        });
        $('courseModal').addEventListener('click', function (e) { if (e.target === this) closeModal(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('courseModal').hidden) closeModal(); });
        $('coursesTableBody').addEventListener('click', onRowAction);
        $('searchCourse').addEventListener('input', render);
        $('crsTabs').addEventListener('click', function (e) {
            var tab = e.target.closest('.crs-tab');
            if (!tab) return;
            filter = tab.getAttribute('data-filter');
            document.querySelectorAll('#crsTabs .crs-tab').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
            render();
        });
        render();
    });
})(window.ELIPS);