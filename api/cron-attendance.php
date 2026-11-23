<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - rekap absensi otomatis (CLI / cron)
//   php api/cron-attendance.php                 -> bulan berjalan
//   php api/cron-attendance.php --month=2026-10
//   php api/cron-attendance.php --from=2026-11-01 --to=2026-11-15
// Hasil: ringkasan di layar + CSV di storage/rekap-absensi-<from>_<to>.csv
// Jadwalkan harian, mis. cron "55 23 * * * php /path/api/cron-attendance.php"
// atau Task Scheduler Windows (Laragon).
// ============================================================

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/actions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

$opts = [];
foreach (array_slice($_SERVER['argv'] ?? [], 1) as $arg) {
    if (preg_match('/^--(\w+)=(.*)$/', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    }
}
$tz = new DateTimeZone(elips_attendance_timezone());
$now = new DateTimeImmutable('now', $tz);
if (!empty($opts['month']) && preg_match('/^\d{4}-\d{2}$/', $opts['month'])) {
    $first = new DateTimeImmutable($opts['month'] . '-01', $tz);
    $from = $first->format('Y-m-d');
    $to = $first->modify('last day of this month')->format('Y-m-d');
} else {
    $from = elips_normalize_date($opts['from'] ?? '') ?? $now->modify('first day of this month')->format('Y-m-d');
    $to = elips_normalize_date($opts['to'] ?? '') ?? $now->format('Y-m-d');
}

$rows = elips_staff_attendance_summary_data($from, $to);
$dir = dirname(__DIR__) . '/storage';
if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}
$file = $dir . '/rekap-absensi-' . $from . '_' . $to . '.csv';
$fh = fopen($file, 'wb');
fputcsv($fh, ['Nama', 'Email', 'Role', 'Divisi', 'Hari hadir', 'Terlambat (kali)', 'Terlambat (menit)',
    'Pulang cepat', 'Lupa absen pulang', 'Alpa', 'Total jam kerja']);
foreach ($rows as $r) {
    fputcsv($fh, [$r['name'], $r['email'], $r['role'], $r['division'], $r['presentDays'], $r['lateCount'],
        $r['lateMinutes'], $r['earlyLeaveCount'], $r['missingCheckout'], $r['absentDays'],
        round($r['workMinutes'] / 60, 2)]);
}
fclose($fh);

echo "Rekap absensi $from s/d $to\n";
foreach ($rows as $r) {
    printf("- %-24s hadir %2d | telat %2d (%d mnt) | alpa %2d | lupa pulang %2d | %.1f jam\n",
        $r['name'], $r['presentDays'], $r['lateCount'], $r['lateMinutes'], $r['absentDays'],
        $r['missingCheckout'], $r['workMinutes'] / 60);
}
echo "CSV: $file\n";
