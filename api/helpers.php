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
        elips_session_forget();
        return null;
    }
    // Sesi lama (sebelum Fase 4) dianggap versi 0. Jika pengguna sudah
    // login ulang/ganti password, versi di database naik dan sesi lama
    // otomatis tidak berlaku.
    $sessionVersion = array_key_exists('session_version', $_SESSION)
        ? (int) $_SESSION['session_version']
        : 0;
    if ($sessionVersion !== (int) $row['session_version']) {
        elips_session_forget();
        return null;
    }
    if ($row['status'] !== 'aktif') {
        elips_session_forget();
        return null;
    }
    return $row;
}

function elips_session_forget(): void
{
    unset($_SESSION['user_id'], $_SESSION['logged_in_at'], $_SESSION['session_version']);
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

/**
 * Guard absensi harian: hanya admin dan karyawan yang boleh mencatat
 * absensi. Instruktur dan peserta tetap memakai alur LMS masing-masing.
 */
function elips_require_attendance_access(): array
{
    $user = elips_require_login();
    if (!in_array($user['role'], ['admin', 'karyawan'], true)) {
        throw new ElipsError('Anda tidak berhak mengakses absensi karyawan.');
    }
    return $user;
}

/** Zona waktu acuan absensi. Ubah di satu tempat bila kebijakan berubah. */
function elips_attendance_timezone(): string
{
    return 'Asia/Jakarta';
}

// ---------- Sesi diingat (remember token) ----------

function elips_remember_cookie_name(): string
{
    return 'elips_remember';
}

/** Masa berlaku sliding 30 hari. */
function elips_remember_ttl(): int
{
    return 30 * 24 * 60 * 60;
}

function elips_request_is_https(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return true;
    }
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwarded === 'https') {
        return true;
    }
    return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function elips_request_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function elips_request_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** Label ringkas perangkat, mis. "Android • Chrome". */
function elips_device_label(?string $userAgent = null): string
{
    $ua = strtolower($userAgent === null ? elips_request_user_agent() : $userAgent);

    $platform = 'Perangkat lain';
    if (strpos($ua, 'android') !== false) {
        $platform = 'Android';
    } elseif (strpos($ua, 'ipad') !== false) {
        $platform = 'iPad';
    } elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipod') !== false) {
        $platform = 'iPhone';
    } elseif (strpos($ua, 'windows') !== false) {
        $platform = 'Windows';
    } elseif (strpos($ua, 'mac os x') !== false || strpos($ua, 'macintosh') !== false) {
        $platform = 'macOS';
    } elseif (strpos($ua, 'linux') !== false) {
        $platform = 'Linux';
    }

    $browser = 'Browser lain';
    if (strpos($ua, 'edg/') !== false) {
        $browser = 'Edge';
    } elseif (strpos($ua, 'chrome/') !== false || strpos($ua, 'crios/') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($ua, 'firefox/') !== false || strpos($ua, 'fxios/') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($ua, 'safari/') !== false) {
        $browser = 'Safari';
    }

    return substr($platform . ' • ' . $browser, 0, 120);
}

function elips_remember_set_cookie(string $token): void
{
    if (headers_sent()) {
        return;
    }
    setcookie(elips_remember_cookie_name(), $token, [
        'expires' => time() + elips_remember_ttl(),
        'path' => '/',
        'secure' => elips_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function elips_remember_clear_cookie(): void
{
    if (!headers_sent()) {
        setcookie(elips_remember_cookie_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => elips_request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    unset($_COOKIE[elips_remember_cookie_name()]);
}

/** Token mentah dari cookie; hanya menerima format 64 karakter hex. */
function elips_remember_current_token(): string
{
    $raw = $_COOKIE[elips_remember_cookie_name()] ?? '';
    if (!is_string($raw) || !preg_match('/^[a-f0-9]{64}$/', $raw)) {
        return '';
    }
    return $raw;
}

function elips_user_device_by_token_hash(string $hash): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM user_devices WHERE token_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_user_device_insert(int $userId, string $label, string $token): int
{
    $now = gmdate('Y-m-d H:i:s');
    $expires = gmdate('Y-m-d H:i:s', time() + elips_remember_ttl());
    $stmt = elips_db()->prepare(
        'INSERT INTO user_devices
            (user_id, token_hash, device_label, user_agent, ip_address, created_at, last_used_at, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        hash('sha256', $token),
        $label,
        elips_request_user_agent(),
        elips_request_ip(),
        $now,
        $now,
        $expires,
    ]);
    return (int) elips_db()->lastInsertId();
}

/** Buang riwayat token yang sudah dicabut lebih dari 30 hari. */
function elips_user_devices_prune(): void
{
    elips_db()->exec(
        'DELETE FROM user_devices
         WHERE revoked_at IS NOT NULL AND revoked_at < (UTC_TIMESTAMP() - INTERVAL 30 DAY)'
    );
}

/**
 * Terbitkan token baru untuk pengguna. Pemanggil bertanggung jawab mencabut
 * perangkat lama lebih dulu agar aturan satu perangkat aktif tetap terjaga.
 */
function elips_remember_issue(int $userId, ?array $actor = null): string
{
    elips_user_devices_prune();
    $label = elips_device_label();
    $token = bin2hex(random_bytes(32));
    $deviceId = elips_user_device_insert($userId, $label, $token);
    elips_audit(
        'create',
        'user_device',
        $deviceId,
        $actor ? $actor['name'] : $label,
        'Mengingat perangkat "' . $label . '" selama 30 hari.',
        $actor
    );
    return $token;
}

/** Cabut semua token aktif milik pengguna. Mengembalikan jumlah perangkat. */
function elips_remember_revoke_all(
    int $userId,
    string $reason,
    ?array $actor = null,
    bool $audit = true,
    string $code = 'revoked'
): int {
    $db = elips_db();
    $stmt = $db->prepare('SELECT id, device_label FROM user_devices WHERE user_id = ? AND revoked_at IS NULL');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return 0;
    }
    $update = $db->prepare('UPDATE user_devices SET revoked_at = UTC_TIMESTAMP(), revoked_reason = ? WHERE id = ?');
    foreach ($rows as $row) {
        $update->execute([$code, (int) $row['id']]);
        if ($audit) {
            elips_audit(
                'revoke',
                'user_device',
                (int) $row['id'],
                $actor ? $actor['name'] : $row['device_label'],
                'Mencabut perangkat "' . $row['device_label'] . '". ' . $reason,
                $actor
            );
        }
    }
    return count($rows);
}

/** Cabut token yang sedang dipakai (dipanggil saat logout). */
function elips_remember_revoke_current(?array $actor = null): int
{
    $raw = elips_remember_current_token();
    if ($raw === '') {
        return 0;
    }
    $device = elips_user_device_by_token_hash(hash('sha256', $raw));
    if (!$device || $device['revoked_at'] !== null) {
        return 0;
    }
    $stmt = elips_db()->prepare(
        "UPDATE user_devices SET revoked_at = UTC_TIMESTAMP(), revoked_reason = 'logout' WHERE id = ?"
    );
    $stmt->execute([(int) $device['id']]);
    elips_audit(
        'revoke',
        'user_device',
        (int) $device['id'],
        $actor ? $actor['name'] : $device['device_label'],
        'Mencabut perangkat "' . $device['device_label'] . '" saat keluar.',
        $actor
    );
    return 1;
}

/**
 * Cari perangkat + pengguna dari token mentah. Hasil juga memuat baris
 * perangkat yang sudah dicabut agar pemakaian ulang token lama terdeteksi.
 */
function elips_remember_find(string $raw): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
        return null;
    }
    $device = elips_user_device_by_token_hash(hash('sha256', $raw));
    if (!$device) {
        return null;
    }
    $user = elips_user_row((int) $device['user_id']);
    if (!$user) {
        return null;
    }
    return [
        'device' => $device,
        'user' => $user,
        'revoked' => $device['revoked_at'] !== null,
        'reused' => $device['revoked_at'] !== null
            && ($device['revoked_reason'] ?? '') === 'rotated',
    ];
}

/**
 * Rotasi token: baris lama dicabut (disimpan sebagai riwayat) lalu baris
 * baru diterbitkan untuk perangkat yang sama.
 */
function elips_remember_rotate(array $device, array $user): string
{
    $db = elips_db();
    $stmt = $db->prepare(
        "UPDATE user_devices SET revoked_at = UTC_TIMESTAMP(), revoked_reason = 'rotated' WHERE id = ?"
    );
    $stmt->execute([(int) $device['id']]);

    elips_user_devices_prune();
    $label = trim((string) $device['device_label']) !== ''
        ? (string) $device['device_label']
        : elips_device_label();
    $token = bin2hex(random_bytes(32));
    elips_user_device_insert((int) $user['id'], $label, $token);
    return $token;
}

function elips_session_apply(array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['logged_in_at'] = gmdate('c');
    $_SESSION['session_version'] = (int) $user['session_version'];
}

// ---------- Rate limit percobaan gagal ----------

function elips_rate_limit_file(string $scope, string $key): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'elips_rate_limit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $safeScope = preg_replace('/[^a-z0-9_]+/i', '-', $scope) ?? 'scope';
    return $dir . DIRECTORY_SEPARATOR . $safeScope . '-' . hash('sha256', $key) . '.json';
}

/** Sisa detik blokir, atau 0 bila belum melewati batas. */
function elips_rate_limit_blocked(string $scope, string $key, int $max = 10, int $window = 60): int
{
    $file = elips_rate_limit_file($scope, $key);
    if (!is_file($file)) {
        return 0;
    }
    $decoded = json_decode((string) @file_get_contents($file), true);
    if (!is_array($decoded)) {
        return 0;
    }
    $now = time();
    $hits = array_values(array_filter($decoded, static function ($timestamp) use ($now, $window): bool {
        return is_int($timestamp) && $timestamp > ($now - $window);
    }));
    if (count($hits) < $max) {
        return 0;
    }
    return max(1, ((int) $hits[0] + $window) - $now);
}

function elips_rate_limit_hit(string $scope, string $key, int $window = 60): void
{
    $file = elips_rate_limit_file($scope, $key);
    $now = time();
    $decoded = is_file($file) ? json_decode((string) @file_get_contents($file), true) : [];
    $hits = is_array($decoded) ? $decoded : [];
    $hits = array_values(array_filter($hits, static function ($timestamp) use ($now, $window): bool {
        return is_int($timestamp) && $timestamp > ($now - $window);
    }));
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
}

function elips_rate_limit_clear(string $scope, string $key): void
{
    @unlink(elips_rate_limit_file($scope, $key));
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

function elips_user_divisions(): array
{
    return ['marketing', 'operasional', 'keuangan', 'hrd'];
}

function elips_normalize_division(string $division): string
{
    $division = strtolower(trim($division));
    return in_array($division, elips_user_divisions(), true) ? $division : '';
}

function elips_serialize_user(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'role' => $row['role'],
        'division' => $row['division'] ?? '',
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
 * Serialisasi absensi staf/admin. Foto bersifat opsional karena snapshot
 * tidak boleh membawa data biner; daftar absensi pribadi meminta foto
 * secara eksplisit melalui action khusus.
 */
function elips_staff_attendance_public(array $row, bool $includePhoto = true): array
{
    $photo = '';
    if ($includePhoto && !empty($row['photo'])) {
        $mime = trim((string) ($row['photo_mime'] ?? '')) ?: 'image/jpeg';
        $photo = 'data:' . $mime . ';base64,' . $row['photo'];
    }
    return [
        'id' => (int) $row['id'],
        'userId' => (int) $row['user_id'],
        'userName' => $row['user_name'] ?? '',
        'kind' => $row['kind'],
        'attendanceDate' => elips_date_only($row['attendance_date']),
        'takenAt' => elips_iso($row['taken_at']),
        'timezone' => $row['timezone'],
        'photo' => $photo,
        'photoMime' => $row['photo_mime'],
        'latitude' => $row['latitude'] === null ? null : (float) $row['latitude'],
        'longitude' => $row['longitude'] === null ? null : (float) $row['longitude'],
        'accuracyM' => $row['accuracy_m'] === null ? null : (float) $row['accuracy_m'],
        'locationLabel' => $row['location_label'],
        'locationSource' => $row['location_source'],
        'ipAddress' => $row['ip_address'],
        'userAgent' => $row['user_agent'],
        'platform' => $row['platform'],
        'language' => $row['language'],
        'screen' => $row['screen'],
        'deviceMemory' => $row['device_memory'] === null ? null : (float) $row['device_memory'],
        'hardwareConcurrency' => $row['hardware_concurrency'] === null ? null : (int) $row['hardware_concurrency'],
        'connectionType' => $row['connection_type'],
        'online' => (bool) $row['online'],
        'notes' => $row['notes'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

/**
 * Serialisasi jadwal shift karyawan. Jam dikembalikan dalam format HH:MM
 * agar mudah dipakai langsung oleh input time di klien.
 */
function elips_staff_shift_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'userId' => (int) $row['user_id'],
        'userName' => $row['user_name'] ?? '',
        'userEmail' => $row['user_email'] ?? '',
        'userRole' => $row['user_role'] ?? '',
        'shiftDate' => elips_date_only($row['shift_date']),
        'shiftType' => $row['shift_type'],
        'startTime' => substr((string) $row['start_time'], 0, 5),
        'endTime' => substr((string) $row['end_time'], 0, 5),
        'note' => $row['note'],
        'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

/**
 * Serialisasi surat masuk/keluar untuk halaman Admin. Semua field
 * detail dikembalikan agar form ubah dan tampilan detail tidak perlu
 * permintaan tambahan.
 */
function elips_letter_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'direction' => $row['direction'],
        'letterNumber' => $row['letter_number'],
        'subject' => $row['subject'],
        'sender' => $row['sender'],
        'recipient' => $row['recipient'],
        'letterDate' => elips_date_only($row['letter_date']),
        'handledDate' => $row['handled_date'] === null ? null : elips_date_only($row['handled_date']),
        'category' => $row['category'],
        'classification' => $row['classification'],
        'status' => $row['status'],
        'summary' => $row['summary'] ?? '',
        'attachmentUrl' => $row['attachment_url'],
        'notes' => $row['notes'],
        'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
        'createdByName' => $row['created_by_name'] ?? '',
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
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

// ---------- Tugas, submission, dan ujian (Phase 5) ----------

function elips_normalize_datetime($value): ?string
{
    $text = trim((string) ($value ?? ''));
    if ($text === '') {
        return null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?/', $text, $matches)) {
        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        $hour = (int) $matches[4];
        $minute = (int) $matches[5];
        $second = (int) ($matches[6] ?? 0);
        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
    try {
        $date = new DateTimeImmutable($text, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

function elips_assignment_status_valid(string $status): bool
{
    return in_array($status, ['draft', 'published', 'closed', 'archived'], true);
}

function elips_assignment_type_valid(string $type): bool
{
    return in_array($type, ['file_upload', 'text_submission', 'in_class_practice', 'external_reference'], true);
}

function elips_exam_status_valid(string $status): bool
{
    return in_array($status, ['draft', 'published', 'closed', 'archived'], true);
}

function elips_exam_type_valid(string $type): bool
{
    return in_array($type, ['written', 'practical', 'oral', 'project', 'other'], true);
}

function elips_assignment_file_public(array $row, bool $includePath = false): array
{
    $result = [
        'id' => (int) $row['id'],
        'assignmentId' => (int) $row['assignment_id'],
        'fileName' => $row['file_name'],
        'fileSize' => $row['file_size'] === null ? null : (int) $row['file_size'],
        'mimeType' => $row['mime_type'],
        'createdAt' => elips_iso($row['created_at']),
    ];
    if ($includePath) {
        $result['filePath'] = $row['file_path'];
    }
    return $result;
}

function elips_assignment_rubric_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'assignmentId' => (int) $row['assignment_id'],
        'criterion' => $row['criterion'],
        'description' => $row['description'],
        'maxScore' => (float) $row['max_score'],
        'order' => (int) $row['sort_order'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_submission_file_public(array $row, bool $includePath = false): array
{
    $result = [
        'id' => (int) $row['id'],
        'submissionId' => (int) $row['submission_id'],
        'fileName' => $row['file_name'],
        'fileSize' => $row['file_size'] === null ? null : (int) $row['file_size'],
        'mimeType' => $row['mime_type'],
        'createdAt' => elips_iso($row['created_at']),
    ];
    if ($includePath) {
        $result['filePath'] = $row['file_path'];
    }
    return $result;
}

function elips_submission_review_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'submissionId' => (int) $row['submission_id'],
        'reviewerId' => $row['reviewer_id'] === null ? null : (int) $row['reviewer_id'],
        'reviewerName' => $row['reviewer_name'] ?? '',
        'action' => $row['action'],
        'score' => $row['score'] === null ? null : (float) $row['score'],
        'note' => $row['note'],
        'createdAt' => elips_iso($row['created_at']),
    ];
}

function elips_exam_participant_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'examId' => (int) $row['exam_id'],
        'enrollmentId' => (int) $row['enrollment_id'],
        'studentId' => (int) $row['student_id'],
        'studentName' => $row['student_name'] ?? '',
        'status' => $row['status'],
        'note' => $row['note'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_exam_result_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'examId' => (int) $row['exam_id'],
        'enrollmentId' => (int) $row['enrollment_id'],
        'studentId' => (int) $row['student_id'],
        'studentName' => $row['student_name'] ?? '',
        'score' => $row['score'] === null ? null : (float) $row['score'],
        'status' => $row['status'],
        'gradedBy' => $row['graded_by'] === null ? null : (int) $row['graded_by'],
        'gradedByName' => $row['graded_by_name'] ?? '',
        'gradedAt' => elips_iso($row['graded_at']),
        'note' => $row['note'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_assignment_row(int $id): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT a.*, cl.code AS class_code, cl.name AS class_name, cl.course_id,
            cl.curriculum_version_id AS class_version_id, c.title AS course_title,
            u.name AS creator_name, m.title AS module_title, l.title AS lesson_title
         FROM assignments a
         JOIN classes cl ON cl.id = a.class_id
         JOIN courses c ON c.id = cl.course_id
         LEFT JOIN users u ON u.id = a.created_by
         LEFT JOIN modules m ON m.id = a.module_id
         LEFT JOIN lessons l ON l.id = a.lesson_id
         WHERE a.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_assignment_files_for(int $assignmentId, bool $includePath = false): array
{
    $stmt = elips_db()->prepare('SELECT * FROM assignment_files WHERE assignment_id = ? ORDER BY id ASC');
    $stmt->execute([$assignmentId]);
    $files = [];
    foreach ($stmt->fetchAll() as $row) {
        $files[] = elips_assignment_file_public($row, $includePath);
    }
    return $files;
}

function elips_assignment_rubrics_for(int $assignmentId): array
{
    $stmt = elips_db()->prepare(
        'SELECT * FROM assignment_rubrics WHERE assignment_id = ? ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([$assignmentId]);
    $rubrics = [];
    foreach ($stmt->fetchAll() as $row) {
        $rubrics[] = elips_assignment_rubric_public($row);
    }
    return $rubrics;
}

function elips_assignment_stats(int $assignmentId): array
{
    $stmt = elips_db()->prepare(
        "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status IN ('submitted', 'late', 'resubmitted') THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status IN ('reviewed', 'accepted') THEN 1 ELSE 0 END) AS reviewed_count,
            SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) AS late_count,
            AVG(CASE WHEN score IS NOT NULL THEN score END) AS average_score
         FROM submissions WHERE assignment_id = ?"
    );
    $stmt->execute([$assignmentId]);
    $row = $stmt->fetch() ?: [];
    return [
        'submissionCount' => (int) ($row['total'] ?? 0),
        'pendingCount' => (int) ($row['pending_count'] ?? 0),
        'reviewedCount' => (int) ($row['reviewed_count'] ?? 0),
        'lateCount' => (int) ($row['late_count'] ?? 0),
        'averageScore' => $row['average_score'] === null ? null : round((float) $row['average_score'], 2),
    ];
}

function elips_assignment_public(array $row, bool $includeInstructions = true): array
{
    $result = [
        'id' => (int) $row['id'],
        'classId' => (int) $row['class_id'],
        'classCode' => $row['class_code'] ?? '',
        'className' => $row['class_name'] ?? '',
        'courseId' => $row['course_id'] === null ? null : (int) $row['course_id'],
        'courseTitle' => $row['course_title'] ?? '',
        'curriculumVersionId' => (int) $row['curriculum_version_id'],
        'moduleId' => $row['module_id'] === null ? null : (int) $row['module_id'],
        'moduleTitle' => $row['module_title'] ?? '',
        'lessonId' => $row['lesson_id'] === null ? null : (int) $row['lesson_id'],
        'lessonTitle' => $row['lesson_title'] ?? '',
        'title' => $row['title'],
        'type' => $row['type'],
        'openAt' => elips_iso($row['open_at']),
        'dueAt' => elips_iso($row['due_at']),
        'maxScore' => (float) $row['max_score'],
        'weight' => (float) $row['weight'],
        'requireFile' => (bool) $row['require_file'],
        'allowedTypes' => $row['allowed_types'],
        'maxFileSize' => $row['max_file_size'] === null ? null : (int) $row['max_file_size'],
        'status' => $row['status'],
        'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
        'creatorName' => $row['creator_name'] ?? '',
        'updatedBy' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
    if ($includeInstructions) {
        $result['instructions'] = $row['instructions'];
    }
    return $result;
}

function elips_submission_row(int $id): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT s.*, u.name AS student_name, a.title AS assignment_title, a.class_id,
            a.max_score, a.due_at, a.type AS assignment_type, r.name AS reviewer_name
         FROM submissions s
         JOIN users u ON u.id = s.student_id
         JOIN assignments a ON a.id = s.assignment_id
         LEFT JOIN users r ON r.id = s.reviewed_by
         WHERE s.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_submission_public(array $row, bool $includeContent = true): array
{
    $result = [
        'id' => (int) $row['id'],
        'assignmentId' => (int) $row['assignment_id'],
        'assignmentTitle' => $row['assignment_title'] ?? '',
        'classId' => $row['class_id'] === null ? null : (int) $row['class_id'],
        'enrollmentId' => (int) $row['enrollment_id'],
        'studentId' => (int) $row['student_id'],
        'studentName' => $row['student_name'] ?? '',
        'assignmentType' => $row['assignment_type'] ?? '',
        'maxScore' => $row['max_score'] === null ? null : (float) $row['max_score'],
        'status' => $row['status'],
        'submittedAt' => elips_iso($row['submitted_at']),
        'isLate' => (bool) $row['is_late'],
        'score' => $row['score'] === null ? null : (float) $row['score'],
        'reviewedBy' => $row['reviewed_by'] === null ? null : (int) $row['reviewed_by'],
        'reviewerName' => $row['reviewer_name'] ?? '',
        'reviewedAt' => elips_iso($row['reviewed_at']),
        'revisionCount' => (int) $row['revision_count'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
    if ($includeContent) {
        $result['textAnswer'] = $row['text_answer'];
        $result['feedback'] = $row['feedback'];
    }
    return $result;
}

function elips_submission_files_for(int $submissionId, bool $includePath = false): array
{
    $stmt = elips_db()->prepare('SELECT * FROM submission_files WHERE submission_id = ? ORDER BY id ASC');
    $stmt->execute([$submissionId]);
    $files = [];
    foreach ($stmt->fetchAll() as $row) {
        $files[] = elips_submission_file_public($row, $includePath);
    }
    return $files;
}

function elips_submission_reviews_for(int $submissionId): array
{
    $stmt = elips_db()->prepare(
        'SELECT r.*, u.name AS reviewer_name
         FROM submission_reviews r
         LEFT JOIN users u ON u.id = r.reviewer_id
         WHERE r.submission_id = ? ORDER BY r.id ASC'
    );
    $stmt->execute([$submissionId]);
    $reviews = [];
    foreach ($stmt->fetchAll() as $row) {
        $reviews[] = elips_submission_review_public($row);
    }
    return $reviews;
}

function elips_assignment_submission_for(int $assignmentId, int $enrollmentId): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT s.*, u.name AS student_name, a.title AS assignment_title, a.class_id,
            a.max_score, a.due_at, a.type AS assignment_type, r.name AS reviewer_name
         FROM submissions s
         JOIN users u ON u.id = s.student_id
         JOIN assignments a ON a.id = s.assignment_id
         LEFT JOIN users r ON r.id = s.reviewed_by
         WHERE s.assignment_id = ? AND s.enrollment_id = ? LIMIT 1'
    );
    $stmt->execute([$assignmentId, $enrollmentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_exam_row(int $id): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT e.*, cl.code AS class_code, cl.name AS class_name, cl.course_id,
            cl.curriculum_version_id AS class_version_id, c.title AS course_title,
            u.name AS creator_name
         FROM exams e
         JOIN classes cl ON cl.id = e.class_id
         JOIN courses c ON c.id = cl.course_id
         LEFT JOIN users u ON u.id = e.created_by
         WHERE e.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_exam_stats(int $examId): array
{
    $participantStmt = elips_db()->prepare(
        "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 'attended' THEN 1 ELSE 0 END) AS attended_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) AS excused_count
         FROM exam_participants WHERE exam_id = ?"
    );
    $participantStmt->execute([$examId]);
    $participants = $participantStmt->fetch() ?: [];

    $resultStmt = elips_db()->prepare(
        "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) AS graded_count,
            AVG(CASE WHEN score IS NOT NULL THEN score END) AS average_score
         FROM exam_results WHERE exam_id = ?"
    );
    $resultStmt->execute([$examId]);
    $results = $resultStmt->fetch() ?: [];

    return [
        'participantCount' => (int) ($participants['total'] ?? 0),
        'attendedCount' => (int) ($participants['attended_count'] ?? 0),
        'absentCount' => (int) ($participants['absent_count'] ?? 0),
        'excusedCount' => (int) ($participants['excused_count'] ?? 0),
        'resultCount' => (int) ($results['total'] ?? 0),
        'gradedCount' => (int) ($results['graded_count'] ?? 0),
        'averageScore' => $results['average_score'] === null ? null : round((float) $results['average_score'], 2),
    ];
}

function elips_exam_public(array $row, bool $includeInstructions = true): array
{
    $result = [
        'id' => (int) $row['id'],
        'classId' => (int) $row['class_id'],
        'classCode' => $row['class_code'] ?? '',
        'className' => $row['class_name'] ?? '',
        'courseId' => $row['course_id'] === null ? null : (int) $row['course_id'],
        'courseTitle' => $row['course_title'] ?? '',
        'curriculumVersionId' => (int) $row['curriculum_version_id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'type' => $row['type'],
        'examDate' => elips_date_only($row['exam_date']),
        'startTime' => elips_time_short($row['start_time']),
        'durationMinutes' => $row['duration_minutes'] === null ? null : (int) $row['duration_minutes'],
        'maxScore' => (float) $row['max_score'],
        'weight' => (float) $row['weight'],
        'status' => $row['status'],
        'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
        'creatorName' => $row['creator_name'] ?? '',
        'updatedBy' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
    if ($includeInstructions) {
        $result['instructions'] = $row['instructions'];
    }
    return $result;
}

function elips_exam_participants_for(int $examId): array
{
    $stmt = elips_db()->prepare(
        'SELECT p.*, u.name AS student_name
         FROM exam_participants p
         JOIN users u ON u.id = p.student_id
         WHERE p.exam_id = ? ORDER BY u.name ASC, p.id ASC'
    );
    $stmt->execute([$examId]);
    $participants = [];
    foreach ($stmt->fetchAll() as $row) {
        $participants[] = elips_exam_participant_public($row);
    }
    return $participants;
}

function elips_exam_results_for(int $examId): array
{
    $stmt = elips_db()->prepare(
        'SELECT r.*, u.name AS student_name, g.name AS graded_by_name
         FROM exam_results r
         JOIN users u ON u.id = r.student_id
         LEFT JOIN users g ON g.id = r.graded_by
         WHERE r.exam_id = ? ORDER BY u.name ASC, r.id ASC'
    );
    $stmt->execute([$examId]);
    $results = [];
    foreach ($stmt->fetchAll() as $row) {
        $results[] = elips_exam_result_public($row);
    }
    return $results;
}

function elips_exam_result_for(int $examId, int $enrollmentId): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT r.*, u.name AS student_name, g.name AS graded_by_name
         FROM exam_results r
         JOIN users u ON u.id = r.student_id
         LEFT JOIN users g ON g.id = r.graded_by
         WHERE r.exam_id = ? AND r.enrollment_id = ? LIMIT 1'
    );
    $stmt->execute([$examId, $enrollmentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Pastikan seluruh peserta aktif memiliki baris participant dan result.
 * Enrollment baru yang masuk setelah ujian dibuat tetap dapat dinilai.
 */
function elips_exam_ensure_roster(int $examId, int $classId): void
{
    $db = elips_db();
    $stmt = $db->prepare(
        "INSERT IGNORE INTO exam_participants (exam_id, enrollment_id, student_id, status, note, created_at)
         SELECT ?, e.id, e.student_id, 'registered', '', UTC_TIMESTAMP()
         FROM enrollments e
         WHERE e.class_id = ? AND e.status IN ('active', 'completed')"
    );
    $stmt->execute([$examId, $classId]);

    $stmt = $db->prepare(
        "INSERT IGNORE INTO exam_results (exam_id, enrollment_id, student_id, status, note, created_at)
         SELECT ?, e.id, e.student_id, 'not_assessed', '', UTC_TIMESTAMP()
         FROM enrollments e
         WHERE e.class_id = ? AND e.status IN ('active', 'completed')"
    );
    $stmt->execute([$examId, $classId]);
}

function elips_class_enrollment_for(int $classId, int $studentId): ?array
{
    $stmt = elips_db()->prepare(
        "SELECT * FROM enrollments
         WHERE class_id = ? AND student_id = ? AND status IN ('active', 'completed')
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$classId, $studentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_class_enrollment_by_id(int $classId, int $enrollmentId): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM enrollments WHERE id = ? AND class_id = ? LIMIT 1');
    $stmt->execute([$enrollmentId, $classId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_assignment_classroom_payload(array $class, ?array $enrollment, bool $preview): array
{
    $classId = (int) $class['id'];
    $course = elips_course_row((int) $class['course_id']);
    $enrollmentId = $enrollment ? (int) $enrollment['id'] : 0;
    $now = gmdate('Y-m-d H:i:s');

    $stmt = elips_db()->prepare(
        'SELECT a.*, cl.code AS class_code, cl.name AS class_name, cl.course_id,
            c.title AS course_title, u.name AS creator_name, m.title AS module_title,
            l.title AS lesson_title
         FROM assignments a
         JOIN classes cl ON cl.id = a.class_id
         JOIN courses c ON c.id = cl.course_id
         LEFT JOIN users u ON u.id = a.created_by
         LEFT JOIN modules m ON m.id = a.module_id
         LEFT JOIN lessons l ON l.id = a.lesson_id
         WHERE a.class_id = ?'
    );
    $params = [$classId];
    if (!$preview) {
        $stmt = elips_db()->prepare(
            'SELECT a.*, cl.code AS class_code, cl.name AS class_name, cl.course_id,
                c.title AS course_title, u.name AS creator_name, m.title AS module_title,
                l.title AS lesson_title
             FROM assignments a
             JOIN classes cl ON cl.id = a.class_id
             JOIN courses c ON c.id = cl.course_id
             LEFT JOIN users u ON u.id = a.created_by
             LEFT JOIN modules m ON m.id = a.module_id
             LEFT JOIN lessons l ON l.id = a.lesson_id
             WHERE a.class_id = ? AND a.status IN (\'published\', \'closed\')'
        );
    }
    $stmt->execute($params);
    $assignmentRows = $stmt->fetchAll();

    $assignments = [];
    $assignmentSubmitted = 0;
    $assignmentGraded = 0;
    foreach ($assignmentRows as $row) {
        $item = elips_assignment_public($row, true);
        $item['stats'] = elips_assignment_stats((int) $row['id']);
        $item['pastDue'] = $row['due_at'] !== null && $row['due_at'] < $now;
        $item['canSubmit'] = !$preview
            && $row['status'] === 'published'
            && ($row['open_at'] === null || $row['open_at'] <= $now);
        $submission = null;
        if ($enrollmentId > 0) {
            $submissionRow = elips_assignment_submission_for((int) $row['id'], $enrollmentId);
            if ($submissionRow) {
                $submission = elips_submission_public($submissionRow, false);
                if (in_array($submissionRow['status'], ['submitted', 'late', 'reviewed', 'accepted', 'resubmitted'], true)) {
                    $assignmentSubmitted++;
                }
                if (in_array($submissionRow['status'], ['reviewed', 'accepted'], true)) {
                    $assignmentGraded++;
                }
            }
        }
        $item['submission'] = $submission;
        $assignments[] = $item;
    }

    $examStmt = elips_db()->prepare(
        'SELECT e.*, cl.code AS class_code, cl.name AS class_name, cl.course_id,
            c.title AS course_title, u.name AS creator_name
         FROM exams e
         JOIN classes cl ON cl.id = e.class_id
         JOIN courses c ON c.id = cl.course_id
         LEFT JOIN users u ON u.id = e.created_by
         WHERE e.class_id = ?'
    );
    if (!$preview) {
        $examStmt = elips_db()->prepare(
            'SELECT e.*, cl.code AS class_code, cl.name AS class_name, cl.course_id,
                c.title AS course_title, u.name AS creator_name
             FROM exams e
             JOIN classes cl ON cl.id = e.class_id
             JOIN courses c ON c.id = cl.course_id
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.class_id = ? AND e.status IN (\'published\', \'closed\')'
        );
    }
    $examStmt->execute([$classId]);
    $exams = [];
    $examGraded = 0;
    foreach ($examStmt->fetchAll() as $row) {
        $item = elips_exam_public($row, true);
        $item['stats'] = elips_exam_stats((int) $row['id']);
        $item['pastExam'] = $row['exam_date'] !== null && $row['exam_date'] < gmdate('Y-m-d');
        $result = null;
        if ($enrollmentId > 0) {
            $resultRow = elips_exam_result_for((int) $row['id'], $enrollmentId);
            if ($resultRow) {
                $result = elips_exam_result_public($resultRow);
                if ($resultRow['status'] === 'graded') $examGraded++;
            }
        }
        $item['result'] = $result;
        $exams[] = $item;
    }

    return [
        'class' => [
            'id' => $classId,
            'code' => $class['code'],
            'name' => $class['name'],
            'courseId' => (int) $class['course_id'],
            'courseTitle' => $course ? $course['title'] : '',
            'curriculumVersionId' => (int) $class['curriculum_version_id'],
            'instructorId' => $class['instructor_id'] === null ? null : (int) $class['instructor_id'],
            'status' => $class['status'],
            'periodStart' => elips_date_only($class['period_start']),
            'periodEnd' => elips_date_only($class['period_end']),
        ],
        'enrollment' => $enrollment ? elips_enrollment_public((int) $enrollment['id']) : null,
        'preview' => $preview,
        'assignments' => $assignments,
        'exams' => $exams,
        'stats' => [
            'assignmentTotal' => count($assignments),
            'assignmentSubmitted' => $assignmentSubmitted,
            'assignmentGraded' => $assignmentGraded,
            'examTotal' => count($exams),
            'examGraded' => $examGraded,
        ],
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
    $components = [];
    if (!empty($row['components_snapshot'])) {
        $decoded = json_decode((string) $row['components_snapshot'], true);
        if (is_array($decoded)) {
            $components = $decoded;
        }
    }
    return [
        'id' => (int) $row['id'],
        'enrollmentId' => $row['enrollment_id'] === null ? null : (int) $row['enrollment_id'],
        'schemeId' => $row['scheme_id'] === null ? null : (int) $row['scheme_id'],
        'courseId' => (int) $row['course_id'],
        'studentId' => (int) $row['student_id'],
        'instructorId' => $row['instructor_id'] === null ? null : (int) $row['instructor_id'],
        'score' => (int) $row['score'],
        'finalScore' => $row['final_score'] === null ? null : (float) $row['final_score'],
        'grade' => $row['grade'],
        'status' => $row['status'] ?? 'draft',
        'components' => $components,
        'note' => $row['note'],
        'finalizedBy' => $row['finalized_by'] === null ? null : (int) $row['finalized_by'],
        'finalizedAt' => elips_iso($row['finalized_at']),
        'computedAt' => elips_iso($row['computed_at']),
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_grade_row_by_enrollment(int $enrollmentId): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM grades WHERE enrollment_id = ? LIMIT 1');
    $stmt->execute([$enrollmentId]);
    $row = $stmt->fetch();
    return $row ?: null;
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

function elips_default_predicate_ranges(): array
{
    return [
        ['letter' => 'A', 'label' => 'Sangat Baik', 'minScore' => 90.0, 'maxScore' => 100.0, 'sortOrder' => 1],
        ['letter' => 'A-', 'label' => 'Sangat Baik', 'minScore' => 80.0, 'maxScore' => 89.99, 'sortOrder' => 2],
        ['letter' => 'B+', 'label' => 'Baik', 'minScore' => 75.0, 'maxScore' => 79.99, 'sortOrder' => 3],
        ['letter' => 'B', 'label' => 'Baik', 'minScore' => 70.0, 'maxScore' => 74.99, 'sortOrder' => 4],
        ['letter' => 'B-', 'label' => 'Baik', 'minScore' => 65.0, 'maxScore' => 69.99, 'sortOrder' => 5],
        ['letter' => 'C+', 'label' => 'Cukup', 'minScore' => 60.0, 'maxScore' => 64.99, 'sortOrder' => 6],
        ['letter' => 'C', 'label' => 'Cukup', 'minScore' => 55.0, 'maxScore' => 59.99, 'sortOrder' => 7],
        ['letter' => 'D', 'label' => 'Kurang', 'minScore' => 50.0, 'maxScore' => 54.99, 'sortOrder' => 8],
        ['letter' => 'E', 'label' => 'Tidak Lulus', 'minScore' => 0.0, 'maxScore' => 49.99, 'sortOrder' => 9],
    ];
}

function elips_predicate_ranges(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM predicate_ranges';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, min_score DESC, id ASC';
    $rows = elips_db()->query($sql)->fetchAll();
    if (!$rows) {
        return elips_default_predicate_ranges();
    }
    $ranges = [];
    foreach ($rows as $row) {
        $ranges[] = [
            'id' => (int) $row['id'],
            'letter' => $row['letter'],
            'label' => $row['label'],
            'minScore' => (float) $row['min_score'],
            'maxScore' => (float) $row['max_score'],
            'sortOrder' => (int) $row['sort_order'],
            'isActive' => (bool) $row['is_active'],
            'createdAt' => elips_iso($row['created_at']),
            'updatedAt' => elips_iso($row['updated_at']),
        ];
    }
    return $ranges;
}

function elips_predicate_letter($score): string
{
    $value = (float) $score;
    foreach (elips_predicate_ranges() as $range) {
        if ($value >= (float) $range['minScore'] && $value <= (float) $range['maxScore']) {
            return (string) $range['letter'];
        }
    }
    // Fallback ke pemetaan bawaan bila konfigurasi tidak mencakup skor.
    foreach (elips_default_predicate_ranges() as $range) {
        if ($value >= $range['minScore'] && $value <= $range['maxScore']) {
            return $range['letter'];
        }
    }
    return 'E';
}

function elips_grade_letter($score): string
{
    return elips_predicate_letter($score);
}

function elips_grade_round(float $value, string $mode): float
{
    switch ($mode) {
        case 'floor':
            return floor($value * 100) / 100;
        case 'ceil':
            return ceil($value * 100) / 100;
        case 'half_down':
            return round($value, 2, PHP_ROUND_HALF_DOWN);
        case 'half_up':
        default:
            return round($value, 2, PHP_ROUND_HALF_UP);
    }
}

function elips_grade_scheme_component_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'schemeId' => (int) $row['scheme_id'],
        'type' => $row['type'],
        'name' => $row['name'],
        'description' => $row['description'],
        'weight' => (float) $row['weight'],
        'aggregation' => $row['aggregation'],
        'maxScore' => (float) $row['max_score'],
        'isRequired' => (bool) $row['is_required'],
        'order' => (int) $row['sort_order'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_grade_scheme_components(int $schemeId): array
{
    $stmt = elips_db()->prepare(
        'SELECT * FROM grade_scheme_components WHERE scheme_id = ? ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([$schemeId]);
    $components = [];
    foreach ($stmt->fetchAll() as $row) {
        $components[] = elips_grade_scheme_component_public($row);
    }
    return $components;
}

function elips_grade_scheme_row(int $id): ?array
{
    $stmt = elips_db()->prepare(
        'SELECT gs.*, c.title AS course_title, cl.code AS class_code, cl.name AS class_name
         FROM grade_schemes gs
         JOIN courses c ON c.id = gs.course_id
         JOIN classes cl ON cl.id = gs.class_id
         WHERE gs.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_grade_scheme_for_class(int $classId): ?array
{
    $stmt = elips_db()->prepare('SELECT id FROM grade_schemes WHERE class_id = ? LIMIT 1');
    $stmt->execute([$classId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : elips_grade_scheme_row((int) $id);
}

function elips_grade_scheme_public(array $row, bool $withComponents = true): array
{
    $result = [
        'id' => (int) $row['id'],
        'courseId' => (int) $row['course_id'],
        'courseTitle' => $row['course_title'] ?? '',
        'classId' => (int) $row['class_id'],
        'classCode' => $row['class_code'] ?? '',
        'className' => $row['class_name'] ?? '',
        'name' => $row['name'],
        'status' => $row['status'],
        'passScore' => (float) $row['pass_score'],
        'rounding' => $row['rounding'],
        'notes' => $row['notes'],
        'createdBy' => $row['created_by'] === null ? null : (int) $row['created_by'],
        'updatedBy' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
    if ($withComponents) {
        $result['components'] = elips_grade_scheme_components((int) $row['id']);
        $result['totalWeight'] = array_sum(array_map(function ($component) {
            return (float) $component['weight'];
        }, $result['components']));
    }
    return $result;
}

function elips_grade_component_score_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'componentId' => (int) $row['component_id'],
        'enrollmentId' => (int) $row['enrollment_id'],
        'studentId' => (int) $row['student_id'],
        'score' => $row['score'] === null ? null : (float) $row['score'],
        'status' => $row['status'],
        'assessorId' => $row['assessor_id'] === null ? null : (int) $row['assessor_id'],
        'assessorName' => $row['assessor_name'] ?? '',
        'assessedAt' => elips_iso($row['assessed_at']),
        'note' => $row['note'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at']),
    ];
}

function elips_grade_correction_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'gradeId' => (int) $row['grade_id'],
        'enrollmentId' => $row['enrollment_id'] === null ? null : (int) $row['enrollment_id'],
        'previousScore' => $row['previous_score'] === null ? null : (float) $row['previous_score'],
        'newScore' => $row['new_score'] === null ? null : (float) $row['new_score'],
        'previousLetter' => $row['previous_letter'],
        'newLetter' => $row['new_letter'],
        'reason' => $row['reason'],
        'actorId' => $row['actor_id'] === null ? null : (int) $row['actor_id'],
        'actorName' => $row['actor_name'] ?? '',
        'createdAt' => elips_iso($row['created_at']),
    ];
}

/**
 * Menghitung nilai akhir satu enrollment berdasarkan skema penilaian.
 * Nilai kosong tidak pernah dianggap nol: komponen wajib yang belum
 * dinilai membuat hasil berstatus incomplete.
 */
function elips_grade_compute_enrollment(array $enrollment, ?array $scheme = null): array
{
    $classId = $enrollment['class_id'] === null ? 0 : (int) $enrollment['class_id'];
    $enrollmentId = (int) $enrollment['id'];
    if ($scheme === null && $classId > 0) {
        $scheme = elips_grade_scheme_for_class($classId);
    }
    if (!$scheme) {
        return [
            'schemeId' => null,
            'schemeName' => '',
            'schemeStatus' => null,
            'passScore' => null,
            'rounding' => 'half_up',
            'components' => [],
            'missing' => [],
            'finalScore' => null,
            'letter' => null,
            'status' => 'no_scheme',
            'passed' => null,
        ];
    }

    $db = elips_db();
    $components = elips_grade_scheme_components((int) $scheme['id']);
    $resultComponents = [];
    $missing = [];

    foreach ($components as $component) {
        $componentId = (int) $component['id'];
        $type = $component['type'];
        $componentResult = [
            'componentId' => $componentId,
            'name' => $component['name'],
            'type' => $type,
            'weight' => (float) $component['weight'],
            'aggregation' => $component['aggregation'],
            'maxScore' => (float) $component['maxScore'],
            'isRequired' => (bool) $component['isRequired'],
            'order' => (int) $component['order'],
            'score' => null,
            'status' => 'not_assessed',
            'note' => '',
            'activities' => [],
        ];

        // Pengecualian resmi (excused) berlaku untuk semua jenis komponen.
        $stmt = $db->prepare(
            'SELECT * FROM grade_component_scores WHERE component_id = ? AND enrollment_id = ? LIMIT 1'
        );
        $stmt->execute([$componentId, $enrollmentId]);
        $override = $stmt->fetch() ?: null;
        if ($override && $override['status'] === 'excused') {
            $componentResult['status'] = 'excused';
            $componentResult['note'] = $override['note'];
            $resultComponents[] = $componentResult;
            continue;
        }

        if ($type === 'manual') {
            if ($override && $override['status'] === 'graded' && $override['score'] !== null) {
                $componentResult['score'] = max(0, min(100, (float) $override['score']));
                $componentResult['status'] = 'graded';
                $componentResult['note'] = $override['note'];
            } elseif ($override && $override['status'] === 'missing') {
                $componentResult['status'] = 'missing';
                $componentResult['note'] = $override['note'];
            } else {
                $componentResult['status'] = 'missing';
            }
        } elseif ($type === 'assignment') {
            $stmt = $db->prepare(
                "SELECT * FROM assignments
                 WHERE class_id = ? AND status IN ('published', 'closed')
                   AND type IN ('file_upload', 'text_submission')
                 ORDER BY id ASC"
            );
            $stmt->execute([$classId]);
            $activities = $stmt->fetchAll();
            $weightedSum = 0.0;
            $weightTotal = 0.0;
            $activityMissing = false;
            foreach ($activities as $activity) {
                $maxScore = (float) $activity['max_score'] > 0 ? (float) $activity['max_score'] : 100.0;
                $stmt = $db->prepare(
                    'SELECT * FROM submissions WHERE assignment_id = ? AND enrollment_id = ? LIMIT 1'
                );
                $stmt->execute([(int) $activity['id'], $enrollmentId]);
                $submission = $stmt->fetch() ?: null;
                $activityRow = [
                    'id' => (int) $activity['id'],
                    'title' => $activity['title'],
                    'maxScore' => $maxScore,
                    'weight' => (float) $activity['weight'],
                    'score' => null,
                    'normalized' => null,
                    'status' => 'missing',
                ];
                if ($submission && $submission['score'] !== null) {
                    $normalized = ((float) $submission['score'] / $maxScore) * 100;
                    $normalized = max(0, min(100, $normalized));
                    $activityRow['score'] = (float) $submission['score'];
                    $activityRow['normalized'] = $normalized;
                    $activityRow['status'] = 'graded';
                    $weight = $component['aggregation'] === 'average' ? 1.0 : ((float) $activity['weight'] > 0 ? (float) $activity['weight'] : 1.0);
                    $weightedSum += $normalized * $weight;
                    $weightTotal += $weight;
                } else {
                    $activityMissing = true;
                }
                $componentResult['activities'][] = $activityRow;
            }
            if (count($activities) === 0) {
                $componentResult['status'] = 'missing';
            } elseif ($weightTotal > 0) {
                $componentResult['score'] = $weightedSum / $weightTotal;
                $componentResult['status'] = $weightTotal > 0 ? 'graded' : 'missing';
                if ($activityMissing) {
                    // Nilai sebagian tetap ditampilkan, tetapi komponen wajib
                    // yang belum lengkap tidak boleh menghasilkan final.
                    $componentResult['status'] = 'missing';
                }
            } else {
                $componentResult['status'] = 'missing';
            }
        } elseif ($type === 'exam') {
            $stmt = $db->prepare(
                "SELECT * FROM exams
                 WHERE class_id = ? AND status IN ('published', 'closed')
                 ORDER BY id ASC"
            );
            $stmt->execute([$classId]);
            $activities = $stmt->fetchAll();
            $weightedSum = 0.0;
            $weightTotal = 0.0;
            $activityMissing = false;
            foreach ($activities as $activity) {
                $maxScore = (float) $activity['max_score'] > 0 ? (float) $activity['max_score'] : 100.0;
                $stmt = $db->prepare(
                    'SELECT * FROM exam_results WHERE exam_id = ? AND enrollment_id = ? LIMIT 1'
                );
                $stmt->execute([(int) $activity['id'], $enrollmentId]);
                $examResult = $stmt->fetch() ?: null;
                $activityRow = [
                    'id' => (int) $activity['id'],
                    'title' => $activity['title'],
                    'maxScore' => $maxScore,
                    'weight' => (float) $activity['weight'],
                    'score' => null,
                    'normalized' => null,
                    'status' => 'missing',
                ];
                if ($examResult && $examResult['status'] === 'excused') {
                    $activityRow['status'] = 'excused';
                } elseif ($examResult && $examResult['status'] === 'graded' && $examResult['score'] !== null) {
                    $normalized = ((float) $examResult['score'] / $maxScore) * 100;
                    $normalized = max(0, min(100, $normalized));
                    $activityRow['score'] = (float) $examResult['score'];
                    $activityRow['normalized'] = $normalized;
                    $activityRow['status'] = 'graded';
                    $weight = $component['aggregation'] === 'average' ? 1.0 : ((float) $activity['weight'] > 0 ? (float) $activity['weight'] : 1.0);
                    $weightedSum += $normalized * $weight;
                    $weightTotal += $weight;
                } else {
                    $activityMissing = true;
                }
                $componentResult['activities'][] = $activityRow;
            }
            if (count($activities) === 0) {
                $componentResult['status'] = 'missing';
            } elseif ($weightTotal > 0) {
                $componentResult['score'] = $weightedSum / $weightTotal;
                $componentResult['status'] = $activityMissing ? 'missing' : 'graded';
            } else {
                $componentResult['status'] = 'missing';
            }
        }

        if ($componentResult['status'] === 'missing' && (bool) $component['isRequired']) {
            $missing[] = $component['name'];
        }
        $resultComponents[] = $componentResult;
    }

    $included = array_values(array_filter($resultComponents, function ($component) {
        return $component['status'] === 'graded' && $component['score'] !== null;
    }));
    $weightTotal = array_sum(array_map(function ($component) {
        return (float) $component['weight'];
    }, $included));

    $finalScore = null;
    $letter = null;
    $status = 'incomplete';
    if (count($missing) === 0 && $weightTotal > 0) {
        $sum = 0.0;
        foreach ($included as $component) {
            $sum += (float) $component['score'] * (float) $component['weight'];
        }
        $finalScore = elips_grade_round($sum / $weightTotal, (string) $scheme['rounding']);
        $letter = elips_predicate_letter($finalScore);
        $status = 'computed';
    }

    return [
        'schemeId' => (int) $scheme['id'],
        'schemeName' => $scheme['name'],
        'schemeStatus' => $scheme['status'],
        'passScore' => (float) $scheme['pass_score'],
        'rounding' => $scheme['rounding'],
        'components' => $resultComponents,
        'missing' => $missing,
        'finalScore' => $finalScore,
        'letter' => $letter,
        'status' => $status,
        'passed' => $finalScore === null ? null : ($finalScore >= (float) $scheme['pass_score']),
    ];
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
