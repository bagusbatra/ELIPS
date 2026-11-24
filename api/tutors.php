<?php
// Direktori: api/tutors.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - akun tentor
//
// Alur:
//  1. Admin menambah tentor dengan NAMA saja -> akun berstatus 'menunggu'.
//  2. Admin membuat link unik (7 hari, sekali pakai) dan mengirimnya.
//  3. Tentor membuka link, mengisi biodata + unggah berkas wajib
//     (CV, KTP, ijazah terakhir, minimal satu sertifikat), lalu membuat sandi.
//  4. Akun aktif dan tentor masuk ke dashboard tentor.
//
// Role di database tetap 'instruktur'; "tentor" adalah istilah tampilan.
// ============================================================

require_once __DIR__ . '/activation.php';

const ELIPS_TUTOR_DOC_MAX_BYTES = 3 * 1024 * 1024;
const ELIPS_TUTOR_CERT_MAX = 10;
const ELIPS_TUTOR_DOC_KINDS = ['cv', 'ktp', 'ijazah', 'sertifikat'];

function elips_tutor_doc_label(string $kind): string
{
    return ['cv' => 'CV', 'ktp' => 'KTP', 'ijazah' => 'Ijazah terakhir', 'sertifikat' => 'Sertifikat pendukung'][$kind] ?? $kind;
}

/** Buat tabel/enum yang dibutuhkan alur tentor (aman dipanggil berulang). */
function elips_ensure_tutor_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    elips_migrate_activation_schema();
    $db = elips_db();

    $col = $db->query("SHOW COLUMNS FROM account_tokens LIKE 'purpose'")->fetch();
    if ($col && strpos((string) $col['Type'], "'tutor_activation'") === false) {
        $db->exec("ALTER TABLE account_tokens MODIFY purpose ENUM('activation', 'reset', 'tutor_activation') NOT NULL");
    }

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `tutor_profiles` (
    `user_id` INT UNSIGNED NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `birth_place` VARCHAR(100) NOT NULL DEFAULT '',
    `birth_date` DATE NULL,
    `address` VARCHAR(500) NOT NULL DEFAULT '',
    `phone` VARCHAR(40) NOT NULL DEFAULT '',
    `email` VARCHAR(190) NOT NULL DEFAULT '',
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_tutor_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `tutor_documents` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `kind` ENUM('cv', 'ktp', 'ijazah', 'sertifikat') NOT NULL,
    `label` VARCHAR(150) NOT NULL DEFAULT '',
    `original_name` VARCHAR(190) NOT NULL DEFAULT '',
    `stored_name` VARCHAR(80) NOT NULL,
    `mime` VARCHAR(60) NOT NULL,
    `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tutor_docs_user` (`user_id`, `kind`),
    CONSTRAINT `fk_tutor_docs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    $done = true;
}

function elips_tutor_doc_dir(): string
{
    $dir = dirname(__DIR__) . '/storage/tutor-docs';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new ElipsError('Folder berkas tentor tidak dapat dibuat.');
    }
    return $dir;
}

function elips_tutor_doc_public(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'kind' => (string) $row['kind'],
        'kindLabel' => elips_tutor_doc_label((string) $row['kind']),
        'label' => (string) $row['label'],
        'fileName' => (string) $row['original_name'],
        'mime' => (string) $row['mime'],
        'size' => (int) $row['size_bytes'],
    ];
}

function elips_tutor_docs(int $userId): array
{
    $stmt = elips_db()->prepare('SELECT * FROM tutor_documents WHERE user_id = ? ORDER BY FIELD(kind, \'cv\', \'ktp\', \'ijazah\', \'sertifikat\'), id');
    $stmt->execute([$userId]);
    return array_map('elips_tutor_doc_public', $stmt->fetchAll());
}

/**
 * Pembatas untuk halaman aktivasi tentor. Percobaan dengan token SALAH dibatasi
 * ketat (cegah tebak link); panggilan dengan token benar (unggah berkas,
 * pengecekan, dll.) dibatasi lebih longgar.
 */
function elips_tutor_rate_guard(): ?array
{
    $ip = elips_request_ip();
    $wait = max(elips_rate_limit_blocked('token', $ip, 30, 300), elips_rate_limit_blocked('tutor_calls', $ip, 120, 300));
    if ($wait > 0) {
        return elips_fail('Terlalu banyak percobaan. Coba lagi dalam ' . $wait . ' detik.');
    }
    elips_rate_limit_hit('tutor_calls', $ip, 300);
    return null;
}

/** Token aktivasi tentor yang masih berlaku + akun yang cocok. @return array{token:?array,user:?array,error:string} */
function elips_tutor_token_context(string $raw, bool $forUpdate = false): array
{
    $found = elips_token_find($raw, 'tutor_activation', $forUpdate);
    if (!$found['row']) {
        elips_rate_limit_hit('token', elips_request_ip(), 300);
        return ['token' => null, 'user' => null, 'error' => $found['error']];
    }
    $user = elips_user_row((int) $found['row']['user_id']);
    if (!$user || $user['role'] !== 'instruktur' || $user['status'] !== 'menunggu') {
        return ['token' => null, 'user' => null, 'error' => 'Akun ini sudah diaktifkan. Silakan masuk.'];
    }
    return ['token' => $found['row'], 'user' => $user, 'error' => ''];
}

// ---------- Admin ----------

function elips_action_tutor_create(array $p): array
{
    $actor = elips_require_admin();
    elips_ensure_tutor_schema();
    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') return elips_fail('Nama tentor wajib diisi.');
    if (mb_strlen($name) > 150) return elips_fail('Nama tentor maksimal 150 karakter.');

    // Email sementara (tidak valid, unik) sampai tentor mengisi email aktifnya sendiri.
    $placeholder = 'tentor-' . bin2hex(random_bytes(6)) . '@pending.invalid';
    elips_db()->prepare(
        "INSERT INTO users (name, email, phone, phone_norm, password_hash, role, division, status, created_at)
         VALUES (?, ?, '', NULL, ?, 'instruktur', '', 'menunggu', UTC_TIMESTAMP())"
    )->execute([$name, $placeholder, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
    $id = (int) elips_db()->lastInsertId();

    elips_audit('create', 'user', $id, $name, 'Menambahkan akun tentor "' . $name . '" (menunggu aktivasi).', $actor);
    return elips_ok('Akun tentor dibuat. Kirim link aktivasi agar tentor bisa melengkapi data.', elips_public_user($id));
}

function elips_action_tutor_send_activation(array $p): array
{
    $actor = elips_require_admin();
    elips_ensure_tutor_schema();
    $userId = (int) ($p['userId'] ?? 0);
    $user = elips_user_row($userId);
    if (!$user || $user['role'] !== 'instruktur') return elips_fail('Akun tentor tidak ditemukan.');
    if ($user['status'] !== 'menunggu') return elips_fail('Akun tentor ini sudah aktif, link aktivasi tidak diperlukan.');

    $token = elips_token_issue($userId, 'tutor_activation', null, null, ELIPS_ACTIVATION_TOKEN_DAYS . ' DAY', (int) $actor['id']);
    $link = elips_public_base_url() . '/activate-tentor.php?token=' . $token['token'];
    $message = 'Halo ' . $user['name'] . ', kamu diundang menjadi tentor di ELIPS LMS.' . "\n\n"
        . 'Silakan lengkapi data diri, unggah berkas, dan buat sandi lewat link berikut:' . "\n" . $link . "\n\n"
        . 'Link berlaku ' . ELIPS_ACTIVATION_TOKEN_DAYS . ' hari dan hanya bisa dipakai sekali.';

    elips_audit('update', 'user', $userId, (string) $user['name'], 'Membuat link aktivasi tentor.', $actor);
    return elips_ok('Link aktivasi dibuat.', [
        'link' => $link,
        'message' => $message,
        // Nomor tentor belum diketahui: WhatsApp dibuka tanpa tujuan, admin memilih kontaknya.
        'waUrl' => 'https://wa.me/?text=' . rawurlencode($message),
        'expiresAt' => elips_iso($token['expiresAt']),
    ]);
}

/** Admin: biodata + berkas tentor. */
function elips_action_tutor_profile(array $p): array
{
    elips_require_admin();
    elips_ensure_tutor_schema();
    $userId = (int) ($p['userId'] ?? 0);
    $user = elips_user_row($userId);
    if (!$user || $user['role'] !== 'instruktur') return elips_fail('Akun tentor tidak ditemukan.');
    $stmt = elips_db()->prepare('SELECT * FROM tutor_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return elips_ok('', [
        'user' => elips_serialize_user($user),
        'profile' => $row ? [
            'fullName' => $row['full_name'],
            'birthPlace' => $row['birth_place'],
            'birthDate' => $row['birth_date'] === null ? '' : elips_date_only($row['birth_date']),
            'address' => $row['address'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'completedAt' => elips_iso($row['completed_at']),
        ] : null,
        'documents' => elips_tutor_docs($userId),
    ]);
}

/** Kirim berkas (GET): admin, atau tentor pemilik. */
function elips_tutor_document_send(int $id): void
{
    $deny = static function (int $code): void {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $code === 404 ? 'Berkas tidak ditemukan.' : 'Tidak diizinkan.';
        exit;
    };
    $user = elips_session_user();
    if (!$user) $deny(403);
    elips_ensure_tutor_schema();
    $stmt = elips_db()->prepare('SELECT * FROM tutor_documents WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) $deny(404);
    if ($user['role'] !== 'admin' && (int) $user['id'] !== (int) $row['user_id']) $deny(403);

    $path = elips_tutor_doc_dir() . '/' . basename((string) $row['stored_name']);
    if (!is_file($path)) $deny(404);
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $row['original_name']) ?: 'berkas';
    header('Content-Type: ' . $row['mime']);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="' . $name . '"');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

// ---------- Publik (lewat link) ----------

function elips_action_tutor_activation_info(array $p): array
{
    elips_ensure_tutor_schema();
    if ($blocked = elips_tutor_rate_guard()) return $blocked;
    $ctx = elips_tutor_token_context((string) ($p['token'] ?? ''));
    if (!$ctx['user']) return elips_fail($ctx['error']);
    $user = $ctx['user'];

    $stmt = elips_db()->prepare('SELECT * FROM tutor_profiles WHERE user_id = ?');
    $stmt->execute([(int) $user['id']]);
    $row = $stmt->fetch();
    return elips_ok('', [
        'name' => (string) $user['name'],
        'prefill' => [
            'fullName' => $row ? $row['full_name'] : (string) $user['name'],
            'birthPlace' => $row ? $row['birth_place'] : '',
            'birthDate' => $row && $row['birth_date'] !== null ? elips_date_only($row['birth_date']) : '',
            'address' => $row ? $row['address'] : '',
            'phone' => $row ? $row['phone'] : '',
            'email' => $row ? $row['email'] : '',
        ],
        'documents' => elips_tutor_docs((int) $user['id']),
        'limits' => ['maxBytes' => ELIPS_TUTOR_DOC_MAX_BYTES, 'maxCertificates' => ELIPS_TUTOR_CERT_MAX],
        'expiresAt' => elips_iso($ctx['token']['expires_at']),
    ]);
}

function elips_tutor_remove_doc_row(array $row): void
{
    elips_db()->prepare('DELETE FROM tutor_documents WHERE id = ?')->execute([(int) $row['id']]);
    $path = elips_tutor_doc_dir() . '/' . basename((string) $row['stored_name']);
    if (is_file($path)) {
        @unlink($path);
    }
}

function elips_action_tutor_document_upload(array $p): array
{
    elips_ensure_tutor_schema();
    if ($blocked = elips_tutor_rate_guard()) return $blocked;
    $ctx = elips_tutor_token_context((string) ($p['token'] ?? ''));
    if (!$ctx['user']) return elips_fail($ctx['error']);
    $userId = (int) $ctx['user']['id'];

    $kind = (string) ($p['kind'] ?? '');
    if (!in_array($kind, ELIPS_TUTOR_DOC_KINDS, true)) return elips_fail('Jenis berkas tidak valid.');
    $label = trim((string) ($p['label'] ?? ''));
    if ($kind === 'sertifikat' && $label === '') return elips_fail('Isi nama sertifikat terlebih dahulu.');
    if (mb_strlen($label) > 150) return elips_fail('Nama sertifikat maksimal 150 karakter.');

    if (!preg_match('#^data:(application/pdf|image/jpeg|image/png);base64,([A-Za-z0-9+/=\s]+)$#', (string) ($p['dataUrl'] ?? ''), $m)) {
        return elips_fail('Berkas harus berupa PDF, JPG, atau PNG.');
    }
    $bin = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
    if ($bin === false || $bin === '') return elips_fail('Berkas tidak valid.');
    if (strlen($bin) > ELIPS_TUTOR_DOC_MAX_BYTES) return elips_fail('Ukuran berkas maksimal 3 MB.');

    // Isi berkas harus cocok dengan jenis yang diklaim.
    $mime = $m[1];
    if ($mime === 'application/pdf') {
        if (strncmp($bin, '%PDF-', 5) !== 0) return elips_fail('Berkas PDF tidak valid.');
        $ext = 'pdf';
    } else {
        $info = @getimagesizefromstring($bin);
        $type = $info[2] ?? 0;
        if ($type === IMAGETYPE_JPEG) { $mime = 'image/jpeg'; $ext = 'jpg'; }
        elseif ($type === IMAGETYPE_PNG) { $mime = 'image/png'; $ext = 'png'; }
        else return elips_fail('Berkas bukan gambar yang valid.');
    }

    $db = elips_db();
    if ($kind === 'sertifikat') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM tutor_documents WHERE user_id = ? AND kind = 'sertifikat'");
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() >= ELIPS_TUTOR_CERT_MAX) {
            return elips_fail('Maksimal ' . ELIPS_TUTOR_CERT_MAX . ' sertifikat.');
        }
    } else {
        // CV/KTP/ijazah: satu berkas; unggahan baru menggantikan yang lama.
        $stmt = $db->prepare('SELECT * FROM tutor_documents WHERE user_id = ? AND kind = ?');
        $stmt->execute([$userId, $kind]);
        foreach ($stmt->fetchAll() as $old) {
            elips_tutor_remove_doc_row($old);
        }
    }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (@file_put_contents(elips_tutor_doc_dir() . '/' . $stored, $bin) === false) {
        return elips_fail('Berkas gagal disimpan.');
    }
    $original = mb_substr(trim((string) ($p['fileName'] ?? '')), 0, 190);
    $db->prepare(
        'INSERT INTO tutor_documents (user_id, kind, label, original_name, stored_name, mime, size_bytes, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    )->execute([$userId, $kind, $kind === 'sertifikat' ? $label : '', $original, $stored, $mime, strlen($bin)]);

    return elips_ok('Berkas tersimpan.', ['documents' => elips_tutor_docs($userId)]);
}

function elips_action_tutor_document_remove(array $p): array
{
    elips_ensure_tutor_schema();
    if ($blocked = elips_tutor_rate_guard()) return $blocked;
    $ctx = elips_tutor_token_context((string) ($p['token'] ?? ''));
    if (!$ctx['user']) return elips_fail($ctx['error']);
    $userId = (int) $ctx['user']['id'];

    $stmt = elips_db()->prepare('SELECT * FROM tutor_documents WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) ($p['id'] ?? 0), $userId]);
    $row = $stmt->fetch();
    if (!$row) return elips_fail('Berkas tidak ditemukan.');
    elips_tutor_remove_doc_row($row);
    return elips_ok('Berkas dihapus.', ['documents' => elips_tutor_docs($userId)]);
}

/** Validasi biodata tentor: semua wajib. @return array{data?:array,error?:string} */
function elips_tutor_profile_validate(array $in): array
{
    $s = static function (string $key, int $max) use ($in): string {
        return mb_substr(trim((string) ($in[$key] ?? '')), 0, $max);
    };
    $d = [
        'full_name' => $s('fullName', 150),
        'birth_place' => $s('birthPlace', 100),
        'address' => $s('address', 500),
        'phone' => $s('phone', 40),
        'email' => strtolower($s('email', 190)),
    ];
    $birth = trim((string) ($in['birthDate'] ?? ''));
    foreach (['full_name' => 'Nama lengkap', 'birth_place' => 'Tempat lahir', 'address' => 'Alamat lengkap', 'phone' => 'No telepon/WhatsApp', 'email' => 'Email aktif'] as $k => $label) {
        if ($d[$k] === '') return ['error' => $label . ' wajib diisi.'];
    }
    if ($birth === '') return ['error' => 'Tanggal lahir wajib diisi.'];
    $date = elips_normalize_date($birth);
    if ($date === null || $date >= date('Y-m-d') || $date < '1900-01-01') return ['error' => 'Tanggal lahir tidak valid.'];
    $d['birth_date'] = $date;
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return ['error' => 'Email aktif tidak valid.'];
    if (elips_normalize_phone($d['phone']) === '' || strlen(preg_replace('/\D+/', '', $d['phone'])) < 9) {
        return ['error' => 'No telepon/WhatsApp tidak valid.'];
    }
    return ['data' => $d];
}

function elips_action_tutor_activation_complete(array $p): array
{
    elips_ensure_tutor_schema();
    if ($blocked = elips_tutor_rate_guard()) return $blocked;

    $validated = elips_tutor_profile_validate(is_array($p['profile'] ?? null) ? $p['profile'] : []);
    if (isset($validated['error'])) return elips_fail($validated['error']);
    $profile = $validated['data'];

    $password = (string) ($p['password'] ?? '');
    $confirm = (string) ($p['passwordConfirm'] ?? '');
    if ($error = elips_password_policy_error($password)) return elips_fail($error);
    if ($password !== $confirm) return elips_fail('Konfirmasi sandi tidak sama.');

    $db = elips_db();
    $db->beginTransaction();
    try {
        $ctx = elips_tutor_token_context((string) ($p['token'] ?? ''), true);
        if (!$ctx['user']) {
            $db->rollBack();
            return elips_fail($ctx['error']);
        }
        $userId = (int) $ctx['user']['id'];

        // Berkas wajib lengkap.
        $counts = ['cv' => 0, 'ktp' => 0, 'ijazah' => 0, 'sertifikat' => 0];
        foreach (elips_tutor_docs($userId) as $doc) {
            $counts[$doc['kind']]++;
        }
        foreach (['cv' => 'CV', 'ktp' => 'KTP', 'ijazah' => 'Ijazah terakhir'] as $kind => $label) {
            if ($counts[$kind] < 1) {
                $db->rollBack();
                return elips_fail('Berkas ' . $label . ' wajib diunggah.');
            }
        }
        if ($counts['sertifikat'] < 1) {
            $db->rollBack();
            return elips_fail('Minimal satu sertifikat pendukung wajib diunggah.');
        }

        $existing = elips_user_by_email($profile['email']);
        if ($existing && (int) $existing['id'] !== $userId) {
            $db->rollBack();
            return elips_fail('Email ini sudah dipakai akun lain.');
        }
        $phoneCheck = elips_user_phone_norm_check($profile['phone'], $userId);
        if ($phoneCheck['error'] !== '') {
            $db->rollBack();
            return elips_fail($phoneCheck['error']);
        }

        $db->prepare(
            "UPDATE users SET name = ?, email = ?, phone = ?, phone_norm = ?, password_hash = ?, status = 'aktif',
                    session_version = session_version + 1, updated_at = UTC_TIMESTAMP()
             WHERE id = ?"
        )->execute([$profile['full_name'], $profile['email'], $profile['phone'], $phoneCheck['norm'], password_hash($password, PASSWORD_DEFAULT), $userId]);

        $db->prepare(
            'INSERT INTO tutor_profiles (user_id, full_name, birth_place, birth_date, address, phone, email, completed_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), birth_place = VALUES(birth_place), birth_date = VALUES(birth_date),
                 address = VALUES(address), phone = VALUES(phone), email = VALUES(email), completed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()'
        )->execute([$userId, $profile['full_name'], $profile['birth_place'], $profile['birth_date'], $profile['address'], $profile['phone'], $profile['email']]);

        $db->prepare('UPDATE account_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int) $ctx['token']['id']]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    $fresh = elips_user_row($userId);
    elips_remember_revoke_all($userId, 'Akun diaktifkan.', $fresh, true, 'security');
    elips_audit('update', 'user', $userId, $profile['full_name'], 'Tentor mengaktifkan akun lewat link aktivasi (biodata, berkas, dan sandi dilengkapi).', $fresh);
    elips_session_apply($fresh);

    return elips_ok('Akun aktif. Selamat datang!', [
        'user' => elips_serialize_user($fresh),
        'redirect' => 'instructor/dashboard.php',
    ]);
}