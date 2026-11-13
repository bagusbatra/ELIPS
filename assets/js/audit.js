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
