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
        [1, 'Pelatihan Manajemen Dasar', 'Pelatihan', 'Pelatihan ini membahas konsep dasar manajemen, perencanaan, organisasi, kepemimpinan, dan pengendalian untuk meningkatkan efektivitas kerja.', 2, 'published', '2026-09-20'],
        [2, 'Komunikasi Efektif di Tempat Kerja', 'Soft Skill', 'Meningkatkan kemampuan komunikasi verbal, non-verbal, mendengarkan aktif, dan membangun hubungan yang harmonis di lingkungan kerja.', 3, 'draft', '2026-09-25'],
        [3, 'Administrasi Perkantoran Modern', 'Administrasi', 'Pengelolaan arsip, surat menyurat, pengarsipan digital, penggunaan aplikasi perkantoran, dan tata kelola administrasi yang efisien.', 4, 'draft', '2026-09-28'],
        [4, 'Microsoft Office', 'Reguler Class', 'Kelas rombel Microsoft Word, Microsoft Excel, dan Microsoft PowerPoint untuk kebutuhan administrasi perkantoran.', 2, 'draft', '2026-10-02'],
    ];
    $courseStmt = $db->prepare(
        'INSERT INTO courses (id, title, category, description, instructor_id, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($courses as $course) {
        $courseStmt->execute($course);
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
        'INSERT INTO modules (id, course_id, instructor_id, title, description, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $lessonStmt = $db->prepare(
        'INSERT INTO lessons (id, module_id, course_id, instructor_id, title, type, content, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $moduleId = 1;
    $lessonId = 1;
    foreach ($courses as $course) {
        $courseId = (int) $course[0];
        $instructorId = (int) $course[4];
        $courseTitle = (string) $course[1];
        $createdAt = (string) $course[6] . ' 00:00:00';
        $titles = $templates[$courseId] ?? ['Modul 1'];

        foreach ($titles as $index => $title) {
            $currentModuleId = $moduleId++;
            $moduleStmt->execute([
                $currentModuleId,
                $courseId,
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

    // ---------- Enrollments ----------
    $enrollmentStmt = $db->prepare(
        "INSERT INTO enrollments (id, student_id, course_id, enrolled_by, enrolled_at, status)
         VALUES (?, ?, ?, 'admin', ?, 'active')"
    );
    $enrollmentStmt->execute([1, 5, 1, '2026-10-01']);
    $enrollmentStmt->execute([2, 6, 1, '2026-10-05']);
    $enrollmentStmt->execute([3, 7, 1, '2026-10-10']);

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
