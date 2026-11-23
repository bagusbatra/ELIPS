// ============================================================
// ELIPS LMS - sync.js
// Sinkronisasi data turunan: kepemilikan konten, nama instruktur,
// dan penghitung modul/materi pada kursus.
// ============================================================

(function (ELIPS) {
    'use strict';

    function syncInstructorNameOnCourses(user) {
        if (!user) return;
        const courses = ELIPS.getCourses();
        let changed = false;
        courses.forEach(function (course) {
            if (course.instructorId === user.id && course.instructorName !== user.name) {
                course.instructorName = user.name;
                changed = true;
            }
        });
        if (changed) ELIPS.setCourses(courses);
    }

    function syncAllContentOwners() {
        const courses = ELIPS.getCourses();
        const owners = {};
        courses.forEach(function (course) { owners[course.id] = course.instructorId || null; });

        const modules = ELIPS.getModules();
        let modulesChanged = false;
        modules.forEach(function (module) {
            const owner = owners[module.courseId] === undefined ? null : owners[module.courseId];
            if (module.instructorId !== owner) {
                module.instructorId = owner;
                modulesChanged = true;
            }
        });
        if (modulesChanged) ELIPS.setModules(modules);

        const lessons = ELIPS.getLessons();
        let lessonsChanged = false;
        lessons.forEach(function (lesson) {
            const owner = owners[lesson.courseId] === undefined ? null : owners[lesson.courseId];
            if (lesson.instructorId !== owner) {
                lesson.instructorId = owner;
                lessonsChanged = true;
            }
        });
        if (lessonsChanged) ELIPS.setLessons(lessons);
    }

    function syncCourseCounters(courseId) {
        const courses = ELIPS.getCourses();
        const course = courses.find(function (item) { return item.id === Number(courseId); });
        if (!course) return null;
        course.modules = ELIPS.getModulesByCourse(course.id).length;
        course.lessons = ELIPS.getLessonsByCourse(course.id).length;
        course.totalLessons = course.lessons;
        ELIPS.setCourses(courses);
        return { modules: course.modules, lessons: course.lessons };
    }

    function releaseInstructorCourses(instructorId, onlyUnpublish) {
        const courses = ELIPS.getCourses();
        let changed = 0;
        courses.forEach(function (course) {
            if (course.instructorId !== Number(instructorId)) return;
            if (!onlyUnpublish) {
                course.instructorId = null;
                course.instructorName = '';
            }
            if (course.status === 'published') course.status = 'draft';
            changed++;
        });
        if (changed > 0) {
            ELIPS.setCourses(courses);
            if (!onlyUnpublish) syncAllContentOwners();
        }
        return changed;
    }

    ELIPS.syncCourseCounters = syncCourseCounters;
    ELIPS._.syncInstructorNameOnCourses = syncInstructorNameOnCourses;
    ELIPS._.syncAllContentOwners = syncAllContentOwners;
    ELIPS._.releaseInstructorCourses = releaseInstructorCourses;
})(window.ELIPS);
