<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - aturan absensi karyawan di server
// Validasi lokasi (geofence + akurasi GPS), validasi foto, penentuan
// status (tepat waktu / terlambat / pulang cepat) dan rekap otomatis.
// Seluruh keputusan dibuat di server; nilai dari browser hanya masukan.
// ============================================================

/** Kebijakan absensi (gabungan default + config.php['attendance']). */
function elips_attendance_cfg(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $defaults = [
            'geofence_enforce' => false,   // true = tolak absensi di luar radius
            'geofence' => [],              // [['name'=>'Kantor','lat'=>-7.45,'lng'=>112.7,'radius_m'=>150], ...]
            'max_accuracy_m' => 150,       // GPS lebih kasar dari ini ditolak
            'photo_min_px' => 200,         // sisi terpendek foto
            'photo_max_px' => 6000,
            'reject_reused_photo' => true, // foto identik milik pengguna yang sama ditolak
            'default_start' => '08:00',
            'default_end' => '16:00',
            'late_grace_minutes' => 10,
            'early_grace_minutes' => 10,
        ];
        $custom = elips_config()['attendance'] ?? [];
        $cfg = array_replace($defaults, is_array($custom) ? $custom : []);
    }
    return $cfg;
}

/** Jarak dua koordinat (meter), rumus haversine. */
function elips_haversine_m(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000.0;
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lng2 - $lng1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

/**
 * Cek lokasi terhadap titik kantor. Mengembalikan:
 *  ['ok'=>bool,'name'=>string,'distance'=>?float,'message'=>string]
 * Tanpa titik terdaftar, lokasi diterima (name kosong, distance null).
 */
function elips_geofence_check(float $lat, float $lng, ?float $accuracy): array
{
    $cfg = elips_attendance_cfg();
    $max = (float) $cfg['max_accuracy_m'];
    if ($accuracy !== null && $max > 0 && $accuracy > $max) {
        return ['ok' => false, 'name' => '', 'distance' => null,
            'message' => 'Akurasi GPS terlalu rendah (' . round($accuracy) . ' m, maksimal ' . round($max)
                . ' m). Pindah ke area terbuka lalu coba lagi.'];
    }
    $zones = is_array($cfg['geofence']) ? $cfg['geofence'] : [];
    if (!$zones) {
        return ['ok' => true, 'name' => '', 'distance' => null, 'message' => ''];
    }
    $best = null;
    foreach ($zones as $zone) {
        $d = elips_haversine_m($lat, $lng, (float) $zone['lat'], (float) $zone['lng']);
        if ($best === null || $d < $best['distance']) {
            $best = ['name' => (string) ($zone['name'] ?? 'Lokasi kerja'), 'distance' => $d,
                'radius' => (float) ($zone['radius_m'] ?? 100)];
        }
    }
    // Akurasi buruk diberi kelonggaran: jarak dihitung dikurangi akurasi.
    $effective = max(0.0, $best['distance'] - (float) ($accuracy ?? 0));
    if ($effective > $best['radius']) {
        $msg = 'Anda berada ' . round($best['distance']) . ' m dari ' . $best['name']
            . ' (maksimal ' . round($best['radius']) . ' m).';
        return ['ok' => !$cfg['geofence_enforce'], 'name' => $best['name'],
            'distance' => round($best['distance'], 2), 'message' => $msg, 'outside' => true];
    }
    return ['ok' => true, 'name' => $best['name'], 'distance' => round($best['distance'], 2), 'message' => ''];
}

/** Validasi dimensi foto. Mengembalikan pesan galat atau null. */
function elips_photo_dimension_error(array $imageInfo): ?string
{
    $cfg = elips_attendance_cfg();
    $w = (int) ($imageInfo[0] ?? 0);
    $h = (int) ($imageInfo[1] ?? 0);
    if ($w <= 0 || $h <= 0) {
        return 'Dimensi foto absensi tidak terbaca.';
    }
    if (min($w, $h) < (int) $cfg['photo_min_px']) {
        return 'Foto absensi terlalu kecil. Minimal ' . $cfg['photo_min_px'] . ' piksel pada sisi terpendek.';
    }
    if (max($w, $h) > (int) $cfg['photo_max_px']) {
        return 'Dimensi foto absensi terlalu besar.';
    }
    return null;
}

/** Jam terjadwal (HH:MM:SS) untuk pengguna/tanggal: shift bila ada, kalau tidak default. */
function elips_attendance_schedule(int $userId, string $date): array
{
    $cfg = elips_attendance_cfg();
    $stmt = elips_db()->prepare('SELECT start_time, end_time FROM staff_shifts WHERE user_id = ? AND shift_date = ? LIMIT 1');
    $stmt->execute([$userId, $date]);
    $shift = $stmt->fetch();
    if ($shift) {
        return ['start' => substr((string) $shift['start_time'], 0, 5), 'end' => substr((string) $shift['end_time'], 0, 5), 'shift' => true];
    }
    return ['start' => (string) $cfg['default_start'], 'end' => (string) $cfg['default_end'], 'shift' => false];
}

/**
 * Status absensi: ['status'=>'on_time|late|early_leave','minutes'=>int].
 * $taken berada pada zona waktu absensi.
 */
function elips_attendance_status(string $kind, DateTimeImmutable $taken, int $userId): array
{
    $cfg = elips_attendance_cfg();
    $date = $taken->format('Y-m-d');
    $sched = elips_attendance_schedule($userId, $date);
    $tz = $taken->getTimezone();
    $start = new DateTimeImmutable($date . ' ' . $sched['start'], $tz);
    $end = new DateTimeImmutable($date . ' ' . $sched['end'], $tz);
    if ($end <= $start) {
        $end = $end->modify('+1 day'); // shift melewati tengah malam
    }
    if ($kind === 'in') {
        $late = (int) floor(($taken->getTimestamp() - $start->getTimestamp()) / 60) - (int) $cfg['late_grace_minutes'];
        return $late > 0 ? ['status' => 'late', 'minutes' => $late] : ['status' => 'on_time', 'minutes' => 0];
    }
    $early = (int) floor(($end->getTimestamp() - $taken->getTimestamp()) / 60) - (int) $cfg['early_grace_minutes'];
    return $early > 0 ? ['status' => 'early_leave', 'minutes' => $early] : ['status' => 'on_time', 'minutes' => 0];
}

/**
 * Rekap otomatis per karyawan pada rentang tanggal.
 * Hari tanpa absen masuk dihitung "alpa" hanya bila ada shift terjadwal
 * pada hari itu (dan harinya sudah lewat/hari ini sudah melewati jam mulai).
 */
function elips_staff_attendance_summary_data(string $from, string $to, int $userId = 0): array
{
    $db = elips_db();
    $tz = new DateTimeZone(elips_attendance_timezone());
    $now = new DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');

    $userWhere = "u.role IN ('admin','karyawan') AND u.status = 'aktif'";
    $args = [];
    if ($userId > 0) {
        $userWhere .= ' AND u.id = ?';
        $args[] = $userId;
    }
    $users = [];
    $stmt = $db->prepare("SELECT u.id, u.name, u.email, u.role, u.division FROM users u WHERE $userWhere ORDER BY u.name");
    $stmt->execute($args);
    foreach ($stmt->fetchAll() as $u) {
        $users[(int) $u['id']] = [
            'userId' => (int) $u['id'], 'name' => $u['name'], 'email' => $u['email'],
            'role' => $u['role'], 'division' => $u['division'],
            'presentDays' => 0, 'lateCount' => 0, 'lateMinutes' => 0, 'earlyLeaveCount' => 0,
            'missingCheckout' => 0, 'absentDays' => 0, 'workMinutes' => 0, 'days' => [],
        ];
    }

    $att = $db->prepare(
        'SELECT user_id, kind, attendance_date, taken_at, status, late_minutes
         FROM staff_attendances WHERE attendance_date BETWEEN ? AND ? ORDER BY taken_at'
    );
    $att->execute([$from, $to]);
    $byDay = [];
    foreach ($att->fetchAll() as $r) {
        $uid = (int) $r['user_id'];
        if (!isset($users[$uid])) {
            continue;
        }
        $byDay[$uid][$r['attendance_date']][$r['kind']] = $r;
    }
    $shifts = $db->prepare('SELECT user_id, shift_date, start_time FROM staff_shifts WHERE shift_date BETWEEN ? AND ?');
    $shifts->execute([$from, $to]);
    $shiftDays = [];
    foreach ($shifts->fetchAll() as $s) {
        $shiftDays[(int) $s['user_id']][$s['shift_date']] = substr((string) $s['start_time'], 0, 5);
    }

    foreach ($users as $uid => &$row) {
        $days = array_unique(array_merge(array_keys($byDay[$uid] ?? []), array_keys($shiftDays[$uid] ?? [])));
        sort($days);
        foreach ($days as $day) {
            $in = $byDay[$uid][$day]['in'] ?? null;
            $out = $byDay[$uid][$day]['out'] ?? null;
            $entry = ['date' => $day, 'in' => null, 'out' => null, 'status' => '', 'workMinutes' => 0];
            if ($in) {
                $row['presentDays']++;
                $entry['in'] = elips_iso($in['taken_at']);
                if (($in['status'] ?? '') === 'late') {
                    $row['lateCount']++;
                    $row['lateMinutes'] += (int) $in['late_minutes'];
                    $entry['status'] = 'late';
                } else {
                    $entry['status'] = 'on_time';
                }
                if ($out) {
                    $entry['out'] = elips_iso($out['taken_at']);
                    $mins = (int) floor((strtotime($out['taken_at'] . ' UTC') - strtotime($in['taken_at'] . ' UTC')) / 60);
                    $entry['workMinutes'] = max(0, $mins);
                    $row['workMinutes'] += $entry['workMinutes'];
                    if (($out['status'] ?? '') === 'early_leave') {
                        $row['earlyLeaveCount']++;
                    }
                } elseif ($day < $today) {
                    $row['missingCheckout']++;
                    $entry['status'] .= ',missing_checkout';
                }
            } elseif (isset($shiftDays[$uid][$day])) {
                $started = $day < $today
                    || ($day === $today && $now >= new DateTimeImmutable($day . ' ' . $shiftDays[$uid][$day], $tz));
                if ($started) {
                    $row['absentDays']++;
                    $entry['status'] = 'absent';
                } else {
                    continue;
                }
            }
            $row['days'][] = $entry;
        }
    }
    unset($row);

    return array_values($users);
}

function elips_action_staff_attendance_summary(array $p): array
{
    $actor = elips_require_attendance_access();
    if ($actor['role'] !== 'admin') {
        return elips_fail('Hanya administrator yang dapat melihat rekap absensi karyawan.');
    }
    $today = new DateTimeImmutable('now', new DateTimeZone(elips_attendance_timezone()));
    $from = elips_normalize_date($p['from'] ?? '') ?? $today->modify('first day of this month')->format('Y-m-d');
    $to = elips_normalize_date($p['to'] ?? '') ?? $today->format('Y-m-d');
    if ($from > $to) {
        return elips_fail('Tanggal awal tidak boleh setelah tanggal akhir.');
    }
    $span = (new DateTimeImmutable($from, new DateTimeZone('UTC')))->diff(new DateTimeImmutable($to, new DateTimeZone('UTC')))->days;
    if ($span > 366) {
        return elips_fail('Rentang rekap maksimal 366 hari.');
    }
    $rows = elips_staff_attendance_summary_data($from, $to, (int) ($p['userId'] ?? 0));
    return elips_ok('Rekap otomatis dimuat.', ['from' => $from, 'to' => $to, 'rows' => $rows]);
}

function elips_action_staff_attendance_my_summary(array $p): array
{
    $actor = elips_require_attendance_access();
    $tz = new DateTimeZone(elips_attendance_timezone());
    $today = new DateTimeImmutable('now', $tz);
    $month = trim((string) ($p['month'] ?? ''));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        $month = $today->format('Y-m');
    }
    $first = new DateTimeImmutable($month . '-01', $tz);
    $from = $first->format('Y-m-d');
    $to = $first->modify('last day of this month')->format('Y-m-d');
    $rows = elips_staff_attendance_summary_data($from, $to, (int) $actor['id']);
    return elips_ok('Rekap bulan ini dimuat.', ['month' => $month, 'from' => $from, 'to' => $to, 'summary' => $rows[0] ?? null]);
}
