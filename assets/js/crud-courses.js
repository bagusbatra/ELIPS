// ============================================================
// ELIPS LMS - crud-courses.js
// CRUD kursus dan aturan Draft/Publish.
// ============================================================

(function (ELIPS) {
    'use strict';

    const { success, failure, todayString, isActiveInstructor } = ELIPS._;

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
        course.title = title;
        course.category = category;
        course.description = description;
        course.instructorId = instructor.id;
        course.instructorName = instructor.name;
        course.updatedAt = new Date().toISOString();

        // Status kursus tidak diubah lewat edit. Publikasi memakai aksi khusus.
        ELIPS.setCourses(courses);
        if (instructor.id !== Number(previousInstructorId)) ELIPS._.syncAllContentOwners();
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

        return success('Kursus beserta seluruh datanya berhasil dihapus.');
    }

    function setCourseStatus(id, status) {
        if (status !== 'draft' && status !== 'published') return failure('Status kursus tidak valid.');
        const courses = ELIPS.getCourses();
        const course = courses.find(function (c) { return c.id === Number(id); });
        if (!course) return failure('Kursus tidak ditemukan.');

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
