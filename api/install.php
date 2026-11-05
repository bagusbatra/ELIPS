<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - installer database
// Jalankan sekali: php api/install.php
// Opsi: php api/install.php --fresh  (hapus & buat ulang database)
// ============================================================

require __DIR__ . '/db.php';

$isCli = PHP_SAPI === 'cli';
$fresh = $isCli && in_array('--fresh', $argv ?? [], true);

header('Content-Type: text/plain; charset=utf-8');

try {
    $config = elips_config();
    $dbConfig = $config['db'];

    if ($fresh) {
        $server = elips_db(false);
        $server->exec('DROP DATABASE IF EXISTS `' . $dbConfig['name'] . '`');
        echo "Database lama dihapus.\n";
    }

    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('schema.sql tidak ditemukan.');
    }

    // Buang komentar baris agar pemisahan pernyataan SQL tetap bersih.
    $schema = preg_replace('/^\s*--.*$/m', '', $schema);
    $statements = array_filter(array_map('trim', explode(';', (string) $schema)));
    $server = elips_db(false);
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $server->exec($statement);
    }
    echo "Skema database siap.\n";

    $db = elips_db();
    $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount > 0) {
        echo "Data sudah ada (" . $userCount . " pengguna). Seed dilewati.\n";
        echo "Instalasi selesai.\n";
        exit;
    }

    $demoPassword = password_hash($config['demo_password'], PASSWORD_DEFAULT);

    $db->beginTransaction();

    // ---------- Categories ----------
    $categories = [
        [1, 'Pelatihan', 'pelatihan'],
        [2, 'Soft Skill', 'soft-skill'],
        [3, 'Administrasi', 'administrasi'],
        [4, 'Reguler Class', 'reguler-class'],
    ];
    $categoryStmt = $db->prepare(
        'INSERT INTO categories (id, name, slug, status) VALUES (?, ?, ?, \'active\')'
    );
    foreach ($categories as $category) {
        $categoryStmt->execute($category);
    }

    // ---------- Users ----------
    $users = [
        [1, 'Administrator', 'admin@example.com', '081234567890', 'admin', 'aktif', '2026-01-01'],
        [2, 'Ahmad Fauzi, M.M.', 'ahmad@example.com', '081234567891', 'instruktur', 'aktif', '2026-09-15'],
        [3, 'Siti Nurhaliza, S.Psi.', 'siti@example.com', '081234567892', 'instruktur', 'aktif', '2026-09-16'],
        [4, 'Budi Santoso, A.Md.', 'budi@example.com', '081234567893', 'instruktur', 'aktif', '2026-09-17'],
        [5, 'Budi Santoso', 'budi.student@example.com', '081234567894', 'peserta', 'aktif', '2026-10-01'],
        [6, 'Ani Nurhayati', 'ani@example.com', '081234567895', 'peserta', 'aktif', '2026-10-02'],
        [7, 'Rina Setiawati', 'rina@example.com', '081234567896', 'peserta', 'aktif', '2026-10-03'],
    ];
    $userStmt = $db->prepare(
        'INSERT INTO users (id, name, email, phone, password_hash, role, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($users as $user) {
        $userStmt->execute([$user[0], $user[1], $user[2], $user[3], $demoPassword, $user[4], $user[5], $user[6]]);
    }

    // ---------- Courses ----------
    $courses = [
        [1, 'Pelatihan Manajemen Dasar', 'Pelatihan', 1, 'Pelatihan ini membahas konsep dasar manajemen, perencanaan, organisasi, kepemimpinan, dan pengendalian untuk meningkatkan efektivitas kerja.', 2, 'published', '2026-09-20'],
        [2, 'Komunikasi Efektif di Tempat Kerja', 'Soft Skill', 2, 'Meningkatkan kemampuan komunikasi verbal, non-verbal, mendengarkan aktif, dan membangun hubungan yang harmonis di lingkungan kerja.', 3, 'draft', '2026-09-25'],
        [3, 'Administrasi Perkantoran Modern', 'Administrasi', 3, 'Pengelolaan arsip, surat menyurat, pengarsipan digital, penggunaan aplikasi perkantoran, dan tata kelola administrasi yang efisien.', 4, 'draft', '2026-09-28'],
        [4, 'Microsoft Office', 'Reguler Class', 4, 'Kelas rombel Microsoft Word, Microsoft Excel, dan Microsoft PowerPoint untuk kebutuhan administrasi perkantoran.', 2, 'draft', '2026-10-02'],
    ];
    $courseStmt = $db->prepare(
        'INSERT INTO courses (id, title, category, category_id, description, instructor_id, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($courses as $course) {
        $courseStmt->execute($course);
    }

    // ---------- Curriculum versions ----------
    // Setiap kursus lama mendapat versi kurikulum awal 1.0. Versi kursus 1
    // berstatus locked karena sudah dipakai oleh kelas migrasi.
    $versions = [
        [1, 1, '1.0', 'Kurikulum 1.0 — Pelatihan Manajemen Dasar', 'Versi awal kurikulum Pelatihan Manajemen Dasar.', 'locked', '2026-09-20', 'Migrasi struktur modul dan materi v3.0.'],
        [2, 2, '1.0', 'Kurikulum 1.0 — Komunikasi Efektif', 'Versi awal kurikulum Komunikasi Efektif di Tempat Kerja.', 'draft', '2026-09-25', 'Migrasi struktur modul dan materi v3.0.'],
        [3, 3, '1.0', 'Kurikulum 1.0 — Administrasi Perkantoran Modern', 'Versi awal kurikulum Administrasi Perkantoran Modern.', 'draft', '2026-09-28', 'Migrasi struktur modul dan materi v3.0.'],
        [4, 4, '1.0', 'Kurikulum 1.0 — Microsoft Office', 'Versi awal kurikulum Microsoft Office.', 'published', '2026-10-02', 'Migrasi struktur modul dan materi v3.0.'],
    ];
    $versionStmt = $db->prepare(
        "INSERT INTO curriculum_versions
         (id, course_id, code, name, description, status, effective_date, notes, created_by, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
    );
    foreach ($versions as $version) {
        $versionStmt->execute($version);
    }

    $templates = [
        1 => [
            'Pengantar Manajemen',
            'Perencanaan (Planning)',
            'Pengorganisasian (Organizing)',
            'Kepemimpinan (Leading)',
            'Pengendalian (Controlling)',
            'Manajemen Sumber Daya Manusia',
            'Manajemen Waktu & Produktivitas',
            'Evaluasi & Studi Kasus',
        ],
        2 => [
            'Dasar Komunikasi Efektif',
            'Komunikasi Verbal & Non-Verbal',
            'Mendengarkan Aktif',
            'Komunikasi dalam Tim',
            'Menangani Konflik',
            'Presentasi & Negosiasi',
        ],
        3 => [
            'Pengantar Administrasi Perkantoran',
            'Pengelolaan Arsip',
            'Surat Menyurat',
            'Kearsipan Digital',
            'Aplikasi Perkantoran',
            'Tata Kelola Administrasi',
            'Evaluasi & Praktik',
        ],
        4 => [
            'Pengenalan Microsoft Word',
            'Microsoft Excel Dasar',
            'Microsoft PowerPoint',
            'Praktik Terpadu',
        ],
    ];

    $moduleStmt = $db->prepare(
        'INSERT INTO modules (id, course_id, curriculum_version_id, instructor_id, title, description, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $lessonStmt = $db->prepare(
        'INSERT INTO lessons (id, module_id, course_id, curriculum_version_id, instructor_id, title, type, content, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $moduleId = 1;
    $lessonId = 1;
    foreach ($courses as $course) {
        $courseId = (int) $course[0];
        $instructorId = (int) $course[5];
        $courseTitle = (string) $course[1];
        $createdAt = (string) $course[7] . ' 00:00:00';
        $versionId = $courseId;
        $titles = $templates[$courseId] ?? ['Modul 1'];

        foreach ($titles as $index => $title) {
            $currentModuleId = $moduleId++;
            $moduleStmt->execute([
                $currentModuleId,
                $courseId,
                $versionId,
                $instructorId,
                'Modul ' . ($index + 1) . ': ' . $title,
                'Materi ' . strtolower($title) . ' pada kursus ' . $courseTitle . '.',
                $index + 1,
                $createdAt,
            ]);

            for ($lessonIndex = 1; $lessonIndex <= 3; $lessonIndex++) {
                $isVideo = $lessonIndex === 3;
                $suffix = $lessonIndex === 1 ? ' - Konsep Dasar' : ($lessonIndex === 2 ? ' - Penerapan' : ' - Studi Kasus');
                $content = $isVideo
                    ? '<p>Simulasi video pembelajaran untuk materi <strong>' . $title . '</strong>. Pada versi produksi, konten video dapat di-embed dari YouTube atau Vimeo.</p>'
                    : '<p>Materi ini membahas <strong>' . $title . '</strong> secara mendalam, mulai dari konsep dasar, penerapan di tempat kerja, hingga studi kasus sederhana.</p><ul><li>Pahami konsep utama materi.</li><li>Perhatikan contoh penerapan.</li><li>Kerjakan latihan mandiri.</li></ul>';
                $lessonStmt->execute([
                    $lessonId++,
                    $currentModuleId,
                    $courseId,
                    $versionId,
                    $instructorId,
                    ($index + 1) . '.' . $lessonIndex . ' ' . $title . $suffix,
                    $isVideo ? 'video' : 'text',
                    $content,
                    $lessonIndex,
                    $createdAt,
                ]);
            }
        }
    }

    // ---------- Classes / cohorts ----------
    // Hanya kelas migrasi yang di-seed. Kelas contoh lain sengaja tidak dibuat
    // agar setiap kelas pasti merujuk versi yang sudah Published dan terkunci.
    $classes = [
        [1, 'ANG-2026-MNJ-01', 'Angkatan 2026 — Pelatihan Manajemen Dasar', 1, 1, 2, '2026-10-01', '2026-12-31', 30, 'Kampus Pusat', 'R-101', 'Senin & Rabu, 09.00-11.00', 32, 'ongoing', 1, 'Kelas migrasi dari enrollment kursus v3.0.'],
    ];
    $classStmt = $db->prepare(
        "INSERT INTO classes
         (id, code, name, course_id, curriculum_version_id, instructor_id, period_start, period_end, quota,
          location, room, schedule_note, planned_jp, status, legacy_import, notes, created_by, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
    );
    foreach ($classes as $class) {
        $classStmt->execute($class);
    }

    $historyStmt = $db->prepare(
        'INSERT INTO class_instructor_histories (class_id, instructor_id, started_at, ended_at, reason, actor_id)
         VALUES (?, ?, ?, NULL, ?, 1)'
    );
    foreach ($classes as $class) {
        $historyStmt->execute([
            (int) $class[0],
            (int) $class[5],
            (string) $class[6],
            'Penetapan tentor awal saat kelas dibuat.',
        ]);
    }

    // ---------- Enrollments ----------
    // Enrollment lama dimigrasikan ke kelas migrasi kursus 1.
    $enrollmentStmt = $db->prepare(
        "INSERT INTO enrollments (id, student_id, course_id, class_id, enrolled_by, enrolled_at, status)
         VALUES (?, ?, ?, ?, 'admin', ?, 'active')"
    );
    $enrollmentStmt->execute([1, 5, 1, 1, '2026-10-01']);
    $enrollmentStmt->execute([2, 6, 1, 1, '2026-10-05']);
    $enrollmentStmt->execute([3, 7, 1, 1, '2026-10-10']);

    // ---------- Sesi pertemuan & presensi ----------
    $sessionStmt = $db->prepare(
        "INSERT INTO class_sessions
         (id, class_id, session_number, title, module_id, session_date, start_time, end_time,
          location, room, instructor_id, planned_jp, actual_jp, status, notes, created_by, updated_by, created_at)
         VALUES (?, 1, ?, ?, ?, ?, '09:00:00', '11:00:00', 'Kampus Pusat', 'R-101', 2, 4, ?, ?, ?, 1, 1, ?)"
    );
    $sessions = [
        [1, 1, 'Pertemuan 1: Pengantar Manajemen', 1, '2026-10-05', 4, 'completed', 'Sesi pembuka dan kontrak belajar.', '2026-10-05 09:00:00'],
        [2, 2, 'Pertemuan 2: Perencanaan (Planning)', 2, '2026-10-07', 4, 'completed', 'Latihan menyusun rencana kerja sederhana.', '2026-10-07 09:00:00'],
        [3, 3, 'Pertemuan 3: Pengorganisasian (Organizing)', 3, '2026-10-12', 4, 'completed', 'Diskusi struktur organisasi dan pembagian tugas.', '2026-10-12 09:00:00'],
        [4, 4, 'Pertemuan 4: Kepemimpinan (Leading)', 4, '2026-10-14', 0, 'scheduled', 'Sesi berikutnya, presensi belum diisi.', '2026-10-14 09:00:00'],
    ];
    foreach ($sessions as $session) {
        $sessionStmt->execute([
            $session[0],
            $session[1],
            $session[2],
            $session[3],
            $session[4],
            $session[5],
            $session[6],
            $session[7],
            $session[8],
        ]);
    }

    $attendanceStmt = $db->prepare(
        "INSERT INTO attendances
         (id, session_id, enrollment_id, student_id, status, recorded_by, recorded_at, note, correction_reason, created_at)
         VALUES (?, ?, ?, ?, ?, 2, ?, ?, '', ?)"
    );
    $attendances = [
        [1, 1, 1, 5, 'present', '2026-10-05 11:05:00', '', '2026-10-05 11:05:00'],
        [2, 1, 2, 6, 'present', '2026-10-05 11:05:00', '', '2026-10-05 11:05:00'],
        [3, 1, 3, 7, 'late', '2026-10-05 11:05:00', 'Datang 15 menit setelah sesi dimulai.', '2026-10-05 11:05:00'],
        [4, 2, 1, 5, 'present', '2026-10-07 11:05:00', '', '2026-10-07 11:05:00'],
        [5, 2, 2, 6, 'permission', '2026-10-07 11:05:00', 'Izin keperluan keluarga.', '2026-10-07 11:05:00'],
        [6, 2, 3, 7, 'present', '2026-10-07 11:05:00', '', '2026-10-07 11:05:00'],
        [7, 3, 1, 5, 'late', '2026-10-12 11:05:00', 'Terlambat 10 menit.', '2026-10-12 11:05:00'],
        [8, 3, 2, 6, 'present', '2026-10-12 11:05:00', '', '2026-10-12 11:05:00'],
        [9, 3, 3, 7, 'absent', '2026-10-12 11:05:00', 'Tidak hadir tanpa keterangan.', '2026-10-12 11:05:00'],
    ];
    foreach ($attendances as $attendance) {
        $attendanceStmt->execute([
            $attendance[0],
            $attendance[1],
            $attendance[2],
            $attendance[3],
            $attendance[4],
            $attendance[5],
            $attendance[6],
            $attendance[7],
        ]);
    }

    $db->commit();

    echo "Data demo berhasil dimasukkan.\n";
    echo "Akun demo: admin@example.com / instruktur / peserta — password: " . $config['demo_password'] . "\n";
    echo "Instalasi selesai.\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo "Instalasi gagal: " . $e->getMessage() . "\n";
    if ($isCli) {
        exit(1);
    }
}
