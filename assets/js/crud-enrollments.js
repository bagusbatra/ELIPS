// ============================================================
// ELIPS LMS - crud-enrollments.js
// CRUD penugasan siswa ke kursus (enrollment).
// ============================================================

(function (ELIPS) {
    'use strict';

    const { success, failure, todayString } = ELIPS._;

    function assignStudentToCourses(studentId, courseIds, enrolledAt) {
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
        requested.forEach(function (courseId) {
            const exists = enrollments.some(function (e) {
                return e.studentId === student.id && e.courseId === courseId;
            });
            if (exists) return;
            enrollments.push({
                id: ELIPS.nextId(enrollments),
                studentId: student.id,
                courseId: courseId,
                enrolledBy: 'admin',
                enrolledAt: enrolledAt || todayString(),
                status: 'active'
            });
            added++;
        });

        ELIPS.setEnrollments(enrollments);
        return success(
            added > 0 ? added + ' penugasan berhasil dibuat.' : 'Semua kursus yang dipilih sudah ditugaskan.',
            { added: added }
        );
    }

    function setEnrollmentStatus(id, status) {
        if (status !== 'active' && status !== 'dihentikan') return failure('Status penugasan tidak valid.');
        const enrollments = ELIPS.getEnrollments();
        const enrollment = enrollments.find(function (e) { return e.id === Number(id); });
        if (!enrollment) return failure('Penugasan tidak ditemukan.');
        enrollment.status = status;
        enrollment.updatedAt = new Date().toISOString();
        ELIPS.setEnrollments(enrollments);
        return success(status === 'active' ? 'Penugasan diaktifkan.' : 'Penugasan dihentikan.', enrollment);
    }

    function deleteEnrollment(id) {
        const enrollment = ELIPS.getEnrollments().find(function (e) { return e.id === Number(id); });
        if (!enrollment) return failure('Penugasan tidak ditemukan.');

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

        return success('Penugasan dan data terkait berhasil dihapus.');
    }

    Object.assign(ELIPS, {
        assignStudentToCourses: assignStudentToCourses,
        setEnrollmentStatus: setEnrollmentStatus,
        deleteEnrollment: deleteEnrollment
    });
})(window.ELIPS);
