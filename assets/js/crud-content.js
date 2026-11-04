// ============================================================
// ELIPS LMS - crud-content.js
// CRUD modul dan materi pada kursus yang diampu instruktur.
// ============================================================

(function (ELIPS) {
    'use strict';

    const { success, failure } = ELIPS._;

    function recordAudit(entry) {
        if (typeof ELIPS.logAudit === 'function') ELIPS.logAudit(entry);
    }

    function canManageCourse(courseId, actorId) {
        if (actorId === undefined || actorId === null) return true;
        const course = ELIPS.getCourseById(courseId);
        const actor = ELIPS.getUserById(actorId);
        return !!course && !!actor && actor.role === 'instruktur' && course.instructorId === actor.id;
    }

    // ---------- Modules ----------
    function createModule(courseId, data, actorId) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('module.create', { courseId: courseId, data: data || {} });
        data = data || {};
        const course = ELIPS.getCourseById(courseId);
        if (!course) return failure('Kursus tidak ditemukan.');
        if (!canManageCourse(course.id, actorId)) return failure('Anda tidak berhak mengelola kursus ini.');

        const title = String(data.title || '').trim();
        if (!title) return failure('Judul modul wajib diisi.');

        const modules = ELIPS.getModules();
        const maxOrder = modules
            .filter(function (item) { return item.courseId === course.id; })
            .reduce(function (max, item) { return Math.max(max, Number(item.order) || 0); }, 0);

        const module = {
            id: ELIPS.nextId(modules),
            courseId: course.id,
            instructorId: course.instructorId,
            title: title,
            description: String(data.description || '').trim(),
            order: maxOrder + 1,
            createdAt: new Date().toISOString()
        };
        modules.push(module);
        ELIPS.setModules(modules);
        ELIPS.syncCourseCounters(course.id);
        recordAudit({
            action: 'create',
            entity: 'module',
            entityId: module.id,
            entityLabel: module.title,
            actorId: actorId,
            detail: 'Menambahkan modul "' + module.title + '" pada kursus "' + course.title + '".'
        });
        return success('Modul berhasil ditambahkan.', module);
    }

    function updateModule(id, data, actorId) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('module.update', { id: id, data: data || {} });
        data = data || {};
        const modules = ELIPS.getModules();
        const module = modules.find(function (item) { return item.id === Number(id); });
        if (!module) return failure('Modul tidak ditemukan.');
        if (!canManageCourse(module.courseId, actorId)) return failure('Anda tidak berhak mengelola modul ini.');

        const title = data.title !== undefined ? String(data.title).trim() : module.title;
        if (!title) return failure('Judul modul wajib diisi.');

        module.title = title;
        if (data.description !== undefined) module.description = String(data.description).trim();
        module.updatedAt = new Date().toISOString();
        ELIPS.setModules(modules);
        const course = ELIPS.getCourseById(module.courseId);
        recordAudit({
            action: 'update',
            entity: 'module',
            entityId: module.id,
            entityLabel: module.title,
            actorId: actorId,
            detail: 'Memperbarui modul "' + module.title + '" pada kursus "' +
                (course ? course.title : 'Kursus #' + module.courseId) + '".'
        });
        return success('Modul berhasil diperbarui.', module);
    }

    function deleteModule(id, actorId) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('module.delete', { id: id });
        const module = ELIPS.getModules().find(function (item) { return item.id === Number(id); });
        if (!module) return failure('Modul tidak ditemukan.');
        if (!canManageCourse(module.courseId, actorId)) return failure('Anda tidak berhak menghapus modul ini.');

        const lessonIds = ELIPS.getLessonsByModule(module.id).map(function (lesson) { return lesson.id; });
        const course = ELIPS.getCourseById(module.courseId);
        ELIPS.setModules(ELIPS.getModules().filter(function (item) { return item.id !== module.id; }));
        ELIPS.setLessons(ELIPS.getLessons().filter(function (lesson) { return lesson.moduleId !== module.id; }));
        ELIPS.setCompletedLessons(ELIPS.getCompletedLessons().filter(function (progress) {
            return lessonIds.indexOf(progress.lessonId) === -1;
        }));
        ELIPS.syncCourseCounters(module.courseId);
        recordAudit({
            action: 'delete',
            entity: 'module',
            entityId: module.id,
            entityLabel: module.title,
            actorId: actorId,
            detail: 'Menghapus modul "' + module.title + '" dari kursus "' +
                (course ? course.title : 'Kursus #' + module.courseId) + '" beserta ' + lessonIds.length + ' materi.'
        });
        return success('Modul dihapus.', { removedLessons: lessonIds.length });
    }

    // ---------- Lessons ----------
    function createLesson(moduleId, data, actorId) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('lesson.create', { moduleId: moduleId, data: data || {} });
        data = data || {};
        const module = ELIPS.getModules().find(function (item) { return item.id === Number(moduleId); });
        if (!module) return failure('Modul tidak ditemukan.');
        if (!canManageCourse(module.courseId, actorId)) return failure('Anda tidak berhak mengelola materi ini.');

        const title = String(data.title || '').trim();
        const type = data.type || 'text';
        let content = String(data.content || '').trim();
        if (!title) return failure('Judul materi wajib diisi.');
        if (['text', 'video', 'file', 'link'].indexOf(type) === -1) return failure('Tipe konten tidak valid.');
        if (type !== 'text' && !content) return failure('Isi URL atau nama file untuk tipe konten ini.');
        if (type === 'text') content = ELIPS.sanitizeHtml(content);

        const lessons = ELIPS.getLessons();
        const maxOrder = lessons
            .filter(function (item) { return item.moduleId === module.id; })
            .reduce(function (max, item) { return Math.max(max, Number(item.order) || 0); }, 0);

        const lesson = {
            id: ELIPS.nextId(lessons),
            moduleId: module.id,
            courseId: module.courseId,
            instructorId: module.instructorId || null,
            title: title,
            type: type,
            content: content,
            order: maxOrder + 1,
            createdAt: new Date().toISOString()
        };
        lessons.push(lesson);
        ELIPS.setLessons(lessons);
        ELIPS.syncCourseCounters(module.courseId);
        const course = ELIPS.getCourseById(module.courseId);
        recordAudit({
            action: 'create',
            entity: 'lesson',
            entityId: lesson.id,
            entityLabel: lesson.title,
            actorId: actorId,
            detail: 'Menambahkan materi "' + lesson.title + '" pada modul "' + module.title + '" kursus "' +
                (course ? course.title : 'Kursus #' + module.courseId) + '".'
        });
        return success('Materi berhasil ditambahkan.', lesson);
    }

    function updateLesson(id, data, actorId) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('lesson.update', { id: id, data: data || {} });
        data = data || {};
        const lessons = ELIPS.getLessons();
        const lesson = lessons.find(function (item) { return item.id === Number(id); });
        if (!lesson) return failure('Materi tidak ditemukan.');
        if (!canManageCourse(lesson.courseId, actorId)) return failure('Anda tidak berhak mengelola materi ini.');

        const nextModuleId = data.moduleId !== undefined ? Number(data.moduleId) : lesson.moduleId;
        const targetModule = ELIPS.getModules().find(function (item) { return item.id === nextModuleId; });
        if (!targetModule || targetModule.courseId !== lesson.courseId) {
            return failure('Modul tujuan tidak valid untuk kursus ini.');
        }

        const title = data.title !== undefined ? String(data.title).trim() : lesson.title;
        const type = data.type !== undefined ? data.type : lesson.type;
        let content = data.content !== undefined ? String(data.content).trim() : lesson.content;
        if (!title) return failure('Judul materi wajib diisi.');
        if (['text', 'video', 'file', 'link'].indexOf(type) === -1) return failure('Tipe konten tidak valid.');
        if (type !== 'text' && !content) return failure('Isi URL atau nama file untuk tipe konten ini.');
        if (type === 'text') content = ELIPS.sanitizeHtml(content);

        if (lesson.moduleId !== targetModule.id) {
            const maxOrder = ELIPS.getLessonsByModule(targetModule.id)
                .reduce(function (max, item) { return Math.max(max, Number(item.order) || 0); }, 0);
            lesson.order = maxOrder + 1;
        }
        lesson.moduleId = targetModule.id;
        lesson.instructorId = targetModule.instructorId || lesson.instructorId || null;
        lesson.title = title;
        lesson.type = type;
        lesson.content = content;
        lesson.updatedAt = new Date().toISOString();
        ELIPS.setLessons(lessons);
        const course = ELIPS.getCourseById(lesson.courseId);
        recordAudit({
            action: 'update',
            entity: 'lesson',
            entityId: lesson.id,
            entityLabel: lesson.title,
            actorId: actorId,
            detail: 'Memperbarui materi "' + lesson.title + '" pada modul "' + targetModule.title + '" kursus "' +
                (course ? course.title : 'Kursus #' + lesson.courseId) + '".'
        });
        return success('Materi berhasil diperbarui.', lesson);
    }

    function deleteLesson(id, actorId) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('lesson.delete', { id: id });
        const lesson = ELIPS.getLessons().find(function (item) { return item.id === Number(id); });
        if (!lesson) return failure('Materi tidak ditemukan.');
        if (!canManageCourse(lesson.courseId, actorId)) return failure('Anda tidak berhak menghapus materi ini.');
        const course = ELIPS.getCourseById(lesson.courseId);

        ELIPS.setLessons(ELIPS.getLessons().filter(function (item) { return item.id !== lesson.id; }));
        ELIPS.setCompletedLessons(ELIPS.getCompletedLessons().filter(function (progress) {
            return progress.lessonId !== lesson.id;
        }));
        ELIPS.syncCourseCounters(lesson.courseId);
        recordAudit({
            action: 'delete',
            entity: 'lesson',
            entityId: lesson.id,
            entityLabel: lesson.title,
            actorId: actorId,
            detail: 'Menghapus materi "' + lesson.title + '" dari kursus "' +
                (course ? course.title : 'Kursus #' + lesson.courseId) + '".'
        });
        return success('Materi dihapus.', lesson);
    }

    Object.assign(ELIPS, {
        createModule: createModule,
        updateModule: updateModule,
        deleteModule: deleteModule,
        createLesson: createLesson,
        updateLesson: updateLesson,
        deleteLesson: deleteLesson
    });
})(window.ELIPS);
