// ============================================================
// ELIPS LMS - data.js
// Getter/setter seluruh koleksi data pada localStorage.
// Tidak berisi aturan bisnis; hanya akses data mentah.
// ============================================================

(function (ELIPS) {
    'use strict';

    const KEYS = ELIPS.KEYS;
    const read = ELIPS.read;
    const write = ELIPS.write;

    // ---------- Users ----------
    function getUsers() { return read(KEYS.users, []); }
    function setUsers(list) { return write(KEYS.users, list); }
    function getUserById(id) { return getUsers().find(function (u) { return u.id === Number(id); }) || null; }
    function getUsersByRole(role) { return getUsers().filter(function (u) { return u.role === role; }); }

    // ---------- Courses ----------
    function getCourses() { return read(KEYS.courses, []); }
    function setCourses(list) { return write(KEYS.courses, list); }
    function getCourseById(id) { return getCourses().find(function (c) { return c.id === Number(id); }) || null; }

    // ---------- Branches / cabang ----------
    function getBranches() { return read(KEYS.branches, []); }
    function setBranches(list) { return write(KEYS.branches, list); }
    function getBranchById(id) {
        return getBranches().find(function (b) { return b.id === Number(id); }) || null;
    }
    function getActiveBranches() {
        return getBranches().filter(function (b) { return b.status === 'active'; });
    }
    function getBranchByCode(code) {
        const target = String(code || '').trim().toUpperCase();
        return getBranches().find(function (b) {
            return String(b.code || '').toUpperCase() === target;
        }) || null;
    }

    // ---------- Categories ----------
    function getCategories() { return read(KEYS.categories, []); }
    function setCategories(list) { return write(KEYS.categories, list); }

    // ---------- Curriculum versions ----------
    function getCurriculumVersions() { return read(KEYS.curriculumVersions, []); }
    function setCurriculumVersions(list) { return write(KEYS.curriculumVersions, list); }
    function getCurriculumVersionById(id) {
        return getCurriculumVersions().find(function (v) { return v.id === Number(id); }) || null;
    }
    function getCurriculumVersionsByCourse(courseId) {
        return getCurriculumVersions()
            .filter(function (v) { return v.courseId === Number(courseId); })
            .sort(function (a, b) { return Number(a.id) - Number(b.id); });
    }
    function getCurrentCurriculumVersion(courseId) {
        const versions = getCurriculumVersionsByCourse(courseId)
            .filter(function (v) { return v.status !== 'retired'; });
        const list = versions.length ? versions : getCurriculumVersionsByCourse(courseId);
        return list.length ? list[list.length - 1] : null;
    }

    // ---------- Classes / cohorts ----------
    function getClasses() { return read(KEYS.classes, []); }
    function setClasses(list) { return write(KEYS.classes, list); }
    function getClassById(id) { return getClasses().find(function (c) { return c.id === Number(id); }) || null; }
    function getClassesByCourse(courseId) {
        return getClasses().filter(function (c) { return c.courseId === Number(courseId); });
    }
    function getClassesByInstructor(instructorId) {
        return getClasses().filter(function (c) { return c.instructorId === Number(instructorId); });
    }
    function getClassInstructorHistories() { return read(KEYS.classInstructorHistories, []); }
    function setClassInstructorHistories(list) { return write(KEYS.classInstructorHistories, list); }
    function getClassInstructorHistoriesByClass(classId) {
        return getClassInstructorHistories().filter(function (h) { return h.classId === Number(classId); });
    }
    function getClassEnrollments(classId) {
        return getEnrollments().filter(function (e) { return e.classId === Number(classId); });
    }

    // ---------- Enrollments ----------
    function getEnrollments() { return read(KEYS.enrollments, []); }
    function setEnrollments(list) { return write(KEYS.enrollments, list); }
    function getEnrollmentsByStudent(studentId) {
        return getEnrollments().filter(function (e) { return e.studentId === Number(studentId); });
    }
    function getEnrollmentsByCourse(courseId) {
        return getEnrollments().filter(function (e) { return e.courseId === Number(courseId); });
    }
    function getEnrollment(studentId, courseId) {
        return getEnrollments().find(function (e) {
            return e.studentId === Number(studentId) && e.courseId === Number(courseId);
        }) || null;
    }
    function isEnrolled(studentId, courseId) { return !!getEnrollment(studentId, courseId); }

    // ---------- Class sessions ----------
    function getClassSessions() { return read(KEYS.classSessions, []); }
    function setClassSessions(list) { return write(KEYS.classSessions, list); }
    function getClassSessionById(id) {
        return getClassSessions().find(function (s) { return s.id === Number(id); }) || null;
    }
    function getSessionsByClass(classId) {
        return getClassSessions()
            .filter(function (s) { return Number(s.classId) === Number(classId); })
            .sort(function (a, b) {
                const dateCompare = String(a.sessionDate || '').localeCompare(String(b.sessionDate || ''));
                if (dateCompare !== 0) return dateCompare;
                return (Number(a.sessionNumber) || 0) - (Number(b.sessionNumber) || 0);
            });
    }

    // ---------- Attendance ----------
    function getAttendances() { return read(KEYS.attendances, []); }
    function setAttendances(list) { return write(KEYS.attendances, list); }
    function getSessionAttendances(sessionId) {
        return getAttendances().filter(function (a) { return Number(a.sessionId) === Number(sessionId); });
    }
    function getAttendance(sessionId, enrollmentId) {
        return getAttendances().find(function (a) {
            return Number(a.sessionId) === Number(sessionId) &&
                Number(a.enrollmentId) === Number(enrollmentId);
        }) || null;
    }
    function getAttendancesByStudent(studentId) {
        return getAttendances().filter(function (a) { return Number(a.studentId) === Number(studentId); });
    }
    function getAttendanceCorrections() { return read(KEYS.attendanceCorrections, []); }
    function setAttendanceCorrections(list) { return write(KEYS.attendanceCorrections, list); }
    function getCorrectionsByAttendance(attendanceId) {
        return getAttendanceCorrections().filter(function (c) {
            return Number(c.attendanceId) === Number(attendanceId);
        });
    }

    // ---------- Absensi staf/admin (kamera + metadata) ----------
    function getStaffAttendances() { return read(KEYS.staffAttendances, []); }
    function setStaffAttendances(list) { return write(KEYS.staffAttendances, list); }
    function getStaffAttendancesByUser(userId) {
        return getStaffAttendances()
            .filter(function (a) { return Number(a.userId) === Number(userId); })
            .sort(function (a, b) {
                return String(b.takenAt || '').localeCompare(String(a.takenAt || ''));
            });
    }

    // ---------- Modules ----------
    function getModules() { return read(KEYS.modules, []); }
    function setModules(list) { return write(KEYS.modules, list); }
    function getModulesByCourse(courseId) {
        return getModules()
            .filter(function (m) { return m.courseId === Number(courseId); })
            .sort(function (a, b) { return (a.order || 0) - (b.order || 0); });
    }

    // ---------- Lessons ----------
    function getLessons() { return read(KEYS.lessons, []); }
    function setLessons(list) { return write(KEYS.lessons, list); }
    function getLessonsByModule(moduleId) {
        return getLessons()
            .filter(function (l) { return l.moduleId === Number(moduleId); })
            .sort(function (a, b) { return (a.order || 0) - (b.order || 0); });
    }
    function getLessonsByCourse(courseId) {
        return getLessons().filter(function (l) { return l.courseId === Number(courseId); });
    }

    // ---------- Grades ----------
    function getGrades() { return read(KEYS.grades, []); }
    function setGrades(list) { return write(KEYS.grades, list); }
    function getGrade(studentId, courseId) {
        return getGrades().find(function (g) {
            return g.studentId === Number(studentId) && g.courseId === Number(courseId);
        }) || null;
    }
    function getGradesByStudent(studentId) {
        return getGrades().filter(function (g) { return g.studentId === Number(studentId); });
    }

    // ---------- Penilaian & finalisasi (Phase 6) ----------
    function defaultPredicateRanges() {
        return [
            { letter: 'A', label: 'Sangat Baik', minScore: 90, maxScore: 100, sortOrder: 1, isActive: true },
            { letter: 'A-', label: 'Sangat Baik', minScore: 80, maxScore: 89.99, sortOrder: 2, isActive: true },
            { letter: 'B+', label: 'Baik', minScore: 75, maxScore: 79.99, sortOrder: 3, isActive: true },
            { letter: 'B', label: 'Baik', minScore: 70, maxScore: 74.99, sortOrder: 4, isActive: true },
            { letter: 'B-', label: 'Baik', minScore: 65, maxScore: 69.99, sortOrder: 5, isActive: true },
            { letter: 'C+', label: 'Cukup', minScore: 60, maxScore: 64.99, sortOrder: 6, isActive: true },
            { letter: 'C', label: 'Cukup', minScore: 55, maxScore: 59.99, sortOrder: 7, isActive: true },
            { letter: 'D', label: 'Kurang', minScore: 50, maxScore: 54.99, sortOrder: 8, isActive: true },
            { letter: 'E', label: 'Tidak Lulus', minScore: 0, maxScore: 49.99, sortOrder: 9, isActive: true }
        ];
    }
    function getPredicateRanges() {
        const list = read(KEYS.predicateRanges, []);
        return Array.isArray(list) && list.length ? list : defaultPredicateRanges();
    }
    function setPredicateRanges(list) { return write(KEYS.predicateRanges, list); }
    function predicateLetter(score) {
        const value = Number(score);
        if (!isFinite(value)) return null;
        const ranges = getPredicateRanges()
            .filter(function (r) { return r.isActive !== false; })
            .slice()
            .sort(function (a, b) { return Number(b.minScore) - Number(a.minScore); });
        for (let i = 0; i < ranges.length; i++) {
            if (value >= Number(ranges[i].minScore) && value <= Number(ranges[i].maxScore)) {
                return ranges[i].letter;
            }
        }
        return 'E';
    }
    function gradeRound(value, mode) {
        const factor = 100;
        const scaled = Number(value) * factor;
        let rounded;
        if (mode === 'floor') rounded = Math.floor(scaled);
        else if (mode === 'ceil') rounded = Math.ceil(scaled);
        else if (mode === 'half_down') rounded = Math.round(scaled - 0.000000001);
        else rounded = Math.round(scaled);
        return rounded / factor;
    }

    function getGradeSchemes() { return read(KEYS.gradeSchemes, []); }
    function setGradeSchemes(list) { return write(KEYS.gradeSchemes, list); }
    function getGradeSchemeById(id) {
        return getGradeSchemes().find(function (s) { return Number(s.id) === Number(id); }) || null;
    }
    function getGradeSchemeByClass(classId) {
        return getGradeSchemes().find(function (s) { return Number(s.classId) === Number(classId); }) || null;
    }
    function getGradeSchemeComponents() { return read(KEYS.gradeSchemeComponents, []); }
    function setGradeSchemeComponents(list) { return write(KEYS.gradeSchemeComponents, list); }
    function getGradeComponentsByScheme(schemeId) {
        return getGradeSchemeComponents()
            .filter(function (c) { return Number(c.schemeId) === Number(schemeId); })
            .sort(function (a, b) {
                const order = (Number(a.order) || 0) - (Number(b.order) || 0);
                return order !== 0 ? order : Number(a.id) - Number(b.id);
            });
    }
    function getGradeComponentScores() { return read(KEYS.gradeComponentScores, []); }
    function setGradeComponentScores(list) { return write(KEYS.gradeComponentScores, list); }
    function getGradeComponentScore(componentId, enrollmentId) {
        return getGradeComponentScores().find(function (s) {
            return Number(s.componentId) === Number(componentId) &&
                Number(s.enrollmentId) === Number(enrollmentId);
        }) || null;
    }
    function getGradeCorrections() { return read(KEYS.gradeCorrections, []); }
    function setGradeCorrections(list) { return write(KEYS.gradeCorrections, list); }
    function getGradeCorrectionsByGrade(gradeId) {
        return getGradeCorrections()
            .filter(function (c) { return Number(c.gradeId) === Number(gradeId); })
            .sort(function (a, b) { return Number(b.id) - Number(a.id); });
    }
    function gradeWeightTotal(components) {
        return (components || []).reduce(function (total, component) {
            return total + (Number(component.weight) || 0);
        }, 0);
    }

    // Cermin dari elips_grade_compute_enrollment() pada server agar mode
    // localStorage menghasilkan nilai yang sama.
    function computeEnrollmentGrade(enrollment, schemeOverride) {
        if (!enrollment) {
            return { schemeId: null, status: 'no_scheme', components: [], missing: [], finalScore: null, letter: null, passed: null };
        }
        const classId = Number(enrollment.classId);
        const enrollmentId = Number(enrollment.id);
        const scheme = schemeOverride || getGradeSchemeByClass(classId);
        if (!scheme) {
            return { schemeId: null, schemeName: '', schemeStatus: null, passScore: null, rounding: 'half_up', components: [], missing: [], finalScore: null, letter: null, status: 'no_scheme', passed: null };
        }
        const components = getGradeComponentsByScheme(scheme.id);
        const resultComponents = [];
        const missing = [];

        components.forEach(function (component) {
            const componentId = Number(component.id);
            const override = getGradeComponentScore(componentId, enrollmentId);
            const isRequired = component.isRequired !== false;
            const result = {
                componentId: componentId,
                name: component.name,
                type: component.type,
                weight: Number(component.weight) || 0,
                aggregation: component.aggregation || 'weighted',
                maxScore: Number(component.maxScore) || 100,
                isRequired: isRequired,
                order: Number(component.order) || 0,
                score: null,
                status: 'not_assessed',
                note: '',
                activities: []
            };

            if (override && override.status === 'excused') {
                result.status = 'excused';
                result.note = override.note || '';
                resultComponents.push(result);
                return;
            }

            if (component.type === 'manual') {
                if (override && override.status === 'graded' && override.score !== null && override.score !== undefined) {
                    result.score = Math.max(0, Math.min(100, Number(override.score)));
                    result.status = 'graded';
                    result.note = override.note || '';
                } else {
                    result.status = 'missing';
                    result.note = override ? override.note || '' : '';
                }
            } else {
                const activities = component.type === 'assignment'
                    ? getAssignmentsByClass(classId).filter(function (a) {
                        return a.status === 'published' || a.status === 'closed';
                    })
                    : getExamsByClass(classId).filter(function (e) {
                        return e.status === 'published' || e.status === 'closed';
                    });
                let weightedSum = 0;
                let weightTotal = 0;
                let activityMissing = false;
                activities.forEach(function (activity) {
                    const maxScore = Number(activity.maxScore) > 0 ? Number(activity.maxScore) : 100;
                    const record = component.type === 'assignment'
                        ? getSubmissionByEnrollment(activity.id, enrollmentId)
                        : getExamResultByEnrollment(activity.id, enrollmentId);
                    const row = {
                        id: Number(activity.id),
                        title: activity.title,
                        maxScore: maxScore,
                        weight: Number(activity.weight) || 0,
                        score: null,
                        normalized: null,
                        status: 'missing'
                    };
                    if (record && record.status === 'excused') {
                        row.status = 'excused';
                    } else if (record && record.score !== null && record.score !== undefined &&
                        (component.type === 'assignment' ? record.status === 'reviewed' : record.status === 'graded')) {
                        const normalized = Math.max(0, Math.min(100, (Number(record.score) / maxScore) * 100));
                        row.score = Number(record.score);
                        row.normalized = normalized;
                        row.status = 'graded';
                        const weight = result.aggregation === 'average' ? 1 : (Number(activity.weight) > 0 ? Number(activity.weight) : 1);
                        weightedSum += normalized * weight;
                        weightTotal += weight;
                    } else {
                        activityMissing = true;
                    }
                    result.activities.push(row);
                });
                if (activities.length === 0) {
                    result.status = 'missing';
                } else if (weightTotal > 0) {
                    result.score = weightedSum / weightTotal;
                    result.status = activityMissing ? 'missing' : 'graded';
                } else {
                    result.status = 'missing';
                }
            }

            if (result.status === 'missing' && isRequired) {
                missing.push(component.name);
            }
            resultComponents.push(result);
        });

        const included = resultComponents.filter(function (component) {
            return component.status === 'graded' && component.score !== null;
        });
        const weightTotal = gradeWeightTotal(included);
        let finalScore = null;
        let letter = null;
        let status = 'incomplete';
        if (missing.length === 0 && weightTotal > 0) {
            const sum = included.reduce(function (total, component) {
                return total + component.score * component.weight;
            }, 0);
            finalScore = gradeRound(sum / weightTotal, scheme.rounding || 'half_up');
            letter = predicateLetter(finalScore);
            status = 'computed';
        }
        return {
            schemeId: Number(scheme.id),
            schemeName: scheme.name,
            schemeStatus: scheme.status,
            passScore: Number(scheme.passScore),
            rounding: scheme.rounding || 'half_up',
            components: resultComponents,
            missing: missing,
            finalScore: finalScore,
            letter: letter,
            status: status,
            passed: finalScore === null ? null : finalScore >= Number(scheme.passScore)
        };
    }

    // ---------- Certificates ----------
    function getCertificates() { return read(KEYS.certificates, []); }
    function setCertificates(list) { return write(KEYS.certificates, list); }
    function normalizeCertificateStatus(cert) {
        const status = String((cert && cert.status) || '').trim().toLowerCase();
        if (status === 'publish' || status === 'published' || status === 'terbit' || status === 'approved') {
            return 'publish';
        }
        if (status === 'draft' || status === 'menunggu' || status === 'pending') {
            return 'draft';
        }
        // Data lama tanpa status dianggap sudah terbit agar tetap tampil.
        return 'publish';
    }
    function isPublishedCertificate(cert) {
        return normalizeCertificateStatus(cert) === 'publish';
    }
    function getCertificatesByStudent(studentId) {
        const id = Number(studentId);
        return getCertificates().filter(function (c) { return Number(c.studentId) === id; });
    }
    function getPublishedCertificatesByStudent(studentId) {
        return getCertificatesByStudent(studentId).filter(isPublishedCertificate);
    }
    function getCertificateByNumber(number) {
        const target = String(number || '').trim().toUpperCase();
        return getCertificates().find(function (c) {
            return String(c.certificateNumber).toUpperCase() === target;
        }) || null;
    }
    function getCertificate(studentId, courseId) {
        const sid = Number(studentId);
        const cid = Number(courseId);
        return getCertificates().find(function (c) {
            return Number(c.studentId) === sid && Number(c.courseId) === cid;
        }) || null;
    }

    // ---------- Lesson progress ----------
    function getCompletedLessons() { return read(KEYS.completedLessons, []); }
    function setCompletedLessons(list) { return write(KEYS.completedLessons, list); }
    function isLessonCompleted(studentId, courseId, lessonId) {
        const legacy = getCompletedLessons().some(function (c) {
            return c.studentId === Number(studentId) &&
                c.courseId === Number(courseId) &&
                c.lessonId === Number(lessonId);
        });
        if (legacy) return true;
        // Phase 4: progres kelas disimpan per penugasan. Data ini menjadi
        // sumber utama ketika peserta belajar melalui kelas.
        return ELIPS.getEnrollmentsByStudent(studentId).some(function (enrollment) {
            if (Number(enrollment.courseId) !== Number(courseId)) return false;
            return ELIPS.getClassLessonProgressByEnrollment(enrollment.id).some(function (row) {
                return Number(row.lessonId) === Number(lessonId);
            });
        });
    }
    function markLessonCompleted(studentId, courseId, lessonId) {
        if (ELIPS.api && ELIPS.api.enabled) {
            ELIPS.api.call('progress.complete', {
                studentId: Number(studentId),
                courseId: Number(courseId),
                lessonId: Number(lessonId)
            });
            return getCompletedLessons();
        }
        const list = getCompletedLessons();
        if (isLessonCompleted(studentId, courseId, lessonId)) return list;
        list.push({
            studentId: Number(studentId),
            courseId: Number(courseId),
            lessonId: Number(lessonId),
            completedAt: new Date().toISOString()
        });
        return setCompletedLessons(list);
    }

    // ---------- Materi kelas (Phase 4) ----------
    function getClassMaterialSettingsRows() { return read(KEYS.classMaterialSettings, []); }
    function setClassMaterialSettingsRows(list) { return write(KEYS.classMaterialSettings, list); }
    function getClassMaterials() { return read(KEYS.classMaterials, []); }
    function setClassMaterials(list) { return write(KEYS.classMaterials, list); }
    function getClassMaterialById(id) {
        return getClassMaterials().find(function (m) { return m.id === Number(id); }) || null;
    }
    function getClassMaterialsByClass(classId) {
        return getClassMaterials()
            .filter(function (m) { return Number(m.classId) === Number(classId); })
            .sort(function (a, b) {
                const order = (Number(a.order) || 0) - (Number(b.order) || 0);
                return order !== 0 ? order : Number(a.id) - Number(b.id);
            });
    }
    function getClassLessonProgress() { return read(KEYS.classLessonProgress, []); }
    function setClassLessonProgress(list) { return write(KEYS.classLessonProgress, list); }
    function getClassLessonProgressByEnrollment(enrollmentId) {
        return getClassLessonProgress().filter(function (p) {
            return Number(p.enrollmentId) === Number(enrollmentId);
        });
    }
    function getClassMaterialProgress() { return read(KEYS.classMaterialProgress, []); }
    function setClassMaterialProgress(list) { return write(KEYS.classMaterialProgress, list); }
    function getClassMaterialProgressByEnrollment(enrollmentId) {
        return getClassMaterialProgress().filter(function (p) {
            return Number(p.enrollmentId) === Number(enrollmentId);
        });
    }

    // ---------- Tugas, pengumpulan, dan ujian (Phase 5) ----------
    function getAssignments() { return read(KEYS.assignments, []); }
    function setAssignments(list) { return write(KEYS.assignments, list); }
    function getAssignmentById(id) {
        return getAssignments().find(function (a) { return a.id === Number(id); }) || null;
    }
    function getAssignmentsByClass(classId) {
        return getAssignments()
            .filter(function (a) { return Number(a.classId) === Number(classId); })
            .sort(function (a, b) { return Number(a.id) - Number(b.id); });
    }
    function getAssignmentsByCourse(courseId) {
        return getAssignments().filter(function (a) { return Number(a.courseId) === Number(courseId); });
    }

    function getAssignmentFiles() { return read(KEYS.assignmentFiles, []); }
    function setAssignmentFiles(list) { return write(KEYS.assignmentFiles, list); }
    function getAssignmentFilesByAssignment(assignmentId) {
        return getAssignmentFiles().filter(function (f) {
            return Number(f.assignmentId) === Number(assignmentId);
        });
    }

    function getAssignmentRubrics() { return read(KEYS.assignmentRubrics, []); }
    function setAssignmentRubrics(list) { return write(KEYS.assignmentRubrics, list); }
    function getAssignmentRubricsByAssignment(assignmentId) {
        return getAssignmentRubrics()
            .filter(function (r) { return Number(r.assignmentId) === Number(assignmentId); })
            .sort(function (a, b) {
                const order = (Number(a.order) || 0) - (Number(b.order) || 0);
                return order !== 0 ? order : Number(a.id) - Number(b.id);
            });
    }

    function getSubmissions() { return read(KEYS.submissions, []); }
    function setSubmissions(list) { return write(KEYS.submissions, list); }
    function getSubmissionById(id) {
        return getSubmissions().find(function (s) { return s.id === Number(id); }) || null;
    }
    function getSubmissionsByAssignment(assignmentId) {
        return getSubmissions().filter(function (s) {
            return Number(s.assignmentId) === Number(assignmentId);
        });
    }
    function getSubmissionsByStudent(studentId) {
        return getSubmissions().filter(function (s) { return Number(s.studentId) === Number(studentId); });
    }
    function getSubmissionByEnrollment(assignmentId, enrollmentId) {
        return getSubmissions().find(function (s) {
            return Number(s.assignmentId) === Number(assignmentId) &&
                Number(s.enrollmentId) === Number(enrollmentId);
        }) || null;
    }

    function getSubmissionFiles() { return read(KEYS.submissionFiles, []); }
    function setSubmissionFiles(list) { return write(KEYS.submissionFiles, list); }
    function getSubmissionFilesBySubmission(submissionId) {
        return getSubmissionFiles().filter(function (f) {
            return Number(f.submissionId) === Number(submissionId);
        });
    }

    function getSubmissionReviews() { return read(KEYS.submissionReviews, []); }
    function setSubmissionReviews(list) { return write(KEYS.submissionReviews, list); }
    function getSubmissionReviewsBySubmission(submissionId) {
        return getSubmissionReviews()
            .filter(function (r) { return Number(r.submissionId) === Number(submissionId); })
            .sort(function (a, b) { return Number(a.id) - Number(b.id); });
    }

    function getExams() { return read(KEYS.exams, []); }
    function setExams(list) { return write(KEYS.exams, list); }
    function getExamById(id) {
        return getExams().find(function (e) { return e.id === Number(id); }) || null;
    }
    function getExamsByClass(classId) {
        return getExams()
            .filter(function (e) { return Number(e.classId) === Number(classId); })
            .sort(function (a, b) {
                const dateCompare = String(a.examDate || '').localeCompare(String(b.examDate || ''));
                return dateCompare !== 0 ? dateCompare : Number(a.id) - Number(b.id);
            });
    }

    function getExamParticipants() { return read(KEYS.examParticipants, []); }
    function setExamParticipants(list) { return write(KEYS.examParticipants, list); }
    function getExamParticipantsByExam(examId) {
        return getExamParticipants().filter(function (p) {
            return Number(p.examId) === Number(examId);
        });
    }
    function getExamParticipant(examId, enrollmentId) {
        return getExamParticipants().find(function (p) {
            return Number(p.examId) === Number(examId) &&
                Number(p.enrollmentId) === Number(enrollmentId);
        }) || null;
    }

    function getExamResults() { return read(KEYS.examResults, []); }
    function setExamResults(list) { return write(KEYS.examResults, list); }
    function getExamResultsByExam(examId) {
        return getExamResults().filter(function (r) { return Number(r.examId) === Number(examId); });
    }
    function getExamResultsByStudent(studentId) {
        return getExamResults().filter(function (r) { return Number(r.studentId) === Number(studentId); });
    }
    function getExamResultByEnrollment(examId, enrollmentId) {
        return getExamResults().find(function (r) {
            return Number(r.examId) === Number(examId) &&
                Number(r.enrollmentId) === Number(enrollmentId);
        }) || null;
    }

    Object.assign(ELIPS, {
        getUsers: getUsers,
        setUsers: setUsers,
        getUserById: getUserById,
        getUsersByRole: getUsersByRole,
        getCourses: getCourses,
        setCourses: setCourses,
        getCourseById: getCourseById,
        getBranches: getBranches,
        setBranches: setBranches,
        getBranchById: getBranchById,
        getActiveBranches: getActiveBranches,
        getBranchByCode: getBranchByCode,
        getCategories: getCategories,
        setCategories: setCategories,
        getCurriculumVersions: getCurriculumVersions,
        setCurriculumVersions: setCurriculumVersions,
        getCurriculumVersionById: getCurriculumVersionById,
        getCurriculumVersionsByCourse: getCurriculumVersionsByCourse,
        getCurrentCurriculumVersion: getCurrentCurriculumVersion,
        getClasses: getClasses,
        setClasses: setClasses,
        getClassById: getClassById,
        getClassesByCourse: getClassesByCourse,
        getClassesByInstructor: getClassesByInstructor,
        getClassInstructorHistories: getClassInstructorHistories,
        setClassInstructorHistories: setClassInstructorHistories,
        getClassInstructorHistoriesByClass: getClassInstructorHistoriesByClass,
        getClassEnrollments: getClassEnrollments,
        getEnrollments: getEnrollments,
        setEnrollments: setEnrollments,
        getEnrollmentsByStudent: getEnrollmentsByStudent,
        getEnrollmentsByCourse: getEnrollmentsByCourse,
        getEnrollment: getEnrollment,
        isEnrolled: isEnrolled,
        getClassSessions: getClassSessions,
        setClassSessions: setClassSessions,
        getClassSessionById: getClassSessionById,
        getSessionsByClass: getSessionsByClass,
        getAttendances: getAttendances,
        setAttendances: setAttendances,
        getSessionAttendances: getSessionAttendances,
        getAttendance: getAttendance,
        getAttendancesByStudent: getAttendancesByStudent,
        getAttendanceCorrections: getAttendanceCorrections,
        setAttendanceCorrections: setAttendanceCorrections,
        getCorrectionsByAttendance: getCorrectionsByAttendance,
        getStaffAttendances: getStaffAttendances,
        setStaffAttendances: setStaffAttendances,
        getStaffAttendancesByUser: getStaffAttendancesByUser,
        getModules: getModules,
        setModules: setModules,
        getModulesByCourse: getModulesByCourse,
        getLessons: getLessons,
        setLessons: setLessons,
        getLessonsByModule: getLessonsByModule,
        getLessonsByCourse: getLessonsByCourse,
        getGrades: getGrades,
        setGrades: setGrades,
        getGrade: getGrade,
        getGradesByStudent: getGradesByStudent,
        defaultPredicateRanges: defaultPredicateRanges,
        getPredicateRanges: getPredicateRanges,
        setPredicateRanges: setPredicateRanges,
        predicateLetter: predicateLetter,
        gradeRound: gradeRound,
        getGradeSchemes: getGradeSchemes,
        setGradeSchemes: setGradeSchemes,
        getGradeSchemeById: getGradeSchemeById,
        getGradeSchemeByClass: getGradeSchemeByClass,
        getGradeSchemeComponents: getGradeSchemeComponents,
        setGradeSchemeComponents: setGradeSchemeComponents,
        getGradeComponentsByScheme: getGradeComponentsByScheme,
        getGradeComponentScores: getGradeComponentScores,
        setGradeComponentScores: setGradeComponentScores,
        getGradeComponentScore: getGradeComponentScore,
        getGradeCorrections: getGradeCorrections,
        setGradeCorrections: setGradeCorrections,
        getGradeCorrectionsByGrade: getGradeCorrectionsByGrade,
        gradeWeightTotal: gradeWeightTotal,
        computeEnrollmentGrade: computeEnrollmentGrade,
        getCertificates: getCertificates,
        setCertificates: setCertificates,
        getCertificatesByStudent: getCertificatesByStudent,
        getPublishedCertificatesByStudent: getPublishedCertificatesByStudent,
        normalizeCertificateStatus: normalizeCertificateStatus,
        isPublishedCertificate: isPublishedCertificate,
        getCertificateByNumber: getCertificateByNumber,
        getCertificate: getCertificate,
        getCompletedLessons: getCompletedLessons,
        setCompletedLessons: setCompletedLessons,
        isLessonCompleted: isLessonCompleted,
        markLessonCompleted: markLessonCompleted,
        getClassMaterialSettingsRows: getClassMaterialSettingsRows,
        setClassMaterialSettingsRows: setClassMaterialSettingsRows,
        getClassMaterials: getClassMaterials,
        setClassMaterials: setClassMaterials,
        getClassMaterialById: getClassMaterialById,
        getClassMaterialsByClass: getClassMaterialsByClass,
        getClassLessonProgress: getClassLessonProgress,
        setClassLessonProgress: setClassLessonProgress,
        getClassLessonProgressByEnrollment: getClassLessonProgressByEnrollment,
        getClassMaterialProgress: getClassMaterialProgress,
        setClassMaterialProgress: setClassMaterialProgress,
        getClassMaterialProgressByEnrollment: getClassMaterialProgressByEnrollment,
        getAssignments: getAssignments,
        setAssignments: setAssignments,
        getAssignmentById: getAssignmentById,
        getAssignmentsByClass: getAssignmentsByClass,
        getAssignmentsByCourse: getAssignmentsByCourse,
        getAssignmentFiles: getAssignmentFiles,
        setAssignmentFiles: setAssignmentFiles,
        getAssignmentFilesByAssignment: getAssignmentFilesByAssignment,
        getAssignmentRubrics: getAssignmentRubrics,
        setAssignmentRubrics: setAssignmentRubrics,
        getAssignmentRubricsByAssignment: getAssignmentRubricsByAssignment,
        getSubmissions: getSubmissions,
        setSubmissions: setSubmissions,
        getSubmissionById: getSubmissionById,
        getSubmissionsByAssignment: getSubmissionsByAssignment,
        getSubmissionsByStudent: getSubmissionsByStudent,
        getSubmissionByEnrollment: getSubmissionByEnrollment,
        getSubmissionFiles: getSubmissionFiles,
        setSubmissionFiles: setSubmissionFiles,
        getSubmissionFilesBySubmission: getSubmissionFilesBySubmission,
        getSubmissionReviews: getSubmissionReviews,
        setSubmissionReviews: setSubmissionReviews,
        getSubmissionReviewsBySubmission: getSubmissionReviewsBySubmission,
        getExams: getExams,
        setExams: setExams,
        getExamById: getExamById,
        getExamsByClass: getExamsByClass,
        getExamParticipants: getExamParticipants,
        setExamParticipants: setExamParticipants,
        getExamParticipantsByExam: getExamParticipantsByExam,
        getExamParticipant: getExamParticipant,
        getExamResults: getExamResults,
        setExamResults: setExamResults,
        getExamResultsByExam: getExamResultsByExam,
        getExamResultsByStudent: getExamResultsByStudent,
        getExamResultByEnrollment: getExamResultByEnrollment
    });
})(window.ELIPS);
