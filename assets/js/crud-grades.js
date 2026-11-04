// ============================================================
// ELIPS LMS - crud-grades.js
// Input nilai dan alur sertifikat Draft -> Publish.
// ============================================================

(function (ELIPS) {
    'use strict';

    const { success, failure } = ELIPS._;

    function recordAudit(entry) {
        if (typeof ELIPS.logAudit === 'function') ELIPS.logAudit(entry);
    }

    function generateDraftCertificateNumber() {
        let number;
        do {
            number = 'CERT-DRAFT-' + Date.now().toString().slice(-6) + '-' +
                Math.random().toString(36).slice(2, 5).toUpperCase();
        } while (ELIPS.getCertificates().some(function (cert) {
            return String(cert.certificateNumber) === number;
        }));
        return number;
    }

    function saveGrade(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('grade.save', payload || {});
        payload = payload || {};
        const studentId = Number(payload.studentId);
        const courseId = Number(payload.courseId);
        const score = Number(payload.score);
        const note = String(payload.note || '').trim();

        if (!studentId || !courseId) return failure('Peserta atau kursus tidak valid.');
        if (!Number.isFinite(score) || score < 0 || score > 100) return failure('Masukkan nilai antara 0-100.');

        const course = ELIPS.getCourseById(courseId);
        if (!course) return failure('Kursus tidak ditemukan.');
        if (payload.instructorId !== undefined && payload.instructorId !== null &&
            course.instructorId !== Number(payload.instructorId)) {
            return failure('Anda tidak berhak menilai kursus ini.');
        }

        const enrollments = ELIPS.getEnrollments();
        const enrollment = payload.enrollmentId
            ? enrollments.find(function (e) { return e.id === Number(payload.enrollmentId); })
            : ELIPS.getEnrollment(studentId, courseId);
        if (!enrollment || enrollment.studentId !== studentId || enrollment.courseId !== courseId) {
            return failure('Penugasan peserta pada kursus ini tidak ditemukan.');
        }

        const letter = ELIPS.gradeLetter(score);
        const grades = ELIPS.getGrades();
        let grade = grades.find(function (item) {
            return item.enrollmentId === enrollment.id ||
                (item.studentId === studentId && item.courseId === courseId);
        });
        const isNewGrade = !grade;

        if (grade) {
            grade.enrollmentId = enrollment.id;
            grade.score = score;
            grade.grade = letter;
            grade.note = note;
            if (payload.instructorId !== undefined && payload.instructorId !== null) {
                grade.instructorId = Number(payload.instructorId);
            }
            grade.updatedAt = new Date().toISOString();
        } else {
            grade = {
                id: ELIPS.nextId(grades),
                enrollmentId: enrollment.id,
                courseId: courseId,
                studentId: studentId,
                instructorId: payload.instructorId !== undefined && payload.instructorId !== null
                    ? Number(payload.instructorId)
                    : course.instructorId,
                score: score,
                grade: letter,
                note: note,
                createdAt: new Date().toISOString()
            };
            grades.push(grade);
        }
        ELIPS.setGrades(grades);

        const certificates = ELIPS.getCertificates();
        let certificate = certificates.find(function (cert) {
            return cert.enrollmentId === enrollment.id ||
                (cert.studentId === studentId && cert.courseId === courseId);
        });
        const certificateExisted = !!certificate;

        if (certificate) {
            certificate.enrollmentId = enrollment.id;
            certificate.grade = letter;
            certificate.score = score;
            certificate.issuedByInstructorAt = new Date().toISOString();
            if (certificate.status !== 'publish') certificate.status = 'draft';
            if (!certificate.certificateNumber) certificate.certificateNumber = generateDraftCertificateNumber();
            certificate.updatedAt = new Date().toISOString();
        } else {
            certificate = {
                id: ELIPS.nextId(certificates),
                studentId: studentId,
                courseId: courseId,
                enrollmentId: enrollment.id,
                certificateNumber: generateDraftCertificateNumber(),
                status: 'draft',
                grade: letter,
                score: score,
                issuedByInstructorAt: new Date().toISOString(),
                approvedByAdminId: null,
                approvedAt: null,
                createdAt: new Date().toISOString()
            };
            certificates.push(certificate);
        }
        ELIPS.setCertificates(certificates);

        const student = ELIPS.getUserById(studentId);
        const studentName = student ? student.name : 'Peserta #' + studentId;
        recordAudit({
            action: 'grade',
            entity: 'grade',
            entityId: grade.id,
            entityLabel: studentName + ' — ' + course.title,
            actorId: payload.instructorId,
            detail: (isNewGrade ? 'Memberi' : 'Memperbarui') + ' nilai ' + score + ' (' + letter + ')' +
                (note ? ' — catatan: ' + note : '') + ' untuk ' +
                studentName + ' pada kursus "' + course.title + '".'
        });

        if (!certificateExisted) {
            recordAudit({
                action: 'submit',
                entity: 'certificate',
                entityId: certificate.id,
                entityLabel: certificate.certificateNumber,
                actorId: payload.instructorId,
                detail: 'Membuat e-sertifikat Draft ' + certificate.certificateNumber + ' untuk ' +
                    studentName + ' pada kursus "' + course.title + '".'
            });
        }

        return success('Nilai disimpan. E-sertifikat Draft menunggu persetujuan Admin.', {
            grade: grade,
            certificate: certificate
        });
    }

    function createDraftCertificateForEnrollment(enrollmentId, instructorId) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('certificate.draft', { enrollmentId: enrollmentId });
        }
        const enrollment = ELIPS.getEnrollments().find(function (e) { return e.id === Number(enrollmentId); });
        if (!enrollment) return failure('Penugasan peserta tidak ditemukan.');

        const course = ELIPS.getCourseById(enrollment.courseId);
        if (!course) return failure('Kursus tidak ditemukan.');
        if (instructorId !== undefined && instructorId !== null && course.instructorId !== Number(instructorId)) {
            return failure('Anda tidak berhak mengajukan sertifikat kursus ini.');
        }
        if (ELIPS.getCertificate(enrollment.studentId, enrollment.courseId)) {
            return failure('Sertifikat peserta ini sudah ada.');
        }

        const grade = ELIPS.getGrade(enrollment.studentId, enrollment.courseId);
        if (!grade) return failure('Peserta belum memiliki nilai.');

        const certificates = ELIPS.getCertificates();
        const certificate = {
            id: ELIPS.nextId(certificates),
            studentId: enrollment.studentId,
            courseId: enrollment.courseId,
            enrollmentId: enrollment.id,
            certificateNumber: generateDraftCertificateNumber(),
            status: 'draft',
            grade: grade.grade,
            score: grade.score,
            issuedByInstructorAt: new Date().toISOString(),
            approvedByAdminId: null,
            approvedAt: null,
            createdAt: new Date().toISOString()
        };
        certificates.push(certificate);
        ELIPS.setCertificates(certificates);

        const student = ELIPS.getUserById(enrollment.studentId);
        recordAudit({
            action: 'submit',
            entity: 'certificate',
            entityId: certificate.id,
            entityLabel: certificate.certificateNumber,
            actorId: instructorId,
            detail: 'Mengajukan e-sertifikat Draft ' + certificate.certificateNumber + ' untuk ' +
                (student ? student.name : 'Peserta #' + enrollment.studentId) + ' pada kursus "' + course.title + '".'
        });
        return success('E-sertifikat Draft berhasil diajukan ke Admin.', certificate);
    }

    function approveCertificate(id, adminId) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('certificate.approve', { id: id });
        const certificates = ELIPS.getCertificates();
        const certificate = certificates.find(function (cert) { return cert.id === Number(id); });
        if (!certificate) return failure('Sertifikat tidak ditemukan.');
        if (certificate.status !== 'draft') return failure('Sertifikat ini sudah berstatus Publish.');

        const previousNumber = certificate.certificateNumber;
        const student = ELIPS.getUserById(certificate.studentId);
        const course = ELIPS.getCourseById(certificate.courseId);

        certificate.status = 'publish';
        certificate.approvedByAdminId = adminId !== undefined && adminId !== null ? Number(adminId) : 1;
        certificate.approvedAt = new Date().toISOString();
        if (!certificate.certificateNumber || String(certificate.certificateNumber).indexOf('CERT-DRAFT') === 0) {
            certificate.certificateNumber = ELIPS.generateCertificateNumber();
        }
        ELIPS.setCertificates(certificates);

        recordAudit({
            action: 'approve',
            entity: 'certificate',
            entityId: certificate.id,
            entityLabel: certificate.certificateNumber,
            actorId: adminId,
            detail: 'Menyetujui e-sertifikat Draft ' + previousNumber + ' menjadi Publish (' +
                certificate.certificateNumber + ') untuk ' + (student ? student.name : 'Peserta #' + certificate.studentId) +
                ' pada kursus "' + (course ? course.title : 'Kursus #' + certificate.courseId) + '".'
        });

        if (typeof ELIPS.notifyCertificatePublished === 'function') {
            ELIPS.notifyCertificatePublished(certificate);
        }
        return success('Sertifikat berhasil disetujui dan diterbitkan.', certificate);
    }

    Object.assign(ELIPS, {
        saveGrade: saveGrade,
        createDraftCertificateForEnrollment: createDraftCertificateForEnrollment,
        approveCertificate: approveCertificate
    });
})(window.ELIPS);
