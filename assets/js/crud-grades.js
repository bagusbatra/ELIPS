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

        if (grade && grade.status === 'finalized') {
            return failure('Nilai sudah difinalisasi dan terkunci. Gunakan koreksi nilai dengan alasan.');
        }

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

    // ---------- Penilaian & finalisasi (Phase 6) ----------
    function resolveActorId(payload) {
        if (payload && payload.actorId !== undefined && payload.actorId !== null && payload.actorId !== '') {
            return Number(payload.actorId);
        }
        const session = typeof ELIPS.getSessionUser === 'function' ? ELIPS.getSessionUser() : null;
        return session ? Number(session.id) : null;
    }

    function actorCanManageClass(classItem, actorId) {
        if (!actorId) return true;
        const actor = ELIPS.getUserById(actorId);
        if (!actor) return true;
        if (actor.role === 'admin') return true;
        return actor.role === 'instruktur' && Number(classItem.instructorId) === Number(actor.id);
    }

    function validatePredicateRanges(ranges) {
        if (!Array.isArray(ranges) || ranges.length === 0) return 'Daftar rentang predikat wajib diisi.';
        const seen = {};
        const normalized = [];
        for (let i = 0; i < ranges.length; i++) {
            const range = ranges[i] || {};
            const letter = String(range.letter || '').trim();
            if (!letter || letter.length > 4) return 'Huruf predikat #' + (i + 1) + ' tidak valid.';
            const key = letter.toLowerCase();
            if (seen[key]) return 'Huruf predikat "' + letter + '" dipakai lebih dari sekali.';
            seen[key] = true;
            const min = Number(range.minScore);
            const max = Number(range.maxScore);
            if (!isFinite(min) || !isFinite(max) || min < 0 || max > 100 || min > max) {
                return 'Rentang predikat "' + letter + '" harus berada di 0-100 dan min <= max.';
            }
            normalized.push({
                id: range.id === undefined || range.id === null ? null : Number(range.id),
                letter: letter,
                label: String(range.label || ''),
                minScore: min,
                maxScore: max,
                sortOrder: Number(range.sortOrder) || (i + 1),
                isActive: range.isActive !== false,
                createdAt: range.createdAt || new Date().toISOString(),
                updatedAt: new Date().toISOString()
            });
        }
        const active = normalized.filter(function (r) { return r.isActive; })
            .sort(function (a, b) { return a.minScore - b.minScore; });
        if (!active.length) return 'Minimal satu rentang predikat harus aktif.';
        if (active[0].minScore > 0) return 'Rentang predikat aktif harus dimulai dari skor 0.';
        for (let i = 1; i < active.length; i++) {
            if (active[i].minScore <= active[i - 1].maxScore) return 'Rentang predikat tidak boleh tumpang tindih.';
            if (Math.round((active[i].minScore - active[i - 1].maxScore) * 100) / 100 > 0.01) {
                return 'Rentang predikat harus mencakup seluruh skala tanpa celah.';
            }
        }
        if (active[active.length - 1].maxScore < 100) return 'Rentang predikat aktif harus mencakup skor hingga 100.';
        return normalized;
    }

    function savePredicateRanges(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('predicate.save', payload || {});
        payload = payload || {};
        const actorId = resolveActorId(payload);
        const actor = actorId ? ELIPS.getUserById(actorId) : null;
        if (actor && actor.role !== 'admin') return failure('Anda tidak berhak mengubah rentang predikat.');
        const normalized = validatePredicateRanges(payload.ranges);
        if (typeof normalized === 'string') return failure(normalized);
        const ranges = normalized.map(function (range, index) {
            range.id = index + 1;
            return range;
        });
        ELIPS.setPredicateRanges(ranges);
        recordAudit({
            action: 'predicate',
            entity: 'predicate',
            entityId: null,
            entityLabel: 'Rentang predikat',
            actorId: actorId,
            detail: 'Memperbarui ' + ranges.length + ' rentang predikat A-E.'
        });
        return success('Rentang predikat disimpan.', { ranges: ranges });
    }

    function saveGradeScheme(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('gradeScheme.save', payload || {});
        payload = payload || {};
        const actorId = resolveActorId(payload);
        let schemes = ELIPS.getGradeSchemes();
        let scheme = payload.id ? schemes.find(function (s) { return Number(s.id) === Number(payload.id); }) : null;
        let classId = Number(payload.classId) || (scheme ? Number(scheme.classId) : 0);
        if (!classId) return failure('Kelas tidak valid.');
        const classItem = ELIPS.getClassById(classId);
        if (!classItem) return failure('Kelas tidak ditemukan.');
        if (!actorCanManageClass(classItem, actorId)) return failure('Anda tidak berhak mengatur penilaian kelas ini.');
        if (!scheme) {
            scheme = schemes.find(function (s) { return Number(s.classId) === classId; }) || null;
        }

        const name = String(payload.name || (scheme ? scheme.name : '')).trim();
        if (!name) return failure('Nama skema penilaian wajib diisi.');
        const status = String(payload.status || (scheme ? scheme.status : 'draft'));
        if (['draft', 'published', 'archived'].indexOf(status) === -1) return failure('Status skema tidak valid.');
        if (scheme && scheme.status === 'published' && status === 'draft') {
            return failure('Skema yang sudah dipublikasikan tidak dapat dikembalikan ke draft. Arsipkan skema untuk menggantinya.');
        }
        const rounding = String(payload.rounding || (scheme ? scheme.rounding : 'half_up'));
        if (['half_up', 'half_down', 'floor', 'ceil'].indexOf(rounding) === -1) return failure('Mode pembulatan tidak valid.');
        const passScore = payload.passScore === undefined || payload.passScore === null || payload.passScore === ''
            ? Number(scheme ? scheme.passScore : 60) : Number(payload.passScore);
        if (!isFinite(passScore) || passScore < 0 || passScore > 100) return failure('Batas lulus harus antara 0-100.');

        const existingComponents = scheme ? ELIPS.getGradeComponentsByScheme(scheme.id) : [];
        const inputComponents = Array.isArray(payload.components) ? payload.components : existingComponents;
        if (!inputComponents.length && status === 'published') {
            return failure('Skema harus memiliki minimal satu komponen sebelum dipublikasikan.');
        }
        const seenNames = {};
        const components = [];
        for (let i = 0; i < inputComponents.length; i++) {
            const input = inputComponents[i] || {};
            const type = String(input.type || '');
            if (['assignment', 'exam', 'manual'].indexOf(type) === -1) return failure('Jenis komponen #' + (i + 1) + ' tidak valid.');
            const componentName = String(input.name || '').trim();
            if (!componentName) return failure('Nama komponen #' + (i + 1) + ' wajib diisi.');
            const nameKey = componentName.toLowerCase();
            if (seenNames[nameKey]) return failure('Nama komponen "' + componentName + '" dipakai lebih dari sekali.');
            seenNames[nameKey] = true;
            const weight = Number(input.weight);
            if (!isFinite(weight) || weight < 0 || weight > 100) return failure('Bobot komponen "' + componentName + '" harus antara 0-100.');
            const aggregation = String(input.aggregation || 'weighted');
            if (['weighted', 'average'].indexOf(aggregation) === -1) return failure('Agregasi komponen "' + componentName + '" tidak valid.');
            const maxScore = input.maxScore === undefined || input.maxScore === null || input.maxScore === ''
                ? 100 : Number(input.maxScore);
            if (!isFinite(maxScore) || maxScore <= 0 || maxScore > 100) return failure('Skor maksimum komponen "' + componentName + '" harus antara 0-100.');
            components.push({
                id: input.id ? Number(input.id) : null,
                schemeId: scheme ? Number(scheme.id) : null,
                type: type,
                name: componentName,
                description: String(input.description || ''),
                weight: weight,
                aggregation: aggregation,
                maxScore: maxScore,
                isRequired: input.isRequired !== false,
                order: Number(input.order) || (i + 1),
                createdAt: input.createdAt || new Date().toISOString(),
                updatedAt: new Date().toISOString()
            });
        }
        const totalWeight = components.reduce(function (total, c) { return total + c.weight; }, 0);
        if (status === 'published' && Math.abs(totalWeight - 100) > 0.01) {
            return failure('Total bobot komponen harus tepat 100% sebelum skema dipublikasikan.');
        }

        const finalizedCount = ELIPS.getGrades().filter(function (g) {
            return scheme && Number(g.schemeId) === Number(scheme.id) && g.status === 'finalized';
        }).length;
        if (finalizedCount > 0) {
            const current = existingComponents.map(function (c) { return [Number(c.id), c.type, Number(c.weight)]; }).join('|');
            const next = components.map(function (c) { return [Number(c.id || 0), c.type, Number(c.weight)]; }).join('|');
            if (current !== next) {
                return failure('Skema sudah memiliki nilai final. Komponen dan bobot tidak dapat diubah; gunakan koreksi nilai.');
            }
        }

        if (!scheme) {
            scheme = {
                id: ELIPS.nextId(schemes),
                courseId: Number(classItem.courseId),
                courseTitle: classItem.courseTitle || '',
                classId: classId,
                classCode: classItem.code || '',
                className: classItem.name || '',
                createdAt: new Date().toISOString()
            };
            schemes.push(scheme);
        }
        scheme.name = name;
        scheme.status = status;
        scheme.passScore = passScore;
        scheme.rounding = rounding;
        scheme.notes = String(payload.notes !== undefined ? payload.notes : (scheme.notes || ''));
        scheme.updatedBy = actorId;
        scheme.updatedAt = new Date().toISOString();
        ELIPS.setGradeSchemes(schemes);

        const componentScores = ELIPS.getGradeComponentScores();
        const allExistingComponents = ELIPS.getGradeSchemeComponents();
        const otherComponents = allExistingComponents.filter(function (c) {
            return Number(c.schemeId) !== Number(scheme.id);
        });
        let nextComponentId = ELIPS.nextId(allExistingComponents);
        const keptIds = [];
        const savedComponents = components.map(function (component) {
            const existing = component.id
                ? existingComponents.find(function (c) { return Number(c.id) === Number(component.id); })
                : null;
            let saved;
            if (existing) {
                saved = Object.assign(existing, component, { schemeId: Number(scheme.id) });
            } else {
                saved = Object.assign({}, component, {
                    id: nextComponentId++,
                    schemeId: Number(scheme.id),
                    createdAt: component.createdAt || new Date().toISOString()
                });
            }
            keptIds.push(Number(saved.id));
            return saved;
        });
        ELIPS.setGradeSchemeComponents(otherComponents.concat(savedComponents));
        // Hapus nilai komponen yang komponennya sudah tidak ada.
        const remainingScores = componentScores.filter(function (score) {
            return keptIds.indexOf(Number(score.componentId)) !== -1;
        });
        ELIPS.setGradeComponentScores(remainingScores);

        recordAudit({
            action: 'status',
            entity: 'grade_scheme',
            entityId: scheme.id,
            entityLabel: scheme.name,
            actorId: actorId,
            detail: 'Menyimpan skema penilaian "' + scheme.name + '" untuk kelas ' + scheme.classCode + '.'
        });
        return success('Skema penilaian disimpan.', { scheme: scheme, components: ELIPS.getGradeComponentsByScheme(scheme.id) });
    }

    function setGradeSchemeStatus(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('gradeScheme.status', payload || {});
        payload = payload || {};
        const actorId = resolveActorId(payload);
        const status = String(payload.status || '');
        if (['draft', 'published', 'archived'].indexOf(status) === -1) return failure('Status skema tidak valid.');
        const schemes = ELIPS.getGradeSchemes();
        const scheme = schemes.find(function (s) { return Number(s.id) === Number(payload.id); });
        if (!scheme) return failure('Skema penilaian tidak ditemukan.');
        if ((scheme.status === 'published' || scheme.status === 'archived') && status === 'draft') {
            return failure('Skema yang sudah dipublikasikan tidak dapat dikembalikan ke draft. Arsipkan skema untuk menggantinya.');
        }
        const classItem = ELIPS.getClassById(scheme.classId);
        if (!classItem) return failure('Kelas tidak ditemukan.');
        if (!actorCanManageClass(classItem, actorId)) return failure('Anda tidak berhak mengubah skema ini.');
        if (status === 'published') {
            const components = ELIPS.getGradeComponentsByScheme(scheme.id);
            if (!components.length) return failure('Skema harus memiliki minimal satu komponen sebelum dipublikasikan.');
            const total = ELIPS.gradeWeightTotal(components);
            if (Math.abs(total - 100) > 0.01) return failure('Total bobot komponen harus tepat 100% sebelum skema dipublikasikan.');
        }
        scheme.status = status;
        scheme.updatedBy = actorId;
        scheme.updatedAt = new Date().toISOString();
        ELIPS.setGradeSchemes(schemes);
        recordAudit({
            action: 'status',
            entity: 'grade_scheme',
            entityId: scheme.id,
            entityLabel: scheme.name,
            actorId: actorId,
            detail: 'Mengubah status skema penilaian menjadi ' + status + '.'
        });
        return success('Status skema diperbarui.', { scheme: scheme });
    }

    function deleteGradeScheme(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('gradeScheme.delete', payload || {});
        payload = payload || {};
        const actorId = resolveActorId(payload);
        const schemes = ELIPS.getGradeSchemes();
        const scheme = schemes.find(function (s) { return Number(s.id) === Number(payload.id); });
        if (!scheme) return failure('Skema penilaian tidak ditemukan.');
        const classItem = ELIPS.getClassById(scheme.classId);
        if (!classItem) return failure('Kelas tidak ditemukan.');
        if (!actorCanManageClass(classItem, actorId)) return failure('Anda tidak berhak menghapus skema ini.');
        if (scheme.status === 'published') return failure('Arsipkan skema terlebih dahulu sebelum menghapus.');
        ELIPS.setGradeSchemes(schemes.filter(function (s) { return Number(s.id) !== Number(scheme.id); }));
        ELIPS.setGradeSchemeComponents(ELIPS.getGradeSchemeComponents().filter(function (c) {
            return Number(c.schemeId) !== Number(scheme.id);
        }));
        recordAudit({
            action: 'delete',
            entity: 'grade_scheme',
            entityId: scheme.id,
            entityLabel: scheme.name,
            actorId: actorId,
            detail: 'Menghapus skema penilaian "' + scheme.name + '".'
        });
        return success('Skema penilaian dihapus.');
    }

    function gradeRoster(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('grade.roster', payload || {});
        payload = payload || {};
        const actorId = resolveActorId(payload);
        const classId = Number(payload.classId);
        const classItem = ELIPS.getClassById(classId);
        if (!classItem) return failure('Kelas tidak ditemukan.');
        if (!actorCanManageClass(classItem, actorId)) return failure('Anda tidak berhak melihat nilai kelas ini.');
        const schemeRow = ELIPS.getGradeSchemeByClass(classId);
        const scheme = schemeRow ? Object.assign({}, schemeRow, {
            components: ELIPS.getGradeComponentsByScheme(schemeRow.id),
            totalWeight: ELIPS.gradeWeightTotal(ELIPS.getGradeComponentsByScheme(schemeRow.id))
        }) : null;
        const roster = ELIPS.getClassEnrollments(classId)
            .filter(function (e) { return e.status === 'active' || e.status === 'pending'; })
            .map(function (enrollment) {
                const student = ELIPS.getUserById(enrollment.studentId);
                const computed = ELIPS.computeEnrollmentGrade(enrollment, scheme);
                const grade = ELIPS.getGrades().find(function (g) {
                    return Number(g.enrollmentId) === Number(enrollment.id);
                }) || null;
                return {
                    enrollmentId: Number(enrollment.id),
                    studentId: Number(enrollment.studentId),
                    studentName: student ? student.name : 'Peserta #' + enrollment.studentId,
                    studentEmail: student ? student.email : '',
                    enrollmentStatus: enrollment.status,
                    gradeId: grade ? Number(grade.id) : null,
                    gradeStatus: grade ? (grade.status || 'draft') : 'draft',
                    finalScore: grade && grade.finalScore !== undefined && grade.finalScore !== null ? grade.finalScore : computed.finalScore,
                    letter: grade && grade.grade ? grade.grade : computed.letter,
                    computedStatus: computed.status,
                    missing: computed.missing,
                    passed: computed.passed,
                    finalizedAt: grade ? grade.finalizedAt || null : null,
                    components: computed.components
                };
            });
        return success('', {
            class: { id: classItem.id, code: classItem.code, name: classItem.name, courseId: classItem.courseId },
            scheme: scheme,
            predicates: ELIPS.getPredicateRanges(),
            roster: roster
        });
    }

    function saveManualComponentScore(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('grade.manualSave', payload || {});
        payload = payload || {};
        const actorId = resolveActorId(payload);
        const enrollment = ELIPS.getEnrollments().find(function (e) { return Number(e.id) === Number(payload.enrollmentId); });
        if (!enrollment) return failure('Penugasan peserta tidak ditemukan.');
        const grade = ELIPS.getGrades().find(function (g) { return Number(g.enrollmentId) === Number(enrollment.id); });
        if (grade && grade.status === 'finalized') return failure('Nilai sudah difinalisasi. Gunakan koreksi nilai dengan alasan.');
        const classItem = ELIPS.getClassById(enrollment.classId);
        if (!classItem) return failure('Kelas peserta tidak ditemukan.');
        if (!actorCanManageClass(classItem, actorId)) return failure('Anda tidak berhak menilai peserta ini.');
        const scheme = ELIPS.getGradeSchemeByClass(enrollment.classId);
        if (!scheme) return failure('Skema penilaian kelas belum dibuat.');
        const component = ELIPS.getGradeComponentsByScheme(scheme.id).find(function (c) {
            return Number(c.id) === Number(payload.componentId);
        });
        if (!component) return failure('Komponen penilaian tidak ditemukan pada skema kelas.');
        if (component.type !== 'manual') return failure('Nilai komponen tugas/ujian dihitung otomatis dari aktivitas.');
        const status = String(payload.status || 'graded');
        if (['not_assessed', 'graded', 'excused', 'missing'].indexOf(status) === -1) return failure('Status komponen tidak valid.');
        let score = null;
        if (status === 'graded') {
            if (payload.score === undefined || payload.score === null || payload.score === '') {
                return failure('Masukkan nilai komponen antara 0-100.');
            }
            score = Number(payload.score);
            if (!isFinite(score) || score < 0 || score > 100) return failure('Masukkan nilai komponen antara 0-100.');
        }
        const scores = ELIPS.getGradeComponentScores();
        let record = scores.find(function (s) {
            return Number(s.componentId) === Number(component.id) && Number(s.enrollmentId) === Number(enrollment.id);
        });
        const now = new Date().toISOString();
        const actor = actorId ? ELIPS.getUserById(actorId) : null;
        if (record) {
            record.score = score;
            record.status = status;
            record.assessorId = actorId;
            record.assessorName = actor ? actor.name : record.assessorName || '';
            record.assessedAt = now;
            record.note = String(payload.note || '');
            record.updatedAt = now;
        } else {
            record = {
                id: ELIPS.nextId(scores),
                componentId: Number(component.id),
                enrollmentId: Number(enrollment.id),
                studentId: Number(enrollment.studentId),
                score: score,
                status: status,
                assessorId: actorId,
                assessorName: actor ? actor.name : '',
                assessedAt: now,
                note: String(payload.note || ''),
                createdAt: now,
                updatedAt: now
            };
            scores.push(record);
        }
        ELIPS.setGradeComponentScores(scores);
        const student = ELIPS.getUserById(enrollment.studentId);
        recordAudit({
            action: 'grade',
            entity: 'grade_component',
            entityId: component.id,
            entityLabel: component.name + ' — ' + (student ? student.name : 'Peserta #' + enrollment.studentId),
            actorId: actorId,
            detail: 'Menyimpan nilai komponen "' + component.name + '" untuk ' +
                (student ? student.name : 'Peserta #' + enrollment.studentId) +
                (score !== null ? ' sebesar ' + score : ' dengan status ' + status) + '.'
        });
        return success('Nilai komponen disimpan.', { computed: ELIPS.computeEnrollmentGrade(enrollment, scheme) });
    }

    function exemptGradeComponent(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('grade.exempt', payload || {});
        payload = payload || {};
        const actorId = resolveActorId(payload);
        const reason = String(payload.reason || payload.note || '').trim();
        if (reason.length < 5) return failure('Alasan pengecualian wajib diisi minimal 5 karakter.');
        const enrollment = ELIPS.getEnrollments().find(function (e) { return Number(e.id) === Number(payload.enrollmentId); });
        if (!enrollment) return failure('Penugasan peserta tidak ditemukan.');
        const grade = ELIPS.getGrades().find(function (g) { return Number(g.enrollmentId) === Number(enrollment.id); });
        if (grade && grade.status === 'finalized') return failure('Nilai sudah difinalisasi. Gunakan koreksi nilai dengan alasan.');
        const classItem = ELIPS.getClassById(enrollment.classId);
        if (!classItem) return failure('Kelas peserta tidak ditemukan.');
        if (!actorCanManageClass(classItem, actorId)) return failure('Anda tidak berhak mengecualikan komponen peserta ini.');
        const scheme = ELIPS.getGradeSchemeByClass(enrollment.classId);
        if (!scheme) return failure('Skema penilaian kelas belum dibuat.');
        const component = ELIPS.getGradeComponentsByScheme(scheme.id).find(function (c) {
            return Number(c.id) === Number(payload.componentId);
        });
        if (!component) return failure('Komponen penilaian tidak ditemukan pada skema kelas.');
        const scores = ELIPS.getGradeComponentScores();
        let record = scores.find(function (s) {
            return Number(s.componentId) === Number(component.id) && Number(s.enrollmentId) === Number(enrollment.id);
        });
        const now = new Date().toISOString();
        const actor = actorId ? ELIPS.getUserById(actorId) : null;
        if (record) {
            record.score = null;
            record.status = 'excused';
            record.assessorId = actorId;
            record.assessorName = actor ? actor.name : record.assessorName || '';
            record.assessedAt = now;
            record.note = reason;
            record.updatedAt = now;
        } else {
            record = {
                id: ELIPS.nextId(scores),
                componentId: Number(component.id),
                enrollmentId: Number(enrollment.id),
                studentId: Number(enrollment.studentId),
                score: null,
                status: 'excused',
                assessorId: actorId,
                assessorName: actor ? actor.name : '',
                assessedAt: now,
                note: reason,
                createdAt: now,
                updatedAt: now
            };
            scores.push(record);
        }
        ELIPS.setGradeComponentScores(scores);
        const student = ELIPS.getUserById(enrollment.studentId);
        recordAudit({
            action: 'exempt',
            entity: 'grade_component',
            entityId: component.id,
            entityLabel: component.name + ' — ' + (student ? student.name : 'Peserta #' + enrollment.studentId),
            actorId: actorId,
            detail: 'Memberi pengecualian komponen "' + component.name + '" untuk ' +
                (student ? student.name : 'Peserta #' + enrollment.studentId) + ' — alasan: ' + reason + '.'
        });
        return success('Pengecualian komponen disimpan.', { computed: ELIPS.computeEnrollmentGrade(enrollment, scheme) });
    }

    function finalizeGrades(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('grade.finalize', payload || {});
        payload = payload || {};
        const actorId = resolveActorId(payload);
        let enrollmentIds = Array.isArray(payload.enrollmentIds) ? payload.enrollmentIds.slice() : [];
        if (!enrollmentIds.length && payload.enrollmentId) enrollmentIds = [payload.enrollmentId];
        enrollmentIds = enrollmentIds.map(Number).filter(function (id) { return id > 0; });
        if (!enrollmentIds.length) return failure('Pilih minimal satu peserta untuk difinalisasi.');

        const grades = ELIPS.getGrades();
        const finalized = [];
        for (let i = 0; i < enrollmentIds.length; i++) {
            const enrollmentId = enrollmentIds[i];
            const enrollment = ELIPS.getEnrollments().find(function (e) { return Number(e.id) === enrollmentId; });
            if (!enrollment) return failure('Penugasan peserta #' + enrollmentId + ' tidak ditemukan.');
            const classItem = ELIPS.getClassById(enrollment.classId);
            if (!classItem) return failure('Kelas peserta tidak ditemukan.');
            if (!actorCanManageClass(classItem, actorId)) return failure('Anda tidak berhak memfinalisasi nilai kelas ini.');
            const scheme = ELIPS.getGradeSchemeByClass(enrollment.classId);
            if (!scheme) return failure('Skema penilaian kelas belum dibuat.');
            if (scheme.status !== 'published') return failure('Skema penilaian harus dipublikasikan sebelum nilai difinalisasi.');
            const existing = grades.find(function (g) { return Number(g.enrollmentId) === enrollmentId; });
            if (existing && existing.status === 'finalized') return failure('Nilai peserta #' + enrollmentId + ' sudah difinalisasi.');
            const computed = ELIPS.computeEnrollmentGrade(enrollment, scheme);
            if (computed.missing.length) return failure('Komponen wajib belum lengkap: ' + computed.missing.join(', ') + '.');
            if (computed.status !== 'computed' || computed.finalScore === null) {
                return failure('Nilai akhir belum dapat dihitung untuk peserta #' + enrollmentId + '.');
            }
            const now = new Date().toISOString();
            const scoreInt = Math.max(0, Math.min(100, Math.round(computed.finalScore)));
            let grade = existing;
            if (grade) {
                grade.schemeId = Number(scheme.id);
                grade.courseId = Number(enrollment.courseId);
                grade.studentId = Number(enrollment.studentId);
                grade.instructorId = Number(classItem.instructorId) || actorId;
                grade.score = scoreInt;
                grade.finalScore = computed.finalScore;
                grade.grade = computed.letter;
                grade.status = 'finalized';
                grade.components = computed.components;
                grade.finalizedBy = actorId;
                grade.finalizedAt = now;
                grade.computedAt = now;
                grade.updatedAt = now;
            } else {
                grade = {
                    id: ELIPS.nextId(grades),
                    enrollmentId: enrollmentId,
                    schemeId: Number(scheme.id),
                    courseId: Number(enrollment.courseId),
                    studentId: Number(enrollment.studentId),
                    instructorId: Number(classItem.instructorId) || actorId,
                    score: scoreInt,
                    finalScore: computed.finalScore,
                    grade: computed.letter,
                    status: 'finalized',
                    components: computed.components,
                    note: '',
                    finalizedBy: actorId,
                    finalizedAt: now,
                    computedAt: now,
                    createdAt: now,
                    updatedAt: now
                };
                grades.push(grade);
            }
            const student = ELIPS.getUserById(enrollment.studentId);
            recordAudit({
                action: 'finalize',
                entity: 'grade',
                entityId: grade.id,
                entityLabel: (student ? student.name : 'Peserta #' + enrollment.studentId) + ' — ' + classItem.code,
                actorId: actorId,
                detail: 'Memfinalisasi nilai akhir ' + computed.finalScore + ' (' + computed.letter + ') untuk ' +
                    (student ? student.name : 'Peserta #' + enrollment.studentId) + '.'
            });
            finalized.push(grade);
        }
        ELIPS.setGrades(grades);
        return success('Nilai akhir difinalisasi dan dikunci.', { grades: finalized });
    }

    function correctGrade(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('grade.correct', payload || {});
        payload = payload || {};
        const actorId = resolveActorId(payload);
        const grades = ELIPS.getGrades();
        let grade = payload.gradeId ? grades.find(function (g) { return Number(g.id) === Number(payload.gradeId); }) : null;
        if (!grade && payload.enrollmentId) {
            grade = grades.find(function (g) { return Number(g.enrollmentId) === Number(payload.enrollmentId); }) || null;
        }
        if (!grade) return failure('Nilai tidak ditemukan.');
        if (grade.status !== 'finalized') return failure('Koreksi hanya berlaku untuk nilai yang sudah difinalisasi.');
        const reason = String(payload.reason || '').trim();
        if (reason.length < 5) return failure('Alasan koreksi wajib diisi minimal 5 karakter.');
        const enrollment = ELIPS.getEnrollments().find(function (e) { return Number(e.id) === Number(grade.enrollmentId); });
        if (!enrollment) return failure('Penugasan peserta tidak ditemukan.');
        const classItem = ELIPS.getClassById(enrollment.classId);
        if (classItem && !actorCanManageClass(classItem, actorId)) return failure('Anda tidak berhak mengoreksi nilai ini.');
        const scheme = ELIPS.getGradeSchemeById(grade.schemeId);
        if (!scheme) return failure('Skema penilaian nilai ini tidak ditemukan.');

        let newScore = null;
        let newLetter = null;
        let snapshot = grade.components || [];

        if (Array.isArray(payload.componentScores) && payload.componentScores.length) {
            const scores = ELIPS.getGradeComponentScores();
            const scoresBackup = scores.map(function (record) { return Object.assign({}, record); });
            const components = ELIPS.getGradeComponentsByScheme(scheme.id);
            const now = new Date().toISOString();
            for (let i = 0; i < payload.componentScores.length; i++) {
                const entry = payload.componentScores[i] || {};
                const componentId = Number(entry.componentId);
                if (entry.score === undefined || entry.score === null || entry.score === '') continue;
                const score = Number(entry.score);
                if (!isFinite(score) || score < 0 || score > 100) return failure('Nilai komponen harus antara 0-100.');
                if (!components.some(function (c) { return Number(c.id) === componentId; })) {
                    return failure('Komponen #' + componentId + ' bukan bagian dari skema nilai ini.');
                }
                let record = scores.find(function (s) {
                    return Number(s.componentId) === componentId && Number(s.enrollmentId) === Number(enrollment.id);
                });
                if (record) {
                    record.score = score;
                    record.status = 'graded';
                    record.assessorId = actorId;
                    record.assessedAt = now;
                    record.updatedAt = now;
                } else {
                    scores.push({
                        id: ELIPS.nextId(scores),
                        componentId: componentId,
                        enrollmentId: Number(enrollment.id),
                        studentId: Number(enrollment.studentId),
                        score: score,
                        status: 'graded',
                        assessorId: actorId,
                        assessorName: '',
                        assessedAt: now,
                        note: '',
                        createdAt: now,
                        updatedAt: now
                    });
                }
            }
            ELIPS.setGradeComponentScores(scores);
            const computed = ELIPS.computeEnrollmentGrade(enrollment, scheme);
            if (computed.missing.length) {
                ELIPS.setGradeComponentScores(scoresBackup);
                return failure('Komponen wajib belum lengkap: ' + computed.missing.join(', ') + '.');
            }
            if (computed.finalScore === null) {
                ELIPS.setGradeComponentScores(scoresBackup);
                return failure('Nilai akhir belum dapat dihitung.');
            }
            newScore = computed.finalScore;
            newLetter = computed.letter;
            snapshot = computed.components;
        } else if (payload.finalScore !== undefined && payload.finalScore !== null && payload.finalScore !== '') {
            const manual = Number(payload.finalScore);
            if (!isFinite(manual) || manual < 0 || manual > 100) return failure('Nilai akhir harus antara 0-100.');
            newScore = ELIPS.gradeRound(manual, scheme.rounding || 'half_up');
            newLetter = ELIPS.predicateLetter(newScore);
        } else {
            return failure('Masukkan nilai komponen atau nilai akhir pengganti.');
        }

        const previousScore = grade.finalScore !== undefined && grade.finalScore !== null ? Number(grade.finalScore) : Number(grade.score);
        const previousLetter = grade.grade;
        const now = new Date().toISOString();
        grade.score = Math.max(0, Math.min(100, Math.round(newScore)));
        grade.finalScore = newScore;
        grade.grade = newLetter;
        grade.components = snapshot;
        grade.status = 'finalized';
        grade.finalizedBy = actorId;
        grade.finalizedAt = now;
        grade.computedAt = now;
        grade.updatedAt = now;
        ELIPS.setGrades(grades);

        const corrections = ELIPS.getGradeCorrections();
        const actor = actorId ? ELIPS.getUserById(actorId) : null;
        const correction = {
            id: ELIPS.nextId(corrections),
            gradeId: Number(grade.id),
            enrollmentId: Number(grade.enrollmentId),
            previousScore: previousScore,
            newScore: newScore,
            previousLetter: previousLetter,
            newLetter: newLetter,
            reason: reason,
            actorId: actorId,
            actorName: actor ? actor.name : '',
            createdAt: now
        };
        corrections.push(correction);
        ELIPS.setGradeCorrections(corrections);

        const student = ELIPS.getUserById(grade.studentId);
        const course = ELIPS.getCourseById(grade.courseId);
        recordAudit({
            action: 'correct',
            entity: 'grade',
            entityId: grade.id,
            entityLabel: (student ? student.name : 'Peserta #' + grade.studentId) + ' — ' + (course ? course.title : ''),
            actorId: actorId,
            detail: 'Mengoreksi nilai dari ' + previousScore + ' (' + previousLetter + ') menjadi ' + newScore + ' (' + newLetter + ') — alasan: ' + reason + '.'
        });
        return success('Koreksi nilai disimpan dan dicatat pada jejak audit.', { grade: grade, correction: correction });
    }

    function gradeTranscript(payload) {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('grade.transcript', payload || {});
        payload = payload || {};
        const actorId = resolveActorId(payload);
        const actor = actorId ? ELIPS.getUserById(actorId) : null;
        let enrollment = null;
        if (payload.enrollmentId) {
            enrollment = ELIPS.getEnrollments().find(function (e) { return Number(e.id) === Number(payload.enrollmentId); });
        } else if (payload.studentId && payload.courseId) {
            enrollment = ELIPS.getEnrollment(payload.studentId, payload.courseId);
        }
        if (!enrollment) return failure('Penugasan peserta tidak ditemukan.');
        if (actor && actor.role === 'peserta' && Number(enrollment.studentId) !== Number(actor.id)) {
            return failure('Anda hanya dapat melihat nilai Anda sendiri.');
        }
        if (actor && actor.role !== 'peserta') {
            const classItem = ELIPS.getClassById(enrollment.classId);
            const course = ELIPS.getCourseById(enrollment.courseId);
            let allowed = classItem ? actorCanManageClass(classItem, actorId) : false;
            if (!allowed && course) allowed = Number(course.instructorId) === Number(actor.id) || actor.role === 'admin';
            if (!allowed) return failure('Anda tidak berhak melihat nilai peserta ini.');
        }
        const computed = ELIPS.computeEnrollmentGrade(enrollment);
        const grade = ELIPS.getGrades().find(function (g) { return Number(g.enrollmentId) === Number(enrollment.id); }) || null;
        const corrections = grade ? ELIPS.getGradeCorrectionsByGrade(grade.id).map(function (row) {
            const actor = row.actorId ? ELIPS.getUserById(row.actorId) : null;
            return Object.assign({}, row, { actorName: row.actorName || (actor ? actor.name : '') });
        }) : [];
        return success('', {
            enrollment: enrollment,
            grade: grade,
            computed: computed,
            corrections: corrections
        });
    }

    Object.assign(ELIPS, {
        saveGrade: saveGrade,
        createDraftCertificateForEnrollment: createDraftCertificateForEnrollment,
        approveCertificate: approveCertificate,
        savePredicateRanges: savePredicateRanges,
        saveGradeScheme: saveGradeScheme,
        setGradeSchemeStatus: setGradeSchemeStatus,
        deleteGradeScheme: deleteGradeScheme,
        gradeRoster: gradeRoster,
        saveManualComponentScore: saveManualComponentScore,
        exemptGradeComponent: exemptGradeComponent,
        finalizeGrades: finalizeGrades,
        correctGrade: correctGrade,
        gradeTranscript: gradeTranscript
    });
})(window.ELIPS);
