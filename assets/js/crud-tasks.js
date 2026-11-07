// ============================================================
// ELIPS LMS - crud-tasks.js
// Tugas, pengumpulan, penilaian/revisi, ujian, dan hasil ujian.
// Aturan akses dan validasi di sini mencerminkan backend PHP agar
// mode lokal berperilaku sama dengan mode server.
// ============================================================

(function (ELIPS) {
    'use strict';

    const { success, failure } = ELIPS._;

    const ASSIGNMENT_STATUSES = ['draft', 'published', 'closed', 'archived'];
    const ASSIGNMENT_TYPES = ['file_upload', 'text_submission', 'in_class_practice', 'external_reference'];
    const EXAM_STATUSES = ['draft', 'published', 'closed', 'archived'];
    const EXAM_TYPES = ['written', 'practical', 'oral', 'project', 'other'];
    const SUBMISSION_STATUSES = ['submitted', 'late', 'reviewed', 'revision_requested', 'resubmitted', 'accepted'];
    const PARTICIPANT_STATUSES = ['registered', 'attended', 'absent', 'excused'];
    const RESULT_STATUSES = ['not_assessed', 'excused', 'missing', 'graded'];

    function recordAudit(entry) {
        if (typeof ELIPS.logAudit === 'function') ELIPS.logAudit(entry);
    }

    function sessionActor() {
        if (typeof ELIPS.getCurrentUser === 'function') {
            const user = ELIPS.getCurrentUser();
            if (user) return user;
        }
        if (typeof ELIPS.getSessionUser === 'function') return ELIPS.getSessionUser();
        return null;
    }

    function canManageClassLocal(classItem) {
        const user = sessionActor();
        if (!user || !classItem) return false;
        if (user.role === 'admin') return true;
        return user.role === 'instruktur' && Number(classItem.instructorId) === Number(user.id);
    }

    function parseTime(value) {
        if (!value) return null;
        const time = new Date(value).getTime();
        return isNaN(time) ? null : time;
    }

    function toIsoOrNull(value) {
        const text = String(value === null || value === undefined ? '' : value).trim();
        if (text === '') return null;
        const time = new Date(text).getTime();
        if (isNaN(time)) return null;
        return new Date(time).toISOString();
    }

    function nowIso() {
        return new Date().toISOString();
    }

    function normalizeFiles(value) {
        if (!Array.isArray(value)) return { files: [], error: null };
        const files = [];
        for (let index = 0; index < value.length; index++) {
            const item = value[index];
            if (!item || typeof item !== 'object') continue;
            const fileName = String(item.fileName || item.file_name || '').trim();
            if (fileName === '') continue;
            if (files.length >= 20) return { files: [], error: 'Maksimal 20 file per pengumpulan.' };
            const rawSize = item.fileSize === undefined ? item.file_size : item.fileSize;
            files.push({
                fileName: fileName.slice(0, 255),
                filePath: String(item.filePath || item.file_path || '').trim(),
                fileSize: rawSize === null || rawSize === undefined || rawSize === ''
                    ? null : Math.max(0, Number(rawSize) || 0),
                mimeType: String(item.mimeType || item.mime_type || '').trim().slice(0, 120)
            });
        }
        return { files: files, error: null };
    }

    function normalizeRubrics(value) {
        const rubrics = [];
        if (!Array.isArray(value)) return { rubrics: rubrics, error: null };
        for (let index = 0; index < value.length; index++) {
            const item = value[index];
            if (!item || typeof item !== 'object') continue;
            const criterion = String(item.criterion || '').trim();
            if (criterion === '') return { rubrics: [], error: 'Kriteria rubrik wajib diisi.' };
            const maxScore = Number(item.maxScore === undefined ? item.max_score : item.maxScore) || 0;
            if (maxScore <= 0) return { rubrics: [], error: 'Nilai maksimum rubrik harus lebih dari 0.' };
            rubrics.push({
                criterion: criterion.slice(0, 200),
                description: String(item.description || '').trim(),
                maxScore: maxScore,
                order: Math.max(1, Number(item.order === undefined ? item.sort_order : item.order) || (index + 1))
            });
        }
        return { rubrics: rubrics, error: null };
    }

    function fileExtension(fileName) {
        const text = String(fileName || '');
        const dot = text.lastIndexOf('.');
        return dot === -1 ? '' : text.slice(dot + 1).toLowerCase();
    }

    function allowedFileError(fileName, assignment) {
        const allowed = String(assignment.allowedTypes || '')
            .split(',')
            .map(function (part) { return part.trim().toLowerCase(); })
            .filter(function (part) { return part !== ''; });
        if (allowed.length && allowed.indexOf(fileExtension(fileName)) === -1) {
            return 'Tipe file tidak diizinkan. Gunakan: ' + allowed.join(', ') + '.';
        }
        return null;
    }

    function classFor(classId) {
        return ELIPS.getClassById(classId);
    }

    function courseFor(classItem) {
        return classItem ? ELIPS.getCourseById(classItem.courseId) : null;
    }

    function moduleFor(id) {
        if (id === null || id === undefined || id === '') return null;
        return ELIPS.getModules().find(function (m) { return Number(m.id) === Number(id); }) || null;
    }

    function lessonFor(id) {
        if (id === null || id === undefined || id === '') return null;
        return ELIPS.getLessons().find(function (l) { return Number(l.id) === Number(id); }) || null;
    }

    function enrollmentFor(classId, studentId) {
        return ELIPS.getEnrollmentsByStudent(studentId).find(function (e) {
            return Number(e.classId) === Number(classId) &&
                (e.status === 'active' || e.status === 'completed');
        }) || null;
    }

    function enrollmentById(classId, enrollmentId) {
        return ELIPS.getEnrollments().find(function (e) {
            return Number(e.id) === Number(enrollmentId) && Number(e.classId) === Number(classId);
        }) || null;
    }

    function classEnrollments(classId) {
        return ELIPS.getEnrollments().filter(function (e) {
            return Number(e.classId) === Number(classId) &&
                (e.status === 'active' || e.status === 'completed');
        });
    }

    function moduleTitleOf(id) {
        const module = moduleFor(id);
        return module ? module.title : '';
    }

    function lessonTitleOf(id) {
        const lesson = lessonFor(id);
        return lesson ? lesson.title : '';
    }

    function assignmentPublicLocal(item, includeInstructions) {
        const result = {
            id: Number(item.id),
            classId: Number(item.classId),
            classCode: item.classCode || '',
            className: item.className || '',
            courseId: item.courseId === null || item.courseId === undefined ? null : Number(item.courseId),
            courseTitle: item.courseTitle || '',
            curriculumVersionId: Number(item.curriculumVersionId) || 0,
            moduleId: item.moduleId === null || item.moduleId === undefined ? null : Number(item.moduleId),
            moduleTitle: item.moduleTitle || moduleTitleOf(item.moduleId),
            lessonId: item.lessonId === null || item.lessonId === undefined ? null : Number(item.lessonId),
            lessonTitle: item.lessonTitle || lessonTitleOf(item.lessonId),
            title: item.title,
            type: item.type,
            openAt: item.openAt || null,
            dueAt: item.dueAt || null,
            maxScore: Number(item.maxScore) || 0,
            weight: Number(item.weight) || 0,
            requireFile: !!item.requireFile,
            allowedTypes: item.allowedTypes || '',
            maxFileSize: item.maxFileSize === null || item.maxFileSize === undefined ? null : Number(item.maxFileSize),
            status: item.status,
            createdBy: item.createdBy === null || item.createdBy === undefined ? null : Number(item.createdBy),
            creatorName: item.creatorName || '',
            updatedBy: item.updatedBy === null || item.updatedBy === undefined ? null : Number(item.updatedBy),
            createdAt: item.createdAt || null,
            updatedAt: item.updatedAt || null
        };
        if (includeInstructions) result.instructions = item.instructions || '';
        return result;
    }

    function submissionPublicLocal(item, includeContent) {
        const result = {
            id: Number(item.id),
            assignmentId: Number(item.assignmentId),
            assignmentTitle: item.assignmentTitle || '',
            classId: item.classId === null || item.classId === undefined ? null : Number(item.classId),
            enrollmentId: Number(item.enrollmentId),
            studentId: Number(item.studentId),
            studentName: item.studentName || '',
            assignmentType: item.assignmentType || '',
            maxScore: item.maxScore === null || item.maxScore === undefined ? null : Number(item.maxScore),
            status: item.status,
            submittedAt: item.submittedAt || null,
            isLate: !!item.isLate,
            score: item.score === null || item.score === undefined ? null : Number(item.score),
            reviewedBy: item.reviewedBy === null || item.reviewedBy === undefined ? null : Number(item.reviewedBy),
            reviewerName: item.reviewerName || '',
            reviewedAt: item.reviewedAt || null,
            revisionCount: Number(item.revisionCount) || 0,
            createdAt: item.createdAt || null,
            updatedAt: item.updatedAt || null
        };
        if (includeContent) {
            result.textAnswer = item.textAnswer || '';
            result.feedback = item.feedback || '';
        }
        return result;
    }

    function filePublicLocal(item, ownerKey, includePath) {
        const result = {
            id: Number(item.id),
            [ownerKey]: Number(item[ownerKey]),
            fileName: item.fileName || '',
            fileSize: item.fileSize === null || item.fileSize === undefined ? null : Number(item.fileSize),
            mimeType: item.mimeType || '',
            createdAt: item.createdAt || null
        };
        if (includePath) result.filePath = item.filePath || '';
        return result;
    }

    function filesForAssignment(assignmentId, includePath) {
        return ELIPS.getAssignmentFilesByAssignment(assignmentId).map(function (item) {
            return filePublicLocal(item, 'assignmentId', includePath);
        });
    }

    function rubricsForAssignment(assignmentId) {
        return ELIPS.getAssignmentRubricsByAssignment(assignmentId).map(function (item) {
            return {
                id: Number(item.id),
                assignmentId: Number(item.assignmentId),
                criterion: item.criterion,
                description: item.description,
                maxScore: Number(item.maxScore) || 0,
                order: Number(item.order) || 1,
                createdAt: item.createdAt || null,
                updatedAt: item.updatedAt || null
            };
        });
    }

    function filesForSubmission(submissionId, includePath) {
        return ELIPS.getSubmissionFilesBySubmission(submissionId).map(function (item) {
            return filePublicLocal(item, 'submissionId', includePath);
        });
    }

    function reviewsForSubmission(submissionId) {
        return ELIPS.getSubmissionReviewsBySubmission(submissionId).map(function (item) {
            return {
                id: Number(item.id),
                submissionId: Number(item.submissionId),
                reviewerId: item.reviewerId === null || item.reviewerId === undefined ? null : Number(item.reviewerId),
                reviewerName: item.reviewerName || '',
                action: item.action,
                score: item.score === null || item.score === undefined ? null : Number(item.score),
                note: item.note || '',
                createdAt: item.createdAt || null
            };
        });
    }

    function examPublicLocal(item, includeInstructions) {
        const result = {
            id: Number(item.id),
            classId: Number(item.classId),
            classCode: item.classCode || '',
            className: item.className || '',
            courseId: item.courseId === null || item.courseId === undefined ? null : Number(item.courseId),
            courseTitle: item.courseTitle || '',
            curriculumVersionId: Number(item.curriculumVersionId) || 0,
            title: item.title,
            description: item.description || '',
            type: item.type,
            examDate: item.examDate || null,
            startTime: item.startTime || null,
            durationMinutes: item.durationMinutes === null || item.durationMinutes === undefined ? null : Number(item.durationMinutes),
            maxScore: Number(item.maxScore) || 0,
            weight: Number(item.weight) || 0,
            status: item.status,
            createdBy: item.createdBy === null || item.createdBy === undefined ? null : Number(item.createdBy),
            creatorName: item.creatorName || '',
            updatedBy: item.updatedBy === null || item.updatedBy === undefined ? null : Number(item.updatedBy),
            createdAt: item.createdAt || null,
            updatedAt: item.updatedAt || null
        };
        if (includeInstructions) result.instructions = item.instructions || '';
        return result;
    }

    function participantPublicLocal(item) {
        return {
            id: Number(item.id),
            examId: Number(item.examId),
            enrollmentId: Number(item.enrollmentId),
            studentId: Number(item.studentId),
            studentName: item.studentName || '',
            status: item.status,
            note: item.note || '',
            createdAt: item.createdAt || null,
            updatedAt: item.updatedAt || null
        };
    }

    function resultPublicLocal(item) {
        return {
            id: Number(item.id),
            examId: Number(item.examId),
            enrollmentId: Number(item.enrollmentId),
            studentId: Number(item.studentId),
            studentName: item.studentName || '',
            score: item.score === null || item.score === undefined ? null : Number(item.score),
            status: item.status,
            gradedBy: item.gradedBy === null || item.gradedBy === undefined ? null : Number(item.gradedBy),
            gradedByName: item.gradedByName || '',
            gradedAt: item.gradedAt || null,
            note: item.note || '',
            createdAt: item.createdAt || null,
            updatedAt: item.updatedAt || null
        };
    }

    function assignmentStats(assignmentId) {
        const rows = ELIPS.getSubmissionsByAssignment(assignmentId);
        const scores = rows.filter(function (item) { return item.score !== null && item.score !== undefined; });
        const average = scores.length
            ? scores.reduce(function (sum, item) { return sum + Number(item.score || 0); }, 0) / scores.length
            : null;
        return {
            submissionCount: rows.length,
            pendingCount: rows.filter(function (item) {
                return ['submitted', 'late', 'resubmitted'].indexOf(item.status) !== -1;
            }).length,
            reviewedCount: rows.filter(function (item) {
                return ['reviewed', 'accepted'].indexOf(item.status) !== -1;
            }).length,
            lateCount: rows.filter(function (item) { return !!item.isLate; }).length,
            averageScore: average === null ? null : Math.round(average * 100) / 100
        };
    }

    function examStats(examId) {
        const participants = ELIPS.getExamParticipantsByExam(examId);
        const results = ELIPS.getExamResultsByExam(examId);
        const scores = results.filter(function (item) { return item.score !== null && item.score !== undefined; });
        const average = scores.length
            ? scores.reduce(function (sum, item) { return sum + Number(item.score || 0); }, 0) / scores.length
            : null;
        return {
            participantCount: participants.length,
            attendedCount: participants.filter(function (p) { return p.status === 'attended'; }).length,
            absentCount: participants.filter(function (p) { return p.status === 'absent'; }).length,
            excusedCount: participants.filter(function (p) { return p.status === 'excused'; }).length,
            resultCount: results.length,
            gradedCount: results.filter(function (r) { return r.status === 'graded'; }).length,
            averageScore: average === null ? null : Math.round(average * 100) / 100
        };
    }

    function assignmentContext(id, actor, staffOnly) {
        const assignment = ELIPS.getAssignmentById(id);
        if (!assignment) return { error: 'Tugas tidak ditemukan.' };
        const classItem = classFor(assignment.classId);
        if (!classItem) return { error: 'Kelas tidak ditemukan.' };

        if (canManageClassLocal(classItem)) {
            return { assignment: assignment, class: classItem, staff: true, enrollment: null };
        }
        if (staffOnly) return { error: 'Anda tidak berhak mengelola tugas pada kelas ini.' };
        if (!actor || actor.role !== 'peserta') {
            return { error: 'Anda tidak berhak membuka tugas kelas ini.' };
        }
        const course = courseFor(classItem);
        if (!course || course.status !== 'published') {
            return { error: 'Kursus belum dipublikasikan.' };
        }
        const enrollment = enrollmentFor(classItem.id, actor.id);
        if (!enrollment) return { error: 'Anda tidak terdaftar pada kelas ini.' };
        if (['published', 'closed'].indexOf(assignment.status) === -1) {
            return { error: 'Tugas belum dipublikasikan.' };
        }
        return { assignment: assignment, class: classItem, staff: false, enrollment: enrollment };
    }

    function examContext(id, actor, staffOnly) {
        const exam = ELIPS.getExamById(id);
        if (!exam) return { error: 'Ujian tidak ditemukan.' };
        const classItem = classFor(exam.classId);
        if (!classItem) return { error: 'Kelas tidak ditemukan.' };

        if (canManageClassLocal(classItem)) {
            return { exam: exam, class: classItem, staff: true, enrollment: null };
        }
        if (staffOnly) return { error: 'Anda tidak berhak mengelola ujian pada kelas ini.' };
        if (!actor || actor.role !== 'peserta') {
            return { error: 'Anda tidak berhak membuka ujian kelas ini.' };
        }
        const course = courseFor(classItem);
        if (!course || course.status !== 'published') {
            return { error: 'Kursus belum dipublikasikan.' };
        }
        const enrollment = enrollmentFor(classItem.id, actor.id);
        if (!enrollment) return { error: 'Anda tidak terdaftar pada kelas ini.' };
        if (['published', 'closed'].indexOf(exam.status) === -1) {
            return { error: 'Ujian belum dipublikasikan.' };
        }
        return { exam: exam, class: classItem, staff: false, enrollment: enrollment };
    }

    function classContext(params, actor) {
        params = params || {};
        const classId = Number(params.classId);
        const classItem = classFor(classId);
        if (!classItem) return { error: 'Kelas tidak ditemukan.' };

        let preview = canManageClassLocal(classItem);
        let enrollment = null;
        if (preview) {
            const enrollmentId = Number(params.enrollmentId || 0);
            if (enrollmentId > 0) {
                enrollment = enrollmentById(classId, enrollmentId);
                if (!enrollment) return { error: 'Peserta tidak terdaftar pada kelas ini.' };
            }
        } else {
            if (!actor || actor.role !== 'peserta') {
                return { error: 'Anda tidak berhak membuka ruang tugas kelas ini.' };
            }
            const course = courseFor(classItem);
            if (!course || course.status !== 'published') {
                return { error: 'Kursus belum dipublikasikan.' };
            }
            enrollment = enrollmentFor(classId, actor.id);
            if (!enrollment) return { error: 'Anda tidak terdaftar pada kelas ini.' };
        }
        return { class: classItem, enrollment: enrollment, preview: preview };
    }

    function classroomBase(classItem, enrollment, preview) {
        const course = courseFor(classItem);
        return {
            class: {
                id: Number(classItem.id),
                code: classItem.code,
                name: classItem.name,
                courseId: Number(classItem.courseId),
                courseTitle: course ? course.title : '',
                curriculumVersionId: Number(classItem.curriculumVersionId) || 0,
                instructorId: classItem.instructorId === null || classItem.instructorId === undefined
                    ? null : Number(classItem.instructorId),
                status: classItem.status,
                periodStart: classItem.periodStart || null,
                periodEnd: classItem.periodEnd || null
            },
            enrollment: enrollment ? {
                id: Number(enrollment.id),
                studentId: Number(enrollment.studentId),
                courseId: Number(enrollment.courseId),
                classId: Number(enrollment.classId),
                status: enrollment.status
            } : null,
            preview: preview
        };
    }

    function assignmentClassroomPayloadLocal(classItem, enrollment, preview) {
        const now = Date.now();
        const enrollmentId = enrollment ? Number(enrollment.id) : 0;
        const assignments = ELIPS.getAssignmentsByClass(classItem.id)
            .filter(function (item) {
                return preview || ['published', 'closed'].indexOf(item.status) !== -1;
            })
            .map(function (row) {
                const item = assignmentPublicLocal(row, true);
                item.stats = assignmentStats(row.id);
                item.pastDue = !!row.dueAt && (parseTime(row.dueAt) || 0) < now;
                item.canSubmit = !preview
                    && row.status === 'published'
                    && (!row.openAt || (parseTime(row.openAt) || 0) <= now);
                item.submission = null;
                if (enrollmentId > 0) {
                    const submission = ELIPS.getSubmissionByEnrollment(row.id, enrollmentId);
                    if (submission) item.submission = submissionPublicLocal(submission, false);
                }
                return item;
            });

        const exams = ELIPS.getExamsByClass(classItem.id)
            .filter(function (item) {
                return preview || ['published', 'closed'].indexOf(item.status) !== -1;
            })
            .map(function (row) {
                const item = examPublicLocal(row, true);
                item.stats = examStats(row.id);
                item.pastExam = !!row.examDate && String(row.examDate).slice(0, 10) < new Date().toISOString().slice(0, 10);
                item.result = null;
                if (enrollmentId > 0) {
                    const result = ELIPS.getExamResultByEnrollment(row.id, enrollmentId);
                    if (result) item.result = resultPublicLocal(result);
                }
                return item;
            });

        const payload = classroomBase(classItem, enrollment, preview);
        payload.assignments = assignments;
        payload.exams = exams;
        payload.stats = {
            assignmentTotal: assignments.length,
            assignmentSubmitted: assignments.filter(function (item) {
                return !!item.submission && ['submitted', 'late', 'reviewed', 'accepted', 'resubmitted'].indexOf(item.submission.status) !== -1;
            }).length,
            assignmentGraded: assignments.filter(function (item) {
                return !!item.submission && ['reviewed', 'accepted'].indexOf(item.submission.status) !== -1;
            }).length,
            examTotal: exams.length,
            examGraded: exams.filter(function (item) {
                return !!item.result && item.result.status === 'graded';
            }).length
        };
        return payload;
    }

    function assignmentDetailPayloadLocal(assignment, enrollmentId) {
        const item = assignmentPublicLocal(assignment, true);
        item.files = filesForAssignment(assignment.id, true);
        item.rubrics = rubricsForAssignment(assignment.id);
        item.stats = assignmentStats(assignment.id);
        item.submission = null;
        if (enrollmentId) {
            const submission = ELIPS.getSubmissionByEnrollment(assignment.id, enrollmentId);
            if (submission) {
                const publicSubmission = submissionPublicLocal(submission, true);
                publicSubmission.files = filesForSubmission(submission.id, true);
                publicSubmission.reviews = reviewsForSubmission(submission.id);
                item.submission = publicSubmission;
            }
        }
        return item;
    }

    function ensureExamRosterLocal(examId, classId) {
        const participants = ELIPS.getExamParticipants();
        const results = ELIPS.getExamResults();
        let participantsChanged = false;
        let resultsChanged = false;
        classEnrollments(classId).forEach(function (enrollment) {
            const hasParticipant = participants.some(function (p) {
                return Number(p.examId) === Number(examId) && Number(p.enrollmentId) === Number(enrollment.id);
            });
            if (!hasParticipant) {
                const user = ELIPS.getUserById(enrollment.studentId);
                participants.push({
                    id: ELIPS.nextId(participants),
                    examId: Number(examId),
                    enrollmentId: Number(enrollment.id),
                    studentId: Number(enrollment.studentId),
                    studentName: user ? user.name : '',
                    status: 'registered',
                    note: '',
                    createdAt: nowIso(),
                    updatedAt: nowIso()
                });
                participantsChanged = true;
            }
            const hasResult = results.some(function (r) {
                return Number(r.examId) === Number(examId) && Number(r.enrollmentId) === Number(enrollment.id);
            });
            if (!hasResult) {
                const user = ELIPS.getUserById(enrollment.studentId);
                results.push({
                    id: ELIPS.nextId(results),
                    examId: Number(examId),
                    enrollmentId: Number(enrollment.id),
                    studentId: Number(enrollment.studentId),
                    studentName: user ? user.name : '',
                    score: null,
                    status: 'not_assessed',
                    gradedBy: null,
                    gradedByName: '',
                    gradedAt: null,
                    note: '',
                    createdAt: nowIso(),
                    updatedAt: nowIso()
                });
                resultsChanged = true;
            }
        });
        if (participantsChanged) ELIPS.setExamParticipants(participants);
        if (resultsChanged) ELIPS.setExamResults(results);
    }

    function examDetailPayloadLocal(exam, staff, enrollmentId) {
        if (staff) ensureExamRosterLocal(exam.id, exam.classId);
        const item = examPublicLocal(exam, true);
        item.stats = examStats(exam.id);
        item.participants = staff
            ? ELIPS.getExamParticipantsByExam(exam.id).map(participantPublicLocal)
            : [];
        item.results = staff
            ? ELIPS.getExamResultsByExam(exam.id).map(resultPublicLocal)
            : [];
        item.result = null;
        if (enrollmentId) {
            const result = ELIPS.getExamResultByEnrollment(exam.id, enrollmentId);
            if (result) item.result = resultPublicLocal(result);
        }
        return item;
    }

    function validateCurriculumRefs(classItem, moduleId, lessonId) {
        const courseId = Number(classItem.courseId);
        const versionId = Number(classItem.curriculumVersionId);
        const result = { moduleId: moduleId || null, lessonId: lessonId || null };

        if (lessonId) {
            const lesson = lessonFor(lessonId);
            if (!lesson
                || Number(lesson.courseId) !== courseId
                || Number(lesson.curriculumVersionId) !== versionId) {
                return { error: 'Materi kurikulum tidak valid untuk kelas ini.' };
            }
            if (moduleId && Number(lesson.moduleId) !== Number(moduleId)) {
                return { error: 'Materi tidak berada pada modul yang dipilih.' };
            }
            result.moduleId = Number(lesson.moduleId);
            result.lessonId = Number(lesson.id);
            return result;
        }
        if (moduleId) {
            const module = moduleFor(moduleId);
            if (!module
                || Number(module.courseId) !== courseId
                || Number(module.curriculumVersionId) !== versionId) {
                return { error: 'Modul kurikulum tidak valid untuk kelas ini.' };
            }
        }
        return result;
    }

    function saveAssignmentFiles(assignmentId, files) {
        const list = ELIPS.getAssignmentFiles().filter(function (item) {
            return Number(item.assignmentId) !== Number(assignmentId);
        });
        files.forEach(function (file) {
            list.push({
                id: ELIPS.nextId(list),
                assignmentId: Number(assignmentId),
                fileName: file.fileName,
                filePath: file.filePath,
                fileSize: file.fileSize,
                mimeType: file.mimeType,
                createdAt: nowIso()
            });
        });
        ELIPS.setAssignmentFiles(list);
    }

    function saveRubrics(assignmentId, rubrics) {
        const list = ELIPS.getAssignmentRubrics().filter(function (item) {
            return Number(item.assignmentId) !== Number(assignmentId);
        });
        rubrics.forEach(function (rubric) {
            list.push({
                id: ELIPS.nextId(list),
                assignmentId: Number(assignmentId),
                criterion: rubric.criterion,
                description: rubric.description,
                maxScore: rubric.maxScore,
                order: rubric.order,
                createdAt: nowIso(),
                updatedAt: null
            });
        });
        ELIPS.setAssignmentRubrics(list);
    }

    function saveSubmissionFiles(submissionId, files) {
        const list = ELIPS.getSubmissionFiles().filter(function (item) {
            return Number(item.submissionId) !== Number(submissionId);
        });
        files.forEach(function (file) {
            list.push({
                id: ELIPS.nextId(list),
                submissionId: Number(submissionId),
                fileName: file.fileName,
                filePath: file.filePath,
                fileSize: file.fileSize,
                mimeType: file.mimeType,
                createdAt: nowIso()
            });
        });
        ELIPS.setSubmissionFiles(list);
    }

    function addSubmissionReview(submissionId, action, score, note) {
        const actor = sessionActor();
        const reviews = ELIPS.getSubmissionReviews();
        reviews.push({
            id: ELIPS.nextId(reviews),
            submissionId: Number(submissionId),
            reviewerId: actor ? Number(actor.id) : null,
            reviewerName: actor ? actor.name : 'Sistem',
            action: action,
            score: score === null || score === undefined ? null : Number(score),
            note: note || '',
            createdAt: nowIso()
        });
        ELIPS.setSubmissionReviews(reviews);
    }

    function assignmentInput(data, current) {
        current = current || {};
        data = data || {};
        const title = String(data.title === undefined ? (current.title || '') : data.title).trim();
        if (title === '') return { error: 'Judul tugas wajib diisi.' };

        const type = String(data.type === undefined ? (current.type || 'text_submission') : data.type);
        if (ASSIGNMENT_TYPES.indexOf(type) === -1) return { error: 'Jenis tugas tidak valid.' };

        const status = String(data.status === undefined ? (current.status || 'draft') : data.status);
        if (ASSIGNMENT_STATUSES.indexOf(status) === -1) return { error: 'Status tugas tidak valid.' };

        let openAt = current.openAt || null;
        if (Object.prototype.hasOwnProperty.call(data, 'openAt')) {
            openAt = data.openAt ? toIsoOrNull(data.openAt) : null;
            if (data.openAt && openAt === null) return { error: 'Tanggal dibuka tidak valid.' };
        }
        let dueAt = current.dueAt || null;
        if (Object.prototype.hasOwnProperty.call(data, 'dueAt')) {
            dueAt = data.dueAt ? toIsoOrNull(data.dueAt) : null;
            if (data.dueAt && dueAt === null) return { error: 'Tenggat tugas tidak valid.' };
        }
        if (openAt && dueAt && (parseTime(dueAt) || 0) < (parseTime(openAt) || 0)) {
            return { error: 'Tenggat harus setelah tanggal dibuka.' };
        }

        const maxScore = Number(data.maxScore === undefined ? (current.maxScore === undefined ? 100 : current.maxScore) : data.maxScore);
        if (!(maxScore > 0)) return { error: 'Nilai maksimum tugas harus lebih dari 0.' };
        const weight = Number(data.weight === undefined ? (current.weight || 0) : data.weight);
        if (weight < 0) return { error: 'Bobot tugas tidak valid.' };

        return {
            title: title,
            type: type,
            status: status,
            openAt: openAt,
            dueAt: dueAt,
            maxScore: maxScore,
            weight: weight,
            requireFile: data.requireFile === undefined ? !!current.requireFile : !!data.requireFile,
            allowedTypes: String(data.allowedTypes === undefined ? (current.allowedTypes || '') : data.allowedTypes).trim(),
            maxFileSize: data.maxFileSize === undefined
                ? (current.maxFileSize === undefined ? null : current.maxFileSize)
                : (data.maxFileSize === null || data.maxFileSize === '' ? null : Math.max(0, Number(data.maxFileSize) || 0)),
            instructions: String(data.instructions === undefined ? (current.instructions || '') : data.instructions).trim(),
            moduleId: Number(data.moduleId === undefined ? (current.moduleId || 0) : data.moduleId) || 0,
            lessonId: Number(data.lessonId === undefined ? (current.lessonId || 0) : data.lessonId) || 0
        };
    }

    // ---------- Aksi tugas ----------

    function getAssignmentClassroom(params) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('assignment.classroom', params || {});
        }
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = classContext(params, actor);
        if (context.error) return failure(context.error);
        return success(
            'Ruang tugas dan ujian dimuat.',
            assignmentClassroomPayloadLocal(context.class, context.enrollment, context.preview)
        );
    }

    function getAssignmentDetail(params) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('assignment.detail', params || {});
        }
        params = params || {};
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = assignmentContext(Number(params.id), actor);
        if (context.error) return failure(context.error);

        let enrollmentId = 0;
        if (context.staff) {
            enrollmentId = Number(params.enrollmentId || 0);
            if (enrollmentId > 0 && !enrollmentById(context.class.id, enrollmentId)) {
                return failure('Peserta tidak terdaftar pada kelas ini.');
            }
            if (enrollmentId <= 0 && params.studentId) {
                const enrollment = enrollmentFor(context.class.id, Number(params.studentId));
                enrollmentId = enrollment ? Number(enrollment.id) : 0;
            }
        } else {
            enrollmentId = Number(context.enrollment.id);
        }
        return success(
            'Detail tugas dimuat.',
            assignmentDetailPayloadLocal(context.assignment, enrollmentId)
        );
    }

    function createAssignment(data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('assignment.create', {
                classId: data && data.classId,
                data: data || {}
            });
        }
        data = data || {};
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const classItem = classFor(data.classId);
        if (!classItem) return failure('Kelas tidak ditemukan.');
        if (!canManageClassLocal(classItem)) {
            return failure('Anda tidak berhak menambahkan tugas pada kelas ini.');
        }

        const input = assignmentInput(data, null);
        if (input.error) return failure(input.error);
        const refs = validateCurriculumRefs(classItem, input.moduleId, input.lessonId);
        if (refs.error) return failure(refs.error);

        const filesResult = normalizeFiles(data.files || []);
        if (filesResult.error) return failure(filesResult.error);
        const rubricsResult = normalizeRubrics(data.rubrics || []);
        if (rubricsResult.error) return failure(rubricsResult.error);

        const list = ELIPS.getAssignments();
        const course = courseFor(classItem);
        const item = {
            id: ELIPS.nextId(list),
            classId: Number(classItem.id),
            classCode: classItem.code,
            className: classItem.name,
            courseId: Number(classItem.courseId),
            courseTitle: course ? course.title : '',
            curriculumVersionId: Number(classItem.curriculumVersionId),
            moduleId: refs.moduleId,
            moduleTitle: moduleTitleOf(refs.moduleId),
            lessonId: refs.lessonId,
            lessonTitle: lessonTitleOf(refs.lessonId),
            title: input.title,
            instructions: input.instructions,
            type: input.type,
            openAt: input.openAt,
            dueAt: input.dueAt,
            maxScore: input.maxScore,
            weight: input.weight,
            requireFile: input.requireFile,
            allowedTypes: input.allowedTypes,
            maxFileSize: input.maxFileSize,
            status: input.status,
            createdBy: actor.id,
            creatorName: actor.name,
            updatedBy: actor.id,
            createdAt: nowIso(),
            updatedAt: nowIso()
        };
        list.push(item);
        ELIPS.setAssignments(list);
        saveAssignmentFiles(item.id, filesResult.files);
        if (Object.prototype.hasOwnProperty.call(data, 'rubrics')) saveRubrics(item.id, rubricsResult.rubrics);

        recordAudit({
            action: 'create',
            entity: 'assignment',
            entityId: item.id,
            entityLabel: item.title,
            detail: 'Menambahkan tugas "' + item.title + '" pada kelas "' + classItem.code + '".'
        });

        return success('Tugas berhasil ditambahkan.', assignmentDetailPayloadLocal(item, 0));
    }

    function updateAssignment(id, data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('assignment.update', { id: id, data: data || {} });
        }
        data = data || {};
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = assignmentContext(Number(id), actor, true);
        if (context.error) return failure(context.error);
        if (!canManageClassLocal(context.class)) return failure('Anda tidak berhak mengelola tugas pada kelas ini.');

        const input = assignmentInput(data, context.assignment);
        if (input.error) return failure(input.error);
        const refs = validateCurriculumRefs(context.class, input.moduleId, input.lessonId);
        if (refs.error) return failure(refs.error);

        const filesResult = normalizeFiles(data.files || []);
        if (filesResult.error) return failure(filesResult.error);
        const rubricsResult = normalizeRubrics(data.rubrics || []);
        if (rubricsResult.error) return failure(rubricsResult.error);

        let list = ELIPS.getAssignments();
        let updated = null;
        list = list.map(function (item) {
            if (Number(item.id) !== Number(id)) return item;
            updated = Object.assign({}, item, {
                moduleId: refs.moduleId,
                moduleTitle: moduleTitleOf(refs.moduleId),
                lessonId: refs.lessonId,
                lessonTitle: lessonTitleOf(refs.lessonId),
                title: input.title,
                instructions: input.instructions,
                type: input.type,
                openAt: input.openAt,
                dueAt: input.dueAt,
                maxScore: input.maxScore,
                weight: input.weight,
                requireFile: input.requireFile,
                allowedTypes: input.allowedTypes,
                maxFileSize: input.maxFileSize,
                status: input.status,
                updatedBy: actor.id,
                updatedAt: nowIso()
            });
            return updated;
        });
        ELIPS.setAssignments(list);
        if (Object.prototype.hasOwnProperty.call(data, 'files')) saveAssignmentFiles(id, filesResult.files);
        if (Object.prototype.hasOwnProperty.call(data, 'rubrics')) saveRubrics(id, rubricsResult.rubrics);

        recordAudit({
            action: 'update',
            entity: 'assignment',
            entityId: Number(id),
            entityLabel: input.title,
            detail: 'Memperbarui tugas "' + input.title + '" pada kelas "' + context.class.code + '".'
        });

        return success('Tugas berhasil diperbarui.', assignmentDetailPayloadLocal(updated, 0));
    }

    function updateAssignmentStatus(id, status, reason) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('assignment.status', {
                id: id,
                data: { status: status, reason: reason || '' }
            });
        }
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = assignmentContext(Number(id), actor, true);
        if (context.error) return failure(context.error);
        if (!canManageClassLocal(context.class)) return failure('Anda tidak berhak mengelola tugas pada kelas ini.');
        if (ASSIGNMENT_STATUSES.indexOf(status) === -1) return failure('Status tugas tidak valid.');

        let updated = null;
        const list = ELIPS.getAssignments().map(function (item) {
            if (Number(item.id) !== Number(id)) return item;
            updated = Object.assign({}, item, {
                status: status,
                updatedBy: actor.id,
                updatedAt: nowIso()
            });
            return updated;
        });
        ELIPS.setAssignments(list);

        recordAudit({
            action: 'status',
            entity: 'assignment',
            entityId: Number(id),
            entityLabel: context.assignment.title,
            detail: 'Mengubah status tugas "' + context.assignment.title + '" pada kelas "'
                + context.class.code + '" dari ' + context.assignment.status + ' menjadi ' + status
                + (reason ? '; alasan: ' + reason : '') + '.'
        });

        return success('Status tugas diperbarui.', assignmentDetailPayloadLocal(updated, 0));
    }

    function deleteAssignment(id) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('assignment.delete', { id: id });
        }
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = assignmentContext(Number(id), actor, true);
        if (context.error) return failure(context.error);
        if (!canManageClassLocal(context.class)) return failure('Anda tidak berhak mengelola tugas pada kelas ini.');

        const submissions = ELIPS.getSubmissionsByAssignment(id);
        const submissionIds = submissions.map(function (item) { return Number(item.id); });
        ELIPS.setAssignments(ELIPS.getAssignments().filter(function (item) { return Number(item.id) !== Number(id); }));
        ELIPS.setAssignmentFiles(ELIPS.getAssignmentFiles().filter(function (item) {
            return Number(item.assignmentId) !== Number(id);
        }));
        ELIPS.setAssignmentRubrics(ELIPS.getAssignmentRubrics().filter(function (item) {
            return Number(item.assignmentId) !== Number(id);
        }));
        ELIPS.setSubmissions(ELIPS.getSubmissions().filter(function (item) {
            return Number(item.assignmentId) !== Number(id);
        }));
        ELIPS.setSubmissionFiles(ELIPS.getSubmissionFiles().filter(function (item) {
            return submissionIds.indexOf(Number(item.submissionId)) === -1;
        }));
        ELIPS.setSubmissionReviews(ELIPS.getSubmissionReviews().filter(function (item) {
            return submissionIds.indexOf(Number(item.submissionId)) === -1;
        }));

        recordAudit({
            action: 'delete',
            entity: 'assignment',
            entityId: Number(id),
            entityLabel: context.assignment.title,
            detail: 'Menghapus tugas "' + context.assignment.title + '" dari kelas "'
                + context.class.code + '" beserta ' + submissions.length + ' pengumpulan.'
        });

        return success('Tugas dihapus.', { id: Number(id), removedSubmissions: submissions.length });
    }

    function getAssignmentSubmissions(id) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('assignment.submissions', { id: id });
        }
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = assignmentContext(Number(id), actor, true);
        if (context.error) return failure(context.error);
        if (!canManageClassLocal(context.class)) return failure('Anda tidak berhak mengelola tugas pada kelas ini.');

        const students = classEnrollments(context.class.id).map(function (enrollment) {
            const submission = ELIPS.getSubmissionByEnrollment(id, enrollment.id);
            const user = ELIPS.getUserById(enrollment.studentId);
            return {
                enrollmentId: Number(enrollment.id),
                studentId: Number(enrollment.studentId),
                studentName: user ? user.name : '',
                enrollmentStatus: enrollment.status,
                submission: submission ? submissionPublicLocal(submission, false) : null
            };
        }).sort(function (a, b) {
            return String(a.studentName).localeCompare(String(b.studentName));
        });

        return success('Daftar pengumpulan tugas dimuat.', {
            assignment: assignmentPublicLocal(context.assignment, true),
            students: students,
            stats: assignmentStats(id)
        });
    }

    function submitAssignment(assignmentId, data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('assignment.submit', {
                assignmentId: assignmentId,
                data: data || {}
            });
        }
        data = data || {};
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = assignmentContext(Number(assignmentId), actor);
        if (context.error) return failure(context.error);
        if (context.staff) return failure('Pengumpulan tugas dilakukan oleh peserta.');

        const assignment = context.assignment;
        const now = Date.now();
        if (assignment.status !== 'published') {
            return failure('Tugas ini sudah ditutup untuk pengumpulan.');
        }
        if (assignment.openAt && (parseTime(assignment.openAt) || 0) > now) {
            return failure('Tugas belum dibuka.');
        }
        if (assignment.type === 'in_class_practice') {
            return failure('Tugas praktik kelas dicatat langsung oleh instruktur.');
        }

        const textAnswer = String(data.textAnswer || '').trim();
        const filesResult = normalizeFiles(data.files || []);
        if (filesResult.error) return failure(filesResult.error);
        const files = filesResult.files;

        const existing = ELIPS.getSubmissionByEnrollment(assignment.id, context.enrollment.id);
        if (existing && ['reviewed', 'accepted'].indexOf(existing.status) !== -1) {
            return failure('Pengumpulan ini sudah dinilai. Tunggu instruktur membuka revisi.');
        }

        const existingFiles = existing ? ELIPS.getSubmissionFilesBySubmission(existing.id) : [];
        const willHaveFiles = Object.prototype.hasOwnProperty.call(data, 'files')
            ? files.length > 0 : existingFiles.length > 0;
        if (textAnswer === '' && !willHaveFiles) {
            return failure('Jawaban teks atau file tugas wajib diisi.');
        }
        if (assignment.type === 'text_submission' && textAnswer === '') {
            return failure('Jawaban teks tugas wajib diisi.');
        }
        if (assignment.type === 'external_reference' && textAnswer === '' && !willHaveFiles) {
            return failure('Tautan atau bukti penyelesaian tugas wajib diisi.');
        }
        if (assignment.requireFile && !willHaveFiles) {
            return failure('File tugas wajib diunggah.');
        }
        for (let index = 0; index < files.length; index++) {
            const fileError = allowedFileError(files[index].fileName, assignment);
            if (fileError) return failure(fileError);
            if (assignment.maxFileSize !== null && assignment.maxFileSize !== undefined
                && files[index].fileSize !== null && files[index].fileSize > Number(assignment.maxFileSize)) {
                return failure('Ukuran file melebihi batas yang diizinkan.');
            }
        }

        const isLate = !!assignment.dueAt && (parseTime(assignment.dueAt) || 0) < now;
        const revisionRequested = !!existing && existing.status === 'revision_requested';
        const status = revisionRequested ? 'resubmitted' : (isLate ? 'late' : 'submitted');
        let revisionCount = existing ? Number(existing.revisionCount) || 0 : 0;
        if (revisionRequested) revisionCount += 1;

        const submissions = ELIPS.getSubmissions();
        let submissionId;
        if (existing) {
            submissionId = Number(existing.id);
            const updated = submissions.map(function (item) {
                if (Number(item.id) !== submissionId) return item;
                return Object.assign({}, item, {
                    status: status,
                    textAnswer: textAnswer,
                    submittedAt: nowIso(),
                    isLate: isLate,
                    revisionCount: revisionCount,
                    updatedAt: nowIso()
                });
            });
            ELIPS.setSubmissions(updated);
        } else {
            submissionId = ELIPS.nextId(submissions);
            const student = ELIPS.getUserById(context.enrollment.studentId);
            submissions.push({
                id: submissionId,
                assignmentId: Number(assignment.id),
                assignmentTitle: assignment.title,
                classId: Number(assignment.classId),
                enrollmentId: Number(context.enrollment.id),
                studentId: Number(context.enrollment.studentId),
                studentName: student ? student.name : '',
                assignmentType: assignment.type,
                maxScore: Number(assignment.maxScore),
                status: status,
                submittedAt: nowIso(),
                isLate: isLate,
                score: null,
                feedback: '',
                reviewedBy: null,
                reviewerName: '',
                reviewedAt: null,
                revisionCount: revisionCount,
                textAnswer: textAnswer,
                createdAt: nowIso(),
                updatedAt: nowIso()
            });
            ELIPS.setSubmissions(submissions);
        }

        if (Object.prototype.hasOwnProperty.call(data, 'files')) {
            saveSubmissionFiles(submissionId, files);
        }
        addSubmissionReview(
            submissionId,
            'submit',
            null,
            revisionRequested ? 'Mengumpulkan ulang tugas setelah revisi.' : 'Mengumpulkan tugas.'
        );

        recordAudit({
            action: revisionRequested ? 'update' : 'submit',
            entity: 'submission',
            entityId: submissionId,
            entityLabel: assignment.title,
            detail: 'Peserta #' + context.enrollment.studentId + ' mengumpulkan tugas "' + assignment.title
                + '" pada kelas "' + context.class.code + '"' + (isLate ? ' (terlambat)' : '') + '.'
        });

        const row = ELIPS.getSubmissionById(submissionId);
        const result = submissionPublicLocal(row, true);
        result.files = filesForSubmission(submissionId, true);
        result.reviews = reviewsForSubmission(submissionId);
        return success(isLate ? 'Tugas dikumpulkan (terlambat).' : 'Tugas berhasil dikumpulkan.', result);
    }

    function reviewSubmission(submissionId, data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('assignment.review', {
                submissionId: submissionId,
                data: data || {}
            });
        }
        data = data || {};
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const submission = ELIPS.getSubmissionById(submissionId);
        if (!submission) return failure('Pengumpulan tugas tidak ditemukan.');

        const context = assignmentContext(Number(submission.assignmentId), actor, true);
        if (context.error) return failure(context.error);
        if (!canManageClassLocal(context.class)) return failure('Anda tidak berhak mengelola tugas pada kelas ini.');

        const action = String(data.action || 'score');
        if (['score', 'revision', 'accept'].indexOf(action) === -1) {
            return failure('Aksi penilaian tidak valid.');
        }
        const maxScore = Number(submission.maxScore) || Number(context.assignment.maxScore) || 0;
        let score = null;
        if (Object.prototype.hasOwnProperty.call(data, 'score')
            && data.score !== '' && data.score !== null && data.score !== undefined) {
            score = Number(data.score);
            if (isNaN(score) || score < 0 || score > maxScore) {
                return failure('Nilai harus antara 0 dan ' + maxScore + '.');
            }
        }
        if (action === 'score' && score === null) {
            return failure('Nilai wajib diisi untuk penilaian.');
        }
        const note = String(data.note === undefined ? (data.feedback || '') : data.note).trim();
        if (action === 'revision' && note === '') {
            return failure('Catatan revisi wajib diisi.');
        }
        const status = action === 'revision' ? 'revision_requested' : (action === 'accept' ? 'accepted' : 'reviewed');
        const feedback = Object.prototype.hasOwnProperty.call(data, 'feedback')
            ? String(data.feedback || '').trim()
            : (note !== '' ? note : (submission.feedback || ''));

        const list = ELIPS.getSubmissions().map(function (item) {
            if (Number(item.id) !== Number(submissionId)) return item;
            return Object.assign({}, item, {
                status: status,
                score: score,
                feedback: feedback,
                reviewedBy: actor.id,
                reviewerName: actor.name,
                reviewedAt: nowIso(),
                updatedAt: nowIso()
            });
        });
        ELIPS.setSubmissions(list);
        addSubmissionReview(submissionId, action, score, note);

        recordAudit({
            action: action === 'revision' ? 'revision' : (action === 'accept' ? 'approve' : 'grade'),
            entity: 'submission',
            entityId: Number(submissionId),
            entityLabel: context.assignment.title,
            detail: 'Memberi ' + (action === 'revision' ? 'permintaan revisi' : (action === 'accept' ? 'persetujuan' : 'nilai'))
                + ' untuk pengumpulan tugas "' + context.assignment.title + '" peserta #' + submission.studentId
                + (score !== null ? ' dengan nilai ' + score : '') + '.'
        });

        const row = ELIPS.getSubmissionById(submissionId);
        const result = submissionPublicLocal(row, true);
        result.files = filesForSubmission(submissionId, true);
        result.reviews = reviewsForSubmission(submissionId);
        return success('Penilaian tugas disimpan.', result);
    }

    // ---------- Aksi ujian ----------

    function getExamClassroom(params) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('exam.classroom', params || {});
        }
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = classContext(params, actor);
        if (context.error) return failure(context.error);
        const payload = assignmentClassroomPayloadLocal(context.class, context.enrollment, context.preview);
        return success('Ruang ujian dimuat.', {
            class: payload.class,
            enrollment: payload.enrollment,
            preview: payload.preview,
            exams: payload.exams,
            stats: {
                examTotal: payload.stats.examTotal,
                examGraded: payload.stats.examGraded
            }
        });
    }

    function getExamDetail(params) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('exam.detail', params || {});
        }
        params = params || {};
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = examContext(Number(params.id), actor);
        if (context.error) return failure(context.error);

        let enrollmentId = 0;
        if (context.staff) {
            enrollmentId = Number(params.enrollmentId || 0);
            if (enrollmentId > 0 && !enrollmentById(context.class.id, enrollmentId)) {
                return failure('Peserta tidak terdaftar pada kelas ini.');
            }
            if (enrollmentId <= 0 && params.studentId) {
                const enrollment = enrollmentFor(context.class.id, Number(params.studentId));
                enrollmentId = enrollment ? Number(enrollment.id) : 0;
            }
        } else {
            enrollmentId = Number(context.enrollment.id);
        }
        return success(
            'Detail ujian dimuat.',
            examDetailPayloadLocal(context.exam, context.staff, enrollmentId)
        );
    }

    function examInput(data, current) {
        current = current || {};
        data = data || {};
        const title = String(data.title === undefined ? (current.title || '') : data.title).trim();
        if (title === '') return { error: 'Judul ujian wajib diisi.' };
        const type = String(data.type === undefined ? (current.type || 'written') : data.type);
        if (EXAM_TYPES.indexOf(type) === -1) return { error: 'Jenis ujian tidak valid.' };
        const status = String(data.status === undefined ? (current.status || 'draft') : data.status);
        if (EXAM_STATUSES.indexOf(status) === -1) return { error: 'Status ujian tidak valid.' };

        let examDate = current.examDate || null;
        if (Object.prototype.hasOwnProperty.call(data, 'examDate')) {
            const text = String(data.examDate || '').trim();
            examDate = text === '' ? null : text.slice(0, 10);
            if (text !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(examDate)) {
                return { error: 'Tanggal ujian tidak valid.' };
            }
        }
        let startTime = current.startTime || null;
        if (Object.prototype.hasOwnProperty.call(data, 'startTime')) {
            const text = String(data.startTime || '').trim();
            if (text === '') {
                startTime = null;
            } else if (/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/.test(text)) {
                startTime = text.slice(0, 5);
            } else {
                return { error: 'Waktu mulai ujian tidak valid.' };
            }
        }
        const duration = data.durationMinutes === undefined
            ? (current.durationMinutes === undefined ? null : current.durationMinutes)
            : (data.durationMinutes === null || data.durationMinutes === '' ? null : Math.max(0, Number(data.durationMinutes) || 0));
        const maxScore = Number(data.maxScore === undefined ? (current.maxScore === undefined ? 100 : current.maxScore) : data.maxScore);
        if (!(maxScore > 0)) return { error: 'Nilai maksimum ujian harus lebih dari 0.' };
        const weight = Number(data.weight === undefined ? (current.weight || 0) : data.weight);
        if (weight < 0) return { error: 'Bobot ujian tidak valid.' };

        return {
            title: title,
            type: type,
            status: status,
            examDate: examDate,
            startTime: startTime,
            durationMinutes: duration,
            maxScore: maxScore,
            weight: weight,
            description: String(data.description === undefined ? (current.description || '') : data.description).trim(),
            instructions: String(data.instructions === undefined ? (current.instructions || '') : data.instructions).trim()
        };
    }

    function createExam(data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('exam.create', {
                classId: data && data.classId,
                data: data || {}
            });
        }
        data = data || {};
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const classItem = classFor(data.classId);
        if (!classItem) return failure('Kelas tidak ditemukan.');
        if (!canManageClassLocal(classItem)) {
            return failure('Anda tidak berhak menambahkan ujian pada kelas ini.');
        }
        const input = examInput(data, null);
        if (input.error) return failure(input.error);

        const list = ELIPS.getExams();
        const course = courseFor(classItem);
        const item = {
            id: ELIPS.nextId(list),
            classId: Number(classItem.id),
            classCode: classItem.code,
            className: classItem.name,
            courseId: Number(classItem.courseId),
            courseTitle: course ? course.title : '',
            curriculumVersionId: Number(classItem.curriculumVersionId),
            title: input.title,
            description: input.description,
            type: input.type,
            examDate: input.examDate,
            startTime: input.startTime,
            durationMinutes: input.durationMinutes,
            maxScore: input.maxScore,
            weight: input.weight,
            instructions: input.instructions,
            status: input.status,
            createdBy: actor.id,
            creatorName: actor.name,
            updatedBy: actor.id,
            createdAt: nowIso(),
            updatedAt: nowIso()
        };
        list.push(item);
        ELIPS.setExams(list);
        ensureExamRosterLocal(item.id, item.classId);

        recordAudit({
            action: 'create',
            entity: 'exam',
            entityId: item.id,
            entityLabel: item.title,
            detail: 'Menambahkan ujian "' + item.title + '" pada kelas "' + classItem.code + '".'
        });

        return success('Ujian berhasil ditambahkan.', examDetailPayloadLocal(item, true, 0));
    }

    function updateExam(id, data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('exam.update', { id: id, data: data || {} });
        }
        data = data || {};
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = examContext(Number(id), actor, true);
        if (context.error) return failure(context.error);
        if (!canManageClassLocal(context.class)) return failure('Anda tidak berhak mengelola ujian pada kelas ini.');

        const input = examInput(data, context.exam);
        if (input.error) return failure(input.error);

        let updated = null;
        const list = ELIPS.getExams().map(function (item) {
            if (Number(item.id) !== Number(id)) return item;
            updated = Object.assign({}, item, {
                title: input.title,
                description: input.description,
                type: input.type,
                examDate: input.examDate,
                startTime: input.startTime,
                durationMinutes: input.durationMinutes,
                maxScore: input.maxScore,
                weight: input.weight,
                instructions: input.instructions,
                status: input.status,
                updatedBy: actor.id,
                updatedAt: nowIso()
            });
            return updated;
        });
        ELIPS.setExams(list);
        ensureExamRosterLocal(id, context.class.id);

        recordAudit({
            action: 'update',
            entity: 'exam',
            entityId: Number(id),
            entityLabel: input.title,
            detail: 'Memperbarui ujian "' + input.title + '" pada kelas "' + context.class.code + '".'
        });

        return success('Ujian berhasil diperbarui.', examDetailPayloadLocal(updated, true, 0));
    }

    function updateExamStatus(id, status, reason) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('exam.status', {
                id: id,
                data: { status: status, reason: reason || '' }
            });
        }
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = examContext(Number(id), actor, true);
        if (context.error) return failure(context.error);
        if (!canManageClassLocal(context.class)) return failure('Anda tidak berhak mengelola ujian pada kelas ini.');
        if (EXAM_STATUSES.indexOf(status) === -1) return failure('Status ujian tidak valid.');

        let updated = null;
        const list = ELIPS.getExams().map(function (item) {
            if (Number(item.id) !== Number(id)) return item;
            updated = Object.assign({}, item, {
                status: status,
                updatedBy: actor.id,
                updatedAt: nowIso()
            });
            return updated;
        });
        ELIPS.setExams(list);

        recordAudit({
            action: 'status',
            entity: 'exam',
            entityId: Number(id),
            entityLabel: context.exam.title,
            detail: 'Mengubah status ujian "' + context.exam.title + '" pada kelas "'
                + context.class.code + '" dari ' + context.exam.status + ' menjadi ' + status
                + (reason ? '; alasan: ' + reason : '') + '.'
        });

        return success('Status ujian diperbarui.', examDetailPayloadLocal(updated, true, 0));
    }

    function deleteExam(id) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('exam.delete', { id: id });
        }
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = examContext(Number(id), actor, true);
        if (context.error) return failure(context.error);
        if (!canManageClassLocal(context.class)) return failure('Anda tidak berhak mengelola ujian pada kelas ini.');

        const gradedCount = ELIPS.getExamResultsByExam(id).filter(function (item) {
            return item.status === 'graded';
        }).length;
        ELIPS.setExams(ELIPS.getExams().filter(function (item) { return Number(item.id) !== Number(id); }));
        ELIPS.setExamParticipants(ELIPS.getExamParticipants().filter(function (item) {
            return Number(item.examId) !== Number(id);
        }));
        ELIPS.setExamResults(ELIPS.getExamResults().filter(function (item) {
            return Number(item.examId) !== Number(id);
        }));

        recordAudit({
            action: 'delete',
            entity: 'exam',
            entityId: Number(id),
            entityLabel: context.exam.title,
            detail: 'Menghapus ujian "' + context.exam.title + '" dari kelas "' + context.class.code
                + '" beserta ' + gradedCount + ' nilai peserta.'
        });

        return success('Ujian dihapus.', { id: Number(id), removedResults: gradedCount });
    }

    function saveExamResult(examId, params) {
        if (ELIPS.api && ELIPS.api.enabled) {
            const payload = Object.assign({ examId: examId }, params || {});
            return ELIPS.api.call('exam.result', payload);
        }
        params = params || {};
        const actor = sessionActor();
        if (!actor) return failure('Silakan login terlebih dahulu.');
        const context = examContext(Number(examId), actor, true);
        if (context.error) return failure(context.error);
        if (!canManageClassLocal(context.class)) return failure('Anda tidak berhak mengelola ujian pada kelas ini.');

        ensureExamRosterLocal(context.exam.id, context.class.id);

        let enrollment = null;
        const enrollmentId = Number(params.enrollmentId || 0);
        if (enrollmentId > 0) {
            enrollment = enrollmentById(context.class.id, enrollmentId);
        } else if (params.studentId) {
            enrollment = enrollmentFor(context.class.id, Number(params.studentId));
        }
        if (!enrollment) return failure('Peserta tidak terdaftar pada kelas ini.');

        const data = params.data && typeof params.data === 'object' ? params.data : params;
        const status = String(data.status === undefined ? 'graded' : data.status);
        if (RESULT_STATUSES.indexOf(status) === -1) return failure('Status hasil ujian tidak valid.');

        const maxScore = Number(context.exam.maxScore) || 0;
        let score = null;
        if (Object.prototype.hasOwnProperty.call(data, 'score')
            && data.score !== null && data.score !== '' && data.score !== undefined) {
            score = Number(data.score);
            if (isNaN(score) || score < 0 || score > maxScore) {
                return failure('Nilai ujian harus antara 0 dan ' + maxScore + '.');
            }
        }
        if (status === 'graded' && score === null) {
            return failure('Nilai wajib diisi untuk hasil yang dinilai.');
        }
        const note = String(data.note || '').trim();

        let participantStatus = String(data.participantStatus || '');
        if (participantStatus !== '' && PARTICIPANT_STATUSES.indexOf(participantStatus) === -1) {
            return failure('Status keikutsertaan ujian tidak valid.');
        }
        if (participantStatus === '') {
            if (status === 'graded') participantStatus = 'attended';
            else if (status === 'missing') participantStatus = 'absent';
            else if (status === 'excused') participantStatus = 'excused';
            else participantStatus = 'registered';
        }

        const results = ELIPS.getExamResults();
        let resultId;
        const existing = results.find(function (item) {
            return Number(item.examId) === Number(examId) && Number(item.enrollmentId) === Number(enrollment.id);
        });
        if (existing) {
            resultId = Number(existing.id);
            const updated = results.map(function (item) {
                if (Number(item.id) !== resultId) return item;
                return Object.assign({}, item, {
                    score: score,
                    status: status,
                    gradedBy: status === 'not_assessed' ? null : actor.id,
                    gradedByName: status === 'not_assessed' ? '' : actor.name,
                    gradedAt: status === 'not_assessed' ? null : nowIso(),
                    note: note,
                    updatedAt: nowIso()
                });
            });
            ELIPS.setExamResults(updated);
        } else {
            const student = ELIPS.getUserById(enrollment.studentId);
            resultId = ELIPS.nextId(results);
            results.push({
                id: resultId,
                examId: Number(examId),
                enrollmentId: Number(enrollment.id),
                studentId: Number(enrollment.studentId),
                studentName: student ? student.name : '',
                score: score,
                status: status,
                gradedBy: status === 'not_assessed' ? null : actor.id,
                gradedByName: status === 'not_assessed' ? '' : actor.name,
                gradedAt: status === 'not_assessed' ? null : nowIso(),
                note: note,
                createdAt: nowIso(),
                updatedAt: nowIso()
            });
            ELIPS.setExamResults(results);
        }

        const participants = ELIPS.getExamParticipants();
        const participant = participants.find(function (item) {
            return Number(item.examId) === Number(examId) && Number(item.enrollmentId) === Number(enrollment.id);
        });
        if (participant) {
            ELIPS.setExamParticipants(participants.map(function (item) {
                if (Number(item.id) !== Number(participant.id)) return item;
                return Object.assign({}, item, {
                    status: participantStatus,
                    note: note,
                    updatedAt: nowIso()
                });
            }));
        } else {
            const student = ELIPS.getUserById(enrollment.studentId);
            participants.push({
                id: ELIPS.nextId(participants),
                examId: Number(examId),
                enrollmentId: Number(enrollment.id),
                studentId: Number(enrollment.studentId),
                studentName: student ? student.name : '',
                status: participantStatus,
                note: note,
                createdAt: nowIso(),
                updatedAt: nowIso()
            });
            ELIPS.setExamParticipants(participants);
        }

        recordAudit({
            action: 'grade',
            entity: 'exam_result',
            entityId: resultId,
            entityLabel: context.exam.title,
            detail: 'Menyimpan hasil ujian "' + context.exam.title + '" untuk peserta #' + enrollment.studentId
                + ' dengan status ' + status + (score !== null ? ' dan nilai ' + score : '') + '.'
        });

        const row = ELIPS.getExamResultsByExam(examId).find(function (item) {
            return Number(item.id) === resultId;
        });
        return success('Hasil ujian disimpan.', row ? resultPublicLocal(row) : null);
    }

    Object.assign(ELIPS, {
        getAssignmentClassroom: getAssignmentClassroom,
        getAssignmentDetail: getAssignmentDetail,
        getAssignmentSubmissions: getAssignmentSubmissions,
        createAssignment: createAssignment,
        updateAssignment: updateAssignment,
        updateAssignmentStatus: updateAssignmentStatus,
        deleteAssignment: deleteAssignment,
        submitAssignment: submitAssignment,
        reviewSubmission: reviewSubmission,
        getExamClassroom: getExamClassroom,
        getExamDetail: getExamDetail,
        createExam: createExam,
        updateExam: updateExam,
        updateExamStatus: updateExamStatus,
        deleteExam: deleteExam,
        saveExamResult: saveExamResult,
        getAssignmentStats: assignmentStats,
        getExamStats: examStats,
        ensureExamRosterLocal: ensureExamRosterLocal
    });
})(window.ELIPS);
