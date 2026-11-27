<?php
// Direktori: api/course_publish.php
// ============================================================
// ELIPS LMS - Halaman Publikasi Kursus (admin & Operasional)
//
// Alur: admin membuat kursus -> klik Publikasi -> halaman publikasi:
//   1. mengisi STRUKTUR saja (modul + judul materi/sesi),
//   2. isi tiap materi (tujuan, materi, penugasan, durasi) diisi TENTOR
//      lewat portal tentor,
//   3. publikasikan kursus,
//   4. buat ANGKATAN BARU dengan satu formulir: nama otomatis berurutan,
//      tentor bawaan kursus, jadwal sesi dibuat otomatis dari daftar materi.
//
// Semua angkatan satu kursus memakai satu kurikulum yang sama, sehingga isi
// yang sudah diisi tentor otomatis terbawa ke angkatan berikutnya.
// Sesi yang sudah dikonfirmasi di angkatan berjalan tetap terkunci.
//
// Aksi (tanpa snapshot, lihat api/index.php):
//   cp.get, cp.structure.save, cp.structure.copy, cp.angkatan.create
// ============================================================

require_once __DIR__ . '/tutor_portal.php';

/** Kursus + pemeriksaan akses, atau ElipsError. */
function elips_cp_course(int $courseId): array
{
    $course = elips_course_row($courseId);
    if (!$course) {
        throw new ElipsError('Kursus tidak ditemukan.');
    }
    return $course;
}

/** Versi kurikulum yang sedang dipakai kursus (dibuat bila belum ada). */
function elips_cp_version(array $course, int $actorId): array
{
    $version = elips_ensure_curriculum_version((int) $course['id'], $actorId);
    if ($version['status'] === 'retired') {
        throw new ElipsError('Kurikulum kursus ini sudah dipensiunkan. Buat versi baru di menu Kurikulum & Kelas.');
    }
    return $version;
}

/** Pohon modul -> materi dengan nomor sesi, kunci, dan status isian tentor. */
function elips_cp_tree(int $versionId): array
{
    elips_ensure_tp_schema();
    $db = elips_db();

    $stmt = $db->prepare('SELECT id, title FROM modules WHERE curriculum_version_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$versionId]);
    $modules = [];
    foreach ($stmt->fetchAll() as $row) {
        $modules[(int) $row['id']] = ['id' => (int) $row['id'], 'title' => (string) $row['title'], 'lessons' => []];
    }

    $stmt = $db->prepare(
        "SELECT l.id, l.module_id, l.title,
                CASE WHEN COALESCE(d.objective, '') <> ''
                       OR EXISTS (SELECT 1 FROM lesson_materials lm WHERE lm.lesson_id = l.id)
                       OR EXISTS (SELECT 1 FROM lesson_tasks lt WHERE lt.lesson_id = l.id)
                     THEN 1 ELSE 0 END AS filled
         FROM lessons l
         LEFT JOIN lesson_details d ON d.lesson_id = l.id
         WHERE l.curriculum_version_id = ?
         ORDER BY l.sort_order ASC, l.id ASC"
    );
    $stmt->execute([$versionId]);
    foreach ($stmt->fetchAll() as $row) {
        $mid = (int) $row['module_id'];
        if (!isset($modules[$mid])) {
            continue;
        }
        $modules[$mid]['lessons'][] = [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'filled' => (int) $row['filled'] === 1,
        ];
    }

    $locked = elips_tp_locked_numbers($versionId);
    $number = 0;
    foreach ($modules as &$module) {
        foreach ($module['lessons'] as &$lesson) {
            $number++;
            $lesson['number'] = $number;
            $lesson['locked'] = isset($locked[$number]);
            $lesson['lockedBy'] = $locked[$number] ?? [];
        }
        unset($lesson);
    }
    unset($module);

    return array_values($modules);
}

/** Saran nama angkatan berikutnya, mis. "MS Office 13". */
function elips_cp_next_name(array $course, array $classNames): string
{
    $title = trim((string) $course['title']);
    if (!$classNames) {
        return preg_match('/\d\s*$/', $title) ? $title : $title . ' 1';
    }
    $base = trim(preg_replace('/\s*\d+\s*$/', '', $title));
    if ($base === '') {
        $base = $title;
    }
    $max = 0;
    foreach ($classNames as $name) {
        if (preg_match('/(\d+)\s*$/', (string) $name, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    if ($max === 0) {
        $max = count($classNames);
    }
    return $base . ' ' . ($max + 1);
}

/** Seluruh data halaman publikasi untuk satu kursus. */
function elips_cp_payload(int $courseId): array
{
    $db = elips_db();
    $course = elips_cp_course($courseId);
    $version = elips_current_curriculum_version($courseId);
    $tree = $version ? elips_cp_tree((int) $version['id']) : [];

    $lessonTotal = 0;
    $filled = 0;
    foreach ($tree as $module) {
        foreach ($module['lessons'] as $lesson) {
            $lessonTotal++;
            if ($lesson['filled']) {
                $filled++;
            }
        }
    }

    $instructor = $course['instructor_id'] ? elips_user_row((int) $course['instructor_id']) : null;

    $stmt = $db->prepare(
        "SELECT c.id, c.code, c.name, c.status, c.period_start, c.period_end, c.quota, c.location, c.room,
                c.instructor_id, c.completed_at, u.name AS instructor_name,
                (SELECT COUNT(*) FROM class_sessions cs WHERE cs.class_id = c.id AND cs.status <> 'rescheduled') AS sessions,
                (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id AND e.status IN ('active', 'pending')) AS students
         FROM classes c
         LEFT JOIN users u ON u.id = c.instructor_id
         WHERE c.course_id = ?
         ORDER BY c.period_start DESC, c.id DESC"
    );
    $stmt->execute([$courseId]);
    $classes = [];
    $classNames = [];
    foreach ($stmt->fetchAll() as $row) {
        $classNames[] = (string) $row['name'];
        $classes[] = [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'status' => (string) $row['status'],
            'periodStart' => $row['period_start'] ? substr((string) $row['period_start'], 0, 10) : null,
            'periodEnd' => $row['period_end'] ? substr((string) $row['period_end'], 0, 10) : null,
            'quota' => (int) $row['quota'],
            'students' => (int) $row['students'],
            'sessions' => (int) $row['sessions'],
            'missingSessions' => max(0, $lessonTotal - (int) $row['sessions']),
            'instructorName' => (string) ($row['instructor_name'] ?? ''),
            'completedAt' => $row['completed_at'] ? substr((string) $row['completed_at'], 0, 10) : null,
            'overdue' => $row['status'] === 'ongoing' && $row['period_end'] && substr((string) $row['period_end'], 0, 10) < elips_class_today(),
        ];
    }

    // Bawaan formulir angkatan baru: ikuti angkatan terakhir.
    $last = $classes ? $classes[0] : null;
    $defaults = ['quota' => 20, 'location' => (string) ($course['location'] ?? ''), 'room' => '', 'startTime' => '09:00', 'endTime' => '11:00', 'weekdays' => [], 'jp' => 2];
    if ($last) {
        $defaults['quota'] = $last['quota'] > 0 ? $last['quota'] : 20;
        $row = $db->prepare('SELECT location, room FROM classes WHERE id = ?');
        $row->execute([$last['id']]);
        $info = $row->fetch();
        $defaults['location'] = (string) ($info['location'] ?? '');
        $defaults['room'] = (string) ($info['room'] ?? '');
        $sess = $db->prepare(
            "SELECT session_date, start_time, end_time, planned_jp FROM class_sessions
             WHERE class_id = ? AND status <> 'rescheduled' ORDER BY session_number ASC LIMIT 12"
        );
        $sess->execute([$last['id']]);
        $days = [];
        foreach ($sess->fetchAll() as $i => $s) {
            if ($i === 0) {
                $defaults['startTime'] = substr((string) $s['start_time'], 0, 5);
                $defaults['endTime'] = substr((string) $s['end_time'], 0, 5);
                if ((float) $s['planned_jp'] > 0) {
                    $defaults['jp'] = (float) $s['planned_jp'];
                }
            }
            $days[(int) (new DateTimeImmutable((string) $s['session_date']))->format('N')] = true;
        }
        $defaults['weekdays'] = array_keys($days);
        sort($defaults['weekdays']);
    }

    $name = elips_cp_next_name($course, $classNames);

    $tentors = [];
    foreach ($db->query("SELECT id, name FROM users WHERE role = 'instruktur' AND status = 'aktif' ORDER BY name ASC")->fetchAll() as $t) {
        $tentors[] = ['id' => (int) $t['id'], 'name' => (string) $t['name']];
    }

    // Kursus lain yang strukturnya bisa disalin (judul saja).
    $copy = [];
    $stmt = $db->prepare(
        "SELECT c.id, c.title,
                (SELECT COUNT(*) FROM modules m WHERE m.curriculum_version_id = cv.id) AS modules,
                (SELECT COUNT(*) FROM lessons l WHERE l.curriculum_version_id = cv.id) AS lessons
         FROM courses c
         JOIN curriculum_versions cv ON cv.id = (
              SELECT MAX(x.id) FROM curriculum_versions x WHERE x.course_id = c.id AND x.status <> 'retired')
         WHERE c.id <> ?
         ORDER BY c.title ASC"
    );
    $stmt->execute([$courseId]);
    foreach ($stmt->fetchAll() as $row) {
        if ((int) $row['lessons'] > 0) {
            $copy[] = ['id' => (int) $row['id'], 'title' => (string) $row['title'], 'modules' => (int) $row['modules'], 'lessons' => (int) $row['lessons']];
        }
    }

    $priceOk = (float) $course['price'] > 0;
    $structureOk = $lessonTotal > 0;

    return [
        'course' => [
            'id' => (int) $course['id'],
            'title' => (string) $course['title'],
            'status' => (string) $course['status'],
            'price' => (float) $course['price'],
            'instructorId' => $course['instructor_id'] === null ? null : (int) $course['instructor_id'],
            'instructorName' => $instructor ? (string) $instructor['name'] : '',
        ],
        'version' => $version ? ['id' => (int) $version['id'], 'code' => (string) $version['code'], 'status' => (string) $version['status']] : null,
        'modules' => $tree,
        'totals' => ['modules' => count($tree), 'lessons' => $lessonTotal, 'filled' => $filled],
        'checklist' => [
            ['key' => 'price', 'label' => 'Harga kursus diisi', 'ok' => $priceOk, 'hint' => 'Ubah kursus di Kelola Kursus untuk mengisi harga.'],
            ['key' => 'structure', 'label' => 'Struktur modul & materi dibuat', 'ok' => $structureOk, 'hint' => 'Tambahkan minimal satu modul dan satu materi.'],
        ],
        'canPublish' => $priceOk && $structureOk,
        'angkatan' => $classes,
        'suggest' => ['name' => $name, 'defaults' => $defaults],
        'tentors' => $tentors,
        'copySources' => $copy,
    ];
}

function elips_action_cp_get(array $p): array
{
    elips_require_academic_access();
    return elips_ok('', elips_cp_payload((int) ($p['courseId'] ?? 0)));
}

/**
 * Simpan seluruh struktur (modul + judul materi) sekaligus.
 * payload: courseId, modules: [{id?, title, lessons: [{id?, title}]}]
 * Urutan array = urutan tampil = nomor sesi.
 */
function elips_action_cp_structure_save(array $p): array
{
    $actor = elips_require_academic_access();
    $course = elips_cp_course((int) ($p['courseId'] ?? 0));
    $courseId = (int) $course['id'];
    $version = elips_cp_version($course, (int) $actor['id']);
    $versionId = (int) $version['id'];
    elips_ensure_tp_schema();

    $input = $p['modules'] ?? null;
    if (!is_array($input)) {
        return elips_fail('Data struktur tidak valid.');
    }
    if (count($input) > 60) {
        return elips_fail('Maksimal 60 modul per kursus.');
    }

    $db = elips_db();
    $stmt = $db->prepare('SELECT id FROM modules WHERE curriculum_version_id = ?');
    $stmt->execute([$versionId]);
    $existingModules = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $stmt = $db->prepare('SELECT id, title FROM lessons WHERE curriculum_version_id = ?');
    $stmt->execute([$versionId]);
    $existingLessons = [];
    foreach ($stmt->fetchAll() as $row) {
        $existingLessons[(int) $row['id']] = (string) $row['title'];
    }

    // Normalisasi + validasi masukan.
    $modules = [];
    $usedModules = [];
    $usedLessons = [];
    $lessonTotal = 0;
    foreach (array_values($input) as $mi => $m) {
        $m = is_array($m) ? $m : [];
        $title = trim((string) ($m['title'] ?? ''));
        if ($title === '') {
            return elips_fail('Judul modul ke-' . ($mi + 1) . ' belum diisi.');
        }
        if (mb_strlen($title) > 200) {
            return elips_fail('Judul modul ke-' . ($mi + 1) . ' terlalu panjang (maks 200 karakter).');
        }
        $mid = (int) ($m['id'] ?? 0);
        if ($mid > 0) {
            if (!in_array($mid, $existingModules, true)) {
                return elips_fail('Modul tidak ditemukan pada kurikulum ini. Muat ulang halaman.');
            }
            if (isset($usedModules[$mid])) {
                return elips_fail('Modul ganda terdeteksi. Muat ulang halaman.');
            }
            $usedModules[$mid] = true;
        }
        $lessons = [];
        foreach (array_values((array) ($m['lessons'] ?? [])) as $li => $l) {
            $l = is_array($l) ? $l : [];
            $lt = trim((string) ($l['title'] ?? ''));
            if ($lt === '') {
                return elips_fail('Judul materi ke-' . ($li + 1) . ' pada modul "' . $title . '" belum diisi.');
            }
            if (mb_strlen($lt) > 200) {
                return elips_fail('Judul materi "' . mb_substr($lt, 0, 30) . '..." terlalu panjang (maks 200 karakter).');
            }
            $lid = (int) ($l['id'] ?? 0);
            if ($lid > 0) {
                if (!isset($existingLessons[$lid])) {
                    return elips_fail('Materi tidak ditemukan pada kurikulum ini. Muat ulang halaman.');
                }
                if (isset($usedLessons[$lid])) {
                    return elips_fail('Materi ganda terdeteksi. Muat ulang halaman.');
                }
                $usedLessons[$lid] = true;
            }
            $lessons[] = ['id' => $lid, 'title' => $lt];
            $lessonTotal++;
        }
        $modules[] = ['id' => $mid, 'title' => $title, 'lessons' => $lessons];
    }
    if ($lessonTotal > 300) {
        return elips_fail('Maksimal 300 materi per kursus.');
    }

    // Sesi yang sudah dikonfirmasi pada angkatan berjalan tidak boleh berubah.
    $locked = elips_tp_locked_numbers($versionId);
    if ($locked) {
        $oldAtNumber = [];
        foreach (elips_tp_lessons($versionId) as $row) {
            $oldAtNumber[(int) $row['number']] = ['id' => (int) $row['id'], 'title' => (string) $row['title']];
        }
        $newAtNumber = [];
        $n = 0;
        foreach ($modules as $m) {
            foreach ($m['lessons'] as $l) {
                $n++;
                $newAtNumber[$n] = $l;
            }
        }
        foreach ($locked as $number => $classNames) {
            $old = $oldAtNumber[$number] ?? null;
            $new = $newAtNumber[$number] ?? null;
            if (!$old || !$new || (int) $new['id'] !== $old['id'] || $new['title'] !== $old['title']) {
                return elips_fail(
                    'Sesi ' . $number . ' sudah dikonfirmasi pada angkatan ' . implode(', ', $classNames)
                    . ', jadi tidak bisa diubah, dipindah, atau dihapus.'
                );
            }
        }
    }

    $db->beginTransaction();
    try {
        $insModule = $db->prepare(
            'INSERT INTO modules (course_id, curriculum_version_id, instructor_id, title, description, sort_order, created_at)
             VALUES (?, ?, ?, ?, "", ?, UTC_TIMESTAMP())'
        );
        $updModule = $db->prepare('UPDATE modules SET title = ?, sort_order = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
        $insLesson = $db->prepare(
            "INSERT INTO lessons (module_id, course_id, curriculum_version_id, instructor_id, title, type, content, sort_order, created_at)
             VALUES (?, ?, ?, ?, ?, 'text', '', ?, UTC_TIMESTAMP())"
        );
        $updLesson = $db->prepare(
            'UPDATE lessons SET module_id = ?, title = ?, sort_order = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?'
        );

        $keptLessons = [];
        $titleByNumber = [];
        $number = 0;
        foreach ($modules as $mi => $m) {
            if ($m['id'] > 0) {
                $updModule->execute([$m['title'], $mi + 1, $m['id']]);
                $moduleId = $m['id'];
            } else {
                $insModule->execute([$courseId, $versionId, $course['instructor_id'], $m['title'], $mi + 1]);
                $moduleId = (int) $db->lastInsertId();
            }
            foreach ($m['lessons'] as $li => $l) {
                $number++;
                if ($l['id'] > 0) {
                    $updLesson->execute([$moduleId, $l['title'], $li + 1, $l['id']]);
                    $lessonId = $l['id'];
                } else {
                    $insLesson->execute([$moduleId, $courseId, $versionId, $course['instructor_id'], $l['title'], $li + 1]);
                    $lessonId = (int) $db->lastInsertId();
                }
                $keptLessons[$lessonId] = true;
                $titleByNumber[$number] = ['title' => $l['title'], 'moduleId' => $moduleId];
            }
        }

        // Hapus yang tidak lagi ada pada daftar.
        $delLesson = $db->prepare('DELETE FROM lessons WHERE id = ?');
        $removedLessons = 0;
        foreach ($existingLessons as $lid => $title) {
            if (!isset($keptLessons[$lid])) {
                $delLesson->execute([$lid]);
                $removedLessons++;
            }
        }
        $delModule = $db->prepare('DELETE FROM modules WHERE id = ?');
        foreach ($existingModules as $mid) {
            if (!isset($usedModules[$mid])) {
                $delModule->execute([$mid]);
            }
        }

        // Jadwal yang belum berjalan mengikuti judul & modul terbaru.
        $stmt = $db->prepare(
            "SELECT id FROM classes WHERE curriculum_version_id = ? AND status IN ('draft','open','ready','ongoing')"
        );
        $stmt->execute([$versionId]);
        $sync = $db->prepare(
            "UPDATE class_sessions SET title = ?, module_id = ?, updated_at = UTC_TIMESTAMP()
             WHERE class_id = ? AND session_number = ? AND status = 'scheduled'"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $classId) {
            foreach ($titleByNumber as $num => $info) {
                $sync->execute([$info['title'], $info['moduleId'], (int) $classId, $num]);
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    elips_audit(
        'update',
        'curriculum',
        $versionId,
        $course['title'],
        'Menyusun struktur kursus "' . $course['title'] . '": ' . count($modules) . ' modul, ' . $lessonTotal . ' materi'
            . ($removedLessons > 0 ? ' (' . $removedLessons . ' materi dihapus)' : '') . '.',
        $actor
    );

    return elips_ok('Struktur disimpan: ' . count($modules) . ' modul, ' . $lessonTotal . ' materi.', elips_cp_payload($courseId));
}

/** Salin struktur (judul saja) dari kursus lain ke kursus yang masih kosong. */
function elips_action_cp_structure_copy(array $p): array
{
    $actor = elips_require_academic_access();
    $course = elips_cp_course((int) ($p['courseId'] ?? 0));
    $courseId = (int) $course['id'];
    $source = elips_cp_course((int) ($p['fromCourseId'] ?? 0));
    if ((int) $source['id'] === $courseId) {
        return elips_fail('Pilih kursus lain sebagai sumber salinan.');
    }
    $version = elips_cp_version($course, (int) $actor['id']);
    $versionId = (int) $version['id'];

    $db = elips_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM modules WHERE curriculum_version_id = ?');
    $stmt->execute([$versionId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return elips_fail('Struktur kursus ini sudah ada. Salin hanya bisa dilakukan pada kursus yang masih kosong.');
    }

    $sourceVersion = elips_current_curriculum_version((int) $source['id']);
    if (!$sourceVersion) {
        return elips_fail('Kursus sumber belum memiliki struktur.');
    }

    $stmt = $db->prepare('SELECT id, title FROM modules WHERE curriculum_version_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([(int) $sourceVersion['id']]);
    $sourceModules = $stmt->fetchAll();
    if (!$sourceModules) {
        return elips_fail('Kursus sumber belum memiliki struktur.');
    }

    $insModule = $db->prepare(
        'INSERT INTO modules (course_id, curriculum_version_id, instructor_id, title, description, sort_order, created_at)
         VALUES (?, ?, ?, ?, "", ?, UTC_TIMESTAMP())'
    );
    $insLesson = $db->prepare(
        "INSERT INTO lessons (module_id, course_id, curriculum_version_id, instructor_id, title, type, content, sort_order, created_at)
         VALUES (?, ?, ?, ?, ?, 'text', '', ?, UTC_TIMESTAMP())"
    );
    $lessonQuery = $db->prepare('SELECT title FROM lessons WHERE module_id = ? ORDER BY sort_order ASC, id ASC');

    $db->beginTransaction();
    try {
        $lessonCount = 0;
        foreach ($sourceModules as $i => $m) {
            $insModule->execute([$courseId, $versionId, $course['instructor_id'], $m['title'], $i + 1]);
            $moduleId = (int) $db->lastInsertId();
            $lessonQuery->execute([(int) $m['id']]);
            foreach ($lessonQuery->fetchAll(PDO::FETCH_COLUMN) as $li => $title) {
                $insLesson->execute([$moduleId, $courseId, $versionId, $course['instructor_id'], $title, $li + 1]);
                $lessonCount++;
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    elips_audit(
        'create',
        'curriculum',
        $versionId,
        $course['title'],
        'Menyalin struktur (judul saja) dari kursus "' . $source['title'] . '" ke "' . $course['title'] . '": '
            . count($sourceModules) . ' modul, ' . $lessonCount . ' materi.',
        $actor
    );

    return elips_ok('Struktur disalin: ' . count($sourceModules) . ' modul, ' . $lessonCount . ' materi. Isi materi diisi tentor.', elips_cp_payload($courseId));
}

/**
 * Buat angkatan baru dalam satu langkah, lengkap dengan jadwal sesi otomatis
 * (sesi ke-N = materi ke-N pada kurikulum).
 */
function elips_action_cp_angkatan_create(array $p): array
{
    $actor = elips_require_academic_access();
    $course = elips_cp_course((int) ($p['courseId'] ?? 0));
    $courseId = (int) $course['id'];
    if ($course['status'] !== 'published') {
        return elips_fail('Publikasikan kursus terlebih dahulu sebelum membuat angkatan.');
    }
    $version = elips_current_curriculum_version($courseId);
    if (!$version || !in_array($version['status'], ['published', 'locked'], true)) {
        return elips_fail('Kurikulum kursus belum berstatus Published.');
    }
    $versionId = (int) $version['id'];

    $payload = elips_cp_payload($courseId);
    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') {
        $name = $payload['suggest']['name'];
    }
    if (mb_strlen($name) > 150) {
        return elips_fail('Nama angkatan terlalu panjang.');
    }

    $db = elips_db();
    $stmt = $db->prepare('SELECT id FROM classes WHERE course_id = ? AND name = ? LIMIT 1');
    $stmt->execute([$courseId, $name]);
    if ($stmt->fetch()) {
        return elips_fail('Angkatan "' . $name . '" sudah ada pada kursus ini.');
    }
    $code = trim((string) ($p['code'] ?? ''));
    if ($code === '') {
        $code = elips_generate_class_code($courseId, $name);
    }
    $stmt = $db->prepare('SELECT id FROM classes WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    if ($stmt->fetch()) {
        return elips_fail('Kode angkatan sudah dipakai.');
    }

    $instructorId = (int) ($p['instructorId'] ?? 0);
    if ($instructorId <= 0) {
        $instructorId = (int) ($course['instructor_id'] ?? 0);
    }
    if ($instructorId <= 0 || !elips_is_active_instructor($instructorId)) {
        return elips_fail('Pilih tentor yang berstatus aktif untuk angkatan ini.');
    }

    $periodStart = elips_normalize_date($p['periodStart'] ?? null);
    if ($periodStart === null) {
        return elips_fail('Tanggal mulai angkatan wajib diisi.');
    }
    $quota = max(0, (int) ($p['quota'] ?? 0));
    if ($quota <= 0) {
        return elips_fail('Kuota peserta harus lebih dari 0.');
    }
    $lessons = elips_tp_lessons($versionId);
    $generate = !array_key_exists('generateSessions', $p) || !empty($p['generateSessions']);
    $dates = [];
    $start = '';
    $end = '';
    $jp = max(0, (float) ($p['jp'] ?? 2));
    if ($generate) {
        if (!$lessons) {
            return elips_fail('Kurikulum belum memiliki materi untuk dijadwalkan.');
        }
        if (count($lessons) > 100) {
            return elips_fail('Jumlah materi melebihi 100, buat jadwal manual di menu Jadwal.');
        }
        $weekdays = [];
        foreach ((array) ($p['weekdays'] ?? []) as $w) {
            $w = (int) $w;
            if ($w >= 1 && $w <= 7) {
                $weekdays[$w] = true;
            }
        }
        if (!$weekdays) {
            return elips_fail('Pilih minimal satu hari belajar dalam seminggu.');
        }
        try {
            $start = elips_session_time_value($p['startTime'] ?? null, 'Jam mulai');
            $end = elips_session_time_value($p['endTime'] ?? null, 'Jam selesai');
        } catch (ElipsError $e) {
            return elips_fail($e->getMessage());
        }
        if ($end <= $start) {
            return elips_fail('Jam selesai harus setelah jam mulai.');
        }
        $cursor = new DateTimeImmutable($periodStart, new DateTimeZone('UTC'));
        $guard = 0;
        while (count($dates) < count($lessons) && $guard < 1500) {
            if (isset($weekdays[(int) $cursor->format('N')])) {
                $dates[] = $cursor->format('Y-m-d');
            }
            $cursor = $cursor->modify('+1 day');
            $guard++;
        }
    }

    $periodEnd = $dates ? end($dates) : elips_normalize_date($p['periodEnd'] ?? null);

    elips_ensure_class_close_schema();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO classes
             (code, name, course_id, curriculum_version_id, branch_id, instructor_id, period_start, period_end, quota,
              location, room, schedule_note, planned_jp, status, legacy_import, notes, created_by, updated_by)
             VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, '', ?, ?, 0, '', ?, ?)"
        );
        $stmt->execute([
            $code, $name, $courseId, $versionId, $instructorId, $periodStart, $periodEnd, $quota,
            trim((string) ($p['location'] ?? '')), trim((string) ($p['room'] ?? '')),
            $generate ? round($jp * count($lessons), 2) : 0, 'open',
            (int) $actor['id'], (int) $actor['id'],
        ]);
        $classId = (int) $db->lastInsertId();

        $db->prepare(
            'INSERT INTO class_instructor_histories (class_id, instructor_id, started_at, ended_at, reason, actor_id)
             VALUES (?, ?, ?, NULL, ?, ?)'
        )->execute([$classId, $instructorId, $periodStart, 'Penetapan tentor awal saat angkatan dibuat.', (int) $actor['id']]);

        $sessionCount = 0;
        if ($generate) {
            $class = elips_class_row($classId);
            foreach ($lessons as $i => $lesson) {
                elips_session_store($class, [
                    'sessionDate' => $dates[$i],
                    'startTime' => $start,
                    'endTime' => $end,
                    'title' => $lesson['title'],
                    'moduleId' => $lesson['module_id'],
                    'plannedJp' => $jp,
                    'status' => 'scheduled',
                ], $actor, $i + 1);
                $sessionCount++;
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    elips_audit(
        'create',
        'class',
        $classId,
        $name,
        'Membuat angkatan "' . $name . '" (' . $code . ') untuk kursus "' . $course['title'] . '"'
            . ($sessionCount > 0 ? ' dengan ' . $sessionCount . ' sesi terjadwal otomatis.' : '.'),
        $actor
    );

    $tentor = elips_user_row($instructorId);
    if ($tentor && filter_var($tentor['email'], FILTER_VALIDATE_EMAIL) && $tentor['status'] !== 'nonaktif') {
        $totals = $payload['totals'];
        $empty = max(0, $totals['lessons'] - $totals['filled']);
        elips_create_notification([
            'type' => 'angkatan_baru',
            'recipient_id' => $instructorId,
            'recipient_name' => $tentor['name'],
            'recipient_email' => $tentor['email'],
            'recipient_role' => $tentor['role'],
            'subject' => 'Angkatan baru: ' . $name,
            'body' => 'Halo ' . $tentor['name'] . ",\n\nAnda ditugaskan sebagai tentor angkatan \"" . $name . '" kursus "' . $course['title'] . '".'
                . ($empty > 0 ? "\n\nMasih ada " . $empty . ' materi yang isinya belum diisi (tujuan, materi, penugasan, durasi). Silakan lengkapi di menu Kurikulum pada portal tentor.' : '')
                . "\n\nSalam,\nELIPS LMS",
            'entity' => 'class',
            'entity_id' => $classId,
            'entity_label' => $name,
        ]);
    }

    elips_sync_class_status();   // tanggal mulai sudah lewat -> langsung Berlangsung
    $data = elips_cp_payload($courseId);
    $data['createdClassId'] = $classId;
    $data['createdSessions'] = $sessionCount;
    return elips_ok(
        'Angkatan "' . $name . '" dibuat' . ($sessionCount > 0 ? ' dengan ' . $sessionCount . ' sesi terjadwal.' : '.'),
        $data
    );
}