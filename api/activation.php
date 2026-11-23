<?php
// Direktori: api/activation.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - aktivasi akun siswa, biodata, kebijakan sandi,
// lupa sandi & reset sandi lewat link sekali pakai.
//
// Token disimpan sebagai hash SHA-256 (token asli hanya ada di link
// yang dikirim admin lewat WhatsApp). Link aktivasi berlaku 7 hari,
// link reset 24 jam, keduanya sekali pakai.
// ============================================================

require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/billing.php';

const ELIPS_ACTIVATION_TOKEN_DAYS = 7;
const ELIPS_RESET_TOKEN_HOURS = 24;

// ---------- Kebijakan sandi ----------

/** Pesan kesalahan bila sandi tidak memenuhi aturan, atau '' bila valid. */
function elips_password_policy_error(string $password): string
{
    if (strlen($password) < 8) {
        return 'Sandi minimal 8 karakter.';
    }
    if (strlen($password) > 72) {
        return 'Sandi maksimal 72 karakter.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Sandi harus memuat minimal satu huruf besar.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Sandi harus memuat minimal satu huruf kecil.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Sandi harus memuat minimal satu angka.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Sandi harus memuat minimal satu karakter khusus (misalnya ! @ # $).';
    }
    return '';
}

// ---------- URL & WhatsApp ----------

/** Alamat dasar aplikasi untuk membangun link. ELIPS_PUBLIC_URL menang bila diisi. */
function elips_public_base_url(): string
{
    $env = getenv('ELIPS_PUBLIC_URL');
    if (is_string($env) && trim($env) !== '') {
        return rtrim(trim($env), '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Hanya karakter host yang wajar (cegah header injection pada link).
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'));
    $base = rtrim(dirname(dirname($script)), '/');
    if ($base === '.' || $base === '\\') {
        $base = '';
    }
    return ($https ? 'https' : 'http') . '://' . $host . $base;
}

function elips_wa_link(string $phoneNorm, string $message): string
{
    $phoneNorm = preg_replace('/\D+/', '', $phoneNorm) ?? '';
    return 'https://wa.me/' . $phoneNorm . '?text=' . rawurlencode($message);
}

// ---------- Token ----------

function elips_token_hash(string $raw): string
{
    return hash('sha256', $raw);
}

/**
 * Terbitkan token baru; token lama untuk user + tujuan yang sama dicabut.
 * @return array{token:string,expiresAt:string}
 */
function elips_token_issue(int $userId, string $purpose, ?int $registrationId, ?int $resetRequestId, string $interval, ?int $createdBy): array
{
    $db = elips_db();
    $db->prepare(
        'UPDATE account_tokens SET revoked_at = UTC_TIMESTAMP()
         WHERE user_id = ? AND purpose = ? AND used_at IS NULL AND revoked_at IS NULL'
    )->execute([$userId, $purpose]);

    $raw = bin2hex(random_bytes(32));
    $expires = $db->query('SELECT DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . $interval . ')')->fetchColumn();
    $db->prepare(
        'INSERT INTO account_tokens (user_id, purpose, token_hash, registration_id, reset_request_id, expires_at, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    )->execute([$userId, $purpose, elips_token_hash($raw), $registrationId, $resetRequestId, $expires, $createdBy]);

    return ['token' => $raw, 'expiresAt' => (string) $expires];
}

/** Cabut seluruh token aktif sebuah user (opsional per tujuan). */
function elips_token_revoke_all(int $userId, ?string $purpose = null): void
{
    $sql = 'UPDATE account_tokens SET revoked_at = UTC_TIMESTAMP()
            WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL';
    $params = [$userId];
    if ($purpose !== null) {
        $sql .= ' AND purpose = ?';
        $params[] = $purpose;
    }
    elips_db()->prepare($sql)->execute($params);
}

/**
 * Cari token yang masih berlaku. $forUpdate mengunci barisnya (dalam transaksi).
 * @return array{row:?array,error:string}
 */
function elips_token_find(string $raw, string $purpose, bool $forUpdate = false): array
{
    $raw = trim($raw);
    if ($raw === '' || !preg_match('/^[a-f0-9]{64}$/', $raw)) {
        return ['row' => null, 'error' => 'Link tidak valid.'];
    }
    $stmt = elips_db()->prepare(
        'SELECT *, (expires_at < UTC_TIMESTAMP()) AS is_expired
         FROM account_tokens WHERE token_hash = ? AND purpose = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([elips_token_hash($raw), $purpose]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['row' => null, 'error' => 'Link tidak valid.'];
    }
    if ($row['used_at'] !== null) {
        return ['row' => null, 'error' => 'Link ini sudah pernah dipakai.'];
    }
    if ($row['revoked_at'] !== null) {
        return ['row' => null, 'error' => 'Link ini sudah tidak berlaku. Hubungi admin untuk meminta link baru.'];
    }
    if ((int) $row['is_expired'] === 1) {
        return ['row' => null, 'error' => 'Link sudah kedaluwarsa. Hubungi admin untuk meminta link baru.'];
    }
    return ['row' => $row, 'error' => ''];
}

/** Batasi percobaan token per IP agar link tidak bisa ditebak. */
function elips_token_rate_guard(): ?array
{
    $key = elips_request_ip();
    $wait = elips_rate_limit_blocked('token', $key, 30, 300);
    if ($wait > 0) {
        return elips_fail('Terlalu banyak percobaan. Coba lagi dalam ' . $wait . ' detik.');
    }
    elips_rate_limit_hit('token', $key, 300);
    return null;
}

// ---------- Biodata ----------

function elips_profile_options(): array
{
    $school = ['institutionLabel' => 'Nama sekolah', 'majorLabel' => null, 'majorRequired' => false, 'yearLabel' => 'Tahun lulus'];
    $vocational = ['institutionLabel' => 'Nama sekolah', 'majorLabel' => 'Jurusan', 'majorRequired' => true, 'yearLabel' => 'Tahun lulus'];
    $college = ['institutionLabel' => 'Nama perguruan tinggi', 'majorLabel' => 'Program studi', 'majorRequired' => true, 'yearLabel' => 'Tahun lulus'];

    return [
        'religions' => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'],
        'educationLevels' => [
            ['code' => 'SD'] + ['label' => 'SD / Sederajat'] + $school,
            ['code' => 'SMP'] + ['label' => 'SMP / Sederajat'] + $school,
            ['code' => 'SMA'] + ['label' => 'SMA / SMK / Sederajat'] + $vocational,
            ['code' => 'D3'] + ['label' => 'Diploma (D1 - D3)'] + $college,
            ['code' => 'S1'] + ['label' => 'Sarjana (S1 / D4)'] + $college,
            ['code' => 'S2'] + ['label' => 'Magister (S2)'] + $college,
            ['code' => 'S3'] + ['label' => 'Doktor (S3)'] + $college,
        ],
        'occupations' => [
            'Pelajar', 'Mahasiswa', 'Karyawan Swasta', 'PNS / ASN', 'Wiraswasta',
            'Guru / Dosen', 'Tenaga Kesehatan', 'Ibu Rumah Tangga', 'Belum Bekerja', 'Lainnya',
        ],
    ];
}

function elips_profile_row(int $userId): ?array
{
    $stmt = elips_db()->prepare('SELECT * FROM student_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function elips_profile_public(?array $row): array
{
    if (!$row) {
        return [];
    }
    return [
        'fullName' => (string) $row['full_name'],
        'address' => (string) $row['address'],
        'phone' => (string) $row['phone'],
        'birthPlace' => (string) $row['birth_place'],
        'birthDate' => $row['birth_date'] === null ? '' : elips_date_only($row['birth_date']),
        'religion' => (string) $row['religion'],
        'educationLevel' => (string) $row['education_level'],
        'educationInstitution' => (string) $row['education_institution'],
        'educationMajor' => (string) $row['education_major'],
        'educationYear' => $row['education_year'] === null ? '' : (string) $row['education_year'],
        'occupation' => (string) $row['occupation'],
        'occupationOther' => (string) $row['occupation_other'],
    ];
}

/**
 * Validasi biodata. Mengembalikan ['error' => pesan] atau ['data' => nilai bersih].
 */
function elips_profile_validate(array $in): array
{
    $opts = elips_profile_options();
    $get = static function (string $k) use ($in): string {
        return trim((string) ($in[$k] ?? ''));
    };

    $fullName = $get('fullName');
    $address = $get('address');
    $phone = $get('phone');
    $birthPlace = $get('birthPlace');
    $birthDate = elips_normalize_date($in['birthDate'] ?? '');
    $religion = $get('religion');
    $level = $get('educationLevel');
    $institution = $get('educationInstitution');
    $major = $get('educationMajor');
    $yearRaw = $get('educationYear');
    $occupation = $get('occupation');
    $occupationOther = $get('occupationOther');

    if ($fullName === '' || mb_strlen($fullName) > 150) return ['error' => 'Nama lengkap wajib diisi (maksimal 150 karakter).'];
    if ($address === '' || mb_strlen($address) > 500) return ['error' => 'Alamat wajib diisi (maksimal 500 karakter).'];
    $phoneNorm = elips_normalize_phone($phone);
    if ($phone === '' || mb_strlen($phone) > 40 || $phoneNorm === '') return ['error' => 'No telp/WhatsApp tidak valid. Contoh: 081234567890.'];
    if ($birthPlace === '' || mb_strlen($birthPlace) > 100) return ['error' => 'Tempat lahir wajib diisi (maksimal 100 karakter).'];
    if (!$birthDate) return ['error' => 'Tanggal lahir tidak valid.'];
    if (strtotime($birthDate . ' 00:00:00') > time()) return ['error' => 'Tanggal lahir tidak boleh di masa depan.'];
    if (!in_array($religion, $opts['religions'], true)) return ['error' => 'Pilih agama.'];

    $levelCfg = null;
    foreach ($opts['educationLevels'] as $cfg) {
        if ($cfg['code'] === $level) {
            $levelCfg = $cfg;
            break;
        }
    }
    if (!$levelCfg) return ['error' => 'Pilih pendidikan terakhir.'];
    if ($institution === '' || mb_strlen($institution) > 150) return ['error' => $levelCfg['institutionLabel'] . ' wajib diisi (maksimal 150 karakter).'];
    if ($levelCfg['majorLabel'] === null) {
        $major = '';
    } elseif (($levelCfg['majorRequired'] && $major === '') || mb_strlen($major) > 150) {
        return ['error' => $levelCfg['majorLabel'] . ' wajib diisi (maksimal 150 karakter).'];
    }
    if (!preg_match('/^\d{4}$/', $yearRaw) || (int) $yearRaw < 1950 || (int) $yearRaw > ((int) gmdate('Y') + 8)) {
        return ['error' => $levelCfg['yearLabel'] . ' tidak valid.'];
    }

    if (!in_array($occupation, $opts['occupations'], true)) return ['error' => 'Pilih pekerjaan.'];
    if ($occupation === 'Lainnya') {
        if ($occupationOther === '' || mb_strlen($occupationOther) > 100) return ['error' => 'Sebutkan pekerjaanmu (maksimal 100 karakter).'];
    } else {
        $occupationOther = '';
    }

    return ['data' => [
        'full_name' => $fullName,
        'address' => $address,
        'phone' => $phone,
        'phone_norm' => $phoneNorm,
        'birth_place' => $birthPlace,
        'birth_date' => $birthDate,
        'religion' => $religion,
        'education_level' => $level,
        'education_institution' => $institution,
        'education_major' => $major,
        'education_year' => (int) $yearRaw,
        'occupation' => $occupation,
        'occupation_other' => $occupationOther,
    ]];
}

function elips_profile_save(int $userId, array $d): void
{
    elips_db()->prepare(
        'INSERT INTO student_profiles
            (user_id, full_name, address, phone, birth_place, birth_date, religion, education_level,
             education_institution, education_major, education_year, occupation, occupation_other,
             completed_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name), address = VALUES(address), phone = VALUES(phone),
            birth_place = VALUES(birth_place), birth_date = VALUES(birth_date), religion = VALUES(religion),
            education_level = VALUES(education_level), education_institution = VALUES(education_institution),
            education_major = VALUES(education_major), education_year = VALUES(education_year),
            occupation = VALUES(occupation), occupation_other = VALUES(occupation_other),
            completed_at = COALESCE(completed_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP()'
    )->execute([
        $userId, $d['full_name'], $d['address'], $d['phone'], $d['birth_place'], $d['birth_date'],
        $d['religion'], $d['education_level'], $d['education_institution'], $d['education_major'],
        $d['education_year'], $d['occupation'], $d['occupation_other'],
    ]);
}

// ---------- Telepon pada akun ----------

/**
 * Normalisasi + cek keunikan nomor untuk disimpan di users.phone_norm.
 * @return array{norm:?string,error:string}
 */
function elips_user_phone_norm_check(string $phone, ?int $exceptUserId): array
{
    $phone = trim($phone);
    if ($phone === '') {
        return ['norm' => null, 'error' => ''];
    }
    $norm = elips_normalize_phone($phone);
    if ($norm === '') {
        return ['norm' => null, 'error' => ''];
    }
    $stmt = elips_db()->prepare('SELECT id FROM users WHERE phone_norm = ? AND id <> ? LIMIT 1');
    $stmt->execute([$norm, (int) $exceptUserId]);
    if ($stmt->fetch()) {
        return ['norm' => null, 'error' => 'Nomor telepon sudah dipakai akun lain.'];
    }
    return ['norm' => $norm, 'error' => ''];
}

/** Login boleh memakai email atau nomor telepon. */
function elips_user_by_identifier(string $identifier): ?array
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }
    if (strpos($identifier, '@') !== false) {
        return elips_user_by_email(strtolower($identifier));
    }
    $norm = elips_normalize_phone($identifier);
    if ($norm === '') {
        return null;
    }
    $stmt = elips_db()->prepare('SELECT * FROM users WHERE phone_norm = ? LIMIT 1');
    $stmt->execute([$norm]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ---------- Aktivasi (publik, lewat link) ----------

function elips_action_activation_info(array $p): array
{
    elips_migrate_activation_schema();
    if ($blocked = elips_token_rate_guard()) {
        return $blocked;
    }
    $found = elips_token_find((string) ($p['token'] ?? ''), 'activation');
    if (!$found['row']) {
        return elips_fail($found['error']);
    }
    $token = $found['row'];
    $reg = elips_course_registration_row((int) $token['registration_id']);
    if (!$reg || $reg['status'] !== 'menunggu') {
        return elips_fail('Pendaftaran ini sudah tidak aktif. Hubungi admin.');
    }
    $user = elips_user_row((int) $token['user_id']);
    if (!$user || $user['status'] !== 'menunggu') {
        return elips_fail('Akun ini sudah diaktifkan. Silakan masuk.');
    }

    // Prioritas: biodata tersimpan (bila ada) > data pendaftaran.
    $profile = elips_profile_public(elips_profile_row((int) $user['id']));
    $prefill = $profile + [
        'fullName' => (string) $reg['name'],
        'address' => (string) $reg['address'],
        'phone' => (string) $reg['phone'],
        'birthPlace' => (string) $reg['birth_place'],
        'birthDate' => $reg['birth_date'] === null ? '' : elips_date_only($reg['birth_date']),
    ];

    return elips_ok('', [
        'email' => (string) $user['email'],
        'courseTitle' => (string) $reg['course_title'],
        'className' => (string) ($reg['class_name'] ?? ''),
        'prefill' => $prefill,
        'options' => elips_profile_options(),
        'expiresAt' => elips_iso($token['expires_at']),
    ]);
}

function elips_action_activation_complete(array $p): array
{
    elips_migrate_activation_schema();
    if ($blocked = elips_token_rate_guard()) {
        return $blocked;
    }

    $validated = elips_profile_validate(is_array($p['profile'] ?? null) ? $p['profile'] : []);
    if (isset($validated['error'])) {
        return elips_fail($validated['error']);
    }
    $profile = $validated['data'];

    $password = (string) ($p['password'] ?? '');
    $confirm = (string) ($p['passwordConfirm'] ?? '');
    if ($error = elips_password_policy_error($password)) {
        return elips_fail($error);
    }
    if ($password !== $confirm) {
        return elips_fail('Konfirmasi sandi tidak sama.');
    }

    $db = elips_db();
    $db->beginTransaction();
    try {
        $found = elips_token_find((string) ($p['token'] ?? ''), 'activation', true);
        if (!$found['row']) {
            $db->rollBack();
            return elips_fail($found['error']);
        }
        $token = $found['row'];
        $userId = (int) $token['user_id'];

        $stmt = $db->prepare('SELECT * FROM course_registrations WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $token['registration_id']]);
        $reg = $stmt->fetch();
        $user = elips_user_row($userId);
        if (!$reg || $reg['status'] !== 'menunggu' || !$user || $user['status'] !== 'menunggu') {
            $db->rollBack();
            return elips_fail('Pendaftaran ini sudah tidak aktif. Hubungi admin.');
        }
        if ($reg['class_id'] === null) {
            $db->rollBack();
            return elips_fail('Kelas pilihanmu sudah tidak tersedia. Hubungi admin.');
        }

        $phoneCheck = elips_user_phone_norm_check($profile['phone'], $userId);
        if ($phoneCheck['error'] !== '') {
            $db->rollBack();
            return elips_fail($phoneCheck['error']);
        }

        // Akun aktif; sandi tersimpan sebagai hash.
        $db->prepare(
            "UPDATE users SET name = ?, phone = ?, phone_norm = ?, password_hash = ?, status = 'aktif',
                    session_version = session_version + 1, updated_at = UTC_TIMESTAMP()
             WHERE id = ?"
        )->execute([$profile['full_name'], $profile['phone'], $phoneCheck['norm'], password_hash($password, PASSWORD_DEFAULT), $userId]);
        elips_profile_save($userId, $profile);

        $enrollment = elips_enroll_student_in_class($userId, (int) $reg['class_id'], (int) $reg['course_id']);
        if ($enrollment['error'] !== '') {
            $db->rollBack();
            return elips_fail($enrollment['error']);
        }
        $invoiceId = elips_invoice_create([
            'registration_id' => (int) $reg['id'],
            'student_id' => $userId,
            'enrollment_id' => $enrollment['id'],
            'course_id' => (int) $reg['course_id'],
            'class_id' => (int) $reg['class_id'],
            'course_title' => (string) $reg['course_title'],
            'class_name' => (string) $reg['class_name'],
            'amount_due' => (float) $reg['amount_due'],
        ]);

        $db->prepare(
            "UPDATE course_registrations
             SET status = 'terdaftar', student_id = ?, enrollment_id = ?, converted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ?"
        )->execute([$userId, $enrollment['id'], (int) $reg['id']]);
        $db->prepare('UPDATE account_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $token['id']]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    $fresh = elips_user_row($userId);
    elips_remember_revoke_all($userId, 'Akun diaktifkan.', $fresh, true, 'security');
    elips_audit(
        'update',
        'user',
        $userId,
        $profile['full_name'],
        'Siswa mengaktifkan akun lewat link aktivasi (biodata & sandi dibuat), kelas ' . (string) $reg['class_name'] . '.',
        $fresh
    );
    elips_session_apply($fresh);

    return elips_ok('Akun aktif. Selamat datang!', [
        'user' => elips_serialize_user($fresh),
        'invoiceId' => $invoiceId,
        'redirect' => 'student/dashboard.php',
    ]);
}

// ---------- Lupa sandi ----------

function elips_action_password_forgot(array $p): array
{
    elips_migrate_activation_schema();
    $generic = elips_ok('Jika data cocok dengan akun siswa, permintaan reset sandi sudah diteruskan ke admin. Admin akan menghubungimu lewat WhatsApp.');

    $ip = elips_request_ip();
    $wait = elips_rate_limit_blocked('forgot', $ip, 6, 600);
    if ($wait > 0) {
        return elips_fail('Terlalu banyak permintaan. Coba lagi dalam ' . (int) ceil($wait / 60) . ' menit.');
    }
    elips_rate_limit_hit('forgot', $ip, 600);

    $identifier = trim((string) ($p['identifier'] ?? ($p['email'] ?? '')));
    if ($identifier === '') {
        return elips_fail('Isi email atau nomor telepon akunmu.');
    }

    $user = elips_user_by_identifier($identifier);
    if (!$user || $user['role'] !== 'peserta' || $user['status'] !== 'aktif') {
        return $generic;
    }

    $db = elips_db();
    $stmt = $db->prepare("SELECT id FROM password_reset_requests WHERE user_id = ? AND status IN ('pending', 'sent') LIMIT 1");
    $stmt->execute([(int) $user['id']]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $db->prepare("UPDATE password_reset_requests SET status = 'pending', requested_at = UTC_TIMESTAMP(), requested_ip = ? WHERE id = ?")
            ->execute([$ip, (int) $existing]);
    } else {
        $db->prepare("INSERT INTO password_reset_requests (user_id, status, requested_at, requested_ip) VALUES (?, 'pending', UTC_TIMESTAMP(), ?)")
            ->execute([(int) $user['id'], $ip]);
        elips_audit('create', 'password_reset_request', (int) $db->lastInsertId(), (string) $user['name'], 'Siswa meminta reset sandi.', $user);
    }
    return $generic;
}

/** Permintaan reset yang menunggu ditangani admin. */
function elips_action_password_requests(array $p): array
{
    elips_require_admin();
    elips_migrate_activation_schema();
    $rows = elips_db()->query(
        "SELECT r.id, r.user_id, r.status, r.requested_at, u.name, u.email, u.phone, u.phone_norm
         FROM password_reset_requests r
         JOIN users u ON u.id = r.user_id
         WHERE r.status IN ('pending', 'sent')
         ORDER BY r.requested_at DESC"
    )->fetchAll();
    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'id' => (int) $r['id'],
            'userId' => (int) $r['user_id'],
            'status' => (string) $r['status'],
            'requestedAt' => elips_iso($r['requested_at']),
            'name' => (string) $r['name'],
            'email' => (string) $r['email'],
            'phone' => (string) $r['phone'],
        ];
    }
    return elips_ok('', $items);
}

function elips_action_password_reset_issue(array $p): array
{
    $actor = elips_require_admin();
    elips_migrate_activation_schema();
    $requestId = (int) ($p['requestId'] ?? 0);

    $stmt = elips_db()->prepare(
        "SELECT r.*, u.name, u.phone, u.phone_norm, u.status AS user_status
         FROM password_reset_requests r JOIN users u ON u.id = r.user_id
         WHERE r.id = ? LIMIT 1"
    );
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();
    if (!$req || !in_array($req['status'], ['pending', 'sent'], true)) {
        return elips_fail('Permintaan reset sandi tidak ditemukan atau sudah selesai.');
    }
    if ($req['user_status'] !== 'aktif') {
        return elips_fail('Akun siswa ini tidak aktif.');
    }

    $issued = elips_token_issue((int) $req['user_id'], 'reset', null, $requestId, ELIPS_RESET_TOKEN_HOURS . ' HOUR', (int) $actor['id']);
    elips_db()->prepare("UPDATE password_reset_requests SET status = 'sent', handled_by = ?, handled_at = UTC_TIMESTAMP() WHERE id = ?")
        ->execute([(int) $actor['id'], $requestId]);

    $link = elips_public_base_url() . '/reset-password.php?token=' . $issued['token'];
    $message = 'Halo ' . $req['name'] . ', ini link untuk membuat sandi baru akun ELIPS LMS kamu:' . "\n" . $link
        . "\n\nLink berlaku " . ELIPS_RESET_TOKEN_HOURS . ' jam dan hanya bisa dipakai sekali.';
    $phone = (string) ($req['phone_norm'] ?? '');

    elips_audit('create', 'password_reset_link', $requestId, (string) $req['name'], 'Admin menerbitkan link reset sandi.', $actor);

    return elips_ok('Link reset sandi dibuat.', [
        'link' => $link,
        'waUrl' => $phone !== '' ? elips_wa_link($phone, $message) : '',
        'message' => $message,
        'expiresAt' => elips_iso($issued['expiresAt']),
    ]);
}

function elips_action_password_request_dismiss(array $p): array
{
    $actor = elips_require_admin();
    elips_migrate_activation_schema();
    $requestId = (int) ($p['requestId'] ?? 0);
    $stmt = elips_db()->prepare("SELECT * FROM password_reset_requests WHERE id = ? AND status IN ('pending', 'sent') LIMIT 1");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();
    if (!$req) {
        return elips_fail('Permintaan reset sandi tidak ditemukan.');
    }
    elips_db()->prepare("UPDATE password_reset_requests SET status = 'cancelled', handled_by = ?, handled_at = UTC_TIMESTAMP() WHERE id = ?")
        ->execute([(int) $actor['id'], $requestId]);
    elips_token_revoke_all((int) $req['user_id'], 'reset');
    elips_audit('update', 'password_reset_request', $requestId, '', 'Admin menutup permintaan reset sandi.', $actor);
    return elips_ok('Permintaan ditutup.');
}

function elips_action_password_reset_info(array $p): array
{
    elips_migrate_activation_schema();
    if ($blocked = elips_token_rate_guard()) {
        return $blocked;
    }
    $found = elips_token_find((string) ($p['token'] ?? ''), 'reset');
    if (!$found['row']) {
        return elips_fail($found['error']);
    }
    $user = elips_user_row((int) $found['row']['user_id']);
    if (!$user || $user['status'] !== 'aktif') {
        return elips_fail('Akun tidak ditemukan atau tidak aktif.');
    }
    return elips_ok('', ['name' => (string) $user['name'], 'expiresAt' => elips_iso($found['row']['expires_at'])]);
}

function elips_action_password_reset_complete(array $p): array
{
    elips_migrate_activation_schema();
    if ($blocked = elips_token_rate_guard()) {
        return $blocked;
    }
    $password = (string) ($p['password'] ?? '');
    if ($error = elips_password_policy_error($password)) {
        return elips_fail($error);
    }
    if ($password !== (string) ($p['passwordConfirm'] ?? '')) {
        return elips_fail('Konfirmasi sandi tidak sama.');
    }

    $db = elips_db();
    $db->beginTransaction();
    try {
        $found = elips_token_find((string) ($p['token'] ?? ''), 'reset', true);
        if (!$found['row']) {
            $db->rollBack();
            return elips_fail($found['error']);
        }
        $token = $found['row'];
        $userId = (int) $token['user_id'];
        $user = elips_user_row($userId);
        if (!$user || $user['status'] !== 'aktif') {
            $db->rollBack();
            return elips_fail('Akun tidak ditemukan atau tidak aktif.');
        }
        $db->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1, updated_at = UTC_TIMESTAMP() WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        $db->prepare('UPDATE account_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $token['id']]);
        if ($token['reset_request_id'] !== null) {
            $db->prepare("UPDATE password_reset_requests SET status = 'done', handled_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([(int) $token['reset_request_id']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    elips_remember_revoke_all($userId, 'Sandi direset.', $user, true, 'security');
    elips_audit('update', 'user', $userId, (string) $user['name'], 'Siswa membuat sandi baru lewat link reset.', $user);
    return elips_ok('Sandi berhasil diubah. Silakan masuk dengan sandi baru.');
}