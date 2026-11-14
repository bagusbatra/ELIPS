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

    // ---------- Migrasi Campaigns Marketing ----------
    // Skema baru langsung memuat kolom campaign_id; database lama perlu
    // ditambahkan agar relasi lead ke campaign tetap kompatibel.
    $hasLeadCampaign = (bool) $db->query("SHOW COLUMNS FROM marketing_leads LIKE 'campaign_id'")->fetch();
    if (!$hasLeadCampaign) {
        $db->exec('ALTER TABLE marketing_leads ADD COLUMN campaign_id INT UNSIGNED NULL AFTER owner_id');
        echo "Kolom campaign lead ditambahkan.\n";
    }
    $hasLeadCampaignIndex = (bool) $db->query(
        "SHOW INDEX FROM marketing_leads WHERE Key_name = 'idx_marketing_leads_campaign'"
    )->fetch();
    if (!$hasLeadCampaignIndex) {
        $db->exec('ALTER TABLE marketing_leads ADD KEY idx_marketing_leads_campaign (campaign_id)');
    }
    $leadCampaignFk = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_leads'
           AND CONSTRAINT_NAME = 'fk_marketing_leads_campaign'"
    );
    $leadCampaignFk->execute();
    if ((int) $leadCampaignFk->fetchColumn() === 0) {
        $db->exec(
            'ALTER TABLE marketing_leads ADD CONSTRAINT fk_marketing_leads_campaign
             FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL'
        );
    }

    // ---------- Migrasi v4.0: cabang ----------
    // Skema baru membuat kolom branch_id langsung; database lama perlu
    // ditambahkan agar tetap kompatibel tanpa menghapus data.
    $hasBranchColumn = (bool) $db->query("SHOW COLUMNS FROM classes LIKE 'branch_id'")->fetch();
    if (!$hasBranchColumn) {
        $db->exec('ALTER TABLE classes ADD COLUMN branch_id INT UNSIGNED NULL AFTER curriculum_version_id');
    }
    $hasBranchIndex = (bool) $db->query("SHOW INDEX FROM classes WHERE Key_name = 'idx_classes_branch'")->fetch();
    if (!$hasBranchIndex) {
        $db->exec('ALTER TABLE classes ADD KEY idx_classes_branch (branch_id)');
    }
    $fkStmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'classes'
           AND CONSTRAINT_NAME = 'fk_classes_branch'"
    );
    $fkStmt->execute();
    if ((int) $fkStmt->fetchColumn() === 0) {
        $db->exec(
            'ALTER TABLE classes ADD CONSTRAINT fk_classes_branch
             FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL'
        );
    }

    // ---------- Migrasi v4.0: Phase 6 penilaian ----------
    // Skema baru membuat kolom penilaian langsung; database lama perlu
    // ditambahkan agar data nilai yang sudah ada tetap kompatibel.
    $gradeColumnMigrations = [
        'scheme_id' => 'ALTER TABLE grades ADD COLUMN scheme_id INT UNSIGNED NULL AFTER enrollment_id',
        'final_score' => 'ALTER TABLE grades ADD COLUMN final_score DECIMAL(6,2) NULL AFTER score',
        'status' => "ALTER TABLE grades ADD COLUMN status ENUM('draft', 'incomplete', 'finalized') NOT NULL DEFAULT 'draft' AFTER grade",
        'components_snapshot' => 'ALTER TABLE grades ADD COLUMN components_snapshot LONGTEXT NULL AFTER status',
        'finalized_by' => 'ALTER TABLE grades ADD COLUMN finalized_by INT UNSIGNED NULL AFTER note',
        'finalized_at' => 'ALTER TABLE grades ADD COLUMN finalized_at DATETIME NULL AFTER finalized_by',
        'computed_at' => 'ALTER TABLE grades ADD COLUMN computed_at DATETIME NULL AFTER finalized_at',
    ];
    foreach ($gradeColumnMigrations as $column => $sql) {
        $hasColumn = (bool) $db->query("SHOW COLUMNS FROM grades LIKE " . $db->quote($column))->fetch();
        if (!$hasColumn) {
            $db->exec($sql);
        }
    }
    $gradeIndexMigrations = [
        'idx_grades_scheme' => 'ALTER TABLE grades ADD KEY idx_grades_scheme (scheme_id)',
        'idx_grades_status' => 'ALTER TABLE grades ADD KEY idx_grades_status (status)',
    ];
    foreach ($gradeIndexMigrations as $index => $sql) {
        $hasIndex = (bool) $db->query("SHOW INDEX FROM grades WHERE Key_name = " . $db->quote($index))->fetch();
        if (!$hasIndex) {
            $db->exec($sql);
        }
    }
    $gradeFkMigrations = [
        'fk_grades_scheme' => 'ALTER TABLE grades ADD CONSTRAINT fk_grades_scheme
             FOREIGN KEY (scheme_id) REFERENCES grade_schemes (id) ON DELETE SET NULL',
        'fk_grades_finalized_by' => 'ALTER TABLE grades ADD CONSTRAINT fk_grades_finalized_by
             FOREIGN KEY (finalized_by) REFERENCES users (id) ON DELETE SET NULL',
    ];
    foreach ($gradeFkMigrations as $constraint => $sql) {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grades'
               AND CONSTRAINT_NAME = ?"
        );
        $stmt->execute([$constraint]);
        if ((int) $stmt->fetchColumn() === 0) {
            $db->exec($sql);
        }
    }

    // ---------- Migrasi Fase 4 sesi diingat ----------
    // Versi sesi dipakai untuk membatalkan sesi PHP lama begitu pengguna
    // login di perangkat lain, ganti password, atau dinonaktifkan.
    $hasSessionVersion = (bool) $db->query("SHOW COLUMNS FROM users LIKE 'session_version'")->fetch();
    if (!$hasSessionVersion) {
        $db->exec('ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER status');
        echo "Versi sesi pengguna ditambahkan.\n";
    }
    $hasRevokedReason = (bool) $db->query("SHOW COLUMNS FROM user_devices LIKE 'revoked_reason'")->fetch();
    if (!$hasRevokedReason) {
        $db->exec("ALTER TABLE user_devices ADD COLUMN revoked_reason VARCHAR(40) NOT NULL DEFAULT '' AFTER revoked_at");
        echo "Alasan pencabutan perangkat ditambahkan.\n";
    }

    // ---------- Migrasi Fase 1 absensi karyawan ----------
    // Role `karyawan` hanya untuk absensi; klien baru memakainya pada Fase 3.
    $roleColumn = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if ($roleColumn && strpos((string) $roleColumn['Type'], "'karyawan'") === false) {
        $db->exec(
            "ALTER TABLE users MODIFY role ENUM('admin', 'instruktur', 'peserta', 'karyawan')
             NOT NULL DEFAULT 'peserta'"
        );
        echo "Role karyawan ditambahkan.\n";
    }

    // ---------- Migrasi divisi karyawan ----------
    // Divisi hanya dipakai role karyawan. Kolom teks dipilih agar divisi
    // baru dapat ditambahkan tanpa mengubah enum database.
    $hasDivisionColumn = (bool) $db->query("SHOW COLUMNS FROM users LIKE 'division'")->fetch();
    if (!$hasDivisionColumn) {
        $db->exec("ALTER TABLE users ADD COLUMN division VARCHAR(40) NOT NULL DEFAULT '' AFTER role");
        echo "Kolom divisi karyawan ditambahkan.\n";
    }
    $hasDivisionIndex = (bool) $db->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_division'")->fetch();
    if (!$hasDivisionIndex) {
        $db->exec('ALTER TABLE users ADD KEY idx_users_division (division)');
    }

    // Aturan 1 masuk + 1 pulang per hari dijaga database. Duplikat lama
    // dirapikan lebih dulu (menyisakan baris dengan id terkecil).
    $hasAttendanceUnique = (bool) $db->query(
        "SHOW INDEX FROM staff_attendances WHERE Key_name = 'uq_staff_attendances_user_day_kind'"
    )->fetch();
    if (!$hasAttendanceUnique) {
        $db->exec(
            'DELETE sa1 FROM staff_attendances sa1
             INNER JOIN staff_attendances sa2
               ON sa1.user_id = sa2.user_id
              AND sa1.attendance_date = sa2.attendance_date
              AND sa1.kind = sa2.kind
              AND sa1.id > sa2.id'
        );
        $db->exec(
            'ALTER TABLE staff_attendances
             ADD UNIQUE KEY uq_staff_attendances_user_day_kind (user_id, attendance_date, kind)'
        );
        echo "Aturan absensi harian diterapkan di database.\n";
    }

    // Rentang predikat default mengikuti pemetaan huruf yang sudah dipakai
    // versi sebelumnya, sehingga perilaku lama tetap konsisten.
    $predicateCount = (int) $db->query('SELECT COUNT(*) FROM predicate_ranges')->fetchColumn();
    if ($predicateCount === 0) {
        $predicateStmt = $db->prepare(
            'INSERT INTO predicate_ranges (letter, label, min_score, max_score, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        $predicates = [
            ['A', 'Sangat Baik', 90.00, 100.00, 1],
            ['A-', 'Sangat Baik', 80.00, 89.99, 2],
            ['B+', 'Baik', 75.00, 79.99, 3],
            ['B', 'Baik', 70.00, 74.99, 4],
            ['B-', 'Baik', 65.00, 69.99, 5],
            ['C+', 'Cukup', 60.00, 64.99, 6],
            ['C', 'Cukup', 55.00, 59.99, 7],
            ['D', 'Kurang', 50.00, 54.99, 8],
            ['E', 'Tidak Lulus', 0.00, 49.99, 9],
        ];
        foreach ($predicates as $predicate) {
            $predicateStmt->execute($predicate);
        }
        echo "Rentang predikat default ditambahkan.\n";
    }

    $branchCount = (int) $db->query('SELECT COUNT(*) FROM branches')->fetchColumn();
    if ($branchCount === 0) {
        $branchStmt = $db->prepare(
            "INSERT INTO branches (id, code, name, address, city, phone, manager, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)"
        );
        $branches = [
            [1, 'CBG-JKT', 'Cabang Jakarta', 'Jl. Jenderal Sudirman No. 21', 'Jakarta', '021-5550101', 'Dewi Lestari', 'Kantor pusat dan kelas reguler wilayah Jabodetabek.'],
            [2, 'CBG-BDG', 'Cabang Bandung', 'Jl. Asia Afrika No. 88', 'Bandung', '022-5550202', 'Rangga Pratama', 'Cabang wilayah Bandung dan sekitarnya.'],
            [3, 'CBG-SBY', 'Cabang Surabaya', 'Jl. Basuki Rahmat No. 45', 'Surabaya', '031-5550303', 'Maya Kusuma', 'Cabang wilayah Jawa Timur.'],
        ];
        foreach ($branches as $branch) {
            $branchStmt->execute($branch);
        }
        echo "Cabang awal ditambahkan.\n";
    }

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
        [1, 'ANG-2026-MNJ-01', 'Angkatan 2026 — Pelatihan Manajemen Dasar', 1, 1, 1, 2, '2026-10-01', '2026-12-31', 30, 'Kampus Pusat', 'R-101', 'Senin & Rabu, 09.00-11.00', 32, 'ongoing', 1, 'Kelas migrasi dari enrollment kursus v3.0.'],
    ];
    $classStmt = $db->prepare(
        "INSERT INTO classes
         (id, code, name, course_id, curriculum_version_id, branch_id, instructor_id, period_start, period_end, quota,
          location, room, schedule_note, planned_jp, status, legacy_import, notes, created_by, updated_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
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
            (int) $class[6],
            (string) $class[7],
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

    // ---------- Tugas, pengumpulan, dan ujian (Phase 5) ----------
    $assignmentStmt = $db->prepare(
        "INSERT INTO assignments
         (id, class_id, curriculum_version_id, module_id, lesson_id, title, instructions, type,
          open_at, due_at, max_score, weight, require_file, allowed_types, max_file_size,
          status, created_by, updated_by, created_at, updated_at)
         VALUES (?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 2, 2, ?, ?)"
    );
    $assignments = [
        [
            1, 1, 1,
            'Tugas 1: Analisis Fungsi Manajemen',
            'Pilih satu organisasi yang Anda kenal, lalu analisis bagaimana fungsi perencanaan, pengorganisasian, kepemimpinan, dan pengendalian diterapkan di organisasi tersebut. Tulis dalam 400-600 kata.',
            'text_submission',
            '2026-10-05 09:00:00', '2026-10-12 23:59:59',
            100, 20, 0, '', null,
            'published', '2026-10-05 09:00:00', '2026-10-05 09:00:00',
        ],
        [
            2, 2, 2,
            'Tugas 2: Rencana Kerja Sederhana',
            'Susun rencana kerja sederhana untuk satu kegiatan di tempat kerja Anda. Gunakan format 5W1H dan unggah dokumen dalam format PDF atau DOCX.',
            'file_upload',
            '2026-10-12 09:00:00', '2026-10-26 23:59:59',
            100, 20, 1, 'pdf,doc,docx', 5242880,
            'published', '2026-10-12 09:00:00', '2026-10-12 09:00:00',
        ],
    ];
    foreach ($assignments as $assignment) {
        $assignmentStmt->execute($assignment);
    }

    $rubricStmt = $db->prepare(
        'INSERT INTO assignment_rubrics
         (id, assignment_id, criterion, description, max_score, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $rubrics = [
        [1, 1, 'Kejelasan analisis', 'Analisis fungsi manajemen dijelaskan dengan contoh konkret.', 40, 1, '2026-10-05 09:00:00'],
        [2, 1, 'Keterkaitan teori', 'Mengaitkan praktik organisasi dengan konsep manajemen yang dipelajari.', 30, 2, '2026-10-05 09:00:00'],
        [3, 1, 'Kerapian penulisan', 'Struktur tulisan runtut, ejaan benar, dan panjang sesuai ketentuan.', 30, 3, '2026-10-05 09:00:00'],
    ];
    foreach ($rubrics as $rubric) {
        $rubricStmt->execute($rubric);
    }

    $submissionStmt = $db->prepare(
        "INSERT INTO submissions
         (id, assignment_id, enrollment_id, student_id, status, text_answer, submitted_at, is_late,
          score, feedback, reviewed_by, reviewed_at, revision_count, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $submissionStmt->execute([
        1, 1, 1, 5, 'reviewed',
        'Organisasi yang saya amati adalah kantor tempat saya bekerja. Fungsi perencanaan terlihat dari penyusunan target bulanan. Pengorganisasian dilakukan dengan pembagian tugas per divisi. Kepemimpinan tampak pada arahan harian kepala divisi. Pengendalian dilakukan melalui evaluasi mingguan.',
        '2026-10-10 20:15:00', 0,
        85,
        'Analisis sudah baik dan contohnya relevan. Tambahkan data pendukung pada bagian pengendalian agar lebih kuat.',
        2, '2026-10-13 10:00:00', 0,
        '2026-10-10 20:15:00', '2026-10-13 10:00:00',
    ]);

    $reviewStmt = $db->prepare(
        'INSERT INTO submission_reviews (id, submission_id, reviewer_id, action, score, note, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $reviewStmt->execute([1, 1, 5, 'submit', null, 'Mengumpulkan tugas.', '2026-10-10 20:15:00']);
    $reviewStmt->execute([
        2, 1, 2, 'score', 85,
        'Analisis sudah baik dan contohnya relevan. Tambahkan data pendukung pada bagian pengendalian agar lebih kuat.',
        '2026-10-13 10:00:00',
    ]);

    $examStmt = $db->prepare(
        "INSERT INTO exams
         (id, class_id, curriculum_version_id, title, description, type, exam_date, start_time,
          duration_minutes, max_score, weight, instructions, status, created_by, updated_by,
          created_at, updated_at)
         VALUES (?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 2, 2, ?, ?)"
    );
    $exams = [
        [
            1,
            'Ujian Tengah Semester: Dasar Manajemen',
            'Evaluasi pemahaman konsep dasar manajemen, perencanaan, dan pengorganisasian.',
            'written', '2026-10-20', '09:00:00', 90, 100, 30,
            'Kerjakan 30 soal pilihan ganda dan 2 soal esai. Buku tertutup.',
            'published', '2026-10-01 08:00:00', '2026-10-01 08:00:00',
        ],
        [
            2,
            'Ujian Praktik: Studi Kasus Manajemen',
            'Peserta menyelesaikan studi kasus dan mempresentasikan solusinya.',
            'practical', '2026-12-15', '09:00:00', 120, 100, 30,
            'Presentasikan solusi studi kasus dalam 10 menit dan jawab pertanyaan penguji.',
            'draft', '2026-10-15 08:00:00', '2026-10-15 08:00:00',
        ],
    ];
    foreach ($exams as $exam) {
        $examStmt->execute($exam);
    }

    $participantStmt = $db->prepare(
        "INSERT INTO exam_participants
         (id, exam_id, enrollment_id, student_id, status, note, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $participants = [
        [1, 1, 1, 5, 'attended', '', '2026-10-01 08:00:00', '2026-10-20 11:00:00'],
        [2, 1, 2, 6, 'attended', '', '2026-10-01 08:00:00', '2026-10-20 11:00:00'],
        [3, 1, 3, 7, 'absent', 'Tidak hadir tanpa keterangan.', '2026-10-01 08:00:00', '2026-10-20 11:00:00'],
        [4, 2, 1, 5, 'registered', '', '2026-10-15 08:00:00', '2026-10-15 08:00:00'],
        [5, 2, 2, 6, 'registered', '', '2026-10-15 08:00:00', '2026-10-15 08:00:00'],
        [6, 2, 3, 7, 'registered', '', '2026-10-15 08:00:00', '2026-10-15 08:00:00'],
    ];
    foreach ($participants as $participant) {
        $participantStmt->execute($participant);
    }

    $examResultStmt = $db->prepare(
        "INSERT INTO exam_results
         (id, exam_id, enrollment_id, student_id, score, status, graded_by, graded_at, note, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $examResults = [
        [1, 1, 1, 5, 88, 'graded', 2, '2026-10-25 10:00:00', 'Pemahaman konsep baik.', '2026-10-01 08:00:00', '2026-10-25 10:00:00'],
        [2, 1, 2, 6, 80, 'graded', 2, '2026-10-25 10:05:00', 'Perlu memperdalam bagian pengendalian.', '2026-10-01 08:00:00', '2026-10-25 10:05:00'],
        [3, 1, 3, 7, null, 'missing', null, null, 'Tidak hadir tanpa keterangan.', '2026-10-01 08:00:00', '2026-10-20 11:00:00'],
        [4, 2, 1, 5, null, 'not_assessed', null, null, '', '2026-10-15 08:00:00', '2026-10-15 08:00:00'],
        [5, 2, 2, 6, null, 'not_assessed', null, null, '', '2026-10-15 08:00:00', '2026-10-15 08:00:00'],
        [6, 2, 3, 7, null, 'not_assessed', null, null, '', '2026-10-15 08:00:00', '2026-10-15 08:00:00'],
    ];
    foreach ($examResults as $examResult) {
        $examResultStmt->execute($examResult);
    }

    // ---------- Skema penilaian Phase 6 (demo) ----------
    $schemeStmt = $db->prepare(
        "INSERT INTO grade_schemes
         (id, course_id, class_id, name, status, pass_score, rounding, notes, created_by, updated_by, created_at, updated_at)
         VALUES (1, 1, 1, ?, 'published', 60.00, 'half_up', ?, 1, 1, ?, ?)"
    );
    $schemeStmt->execute([
        'Skema Penilaian Pelatihan Manajemen Dasar',
        'Template bobot: tugas 30%, ujian 40%, penilaian manual 30%.',
        '2026-10-01 08:00:00',
        '2026-10-01 08:00:00',
    ]);

    $componentStmt = $db->prepare(
        'INSERT INTO grade_scheme_components
         (id, scheme_id, type, name, description, weight, aggregation, max_score, is_required, sort_order, created_at, updated_at)
         VALUES (?, 1, ?, ?, ?, ?, ?, 100.00, 1, ?, ?, ?)'
    );
    $components = [
        [1, 'assignment', 'Tugas', 'Rata-rata nilai tugas kelas.', 30.00, 'weighted', 1, '2026-10-01 08:00:00', '2026-10-01 08:00:00'],
        [2, 'exam', 'Ujian', 'Rata-rata nilai ujian kelas.', 40.00, 'weighted', 2, '2026-10-01 08:00:00', '2026-10-01 08:00:00'],
        [3, 'manual', 'Praktik & Partisipasi', 'Penilaian langsung oleh instruktur.', 30.00, 'average', 3, '2026-10-01 08:00:00', '2026-10-01 08:00:00'],
    ];
    foreach ($components as $component) {
        $componentStmt->execute($component);
    }

    $manualStmt = $db->prepare(
        'INSERT INTO grade_component_scores
         (id, component_id, enrollment_id, student_id, score, status, assessor_id, assessed_at, note, created_at, updated_at)
         VALUES (?, 3, ?, ?, ?, ?, 2, ?, ?, ?, ?)'
    );
    $manualScores = [
        [1, 1, 5, 90.00, 'graded', '2026-10-28 10:00:00', 'Praktik kerja rapi dan komunikatif.', '2026-10-28 10:00:00', '2026-10-28 10:00:00'],
        [2, 2, 6, 85.00, 'graded', '2026-10-28 10:05:00', 'Pemahaman konsep baik, partisipasi aktif.', '2026-10-28 10:05:00', '2026-10-28 10:05:00'],
    ];
    foreach ($manualScores as $manualScore) {
        $manualStmt->execute($manualScore);
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
