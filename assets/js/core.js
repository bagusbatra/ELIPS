// ============================================================
// ELIPS LMS - core.js
// Namespace global, konfigurasi storage, dan helper dasar.
// Dimuat pertama sebelum modul JavaScript lainnya.
// ============================================================

(function (window) {
    'use strict';

    const ELIPS = window.ELIPS = window.ELIPS || {};

    // Password demo untuk akun seed dan migrasi akun lama yang belum
    // memiliki password. Bukan pengganti autentikasi server-side.
    const DEFAULT_PASSWORD = 'elips123';

    const KEYS = {
        users: 'elips_users',
        branches: 'elips_branches',
        categories: 'elips_categories',
        courses: 'elips_courses',
        curriculumVersions: 'elips_curriculumVersions',
        classes: 'elips_classes',
        classInstructorHistories: 'elips_classInstructorHistories',
        enrollments: 'elips_enrollments',
        classSessions: 'elips_classSessions',
        attendances: 'elips_attendances',
        attendanceCorrections: 'elips_attendanceCorrections',
        modules: 'elips_modules',
        lessons: 'elips_lessons',
        grades: 'elips_grades',
        predicateRanges: 'elips_predicateRanges',
        gradeSchemes: 'elips_gradeSchemes',
        gradeSchemeComponents: 'elips_gradeSchemeComponents',
        gradeComponentScores: 'elips_gradeComponentScores',
        gradeCorrections: 'elips_gradeCorrections',
        certificates: 'elips_certificates',
        completedLessons: 'elips_completedLessons',
        classMaterialSettings: 'elips_classMaterialSettings',
        classMaterials: 'elips_classMaterials',
        classLessonProgress: 'elips_classLessonProgress',
        classMaterialProgress: 'elips_classMaterialProgress',
        assignments: 'elips_assignments',
        assignmentFiles: 'elips_assignmentFiles',
        assignmentRubrics: 'elips_assignmentRubrics',
        submissions: 'elips_submissions',
        submissionFiles: 'elips_submissionFiles',
        submissionReviews: 'elips_submissionReviews',
        exams: 'elips_exams',
        examParticipants: 'elips_examParticipants',
        examResults: 'elips_examResults',
        auditLogs: 'elips_auditLogs',
        notifications: 'elips_notifications',
        staffAttendances: 'elips_staffAttendances',
        staffShifts: 'elips_staffShifts',
        letters: 'elips_letters',
        session: 'elips_session'
    };

    // ---------- Generic storage helpers ----------
    function read(key, fallback) {
        try {
            const raw = localStorage.getItem(key);
            if (raw === null) return fallback;
            const parsed = JSON.parse(raw);
            return parsed === null || parsed === undefined ? fallback : parsed;
        } catch (err) {
            console.warn('[ELIPS] Gagal membaca', key, err);
            return fallback;
        }
    }

    function write(key, value) {
        localStorage.setItem(key, JSON.stringify(value));
        return value;
    }

    function nextId(items) {
        return items.reduce(function (max, item) {
            const id = Number(item.id) || 0;
            return id > max ? id : max;
        }, 0) + 1;
    }

    // ---------- Shared result & validation helpers ----------
    function todayString() {
        return new Date().toISOString().split('T')[0];
    }

    function success(message, data) {
        return { ok: true, message: message || '', data: data === undefined ? null : data };
    }

    function failure(message) {
        return { ok: false, message: message || 'Operasi gagal.' };
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || ''));
    }

    function isValidRole(role) {
        return role === 'admin' || role === 'instruktur' || role === 'peserta'
            || role === 'karyawan';
    }

    const USER_DIVISIONS = ['marketing', 'operasional', 'keuangan', 'hrd'];

    const DIVISION_LABELS = {
        marketing: 'Marketing',
        operasional: 'Operasional',
        keuangan: 'Keuangan',
        hrd: 'HRD'
    };

    function isValidDivision(division) {
        return division === '' || USER_DIVISIONS.indexOf(division) !== -1;
    }

    function divisionLabel(division) {
        return DIVISION_LABELS[division] || '';
    }

    function isValidUserStatus(status) {
        return status === 'aktif' || status === 'nonaktif';
    }

    function isActiveInstructor(id) {
        const user = typeof ELIPS.getUserById === 'function' ? ELIPS.getUserById(id) : null;
        return !!user && user.role === 'instruktur' && user.status === 'aktif';
    }

    ELIPS.KEYS = KEYS;
    ELIPS.DEFAULT_PASSWORD = DEFAULT_PASSWORD;
    ELIPS.USER_DIVISIONS = USER_DIVISIONS;
    ELIPS.divisionLabel = divisionLabel;
    ELIPS.read = read;
    ELIPS.write = write;
    ELIPS.nextId = nextId;

    // Helper internal yang dipakai lintas modul.
    ELIPS._ = {
        todayString: todayString,
        success: success,
        failure: failure,
        isValidEmail: isValidEmail,
        isValidRole: isValidRole,
        USER_DIVISIONS: USER_DIVISIONS,
        isValidDivision: isValidDivision,
        divisionLabel: divisionLabel,
        isValidUserStatus: isValidUserStatus,
        isActiveInstructor: isActiveInstructor
    };
})(window);
