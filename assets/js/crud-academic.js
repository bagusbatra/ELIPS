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

    // ---------- Branches / cabang ----------

    function generateBranchCode(name) {
        const clean = String(name || '').replace(/[^a-zA-Z0-9]+/g, '').toUpperCase().slice(0, 4) || 'CBG';
        const base = 'CBG-' + clean;
        let code = base;
        let suffix = 2;
        while (ELIPS.getBranches().some(function (b) { return b.code === code; })) {
            code = base + '-' + suffix++;
        }
        return code;
    }

    function createBranch(data) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('branch.create', data || {});
        data = data || {};
        const name = String(data.name || '').trim();
        if (!name) return failure('Nama cabang wajib diisi.');

        const code = String(data.code || '').trim() || generateBranchCode(name);
        if (ELIPS.getBranches().some(function (b) { return b.code === code; })) {
            return failure('Kode cabang sudah dipakai.');
        }

        const status = data.status === 'inactive' ? 'inactive' : 'active';
        const branches = ELIPS.getBranches();
        const item = {
            id: ELIPS.nextId(branches),
            code: code,
            name: name,
            address: String(data.address || '').trim(),
            city: String(data.city || '').trim(),
            phone: String(data.phone || '').trim(),
            manager: String(data.manager || '').trim(),
            status: status,
            notes: String(data.notes || '').trim(),
            createdAt: new Date().toISOString(),
            updatedAt: null
        };
        branches.push(item);
        ELIPS.setBranches(branches);

        recordAudit({
            action: 'create',
            entity: 'branch',
            entityId: item.id,
            entityLabel: item.name,
            actorId: data.actorId,
            detail: 'Membuat cabang "' + item.name + '" (' + item.code + ') di ' + (item.city || 'lokasi belum diisi') + '.'
        });
        return success('Cabang berhasil dibuat.', item);
    }

    function updateBranch(id, data) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('branch.update', { id: id, data: data || {} });
        data = data || {};
        const branches = ELIPS.getBranches();
        const item = branches.find(function (b) { return b.id === Number(id); });
        if (!item) return failure('Cabang tidak ditemukan.');

        const name = data.name !== undefined ? String(data.name).trim() : item.name;
        if (!name) return failure('Nama cabang wajib diisi.');

        const code = data.code !== undefined ? String(data.code).trim() : item.code;
        if (!code) return failure('Kode cabang wajib diisi.');
        if (branches.some(function (b) { return b.code === code && b.id !== item.id; })) {
            return failure('Kode cabang sudah dipakai.');
        }

        let status = item.status;
        if (data.status !== undefined) {
            if (['active', 'inactive'].indexOf(data.status) === -1) {
                return failure('Status cabang tidak valid.');
            }
            status = data.status;
        }

        item.code = code;
        item.name = name;
        if (data.address !== undefined) item.address = String(data.address).trim();
        if (data.city !== undefined) item.city = String(data.city).trim();
        if (data.phone !== undefined) item.phone = String(data.phone).trim();
        if (data.manager !== undefined) item.manager = String(data.manager).trim();
        item.status = status;
        if (data.notes !== undefined) item.notes = String(data.notes).trim();
        item.updatedAt = new Date().toISOString();
        ELIPS.setBranches(branches);

        // Nama/kode cabang tersimpan denormalisasi pada kelas dan sesi.
        const classes = ELIPS.getClasses();
        let classesChanged = false;
        classes.forEach(function (c) {
            if (Number(c.branchId) === item.id) {
                c.branchCode = item.code;
                c.branchName = item.name;
                c.branchCity = item.city;
                classesChanged = true;
            }
        });
        if (classesChanged) ELIPS.setClasses(classes);
        const sessions = ELIPS.getClassSessions();
        let sessionsChanged = false;
        sessions.forEach(function (s) {
            if (Number(s.branchId) === item.id) {
                s.branchCode = item.code;
                s.branchName = item.name;
                s.branchCity = item.city;
                sessionsChanged = true;
            }
        });
        if (sessionsChanged) ELIPS.setClassSessions(sessions);

        recordAudit({
            action: 'update',
            entity: 'branch',
            entityId: item.id,
            entityLabel: item.name,
            actorId: data.actorId,
            detail: 'Memperbarui cabang "' + item.name + '" (' + item.code + ').'
        });
        return success('Cabang berhasil diperbarui.', item);
    }

    function setBranchStatus(id, status) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('branch.status', { id: id, status: status });
        if (['active', 'inactive'].indexOf(status) === -1) return failure('Status cabang tidak valid.');
        const branches = ELIPS.getBranches();
        const item = branches.find(function (b) { return b.id === Number(id); });
        if (!item) return failure('Cabang tidak ditemukan.');

        const previous = item.status;
        item.status = status;
        item.updatedAt = new Date().toISOString();
        ELIPS.setBranches(branches);

        recordAudit({
            action: 'status',
            entity: 'branch',
            entityId: item.id,
            entityLabel: item.name,
            detail: 'Mengubah status cabang "' + item.name + '" dari ' + previous + ' menjadi ' + status + '.'
        });
        return success('Status cabang diperbarui menjadi ' + (status === 'active' ? 'aktif' : 'nonaktif') + '.', item);
    }

    function deleteBranch(id) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('branch.delete', { id: id });
        const branches = ELIPS.getBranches();
        const item = branches.find(function (b) { return b.id === Number(id); });
        if (!item) return failure('Cabang tidak ditemukan.');

        const classCount = ELIPS.getClasses().filter(function (c) {
            return Number(c.branchId) === Number(id);
        }).length;
        if (classCount > 0) {
            return failure('Cabang tidak dapat dihapus karena masih dipakai oleh ' + classCount + ' kelas.');
        }

        ELIPS.setBranches(branches.filter(function (b) { return b.id !== item.id; }));
        recordAudit({
            action: 'delete',
            entity: 'branch',
            entityId: item.id,
            entityLabel: item.name,
            detail: 'Menghapus cabang "' + item.name + '" (' + item.code + ').'
        });
        return success('Cabang berhasil dihapus.');
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

        const branchId = data.branchId ? Number(data.branchId) : 0;
        let branch = null;
        if (branchId > 0) {
            branch = ELIPS.getBranchById(branchId);
            if (!branch) return failure('Cabang tidak ditemukan.');
            if (branch.status !== 'active') return failure('Cabang harus berstatus aktif.');
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
            branchId: branch ? branch.id : null,
            branchCode: branch ? branch.code : '',
            branchName: branch ? branch.name : '',
            branchCity: branch ? branch.city : '',
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

        let nextBranch = item.branchId ? ELIPS.getBranchById(item.branchId) : null;
        if (data.branchId !== undefined) {
            const branchId = data.branchId ? Number(data.branchId) : 0;
            nextBranch = null;
            if (branchId > 0) {
                nextBranch = ELIPS.getBranchById(branchId);
                if (!nextBranch) return failure('Cabang tidak ditemukan.');
                if (nextBranch.status !== 'active') return failure('Cabang harus berstatus aktif.');
            }
        }

        item.name = name;
        item.branchId = nextBranch ? nextBranch.id : null;
        item.branchCode = nextBranch ? nextBranch.code : '';
        item.branchName = nextBranch ? nextBranch.name : '';
        item.branchCity = nextBranch ? nextBranch.city : '';
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

    // ---------- Class sessions & attendance ----------

    const SESSION_STATUSES = ['scheduled', 'confirmed', 'completed', 'rescheduled', 'cancelled', 'no_show'];
    const ATTENDANCE_STATUSES = ['present', 'late', 'permission', 'sick', 'absent', 'excused'];

    function getAttendancePolicy() {
        return {
            presentStatuses: ['present', 'late'],
            countedStatuses: ['confirmed', 'completed'],
            presentPercentageBasis: 'JP'
        };
    }

    function sessionActor() {
        return typeof ELIPS.getCurrentUser === 'function' ? ELIPS.getCurrentUser() : null;
    }

    function canManageClassLocal(classItem) {
        const user = sessionActor();
        if (!user || !classItem) return false;
        if (user.role === 'admin') return true;
        return user.role === 'instruktur' && Number(classItem.instructorId) === Number(user.id);
    }

    function canManageSessionLocal(session) {
        const user = sessionActor();
        if (!user || !session) return false;
        if (user.role === 'admin') return true;
        if (user.role !== 'instruktur') return false;
        const classItem = ELIPS.getClassById(session.classId);
        if (!classItem) return false;
        if (session.instructorId !== null && session.instructorId !== undefined) {
            return Number(session.instructorId) === Number(user.id);
        }
        return Number(classItem.instructorId) === Number(user.id);
    }

    function normalizeSessionTime(value) {
        const text = String(value || '').trim();
        if (!/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/.test(text)) return null;
        return text.slice(0, 5);
    }

    function sessionWeight(session) {
        const planned = Number(session.plannedJp) || 0;
        if (planned > 0) return planned;
        const actual = Number(session.actualJp) || 0;
        if (actual > 0) return actual;
        return 1;
    }

    function sessionNextNumber(classId) {
        return ELIPS.getSessionsByClass(classId).reduce(function (max, item) {
            return Math.max(max, Number(item.sessionNumber) || 0);
        }, 0) + 1;
    }

    function classSessionLabel(classItem, session) {
        return (classItem ? classItem.name : 'Kelas #' + session.classId) +
            ' — Pertemuan ' + session.sessionNumber;
    }

    function buildSessionValues(classItem, data, sessionNumber) {
        data = data || {};
        const sessionDate = normalizeDate(data.sessionDate);
        if (!sessionDate) return failure('Tanggal sesi tidak valid.');

        const startTime = normalizeSessionTime(data.startTime);
        const endTime = normalizeSessionTime(data.endTime);
        if (!startTime) return failure('Waktu mulai wajib diisi dengan format JJ:MM.');
        if (!endTime) return failure('Waktu selesai wajib diisi dengan format JJ:MM.');
        if (endTime <= startTime) return failure('Waktu selesai harus setelah waktu mulai.');

        let moduleId = data.moduleId ? Number(data.moduleId) : null;
        if (moduleId > 0) {
            const module = ELIPS.getModules().find(function (m) { return Number(m.id) === moduleId; });
            if (!module || Number(module.curriculumVersionId) !== Number(classItem.curriculumVersionId)) {
                return failure('Modul sesi tidak sesuai dengan kurikulum kelas.');
            }
        } else {
            moduleId = null;
        }

        let instructorId = data.instructorId !== undefined && data.instructorId !== null && data.instructorId !== ''
            ? Number(data.instructorId)
            : Number(classItem.instructorId) || 0;
        if (instructorId < 0) instructorId = 0;
        if (instructorId > 0 && !isActiveInstructor(instructorId)) {
            return failure('Instruktur sesi harus berstatus aktif.');
        }

        const status = String(data.status || 'scheduled');
        if (SESSION_STATUSES.indexOf(status) === -1) return failure('Status sesi tidak valid.');

        let plannedJp = Math.max(0, Number(data.plannedJp) || 0);
        let actualJp = Math.max(0, Number(data.actualJp) || 0);
        if (status === 'completed' && actualJp <= 0) actualJp = plannedJp;

        let title = String(data.title || '').trim();
        if (!title) title = 'Pertemuan ' + sessionNumber;

        return success('', {
            title: title,
            moduleId: moduleId,
            sessionDate: sessionDate,
            startTime: startTime,
            endTime: endTime,
            location: data.location !== undefined ? String(data.location).trim() : String(classItem.location || ''),
            room: data.room !== undefined ? String(data.room).trim() : String(classItem.room || ''),
            instructorId: instructorId > 0 ? instructorId : null,
            plannedJp: plannedJp,
            actualJp: actualJp,
            status: status,
            notes: String(data.notes || '').trim()
        });
    }

    function decorateSession(classItem, values, sessionNumber) {
        const module = values.moduleId ? ELIPS.getModules().find(function (m) {
            return Number(m.id) === Number(values.moduleId);
        }) : null;
        const instructor = values.instructorId ? ELIPS.getUserById(values.instructorId) : null;
        return Object.assign({}, values, {
            id: 0,
            classId: Number(classItem.id),
            classCode: classItem.code,
            className: classItem.name,
            courseId: Number(classItem.courseId),
            courseTitle: classItem.courseTitle,
            branchId: classItem.branchId || null,
            branchCode: classItem.branchCode || '',
            branchName: classItem.branchName || '',
            branchCity: classItem.branchCity || '',
            sessionNumber: sessionNumber,
            moduleTitle: module ? module.title : '',
            instructorName: instructor ? instructor.name : '',
            rescheduledFromId: null,
            presentCount: 0,
            lateCount: 0,
            permissionCount: 0,
            sickCount: 0,
            absentCount: 0,
            excusedCount: 0,
            attendanceCount: 0,
            createdBy: null,
            updatedBy: null,
            createdAt: new Date().toISOString(),
            updatedAt: null
        });
    }

    function createSession(data) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('session.create', data || {});
        data = data || {};
        const classItem = ELIPS.getClassById(data.classId);
        if (!classItem) return failure('Kelas tidak ditemukan.');
        if (!canManageClassLocal(classItem)) return failure('Anda tidak berhak mengelola jadwal kelas ini.');
        if (classItem.status === 'cancelled' || classItem.status === 'archived') {
            return failure('Kelas ini tidak dapat dijadwalkan.');
        }

        let sessionNumber = Number(data.sessionNumber) || 0;
        if (sessionNumber > 0) {
            if (ELIPS.getSessionsByClass(classItem.id).some(function (s) {
                return Number(s.sessionNumber) === sessionNumber;
            })) {
                return failure('Nomor pertemuan ' + sessionNumber + ' sudah digunakan pada kelas ini.');
            }
        } else {
            sessionNumber = sessionNextNumber(classItem.id);
        }

        const built = buildSessionValues(classItem, data, sessionNumber);
        if (!built.ok) return built;

        let rescheduledFrom = null;
        if (data.rescheduledFromId) {
            rescheduledFrom = ELIPS.getClassSessionById(data.rescheduledFromId);
            if (!rescheduledFrom || Number(rescheduledFrom.classId) !== Number(classItem.id)) {
                return failure('Sesi asal penjadwalan ulang tidak ditemukan pada kelas ini.');
            }
            if (rescheduledFrom.status === 'completed') {
                return failure('Sesi yang sudah selesai tidak dapat dijadwalkan ulang.');
            }
        }

        const sessions = ELIPS.getClassSessions();
        const user = sessionActor();
        const item = decorateSession(classItem, built.data, sessionNumber);
        item.id = ELIPS.nextId(sessions);
        item.rescheduledFromId = rescheduledFrom ? rescheduledFrom.id : null;
        item.createdBy = user ? user.id : null;
        item.updatedBy = user ? user.id : null;
        sessions.push(item);
        ELIPS.setClassSessions(sessions);

        if (rescheduledFrom) {
            const stored = sessions.find(function (s) { return s.id === rescheduledFrom.id; });
            if (stored) {
                stored.status = 'rescheduled';
                stored.updatedBy = user ? user.id : null;
                stored.updatedAt = new Date().toISOString();
                ELIPS.setClassSessions(sessions);
            }
        }

        recordAudit({
            action: 'create',
            entity: 'session',
            entityId: item.id,
            entityLabel: classItem.name + ' — Pertemuan ' + sessionNumber,
            actorId: user ? user.id : null,
            detail: 'Menambahkan sesi pertemuan ' + sessionNumber + ' untuk kelas "' + classItem.name +
                '" pada ' + item.sessionDate + ' pukul ' + item.startTime + '-' + item.endTime +
                ' (' + item.plannedJp + ' JP).'
        });
        return success('Sesi pertemuan berhasil dibuat.', item);
    }

    function createBulkSessions(data) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('session.createBulk', data || {});
        data = data || {};
        const classItem = ELIPS.getClassById(data.classId);
        if (!classItem) return failure('Kelas tidak ditemukan.');
        if (!canManageClassLocal(classItem)) return failure('Anda tidak berhak mengelola jadwal kelas ini.');

        let dates = [];
        if (Array.isArray(data.dates) && data.dates.length) {
            const seen = {};
            data.dates.forEach(function (date) {
                const normalized = normalizeDate(date);
                if (normalized && !seen[normalized]) {
                    seen[normalized] = true;
                    dates.push(normalized);
                }
            });
            dates.sort();
        } else {
            const startDate = normalizeDate(data.startDate);
            if (!startDate) return failure('Tanggal awal jadwal tidak valid.');
            const weekdays = {};
            (Array.isArray(data.weekdays) ? data.weekdays : []).forEach(function (day) {
                const value = Number(day);
                if (value >= 1 && value <= 7) weekdays[value] = true;
            });
            if (!Object.keys(weekdays).length) return failure('Pilih minimal satu hari dalam seminggu.');
            const occurrences = Math.max(1, Math.min(52, Number(data.occurrences) || Object.keys(weekdays).length));
            const cursor = new Date(startDate + 'T00:00:00Z');
            let guard = 0;
            while (dates.length < occurrences && guard < 366) {
                const jsDay = cursor.getUTCDay();
                const isoDay = jsDay === 0 ? 7 : jsDay;
                if (weekdays[isoDay]) {
                    dates.push(cursor.toISOString().slice(0, 10));
                }
                cursor.setUTCDate(cursor.getUTCDate() + 1);
                guard++;
            }
        }

        if (!dates.length) return failure('Tidak ada tanggal sesi yang dipilih.');
        if (dates.length > 52) return failure('Maksimal 52 sesi dapat dibuat sekaligus.');

        const created = [];
        for (let index = 0; index < dates.length; index++) {
            const input = Object.assign({}, data, { sessionDate: dates[index] });
            delete input.dates;
            delete input.startDate;
            delete input.weekdays;
            delete input.occurrences;
            delete input.sessionNumber;
            const result = createSession(input);
            if (!result.ok) return result;
            created.push(result.data);
        }
        return success(created.length + ' sesi pertemuan berhasil dibuat.', { sessions: created });
    }

    function updateSession(id, data) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('session.update', { id: id, data: data || {} });
        data = data || {};
        const sessions = ELIPS.getClassSessions();
        const item = sessions.find(function (s) { return Number(s.id) === Number(id); });
        if (!item) return failure('Sesi tidak ditemukan.');
        if (!canManageSessionLocal(item)) return failure('Anda tidak berhak memperbarui sesi ini.');
        const classItem = ELIPS.getClassById(item.classId);
        if (!classItem) return failure('Kelas sesi tidak ditemukan.');

        const merged = Object.assign({
            title: item.title,
            moduleId: item.moduleId,
            sessionDate: item.sessionDate,
            startTime: item.startTime,
            endTime: item.endTime,
            location: item.location,
            room: item.room,
            instructorId: item.instructorId,
            plannedJp: item.plannedJp,
            actualJp: item.actualJp,
            status: item.status,
            notes: item.notes
        }, data);
        let sessionNumber = Number(item.sessionNumber);
        if (data.sessionNumber !== undefined && Number(data.sessionNumber) > 0 &&
            Number(data.sessionNumber) !== sessionNumber) {
            sessionNumber = Number(data.sessionNumber);
            if (sessions.some(function (s) {
                return Number(s.id) !== Number(id) && Number(s.sessionNumber) === sessionNumber;
            })) {
                return failure('Nomor pertemuan ' + sessionNumber + ' sudah digunakan pada kelas ini.');
            }
        }

        const built = buildSessionValues(classItem, merged, sessionNumber);
        if (!built.ok) return built;

        const user = sessionActor();
        Object.assign(item, built.data, {
            sessionNumber: sessionNumber,
            moduleTitle: built.data.moduleId
                ? (ELIPS.getModules().find(function (m) { return Number(m.id) === Number(built.data.moduleId); }) || {}).title || ''
                : '',
            instructorName: built.data.instructorId
                ? (ELIPS.getUserById(built.data.instructorId) || {}).name || ''
                : '',
            updatedBy: user ? user.id : null,
            updatedAt: new Date().toISOString()
        });
        ELIPS.setClassSessions(sessions);

        recordAudit({
            action: 'update',
            entity: 'session',
            entityId: item.id,
            entityLabel: classItem.name + ' — Pertemuan ' + sessionNumber,
            actorId: user ? user.id : null,
            detail: 'Memperbarui sesi pertemuan ' + sessionNumber + ' kelas "' + classItem.name +
                '" pada ' + item.sessionDate + ' (' + item.status + ').'
        });
        return success('Sesi pertemuan berhasil diperbarui.', item);
    }

    function setSessionStatus(id, status, extra) {
        const payload = Object.assign({ id: id, status: status }, extra || {});
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('session.status', payload);
        const sessions = ELIPS.getClassSessions();
        const item = sessions.find(function (s) { return Number(s.id) === Number(id); });
        if (!item) return failure('Sesi tidak ditemukan.');
        if (!canManageSessionLocal(item)) return failure('Anda tidak berhak mengubah status sesi ini.');
        if (SESSION_STATUSES.indexOf(status) === -1) return failure('Status sesi tidak valid.');

        const previous = item.status;
        let actualJp = extra && extra.actualJp !== undefined
            ? Math.max(0, Number(extra.actualJp) || 0)
            : Number(item.actualJp) || 0;
        if (status === 'completed' && actualJp <= 0) actualJp = Number(item.plannedJp) || 0;

        const user = sessionActor();
        item.status = status;
        item.actualJp = actualJp;
        item.updatedBy = user ? user.id : null;
        item.updatedAt = new Date().toISOString();
        ELIPS.setClassSessions(sessions);

        const classItem = ELIPS.getClassById(item.classId);
        const label = classSessionLabel(classItem, item);
        recordAudit({
            action: 'status',
            entity: 'session',
            entityId: item.id,
            entityLabel: label,
            actorId: user ? user.id : null,
            detail: 'Mengubah status sesi "' + label + '" dari ' + previous + ' menjadi ' + status + '.'
        });
        return success('Status sesi diperbarui menjadi ' + status + '.', item);
    }

    function deleteSession(id) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('session.delete', { id: id });
        const sessions = ELIPS.getClassSessions();
        const item = sessions.find(function (s) { return Number(s.id) === Number(id); });
        if (!item) return failure('Sesi tidak ditemukan.');
        if (!canManageSessionLocal(item)) return failure('Anda tidak berhak menghapus sesi ini.');
        if (item.status === 'completed') {
            return failure('Sesi yang sudah berlangsung tidak dapat dihapus. Batalkan atau koreksi datanya.');
        }
        if (ELIPS.getSessionAttendances(item.id).length > 0) {
            return failure('Sesi sudah memiliki presensi sehingga tidak dapat dihapus. Batalkan sesi untuk menjaga histori.');
        }

        const classItem = ELIPS.getClassById(item.classId);
        const label = classSessionLabel(classItem, item);
        const user = sessionActor();
        ELIPS.setClassSessions(sessions.filter(function (s) { return Number(s.id) !== Number(id); }));

        recordAudit({
            action: 'delete',
            entity: 'session',
            entityId: item.id,
            entityLabel: label,
            actorId: user ? user.id : null,
            detail: 'Menghapus sesi "' + label + '" yang belum memiliki presensi.'
        });
        return success('Sesi pertemuan berhasil dihapus.');
    }

    function getSessionDetail(id) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('session.detail', { id: id });
        const item = ELIPS.getClassSessionById(id);
        if (!item) return failure('Sesi tidak ditemukan.');
        const user = sessionActor();
        if (!user) return failure('Anda harus masuk terlebih dahulu.');
        if (user.role === 'peserta') {
            const enrolled = ELIPS.getClassEnrollments(item.classId).some(function (e) {
                return Number(e.studentId) === Number(user.id) &&
                    ['active', 'pending', 'completed'].indexOf(e.status) !== -1;
            });
            if (!enrolled) return failure('Anda tidak terdaftar pada kelas ini.');
        } else if (!canManageSessionLocal(item)) {
            return failure('Anda tidak berhak melihat sesi ini.');
        }
        return success('Detail sesi dimuat.', item);
    }

    function refreshSessionCounts(sessionId) {
        const sessions = ELIPS.getClassSessions();
        const item = sessions.find(function (s) { return Number(s.id) === Number(sessionId); });
        if (!item) return null;
        const counts = {
            present: 0, late: 0, permission: 0, sick: 0, absent: 0, excused: 0
        };
        const rows = ELIPS.getSessionAttendances(item.id);
        rows.forEach(function (row) {
            if (counts[row.status] !== undefined) counts[row.status]++;
        });
        item.presentCount = counts.present;
        item.lateCount = counts.late;
        item.permissionCount = counts.permission;
        item.sickCount = counts.sick;
        item.absentCount = counts.absent;
        item.excusedCount = counts.excused;
        item.attendanceCount = rows.length;
        item.updatedAt = new Date().toISOString();
        ELIPS.setClassSessions(sessions);
        return item;
    }

    function saveAttendanceBulk(sessionId, records) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('attendance.saveBulk', { sessionId: sessionId, records: records || [] });
        }
        const session = ELIPS.getClassSessionById(sessionId);
        if (!session) return failure('Sesi tidak ditemukan.');
        if (!canManageSessionLocal(session)) return failure('Anda tidak berhak mengisi presensi sesi ini.');
        if (['cancelled', 'rescheduled', 'no_show'].indexOf(session.status) !== -1) {
            return failure('Presensi tidak dapat diisi untuk sesi berstatus ' + session.status + '.');
        }
        if (!Array.isArray(records) || !records.length) return failure('Tidak ada data presensi yang dikirim.');

        const user = sessionActor();
        const attendances = ELIPS.getAttendances();
        const corrections = ELIPS.getAttendanceCorrections();
        let saved = 0;
        let updated = 0;
        let corrected = 0;

        for (let index = 0; index < records.length; index++) {
            const record = records[index] || {};
            const status = String(record.status || '');
            if (ATTENDANCE_STATUSES.indexOf(status) === -1) {
                return failure('Status presensi "' + status + '" tidak valid.');
            }
            let enrollment = null;
            if (record.enrollmentId) {
                enrollment = ELIPS.getEnrollments().find(function (e) {
                    return Number(e.id) === Number(record.enrollmentId) &&
                        Number(e.classId) === Number(session.classId);
                }) || null;
            } else if (record.studentId) {
                enrollment = ELIPS.getClassEnrollments(session.classId).find(function (e) {
                    return Number(e.studentId) === Number(record.studentId) &&
                        ['active', 'pending', 'completed'].indexOf(e.status) !== -1;
                }) || null;
            }
            if (!enrollment) return failure('Peserta pada data presensi bukan anggota kelas sesi ini.');

            const existing = attendances.find(function (a) {
                return Number(a.sessionId) === Number(sessionId) &&
                    Number(a.enrollmentId) === Number(enrollment.id);
            });
            const now = new Date().toISOString();
            const note = String(record.note || '').trim();
            let reason = String(record.reason || '').trim();

            if (!existing) {
                const student = ELIPS.getUserById(enrollment.studentId);
                attendances.push({
                    id: ELIPS.nextId(attendances),
                    sessionId: Number(sessionId),
                    enrollmentId: Number(enrollment.id),
                    studentId: Number(enrollment.studentId),
                    studentName: student ? student.name : 'Peserta #' + enrollment.studentId,
                    status: status,
                    recordedBy: user ? user.id : null,
                    recordedByName: user ? user.name : '',
                    recordedAt: now,
                    note: note,
                    correctedAt: null,
                    correctionReason: '',
                    createdAt: now,
                    updatedAt: null
                });
                saved++;
                continue;
            }

            if (existing.status !== status) {
                if (!reason) reason = 'Pembaruan presensi sesi pertemuan ' + session.sessionNumber + '.';
                corrections.push({
                    id: ELIPS.nextId(corrections),
                    attendanceId: existing.id,
                    sessionId: Number(sessionId),
                    enrollmentId: Number(enrollment.id),
                    oldStatus: existing.status,
                    newStatus: status,
                    actorId: user ? user.id : null,
                    actorName: user ? user.name : 'Sistem',
                    actorRole: user ? user.role : 'sistem',
                    reason: reason,
                    correctedAt: now,
                    createdAt: now
                });
                existing.correctedAt = now;
                existing.correctionReason = reason;
                corrected++;
            }

            existing.status = status;
            existing.note = note;
            existing.recordedBy = user ? user.id : null;
            existing.recordedByName = user ? user.name : '';
            existing.recordedAt = now;
            existing.updatedAt = now;
            updated++;
        }

        ELIPS.setAttendances(attendances);
        ELIPS.setAttendanceCorrections(corrections);
        refreshSessionCounts(sessionId);

        recordAudit({
            action: 'attendance',
            entity: 'session',
            entityId: Number(sessionId),
            entityLabel: classSessionLabel(ELIPS.getClassById(session.classId), session),
            actorId: user ? user.id : null,
            detail: 'Menyimpan presensi sesi pertemuan ' + session.sessionNumber + ': ' +
                saved + ' baru, ' + updated + ' diperbarui, ' + corrected + ' dikoreksi.'
        });

        return success('Presensi berhasil disimpan.', {
            saved: saved,
            updated: updated,
            corrected: corrected,
            session: ELIPS.getClassSessionById(sessionId)
        });
    }

    function correctAttendance(id, status, reason) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('attendance.correct', { id: id, status: status, reason: reason });
        }
        const attendances = ELIPS.getAttendances();
        const attendance = attendances.find(function (a) { return Number(a.id) === Number(id); });
        if (!attendance) return failure('Presensi tidak ditemukan.');
        const session = ELIPS.getClassSessionById(attendance.sessionId);
        if (!session) return failure('Sesi presensi tidak ditemukan.');
        if (!canManageSessionLocal(session)) return failure('Anda tidak berhak mengoreksi presensi sesi ini.');
        if (ATTENDANCE_STATUSES.indexOf(status) === -1) return failure('Status presensi tidak valid.');
        reason = String(reason || '').trim();
        if (!reason) return failure('Alasan koreksi wajib diisi.');
        if (attendance.status === status) return failure('Status baru sama dengan status presensi saat ini.');

        const user = sessionActor();
        const now = new Date().toISOString();
        const corrections = ELIPS.getAttendanceCorrections();
        const correction = {
            id: ELIPS.nextId(corrections),
            attendanceId: Number(id),
            sessionId: Number(attendance.sessionId),
            enrollmentId: Number(attendance.enrollmentId),
            oldStatus: attendance.status,
            newStatus: status,
            actorId: user ? user.id : null,
            actorName: user ? user.name : 'Sistem',
            actorRole: user ? user.role : 'sistem',
            reason: reason,
            correctedAt: now,
            createdAt: now
        };
        corrections.push(correction);

        attendance.status = status;
        attendance.correctedAt = now;
        attendance.correctionReason = reason;
        attendance.updatedAt = now;
        ELIPS.setAttendances(attendances);
        ELIPS.setAttendanceCorrections(corrections);
        refreshSessionCounts(attendance.sessionId);

        const student = ELIPS.getUserById(attendance.studentId);
        const classItem = ELIPS.getClassById(session.classId);
        const label = (student ? student.name : 'Peserta #' + attendance.studentId) + ' — ' +
            classSessionLabel(classItem, session);
        recordAudit({
            action: 'correct',
            entity: 'attendance',
            entityId: Number(id),
            entityLabel: label,
            actorId: user ? user.id : null,
            detail: 'Mengoreksi presensi "' + label + '" dari ' + correction.oldStatus +
                ' menjadi ' + status + '. Alasan: ' + reason
        });

        return success('Koreksi presensi berhasil disimpan.', {
            attendance: attendance,
            correction: correction,
            session: ELIPS.getClassSessionById(attendance.sessionId)
        });
    }

    function buildAttendanceRecap(classId, studentId) {
        const classItem = ELIPS.getClassById(classId);
        if (!classItem) return null;
        const course = ELIPS.getCourseById(classItem.courseId);
        const policy = getAttendancePolicy();
        const sessions = ELIPS.getSessionsByClass(classItem.id);
        const countedSessions = sessions.filter(function (s) {
            return policy.countedStatuses.indexOf(s.status) !== -1;
        });
        const requiredJp = countedSessions.reduce(function (sum, s) { return sum + sessionWeight(s); }, 0);
        let plannedJp = 0;
        let actualJp = 0;
        sessions.forEach(function (s) {
            plannedJp += Number(s.plannedJp) || 0;
            if (['cancelled', 'no_show', 'rescheduled'].indexOf(s.status) === -1) {
                actualJp += Number(s.actualJp) || 0;
            }
        });

        const sessionIds = {};
        sessions.forEach(function (s) { sessionIds[s.id] = true; });
        const attendanceMap = {};
        ELIPS.getAttendances().forEach(function (a) {
            if (!sessionIds[a.sessionId]) return;
            attendanceMap[a.enrollmentId + ':' + a.sessionId] = a;
        });

        let enrollments = ELIPS.getClassEnrollments(classItem.id).filter(function (e) {
            return ['active', 'pending', 'completed'].indexOf(e.status) !== -1;
        });
        if (studentId !== undefined && studentId !== null) {
            enrollments = enrollments.filter(function (e) {
                return Number(e.studentId) === Number(studentId);
            });
        }

        const students = enrollments.map(function (enrollment) {
            const student = ELIPS.getUserById(enrollment.studentId);
            const counts = { present: 0, late: 0, permission: 0, sick: 0, absent: 0, excused: 0 };
            let missing = 0;
            let presentJp = 0;
            const details = [];

            sessions.forEach(function (session) {
                const attendance = attendanceMap[enrollment.id + ':' + session.id] || null;
                const status = attendance ? attendance.status : null;
                const counted = policy.countedStatuses.indexOf(session.status) !== -1;
                const weight = sessionWeight(session);

                if (status === null) {
                    missing++;
                } else if (counts[status] !== undefined) {
                    counts[status]++;
                }

                if (counted) {
                    if (status !== null && policy.presentStatuses.indexOf(status) !== -1) {
                        presentJp += weight;
                    } else if (status === null) {
                        counts.absent++;
                    }
                }

                details.push({
                    sessionId: session.id,
                    sessionNumber: session.sessionNumber,
                    sessionDate: session.sessionDate,
                    sessionStatus: session.status,
                    plannedJp: Number(session.plannedJp) || 0,
                    actualJp: Number(session.actualJp) || 0,
                    status: status
                });
            });

            const countedPresent = requiredJp > 0 ? presentJp : 0;
            const percentage = requiredJp > 0 ? Math.round((countedPresent / requiredJp) * 10000) / 100 : 0;
            return {
                enrollmentId: enrollment.id,
                studentId: enrollment.studentId,
                studentName: student ? student.name : 'Peserta #' + enrollment.studentId,
                studentEmail: student ? student.email : '',
                enrollmentStatus: enrollment.status,
                present: counts.present,
                late: counts.late,
                permission: counts.permission,
                sick: counts.sick,
                absent: counts.absent,
                excused: counts.excused,
                missing: missing,
                presentJp: Math.round(countedPresent * 100) / 100,
                requiredJp: Math.round(requiredJp * 100) / 100,
                percentage: percentage,
                sessions: details
            };
        });

        return {
            classId: Number(classItem.id),
            classCode: classItem.code,
            className: classItem.name,
            courseId: Number(classItem.courseId),
            courseTitle: course ? course.title : 'Kursus #' + classItem.courseId,
            totalSessions: sessions.length,
            countedSessions: countedSessions.length,
            plannedJp: Math.round(plannedJp * 100) / 100,
            actualJp: Math.round(actualJp * 100) / 100,
            policy: policy,
            students: students
        };
    }

    function getAttendanceRecap(classId) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('attendance.recap', { classId: classId });
        }
        const classItem = ELIPS.getClassById(classId);
        if (!classItem) return failure('Kelas tidak ditemukan.');
        const user = sessionActor();
        if (!user || (user.role !== 'admin' &&
            !(user.role === 'instruktur' && Number(classItem.instructorId) === Number(user.id)))) {
            return failure('Anda tidak berhak melihat rekap kelas ini.');
        }
        const recap = buildAttendanceRecap(classItem.id);
        if (!recap) return failure('Rekap kehadiran tidak tersedia.');
        return success('Rekap kehadiran dimuat.', recap);
    }

    function getMyAttendanceRecap(classId) {
        const payload = classId ? { classId: Number(classId) } : {};
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('attendance.myRecap', payload);
        const user = sessionActor();
        if (!user) return failure('Anda harus masuk terlebih dahulu.');
        if (user.role !== 'peserta') return failure('Rekap ini hanya tersedia untuk akun peserta.');

        let enrollments = ELIPS.getEnrollmentsByStudent(user.id).filter(function (e) {
            return e.classId !== null && e.classId !== undefined &&
                ['active', 'pending', 'completed'].indexOf(e.status) !== -1;
        });
        if (classId) {
            enrollments = enrollments.filter(function (e) { return Number(e.classId) === Number(classId); });
        }
        const seen = {};
        const classes = [];
        enrollments.forEach(function (enrollment) {
            if (seen[enrollment.classId]) return;
            seen[enrollment.classId] = true;
            const recap = buildAttendanceRecap(enrollment.classId, user.id);
            if (recap) classes.push(recap);
        });
        return success('Rekap kehadiran Anda dimuat.', { classes: classes });
    }

    Object.assign(ELIPS, {
        createBranch: createBranch,
        updateBranch: updateBranch,
        setBranchStatus: setBranchStatus,
        deleteBranch: deleteBranch,
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
        activeClassEnrollmentCount: activeClassEnrollmentCount,
        getAttendancePolicy: getAttendancePolicy,
        createSession: createSession,
        createBulkSessions: createBulkSessions,
        updateSession: updateSession,
        setSessionStatus: setSessionStatus,
        deleteSession: deleteSession,
        getSessionDetail: getSessionDetail,
        saveAttendanceBulk: saveAttendanceBulk,
        correctAttendance: correctAttendance,
        getAttendanceRecap: getAttendanceRecap,
        getMyAttendanceRecap: getMyAttendanceRecap
    });
})(window.ELIPS);
