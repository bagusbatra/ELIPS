<?php
// Direktori: api/tutor_portal.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - portal tentor
//
// Aturan bisnis:
//  - Kursus bersifat tetap; pengulangannya adalah angkatan (tabel classes).
//    Satu angkatan = satu tentor.
//  - Struktur kurikulum (modul dan judul sesi/lesson) diatur admin. Tentor
//    hanya mengisi ISI sesi: tujuan, materi, penugasan, dan durasi.
//  - Sesi ke-N dianggap terkunci bila pertemuan ke-N sebuah angkatan yang
//    memakai kurikulum yang sama (status ready/ongoing) sudah dikonfirmasi
//    admin (class_sessions.status confirmed/completed).
//  - Penugasan yang diisi tentor menjadi template di lesson_tasks, lalu
//    disalin ke tabel assignments tiap angkatan yang sesinya belum terkunci.
//  - Penilaian akhir per siswa memakai struktur materi/sub materi bebas.
//    Struktur terakhir yang tersimpan menjadi template kursus (final_templates)
//    dan dipakai siswa lain serta angkatan berikutnya. Nilai siswa menyimpan
//    salinan struktur saat disimpan, sehingga perubahan template tidak
//    mengubah nilai siswa yang sudah tersimpan.
// ============================================================

const ELIPS_TP_ACTIVE_STATUSES = ['open', 'ready', 'ongoing'];
const ELIPS_TP_TASK_FORMATS = ['file_upload', 'text_submission', 'in_class_practice', 'external_reference'];
const ELIPS_TP_MATERIAL_KINDS = ['teks', 'dokumen', 'video', 'tautan'];

/** Buat tabel pendukung portal tentor (aman dipanggil berulang). */
function elips_ensure_tp_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $db = elips_db();

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `lesson_details` (
    `lesson_id` INT UNSIGNED NOT NULL,
    `objective` TEXT NOT NULL,
    `duration_jp` DECIMAL(5,2) NULL,
    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`lesson_id`),
    CONSTRAINT `fk_lesson_details_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `lesson_materials` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lesson_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `kind` ENUM('teks', 'dokumen', 'video', 'tautan') NOT NULL DEFAULT 'teks',
    `content` TEXT NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_lesson_materials_lesson` (`lesson_id`, `sort_order`),
    CONSTRAINT `fk_lesson_materials_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `lesson_tasks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lesson_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `instructions` TEXT NOT NULL,
    `due_days` SMALLINT UNSIGNED NOT NULL DEFAULT 7,
    `format` ENUM('file_upload', 'text_submission', 'in_class_practice', 'external_reference') NOT NULL DEFAULT 'file_upload',
    `max_score` DECIMAL(6,2) NOT NULL DEFAULT 100.00,
    `weight` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_lesson_tasks_lesson` (`lesson_id`, `sort_order`),
    CONSTRAINT `fk_lesson_tasks_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $col = $db->query("SHOW COLUMNS FROM assignments LIKE 'template_task_id'")->fetch();
    if (!$col) {
        $db->exec('ALTER TABLE assignments ADD COLUMN `template_task_id` INT UNSIGNED NULL, ADD KEY `idx_assignments_template_task` (`template_task_id`)');
    }

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `curriculum_change_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id` INT UNSIGNED NOT NULL,
    `curriculum_version_id` INT UNSIGNED NOT NULL,
    `lesson_id` INT UNSIGNED NULL,
    `lesson_title` VARCHAR(200) NOT NULL DEFAULT '',
    `actor_id` INT UNSIGNED NULL,
    `actor_name` VARCHAR(150) NOT NULL DEFAULT '',
    `summary` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_curriculum_log_course` (`course_id`, `created_at`),
    KEY `idx_curriculum_log_version` (`curriculum_version_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `final_templates` (
    `course_id` INT UNSIGNED NOT NULL,
    `structure` LONGTEXT NOT NULL,
    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`course_id`),
    CONSTRAINT `fk_final_templates_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `final_grades` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `class_id` INT UNSIGNED NOT NULL,
    `enrollment_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `structure` LONGTEXT NOT NULL,
    `average` DECIMAL(6,2) NULL,
    `predicate` VARCHAR(8) NOT NULL DEFAULT '',
    `total_jp` DECIMAL(7,2) NOT NULL DEFAULT 0,
    `item_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `filled_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'complete') NOT NULL DEFAULT 'draft',
    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_final_grades_enrollment` (`class_id`, `enrollment_id`),
    KEY `idx_final_grades_status` (`class_id`, `status`),
    CONSTRAINT `fk_final_grades_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_final_grades_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `final_dispatches` (
    `class_id` INT UNSIGNED NOT NULL,
    `sent_by` INT UNSIGNED NULL,
    `sent_at` DATETIME NOT NULL,
    `student_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`class_id`),
    CONSTRAINT `fk_final_dispatches_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $done = true;
}

// ---------- Pembantu umum ----------

function elips_tp_user(): array
{
    $user = elips_require_login();
    if ($user['role'] !== 'instruktur') {
        throw new ElipsError('Halaman ini khusus tentor.');
    }
    elips_ensure_tp_schema();
    return $user;
}

function elips_tp_today(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
}

function elips_tp_in(array $ids): string
{
    return implode(',', array_fill(0, count($ids), '?'));
}

function elips_tp_group(string $status): string
{
    if ($status === 'ongoing') {
        return 'berjalan';
    }
    if ($status === 'open' || $status === 'ready') {
        return 'pendaftaran';
    }
    if ($status === 'completed') {
        return 'selesai';
    }
    return 'lain';
}

/** Angkatan milik tentor (atau ElipsError). */
function elips_tp_class(int $classId, array $user): array
{
    $stmt = elips_db()->prepare(
        'SELECT c.*, co.title AS course_title, cv.code AS version_code, cv.name AS version_name
         FROM classes c
         JOIN courses co ON co.id = c.course_id
         JOIN curriculum_versions cv ON cv.id = c.curriculum_version_id
         WHERE c.id = ? AND c.instructor_id = ?'
    );
    $stmt->execute([$classId, (int) $user['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new ElipsError('Angkatan tidak ditemukan atau bukan angkatan Anda.');
    }
    return $row;
}

/** Angkatan yang sudah ditutup admin hanya bisa dilihat tentor. */
function elips_tp_assert_writable(array $class): void
{
    if (($class['status'] ?? '') === 'completed') {
        throw new ElipsError('Pembelajaran angkatan ini sudah ditutup admin, data hanya bisa dilihat.');
    }
}

/** Nilai akhir hanya bisa disimpan/dikirim setelah admin menutup pembelajaran. */
function elips_tp_assert_final_open(array $class): void
{
    if (($class['status'] ?? '') !== 'completed') {
        throw new ElipsError('Nilai akhir bisa diisi setelah admin menutup pembelajaran angkatan ini.');
    }
}

function elips_tp_class_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'code' => (string) $row['code'],
        'name' => (string) $row['name'],
        'courseId' => (int) $row['course_id'],
        'courseTitle' => (string) ($row['course_title'] ?? ''),
        'versionId' => (int) $row['curriculum_version_id'],
        'versionCode' => (string) ($row['version_code'] ?? ''),
        'status' => (string) $row['status'],
        'group' => elips_tp_group((string) $row['status']),
        'periodStart' => $row['period_start'],
        'periodEnd' => $row['period_end'],
        'quota' => (int) $row['quota'],
        'location' => (string) $row['location'],
        'room' => (string) $row['room'],
    ];
}

/** Daftar sesi (lesson) satu versi kurikulum, bernomor urut 1..n. */
function elips_tp_lessons(int $versionId): array
{
    elips_ensure_module_lessons($versionId);
    $stmt = elips_db()->prepare(
        'SELECT l.id, l.module_id, l.title, m.title AS module_title
         FROM lessons l
         JOIN modules m ON m.id = l.module_id
         WHERE l.curriculum_version_id = ?
         ORDER BY m.sort_order ASC, m.id ASC, l.sort_order ASC, l.id ASC'
    );
    $stmt->execute([$versionId]);
    $rows = $stmt->fetchAll();
    $number = 0;
    foreach ($rows as &$row) {
        $number++;
        $row['id'] = (int) $row['id'];
        $row['module_id'] = (int) $row['module_id'];
        $row['number'] = $number;
    }
    unset($row);
    return $rows;
}

/** Nomor sesi yang terkunci pada satu versi: nomor => [nama angkatan]. */
function elips_tp_locked_numbers(int $versionId): array
{
    $stmt = elips_db()->prepare(
        "SELECT cs.session_number, c.name
         FROM class_sessions cs
         JOIN classes c ON c.id = cs.class_id
         WHERE c.curriculum_version_id = ? AND c.status IN ('ready', 'ongoing')
           AND cs.status IN ('confirmed', 'completed')
         ORDER BY cs.session_number ASC, c.name ASC"
    );
    $stmt->execute([$versionId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['session_number']][] = (string) $row['name'];
    }
    return $map;
}

/** Ringkasan angka per angkatan: siswa, sesi, tugas, nilai. @return array<int,array> */
function elips_tp_summaries(array $classIds): array
{
    $result = [];
    foreach ($classIds as $id) {
        $result[(int) $id] = [
            'students' => 0, 'sessionsTotal' => 0, 'sessionsConfirmed' => 0,
            'submissions' => 0, 'pending' => 0, 'graded' => 0, 'average' => null,
            'tasks' => 0, 'finalSent' => false, 'finalComplete' => 0,
            'studentsReady' => 0, 'lessonsTotal' => 0,
        ];
    }
    if (!$classIds) {
        return $result;
    }
    $db = elips_db();
    $in = elips_tp_in($classIds);

    $stmt = $db->prepare("SELECT class_id, COUNT(*) AS n FROM enrollments WHERE class_id IN ($in) AND status IN ('active', 'pending', 'completed') GROUP BY class_id");
    $stmt->execute($classIds);
    foreach ($stmt->fetchAll() as $row) {
        $result[(int) $row['class_id']]['students'] = (int) $row['n'];
    }

    $stmt = $db->prepare(
        "SELECT class_id, SUM(status <> 'cancelled') AS total, SUM(status IN ('confirmed', 'completed')) AS done
         FROM class_sessions WHERE class_id IN ($in) GROUP BY class_id"
    );
    $stmt->execute($classIds);
    foreach ($stmt->fetchAll() as $row) {
        $result[(int) $row['class_id']]['sessionsTotal'] = (int) $row['total'];
        $result[(int) $row['class_id']]['sessionsConfirmed'] = (int) $row['done'];
    }

    $stmt = $db->prepare("SELECT class_id, COUNT(*) AS n FROM assignments WHERE class_id IN ($in) AND status IN ('published', 'closed') GROUP BY class_id");
    $stmt->execute($classIds);
    foreach ($stmt->fetchAll() as $row) {
        $result[(int) $row['class_id']]['tasks'] = (int) $row['n'];
    }

    $stmt = $db->prepare(
        "SELECT a.class_id, COUNT(s.id) AS total,
                SUM(s.score IS NULL AND s.status IN ('submitted', 'late', 'resubmitted')) AS pending,
                SUM(s.score IS NOT NULL) AS graded,
                AVG(CASE WHEN s.score IS NOT NULL AND a.max_score > 0 THEN s.score / a.max_score * 100 END) AS avg_score
         FROM assignments a
         JOIN submissions s ON s.assignment_id = a.id
         WHERE a.class_id IN ($in) AND a.status IN ('published', 'closed')
         GROUP BY a.class_id"
    );
    $stmt->execute($classIds);
    foreach ($stmt->fetchAll() as $row) {
        $item = &$result[(int) $row['class_id']];
        $item['submissions'] = (int) $row['total'];
        $item['pending'] = (int) $row['pending'];
        $item['graded'] = (int) $row['graded'];
        $item['average'] = $row['avg_score'] === null ? null : round((float) $row['avg_score'], 1);
        unset($item);
    }

    $stmt = $db->prepare(
        "SELECT e.class_id, COUNT(*) AS n FROM enrollments e JOIN classes c ON c.id = e.class_id
         WHERE e.class_id IN ($in) AND e.status IN ('active', 'completed')
           AND (SELECT COUNT(*) FROM lessons l2 WHERE l2.curriculum_version_id = c.curriculum_version_id) > 0
           AND (SELECT COUNT(*) FROM class_lesson_progress p JOIN lessons l ON l.id = p.lesson_id
                WHERE p.enrollment_id = e.id AND l.curriculum_version_id = c.curriculum_version_id)
               >= (SELECT COUNT(*) FROM lessons l2 WHERE l2.curriculum_version_id = c.curriculum_version_id)
         GROUP BY e.class_id"
    );
    $stmt->execute($classIds);
    foreach ($stmt->fetchAll() as $row) {
        $result[(int) $row['class_id']]['studentsReady'] = (int) $row['n'];
    }
    $stmt = $db->prepare(
        "SELECT c.id AS class_id, COUNT(l.id) AS n FROM classes c JOIN lessons l ON l.curriculum_version_id = c.curriculum_version_id
         WHERE c.id IN ($in) GROUP BY c.id"
    );
    $stmt->execute($classIds);
    foreach ($stmt->fetchAll() as $row) {
        $result[(int) $row['class_id']]['lessonsTotal'] = (int) $row['n'];
    }

    $stmt = $db->prepare("SELECT class_id FROM final_dispatches WHERE class_id IN ($in)");
    $stmt->execute($classIds);
    foreach ($stmt->fetchAll() as $row) {
        $result[(int) $row['class_id']]['finalSent'] = true;
    }
    $stmt = $db->prepare("SELECT class_id, COUNT(*) AS n FROM final_grades WHERE class_id IN ($in) AND status = 'complete' GROUP BY class_id");
    $stmt->execute($classIds);
    foreach ($stmt->fetchAll() as $row) {
        $result[(int) $row['class_id']]['finalComplete'] = (int) $row['n'];
    }
    return $result;
}

function elips_tp_final_state(array $summary, string $classStatus = ''): string
{
    if ($summary['finalSent']) {
        return 'sent';
    }
    if ($classStatus === 'completed' && $summary['studentsReady'] > 0) {
        return 'open';
    }
    return 'locked';
}

function elips_tp_class_card(array $row, array $summary): array
{
    $card = elips_tp_class_public($row);
    $card['students'] = $summary['students'];
    $card['sessionsTotal'] = $summary['sessionsTotal'];
    $card['sessionsConfirmed'] = $summary['sessionsConfirmed'];
    $card['tasks'] = $summary['tasks'];
    $card['submissions'] = $summary['submissions'];
    $card['pending'] = $summary['pending'];
    $card['graded'] = $summary['graded'];
    $card['average'] = $summary['average'];
    $card['finalState'] = elips_tp_final_state($summary, (string) $row['status']);
    $card['finalComplete'] = $summary['finalComplete'];
    $card['studentsReady'] = $summary['studentsReady'];
    $card['lessonsTotal'] = $summary['lessonsTotal'];
    return $card;
}

/** Semua angkatan milik tentor beserta ringkasannya. */
function elips_tp_my_classes(array $user, ?array $statuses = null): array
{
    $sql = 'SELECT c.*, co.title AS course_title, cv.code AS version_code
            FROM classes c
            JOIN courses co ON co.id = c.course_id
            JOIN curriculum_versions cv ON cv.id = c.curriculum_version_id
            WHERE c.instructor_id = ?';
    $params = [(int) $user['id']];
    if ($statuses) {
        $sql .= ' AND c.status IN (' . elips_tp_in($statuses) . ')';
        $params = array_merge($params, $statuses);
    } else {
        $sql .= " AND c.status NOT IN ('draft', 'cancelled', 'archived')";
    }
    $sql .= ' ORDER BY FIELD(c.status, \'ongoing\', \'ready\', \'open\', \'completed\'), c.period_start DESC, c.id DESC';
    $stmt = elips_db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $summaries = elips_tp_summaries(array_map(static fn ($r) => (int) $r['id'], $rows));
    $cards = [];
    foreach ($rows as $row) {
        $cards[] = elips_tp_class_card($row, $summaries[(int) $row['id']]);
    }
    return $cards;
}

// ---------- Dashboard & angkatan ----------

function elips_action_tp_dashboard(array $p): array
{
    $user = elips_tp_user();
    $db = elips_db();
    $classes = elips_tp_my_classes($user);
    $active = array_values(array_filter($classes, static fn ($c) => in_array($c['status'], ELIPS_TP_ACTIVE_STATUSES, true)));

    $courseIds = [];
    $pending = 0;
    foreach ($active as $c) {
        $courseIds[$c['courseId']] = true;
        $pending += $c['pending'];
    }

    $today = elips_tp_today();
    $stmt = $db->prepare(
        "SELECT cs.id, cs.class_id, cs.session_number, cs.title, cs.start_time, cs.end_time, cs.status, c.name AS class_name
         FROM class_sessions cs
         JOIN classes c ON c.id = cs.class_id
         WHERE c.instructor_id = ? AND cs.session_date = ? AND cs.status <> 'cancelled'
         ORDER BY cs.start_time ASC, cs.id ASC"
    );
    $stmt->execute([(int) $user['id'], $today]);
    $todaySessions = [];
    foreach ($stmt->fetchAll() as $row) {
        $todaySessions[] = [
            'id' => (int) $row['id'],
            'classId' => (int) $row['class_id'],
            'className' => (string) $row['class_name'],
            'number' => (int) $row['session_number'],
            'title' => (string) $row['title'],
            'start' => $row['start_time'] ? substr((string) $row['start_time'], 0, 5) : '',
            'end' => $row['end_time'] ? substr((string) $row['end_time'], 0, 5) : '',
            'confirmed' => in_array($row['status'], ['confirmed', 'completed'], true),
        ];
    }

    // Sesi yang belum punya penugasan dan masih bisa diedit.
    $needsTask = [];
    $seenVersions = [];
    foreach ($active as $c) {
        if (isset($seenVersions[$c['versionId']])) {
            continue;
        }
        $seenVersions[$c['versionId']] = true;
        $locked = elips_tp_locked_numbers($c['versionId']);
        $withTask = [];
        $stmt = $db->prepare(
            'SELECT l.id FROM lessons l WHERE l.curriculum_version_id = ?
               AND (EXISTS (SELECT 1 FROM lesson_tasks t WHERE t.lesson_id = l.id)
                 OR EXISTS (SELECT 1 FROM assignments a WHERE a.lesson_id = l.id))'
        );
        $stmt->execute([$c['versionId']]);
        foreach ($stmt->fetchAll() as $r) {
            $withTask[(int) $r['id']] = true;
        }
        foreach (elips_tp_lessons($c['versionId']) as $lesson) {
            if (isset($locked[$lesson['number']]) || isset($withTask[$lesson['id']])) {
                continue;
            }
            $needsTask[] = [
                'courseId' => $c['courseId'], 'versionId' => $c['versionId'], 'lessonId' => $lesson['id'],
                'number' => $lesson['number'], 'title' => (string) $lesson['title'], 'className' => $c['name'],
            ];
            if (count($needsTask) >= 5) {
                break 2;
            }
        }
    }

    $user = elips_user_row((int) $user['id']);
    return elips_ok('', [
        'name' => (string) $user['name'],
        'today' => $today,
        'stats' => [
            'classes' => count($active),
            'sessionsToday' => count($todaySessions),
            'courses' => count($courseIds),
            'pending' => $pending,
        ],
        'todaySessions' => $todaySessions,
        'classes' => $active,
        'needsTask' => $needsTask,
        'finalOpen' => array_values(array_filter($classes, static fn ($c) => $c['finalState'] === 'open')),
    ]);
}

function elips_action_tp_classes(array $p): array
{
    $user = elips_tp_user();
    return elips_ok('', ['classes' => elips_tp_my_classes($user)]);
}

function elips_action_tp_class_detail(array $p): array
{
    $user = elips_tp_user();
    $db = elips_db();
    $class = elips_tp_class((int) ($p['classId'] ?? 0), $user);
    $classId = (int) $class['id'];
    $summary = elips_tp_summaries([$classId])[$classId];
    $card = elips_tp_class_card($class, $summary);

    // Sesi: kurikulum + jadwal angkatan.
    $stmt = $db->prepare('SELECT * FROM class_sessions WHERE class_id = ?');
    $stmt->execute([$classId]);
    $bySession = [];
    foreach ($stmt->fetchAll() as $row) {
        $bySession[(int) $row['session_number']] = $row;
    }
    $details = elips_tp_lesson_details(array_map(static fn ($l) => $l['id'], elips_tp_lessons((int) $class['curriculum_version_id'])));
    $sessions = [];
    foreach (elips_tp_lessons((int) $class['curriculum_version_id']) as $lesson) {
        $cs = $bySession[$lesson['number']] ?? null;
        $locked = $cs && in_array($cs['status'], ['confirmed', 'completed'], true);
        $detail = $details[$lesson['id']] ?? ['objective' => '', 'durationJp' => null, 'materials' => 0, 'tasks' => 0];
        $sessions[] = [
            'lessonId' => $lesson['id'],
            'number' => $lesson['number'],
            'title' => (string) $lesson['title'],
            'moduleTitle' => (string) $lesson['module_title'],
            'date' => $cs ? $cs['session_date'] : null,
            'start' => $cs && $cs['start_time'] ? substr((string) $cs['start_time'], 0, 5) : '',
            'locked' => (bool) $locked,
            'scheduled' => (bool) $cs,
            'materials' => $detail['materials'],
            'tasks' => $detail['tasks'],
            'durationJp' => $detail['durationJp'] ?? ($cs ? (float) $cs['planned_jp'] : null),
        ];
    }

    $stmt = $db->prepare(
        "SELECT e.id AS enrollment_id, e.status, u.id AS student_id, u.name, u.email
         FROM enrollments e JOIN users u ON u.id = e.student_id
         WHERE e.class_id = ? AND e.status IN ('active', 'pending', 'completed')
         ORDER BY u.name ASC"
    );
    $stmt->execute([$classId]);
    $enrolls = $stmt->fetchAll();

    // Rata-rata tugas per siswa.
    $avg = [];
    $stmt = $db->prepare(
        "SELECT s.enrollment_id, COUNT(*) AS n,
                SUM(s.score IS NULL AND s.status IN ('submitted', 'late', 'resubmitted')) AS pending,
                AVG(CASE WHEN s.score IS NOT NULL AND a.max_score > 0 THEN s.score / a.max_score * 100 END) AS avg_score
         FROM submissions s JOIN assignments a ON a.id = s.assignment_id
         WHERE a.class_id = ? AND a.status IN ('published', 'closed') GROUP BY s.enrollment_id"
    );
    $stmt->execute([$classId]);
    foreach ($stmt->fetchAll() as $row) {
        $avg[(int) $row['enrollment_id']] = $row;
    }
    $students = [];
    foreach ($enrolls as $row) {
        $a = $avg[(int) $row['enrollment_id']] ?? null;
        $students[] = [
            'enrollmentId' => (int) $row['enrollment_id'],
            'studentId' => (int) $row['student_id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'status' => (string) $row['status'],
            'submitted' => $a ? (int) $a['n'] : 0,
            'pending' => $a ? (int) $a['pending'] : 0,
            'average' => $a && $a['avg_score'] !== null ? round((float) $a['avg_score'], 1) : null,
        ];
    }

    $tasks = elips_tp_task_rows($classId, (int) $class['curriculum_version_id']);

    return elips_ok('', [
        'class' => $card,
        'sessions' => $sessions,
        'students' => $students,
        'tasks' => $tasks,
    ]);
}

/** Isi sesi ringkas per lesson: tujuan, durasi, jumlah materi dan tugas. */
function elips_tp_lesson_details(array $lessonIds): array
{
    $out = [];
    if (!$lessonIds) {
        return $out;
    }
    $db = elips_db();
    $in = elips_tp_in($lessonIds);
    foreach ($lessonIds as $id) {
        $out[(int) $id] = ['objective' => '', 'durationJp' => null, 'materials' => 0, 'tasks' => 0];
    }
    $stmt = $db->prepare("SELECT lesson_id, objective, duration_jp FROM lesson_details WHERE lesson_id IN ($in)");
    $stmt->execute($lessonIds);
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['lesson_id']]['objective'] = (string) $row['objective'];
        $out[(int) $row['lesson_id']]['durationJp'] = $row['duration_jp'] === null ? null : (float) $row['duration_jp'];
    }
    $stmt = $db->prepare("SELECT lesson_id, COUNT(*) AS n FROM lesson_materials WHERE lesson_id IN ($in) GROUP BY lesson_id");
    $stmt->execute($lessonIds);
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['lesson_id']]['materials'] = (int) $row['n'];
    }
    $stmt = $db->prepare("SELECT lesson_id, COUNT(*) AS n FROM lesson_tasks WHERE lesson_id IN ($in) GROUP BY lesson_id");
    $stmt->execute($lessonIds);
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['lesson_id']]['tasks'] = (int) $row['n'];
    }
    return $out;
}

/** Penugasan sebuah angkatan beserta hitungan pengumpulan. */
function elips_tp_task_rows(int $classId, int $versionId): array
{
    $numbers = [];
    foreach (elips_tp_lessons($versionId) as $lesson) {
        $numbers[$lesson['id']] = $lesson['number'];
    }
    $stmt = elips_db()->prepare(
        "SELECT a.*,
                (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS total,
                (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.score IS NULL AND s.status IN ('submitted', 'late', 'resubmitted')) AS pending,
                (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.score IS NOT NULL) AS graded,
                (SELECT AVG(s.score / NULLIF(a.max_score, 0) * 100) FROM submissions s WHERE s.assignment_id = a.id AND s.score IS NOT NULL) AS avg_score
         FROM assignments a
         WHERE a.class_id = ? AND a.status IN ('published', 'closed')
         ORDER BY a.lesson_id IS NULL, a.id ASC"
    );
    $stmt->execute([$classId]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $now = gmdate('Y-m-d H:i:s');
        $notOpen = $row['open_at'] !== null && $row['open_at'] > $now;
        $rows[] = [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'sessionNumber' => $row['lesson_id'] !== null ? ($numbers[(int) $row['lesson_id']] ?? null) : null,
            'dueAt' => $row['due_at'],
            'maxScore' => (float) $row['max_score'],
            'status' => (string) $row['status'],
            'notOpen' => $notOpen,
            'total' => (int) $row['total'],
            'pending' => (int) $row['pending'],
            'graded' => (int) $row['graded'],
            'average' => $row['avg_score'] === null ? null : round((float) $row['avg_score'], 1),
        ];
    }
    return $rows;
}

// ---------- Kurikulum ----------

/** Versi kurikulum yang dapat diedit tentor pada sebuah kursus. */
function elips_tp_editable_versions(array $user, int $courseId): array
{
    $stmt = elips_db()->prepare(
        "SELECT c.id, c.name, c.status, c.curriculum_version_id, cv.code, cv.name AS version_name, cv.status AS version_status
         FROM classes c JOIN curriculum_versions cv ON cv.id = c.curriculum_version_id
         WHERE c.instructor_id = ? AND c.course_id = ? AND c.status IN ('open', 'ready', 'ongoing')
         ORDER BY c.period_start DESC, c.id DESC"
    );
    $stmt->execute([(int) $user['id'], $courseId]);
    $versions = [];
    foreach ($stmt->fetchAll() as $row) {
        $vid = (int) $row['curriculum_version_id'];
        if (!isset($versions[$vid])) {
            $versions[$vid] = [
                'id' => $vid, 'code' => (string) $row['code'], 'name' => (string) $row['version_name'],
                'editable' => $row['version_status'] !== 'retired',
                'classes' => [],
            ];
        }
        $versions[$vid]['classes'][] = [
            'id' => (int) $row['id'], 'name' => (string) $row['name'],
            'status' => (string) $row['status'], 'group' => elips_tp_group((string) $row['status']),
        ];
    }
    return array_values($versions);
}

function elips_action_tp_curriculum_courses(array $p): array
{
    $user = elips_tp_user();
    $db = elips_db();
    $stmt = $db->prepare(
        'SELECT DISTINCT co.id, co.title, co.category
         FROM courses co
         LEFT JOIN classes c ON c.course_id = co.id AND c.instructor_id = ?
         WHERE co.instructor_id = ? OR c.id IS NOT NULL
         ORDER BY co.title ASC'
    );
    $stmt->execute([(int) $user['id'], (int) $user['id']]);
    $cards = [];
    foreach ($stmt->fetchAll() as $row) {
        $courseId = (int) $row['id'];
        $versions = elips_tp_editable_versions($user, $courseId);
        $modules = 0;
        $sessions = 0;
        $versionForCount = $versions[0]['id'] ?? null;
        if ($versionForCount === null) {
            $s = $db->prepare('SELECT id FROM curriculum_versions WHERE course_id = ? ORDER BY FIELD(status, \'published\', \'locked\', \'draft\', \'retired\'), id DESC LIMIT 1');
            $s->execute([$courseId]);
            $versionForCount = (int) ($s->fetchColumn() ?: 0);
        }
        if ($versionForCount) {
            $s = $db->prepare('SELECT COUNT(*) FROM modules WHERE curriculum_version_id = ?');
            $s->execute([$versionForCount]);
            $modules = (int) $s->fetchColumn();
            $s = $db->prepare('SELECT COUNT(*) FROM lessons WHERE curriculum_version_id = ?');
            $s->execute([$versionForCount]);
            $sessions = (int) $s->fetchColumn();
        }
        $classes = [];
        foreach ($versions as $v) {
            foreach ($v['classes'] as $cl) {
                $classes[] = $cl;
            }
        }
        $cards[] = [
            'courseId' => $courseId, 'title' => (string) $row['title'], 'category' => (string) $row['category'],
            'modules' => $modules, 'sessions' => $sessions, 'classes' => $classes,
        ];
    }
    return elips_ok('', ['courses' => $cards]);
}

function elips_tp_lesson_full(int $lessonId): array
{
    $db = elips_db();
    $stmt = $db->prepare('SELECT objective, duration_jp FROM lesson_details WHERE lesson_id = ?');
    $stmt->execute([$lessonId]);
    $d = $stmt->fetch();
    $stmt = $db->prepare('SELECT id, title, kind, content FROM lesson_materials WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$lessonId]);
    $materials = array_map(static fn ($r) => [
        'id' => (int) $r['id'], 'title' => (string) $r['title'], 'kind' => (string) $r['kind'], 'content' => (string) $r['content'],
    ], $stmt->fetchAll());
    $stmt = $db->prepare('SELECT * FROM lesson_tasks WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$lessonId]);
    $tasks = array_map(static fn ($r) => [
        'id' => (int) $r['id'], 'title' => (string) $r['title'], 'instructions' => (string) $r['instructions'],
        'dueDays' => (int) $r['due_days'], 'format' => (string) $r['format'],
        'maxScore' => (float) $r['max_score'], 'weight' => (float) $r['weight'],
    ], $stmt->fetchAll());
    return [
        'objective' => $d ? (string) $d['objective'] : '',
        'durationJp' => $d && $d['duration_jp'] !== null ? (float) $d['duration_jp'] : null,
        'materials' => $materials,
        'tasks' => $tasks,
    ];
}

function elips_action_tp_curriculum_get(array $p): array
{
    $user = elips_tp_user();
    $courseId = (int) ($p['courseId'] ?? 0);
    $versions = elips_tp_editable_versions($user, $courseId);
    if (!$versions) {
        return elips_fail('Tidak ada angkatan berjalan atau pendaftaran untuk kursus ini.');
    }
    $versionId = (int) ($p['versionId'] ?? 0);
    $current = null;
    foreach ($versions as $v) {
        if ($versionId === 0 || $v['id'] === $versionId) {
            $current = $v;
            break;
        }
    }
    if (!$current) {
        return elips_fail('Versi kurikulum tidak ditemukan.');
    }

    $stmt = elips_db()->prepare('SELECT title FROM courses WHERE id = ?');
    $stmt->execute([$courseId]);
    $courseTitle = (string) $stmt->fetchColumn();

    $locked = elips_tp_locked_numbers($current['id']);
    $modules = [];
    foreach (elips_tp_lessons($current['id']) as $lesson) {
        $mid = $lesson['module_id'];
        if (!isset($modules[$mid])) {
            $modules[$mid] = ['id' => $mid, 'title' => (string) $lesson['module_title'], 'lessons' => []];
        }
        $full = elips_tp_lesson_full($lesson['id']);
        $modules[$mid]['lessons'][] = array_merge([
            'id' => $lesson['id'],
            'number' => $lesson['number'],
            'title' => (string) $lesson['title'],
            'locked' => isset($locked[$lesson['number']]),
            'lockedBy' => $locked[$lesson['number']] ?? [],
        ], $full);
    }
    return elips_ok('', [
        'courseId' => $courseId,
        'courseTitle' => $courseTitle,
        'versions' => $versions,
        'version' => $current,
        'modules' => array_values($modules),
    ]);
}

function elips_tp_clean_text($value, int $max): string
{
    $text = trim((string) $value);
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
}

function elips_tp_clean_lesson(array $in): array
{
    $objective = elips_tp_clean_text($in['objective'] ?? '', 2000);
    $duration = $in['durationJp'] ?? null;
    if ($duration === '' || $duration === null) {
        $duration = null;
    } else {
        if (!is_numeric($duration) || (float) $duration < 0 || (float) $duration > 99) {
            throw new ElipsError('Durasi (JP) harus angka 0 sampai 99.');
        }
        $duration = round((float) $duration, 2);
    }
    $materials = [];
    foreach ((array) ($in['materials'] ?? []) as $m) {
        if (!is_array($m)) {
            continue;
        }
        $title = elips_tp_clean_text($m['title'] ?? '', 200);
        if ($title === '') {
            continue;
        }
        $kind = in_array($m['kind'] ?? '', ELIPS_TP_MATERIAL_KINDS, true) ? $m['kind'] : 'teks';
        $materials[] = [
            'id' => (int) ($m['id'] ?? 0), 'title' => $title, 'kind' => $kind,
            'content' => elips_tp_clean_text($m['content'] ?? '', 4000),
        ];
    }
    if (count($materials) > 30) {
        throw new ElipsError('Maksimal 30 materi per sesi.');
    }
    $tasks = [];
    foreach ((array) ($in['tasks'] ?? []) as $t) {
        if (!is_array($t)) {
            continue;
        }
        $title = elips_tp_clean_text($t['title'] ?? '', 200);
        if ($title === '') {
            throw new ElipsError('Judul penugasan wajib diisi.');
        }
        $format = in_array($t['format'] ?? '', ELIPS_TP_TASK_FORMATS, true) ? $t['format'] : 'file_upload';
        $due = (int) ($t['dueDays'] ?? 7);
        $max = (float) ($t['maxScore'] ?? 100);
        $weight = (float) ($t['weight'] ?? 0);
        if ($due < 0 || $due > 365) {
            throw new ElipsError('Tenggat penugasan harus 0 sampai 365 hari setelah sesi.');
        }
        if ($max <= 0 || $max > 1000) {
            throw new ElipsError('Nilai maksimal penugasan tidak valid.');
        }
        if ($weight < 0 || $weight > 100) {
            throw new ElipsError('Bobot penugasan harus 0 sampai 100.');
        }
        $tasks[] = [
            'id' => (int) ($t['id'] ?? 0), 'title' => $title,
            'instructions' => elips_tp_clean_text($t['instructions'] ?? '', 4000),
            'dueDays' => $due, 'format' => $format, 'maxScore' => $max, 'weight' => $weight,
        ];
    }
    if (count($tasks) > 10) {
        throw new ElipsError('Maksimal 10 penugasan per sesi.');
    }
    return ['objective' => $objective, 'durationJp' => $duration, 'materials' => $materials, 'tasks' => $tasks];
}

function elips_tp_signature(array $lesson): string
{
    $strip = static function (array $rows): array {
        return array_map(static function ($r) {
            unset($r['id']);
            return $r;
        }, $rows);
    };
    return json_encode([
        $lesson['objective'], $lesson['durationJp'],
        $strip($lesson['materials']), $strip($lesson['tasks']),
    ], JSON_UNESCAPED_UNICODE);
}

/** Simpan isi satu sesi. Mengembalikan ringkasan perubahan ('' bila tidak ada). */
function elips_tp_write_lesson(array $user, array $lessonRow, int $versionId, int $courseId, array $in): string
{
    $db = elips_db();
    $lessonId = (int) $lessonRow['id'];
    $before = elips_tp_lesson_full($lessonId);
    if (elips_tp_signature($before) === elips_tp_signature($in)) {
        return '';
    }
    $now = gmdate('Y-m-d H:i:s');

    $db->prepare(
        'INSERT INTO lesson_details (lesson_id, objective, duration_jp, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE objective = VALUES(objective), duration_jp = VALUES(duration_jp), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
    )->execute([$lessonId, $in['objective'], $in['durationJp'], (int) $user['id'], $now]);

    // Materi
    $keep = [];
    $order = 0;
    foreach ($in['materials'] as $m) {
        $order++;
        $ownId = 0;
        if ($m['id'] > 0) {
            $chk = $db->prepare('SELECT id FROM lesson_materials WHERE id = ? AND lesson_id = ?');
            $chk->execute([$m['id'], $lessonId]);
            $ownId = (int) ($chk->fetchColumn() ?: 0);
        }
        if ($ownId) {
            $db->prepare('UPDATE lesson_materials SET title = ?, kind = ?, content = ?, sort_order = ? WHERE id = ?')
                ->execute([$m['title'], $m['kind'], $m['content'], $order, $ownId]);
            $keep[] = $ownId;
        } else {
            $db->prepare('INSERT INTO lesson_materials (lesson_id, title, kind, content, sort_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$lessonId, $m['title'], $m['kind'], $m['content'], $order]);
            $keep[] = (int) $db->lastInsertId();
        }
    }
    if ($keep) {
        $db->prepare('DELETE FROM lesson_materials WHERE lesson_id = ? AND id NOT IN (' . elips_tp_in($keep) . ')')
            ->execute(array_merge([$lessonId], $keep));
    } else {
        $db->prepare('DELETE FROM lesson_materials WHERE lesson_id = ?')->execute([$lessonId]);
    }

    // Penugasan (template)
    $keepTasks = [];
    $order = 0;
    foreach ($in['tasks'] as $t) {
        $order++;
        $ownId = 0;
        if ($t['id'] > 0) {
            $chk = $db->prepare('SELECT id FROM lesson_tasks WHERE id = ? AND lesson_id = ?');
            $chk->execute([$t['id'], $lessonId]);
            $ownId = (int) ($chk->fetchColumn() ?: 0);
        }
        if ($ownId) {
            $db->prepare('UPDATE lesson_tasks SET title = ?, instructions = ?, due_days = ?, format = ?, max_score = ?, weight = ?, sort_order = ? WHERE id = ?')
                ->execute([$t['title'], $t['instructions'], $t['dueDays'], $t['format'], $t['maxScore'], $t['weight'], $order, $ownId]);
            $keepTasks[] = $ownId;
        } else {
            $db->prepare('INSERT INTO lesson_tasks (lesson_id, title, instructions, due_days, format, max_score, weight, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$lessonId, $t['title'], $t['instructions'], $t['dueDays'], $t['format'], $t['maxScore'], $t['weight'], $order]);
            $keepTasks[] = (int) $db->lastInsertId();
        }
    }
    $removed = [];
    $stmt = $db->prepare('SELECT id FROM lesson_tasks WHERE lesson_id = ?');
    $stmt->execute([$lessonId]);
    foreach ($stmt->fetchAll() as $r) {
        if (!in_array((int) $r['id'], $keepTasks, true)) {
            $removed[] = (int) $r['id'];
        }
    }
    if ($removed) {
        $db->prepare('DELETE FROM lesson_tasks WHERE id IN (' . elips_tp_in($removed) . ')')->execute($removed);
    }
    elips_tp_sync_assignments($user, $lessonRow, $versionId, $keepTasks, $removed);

    // Ringkasan perubahan untuk riwayat.
    $parts = [];
    if ($before['objective'] !== $in['objective']) {
        $parts[] = 'tujuan';
    }
    if ($before['durationJp'] !== $in['durationJp']) {
        $parts[] = 'durasi';
    }
    $matBefore = count($before['materials']);
    $matAfter = count($in['materials']);
    if (json_encode(array_map(static fn ($m) => [$m['title'], $m['kind'], $m['content']], $before['materials'])) !==
        json_encode(array_map(static fn ($m) => [$m['title'], $m['kind'], $m['content']], $in['materials']))) {
        $parts[] = 'materi (' . $matBefore . ' menjadi ' . $matAfter . ')';
    }
    $taskBefore = count($before['tasks']);
    $taskAfter = count($in['tasks']);
    if (elips_tp_signature(['objective' => '', 'durationJp' => null, 'materials' => [], 'tasks' => $before['tasks']]) !==
        elips_tp_signature(['objective' => '', 'durationJp' => null, 'materials' => [], 'tasks' => $in['tasks']])) {
        $parts[] = 'penugasan (' . $taskBefore . ' menjadi ' . $taskAfter . ')';
    }
    $summary = 'Mengubah ' . implode(', ', $parts ?: ['isi sesi']);

    $db->prepare(
        'INSERT INTO curriculum_change_log (course_id, curriculum_version_id, lesson_id, lesson_title, actor_id, actor_name, summary, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    )->execute([$courseId, $versionId, $lessonId, (string) $lessonRow['title'], (int) $user['id'], (string) $user['name'], $summary]);
    elips_audit('update', 'curriculum_session', $lessonId, (string) $lessonRow['title'], 'Sesi ' . $lessonRow['number'] . ': ' . $summary . '.', $user);
    return $summary;
}

/** Salin template penugasan ke angkatan yang sesinya belum terkunci. */
function elips_tp_sync_assignments(array $user, array $lessonRow, int $versionId, array $taskIds, array $removedIds): void
{
    $db = elips_db();
    $number = (int) $lessonRow['number'];
    $stmt = $db->prepare(
        "SELECT c.id, cs.session_date, cs.status AS session_status
         FROM classes c
         LEFT JOIN class_sessions cs ON cs.class_id = c.id AND cs.session_number = ?
         WHERE c.curriculum_version_id = ? AND c.status IN ('open', 'ready', 'ongoing')"
    );
    $stmt->execute([$number, $versionId]);
    $targets = [];
    foreach ($stmt->fetchAll() as $row) {
        if (in_array($row['session_status'], ['confirmed', 'completed'], true)) {
            continue;
        }
        $targets[] = $row;
    }

    foreach ($targets as $target) {
        $classId = (int) $target['id'];
        foreach ($removedIds as $taskId) {
            $f = $db->prepare('SELECT a.id, (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS n FROM assignments a WHERE a.class_id = ? AND a.template_task_id = ?');
            $f->execute([$classId, $taskId]);
            foreach ($f->fetchAll() as $a) {
                if ((int) $a['n'] > 0) {
                    $db->prepare("UPDATE assignments SET status = 'archived', updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([(int) $a['id']]);
                } else {
                    $db->prepare('DELETE FROM assignments WHERE id = ?')->execute([(int) $a['id']]);
                }
            }
        }
        if (!$taskIds) {
            continue;
        }
        $t = $db->prepare('SELECT * FROM lesson_tasks WHERE id IN (' . elips_tp_in($taskIds) . ')');
        $t->execute($taskIds);
        foreach ($t->fetchAll() as $task) {
            $due = null;
            if ($target['session_date']) {
                $due = date('Y-m-d 23:59:00', strtotime((string) $target['session_date'] . ' +' . (int) $task['due_days'] . ' day'));
            }
            $f = $db->prepare('SELECT id FROM assignments WHERE class_id = ? AND template_task_id = ?');
            $f->execute([$classId, (int) $task['id']]);
            $existing = (int) ($f->fetchColumn() ?: 0);
            if ($existing) {
                $db->prepare(
                    "UPDATE assignments SET title = ?, instructions = ?, type = ?, due_at = ?, max_score = ?, weight = ?,
                            require_file = ?, status = IF(status = 'archived', 'published', status), updated_by = ?, updated_at = UTC_TIMESTAMP()
                     WHERE id = ?"
                )->execute([
                    $task['title'], $task['instructions'], $task['format'], $due, $task['max_score'], $task['weight'],
                    $task['format'] === 'file_upload' ? 1 : 0, (int) $user['id'], $existing,
                ]);
            } else {
                $db->prepare(
                    "INSERT INTO assignments (class_id, curriculum_version_id, module_id, lesson_id, title, instructions, type, due_at,
                        max_score, weight, require_file, status, created_by, updated_by, template_task_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?, ?, ?, UTC_TIMESTAMP())"
                )->execute([
                    $classId, $versionId, (int) $lessonRow['module_id'], (int) $lessonRow['id'], $task['title'], $task['instructions'],
                    $task['format'], $due, $task['max_score'], $task['weight'], $task['format'] === 'file_upload' ? 1 : 0,
                    (int) $user['id'], (int) $user['id'], (int) $task['id'],
                ]);
            }
        }
    }
}

function elips_action_tp_curriculum_save(array $p): array
{
    $user = elips_tp_user();
    $db = elips_db();
    $courseId = (int) ($p['courseId'] ?? 0);
    $versionId = (int) ($p['versionId'] ?? 0);
    $items = $p['lessons'] ?? [];
    if (!is_array($items) || !$items) {
        return elips_fail('Tidak ada perubahan untuk disimpan.');
    }

    $version = null;
    foreach (elips_tp_editable_versions($user, $courseId) as $v) {
        if ($v['id'] === $versionId) {
            $version = $v;
        }
    }
    if (!$version) {
        return elips_fail('Kurikulum ini tidak bisa Anda ubah.');
    }
    if (!$version['editable']) {
        return elips_fail('Kurikulum ini sudah dipensiunkan sehingga tidak bisa diubah.');
    }

    $lessons = [];
    foreach (elips_tp_lessons($versionId) as $l) {
        $lessons[$l['id']] = $l;
    }
    $locked = elips_tp_locked_numbers($versionId);

    // Validasi semua dulu sebelum menulis apa pun.
    $clean = [];
    foreach ($items as $item) {
        $lid = (int) ($item['id'] ?? 0);
        if (!isset($lessons[$lid])) {
            return elips_fail('Sesi tidak ditemukan pada kurikulum ini.');
        }
        if (isset($locked[$lessons[$lid]['number']])) {
            return elips_fail('Sesi ' . $lessons[$lid]['number'] . ' sudah dikonfirmasi admin dan terkunci.');
        }
        try {
            $clean[$lid] = elips_tp_clean_lesson((array) $item);
        } catch (ElipsError $e) {
            return elips_fail('Sesi ' . $lessons[$lid]['number'] . ': ' . $e->getMessage());
        }
    }

    $changed = [];
    $db->beginTransaction();
    try {
        foreach ($clean as $lid => $data) {
            $summary = elips_tp_write_lesson($user, $lessons[$lid], $versionId, $courseId, $data);
            if ($summary !== '') {
                $changed[] = ['id' => $lid, 'number' => $lessons[$lid]['number'], 'summary' => $summary];
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    $fresh = [];
    foreach ($clean as $lid => $_) {
        $fresh[] = array_merge(['id' => $lid], elips_tp_lesson_full($lid));
    }
    $message = $changed ? count($changed) . ' sesi disimpan.' : 'Tidak ada perubahan.';
    return elips_ok($message, ['changed' => $changed, 'lessons' => $fresh]);
}

// ---------- Penilaian tugas ----------

function elips_action_tp_grading_classes(array $p): array
{
    $user = elips_tp_user();
    $classes = elips_tp_my_classes($user, ['open', 'ready', 'ongoing', 'completed']);
    return elips_ok('', ['classes' => $classes]);
}

function elips_action_tp_grading_tasks(array $p): array
{
    $user = elips_tp_user();
    $class = elips_tp_class((int) ($p['classId'] ?? 0), $user);
    $classId = (int) $class['id'];
    $summary = elips_tp_summaries([$classId])[$classId];
    return elips_ok('', [
        'class' => elips_tp_class_card($class, $summary),
        'tasks' => elips_tp_task_rows($classId, (int) $class['curriculum_version_id']),
    ]);
}

function elips_tp_assignment(int $assignmentId, array $user): array
{
    $stmt = elips_db()->prepare(
        'SELECT a.*, c.name AS class_name, c.instructor_id FROM assignments a JOIN classes c ON c.id = a.class_id WHERE a.id = ?'
    );
    $stmt->execute([$assignmentId]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['instructor_id'] !== (int) $user['id']) {
        throw new ElipsError('Penugasan tidak ditemukan atau bukan angkatan Anda.');
    }
    return $row;
}

function elips_action_tp_grading_submissions(array $p): array
{
    $user = elips_tp_user();
    $db = elips_db();
    $assignment = elips_tp_assignment((int) ($p['assignmentId'] ?? 0), $user);
    $aid = (int) $assignment['id'];

    $stmt = $db->prepare(
        "SELECT e.id AS enrollment_id, u.id AS student_id, u.name,
                s.id AS sid, s.status, s.text_answer, s.submitted_at, s.is_late, s.score, s.feedback, s.reviewed_at
         FROM enrollments e
         JOIN users u ON u.id = e.student_id
         LEFT JOIN submissions s ON s.enrollment_id = e.id AND s.assignment_id = ?
         WHERE e.class_id = ? AND e.status IN ('active', 'completed')
         ORDER BY u.name ASC"
    );
    $stmt->execute([$aid, (int) $assignment['class_id']]);
    $students = [];
    foreach ($stmt->fetchAll() as $row) {
        $files = [];
        if ($row['sid']) {
            foreach (elips_submission_files_for((int) $row['sid'], true) as $f) {
                $files[] = ['id' => $f['id'], 'name' => $f['fileName'], 'size' => $f['fileSize'], 'path' => $f['filePath'] ?? ''];
            }
        }
        $state = 'belum';
        if ($row['sid']) {
            $state = $row['score'] !== null ? 'dinilai' : 'perlu';
        }
        $students[] = [
            'enrollmentId' => (int) $row['enrollment_id'],
            'studentId' => (int) $row['student_id'],
            'name' => (string) $row['name'],
            'submissionId' => $row['sid'] ? (int) $row['sid'] : null,
            'state' => $state,
            'status' => $row['status'],
            'text' => (string) ($row['text_answer'] ?? ''),
            'submittedAt' => $row['submitted_at'],
            'isLate' => (bool) $row['is_late'],
            'score' => $row['score'] === null ? null : (float) $row['score'],
            'feedback' => (string) ($row['feedback'] ?? ''),
            'files' => $files,
        ];
    }

    $numbers = [];
    foreach (elips_tp_lessons((int) $assignment['curriculum_version_id']) as $l) {
        $numbers[$l['id']] = $l['number'];
    }
    return elips_ok('', [
        'assignment' => [
            'id' => $aid,
            'classId' => (int) $assignment['class_id'],
            'className' => (string) $assignment['class_name'],
            'title' => (string) $assignment['title'],
            'instructions' => (string) $assignment['instructions'],
            'type' => (string) $assignment['type'],
            'maxScore' => (float) $assignment['max_score'],
            'dueAt' => $assignment['due_at'],
            'sessionNumber' => $assignment['lesson_id'] !== null ? ($numbers[(int) $assignment['lesson_id']] ?? null) : null,
        ],
        'students' => $students,
    ]);
}

function elips_action_tp_grading_score(array $p): array
{
    $user = elips_tp_user();
    $db = elips_db();
    $sid = (int) ($p['submissionId'] ?? 0);
    $stmt = $db->prepare('SELECT s.*, a.max_score, a.class_id FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.id = ?');
    $stmt->execute([$sid]);
    $sub = $stmt->fetch();
    if (!$sub) {
        return elips_fail('Pengumpulan tidak ditemukan.');
    }
    elips_tp_assert_writable(elips_tp_class((int) $sub['class_id'], $user));

    $raw = $p['score'] ?? null;
    if ($raw === null || $raw === '' || !is_numeric($raw)) {
        return elips_fail('Nilai wajib diisi dengan angka.');
    }
    $score = round((float) $raw, 2);
    $max = (float) $sub['max_score'];
    if ($score < 0 || $score > $max) {
        return elips_fail('Nilai harus antara 0 dan ' . rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.') . '.');
    }
    $feedback = elips_tp_clean_text($p['feedback'] ?? '', 2000);

    $db->prepare(
        "UPDATE submissions SET score = ?, feedback = ?, status = 'reviewed', reviewed_by = ?, reviewed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?"
    )->execute([$score, $feedback, (int) $user['id'], $sid]);
    $db->prepare("INSERT INTO submission_reviews (submission_id, reviewer_id, action, score, note, created_at) VALUES (?, ?, 'score', ?, ?, UTC_TIMESTAMP())")
        ->execute([$sid, (int) $user['id'], $score, $feedback]);
    elips_audit('update', 'submission', $sid, 'Nilai tugas', 'Memberi nilai ' . $score . ' pada pengumpulan #' . $sid . '.', $user);
    return elips_ok('Nilai tersimpan.', ['score' => $score]);
}

// ---------- Penilaian akhir ----------

/** Rapikan struktur materi: [{title, items:[{title, jp, score?}]}]. */
function elips_tp_final_clean($structure, bool $withScores): array
{
    if (!is_array($structure) || !$structure) {
        throw new ElipsError('Tambahkan minimal satu materi dan satu sub materi.');
    }
    if (count($structure) > 30) {
        throw new ElipsError('Maksimal 30 materi.');
    }
    $out = [];
    $total = 0;
    foreach ($structure as $group) {
        $title = elips_tp_clean_text($group['title'] ?? '', 150);
        if ($title === '') {
            throw new ElipsError('Nama materi tidak boleh kosong.');
        }
        $items = [];
        foreach ((array) ($group['items'] ?? []) as $item) {
            $name = elips_tp_clean_text($item['title'] ?? '', 250);
            if ($name === '') {
                throw new ElipsError('Nama sub materi pada "' . $title . '" tidak boleh kosong.');
            }
            $jp = $item['jp'] ?? 0;
            if ($jp === '' || $jp === null) {
                $jp = 0;
            }
            if (!is_numeric($jp) || (float) $jp < 0 || (float) $jp > 99) {
                throw new ElipsError('JP pada "' . $name . '" harus angka 0 sampai 99.');
            }
            $row = ['title' => $name, 'jp' => round((float) $jp, 2)];
            if ($withScores) {
                $score = $item['score'] ?? null;
                if ($score === '' || $score === null) {
                    $row['score'] = null;
                } else {
                    if (!is_numeric($score) || (float) $score < 0 || (float) $score > 100) {
                        throw new ElipsError('Nilai pada "' . $name . '" harus antara 0 dan 100.');
                    }
                    $row['score'] = round((float) $score, 2);
                }
            }
            $items[] = $row;
            $total++;
        }
        if (!$items) {
            throw new ElipsError('Materi "' . $title . '" belum punya sub materi.');
        }
        $out[] = ['title' => $title, 'items' => $items];
    }
    if ($total > 120) {
        throw new ElipsError('Maksimal 120 sub materi.');
    }
    return $out;
}

/** Hitung rata-rata, total JP, jumlah item terisi. */
function elips_tp_final_metrics(array $structure): array
{
    $sum = 0.0;
    $filled = 0;
    $count = 0;
    $jp = 0.0;
    foreach ($structure as $group) {
        foreach ($group['items'] as $item) {
            $count++;
            $jp += (float) $item['jp'];
            if (isset($item['score']) && $item['score'] !== null) {
                $filled++;
                $sum += (float) $item['score'];
            }
        }
    }
    $complete = $count > 0 && $filled === $count;
    $average = $filled > 0 ? round($sum / $filled, 2) : null;
    return [
        'count' => $count, 'filled' => $filled, 'jp' => round($jp, 2), 'complete' => $complete,
        'average' => $complete ? $average : null,
        'predicate' => $complete ? elips_predicate_letter($average) : '',
    ];
}

/** Struktur awal bila belum ada template: dari kurikulum angkatan. */
function elips_tp_final_default(array $class): array
{
    $db = elips_db();
    $stmt = $db->prepare('SELECT session_number, planned_jp FROM class_sessions WHERE class_id = ?');
    $stmt->execute([(int) $class['id']]);
    $planned = [];
    foreach ($stmt->fetchAll() as $row) {
        $planned[(int) $row['session_number']] = (float) $row['planned_jp'];
    }
    $lessons = elips_tp_lessons((int) $class['curriculum_version_id']);
    $details = elips_tp_lesson_details(array_map(static fn ($l) => $l['id'], $lessons));
    $groups = [];
    foreach ($lessons as $l) {
        $mid = $l['module_id'];
        if (!isset($groups[$mid])) {
            $groups[$mid] = ['title' => mb_strtoupper((string) $l['module_title']), 'items' => []];
        }
        $jp = $details[$l['id']]['durationJp'] ?? null;
        if ($jp === null) {
            $jp = $planned[$l['number']] ?? 2.0;
        }
        $groups[$mid]['items'][] = ['title' => (string) $l['title'], 'jp' => (float) $jp];
    }
    return array_values($groups);
}

/**
 * Susunan nilai akhir dari penugasan tentor: hanya pertemuan yang punya tugas yang menjadi sub materi.
 * Bila $enrollmentId diberikan, nilai diisi dari rata-rata nilai tugas siswa (0-100) per sub materi.
 * @return array|null null bila angkatan belum punya penugasan terbit.
 */
function elips_tp_final_from_assignments(array $class, ?int $enrollmentId = null): ?array
{
    $db = elips_db();
    $classId = (int) $class['id'];
    $stmt = $db->prepare("SELECT id, lesson_id, title, max_score FROM assignments WHERE class_id = ? AND status IN ('published', 'closed') ORDER BY id ASC");
    $stmt->execute([$classId]);
    $assignments = $stmt->fetchAll();
    if (!$assignments) {
        return null;
    }

    $scores = [];
    if ($enrollmentId) {
        $stmt = $db->prepare(
            'SELECT s.assignment_id, s.score FROM submissions s JOIN assignments a ON a.id = s.assignment_id
             WHERE a.class_id = ? AND s.enrollment_id = ? AND s.score IS NOT NULL'
        );
        $stmt->execute([$classId, $enrollmentId]);
        foreach ($stmt->fetchAll() as $r) {
            $scores[(int) $r['assignment_id']] = (float) $r['score'];
        }
    }
    $percent = static function (array $rows) use ($scores): ?float {
        $vals = [];
        foreach ($rows as $a) {
            if (isset($scores[(int) $a['id']]) && (float) $a['max_score'] > 0) {
                $vals[] = $scores[(int) $a['id']] / (float) $a['max_score'] * 100;
            }
        }
        return $vals ? round(array_sum($vals) / count($vals), 2) : null;
    };

    $stmt = $db->prepare('SELECT session_number, planned_jp FROM class_sessions WHERE class_id = ?');
    $stmt->execute([$classId]);
    $planned = [];
    foreach ($stmt->fetchAll() as $row) {
        $planned[(int) $row['session_number']] = (float) $row['planned_jp'];
    }
    $lessons = elips_tp_lessons((int) $class['curriculum_version_id']);
    $details = elips_tp_lesson_details(array_map(static fn ($l) => $l['id'], $lessons));
    $byLesson = [];
    $general = [];
    $known = array_column($lessons, 'id');
    foreach ($assignments as $a) {
        if ($a['lesson_id'] !== null && in_array((int) $a['lesson_id'], $known, true)) {
            $byLesson[(int) $a['lesson_id']][] = $a;
        } else {
            $general[] = $a;
        }
    }

    $groups = [];
    foreach ($lessons as $l) {
        if (empty($byLesson[$l['id']])) {
            continue;
        }
        $mid = $l['module_id'];
        if (!isset($groups[$mid])) {
            $groups[$mid] = ['title' => mb_strtoupper((string) $l['module_title']), 'items' => []];
        }
        $jp = $details[$l['id']]['durationJp'] ?? null;
        if ($jp === null) {
            $jp = $planned[$l['number']] ?? 2.0;
        }
        $groups[$mid]['items'][] = ['title' => (string) $l['title'], 'jp' => (float) $jp, 'score' => $percent($byLesson[$l['id']])];
    }
    $out = array_values($groups);
    if ($general) {
        $items = [];
        foreach ($general as $a) {
            $items[] = ['title' => (string) $a['title'], 'jp' => 0.0, 'score' => $percent([$a])];
        }
        $out[] = ['title' => 'TUGAS UMUM', 'items' => $items];
    }
    return $out;
}

function elips_tp_final_template(array $class): array
{
    $fromTasks = elips_tp_final_from_assignments($class);
    if ($fromTasks !== null) {
        return ['source' => 'assignments', 'structure' => $fromTasks];
    }
    $stmt = elips_db()->prepare('SELECT structure FROM final_templates WHERE course_id = ?');
    $stmt->execute([(int) $class['course_id']]);
    $raw = $stmt->fetchColumn();
    if ($raw) {
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded) && $decoded) {
            return ['source' => 'template', 'structure' => $decoded];
        }
    }
    return ['source' => 'curriculum', 'structure' => elips_tp_final_default($class)];
}

function elips_tp_with_empty_scores(array $structure): array
{
    foreach ($structure as &$group) {
        foreach ($group['items'] as &$item) {
            $item = ['title' => $item['title'], 'jp' => (float) $item['jp'], 'score' => $item['score'] ?? null];
        }
        unset($item);
    }
    unset($group);
    return $structure;
}

/** Siswa boleh dinilai bila semua materi kurikulum sudah dikonfirmasi untuknya. */
function elips_tp_final_student_guard(array $class, int $enrollmentId): void
{
    $students = elips_tp_final_students((int) $class['id']);
    foreach ($students as $st) {
        if ($st['enrollmentId'] === $enrollmentId) {
            if (!$st['materi']['ready']) {
                throw new ElipsError('Materi siswa ini belum selesai dikonfirmasi (' . $st['materi']['done'] . ' dari ' . $st['materi']['total'] . ').');
            }
            return;
        }
    }
}

/** Syarat tingkat angkatan: hanya ditutup admin atau sudah terkirim. Syarat per siswa: materi terkonfirmasi. */
function elips_tp_final_gate(array $class, array $summary, bool $sent): array
{
    $closed = ($class['status'] ?? '') === 'completed';
    $reason = '';
    if ($sent) {
        $reason = 'Nilai akhir sudah dikirim ke operasional dan tidak bisa diubah.';
    } elseif (!$closed) {
        $reason = 'Nilai akhir bisa diisi setelah admin menutup pembelajaran angkatan ini.';
    }
    return [
        'sessionsTotal' => (int) $summary['sessionsTotal'],
        'sessionsConfirmed' => (int) $summary['sessionsConfirmed'],
        'studentsReady' => (int) $summary['studentsReady'],
        'students' => (int) $summary['students'],
        'lessonsTotal' => (int) $summary['lessonsTotal'],
        'sent' => $sent,
        'closed' => $closed,
        'canEdit' => $reason === '',
        'reason' => $reason,
    ];
}

/** Kehadiran dan tugas tiap siswa pada sesi yang sudah dikonfirmasi. @return array<int,array> */
function elips_tp_final_progress(int $classId): array
{
    $db = elips_db();
    $out = [];
    $stmt = $db->prepare(
        "SELECT a.enrollment_id,
                SUM(a.status IN ('present', 'late')) AS present,
                SUM(a.status IN ('permission', 'sick', 'excused')) AS permitted,
                SUM(a.status = 'absent') AS absent
         FROM attendances a JOIN class_sessions cs ON cs.id = a.session_id
         WHERE cs.class_id = ? AND cs.status IN ('confirmed', 'completed')
         GROUP BY a.enrollment_id"
    );
    $stmt->execute([$classId]);
    foreach ($stmt->fetchAll() as $r) {
        $out[(int) $r['enrollment_id']]['attendance'] = [
            'present' => (int) $r['present'], 'permitted' => (int) $r['permitted'], 'absent' => (int) $r['absent'],
        ];
    }
    $stmt = $db->prepare('SELECT curriculum_version_id FROM classes WHERE id = ?');
    $stmt->execute([$classId]);
    $versionId = (int) $stmt->fetchColumn();
    $stmt = $db->prepare('SELECT COUNT(*) FROM lessons WHERE curriculum_version_id = ?');
    $stmt->execute([$versionId]);
    $lessonsTotal = (int) $stmt->fetchColumn();
    $stmt = $db->prepare(
        'SELECT p.enrollment_id, COUNT(*) AS n FROM class_lesson_progress p JOIN lessons l ON l.id = p.lesson_id
         WHERE p.class_id = ? AND l.curriculum_version_id = ? GROUP BY p.enrollment_id'
    );
    $stmt->execute([$classId, $versionId]);
    foreach ($stmt->fetchAll() as $r) {
        $out[(int) $r['enrollment_id']]['materiDone'] = (int) $r['n'];
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM assignments WHERE class_id = ? AND status IN ('published', 'closed')");
    $stmt->execute([$classId]);
    $tasks = (int) $stmt->fetchColumn();
    $stmt = $db->prepare(
        "SELECT s.enrollment_id, COUNT(*) AS submitted, SUM(s.score IS NOT NULL) AS graded,
                AVG(CASE WHEN s.score IS NOT NULL AND a.max_score > 0 THEN s.score / a.max_score * 100 END) AS avg_score
         FROM submissions s JOIN assignments a ON a.id = s.assignment_id
         WHERE a.class_id = ? AND a.status IN ('published', 'closed')
         GROUP BY s.enrollment_id"
    );
    $stmt->execute([$classId]);
    foreach ($stmt->fetchAll() as $r) {
        $out[(int) $r['enrollment_id']]['tasks'] = [
            'total' => $tasks, 'submitted' => (int) $r['submitted'], 'graded' => (int) $r['graded'],
            'average' => $r['avg_score'] === null ? null : round((float) $r['avg_score'], 1),
        ];
    }
    return ['tasksTotal' => $tasks, 'lessonsTotal' => $lessonsTotal, 'byEnrollment' => $out];
}

function elips_tp_final_student_gate(array $gate, array $student): array
{
    if ($gate['canEdit'] && !$student['materi']['ready']) {
        $gate['canEdit'] = false;
        $gate['reason'] = 'Nilai akhir bisa diisi setelah semua materi siswa ini dikonfirmasi (' . $student['materi']['done'] . ' dari ' . $student['materi']['total'] . ').';
    }
    return $gate;
}

function elips_tp_final_students(int $classId): array
{
    $stmt = elips_db()->prepare(
        "SELECT e.id AS enrollment_id, e.student_id, u.name, f.status, f.average, f.predicate, f.item_count, f.filled_count, f.total_jp
         FROM enrollments e
         JOIN users u ON u.id = e.student_id
         LEFT JOIN final_grades f ON f.class_id = e.class_id AND f.enrollment_id = e.id
         WHERE e.class_id = ? AND e.status IN ('active', 'completed')
         ORDER BY u.name ASC"
    );
    $stmt->execute([$classId]);
    $rows = [];
    $progress = elips_tp_final_progress($classId);
    foreach ($stmt->fetchAll() as $row) {
        $prog = $progress['byEnrollment'][(int) $row['enrollment_id']] ?? [];
        $rows[] = [
            'materi' => [
                'done' => (int) ($prog['materiDone'] ?? 0),
                'total' => $progress['lessonsTotal'],
                'ready' => $progress['lessonsTotal'] > 0 && (int) ($prog['materiDone'] ?? 0) >= $progress['lessonsTotal'],
            ],
            'attendance' => $prog['attendance'] ?? ['present' => 0, 'permitted' => 0, 'absent' => 0],
            'tasks' => $prog['tasks'] ?? ['total' => $progress['tasksTotal'], 'submitted' => 0, 'graded' => 0, 'average' => null],
            'enrollmentId' => (int) $row['enrollment_id'],
            'studentId' => (int) $row['student_id'],
            'name' => (string) $row['name'],
            'status' => $row['status'] === null ? 'kosong' : ($row['status'] === 'complete' ? 'lengkap' : 'draf'),
            'filled' => (int) ($row['filled_count'] ?? 0),
            'count' => (int) ($row['item_count'] ?? 0),
            'average' => $row['average'] === null ? null : (float) $row['average'],
            'predicate' => (string) ($row['predicate'] ?? ''),
            'totalJp' => $row['total_jp'] === null ? null : (float) $row['total_jp'],
        ];
    }
    return $rows;
}

function elips_action_tp_final_list(array $p): array
{
    $user = elips_tp_user();
    $class = elips_tp_class((int) ($p['classId'] ?? 0), $user);
    $classId = (int) $class['id'];
    $summary = elips_tp_summaries([$classId])[$classId];
    $template = elips_tp_final_template($class);
    $metrics = elips_tp_final_metrics(elips_tp_with_empty_scores($template['structure']));
    $students = elips_tp_final_students((int) $class['id']);
    if ($template['source'] === 'assignments') {
        // Siswa yang belum disimpan: tampilkan sub materi terisi dari nilai tugasnya.
        foreach ($students as &$st) {
            if ($st['status'] === 'kosong') {
                $pre = elips_tp_final_from_assignments($class, $st['enrollmentId']);
                if ($pre) {
                    $m = elips_tp_final_metrics($pre);
                    $st['filled'] = $m['filled'];
                    $st['count'] = $m['count'];
                }
            }
        }
        unset($st);
    }
    $complete = count(array_filter($students, static fn ($s) => $s['status'] === 'lengkap'));
    $draft = count(array_filter($students, static fn ($s) => $s['status'] === 'draf'));
    $stmt = elips_db()->prepare('SELECT sent_at FROM final_dispatches WHERE class_id = ?');
    $stmt->execute([(int) $class['id']]);
    $sentAt = $stmt->fetchColumn();
    return elips_ok('', [
        'class' => elips_tp_class_card($class, $summary),
        'gate' => elips_tp_final_gate($class, $summary, (bool) $sentAt),
        'students' => $students,
        'counts' => ['total' => count($students), 'complete' => $complete, 'draft' => $draft, 'empty' => count($students) - $complete - $draft],
        'template' => ['source' => $template['source'], 'items' => $metrics['count'], 'jp' => $metrics['jp']],
        'sentAt' => $sentAt ?: null,
        'ranges' => elips_predicate_ranges(),
    ]);
}

function elips_action_tp_final_get(array $p): array
{
    $user = elips_tp_user();
    $db = elips_db();
    $class = elips_tp_class((int) ($p['classId'] ?? 0), $user);
    $classId = (int) $class['id'];
    $summary = elips_tp_summaries([$classId])[$classId];
    $enrollmentId = (int) ($p['enrollmentId'] ?? 0);

    $students = elips_tp_final_students($classId);
    $index = null;
    foreach ($students as $i => $s) {
        if ($s['enrollmentId'] === $enrollmentId) {
            $index = $i;
        }
    }
    if ($index === null) {
        return elips_fail('Siswa tidak ditemukan pada angkatan ini.');
    }

    $stmt = $db->prepare('SELECT structure FROM final_grades WHERE class_id = ? AND enrollment_id = ?');
    $stmt->execute([$classId, $enrollmentId]);
    $raw = $stmt->fetchColumn();
    $structure = $raw ? json_decode((string) $raw, true) : null;
    $source = 'student';
    if (!is_array($structure) || !$structure) {
        $template = elips_tp_final_template($class);
        $source = $template['source'];
        $structure = $source === 'assignments'
            ? (elips_tp_final_from_assignments($class, $enrollmentId) ?? $template['structure'])
            : elips_tp_with_empty_scores($template['structure']);
    }

    $stmt = $db->prepare('SELECT sent_at FROM final_dispatches WHERE class_id = ?');
    $stmt->execute([$classId]);
    $sentAt = $stmt->fetchColumn();

    return elips_ok('', [
        'class' => elips_tp_class_card($class, $summary),
        'gate' => elips_tp_final_student_gate(elips_tp_final_gate($class, $summary, (bool) $sentAt), $students[$index]),
        'student' => $students[$index],
        'structure' => $structure,
        'source' => $source,
        'students' => $students,
        'prevId' => $index > 0 ? $students[$index - 1]['enrollmentId'] : null,
        'nextId' => $index < count($students) - 1 ? $students[$index + 1]['enrollmentId'] : null,
        'position' => $index + 1,
        'ranges' => elips_predicate_ranges(),
        'sentAt' => $sentAt ?: null,
    ]);
}

function elips_action_tp_final_save(array $p): array
{
    $user = elips_tp_user();
    $db = elips_db();
    $class = elips_tp_class((int) ($p['classId'] ?? 0), $user);
    elips_tp_assert_final_open($class);
    $classId = (int) $class['id'];
    $enrollmentId = (int) ($p['enrollmentId'] ?? 0);
    elips_tp_final_student_guard($class, $enrollmentId);

    $stmt = $db->prepare("SELECT student_id FROM enrollments WHERE id = ? AND class_id = ? AND status IN ('active', 'completed')");
    $stmt->execute([$enrollmentId, $classId]);
    $studentId = (int) ($stmt->fetchColumn() ?: 0);
    if (!$studentId) {
        return elips_fail('Siswa tidak ditemukan pada angkatan ini.');
    }
    $stmt = $db->prepare('SELECT 1 FROM final_dispatches WHERE class_id = ?');
    $stmt->execute([$classId]);
    if ($stmt->fetchColumn()) {
        return elips_fail('Nilai akhir angkatan ini sudah dikirim ke operasional dan tidak bisa diubah.');
    }

    try {
        $structure = elips_tp_final_clean($p['structure'] ?? null, true);
    } catch (ElipsError $e) {
        return elips_fail($e->getMessage());
    }
    $m = elips_tp_final_metrics($structure);

    $db->beginTransaction();
    try {
        $db->prepare(
            "INSERT INTO final_grades (class_id, enrollment_id, student_id, structure, average, predicate, total_jp, item_count, filled_count, status, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE structure = VALUES(structure), average = VALUES(average), predicate = VALUES(predicate),
                total_jp = VALUES(total_jp), item_count = VALUES(item_count), filled_count = VALUES(filled_count),
                status = VALUES(status), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)"
        )->execute([
            $classId, $enrollmentId, $studentId, json_encode($structure, JSON_UNESCAPED_UNICODE),
            $m['average'], $m['predicate'], $m['jp'], $m['count'], $m['filled'],
            $m['complete'] ? 'complete' : 'draft', (int) $user['id'],
        ]);

        // Struktur yang dipakai otomatis menjadi template kursus (tanpa nilai).
        $template = [];
        foreach ($structure as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                $items[] = ['title' => $item['title'], 'jp' => $item['jp']];
            }
            $template[] = ['title' => $group['title'], 'items' => $items];
        }
        $db->prepare(
            'INSERT INTO final_templates (course_id, structure, updated_by, updated_at) VALUES (?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE structure = VALUES(structure), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
        )->execute([(int) $class['course_id'], json_encode($template, JSON_UNESCAPED_UNICODE), (int) $user['id']]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    elips_audit('update', 'final_grade', $enrollmentId, 'Nilai akhir', 'Menyimpan nilai akhir siswa #' . $studentId . ' pada ' . $class['name'] . '.', $user);
    return elips_ok($m['complete'] ? 'Nilai akhir lengkap dan tersimpan.' : 'Draf tersimpan.', [
        'status' => $m['complete'] ? 'lengkap' : 'draf',
        'average' => $m['average'], 'predicate' => $m['predicate'], 'totalJp' => $m['jp'],
        'filled' => $m['filled'], 'count' => $m['count'],
    ]);
}

function elips_action_tp_final_send(array $p): array
{
    $user = elips_tp_user();
    $db = elips_db();
    $class = elips_tp_class((int) ($p['classId'] ?? 0), $user);
    elips_tp_assert_final_open($class);
    $classId = (int) $class['id'];

    $stmt = $db->prepare('SELECT 1 FROM final_dispatches WHERE class_id = ?');
    $stmt->execute([$classId]);
    if ($stmt->fetchColumn()) {
        return elips_fail('Nilai akhir angkatan ini sudah dikirim.');
    }
    $students = elips_tp_final_students($classId);
    if (!$students) {
        return elips_fail('Belum ada siswa pada angkatan ini.');
    }
    $notReady = array_filter($students, static fn ($s) => !$s['materi']['ready']);
    if ($notReady) {
        return elips_fail(count($notReady) . ' siswa belum selesai dikonfirmasi materinya.');
    }
    $incomplete = array_filter($students, static fn ($s) => $s['status'] !== 'lengkap');
    if ($incomplete) {
        return elips_fail(count($incomplete) . ' siswa belum lengkap. Lengkapi semua nilai sebelum mengirim.');
    }
    $db->prepare('INSERT INTO final_dispatches (class_id, sent_by, sent_at, student_count) VALUES (?, ?, UTC_TIMESTAMP(), ?)')
        ->execute([$classId, (int) $user['id'], count($students)]);
    elips_audit('update', 'final_grade', $classId, (string) $class['name'], 'Mengirim nilai akhir ' . count($students) . ' siswa ke operasional.', $user);
    return elips_ok('Nilai akhir terkirim ke operasional.', ['sent' => true]);
}