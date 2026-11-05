// ============================================================
// ELIPS LMS - crud-academic.js
// CRUD versi kurikulum, kelas/angkatan, penugasan tentor, dan
// pendaftaran peserta ke kelas.
// ============================================================

(function (ELIPS) {
    'use strict';

    const { success, failure, todayString, isActiveInstructor } = ELIPS._;

    function recordAudit(entry) {
        if (typeof ELIPS.logAudit === 'function') ELIPS.logAudit(entry);
    }

    function isVersionEditable(version) {
        return !!version && (version.status === 'draft' || version.status === 'published');
    }

    function versionModules(versionId) {
        return ELIPS.getModules()
            .filter(function (m) { return Number(m.curriculumVersionId) === Number(versionId); })
            .sort(function (a, b) { return (a.order || 0) - (b.order || 0); });
    }

    function versionLessons(versionId) {
        return ELIPS.getLessons()
            .filter(function (l) { return Number(l.curriculumVersionId) === Number(versionId); })
            .sort(function (a, b) { return (a.order || 0) - (b.order || 0); });
    }

    function nextVersionCode(courseId) {
        const versions = ELIPS.getCurriculumVersionsByCourse(courseId);
        const used = versions.map(function (v) { return String(v.code); });
        for (let major = 1; major <= 999; major++) {
            const code = major + '.0';
            if (used.indexOf(code) === -1) return code;
        }
        return 'v' + (versions.length + 1);
    }

    function refreshVersionCounters(versionId) {
        const versions = ELIPS.getCurriculumVersions();
        const version = versions.find(function (v) { return v.id === Number(versionId); });
        if (!version) return;
        version.modules = versionModules(version.id).length;
        version.lessons = versionLessons(version.id).length;
        version.classes = ELIPS.getClasses().filter(function (c) {
            return Number(c.curriculumVersionId) === Number(version.id);
        }).length;
        ELIPS.setCurriculumVersions(versions);
    }

    function syncCourseVersion(courseId) {
        const courses = ELIPS.getCourses();
        const course = courses.find(function (c) { return c.id === Number(courseId); });
        if (!course) return;
        const version = ELIPS.getCurrentCurriculumVersion(course.id);
        course.curriculumVersionId = version ? version.id : null;
        course.curriculumVersionCode = version ? version.code : null;
        course.curriculumVersionStatus = version ? version.status : null;
        course.classes = ELIPS.getClassesByCourse(course.id).length;
        ELIPS.setCourses(courses);
    }

    // ---------- Curriculum versions ----------

    function createCurriculumVersion(courseId, data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            const payload = Object.assign({ courseId: courseId }, data || {});
            return ELIPS.api.call('curriculum.create', payload);
        }
        data = data || {};
        const course = ELIPS.getCourseById(courseId);
        if (!course) return failure('Kursus tidak ditemukan.');

        const code = String(data.code || '').trim() || nextVersionCode(course.id);
        const versions = ELIPS.getCurriculumVersions();
        if (versions.some(function (v) { return Number(v.courseId) === course.id && v.code === code; })) {
            return failure('Kode versi kurikulum sudah dipakai pada kursus ini.');
        }

        const name = String(data.name || '').trim() || ('Kurikulum ' + code + ' — ' + course.title);
        const version = {
            id: ELIPS.nextId(versions),
            courseId: course.id,
            courseTitle: course.title,
            code: code,
            name: name,
            description: String(data.description || '').trim(),
            status: 'draft',
            effectiveDate: data.effectiveDate || null,
            notes: String(data.notes || '').trim(),
            createdBy: data.actorId || null,
            updatedBy: data.actorId || null,
            modules: 0,
            lessons: 0,
            classes: 0,
            createdAt: new Date().toISOString()
        };
        versions.push(version);
        ELIPS.setCurriculumVersions(versions);
        syncCourseVersion(course.id);

        recordAudit({
            action: 'create',
            entity: 'curriculum',
            entityId: version.id,
            entityLabel: version.name,
            actorId: data.actorId,
            detail: 'Membuat versi kurikulum "' + version.name + '" (' + code + ') untuk kursus "' + course.title + '".'
        });
        return success('Versi kurikulum baru berhasil dibuat sebagai Draft.', version);
    }

    function updateCurriculumVersion(id, data) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('curriculum.update', { id: id, data: data || {} });
        data = data || {};
        const versions = ELIPS.getCurriculumVersions();
        const version = versions.find(function (v) { return v.id === Number(id); });
        if (!version) return failure('Versi kurikulum tidak ditemukan.');
        if (version.status !== 'draft') {
            return failure('Hanya versi kurikulum berstatus Draft yang dapat diubah. Buat versi baru untuk revisi.');
        }

        const code = data.code !== undefined ? String(data.code).trim() : version.code;
        const name = data.name !== undefined ? String(data.name).trim() : version.name;
        if (!code) return failure('Kode versi wajib diisi.');
        if (!name) return failure('Nama versi wajib diisi.');
        if (versions.some(function (v) {
            return v.id !== version.id && Number(v.courseId) === Number(version.courseId) && v.code === code;
        })) {
            return failure('Kode versi kurikulum sudah dipakai pada kursus ini.');
        }

        version.code = code;
        version.name = name;
        if (data.description !== undefined) version.description = String(data.description).trim();
        if (data.effectiveDate !== undefined) version.effectiveDate = data.effectiveDate || null;
        if (data.notes !== undefined) version.notes = String(data.notes).trim();
        version.updatedBy = data.actorId || version.updatedBy || null;
        version.updatedAt = new Date().toISOString();
        ELIPS.setCurriculumVersions(versions);
        syncCourseVersion(version.courseId);

        recordAudit({
            action: 'update',
            entity: 'curriculum',
            entityId: version.id,
            entityLabel: version.name,
            actorId: data.actorId,
            detail: 'Memperbarui versi kurikulum "' + version.name + '" (' + code + ').'
        });
        return success('Versi kurikulum berhasil diperbarui.', version);
    }

    function publishCurriculumVersion(id) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('curriculum.publish', { id: id });
        const versions = ELIPS.getCurriculumVersions();
        const version = versions.find(function (v) { return v.id === Number(id); });
        if (!version) return failure('Versi kurikulum tidak ditemukan.');
        if (version.status !== 'draft') return failure('Hanya versi Draft yang dapat dipublikasikan.');
        if (versionModules(version.id).length === 0 || versionLessons(version.id).length === 0) {
            return failure('Tambahkan minimal satu modul dan satu materi sebelum mempublikasikan versi.');
        }

        version.status = 'published';
        version.updatedAt = new Date().toISOString();
        ELIPS.setCurriculumVersions(versions);
        syncCourseVersion(version.courseId);

        recordAudit({
            action: 'publish',
            entity: 'curriculum',
            entityId: version.id,
            entityLabel: version.name,
            detail: 'Mempublikasikan versi kurikulum "' + version.name + '" (' + version.code + ').'
        });
        return success('Versi kurikulum berhasil dipublikasikan.', version);
    }

    function cloneCurriculumVersion(id, data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            const payload = Object.assign({ id: id }, data || {});
            return ELIPS.api.call('curriculum.clone', payload);
        }
        data = data || {};
        const source = ELIPS.getCurriculumVersionById(id);
        if (!source) return failure('Versi kurikulum sumber tidak ditemukan.');

        const courseId = source.courseId;
        const code = String(data.code || '').trim() || nextVersionCode(courseId);
        const versions = ELIPS.getCurriculumVersions();
        if (versions.some(function (v) { return Number(v.courseId) === Number(courseId) && v.code === code; })) {
            return failure('Kode versi kurikulum sudah dipakai pada kursus ini.');
        }

        const course = ELIPS.getCourseById(courseId);
        const name = String(data.name || '').trim() || ('Kurikulum ' + code + ' — revisi dari ' + source.code);
        const version = {
            id: ELIPS.nextId(versions),
            courseId: courseId,
            courseTitle: course ? course.title : source.courseTitle,
            code: code,
            name: name,
            description: source.description,
            status: 'draft',
            effectiveDate: null,
            notes: 'Disalin dari versi ' + source.code + '.',
            createdBy: data.actorId || null,
            updatedBy: data.actorId || null,
            modules: 0,
            lessons: 0,
            classes: 0,
            createdAt: new Date().toISOString()
        };
        versions.push(version);
        ELIPS.setCurriculumVersions(versions);

        const modules = ELIPS.getModules();
        const lessons = ELIPS.getLessons();
        versionModules(source.id).forEach(function (module) {
            const newModuleId = ELIPS.nextId(modules);
            const copied = Object.assign({}, module, {
                id: newModuleId,
                curriculumVersionId: version.id,
                createdAt: new Date().toISOString(),
                updatedAt: undefined
            });
            modules.push(copied);

            ELIPS.getLessons()
                .filter(function (lesson) { return lesson.moduleId === module.id; })
                .sort(function (a, b) { return (a.order || 0) - (b.order || 0); })
                .forEach(function (lesson) {
                    lessons.push(Object.assign({}, lesson, {
                        id: ELIPS.nextId(lessons),
                        moduleId: newModuleId,
                        curriculumVersionId: version.id,
                        createdAt: new Date().toISOString(),
                        updatedAt: undefined
                    }));
                });
        });
        ELIPS.setModules(modules);
        ELIPS.setLessons(lessons);
        refreshVersionCounters(version.id);
        syncCourseVersion(courseId);

        recordAudit({
            action: 'clone',
            entity: 'curriculum',
            entityId: version.id,
            entityLabel: version.name,
            actorId: data.actorId,
            detail: 'Membuat versi kurikulum "' + version.name + '" (' + code + ') sebagai salinan Draft dari versi ' + source.code + '.'
        });
        return success('Versi kurikulum baru berhasil disalin sebagai Draft.', ELIPS.getCurriculumVersionById(version.id));
    }

    function retireCurriculumVersion(id) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('curriculum.retire', { id: id });
        const versions = ELIPS.getCurriculumVersions();
        const version = versions.find(function (v) { return v.id === Number(id); });
        if (!version) return failure('Versi kurikulum tidak ditemukan.');
        if (version.status === 'retired') return failure('Versi kurikulum sudah berstatus Retired.');
        if (version.status === 'draft') return failure('Versi Draft dapat dihapus, bukan dipensiunkan.');

        version.status = 'retired';
        version.updatedAt = new Date().toISOString();
        ELIPS.setCurriculumVersions(versions);
        syncCourseVersion(version.courseId);

        recordAudit({
            action: 'retire',
            entity: 'curriculum',
            entityId: version.id,
            entityLabel: version.name,
            detail: 'Memensiunkan versi kurikulum "' + version.name + '" (' + version.code + '). Riwayat kelas tetap tersedia.'
        });
        return success('Versi kurikulum dipensiunkan. Kelas lama tetap menggunakan versi ini.', version);
    }

    function deleteCurriculumVersion(id) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('curriculum.delete', { id: id });
        const version = ELIPS.getCurriculumVersionById(id);
        if (!version) return failure('Versi kurikulum tidak ditemukan.');
        if (version.status !== 'draft') {
            return failure('Hanya versi Draft tanpa kelas yang dapat dihapus. Versi terpakai harus dipensiunkan.');
        }
        if (ELIPS.getClassesByCourse(version.courseId).some(function (c) {
            return Number(c.curriculumVersionId) === Number(version.id);
        })) {
            return failure('Versi kurikulum sudah dipakai kelas dan tidak dapat dihapus.');
        }

        const moduleIds = versionModules(version.id).map(function (m) { return m.id; });
        ELIPS.setCurriculumVersions(ELIPS.getCurriculumVersions().filter(function (v) { return v.id !== version.id; }));
        ELIPS.setModules(ELIPS.getModules().filter(function (m) { return moduleIds.indexOf(m.id) === -1; }));
        ELIPS.setLessons(ELIPS.getLessons().filter(function (l) {
            return Number(l.curriculumVersionId) !== Number(version.id);
        }));
        syncCourseVersion(version.courseId);

        recordAudit({
            action: 'delete',
            entity: 'curriculum',
            entityId: version.id,
            entityLabel: version.name,
            detail: 'Menghapus versi kurikulum Draft "' + version.name + '" (' + version.code + ').'
        });
        return success('Versi kurikulum Draft berhasil dihapus.');
    }

    function getCurriculumDetail(id) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('curriculum.detail', { id: id });
        const version = ELIPS.getCurriculumVersionById(id);
        if (!version) return failure('Versi kurikulum tidak ditemukan.');

        const modules = versionModules(version.id).map(function (module) {
            return Object.assign({}, module, {
                lessons: ELIPS.getLessonsByModule(module.id).map(function (lesson) {
                    return Object.assign({}, lesson);
                })
            });
        });

        return success('Detail kurikulum dimuat.', Object.assign({}, version, { modules: modules }));
    }

    // ---------- Classes / cohorts ----------

    function activeClassEnrollmentCount(classId) {
        return ELIPS.getClassEnrollments(classId).filter(function (e) {
            return e.status === 'active' || e.status === 'pending';
        }).length;
    }

    function generateClassCode(courseId, name) {
        const prefix = 'ANG-' + new Date().getFullYear() + '-' + courseId;
        const clean = String(name || '').replace(/[^a-zA-Z0-9]+/g, '').toUpperCase().slice(0, 4) || 'KLS';
        let code = prefix + '-' + clean;
        let suffix = 2;
        while (ELIPS.getClasses().some(function (c) { return c.code === code; })) {
            code = prefix + '-' + clean + '-' + suffix++;
        }
        return code;
    }

    function normalizeDate(value) {
        const text = String(value || '').trim();
        return /^\d{4}-\d{2}-\d{2}$/.test(text) ? text : null;
    }

    function createClass(data) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('class.create', data || {});
        data = data || {};
        const course = ELIPS.getCourseById(data.courseId);
        if (!course) return failure('Kursus tidak ditemukan.');

        const version = ELIPS.getCurriculumVersionById(data.curriculumVersionId);
        if (!version || Number(version.courseId) !== Number(course.id)) {
            return failure('Versi kurikulum tidak valid untuk kursus ini.');
        }
        if (['published', 'locked'].indexOf(version.status) === -1) {
            return failure('Pilih versi kurikulum yang sudah Published.');
        }

        const name = String(data.name || '').trim();
        if (!name) return failure('Nama kelas wajib diisi.');
        const code = String(data.code || '').trim() || generateClassCode(course.id, name);
        if (ELIPS.getClasses().some(function (c) { return c.code === code; })) {
            return failure('Kode kelas sudah dipakai.');
        }

        const instructorId = data.instructorId ? Number(data.instructorId) : 0;
        if (instructorId > 0 && !isActiveInstructor(instructorId)) {
            return failure('Tentor harus berstatus aktif.');
        }

        const periodStart = normalizeDate(data.periodStart);
        const periodEnd = normalizeDate(data.periodEnd);
        if (data.periodStart && !periodStart) return failure('Tanggal mulai tidak valid.');
        if (data.periodEnd && !periodEnd) return failure('Tanggal selesai tidak valid.');
        if (periodStart && periodEnd && periodStart > periodEnd) {
            return failure('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        const status = data.status || 'draft';
        if (['draft', 'open'].indexOf(status) === -1) {
            return failure('Kelas baru hanya dapat berstatus Draft atau Open.');
        }

        const classes = ELIPS.getClasses();
        const instructor = instructorId > 0 ? ELIPS.getUserById(instructorId) : null;
        const item = {
            id: ELIPS.nextId(classes),
            code: code,
            name: name,
            courseId: course.id,
            courseTitle: course.title,
            curriculumVersionId: version.id,
            curriculumVersionCode: version.code,
            curriculumVersionName: version.name,
            instructorId: instructorId > 0 ? instructorId : null,
            instructorName: instructor ? instructor.name : '',
            periodStart: periodStart,
            periodEnd: periodEnd,
            quota: Math.max(0, Number(data.quota) || 0),
            location: String(data.location || '').trim(),
            room: String(data.room || '').trim(),
            scheduleNote: String(data.scheduleNote || '').trim(),
            plannedJp: Math.max(0, Number(data.plannedJp) || 0),
            status: status,
            legacyImport: false,
            notes: String(data.notes || '').trim(),
            enrollmentCount: 0,
            activeEnrollmentCount: 0,
            createdBy: data.actorId || null,
            updatedBy: data.actorId || null,
            createdAt: new Date().toISOString()
        };
        classes.push(item);
        ELIPS.setClasses(classes);

        // Kunci versi kurikulum agar kelas yang berjalan tidak berubah.
        if (version.status === 'published') {
            const versions = ELIPS.getCurriculumVersions();
            const stored = versions.find(function (v) { return v.id === version.id; });
            if (stored) {
                stored.status = 'locked';
                stored.updatedAt = new Date().toISOString();
                ELIPS.setCurriculumVersions(versions);
            }
        }

        if (instructorId > 0) {
            const histories = ELIPS.getClassInstructorHistories();
            histories.push({
                id: ELIPS.nextId(histories),
                classId: item.id,
                instructorId: instructorId,
                instructorName: instructor ? instructor.name : '',
                startedAt: periodStart || todayString(),
                endedAt: null,
                reason: 'Penetapan tentor awal saat kelas dibuat.',
                actorId: data.actorId || null,
                createdAt: new Date().toISOString()
            });
            ELIPS.setClassInstructorHistories(histories);
        }

        refreshVersionCounters(version.id);
        syncCourseVersion(course.id);

        recordAudit({
            action: 'create',
            entity: 'class',
            entityId: item.id,
            entityLabel: item.name,
            actorId: data.actorId,
            detail: 'Membuat kelas "' + item.name + '" (' + code + ') untuk kursus "' + course.title +
                '" dengan kurikulum versi ' + version.code + '.'
        });
        return success('Kelas berhasil dibuat dan versi kurikulum dikunci untuk kelas ini.', item);
    }

    function applyClassInstructor(classId, instructorId, reason, actorId) {
        const classes = ELIPS.getClasses();
        const item = classes.find(function (c) { return c.id === Number(classId); });
        if (!item) return null;
        const today = todayString();

        const histories = ELIPS.getClassInstructorHistories();
        histories.forEach(function (history) {
            if (history.classId === item.id && !history.endedAt) history.endedAt = today;
        });

        const instructor = instructorId ? ELIPS.getUserById(instructorId) : null;
        item.instructorId = instructorId || null;
        item.instructorName = instructor ? instructor.name : '';
        item.updatedBy = actorId || null;
        item.updatedAt = new Date().toISOString();
        ELIPS.setClasses(classes);

        if (instructorId) {
            histories.push({
                id: ELIPS.nextId(histories),
                classId: item.id,
                instructorId: instructorId,
                instructorName: instructor ? instructor.name : '',
                startedAt: today,
                endedAt: null,
                reason: reason || 'Pergantian tentor oleh Admin.',
                actorId: actorId || null,
                createdAt: new Date().toISOString()
            });
        }
        ELIPS.setClassInstructorHistories(histories);
        return item;
    }

    function updateClass(id, data) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('class.update', { id: id, data: data || {} });
        data = data || {};
        const classes = ELIPS.getClasses();
        const item = classes.find(function (c) { return c.id === Number(id); });
        if (!item) return failure('Kelas tidak ditemukan.');
        if (item.status === 'archived') return failure('Kelas yang sudah diarsipkan tidak dapat diubah.');

        const name = data.name !== undefined ? String(data.name).trim() : item.name;
        if (!name) return failure('Nama kelas wajib diisi.');

        const periodStart = data.periodStart !== undefined ? normalizeDate(data.periodStart) : item.periodStart;
        const periodEnd = data.periodEnd !== undefined ? normalizeDate(data.periodEnd) : item.periodEnd;
        if (data.periodStart && !periodStart) return failure('Tanggal mulai tidak valid.');
        if (data.periodEnd && !periodEnd) return failure('Tanggal selesai tidak valid.');
        if (periodStart && periodEnd && periodStart > periodEnd) {
            return failure('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        const activeCount = activeClassEnrollmentCount(item.id);
        const quota = data.quota !== undefined ? Math.max(0, Number(data.quota) || 0) : Number(item.quota) || 0;
        if (quota > 0 && quota < activeCount) {
            return failure('Kuota tidak boleh lebih kecil dari jumlah peserta aktif (' + activeCount + ').');
        }

        const nextInstructorId = data.instructorId !== undefined
            ? (data.instructorId ? Number(data.instructorId) : 0)
            : (item.instructorId || 0);
        if (nextInstructorId > 0 && !isActiveInstructor(nextInstructorId)) {
            return failure('Tentor harus berstatus aktif.');
        }

        item.name = name;
        item.periodStart = periodStart;
        item.periodEnd = periodEnd;
        item.quota = quota;
        if (data.location !== undefined) item.location = String(data.location).trim();
        if (data.room !== undefined) item.room = String(data.room).trim();
        if (data.scheduleNote !== undefined) item.scheduleNote = String(data.scheduleNote).trim();
        if (data.plannedJp !== undefined) item.plannedJp = Math.max(0, Number(data.plannedJp) || 0);
        if (data.notes !== undefined) item.notes = String(data.notes).trim();
        item.updatedBy = data.actorId || null;
        item.updatedAt = new Date().toISOString();
        ELIPS.setClasses(classes);

        if (nextInstructorId !== (item.instructorId || 0)) {
            applyClassInstructor(item.id, nextInstructorId || null,
                data.instructorReason || 'Pergantian tentor melalui pembaruan kelas.', data.actorId);
        }

        recordAudit({
            action: 'update',
            entity: 'class',
            entityId: item.id,
            entityLabel: item.name,
            actorId: data.actorId,
            detail: 'Memperbarui kelas "' + item.name + '" (' + item.code + ').'
        });
        return success('Kelas berhasil diperbarui.', ELIPS.getClassById(item.id));
    }

    function setClassStatus(id, status) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('class.status', { id: id, status: status });
        const allowed = ['draft', 'open', 'ready', 'ongoing', 'completed', 'cancelled', 'archived'];
        if (allowed.indexOf(status) === -1) return failure('Status kelas tidak valid.');

        const classes = ELIPS.getClasses();
        const item = classes.find(function (c) { return c.id === Number(id); });
        if (!item) return failure('Kelas tidak ditemukan.');
        if ((status === 'ready' || status === 'ongoing') && !isActiveInstructor(item.instructorId)) {
            return failure('Tetapkan tentor aktif sebelum kelas berstatus Ready atau Ongoing.');
        }

        const previous = item.status;
        item.status = status;
        item.updatedAt = new Date().toISOString();
        ELIPS.setClasses(classes);

        recordAudit({
            action: 'status',
            entity: 'class',
            entityId: item.id,
            entityLabel: item.name,
            detail: 'Mengubah status kelas "' + item.name + '" dari ' + previous + ' menjadi ' + status + '.'
        });
        return success('Status kelas diperbarui menjadi ' + status + '.', item);
    }

    function assignClassInstructor(id, instructorId, reason, actorId) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('class.assignInstructor', {
                id: id,
                instructorId: instructorId,
                reason: reason
            });
        }
        const item = ELIPS.getClassById(id);
        if (!item) return failure('Kelas tidak ditemukan.');
        if (!isActiveInstructor(instructorId)) return failure('Pilih tentor aktif yang valid.');
        if (item.instructorId && Number(item.instructorId) === Number(instructorId)) {
            return success('Tentor tersebut sudah ditugaskan pada kelas ini.', item);
        }

        const applied = applyClassInstructor(item.id, Number(instructorId),
            String(reason || '').trim() || 'Pergantian tentor oleh Admin.', actorId);
        const instructor = ELIPS.getUserById(instructorId);
        recordAudit({
            action: 'assign',
            entity: 'class',
            entityId: applied.id,
            entityLabel: applied.name,
            actorId: actorId,
            detail: 'Menetapkan "' + (instructor ? instructor.name : 'Tentor #' + instructorId) +
                '" sebagai tentor kelas "' + applied.name + '".'
        });
        return success('Tentor kelas berhasil ditetapkan.', applied);
    }

    function deleteClass(id) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('class.delete', { id: id });
        const item = ELIPS.getClassById(id);
        if (!item) return failure('Kelas tidak ditemukan.');
        if (['draft', 'cancelled'].indexOf(item.status) === -1) {
            return failure('Hanya kelas Draft atau Cancelled tanpa peserta yang dapat dihapus.');
        }
        if (ELIPS.getClassEnrollments(item.id).length > 0) {
            return failure('Kelas sudah memiliki enrollment. Ubah status kelas atau arsipkan, jangan hapus permanen.');
        }

        ELIPS.setClasses(ELIPS.getClasses().filter(function (c) { return c.id !== item.id; }));
        ELIPS.setClassInstructorHistories(ELIPS.getClassInstructorHistories().filter(function (h) {
            return h.classId !== item.id;
        }));
        refreshVersionCounters(item.curriculumVersionId);
        syncCourseVersion(item.courseId);

        recordAudit({
            action: 'delete',
            entity: 'class',
            entityId: item.id,
            entityLabel: item.name,
            detail: 'Menghapus kelas "' + item.name + '" (' + item.code + ') yang belum memiliki peserta.'
        });
        return success('Kelas berhasil dihapus.');
    }

    Object.assign(ELIPS, {
        createCurriculumVersion: createCurriculumVersion,
        updateCurriculumVersion: updateCurriculumVersion,
        publishCurriculumVersion: publishCurriculumVersion,
        cloneCurriculumVersion: cloneCurriculumVersion,
        retireCurriculumVersion: retireCurriculumVersion,
        deleteCurriculumVersion: deleteCurriculumVersion,
        getCurriculumDetail: getCurriculumDetail,
        createClass: createClass,
        updateClass: updateClass,
        setClassStatus: setClassStatus,
        assignClassInstructor: assignClassInstructor,
        deleteClass: deleteClass,
        activeClassEnrollmentCount: activeClassEnrollmentCount
    });
})(window.ELIPS);
