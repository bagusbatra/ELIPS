<?php
// Direktori: api/seed-silabus.php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - buat kursus otomatis dari silabus
//
//   php api/seed-silabus.php                  buat semua kursus (Draft)
//   php api/seed-silabus.php --dry-run        hanya tampilkan rencana, tanpa menulis
//   php api/seed-silabus.php --only=excel     hanya kursus yang judulnya memuat "excel"
//
// Data silabus: api/seed-data/silabus-kursus.json (disusun dari PDF silabus).
// Tiap kursus dibuat sebagai Draft dengan: judul, kategori, deskripsi + tujuan
// pembelajaran, jumlah pertemuan sesuai silabus, satu modul "Rencana Pertemuan"
// berisi satu materi per pertemuan (sasaran, materi, latihan ada di isi materi).
// Harga, gambar, dan lokasi diisi admin di Kelola Kursus; tentor ditetapkan
// saat membuat angkatan. Idempoten: kursus dengan judul yang sama dilewati.
// ============================================================

require __DIR__ . '/db.php';
require __DIR__ . '/setup-guard.php';
elips_setup_guard('cli');
require_once __DIR__ . '/helpers.php';

$args = array_slice($argv ?? [], 1);
$dry = in_array('--dry-run', $args, true);
$only = '';
foreach ($args as $a) {
    if (strpos($a, '--only=') === 0) {
        $only = mb_strtolower(trim(substr($a, 7)));
    }
}

$file = __DIR__ . '/seed-data/silabus-kursus.json';
$data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
if (!is_array($data)) {
    fwrite(STDERR, "Berkas data silabus tidak ditemukan atau rusak: $file\n");
    exit(1);
}

function seed_list(array $items, bool $numbered): string
{
    $lines = [];
    foreach (array_values($items) as $i => $item) {
        $lines[] = ($numbered ? ($i + 1) . '. ' : '- ') . $item;
    }
    return implode("\n", $lines);
}

function seed_lesson_content(array $l): string
{
    $parts = [];
    if (!empty($l['objectives'])) {
        $parts[] = "Sasaran Pembelajaran:\n" . seed_list($l['objectives'], false);
    }
    if (!empty($l['materials'])) {
        $parts[] = "Materi Pembelajaran:\n" . seed_list($l['materials'], true);
    }
    if (!empty($l['practice'])) {
        $parts[] = "Latihan dan Praktik:\n" . seed_list($l['practice'], true);
    }
    return implode("\n\n", $parts);
}

$db = elips_db();
$created = 0;
$skipped = 0;
$totalLessons = 0;

foreach ($data as $c) {
    $title = (string) $c['title'];
    if ($only !== '' && mb_strpos(mb_strtolower($title), $only) === false) {
        continue;
    }
    $exists = $db->prepare('SELECT id FROM courses WHERE LOWER(title) = LOWER(?) LIMIT 1');
    $exists->execute([$title]);
    $lessons = $c['lessons'];
    $meetings = (int) ($c['meetings'] ?? count($lessons));
    if ($exists->fetchColumn() !== false) {
        printf("  lewati   %-34s sudah ada\n", $title);
        $skipped++;
        continue;
    }
    printf("  %s %-34s %2d pertemuan  [%s]\n", $dry ? 'rencana ' : 'buat    ', $title, $meetings, $c['category']);
    if ($dry) {
        $created++;
        $totalLessons += count($lessons);
        continue;
    }

    $description = trim((string) $c['description']);
    if (!empty($c['goals'])) {
        $description .= "\n\nTujuan pembelajaran:\n" . seed_list($c['goals'], true);
    }

    $db->beginTransaction();
    try {
        $categoryId = elips_resolve_category_id(null, (string) $c['category']);
        $db->prepare(
            "INSERT INTO courses (title, category, category_id, description, instructor_id, status, price, discount_price, location, start_date, meetings, created_at)
             VALUES (?, ?, ?, ?, NULL, 'draft', 0, 0, '', NULL, ?, UTC_TIMESTAMP())"
        )->execute([$title, $c['category'], $categoryId, $description, $meetings]);
        $courseId = (int) $db->lastInsertId();
        $version = elips_ensure_curriculum_version($courseId, null);

        $db->prepare(
            'INSERT INTO modules (course_id, curriculum_version_id, instructor_id, title, description, sort_order, created_at)
             VALUES (?, ?, NULL, ?, "", 1, UTC_TIMESTAMP())'
        )->execute([$courseId, (int) $version['id'], 'Rencana Pertemuan']);
        $moduleId = (int) $db->lastInsertId();

        $insLesson = $db->prepare(
            "INSERT INTO lessons (module_id, course_id, curriculum_version_id, instructor_id, title, type, content, sort_order, created_at)
             VALUES (?, ?, ?, NULL, ?, 'text', ?, ?, UTC_TIMESTAMP())"
        );
        foreach ($lessons as $i => $l) {
            $insLesson->execute([
                $moduleId, $courseId, (int) $version['id'],
                mb_substr('Pertemuan ' . ($i + 1) . ': ' . $l['title'], 0, 200),
                seed_lesson_content($l), $i + 1,
            ]);
        }
        $db->commit();
        $created++;
        $totalLessons += count($lessons);
    } catch (Throwable $e) {
        $db->rollBack();
        fwrite(STDERR, "  GAGAL    $title: " . $e->getMessage() . "\n");
        exit(1);
    }
}

printf(
    "\nSelesai%s: %d kursus %s (%d materi), %d dilewati.\n",
    $dry ? ' (dry-run)' : '', $created, $dry ? 'akan dibuat' : 'dibuat', $totalLessons, $skipped
);
if (!$dry && $created > 0) {
    echo "Semua kursus berstatus Draft. Isi harga di Kelola Kursus, lalu publikasikan.\n";
}