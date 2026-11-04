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
