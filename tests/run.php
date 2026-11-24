<?php
// Direktori: tests/run.php
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
foreach (['index.php', 'login.php', 'activate.php', 'forgot-password.php', 'reset-password.php', 'verify-certificate.php', 'offline.php'] as $p) {
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

// ============================================================
echo "\n== 10. Pendaftaran publik, kelas & kuota, badge\n";
require_once $root . '/api/migrations.php';
foreach (['0812-3456-7890' => '6281234567890', '+62 812 3456 7890' => '6281234567890', '6281234567890' => '6281234567890',
          '81234567890' => '6281234567890', '0062 812 3456 7890' => '6281234567890', '12345' => '', 'abc' => '', '' => ''] as $in => $want) {
    t("normalisasi telepon '$in'", elips_normalize_phone((string) $in) === $want, elips_normalize_phone((string) $in));
}
function reset_rl(): void
{
    foreach (glob(rtrim(sys_get_temp_dir(), '/\\') . '/elips_rate_limit/register-*') ?: [] as $f) { @unlink($f); }
}
function rrmdir(string $dir): void
{
    if (!is_dir($dir)) { return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($dir);
}
reset_rl();
$anon = new Client($base);
$pdo->exec("DELETE FROM course_registrations");
$base_form = ['courseId' => 1, 'name' => 'Calon Siswa Uji', 'birthPlace' => 'Surabaya', 'birthDate' => '2001-05-17',
    'address' => 'Jl. Contoh No. 1, Surabaya', 'phone' => '0812-3456-7890', 'email' => 'calon.uji@example.com'];

$res = $anon->api('course.classes', ['courseId' => 1]);
t('course.classes publik ok tanpa login', !empty($res['ok']), $res['message'] ?? '');
t('course.classes tidak menyertakan snapshot', !isset($res['snapshot']));
t('tanpa kelas open: daftar kosong', ($res['data'] ?? null) === []);
$res = $anon->api('course.classes', ['courseId' => 2]);
t('course.classes program draft ditolak', empty($res['ok']));
$res = $anon->api('course.register', $base_form);
t('daftar tanpa kelas open ditolak', empty($res['ok']) && strpos($res['message'] ?? '', 'Belum ada kelas') !== false, $res['message'] ?? '');

$version = (int) $pdo->query('SELECT curriculum_version_id FROM classes WHERE course_id = 1 LIMIT 1')->fetchColumn();
$pdo->prepare("INSERT INTO classes (code, name, course_id, curriculum_version_id, period_start, period_end, quota, status, notes)
               VALUES ('UJI-REG-01', 'Kelas Uji Pendaftaran', 1, ?, '2026-12-01', '2027-02-28', 2, 'open', '')")->execute([$version]);
$classId = (int) $pdo->lastInsertId();
$pdo->exec("UPDATE courses SET price = 1500000, discount_price = 1200000 WHERE id = 1");

$res = $anon->api('course.classes', ['courseId' => 1]);
t('kelas open tampil dengan sisa kuota 2', ($res['data'][0]['id'] ?? 0) === $classId && ($res['data'][0]['seatsLeft'] ?? -1) === 2, json_encode($res['data'] ?? null));
t('course.classes tidak membocorkan instruktur/peserta', !isset($res['data'][0]['instructorId']) && !isset($res['data'][0]['students']));

reset_rl();
$res = $anon->api('course.register', $base_form);
t('daftar tanpa classId ditolak', empty($res['ok']) && strpos($res['message'] ?? '', 'kelas') !== false, $res['message'] ?? '');
$res = $anon->api('course.register', $base_form + ['classId' => 1]);
t('daftar ke kelas non-open ditolak', empty($res['ok']), $res['message'] ?? '');
$res = $anon->api('course.register', array_merge($base_form, ['classId' => $classId, 'phone' => '12345']));
t('nomor WhatsApp tidak valid ditolak', empty($res['ok']) && strpos($res['message'] ?? '', 'WhatsApp') !== false, $res['message'] ?? '');
$res = $anon->api('course.register', array_merge($base_form, ['classId' => $classId, 'website' => 'http://spam.example']));
t('honeypot: sukses semu tanpa data', !empty($res['ok']) && ($res['data']['accepted'] ?? true) === false);
t('honeypot tidak menyimpan baris', (int) $pdo->query('SELECT COUNT(*) FROM course_registrations')->fetchColumn() === 0);

$res = $anon->api('course.register', array_merge($base_form, ['classId' => $classId]));
t('daftar valid berhasil', !empty($res['ok']) && ($res['data']['accepted'] ?? false) === true, $res['message'] ?? '');
t('respons pendaftaran tidak mengembalikan data pribadi', !isset($res['data']['email']) && !isset($res['data']['phone']));
t('respons pendaftaran tanpa snapshot', !isset($res['snapshot']));
$row = $pdo->query('SELECT * FROM course_registrations ORDER BY id DESC LIMIT 1')->fetch();
t('baris tersimpan status baru', ($row['status'] ?? '') === 'baru');
t('kelas dan nama kelas tersimpan', (int) $row['class_id'] === $classId && $row['class_name'] === 'Kelas Uji Pendaftaran');
t('nominal tagihan memakai harga diskon', (float) $row['amount_due'] === 1200000.0, (string) $row['amount_due']);
t('nomor WA ternormalisasi', $row['phone_norm'] === '6281234567890', (string) $row['phone_norm']);
$regId = (int) $row['id'];

$res = $anon->api('course.register', array_merge($base_form, ['classId' => $classId]));
t('pendaftaran ganda (email+program) ditolak', empty($res['ok']), $res['message'] ?? '');
$pdo->exec("UPDATE courses SET discount_price = 0 WHERE id = 1");

// Kuota: pendaftar 'baru' belum memakai kursi, 'menunggu' memakai kursi.
$res = $anon->api('course.classes', ['courseId' => 1]);
t("pendaftar 'baru' belum memakai kursi", ($res['data'][0]['seatsLeft'] ?? -1) === 2);
$pdo->exec("UPDATE course_registrations SET status = 'menunggu', activation_sent_at = UTC_TIMESTAMP() WHERE id = $regId");
$res = $anon->api('course.classes', ['courseId' => 1]);
t("pendaftar 'menunggu' memakai 1 kursi", ($res['data'][0]['seatsLeft'] ?? -1) === 1);
$pdo->exec("UPDATE classes SET quota = 1 WHERE id = $classId");
$res = $anon->api('course.classes', ['courseId' => 1]);
t('kelas penuh ditandai full', ($res['data'][0]['full'] ?? false) === true && ($res['data'][0]['seatsLeft'] ?? -1) === 0);
reset_rl();
$res = $anon->api('course.register', array_merge($base_form, ['classId' => $classId, 'email' => 'lain@example.com']));
t('daftar ke kelas penuh ditolak', empty($res['ok']) && strpos($res['message'] ?? '', 'penuh') !== false, $res['message'] ?? '');
$pdo->exec("UPDATE course_registrations SET activation_sent_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 DAY) WHERE id = $regId");
$res = $anon->api('course.classes', ['courseId' => 1]);
t('link kedaluwarsa 15 hari -> kursi lepas', ($res['data'][0]['seatsLeft'] ?? -1) === 1 && ($res['data'][0]['full'] ?? true) === false);
t("pendaftaran kedaluwarsa menjadi 'batal'", $pdo->query("SELECT status FROM course_registrations WHERE id = $regId")->fetchColumn() === 'batal');
$res = $anon->api('course.register', array_merge($base_form, ['classId' => $classId]));
t('setelah batal, email yang sama boleh mendaftar lagi', !empty($res['ok']), $res['message'] ?? '');

// Rate limit pendaftaran per IP.
reset_rl();
$blocked = false;
for ($i = 0; $i < 10; $i++) {
    $res = $anon->api('course.register', ['courseId' => 1]);
    if (strpos($res['message'] ?? '', 'Terlalu banyak') !== false) { $blocked = true; break; }
}
t('rate limit course.register aktif (maks 8/10 menit)', $blocked && $i === 8, "percobaan ke-$i");
reset_rl();

// Hak akses daftar pendaftar & badge.
$res = $anon->api('course.registration.list', []);
t('daftar pendaftar ditolak untuk anonim', empty($res['ok']));
$res = $clients['peserta']->api('course.registration.list', []);
t('daftar pendaftar ditolak untuk peserta', empty($res['ok']));
$res = $clients['admin']->api('course.registration.list', []);
t('daftar pendaftar untuk admin ok & tanpa snapshot', !empty($res['ok']) && !isset($res['snapshot']) && count($res['data']) >= 1);
t('daftar pendaftar memuat kelas & tagihan', isset($res['data'][0]['className'], $res['data'][0]['amountDue']));
$newCount = (int) $pdo->query("SELECT COUNT(*) FROM course_registrations WHERE status = 'baru'")->fetchColumn();
$badge = json_decode($anon->req('GET', '/api/index.php?action=badges')['body'], true);
t('badges anonim kosong', ($badge['ok'] ?? false) && ($badge['data'] ?? null) === []);
$badge = json_decode($clients['admin']->req('GET', '/api/index.php?action=badges')['body'], true);
t('badges admin = jumlah pendaftar baru', ($badge['data']['registrationsNew'] ?? -1) === $newCount && $newCount >= 1, json_encode($badge));
$keu = new Client($base);
$keu->api('auth.login', ['email' => 'sri.karyawan@example.com', 'password' => 'elips123']);
$badge = json_decode($keu->req('GET', '/api/index.php?action=badges')['body'], true);
t('badges karyawan keuangan hanya tagihan belum lunas', array_keys($badge['data'] ?? []) === ['unpaidInvoices'], json_encode($badge));
$mkt = new Client($base);
$mkt->api('auth.login', ['email' => 'dewi.karyawan@example.com', 'password' => 'elips123']);
$badge = json_decode($mkt->req('GET', '/api/index.php?action=badges')['body'], true);
t('badges karyawan non-keuangan kosong', ($badge['data'] ?? null) === []);
$html = $clients['admin']->get('/admin/registrations.php')['body'];
t('sidebar admin memuat badge merah dengan angka dari server',
    (bool) preg_match('/data-badge="registrationsNew" data-count="' . $newCount . '"/', $html));
$html = $clients['admin']->get('/admin/users.php')['body'];
t('badge tampil di semua halaman admin', strpos($html, 'nav-badge-red') !== false);

// Akun berstatus 'menunggu' tidak bisa login.
$pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, status, created_at)
               VALUES ('Siswa Menunggu', 'menunggu@example.com', '', ?, 'peserta', 'menunggu', CURDATE())")
    ->execute([password_hash('Rahasia123!', PASSWORD_DEFAULT)]);
$res = (new Client($base))->api('auth.login', ['email' => 'menunggu@example.com', 'password' => 'Rahasia123!']);
t("login akun 'menunggu' ditolak", empty($res['ok']) && strpos($res['message'] ?? '', 'belum diaktifkan') !== false, $res['message'] ?? '');

// Migrasi idempoten.
[$code, $out] = cli($root, 'install.php', ['--force'], $env);
t('migrasi/instal ulang (--force) aman dijalankan lagi', $code === 0 && strpos($out, 'Instalasi selesai') !== false, $out);

// Bundel: basi tidak boleh dipakai (cek isi, bukan waktu ubah).
$tmp = sys_get_temp_dir() . '/elips-bundle-' . getmypid();
@mkdir($tmp . '/assets/js', 0777, true);
@mkdir($tmp . '/api', 0777, true);
@mkdir($tmp . '/tools', 0777, true);
foreach (['includes'] as $d) { @mkdir("$tmp/$d"); foreach (glob("$root/$d/*.php") as $f) { copy($f, "$tmp/$d/" . basename($f)); } }
foreach (glob("$root/assets/js/*.js") as $f) { copy($f, "$tmp/assets/js/" . basename($f)); }
copy("$root/api/config.php", "$tmp/api/config.php");
copy("$root/tools/build-assets.php", "$tmp/tools/build-assets.php");
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$tmp/tools/build-assets.php") . ' 2>&1', $o, $bc);
t('build-assets menulis manifest.json', $bc === 0 && is_file("$tmp/assets/js/dist/manifest.json"), implode("\n", $o));
file_put_contents("$tmp/probe.php", '<?php require __DIR__ . "/includes/bootstrap.php";
echo elips_bundle_for("core.bundle", elips_core_scripts()) ?? "NULL";');
$probe = fn() => trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$tmp/probe.php") . ' 2>&1'));
t('bundel segar dipakai', $probe() === 'dist/core.bundle', $probe());
file_put_contents("$tmp/assets/js/data.js", "\n// ubah isi\n", FILE_APPEND);
touch("$tmp/assets/js/dist/core.bundle.js", time() + 100);   // bundel "lebih baru" dari sumber
t('bundel basi (isi sumber berubah) tidak dipakai walau waktu ubah lebih baru', $probe() === 'NULL', $probe());
unlink("$tmp/assets/js/dist/manifest.json");
t('tanpa manifest bundel tidak dipakai', $probe() === 'NULL');
file_put_contents("$tmp/probe-repo.php", '<?php require ' . var_export($root . '/includes/bootstrap.php', true) . ';
echo elips_bundle_for("core.bundle", elips_core_scripts()) ?? "NULL";');
$repoBundle = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$tmp/probe-repo.php") . ' 2>&1'));
t('bundel di repo hanya dipakai bila cocok dengan sumber', in_array($repoBundle, ['NULL', 'dist/core.bundle'], true));
if ($repoBundle === 'dist/core.bundle') {
    t('bundel di repo memuat addCourseRegistration', strpos((string) file_get_contents($root . '/assets/js/dist/core.bundle.js'), 'addCourseRegistration') !== false);
}
rrmdir($tmp);
$pdo->exec("DELETE FROM users WHERE email = 'menunggu@example.com'");
$pdo->exec("DELETE FROM course_registrations");
$pdo->exec("DELETE FROM classes WHERE code = 'UJI-REG-01'");
$pdo->exec("UPDATE courses SET price = 0, discount_price = 0 WHERE id = 1");

// ============================================================
echo "\n== 11. Aktivasi akun, sandi, login telepon, lupa sandi, pembayaran & nomor EC\n";
// ============================================================
$mk = function (string $email, string $pw = 'elips123') use ($base): Client {
    $c = new Client($base);
    $c->api('auth.login', ['email' => $email, 'password' => $pw]);
    return $c;
};
$adm = $mk('admin@example.com');
$keuC = $mk('sri.karyawan@example.com');
$pdo->exec("DELETE FROM course_registrations");
$pdo->prepare("INSERT INTO classes (code, name, course_id, curriculum_version_id, period_start, period_end, quota, status, notes)
               VALUES ('UJI-ACT-01', 'Kelas Uji Aktivasi', 1, ?, '2027-03-01', '2027-05-30', 2, 'open', '')")->execute([$version]);
$actClass = (int) $pdo->lastInsertId();
$pdo->exec("UPDATE courses SET price = 1000000, discount_price = 0 WHERE id = 1");
$reg = function (string $email, string $phone, string $name = 'Calon Aktivasi') use ($actClass): array {
    return [
        'courseId' => 1, 'classId' => $actClass, 'name' => $name, 'birthPlace' => 'Malang', 'birthDate' => '2000-05-06',
        'address' => 'Jl. Aktivasi 1', 'phone' => $phone, 'email' => $email,
    ];
};
$profile = [
    'fullName' => 'Calon Aktivasi', 'address' => 'Jl. Aktivasi 1 Malang', 'phone' => '0812-5550-0001', 'birthPlace' => 'Malang',
    'birthDate' => '2000-05-06', 'religion' => 'Islam', 'educationLevel' => 'S1', 'educationInstitution' => 'Universitas Uji',
    'educationMajor' => 'Teknik Informatika', 'educationYear' => '2022', 'occupation' => 'Karyawan Swasta', 'occupationOther' => '',
];
$seats_taken_fn = null;
function seats_taken(PDO $pdo, int $classId): int
{
    return (int) $pdo->query("SELECT (SELECT COUNT(*) FROM enrollments WHERE class_id = $classId AND status IN ('active','pending'))
        + (SELECT COUNT(*) FROM course_registrations WHERE class_id = $classId AND status = 'menunggu')")->fetchColumn();
}
// Rate limit pendaftaran dibersihkan agar uji ini tidak terganggu bagian sebelumnya.
foreach (glob(sys_get_temp_dir() . '/elips_rate_limit/register-*') ?: [] as $f) { @unlink($f); }

// -- Kebijakan sandi (server)
require_once $root . '/api/activation.php';
t('sandi: terlalu pendek ditolak', elips_password_policy_error('Ab1!') !== '');
t('sandi: tanpa huruf besar ditolak', elips_password_policy_error('abcdef1!') !== '');
t('sandi: tanpa huruf kecil ditolak', elips_password_policy_error('ABCDEF1!') !== '');
t('sandi: tanpa angka ditolak', elips_password_policy_error('Abcdefg!') !== '');
t('sandi: tanpa karakter khusus ditolak', elips_password_policy_error('Abcdefg1') !== '');
t('sandi: memenuhi semua aturan diterima', elips_password_policy_error('Abcdef1!') === '');
t('telepon dinormalisasi ke 62', elips_normalize_phone('0812-5550-0001') === '6281255500001' && elips_normalize_phone('+62 812 5550 0001') === '6281255500001');

// -- Pendaftar -> link aktivasi
$res = $anon->api('course.register', $reg('aktivasi1@example.com', '081255500001'));
t('pendaftaran untuk uji aktivasi diterima', !empty($res['ok']), $res['message'] ?? '');
$regId = (int) $pdo->query("SELECT id FROM course_registrations WHERE email = 'aktivasi1@example.com'")->fetchColumn();
t('send activation ditolak anonim', empty($anon->api('registration.sendActivation', ['id' => $regId])['ok']));
t('send activation ditolak peserta', empty($clients['peserta']->api('registration.sendActivation', ['id' => $regId])['ok']));
t('send activation ditolak karyawan keuangan', empty($keuC->api('registration.sendActivation', ['id' => $regId])['ok']));
$res = $adm->api('registration.sendActivation', ['id' => $regId]);
t('admin membuat link aktivasi', !empty($res['ok']) && ($res['data']['mode'] ?? '') === 'link', $res['message'] ?? '');
$link = $res['data']['link'] ?? '';
$token = preg_match('/token=([a-f0-9]{64})$/', $link, $m) ? $m[1] : '';
t('link memuat token 64 hex', $token !== '', $link);
t('tombol WhatsApp memakai nomor ternormalisasi', strpos($res['data']['waUrl'] ?? '', 'https://wa.me/6281255500001?text=') === 0);
t('pesan WhatsApp memuat link', strpos($res['data']['message'] ?? '', $link) !== false);
t('token tersimpan sebagai hash, bukan teks asli',
    (int) $pdo->query("SELECT COUNT(*) FROM account_tokens WHERE token_hash = " . $pdo->quote($token))->fetchColumn() === 0
    && (int) $pdo->query("SELECT COUNT(*) FROM account_tokens WHERE token_hash = " . $pdo->quote(hash('sha256', $token)))->fetchColumn() === 1);
$ur = $pdo->query("SELECT status, password_hash FROM users WHERE email = 'aktivasi1@example.com'")->fetch();
t("akun dibuat berstatus 'menunggu'", ($ur['status'] ?? '') === 'menunggu');
t("pendaftaran berstatus 'menunggu'", $pdo->query("SELECT status FROM course_registrations WHERE id = $regId")->fetchColumn() === 'menunggu');
t('kursi kelas terpakai saat link dikirim', seats_taken($pdo, $actClass) === 1);
$res = (new Client($base))->api('auth.login', ['email' => 'aktivasi1@example.com', 'password' => 'Abcdef1!']);
t("login ditolak selama 'menunggu'", empty($res['ok']) && strpos($res['message'] ?? '', 'belum diaktifkan') !== false);

// -- Halaman & info aktivasi (publik)
t('halaman activate.php terbuka tanpa login', $anon->get('/activate.php?token=' . $token)['code'] === 200);
$res = $anon->api('activation.info', ['token' => $token]);
t('activation.info memberi data terisi dari pendaftaran', !empty($res['ok']) && ($res['data']['prefill']['fullName'] ?? '') === 'Calon Aktivasi'
    && ($res['data']['email'] ?? '') === 'aktivasi1@example.com', $res['message'] ?? '');
t('activation.info tanpa snapshot', !isset($res['snapshot']));
t('activation.info memuat opsi pendidikan dinamis', count($res['data']['options']['educationLevels'] ?? []) >= 6);
$res = $anon->api('activation.info', ['token' => str_repeat('0', 64)]);
t('token acak ditolak', empty($res['ok']));
$res = $anon->api('activation.info', ['token' => 'bukan-token']);
t('token berbentuk salah ditolak', empty($res['ok']));

// -- Selesaikan aktivasi: validasi
$stu = new Client($base);
$bad = $stu->api('activation.complete', ['token' => $token, 'profile' => $profile, 'password' => 'abc', 'passwordConfirm' => 'abc']);
t('aktivasi: sandi lemah ditolak server', empty($bad['ok']));
$bad = $stu->api('activation.complete', ['token' => $token, 'profile' => $profile, 'password' => 'Abcdef1!', 'passwordConfirm' => 'Abcdef1?']);
t('aktivasi: konfirmasi tidak sama ditolak', empty($bad['ok']));
$p2 = $profile; $p2['birthDate'] = '2999-01-01';
t('aktivasi: tanggal lahir masa depan ditolak', empty($stu->api('activation.complete', ['token' => $token, 'profile' => $p2, 'password' => 'Abcdef1!', 'passwordConfirm' => 'Abcdef1!'])['ok']));
$p2 = $profile; $p2['educationLevel'] = 'XX';
t('aktivasi: jenjang pendidikan tidak dikenal ditolak', empty($stu->api('activation.complete', ['token' => $token, 'profile' => $p2, 'password' => 'Abcdef1!', 'passwordConfirm' => 'Abcdef1!'])['ok']));
$p2 = $profile; $p2['educationMajor'] = '';
t('aktivasi: program studi wajib untuk jenjang S1', empty($stu->api('activation.complete', ['token' => $token, 'profile' => $p2, 'password' => 'Abcdef1!', 'passwordConfirm' => 'Abcdef1!'])['ok']));
$p2 = $profile; $p2['educationLevel'] = 'SD'; $p2['educationMajor'] = 'tidak dipakai';
$pSd = $p2;
$p2 = $profile; $p2['occupation'] = 'Lainnya'; $p2['occupationOther'] = '';
t("aktivasi: pekerjaan 'Lainnya' wajib disebutkan", empty($stu->api('activation.complete', ['token' => $token, 'profile' => $p2, 'password' => 'Abcdef1!', 'passwordConfirm' => 'Abcdef1!'])['ok']));
t("akun tetap 'menunggu' setelah percobaan gagal", $pdo->query("SELECT status FROM users WHERE email = 'aktivasi1@example.com'")->fetchColumn() === 'menunggu');

// -- Selesaikan aktivasi: sukses
$res = $stu->api('activation.complete', ['token' => $token, 'profile' => $profile, 'password' => 'Abcdef1!', 'passwordConfirm' => 'Abcdef1!']);
t('aktivasi berhasil', !empty($res['ok']), $res['message'] ?? '');
t('aktivasi: langsung masuk sebagai siswa', ($res['data']['user']['role'] ?? '') === 'peserta' && ($res['session']['userId'] ?? 0) > 0);
$studentId = (int) ($res['data']['user']['id'] ?? 0);
$ur = $pdo->query("SELECT * FROM users WHERE id = $studentId")->fetch();
t("akun menjadi 'aktif'", ($ur['status'] ?? '') === 'aktif');
t('sandi tersimpan sebagai hash', password_verify('Abcdef1!', (string) $ur['password_hash']) && strpos((string) $ur['password_hash'], 'Abcdef1!') === false);
t('nomor telepon akun diperbarui dari biodata', $ur['phone_norm'] === '6281255500001');
$pr = $pdo->query("SELECT * FROM student_profiles WHERE user_id = $studentId")->fetch();
t('biodata tersimpan lengkap', ($pr['religion'] ?? '') === 'Islam' && ($pr['education_level'] ?? '') === 'S1'
    && ($pr['education_institution'] ?? '') === 'Universitas Uji' && (int) ($pr['education_year'] ?? 0) === 2022 && ($pr['birth_place'] ?? '') === 'Malang');
$rr = $pdo->query("SELECT * FROM course_registrations WHERE id = $regId")->fetch();
t("pendaftaran menjadi 'terdaftar' dan terhubung ke siswa & enrollment", $rr['status'] === 'terdaftar' && (int) $rr['student_id'] === $studentId && $rr['enrollment_id'] !== null);
$en = $pdo->query("SELECT * FROM enrollments WHERE id = " . (int) $rr['enrollment_id'])->fetch();
t('siswa terdaftar aktif di kelas pilihannya', ($en['status'] ?? '') === 'active' && (int) $en['class_id'] === $actClass && (int) $en['student_id'] === $studentId);
$inv = $pdo->query("SELECT * FROM invoices WHERE student_id = $studentId")->fetch();
t('tagihan dibuat sesuai harga program', ($inv['status'] ?? '') === 'unpaid' && (float) $inv['amount_due'] === 1000000.0 && $inv['student_number'] === null);
t('kursi tidak terhitung ganda setelah aktivasi', seats_taken($pdo, $actClass) === 1);
t('token tidak bisa dipakai ulang', empty((new Client($base))->api('activation.info', ['token' => $token])['ok'])
    && empty((new Client($base))->api('activation.complete', ['token' => $token, 'profile' => $profile, 'password' => 'Abcdef1!', 'passwordConfirm' => 'Abcdef1!'])['ok']));
t('audit aktivasi tercatat', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE detail LIKE '%link aktivasi%'")->fetchColumn() >= 1);
$res = $stu->api('payment.mine', []);
t('siswa melihat tagihan sendiri', !empty($res['ok']) && count($res['data']['invoices']) === 1 && ($res['data']['invoices'][0]['remaining'] ?? 0) == 1000000);
t('payment.mine tidak mengembalikan snapshot (biodata/pembayaran di luar snapshot)', !isset($res['snapshot']));

// -- Login via email & telepon
$res = (new Client($base))->api('auth.login', ['email' => 'aktivasi1@example.com', 'password' => 'Abcdef1!']);
t('login dengan email berhasil', !empty($res['ok']));
foreach (['081255500001', '+6281255500001', '6281255500001', '0812-5550-0001'] as $fmt) {
    $res = (new Client($base))->api('auth.login', ['email' => $fmt, 'password' => 'Abcdef1!']);
    t("login dengan telepon '$fmt' berhasil", !empty($res['ok']), $res['message'] ?? '');
}
$g1 = (new Client($base))->api('auth.login', ['email' => 'aktivasi1@example.com', 'password' => 'SalahSekali1!']);
$g2 = (new Client($base))->api('auth.login', ['email' => 'tidak.ada@example.com', 'password' => 'SalahSekali1!']);
$g3 = (new Client($base))->api('auth.login', ['email' => '089999999999', 'password' => 'SalahSekali1!']);
t('pesan gagal login generik (tidak membocorkan akun)', ($g1['message'] ?? 'a') === ($g2['message'] ?? 'b') && ($g2['message'] ?? 'a') === ($g3['message'] ?? 'b'), json_encode([$g1['message'] ?? '', $g2['message'] ?? '']));

// -- Pendaftar baru dengan email aktif yang sama (siswa lama): tanpa akun baru
$res = $anon->api('course.register', $reg('aktivasi1@example.com', '081255500001'));
t('siswa aktif tidak bisa daftar program yang sama lagi', empty($res['ok']));

// -- Telepon ganda & kuota
$res = $anon->api('course.register', $reg('aktivasi2@example.com', '081255500001', 'Orang Lain'));
t('pendaftaran dengan telepon yang sama diterima (belum jadi akun)', !empty($res['ok']), $res['message'] ?? '');
$reg2 = (int) $pdo->query("SELECT id FROM course_registrations WHERE email = 'aktivasi2@example.com'")->fetchColumn();
$res = $adm->api('registration.sendActivation', ['id' => $reg2]);
t('link ditolak bila nomor sudah dipakai akun lain', empty($res['ok']), $res['message'] ?? '');
$pdo->prepare("UPDATE course_registrations SET phone = '081255500002', phone_norm = '6281255500002' WHERE id = ?")->execute([$reg2]);
$res = $adm->api('registration.sendActivation', ['id' => $reg2]);
t('link dibuat setelah nomor diperbaiki', !empty($res['ok']), $res['message'] ?? '');
$token2 = preg_match('/token=([a-f0-9]{64})$/', $res['data']['link'] ?? '', $m) ? $m[1] : '';
// Kuota 2: siswa 1 (terdaftar) + siswa 2 (menunggu) = penuh -> pendaftar ketiga tidak bisa mendapat link.
$res = $anon->api('course.register', $reg('aktivasi3@example.com', '081255500003', 'Orang Ketiga'));
t('kelas penuh: pendaftaran baru ditolak', empty($res['ok']));
$pdo->exec("UPDATE classes SET quota = 3 WHERE id = $actClass");
$res = $anon->api('course.register', $reg('aktivasi3@example.com', '081255500003', 'Orang Ketiga'));
$reg3 = (int) $pdo->query("SELECT id FROM course_registrations WHERE email = 'aktivasi3@example.com'")->fetchColumn();
$pdo->exec("UPDATE classes SET quota = 2 WHERE id = $actClass");
$res = $adm->api('registration.sendActivation', ['id' => $reg3]);
t('kuota dicek ulang saat link dikirim (kelas penuh)', empty($res['ok']) && strpos($res['message'] ?? '', 'penuh') !== false, $res['message'] ?? '');

// -- Kirim ulang membatalkan link lama; link kedaluwarsa & dibatalkan
$res = $adm->api('registration.sendActivation', ['id' => $reg2]);
$token2b = preg_match('/token=([a-f0-9]{64})$/', $res['data']['link'] ?? '', $m) ? $m[1] : '';
t('kirim ulang membuat token baru', $token2b !== '' && $token2b !== $token2);
t('link lama tidak berlaku setelah kirim ulang', empty($anon->api('activation.info', ['token' => $token2])['ok']));
t('link baru berlaku', !empty($anon->api('activation.info', ['token' => $token2b])['ok']));
t('kirim ulang tidak menambah pemakaian kursi', seats_taken($pdo, $actClass) === 2);
$pdo->prepare("UPDATE account_tokens SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE token_hash = ?")->execute([hash('sha256', $token2b)]);
$res = $anon->api('activation.info', ['token' => $token2b]);
t('link kedaluwarsa (7 hari) ditolak', empty($res['ok']) && strpos($res['message'] ?? '', 'kedaluwarsa') !== false, $res['message'] ?? '');
t('link kedaluwarsa tidak bisa dipakai menyelesaikan aktivasi', empty((new Client($base))->api('activation.complete', ['token' => $token2b, 'profile' => $profile, 'password' => 'Abcdef1!', 'passwordConfirm' => 'Abcdef1!'])['ok']));
$res = $adm->api('registration.sendActivation', ['id' => $reg2]);
$token2c = preg_match('/token=([a-f0-9]{64})$/', $res['data']['link'] ?? '', $m) ? $m[1] : '';
t('admin dapat kirim ulang setelah kedaluwarsa', $token2c !== '');
$res = $adm->api('registration.cancel', ['id' => $reg2, 'reason' => '']);
t('batal tanpa alasan ditolak', empty($res['ok']));
$res = $adm->api('registration.cancel', ['id' => $reg2, 'reason' => 'Tidak jadi ikut']);
t('admin membatalkan pendaftaran', !empty($res['ok']), $res['message'] ?? '');
t('link dicabut saat pendaftaran dibatalkan', empty($anon->api('activation.info', ['token' => $token2c])['ok']));
t('kursi lepas setelah pembatalan', seats_taken($pdo, $actClass) === 1);
t('batal ditolak untuk pendaftaran yang sudah aktif', empty($adm->api('registration.cancel', ['id' => $regId, 'reason' => 'x'])['ok']));

// -- Siswa lama aktif mendaftar program lain: tanpa akun baru
$pdo->prepare("INSERT INTO classes (code, name, course_id, curriculum_version_id, period_start, period_end, quota, status, notes)
               VALUES ('UJI-ACT-02', 'Kelas Uji Program Lain', 2, ?, '2027-03-01', '2027-05-30', 0, 'open', '')")->execute([$version]);
$class2 = (int) $pdo->lastInsertId();
$pdo->exec("UPDATE courses SET status = 'published', price = 500000, discount_price = 0 WHERE id = 2");
$res = $anon->api('course.register', ['courseId' => 2, 'classId' => $class2, 'name' => 'Calon Aktivasi', 'birthPlace' => 'Malang', 'birthDate' => '2000-05-06',
    'address' => 'Jl. Aktivasi 1', 'phone' => '081255500001', 'email' => 'aktivasi1@example.com']);
t('siswa lama dapat mendaftar program lain', !empty($res['ok']), $res['message'] ?? '');
$regOld = (int) $pdo->query("SELECT id FROM course_registrations WHERE email = 'aktivasi1@example.com' AND course_id = 2")->fetchColumn();
$usersBefore = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$res = $adm->api('registration.sendActivation', ['id' => $regOld]);
t('siswa lama: mode existing tanpa link', !empty($res['ok']) && ($res['data']['mode'] ?? '') === 'existing' && ($res['data']['link'] ?? 'x') === '', $res['message'] ?? '');
t('siswa lama: tidak ada akun baru', (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === $usersBefore);
t('siswa lama: tagihan program kedua dibuat', (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE student_id = $studentId")->fetchColumn() === 2);
$invA = (int) $inv['id'];
$invB = (int) $pdo->query("SELECT id FROM invoices WHERE student_id = $studentId AND course_id = 2")->fetchColumn();

// -- Pembayaran: unggah bukti
$png = 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n" . str_repeat('x', 200));
$pay = ['invoiceId' => $invA, 'amount' => 400000, 'method' => 'transfer', 'paidDate' => gmdate('Y-m-d'), 'note' => 'tes', 'proof' => ['data' => $png]];
t('unggah bukti ditolak anonim', empty($anon->api('payment.submit', $pay)['ok']));
t('unggah bukti ditolak untuk tagihan milik siswa lain', empty($clients['peserta']->api('payment.submit', $pay)['ok']));
$stu = $mk('aktivasi1@example.com', 'Abcdef1!');
$r0 = $stu->api('payment.submit', array_merge($pay, ['amount' => 0]));
t('unggah bukti: nominal 0 ditolak', empty($r0['ok']));
$r0 = $stu->api('payment.submit', array_merge($pay, ['amount' => 2000000]));
t('unggah bukti: nominal melebihi sisa ditolak', empty($r0['ok']));
$r0 = $stu->api('payment.submit', array_merge($pay, ['proof' => ['data' => 'data:image/png;base64,' . base64_encode('bukan gambar')]]));
t('unggah bukti: isi berkas bukan gambar/PDF ditolak', empty($r0['ok']));
$r0 = $stu->api('payment.submit', array_merge($pay, ['proof' => ['data' => 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n" . str_repeat('x', 2200000))]]));
t('unggah bukti: berkas >2 MB ditolak', empty($r0['ok']));
$r0 = $stu->api('payment.submit', array_merge($pay, ['proof' => []]));
t('unggah bukti: tanpa berkas ditolak', empty($r0['ok']));
$res = $stu->api('payment.submit', $pay);
t('siswa mengunggah bukti (pending)', !empty($res['ok']), $res['message'] ?? '');
$payId = (int) ($res['data']['id'] ?? 0);
t('pembayaran tersimpan berstatus pending & belum mengurangi tagihan',
    $pdo->query("SELECT status FROM payments WHERE id = $payId")->fetchColumn() === 'pending'
    && $pdo->query("SELECT status FROM invoices WHERE id = $invA")->fetchColumn() === 'unpaid');
$proofFile = (string) $pdo->query("SELECT proof_file FROM payments WHERE id = $payId")->fetchColumn();
t('bukti disimpan di storage dengan nama acak', preg_match('/^[a-f0-9]{32}\.png$/', $proofFile) === 1 && is_file($root . '/storage/payment-proofs/' . $proofFile));
t('folder bukti tidak bisa diakses lewat web', is_file($root . '/storage/payment-proofs/.htaccess'));

// Akses bukti
t('bukti dapat dibuka pemilik', $stu->get('/api/index.php?action=payment.proof&id=' . $payId)['code'] === 200);
t('bukti dapat dibuka admin', $adm->get('/api/index.php?action=payment.proof&id=' . $payId)['code'] === 200);
t('bukti dapat dibuka karyawan keuangan', $keuC->get('/api/index.php?action=payment.proof&id=' . $payId)['code'] === 200);
t('bukti ditolak untuk anonim', $anon->get('/api/index.php?action=payment.proof&id=' . $payId)['code'] === 403);
t('bukti ditolak untuk siswa lain', $mk('ani@example.com')->get('/api/index.php?action=payment.proof&id=' . $payId)['code'] === 403);
t('bukti ditolak untuk karyawan non-keuangan', $mk('dewi.karyawan@example.com')->get('/api/index.php?action=payment.proof&id=' . $payId)['code'] === 403);

// Akses halaman/aksi Keuangan
t('daftar tagihan ditolak peserta', empty($stu->api('payment.invoices', [])['ok']));
t('daftar tagihan ditolak karyawan marketing', empty($mk('dewi.karyawan@example.com')->api('payment.invoices', [])['ok']));
t('verifikasi ditolak peserta', empty($stu->api('payment.verify', ['paymentId' => $payId, 'decision' => 'approve'])['ok']));
t('lunas ditolak peserta', empty($stu->api('payment.markPaid', ['invoiceId' => $invA])['ok']));
t('pengaturan rekening ditolak karyawan keuangan (khusus admin)', empty($keuC->api('payment.settings.save', ['bankName' => 'X'])['ok']));
t('halaman pembayaran admin terbuka', $adm->get('/admin/payments.php')['code'] === 200);
t('halaman pembayaran Keuangan terbuka untuk karyawan keuangan', $keuC->get('/employee/keuangan/payments.php')['code'] === 200);
t('halaman pembayaran Keuangan ditolak untuk peserta', in_array($stu->get('/employee/keuangan/payments.php')['code'], [302, 403], true));
t('halaman pembayaran admin ditolak untuk karyawan', in_array($keuC->get('/admin/payments.php')['code'], [302, 403], true));
$res = $keuC->api('payment.invoices', ['status' => 'pending']);
t('keuangan melihat tagihan dengan bukti menunggu', !empty($res['ok']) && count($res['data']) >= 1 && !isset($res['snapshot']));
$res = $adm->api('payment.settings.save', ['bankName' => 'Bank Uji', 'accountNumber' => '1234567890', 'accountHolder' => 'ELIPS']);
t('admin menyimpan rekening', !empty($res['ok']));
$res = $stu->api('payment.mine', []);
t('siswa melihat rekening pembayaran', ($res['data']['bank']['bankName'] ?? '') === 'Bank Uji');
$res = $adm->api('payment.verify', ['paymentId' => $payId, 'decision' => 'reject', 'reason' => '']);
t('tolak tanpa alasan ditolak', empty($res['ok']));

// Verifikasi dengan nominal disesuaikan (sebagian)
$res = $keuC->api('payment.verify', ['paymentId' => $payId, 'decision' => 'approve', 'amount' => 300000]);
t('keuangan menyetujui dengan nominal disesuaikan', !empty($res['ok']), $res['message'] ?? '');
t('tagihan menjadi sebagian & sisa dihitung dari pembayaran terverifikasi',
    ($res['data']['status'] ?? '') === 'partial' && (float) ($res['data']['remaining'] ?? 0) === 700000.0, json_encode($res['data'] ?? []));
t('belum ada nomor siswa saat belum lunas', ($res['data']['studentNumber'] ?? 'x') === '');
t('bukti yang sudah diproses tidak bisa diproses lagi', empty($adm->api('payment.verify', ['paymentId' => $payId, 'decision' => 'approve'])['ok']));
t('audit verifikasi tercatat', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity = 'payment' AND action = 'approve'")->fetchColumn() >= 1);

// Bukti kedua ditolak
$res = $stu->api('payment.submit', array_merge($pay, ['amount' => 100000]));
$payId2 = (int) ($res['data']['id'] ?? 0);
$res = $adm->api('payment.verify', ['paymentId' => $payId2, 'decision' => 'reject', 'reason' => 'Bukti buram']);
t('admin menolak bukti dengan alasan', !empty($res['ok']));
t('penolakan tidak mengubah tagihan', (float) $pdo->query("SELECT SUM(amount) FROM payments WHERE invoice_id = $invA AND status = 'verified'")->fetchColumn() === 300000.0);
$res = $stu->api('payment.mine', []);
$hist = [];
foreach ($res['data']['invoices'] ?? [] as $iv) { if ((int) $iv['id'] === $invA) { $hist = $iv['payments']; } }
t('riwayat pembayaran siswa memuat alasan penolakan', in_array('Bukti buram', array_column($hist, 'rejectReason'), true) && count($hist) >= 2);

// Catat langsung & batas nominal
$res = $adm->api('payment.record', ['invoiceId' => $invA, 'amount' => 800000, 'method' => 'tunai']);
t('catat langsung: melebihi sisa ditolak', empty($res['ok']));
$res = $adm->api('payment.record', ['invoiceId' => $invA, 'amount' => 200000, 'method' => 'tunai', 'note' => 'Bayar di kantor']);
t('admin mencatat pembayaran tunai sebagian', !empty($res['ok']) && (float) ($res['data']['remaining'] ?? 0) === 500000.0, $res['message'] ?? '');
t('masih belum lunas: tidak ada nomor siswa', ($res['data']['studentNumber'] ?? 'x') === '' && ($res['data']['status'] ?? '') === 'partial');

// Sertifikat diblokir selama belum lunas (server)
$pdo->prepare("INSERT INTO certificates (student_id, course_id, enrollment_id, certificate_number, status, created_at) VALUES (?, 1, NULL, 'DRAFT-UJI-1', 'draft', UTC_TIMESTAMP())")->execute([$studentId]);
$certId = (int) $pdo->lastInsertId();
$res = $adm->api('certificate.approve', ['id' => $certId]);
t('sertifikat tidak bisa diterbitkan selama belum lunas', empty($res['ok']) && strpos($res['message'] ?? '', 'belum lunas') !== false, $res['message'] ?? '');
t('status sertifikat tetap draft', $pdo->query("SELECT status FROM certificates WHERE id = $certId")->fetchColumn() === 'draft');
$res = $stu->api('payment.mine', []);
t('siswa: program belum lunas ditandai untuk kunci sertifikat', in_array(1, $res['data']['certificateLockedCourseIds'] ?? [], true));

// Lunas Pembayaran -> nomor siswa
$res = $keuC->api('payment.markPaid', ['invoiceId' => $invA, 'method' => 'transfer']);
t('Lunas Pembayaran mencatat sisa tagihan', !empty($res['ok']) && ($res['data']['status'] ?? '') === 'paid' && (float) ($res['data']['amountPaid'] ?? 0) === 1000000.0, $res['message'] ?? '');
$numA = (string) ($res['data']['studentNumber'] ?? '');
t('nomor siswa berformat EC-xxxxxx (6 digit)', preg_match('/^EC-\d{6}$/', $numA) === 1, $numA);
$dup = $adm->api('payment.markPaid', ['invoiceId' => $invA]);
t('Lunas Pembayaran dua kali ditolak (tidak membuat nomor baru)', empty($dup['ok']));
$res = $stu->api('payment.mine', []);
t('siswa melihat nomor siswa & status lunas', in_array($numA, array_column($res['data']['invoices'] ?? [], 'studentNumber'), true));
t('program lunas tidak lagi mengunci sertifikat', !in_array(1, $res['data']['certificateLockedCourseIds'] ?? [], true));
$res = $adm->api('certificate.approve', ['id' => $certId]);
t('sertifikat dapat diterbitkan setelah lunas', !empty($res['ok']), $res['message'] ?? '');

// Nomor berikutnya berurutan & dimiliki program (tagihan kedua)
$res = $adm->api('payment.markPaid', ['invoiceId' => $invB]);
$numB = (string) ($res['data']['studentNumber'] ?? '');
t('program kedua siswa yang sama mendapat nomor BERBEDA', preg_match('/^EC-\d{6}$/', $numB) === 1 && $numB !== $numA, "$numA / $numB");
t('nomor berurutan (selisih 1)', (int) substr($numB, 3) === (int) substr($numA, 3) + 1, "$numA / $numB");
t('nomor unik per tagihan', (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE student_number IS NOT NULL")->fetchColumn()
    === (int) $pdo->query("SELECT COUNT(DISTINCT student_number) FROM invoices WHERE student_number IS NOT NULL")->fetchColumn());

// Void: pembayaran tidak dihapus, nomor tidak dipakai ulang
$verifiedId = (int) $pdo->query("SELECT id FROM payments WHERE invoice_id = $invA AND status = 'verified' ORDER BY id DESC LIMIT 1")->fetchColumn();
t('void tanpa alasan ditolak', empty($adm->api('payment.void', ['paymentId' => $verifiedId, 'reason' => ''])['ok']));
t('void ditolak untuk peserta', empty($stu->api('payment.void', ['paymentId' => $verifiedId, 'reason' => 'x'])['ok']));
$res = $adm->api('payment.void', ['paymentId' => $verifiedId, 'reason' => 'Salah input nominal']);
t('admin membatalkan pembayaran dengan alasan', !empty($res['ok']), $res['message'] ?? '');
t('status tagihan dihitung ulang setelah void', ($res['data']['status'] ?? '') === 'partial');
t('pembayaran yang di-void tetap ada (tidak dihapus)', $pdo->query("SELECT status FROM payments WHERE id = $verifiedId")->fetchColumn() === 'void'
    && $pdo->query("SELECT void_reason FROM payments WHERE id = $verifiedId")->fetchColumn() === 'Salah input nominal');
t('nomor siswa tetap melekat (tidak dipakai ulang)', $pdo->query("SELECT student_number FROM invoices WHERE id = $invA")->fetchColumn() === $numA);
t('audit void tercatat', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity = 'payment' AND action = 'void'")->fetchColumn() >= 1);
$res = $adm->api('payment.markPaid', ['invoiceId' => $invA]);
t('lunas lagi setelah void tidak membuat nomor baru', ($res['data']['studentNumber'] ?? '') === $numA);
t('penghitung nomor sama dengan nomor terbesar', (int) $pdo->query("SELECT value FROM counters WHERE name = 'student_number'")->fetchColumn()
    === (int) $pdo->query("SELECT MAX(number_seq) FROM invoices")->fetchColumn());

// Program gratis -> langsung lunas & bernomor
$pdo->exec("UPDATE courses SET price = 0, discount_price = 0 WHERE id = 1");
$freeEmail = 'gratis1@example.com';
$pdo->prepare("INSERT INTO classes (code, name, course_id, curriculum_version_id, period_start, period_end, quota, status, notes)
               VALUES ('UJI-ACT-03', 'Kelas Uji Gratis', 1, ?, '2027-06-01', '2027-08-30', 0, 'open', '')")->execute([$version]);
$class3 = (int) $pdo->lastInsertId();
$res = $anon->api('course.register', ['courseId' => 1, 'classId' => $class3, 'name' => 'Peserta Gratis', 'birthPlace' => 'Solo', 'birthDate' => '1999-01-02',
    'address' => 'Jl. Gratis 3', 'phone' => '081255500009', 'email' => $freeEmail]);
$regFree = (int) $pdo->query("SELECT id FROM course_registrations WHERE email = '$freeEmail'")->fetchColumn();
$res = $adm->api('registration.sendActivation', ['id' => $regFree]);
$tokF = preg_match('/token=([a-f0-9]{64})$/', $res['data']['link'] ?? '', $m) ? $m[1] : '';
$pf = $profile; $pf['fullName'] = 'Peserta Gratis'; $pf['phone'] = '081255500009';
$res = (new Client($base))->api('activation.complete', ['token' => $tokF, 'profile' => $pf, 'password' => 'Gratis#2026', 'passwordConfirm' => 'Gratis#2026']);
t('aktivasi program gratis berhasil', !empty($res['ok']), $res['message'] ?? '');
$freeInv = $pdo->query("SELECT * FROM invoices WHERE student_id = " . (int) ($res['data']['user']['id'] ?? 0))->fetch();
t('program gratis: tagihan langsung lunas & bernomor', ($freeInv['status'] ?? '') === 'paid' && preg_match('/^EC-\d{6}$/', (string) ($freeInv['student_number'] ?? '')) === 1);
$pdo->exec("UPDATE courses SET price = 1000000, discount_price = 0 WHERE id = 1");

// Hapus pengguna yang punya tagihan ditolak
$res = $adm->api('user.delete', ['id' => $studentId]);
t('pengguna dengan tagihan tidak bisa dihapus', empty($res['ok']) && (int) $pdo->query("SELECT COUNT(*) FROM users WHERE id = $studentId")->fetchColumn() === 1, $res['message'] ?? '');

// Badge kuning
$badge = json_decode($adm->req('GET', '/api/index.php?action=badges')['body'], true);
$expectUnpaid = (int) $pdo->query("SELECT COUNT(*) FROM invoices i JOIN users u ON u.id = i.student_id WHERE i.status <> 'paid' AND u.status = 'aktif'")->fetchColumn();
t('badge kuning = siswa aktif belum lunas', ($badge['data']['unpaidInvoices'] ?? -1) === $expectUnpaid, json_encode($badge));
$html = $adm->get('/admin/users.php')['body'];
t('sidebar admin memuat menu Pembayaran dengan badge kuning', strpos($html, 'nav-badge-yellow') !== false && strpos($html, 'admin/payments.php') !== false);

// -- Lupa sandi & reset
$forgotBefore = (int) $pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn();
$res1 = (new Client($base))->api('password.forgot', ['identifier' => 'tidak.ada@example.com']);
$res2 = (new Client($base))->api('password.forgot', ['identifier' => 'aktivasi1@example.com']);
t('lupa sandi: respons sama untuk akun ada/tidak ada', !empty($res1['ok']) && ($res1['message'] ?? '') === ($res2['message'] ?? 'x'));
t('lupa sandi: akun tak dikenal tidak membuat permintaan', (int) $pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn() === $forgotBefore + 1);
(new Client($base))->api('password.forgot', ['identifier' => '081255500001']);
t('lupa sandi: permintaan ganda digabung (satu aktif per akun)',
    (int) $pdo->query("SELECT COUNT(*) FROM password_reset_requests WHERE user_id = $studentId AND status IN ('pending','sent')")->fetchColumn() === 1);
t('lupa sandi: akun admin tidak membuat permintaan', (function () use ($base, $pdo) {
    (new Client($base))->api('password.forgot', ['identifier' => 'admin@example.com']);
    return (int) $pdo->query("SELECT COUNT(*) FROM password_reset_requests WHERE user_id = 1")->fetchColumn() === 0;
})());
t('daftar permintaan ditolak untuk non-admin', empty($stu->api('password.requests', [])['ok']) && empty($keuC->api('password.requests', [])['ok']) && empty($anon->api('password.requests', [])['ok']));
$res = $adm->api('password.requests', []);
$reqRow = null;
foreach ($res['data'] ?? [] as $r) { if ((int) $r['userId'] === $studentId) { $reqRow = $r; } }
t('admin melihat permintaan reset hanya untuk siswa yang menekan Lupa sandi', $reqRow !== null && count($res['data']) === 1 && !isset($res['snapshot']), json_encode($res['data'] ?? []));
t('terbitkan link reset ditolak untuk non-admin', empty($stu->api('password.reset.issue', ['requestId' => (int) $reqRow['id']])['ok']));
$res = $adm->api('password.reset.issue', ['requestId' => (int) $reqRow['id']]);
t('admin menerbitkan link reset', !empty($res['ok']) && strpos($res['data']['link'] ?? '', '/reset-password.php?token=') !== false && strpos($res['data']['waUrl'] ?? '', 'https://wa.me/6281255500001') === 0);
$resetTok = preg_match('/token=([a-f0-9]{64})$/', $res['data']['link'] ?? '', $m) ? $m[1] : '';
t('halaman reset-password.php terbuka', (new Client($base))->get('/reset-password.php?token=' . $resetTok)['code'] === 200);
t('token reset tidak bisa dipakai di endpoint aktivasi', empty($anon->api('activation.info', ['token' => $resetTok])['ok']));
t('reset: sandi lemah ditolak', empty($anon->api('password.reset.complete', ['token' => $resetTok, 'password' => 'lemah', 'passwordConfirm' => 'lemah'])['ok']));
t('reset: konfirmasi beda ditolak', empty($anon->api('password.reset.complete', ['token' => $resetTok, 'password' => 'Baru#Sandi1', 'passwordConfirm' => 'Baru#Sandi2'])['ok']));
$oldSession = $mk('aktivasi1@example.com', 'Abcdef1!');
$res = $anon->api('password.reset.complete', ['token' => $resetTok, 'password' => 'Baru#Sandi1', 'passwordConfirm' => 'Baru#Sandi1']);
t('reset sandi berhasil', !empty($res['ok']), $res['message'] ?? '');
t('sandi lama tidak berlaku lagi', empty((new Client($base))->api('auth.login', ['email' => 'aktivasi1@example.com', 'password' => 'Abcdef1!'])['ok']));
t('sandi baru berlaku (email & telepon)', !empty((new Client($base))->api('auth.login', ['email' => 'aktivasi1@example.com', 'password' => 'Baru#Sandi1'])['ok'])
    && !empty((new Client($base))->api('auth.login', ['email' => '081255500001', 'password' => 'Baru#Sandi1'])['ok']));
t('sesi lama dicabut setelah reset', empty($oldSession->api('payment.mine', [])['ok']));
t('link reset sekali pakai', empty($anon->api('password.reset.complete', ['token' => $resetTok, 'password' => 'Lain#Sandi99', 'passwordConfirm' => 'Lain#Sandi99'])['ok']));
t('permintaan reset ditandai selesai (tombol hilang)', (int) $pdo->query("SELECT COUNT(*) FROM password_reset_requests WHERE user_id = $studentId AND status IN ('pending','sent')")->fetchColumn() === 0);
$res = $adm->api('password.requests', []);
t('admin tidak lagi melihat tombol reset untuk siswa itu', count(array_filter($res['data'] ?? [], fn($r) => (int) $r['userId'] === $studentId)) === 0);
$pdo->prepare("INSERT INTO password_reset_requests (user_id, status, requested_ip) VALUES (?, 'pending', '127.0.0.1')")->execute([$studentId]);
$reqId2 = (int) $pdo->lastInsertId();
$res = $adm->api('password.reset.issue', ['requestId' => $reqId2]);
$resetTok2 = preg_match('/token=([a-f0-9]{64})$/', $res['data']['link'] ?? '', $m) ? $m[1] : '';
$pdo->prepare("UPDATE account_tokens SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE token_hash = ?")->execute([hash('sha256', $resetTok2)]);
t('link reset kedaluwarsa (24 jam) ditolak', empty($anon->api('password.reset.info', ['token' => $resetTok2])['ok']));
$res = $adm->api('password.request.dismiss', ['requestId' => $reqId2]);
t('admin dapat menutup permintaan reset', !empty($res['ok']));

// -- Pendaftaran langsung ditutup
$res = $anon->api('user.create', ['name' => 'Pembuat Liar', 'email' => 'liar@example.com', 'password' => 'Abcdef1!', 'role' => 'peserta', 'status' => 'aktif']);
t('user.create anonim ditutup', empty($res['ok']) && (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'liar@example.com'")->fetchColumn() === 0);
$res = $clients['peserta']->api('user.create', ['name' => 'X', 'email' => 'liar2@example.com', 'password' => 'Abcdef1!', 'role' => 'peserta']);
t('user.create oleh peserta ditolak', empty($res['ok']));
$r = $anon->get('/register.php');
t('register.php dialihkan ke katalog program', $r['code'] === 302 && strpos($r['loc'], 'index.php') !== false, $r['loc']);

// -- Kelola pengguna: akun menunggu & telepon
$res = $adm->api('user.create', ['name' => 'Dup Telp', 'email' => 'duptelp@example.com', 'password' => 'Abcdef12', 'role' => 'peserta', 'phone' => '081255500001']);
t('user.create menolak nomor telepon yang sudah dipakai', empty($res['ok']) && strpos($res['message'] ?? '', 'Nomor telepon') !== false, $res['message'] ?? '');
$res = $adm->api('user.create', ['name' => 'Telp Baru', 'email' => 'telpbaru@example.com', 'password' => 'Abcdef12', 'role' => 'peserta', 'phone' => '081255500077']);
t('user.create menyimpan phone_norm', !empty($res['ok']) && $pdo->query("SELECT phone_norm FROM users WHERE email = 'telpbaru@example.com'")->fetchColumn() === '6281255500077');
$tid = (int) ($res['data']['id'] ?? 0);
$res = $adm->api('user.update', ['id' => $tid, 'data' => ['phone' => '081255500001']]);
t('user.update menolak nomor telepon milik akun lain', empty($res['ok']));
$res = $adm->api('user.update', ['id' => $tid, 'data' => ['phone' => '081255500078']]);
t('user.update menyinkronkan phone_norm', !empty($res['ok']) && $pdo->query("SELECT phone_norm FROM users WHERE id = $tid")->fetchColumn() === '6281255500078');
$pdo->prepare("INSERT INTO users (name, email, phone, phone_norm, password_hash, role, status, created_at) VALUES ('Tunggu', 'tunggu2@example.com', '', NULL, 'x', 'peserta', 'menunggu', CURDATE())")->execute();
$wid = (int) $pdo->lastInsertId();
$res = $adm->api('user.update', ['id' => $wid, 'data' => ['status' => 'aktif']]);
t("akun 'menunggu' tidak bisa diaktifkan manual lewat edit pengguna", empty($res['ok']));
$res = $adm->api('user.update', ['id' => $wid, 'data' => ['name' => 'Tunggu Ganti']]);
t("akun 'menunggu' tetap bisa diedit datanya (status tidak berubah)", !empty($res['ok']) && $pdo->query("SELECT status FROM users WHERE id = $wid")->fetchColumn() === 'menunggu', $res['message'] ?? '');
$res = $adm->api('user.delete', ['id' => $wid]);
t("akun 'menunggu' tanpa tagihan bisa dihapus", !empty($res['ok']));
$adm->api('user.delete', ['id' => $tid]);

// -- Halaman publik & demo
$login = $anon->get('/login.php')['body'];
t('login.php memuat tautan Lupa sandi', strpos($login, 'forgot-password.php') !== false);
t('login.php menerima email atau telepon', strpos($login, 'Email atau No. telepon') !== false);
t('login.php menampilkan akun demo di mode development', strpos($login, 'data-demo-email') !== false);
$act = $anon->get('/activate.php')['body'];
t('activate.php memuat langkah biodata & sandi', strpos($act, 'actBioForm') !== false && strpos($act, 'actPwForm') !== false);


// ============================================================
// Kelola kursus: admin + karyawan Operasional
// ============================================================
echo "\n[Kelola kursus]\n";
$ops = new Client($base);
$ops->api('auth.login', ['email' => 'agus.karyawan@example.com', 'password' => 'elips123']);
$instrId = (int) $pdo->query("SELECT id FROM users WHERE role = 'instruktur' AND status = 'aktif' ORDER BY id LIMIT 1")->fetchColumn();
$crs = ['title' => 'Uji Kursus Ops', 'category' => 'Pelatihan', 'description' => 'x', 'instructorId' => $instrId,
        'price' => 1500000, 'discountPrice' => 1200000, 'location' => 'Online', 'startDate' => '2026-12-15', 'meetings' => 8];

$res = $keu->api('course.create', $crs);
t('karyawan Keuangan tidak boleh membuat kursus', empty($res['ok']));
$res = $mkt->api('course.create', $crs);
t('karyawan Marketing tidak boleh membuat kursus', empty($res['ok']));
$res = $clients['instruktur']->api('course.create', $crs);
t('instruktur tidak boleh membuat kursus', empty($res['ok']));

$res = $ops->api('course.create', $crs);
t('karyawan Operasional boleh membuat kursus', !empty($res['ok']), $res['message'] ?? '');
$cid = (int) ($res['data']['id'] ?? 0);
t('kursus baru menyimpan harga, diskon, lokasi, pertemuan', ($res['data']['price'] ?? 0) == 1500000 && ($res['data']['discountPrice'] ?? 0) == 1200000
    && ($res['data']['location'] ?? '') === 'Online' && ($res['data']['meetings'] ?? 0) === 8 && ($res['data']['startDate'] ?? '') === '2026-12-15');
t('kursus baru berstatus draft', ($res['data']['status'] ?? '') === 'draft');

$res = $ops->api('course.create', array_merge($crs, ['title' => 'Uji Harga Negatif', 'price' => -5]));
t('harga negatif ditolak', empty($res['ok']));
$res = $ops->api('course.create', array_merge($crs, ['title' => 'Uji Diskon Besar', 'discountPrice' => 2000000]));
t('diskon >= harga ditolak', empty($res['ok']));
$res = $ops->api('course.create', array_merge($crs, ['title' => 'Uji Tanggal', 'startDate' => 'bukan-tanggal']));
t('tanggal mulai tidak valid ditolak', empty($res['ok']));

$res = $ops->api('course.update', ['id' => $cid, 'data' => ['price' => 1800000, 'location' => 'Bandung']]);
t('operasional boleh mengubah harga kursus', !empty($res['ok']) && ($res['data']['price'] ?? 0) == 1800000 && ($res['data']['location'] ?? '') === 'Bandung', $res['message'] ?? '');
t('ubah sebagian tidak menghapus field lain', ($res['data']['meetings'] ?? 0) === 8 && ($res['data']['discountPrice'] ?? 0) == 1200000);

// Publikasi: butuh harga & materi
$free = $ops->api('course.create', ['title' => 'Uji Tanpa Harga', 'category' => 'Pelatihan', 'instructorId' => $instrId]);
$freeId = (int) ($free['data']['id'] ?? 0);
$res = $ops->api('course.status', ['id' => $freeId, 'status' => 'published']);
t('publikasi tanpa harga ditolak', empty($res['ok']) && stripos($res['message'] ?? '', 'harga') !== false, $res['message'] ?? '');
$res = $ops->api('course.status', ['id' => $cid, 'status' => 'published']);
t('publikasi tanpa modul & materi ditolak', empty($res['ok']), $res['message'] ?? '');

// Modul + materi oleh operasional
$res = $ops->api('module.create', ['courseId' => $cid, 'data' => ['title' => 'Modul Uji']]);
t('operasional boleh menambah modul', !empty($res['ok']), $res['message'] ?? '');
$mid = (int) ($res['data']['id'] ?? 0);
$res = $ops->api('lesson.create', ['courseId' => $cid, 'moduleId' => $mid, 'data' => ['title' => 'Materi Uji', 'type' => 'text', 'content' => 'isi']]);
t('operasional boleh menambah materi', !empty($res['ok']), $res['message'] ?? '');
$res = $keu->api('module.create', ['courseId' => $cid, 'data' => ['title' => 'Modul Terlarang']]);
t('karyawan Keuangan tidak boleh menambah modul', empty($res['ok']));

$res = $ops->api('course.status', ['id' => $cid, 'status' => 'published']);
t('operasional boleh mempublikasikan kursus lengkap', !empty($res['ok']) && ($res['data']['status'] ?? '') === 'published', $res['message'] ?? '');

// Kurikulum & kelas oleh operasional
$verId = (int) ($res['data']['curriculumVersionId'] ?? 0);
$res = $ops->api('class.create', ['courseId' => $cid, 'curriculumVersionId' => $verId, 'name' => 'Angkatan Uji Ops', 'quota' => 3, 'status' => 'open']);
t('operasional boleh membuat kelas', !empty($res['ok']), $res['message'] ?? '');
$clsId = (int) ($res['data']['id'] ?? 0);
$res = $keu->api('class.create', ['courseId' => $cid, 'curriculumVersionId' => $verId, 'name' => 'Kelas Terlarang']);
t('karyawan Keuangan tidak boleh membuat kelas', empty($res['ok']));
$res = $ops->api('curriculum.clone', ['id' => $verId]);
t('operasional boleh menduplikasi kurikulum', !empty($res['ok']), $res['message'] ?? '');

// Kursus pakai: tidak bisa dihapus, hanya diarsipkan
$res = $ops->api('course.delete', ['id' => $cid]);
t('kursus yang punya kelas tidak bisa dihapus', empty($res['ok']));
$res = $ops->api('course.status', ['id' => $cid, 'status' => 'archived']);
t('operasional boleh mengarsipkan kursus', !empty($res['ok']) && ($res['data']['status'] ?? '') === 'archived', $res['message'] ?? '');
$res = $anon->api('course.classes', ['courseId' => $cid]);
t('kursus arsip tidak tampil di pendaftaran publik', empty($res['ok']));
$res = $ops->api('class.create', ['courseId' => $cid, 'curriculumVersionId' => $verId, 'name' => 'Kelas Di Arsip']);
t('kelas baru pada kursus arsip ditolak', empty($res['ok']));
$res = $ops->api('course.status', ['id' => $cid, 'status' => 'draft']);
t('kursus arsip bisa diaktifkan kembali sebagai draft', !empty($res['ok']) && ($res['data']['status'] ?? '') === 'draft');

// Kursus riwayat pendaftaran tidak boleh dihapus walau tanpa kelas
$reg = $ops->api('course.create', ['title' => 'Uji Hapus Bersih', 'category' => 'Pelatihan', 'instructorId' => $instrId, 'price' => 100000]);
$cleanId = (int) ($reg['data']['id'] ?? 0);
$res = $ops->api('course.delete', ['id' => $cleanId]);
t('kursus draft yang belum dipakai bisa dihapus', !empty($res['ok']), $res['message'] ?? '');
$reg = $ops->api('course.create', ['title' => 'Uji Riwayat Daftar', 'category' => 'Pelatihan', 'instructorId' => $instrId, 'price' => 100000]);
$histId = (int) ($reg['data']['id'] ?? 0);
$pdo->exec("INSERT INTO course_registrations (course_id, course_title, name, email, phone, status) VALUES ($histId, 'Uji Riwayat Daftar', 'R', 'riwayat@example.com', '0811', 'baru')");
$res = $ops->api('course.delete', ['id' => $histId]);
t('kursus dengan riwayat pendaftaran tidak bisa dihapus', empty($res['ok']));

// Gambar
$img = make_jpeg(77, 640, 480);
$res = $ops->api('course.image', ['id' => $cid, 'dataUrl' => $img]);
$imgPath = $res['data']['image'] ?? '';
t('operasional boleh mengunggah gambar kursus', !empty($res['ok']) && strpos($imgPath, 'assets/uploads/courses/') === 0, $res['message'] ?? '');
t('berkas gambar tersimpan & bisa dibuka', $imgPath !== '' && is_file($root . '/' . $imgPath) && $anon->get('/' . $imgPath)['code'] === 200);
$res = $ops->api('course.image', ['id' => $cid, 'dataUrl' => 'data:text/html;base64,' . base64_encode('<script>x</script>')]);
t('unggah non-gambar ditolak', empty($res['ok']));
$res = $ops->api('course.image', ['id' => $cid, 'dataUrl' => 'data:image/png;base64,' . base64_encode('bukan png')]);
t('gambar rusak ditolak', empty($res['ok']));
$res = $keu->api('course.image', ['id' => $cid, 'dataUrl' => $img]);
t('karyawan Keuangan tidak boleh mengunggah gambar kursus', empty($res['ok']));
$res = $ops->api('course.image', ['id' => $cid, 'remove' => true]);
clearstatcache();
t('gambar kursus bisa dihapus & berkas ikut terhapus', !empty($res['ok']) && ($res['data']['image'] ?? 'x') === '' && !is_file($root . '/' . $imgPath), json_encode([$res['message'] ?? '', $res['data']['image'] ?? null, is_file($root . '/' . $imgPath)]));

// Halaman
foreach (['employee/operasional/kursus.php', 'employee/operasional/akademik.php'] as $pg) {
    $r = $ops->get('/' . $pg);
    t("halaman $pg terbuka untuk Operasional", $r['code'] === 200 && strpos($r['body'], 'dashboard-main') !== false, (string) $r['code']);
    $r = $keu->get('/' . $pg);
    t("halaman $pg tertutup untuk Keuangan", $r['code'] !== 200);
}
$ra = $adm->get('/admin/courses.php');
t('halaman admin/courses.php terbuka untuk admin', $ra['code'] === 200, $ra['code'] . ' ' . $ra['loc'] . ' ' . substr(strip_tags($ra['body']), 0, 150));
t('halaman admin/courses.php tertutup untuk Operasional', $ops->get('/admin/courses.php')['code'] !== 200);

// Bersihkan
$pdo->exec("DELETE FROM course_registrations WHERE course_id = $histId");
$pdo->exec("DELETE FROM classes WHERE course_id = $cid");
foreach ([$cid, $freeId, $histId] as $d) { if ($d) { $pdo->exec("DELETE FROM courses WHERE id = $d"); } }
foreach (glob($root . '/assets/uploads/courses/*') ?: [] as $f) { if (basename($f) !== '.htaccess') { @unlink($f); } }

// Bersihkan data uji bagian ini
$pdo->exec("DELETE FROM classes WHERE code IN ('UJI-ACT-01','UJI-ACT-02','UJI-ACT-03')");
$pdo->exec("UPDATE courses SET price = 0, discount_price = 0 WHERE id IN (1, 2)");
$pdo->exec("UPDATE courses SET status = 'draft' WHERE id = 2");
foreach (glob($root . '/storage/payment-proofs/*') ?: [] as $f) { if (basename($f) !== '.htaccess') { @unlink($f); } }

proc_terminate($srv);
if (!$hadLock) { @unlink($lock); }
echo "\n----------------------------------------\n";
echo "Lulus: $passed | Gagal: " . count($failed) . "\n";
if ($failed) {
    echo "Gagal:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}
exit(0);