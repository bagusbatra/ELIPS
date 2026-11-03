// ============================================================
// ELIPS LMS - crud-courses.js
// CRUD kursus dan aturan Draft/Publish.
// ============================================================

(function (ELIPS) {
    'use strict';

    const { success, failure, todayString, isActiveInstructor } = ELIPS._;

    function recordAudit(entry) {
        if (typeof ELIPS.logAudit === 'function') ELIPS.logAudit(entry);
    }

    function createCourse(data) {
        data = data || {};
        const title = String(data.title || '').trim();
        const category = String(data.category || '').trim();
        const description = String(data.description || '').trim();
        const instructorId = Number(data.instructorId);

        if (!title) return failure('Judul kursus wajib diisi.');
        if (!category) return failure('Kategori kursus wajib diisi.');
        if (!instructorId) return failure('Pilih satu instruktur untuk kursus ini.');
        if (!isActiveInstructor(instructorId)) return failure('Instruktur harus berstatus aktif.');

        const instructor = ELIPS.getUserById(instructorId);
        const courses = ELIPS.getCourses();
        const course = {
            id: ELIPS.nextId(courses),
            title: title,
            category: category,
            description: description,
            instructorId: instructor.id,
            instructorName: instructor.name,
            modules: 0,
            lessons: 0,
            totalLessons: 0,
            status: 'draft',
            createdAt: todayString()
        };

        courses.push(course);
        ELIPS.setCourses(courses);
        recordAudit({
            action: 'create',
            entity: 'course',
            entityId: course.id,
            entityLabel: course.title,
            actorId: data.actorId,
            detail: 'Membuat kursus "' + course.title + '" (Draft) dengan instruktur ' + instructor.name + '.'
        });
        return success('Kursus baru berhasil ditambahkan dan berstatus Draft.', course);
    }

    function updateCourse(id, data) {
        data = data || {};
        const courses = ELIPS.getCourses();
        const course = courses.find(function (c) { return c.id === Number(id); });
        if (!course) return failure('Kursus tidak ditemukan.');

        const title = data.title !== undefined ? String(data.title).trim() : course.title;
        const category = data.category !== undefined ? String(data.category).trim() : course.category;
        const description = data.description !== undefined ? String(data.description).trim() : course.description;
        const nextInstructorId = data.instructorId !== undefined ? Number(data.instructorId) : course.instructorId;

        if (!title) return failure('Judul kursus wajib diisi.');
        if (!category) return failure('Kategori kursus wajib diisi.');
        if (!nextInstructorId) return failure('Pilih satu instruktur untuk kursus ini.');
        if (!isActiveInstructor(nextInstructorId)) return failure('Instruktur harus berstatus aktif.');

        const previousInstructorId = course.instructorId;
        const instructor = ELIPS.getUserById(nextInstructorId);

        const changes = [];
        if (course.title !== title) changes.push('judul');
        if (course.category !== category) changes.push('kategori');
        if (String(course.description || '') !== description) changes.push('deskripsi');
        if (Number(course.instructorId) !== instructor.id) changes.push('instruktur');

        course.title = title;
        course.category = category;
        course.description = description;
        course.instructorId = instructor.id;
        course.instructorName = instructor.name;
        course.updatedAt = new Date().toISOString();

        // Status kursus tidak diubah lewat edit. Publikasi memakai aksi khusus.
        ELIPS.setCourses(courses);
        if (instructor.id !== Number(previousInstructorId)) ELIPS._.syncAllContentOwners();

        if (changes.length > 0) {
            recordAudit({
                action: 'update',
                entity: 'course',
                entityId: course.id,
                entityLabel: course.title,
                actorId: data.actorId,
                detail: 'Memperbarui kursus "' + course.title + '" (' + changes.join(', ') + ').'
            });
        }
        return success('Kursus berhasil diperbarui.', course);
    }

    function deleteCourse(id) {
        const course = ELIPS.getCourseById(id);
        if (!course) return failure('Kursus tidak ditemukan.');

        const lessonIds = ELIPS.getLessonsByCourse(course.id).map(function (lesson) { return lesson.id; });
        ELIPS.setCourses(ELIPS.getCourses().filter(function (c) { return c.id !== course.id; }));
        ELIPS.setModules(ELIPS.getModules().filter(function (m) { return m.courseId !== course.id; }));
        ELIPS.setLessons(ELIPS.getLessons().filter(function (l) { return l.courseId !== course.id; }));
        ELIPS.setEnrollments(ELIPS.getEnrollments().filter(function (e) { return e.courseId !== course.id; }));
        ELIPS.setGrades(ELIPS.getGrades().filter(function (g) { return g.courseId !== course.id; }));
        ELIPS.setCertificates(ELIPS.getCertificates().filter(function (c) { return c.courseId !== course.id; }));
        ELIPS.setCompletedLessons(ELIPS.getCompletedLessons().filter(function (progress) {
            return progress.courseId !== course.id && lessonIds.indexOf(progress.lessonId) === -1;
        }));

        recordAudit({
            action: 'delete',
            entity: 'course',
            entityId: course.id,
            entityLabel: course.title,
            detail: 'Menghapus kursus "' + course.title + '" beserta modul, materi, penugasan, nilai, sertifikat, dan progres terkait.'
        });
        return success('Kursus beserta seluruh datanya berhasil dihapus.');
    }

    function setCourseStatus(id, status) {
        if (status !== 'draft' && status !== 'published') return failure('Status kursus tidak valid.');
        const courses = ELIPS.getCourses();
        const course = courses.find(function (c) { return c.id === Number(id); });
        if (!course) return failure('Kursus tidak ditemukan.');
        const previousStatus = course.status;

        if (status === 'published') {
            if (!isActiveInstructor(course.instructorId)) {
                return failure('Tugaskan instruktur aktif sebelum mempublikasikan kursus.');
            }
            if (ELIPS.getModulesByCourse(course.id).length === 0 || ELIPS.getLessonsByCourse(course.id).length === 0) {
                return failure('Tambahkan minimal satu modul dan satu materi sebelum publikasi.');
            }
        }

        course.status = status;
        course.updatedAt = new Date().toISOString();
        ELIPS.setCourses(courses);

        if (previousStatus !== status) {
            recordAudit({
                action: status === 'published' ? 'publish' : 'unpublish',
                entity: 'course',
                entityId: course.id,
                entityLabel: course.title,
                detail: status === 'published'
                    ? 'Mempublikasikan kursus "' + course.title + '".'
                    : 'Mengembalikan kursus "' + course.title + '" ke Draft.'
            });

            if (status === 'published' && typeof ELIPS.notifyCoursePublished === 'function') {
                ELIPS.notifyCoursePublished(course);
            }
        }
        return success(status === 'published' ? 'Kursus berhasil dipublikasikan.' : 'Kursus dikembalikan ke Draft.', course);
    }

    function toggleCourseStatus(id) {
        const course = ELIPS.getCourseById(id);
        if (!course) return failure('Kursus tidak ditemukan.');
        return setCourseStatus(course.id, course.status === 'published' ? 'draft' : 'published');
    }

    Object.assign(ELIPS, {
        createCourse: createCourse,
        updateCourse: updateCourse,
        deleteCourse: deleteCourse,
        setCourseStatus: setCourseStatus,
        toggleCourseStatus: toggleCourseStatus
    });
})(window.ELIPS);
