// ============================================================
// ELIPS LMS - crud-enrollments.js
// CRUD penugasan siswa ke kursus (enrollment).
// ============================================================

(function (ELIPS) {
    'use strict';

    const { success, failure, todayString } = ELIPS._;

    function recordAudit(entry) {
        if (typeof ELIPS.logAudit === 'function') ELIPS.logAudit(entry);
    }

    function enrollmentLabel(enrollment) {
        const student = ELIPS.getUserById(enrollment.studentId);
        const course = ELIPS.getCourseById(enrollment.courseId);
        return (student ? student.name : 'Peserta #' + enrollment.studentId) + ' — ' +
            (course ? course.title : 'Kursus #' + enrollment.courseId);
    }

    function activeCountForClass(classId) {
        return ELIPS.getClassEnrollments(classId).filter(function (e) {
            return e.status === 'active' || e.status === 'pending';
        }).length;
    }

    // Kompatibilitas v3: enrollment berbasis kursus diarahkan ke kelas aktif
    // yang masih memiliki kuota bila tersedia.
    function pickOpenClass(courseId) {
        const found = ELIPS.getClassesByCourse(courseId).find(function (item) {
            if (['open', 'ready', 'ongoing'].indexOf(item.status) === -1) return false;
            const quota = Number(item.quota) || 0;
            return quota === 0 || activeCountForClass(item.id) < quota;
        });
        return found ? found.id : null;
    }

    function assignStudentToCourses(studentId, courseIds, enrolledAt) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('enrollment.assign', {
                studentId: studentId,
                courseIds: courseIds || [],
                enrolledAt: enrolledAt
            });
        }
        const student = ELIPS.getUserById(studentId);
        if (!student || student.role !== 'peserta') return failure('Pilih akun peserta yang valid.');
        if (student.status !== 'aktif') return failure('Peserta harus berstatus aktif.');

        const courses = ELIPS.getCourses();
        const requested = (courseIds || []).map(Number).filter(Boolean);
        if (requested.length === 0) return failure('Pilih minimal satu kursus.');

        const invalid = requested.some(function (courseId) {
            return !courses.some(function (course) { return course.id === courseId; });
        });
        if (invalid) return failure('Ada kursus yang tidak ditemukan.');

        const enrollments = ELIPS.getEnrollments();
        let added = 0;
        const created = [];
        requested.forEach(function (courseId) {
            const exists = enrollments.some(function (e) {
                return e.studentId === student.id && e.courseId === courseId;
            });
            if (exists) return;
            const enrollment = {
                id: ELIPS.nextId(enrollments),
                studentId: student.id,
                courseId: courseId,
                classId: pickOpenClass(courseId),
                enrolledBy: 'admin',
                enrolledAt: enrolledAt || todayString(),
                status: 'active'
            };
            enrollments.push(enrollment);
            created.push({
                enrollment: enrollment,
                course: courses.find(function (course) { return course.id === courseId; })
            });
            added++;
        });

        ELIPS.setEnrollments(enrollments);
        created.forEach(function (item) {
            recordAudit({
                action: 'create',
                entity: 'enrollment',
                entityId: item.enrollment.id,
                entityLabel: enrollmentLabel(item.enrollment),
                detail: 'Menugaskan "' + student.name + '" ke kursus "' +
                    (item.course ? item.course.title : 'Kursus #' + item.enrollment.courseId) + '".'
            });
        });
        return success(
            added > 0 ? added + ' penugasan berhasil dibuat.' : 'Semua kursus yang dipilih sudah ditugaskan.',
            { added: added }
        );
    }

    function setEnrollmentStatus(id, status) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('enrollment.status', { id: id, status: status });
        if (status !== 'active' && status !== 'dihentikan') return failure('Status penugasan tidak valid.');
        const enrollments = ELIPS.getEnrollments();
        const enrollment = enrollments.find(function (e) { return e.id === Number(id); });
        if (!enrollment) return failure('Penugasan tidak ditemukan.');
        enrollment.status = status;
        enrollment.updatedAt = new Date().toISOString();
        ELIPS.setEnrollments(enrollments);
        recordAudit({
            action: 'status',
            entity: 'enrollment',
            entityId: enrollment.id,
            entityLabel: enrollmentLabel(enrollment),
            detail: (status === 'active' ? 'Mengaktifkan' : 'Menghentikan') + ' penugasan "' +
                enrollmentLabel(enrollment) + '".'
        });
        return success(status === 'active' ? 'Penugasan diaktifkan.' : 'Penugasan dihentikan.', enrollment);
    }

    function deleteEnrollment(id) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('enrollment.delete', { id: id });
        const enrollment = ELIPS.getEnrollments().find(function (e) { return e.id === Number(id); });
        if (!enrollment) return failure('Penugasan tidak ditemukan.');
        const label = enrollmentLabel(enrollment);

        ELIPS.setEnrollments(ELIPS.getEnrollments().filter(function (e) { return e.id !== enrollment.id; }));
        ELIPS.setGrades(ELIPS.getGrades().filter(function (g) {
            return !(g.studentId === enrollment.studentId && g.courseId === enrollment.courseId);
        }));
        ELIPS.setCertificates(ELIPS.getCertificates().filter(function (c) {
            return !(c.studentId === enrollment.studentId && c.courseId === enrollment.courseId);
        }));
        ELIPS.setCompletedLessons(ELIPS.getCompletedLessons().filter(function (progress) {
            return !(progress.studentId === enrollment.studentId && progress.courseId === enrollment.courseId);
        }));

        recordAudit({
            action: 'delete',
            entity: 'enrollment',
            entityId: enrollment.id,
            entityLabel: label,
            detail: 'Menghapus penugasan "' + label + '" beserta nilai, sertifikat, dan progres terkait.'
        });
        return success('Penugasan dan data terkait berhasil dihapus.');
    }

    function assignStudentsToClass(classId, studentIds, enrolledAt) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('enrollment.assignClass', {
                classId: classId,
                studentIds: studentIds || [],
                enrolledAt: enrolledAt
            });
        }

        const targetClass = ELIPS.getClassById(classId);
        if (!targetClass) return failure('Kelas tidak ditemukan.');
        if (targetClass.status === 'cancelled' || targetClass.status === 'archived') {
            return failure('Kelas ini tidak menerima pendaftaran peserta.');
        }

        const requested = (studentIds || []).map(Number).filter(Boolean);
        if (requested.length === 0) return failure('Pilih minimal satu peserta.');

        const enrollments = ELIPS.getEnrollments();
        const quota = Number(targetClass.quota) || 0;
        let activeCount = activeCountForClass(targetClass.id);
        let added = 0;
        let skipped = 0;

        for (const studentId of requested) {
            const student = ELIPS.getUserById(studentId);
            if (!student || student.role !== 'peserta') return failure('Peserta #' + studentId + ' tidak valid.');
            if (student.status !== 'aktif') return failure('Peserta "' + student.name + '" tidak aktif.');

            const exists = enrollments.some(function (e) {
                return e.studentId === student.id && e.classId === targetClass.id;
            });
            if (exists) {
                skipped++;
                continue;
            }
            if (quota > 0 && activeCount >= quota) {
                return failure('Kuota kelas sudah penuh (' + quota + ' peserta).');
            }

            enrollments.push({
                id: ELIPS.nextId(enrollments),
                studentId: student.id,
                courseId: targetClass.courseId,
                classId: targetClass.id,
                enrolledBy: 'admin',
                enrolledAt: enrolledAt || todayString(),
                status: 'active'
            });
            added++;
            activeCount++;
            recordAudit({
                action: 'create',
                entity: 'enrollment',
                entityId: enrollments[enrollments.length - 1].id,
                entityLabel: student.name + ' — ' + targetClass.name,
                detail: 'Mendaftarkan "' + student.name + '" ke kelas "' + targetClass.name +
                    '" (' + targetClass.code + ').'
            });
        }

        ELIPS.setEnrollments(enrollments);
        return success(
            added > 0
                ? added + ' peserta berhasil didaftarkan ke kelas.' + (skipped > 0 ? ' ' + skipped + ' peserta sudah terdaftar.' : '')
                : 'Semua peserta yang dipilih sudah terdaftar di kelas ini.',
            { added: added, skipped: skipped }
        );
    }

    function setEnrollmentClassStatus(id, status) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('enrollment.classStatus', { id: id, status: status });
        }
        const allowed = ['pending', 'active', 'completed', 'withdrawn', 'suspended', 'cancelled', 'dihentikan'];
        if (allowed.indexOf(status) === -1) return failure('Status enrollment tidak valid.');
        const enrollments = ELIPS.getEnrollments();
        const enrollment = enrollments.find(function (e) { return e.id === Number(id); });
        if (!enrollment) return failure('Enrollment tidak ditemukan.');
        if (enrollment.classId === null || enrollment.classId === undefined) {
            return failure('Enrollment ini bukan enrollment berbasis kelas.');
        }

        enrollment.status = status;
        enrollment.updatedAt = new Date().toISOString();
        ELIPS.setEnrollments(enrollments);
        recordAudit({
            action: 'status',
            entity: 'enrollment',
            entityId: enrollment.id,
            entityLabel: enrollmentLabel(enrollment),
            detail: 'Mengubah status enrollment "' + enrollmentLabel(enrollment) + '" menjadi ' + status + '.'
        });
        return success('Status enrollment diperbarui menjadi ' + status + '.', enrollment);
    }

    Object.assign(ELIPS, {
        assignStudentToCourses: assignStudentToCourses,
        assignStudentsToClass: assignStudentsToClass,
        setEnrollmentStatus: setEnrollmentStatus,
        setEnrollmentClassStatus: setEnrollmentClassStatus,
        deleteEnrollment: deleteEnrollment
    });
})(window.ELIPS);
