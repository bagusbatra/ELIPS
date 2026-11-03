// ============================================================
// ELIPS LMS - seed.js
// Data demo awal dan normalisasi data lama (migrasi).
// Dijalankan sekali saat modul dimuat.
// ============================================================

(function (ELIPS) {
    'use strict';

    const KEYS = ELIPS.KEYS;
    const write = ELIPS.write;
    const read = ELIPS.read;
    const nextId = ELIPS.nextId;

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
        ],
        4: [
            'Pengenalan Microsoft Word',
            'Microsoft Excel Dasar',
            'Microsoft PowerPoint',
            'Praktik Terpadu'
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
                category: 'Reguler Class',
                description: 'Kelas rombel Microsoft Word, Microsoft Excel, dan Microsoft PowerPoint untuk kebutuhan administrasi perkantoran.',
                instructorId: 2,
                instructorName: 'Ahmad Fauzi, M.M.',
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

            // Satu kursus hanya boleh diampu oleh akun ber-role instruktur.
            // Jika instruktur tidak valid, kursus dikosongkan dan dikembalikan ke Draft.
            const instructor = currentUsers.find(function (u) {
                return u.id === course.instructorId && u.role === 'instruktur';
            });
            if (instructor) {
                course.instructorName = instructor.name;
            } else {
                course.instructorId = null;
                course.instructorName = '';
                course.status = 'draft';
            }
        });
        write(KEYS.courses, currentCourses);

        if (!Array.isArray(read(KEYS.enrollments, null))) {
            write(KEYS.enrollments, seedEnrollments());
        }

        // Migrate legacy "elips_enrolled" (self enrollment) into assignments for demo student.
        const legacy = read('elips_enrolled', []);
        if (Array.isArray(legacy) && legacy.length > 0) {
            const enrollments = ELIPS.getEnrollments();
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
        const enrollments = ELIPS.getEnrollments();
        enrollments.forEach(function (e) {
            if (!e.status) e.status = 'active';
            if (!e.enrolledBy) e.enrolledBy = 'admin';
            if (!e.enrolledAt) e.enrolledAt = new Date().toISOString().split('T')[0];
        });
        write(KEYS.enrollments, enrollments);

        // Seed module & lesson structure only when empty
        if (ELIPS.getModules().length === 0 || ELIPS.getLessons().length === 0) {
            const structure = buildStructure(currentCourses, currentUsers);
            write(KEYS.modules, structure.modules);
            write(KEYS.lessons, structure.lessons);
            write(KEYS.courses, currentCourses);
        }

        if (!Array.isArray(read(KEYS.grades, null))) write(KEYS.grades, []);
        if (!Array.isArray(read(KEYS.completedLessons, null))) write(KEYS.completedLessons, []);

        // Audit trail: selalu berupa array dan setiap entri memiliki field minimum.
        if (!Array.isArray(read(KEYS.auditLogs, null))) {
            write(KEYS.auditLogs, []);
        } else {
            const auditLogs = read(KEYS.auditLogs, []);
            auditLogs.forEach(function (log) {
                if (!log.createdAt) log.createdAt = new Date().toISOString();
                if (!log.actorName) log.actorName = 'Sistem';
                if (!log.actorRole) log.actorRole = 'sistem';
                if (!log.detail) log.detail = '';
            });
            write(KEYS.auditLogs, auditLogs);
        }

        // Outbox notifikasi email (simulasi): selalu array dan field minimum lengkap.
        if (!Array.isArray(read(KEYS.notifications, null))) {
            write(KEYS.notifications, []);
        } else {
            const notifications = read(KEYS.notifications, []);
            notifications.forEach(function (item) {
                if (!item.type) item.type = 'course_published';
                if (!item.channel) item.channel = 'email';
                if (item.simulated === undefined) item.simulated = true;
                if (!item.status) item.status = 'sent';
                if (!item.subject) item.subject = '(tanpa subjek)';
                if (!item.body) item.body = '';
                if (!item.recipientName) item.recipientName = item.recipientEmail || 'Penerima';
                if (!item.recipientRole) item.recipientRole = 'peserta';
                if (!item.createdAt) item.createdAt = new Date().toISOString();
            });
            write(KEYS.notifications, notifications);
        }

        // Normalize certificates (migrate legacy ones)
        // Normalisasi ID dan status agar data lama (string ID / status kapital)
        // tetap terbaca oleh halaman peserta dan filter status.
        const certificates = ELIPS.getCertificates();
        const changed = certificates.map(function (cert) {
            cert.studentId = Number(cert.studentId);
            if (!cert.studentId) cert.studentId = 5;
            cert.courseId = Number(cert.courseId);
            if (!cert.courseId) cert.courseId = 1;
            cert.status = ELIPS.normalizeCertificateStatus(cert);
            if (!cert.grade && cert.score) cert.grade = cert.score;
            if (!cert.certificateNumber) cert.certificateNumber = 'CERT-' + new Date().getFullYear() + '-' + Math.random().toString(36).slice(2, 8).toUpperCase();
            if (cert.enrollmentId !== undefined && cert.enrollmentId !== null && cert.enrollmentId !== '') {
                cert.enrollmentId = Number(cert.enrollmentId);
            } else {
                const enr = ELIPS.getEnrollments().find(function (e) {
                    return Number(e.studentId) === cert.studentId && Number(e.courseId) === cert.courseId;
                });
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

    // Seed & migrasi dijalankan saat modul dimuat.
    normalizeData();
})(window.ELIPS);
