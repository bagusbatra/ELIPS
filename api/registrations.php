<?php
// Direktori: api/registrations.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - pendaftaran calon siswa (publik) & kelola pendaftar
//
// Alur: formulir publik (pilih program + kelas) -> status 'baru'
//       -> admin kirim link aktivasi (WhatsApp manual) -> 'menunggu' (kursi terpakai)
//       -> siswa isi biodata + buat sandi -> 'terdaftar' (akun aktif, tagihan dibuat)
//       -> atau 'batal' (kursi lepas)
//
// Kuota kelas dihitung dari: enrollment aktif/pending + pendaftaran
// berstatus 'menunggu'. Pendaftar 'baru' belum memakai kursi.
// ============================================================

require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/activation.php';
require_once __DIR__ . '/billing.php';

/** Berapa hari kursi ditahan sejak link aktivasi dikirim sebelum dilepas otomatis. */
const ELIPS_ACTIVATION_SEAT_DAYS = 14;

function elips_registration_statuses(): array
{
    return ['baru', 'dihubungi', 'menunggu', 'terdaftar', 'batal'];
}

function elips_ensure_course_registration_table(): void
{
    elips_migrate_activation_schema();
}

/** Pendaftaran 'menunggu' yang melewati batas hari dibatalkan agar kursinya lepas. */
function elips_registrations_expire_stale(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    elips_ensure_course_registration_table();
    elips_db()->exec(
        "UPDATE course_registrations
         SET status = 'batal', updated_at = UTC_TIMESTAMP(),
             notes = CONCAT(notes, IF(notes = '', '', ' | '), 'Dibatalkan otomatis: link aktivasi kedaluwarsa.')
         WHERE status = 'menunggu'
           AND activation_sent_at IS NOT NULL
           AND activation_sent_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL " . ELIPS_ACTIVATION_SEAT_DAYS . ' DAY)'
    );
    // Link milik pendaftaran yang sudah batal tidak boleh bisa dipakai lagi.
    elips_db()->exec(
        "UPDATE account_tokens t JOIN course_registrations r ON r.id = t.registration_id
         SET t.revoked_at = UTC_TIMESTAMP()
         WHERE t.purpose = 'activation' AND t.used_at IS NULL AND t.revoked_at IS NULL AND r.status = 'batal'"
    );
}

/**
 * Kursi sebuah kelas. quota = 0 berarti tanpa batas (sama seperti modul kelas).
 * @return array{quota:int,taken:int,left:?int,full:bool}
 */
function elips_class_seats(int $classId): array
{
    elips_registrations_expire_stale();
    $stmt = elips_db()->prepare(
        "SELECT cl.quota,
            (SELECT COUNT(*) FROM enrollments e
              WHERE e.class_id = cl.id AND e.status IN ('active', 'pending')) AS enrolled,
            (SELECT COUNT(*) FROM course_registrations r
              WHERE r.class_id = cl.id AND r.status = 'menunggu') AS reserved
         FROM classes cl WHERE cl.id = ? LIMIT 1"
    );
    $stmt->execute([$classId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['quota' => 0, 'taken' => 0, 'left' => 0, 'full' => true];
    }
    $quota = (int) $row['quota'];
    $taken = (int) $row['enrolled'] + (int) $row['reserved'];
    if ($quota <= 0) {
        return ['quota' => 0, 'taken' => $taken, 'left' => null, 'full' => false];
    }
    $left = max(0, $quota - $taken);
    return ['quota' => $quota, 'taken' => $taken, 'left' => $left, 'full' => $left === 0];
}

/** Daftar kelas yang menerima pendaftaran untuk sebuah program (publik). */
function elips_action_course_classes(array $p): array
{
    $courseId = (int) ($p['courseId'] ?? 0);
    $course = $courseId > 0 ? elips_course_row($courseId) : null;
    if (!$course || ($course['status'] ?? '') !== 'published') {
        return elips_fail('Pelatihan tidak ditemukan.');
    }
    $stmt = elips_db()->prepare(
        "SELECT cl.id, cl.code, cl.name, cl.period_start, cl.period_end, cl.location, cl.schedule_note,
                b.name AS branch_name, b.city AS branch_city
         FROM classes cl
         LEFT JOIN branches b ON b.id = cl.branch_id
         WHERE cl.course_id = ? AND cl.status = 'open'
         ORDER BY cl.period_start IS NULL, cl.period_start ASC, cl.id ASC"
    );
    $stmt->execute([$courseId]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $seats = elips_class_seats((int) $row['id']);
        $items[] = [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'periodStart' => elips_date_only($row['period_start']),
            'periodEnd' => elips_date_only($row['period_end']),
            'location' => (string) $row['location'],
            'scheduleNote' => (string) $row['schedule_note'],
            'branchName' => (string) ($row['branch_name'] ?? ''),
            'branchCity' => (string) ($row['branch_city'] ?? ''),
            'quota' => $seats['quota'],
            'seatsLeft' => $seats['left'],
            'full' => $seats['full'],
        ];
    }
    return elips_ok('', $items);
}

function elips_course_registration_row(int $id): ?array
{
    elips_ensure_course_registration_table();
    $stmt = elips_db()->prepare(
        'SELECT cr.*, u.name AS student_name
         FROM course_registrations cr
         LEFT JOIN users u ON u.id = cr.student_id
         WHERE cr.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_course_registration_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'courseId' => (int) $row['course_id'],
        'courseTitle' => (string) $row['course_title'],
        'classId' => $row['class_id'] === null ? null : (int) $row['class_id'],
        'className' => (string) ($row['class_name'] ?? ''),
        'amountDue' => (float) ($row['amount_due'] ?? 0),
        'name' => (string) $row['name'],
        'birthPlace' => (string) $row['birth_place'],
        'birthDate' => $row['birth_date'] === null ? null : elips_date_only($row['birth_date']),
        'address' => (string) $row['address'],
        'phone' => (string) $row['phone'],
        'phoneNorm' => (string) ($row['phone_norm'] ?? ''),
        'email' => (string) $row['email'],
        'status' => (string) $row['status'],
        'studentId' => $row['student_id'] === null ? null : (int) $row['student_id'],
        'studentName' => (string) ($row['student_name'] ?? ''),
        'enrollmentId' => ($row['enrollment_id'] ?? null) === null ? null : (int) $row['enrollment_id'],
        'convertedAt' => elips_iso($row['converted_at'] ?? null),
        'activationSentAt' => elips_iso($row['activation_sent_at'] ?? null),
        'notes' => (string) $row['notes'],
        'createdAt' => elips_iso($row['created_at']),
        'updatedAt' => elips_iso($row['updated_at'] ?? null),
    ];
}

function elips_action_course_register(array $p): array
{
    elips_ensure_course_registration_table();

    // Honeypot: bot yang mengisi field tersembunyi diberi respons sukses
    // tanpa menyimpan data.
    if (trim((string) ($p['website'] ?? '')) !== '') {
        return elips_ok('Data pendaftaran berhasil dikirim.', ['id' => 0, 'accepted' => false]);
    }

    // Batasi per IP: maksimal 8 percobaan per 10 menit.
    $limitKey = elips_request_ip();
    $wait = elips_rate_limit_blocked('register', $limitKey, 8, 600);
    if ($wait > 0) {
        return elips_fail('Terlalu banyak percobaan pendaftaran. Coba lagi dalam ' . (int) ceil($wait / 60) . ' menit.');
    }
    elips_rate_limit_hit('register', $limitKey, 600);

    $courseId = (int) ($p['courseId'] ?? 0);
    $course = $courseId > 0 ? elips_course_row($courseId) : null;
    if (!$course) {
        return elips_fail('Pelatihan tidak ditemukan.');
    }
    if (($course['status'] ?? '') !== 'published') {
        return elips_fail('Pelatihan ini belum dibuka untuk pendaftaran.');
    }

    $name = trim((string) ($p['name'] ?? ''));
    $birthPlace = trim((string) ($p['birthPlace'] ?? ''));
    $birthDate = elips_normalize_date($p['birthDate'] ?? '');
    $address = trim((string) ($p['address'] ?? ''));
    $phone = trim((string) ($p['phone'] ?? ''));
    $email = strtolower(trim((string) ($p['email'] ?? '')));

    if ($name === '' || mb_strlen($name) > 150) {
        return elips_fail('Nama wajib diisi dan maksimal 150 karakter.');
    }
    if ($birthPlace === '' || mb_strlen($birthPlace) > 100) {
        return elips_fail('Tempat lahir wajib diisi dan maksimal 100 karakter.');
    }
    if (!$birthDate) {
        return elips_fail('Tanggal lahir tidak valid.');
    }
    if (strtotime($birthDate . ' 00:00:00') > time()) {
        return elips_fail('Tanggal lahir tidak boleh di masa depan.');
    }
    if ($address === '' || mb_strlen($address) > 500) {
        return elips_fail('Alamat lengkap wajib diisi dan maksimal 500 karakter.');
    }
    $phoneNorm = elips_normalize_phone($phone);
    if ($phone === '' || mb_strlen($phone) > 40 || $phoneNorm === '') {
        return elips_fail('No WhatsApp tidak valid. Contoh: 081234567890.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        return elips_fail('Email aktif tidak valid.');
    }

    // Kelas wajib dipilih dari kelas yang dibuka untuk program ini.
    $db = elips_db();
    $openStmt = $db->prepare("SELECT COUNT(*) FROM classes WHERE course_id = ? AND status = 'open'");
    $openStmt->execute([$courseId]);
    if ((int) $openStmt->fetchColumn() === 0) {
        return elips_fail('Belum ada kelas yang dibuka untuk program ini. Silakan hubungi kami.');
    }
    $classId = (int) ($p['classId'] ?? 0);
    $class = $classId > 0 ? elips_class_row($classId) : null;
    if (!$class || (int) $class['course_id'] !== $courseId || ($class['status'] ?? '') !== 'open') {
        return elips_fail('Pilih kelas yang tersedia untuk program ini.');
    }
    $seats = elips_class_seats($classId);
    if ($seats['full']) {
        return elips_fail('Kuota kelas "' . $class['name'] . '" sudah penuh. Pilih kelas lain.');
    }

    // Satu pendaftaran aktif per email per program (baru/menunggu), dan tidak
    // ada pendaftaran baru bila email itu sudah menjadi peserta program ini.
    // Pesan sengaja sama agar tidak membocorkan data akun.
    $dupMessage = 'Pendaftaran dengan email ini untuk program tersebut sudah ada. Kami akan segera menghubungimu.';
    $dup = $db->prepare(
        "SELECT id FROM course_registrations
         WHERE course_id = ? AND email = ? AND status IN ('baru', 'dihubungi', 'menunggu')
         LIMIT 1"
    );
    $dup->execute([$courseId, $email]);
    if ($dup->fetch()) {
        return elips_fail($dupMessage);
    }
    $existing = elips_user_by_email($email);
    if ($existing && ($existing['role'] ?? '') === 'peserta') {
        $enr = $db->prepare(
            "SELECT 1 FROM enrollments
             WHERE student_id = ? AND course_id = ? AND status IN ('active', 'pending', 'completed') LIMIT 1"
        );
        $enr->execute([(int) $existing['id'], $courseId]);
        if ($enr->fetchColumn()) {
            return elips_fail($dupMessage);
        }
    }

    $price = (float) $course['price'];
    $discount = (float) $course['discount_price'];
    $amountDue = ($discount > 0 && $discount < $price) ? $discount : $price;

    $stmt = $db->prepare(
        "INSERT INTO course_registrations
            (course_id, course_title, class_id, class_name, amount_due, name, birth_place, birth_date,
             address, phone, phone_norm, email, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'baru', UTC_TIMESTAMP())"
    );
    $stmt->execute([
        $courseId, (string) $course['title'], $classId, (string) $class['name'], $amountDue,
        $name, $birthPlace, $birthDate, $address, $phone, $phoneNorm, $email,
    ]);
    $id = (int) $db->lastInsertId();

    elips_audit(
        'create',
        'course_registration',
        $id,
        $name . ' — ' . $course['title'],
        'Pendaftaran publik untuk kelas ' . $class['name'] . '.'
    );

    return elips_ok('Data pendaftaran berhasil dikirim. Kami akan menghubungimu lewat WhatsApp.', [
        'id' => $id,
        'courseTitle' => (string) $course['title'],
        'className' => (string) $class['name'],
        'accepted' => true,
    ]);
}

function elips_action_course_registration_list(array $p): array
{
    elips_require_marketing_access();
    elips_registrations_expire_stale();

    $where = [];
    $params = [];

    $courseId = (int) ($p['courseId'] ?? 0);
    if ($courseId > 0) {
        $where[] = 'cr.course_id = ?';
        $params[] = $courseId;
    }

    $status = (string) ($p['status'] ?? '');
    if (in_array($status, elips_registration_statuses(), true)) {
        $where[] = 'cr.status = ?';
        $params[] = $status;
    }

    $sql = 'SELECT cr.*, u.name AS student_name
            FROM course_registrations cr
            LEFT JOIN users u ON u.id = cr.student_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY cr.created_at DESC, cr.id DESC';

    $stmt = elips_db()->prepare($sql);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = elips_course_registration_public($row);
    }
    return elips_ok('', $items);
}

function elips_registration_wa_message(array $reg, string $link, string $siteName = 'ELIPS LMS'): string
{
    return 'Halo ' . $reg['name'] . ', pendaftaranmu di ' . $siteName . ' untuk program "' . $reg['course_title']
        . '" (kelas ' . $reg['class_name'] . ') sudah kami proses.' . "\n\n"
        . 'Silakan lengkapi biodata dan buat sandi lewat link berikut:' . "\n" . $link . "\n\n"
        . 'Link berlaku ' . ELIPS_ACTIVATION_TOKEN_DAYS . ' hari dan hanya bisa dipakai sekali.';
}

/**
 * Admin menekan "Kirim link aktivasi" (atau "Kirim ulang"). Akun siswa dibuat
 * berstatus 'menunggu' tanpa sandi; kursi kelas langsung dipakai.
 * Siswa aktif yang mendaftar program baru tidak mendapat akun baru: langsung
 * dibuatkan pendaftaran kelas + tagihan.
 */
function elips_action_registration_send_activation(array $p): array
{
    $actor = elips_require_admin();
    elips_ensure_course_registration_table();
    elips_registrations_expire_stale();

    $id = (int) ($p['id'] ?? 0);
    $db = elips_db();

    $result = elips_tx(static function () use ($id, $actor, $db): array {
        $stmt = $db->prepare('SELECT * FROM course_registrations WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $reg = $stmt->fetch();
        if (!$reg) {
            throw new ElipsError('Pendaftaran tidak ditemukan.');
        }
        if (!in_array($reg['status'], ['baru', 'dihubungi', 'menunggu'], true)) {
            throw new ElipsError($reg['status'] === 'terdaftar'
                ? 'Pendaftaran ini sudah selesai diaktifkan.'
                : 'Pendaftaran yang dibatalkan tidak dapat diproses.');
        }
        if ($reg['class_id'] === null) {
            throw new ElipsError('Kelas pilihan sudah tidak tersedia. Batalkan pendaftaran ini dan minta calon siswa mendaftar ulang.');
        }

        // Kunci baris kelas agar dua admin tidak menjual kursi terakhir bersamaan.
        $stmt = $db->prepare('SELECT id, status FROM classes WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $reg['class_id']]);
        $class = $stmt->fetch();
        if (!$class) {
            throw new ElipsError('Kelas pilihan tidak ditemukan.');
        }
        $isResend = $reg['status'] === 'menunggu';
        if (!$isResend) {
            if (!in_array($class['status'], ['open', 'ready', 'ongoing'], true)) {
                throw new ElipsError('Kelas pilihan sudah tidak menerima peserta.');
            }
            $seats = elips_class_seats((int) $reg['class_id']);
            if ($seats['full']) {
                throw new ElipsError('Kuota kelas "' . $reg['class_name'] . '" sudah penuh. Batalkan pendaftaran atau tambah kuota kelas.');
            }
        }

        $user = elips_user_by_email((string) $reg['email']);

        // Siswa lama yang aktif: tidak ada akun baru.
        if ($user && $user['role'] === 'peserta' && $user['status'] === 'aktif') {
            $enrolled = elips_enroll_student_in_class((int) $user['id'], (int) $reg['class_id'], (int) $reg['course_id']);
            if ($enrolled['error'] !== '') {
                throw new ElipsError($enrolled['error']);
            }
            elips_invoice_create([
                'registration_id' => (int) $reg['id'],
                'student_id' => (int) $user['id'],
                'enrollment_id' => $enrolled['id'],
                'course_id' => (int) $reg['course_id'],
                'class_id' => (int) $reg['class_id'],
                'course_title' => (string) $reg['course_title'],
                'class_name' => (string) $reg['class_name'],
                'amount_due' => (float) $reg['amount_due'],
            ]);
            $db->prepare(
                "UPDATE course_registrations SET status = 'terdaftar', student_id = ?, enrollment_id = ?, activation_sent_at = UTC_TIMESTAMP(),
                        converted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?"
            )->execute([(int) $user['id'], $enrolled['id'], $id]);
            return ['mode' => 'existing', 'reg' => $reg, 'user' => $user, 'token' => null];
        }

        if ($user && ($user['role'] !== 'peserta')) {
            throw new ElipsError('Email sudah dipakai pengguna dengan role lain.');
        }
        if ($user && $user['status'] === 'nonaktif') {
            throw new ElipsError('Akun siswa dengan email ini sedang nonaktif.');
        }

        if (!$user) {
            $phoneCheck = elips_user_phone_norm_check((string) $reg['phone'], null);
            if ($phoneCheck['error'] !== '') {
                throw new ElipsError('Nomor WhatsApp ini sudah dipakai akun lain. Periksa data pendaftar.');
            }
            // Sandi acak panjang: akun belum bisa dipakai masuk sampai siswa membuat sandi sendiri.
            $db->prepare(
                "INSERT INTO users (name, email, phone, phone_norm, password_hash, role, division, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 'peserta', '', 'menunggu', UTC_TIMESTAMP())"
            )->execute([
                (string) $reg['name'], (string) $reg['email'], (string) $reg['phone'], $phoneCheck['norm'],
                password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            ]);
            $user = elips_user_row((int) $db->lastInsertId());
        }

        $token = elips_token_issue((int) $user['id'], 'activation', (int) $reg['id'], null, ELIPS_ACTIVATION_TOKEN_DAYS . ' DAY', (int) $actor['id']);
        $db->prepare(
            "UPDATE course_registrations SET status = 'menunggu', student_id = ?, activation_sent_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?"
        )->execute([(int) $user['id'], $id]);

        return ['mode' => 'link', 'reg' => $reg, 'user' => $user, 'token' => $token, 'resend' => $isResend];
    });

    $reg = $result['reg'];
    if ($result['mode'] === 'existing') {
        $message = 'Halo ' . $reg['name'] . ', pendaftaranmu untuk program "' . $reg['course_title'] . '" (kelas ' . $reg['class_name']
            . ') sudah kami proses. Masuk ke ELIPS LMS dengan akun yang sudah ada: ' . elips_public_base_url() . '/login.php';
        elips_audit('update', 'course_registration', $id, $reg['name'] . ' — ' . $reg['course_title'],
            'Siswa lama didaftarkan ke kelas ' . $reg['class_name'] . ' tanpa akun baru (tagihan dibuat).', $actor);
        $updated = elips_course_registration_row($id);
        return elips_ok('Siswa sudah punya akun aktif: didaftarkan ke kelas dan tagihan dibuat.', [
            'mode' => 'existing',
            'link' => '',
            'waUrl' => elips_wa_link(elips_normalize_phone((string) $reg['phone']), $message),
            'message' => $message,
            'registration' => $updated ? elips_course_registration_public($updated) : null,
        ]);
    }

    $link = elips_public_base_url() . '/activate.php?token=' . $result['token']['token'];
    $message = elips_registration_wa_message($reg, $link);
    elips_audit('update', 'course_registration', $id, $reg['name'] . ' — ' . $reg['course_title'],
        ($result['resend'] ? 'Mengirim ulang' : 'Membuat') . ' link aktivasi (kursi kelas ' . $reg['class_name'] . ' ditahan).', $actor);
    $updated = elips_course_registration_row($id);
    return elips_ok($result['resend'] ? 'Link aktivasi baru dibuat.' : 'Link aktivasi dibuat.', [
        'mode' => 'link',
        'link' => $link,
        'waUrl' => elips_wa_link(elips_normalize_phone((string) $reg['phone']), $message),
        'message' => $message,
        'expiresAt' => elips_iso($result['token']['expiresAt']),
        'registration' => $updated ? elips_course_registration_public($updated) : null,
    ]);
}

function elips_action_registration_cancel(array $p): array
{
    $actor = elips_require_admin();
    elips_ensure_course_registration_table();
    $id = (int) ($p['id'] ?? 0);
    $reason = trim((string) ($p['reason'] ?? ''));
    if ($reason === '') {
        return elips_fail('Alasan pembatalan wajib diisi.');
    }
    if (mb_strlen($reason) > 300) {
        return elips_fail('Alasan maksimal 300 karakter.');
    }
    $reg = elips_course_registration_row($id);
    if (!$reg) {
        return elips_fail('Pendaftaran tidak ditemukan.');
    }
    if (!in_array($reg['status'], ['baru', 'dihubungi', 'menunggu'], true)) {
        return elips_fail($reg['status'] === 'terdaftar' ? 'Pendaftaran yang sudah aktif tidak bisa dibatalkan di sini.' : 'Pendaftaran sudah dibatalkan.');
    }
    elips_db()->prepare(
        "UPDATE course_registrations
         SET status = 'batal', notes = CONCAT(notes, IF(notes = '', '', ' | '), ?), updated_at = UTC_TIMESTAMP()
         WHERE id = ?"
    )->execute(['Dibatalkan admin: ' . $reason, $id]);
    elips_db()->prepare(
        "UPDATE account_tokens SET revoked_at = UTC_TIMESTAMP()
         WHERE registration_id = ? AND used_at IS NULL AND revoked_at IS NULL"
    )->execute([$id]);
    elips_audit('update', 'course_registration', $id, $reg['name'] . ' — ' . $reg['course_title'], 'Membatalkan pendaftaran: ' . $reason, $actor);
    $updated = elips_course_registration_row($id);
    return elips_ok('Pendaftaran dibatalkan.', $updated ? elips_course_registration_public($updated) : null);
}