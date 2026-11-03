// ============================================================
// ELIPS LMS - Main JavaScript & localStorage Data Layer
// ============================================================

(function () {
    'use strict';

    const KEYS = {
        users: 'elips_users',
        courses: 'elips_courses',
        enrollments: 'elips_enrollments',
        modules: 'elips_modules',
        lessons: 'elips_lessons',
        grades: 'elips_grades',
        certificates: 'elips_certificates',
        completedLessons: 'elips_completedLessons',
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

    // ---------- Seed data ----------
    const MODULE_TEMPLATES = {
        1: [
            'Pengantar Manajemen',
            'Perencanaan (Planning)',
            'Pengorganisasian (Organizing)',
            'Kepemimpinan (Leading)',
            'Pengendalian (Controlling)',
            'Manajemen Sumber Daya Manusia',
            'Manajemen Waktu & Produktivitas',
            'Evaluasi & Studi Kasus'
        ],
        2: [
            'Dasar Komunikasi Efektif',
            'Komunikasi Verbal & Non-Verbal',
            'Mendengarkan Aktif',
            'Komunikasi dalam Tim',
            'Menangani Konflik',
            'Presentasi & Negosiasi'
        ],
        3: [
            'Pengantar Administrasi Perkantoran',
            'Pengelolaan Arsip',
            'Surat Menyurat',
            'Kearsipan Digital',
            'Aplikasi Perkantoran',
            'Tata Kelola Administrasi',
            'Evaluasi & Praktik'
        ]
    };

    function seedUsers() {
        return [
            { id: 1, name: 'Administrator', email: 'admin@example.com', role: 'admin', phone: '081234567890', status: 'aktif', createdAt: '2026-01-01' },
            { id: 2, name: 'Ahmad Fauzi, M.M.', email: 'ahmad@example.com', role: 'instruktur', phone: '081234567891', status: 'aktif', createdAt: '2026-09-15' },
            { id: 3, name: 'Siti Nurhaliza, S.Psi.', email: 'siti@example.com', role: 'instruktur', phone: '081234567892', status: 'aktif', createdAt: '2026-09-16' },
            { id: 4, name: 'Budi Santoso, A.Md.', email: 'budi@example.com', role: 'instruktur', phone: '081234567893', status: 'aktif', createdAt: '2026-09-17' },
            { id: 5, name: 'Budi Santoso', email: 'budi.student@example.com', role: 'peserta', phone: '081234567894', status: 'aktif', createdAt: '2026-10-01' },
            { id: 6, name: 'Ani Nurhayati', email: 'ani@example.com', role: 'peserta', phone: '081234567895', status: 'aktif', createdAt: '2026-10-02' },
            { id: 7, name: 'Rina Setiawati', email: 'rina@example.com', role: 'peserta', phone: '081234567896', status: 'aktif', createdAt: '2026-10-03' }
        ];
    }

    function seedCourses() {
        return [
            {
                id: 1,
                title: 'Pelatihan Manajemen Dasar',
                category: 'Pelatihan',
                description: 'Pelatihan ini membahas konsep dasar manajemen, perencanaan, organisasi, kepemimpinan, dan pengendalian untuk meningkatkan efektivitas kerja.',
                instructorId: 2,
                instructorName: 'Ahmad Fauzi, M.M.',
                status: 'published',
                createdAt: '2026-09-20'
            },
            {
                id: 2,
                title: 'Komunikasi Efektif di Tempat Kerja',
                category: 'Soft Skill',
                description: 'Meningkatkan kemampuan komunikasi verbal, non-verbal, mendengarkan aktif, dan membangun hubungan yang harmonis di lingkungan kerja.',
                instructorId: 3,
                instructorName: 'Siti Nurhaliza, S.Psi.',
                status: 'draft',
                createdAt: '2026-09-25'
            },
            {
                id: 3,
                title: 'Administrasi Perkantoran Modern',
                category: 'Administrasi',
                description: 'Pengelolaan arsip, surat menyurat, pengarsipan digital, penggunaan aplikasi perkantoran, dan tata kelola administrasi yang efisien.',
                instructorId: 4,
                instructorName: 'Budi Santoso, A.Md.',
                status: 'draft',
                createdAt: '2026-09-28'
            },
            {
                id: 4,
                title: 'Microsoft Office',
                category: 'reguler class',
                description: 'kelas rombel microsoft word, microsoft excel, dan microsoft powerpoint',
                instructorId: 5,
                instructorName: 'Ridho Ahmad',
                status: 'draft',
                createdAt: '2026-10-02'
            }
        ];
    }

    function seedEnrollments() {
        return [
            { id: 1, studentId: 5, courseId: 1, enrolledBy: 'admin', enrolledAt: '2026-10-01', status: 'active' },
            { id: 2, studentId: 6, courseId: 1, enrolledBy: 'admin', enrolledAt: '2026-10-05', status: 'active' },
            { id: 3, studentId: 7, courseId: 1, enrolledBy: 'admin', enrolledAt: '2026-10-10', status: 'active' }
        ];
    }

    function buildStructure(courses, users) {
        const modules = [];
        const lessons = [];
        let moduleId = 1;
        let lessonId = 1;

        courses.forEach(function (course) {
            const instructor = users.find(function (u) { return u.id === course.instructorId; });
            const titles = MODULE_TEMPLATES[course.id] || ['Modul 1'];
            const createdAt = course.createdAt || '2026-10-01';

            titles.forEach(function (title, mIndex) {
                const currentModuleId = moduleId++;
                modules.push({
                    id: currentModuleId,
                    courseId: course.id,
                    instructorId: course.instructorId,
                    title: 'Modul ' + (mIndex + 1) + ': ' + title,
                    description: 'Materi ' + title.toLowerCase() + ' pada kursus ' + course.title + '.',
                    order: mIndex + 1,
                    createdAt: createdAt
                });

                for (let lIndex = 1; lIndex <= 3; lIndex++) {
                    const isVideo = lIndex === 3;
                    lessons.push({
                        id: lessonId++,
                        moduleId: currentModuleId,
                        courseId: course.id,
                        instructorId: course.instructorId,
                        title: (mIndex + 1) + '.' + lIndex + ' ' + title + (lIndex === 1 ? ' - Konsep Dasar' : lIndex === 2 ? ' - Penerapan' : ' - Studi Kasus'),
                        type: isVideo ? 'video' : 'text',
                        content: isVideo
                            ? '<p>Simulasi video pembelajaran untuk materi <strong>' + title + '</strong>. Pada versi produksi, konten video dapat di-embed dari YouTube atau Vimeo.</p>'
                            : '<p>Materi ini membahas <strong>' + title + '</strong> secara mendalam, mulai dari konsep dasar, penerapan di tempat kerja, hingga studi kasus sederhana.</p><ul><li>Pahami konsep utama materi.</li><li>Perhatikan contoh penerapan.</li><li>Kerjakan latihan mandiri.</li></ul>',
                        order: lIndex,
                        createdAt: createdAt
                    });
                }
            });

            // keep denormalized counters in sync
            course.modules = titles.length;
            course.lessons = titles.length * 3;
            course.totalLessons = course.lessons;
            course.instructorName = instructor ? instructor.name : (course.instructorName || '');
        });

        return { modules: modules, lessons: lessons };
    }

    function normalizeData() {
        const users = read(KEYS.users, null);
        if (!Array.isArray(users) || users.length === 0) {
            write(KEYS.users, seedUsers());
        }

        const currentUsers = read(KEYS.users, []);
        const courses = read(KEYS.courses, null);
        if (!Array.isArray(courses) || courses.length === 0) {
            write(KEYS.courses, seedCourses());
        }

        const currentCourses = read(KEYS.courses, []);
        currentCourses.forEach(function (course) {
            if (course.status !== 'draft' && course.status !== 'published') course.status = 'draft';
            if (!course.instructorId) {
                const fallback = currentUsers.find(function (u) { return u.role === 'instruktur'; });
                course.instructorId = fallback ? fallback.id : null;
            }
            const instructor = currentUsers.find(function (u) { return u.id === course.instructorId; });
            if (instructor) course.instructorName = instructor.name;
        });
        write(KEYS.courses, currentCourses);

        if (!Array.isArray(read(KEYS.enrollments, null))) {
            write(KEYS.enrollments, seedEnrollments());
        }

        // Migrate legacy "elips_enrolled" (self enrollment) into assignments for demo student.
        const legacy = read('elips_enrolled', []);
        if (Array.isArray(legacy) && legacy.length > 0) {
            const enrollments = getEnrollments();
            legacy.forEach(function (item) {
                const courseId = Number(item.courseId);
                if (!courseId) return;
                if (!enrollments.some(function (e) { return e.studentId === 5 && e.courseId === courseId; })) {
                    enrollments.push({
                        id: nextId(enrollments),
                        studentId: 5,
                        courseId: courseId,
                        enrolledBy: 'admin',
                        enrolledAt: (item.enrolledAt || new Date().toISOString()).split('T')[0],
                        status: 'active'
                    });
                }
            });
            write(KEYS.enrollments, enrollments);
            localStorage.removeItem('elips_enrolled');
        }

        // Normalize enrollments
        const enrollments = getEnrollments();
        enrollments.forEach(function (e) {
            if (!e.status) e.status = 'active';
            if (!e.enrolledBy) e.enrolledBy = 'admin';
            if (!e.enrolledAt) e.enrolledAt = new Date().toISOString().split('T')[0];
        });
        write(KEYS.enrollments, enrollments);

        // Seed module & lesson structure only when empty
        if (getModules().length === 0 || getLessons().length === 0) {
            const structure = buildStructure(currentCourses, currentUsers);
            write(KEYS.modules, structure.modules);
            write(KEYS.lessons, structure.lessons);
            write(KEYS.courses, currentCourses);
        }

        if (!Array.isArray(read(KEYS.grades, null))) write(KEYS.grades, []);
        if (!Array.isArray(read(KEYS.completedLessons, null))) write(KEYS.completedLessons, []);

        // Normalize certificates (migrate legacy ones)
        const certificates = getCertificates();
        const changed = certificates.map(function (cert) {
            if (!cert.studentId) cert.studentId = 5;
            if (!cert.courseId) cert.courseId = 1;
            if (!cert.status) cert.status = 'publish';
            if (!cert.grade && cert.score) cert.grade = cert.score;
            if (cert.courseName && !cert.courseId) cert.courseId = 1;
            if (!cert.certificateNumber) cert.certificateNumber = 'CERT-' + new Date().getFullYear() + '-' + Math.random().toString(36).slice(2, 8).toUpperCase();
            if (!cert.enrollmentId) {
                const enr = getEnrollments().find(function (e) { return e.studentId === cert.studentId && e.courseId === cert.courseId; });
                cert.enrollmentId = enr ? enr.id : null;
            }
            return cert;
        });
        if (!Array.isArray(read(KEYS.certificates, null))) {
            write(KEYS.certificates, []);
        } else {
            write(KEYS.certificates, changed);
        }
    }

    // ---------- Getters / setters ----------
    function getUsers() { return read(KEYS.users, []); }
    function setUsers(list) { return write(KEYS.users, list); }
    function getUserById(id) { return getUsers().find(function (u) { return u.id === Number(id); }) || null; }
    function getUsersByRole(role) { return getUsers().filter(function (u) { return u.role === role; }); }

    function getCourses() { return read(KEYS.courses, []); }
    function setCourses(list) { return write(KEYS.courses, list); }
    function getCourseById(id) { return getCourses().find(function (c) { return c.id === Number(id); }) || null; }

    function getEnrollments() { return read(KEYS.enrollments, []); }
    function setEnrollments(list) { return write(KEYS.enrollments, list); }
    function getEnrollmentsByStudent(studentId) { return getEnrollments().filter(function (e) { return e.studentId === Number(studentId); }); }
    function getEnrollmentsByCourse(courseId) { return getEnrollments().filter(function (e) { return e.courseId === Number(courseId); }); }
    function getEnrollment(studentId, courseId) {
        return getEnrollments().find(function (e) { return e.studentId === Number(studentId) && e.courseId === Number(courseId); }) || null;
    }
    function isEnrolled(studentId, courseId) { return !!getEnrollment(studentId, courseId); }

    function getModules() { return read(KEYS.modules, []); }
    function setModules(list) { return write(KEYS.modules, list); }
    function getModulesByCourse(courseId) {
        return getModules()
            .filter(function (m) { return m.courseId === Number(courseId); })
            .sort(function (a, b) { return (a.order || 0) - (b.order || 0); });
    }

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

    function getGrades() { return read(KEYS.grades, []); }
    function setGrades(list) { return write(KEYS.grades, list); }
    function getGrade(studentId, courseId) {
        return getGrades().find(function (g) { return g.studentId === Number(studentId) && g.courseId === Number(courseId); }) || null;
    }
    function getGradesByStudent(studentId) {
        return getGrades().filter(function (g) { return g.studentId === Number(studentId); });
    }

    function getCertificates() { return read(KEYS.certificates, []); }
    function setCertificates(list) { return write(KEYS.certificates, list); }
    function getCertificatesByStudent(studentId) {
        return getCertificates().filter(function (c) { return c.studentId === Number(studentId); });
    }
    function getCertificateByNumber(number) {
        const target = String(number || '').trim().toUpperCase();
        return getCertificates().find(function (c) { return String(c.certificateNumber).toUpperCase() === target; }) || null;
    }
    function getCertificate(studentId, courseId) {
        return getCertificates().find(function (c) { return c.studentId === Number(studentId) && c.courseId === Number(courseId); }) || null;
    }

    function getCompletedLessons() { return read(KEYS.completedLessons, []); }
    function setCompletedLessons(list) { return write(KEYS.completedLessons, list); }
    function isLessonCompleted(studentId, courseId, lessonId) {
        return getCompletedLessons().some(function (c) {
            return c.studentId === Number(studentId) && c.courseId === Number(courseId) && c.lessonId === Number(lessonId);
        });
    }
    function markLessonCompleted(studentId, courseId, lessonId) {
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

    // ---------- Course structure / progress ----------
    function getCourseStructure(courseId) {
        const modules = getModulesByCourse(courseId);
        return modules.map(function (mod) {
            return { module: mod, lessons: getLessonsByModule(mod.id) };
        });
    }

    function getCourseProgress(studentId, courseId) {
        const flat = [];
        getCourseStructure(courseId).forEach(function (item) {
            item.lessons.forEach(function (lesson) { flat.push(lesson); });
        });
        const total = flat.length;
        const completed = flat.filter(function (lesson) {
            return isLessonCompleted(studentId, courseId, lesson.id);
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
            if (!isLessonCompleted(studentId, courseId, flat[i].id)) return false;
        }
        return true;
    }

    // ---------- Student overview ----------
    function getStudentCourseOverview(studentId) {
        const courses = getCourses();
        return getEnrollmentsByStudent(studentId).map(function (enrollment) {
            const course = courses.find(function (c) { return c.id === enrollment.courseId; });
            if (!course) return null;

            const progress = getCourseProgress(studentId, course.id);
            const grade = getGrade(studentId, course.id);
            const certificate = getCertificate(studentId, course.id);
            const isComplete = progress.total > 0 && progress.completed === progress.total;
            let state = 'not-started';

            if (course.status !== 'published') {
                state = 'locked';
            } else if (certificate && certificate.status === 'publish') {
                state = 'certified';
            } else if (certificate && certificate.status === 'draft') {
                state = 'pending-approval';
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
                'pending-approval': 4,
                'completed': 5,
                'certified': 6,
                'locked': 7
            };
            const diff = (rank[a.state] || 99) - (rank[b.state] || 99);
            if (diff !== 0) return diff;
            return Number(b.enrollment.id) - Number(a.enrollment.id);
        });
    }

    // ---------- Session ----------
    function defaultUserForPage() {
        const path = (window.location.pathname || '').toLowerCase().replace(/\\/g, '/');
        if (path.indexOf('/admin/') !== -1) return getUserById(1) || getUsersByRole('admin')[0] || null;
        if (path.indexOf('/instructor/') !== -1) {
            const instructors = getUsersByRole('instruktur');
            return instructors[0] || null;
        }
        return getUserById(5) || getUsersByRole('peserta')[0] || null;
    }

    function getCurrentUser() {
        return getSessionUser() || defaultUserForPage();
    }

    function getSessionUser() {
        const session = read(KEYS.session, null);
        const id = session && (session.userId || session);
        if (!id) return null;
        return getUserById(id);
    }

    function basePathPrefix() {
        const path = (window.location.pathname || '').replace(/\\/g, '/');
        if (/\/(admin|instructor|student)\/[^/]*$/.test(path)) return '../';
        return '';
    }

    function dashboardPathForRole(role) {
        const prefix = basePathPrefix();
        if (role === 'admin') return prefix + 'admin/dashboard.html';
        if (role === 'instruktur') return prefix + 'instructor/dashboard.html';
        return prefix + 'student/dashboard.html';
    }

    function setCurrentUser(userId) {
        write(KEYS.session, { userId: Number(userId), loggedInAt: new Date().toISOString() });
        return getUserById(userId);
    }

    function logout() {
        localStorage.removeItem(KEYS.session);
    }

    // ---------- Utilities ----------
    function gradeLetter(score) {
        const value = Number(score);
        if (isNaN(value)) return '-';
        if (value >= 90) return 'A';
        if (value >= 80) return 'A-';
        if (value >= 75) return 'B+';
        if (value >= 70) return 'B';
        if (value >= 65) return 'B-';
        if (value >= 60) return 'C+';
        if (value >= 55) return 'C';
        if (value >= 50) return 'D';
        return 'E';
    }

    function generateCertificateNumber() {
        return 'CERT-' + new Date().getFullYear() + '-' + Math.random().toString(36).slice(2, 8).toUpperCase();
    }

    function escapeHtml(text) {
        return String(text === undefined || text === null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '-';
        const date = new Date(value);
        if (isNaN(date.getTime())) return value;
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
    }

    function showToast(message) {
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = [
            'position: fixed',
            'bottom: 2rem',
            'left: 50%',
            'transform: translateX(-50%)',
            'background: var(--text-primary, #111827)',
            'color: white',
            'padding: 0.75rem 1.5rem',
            'border-radius: 8px',
            'box-shadow: 0 10px 25px rgba(0,0,0,.15)',
            'z-index: 10000',
            'font-size: 0.9375rem',
            'animation: elipsSlideUp 0.3s ease'
        ].join(';');
        document.body.appendChild(toast);
        setTimeout(function () {
            toast.style.animation = 'elipsSlideDown 0.3s ease';
            setTimeout(function () { toast.remove(); }, 300);
        }, 2500);
    }

    // ---------- Public API ----------
    const ELIPS = {
        KEYS: KEYS,
        read: read,
        write: write,
        nextId: nextId,
        getUsers: getUsers,
        setUsers: setUsers,
        getUserById: getUserById,
        getUsersByRole: getUsersByRole,
        getCourses: getCourses,
        setCourses: setCourses,
        getCourseById: getCourseById,
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
        getCertificateByNumber: getCertificateByNumber,
        getCertificate: getCertificate,
        getCompletedLessons: getCompletedLessons,
        setCompletedLessons: setCompletedLessons,
        isLessonCompleted: isLessonCompleted,
        markLessonCompleted: markLessonCompleted,
        getCourseStructure: getCourseStructure,
        getCourseProgress: getCourseProgress,
        isLessonUnlocked: isLessonUnlocked,
        getStudentCourseOverview: getStudentCourseOverview,
        getCurrentUser: getCurrentUser,
        getSessionUser: getSessionUser,
        dashboardPathForRole: dashboardPathForRole,
        setCurrentUser: setCurrentUser,
        logout: logout,
        gradeLetter: gradeLetter,
        generateCertificateNumber: generateCertificateNumber,
        escapeHtml: escapeHtml,
        formatDate: formatDate,
        toast: showToast
    };

    window.ELIPS = ELIPS;
    window.showToast = showToast;

    // ---------- Initialize ----------
    normalizeData();

    // ---------- Shared UI ----------
    document.addEventListener('DOMContentLoaded', function () {
        // Mobile navigation
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', function () {
                navMenu.classList.toggle('active');
            });
        }

        // Session-aware navigation (public pages & mobile menu)
        const sessionUser = getSessionUser();
        if (sessionUser) {
            const dashboardPath = dashboardPathForRole(sessionUser.role);
            const authActions = document.querySelector('.nav-actions');
            if (authActions && authActions.querySelector('a[href$="login.html"]')) {
                authActions.innerHTML = [
                    '<a href="' + dashboardPath + '" class="btn btn-ghost"><i class="bi bi-speedometer2"></i> Dashboard</a>',
                    '<a href="' + basePathPrefix() + 'index.html" class="btn btn-outline" data-logout>Keluar</a>'
                ].join('');
            }

            if (navMenu && !navMenu.querySelector('[data-session-link]')) {
                const dashboardLink = document.createElement('a');
                dashboardLink.href = dashboardPath;
                dashboardLink.className = 'nav-link mobile-session-link';
                dashboardLink.setAttribute('data-session-link', '1');
                dashboardLink.textContent = 'Dashboard';

                const logoutLink = document.createElement('a');
                logoutLink.href = basePathPrefix() + 'index.html';
                logoutLink.className = 'nav-link mobile-session-link';
                logoutLink.setAttribute('data-session-link', '1');
                logoutLink.setAttribute('data-logout', '1');
                logoutLink.textContent = 'Keluar';

                navMenu.appendChild(dashboardLink);
                navMenu.appendChild(logoutLink);
            }
        }

        // Dashboard sidebar (mobile drawer)
        const dashboardLayout = document.querySelector('.dashboard-layout');
        const dashboardSidebar = dashboardLayout ? dashboardLayout.querySelector('.dashboard-sidebar') : null;
        if (dashboardLayout && dashboardSidebar && !document.getElementById('dashboardSidebarToggle')) {
            const sidebarButton = document.createElement('button');
            sidebarButton.type = 'button';
            sidebarButton.id = 'dashboardSidebarToggle';
            sidebarButton.className = 'dashboard-sidebar-toggle';
            sidebarButton.setAttribute('aria-label', 'Buka menu navigasi');
            sidebarButton.setAttribute('aria-expanded', 'false');
            sidebarButton.innerHTML = '<i class="bi bi-list"></i>';

            const backdrop = document.createElement('div');
            backdrop.className = 'dashboard-sidebar-backdrop';

            dashboardLayout.appendChild(sidebarButton);
            dashboardLayout.appendChild(backdrop);

            function setSidebarOpen(open) {
                dashboardSidebar.classList.toggle('active', open);
                backdrop.classList.toggle('active', open);
                sidebarButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                sidebarButton.innerHTML = '<i class="bi ' + (open ? 'bi-x-lg' : 'bi-list') + '"></i>';
            }

            sidebarButton.addEventListener('click', function () {
                setSidebarOpen(!dashboardSidebar.classList.contains('active'));
            });
            backdrop.addEventListener('click', function () {
                setSidebarOpen(false);
            });
            dashboardSidebar.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.innerWidth <= 968) setSidebarOpen(false);
                });
            });
            window.addEventListener('resize', function () {
                if (window.innerWidth > 968) setSidebarOpen(false);
            });
        }

        // Learning sidebar
        const sidebarToggle = document.getElementById('sidebarToggle');
        const learningSidebar = document.getElementById('learningSidebar');
        if (sidebarToggle && learningSidebar) {
            sidebarToggle.addEventListener('click', function () {
                learningSidebar.classList.toggle('active');
            });
            document.addEventListener('click', function (e) {
                if (window.innerWidth <= 968 && !learningSidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    learningSidebar.classList.remove('active');
                }
            });
        }

        // Homepage course catalog (published only)
        const coursesGrid = document.getElementById('coursesGrid');
        if (coursesGrid) {
            const user = getCurrentUser();
            const enrollments = user ? getEnrollmentsByStudent(user.id) : [];
            coursesGrid.innerHTML = '';
            const published = getCourses().filter(function (c) { return c.status === 'published'; });

            if (published.length === 0) {
                coursesGrid.innerHTML = '<p style="color: var(--text-secondary);">Belum ada kursus yang dipublikasikan.</p>';
            }

            published.forEach(function (course) {
                const lessons = getLessonsByCourse(course.id).length;
                const modules = getModulesByCourse(course.id).length;
                const isEnrolled = enrollments.some(function (e) { return e.courseId === course.id; });
                const card = document.createElement('a');
                card.href = 'course-detail.html?id=' + course.id;
                card.className = 'course-card';
                card.innerHTML = [
                    '<div class="course-card-img"></div>',
                    '<div class="course-card-content">',
                    '  <span class="course-card-category">' + escapeHtml(course.category) + '</span>',
                    '  <h3 class="course-card-title">' + escapeHtml(course.title) + '</h3>',
                    '  <p class="course-card-desc">' + escapeHtml(course.description) + '</p>',
                    '  <div class="course-card-meta">',
                    '    <span><i class="bi bi-collection"></i> ' + modules + ' Modul</span>',
                    '    <span><i class="bi bi-journal-text"></i> ' + lessons + ' Pelajaran</span>',
                    '  </div>',
                    isEnrolled ? '<div style="margin-top: 0.5rem;"><span class="badge badge-success">Ditugaskan</span></div>' : '',
                    '</div>'
                ].join('');
                coursesGrid.appendChild(card);
            });
        }

        // Login handler (validated against elips_users)
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const emailInput = document.getElementById('email');
                const email = (emailInput ? emailInput.value : '').trim().toLowerCase();
                const user = getUsers().find(function (u) { return String(u.email).toLowerCase() === email; });

                if (!user) {
                    showToast('Email tidak terdaftar. Gunakan akun demo yang tersedia.');
                    return;
                }
                if (user.status === 'nonaktif') {
                    showToast('Akun Anda nonaktif. Hubungi administrator.');
                    return;
                }

                setCurrentUser(user.id);
                showToast('Berhasil masuk sebagai ' + user.name);
                if (user.role === 'admin') {
                    window.location.href = 'admin/dashboard.html';
                } else if (user.role === 'instruktur') {
                    window.location.href = 'instructor/dashboard.html';
                } else {
                    window.location.href = 'student/dashboard.html';
                }
            });
        }

        // Logout links
        document.querySelectorAll('[data-logout]').forEach(function (link) {
            link.addEventListener('click', function () {
                logout();
                window.location.href = link.getAttribute('href') || 'index.html';
            });
        });

        // Toast keyframes (once)
        if (!document.getElementById('elipsToastStyles')) {
            const style = document.createElement('style');
            style.id = 'elipsToastStyles';
            style.textContent = [
                '@keyframes elipsSlideUp { from { opacity: 0; transform: translateX(-50%) translateY(1rem); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }',
                '@keyframes elipsSlideDown { from { opacity: 1; transform: translateX(-50%) translateY(0); } to { opacity: 0; transform: translateX(-50%) translateY(1rem); } }',
                '.learning-lesson.locked { opacity: .55; cursor: not-allowed; }',
                '.learning-lesson.locked:hover { background: transparent; color: var(--text-secondary); }',
                '.form-hint { color: var(--text-secondary); font-size: 0.8125rem; }'
            ].join('\n');
            document.head.appendChild(style);
        }
    });
})();
