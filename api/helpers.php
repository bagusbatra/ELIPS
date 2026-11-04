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
    $stmt = elips_db()->prepare(
        'SELECT c.*, u.name AS instructor_name,
            (SELECT COUNT(*) FROM modules m WHERE m.course_id = c.id) AS module_count,
            (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count
         FROM courses c LEFT JOIN users u ON u.id = c.instructor_id
         WHERE c.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $lessonCount = (int) $row['lesson_count'];
    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'category' => $row['category'],
        'description' => $row['description'],
        'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
        'instructorName' => $row['instructor_name'] ?? '',
        'modules' => (int) $row['module_count'],
        'lessons' => $lessonCount,
        'totalLessons' => $lessonCount,
        'status' => $row['status'],
        'createdAt' => elips_date_only($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
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
        'enrolledBy' => $row['enrolled_by'],
        'enrolledAt' => elips_date_only($row['enrolled_at']),
        'status' => $row['status'],
        'updatedAt' => elips_iso($row['updated_at']),
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
