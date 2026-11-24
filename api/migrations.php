<?php
// Direktori: api/migrations.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - migrasi skema alur pendaftaran & aktivasi akun
// Aman dijalankan berulang (idempoten). Dipanggil oleh api/install.php
// dan otomatis oleh modul pendaftaran pada database lama.
// ============================================================

function elips_migrate_has_column(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function elips_migrate_has_index(PDO $db, string $table, string $index): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function elips_migrate_has_constraint(PDO $db, string $table, string $constraint): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
    );
    $stmt->execute([$table, $constraint]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Tahap 1:
 *  - users.status menerima 'menunggu' (akun dibuat admin, belum membuat sandi)
 *  - course_registrations memuat kelas pilihan, nominal tagihan, nomor WA
 *    ternormalisasi, waktu link aktivasi dikirim, dan enrollment hasil aktivasi.
 */
function elips_migrate_activation_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $db = elips_db();
    if (elips_migrate_is_current($db)) {
        $done = true;
        return;
    }

    // courses.status: tambah 'archived'.
    $courseCol = $db->query("SHOW COLUMNS FROM courses LIKE 'status'")->fetch();
    if ($courseCol && strpos((string) $courseCol['Type'], "'archived'") === false) {
        $db->exec("ALTER TABLE courses MODIFY status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft'");
    }

    // users.status: tambah 'menunggu'.
    $col = $db->query("SHOW COLUMNS FROM users LIKE 'status'")->fetch();
    if ($col && strpos((string) $col['Type'], "'menunggu'") === false) {
        $db->exec("ALTER TABLE users MODIFY status ENUM('aktif', 'nonaktif', 'menunggu') NOT NULL DEFAULT 'aktif'");
    }

    // Tabel dasar (database yang dibuat sebelum tabel ini ada).
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `course_registrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id` INT UNSIGNED NOT NULL,
    `course_title` VARCHAR(200) NOT NULL DEFAULT '',
    `name` VARCHAR(150) NOT NULL,
    `birth_place` VARCHAR(100) NOT NULL DEFAULT '',
    `birth_date` DATE NULL,
    `address` VARCHAR(500) NOT NULL DEFAULT '',
    `phone` VARCHAR(40) NOT NULL DEFAULT '',
    `email` VARCHAR(190) NOT NULL,
    `status` ENUM('baru', 'dihubungi', 'menunggu', 'terdaftar', 'batal') NOT NULL DEFAULT 'baru',
    `student_id` INT UNSIGNED NULL,
    `converted_at` DATETIME NULL,
    `notes` VARCHAR(1000) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_course_reg_course` (`course_id`),
    KEY `idx_course_reg_email` (`email`),
    KEY `idx_course_reg_status` (`status`),
    CONSTRAINT `fk_course_reg_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_course_reg_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $status = $db->query("SHOW COLUMNS FROM course_registrations LIKE 'status'")->fetch();
    if ($status && strpos((string) $status['Type'], "'menunggu'") === false) {
        $db->exec(
            "ALTER TABLE course_registrations
             MODIFY status ENUM('baru', 'dihubungi', 'menunggu', 'terdaftar', 'batal') NOT NULL DEFAULT 'baru'"
        );
    }

    $columns = [
        'class_id' => 'ALTER TABLE course_registrations ADD COLUMN class_id INT UNSIGNED NULL AFTER course_title',
        'class_name' => "ALTER TABLE course_registrations ADD COLUMN class_name VARCHAR(150) NOT NULL DEFAULT '' AFTER class_id",
        'amount_due' => 'ALTER TABLE course_registrations ADD COLUMN amount_due DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER class_name',
        'phone_norm' => "ALTER TABLE course_registrations ADD COLUMN phone_norm VARCHAR(20) NOT NULL DEFAULT '' AFTER phone",
        'activation_sent_at' => 'ALTER TABLE course_registrations ADD COLUMN activation_sent_at DATETIME NULL AFTER converted_at',
        'enrollment_id' => 'ALTER TABLE course_registrations ADD COLUMN enrollment_id INT UNSIGNED NULL AFTER student_id',
    ];
    foreach ($columns as $name => $sql) {
        if (!elips_migrate_has_column($db, 'course_registrations', $name)) {
            $db->exec($sql);
        }
    }
    if (!elips_migrate_has_index($db, 'course_registrations', 'idx_course_reg_class')) {
        $db->exec('ALTER TABLE course_registrations ADD KEY idx_course_reg_class (class_id, status)');
    }
    if (!elips_migrate_has_index($db, 'course_registrations', 'idx_course_reg_phone')) {
        $db->exec('ALTER TABLE course_registrations ADD KEY idx_course_reg_phone (phone_norm)');
    }
    if (!elips_migrate_has_constraint($db, 'course_registrations', 'fk_course_reg_class')) {
        $db->exec(
            'ALTER TABLE course_registrations
             ADD CONSTRAINT fk_course_reg_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL'
        );
    }
    if (!elips_migrate_has_constraint($db, 'course_registrations', 'fk_course_reg_enrollment')) {
        $db->exec(
            'ALTER TABLE course_registrations
             ADD CONSTRAINT fk_course_reg_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments (id) ON DELETE SET NULL'
        );
    }

    // Isi nomor ternormalisasi untuk data lama.
    $rows = $db->query("SELECT id, phone FROM course_registrations WHERE phone_norm = '' AND phone <> ''")->fetchAll();
    if ($rows) {
        $upd = $db->prepare('UPDATE course_registrations SET phone_norm = ? WHERE id = ?');
        foreach ($rows as $row) {
            $upd->execute([elips_normalize_phone((string) $row['phone']), (int) $row['id']]);
        }
    }

    // users.phone_norm: kunci login lewat nomor telepon (unik).
    if (!elips_migrate_has_column($db, 'users', 'phone_norm')) {
        $db->exec('ALTER TABLE users ADD COLUMN phone_norm VARCHAR(20) NULL AFTER phone');
    }
    if (!elips_migrate_has_index($db, 'users', 'uq_users_phone_norm')) {
        // Nomor ganda pada data lama: yang pertama (id terkecil) memegang nomor, sisanya dikosongkan.
        $used = [];
        $users = $db->query("SELECT id, phone FROM users WHERE phone <> '' ORDER BY id ASC")->fetchAll();
        $setNorm = $db->prepare('UPDATE users SET phone_norm = ? WHERE id = ?');
        foreach ($users as $u) {
            $norm = elips_normalize_phone((string) $u['phone']);
            if ($norm !== '' && !isset($used[$norm])) {
                $used[$norm] = true;
                $setNorm->execute([$norm, (int) $u['id']]);
            } else {
                $setNorm->execute([null, (int) $u['id']]);
            }
        }
        $db->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_phone_norm (phone_norm)');
    }

    elips_migrate_billing_tables($db);

    $done = true;
}

/** Isi users.phone_norm untuk akun yang belum punya (nomor ganda: yang pertama menang). */
function elips_backfill_phone_norm(PDO $db): int
{
    $taken = [];
    foreach ($db->query('SELECT phone_norm FROM users WHERE phone_norm IS NOT NULL')->fetchAll() as $r) {
        $taken[(string) $r['phone_norm']] = true;
    }
    $set = $db->prepare('UPDATE users SET phone_norm = ? WHERE id = ?');
    $n = 0;
    foreach ($db->query("SELECT id, phone FROM users WHERE phone_norm IS NULL AND phone <> '' ORDER BY id ASC")->fetchAll() as $u) {
        $norm = elips_normalize_phone((string) $u['phone']);
        if ($norm !== '' && !isset($taken[$norm])) {
            $taken[$norm] = true;
            $set->execute([$norm, (int) $u['id']]);
            $n++;
        }
    }
    return $n;
}

/** true bila seluruh skema aktivasi/pembayaran sudah ada (3 query ringan per proses). */
function elips_migrate_is_current(PDO $db): bool
{
    $tables = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME IN ('course_registrations','account_tokens','student_profiles','password_reset_requests',
                            'invoices','payments','counters','app_settings')"
    )->fetchColumn();
    if ($tables !== 8) {
        return false;
    }
    $cols = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND ((TABLE_NAME = 'users' AND COLUMN_NAME = 'phone_norm')
           OR (TABLE_NAME = 'course_registrations' AND COLUMN_NAME = 'enrollment_id'))"
    )->fetchColumn();
    if ($cols !== 2) {
        return false;
    }
    $type = (string) $db->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status'"
    )->fetchColumn();
    $courseType = (string) $db->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'status'"
    )->fetchColumn();
    return strpos($type, "'menunggu'") !== false && strpos($courseType, "'archived'") !== false;
}

/**
 * Tahap 2-4: token aktivasi/reset, biodata siswa, permintaan reset sandi,
 * tagihan, pembayaran, penghitung nomor siswa, dan pengaturan aplikasi.
 */
function elips_migrate_billing_tables(PDO $db): void
{
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `account_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `purpose` ENUM('activation', 'reset') NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `registration_id` INT UNSIGNED NULL,
    `reset_request_id` INT UNSIGNED NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `revoked_at` DATETIME NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_account_tokens_hash` (`token_hash`),
    KEY `idx_account_tokens_user` (`user_id`, `purpose`),
    CONSTRAINT `fk_account_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_account_tokens_registration` FOREIGN KEY (`registration_id`) REFERENCES `course_registrations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `student_profiles` (
    `user_id` INT UNSIGNED NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(500) NOT NULL DEFAULT '',
    `phone` VARCHAR(40) NOT NULL DEFAULT '',
    `birth_place` VARCHAR(100) NOT NULL DEFAULT '',
    `birth_date` DATE NULL,
    `religion` VARCHAR(30) NOT NULL DEFAULT '',
    `education_level` VARCHAR(20) NOT NULL DEFAULT '',
    `education_institution` VARCHAR(150) NOT NULL DEFAULT '',
    `education_major` VARCHAR(150) NOT NULL DEFAULT '',
    `education_year` SMALLINT UNSIGNED NULL,
    `occupation` VARCHAR(60) NOT NULL DEFAULT '',
    `occupation_other` VARCHAR(100) NOT NULL DEFAULT '',
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_student_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `password_reset_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `status` ENUM('pending', 'sent', 'done', 'cancelled') NOT NULL DEFAULT 'pending',
    `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `requested_ip` VARCHAR(45) NOT NULL DEFAULT '',
    `handled_by` INT UNSIGNED NULL,
    `handled_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_pw_reset_status` (`status`),
    KEY `idx_pw_reset_user` (`user_id`, `status`),
    CONSTRAINT `fk_pw_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `registration_id` INT UNSIGNED NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `enrollment_id` INT UNSIGNED NULL,
    `course_id` INT UNSIGNED NOT NULL,
    `class_id` INT UNSIGNED NULL,
    `course_title` VARCHAR(200) NOT NULL DEFAULT '',
    `class_name` VARCHAR(150) NOT NULL DEFAULT '',
    `amount_due` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
    `student_number` VARCHAR(20) NULL,
    `number_seq` INT UNSIGNED NULL,
    `paid_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoices_registration` (`registration_id`),
    UNIQUE KEY `uq_invoices_enrollment` (`enrollment_id`),
    UNIQUE KEY `uq_invoices_student_number` (`student_number`),
    UNIQUE KEY `uq_invoices_number_seq` (`number_seq`),
    KEY `idx_invoices_student` (`student_id`),
    KEY `idx_invoices_status` (`status`),
    KEY `idx_invoices_course` (`course_id`),
    CONSTRAINT `fk_invoices_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_invoices_registration` FOREIGN KEY (`registration_id`) REFERENCES `course_registrations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invoices_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `method` VARCHAR(40) NOT NULL DEFAULT 'transfer',
    `paid_date` DATE NULL,
    `note` VARCHAR(500) NOT NULL DEFAULT '',
    `proof_file` VARCHAR(190) NULL,
    `proof_mime` VARCHAR(60) NOT NULL DEFAULT '',
    `status` ENUM('pending', 'verified', 'rejected', 'void') NOT NULL DEFAULT 'pending',
    `source` ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    `submitted_by` INT UNSIGNED NULL,
    `verified_by` INT UNSIGNED NULL,
    `verified_at` DATETIME NULL,
    `reject_reason` VARCHAR(500) NOT NULL DEFAULT '',
    `void_reason` VARCHAR(500) NOT NULL DEFAULT '',
    `voided_by` INT UNSIGNED NULL,
    `voided_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_payments_invoice` (`invoice_id`, `status`),
    KEY `idx_payments_status` (`status`),
    CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `counters` (
    `name` VARCHAR(40) NOT NULL,
    `value` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    $db->exec("INSERT IGNORE INTO counters (name, value) VALUES ('student_number', 0)");

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_settings` (
    `name` VARCHAR(60) NOT NULL,
    `value` TEXT NOT NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

/**
 * Normalisasi nomor telepon Indonesia ke format 62xxxxxxxxxx.
 * 0812-3456-7890, +62 812 3456 7890, atau 812... menjadi 6281234567890. Kosong bila tidak valid.
 */
function elips_normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return '';
    }
    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }
    if (strpos($digits, '62') === 0) {
        // sudah berformat internasional; buang 0 sisipan: 620812... -> 62812...
        $rest = ltrim(substr($digits, 2), '0');
        $digits = '62' . $rest;
    } elseif (strpos($digits, '0') === 0) {
        $digits = '62' . substr($digits, 1);
    } elseif (strpos($digits, '8') === 0) {
        $digits = '62' . $digits;
    } else {
        return '';
    }
    $len = strlen($digits);
    return ($len >= 10 && $len <= 15) ? $digits : '';
}