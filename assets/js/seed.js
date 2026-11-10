// ============================================================
// ELIPS LMS - seed.js
// Data demo awal dan normalisasi data lama (migrasi).
// Dijalankan sekali saat modul dimuat.
// ============================================================

(function (ELIPS) {
    'use strict';

    // Mode server: data demo dan migrasi sudah ditangani database, jadi
    // seed tidak boleh menimpa snapshot yang dimuat dari backend.
    if (ELIPS.api && ELIPS.api.enabled) return;

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
            { id: 1, name: 'Administrator', email: 'admin@example.com', password: ELIPS.DEFAULT_PASSWORD, role: 'admin', phone: '081234567890', status: 'aktif', createdAt: '2026-01-01' },
            { id: 2, name: 'Ahmad Fauzi, M.M.', email: 'ahmad@example.com', password: ELIPS.DEFAULT_PASSWORD, role: 'instruktur', phone: '081234567891', status: 'aktif', createdAt: '2026-09-15' },
            { id: 3, name: 'Siti Nurhaliza, S.Psi.', email: 'siti@example.com', password: ELIPS.DEFAULT_PASSWORD, role: 'instruktur', phone: '081234567892', status: 'aktif', createdAt: '2026-09-16' },
            { id: 4, name: 'Budi Santoso, A.Md.', email: 'budi@example.com', password: ELIPS.DEFAULT_PASSWORD, role: 'instruktur', phone: '081234567893', status: 'aktif', createdAt: '2026-09-17' },
            { id: 5, name: 'Budi Santoso', email: 'budi.student@example.com', password: ELIPS.DEFAULT_PASSWORD, role: 'peserta', phone: '081234567894', status: 'aktif', createdAt: '2026-10-01' },
            { id: 6, name: 'Ani Nurhayati', email: 'ani@example.com', password: ELIPS.DEFAULT_PASSWORD, role: 'peserta', phone: '081234567895', status: 'aktif', createdAt: '2026-10-02' },
            { id: 7, name: 'Rina Setiawati', email: 'rina@example.com', password: ELIPS.DEFAULT_PASSWORD, role: 'peserta', phone: '081234567896', status: 'aktif', createdAt: '2026-10-03' }
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

    function seedBranches() {
        return [
            { id: 1, code: 'CBG-JKT', name: 'Cabang Jakarta', address: 'Jl. Jenderal Sudirman No. 21', city: 'Jakarta', phone: '021-5550101', manager: 'Dewi Lestari', status: 'active', notes: 'Kantor pusat dan kelas reguler wilayah Jabodetabek.' },
            { id: 2, code: 'CBG-BDG', name: 'Cabang Bandung', address: 'Jl. Asia Afrika No. 88', city: 'Bandung', phone: '022-5550202', manager: 'Rangga Pratama', status: 'active', notes: 'Cabang wilayah Bandung dan sekitarnya.' },
            { id: 3, code: 'CBG-SBY', name: 'Cabang Surabaya', address: 'Jl. Basuki Rahmat No. 45', city: 'Surabaya', phone: '031-5550303', manager: 'Maya Kusuma', status: 'active', notes: 'Cabang wilayah Jawa Timur.' }
        ];
    }

    function seedEnrollments() {
        return [
            { id: 1, studentId: 5, courseId: 1, classId: 1, enrolledBy: 'admin', enrolledAt: '2026-10-01', status: 'active' },
            { id: 2, studentId: 6, courseId: 1, classId: 1, enrolledBy: 'admin', enrolledAt: '2026-10-05', status: 'active' },
            { id: 3, studentId: 7, courseId: 1, classId: 1, enrolledBy: 'admin', enrolledAt: '2026-10-10', status: 'active' }
        ];
    }

    function seedAttendances() {
        return [
            { id: 1, sessionId: 1, enrollmentId: 1, studentId: 5, studentName: 'Budi Santoso', status: 'present', recordedBy: 2, recordedByName: 'Ahmad Fauzi, M.M.', recordedAt: '2026-10-05T11:05:00.000Z', note: '', correctedAt: null, correctionReason: '', createdAt: '2026-10-05T11:05:00.000Z', updatedAt: null },
            { id: 2, sessionId: 1, enrollmentId: 2, studentId: 6, studentName: 'Ani Nurhayati', status: 'present', recordedBy: 2, recordedByName: 'Ahmad Fauzi, M.M.', recordedAt: '2026-10-05T11:05:00.000Z', note: '', correctedAt: null, correctionReason: '', createdAt: '2026-10-05T11:05:00.000Z', updatedAt: null },
            { id: 3, sessionId: 1, enrollmentId: 3, studentId: 7, studentName: 'Rina Setiawati', status: 'late', recordedBy: 2, recordedByName: 'Ahmad Fauzi, M.M.', recordedAt: '2026-10-05T11:05:00.000Z', note: 'Datang 15 menit setelah sesi dimulai.', correctedAt: null, correctionReason: '', createdAt: '2026-10-05T11:05:00.000Z', updatedAt: null },
            { id: 4, sessionId: 2, enrollmentId: 1, studentId: 5, studentName: 'Budi Santoso', status: 'present', recordedBy: 2, recordedByName: 'Ahmad Fauzi, M.M.', recordedAt: '2026-10-07T11:05:00.000Z', note: '', correctedAt: null, correctionReason: '', createdAt: '2026-10-07T11:05:00.000Z', updatedAt: null },
            { id: 5, sessionId: 2, enrollmentId: 2, studentId: 6, studentName: 'Ani Nurhayati', status: 'permission', recordedBy: 2, recordedByName: 'Ahmad Fauzi, M.M.', recordedAt: '2026-10-07T11:05:00.000Z', note: 'Izin keperluan keluarga.', correctedAt: null, correctionReason: '', createdAt: '2026-10-07T11:05:00.000Z', updatedAt: null },
            { id: 6, sessionId: 2, enrollmentId: 3, studentId: 7, studentName: 'Rina Setiawati', status: 'present', recordedBy: 2, recordedByName: 'Ahmad Fauzi, M.M.', recordedAt: '2026-10-07T11:05:00.000Z', note: '', correctedAt: null, correctionReason: '', createdAt: '2026-10-07T11:05:00.000Z', updatedAt: null },
            { id: 7, sessionId: 3, enrollmentId: 1, studentId: 5, studentName: 'Budi Santoso', status: 'late', recordedBy: 2, recordedByName: 'Ahmad Fauzi, M.M.', recordedAt: '2026-10-12T11:05:00.000Z', note: 'Terlambat 10 menit.', correctedAt: null, correctionReason: '', createdAt: '2026-10-12T11:05:00.000Z', updatedAt: null },
            { id: 8, sessionId: 3, enrollmentId: 2, studentId: 6, studentName: 'Ani Nurhayati', status: 'present', recordedBy: 2, recordedByName: 'Ahmad Fauzi, M.M.', recordedAt: '2026-10-12T11:05:00.000Z', note: '', correctedAt: null, correctionReason: '', createdAt: '2026-10-12T11:05:00.000Z', updatedAt: null },
            { id: 9, sessionId: 3, enrollmentId: 3, studentId: 7, studentName: 'Rina Setiawati', status: 'absent', recordedBy: 2, recordedByName: 'Ahmad Fauzi, M.M.', recordedAt: '2026-10-12T11:05:00.000Z', note: 'Tidak hadir tanpa keterangan.', correctedAt: null, correctionReason: '', createdAt: '2026-10-12T11:05:00.000Z', updatedAt: null }
        ];
    }

    function seedClassSessions() {
        const base = {
            classId: 1,
            classCode: 'ANG-2026-MNJ-01',
            className: 'Angkatan 2026 — Pelatihan Manajemen Dasar',
            courseId: 1,
            courseTitle: 'Pelatihan Manajemen Dasar',
            branchId: 1,
            branchCode: 'CBG-JKT',
            branchName: 'Cabang Jakarta',
            branchCity: 'Jakarta',
            location: 'Kampus Pusat',
            room: 'R-101',
            instructorId: 2,
            instructorName: 'Ahmad Fauzi, M.M.',
            startTime: '09:00',
            endTime: '11:00',
            plannedJp: 4,
            rescheduledFromId: null,
            createdBy: 1,
            updatedBy: 1
        };
        return [
            Object.assign({}, base, {
                id: 1, sessionNumber: 1, title: 'Pertemuan 1: Pengantar Manajemen', moduleId: 1,
                moduleTitle: 'Modul 1: Pengantar Manajemen', sessionDate: '2026-10-05', actualJp: 4,
                status: 'completed', notes: 'Sesi pembuka dan kontrak belajar.',
                presentCount: 2, lateCount: 1, permissionCount: 0, sickCount: 0, absentCount: 0,
                excusedCount: 0, attendanceCount: 3, createdAt: '2026-10-05T09:00:00.000Z'
            }),
            Object.assign({}, base, {
                id: 2, sessionNumber: 2, title: 'Pertemuan 2: Perencanaan (Planning)', moduleId: 2,
                moduleTitle: 'Modul 2: Perencanaan (Planning)', sessionDate: '2026-10-07', actualJp: 4,
                status: 'completed', notes: 'Latihan menyusun rencana kerja sederhana.',
                presentCount: 2, lateCount: 0, permissionCount: 1, sickCount: 0, absentCount: 0,
                excusedCount: 0, attendanceCount: 3, createdAt: '2026-10-07T09:00:00.000Z'
            }),
            Object.assign({}, base, {
                id: 3, sessionNumber: 3, title: 'Pertemuan 3: Pengorganisasian (Organizing)', moduleId: 3,
                moduleTitle: 'Modul 3: Pengorganisasian (Organizing)', sessionDate: '2026-10-12', actualJp: 4,
                status: 'completed', notes: 'Diskusi struktur organisasi dan pembagian tugas.',
                presentCount: 1, lateCount: 1, permissionCount: 0, sickCount: 0, absentCount: 1,
                excusedCount: 0, attendanceCount: 3, createdAt: '2026-10-12T09:00:00.000Z'
            }),
            Object.assign({}, base, {
                id: 4, sessionNumber: 4, title: 'Pertemuan 4: Kepemimpinan (Leading)', moduleId: 4,
                moduleTitle: 'Modul 4: Kepemimpinan (Leading)', sessionDate: '2026-10-14', actualJp: 0,
                status: 'scheduled', notes: 'Sesi berikutnya, presensi belum diisi.',
                presentCount: 0, lateCount: 0, permissionCount: 0, sickCount: 0, absentCount: 0,
                excusedCount: 0, attendanceCount: 0, createdAt: '2026-10-14T09:00:00.000Z'
            })
        ];
    }

    function seedAssignments() {
        return [
            {
                id: 1, classId: 1, classCode: 'ANG-2026-MNJ-01',
                className: 'Angkatan 2026 — Pelatihan Manajemen Dasar',
                courseId: 1, courseTitle: 'Pelatihan Manajemen Dasar',
                curriculumVersionId: 1, moduleId: 1, moduleTitle: 'Modul 1: Pengantar Manajemen',
                lessonId: 1, lessonTitle: '1.1 Pengantar Manajemen - Konsep Dasar',
                title: 'Tugas 1: Analisis Fungsi Manajemen',
                instructions: 'Pilih satu organisasi yang Anda kenal, lalu analisis bagaimana fungsi perencanaan, pengorganisasian, kepemimpinan, dan pengendalian diterapkan di organisasi tersebut. Tulis dalam 400-600 kata.',
                type: 'text_submission', openAt: '2026-10-05T09:00:00.000Z', dueAt: '2026-10-12T23:59:59.000Z',
                maxScore: 100, weight: 20, requireFile: false, allowedTypes: '', maxFileSize: null,
                status: 'published', createdBy: 2, creatorName: 'Ahmad Fauzi, M.M.', updatedBy: 2,
                createdAt: '2026-10-05T09:00:00.000Z', updatedAt: '2026-10-05T09:00:00.000Z'
            },
            {
                id: 2, classId: 1, classCode: 'ANG-2026-MNJ-01',
                className: 'Angkatan 2026 — Pelatihan Manajemen Dasar',
                courseId: 1, courseTitle: 'Pelatihan Manajemen Dasar',
                curriculumVersionId: 1, moduleId: 2, moduleTitle: 'Modul 2: Perencanaan (Planning)',
                lessonId: 4, lessonTitle: '2.1 Perencanaan (Planning) - Konsep Dasar',
                title: 'Tugas 2: Rencana Kerja Sederhana',
                instructions: 'Susun rencana kerja sederhana untuk satu kegiatan di tempat kerja Anda. Gunakan format 5W1H dan unggah dokumen dalam format PDF atau DOCX.',
                type: 'file_upload', openAt: '2026-10-12T09:00:00.000Z', dueAt: '2026-10-26T23:59:59.000Z',
                maxScore: 100, weight: 20, requireFile: true, allowedTypes: 'pdf,doc,docx', maxFileSize: 5242880,
                status: 'published', createdBy: 2, creatorName: 'Ahmad Fauzi, M.M.', updatedBy: 2,
                createdAt: '2026-10-12T09:00:00.000Z', updatedAt: '2026-10-12T09:00:00.000Z'
            }
        ];
    }

    function seedAssignmentRubrics() {
        return [
            { id: 1, assignmentId: 1, criterion: 'Kejelasan analisis', description: 'Analisis fungsi manajemen dijelaskan dengan contoh konkret.', maxScore: 40, order: 1, createdAt: '2026-10-05T09:00:00.000Z', updatedAt: null },
            { id: 2, assignmentId: 1, criterion: 'Keterkaitan teori', description: 'Mengaitkan praktik organisasi dengan konsep manajemen yang dipelajari.', maxScore: 30, order: 2, createdAt: '2026-10-05T09:00:00.000Z', updatedAt: null },
            { id: 3, assignmentId: 1, criterion: 'Kerapian penulisan', description: 'Struktur tulisan runtut, ejaan benar, dan panjang sesuai ketentuan.', maxScore: 30, order: 3, createdAt: '2026-10-05T09:00:00.000Z', updatedAt: null }
        ];
    }

    function seedSubmissions() {
        return [
            {
                id: 1, assignmentId: 1, assignmentTitle: 'Tugas 1: Analisis Fungsi Manajemen',
                classId: 1, enrollmentId: 1, studentId: 5, studentName: 'Budi Santoso',
                assignmentType: 'text_submission', maxScore: 100, status: 'reviewed',
                submittedAt: '2026-10-10T20:15:00.000Z', isLate: false, score: 85,
                reviewedBy: 2, reviewerName: 'Ahmad Fauzi, M.M.', reviewedAt: '2026-10-13T10:00:00.000Z',
                revisionCount: 0,
                textAnswer: 'Organisasi yang saya amati adalah kantor tempat saya bekerja. Fungsi perencanaan terlihat dari penyusunan target bulanan. Pengorganisasian dilakukan dengan pembagian tugas per divisi. Kepemimpinan tampak pada arahan harian kepala divisi. Pengendalian dilakukan melalui evaluasi mingguan.',
                feedback: 'Analisis sudah baik dan contohnya relevan. Tambahkan data pendukung pada bagian pengendalian agar lebih kuat.',
                createdAt: '2026-10-10T20:15:00.000Z', updatedAt: '2026-10-13T10:00:00.000Z'
            }
        ];
    }

    function seedSubmissionReviews() {
        return [
            { id: 1, submissionId: 1, reviewerId: 5, reviewerName: 'Budi Santoso', action: 'submit', score: null, note: 'Mengumpulkan tugas.', createdAt: '2026-10-10T20:15:00.000Z' },
            { id: 2, submissionId: 1, reviewerId: 2, reviewerName: 'Ahmad Fauzi, M.M.', action: 'score', score: 85, note: 'Analisis sudah baik dan contohnya relevan. Tambahkan data pendukung pada bagian pengendalian agar lebih kuat.', createdAt: '2026-10-13T10:00:00.000Z' }
        ];
    }

    function seedExams() {
        return [
            {
                id: 1, classId: 1, classCode: 'ANG-2026-MNJ-01',
                className: 'Angkatan 2026 — Pelatihan Manajemen Dasar',
                courseId: 1, courseTitle: 'Pelatihan Manajemen Dasar', curriculumVersionId: 1,
                title: 'Ujian Tengah Semester: Dasar Manajemen',
                description: 'Evaluasi pemahaman konsep dasar manajemen, perencanaan, dan pengorganisasian.',
                type: 'written', examDate: '2026-10-20', startTime: '09:00', durationMinutes: 90,
                maxScore: 100, weight: 30,
                instructions: 'Kerjakan 30 soal pilihan ganda dan 2 soal esai. Buku tertutup.',
                status: 'published', createdBy: 2, creatorName: 'Ahmad Fauzi, M.M.', updatedBy: 2,
                createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-01T08:00:00.000Z'
            },
            {
                id: 2, classId: 1, classCode: 'ANG-2026-MNJ-01',
                className: 'Angkatan 2026 — Pelatihan Manajemen Dasar',
                courseId: 1, courseTitle: 'Pelatihan Manajemen Dasar', curriculumVersionId: 1,
                title: 'Ujian Praktik: Studi Kasus Manajemen',
                description: 'Peserta menyelesaikan studi kasus dan mempresentasikan solusinya.',
                type: 'practical', examDate: '2026-12-15', startTime: '09:00', durationMinutes: 120,
                maxScore: 100, weight: 30,
                instructions: 'Presentasikan solusi studi kasus dalam 10 menit dan jawab pertanyaan penguji.',
                status: 'draft', createdBy: 2, creatorName: 'Ahmad Fauzi, M.M.', updatedBy: 2,
                createdAt: '2026-10-15T08:00:00.000Z', updatedAt: '2026-10-15T08:00:00.000Z'
            }
        ];
    }

    function seedExamParticipants() {
        return [
            { id: 1, examId: 1, enrollmentId: 1, studentId: 5, studentName: 'Budi Santoso', status: 'attended', note: '', createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-20T11:00:00.000Z' },
            { id: 2, examId: 1, enrollmentId: 2, studentId: 6, studentName: 'Ani Nurhayati', status: 'attended', note: '', createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-20T11:00:00.000Z' },
            { id: 3, examId: 1, enrollmentId: 3, studentId: 7, studentName: 'Rina Setiawati', status: 'absent', note: 'Tidak hadir tanpa keterangan.', createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-20T11:00:00.000Z' },
            { id: 4, examId: 2, enrollmentId: 1, studentId: 5, studentName: 'Budi Santoso', status: 'registered', note: '', createdAt: '2026-10-15T08:00:00.000Z', updatedAt: '2026-10-15T08:00:00.000Z' },
            { id: 5, examId: 2, enrollmentId: 2, studentId: 6, studentName: 'Ani Nurhayati', status: 'registered', note: '', createdAt: '2026-10-15T08:00:00.000Z', updatedAt: '2026-10-15T08:00:00.000Z' },
            { id: 6, examId: 2, enrollmentId: 3, studentId: 7, studentName: 'Rina Setiawati', status: 'registered', note: '', createdAt: '2026-10-15T08:00:00.000Z', updatedAt: '2026-10-15T08:00:00.000Z' }
        ];
    }

    function seedExamResults() {
        return [
            { id: 1, examId: 1, enrollmentId: 1, studentId: 5, studentName: 'Budi Santoso', score: 88, status: 'graded', gradedBy: 2, gradedByName: 'Ahmad Fauzi, M.M.', gradedAt: '2026-10-25T10:00:00.000Z', note: 'Pemahaman konsep baik.', createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-25T10:00:00.000Z' },
            { id: 2, examId: 1, enrollmentId: 2, studentId: 6, studentName: 'Ani Nurhayati', score: 80, status: 'graded', gradedBy: 2, gradedByName: 'Ahmad Fauzi, M.M.', gradedAt: '2026-10-25T10:05:00.000Z', note: 'Perlu memperdalam bagian pengendalian.', createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-25T10:05:00.000Z' },
            { id: 3, examId: 1, enrollmentId: 3, studentId: 7, studentName: 'Rina Setiawati', score: null, status: 'missing', gradedBy: null, gradedByName: '', gradedAt: null, note: 'Tidak hadir tanpa keterangan.', createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-20T11:00:00.000Z' },
            { id: 4, examId: 2, enrollmentId: 1, studentId: 5, studentName: 'Budi Santoso', score: null, status: 'not_assessed', gradedBy: null, gradedByName: '', gradedAt: null, note: '', createdAt: '2026-10-15T08:00:00.000Z', updatedAt: '2026-10-15T08:00:00.000Z' },
            { id: 5, examId: 2, enrollmentId: 2, studentId: 6, studentName: 'Ani Nurhayati', score: null, status: 'not_assessed', gradedBy: null, gradedByName: '', gradedAt: null, note: '', createdAt: '2026-10-15T08:00:00.000Z', updatedAt: '2026-10-15T08:00:00.000Z' },
            { id: 6, examId: 2, enrollmentId: 3, studentId: 7, studentName: 'Rina Setiawati', score: null, status: 'not_assessed', gradedBy: null, gradedByName: '', gradedAt: null, note: '', createdAt: '2026-10-15T08:00:00.000Z', updatedAt: '2026-10-15T08:00:00.000Z' }
        ];
    }

    function seedPredicateRanges() {
        return [
            { id: 1, letter: 'A', label: 'Sangat Baik', minScore: 90, maxScore: 100, sortOrder: 1, isActive: true },
            { id: 2, letter: 'A-', label: 'Sangat Baik', minScore: 80, maxScore: 89.99, sortOrder: 2, isActive: true },
            { id: 3, letter: 'B+', label: 'Baik', minScore: 75, maxScore: 79.99, sortOrder: 3, isActive: true },
            { id: 4, letter: 'B', label: 'Baik', minScore: 70, maxScore: 74.99, sortOrder: 4, isActive: true },
            { id: 5, letter: 'B-', label: 'Baik', minScore: 65, maxScore: 69.99, sortOrder: 5, isActive: true },
            { id: 6, letter: 'C+', label: 'Cukup', minScore: 60, maxScore: 64.99, sortOrder: 6, isActive: true },
            { id: 7, letter: 'C', label: 'Cukup', minScore: 55, maxScore: 59.99, sortOrder: 7, isActive: true },
            { id: 8, letter: 'D', label: 'Kurang', minScore: 50, maxScore: 54.99, sortOrder: 8, isActive: true },
            { id: 9, letter: 'E', label: 'Tidak Lulus', minScore: 0, maxScore: 49.99, sortOrder: 9, isActive: true }
        ];
    }

    function seedGradeSchemes() {
        return [
            {
                id: 1, courseId: 1, courseTitle: 'Pelatihan Manajemen Dasar', classId: 1,
                classCode: 'ANG-2026-MNJ-01', className: 'Angkatan 2026 — Pelatihan Manajemen Dasar',
                name: 'Skema Penilaian Pelatihan Manajemen Dasar', status: 'published',
                passScore: 60, rounding: 'half_up',
                notes: 'Template bobot: tugas 30%, ujian 40%, penilaian manual 30%.',
                createdBy: 1, updatedBy: 1, createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-01T08:00:00.000Z'
            }
        ];
    }

    function seedGradeSchemeComponents() {
        return [
            { id: 1, schemeId: 1, type: 'assignment', name: 'Tugas', description: 'Rata-rata nilai tugas kelas.', weight: 30, aggregation: 'weighted', maxScore: 100, isRequired: true, order: 1, createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-01T08:00:00.000Z' },
            { id: 2, schemeId: 1, type: 'exam', name: 'Ujian', description: 'Rata-rata nilai ujian kelas.', weight: 40, aggregation: 'weighted', maxScore: 100, isRequired: true, order: 2, createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-01T08:00:00.000Z' },
            { id: 3, schemeId: 1, type: 'manual', name: 'Praktik & Partisipasi', description: 'Penilaian langsung oleh instruktur.', weight: 30, aggregation: 'average', maxScore: 100, isRequired: true, order: 3, createdAt: '2026-10-01T08:00:00.000Z', updatedAt: '2026-10-01T08:00:00.000Z' }
        ];
    }

    function seedGradeComponentScores() {
        return [
            { id: 1, componentId: 3, enrollmentId: 1, studentId: 5, score: 90, status: 'graded', assessorId: 2, assessorName: 'Ahmad Fauzi, M.M.', assessedAt: '2026-10-28T10:00:00.000Z', note: 'Praktik kerja rapi dan komunikatif.', createdAt: '2026-10-28T10:00:00.000Z', updatedAt: '2026-10-28T10:00:00.000Z' },
            { id: 2, componentId: 3, enrollmentId: 2, studentId: 6, score: 85, status: 'graded', assessorId: 2, assessorName: 'Ahmad Fauzi, M.M.', assessedAt: '2026-10-28T10:05:00.000Z', note: 'Pemahaman konsep baik, partisipasi aktif.', createdAt: '2026-10-28T10:05:00.000Z', updatedAt: '2026-10-28T10:05:00.000Z' }
        ];
    }

    function seedCategories() {        return [
            { id: 1, name: 'Pelatihan', slug: 'pelatihan', status: 'active' },
            { id: 2, name: 'Soft Skill', slug: 'soft-skill', status: 'active' },
            { id: 3, name: 'Administrasi', slug: 'administrasi', status: 'active' },
            { id: 4, name: 'Reguler Class', slug: 'reguler-class', status: 'active' }
        ];
    }

    function seedCurriculumVersions() {
        return [
            { id: 1, courseId: 1, code: '1.0', name: 'Kurikulum 1.0 — Pelatihan Manajemen Dasar', description: 'Versi awal kurikulum Pelatihan Manajemen Dasar.', status: 'locked', effectiveDate: '2026-09-20', notes: 'Migrasi struktur modul dan materi v3.0.' },
            { id: 2, courseId: 2, code: '1.0', name: 'Kurikulum 1.0 — Komunikasi Efektif', description: 'Versi awal kurikulum Komunikasi Efektif di Tempat Kerja.', status: 'draft', effectiveDate: '2026-09-25', notes: 'Migrasi struktur modul dan materi v3.0.' },
            { id: 3, courseId: 3, code: '1.0', name: 'Kurikulum 1.0 — Administrasi Perkantoran Modern', description: 'Versi awal kurikulum Administrasi Perkantoran Modern.', status: 'draft', effectiveDate: '2026-09-28', notes: 'Migrasi struktur modul dan materi v3.0.' },
            { id: 4, courseId: 4, code: '1.0', name: 'Kurikulum 1.0 — Microsoft Office', description: 'Versi awal kurikulum Microsoft Office.', status: 'published', effectiveDate: '2026-10-02', notes: 'Migrasi struktur modul dan materi v3.0.' }
        ];
    }

    function seedClasses() {
        // Hanya kelas migrasi. Setiap kelas di aplikasi selalu merujuk versi
        // kurikulum Published yang langsung dikunci saat kelas dibuat.
        return [
            { id: 1, code: 'ANG-2026-MNJ-01', name: 'Angkatan 2026 — Pelatihan Manajemen Dasar', courseId: 1, courseTitle: 'Pelatihan Manajemen Dasar', curriculumVersionId: 1, curriculumVersionCode: '1.0', curriculumVersionName: 'Kurikulum 1.0 — Pelatihan Manajemen Dasar', branchId: 1, branchCode: 'CBG-JKT', branchName: 'Cabang Jakarta', branchCity: 'Jakarta', instructorId: 2, instructorName: 'Ahmad Fauzi, M.M.', periodStart: '2026-10-01', periodEnd: '2026-12-31', quota: 30, location: 'Kampus Pusat', room: 'R-101', scheduleNote: 'Senin & Rabu, 09.00-11.00', plannedJp: 32, status: 'ongoing', legacyImport: true, notes: 'Kelas migrasi dari enrollment kursus v3.0.' }
        ];
    }

    function seedClassHistories() {
        return seedClasses().map(function (item) {
            return {
                id: item.id,
                classId: item.id,
                instructorId: item.instructorId,
                startedAt: item.periodStart,
                endedAt: null,
                reason: 'Penetapan tentor awal saat kelas dibuat.'
            };
        });
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
                    curriculumVersionId: course.curriculumVersionId || course.id,
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
                        curriculumVersionId: course.curriculumVersionId || course.id,
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
        // Migrasi akun lama: semua akun wajib punya password agar login
        // dapat diverifikasi. Akun tanpa password memakai password demo.
        let usersChanged = false;
        currentUsers.forEach(function (user) {
            if (!user.password) {
                user.password = ELIPS.DEFAULT_PASSWORD;
                usersChanged = true;
            }
        });
        if (usersChanged) write(KEYS.users, currentUsers);

        const courses = read(KEYS.courses, null);
        if (!Array.isArray(courses) || courses.length === 0) {
            write(KEYS.courses, seedCourses());
        }

        const currentCourses = read(KEYS.courses, []);
        const seedCategoryList = seedCategories();
        currentCourses.forEach(function (course) {
            if (course.status !== 'draft' && course.status !== 'published') course.status = 'draft';

            if (!course.categoryId) {
                const match = seedCategoryList.find(function (c) { return c.name === course.category; });
                course.categoryId = match ? match.id : null;
            }
            if (!course.curriculumVersionId) course.curriculumVersionId = course.id;

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

        if (!Array.isArray(read(KEYS.categories, null)) || read(KEYS.categories, []).length === 0) {
            write(KEYS.categories, seedCategoryList);
        }
        if (!Array.isArray(read(KEYS.branches, null)) || read(KEYS.branches, []).length === 0) {
            write(KEYS.branches, seedBranches());
        }
        // Normalisasi cabang: field wajib selalu tersedia untuk UI.
        const branchList = read(KEYS.branches, []);
        branchList.forEach(function (branch) {
            branch.id = Number(branch.id);
            if (!branch.code) branch.code = 'CBG-' + String(branch.id || 1);
            if (!branch.status) branch.status = 'active';
            if (!branch.city) branch.city = '';
            if (!branch.address) branch.address = '';
            if (!branch.phone) branch.phone = '';
            if (!branch.manager) branch.manager = '';
            if (!branch.notes) branch.notes = '';
        });
        write(KEYS.branches, branchList);
        // Kelas lama tanpa cabang dibiarkan null agar tetap kompatibel.
        const classList = read(KEYS.classes, []);
        let classBranchChanged = false;
        classList.forEach(function (item) {
            if (item.branchId === undefined) {
                item.branchId = null;
                classBranchChanged = true;
            }
            if (item.branchId !== null && item.branchId !== '' && !item.branchCode) {
                const branch = branchList.find(function (b) { return b.id === Number(item.branchId); });
                if (branch) {
                    item.branchCode = branch.code;
                    item.branchName = branch.name;
                    item.branchCity = branch.city;
                    classBranchChanged = true;
                }
            }
        });
        if (classBranchChanged) write(KEYS.classes, classList);
        if (!Array.isArray(read(KEYS.curriculumVersions, null)) || read(KEYS.curriculumVersions, []).length === 0) {
            write(KEYS.curriculumVersions, seedCurriculumVersions());
        }
        if (!Array.isArray(read(KEYS.classes, null)) || read(KEYS.classes, []).length === 0) {
            write(KEYS.classes, seedClasses());
        }
        if (!Array.isArray(read(KEYS.classInstructorHistories, null)) || read(KEYS.classInstructorHistories, []).length === 0) {
            write(KEYS.classInstructorHistories, seedClassHistories());
        }

        // ---------- Phase 4: materi dan akses kelas ----------
        // Setiap kelas memiliki satu pengaturan akses. Kelas lama otomatis
        // mendapat mode "open" agar perilaku sebelumnya tidak berubah.
        if (!Array.isArray(read(KEYS.classMaterialSettings, null))) {
            write(KEYS.classMaterialSettings, []);
        }
        const materialSettings = read(KEYS.classMaterialSettings, []);
        const validModes = ['open', 'sequential', 'scheduled', 'conditional'];
        materialSettings.forEach(function (row) {
            row.id = Number(row.id) || nextId(materialSettings);
            row.classId = Number(row.classId);
            if (validModes.indexOf(row.accessMode) === -1) row.accessMode = 'open';
            row.requireInstructorApproval = !!row.requireInstructorApproval;
            row.notes = String(row.notes || '');
        });
        write(KEYS.classMaterialSettings, materialSettings);

        if (!Array.isArray(read(KEYS.classMaterials, null))) {
            write(KEYS.classMaterials, []);
        }
        const materialRows = read(KEYS.classMaterials, []);
        materialRows.forEach(function (item) {
            item.id = Number(item.id);
            item.classId = Number(item.classId);
            item.order = Number(item.order) || 1;
            item.lessonId = item.lessonId === null || item.lessonId === undefined ? null : Number(item.lessonId);
            item.sessionId = item.sessionId === null || item.sessionId === undefined ? null : Number(item.sessionId);
            item.accessLevel = item.accessLevel || 'optional';
            item.status = item.status === 'hidden' ? 'hidden' : 'active';
            item.type = item.type || 'text';
            item.content = String(item.content || '');
            item.fileName = String(item.fileName || '');
            item.filePath = String(item.filePath || '');
            item.mimeType = String(item.mimeType || '');
            item.reason = String(item.reason || '');
            item.availableFrom = item.availableFrom || null;
        });
        write(KEYS.classMaterials, materialRows);

        if (!Array.isArray(read(KEYS.classLessonProgress, null))) {
            write(KEYS.classLessonProgress, []);
        }
        if (!Array.isArray(read(KEYS.classMaterialProgress, null))) {
            write(KEYS.classMaterialProgress, []);
        }

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
        const seededClasses = ELIPS.getClasses();
        enrollments.forEach(function (e) {
            if (!e.status) e.status = 'active';
            if (!e.enrolledBy) e.enrolledBy = 'admin';
            if (!e.enrolledAt) e.enrolledAt = new Date().toISOString().split('T')[0];
            if (e.classId === undefined || e.classId === null) {
                const match = seededClasses.find(function (c) {
                    return c.courseId === Number(e.courseId) && c.status !== 'archived';
                });
                e.classId = match ? match.id : null;
            }
        });
        write(KEYS.enrollments, enrollments);

        if (!Array.isArray(read(KEYS.classSessions, null))) {
            write(KEYS.classSessions, seedClassSessions());
        }
        if (!Array.isArray(read(KEYS.attendances, null))) {
            write(KEYS.attendances, seedAttendances());
        }
        if (!Array.isArray(read(KEYS.attendanceCorrections, null))) {
            write(KEYS.attendanceCorrections, []);
        }

        // Seed module & lesson structure only when empty
        if (ELIPS.getModules().length === 0 || ELIPS.getLessons().length === 0) {
            const structure = buildStructure(currentCourses, currentUsers);
            write(KEYS.modules, structure.modules);
            write(KEYS.lessons, structure.lessons);
            write(KEYS.courses, currentCourses);
        }

        // Pastikan modul/materi lama memiliki relasi versi kurikulum.
        const modules = ELIPS.getModules();
        let modulesChanged = false;
        modules.forEach(function (module) {
            if (!module.curriculumVersionId) {
                const version = ELIPS.getCurrentCurriculumVersion(module.courseId);
                module.curriculumVersionId = version ? version.id : Number(module.courseId);
                modulesChanged = true;
            }
        });
        if (modulesChanged) write(KEYS.modules, modules);

        // Sanitasi konten materi teks lama agar HTML berbahaya tidak tersimpan.
        const lessons = ELIPS.getLessons();
        let lessonsChanged = false;
        lessons.forEach(function (lesson) {
            if (!lesson.curriculumVersionId) {
                const version = ELIPS.getCurrentCurriculumVersion(lesson.courseId);
                lesson.curriculumVersionId = version ? version.id : Number(lesson.courseId);
                lessonsChanged = true;
            }
            if (lesson.type !== 'text') return;
            const safeContent = ELIPS.sanitizeHtml(lesson.content || '');
            if (safeContent !== lesson.content) {
                lesson.content = safeContent;
                lessonsChanged = true;
            }
        });
        if (lessonsChanged) write(KEYS.lessons, lessons);

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

        // ---------- Phase 5: tugas, pengumpulan, dan ujian ----------
        if (!Array.isArray(read(KEYS.assignments, null))) {
            write(KEYS.assignments, seedAssignments());
        }
        if (!Array.isArray(read(KEYS.assignmentFiles, null))) {
            write(KEYS.assignmentFiles, []);
        }
        if (!Array.isArray(read(KEYS.assignmentRubrics, null))) {
            write(KEYS.assignmentRubrics, seedAssignmentRubrics());
        }
        if (!Array.isArray(read(KEYS.submissions, null))) {
            write(KEYS.submissions, seedSubmissions());
        }
        if (!Array.isArray(read(KEYS.submissionFiles, null))) {
            write(KEYS.submissionFiles, []);
        }
        if (!Array.isArray(read(KEYS.submissionReviews, null))) {
            write(KEYS.submissionReviews, seedSubmissionReviews());
        }
        if (!Array.isArray(read(KEYS.exams, null))) {
            write(KEYS.exams, seedExams());
        }
        if (!Array.isArray(read(KEYS.examParticipants, null))) {
            write(KEYS.examParticipants, seedExamParticipants());
        }
        if (!Array.isArray(read(KEYS.examResults, null))) {
            write(KEYS.examResults, seedExamResults());
        }

        // ---------- Phase 6: skema penilaian, predikat, dan finalisasi ----------
        if (!Array.isArray(read(KEYS.predicateRanges, null))) {
            write(KEYS.predicateRanges, seedPredicateRanges());
        }
        if (!Array.isArray(read(KEYS.gradeSchemes, null))) {
            write(KEYS.gradeSchemes, seedGradeSchemes());
        }
        if (!Array.isArray(read(KEYS.gradeSchemeComponents, null))) {
            write(KEYS.gradeSchemeComponents, seedGradeSchemeComponents());
        }
        if (!Array.isArray(read(KEYS.gradeComponentScores, null))) {
            write(KEYS.gradeComponentScores, seedGradeComponentScores());
        }
        if (!Array.isArray(read(KEYS.gradeCorrections, null))) {
            write(KEYS.gradeCorrections, []);
        }

        const assignmentStatuses = ['draft', 'published', 'closed', 'archived'];
        const assignmentTypes = ['file_upload', 'text_submission', 'in_class_practice', 'external_reference'];
        const examStatuses = ['draft', 'published', 'closed', 'archived'];
        const examTypes = ['written', 'practical', 'oral', 'project', 'other'];
        const submissionStatuses = ['submitted', 'late', 'reviewed', 'revision_requested', 'resubmitted', 'accepted'];
        const participantStatuses = ['registered', 'attended', 'absent', 'excused'];
        const resultStatuses = ['not_assessed', 'excused', 'missing', 'graded'];

        const classListForTasks = ELIPS.getClasses();
        const userListForTasks = ELIPS.getUsers();

        const assignmentList = ELIPS.getAssignments();
        const assignmentMap = {};
        assignmentList.forEach(function (item) {
            item.id = Number(item.id);
            item.classId = Number(item.classId);
            const classItem = classListForTasks.find(function (c) { return Number(c.id) === item.classId; });
            if (classItem) {
                if (!item.classCode) item.classCode = classItem.code || '';
                if (!item.className) item.className = classItem.name || '';
                if (!item.courseId) item.courseId = Number(classItem.courseId);
                if (!item.courseTitle) item.courseTitle = classItem.courseTitle || '';
                if (!item.curriculumVersionId) item.curriculumVersionId = Number(classItem.curriculumVersionId);
            }
            item.curriculumVersionId = Number(item.curriculumVersionId) || 0;
            item.moduleId = item.moduleId === null || item.moduleId === undefined || item.moduleId === ''
                ? null : Number(item.moduleId);
            item.lessonId = item.lessonId === null || item.lessonId === undefined || item.lessonId === ''
                ? null : Number(item.lessonId);
            item.title = String(item.title || '');
            item.instructions = String(item.instructions || '');
            if (assignmentTypes.indexOf(item.type) === -1) item.type = 'text_submission';
            if (assignmentStatuses.indexOf(item.status) === -1) item.status = 'draft';
            item.openAt = item.openAt || null;
            item.dueAt = item.dueAt || null;
            item.maxScore = Number(item.maxScore) || 100;
            item.weight = Number(item.weight) || 0;
            item.requireFile = !!item.requireFile;
            item.allowedTypes = String(item.allowedTypes || '');
            item.maxFileSize = item.maxFileSize === null || item.maxFileSize === undefined || item.maxFileSize === ''
                ? null : Number(item.maxFileSize);
            item.createdBy = item.createdBy === null || item.createdBy === undefined ? null : Number(item.createdBy);
            item.updatedBy = item.updatedBy === null || item.updatedBy === undefined ? null : Number(item.updatedBy);
            item.createdAt = item.createdAt || new Date().toISOString();
            item.updatedAt = item.updatedAt || item.createdAt;
            assignmentMap[item.id] = item;
        });
        write(KEYS.assignments, assignmentList);

        const assignmentFileList = ELIPS.getAssignmentFiles();
        assignmentFileList.forEach(function (file) {
            file.id = Number(file.id);
            file.assignmentId = Number(file.assignmentId);
            file.fileName = String(file.fileName || '');
            file.filePath = String(file.filePath || '');
            file.fileSize = file.fileSize === null || file.fileSize === undefined ? null : Number(file.fileSize);
            file.mimeType = String(file.mimeType || '');
            file.createdAt = file.createdAt || new Date().toISOString();
        });
        write(KEYS.assignmentFiles, assignmentFileList);

        const rubricList = ELIPS.getAssignmentRubrics();
        rubricList.forEach(function (rubric) {
            rubric.id = Number(rubric.id);
            rubric.assignmentId = Number(rubric.assignmentId);
            rubric.criterion = String(rubric.criterion || '');
            rubric.description = String(rubric.description || '');
            rubric.maxScore = Number(rubric.maxScore) || 0;
            rubric.order = Number(rubric.order) || 1;
            rubric.createdAt = rubric.createdAt || new Date().toISOString();
            rubric.updatedAt = rubric.updatedAt || null;
        });
        write(KEYS.assignmentRubrics, rubricList);

        const submissionList = ELIPS.getSubmissions();
        const submissionMap = {};
        submissionList.forEach(function (item) {
            item.id = Number(item.id);
            item.assignmentId = Number(item.assignmentId);
            item.enrollmentId = Number(item.enrollmentId);
            item.studentId = Number(item.studentId);
            const assignment = assignmentMap[item.assignmentId];
            if (assignment) {
                if (!item.assignmentTitle) item.assignmentTitle = assignment.title;
                if (!item.classId) item.classId = assignment.classId;
                if (!item.assignmentType) item.assignmentType = assignment.type;
                if (item.maxScore === undefined || item.maxScore === null) item.maxScore = assignment.maxScore;
            }
            item.classId = item.classId === null || item.classId === undefined ? null : Number(item.classId);
            item.assignmentType = String(item.assignmentType || '');
            item.maxScore = Number(item.maxScore) || 0;
            const student = userListForTasks.find(function (u) { return Number(u.id) === item.studentId; });
            if (!item.studentName) item.studentName = student ? student.name : '';
            if (submissionStatuses.indexOf(item.status) === -1) item.status = 'submitted';
            item.submittedAt = item.submittedAt || item.createdAt || new Date().toISOString();
            item.isLate = !!item.isLate;
            item.score = item.score === null || item.score === undefined || item.score === '' ? null : Number(item.score);
            item.reviewedBy = item.reviewedBy === null || item.reviewedBy === undefined ? null : Number(item.reviewedBy);
            item.reviewedAt = item.reviewedAt || null;
            item.revisionCount = Number(item.revisionCount) || 0;
            item.textAnswer = String(item.textAnswer || '');
            item.feedback = String(item.feedback || '');
            item.createdAt = item.createdAt || item.submittedAt;
            item.updatedAt = item.updatedAt || item.createdAt;
            submissionMap[item.id] = item;
        });
        write(KEYS.submissions, submissionList);

        const submissionFileList = ELIPS.getSubmissionFiles();
        submissionFileList.forEach(function (file) {
            file.id = Number(file.id);
            file.submissionId = Number(file.submissionId);
            file.fileName = String(file.fileName || '');
            file.filePath = String(file.filePath || '');
            file.fileSize = file.fileSize === null || file.fileSize === undefined ? null : Number(file.fileSize);
            file.mimeType = String(file.mimeType || '');
            file.createdAt = file.createdAt || new Date().toISOString();
        });
        write(KEYS.submissionFiles, submissionFileList);

        const reviewList = ELIPS.getSubmissionReviews();
        reviewList.forEach(function (item) {
            item.id = Number(item.id);
            item.submissionId = Number(item.submissionId);
            item.reviewerId = item.reviewerId === null || item.reviewerId === undefined ? null : Number(item.reviewerId);
            const reviewer = userListForTasks.find(function (u) { return Number(u.id) === item.reviewerId; });
            if (!item.reviewerName) item.reviewerName = reviewer ? reviewer.name : '';
            if (['submit', 'score', 'revision', 'accept'].indexOf(item.action) === -1) item.action = 'submit';
            item.score = item.score === null || item.score === undefined || item.score === '' ? null : Number(item.score);
            item.note = String(item.note || '');
            item.createdAt = item.createdAt || new Date().toISOString();
        });
        write(KEYS.submissionReviews, reviewList);

        const examList = ELIPS.getExams();
        const examMap = {};
        examList.forEach(function (item) {
            item.id = Number(item.id);
            item.classId = Number(item.classId);
            const classItem = classListForTasks.find(function (c) { return Number(c.id) === item.classId; });
            if (classItem) {
                if (!item.classCode) item.classCode = classItem.code || '';
                if (!item.className) item.className = classItem.name || '';
                if (!item.courseId) item.courseId = Number(classItem.courseId);
                if (!item.courseTitle) item.courseTitle = classItem.courseTitle || '';
                if (!item.curriculumVersionId) item.curriculumVersionId = Number(classItem.curriculumVersionId);
            }
            item.curriculumVersionId = Number(item.curriculumVersionId) || 0;
            item.title = String(item.title || '');
            item.description = String(item.description || '');
            if (examTypes.indexOf(item.type) === -1) item.type = 'written';
            if (examStatuses.indexOf(item.status) === -1) item.status = 'draft';
            item.examDate = item.examDate || null;
            item.startTime = item.startTime || null;
            item.durationMinutes = item.durationMinutes === null || item.durationMinutes === undefined || item.durationMinutes === ''
                ? null : Number(item.durationMinutes);
            item.maxScore = Number(item.maxScore) || 100;
            item.weight = Number(item.weight) || 0;
            item.instructions = String(item.instructions || '');
            item.createdBy = item.createdBy === null || item.createdBy === undefined ? null : Number(item.createdBy);
            item.updatedBy = item.updatedBy === null || item.updatedBy === undefined ? null : Number(item.updatedBy);
            item.createdAt = item.createdAt || new Date().toISOString();
            item.updatedAt = item.updatedAt || item.createdAt;
            examMap[item.id] = item;
        });
        write(KEYS.exams, examList);

        const participantList = ELIPS.getExamParticipants();
        participantList.forEach(function (item) {
            item.id = Number(item.id);
            item.examId = Number(item.examId);
            item.enrollmentId = Number(item.enrollmentId);
            item.studentId = Number(item.studentId);
            const student = userListForTasks.find(function (u) { return Number(u.id) === item.studentId; });
            if (!item.studentName) item.studentName = student ? student.name : '';
            if (participantStatuses.indexOf(item.status) === -1) item.status = 'registered';
            item.note = String(item.note || '');
            item.createdAt = item.createdAt || new Date().toISOString();
            item.updatedAt = item.updatedAt || item.createdAt;
        });
        write(KEYS.examParticipants, participantList);

        const resultList = ELIPS.getExamResults();
        resultList.forEach(function (item) {
            item.id = Number(item.id);
            item.examId = Number(item.examId);
            item.enrollmentId = Number(item.enrollmentId);
            item.studentId = Number(item.studentId);
            const student = userListForTasks.find(function (u) { return Number(u.id) === item.studentId; });
            if (!item.studentName) item.studentName = student ? student.name : '';
            item.score = item.score === null || item.score === undefined || item.score === '' ? null : Number(item.score);
            if (resultStatuses.indexOf(item.status) === -1) item.status = 'not_assessed';
            item.gradedBy = item.gradedBy === null || item.gradedBy === undefined ? null : Number(item.gradedBy);
            if (!item.gradedByName && item.gradedBy) {
                const grader = userListForTasks.find(function (u) { return Number(u.id) === item.gradedBy; });
                item.gradedByName = grader ? grader.name : '';
            }
            item.gradedAt = item.gradedAt || null;
            item.note = String(item.note || '');
            item.createdAt = item.createdAt || new Date().toISOString();
            item.updatedAt = item.updatedAt || item.createdAt;
        });
        write(KEYS.examResults, resultList);
    }

    // Seed & migrasi dijalankan saat modul dimuat.
    normalizeData();
})(window.ELIPS);
