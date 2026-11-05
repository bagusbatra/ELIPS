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
        return getCompletedLessons().some(function (c) {
            return c.studentId === Number(studentId) &&
                c.courseId === Number(courseId) &&
                c.lessonId === Number(lessonId);
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

    Object.assign(ELIPS, {
        getUsers: getUsers,
        setUsers: setUsers,
        getUserById: getUserById,
        getUsersByRole: getUsersByRole,
        getCourses: getCourses,
        setCourses: setCourses,
        getCourseById: getCourseById,
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
        markLessonCompleted: markLessonCompleted
    });
})(window.ELIPS);
