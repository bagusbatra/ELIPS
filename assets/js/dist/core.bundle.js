/* ---- config.js ---- */
// ============================================================
// ELIPS LMS - config.js
// Menentukan lokasi API relatif terhadap halaman. File ini dimuat
// sebelum bundle JavaScript utama. Pada lingkungan uji JS DOM tanpa
// server, file ini tidak dieksekusi sehingga aplikasi otomatis
// berjalan dalam mode localStorage.
// ============================================================

(function (window) {
    'use strict';

    const path = (window.location.pathname || '').replace(/\\/g, '/');
    const prefix = typeof window.ELIPS_BASE === 'string' ? window.ELIPS_BASE + '/' : (/\/(admin|instructor|student|employee)\/[^/]*$/.test(path) ? '../' : '');

    window.ELIPS_CONFIG = {
        apiBase: prefix + 'api/index.php'
    };
})(window);
;
/* ---- core.js ---- */
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
        marketingCampaigns: 'elips_marketingCampaigns',
        marketingTasks: 'elips_marketingTasks',
        marketingClients: 'elips_marketingClients',
        marketingLeads: 'elips_marketingLeads',
        marketingFollowups: 'elips_marketingFollowups',
        marketingTargets: 'elips_marketingTargets',
        courseRegistrations: 'elips_courseRegistrations',
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

    const USER_DIVISIONS = ['marketing', 'operasional', 'keuangan'];

    const DIVISION_LABELS = {
        marketing: 'Marketing',
        operasional: 'Operasional',
        keuangan: 'Keuangan'
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
;
/* ---- api.js ---- */
// ============================================================
// ELIPS LMS - api.js
// Jembatan antara UI sinkron dan backend PHP/MySQL.
//
// Mode server aktif ketika halaman disajikan melalui HTTP/HTTPS dan
// window.ELIPS_CONFIG.apiBase tersedia (lihat config.js). Pada mode
// ini data dibaca dari cache snapshot yang diambil sekali saat halaman
// dimuat, lalu setiap operasi tulis memanggil API dan menyegarkan
// cache dari snapshot terbaru.
//
// Jika backend tidak tersedia (mis. file:// atau JS DOM tanpa server),
// aplikasi otomatis memakai localStorage seperti mode demo lama.
// ============================================================

(function (window) {
    'use strict';

    const ELIPS = window.ELIPS = window.ELIPS || {};
    const KEYS = ELIPS.KEYS || {};
    const originalRead = ELIPS.read;
    const originalWrite = ELIPS.write;

    const api = {
        mode: 'local',
        enabled: false,
        base: '',
        cache: {},
        session: null,
        error: null,
        call: function () {
            return { ok: false, message: 'Backend tidak aktif.', data: null };
        }
    };
    ELIPS.api = api;

    function clone(value) {
        if (value === undefined) return undefined;
        try {
            return JSON.parse(JSON.stringify(value));
        } catch (err) {
            return value;
        }
    }

    function basePrefix() {
        if (typeof window.ELIPS_BASE === 'string') return window.ELIPS_BASE + '/';
        const path = (window.location.pathname || '').replace(/\\/g, '/');
        if (/\/(admin|instructor|student|employee)\/[^/]*$/.test(path)) return '../';
        return '';
    }

    function resolveBase() {
        const config = window.ELIPS_CONFIG || null;
        if (config && config.apiBase) return config.apiBase;
        return basePrefix() + 'api/index.php';
    }

    function supportedProtocol() {
        return window.location.protocol === 'http:' || window.location.protocol === 'https:';
    }

    function applyCollections(collections, session) {
        const next = {};
        if (collections && typeof collections === 'object') {
            Object.keys(collections).forEach(function (name) {
                const key = KEYS[name] || name;
                next[key] = collections[name];
            });
        }
        if (session) next[KEYS.session] = session;
        api.cache = next;
    }

    function applyPayload(payload) {
        if (!payload || typeof payload !== 'object') return;
        if (payload.collections) {
            applyCollections(payload.collections, payload.session || null);
        } else if (payload.snapshot) {
            applyCollections(payload.snapshot, payload.session || null);
        }
        if (Object.prototype.hasOwnProperty.call(payload, 'session')) {
            api.session = payload.session || null;
            if (payload.session) api.cache[KEYS.session] = payload.session;
            else delete api.cache[KEYS.session];
        }
    }

    function request(method, action, payload) {
        let xhr;
        try {
            xhr = new XMLHttpRequest();
        } catch (err) {
            return { ok: false, message: 'XMLHttpRequest tidak tersedia.', data: null, network: true };
        }

        const separator = api.base.indexOf('?') === -1 ? '?' : '&';
        const url = api.base + (method === 'GET' ? separator + 'action=' + encodeURIComponent(action) : '');

        try {
            xhr.open(method, url, false);
            xhr.withCredentials = true;
            if (method === 'POST') {
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(JSON.stringify({ action: action, payload: payload || {} }));
            } else {
                xhr.send(null);
            }
        } catch (err) {
            return { ok: false, message: 'Tidak dapat menghubungi server.', data: null, network: true };
        }

        if (!xhr.responseText) {
            return { ok: false, message: 'Respons server kosong.', data: null, network: true };
        }
        try {
            return JSON.parse(xhr.responseText);
        } catch (err) {
            return { ok: false, message: 'Respons server tidak valid.', data: null, network: true };
        }
    }

    api.call = function (action, payload) {
        const result = request('POST', action, payload || {});
        if (result.network) {
            return { ok: false, message: result.message, data: null };
        }
        applyPayload(result);
        return {
            ok: !!result.ok,
            message: result.message || '',
            data: result.data === undefined ? null : result.data
        };
    };

    const config = window.ELIPS_CONFIG || null;
    if (!config || !config.apiBase || !supportedProtocol()) {
        api.mode = 'local';
        return;
    }

    api.base = resolveBase();
    const boot = request('GET', 'bootstrap');
    if (boot.network || !boot.ok) {
        api.mode = 'local';
        api.error = boot.message || 'Backend tidak tersedia.';
        if (window.console && console.warn) {
            console.warn('[ELIPS] Backend tidak tersedia, memakai localStorage:', api.error);
        }
        return;
    }

    api.enabled = true;
    api.mode = 'server';
    applyCollections(boot.collections || {}, boot.session || null);

    // Sesi PHP bisa habis saat browser ditutup, tetapi cookie "ingat saya"
    // masih berlaku. Pulihkan sebelum guard halaman di auth.js berjalan.
    if (!boot.session) {
        api.call('auth.resume', {});
    }

    // Getter/setter tetap sinkron: pembacaan berasal dari cache snapshot,
    // sedangkan penulisan langsung ditangani aksi API pada modul CRUD.
    ELIPS.read = function (key, fallback) {
        if (!Object.prototype.hasOwnProperty.call(api.cache, key)) return fallback;
        const value = api.cache[key];
        if (value === null || value === undefined) return fallback;
        return clone(value);
    };

    ELIPS.write = function (key, value) {
        api.cache[key] = clone(value);
        return value;
    };

    // Bantu debugging: ELIPS.api.refresh() memuat ulang seluruh snapshot.
    api.refresh = function () {
        const result = request('GET', 'bootstrap');
        if (result.ok) applyPayload(result);
        return result;
    };
})(window);
;
/* ---- utils.js ---- */
// ============================================================
// ELIPS LMS - utils.js
// Helper umum: format nilai, nomor sertifikat, escape/sanitasi HTML,
// format tanggal, dan notifikasi toast.
// ============================================================

(function (ELIPS) {
    'use strict';

    function gradeLetter(score) {
        if (typeof ELIPS.predicateLetter === 'function') {
            const letter = ELIPS.predicateLetter(score);
            if (letter) return letter;
        }
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

    // Allowlist tag & atribut untuk konten materi teks. Konten dari editor
    // instruktur tetap dianggap tidak tepercaya (stored XSS).
    const ALLOWED_TAGS = {
        p: [], br: [], strong: [], b: [], em: [], i: [], u: [], s: [],
        ul: [], ol: [], li: [], blockquote: [], code: [], pre: [],
        h1: [], h2: [], h3: [], h4: [], h5: [], h6: [],
        div: [], span: [], hr: [],
        a: ['href', 'title', 'target', 'rel'],
        img: ['src', 'alt', 'title', 'width', 'height'],
        table: [], thead: [], tbody: [], tfoot: [], tr: [],
        th: ['colspan', 'rowspan'], td: ['colspan', 'rowspan']
    };

    const DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta',
        'base', 'form', 'input', 'button', 'textarea', 'select', 'option',
        'svg', 'math', 'template', 'noscript'
    ];

    function isSafeUrl(value) {
        const normalized = String(value || '')
            .replace(/[\u0000-\u0020]+/g, '')
            .toLowerCase();
        return normalized.indexOf('javascript:') !== 0 &&
            normalized.indexOf('vbscript:') !== 0 &&
            normalized.indexOf('data:') !== 0;
    }

    function sanitizeHtml(html) {
        const source = String(html === undefined || html === null ? '' : html);

        // Lingkungan non-DOM (mis. uji data layer) tidak dapat memakai
        // allowlist; biarkan data apa adanya dan sanitasi saat render.
        if (typeof document === 'undefined' || typeof document.createElement !== 'function') return source;

        const template = document.createElement('template');
        if (!template.content) return source;
        template.innerHTML = source;

        // Buang total elemen berbahaya beserta isinya.
        DROP_TAGS.forEach(function (tag) {
            Array.prototype.slice.call(template.content.querySelectorAll(tag)).forEach(function (node) {
                node.remove();
            });
        });

        const elements = Array.prototype.slice.call(template.content.querySelectorAll('*'));

        elements.forEach(function (el) {
            const tag = el.tagName.toLowerCase();
            const allowedAttrs = ALLOWED_TAGS[tag];

            if (!allowedAttrs) {
                // Tag tidak dikenal: pertahankan isi teks/child yang aman.
                const parent = el.parentNode;
                if (!parent) return;
                while (el.firstChild) parent.insertBefore(el.firstChild, el);
                parent.removeChild(el);
                return;
            }

            Array.prototype.slice.call(el.attributes).forEach(function (attr) {
                const name = attr.name.toLowerCase();
                if (name.indexOf('on') === 0 || allowedAttrs.indexOf(name) === -1) {
                    el.removeAttribute(attr.name);
                    return;
                }
                if ((name === 'href' || name === 'src') && !isSafeUrl(attr.value)) {
                    el.removeAttribute(attr.name);
                }
            });

            if (tag === 'a' && el.getAttribute('href')) {
                el.setAttribute('rel', 'noopener noreferrer');
                const target = el.getAttribute('target');
                if (target && target !== '_blank' && target !== '_self') el.removeAttribute('target');
            }
            if (tag === 'img' && !el.getAttribute('src')) el.remove();
        });

        return template.innerHTML;
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

    ELIPS.gradeLetter = gradeLetter;
    ELIPS.generateCertificateNumber = generateCertificateNumber;
    ELIPS.escapeHtml = escapeHtml;
    ELIPS.sanitizeHtml = sanitizeHtml;
    ELIPS.formatDate = formatDate;
    ELIPS.toast = showToast;
    window.showToast = showToast;
})(window.ELIPS);
;
/* ---- data.js ---- */
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

    // Pendaftaran dari formulir publik pada halaman detail kursus.
    // Mode server: tersimpan di tabel course_registrations lewat API.
    // Mode lokal: fallback ke localStorage untuk demo tanpa database.
    function getCourseRegistrations() { return read(KEYS.courseRegistrations, []); }
    function setCourseRegistrations(list) { return write(KEYS.courseRegistrations, list); }
    function addCourseRegistration(data) {
        if (ELIPS.api && ELIPS.api.enabled) {
            return ELIPS.api.call('course.register', data || {});
        }

        const list = getCourseRegistrations();
        const entry = Object.assign({
            id: ELIPS.nextId(list),
            status: 'baru',
            createdAt: new Date().toISOString()
        }, data);
        setCourseRegistrations(list.concat(entry));
        return { ok: true, message: 'Data pendaftaran berhasil dikirim. Kami akan menghubungimu.', data: entry };
    }

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

    // ---------- Jadwal shift karyawan ----------
    function getStaffShifts() { return read(KEYS.staffShifts, []); }
    function setStaffShifts(list) { return write(KEYS.staffShifts, list); }
    function getStaffShiftsByUser(userId) {
        return getStaffShifts()
            .filter(function (shift) { return Number(shift.userId) === Number(userId); })
            .sort(function (a, b) {
                return String(a.shiftDate || '').localeCompare(String(b.shiftDate || ''));
            });
    }

    // ---------- Surat menyurat ----------
    function getLetters() { return read(KEYS.letters, []); }
    function setLetters(list) { return write(KEYS.letters, list); }
    function getLettersByDirection(direction) {
        return getLetters()
            .filter(function (letter) { return letter.direction === direction; })
            .sort(function (a, b) {
                return String(b.letterDate || '').localeCompare(String(a.letterDate || ''));
            });
    }

    // ---------- Marketing: campaign, lead, follow-up, target ----------
    function getMarketingCampaigns() { return read(KEYS.marketingCampaigns, []); }
    function setMarketingCampaigns(list) { return write(KEYS.marketingCampaigns, list); }
    function getMarketingCampaignsByOwner(ownerId) {
        return getMarketingCampaigns()
            .filter(function (campaign) { return Number(campaign.ownerId) === Number(ownerId); })
            .sort(function (a, b) {
                return String(b.startDate || b.updatedAt || b.createdAt || '')
                    .localeCompare(String(a.startDate || a.updatedAt || a.createdAt || ''));
            });
    }
    function getMarketingCampaign(id) {
        return getMarketingCampaigns().find(function (campaign) {
            return Number(campaign.id) === Number(id);
        }) || null;
    }
    function getMarketingTasks() { return read(KEYS.marketingTasks, []); }
    function setMarketingTasks(list) { return write(KEYS.marketingTasks, list); }
    function getMarketingTasksByOwner(ownerId) {
        return getMarketingTasks()
            .filter(function (task) { return Number(task.ownerId) === Number(ownerId); })
            .sort(function (a, b) {
                return String(a.dueDate || '9999-12-31').localeCompare(String(b.dueDate || '9999-12-31'))
                    || String(b.createdAt || '').localeCompare(String(a.createdAt || ''));
            });
    }
    function getMarketingTask(id) {
        return getMarketingTasks().find(function (task) {
            return Number(task.id) === Number(id);
        }) || null;
    }

    function getMarketingClients() { return read(KEYS.marketingClients, []); }
    function setMarketingClients(list) { return write(KEYS.marketingClients, list); }
    function getMarketingClientsByOwner(ownerId) {
        return getMarketingClients()
            .filter(function (client) { return Number(client.ownerId) === Number(ownerId); })
            .sort(function (a, b) {
                return String(b.updatedAt || b.createdAt || '').localeCompare(String(a.updatedAt || a.createdAt || ''));
            });
    }
    function getMarketingClient(id) {
        return getMarketingClients().find(function (client) {
            return Number(client.id) === Number(id);
        }) || null;
    }

    function getMarketingLeads() { return read(KEYS.marketingLeads, []); }
    function setMarketingLeads(list) { return write(KEYS.marketingLeads, list); }
    function getMarketingLeadsByOwner(ownerId) {
        return getMarketingLeads()
            .filter(function (lead) { return Number(lead.ownerId) === Number(ownerId); })
            .sort(function (a, b) {
                return String(b.updatedAt || b.createdAt || '').localeCompare(String(a.updatedAt || a.createdAt || ''));
            });
    }
    function getMarketingFollowups() { return read(KEYS.marketingFollowups, []); }
    function setMarketingFollowups(list) { return write(KEYS.marketingFollowups, list); }
    function getMarketingFollowupsByLead(leadId) {
        return getMarketingFollowups()
            .filter(function (followup) { return Number(followup.leadId) === Number(leadId); })
            .sort(function (a, b) {
                return String(b.followupDate || '').localeCompare(String(a.followupDate || ''));
            });
    }
    function getMarketingFollowupsByOwner(ownerId) {
        return getMarketingFollowups()
            .filter(function (followup) { return Number(followup.ownerId) === Number(ownerId); })
            .sort(function (a, b) {
                return String(b.followupDate || '').localeCompare(String(a.followupDate || ''));
            });
    }
    function getMarketingTargets() { return read(KEYS.marketingTargets, []); }
    function setMarketingTargets(list) { return write(KEYS.marketingTargets, list); }
    function getMarketingTarget(userId, period) {
        return getMarketingTargets().find(function (target) {
            return Number(target.userId) === Number(userId) && String(target.period) === String(period);
        }) || null;
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
        getCourseRegistrations: getCourseRegistrations,
        setCourseRegistrations: setCourseRegistrations,
        addCourseRegistration: addCourseRegistration,
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
        getStaffShifts: getStaffShifts,
        setStaffShifts: setStaffShifts,
        getStaffShiftsByUser: getStaffShiftsByUser,
        getLetters: getLetters,
        setLetters: setLetters,
        getLettersByDirection: getLettersByDirection,
        getMarketingCampaigns: getMarketingCampaigns,
        setMarketingCampaigns: setMarketingCampaigns,
        getMarketingCampaignsByOwner: getMarketingCampaignsByOwner,
        getMarketingCampaign: getMarketingCampaign,
        getMarketingTasks: getMarketingTasks,
        setMarketingTasks: setMarketingTasks,
        getMarketingTasksByOwner: getMarketingTasksByOwner,
        getMarketingTask: getMarketingTask,
        getMarketingClients: getMarketingClients,
        setMarketingClients: setMarketingClients,
        getMarketingClientsByOwner: getMarketingClientsByOwner,
        getMarketingClient: getMarketingClient,
        getMarketingLeads: getMarketingLeads,
        setMarketingLeads: setMarketingLeads,
        getMarketingLeadsByOwner: getMarketingLeadsByOwner,
        getMarketingFollowups: getMarketingFollowups,
        setMarketingFollowups: setMarketingFollowups,
        getMarketingFollowupsByLead: getMarketingFollowupsByLead,
        getMarketingFollowupsByOwner: getMarketingFollowupsByOwner,
        getMarketingTargets: getMarketingTargets,
        setMarketingTargets: setMarketingTargets,
        getMarketingTarget: getMarketingTarget,
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
;
/* ---- sync.js ---- */
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
;
/* ---- progress.js ---- */
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
;
/* ---- auth.js ---- */
// ============================================================
// ELIPS LMS - auth.js
// Session pengguna, navigasi berbasis role, dan guard halaman.
// ============================================================

(function (ELIPS) {
    'use strict';

    const KEYS = ELIPS.KEYS;
    const read = ELIPS.read;
    const write = ELIPS.write;

    // User aktif HANYA berasal dari sesi. Tidak ada fallback berdasarkan path
    // halaman, sehingga halaman role tidak bisa dibuka tanpa login.
    function getCurrentUser() {
        return getSessionUser();
    }

    function getSessionUser() {
        const session = read(KEYS.session, null);
        const id = session && (session.userId || session);
        if (!id) return null;
        return ELIPS.getUserById(id);
    }

    function basePathPrefix() {
        if (typeof window.ELIPS_BASE === 'string') return window.ELIPS_BASE + '/';
        const path = (window.location.pathname || '').replace(/\\/g, '/');
        if (/\/employee\/[^/]+\/[^/]*$/.test(path)) return '../../';
        if (/\/(admin|instructor|student|employee)\/[^/]*$/.test(path)) return '../';
        return '';
    }

    function dashboardPathForRole(role) {
        const prefix = basePathPrefix();
        if (role === 'admin') return prefix + 'admin/dashboard.php';
        if (role === 'instruktur') return prefix + 'instructor/dashboard.php';
        if (role === 'karyawan') return prefix + 'employee/shared/dashboard.php';
        return prefix + 'student/dashboard.php';
    }

    function requireRole(role) {
        let user = getSessionUser();
        if (!user) user = resumeSession();
        if (!user) {
            window.location.href = basePathPrefix() + 'login.php';
            return null;
        }
        if (user.role !== role) {
            window.location.href = dashboardPathForRole(user.role);
            return null;
        }
        return user;
    }

    // Cadangan bila api.js dimuat tanpa proses resume otomatis.
    function resumeSession() {
        if (!(ELIPS.api && ELIPS.api.enabled)) return null;
        const result = ELIPS.api.call('auth.resume', {});
        if (!result || !result.ok) return null;
        return getSessionUser();
    }

    function setCurrentUser(userId) {
        write(KEYS.session, { userId: Number(userId), loggedInAt: new Date().toISOString() });
        return ELIPS.getUserById(userId);
    }

    function logout() {
        if (ELIPS.api && ELIPS.api.enabled) {
            ELIPS.api.call('auth.logout', {});
            return;
        }
        localStorage.removeItem(KEYS.session);
    }

    Object.assign(ELIPS, {
        getCurrentUser: getCurrentUser,
        getSessionUser: getSessionUser,
        basePathPrefix: basePathPrefix,
        dashboardPathForRole: dashboardPathForRole,
        requireRole: requireRole,
        resumeSession: resumeSession,
        setCurrentUser: setCurrentUser,
        logout: logout
    });
})(window.ELIPS);
;
/* ---- audit.js ---- */
// ============================================================
// ELIPS LMS - audit.js
// Pencatatan jejak audit (audit trail) untuk perubahan data
// penting: kursus, nilai, sertifikat, pengguna, penugasan,
// modul, dan materi.
// ============================================================

(function (ELIPS) {
    'use strict';

    const KEYS = ELIPS.KEYS;
    const read = ELIPS.read;
    const write = ELIPS.write;

    // Batas jumlah entri agar localStorage tidak tumbuh tanpa kendali.
    const MAX_ENTRIES = 1000;

    const ACTION_LABELS = {
        create: 'Tambah',
        update: 'Ubah',
        delete: 'Hapus',
        publish: 'Publish',
        unpublish: 'Draft',
        grade: 'Nilai',
        submit: 'Ajukan Draft',
        approve: 'Setujui',
        assign: 'Penugasan',
        status: 'Ubah Status',
        attendance: 'Presensi',
        correct: 'Koreksi',
        confirm: 'Konfirmasi',
        unconfirm: 'Batal Konfirmasi',
        revision: 'Minta Revisi',
        finalize: 'Finalisasi',
        exempt: 'Pengecualian',
        predicate: 'Predikat',
        revoke: 'Cabut Perangkat'
    };

    const ENTITY_LABELS = {
        course: 'Kursus',
        branch: 'Cabang',
        grade: 'Nilai',
        certificate: 'Sertifikat',
        user: 'Pengguna',
        enrollment: 'Penugasan',
        module: 'Modul',
        lesson: 'Materi',
        session: 'Sesi Pertemuan',
        attendance: 'Presensi',
        material_settings: 'Pengaturan Akses Materi',
        class_material: 'Materi Tambahan Kelas',
        class_lesson_progress: 'Progres Pelajaran',
        class_material_progress: 'Progres Materi Tambahan',
        assignment: 'Tugas',
        submission: 'Pengumpulan Tugas',
        exam: 'Ujian',
        exam_participant: 'Peserta Ujian',
        exam_result: 'Hasil Ujian',
        staff_attendance: 'Absensi Staf',
        staff_shift: 'Shift Karyawan',
        letter: 'Surat Menyurat',
        marketing_campaign: 'Campaign Marketing',
        marketing_task: 'Task Marketing',
        marketing_lead: 'Lead Marketing',
        marketing_followup: 'Follow-up Marketing',
        marketing_target: 'Target Marketing',
        predicate: 'Rentang Predikat',
        grade_scheme: 'Skema Penilaian',
        grade_component: 'Komponen Nilai',
        user_device: 'Perangkat Pengguna'
    };

    function actionLabel(action) {
        return ACTION_LABELS[action] || action || '-';
    }

    function entityLabel(entity) {
        return ENTITY_LABELS[entity] || entity || '-';
    }

    function rawLogs() {
        const logs = read(KEYS.auditLogs, []);
        return Array.isArray(logs) ? logs : [];
    }

    function saveLogs(logs) {
        return write(KEYS.auditLogs, logs);
    }

    // Prioritas aktor: id eksplisit dari pemanggil, lalu user sesi,
    // lalu "Sistem". Jangan memakai getCurrentUser() karena fungsi itu
    // memiliki fallback berdasarkan path halaman dan bisa salah atribusi.
    function resolveActor(actorId) {
        if (actorId !== undefined && actorId !== null && actorId !== '') {
            const explicit = ELIPS.getUserById(actorId);
            if (explicit) return explicit;
        }
        const session = typeof ELIPS.getSessionUser === 'function' ? ELIPS.getSessionUser() : null;
        return session || null;
    }

    function logAudit(entry) {
        // Pada mode server, audit dicatat oleh backend agar tidak ada
        // duplikasi dan aktor selalu berasal dari sesi server.
        if (ELIPS.api && ELIPS.api.enabled) return null;
        try {
            entry = entry || {};
            if (!entry.action || !entry.entity) return null;

            const actor = resolveActor(entry.actorId);
            const logs = rawLogs();
            const record = {
                id: ELIPS.nextId(logs),
                action: String(entry.action),
                entity: String(entry.entity),
                entityId: entry.entityId === undefined || entry.entityId === null ? null : Number(entry.entityId),
                entityLabel: String(entry.entityLabel || ''),
                actorId: actor ? actor.id : null,
                actorName: actor ? actor.name : 'Sistem',
                actorRole: actor ? actor.role : 'sistem',
                detail: String(entry.detail || ''),
                createdAt: new Date().toISOString()
            };

            logs.unshift(record);
            if (logs.length > MAX_ENTRIES) logs.length = MAX_ENTRIES;
            saveLogs(logs);
            return record;
        } catch (err) {
            // Audit tidak boleh menggagalkan operasi bisnis utama.
            console.warn('[ELIPS] Gagal mencatat audit log', err);
            return null;
        }
    }

    function matchesDate(log, from, to) {
        if (!from && !to) return true;
        const time = new Date(log.createdAt).getTime();
        if (isNaN(time)) return false;
        if (from) {
            const start = new Date(from + 'T00:00:00').getTime();
            if (!isNaN(start) && time < start) return false;
        }
        if (to) {
            const end = new Date(to + 'T23:59:59.999').getTime();
            if (!isNaN(end) && time > end) return false;
        }
        return true;
    }

    function getAuditLogs(filters) {
        filters = filters || {};
        let logs = rawLogs().slice().sort(function (a, b) {
            return new Date(b.createdAt || 0).getTime() - new Date(a.createdAt || 0).getTime();
        });

        if (filters.action) {
            logs = logs.filter(function (log) { return log.action === filters.action; });
        }
        if (filters.entity) {
            logs = logs.filter(function (log) { return log.entity === filters.entity; });
        }
        if (filters.actor) {
            const actorFilter = String(filters.actor).toLowerCase();
            if (actorFilter === 'system') {
                logs = logs.filter(function (log) { return log.actorId === null || log.actorId === undefined; });
            } else {
                logs = logs.filter(function (log) {
                    return String(log.actorId) === actorFilter ||
                        String(log.actorName || '').toLowerCase().indexOf(actorFilter) !== -1;
                });
            }
        }
        if (filters.from || filters.to) {
            logs = logs.filter(function (log) { return matchesDate(log, filters.from, filters.to); });
        }
        if (filters.search) {
            const needle = String(filters.search).toLowerCase();
            logs = logs.filter(function (log) {
                const haystack = [
                    log.actorName,
                    log.actorRole,
                    log.action,
                    actionLabel(log.action),
                    log.entity,
                    entityLabel(log.entity),
                    log.entityLabel,
                    log.detail,
                    log.entityId
                ].join(' ').toLowerCase();
                return haystack.indexOf(needle) !== -1;
            });
        }

        const limit = Number(filters.limit);
        if (Number.isFinite(limit) && limit > 0) return logs.slice(0, limit);
        return logs;
    }

    function getAuditActors() {
        const map = {};
        rawLogs().forEach(function (log) {
            const key = log.actorId === null || log.actorId === undefined ? 'system' : String(log.actorId);
            if (!map[key]) {
                map[key] = {
                    id: key === 'system' ? null : Number(log.actorId),
                    name: log.actorName || 'Sistem',
                    role: log.actorRole || 'sistem'
                };
            }
        });
        return Object.keys(map).map(function (key) { return map[key]; }).sort(function (a, b) {
            return String(a.name).localeCompare(String(b.name));
        });
    }

    function clearAuditLogs() {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('audit.clear', {});
        saveLogs([]);
        return ELIPS._.success('Seluruh audit trail berhasil dibersihkan.');
    }

    ELIPS.AUDIT_ACTION_LABELS = ACTION_LABELS;
    ELIPS.AUDIT_ENTITY_LABELS = ENTITY_LABELS;
    ELIPS.auditActionLabel = actionLabel;
    ELIPS.auditEntityLabel = entityLabel;
    ELIPS.logAudit = logAudit;
    ELIPS.getAuditLogs = getAuditLogs;
    ELIPS.getAuditActors = getAuditActors;
    ELIPS.clearAuditLogs = clearAuditLogs;
})(window.ELIPS);
;
/* ---- notifications.js ---- */
// ============================================================
// ELIPS LMS - notifications.js
// Simulasi notifikasi email: outbox tersimpan di localStorage.
// Dicatat saat kursus dipublikasikan atau sertifikat disetujui.
// ============================================================

(function (ELIPS) {
    'use strict';

    const KEYS = ELIPS.KEYS;
    const read = ELIPS.read;
    const write = ELIPS.write;

    // Batas jumlah entri agar localStorage tidak tumbuh tanpa kendali.
    const MAX_ENTRIES = 1000;

    const TYPE_LABELS = {
        course_published: 'Kursus Dipublikasikan',
        certificate_published: 'Sertifikat Terbit'
    };

    function typeLabel(type) {
        return TYPE_LABELS[type] || type || '-';
    }

    function rawNotifications() {
        const list = read(KEYS.notifications, []);
        return Array.isArray(list) ? list : [];
    }

    function saveNotifications(list) {
        return write(KEYS.notifications, list);
    }

    function canReceive(user) {
        return !!user &&
            user.status !== 'nonaktif' &&
            ELIPS._.isValidEmail(user.email);
    }

    function buildRecord(entry) {
        return {
            id: 0,
            type: entry.type,
            channel: 'email',
            simulated: true,
            status: 'sent',
            recipientId: entry.recipientId === undefined || entry.recipientId === null ? null : Number(entry.recipientId),
            recipientName: entry.recipientName || '',
            recipientEmail: entry.recipientEmail || '',
            recipientRole: entry.recipientRole || 'peserta',
            subject: entry.subject || '',
            body: entry.body || '',
            entity: entry.entity || '',
            entityId: entry.entityId === undefined || entry.entityId === null ? null : Number(entry.entityId),
            entityLabel: entry.entityLabel || '',
            createdAt: new Date().toISOString()
        };
    }

    function createNotification(entry) {
        // Notifikasi pada mode server dibuat oleh backend saat aksi terkait
        // dijalankan (publikasi kursus / persetujuan sertifikat).
        if (ELIPS.api && ELIPS.api.enabled) return null;
        try {
            entry = entry || {};
            if (!entry.type || !entry.recipientEmail) return null;

            const list = rawNotifications();
            const record = buildRecord(entry);
            record.id = ELIPS.nextId(list);
            list.unshift(record);
            if (list.length > MAX_ENTRIES) list.length = MAX_ENTRIES;
            saveNotifications(list);
            return record;
        } catch (err) {
            // Notifikasi tidak boleh menggagalkan operasi bisnis utama.
            console.warn('[ELIPS] Gagal membuat notifikasi', err);
            return null;
        }
    }

    function notifyCoursePublished(courseRef) {
        if (ELIPS.api && ELIPS.api.enabled) return [];
        try {
            const course = courseRef && typeof courseRef === 'object'
                ? courseRef
                : ELIPS.getCourseById(courseRef);
            if (!course) return [];

            const recipients = [];
            const seen = {};

            function add(user) {
                if (!canReceive(user) || seen[user.id]) return;
                seen[user.id] = true;
                recipients.push(user);
            }

            ELIPS.getEnrollmentsByCourse(course.id).forEach(function (enrollment) {
                if (enrollment.status && enrollment.status !== 'active') return;
                const student = ELIPS.getUserById(enrollment.studentId);
                if (student && student.role === 'peserta') add(student);
            });

            const instructor = ELIPS.getUserById(course.instructorId);
            if (instructor && instructor.role === 'instruktur') add(instructor);

            return recipients.map(function (user) {
                const isInstructor = user.role === 'instruktur';
                const subject = 'Kursus "' + course.title + '" telah dipublikasikan';
                const body = 'Halo ' + user.name + ',\n\n' +
                    (isInstructor
                        ? 'Kursus "' + course.title + '" yang Anda ampu telah dipublikasikan dan dapat diakses oleh peserta.'
                        : 'Kursus "' + course.title + '" yang Anda ikuti telah dipublikasikan dan dapat diakses sekarang.') +
                    '\n\nSilakan masuk ke ELIPS LMS untuk melihat materi dan melanjutkan pembelajaran.' +
                    '\n\nSalam,\nELIPS LMS';

                return createNotification({
                    type: 'course_published',
                    recipientId: user.id,
                    recipientName: user.name,
                    recipientEmail: user.email,
                    recipientRole: user.role,
                    subject: subject,
                    body: body,
                    entity: 'course',
                    entityId: course.id,
                    entityLabel: course.title
                });
            }).filter(Boolean);
        } catch (err) {
            console.warn('[ELIPS] Gagal membuat notifikasi publikasi kursus', err);
            return [];
        }
    }

    function notifyCertificatePublished(certificateRef) {
        if (ELIPS.api && ELIPS.api.enabled) return [];
        try {
            const certificate = certificateRef && typeof certificateRef === 'object'
                ? certificateRef
                : ELIPS.getCertificates().find(function (cert) {
                    return cert.id === Number(certificateRef);
                });
            if (!certificate) return [];

            const student = ELIPS.getUserById(certificate.studentId);
            if (!canReceive(student)) return [];

            const course = ELIPS.getCourseById(certificate.courseId);
            const courseTitle = course ? course.title : 'Kursus #' + certificate.courseId;
            const scoreText = certificate.score === undefined || certificate.score === null
                ? ''
                : ' dengan nilai ' + certificate.score +
                    (certificate.grade ? ' (' + certificate.grade + ')' : '');
            const subject = 'E-sertifikat "' + courseTitle + '" telah terbit';
            const body = 'Halo ' + student.name + ',\n\n' +
                'Selamat! E-sertifikat Anda untuk kursus "' + courseTitle + '" telah disetujui Admin dan resmi terbit' +
                scoreText + '.' +
                '\n\nNomor sertifikat: ' + (certificate.certificateNumber || '-') +
                '\n\nSertifikat dapat dilihat dan diverifikasi melalui halaman ELIPS LMS.' +
                '\n\nSalam,\nELIPS LMS';

            return [createNotification({
                type: 'certificate_published',
                recipientId: student.id,
                recipientName: student.name,
                recipientEmail: student.email,
                recipientRole: student.role,
                subject: subject,
                body: body,
                entity: 'certificate',
                entityId: certificate.id,
                entityLabel: certificate.certificateNumber || courseTitle
            })].filter(Boolean);
        } catch (err) {
            console.warn('[ELIPS] Gagal membuat notifikasi penerbitan sertifikat', err);
            return [];
        }
    }

    function matchesDate(item, from, to) {
        if (!from && !to) return true;
        const time = new Date(item.createdAt).getTime();
        if (isNaN(time)) return false;
        if (from) {
            const start = new Date(from + 'T00:00:00').getTime();
            if (!isNaN(start) && time < start) return false;
        }
        if (to) {
            const end = new Date(to + 'T23:59:59.999').getTime();
            if (!isNaN(end) && time > end) return false;
        }
        return true;
    }

    function getNotifications(filters) {
        filters = filters || {};
        let list = rawNotifications().slice().sort(function (a, b) {
            return new Date(b.createdAt || 0).getTime() - new Date(a.createdAt || 0).getTime();
        });

        if (filters.type) {
            list = list.filter(function (item) { return item.type === filters.type; });
        }
        if (filters.recipient) {
            const needle = String(filters.recipient).toLowerCase();
            list = list.filter(function (item) {
                return String(item.recipientId) === needle ||
                    String(item.recipientEmail || '').toLowerCase().indexOf(needle) !== -1 ||
                    String(item.recipientName || '').toLowerCase().indexOf(needle) !== -1;
            });
        }
        if (filters.from || filters.to) {
            list = list.filter(function (item) { return matchesDate(item, filters.from, filters.to); });
        }
        if (filters.search) {
            const needle = String(filters.search).toLowerCase();
            list = list.filter(function (item) {
                const haystack = [
                    item.recipientName,
                    item.recipientEmail,
                    item.recipientRole,
                    item.type,
                    typeLabel(item.type),
                    item.subject,
                    item.body,
                    item.entityLabel,
                    item.entityId
                ].join(' ').toLowerCase();
                return haystack.indexOf(needle) !== -1;
            });
        }

        const limit = Number(filters.limit);
        if (Number.isFinite(limit) && limit > 0) return list.slice(0, limit);
        return list;
    }

    function getNotificationRecipients() {
        const map = {};
        rawNotifications().forEach(function (item) {
            const key = item.recipientId === null || item.recipientId === undefined
                ? String(item.recipientEmail || '')
                : String(item.recipientId);
            if (!key || map[key]) return;
            map[key] = {
                id: item.recipientId === null || item.recipientId === undefined ? null : Number(item.recipientId),
                name: item.recipientName || item.recipientEmail || 'Penerima',
                email: item.recipientEmail || '',
                role: item.recipientRole || 'peserta'
            };
        });
        return Object.keys(map).map(function (key) { return map[key]; }).sort(function (a, b) {
            return String(a.name).localeCompare(String(b.name));
        });
    }

    function clearNotifications() {
        if (ELIPS.api && ELIPS.api.enabled) return ELIPS.api.call('notification.clear', {});
        saveNotifications([]);
        return ELIPS._.success('Seluruh outbox notifikasi berhasil dibersihkan.');
    }

    ELIPS.NOTIFICATION_TYPE_LABELS = TYPE_LABELS;
    ELIPS.notificationTypeLabel = typeLabel;
    ELIPS.createNotification = createNotification;
    ELIPS.notifyCoursePublished = notifyCoursePublished;
    ELIPS.notifyCertificatePublished = notifyCertificatePublished;
    ELIPS.getNotifications = getNotifications;
    ELIPS.getNotificationRecipients = getNotificationRecipients;
    ELIPS.clearNotifications = clearNotifications;
})(window.ELIPS);
;
