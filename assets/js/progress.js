// ============================================================
// ELIPS LMS - progress.js
// Struktur kursus, progres belajar, dan ringkasan data
// untuk halaman siswa serta instruktur.
// ============================================================

(function (ELIPS) {
    'use strict';

    // ---------- Course structure / progress ----------
    function getCourseStructure(courseId) {
        const modules = ELIPS.getModulesByCourse(courseId);
        return modules.map(function (mod) {
            return { module: mod, lessons: ELIPS.getLessonsByModule(mod.id) };
        });
    }

    function getCourseProgress(studentId, courseId) {
        const flat = [];
        getCourseStructure(courseId).forEach(function (item) {
            item.lessons.forEach(function (lesson) { flat.push(lesson); });
        });
        const total = flat.length;
        const completed = flat.filter(function (lesson) {
            return ELIPS.isLessonCompleted(studentId, courseId, lesson.id);
        }).length;
        const percentage = total === 0 ? 0 : Math.round((completed / total) * 100);
        return { total: total, completed: completed, percentage: percentage };
    }

    function isLessonUnlocked(studentId, courseId, lessonId) {
        const flat = [];
        getCourseStructure(courseId).forEach(function (item) {
            item.lessons.forEach(function (lesson) { flat.push(lesson); });
        });
        const index = flat.findIndex(function (l) { return l.id === Number(lessonId); });
        if (index <= 0) return true;
        for (let i = 0; i < index; i++) {
            if (!ELIPS.isLessonCompleted(studentId, courseId, flat[i].id)) return false;
        }
        return true;
    }

    // ---------- Student overview ----------
    function getStudentCourseOverview(studentId) {
        const courses = ELIPS.getCourses();
        return ELIPS.getEnrollmentsByStudent(studentId).map(function (enrollment) {
            const course = courses.find(function (c) { return c.id === enrollment.courseId; });
            if (!course) return null;

            const progress = getCourseProgress(studentId, course.id);
            const grade = ELIPS.getGrade(studentId, course.id);
            const certificate = ELIPS.getCertificate(studentId, course.id);
            const isComplete = progress.total > 0 && progress.completed === progress.total;
            let state = 'not-started';

            if (course.status !== 'published') {
                state = 'locked';
            } else if (certificate && certificate.status === 'publish') {
                state = 'certified';
            } else if (isComplete && grade) {
                state = 'completed';
            } else if (isComplete) {
                state = 'awaiting-grade';
            } else if (progress.completed > 0) {
                state = 'learning';
            }

            return {
                enrollment: enrollment,
                course: course,
                progress: progress,
                grade: grade,
                certificate: certificate,
                isComplete: isComplete,
                state: state
            };
        }).filter(Boolean).sort(function (a, b) {
            const rank = {
                'learning': 1,
                'not-started': 2,
                'awaiting-grade': 3,
                'completed': 5,
                'certified': 6,
                'locked': 7
            };
            const diff = (rank[a.state] || 99) - (rank[b.state] || 99);
            if (diff !== 0) return diff;
            return Number(b.enrollment.id) - Number(a.enrollment.id);
        });
    }

    // ---------- Instructor overview ----------
    function getInstructorCourses(instructorId) {
        return ELIPS.getCourses().filter(function (course) {
            return course.instructorId === Number(instructorId);
        }).sort(function (a, b) {
            // Kursus yang sudah dipublikasikan tampil lebih dulu.
            const statusDiff = (b.status === 'published' ? 1 : 0) - (a.status === 'published' ? 1 : 0);
            if (statusDiff !== 0) return statusDiff;
            return Number(b.id) - Number(a.id);
        });
    }

    function buildInstructorRows(courses) {
        const users = ELIPS.getUsers();
        const rows = [];

        courses.forEach(function (course) {
            ELIPS.getEnrollmentsByCourse(course.id)
                .filter(function (enrollment) { return enrollment.status === 'active'; })
                .forEach(function (enrollment) {
                    const student = users.find(function (user) { return user.id === enrollment.studentId; }) || {};
                    const progress = getCourseProgress(enrollment.studentId, course.id);
                    rows.push({
                        course: course,
                        enrollment: enrollment,
                        student: student,
                        progress: progress,
                        isComplete: progress.total > 0 && progress.completed === progress.total,
                        grade: ELIPS.getGrade(enrollment.studentId, course.id),
                        certificate: ELIPS.getCertificate(enrollment.studentId, course.id)
                    });
                });
        });

        return rows.sort(function (a, b) {
            const dateA = new Date(a.enrollment.enrolledAt || 0).getTime();
            const dateB = new Date(b.enrollment.enrolledAt || 0).getTime();
            if (dateA !== dateB) return dateB - dateA;
            return Number(b.enrollment.id) - Number(a.enrollment.id);
        });
    }

    function getInstructorStudentRows(instructorId, courseId) {
        const courses = getInstructorCourses(instructorId);
        if (courseId) {
            const course = courses.find(function (item) { return item.id === Number(courseId); });
            return course ? buildInstructorRows([course]) : [];
        }
        return buildInstructorRows(courses);
    }

    function getInstructorSummary(instructorId) {
        const courses = getInstructorCourses(instructorId);
        const courseIds = courses.map(function (course) { return course.id; });
        const enrollments = ELIPS.getEnrollments().filter(function (enrollment) {
            return courseIds.indexOf(enrollment.courseId) !== -1 && enrollment.status === 'active';
        });
        const studentIds = {};
        enrollments.forEach(function (enrollment) { studentIds[enrollment.studentId] = true; });

        const modules = ELIPS.getModules().filter(function (module) { return courseIds.indexOf(module.courseId) !== -1; });
        const lessons = ELIPS.getLessons().filter(function (lesson) { return courseIds.indexOf(lesson.courseId) !== -1; });
        const certificates = ELIPS.getCertificates().filter(function (cert) { return courseIds.indexOf(cert.courseId) !== -1; });
        const grades = ELIPS.getGrades().filter(function (grade) { return courseIds.indexOf(grade.courseId) !== -1; });

        let completedStudents = 0;
        let awaitingGrade = 0;
        enrollments.forEach(function (enrollment) {
            const progress = getCourseProgress(enrollment.studentId, enrollment.courseId);
            if (progress.total > 0 && progress.completed === progress.total) {
                completedStudents++;
                if (!ELIPS.getGrade(enrollment.studentId, enrollment.courseId)) awaitingGrade++;
            }
        });

        return {
            courses: courses,
            courseIds: courseIds,
            totalCourses: courses.length,
            publishedCourses: courses.filter(function (course) { return course.status === 'published'; }).length,
            draftCourses: courses.filter(function (course) { return course.status !== 'published'; }).length,
            totalStudents: Object.keys(studentIds).length,
            activeEnrollments: enrollments.length,
            totalModules: modules.length,
            totalLessons: lessons.length,
            totalCertificates: certificates.length,
            publishedCertificates: certificates.filter(function (cert) { return cert.status === 'publish'; }).length,
            draftCertificates: certificates.filter(function (cert) { return cert.status === 'draft'; }).length,
            totalGrades: grades.length,
            completedStudents: completedStudents,
            awaitingGrade: awaitingGrade
        };
    }

    Object.assign(ELIPS, {
        getCourseStructure: getCourseStructure,
        getCourseProgress: getCourseProgress,
        isLessonUnlocked: isLessonUnlocked,
        getStudentCourseOverview: getStudentCourseOverview,
        getInstructorCourses: getInstructorCourses,
        getInstructorStudentRows: getInstructorStudentRows,
        getInstructorSummary: getInstructorSummary
    });
})(window.ELIPS);
