<?php
// Direktori: api/academic.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - pengelolaan kursus oleh admin & Operasional
//
// - Akses: admin dan karyawan divisi Operasional.
// - Kursus: harga per kursus, lokasi, tanggal mulai, jumlah
//   pertemuan, gambar, dan status draft -> published -> archived.
// - Kursus yang pernah dipakai tidak dihapus, hanya diarsipkan.
// ============================================================

/** Guard: admin atau karyawan divisi Operasional. */
function elips_require_academic_access(): array
{
    $user = elips_require_login();
    if ($user['role'] === 'admin') {
        return $user;
    }
    if ($user['role'] !== 'karyawan' || ($user['division'] ?? '') !== 'operasional') {
        throw new ElipsError('Anda tidak berhak mengelola kursus dan kurikulum.');
    }
    return $user;
}

/** Pastikan courses.status memuat 'archived' (database lama). */
function elips_migrate_course_archive(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $type = (string) $db->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'status'"
    )->fetchColumn();
    if ($type !== '' && strpos($type, "'archived'") === false) {
        $db->exec("ALTER TABLE courses MODIFY status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft'");
    }
    $done = true;
}

/**
 * Validasi & normalisasi field tambahan kursus.
 * @return array{0: ?array, 1: ?string} [nilai, pesan galat]
 */
function elips_course_extra_fields(array $src, ?array $current): array
{
    $pick = static function (string $key, $fallback) use ($src) {
        return array_key_exists($key, $src) ? $src[$key] : $fallback;
    };

    $price = elips_payment_parse_amount($pick('price', (float) ($current['price'] ?? 0)));
    $discount = elips_payment_parse_amount($pick('discountPrice', (float) ($current['discount_price'] ?? 0)));
    if ($price < 0 || $discount < 0) {
        return [null, 'Harga tidak boleh negatif.'];
    }
    if ($price > 999999999) {
        return [null, 'Harga terlalu besar.'];
    }
    if ($discount > 0 && $discount >= $price) {
        return [null, 'Harga diskon harus lebih kecil dari harga normal.'];
    }

    $location = trim((string) $pick('location', $current['location'] ?? ''));
    if (mb_strlen($location) > 150) {
        return [null, 'Lokasi maksimal 150 karakter.'];
    }

    $startRaw = $pick('startDate', $current['start_date'] ?? null);
    $startDate = null;
    if (trim((string) $startRaw) !== '') {
        $startDate = elips_normalize_date($startRaw);
        if ($startDate === null) {
            return [null, 'Tanggal mulai tidak valid.'];
        }
    }

    $meetings = (int) $pick('meetings', $current['meetings'] ?? 0);
    if ($meetings < 0 || $meetings > 999) {
        return [null, 'Jumlah pertemuan harus antara 0 dan 999.'];
    }

    return [[
        'price' => $price,
        'discount_price' => $discount,
        'location' => $location,
        'start_date' => $startDate,
        'meetings' => $meetings,
    ], null];
}

/** Berapa banyak data penting yang menempel pada kursus (untuk aturan hapus/arsip). */
function elips_course_usage(int $courseId): array
{
    $db = elips_db();
    $count = static function (string $sql) use ($db, $courseId): int {
        $stmt = $db->prepare($sql);
        $stmt->execute([$courseId]);
        return (int) $stmt->fetchColumn();
    };
    return [
        'classes' => $count('SELECT COUNT(*) FROM classes WHERE course_id = ?'),
        'enrollments' => $count('SELECT COUNT(*) FROM enrollments WHERE course_id = ?'),
        'certificates' => $count('SELECT COUNT(*) FROM certificates WHERE course_id = ?'),
        'registrations' => $count('SELECT COUNT(*) FROM course_registrations WHERE course_id = ?'),
    ];
}

/** Simpan gambar kursus (data URL JPG/PNG/WebP, maks 2 MB) ke assets/uploads/courses. */
function elips_course_store_image(string $dataUrl): string
{
    if (!preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
        throw new ElipsError('Gambar harus berupa JPG, PNG, atau WebP.');
    }
    $bin = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
    if ($bin === false || $bin === '') {
        throw new ElipsError('Berkas gambar tidak valid.');
    }
    if (strlen($bin) > 2 * 1024 * 1024) {
        throw new ElipsError('Ukuran gambar maksimal 2 MB.');
    }
    $info = @getimagesizefromstring($bin);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$info || $ext === null) {
        throw new ElipsError('Berkas bukan gambar yang valid.');
    }

    $dir = dirname(__DIR__) . '/assets/uploads/courses';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new ElipsError('Folder gambar kursus tidak dapat dibuat.');
    }
    // Hanya gambar yang boleh disajikan dari folder ini.
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "php_flag engine off\n<FilesMatch \"\\.(?i:php|phtml|phar)$\">\nRequire all denied\n</FilesMatch>\n");
    }
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (@file_put_contents($dir . '/' . $name, $bin) === false) {
        throw new ElipsError('Gambar kursus gagal disimpan.');
    }
    return 'assets/uploads/courses/' . $name;
}

function elips_course_remove_image_file(string $path): void
{
    if (strpos($path, 'assets/uploads/courses/') !== 0) {
        return; // hanya hapus berkas yang kita simpan sendiri
    }
    $full = dirname(__DIR__) . '/' . $path;
    if (basename($full) !== '' && is_file($full)) {
        @unlink($full);
    }
}

function elips_action_course_image(array $p): array
{
    $actor = elips_require_academic_access();
    elips_migrate_course_archive(elips_db());
    $id = (int) ($p['id'] ?? 0);
    $course = elips_course_row($id);
    if (!$course) return elips_fail('Kursus tidak ditemukan.');

    $old = (string) ($course['image'] ?? '');
    if (!empty($p['remove'])) {
        $new = '';
    } else {
        $new = elips_course_store_image((string) ($p['dataUrl'] ?? ''));
    }

    $stmt = elips_db()->prepare('UPDATE courses SET image = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
    $stmt->execute([$new, $id]);
    if ($old !== '' && $old !== $new) {
        elips_course_remove_image_file($old);
    }

    elips_audit('update', 'course', $id, $course['title'],
        $new === '' ? 'Menghapus gambar kursus "' . $course['title'] . '".' : 'Mengganti gambar kursus "' . $course['title'] . '".',
        $actor);

    return elips_ok($new === '' ? 'Gambar kursus dihapus.' : 'Gambar kursus diperbarui.', elips_course_public($id));
}