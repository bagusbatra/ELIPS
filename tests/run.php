<?php
declare(strict_types=1);

// ============================================================
// ELIPS LMS - pengujian otomatis (tanpa dependensi)
//   php tests/run.php
// Memakai database terpisah `elips_lms_test` (dibuat ulang tiap run),
// menjalankan server PHP bawaan, lalu menguji lewat HTTP:
// login per role, guard server, API, absensi, geofence, rekap.
// Env: ELIPS_DB_HOST/PORT/USER/PASS bila bukan root tanpa password.
// ============================================================

const T_DB = 'elips_lms_test';
$root = dirname(__DIR__);
$passed = 0;
$failed = [];

function t(string $name, bool $cond, string $info = ''): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  ok   $name\n";
    } else {
        $failed[] = $name;
        echo "  FAIL $name" . ($info !== '' ? "  -> $info" : '') . "\n";
    }
}

final class Client
{
    public string $jar;
    public function __construct(public string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'elipsjar');
    }
    /** @return array{code:int,body:string,loc:string} */
    public function get(string $path): array
    {
        return $this->req('GET', $path);
    }
    public function req(string $method, string $path, ?array $json = null): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $size);
        $loc = preg_match('/^Location:\s*(\S+)/mi', $head, $m) ? $m[1] : '';
        return ['code' => $code, 'body' => substr($raw, $size), 'loc' => $loc];
    }
    /** Panggil API: kembalikan array JSON. */
    public function api(string $action, array $payload = []): array
    {
        $r = $this->req('POST', '/api/index.php', ['action' => $action, 'payload' => $payload]);
        return json_decode($r['body'], true) ?: ['ok' => false, 'message' => 'bukan JSON: ' . substr($r['body'], 0, 120)];
    }
    public function dropCookie(string $name): void
    {
        $lines = file($this->jar, FILE_IGNORE_NEW_LINES) ?: [];
        $keep = array_filter($lines, fn($l) => !preg_match('/\t' . preg_quote($name, '/') . '\t/', $l));
        file_put_contents($this->jar, implode("\n", $keep) . "\n");
    }
}

function start_server(string $root, int $port, array $env): array
{
    $cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root];
    $proc = proc_open($cmd, [1 => ['file', sys_get_temp_dir() . "/elips-test-$port.log", 'w'], 2 => ['redirect', 1]], $pipes, $root, array_merge(getenv(), $env));
    for ($i = 0; $i < 50; $i++) {
        $s = @fsockopen('127.0.0.1', $port, $e, $es, 0.2);
        if ($s) { fclose($s); break; }
        usleep(100000);
    }
    return [$proc, "http://127.0.0.1:$port"];
}

function cli(string $root, string $script, array $args, array $env): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/api/' . $script) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
    $prefix = '';
    foreach ($env as $k => $v) { putenv("$k=$v"); }
    exec($cmd, $out, $code);
    foreach ($env as $k => $v) { putenv($k); }
    return [$code, implode("\n", $out)];
}

/** JPEG valid 320x240 yang unik (seed membeda-bedakan hash). */
function make_jpeg(int $seed, int $w = 320, int $h = 240): string
{
    $im = imagecreatetruecolor($w, $h);
    mt_srand($seed);
    $bg = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    for ($i = 0; $i < 30; $i++) {
        imagefilledellipse($im, mt_rand(0, $w), mt_rand(0, $h), mt_rand(10, 80), mt_rand(10, 80),
            imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
    }
    ob_start();
    imagejpeg($im, null, 80);
    $data = (string) ob_get_clean();
    imagedestroy($im);
    return 'data:image/jpeg;base64,' . base64_encode($data);
}

// ---------- Persiapan database uji ----------
$env = [
    'ELIPS_DB_NAME' => T_DB,
    'ELIPS_ENV' => 'development',
];
echo "== Menyiapkan database uji ($env[ELIPS_DB_NAME])\n";
[$code, $out] = cli($root, 'install.php', ['--fresh', '--force'], $env);
if ($code !== 0 || strpos($out, 'Instalasi selesai') === false) {
    echo "Instalasi database uji gagal:\n$out\n";
    exit(2);
}
foreach (['seed-karyawan.php', 'seed-marketing.php'] as $seed) {
    [$c, $o] = cli($root, $seed, [], $env);
    if ($c !== 0) { echo "Seed $seed gagal:\n$o\n"; exit(2); }
}
$lock = $root . '/storage/install.lock';
$hadLock = is_file($lock);

$pdo = null;
(function () use (&$pdo) {
    $pdo = new PDO('mysql:host=' . (getenv('ELIPS_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('ELIPS_DB_PORT') ?: 3306) . ';dbname=' . T_DB . ';charset=utf8mb4',
        getenv('ELIPS_DB_USER') ?: 'root', getenv('ELIPS_DB_PASS') !== false ? (string) getenv('ELIPS_DB_PASS') : '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
})();
$demoPass = 'elips123';

[$srv, $base] = start_server($root, 8741, $env);

// ============================================================
echo "\n== 1. Halaman publik & guard anonim\n";
$c = new Client($base);
foreach (['index.php', 'login.php', 'register.php', 'verify-certificate.php', 'offline.php'] as $p) {
    $r = $c->get("/$p");
    t("publik $p -> 200", $r['code'] === 200, (string) $r['code']);
}
foreach (['admin/dashboard.php', 'admin/users.php', 'instructor/dashboard.php', 'student/dashboard.php', 'student/courses.php',
          'employee/shared/dashboard.php', 'employee/shared/attendance.php', 'employee/marketing/tasks.php',
          'employee/operasional/siswa.php', 'employee/keuangan/dashboard.php', 'learning.php'] as $p) {
    $r = $c->get("/$p");
    t("anonim $p -> 302 login", $r['code'] === 302 && str_ends_with($r['loc'], 'login.php'), "$r[code] $r[loc]");
    t("anonim $p tidak membocorkan konten", strpos($r['body'], 'dashboard-sidebar') === false);
}

// ============================================================
echo "\n== 2. Login per role & matriks akses halaman\n";
$accounts = [
    'admin' => ['admin@example.com', 'admin/dashboard.php'],
    'instruktur' => ['ahmad@example.com', 'instructor/dashboard.php'],
    'peserta' => ['ani@example.com', 'student/dashboard.php'],
    'karyawan-marketing' => ['dewi.karyawan@example.com', 'employee/shared/dashboard.php'],
    'karyawan-operasional' => ['agus.karyawan@example.com', 'employee/shared/dashboard.php'],
    'karyawan-keuangan' => ['sri.karyawan@example.com', 'employee/shared/dashboard.php'],
];
$allowed = [
    'admin' => ['admin/dashboard.php', 'admin/users.php', 'admin/attendance-recap.php', 'admin/shifts.php', 'learning.php',
                'employee/operasional/siswa.php', 'employee/keuangan/dashboard.php'],
    'instruktur' => ['instructor/dashboard.php', 'instructor/manage-course.php', 'learning.php'],
    'peserta' => ['student/dashboard.php', 'student/courses.php', 'learning.php'],
    'karyawan-marketing' => ['employee/shared/dashboard.php', 'employee/shared/attendance.php', 'employee/marketing/tasks.php', 'employee/marketing/campaigns.php'],
    'karyawan-operasional' => ['employee/shared/dashboard.php', 'employee/operasional/siswa.php', 'employee/operasional/jadwal.php'],
    'karyawan-keuangan' => ['employee/shared/dashboard.php', 'employee/keuangan/dashboard.php'],
];
$forbidden = [
    'admin' => ['instructor/dashboard.php', 'student/dashboard.php', 'employee/shared/dashboard.php', 'employee/marketing/tasks.php'],
    'instruktur' => ['admin/dashboard.php', 'student/dashboard.php', 'employee/shared/attendance.php'],
    'peserta' => ['admin/dashboard.php', 'admin/users.php', 'instructor/dashboard.php', 'employee/shared/dashboard.php'],
    'karyawan-marketing' => ['admin/dashboard.php', 'student/dashboard.php', 'employee/operasional/siswa.php', 'employee/keuangan/dashboard.php', 'learning.php'],
    'karyawan-operasional' => ['admin/users.php', 'employee/marketing/tasks.php', 'employee/keuangan/dashboard.php'],
    'karyawan-keuangan' => ['admin/users.php', 'employee/marketing/tasks.php', 'employee/operasional/siswa.php'],
];
$clients = [];
foreach ($accounts as $label => [$email, $home]) {
    $cl = new Client($base);
    $res = $cl->api('auth.login', ['email' => $email, 'password' => $demoPass]);
    t("login $label", !empty($res['ok']), $res['message'] ?? '');
    $clients[$label] = $cl;
    foreach ($allowed[$label] as $p) {
        $r = $cl->get("/$p");
        t("$label boleh $p", $r['code'] === 200, "$r[code] $r[loc]");
    }
    foreach ($forbidden[$label] as $p) {
        $r = $cl->get("/$p");
        t("$label ditolak $p -> dashboard sendiri", $r['code'] === 302 && str_ends_with($r['loc'], $home), "$r[code] $r[loc]");
        t("$label ditolak $p tanpa konten", strpos($r['body'], 'dashboard-sidebar') === false);
    }
}

echo "\n== 3. Autentikasi: password salah, logout, ingat saya\n";
$x = new Client($base);
$res = $x->api('auth.login', ['email' => 'admin@example.com', 'password' => 'salah-total']);
t('password salah ditolak', empty($res['ok']));
$res = $x->api('auth.login', ['email' => 'tidak-ada@example.com', 'password' => 'x']);
t('email tak terdaftar ditolak', empty($res['ok']));
t('tanpa login admin/users -> 302', $x->get('/admin/users.php')['code'] === 302);

$y = new Client($base);
$y->api('auth.login', ['email' => 'ani@example.com', 'password' => $demoPass, 'remember' => true]);
t('peserta masuk', $y->get('/student/dashboard.php')['code'] === 200);
$y->dropCookie('PHPSESSID');
$r = $y->get('/student/dashboard.php');
t('sesi PHP hilang, cookie ingat-saya memulihkan akses', $r['code'] === 200, "$r[code] $r[loc]");
$y->api('auth.logout');
t('setelah logout -> 302 login', $y->get('/student/dashboard.php')['code'] === 302);

$z = new Client($base);
$z->api('auth.login', ['email' => 'ani@example.com', 'password' => $demoPass]);
$z2 = new Client($base);
$z2->api('auth.login', ['email' => 'ani@example.com', 'password' => $demoPass]);
t('login di perangkat baru mencabut sesi lama', $z->get('/student/dashboard.php')['code'] === 302);

// ============================================================
echo "\n== 4. API: kewenangan\n";
$anon = new Client($base);
$res = $anon->api('user.create', ['name' => 'X', 'email' => 'x@example.com']);
t('anonim tidak bisa membuat user', empty($res['ok']));
$res = $clients['peserta']->api('user.create', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'admin', 'password' => 'abcdefgh1']);
t('peserta tidak bisa membuat user', empty($res['ok']));
$boot = $clients['admin']->api('bootstrap');
t('bootstrap admin mengembalikan koleksi', !empty($boot['ok']) && !empty($boot['collections']));
$res = $clients['peserta']->api('staffAttendance.create', ['kind' => 'in']);
t('peserta tidak bisa absen karyawan', empty($res['ok']));
$res = $clients['karyawan-marketing']->api('staffAttendance.recap', []);
t('karyawan tidak bisa melihat rekap admin', empty($res['ok']));

// ============================================================
echo "\n== 5. Absensi karyawan (validasi server)\n";
$emp = $clients['karyawan-marketing'];
$empId = (int) $pdo->query("SELECT id FROM users WHERE email='dewi.karyawan@example.com'")->fetchColumn();
$loc = ['latitude' => -7.4478, 'longitude' => 112.7183, 'accuracyM' => 20];
// shift hari ini mulai 1 jam lalu -> absen masuk sekarang = terlambat
$tz = new DateTimeZone('Asia/Jakarta');
$nowJkt = new DateTimeImmutable('now', $tz);
$pdo->prepare("INSERT INTO staff_shifts (user_id, shift_date, shift_type, start_time, end_time) VALUES (?,?,'custom',?,?)")
    ->execute([$empId, $nowJkt->format('Y-m-d'), $nowJkt->modify('-1 hour')->format('H:i:s'), $nowJkt->modify('+7 hours')->format('H:i:s')]);

$res = $emp->api('staffAttendance.create', ['kind' => 'in'] + $loc);
t('tanpa foto ditolak', empty($res['ok']) && stripos($res['message'], 'foto') !== false, $res['message'] ?? '');
$res = $emp->api('staffAttendance.create', ['kind' => 'in', 'photo' => make_jpeg(1), 'latitude' => null, 'longitude' => null]);
t('tanpa GPS ditolak', empty($res['ok']) && stripos($res['message'], 'GPS') !== false, $res['message'] ?? '');
$res = $emp->api('staffAttendance.create', ['kind' => 'in', 'photo' => make_jpeg(1), 'latitude' => 999, 'longitude' => 112]);
t('koordinat di luar rentang ditolak', empty($res['ok']));
$res = $emp->api('staffAttendance.create', ['kind' => 'in', 'photo' => 'data:image/jpeg;base64,' . base64_encode('bukan gambar')] + $loc);
t('file palsu bukan gambar ditolak', empty($res['ok']), $res['message'] ?? '');
$res = $emp->api('staffAttendance.create', ['kind' => 'in', 'photo' => make_jpeg(2, 60, 60)] + $loc);
t('foto terlalu kecil ditolak', empty($res['ok']) && stripos($res['message'], 'kecil') !== false, $res['message'] ?? '');
$res = $emp->api('staffAttendance.create', ['kind' => 'in', 'photo' => make_jpeg(3)] + ['latitude' => -7.4, 'longitude' => 112.7, 'accuracyM' => 900]);
t('akurasi GPS buruk ditolak', empty($res['ok']) && stripos($res['message'], 'akurasi') !== false, $res['message'] ?? '');
$res = $emp->api('staffAttendance.create', ['kind' => 'out', 'photo' => make_jpeg(4)] + $loc);
t('absen pulang sebelum masuk ditolak', empty($res['ok']), $res['message'] ?? '');

$photoIn = make_jpeg(10);
$res = $emp->api('staffAttendance.create', ['kind' => 'in', 'photo' => $photoIn, 'latitude' => $loc['latitude'], 'longitude' => $loc['longitude'], 'accuracyM' => 20, 'takenAt' => '2020-01-01T00:00:00Z']);
t('absen masuk berhasil', !empty($res['ok']), $res['message'] ?? '');
$row = $res['data'] ?? [];
t('jam dari server (klien takenAt diabaikan)', isset($row['takenAt']) && substr($row['takenAt'], 0, 4) !== '2020');
t('status terlambat dihitung server', ($row['status'] ?? '') === 'late' && ($row['lateMinutes'] ?? 0) >= 45, json_encode([$row['status'] ?? null, $row['lateMinutes'] ?? null]));
$res = $emp->api('staffAttendance.create', ['kind' => 'in', 'photo' => make_jpeg(11)] + $loc);
t('absen masuk kedua di hari sama ditolak', empty($res['ok']), $res['message'] ?? '');
$res = $emp->api('staffAttendance.create', ['kind' => 'out', 'photo' => $photoIn] + $loc);
t('foto yang sama dipakai ulang ditolak', empty($res['ok']) && stripos($res['message'], 'foto') !== false, $res['message'] ?? '');
$res = $emp->api('staffAttendance.create', ['kind' => 'out', 'photo' => make_jpeg(12)] + $loc);
t('absen pulang berhasil', !empty($res['ok']), $res['message'] ?? '');
t('pulang cepat tercatat', ($res['data']['status'] ?? '') === 'early_leave', (string) ($res['data']['status'] ?? ''));

echo "\n== 6. Rekap otomatis\n";
$sum = $clients['admin']->api('staffAttendance.summary', []);
t('admin memuat rekap otomatis', !empty($sum['ok']), $sum['message'] ?? '');
$mine = null;
foreach ($sum['data']['rows'] ?? [] as $r) { if ((int) $r['userId'] === $empId) $mine = $r; }
t('rekap: 1 hari hadir, 1 terlambat', $mine && $mine['presentDays'] === 1 && $mine['lateCount'] === 1, json_encode($mine ? [$mine['presentDays'], $mine['lateCount']] : null));
t('rekap: pulang cepat 1', $mine && $mine['earlyLeaveCount'] === 1);
$my = $emp->api('staffAttendance.mySummary', []);
t('karyawan melihat rekap bulannya', !empty($my['ok']) && ($my['data']['summary']['presentDays'] ?? 0) === 1, $my['message'] ?? '');
$res = $emp->api('staffAttendance.summary', []);
t('karyawan tidak bisa membuka rekap semua orang', empty($res['ok']));
// alpa: shift kemarin tanpa absen
$yday = $nowJkt->modify('-1 day')->format('Y-m-d');
$opsId = (int) $pdo->query("SELECT id FROM users WHERE email='agus.karyawan@example.com'")->fetchColumn();
$pdo->prepare("INSERT INTO staff_shifts (user_id, shift_date, shift_type, start_time, end_time) VALUES (?,?,'pagi','08:00:00','16:00:00')")->execute([$opsId, $yday]);
$sum = $clients['admin']->api('staffAttendance.summary', ['from' => $yday, 'to' => $yday, 'userId' => $opsId]);
t('shift tanpa absen dihitung alpa', ($sum['data']['rows'][0]['absentDays'] ?? 0) === 1, json_encode($sum['data']['rows'][0] ?? null));
[$cc, $co] = cli($root, 'cron-attendance.php', ['--month=' . $nowJkt->format('Y-m')], $env);
t('CLI rekap otomatis menulis CSV', $cc === 0 && strpos($co, 'CSV:') !== false, $co);

// ============================================================
echo "\n== 7. Geofence (server terpisah dengan titik kantor)\n";
[$srv2, $base2] = start_server($root, 8742, $env + [
    'ELIPS_GEOFENCE' => json_encode([['name' => 'Kantor Uji', 'lat' => -7.4478, 'lng' => 112.7183, 'radius_m' => 150]]),
    'ELIPS_GEOFENCE_ENFORCE' => '1',
]);
$g = new Client($base2);
$g->api('auth.login', ['email' => 'sri.karyawan@example.com', 'password' => $demoPass]);
$far = $g->api('staffAttendance.create', ['kind' => 'in', 'photo' => make_jpeg(21), 'latitude' => -7.30, 'longitude' => 112.90, 'accuracyM' => 10]);
t('di luar radius ditolak', empty($far['ok']) && stripos($far['message'], 'm dari') !== false, $far['message'] ?? '');
$near = $g->api('staffAttendance.create', ['kind' => 'in', 'photo' => make_jpeg(22), 'latitude' => -7.4480, 'longitude' => 112.7185, 'accuracyM' => 10]);
t('di dalam radius diterima + nama lokasi', !empty($near['ok']) && ($near['data']['geofenceName'] ?? '') === 'Kantor Uji', $near['message'] ?? '');
proc_terminate($srv2);

// ============================================================
echo "\n== 8. Penguncian install & seed\n";
$r = $c->get('/api/install.php');
t('install.php lewat web -> 404', $r['code'] === 404, (string) $r['code']);
$r = $c->get('/api/seed-all.php');
t('seed-all.php lewat web -> 404', $r['code'] === 404, (string) $r['code']);
$r = $c->get('/api/create-admin.php');
t('create-admin.php lewat web -> 404', $r['code'] === 404, (string) $r['code']);
t('install.lock ada setelah instalasi', is_file($lock));
[$code, $out] = cli($root, 'install.php', [], $env);
t('install ulang tanpa --force ditolak', $code !== 0 && strpos($out, 'Ditolak') !== false, $out);
[$code, $out] = cli($root, 'install.php', ['--fresh'], $env);
t('--fresh tanpa --force ditolak', $code !== 0, $out);
[$code, $out] = cli($root, 'seed-letters.php', [], array_merge($env, ['ELIPS_ENV' => 'production']));
t('seed ditolak saat ELIPS_ENV=production', $code !== 0 && strpos($out, 'production') !== false, $out);
[$code, $out] = cli($root, 'create-admin.php', ['admin.baru@example.com', 'Admin Baru', '--password=RahasiaBaru123'], $env);
t('create-admin membuat admin', $code === 0, $out);
$cx = new Client($base);
$res = $cx->api('auth.login', ['email' => 'admin.baru@example.com', 'password' => 'RahasiaBaru123']);
t('admin baru bisa login', !empty($res['ok']), $res['message'] ?? '');

// ============================================================
echo "\n== 9. Ketersediaan aset & berkas komponen\n";
foreach (['assets/css/style.css', 'assets/js/config.js', 'manifest.webmanifest', 'sw.js'] as $p) {
    t("aset $p -> 200", $c->get("/$p")['code'] === 200);
}
$html = $c->get('/index.php')['body'];
t('ELIPS_BASE tercetak di head', strpos($html, 'window.ELIPS_BASE') !== false);
t('halaman login memuat bundel skrip', strpos($c->get('/login.php')['body'], 'assets/js/') !== false);

proc_terminate($srv);
if (!$hadLock) { @unlink($lock); }
echo "\n----------------------------------------\n";
echo "Lulus: $passed | Gagal: " . count($failed) . "\n";
if ($failed) {
    echo "Gagal:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}
exit(0);
