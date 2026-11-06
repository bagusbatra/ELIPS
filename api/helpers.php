<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - helper backend: respons, sesi, validasi, audit,
// notifikasi, dan sinkronisasi data turunan.
// ============================================================

class ElipsError extends RuntimeException
{
}

function elips_ok(string $message = '', $data = null): array
{
    return ['ok' => true, 'message' => $message, 'data' => $data];
}

function elips_fail(string $message): array
{
    return ['ok' => false, 'message' => $message, 'data' => null];
}

function elips_json_response(array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function elips_request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// ---------- Sesi ----------

function elips_session_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $row = elips_user_row((int) $_SESSION['user_id']);
    if (!$row) {
        unset($_SESSION['user_id'], $_SESSION['logged_in_at']);
        return null;
    }
    return $row;
}

function elips_session_payload(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'userId' => (int) $_SESSION['user_id'],
        'loggedInAt' => $_SESSION['logged_in_at'] ?? gmdate('c'),
    ];
}

function elips_require_login(): array
{
    $user = elips_session_user();
    if (!$user) {
        throw new ElipsError('Anda harus masuk terlebih dahulu.');
    }
    if ($user['status'] !== 'aktif') {
        throw new ElipsError('Akun Anda nonaktif. Hubungi administrator.');
    }
    return $user;
}

function elips_require_admin(): array
{
    $user = elips_require_login();
    if ($user['role'] !== 'admin') {
        throw new ElipsError('Anda tidak berhak melakukan tindakan ini.');
    }
    return $user;
}

// ---------- Query dasar ----------

function elips_user_row(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_user_by_email(string $email): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower($email)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_course_row(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_enrollment_row(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM enrollments WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_branch_row(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM branches WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_class_row(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM classes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_curriculum_version_row(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM curriculum_versions WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_session_row(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM class_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_attendance_row(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM attendances WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_attendance_correction_row(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM attendance_corrections WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_current_curriculum_version(int $courseId): ?array
{
    $db = elips_db();
    $stmt = $db->prepare(
        "SELECT * FROM curriculum_versions
         WHERE course_id = ? AND status <> 'retired'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$courseId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $stmt = $db->prepare('SELECT * FROM curriculum_versions WHERE course_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$courseId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_next_curriculum_code(int $courseId): string
{
    $db = elips_db();
    $stmt = $db->prepare('SELECT code FROM curriculum_versions WHERE course_id = ?');
    $stmt->execute([$courseId]);
    $existing = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    for ($major = 1; $major <= 999; $major++) {
        $code = $major . '.0';
        if (!in_array($code, $existing, true)) {
            return $code;
        }
    }
    return 'v' . (count($existing) + 1);
}

/**
 * Pastikan kursus memiliki versi kurikulum. Dipakai oleh operasi konten
 * v3.0 agar modul/materi selalu terhubung ke versi yang valid.
 */
function elips_ensure_curriculum_version(int $courseId, ?int $actorId = null): array
{
    $existing = elips_current_curriculum_version($courseId);
    if ($existing) {
        return $existing;
    }

    $course = elips_course_row($courseId);
    $code = elips_next_curriculum_code($courseId);
    $name = 'Kurikulum ' . $code . ($course ? ' — ' . $course['title'] : '');
    $stmt = elips_db()->prepare(
        "INSERT INTO curriculum_versions
         (course_id, code, name, description, status, effective_date, notes, created_by, updated_by)
         VALUES (?, ?, ?, '', 'draft', NULL, 'Dibuat otomatis saat konten pertama ditambahkan.', ?, ?)"
    );
    $stmt->execute([$courseId, $code, $name, $actorId, $actorId]);
    return elips_curriculum_version_row((int) elips_db()->lastInsertId());
}

function elips_curriculum_is_editable(array $version): bool
{
    return in_array($version['status'], ['draft', 'published'], true);
}

function elips_can_manage_class(array $class, array $actor): bool
{
    if ($actor['role'] === 'admin') {
        return true;
    }
    return $actor['role'] === 'instruktur'
        && $class['instructor_id'] !== null
        && (int) $class['instructor_id'] === (int) $actor['id'];
}

function elips_class_enrollment_count(int $classId, bool $activeOnly = true): int
{
    $sql = 'SELECT COUNT(*) FROM enrollments WHERE class_id = ?';
    if ($activeOnly) {
        $sql .= " AND status IN ('active', 'pending')";
    }
    $stmt = elips_db()->prepare($sql);
    $stmt->execute([$classId]);
    return (int) $stmt->fetchColumn();
}

function elips_is_active_instructor(?int $id): bool
{
    if (!$id) {
        return false;
    }
    $user = elips_user_row($id);
    return $user !== null && $user['role'] === 'instruktur' && $user['status'] === 'aktif';
}

function elips_can_manage_course(array $course, array $actor): bool
{
    if ($actor['role'] === 'admin') {
        return true;
    }
    return $actor['role'] === 'instruktur'
        && $course['instructor_id'] !== null
        && (int) $course['instructor_id'] === (int) $actor['id'];
}

// ---------- Serialisasi ----------

function elips_datetime_utc(?string $value): ?DateTimeImmutable
{
    if ($value === null || $value === '') {
        return null;
    }
    try {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        return null;
    }
}

function elips_iso(?string $value): ?string
{
    $date = elips_datetime_utc($value);
    return $date ? $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z') : null;
}

function elips_date_only(?string $value): ?string
{
    $date = elips_datetime_utc($value);
    return $date ? $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d') : null;
}

function elips_normalize_date($value): ?string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    $parts = explode('-', $value);
    if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
        return null;
    }
    return $value;
}

/**
 * Sanitasi HTML untuk konten materi tipe teks. Mengikuti allowlist
 * yang sama dengan sanitizer client-side agar materi yang dikirim
 * langsung ke API tidak dapat menyisipkan script/event handler.
 */
function elips_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '' || !class_exists('DOMDocument')) {
        return strip_tags($html);
    }

    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'code' => [], 'pre' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'h5' => [], 'h6' => [], 'div' => [], 'span' => [], 'hr' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];
    $drop = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'base',
        'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math',
        'template', 'noscript',
    ];

    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><body>' . $html . '</body>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) {
        return strip_tags($html);
    }

    $sanitize = function (DOMNode $node) use (&$sanitize, $allowed, $drop): void {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (!($child instanceof DOMElement)) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, $drop, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!array_key_exists($tag, $allowed)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                if (strpos($name, 'on') === 0 || !in_array($name, $allowed[$tag], true)) {
                    $child->removeAttribute($attribute->name);
                    continue;
                }
                if ($name === 'href' || $name === 'src') {
                    $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', $attribute->value));
                    if (strpos($normalized, 'javascript:') === 0
                        || strpos($normalized, 'vbscript:') === 0
                        || strpos($normalized, 'data:') === 0) {
                        $child->removeAttribute($attribute->name);
                    }
                }
            }
            if ($tag === 'a' && $child->getAttribute('href') !== '') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
            $sanitize($child);
        }
    };

    $sanitize($body);

    $result = '';
    foreach ($body->childNodes as $child) {
        $result .= $doc->saveHTML($child);
    }
    return trim($result);
}

function elips_serialize_user(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'role' => $row['role'],
        'status' => $row['status'],
        'createdAt' => elips_date_only($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_public_user(int $id): ?array
{
    $row = elips_user_row($id);
    return $row ? elips_serialize_user($row) : null;
}

function elips_course_public(int $id): ?array
{
    $course = elips_course_row($id);
    if (!$course) {
        return null;
    }
    $db = elips_db();
    $stmt = $db->prepare('SELECT name FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$course['category_id'] === null ? 0 : (int) $course['category_id']]);
    $categoryName = $stmt->fetchColumn() ?: $course['category'];

    $stmt = $db->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$course['instructor_id'] === null ? 0 : (int) $course['instructor_id']]);
    $instructorName = $stmt->fetchColumn() ?: '';

    $version = elips_current_curriculum_version($id);
    $versionId = $version ? (int) $version['id'] : null;

    $moduleCount = 0;
    $lessonCount = 0;
    if ($versionId !== null) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM modules WHERE curriculum_version_id = ?');
        $stmt->execute([$versionId]);
        $moduleCount = (int) $stmt->fetchColumn();
        $stmt = $db->prepare('SELECT COUNT(*) FROM lessons WHERE curriculum_version_id = ?');
        $stmt->execute([$versionId]);
        $lessonCount = (int) $stmt->fetchColumn();
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM classes WHERE course_id = ?');
    $stmt->execute([$id]);
    $classCount = (int) $stmt->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status IN ('active', 'pending')");
    $stmt->execute([$id]);
    $enrollmentCount = (int) $stmt->fetchColumn();

    return [
        'id' => (int) $course['id'],
        'title' => $course['title'],
        'category' => $categoryName,
        'categoryId' => $course['category_id'] === null ? null : (int) $course['category_id'],
        'description' => $course['description'],
        'instructorId' => $course['instructor_id'] === null ? null : (int) $course['instructor_id'],
        'instructorName' => $instructorName,
        'curriculumVersionId' => $versionId,
        'curriculumVersionCode' => $version ? $version['code'] : null,
        'curriculumVersionStatus' => $version ? $version['status'] : null,
        'classes' => $classCount,
        'enrollments' => $enrollmentCount,
        'modules' => $moduleCount,
        'lessons' => $lessonCount,
        'totalLessons' => $lessonCount,
        'status' => $course['status'],
        'createdAt' => elips_date_only($course['created_at']),
        'updatedAt' => elips_iso($course['updated_at']),
    ];
}

function elips_category_public(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'slug' => $row['slug'],
        'status' => $row['status'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ] : null;
}

function elips_resolve_category_id(?int $categoryId, string $name): ?int
{
    $db = elips_db();
    if ($categoryId !== null && $categoryId > 0) {
        $stmt = $db->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$categoryId]);
        $found = $stmt->fetchColumn();
        if ($found !== false) {
            return (int) $found;
        }
    }

    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $stmt = $db->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $found = $stmt->fetchColumn();
    if ($found !== false) {
        return (int) $found;
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug = trim((string) $slug, '-') ?: 'kategori';
    $base = $slug;
    $suffix = 2;
    $check = $db->prepare('SELECT COUNT(*) FROM categories WHERE slug = ?');
    while (true) {
        $check->execute([$slug]);
        if ((int) $check->fetchColumn() === 0) {
            break;
        }
        $slug = $base . '-' . $suffix++;
    }
    $stmt = $db->prepare("INSERT INTO categories (name, slug, status) VALUES (?, ?, 'active')");
    $stmt->execute([$name, $slug]);
    return (int) $db->lastInsertId();
}

function elips_curriculum_public(int $id): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT cv.*, c.title AS course_title,
            (SELECT COUNT(*) FROM modules m WHERE m.curriculum_version_id = cv.id) AS module_count,
            (SELECT COUNT(*) FROM lessons l WHERE l.curriculum_version_id = cv.id) AS lesson_count,
            (SELECT COUNT(*) FROM classes cl WHERE cl.curriculum_version_id = cv.id) AS class_count
         FROM curriculum_versions cv
         JOIN courses c ON c.id = cv.course_id
         WHERE cv.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'courseId' => (int) $row['course_id'],
        'courseTitle' => $row['course_title'],
        'code' => $row['code'],
        'name' => $row['name'],
        'description' => $row['description'],
        'status' => $row['status'],
        'effectiveDate' => elips_date_only($row['effective_date']),
        'notes' => $row['notes'],
        'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
        'updatedBy' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
        'modules' => (int) $row['module_count'],
        'lessons' => (int) $row['lesson_count'],
        'classes' => (int) $row['class_count'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_branch_public(int $id): ?array
{
    $row = elips_branch_row($id);
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'name' => $row['name'],
        'address' => $row['address'],
        'city' => $row['city'],
        'phone' => $row['phone'],
        'manager' => $row['manager'],
        'status' => $row['status'],
        'notes' => $row['notes'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_class_public(int $id): ?array
{
    $stmt = elips_db()->prepare(
        "SELECT cl.*, c.title AS course_title, u.name AS instructor_name,
            cv.code AS version_code, cv.name AS version_name,
            b.code AS branch_code, b.name AS branch_name, b.city AS branch_city,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = cl.id AND e.status IN ('active', 'pending')) AS active_count,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = cl.id) AS enrollment_count
         FROM classes cl
         JOIN courses c ON c.id = cl.course_id
         LEFT JOIN users u ON u.id = cl.instructor_id
         LEFT JOIN curriculum_versions cv ON cv.id = cl.curriculum_version_id
         LEFT JOIN branches b ON b.id = cl.branch_id
         WHERE cl.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'code' => $row['code'],
        'name' => $row['name'],
        'courseId' => (int) $row['course_id'],
        'courseTitle' => $row['course_title'],
        'curriculumVersionId' => (int) $row['curriculum_version_id'],
        'curriculumVersionCode' => $row['version_code'],
        'curriculumVersionName' => $row['version_name'],
        'branchId' => $row['branch_id'] === null ? null : (int) $row['branch_id'],
        'branchCode' => $row['branch_code'] ?? '',
        'branchName' => $row['branch_name'] ?? '',
        'branchCity' => $row['branch_city'] ?? '',
        'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
        'instructorName' => $row['instructor_name'] ?? '',
        'periodStart' => elips_date_only($row['period_start']),
        'periodEnd' => elips_date_only($row['period_end']),
        'quota' => (int) $row['quota'],
        'location' => $row['location'],
        'room' => $row['room'],
        'scheduleNote' => $row['schedule_note'],
        'plannedJp' => (float) $row['planned_jp'],
        'status' => $row['status'],
        'legacyImport' => (bool) $row['legacy_import'],
        'notes' => $row['notes'],
        'enrollmentCount' => (int) $row['enrollment_count'],
        'activeEnrollmentCount' => (int) $row['active_count'],
        'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
        'updatedBy' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_instructor_history_public(int $id): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT h.*, u.name AS instructor_name, a.name AS actor_name
         FROM class_instructor_histories h
         LEFT JOIN users u ON u.id = h.instructor_id
         LEFT JOIN users a ON a.id = h.actor_id
         WHERE h.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'classId' => (int) $row['class_id'],
        'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
        'instructorName' => $row['instructor_name'] ?? '',
        'startedAt' => elips_date_only($row['started_at']),
        'endedAt' => elips_date_only($row['ended_at']),
        'reason' => $row['reason'],
        'actorId' => $row['actor_id'] === null ? null : (int) $row['actor_id'],
        'actorName' => $row['actor_name'] ?? '',
        'createdAt' => elips_iso($row['created_at']),
    ];
}

function elips_module_public(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM modules WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'courseId' => (int) $row['course_id'],
        'curriculumVersionId' => (int) $row['curriculum_version_id'],
        'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'order' => (int) $row['sort_order'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_lesson_public(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM lessons WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'moduleId' => (int) $row['module_id'],
        'courseId' => (int) $row['course_id'],
        'curriculumVersionId' => (int) $row['curriculum_version_id'],
        'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
        'title' => $row['title'],
        'type' => $row['type'],
        'content' => $row['content'],
        'order' => (int) $row['sort_order'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_enrollment_public(int $id): ?array
{
    $row = elips_enrollment_row($id);
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'studentId' => (int) $row['student_id'],
        'courseId' => (int) $row['course_id'],
        'classId' => $row['class_id'] === null ? null : (int) $row['class_id'],
        'enrolledBy' => $row['enrolled_by'],
        'enrolledAt' => elips_date_only($row['enrolled_at']),
        'status' => $row['status'],
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

// ---------- Materi kelas (Phase 4) ----------

function elips_class_material_settings_row(int $classId): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM class_material_settings WHERE class_id = ? LIMIT 1');
    $stmt->execute([$classId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_class_material_settings_public(int $classId): array
{
    $row = elips_class_material_settings_row($classId);
    if (!$row) {
        return [
            'id' => null,
            'classId' => $classId,
            'accessMode' => 'open',
            'requireInstructorApproval' => false,
            'notes' => '',
            'updatedBy' => null,
            'createdAt' => null,
            'updatedAt' => null,
        ];
    }
    return [
        'id' => (int) $row['id'],
        'classId' => (int) $row['class_id'],
        'accessMode' => $row['access_mode'],
        'requireInstructorApproval' => (bool) $row['require_instructor_approval'],
        'notes' => $row['notes'],
        'updatedBy' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_class_material_public(array $row, bool $includeContent = true): array
{
    $result = [
        'id' => (int) $row['id'],
        'classId' => (int) $row['class_id'],
        'lessonId' => $row['lesson_id'] === null ? null : (int) $row['lesson_id'],
        'lessonTitle' => $row['lesson_title'] ?? '',
        'sessionId' => $row['session_id'] === null ? null : (int) $row['session_id'],
        'sessionTitle' => $row['session_title'] ?? '',
        'sessionDate' => elips_date_only($row['session_date'] ?? null),
        'title' => $row['title'],
        'description' => $row['description'],
        'type' => $row['type'],
        'fileName' => $row['file_name'],
        'fileSize' => $row['file_size'] === null ? null : (int) $row['file_size'],
        'mimeType' => $row['mime_type'],
        'hasFile' => $row['file_path'] !== '',
        'accessLevel' => $row['access_level'],
        'availableFrom' => elips_date_only($row['available_from']),
        'status' => $row['status'],
        'reason' => $row['reason'],
        'order' => (int) $row['sort_order'],
        'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
        'updatedBy' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
    if ($includeContent) {
        $result['content'] = $row['content'];
    }
    return $result;
}

function elips_class_material_row(int $id): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT cm.*, l.title AS lesson_title,
            s.title AS session_title, s.session_date, s.status AS session_status
         FROM class_materials cm
         LEFT JOIN lessons l ON l.id = cm.lesson_id
         LEFT JOIN class_sessions s ON s.id = cm.session_id
         WHERE cm.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_class_materials_for(int $classId, bool $includeHidden = false): array
{
    $sql = 'SELECT cm.*, l.title AS lesson_title,
            s.title AS session_title, s.session_date, s.status AS session_status
         FROM class_materials cm
         LEFT JOIN lessons l ON l.id = cm.lesson_id
         LEFT JOIN class_sessions s ON s.id = cm.session_id
         WHERE cm.class_id = ?';
    if (!$includeHidden) {
        $sql .= " AND cm.status = 'active'";
    }
    $sql .= ' ORDER BY cm.sort_order ASC, cm.id ASC';
    $stmt = elips_db()->prepare($sql);
    $stmt->execute([$classId]);
    return $stmt->fetchAll();
}

function elips_class_lesson_progress_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'classId' => (int) $row['class_id'],
        'enrollmentId' => (int) $row['enrollment_id'],
        'studentId' => (int) $row['student_id'],
        'lessonId' => (int) $row['lesson_id'],
        'completedBy' => $row['completed_by'] === null ? null : (int) $row['completed_by'],
        'completedAt' => elips_iso($row['completed_at']),
        'createdAt' => elips_iso($row['created_at']),
    ];
}

function elips_class_material_progress_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'classId' => (int) $row['class_id'],
        'enrollmentId' => (int) $row['enrollment_id'],
        'studentId' => (int) $row['student_id'],
        'materialId' => (int) $row['material_id'],
        'completedBy' => $row['completed_by'] === null ? null : (int) $row['completed_by'],
        'completedAt' => elips_iso($row['completed_at']),
        'createdAt' => elips_iso($row['created_at']),
    ];
}

function elips_class_curriculum_lessons(int $classId): array
{
    $stmt = elips_db()->prepare(
        'SELECT l.*, m.title AS module_title, m.sort_order AS module_order
         FROM lessons l
         JOIN modules m ON m.id = l.module_id
         JOIN classes cl ON cl.curriculum_version_id = l.curriculum_version_id
             AND cl.course_id = l.course_id
         WHERE cl.id = ?
         ORDER BY m.sort_order ASC, m.id ASC, l.sort_order ASC, l.id ASC'
    );
    $stmt->execute([$classId]);
    return $stmt->fetchAll();
}

function elips_class_module_session_dates(int $classId): array
{
    $stmt = elips_db()->prepare(
        "SELECT module_id, MIN(session_date) AS session_date
         FROM class_sessions
         WHERE class_id = ? AND module_id IS NOT NULL
           AND status NOT IN ('cancelled', 'no_show', 'rescheduled')
         GROUP BY module_id"
    );
    $stmt->execute([$classId]);
    $dates = [];
    foreach ($stmt->fetchAll() as $row) {
        $dates[(int) $row['module_id']] = elips_date_only($row['session_date']);
    }
    return $dates;
}

/**
 * Menghitung status akses materi kelas untuk satu enrollment.
 * $staffPreview = true membuka seluruh konten untuk pratinjau Admin/Instruktur.
 */
function elips_class_material_state(array $class, ?array $enrollment, bool $staffPreview = false): array
{
    $classId = (int) $class['id'];
    $settings = elips_class_material_settings_public($classId);
    $lessonRows = elips_class_curriculum_lessons($classId);
    $materialRows = elips_class_materials_for($classId, $staffPreview);
    $lessonProgress = [];
    $materialProgress = [];

    if ($enrollment) {
        $enrollmentId = (int) $enrollment['id'];
        $stmt = elips_db()->prepare('SELECT * FROM class_lesson_progress WHERE enrollment_id = ? ORDER BY id ASC');
        $stmt->execute([$enrollmentId]);
        foreach ($stmt->fetchAll() as $row) {
            $lessonProgress[(int) $row['lesson_id']] = elips_class_lesson_progress_public($row);
        }
        $stmt = elips_db()->prepare('SELECT * FROM class_material_progress WHERE enrollment_id = ? ORDER BY id ASC');
        $stmt->execute([$enrollmentId]);
        foreach ($stmt->fetchAll() as $row) {
            $materialProgress[(int) $row['material_id']] = elips_class_material_progress_public($row);
        }
    }

    $moduleDates = elips_class_module_session_dates($classId);
    $today = gmdate('Y-m-d');
    $mode = $settings['accessMode'];
    $previousComplete = true;
    $lessons = [];
    foreach ($lessonRows as $row) {
        $lessonId = (int) $row['id'];
        $progress = $lessonProgress[$lessonId] ?? null;
        $completed = $progress !== null;
        $availableAt = $moduleDates[(int) $row['module_id']] ?? null;
        $locked = false;
        $reason = '';

        if (!$completed && !$staffPreview) {
            if ($mode === 'sequential') {
                if (!$previousComplete) {
                    $locked = true;
                    $reason = 'Selesaikan materi sebelumnya terlebih dahulu.';
                }
            } elseif ($mode === 'scheduled') {
                if ($availableAt === null) {
                    $locked = true;
                    $reason = 'Jadwal sesi untuk modul ini belum tersedia.';
                } elseif ($availableAt > $today) {
                    $locked = true;
                    $reason = 'Materi tersedia mulai ' . $availableAt . '.';
                }
            } elseif ($mode === 'conditional') {
                if (!$previousComplete) {
                    $locked = true;
                    $reason = 'Selesaikan materi sebelumnya terlebih dahulu.';
                } elseif ($availableAt !== null && $availableAt > $today) {
                    $locked = true;
                    $reason = 'Materi tersedia mulai ' . $availableAt . '.';
                }
            }
        }

        $lessons[] = [
            'kind' => 'lesson',
            'id' => $lessonId,
            'moduleId' => (int) $row['module_id'],
            'moduleTitle' => $row['module_title'] ?? '',
            'moduleOrder' => (int) ($row['module_order'] ?? 0),
            'title' => $row['title'],
            'type' => $row['type'],
            'order' => (int) $row['sort_order'],
            'content' => $locked ? '' : $row['content'],
            'completed' => $completed,
            'completedAt' => $progress['completedAt'] ?? null,
            'completedBy' => $progress['completedBy'] ?? null,
            'locked' => $locked,
            'reason' => $reason,
            'availableAt' => $availableAt,
        ];

        if (!$completed) {
            $previousComplete = false;
        }
    }

    $materials = [];
    foreach ($materialRows as $row) {
        $materialId = (int) $row['id'];
        $progress = $materialProgress[$materialId] ?? null;
        $completed = $progress !== null;
        $availableFrom = elips_date_only($row['available_from']);
        $sessionDate = elips_date_only($row['session_date'] ?? null);
        $availableAt = $availableFrom;
        if ($sessionDate !== null && ($availableAt === null || $sessionDate > $availableAt)) {
            $availableAt = $sessionDate;
        }

        $locked = false;
        $reason = '';
        if (!$completed && !$staffPreview) {
            if ($row['status'] !== 'active') {
                $locked = true;
                $reason = 'Materi ini disembunyikan oleh pengajar.';
            } elseif ($row['lesson_id'] !== null && !isset($lessonProgress[(int) $row['lesson_id']])) {
                $locked = true;
                $reason = 'Selesaikan materi terkait terlebih dahulu.';
            } elseif ($availableFrom !== null && $availableFrom > $today) {
                $locked = true;
                $reason = 'Materi tersedia mulai ' . $availableFrom . '.';
            } elseif ($sessionDate !== null
                && in_array((string) ($row['session_status'] ?? ''), ['cancelled', 'no_show', 'rescheduled'], true)) {
                $locked = true;
                $reason = 'Sesi terkait tidak berlangsung.';
            } elseif ($sessionDate !== null && $sessionDate > $today) {
                $locked = true;
                $reason = 'Materi tersedia setelah sesi ' . $sessionDate . '.';
            }
        }

        $item = elips_class_material_public($row, $staffPreview || !$locked);
        $item['kind'] = 'material';
        $item['completed'] = $completed;
        $item['completedAt'] = $progress['completedAt'] ?? null;
        $item['completedBy'] = $progress['completedBy'] ?? null;
        $item['locked'] = $locked;
        $item['reason'] = $reason;
        $item['availableAt'] = $availableAt;
        $materials[] = $item;
    }

    $lessonCompleted = count(array_filter($lessons, fn ($item) => $item['completed']));
    $materialCompleted = count(array_filter($materials, fn ($item) => $item['completed']));
    $required = array_merge(
        array_filter($lessons, fn ($item) => true),
        array_filter($materials, fn ($item) => $item['accessLevel'] === 'required')
    );
    $requiredCompleted = count(array_filter($required, fn ($item) => $item['completed']));

    return [
        'settings' => $settings,
        'lessons' => $lessons,
        'materials' => $materials,
        'stats' => [
            'lessonTotal' => count($lessons),
            'lessonCompleted' => $lessonCompleted,
            'materialTotal' => count($materials),
            'materialCompleted' => $materialCompleted,
            'requiredTotal' => count($required),
            'requiredCompleted' => $requiredCompleted,
        ],
    ];
}


function elips_time_short(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (preg_match('/^(\d{2}):(\d{2})/', $value, $matches)) {
        return $matches[1] . ':' . $matches[2];
    }
    return null;
}

function elips_time_normalized($value): ?string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return null;
    }
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $text, $matches)) {
        return null;
    }
    return $matches[1] . ':' . $matches[2] . ':' . ($matches[3] ?? '00');
}

function elips_can_manage_session(array $session, array $actor): bool
{
    if ($actor['role'] === 'admin') {
        return true;
    }
    if ($actor['role'] !== 'instruktur') {
        return false;
    }
    $class = elips_class_row((int) $session['class_id']);
    if (!$class) {
        return false;
    }
    $actorId = (int) $actor['id'];
    $sessionInstructor = $session['instructor_id'] === null ? null : (int) $session['instructor_id'];
    if ($sessionInstructor !== null && $sessionInstructor === $actorId) {
        return true;
    }
    return $sessionInstructor === null
        && $class['instructor_id'] !== null
        && (int) $class['instructor_id'] === $actorId;
}

function elips_attendance_policy(): array
{
    $config = elips_config();
    $validAttendance = ['present', 'late', 'permission', 'sick', 'absent', 'excused'];
    $validSession = ['scheduled', 'confirmed', 'completed', 'rescheduled', 'cancelled', 'no_show'];
    $present = array_values(array_intersect(
        $validAttendance,
        (array) ($config['attendance_present_statuses'] ?? ['present', 'late'])
    ));
    $counted = array_values(array_intersect(
        $validSession,
        (array) ($config['attendance_counted_statuses'] ?? ['confirmed', 'completed'])
    ));
    return [
        'presentStatuses' => $present ?: ['present', 'late'],
        'countedStatuses' => $counted ?: ['confirmed', 'completed'],
        'presentPercentageBasis' => 'JP',
    ];
}

function elips_session_recap_weight(array $session): float
{
    $planned = (float) $session['planned_jp'];
    if ($planned > 0) {
        return $planned;
    }
    $actual = (float) $session['actual_jp'];
    if ($actual > 0) {
        return $actual;
    }
    return 1.0;
}

function elips_session_public(int $id): ?array
{
    $stmt = elips_db()->prepare(
        "SELECT s.*, cl.code AS class_code, cl.name AS class_name, cl.course_id, cl.branch_id AS branch_id, cl.instructor_id AS class_instructor_id,
            c.title AS course_title, u.name AS instructor_name, m.title AS module_title,
            b.code AS branch_code, b.name AS branch_name, b.city AS branch_city,
            (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status = 'present') AS present_count,
            (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status = 'late') AS late_count,
            (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status = 'permission') AS permission_count,
            (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status = 'sick') AS sick_count,
            (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status = 'absent') AS absent_count,
            (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status = 'excused') AS excused_count,
            (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id) AS attendance_count
         FROM class_sessions s
         JOIN classes cl ON cl.id = s.class_id
         JOIN courses c ON c.id = cl.course_id
         LEFT JOIN users u ON u.id = s.instructor_id
         LEFT JOIN modules m ON m.id = s.module_id
         LEFT JOIN branches b ON b.id = cl.branch_id
         WHERE s.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $instructorId = $row['instructor_id'] === null
        ? ($row['class_instructor_id'] === null ? null : (int) $row['class_instructor_id'])
        : (int) $row['instructor_id'];
    $instructorName = $row['instructor_name'] ?? '';
    if ($instructorName === '' && $instructorId !== null) {
        $instructor = elips_user_row($instructorId);
        $instructorName = $instructor ? $instructor['name'] : '';
    }
    return [
        'id' => (int) $row['id'],
        'classId' => (int) $row['class_id'],
        'classCode' => $row['class_code'],
        'className' => $row['class_name'],
        'courseId' => (int) $row['course_id'],
        'courseTitle' => $row['course_title'],
        'branchId' => $row['branch_id'] === null ? null : (int) $row['branch_id'],
        'branchCode' => $row['branch_code'] ?? '',
        'branchName' => $row['branch_name'] ?? '',
        'branchCity' => $row['branch_city'] ?? '',
        'sessionNumber' => (int) $row['session_number'],
        'title' => $row['title'],
        'moduleId' => $row['module_id'] === null ? null : (int) $row['module_id'],
        'moduleTitle' => $row['module_title'] ?? '',
        'sessionDate' => elips_date_only($row['session_date']),
        'startTime' => elips_time_short($row['start_time']),
        'endTime' => elips_time_short($row['end_time']),
        'location' => $row['location'],
        'room' => $row['room'],
        'instructorId' => $instructorId,
        'instructorName' => $instructorName,
        'plannedJp' => (float) $row['planned_jp'],
        'actualJp' => (float) $row['actual_jp'],
        'status' => $row['status'],
        'rescheduledFromId' => $row['rescheduled_from_id'] === null ? null : (int) $row['rescheduled_from_id'],
        'notes' => $row['notes'],
        'presentCount' => (int) $row['present_count'],
        'lateCount' => (int) $row['late_count'],
        'permissionCount' => (int) $row['permission_count'],
        'sickCount' => (int) $row['sick_count'],
        'absentCount' => (int) $row['absent_count'],
        'excusedCount' => (int) $row['excused_count'],
        'attendanceCount' => (int) $row['attendance_count'],
        'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
        'updatedBy' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_attendance_public(int $id): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT a.*, u.name AS student_name, r.name AS recorder_name
         FROM attendances a
         LEFT JOIN users u ON u.id = a.student_id
         LEFT JOIN users r ON r.id = a.recorded_by
         WHERE a.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'sessionId' => (int) $row['session_id'],
        'enrollmentId' => (int) $row['enrollment_id'],
        'studentId' => (int) $row['student_id'],
        'studentName' => $row['student_name'] ?? '',
        'status' => $row['status'],
        'recordedBy' => $row['recorded_by'] === null ? null : (int) $row['recorded_by'],
        'recordedByName' => $row['recorder_name'] ?? '',
        'recordedAt' => elips_iso($row['recorded_at']),
        'note' => $row['note'],
        'correctedAt' => elips_iso($row['corrected_at']),
        'correctionReason' => $row['correction_reason'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_attendance_correction_public(int $id): ?array
{
    $row = elips_attendance_correction_row($id);
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'attendanceId' => (int) $row['attendance_id'],
        'sessionId' => (int) $row['session_id'],
        'enrollmentId' => (int) $row['enrollment_id'],
        'oldStatus' => $row['old_status'],
        'newStatus' => $row['new_status'],
        'actorId' => $row['actor_id'] === null ? null : (int) $row['actor_id'],
        'actorName' => $row['actor_name'],
        'actorRole' => $row['actor_role'],
        'reason' => $row['reason'],
        'correctedAt' => elips_iso($row['corrected_at']) ?: elips_iso($row['created_at']),
        'createdAt' => elips_iso($row['created_at']),
    ];
}

/**
 * Rekap kehadiran berbasis JP. Hanya sesi yang memenuhi kebijakan
 * yang dihitung sebagai JP wajib; status di luar kebijakan tetap
 * dilaporkan terpisah.
 */
function elips_attendance_recap(int $classId, ?int $studentId = null): ?array
{
    $class = elips_class_row($classId);
    if (!$class) {
        return null;
    }
    $db = elips_db();
    $course = elips_course_row((int) $class['course_id']);

    $stmt = $db->prepare('SELECT * FROM class_sessions WHERE class_id = ? ORDER BY session_number ASC, id ASC');
    $stmt->execute([$classId]);
    $sessions = $stmt->fetchAll();

    $policy = elips_attendance_policy();
    $countedSessions = array_values(array_filter($sessions, function ($session) use ($policy) {
        return in_array($session['status'], $policy['countedStatuses'], true);
    }));
    $requiredJp = 0.0;
    foreach ($countedSessions as $session) {
        $requiredJp += elips_session_recap_weight($session);
    }
    $plannedJp = 0.0;
    $actualJp = 0.0;
    foreach ($sessions as $session) {
        $plannedJp += (float) $session['planned_jp'];
        if (!in_array($session['status'], ['cancelled', 'no_show', 'rescheduled'], true)) {
            $actualJp += (float) $session['actual_jp'];
        }
    }

    $sql = "SELECT * FROM enrollments WHERE class_id = ? AND status IN ('active', 'pending', 'completed')";
    $params = [$classId];
    if ($studentId !== null) {
        $sql .= ' AND student_id = ?';
        $params[] = $studentId;
    }
    $sql .= ' ORDER BY id ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $enrollments = $stmt->fetchAll();

    $attendanceMap = [];
    if ($enrollments) {
        $stmt = $db->prepare(
            'SELECT a.* FROM attendances a
             JOIN class_sessions s ON s.id = a.session_id
             WHERE s.class_id = ?'
        );
        $stmt->execute([$classId]);
        foreach ($stmt->fetchAll() as $attendance) {
            $key = (int) $attendance['enrollment_id'] . ':' . (int) $attendance['session_id'];
            $attendanceMap[$key] = $attendance;
        }
    }

    $students = [];
    foreach ($enrollments as $enrollment) {
        $student = elips_user_row((int) $enrollment['student_id']);
        $counts = ['present' => 0, 'late' => 0, 'permission' => 0, 'sick' => 0, 'absent' => 0, 'excused' => 0];
        $missing = 0;
        $presentJp = 0.0;
        $sessionDetails = [];

        foreach ($sessions as $session) {
            $sessionId = (int) $session['id'];
            $attendance = $attendanceMap[(int) $enrollment['id'] . ':' . $sessionId] ?? null;
            $status = $attendance ? $attendance['status'] : null;
            $counted = in_array($session['status'], $policy['countedStatuses'], true);
            $weight = elips_session_recap_weight($session);

            if ($status === null) {
                $missing++;
            } elseif (isset($counts[$status])) {
                $counts[$status]++;
            }

            if ($counted) {
                if ($status !== null && in_array($status, $policy['presentStatuses'], true)) {
                    $presentJp += $weight;
                } elseif ($status === null) {
                    // Tidak ada catatan pada sesi yang sudah dihitung tetap
                    // dilaporkan sebagai alfa pada rekap, bukan hadir.
                    $counts['absent']++;
                }
            }

            $sessionDetails[] = [
                'sessionId' => $sessionId,
                'sessionNumber' => (int) $session['session_number'],
                'sessionDate' => elips_date_only($session['session_date']),
                'sessionStatus' => $session['status'],
                'plannedJp' => (float) $session['planned_jp'],
                'actualJp' => (float) $session['actual_jp'],
                'status' => $status,
            ];
        }

        $countedPresent = $requiredJp > 0 ? $presentJp : 0.0;
        $percentage = $requiredJp > 0 ? round($countedPresent / $requiredJp * 100, 2) : 0.0;

        $students[] = [
            'enrollmentId' => (int) $enrollment['id'],
            'studentId' => (int) $enrollment['student_id'],
            'studentName' => $student ? $student['name'] : 'Peserta #' . $enrollment['student_id'],
            'studentEmail' => $student ? $student['email'] : '',
            'enrollmentStatus' => $enrollment['status'],
            'present' => $counts['present'],
            'late' => $counts['late'],
            'permission' => $counts['permission'],
            'sick' => $counts['sick'],
            'absent' => $counts['absent'],
            'excused' => $counts['excused'],
            'missing' => $missing,
            'presentJp' => round($countedPresent, 2),
            'requiredJp' => round($requiredJp, 2),
            'percentage' => $percentage,
            'sessions' => $sessionDetails,
        ];
    }

    return [
        'classId' => (int) $class['id'],
        'classCode' => $class['code'],
        'className' => $class['name'],
        'courseId' => (int) $class['course_id'],
        'courseTitle' => $course ? $course['title'] : 'Kursus #' . $class['course_id'],
        'totalSessions' => count($sessions),
        'countedSessions' => count($countedSessions),
        'plannedJp' => round($plannedJp, 2),
        'actualJp' => round($actualJp, 2),
        'policy' => $policy,
        'students' => $students,
    ];
}

function elips_grade_public(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM grades WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'enrollmentId' => $row['enrollment_id'] === null ? null : (int) $row['enrollment_id'],
        'courseId' => (int) $row['course_id'],
        'studentId' => (int) $row['student_id'],
        'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
        'score' => (int) $row['score'],
        'grade' => $row['grade'],
        'note' => $row['note'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_certificate_public(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM certificates WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'studentId' => (int) $row['student_id'],
        'courseId' => (int) $row['course_id'],
        'enrollmentId' => $row['enrollment_id'] === null ? null : (int) $row['enrollment_id'],
        'certificateNumber' => $row['certificate_number'],
        'status' => $row['status'],
        'grade' => $row['grade'],
        'score' => $row['score'] === null ? null : (int) $row['score'],
        'issuedByInstructorAt' => elips_iso($row['issued_by_instructor_at']),
        'approvedByAdminId' => $row['approved_by_admin_id'] === null ? null : (int) $row['approved_by_admin_id'],
        'approvedAt' => elips_iso($row['approved_at']),
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

// ---------- Nilai & sertifikat ----------

function elips_grade_letter($score): string
{
    $value = (float) $score;
    if ($value >= 90) return 'A';
    if ($value >= 80) return 'A-';
    if ($value >= 75) return 'B+';
    if ($value >= 70) return 'B';
    if ($value >= 65) return 'B-';
    if ($value >= 60) return 'C+';
    if ($value >= 55) return 'C';
    if ($value >= 50) return 'D';
    return 'E';
}

function elips_unique_certificate_number(string $prefix): string
{
    $db = elips_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM certificates WHERE certificate_number = ?');
    do {
        $number = $prefix . strtoupper(bin2hex(random_bytes(3)));
        $stmt->execute([$number]);
    } while ((int) $stmt->fetchColumn() > 0);
    return $number;
}

function elips_generate_certificate_number(): string
{
    return elips_unique_certificate_number('CERT-' . gmdate('Y') . '-');
}

// ---------- Data turunan ----------

function elips_sync_content_owners(): void
{
    $db = elips_db();
    $db->exec('UPDATE modules m JOIN courses c ON c.id = m.course_id SET m.instructor_id = c.instructor_id');
    $db->exec('UPDATE lessons l JOIN courses c ON c.id = l.course_id SET l.instructor_id = c.instructor_id');
}

function elips_sync_instructor_name(int $instructorId): void
{
    // Nama instruktur tidak didenormalisasi di database; dibaca lewat JOIN.
    // Fungsi disediakan agar alur bisnis tetap eksplisit.
    if ($instructorId <= 0) {
        return;
    }
    elips_user_row($instructorId);
}

function elips_release_instructor_courses(int $instructorId, bool $onlyUnpublish): int
{
    $db = elips_db();
    if (!$onlyUnpublish) {
        $stmt = $db->prepare('UPDATE courses SET instructor_id = NULL, updated_at = UTC_TIMESTAMP() WHERE instructor_id = ?');
        $stmt->execute([$instructorId]);
        $changed = $stmt->rowCount();
        $stmt = $db->prepare("UPDATE courses SET status = 'draft', updated_at = UTC_TIMESTAMP() WHERE instructor_id = ? AND status = 'published'");
        $stmt->execute([$instructorId]);
        $changed += $stmt->rowCount();
        elips_sync_content_owners();
        return $changed;
    }

    $stmt = $db->prepare("UPDATE courses SET status = 'draft', updated_at = UTC_TIMESTAMP() WHERE instructor_id = ? AND status = 'published'");
    $stmt->execute([$instructorId]);
    return $stmt->rowCount();
}

// ---------- Audit trail ----------

function elips_audit(string $action, string $entity, ?int $entityId, string $entityLabel, string $detail, ?array $actor = null): void
{
    $actor = $actor ?: elips_session_user();
    $stmt = elips_db()->prepare(
        'INSERT INTO audit_logs (action, entity, entity_id, entity_label, actor_id, actor_name, actor_role, detail, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        $action,
        $entity,
        $entityId,
        $entityLabel,
        $actor ? (int) $actor['id'] : null,
        $actor ? $actor['name'] : 'Sistem',
        $actor ? $actor['role'] : 'sistem',
        $detail,
    ]);

    $limit = (int) elips_config()['audit_limit'];
    $db = elips_db();
    $count = (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    if ($count > $limit) {
        $excess = $count - $limit;
        $db->exec('DELETE FROM audit_logs ORDER BY id ASC LIMIT ' . $excess);
    }
}

// ---------- Notifikasi (outbox simulasi) ----------

function elips_create_notification(array $entry): void
{
    $db = elips_db();
    $stmt = $db->prepare(
        'INSERT INTO notifications
         (type, channel, simulated, status, recipient_id, recipient_name, recipient_email, recipient_role,
          subject, body, entity, entity_id, entity_label, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    $stmt->execute([
        $entry['type'],
        'email',
        1,
        'sent',
        $entry['recipient_id'] ?? null,
        $entry['recipient_name'] ?? '',
        $entry['recipient_email'] ?? '',
        $entry['recipient_role'] ?? 'peserta',
        $entry['subject'] ?? '',
        $entry['body'] ?? '',
        $entry['entity'] ?? '',
        $entry['entity_id'] ?? null,
        $entry['entity_label'] ?? '',
    ]);

    $limit = (int) elips_config()['notification_limit'];
    $count = (int) $db->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
    if ($count > $limit) {
        $db->exec('DELETE FROM notifications ORDER BY id ASC LIMIT ' . ($count - $limit));
    }
}

function elips_notify_course_published(int $courseId): void
{
    $course = elips_course_row($courseId);
    if (!$course) {
        return;
    }

    $db = elips_db();
    $stmt = $db->prepare(
        "SELECT DISTINCT u.* FROM users u
         JOIN enrollments e ON e.student_id = u.id
         WHERE e.course_id = ? AND COALESCE(e.status, 'active') = 'active' AND u.role = 'peserta'"
    );
    $stmt->execute([$courseId]);
    $recipients = $stmt->fetchAll();

    if ($course['instructor_id']) {
        $instructor = elips_user_row((int) $course['instructor_id']);
        if ($instructor && $instructor['role'] === 'instruktur') {
            $recipients[] = $instructor;
        }
    }

    $seen = [];
    foreach ($recipients as $user) {
        $id = (int) $user['id'];
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        if ($user['status'] === 'nonaktif' || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $isInstructor = $user['role'] === 'instruktur';
        $subject = 'Kursus "' . $course['title'] . '" telah dipublikasikan';
        $body = 'Halo ' . $user['name'] . ",\n\n"
            . ($isInstructor
                ? 'Kursus "' . $course['title'] . '" yang Anda ampu telah dipublikasikan dan dapat diakses oleh peserta.'
                : 'Kursus "' . $course['title'] . '" yang Anda ikuti telah dipublikasikan dan dapat diakses sekarang.')
            . "\n\nSilakan masuk ke ELIPS LMS untuk melihat materi dan melanjutkan pembelajaran."
            . "\n\nSalam,\nELIPS LMS";

        elips_create_notification([
            'type' => 'course_published',
            'recipient_id' => $id,
            'recipient_name' => $user['name'],
            'recipient_email' => $user['email'],
            'recipient_role' => $user['role'],
            'subject' => $subject,
            'body' => $body,
            'entity' => 'course',
            'entity_id' => $courseId,
            'entity_label' => $course['title'],
        ]);
    }
}

function elips_notify_certificate_published(int $certificateId): void
{
    $stmt = elips_db()->prepare('SELECT * FROM certificates WHERE id = ? LIMIT 1');
    $stmt->execute([$certificateId]);
    $certificate = $stmt->fetch();
    if (!$certificate) {
        return;
    }
    $student = elips_user_row((int) $certificate['student_id']);
    if (!$student || $student['status'] === 'nonaktif' || !filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $course = elips_course_row((int) $certificate['course_id']);
    $courseTitle = $course ? $course['title'] : 'Kursus #' . $certificate['course_id'];
    $scoreText = $certificate['score'] === null
        ? ''
        : ' dengan nilai ' . (int) $certificate['score']
            . ($certificate['grade'] ? ' (' . $certificate['grade'] . ')' : '');

    elips_create_notification([
        'type' => 'certificate_published',
        'recipient_id' => (int) $student['id'],
        'recipient_name' => $student['name'],
        'recipient_email' => $student['email'],
        'recipient_role' => $student['role'],
        'subject' => 'E-sertifikat "' . $courseTitle . '" telah terbit',
        'body' => 'Halo ' . $student['name'] . ",\n\n"
            . 'Selamat! E-sertifikat Anda untuk kursus "' . $courseTitle . '" telah disetujui Admin dan resmi terbit'
            . $scoreText . '.'
            . "\n\nNomor sertifikat: " . ($certificate['certificate_number'] ?: '-')
            . "\n\nSertifikat dapat dilihat dan diverifikasi melalui halaman ELIPS LMS."
            . "\n\nSalam,\nELIPS LMS",
        'entity' => 'certificate',
        'entity_id' => $certificateId,
        'entity_label' => $certificate['certificate_number'] ?: $courseTitle,
    ]);
}
