<?php
// Direktori: api/certificate_issue.php
// ============================================================
// Penerbitan e-sertifikat dari nilai akhir tentor.
// Alur: tentor mengirim nilai (final_dispatches) -> admin / operasional
// meninjau & menerbitkan -> peserta mengunduh PDF / JPG / PNG.
// Verifikasi keaslian lewat QR (halaman publik, tanpa login).
// ============================================================

require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/certificate_render.php';

function elips_ensure_cert_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $db = elips_db();
    elips_ensure_tp_schema();
    $have = [];
    foreach ($db->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'certificates'"
    )->fetchAll(PDO::FETCH_COLUMN) as $c) {
        $have[$c] = true;
    }
    $add = [
        'class_id' => 'INT UNSIGNED NULL',
        'cert_seq' => 'INT UNSIGNED NULL',
        'public_token' => 'CHAR(24) NULL',
        'snapshot' => 'LONGTEXT NULL',
        'issued_at' => 'DATETIME NULL',
        'valid_until' => 'DATE NULL',
    ];
    foreach ($add as $col => $def) {
        if (!isset($have[$col])) {
            $db->exec("ALTER TABLE certificates ADD COLUMN `$col` $def");
        }
    }
    if (!isset($have['public_token'])) {
        $db->exec('ALTER TABLE certificates ADD UNIQUE KEY `uq_certificates_token` (`public_token`)');
    }
    $db->exec(
        'CREATE TABLE IF NOT EXISTS `certificate_seq` (
            `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY, `last_value` INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $db->exec('INSERT IGNORE INTO certificate_seq (id, `last_value`) VALUES (1, 0)');
    $done = true;
}

function elips_cert_next_seq(): int
{
    $db = elips_db();
    $db->exec('UPDATE certificate_seq SET `last_value` = LAST_INSERT_ID(`last_value` + 1) WHERE id = 1');
    return (int) $db->query('SELECT LAST_INSERT_ID()')->fetchColumn();
}

function elips_cert_roman(int $month): string
{
    return ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][max(1, min(12, $month))];
}

/** Singkatan kursus: huruf awal tiap kata (kata sambung dilewati), maks 5. */
function elips_cert_course_code(string $title): string
{
    $skip = ['dan', 'dengan', 'untuk', 'di', 'ke', 'yang', 'of', 'and', '&', '-'];
    $code = '';
    foreach (preg_split('/[\s\-\/]+/u', trim($title)) ?: [] as $word) {
        if ($word === '' || in_array(mb_strtolower($word), $skip, true)) {
            continue;
        }
        $first = mb_substr($word, 0, 1);
        if (preg_match('/[\p{L}\p{N}]/u', $first)) {
            $code .= mb_strtoupper($first);
        }
    }
    return mb_substr($code !== '' ? $code : 'KRS', 0, 5);
}

function elips_cert_months_id(): array
{
    return ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
}

function elips_cert_date_id(DateTimeInterface $d): string
{
    return $d->format('j') . ' ' . elips_cert_months_id()[(int) $d->format('n')] . ' ' . $d->format('Y');
}

function elips_cert_predicate_label(string $letter): string
{
    foreach (elips_predicate_ranges(false) as $r) {
        if ($r['letter'] === $letter) {
            return (string) $r['label'];
        }
    }
    return $letter;
}

function elips_cert_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir = rtrim(str_replace('\\', '/', dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

/** Data untuk perender dari baris sertifikat terbit. */
function elips_cert_render_data(array $row): array
{
    $snap = json_decode((string) $row['snapshot'], true) ?: [];
    $snap['number'] = 'NO: ' . $row['certificate_number'];
    $snap['url'] = elips_cert_base_url() . '/verify-certificate.php?c=' . $row['public_token'];
    return $snap;
}

function elips_cert_row(int $id): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM certificates WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function elips_cert_public_info(array $row): array
{
    $snap = json_decode((string) $row['snapshot'], true) ?: [];
    $until = $row['valid_until'] ? new DateTimeImmutable($row['valid_until']) : null;
    return [
        'id' => (int) $row['id'],
        'number' => $row['certificate_number'],
        'token' => $row['public_token'],
        'name' => $snap['name'] ?? '',
        'course' => $snap['course'] ?? '',
        'predicateLabel' => $snap['predicateLabel'] ?? '',
        'average' => $snap['average'] ?? null,
        'averageLetter' => $snap['averageLetter'] ?? '',
        'totalJp' => $snap['totalJp'] ?? 0,
        'groups' => $snap['groups'] ?? [],
        'issuedAt' => elips_iso($row['issued_at']),
        'validUntil' => $row['valid_until'],
        'validText' => $snap['validText'] ?? '',
        'expired' => $until ? $until < new DateTimeImmutable('today') : false,
    ];
}

// ---------- Aksi staf ----------

/** Antrean: angkatan yang nilainya sudah dikirim tentor + status sertifikat tiap peserta. */
function elips_action_cert_queue(array $p): array
{
    elips_require_academic_access();
    elips_ensure_cert_schema();
    $db = elips_db();
    $classes = $db->query(
        "SELECT c.id, c.code, c.name, co.title AS course_title, d.sent_at
         FROM final_dispatches d JOIN classes c ON c.id = d.class_id JOIN courses co ON co.id = c.course_id
         ORDER BY d.sent_at DESC"
    )->fetchAll();
    $out = [];
    foreach ($classes as $c) {
        $stmt = $db->prepare(
            "SELECT g.enrollment_id, g.student_id, g.average, g.predicate, g.total_jp, g.status, u.name,
                    ce.id AS cert_id, ce.certificate_number, ce.status AS cert_status
             FROM final_grades g JOIN users u ON u.id = g.student_id
             LEFT JOIN certificates ce ON ce.enrollment_id = g.enrollment_id
             WHERE g.class_id = ? ORDER BY u.name"
        );
        $stmt->execute([(int) $c['id']]);
        $students = [];
        foreach ($stmt->fetchAll() as $r) {
            $issued = $r['cert_status'] === 'publish' && $r['certificate_number'] !== '';
            $students[] = [
                'enrollmentId' => (int) $r['enrollment_id'],
                'studentId' => (int) $r['student_id'],
                'name' => $r['name'],
                'average' => $r['average'] === null ? null : (float) $r['average'],
                'predicate' => $r['predicate'],
                'predicateLabel' => $r['predicate'] ? elips_cert_predicate_label($r['predicate']) : '',
                'totalJp' => (float) $r['total_jp'],
                'ready' => $r['status'] === 'complete',
                'issued' => $issued,
                'certificateId' => $issued ? (int) $r['cert_id'] : null,
                'certificateNumber' => $issued ? $r['certificate_number'] : '',
            ];
        }
        $out[] = [
            'classId' => (int) $c['id'], 'code' => $c['code'], 'name' => $c['name'],
            'course' => $c['course_title'], 'sentAt' => elips_iso($c['sent_at']),
            'students' => $students,
        ];
    }
    return elips_ok('', ['classes' => $out]);
}

/** Terbitkan sertifikat untuk satu peserta; mengembalikan id sertifikat. */
function elips_cert_issue_one(int $classId, int $enrollmentId, array $actor): int
{
    $db = elips_db();
    $stmt = $db->prepare(
        'SELECT g.*, c.course_id FROM final_grades g JOIN classes c ON c.id = g.class_id
         WHERE g.class_id = ? AND g.enrollment_id = ? LIMIT 1'
    );
    $stmt->execute([$classId, $enrollmentId]);
    $g = $stmt->fetch();
    if (!$g) {
        throw new ElipsError('Nilai akhir peserta tidak ditemukan.');
    }
    $sent = $db->prepare('SELECT 1 FROM final_dispatches WHERE class_id = ?');
    $sent->execute([$classId]);
    if (!$sent->fetchColumn()) {
        throw new ElipsError('Tentor belum mengirim nilai angkatan ini.');
    }
    if ($g['status'] !== 'complete' || $g['average'] === null) {
        throw new ElipsError('Nilai peserta belum lengkap.');
    }
    $existing = $db->prepare('SELECT * FROM certificates WHERE enrollment_id = ? LIMIT 1');
    $existing->execute([$enrollmentId]);
    $cert = $existing->fetch() ?: null;
    if ($cert && $cert['status'] === 'publish' && !empty($cert['public_token'])) {
        throw new ElipsError('Sertifikat peserta ini sudah terbit.');
    }

    $student = elips_user_row((int) $g['student_id']);
    $course = elips_course_row((int) $g['course_id']);
    if (!$student || !$course) {
        throw new ElipsError('Data peserta atau kursus tidak ditemukan.');
    }
    $structure = json_decode((string) $g['structure'], true) ?: [];
    $groups = [];
    foreach ($structure as $grp) {
        $items = [];
        foreach ($grp['items'] as $it) {
            $score = (float) ($it['score'] ?? 0);
            $items[] = [
                'title' => (string) $it['title'], 'score' => $score,
                'letter' => elips_predicate_letter($score), 'jp' => (float) $it['jp'],
            ];
        }
        $groups[] = ['title' => (string) $grp['title'], 'items' => $items];
    }
    $average = (float) $g['average'];
    $letter = $g['predicate'] !== '' ? (string) $g['predicate'] : elips_predicate_letter($average);

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $until = $now->modify('+3 years');
    $seq = elips_cert_next_seq();
    $number = sprintf(
        'EC / %06d / %s / %s / %s',
        $seq, elips_cert_course_code((string) $course['title']),
        elips_cert_roman((int) $now->format('n')), $now->format('Y')
    );
    $snapshot = [
        'name' => $student['name'], 'course' => $course['title'],
        'predicateLabel' => elips_cert_predicate_label($letter), 'totalJp' => (float) $g['total_jp'],
        'validText' => elips_cert_date_id($until), 'groups' => $groups,
        'average' => $average, 'averageLetter' => $letter,
    ];
    $token = bin2hex(random_bytes(12));
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);

    if ($cert) {
        $db->prepare(
            "UPDATE certificates SET status = 'publish', class_id = ?, cert_seq = ?, certificate_number = ?, public_token = ?,
                    snapshot = ?, issued_at = UTC_TIMESTAMP(), valid_until = ?, grade = ?, score = ?,
                    approved_by_admin_id = ?, approved_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?"
        )->execute([
            $classId, $seq, $number, $token, $json, $until->format('Y-m-d'), $letter, (int) round($average),
            $actor['role'] === 'admin' ? (int) $actor['id'] : null, (int) $cert['id'],
        ]);
        $id = (int) $cert['id'];
    } else {
        $db->prepare(
            "INSERT INTO certificates (student_id, course_id, enrollment_id, class_id, cert_seq, certificate_number, public_token,
                    status, snapshot, issued_at, valid_until, grade, score, approved_by_admin_id, approved_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'publish', ?, UTC_TIMESTAMP(), ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        )->execute([
            (int) $g['student_id'], (int) $g['course_id'], $enrollmentId, $classId, $seq, $number, $token, $json,
            $until->format('Y-m-d'), $letter, (int) round($average),
            $actor['role'] === 'admin' ? (int) $actor['id'] : null,
        ]);
        $id = (int) $db->lastInsertId();
    }
    elips_audit(
        'approve', 'certificate', $id, $number,
        'Menerbitkan e-sertifikat ' . $number . ' untuk ' . $student['name'] . ' (' . $course['title'] . ').', $actor
    );
    elips_notify_certificate_published($id);
    return $id;
}

function elips_action_cert_issue(array $p): array
{
    $actor = elips_require_academic_access();
    elips_ensure_cert_schema();
    $classId = (int) ($p['classId'] ?? 0);
    $ids = array_values(array_unique(array_map('intval', (array) ($p['enrollmentIds'] ?? []))));
    if ($classId <= 0 || !$ids) {
        return elips_fail('Pilih minimal satu peserta.');
    }
    $issued = 0;
    $errors = [];
    foreach ($ids as $enrollmentId) {
        try {
            elips_cert_issue_one($classId, $enrollmentId, $actor);
            $issued++;
        } catch (ElipsError $e) {
            $errors[] = $e->getMessage();
        }
    }
    if ($issued === 0) {
        return elips_fail($errors[0] ?? 'Tidak ada sertifikat yang diterbitkan.');
    }
    $msg = $issued . ' sertifikat berhasil diterbitkan.' . ($errors ? ' ' . count($errors) . ' dilewati.' : '');
    return elips_ok($msg, ['issued' => $issued, 'skipped' => count($errors)]);
}

// ---------- Peserta & publik ----------

function elips_action_cert_mine(array $p): array
{
    $user = elips_require_login();
    elips_ensure_cert_schema();
    $stmt = elips_db()->prepare(
        "SELECT * FROM certificates WHERE student_id = ? AND status = 'publish' AND public_token IS NOT NULL ORDER BY issued_at DESC"
    );
    $stmt->execute([(int) $user['id']]);
    return elips_ok('', ['certificates' => array_map('elips_cert_public_info', $stmt->fetchAll())]);
}

/** Publik (tanpa login): cek keaslian lewat token QR atau nomor sertifikat. */
function elips_action_cert_verify(array $p): array
{
    elips_ensure_cert_schema();
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'x');
    if (elips_rate_limit_blocked('cert_verify', $ip, 40, 60) > 0) {
        return elips_fail('Terlalu banyak percobaan. Coba lagi sebentar lagi.');
    }
    elips_rate_limit_hit('cert_verify', $ip, 60);
    $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($p['token'] ?? '')));
    $number = trim((string) ($p['number'] ?? ''));
    if ($token !== '') {
        $stmt = elips_db()->prepare("SELECT * FROM certificates WHERE public_token = ? AND status = 'publish' LIMIT 1");
        $stmt->execute([$token]);
    } elseif ($number !== '') {
        $stmt = elips_db()->prepare("SELECT * FROM certificates WHERE certificate_number = ? AND status = 'publish' AND public_token IS NOT NULL LIMIT 1");
        $stmt->execute([preg_replace('/\s*\/\s*/', ' / ', $number)]);
    } else {
        return elips_fail('Masukkan nomor sertifikat.');
    }
    $row = $stmt->fetch();
    if (!$row) {
        return elips_fail('Sertifikat tidak ditemukan atau tidak valid.');
    }
    return elips_ok('', ['certificate' => elips_cert_public_info($row)]);
}

// ---------- Berkas (GET) ----------

function elips_certificate_file_send(int $id, string $format, string $side): void
{
    $deny = static function (int $code): void {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $code === 404 ? 'Sertifikat tidak ditemukan.' : 'Tidak diizinkan.';
        exit;
    };
    $user = elips_session_user();
    if (!$user || $user['status'] !== 'aktif') {
        $deny(403);
    }
    elips_ensure_cert_schema();
    $row = elips_cert_row($id);
    if (!$row || $row['status'] !== 'publish' || empty($row['public_token'])) {
        $deny(404);
    }
    $isStaff = $user['role'] === 'admin' || ($user['role'] === 'karyawan' && ($user['division'] ?? '') === 'operasional');
    if (!$isStaff && !($user['role'] === 'peserta' && (int) $user['id'] === (int) $row['student_id'])) {
        $deny(403);
    }
    $format = in_array($format, ['pdf', 'jpg', 'png'], true) ? $format : 'pdf';
    $side = $side === 'back' ? 'back' : 'front';
    $d = elips_cert_render_data($row);
    $base = 'Sertifikat-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $d['name']);
    if ($format === 'pdf') {
        $bytes = elips_cert_pdf(
            elips_cert_image($d, 'front', 'jpg'), elips_cert_image($d, 'back', 'jpg'),
            'Sertifikat ' . $d['name']
        );
        $mime = 'application/pdf';
        $file = $base . '.pdf';
    } else {
        $bytes = elips_cert_image($d, $side, $format);
        $mime = $format === 'png' ? 'image/png' : 'image/jpeg';
        $file = $base . '-' . ($side === 'back' ? 'belakang' : 'depan') . '.' . $format;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($bytes));
    header('Content-Disposition: ' . (isset($_GET['inline']) ? 'inline' : 'attachment') . '; filename="' . $file . '"');
    header('Cache-Control: private, max-age=300');
    echo $bytes;
    exit;
}